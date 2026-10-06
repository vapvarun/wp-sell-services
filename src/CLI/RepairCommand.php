<?php
/**
 * One-time data repairs, dry-run first.
 *
 * @package WPSellServices\CLI
 * @since   1.8.0
 */

namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_CLI_Command;
use WPSellServices\Integrations\Stripe\StripeGateway;
use WPSellServices\Services\AuditLogService;
use WPSellServices\Services\CommissionService;

/**
 * Repair orders recorded wrong by an earlier release.
 *
 * ## EXAMPLES
 *
 *     # List what would change (no writes)
 *     $ wp wpss repair:stripe-tax
 *
 *     # Write the changes listed by the dry run
 *     $ wp wpss repair:stripe-tax --apply
 *
 * @since 1.8.0
 */
class RepairCommand extends WP_CLI_Command {

	/**
	 * Correct Stripe orders whose tax was counted twice.
	 *
	 * Before 1.8.0, when Stripe's payment_intent.succeeded webhook reached the
	 * site before the buyer's browser, the webhook created the order from the
	 * charged amount - which already includes tax - and added the tax again.
	 * The order total, platform fee and vendor earning came out inflated by the
	 * tax rate.
	 *
	 * Only orders with that exact shape are changed: a single order on the
	 * payment, a stored subtotal equal to what Stripe captured, and a total
	 * above it. Every other mismatch is listed for review and left alone, as
	 * is any order with a refund. Reads each payment from Stripe once.
	 *
	 * An order whose vendor was already credited gets its earning corrected
	 * and one `order_correction` ledger row for the difference; a Stripe
	 * Connect order (vendor paid by Stripe from the real charge) gets its
	 * order row corrected only.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Write the changes. Without it nothing is written.
	 *
	 * [--force]
	 * : With --apply, allow it on a site whose environment type is production.
	 *
	 * [--yes]
	 * : With --apply, skip the confirmation of how many orders will be corrected.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function stripe_tax( $args, $assoc_args ): void {
		global $wpdb;

		$apply   = ! empty( $assoc_args['apply'] );
		$orders  = $wpdb->prefix . 'wpss_orders';
		$gateway = new StripeGateway();
		$fix     = array();
		$review  = array();
		$checked = 0;
		$last_id = 0;

		WP_CLI::log( $apply ? 'Checking orders; changes are written after you confirm.' : 'DRY RUN: nothing will be written. Re-run with --apply to write.' );

		do {
			// Taxed Stripe orders only; keyset batches so a large store is read
			// in bounded pages. ponytail: one Stripe read per taxed Stripe
			// order - fine for a one-time repair, not for a request path.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$batch = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
					"SELECT * FROM {$orders}
					WHERE id > %d AND payment_method = 'stripe' AND transaction_id LIKE %s
					AND total > subtotal + addons_total + 0.001
					AND status NOT IN ( 'refunded', 'partially_refunded', 'cancelled' )
					AND ( payment_status IS NULL OR payment_status <> 'refunded' )
					ORDER BY id ASC LIMIT 200",
					$last_id,
					$wpdb->esc_like( 'pi_' ) . '%'
				)
			);

			foreach ( $batch as $order ) {
				$last_id = (int) $order->id;
				++$checked;

				$currency = (string) $order->currency;
				$decimals = wpss_get_currency_decimals( $currency );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$siblings = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE transaction_id = %s", $order->transaction_id ) );

				if ( $siblings > 1 ) {
					continue; // A cart charge; the webhook path never created carts.
				}

				$payment = $gateway->process_payment( (string) $order->transaction_id );

				if ( empty( $payment['success'] ) ) {
					$review[] = array( $order->id, $order->transaction_id, 'could not read the payment: ' . ( $payment['error'] ?? 'unknown' ) );
					continue;
				}

				$charged = round( (float) $payment['amount'], $decimals );

				if ( strtoupper( (string) $payment['currency'] ) !== strtoupper( $currency ) ) {
					$review[] = array( $order->id, $order->transaction_id, "charged in {$payment['currency']}, order in {$currency}" );
					continue;
				}

				$double_taxed = wpss_amounts_match( (float) $order->subtotal, $charged, $currency ) && (float) $order->total > $charged;

				if ( ! $double_taxed ) {
					if ( ! wpss_amounts_match( (float) $order->total, $charged, $currency ) ) {
						$review[] = array( $order->id, $order->transaction_id, "total {$order->total} but Stripe captured {$charged}" );
					}
					continue;
				}

				if ( (float) $order->refunded_amount > 0 ) {
					$review[] = array( $order->id, $order->transaction_id, 'double-taxed but has a refund; correct by hand' );
					continue;
				}

				// The same webhook bug dropped add-ons and Express. If Stripe's
				// metadata names some and the order has none, re-taxing alone
				// would leave the order short: a person decides.
				$paid_for = (array) ( $payment['metadata'] ?? array() );
				if ( (float) $order->addons_total <= 0 && ( '' !== (string) ( $paid_for['addon_ids'] ?? '' ) || '' !== (string) ( $paid_for['addon_sel'] ?? '' ) ) ) {
					$review[] = array( $order->id, $order->transaction_id, 'Stripe metadata names add-ons or Express the order does not have' );
					continue;
				}

				$fix[] = array( $order, $charged, $decimals );
			}

			$more = 200 === count( $batch );
		} while ( $more );

		// The dry run reads only; --apply goes through the shared write guard
		// (production refusal, count confirmation) before anything is written,
		// even at zero, so the refusal behaves the same on every site.
		if ( $apply ) {
			Guard::writes( 'double-taxed Stripe orders', count( $fix ), $assoc_args );
		}

		$fix = array_map( fn( $f ) => $this->repair_order( $f[0], $f[1], $f[2], $apply ), $fix );

		if ( $fix ) {
			WP_CLI\Utils\format_items( 'table', $fix, array( 'order', 'payment', 'charged', 'subtotal', 'tax', 'total', 'vendor_earning', 'ledger' ) );
		}

		if ( $review ) {
			WP_CLI::warning( count( $review ) . ' order(s) need review and were NOT changed:' );
			WP_CLI\Utils\format_items(
				'table',
				array_map(
					static fn( $r ) => array(
						'order'   => $r[0],
						'payment' => $r[1],
						'reason'  => $r[2],
					),
					$review
				),
				array( 'order', 'payment', 'reason' )
			);
		}

		$verb = $apply ? 'Corrected' : 'Would correct';
		WP_CLI::success( sprintf( '%s %d order(s); checked %d taxed Stripe order(s); %d for review.', $verb, count( $fix ), $checked, count( $review ) ) );
	}

	/**
	 * Correct one double-taxed order (or describe the correction).
	 *
	 * @param object $order    Order row.
	 * @param float  $charged  What Stripe captured.
	 * @param int    $decimals Currency decimals.
	 * @param bool   $apply    Write, or only describe.
	 * @return array<string, string> One row for the report table.
	 */
	private function repair_order( object $order, float $charged, int $decimals, bool $apply ): array {
		global $wpdb;

		$orders = $wpdb->prefix . 'wpss_orders';
		$ledger = $wpdb->prefix . 'wpss_wallet_transactions';
		$meta   = json_decode( (string) $order->meta, true );
		$meta   = is_array( $meta ) ? $meta : array();

		// The rate the order was taxed at; derived from the inflation itself
		// when the order did not store it (total = charged * (1 + rate)).
		$rate     = isset( $meta['tax_rate'] ) && (float) $meta['tax_rate'] > 0 ? (float) $meta['tax_rate'] / 100 : ( (float) $order->total / $charged ) - 1;
		$subtotal = round( $charged / ( 1 + $rate ), $decimals );
		$tax      = round( $charged - $subtotal, $decimals );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$credited = $wpdb->get_row( $wpdb->prepare( "SELECT id, amount FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $order->id ) );
		$connect  = '' !== (string) ( $order->connect_transfer_id ?? '' ); // Pro-only column.
		$recorded = null !== $order->vendor_earnings && (float) $order->vendor_earnings > 0;

		// Re-split on the corrected base. calculate() returns a locked split
		// as stored, which here is the inflated one, so the order's own locked
		// rate is applied to the new base instead: the vendor keeps the terms
		// they were paid under. An order with no locked rate yet goes through
		// the one commission authority as completion would. Worked out before
		// the dry-run return, so the dry run shows the new earning too.
		$commission = null;

		if ( $recorded || $credited ) {
			$corrected           = clone $order;
			$corrected->subtotal = $subtotal;
			$corrected->total    = $charged;
			$corrected->meta     = wp_json_encode( array_merge( $meta, array( 'tax_amount' => $tax ) ) );
			$base                = wpss_order_commission_base( $corrected );

			if ( null !== $order->commission_rate ) {
				$fee        = round( $base * (float) $order->commission_rate / 100, $decimals );
				$commission = array(
					'platform_fee'    => $fee,
					'vendor_earnings' => round( $base - $fee, $decimals ),
				);
			} else {
				$commission = CommissionService::compute_breakdown( $base, wpss_get_order( (int) $order->id ) );
			}

			$row_earning = "{$order->vendor_earnings} -> {$commission['vendor_earnings']}";
		}

		$row = array(
			'order'          => (string) $order->id,
			'payment'        => (string) $order->transaction_id,
			'charged'        => (string) $charged,
			'subtotal'       => "{$order->subtotal} -> {$subtotal}",
			'tax'            => ( (string) ( $meta['tax_amount'] ?? '-' ) ) . " -> {$tax}",
			'total'          => "{$order->total} -> {$charged}",
			'vendor_earning' => $row_earning ?? ( $recorded ? (string) $order->vendor_earnings : 'not recorded yet' ),
			'ledger'         => $credited ? ( $connect ? 'Connect: order row only' : 'correction row' ) : 'none (not credited yet)',
		);

		if ( ! $apply ) {
			return $row;
		}

		$meta['tax_amount'] = $tax;
		$update             = array(
			'subtotal' => $subtotal,
			'total'    => $charged,
			'meta'     => wp_json_encode( $meta ),
		);

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $orders, $update, array( 'id' => (int) $order->id ) );

		if ( $commission ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$orders,
				array(
					'platform_fee'    => $commission['platform_fee'],
					'vendor_earnings' => $commission['vendor_earnings'],
				),
				array( 'id' => (int) $order->id )
			);
		}

		if ( $commission && $credited && ! $connect ) {
			$delta = round( (float) $commission['vendor_earnings'] - (float) $credited->amount, $decimals );

			if ( abs( $delta ) > 0 ) {
				$balance = (float) wpss_get_ledger_balance( (int) $order->vendor_id, true );

				wpss_insert_ledger_row(
					array(
						'user_id'        => (int) $order->vendor_id,
						'type'           => 'order_correction',
						'amount'         => $delta,
						'balance_after'  => $balance + $delta,
						'currency'       => (string) $order->currency,
						'description'    => sprintf(
							/* translators: 1: order ID, 2: amount */
							__( 'Correction for order #%1$d: tax was counted twice when the order was recorded (%2$s)', 'wp-sell-services' ),
							(int) $order->id,
							wpss_format_price( $delta, (string) $order->currency )
						),
						'reference_type' => 'order',
						'reference_id'   => (int) $order->id,
						'status'         => 'completed',
						'created_at'     => current_time( 'mysql' ),
					)
				);
				$row['ledger'] = "correction {$delta}";
			}
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		( new AuditLogService() )->log(
			'order.repair',
			'order',
			(int) $order->id,
			array(
				'from_value' => (string) $order->total,
				'to_value'   => (string) $charged,
				'context'    => array(
					'repair'   => 'stripe_double_tax',
					'payment'  => (string) $order->transaction_id,
					'subtotal' => array( (float) $order->subtotal, $subtotal ),
				),
			)
		);

		return $row;
	}
}
