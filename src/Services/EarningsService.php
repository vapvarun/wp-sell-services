<?php
/**
 * Earnings Service
 *
 * Handles vendor earnings, withdrawals, and payout tracking.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages vendor earnings and payouts.
 *
 * @since 1.0.0
 */
class EarningsService {

	/**
	 * Withdrawal statuses.
	 */
	public const WITHDRAWAL_PENDING   = 'pending';
	public const WITHDRAWAL_APPROVED  = 'approved';
	public const WITHDRAWAL_COMPLETED = 'completed';
	public const WITHDRAWAL_REJECTED  = 'rejected';

	/**
	 * Get vendor earnings summary.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array Earnings summary.
	 */
	public function get_summary( int $vendor_id ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';

		// Get completed orders earnings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$completed = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as order_count,
					COALESCE(SUM(total), 0) as total_earned
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED
			)
		);

		// Get pending earnings (orders in progress).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total), 0) FROM {$orders_table}
				WHERE vendor_id = %d AND status IN (%s, %s, %s)",
				$vendor_id,
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_REVISION_REQUESTED
			)
		);

		// Get withdrawn amount.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawn = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$withdrawals_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				self::WITHDRAWAL_COMPLETED
			)
		);

		// Get pending withdrawals.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_withdrawal = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$withdrawals_table}
				WHERE vendor_id = %d AND status IN (%s, %s)",
				$vendor_id,
				self::WITHDRAWAL_PENDING,
				self::WITHDRAWAL_APPROVED
			)
		);

		$total_earned = (float) $completed->total_earned;
		$withdrawn = (float) $withdrawn;
		$pending_withdrawal = (float) $pending_withdrawal;
		$available = $total_earned - $withdrawn - $pending_withdrawal;

		return [
			'total_earned'       => $total_earned,
			'available_balance'  => max( 0, $available ),
			'pending_clearance'  => (float) $pending,
			'withdrawn'          => $withdrawn,
			'pending_withdrawal' => $pending_withdrawal,
			'completed_orders'   => (int) $completed->order_count,
		];
	}

	/**
	 * Get earnings history.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Earnings records.
	 */
	public function get_history( int $vendor_id, array $args = [] ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$defaults = [
			'limit'      => 20,
			'offset'     => 0,
			'start_date' => '',
			'end_date'   => '',
			'status'     => ServiceOrder::STATUS_COMPLETED,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'vendor_id = %d' ];
		$params = [ $vendor_id ];

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		if ( $args['start_date'] ) {
			$where[] = 'completed_at >= %s';
			$params[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where[] = 'completed_at <= %s';
			$params[] = $args['end_date'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_number, service_id, total, status, completed_at, created_at
				FROM {$orders_table}
				WHERE {$where_clause}
				ORDER BY completed_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map( function( $order ) {
			$service = get_post( $order->service_id );
			return [
				'id'           => (int) $order->id,
				'order_number' => $order->order_number,
				'service_name' => $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ),
				'amount'       => (float) $order->total,
				'status'       => $order->status,
				'date'         => $order->completed_at ?: $order->created_at,
			];
		}, $orders );
	}

	/**
	 * Get earnings by period.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $period    Period (day, week, month, year).
	 * @param int    $count     Number of periods.
	 * @return array Earnings by period.
	 */
	public function get_by_period( int $vendor_id, string $period = 'month', int $count = 12 ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$format = match ( $period ) {
			'day'   => '%Y-%m-%d',
			'week'  => '%Y-%u',
			'month' => '%Y-%m',
			'year'  => '%Y',
			default => '%Y-%m',
		};

		$interval = match ( $period ) {
			'day'   => 'DAY',
			'week'  => 'WEEK',
			'month' => 'MONTH',
			'year'  => 'YEAR',
			default => 'MONTH',
		};

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(completed_at, %s) as period,
					COUNT(*) as orders,
					COALESCE(SUM(total), 0) as earnings
				FROM {$orders_table}
				WHERE vendor_id = %d
				AND status = %s
				AND completed_at >= DATE_SUB(NOW(), INTERVAL %d {$interval})
				GROUP BY period
				ORDER BY period DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$format,
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED,
				$count
			)
		);

		return array_map( function( $row ) {
			return [
				'period'   => $row->period,
				'orders'   => (int) $row->orders,
				'earnings' => (float) $row->earnings,
			];
		}, $results );
	}

	/**
	 * Request withdrawal.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param float  $amount    Amount to withdraw.
	 * @param string $method    Withdrawal method.
	 * @param array  $details   Method details (account info).
	 * @return array Result with success status.
	 */
	public function request_withdrawal( int $vendor_id, float $amount, string $method, array $details = [] ): array {
		$summary = $this->get_summary( $vendor_id );

		// Check minimum withdrawal.
		$min_withdrawal = (float) get_option( 'wpss_min_withdrawal', 50 );
		if ( $amount < $min_withdrawal ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: minimum amount */
					__( 'Minimum withdrawal amount is %s.', 'wp-sell-services' ),
					wpss_format_price( $min_withdrawal )
				),
			];
		}

		// Check available balance.
		if ( $amount > $summary['available_balance'] ) {
			return [
				'success' => false,
				'message' => __( 'Insufficient balance for this withdrawal.', 'wp-sell-services' ),
			];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			[
				'vendor_id'   => $vendor_id,
				'amount'      => $amount,
				'method'      => sanitize_key( $method ),
				'details'     => wp_json_encode( $details ),
				'status'      => self::WITHDRAWAL_PENDING,
				'created_at'  => current_time( 'mysql' ),
			],
			[ '%d', '%f', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to create withdrawal request.', 'wp-sell-services' ),
			];
		}

		$withdrawal_id = $wpdb->insert_id;

		// Notify admin.
		$admin_email = get_option( 'admin_email' );
		$vendor = get_user_by( 'id', $vendor_id );

		wp_mail(
			$admin_email,
			__( '[WP Sell Services] New Withdrawal Request', 'wp-sell-services' ),
			sprintf(
				/* translators: 1: vendor name, 2: amount */
				__( 'Vendor %1$s has requested a withdrawal of %2$s. Please review in the admin panel.', 'wp-sell-services' ),
				$vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ),
				wpss_format_price( $amount )
			)
		);

		/**
		 * Fires when withdrawal is requested.
		 *
		 * @param int   $withdrawal_id Withdrawal ID.
		 * @param int   $vendor_id     Vendor user ID.
		 * @param float $amount        Amount.
		 */
		do_action( 'wpss_withdrawal_requested', $withdrawal_id, $vendor_id, $amount );

		return [
			'success'       => true,
			'message'       => __( 'Withdrawal request submitted successfully.', 'wp-sell-services' ),
			'withdrawal_id' => $withdrawal_id,
		];
	}

	/**
	 * Get vendor withdrawals.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Withdrawals.
	 */
	public function get_withdrawals( int $vendor_id, array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = [
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'vendor_id = %d' ];
		$params = [ $vendor_id ];

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE {$where_clause}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map( function( $row ) {
			return [
				'id'           => (int) $row->id,
				'amount'       => (float) $row->amount,
				'method'       => $row->method,
				'details'      => json_decode( $row->details, true ) ?: [],
				'status'       => $row->status,
				'admin_note'   => $row->admin_note ?? '',
				'processed_at' => $row->processed_at,
				'created_at'   => $row->created_at,
			];
		}, $withdrawals );
	}

	/**
	 * Process withdrawal (admin).
	 *
	 * @param int    $withdrawal_id Withdrawal ID.
	 * @param string $status        New status.
	 * @param string $note          Admin note.
	 * @return array Result.
	 */
	public function process_withdrawal( int $withdrawal_id, string $status, string $note = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$valid_statuses = [ self::WITHDRAWAL_APPROVED, self::WITHDRAWAL_COMPLETED, self::WITHDRAWAL_REJECTED ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid status.', 'wp-sell-services' ),
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $withdrawal_id )
		);

		if ( ! $withdrawal ) {
			return [
				'success' => false,
				'message' => __( 'Withdrawal not found.', 'wp-sell-services' ),
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[
				'status'       => $status,
				'admin_note'   => sanitize_textarea_field( $note ),
				'processed_at' => current_time( 'mysql' ),
				'processed_by' => get_current_user_id(),
			],
			[ 'id' => $withdrawal_id ],
			[ '%s', '%s', '%s', '%d' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to update withdrawal.', 'wp-sell-services' ),
			];
		}

		// Notify vendor.
		$notification_service = new NotificationService();
		$status_labels = self::get_withdrawal_statuses();

		$notification_service->send(
			(int) $withdrawal->vendor_id,
			'withdrawal_' . $status,
			__( 'Withdrawal Update', 'wp-sell-services' ),
			sprintf(
				/* translators: 1: amount, 2: status */
				__( 'Your withdrawal request for %1$s has been %2$s.', 'wp-sell-services' ),
				wpss_format_price( $withdrawal->amount ),
				strtolower( $status_labels[ $status ] ?? $status )
			),
			[ 'withdrawal_id' => $withdrawal_id ]
		);

		/**
		 * Fires when withdrawal is processed.
		 *
		 * @param int    $withdrawal_id Withdrawal ID.
		 * @param string $status        New status.
		 * @param object $withdrawal    Withdrawal data.
		 */
		do_action( 'wpss_withdrawal_processed', $withdrawal_id, $status, $withdrawal );

		return [
			'success' => true,
			'message' => __( 'Withdrawal updated successfully.', 'wp-sell-services' ),
		];
	}

	/**
	 * Get all pending withdrawals (admin).
	 *
	 * @param array $args Query arguments.
	 * @return array Withdrawals.
	 */
	public function get_all_withdrawals( array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = [
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ '1=1' ];
		$params = [];

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.*, u.display_name as vendor_name
				FROM {$table} w
				LEFT JOIN {$wpdb->users} u ON w.vendor_id = u.ID
				WHERE {$where_clause}
				ORDER BY w.created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map( function( $row ) {
			return [
				'id'           => (int) $row->id,
				'vendor_id'    => (int) $row->vendor_id,
				'vendor_name'  => $row->vendor_name,
				'amount'       => (float) $row->amount,
				'method'       => $row->method,
				'details'      => json_decode( $row->details, true ) ?: [],
				'status'       => $row->status,
				'admin_note'   => $row->admin_note ?? '',
				'processed_at' => $row->processed_at,
				'created_at'   => $row->created_at,
			];
		}, $withdrawals );
	}

	/**
	 * Get withdrawal methods.
	 *
	 * @return array Available methods.
	 */
	public static function get_withdrawal_methods(): array {
		$methods = [
			'paypal'        => __( 'PayPal', 'wp-sell-services' ),
			'bank_transfer' => __( 'Bank Transfer', 'wp-sell-services' ),
		];

		/**
		 * Filter withdrawal methods.
		 *
		 * @param array $methods Available methods.
		 */
		return apply_filters( 'wpss_withdrawal_methods', $methods );
	}

	/**
	 * Get withdrawal statuses.
	 *
	 * @return array Status labels.
	 */
	public static function get_withdrawal_statuses(): array {
		return [
			self::WITHDRAWAL_PENDING   => __( 'Pending', 'wp-sell-services' ),
			self::WITHDRAWAL_APPROVED  => __( 'Approved', 'wp-sell-services' ),
			self::WITHDRAWAL_COMPLETED => __( 'Completed', 'wp-sell-services' ),
			self::WITHDRAWAL_REJECTED  => __( 'Rejected', 'wp-sell-services' ),
		];
	}
}
