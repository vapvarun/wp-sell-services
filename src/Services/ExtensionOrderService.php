<?php
/**
 * Extension Order Service
 *
 * Paid-extension sub-order flow. Vendor asks the buyer for more money
 * and more days on an in-progress order; buyer accepts by paying through
 * the same gateway as the original order; on payment, the parent order
 * is extended and the vendor is credited post-commission.
 *
 * Mirrors {@see TippingService} — we reuse the sub-order pattern so
 * checkout, commission split, wallet ledger, and abandoned-order cleanup
 * all work without a second implementation.
 *
 * @package WPSellServices\Services
 * @since   1.1.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages paid extension request sub-orders.
 *
 * @since 1.1.0
 */
class ExtensionOrderService {

	/**
	 * Platform marker used on wpss_orders rows for extension sub-orders.
	 *
	 * Checked by checkout, stats filters, and notification gating to treat
	 * extension sub-orders as parent-of-parent payment records instead of
	 * standalone service orders.
	 */
	public const ORDER_TYPE = 'extension';

	/**
	 * Wallet transaction type for extension earnings.
	 *
	 * Stored alongside 'tip' and 'order_earning'; LedgerExporter surfaces it
	 * as a distinct row type in the vendor's CSV export.
	 */
	public const TYPE_EXTENSION = 'extension';

	/**
	 * Cron hook that sweeps abandoned pending-payment extension sub-orders.
	 */
	public const CLEANUP_HOOK = 'wpss_cleanup_abandoned_extensions';

	/**
	 * Hours after creation that an unpaid extension sub-order is marked
	 * cancelled. A buyer who opens the checkout and never pays must not
	 * permanently block the vendor from raising another extension on the
	 * same order — {@see self::cleanup_abandoned_extensions()} clears them.
	 */
	public const ABANDON_AFTER_HOURS = 48;

	/**
	 * Wire hooks. Called from the plugin bootstrap.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 20 so any earlier listeners that need to see the raw
		// paid sub-order (e.g. accounting/audit) fire before we extend the
		// parent and flip the sub-order to completed.
		add_action( 'wpss_order_paid', array( $this, 'handle_order_paid' ), 20, 1 );
		// Cleanup returns the count of cancelled rows for logging/tests; the
		// action discards it.
		add_action(
			self::CLEANUP_HOOK,
			function (): void {
				$this->cleanup_abandoned_extensions();
			}
		);
	}

	/**
	 * Gate wpss_order_paid to our platform marker.
	 *
	 * @param int $order_id Order ID that just transitioned to paid.
	 * @return void
	 */
	public function handle_order_paid( int $order_id ): void {
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( self::ORDER_TYPE !== ( $order->platform ?? '' ) ) {
			return;
		}
		$this->credit_extension_on_payment_complete( $order_id );
	}

	/**
	 * Create a pending-payment extension sub-order along with the matching
	 * `wpss_extension_requests` row.
	 *
	 * Does not credit the vendor or extend the parent — both happen after
	 * the gateway confirms payment via {@see self::credit_extension_on_payment_complete()}.
	 *
	 * @param int    $parent_order_id The active service order being extended.
	 * @param float  $amount          Extra amount the buyer will pay.
	 * @param int    $extra_days      Extra delivery days to add to the parent.
	 * @param int    $vendor_id       Vendor making the request (must own parent).
	 * @param string $reason          Vendor-provided explanation shown to buyer.
	 * @return array{success: bool, request_id: int|null, pay_order_id: int|null, checkout_url: string|null, message: string}
	 */
	public function create_extension_request( int $parent_order_id, float $amount, int $extra_days, int $vendor_id, string $reason ): array {
		global $wpdb;

		$fail = static function ( string $message ): array {
			return array(
				'success'      => false,
				'request_id'   => null,
				'pay_order_id' => null,
				'checkout_url' => null,
				'message'      => $message,
			);
		};

		if ( $amount <= 0 ) {
			return $fail( __( 'Extension amount must be greater than zero.', 'wp-sell-services' ) );
		}

		if ( $extra_days < 1 ) {
			return $fail( __( 'Extension must add at least one day.', 'wp-sell-services' ) );
		}

		$max_extension_days = (int) get_option( 'wpss_max_extension_days', 14 );
		if ( $extra_days > $max_extension_days ) {
			return $fail(
				sprintf(
				/* translators: %d: max extension days */
					__( 'Extension cannot exceed %d days.', 'wp-sell-services' ),
					$max_extension_days
				)
			);
		}

		$reason = sanitize_textarea_field( $reason );
		if ( strlen( trim( $reason ) ) < 10 ) {
			return $fail( __( 'Please provide a detailed reason the buyer can understand.', 'wp-sell-services' ) );
		}

		$parent = $this->get_order( $parent_order_id );
		if ( ! $parent ) {
			return $fail( __( 'Order not found.', 'wp-sell-services' ) );
		}

		if ( $vendor_id !== (int) $parent->vendor_id ) {
			return $fail( __( 'Only the vendor can request an extension on this order.', 'wp-sell-services' ) );
		}

		// Extensions are for small ad-hoc top-ups on fixed-price catalog
		// orders where the buyer already paid the full price. Custom
		// buyer-request projects use Milestones for their phased payment
		// model instead, so the seller never has two overlapping tools
		// available on the same order.
		if ( 'request' === ( $parent->platform ?? '' ) ) {
			return $fail( __( 'Extensions are for fixed-price service orders. For custom-project orders, propose a phase instead.', 'wp-sell-services' ) );
		}

		// Only ongoing orders can be extended. Completed / cancelled / disputed
		// orders go through refunds or reviews, not extensions.
		$allowed_parent_statuses = array(
			ServiceOrder::STATUS_IN_PROGRESS,
			ServiceOrder::STATUS_LATE,
			ServiceOrder::STATUS_REVISION_REQUESTED,
			ServiceOrder::STATUS_PENDING_APPROVAL,
		);
		if ( ! in_array( $parent->status, $allowed_parent_statuses, true ) ) {
			return $fail( __( 'Extensions can only be requested on active orders.', 'wp-sell-services' ) );
		}

		// Only one pending extension at a time — avoids buyer confusion about
		// which request they are paying for. Abandoned pending sub-orders are
		// swept every 48h, so a stalled request eventually clears on its own.
		if ( null !== $this->get_pending_request( $parent_order_id ) ) {
			return $fail( __( 'An extension request is already waiting on this order.', 'wp-sell-services' ) );
		}

		$orders_table     = $wpdb->prefix . 'wpss_orders';
		$extensions_table = $wpdb->prefix . 'wpss_extension_requests';
		$order_number     = 'EXT-' . strtoupper( wp_generate_password( 8, false, false ) );
		$currency         = $parent->currency ?? 'USD';

		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted_order = $wpdb->insert(
			$orders_table,
			array(
				'order_number'      => $order_number,
				'customer_id'       => (int) $parent->customer_id,
				'vendor_id'         => (int) $parent->vendor_id,
				'service_id'        => (int) $parent->service_id,
				'platform'          => self::ORDER_TYPE,
				'platform_order_id' => $parent_order_id,
				'subtotal'          => $amount,
				'total'             => $amount,
				'currency'          => $currency,
				'status'            => 'pending_payment',
				'payment_status'    => 'pending',
				'vendor_notes'      => $reason,
				'meta'              => wp_json_encode(
					array(
						'extension'       => true,
						'parent_order_id' => $parent_order_id,
						'extra_days'      => $extra_days,
						'reason'          => $reason,
					)
				),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted_order ) {
			$wpdb->query( 'ROLLBACK' );
			return $fail( __( 'Could not create extension order. Please try again.', 'wp-sell-services' ) );
		}

		$pay_order_id = (int) $wpdb->insert_id;

		$original_due = $parent->delivery_deadline instanceof \DateTimeImmutable
			? $parent->delivery_deadline->format( 'Y-m-d H:i:s' )
			: null;
		$new_due      = null !== $original_due
			? gmdate( 'Y-m-d H:i:s', strtotime( $original_due . ' +' . $extra_days . ' days' ) )
			: null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted_request = $wpdb->insert(
			$extensions_table,
			array(
				'order_id'          => $parent_order_id,
				'requested_by'      => $vendor_id,
				'extra_days'        => $extra_days,
				'amount'            => $amount,
				'pay_order_id'      => $pay_order_id,
				'reason'            => $reason,
				'status'            => ExtensionRequestService::STATUS_PENDING,
				'original_due_date' => $original_due,
				'new_due_date'      => $new_due,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted_request ) {
			$wpdb->query( 'ROLLBACK' );
			return $fail( __( 'Could not record extension request. Please try again.', 'wp-sell-services' ) );
		}

		$request_id = (int) $wpdb->insert_id;

		$wpdb->query( 'COMMIT' );

		$base_url     = function_exists( 'wpss_get_checkout_base_url' ) ? wpss_get_checkout_base_url() : home_url( '/checkout/' );
		$checkout_url = add_query_arg( 'pay_order', $pay_order_id, $base_url );

		/**
		 * Fires after an extension sub-order has been created and is awaiting
		 * the buyer's payment.
		 *
		 * @since 1.1.0
		 *
		 * @param int $request_id      Extension request row ID.
		 * @param int $pay_order_id    Sub-order ID on wpss_orders.
		 * @param int $parent_order_id Parent service order.
		 * @param int $vendor_id       Vendor who initiated.
		 */
		do_action( 'wpss_extension_request_created', $request_id, $pay_order_id, $parent_order_id, $vendor_id );

		return array(
			'success'      => true,
			'request_id'   => $request_id,
			'pay_order_id' => $pay_order_id,
			'checkout_url' => $checkout_url,
			'message'      => __( 'Quote sent to buyer. They will pay to approve.', 'wp-sell-services' ),
		);
	}

	/**
	 * Credit the vendor and extend the parent order after payment clears.
	 *
	 * Called from {@see self::handle_order_paid()}; idempotent via the
	 * wallet-transaction reference check (same pattern as tips).
	 *
	 * @param int $pay_order_id Extension sub-order ID.
	 * @return bool True if credit + extension applied, false if skipped.
	 */
	public function credit_extension_on_payment_complete( int $pay_order_id ): bool {
		global $wpdb;

		$sub = $this->get_order( $pay_order_id );
		if ( ! $sub ) {
			return false;
		}

		if ( self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return false;
		}

		if ( 'paid' !== ( $sub->payment_status ?? '' ) && empty( $sub->paid_at ) ) {
			return false;
		}

		$txn_table        = $wpdb->prefix . 'wpss_wallet_transactions';
		$orders_table     = $wpdb->prefix . 'wpss_orders';
		$extensions_table = $wpdb->prefix . 'wpss_extension_requests';
		$parent_order_id  = (int) ( $sub->platform_order_id ?? 0 );

		// Idempotency — the wpss_order_paid hook can re-fire if the gateway
		// webhook is retried. One wallet row per extension sub-order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$txn_table} WHERE type = %s AND reference_type = %s AND reference_id = %d LIMIT 1",
				self::TYPE_EXTENSION,
				'order',
				$pay_order_id
			)
		);
		if ( $existing ) {
			return false;
		}

		$amount = (float) $sub->total;
		if ( $amount <= 0 ) {
			return false;
		}

		$commission      = ( new CommissionService() )->calculate( $pay_order_id );
		$commission_rate = $commission['commission_rate'] ?? 0.0;
		$platform_fee    = $commission['platform_fee'] ?? 0.0;
		$vendor_earnings = $commission['vendor_earnings'] ?? $amount;

		if ( $vendor_earnings <= 0 ) {
			return false;
		}

		$request_row = $this->get_request_by_pay_order( $pay_order_id );
		$extra_days  = (int) ( $request_row->extra_days ?? 0 );

		$wpdb->query( 'START TRANSACTION' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current_balance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance_after FROM {$txn_table}
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT 1
				FOR UPDATE",
				(int) $sub->vendor_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$new_balance = $current_balance + $vendor_earnings;

		$description = sprintf(
			/* translators: 1: sub-order number, 2: parent order id */
			__( 'Extension payment for order #%2$d (%1$s)', 'wp-sell-services' ),
			$sub->order_number,
			$parent_order_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'        => (int) $sub->vendor_id,
				'type'           => self::TYPE_EXTENSION,
				'amount'         => $vendor_earnings,
				'balance_after'  => $new_balance,
				'currency'       => $sub->currency ?? 'USD',
				'description'    => $description,
				'reference_type' => 'order',
				'reference_id'   => $pay_order_id,
				'status'         => 'completed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Extend the parent's delivery deadline by extra_days. We intentionally
		// do NOT touch parent.total or parent.vendor_earnings — the extra
		// money and its commission split live on this sub-order row, which
		// reporting surfaces already sum alongside the parent. Adding to
		// parent.total here would double-count the extension in the vendor's
		// revenue once sub-orders are also summed.
		$parent = $this->get_order( $parent_order_id );
		if ( $parent && $extra_days > 0 ) {
			$source       = $parent->delivery_deadline instanceof \DateTimeImmutable
				? $parent->delivery_deadline->format( 'Y-m-d H:i:s' )
				: current_time( 'mysql' );
			$new_deadline = gmdate( 'Y-m-d H:i:s', strtotime( $source . ' +' . $extra_days . ' days' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$orders_table,
				array(
					'delivery_deadline' => $new_deadline,
					'updated_at'        => current_time( 'mysql' ),
				),
				array( 'id' => $parent_order_id )
			);
		}

		// Flip the sub-order to completed and persist commission breakdown so
		// the order row matches the wallet event. The parent's lifecycle
		// continues — only this payment record closes.
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
			array( 'id' => $pay_order_id )
		);

		// Mark the extension_requests row approved so both the on-order view
		// and the REST history agree with the payment ledger.
		if ( $request_row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$extensions_table,
				array(
					'status'       => ExtensionRequestService::STATUS_APPROVED,
					'responded_by' => (int) $sub->customer_id,
					'responded_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $request_row->id )
			);
		}

		$wpdb->query( 'COMMIT' );

		// Parity with regular-order commission so Pro wallet + analytics see
		// extensions as first-class earnings events.
		do_action(
			'wpss_commission_recorded',
			$pay_order_id,
			array(
				'order_total'     => $amount,
				'commission_rate' => $commission_rate,
				'platform_fee'    => $platform_fee,
				'vendor_earnings' => $vendor_earnings,
			),
			(int) $sub->vendor_id
		);

		/**
		 * Fires after a paid extension has been credited and the parent order
		 * extended.
		 *
		 * The amount is the NET vendor earning — matches the tip-sent contract
		 * so notification and analytics listeners can handle both with the
		 * same payload shape.
		 *
		 * @since 1.1.0
		 *
		 * @param int   $pay_order_id    Sub-order ID.
		 * @param int   $parent_order_id Parent order ID.
		 * @param int   $vendor_id       Vendor user ID.
		 * @param int   $customer_id     Buyer user ID.
		 * @param float $amount          NET vendor earnings.
		 * @param int   $extra_days      Days added to the parent deadline.
		 * @param int   $request_id      Extension request row ID.
		 */
		do_action(
			'wpss_extension_approved',
			$pay_order_id,
			$parent_order_id,
			(int) $sub->vendor_id,
			(int) $sub->customer_id,
			(float) $vendor_earnings,
			$extra_days,
			$request_row ? (int) $request_row->id : 0
		);

		return true;
	}

	/**
	 * Buyer declines a pending extension request.
	 *
	 * Cancels the pending sub-order (so the buyer is not charged and a new
	 * request can be raised) and flags the extension record as rejected.
	 *
	 * @param int    $request_id     Extension request row ID.
	 * @param int    $customer_id    User declining (must own parent order).
	 * @param string $response_note  Optional note for vendor.
	 * @return array{success: bool, message: string}
	 */
	public function decline( int $request_id, int $customer_id, string $response_note = '' ): array {
		global $wpdb;

		$extensions_table = $wpdb->prefix . 'wpss_extension_requests';
		$orders_table     = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$extensions_table} WHERE id = %d",
				$request_id
			)
		);

		if ( ! $row ) {
			return array(
				'success' => false,
				'message' => __( 'Extension request not found.', 'wp-sell-services' ),
			);
		}

		if ( ExtensionRequestService::STATUS_PENDING !== $row->status ) {
			return array(
				'success' => false,
				'message' => __( 'This request has already been answered.', 'wp-sell-services' ),
			);
		}

		$parent = $this->get_order( (int) $row->order_id );
		if ( ! $parent || (int) $parent->customer_id !== $customer_id ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to decline this request.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$extensions_table,
			array(
				'status'           => ExtensionRequestService::STATUS_REJECTED,
				'responded_by'     => $customer_id,
				'responded_at'     => current_time( 'mysql' ),
				'response_message' => sanitize_textarea_field( $response_note ),
			),
			array( 'id' => $request_id )
		);

		if ( ! empty( $row->pay_order_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$orders_table,
				array(
					'status'     => 'cancelled',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'id'     => (int) $row->pay_order_id,
					'status' => 'pending_payment',
				)
			);
		}

		/**
		 * Fires when a buyer declines a pending extension request.
		 *
		 * @since 1.1.0
		 *
		 * @param int    $request_id      Extension request ID.
		 * @param int    $parent_order_id Parent order ID.
		 * @param int    $customer_id     Buyer user ID.
		 * @param string $response_note   Buyer's note, if any.
		 */
		do_action( 'wpss_extension_rejected', $request_id, (int) $row->order_id, $customer_id, $response_note );

		return array(
			'success' => true,
			'message' => __( 'Extension request declined.', 'wp-sell-services' ),
		);
	}

	/**
	 * Sweep abandoned pending-payment extension sub-orders.
	 *
	 * Any sub-order that stayed pending_payment past the abandon horizon is
	 * marked cancelled — matching the tip-cleanup contract so that an
	 * unpaid request never permanently blocks the vendor from raising
	 * another extension on the same parent.
	 *
	 * @return int Number of sub-orders cancelled.
	 */
	public function cleanup_abandoned_extensions(): int {
		global $wpdb;

		$orders_table     = $wpdb->prefix . 'wpss_orders';
		$extensions_table = $wpdb->prefix . 'wpss_extension_requests';
		$threshold        = gmdate( 'Y-m-d H:i:s', time() - ( self::ABANDON_AFTER_HOURS * HOUR_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$orders_table}
				WHERE platform = %s AND status = %s AND created_at < %s",
				self::ORDER_TYPE,
				'pending_payment',
				$threshold
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			$sub_id = (int) $row->id;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$affected = $wpdb->update(
				$orders_table,
				array(
					'status'     => 'cancelled',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'id'     => $sub_id,
					'status' => 'pending_payment',
				)
			);

			if ( $affected ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$extensions_table,
					array(
						'status'       => ExtensionRequestService::STATUS_REJECTED,
						'responded_at' => current_time( 'mysql' ),
					),
					array(
						'pay_order_id' => $sub_id,
						'status'       => ExtensionRequestService::STATUS_PENDING,
					)
				);
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Fetch the pending extension request (if any) on an order.
	 *
	 * @param int $parent_order_id Parent service order ID.
	 * @return object|null Raw DB row or null.
	 */
	public function get_pending_request( int $parent_order_id ): ?object {
		global $wpdb;
		$extensions_table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$extensions_table}
				WHERE order_id = %d AND status = %s
				ORDER BY created_at DESC LIMIT 1",
				$parent_order_id,
				ExtensionRequestService::STATUS_PENDING
			)
		);

		return $row ?: null;
	}

	/**
	 * Find the extension-request row linked to a given sub-order ID.
	 *
	 * @param int $pay_order_id Sub-order ID on wpss_orders.
	 * @return object|null Raw DB row or null.
	 */
	private function get_request_by_pay_order( int $pay_order_id ): ?object {
		global $wpdb;
		$extensions_table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$extensions_table} WHERE pay_order_id = %d LIMIT 1",
				$pay_order_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Fetch an order row via the canonical helper so delivery_deadline etc.
	 * are already cast.
	 *
	 * @param int $order_id Order ID.
	 * @return ServiceOrder|null
	 */
	private function get_order( int $order_id ): ?ServiceOrder {
		return function_exists( 'wpss_get_order' ) ? wpss_get_order( $order_id ) : null;
	}
}
