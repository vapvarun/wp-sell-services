<?php
/**
 * Milestone Service
 *
 * Paid-milestone sub-order flow. Vendor breaks an active order into named
 * phases, each priced independently; the buyer pays each phase up front
 * via the same pay_order checkout tips and extensions use; vendor then
 * delivers and buyer approves the delivery.
 *
 * Pattern reuse:
 *   - Storage: each milestone IS a row on wpss_orders with platform='milestone'
 *     and platform_order_id pointing at the parent service order. Title,
 *     description, deliverables, and sort_order live in the sub-order's
 *     `meta` JSON — same place tip and extension already store their
 *     feature-specific fields. No separate milestone table or post meta.
 *   - Status: the sub-order's own `status` column drives the milestone
 *     lifecycle, so there is a single source of truth. No parallel status
 *     enum that can drift.
 *       pending_payment   = vendor proposed, buyer has not paid yet
 *       in_progress       = buyer paid, vendor working
 *       pending_approval  = vendor submitted delivery, awaiting buyer
 *       completed         = buyer approved
 *       cancelled         = declined before payment / abandoned / parent cancelled
 *   - Payment: commission is applied at payment time (wpss_order_paid),
 *     same as tips and extensions, so the vendor's wallet sees the money
 *     the moment the gateway confirms. Approval is purely a delivery
 *     confirmation — no money moves at approval.
 *   - Revisions: happen in the existing conversation thread, not via a
 *     dedicated reject status. The vendor re-submits and the milestone
 *     flips back to pending_approval.
 *
 * @package WPSellServices\Services
 * @since   1.1.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages paid milestone sub-orders.
 *
 * @since 1.1.0
 */
class MilestoneService {

	/**
	 * Platform marker stamped on wpss_orders rows for milestone sub-orders.
	 *
	 * Checked by checkout, stats filters, notification gating, sales/orders
	 * list routing, and email gating to treat milestone sub-orders as
	 * phases of a parent service order rather than standalone orders.
	 */
	public const ORDER_TYPE = 'milestone';

	/**
	 * Wallet-transaction type for milestone earnings.
	 *
	 * Stored alongside 'tip', 'extension', and 'order_earning' so the
	 * LedgerExporter and analytics surfaces can distinguish phase
	 * payments from base-order earnings.
	 */
	public const TYPE_MILESTONE = 'milestone';

	/**
	 * Cron hook that sweeps abandoned pending-payment milestone sub-orders.
	 */
	public const CLEANUP_HOOK = 'wpss_cleanup_abandoned_milestones';

	/**
	 * Hours after which an unpaid milestone is marked cancelled. Mirrors
	 * the tip / extension contract so an unpaid proposal never permanently
	 * blocks the vendor's planning surface.
	 */
	public const ABANDON_AFTER_HOURS = 48;

	/**
	 * Register hooks. Called from the plugin bootstrap.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpss_order_paid', array( $this, 'handle_order_paid' ), 20, 1 );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_abandoned_milestones' ) );
	}

	/**
	 * Gate wpss_order_paid to our platform marker.
	 *
	 * @param int $order_id Order ID that just transitioned to paid.
	 * @return void
	 */
	public function handle_order_paid( int $order_id ): void {
		$order = wpss_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( self::ORDER_TYPE !== ( $order->platform ?? '' ) ) {
			return;
		}
		$this->credit_milestone_on_payment_complete( $order_id );
	}

	/**
	 * Propose a milestone — vendor creates the phase record (as a
	 * pending_payment sub-order) and the buyer gets a notification to
	 * Accept & Pay. Does not credit the vendor or start work — payment
	 * has to clear first.
	 *
	 * @param int    $parent_order_id Active parent service order being phased.
	 * @param int    $vendor_id       Vendor proposing (must own parent).
	 * @param string $title           Short name for the phase.
	 * @param string $description     What the buyer is paying for (shown on the card + receipt).
	 * @param float  $amount          Phase price in parent's currency.
	 * @param int    $days_to_deliver Delivery window in days (0 allowed; stored on due_date).
	 * @param string $deliverables    Optional itemised deliverables list (text).
	 * @param bool   $is_contract     True when this milestone is part of a predefined contract
	 *                                (Upwork-style proposal acceptance). Stored in meta so the
	 *                                abandon-cleanup cron knows to leave it alone.
	 * @param bool   $fire_hooks      When false, suppresses the `wpss_milestone_proposed`
	 *                                action so the caller can defer dispatch until after a
	 *                                surrounding DB transaction commits — avoids phantom
	 *                                emails landing in the buyer's inbox if the enclosing
	 *                                transaction rolls back.
	 * @return array{success: bool, milestone_id: int|null, checkout_url: string|null, message: string}
	 */
	public function propose( int $parent_order_id, int $vendor_id, string $title, string $description, float $amount, int $days_to_deliver, string $deliverables = '', bool $is_contract = false, bool $fire_hooks = true ): array {
		global $wpdb;

		$fail = static function ( string $message ): array {
			return array(
				'success'      => false,
				'milestone_id' => null,
				'checkout_url' => null,
				'message'      => $message,
			);
		};

		$title = sanitize_text_field( $title );
		if ( '' === $title ) {
			return $fail( __( 'Milestone title is required.', 'wp-sell-services' ) );
		}

		if ( $amount <= 0 ) {
			return $fail( __( 'Milestone amount must be greater than zero.', 'wp-sell-services' ) );
		}

		if ( $days_to_deliver < 0 ) {
			$days_to_deliver = 0;
		}

		$description  = sanitize_textarea_field( $description );
		$deliverables = sanitize_textarea_field( $deliverables );

		$parent = wpss_get_order( $parent_order_id );
		if ( ! $parent ) {
			return $fail( __( 'Parent order not found.', 'wp-sell-services' ) );
		}

		if ( $vendor_id !== (int) $parent->vendor_id ) {
			return $fail( __( 'Only the seller can propose milestones on this order.', 'wp-sell-services' ) );
		}

		// Milestones are the payment model for custom, buyer-posted projects
		// (platform='request'). Fixed-price catalog orders top up via
		// Extensions instead, so there is one payment story per order and
		// the seller never has to choose between two overlapping tools.
		if ( 'request' !== ( $parent->platform ?? '' ) ) {
			return $fail( __( 'Milestones are available on custom-project orders only. For fixed-price service orders, use Extensions for extra work.', 'wp-sell-services' ) );
		}

		// Allowed while the parent is still being planned or actively
		// worked on. Cancelled or disputed parents cannot take new phases.
		$allowed_parent_statuses = array(
			ServiceOrder::STATUS_PENDING_REQUIREMENTS,
			ServiceOrder::STATUS_IN_PROGRESS,
			ServiceOrder::STATUS_LATE,
			ServiceOrder::STATUS_REVISION_REQUESTED,
			ServiceOrder::STATUS_PENDING_APPROVAL,
		);
		if ( ! in_array( $parent->status, $allowed_parent_statuses, true ) ) {
			return $fail( __( 'Milestones can only be proposed on active orders.', 'wp-sell-services' ) );
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$sort_order   = count( $this->get_for_parent( $parent_order_id ) ) + 1;
		$order_number = 'MS-' . strtoupper( wp_generate_password( 8, false, false ) );
		$currency     = $parent->currency ?? 'USD';

		$due_date = null;
		if ( $days_to_deliver > 0 ) {
			$due_date = gmdate( 'Y-m-d H:i:s', time() + ( $days_to_deliver * DAY_IN_SECONDS ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
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
				'delivery_deadline' => $due_date,
				'vendor_notes'      => $description,
				'meta'              => wp_json_encode(
					array(
						'milestone'             => true,
						'parent_order_id'       => $parent_order_id,
						'title'                 => $title,
						'description'           => $description,
						'deliverables'          => $deliverables,
						'sort_order'            => $sort_order,
						'is_contract_milestone' => $is_contract,
					)
				),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return $fail( __( 'Could not create milestone. Please try again.', 'wp-sell-services' ) );
		}

		$milestone_id = (int) $wpdb->insert_id;
		$base_url     = function_exists( 'wpss_get_checkout_base_url' ) ? wpss_get_checkout_base_url() : home_url( '/checkout/' );
		$checkout_url = add_query_arg( 'pay_order', $milestone_id, $base_url );

		/**
		 * Fires when a milestone sub-order has been created and is awaiting
		 * the buyer's payment.
		 *
		 * @since 1.1.0
		 *
		 * @param int $milestone_id    Sub-order ID.
		 * @param int $parent_order_id Parent service order ID.
		 * @param int $vendor_id       Vendor who proposed it.
		 */
		if ( $fire_hooks ) {
			do_action( 'wpss_milestone_proposed', $milestone_id, $parent_order_id, $vendor_id );
		}

		return array(
			'success'      => true,
			'milestone_id' => $milestone_id,
			'checkout_url' => $checkout_url,
			'message'      => __( 'Milestone sent to buyer.', 'wp-sell-services' ),
		);
	}

	/**
	 * Credit the vendor on payment clearing + flip the milestone to
	 * in_progress so the Submit Delivery action becomes live.
	 *
	 * Idempotent via the wallet-transaction reference check — the same
	 * pattern tips and extensions use, so gateway webhook retries do not
	 * double-credit.
	 *
	 * @param int $milestone_id Sub-order ID.
	 * @return bool True if credited, false if skipped.
	 */
	public function credit_milestone_on_payment_complete( int $milestone_id ): bool {
		global $wpdb;

		$sub = wpss_get_order( $milestone_id );
		if ( ! $sub ) {
			return false;
		}

		if ( self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return false;
		}

		if ( 'paid' !== ( $sub->payment_status ?? '' ) && empty( $sub->paid_at ) ) {
			return false;
		}

		$txn_table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$txn_table} WHERE type = %s AND reference_type = %s AND reference_id = %d LIMIT 1",
				self::TYPE_MILESTONE,
				'order',
				$milestone_id
			)
		);
		if ( $existing ) {
			return false;
		}

		$amount = (float) $sub->total;
		if ( $amount <= 0 ) {
			return false;
		}

		$commission      = ( new CommissionService() )->calculate( $milestone_id );
		$commission_rate = $commission['commission_rate'] ?? 0.0;
		$platform_fee    = $commission['platform_fee'] ?? 0.0;
		$vendor_earnings = $commission['vendor_earnings'] ?? $amount;

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
				(int) $sub->vendor_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$new_balance = $current_balance + $vendor_earnings;
		$meta        = $this->decode_meta( $sub );
		$description = sprintf(
			/* translators: 1: milestone title, 2: sub-order number */
			__( 'Milestone payment: %1$s (%2$s)', 'wp-sell-services' ),
			(string) ( $meta['title'] ?? $sub->order_number ),
			$sub->order_number
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'        => (int) $sub->vendor_id,
				'type'           => self::TYPE_MILESTONE,
				'amount'         => $vendor_earnings,
				'balance_after'  => $new_balance,
				'currency'       => $sub->currency ?? 'USD',
				'description'    => $description,
				'reference_type' => 'order',
				'reference_id'   => $milestone_id,
				'status'         => 'completed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Flip to in_progress so the vendor sees Submit Delivery and the
		// timeline reflects 'paid, seller working'. Persist the commission
		// breakdown so the sub-order row matches the wallet event.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$orders_table,
			array(
				'status'          => ServiceOrder::STATUS_IN_PROGRESS,
				'started_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'commission_rate' => $commission_rate,
				'platform_fee'    => $platform_fee,
				'vendor_earnings' => $vendor_earnings,
			),
			array( 'id' => $milestone_id )
		);

		$wpdb->query( 'COMMIT' );

		do_action(
			'wpss_commission_recorded',
			$milestone_id,
			array(
				'order_total'     => $amount,
				'commission_rate' => $commission_rate,
				'platform_fee'    => $platform_fee,
				'vendor_earnings' => $vendor_earnings,
			),
			(int) $sub->vendor_id
		);

		/**
		 * Fires after a milestone payment has cleared and the vendor has been
		 * credited. Milestone is now in_progress.
		 *
		 * @since 1.1.0
		 *
		 * @param int   $milestone_id    Sub-order ID.
		 * @param int   $parent_order_id Parent service order ID.
		 * @param int   $vendor_id       Vendor user ID.
		 * @param int   $customer_id     Buyer user ID.
		 * @param float $net_amount      NET vendor earnings.
		 */
		do_action(
			'wpss_milestone_paid',
			$milestone_id,
			(int) $sub->platform_order_id,
			(int) $sub->vendor_id,
			(int) $sub->customer_id,
			(float) $vendor_earnings
		);

		return true;
	}

	/**
	 * Vendor marks a milestone as delivered. Flips the sub-order to
	 * pending_approval so the buyer sees the approve button.
	 *
	 * Allowed from in_progress (normal submission) and pending_approval
	 * (re-submission after a chat revision). No separate 'rejected' state
	 * — revisions live in the conversation per product decision.
	 *
	 * @param int    $milestone_id Sub-order ID.
	 * @param int    $vendor_id    Vendor submitting (must own parent).
	 * @param string $note         Optional message shown to buyer with the delivery.
	 * @return array{success: bool, message: string}
	 */
	public function submit( int $milestone_id, int $vendor_id, string $note = '' ): array {
		global $wpdb;

		$sub = wpss_get_order( $milestone_id );
		if ( ! $sub || self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return array( 'success' => false, 'message' => __( 'Milestone not found.', 'wp-sell-services' ) );
		}

		if ( $vendor_id !== (int) $sub->vendor_id ) {
			return array( 'success' => false, 'message' => __( 'Only the seller can submit this milestone.', 'wp-sell-services' ) );
		}

		$allowed_submit_from = array(
			ServiceOrder::STATUS_IN_PROGRESS,
			ServiceOrder::STATUS_PENDING_APPROVAL,
			ServiceOrder::STATUS_REVISION_REQUESTED,
		);
		if ( ! in_array( $sub->status, $allowed_submit_from, true ) ) {
			return array( 'success' => false, 'message' => __( 'This milestone cannot be submitted in its current state.', 'wp-sell-services' ) );
		}

		// Persist the note on the sub-order's meta so the buyer's receipt
		// view and the milestone-view template can surface it.
		$meta                = $this->decode_meta( $sub );
		$meta['submit_note'] = sanitize_textarea_field( $note );
		$meta['submitted_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			array(
				'status'     => ServiceOrder::STATUS_PENDING_APPROVAL,
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $milestone_id )
		);

		do_action( 'wpss_milestone_submitted', $milestone_id, (int) $sub->platform_order_id, $vendor_id, (int) $sub->customer_id );

		return array( 'success' => true, 'message' => __( 'Milestone submitted. Buyer has been notified.', 'wp-sell-services' ) );
	}

	/**
	 * Buyer approves the submitted delivery. Flips the sub-order to
	 * completed. No money moves — commission already settled at payment
	 * time. Approval is purely the delivery sign-off.
	 *
	 * @param int $milestone_id Sub-order ID.
	 * @param int $customer_id  Buyer user ID (must own parent).
	 * @return array{success: bool, message: string}
	 */
	public function approve( int $milestone_id, int $customer_id ): array {
		global $wpdb;

		$sub = wpss_get_order( $milestone_id );
		if ( ! $sub || self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return array( 'success' => false, 'message' => __( 'Milestone not found.', 'wp-sell-services' ) );
		}

		if ( $customer_id !== (int) $sub->customer_id ) {
			return array( 'success' => false, 'message' => __( 'Only the buyer can approve this milestone.', 'wp-sell-services' ) );
		}

		if ( ServiceOrder::STATUS_PENDING_APPROVAL !== $sub->status ) {
			return array( 'success' => false, 'message' => __( 'Only submitted milestones can be approved.', 'wp-sell-services' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			array(
				'status'       => ServiceOrder::STATUS_COMPLETED,
				'completed_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $milestone_id )
		);

		do_action( 'wpss_milestone_approved', $milestone_id, (int) $sub->platform_order_id, (int) $sub->vendor_id, $customer_id );

		return array( 'success' => true, 'message' => __( 'Milestone approved.', 'wp-sell-services' ) );
	}

	/**
	 * Buyer declines an unpaid milestone. Cancels the sub-order so the
	 * vendor can propose a revised one. After payment, cancellation has
	 * to go through the dispute flow — money has already moved.
	 *
	 * @param int $milestone_id Sub-order ID.
	 * @param int $customer_id  Buyer user ID (must own parent).
	 * @return array{success: bool, message: string}
	 */
	public function decline( int $milestone_id, int $customer_id ): array {
		global $wpdb;

		$sub = wpss_get_order( $milestone_id );
		if ( ! $sub || self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return array( 'success' => false, 'message' => __( 'Milestone not found.', 'wp-sell-services' ) );
		}

		if ( $customer_id !== (int) $sub->customer_id ) {
			return array( 'success' => false, 'message' => __( 'Only the buyer can decline this milestone.', 'wp-sell-services' ) );
		}

		if ( 'pending_payment' !== $sub->status ) {
			return array( 'success' => false, 'message' => __( 'This milestone has already been paid and cannot be declined here. Open a dispute if there is a problem.', 'wp-sell-services' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $milestone_id,
				'status' => 'pending_payment',
			)
		);

		do_action( 'wpss_milestone_declined', $milestone_id, (int) $sub->platform_order_id, $customer_id );

		return array( 'success' => true, 'message' => __( 'Milestone declined.', 'wp-sell-services' ) );
	}

	/**
	 * Vendor deletes a milestone they proposed but the buyer has not paid.
	 * After payment, deletion is not allowed — the money has moved.
	 *
	 * @param int $milestone_id Sub-order ID.
	 * @param int $vendor_id    Vendor user ID.
	 * @return array{success: bool, message: string}
	 */
	public function delete_unpaid( int $milestone_id, int $vendor_id ): array {
		global $wpdb;

		$sub = wpss_get_order( $milestone_id );
		if ( ! $sub || self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return array( 'success' => false, 'message' => __( 'Milestone not found.', 'wp-sell-services' ) );
		}

		if ( $vendor_id !== (int) $sub->vendor_id ) {
			return array( 'success' => false, 'message' => __( 'Only the seller can remove this milestone.', 'wp-sell-services' ) );
		}

		if ( 'pending_payment' !== $sub->status ) {
			return array( 'success' => false, 'message' => __( 'Only unpaid milestones can be removed.', 'wp-sell-services' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $milestone_id,
				'status' => 'pending_payment',
			)
		);

		return array( 'success' => true, 'message' => __( 'Milestone removed.', 'wp-sell-services' ) );
	}

	/**
	 * Sweep abandoned pending-payment milestone sub-orders older than the
	 * abandon horizon. Matches the tip / extension cleanup contract.
	 *
	 * @return int Number of sub-orders cancelled.
	 */
	public function cleanup_abandoned_milestones(): int {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$threshold    = gmdate( 'Y-m-d H:i:s', time() - ( self::ABANDON_AFTER_HOURS * HOUR_IN_SECONDS ) );

		// Contract milestones (predefined at proposal acceptance) are NOT
		// auto-cancelled — long projects may legitimately sit between
		// phases for weeks while the vendor produces deliverables. Only
		// ad-hoc milestones the vendor proposed mid-flight without buyer
		// commitment go to the abandon-cron. The marker is stored in the
		// sub-order's meta JSON; we filter on the JSON text so the
		// cleanup stays a single SQL statement.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$orders_table}
				SET status = 'cancelled', updated_at = %s
				WHERE platform = %s
				AND status = %s
				AND created_at < %s
				AND ( meta IS NULL OR meta NOT LIKE %s )",
				current_time( 'mysql' ),
				self::ORDER_TYPE,
				'pending_payment',
				$threshold,
				'%"is_contract_milestone":true%'
			)
		);

		return $count;
	}

	/**
	 * Fetch all milestones on a parent order, ordered by the
	 * sort_order stored in each sub-order's meta JSON (fallback to
	 * created_at when sort_order is missing).
	 *
	 * Returns decorated rows with the meta fields already decoded so
	 * templates do not have to deal with the JSON shape.
	 *
	 * @param int $parent_order_id Parent service order ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_parent( int $parent_order_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_orders
				WHERE platform = %s AND platform_order_id = %d
				ORDER BY created_at ASC, id ASC",
				self::ORDER_TYPE,
				$parent_order_id
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$decorated = array();
		foreach ( $rows as $row ) {
			$meta        = is_string( $row->meta ?? '' ) && '' !== $row->meta ? json_decode( $row->meta, true ) : array();
			$decorated[] = array(
				'id'                    => (int) $row->id,
				'order_number'          => (string) $row->order_number,
				'status'                => (string) $row->status,
				'payment_status'        => (string) $row->payment_status,
				'amount'                => (float) $row->total,
				'vendor_earnings'       => isset( $row->vendor_earnings ) ? (float) $row->vendor_earnings : null,
				'platform_fee'          => isset( $row->platform_fee ) ? (float) $row->platform_fee : null,
				'currency'              => (string) $row->currency,
				'title'                 => (string) ( $meta['title'] ?? '' ),
				'description'           => (string) ( $meta['description'] ?? ( $row->vendor_notes ?? '' ) ),
				'deliverables'          => (string) ( $meta['deliverables'] ?? '' ),
				'sort_order'            => (int) ( $meta['sort_order'] ?? 0 ),
				'submit_note'           => (string) ( $meta['submit_note'] ?? '' ),
				'is_contract_milestone' => ! empty( $meta['is_contract_milestone'] ),
				'delivery_deadline'     => $row->delivery_deadline ?? null,
				'created_at'            => (string) $row->created_at,
				'completed_at'          => $row->completed_at ?? null,
				'is_locked'             => false, // overwritten in the lock-step pass below.
			);
		}

		usort(
			$decorated,
			static function ( array $a, array $b ): int {
				if ( $a['sort_order'] === $b['sort_order'] ) {
					return strcmp( $a['created_at'], $b['created_at'] );
				}
				return $a['sort_order'] <=> $b['sort_order'];
			}
		);

		// Lock-step gating: a pending_payment milestone is "locked" when any
		// earlier milestone (by sort_order) is still active. Buyer must close
		// out the prior phase (pay it, complete it, or cancel it) before the
		// next one becomes payable. Server-side guard in the checkout handler
		// enforces the same rule against URL tampering.
		$earlier_blocking = false;
		foreach ( $decorated as &$ms ) {
			if ( 'pending_payment' === $ms['status'] && $earlier_blocking ) {
				$ms['is_locked'] = true;
			}
			if ( ! in_array( $ms['status'], array( 'completed', 'cancelled' ), true ) ) {
				$earlier_blocking = true;
			}
		}
		unset( $ms );

		return $decorated;
	}

	/**
	 * Whether a specific milestone is currently locked from buyer payment by
	 * an earlier milestone in the same parent's lock-step sequence.
	 *
	 * Used by the checkout handler to reject `?pay_order=` URLs against
	 * out-of-order milestones — the visible UI hides those Pay buttons but
	 * a buyer could craft the URL by hand without a server-side check.
	 *
	 * @param int $milestone_id Sub-order ID.
	 * @return bool True if locked, false if either terminal or eligible to pay.
	 */
	public function is_locked( int $milestone_id ): bool {
		$sub = wpss_get_order( $milestone_id );
		if ( ! $sub || self::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return false;
		}
		$parent_id = (int) ( $sub->platform_order_id ?? 0 );
		if ( ! $parent_id ) {
			return false;
		}
		foreach ( $this->get_for_parent( $parent_id ) as $row ) {
			if ( (int) $row['id'] === $milestone_id ) {
				return (bool) $row['is_locked'];
			}
		}
		return false;
	}

	/**
	 * Small helper to decode a sub-order's meta JSON safely. Returns an
	 * empty array when the column is null, an empty string, or invalid
	 * JSON, so callers can merge into it unconditionally.
	 *
	 * @param ServiceOrder|object $sub Sub-order row.
	 * @return array<string, mixed>
	 */
	private function decode_meta( object $sub ): array {
		$raw = $sub->meta ?? '';
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
