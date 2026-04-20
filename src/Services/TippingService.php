<?php
/**
 * Tipping Service
 *
 * Handles tips from customers to vendors after order completion.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages order tipping functionality.
 *
 * Tips are real money: a tip creates a pending_payment order of
 * {@see self::ORDER_TYPE}, goes through the same payment gateway stack
 * used for service orders, and only credits the vendor's wallet once
 * payment is confirmed. The wallet_transaction row serves as the
 * post-payment record — it is NOT written before the gateway charge
 * clears, so no value is ever created from nothing.
 *
 * Flow:
 *   1. Buyer clicks "Send a Tip" on a completed order.
 *   2. {@see self::create_pending_tip_order()} writes a pending_payment row
 *      on {$wpdb->prefix}wpss_orders with platform='tip' and
 *      platform_order_id pointing at the original service order (same
 *      discriminator pattern the plugin uses for buyer-request orders).
 *   3. Frontend redirects buyer to /checkout?pay_order=<tip_order_id>;
 *      the existing standalone checkout charges the buyer's card.
 *   4. On the `wpss_order_paid` action, {@see self::credit_tip_on_payment_complete()}
 *      runs, applies the admin-configured tip commission rate (or the
 *      main commission rate as default), credits vendor_earnings to the
 *      wallet, completes the tip order, and fires {@see wpss_tip_sent}.
 *
 * @since 1.0.0
 */
class TippingService {

	/**
	 * Transaction type for tips.
	 */
	public const TYPE_TIP = 'tip';

	/**
	 * Platform discriminator used on the orders table for tip-type orders.
	 *
	 * Mirrors how buyer-request conversions use `platform='request'` — the
	 * orders table stays schema-stable and the checkout flow routes on the
	 * platform column instead of a new order_type field.
	 */
	public const ORDER_TYPE = 'tip';

	/**
	 * Tip status constants.
	 */
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_PENDING   = 'pending';
	public const STATUS_REFUNDED  = 'refunded';

	/**
	 * Register the order-paid hook so a confirmed tip order credits the vendor.
	 *
	 * Called from the plugin bootstrap. Hooks into {@see wpss_order_paid} and,
	 * when the paid order carries the {@see self::ORDER_TYPE} platform marker,
	 * runs {@see self::credit_tip_on_payment_complete()} against that order.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpss_order_paid', array( $this, 'handle_order_paid' ), 20, 2 );
	}

	/**
	 * Handler for the `wpss_order_paid` action.
	 *
	 * Skips orders whose platform is not a tip so the normal service-order
	 * flow is untouched. On a matching tip order, credits the vendor's
	 * wallet exactly once.
	 *
	 * @param int    $order_id       Paid order ID.
	 * @param string $transaction_id Gateway transaction reference.
	 * @return void
	 */
	public function handle_order_paid( int $order_id, string $transaction_id = '' ): void {
		unset( $transaction_id );
		$order = $this->get_order( $order_id );
		if ( ! $order || self::ORDER_TYPE !== ( $order->platform ?? '' ) ) {
			return;
		}
		$this->credit_tip_on_payment_complete( $order_id );
	}

	/**
	 * Create a pending-payment tip order that the buyer pays via the
	 * configured gateway. Does NOT credit the vendor — that happens after
	 * the gateway confirms payment via {@see self::credit_tip_on_payment_complete()}.
	 *
	 * @param int    $parent_order_id The completed service order being tipped against.
	 * @param float  $amount          Tip amount in the order's currency.
	 * @param int    $customer_id     Buyer user ID (must own parent order).
	 * @param string $message         Optional thank-you note from buyer.
	 * @return array{success: bool, tip_order_id: int|null, checkout_url: string|null, message: string}
	 */
	public function create_pending_tip_order( int $parent_order_id, float $amount, int $customer_id, string $message = '' ): array {
		global $wpdb;

		if ( $amount <= 0 ) {
			return array(
				'success'      => false,
				'tip_order_id' => null,
				'checkout_url' => null,
				'message'      => __( 'Tip amount must be greater than zero.', 'wp-sell-services' ),
			);
		}

		$parent = $this->get_order( $parent_order_id );
		if ( ! $parent ) {
			return array(
				'success'      => false,
				'tip_order_id' => null,
				'checkout_url' => null,
				'message'      => __( 'Order not found.', 'wp-sell-services' ),
			);
		}

		if ( ServiceOrder::STATUS_COMPLETED !== $parent->status ) {
			return array(
				'success'      => false,
				'tip_order_id' => null,
				'checkout_url' => null,
				'message'      => __( 'Tips can only be sent for completed orders.', 'wp-sell-services' ),
			);
		}

		if ( $customer_id !== (int) $parent->customer_id ) {
			return array(
				'success'      => false,
				'tip_order_id' => null,
				'checkout_url' => null,
				'message'      => __( 'You can only tip on your own orders.', 'wp-sell-services' ),
			);
		}

		if ( $this->has_tipped( $parent_order_id, $customer_id ) ) {
			return array(
				'success'      => false,
				'tip_order_id' => null,
				'checkout_url' => null,
				'message'      => __( 'You have already tipped on this order.', 'wp-sell-services' ),
			);
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$order_number = 'TIP-' . strtoupper( wp_generate_password( 8, false, false ) );
		$currency     = $parent->currency ?? 'USD';
		$tip_message  = sanitize_textarea_field( $message );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$orders_table,
			array(
				'order_number'      => $order_number,
				'customer_id'       => $customer_id,
				'vendor_id'         => (int) $parent->vendor_id,
				'service_id'        => (int) $parent->service_id,
				'platform'          => self::ORDER_TYPE, // 'tip' — routes checkout like 'request' does.
				'platform_order_id' => $parent_order_id,
				'subtotal'          => $amount,
				'total'             => $amount,
				'currency'          => $currency,
				'status'            => 'pending_payment',
				'payment_status'    => 'pending',
				'vendor_notes'      => $tip_message,
				'meta'              => wp_json_encode(
					array(
						'tip'             => true,
						'parent_order_id' => $parent_order_id,
						'buyer_note'      => $tip_message,
					)
				),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $inserted ) {
			return array(
				'success'      => false,
				'tip_order_id' => null,
				'checkout_url' => null,
				'message'      => __( 'Could not create tip order. Please try again.', 'wp-sell-services' ),
			);
		}

		$tip_order_id = (int) $wpdb->insert_id;
		$base_url     = function_exists( 'wpss_get_checkout_base_url' ) ? wpss_get_checkout_base_url() : home_url( '/checkout/' );
		// The standalone checkout reads `pay_order` to resume an existing
		// pending_payment order; using the same param keeps tip orders on
		// the same code path as proposal-accepted orders.
		$checkout_url = add_query_arg( 'pay_order', $tip_order_id, $base_url );

		/**
		 * Fires when a pending-payment tip order is created and awaits the buyer's gateway charge.
		 *
		 * @since 1.1.0
		 *
		 * @param int   $tip_order_id    Newly created tip order ID.
		 * @param int   $parent_order_id Original service order being tipped.
		 * @param int   $customer_id     Buyer user ID.
		 * @param float $amount          Tip amount.
		 */
		do_action( 'wpss_tip_order_created', $tip_order_id, $parent_order_id, $customer_id, $amount );

		return array(
			'success'      => true,
			'tip_order_id' => $tip_order_id,
			'checkout_url' => $checkout_url,
			'message'      => __( 'Tip order created. Continue to payment.', 'wp-sell-services' ),
		);
	}

	/**
	 * Credit the vendor's wallet after a tip order has been paid by the gateway.
	 *
	 * Called from an order-status hook, not directly from user input — the tip
	 * order must already be in a paid state before this runs, otherwise the
	 * method no-ops. This is the single place where tip money actually flows to
	 * the vendor.
	 *
	 * @param int $tip_order_id Tip order ID (must have order_type='tip' and a paid status).
	 * @return bool True if credited, false if skipped (wrong type, not paid, already credited, etc.).
	 */
	public function credit_tip_on_payment_complete( int $tip_order_id ): bool {
		global $wpdb;

		$tip_order = $this->get_order( $tip_order_id );
		if ( ! $tip_order ) {
			return false;
		}

		if ( self::ORDER_TYPE !== ( $tip_order->platform ?? '' ) ) {
			return false;
		}

		if ( 'paid' !== ( $tip_order->payment_status ?? '' ) && empty( $tip_order->paid_at ) ) {
			return false;
		}

		$txn_table       = $wpdb->prefix . 'wpss_wallet_transactions';
		$parent_order_id = (int) ( $tip_order->platform_order_id ?? 0 );

		// Idempotency: bail if we already credited any tip for this parent
		// order (covers the case where the hook fires twice for the same
		// wpss_order_paid event).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$txn_table} WHERE type = %s AND reference_type = %s AND reference_id = %d LIMIT 1",
				self::TYPE_TIP,
				'order',
				$parent_order_id
			)
		);
		if ( $existing ) {
			return false;
		}

		$amount = (float) $tip_order->total;
		if ( $amount <= 0 ) {
			return false;
		}

		// Tips use the same commission model as service orders — the platform
		// takes its configured cut and only the vendor-earnings portion hits
		// the wallet. Admins can override the cut via wpss_commission →
		// tip_commission_rate: empty falls back to the regular rate, 0 gives
		// the vendor 100%, any positive value sets a custom tip-only rate.
		$commission      = ( new CommissionService() )->calculate( $tip_order_id );
		$commission_rate = $commission['commission_rate'] ?? 0.0;
		$platform_fee    = $commission['platform_fee'] ?? 0.0;
		$vendor_earnings = $commission['vendor_earnings'] ?? $amount;

		$commission_settings = get_option( 'wpss_commission', array() );
		$tip_rate_override   = $commission_settings['tip_commission_rate'] ?? '';
		$tip_rate_override   = is_string( $tip_rate_override ) && '' === $tip_rate_override ? null : (float) $tip_rate_override;

		/**
		 * Filter the commission rate applied to a tip.
		 *
		 * Default: matches the rate computed for regular orders on the same
		 * vendor/service. Returning null keeps that default; any numeric value
		 * replaces the rate for this tip.
		 *
		 * @since 1.1.0
		 *
		 * @param float|null $tip_rate_override Override percentage, or null to keep default.
		 * @param object     $tip_order         Tip order row.
		 * @param float      $default_rate      Rate that would be used without override.
		 */
		$tip_rate_override = apply_filters( 'wpss_tip_commission_rate', $tip_rate_override, $tip_order, $commission_rate );

		if ( null !== $tip_rate_override && is_numeric( $tip_rate_override ) ) {
			$commission_rate = min( 100.0, max( 0.0, (float) $tip_rate_override ) );
			$platform_fee    = round( $amount * ( $commission_rate / 100 ), 2 );
			$vendor_earnings = round( $amount - $platform_fee, 2 );
		}

		if ( $vendor_earnings <= 0 ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current_balance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance_after FROM {$txn_table}
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT 1
				FOR UPDATE",
				(int) $tip_order->vendor_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$new_balance = $current_balance + $vendor_earnings;
		$description = sprintf(
			/* translators: %s: tip order number */
			__( 'Tip received for order %s', 'wp-sell-services' ),
			$tip_order->order_number
		);
		if ( ! empty( $tip_order->vendor_notes ) ) {
			$description .= ': ' . $tip_order->vendor_notes;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'        => (int) $tip_order->vendor_id,
				'type'           => self::TYPE_TIP,
				'amount'         => $vendor_earnings,
				'balance_after'  => $new_balance,
				'currency'       => $tip_order->currency ?? 'USD',
				'description'    => $description,
				'reference_type' => 'order',
				'reference_id'   => $parent_order_id,
				'status'         => self::STATUS_COMPLETED,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		// Tip orders have no requirements / delivery phase — flip straight
		// to completed once credited so the record does not sit in
		// pending_requirements (the standalone provider's default post-paid
		// status) with no UI surface to clear it. Also persist the commission
		// breakdown on the order row so admin reporting matches the split.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$orders_table,
			array(
				'status'          => 'completed',
				'completed_at'    => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'commission_rate' => $commission_rate,
				'platform_fee'    => $platform_fee,
				'vendor_earnings' => $vendor_earnings,
			),
			array( 'id' => $tip_order_id )
		);

		/**
		 * Fires after a paid tip has been credited to the vendor's wallet.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $tip_id         Wallet-transaction row ID.
		 * @param int    $tip_order_id   Tip order ID (parent is on the order row).
		 * @param int    $vendor_id      Vendor user ID.
		 * @param int    $customer_id    Buyer user ID.
		 * @param float  $amount         Tip amount.
		 * @param string $message        Tip note (from vendor_notes).
		 */
		do_action(
			'wpss_tip_sent',
			(int) $wpdb->insert_id,
			$parent_order_id,
			(int) $tip_order->vendor_id,
			(int) $tip_order->customer_id,
			$amount,
			$tip_order->vendor_notes ?? ''
		);

		return true;
	}

	/**
	 * Check if the given buyer has an outstanding or completed tip on a parent order.
	 *
	 * Returns true when either a pending-payment tip order exists on
	 * wpss_orders for this buyer/parent pair, or when the wallet transaction
	 * recording a paid tip exists. The pending-order branch prevents a buyer
	 * from starting a second tip while a gateway redirect is in flight.
	 *
	 * @param int $order_id    Parent service order ID.
	 * @param int $customer_id Buyer user ID (used to scope pending tip orders).
	 * @return bool
	 */
	public function has_tipped( int $order_id, int $customer_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Pending tip in flight? Block re-entry.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$orders_table}
				WHERE platform = %s
				AND platform_order_id = %d
				AND customer_id = %d
				AND status != 'cancelled'
				LIMIT 1",
				self::ORDER_TYPE,
				$order_id,
				$customer_id
			)
		);
		if ( $pending ) {
			return true;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE type = %s
				AND reference_type = 'order'
				AND reference_id = %d
				AND user_id = %d
				LIMIT 1",
				self::TYPE_TIP,
				$order_id,
				$order->vendor_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (bool) $exists;
	}

	/**
	 * Get tips received by a vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Array of tip records.
	 */
	public function get_vendor_tips( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d AND type = %s
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				self::TYPE_TIP,
				$args['limit'],
				$args['offset']
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}

	/**
	 * Get tip for a specific order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Tip record or null.
	 */
	public function get_order_tip( int $order_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tip = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE type = %s AND reference_type = 'order' AND reference_id = %d
				LIMIT 1",
				self::TYPE_TIP,
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $tip ? $tip : null;
	}

	/**
	 * Get total tips received by a vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return float Total tips amount.
	 */
	public function get_vendor_tips_total( int $vendor_id ): float {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table}
				WHERE user_id = %d AND type = %s AND status = %s",
				$vendor_id,
				self::TYPE_TIP,
				self::STATUS_COMPLETED
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (float) $total;
	}

	/**
	 * Get order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Order row or null.
	 */
	private function get_order( int $order_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $order ? $order : null;
	}
}
