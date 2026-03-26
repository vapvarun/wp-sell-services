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

defined( 'ABSPATH' ) || exit;

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
		$orders_table      = $wpdb->prefix . 'wpss_orders';
		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';

		// Get clearance_days setting — earnings from orders completed less than
		// clearance_days ago are not yet available for withdrawal.
		$payouts_settings = get_option( 'wpss_payouts', array() );
		$clearance_days   = (int) ( $payouts_settings['clearance_days'] ?? 14 );

		// Get completed orders earnings (uses vendor_earnings after commission).
		// COALESCE(vendor_earnings, 0) prevents inflated earnings when vendor_earnings is NULL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$completed = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as order_count,
					COALESCE(SUM(COALESCE(vendor_earnings, 0)), 0) as total_earned
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED
			)
		);

		// Get earnings still within the clearance window (completed less than clearance_days ago).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$in_clearance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(COALESCE(vendor_earnings, 0)), 0)
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = %s
				AND completed_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED,
				$clearance_days
			)
		);

		// Get pending earnings (orders in progress) — show vendor's expected share after commission.
		// Use CommissionService::get_global_commission_rate() for consistency with actual commission calculation.
		$commission_rate = CommissionService::get_global_commission_rate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(
					CASE WHEN vendor_earnings IS NOT NULL THEN vendor_earnings
					ELSE total * (1 - %f / 100) END
				), 0) FROM {$orders_table}
				WHERE vendor_id = %d AND status IN (%s, %s, %s)",
				$commission_rate,
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

		$total_earned       = (float) $completed->total_earned;
		$withdrawn          = (float) $withdrawn;
		$pending_withdrawal = (float) $pending_withdrawal;
		$available          = $total_earned - $withdrawn - $pending_withdrawal - $in_clearance;

		return array(
			'total_earned'       => $total_earned,
			'available_balance'  => max( 0, $available ),
			'pending_clearance'  => (float) $pending + $in_clearance,
			'in_clearance'       => $in_clearance,
			'withdrawn'          => $withdrawn,
			'pending_withdrawal' => $pending_withdrawal,
			'completed_orders'   => (int) $completed->order_count,
		);
	}

	/**
	 * Get earnings history.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Earnings records.
	 */
	public function get_history( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'limit'      => 20,
			'offset'     => 0,
			'start_date' => '',
			'end_date'   => '',
			'status'     => ServiceOrder::STATUS_COMPLETED,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'vendor_id = %d' );
		$params = array( $vendor_id );

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( $args['start_date'] ) {
			$where[]  = 'completed_at >= %s';
			$params[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where[]  = 'completed_at <= %s';
			$params[] = $args['end_date'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[]     = $args['limit'];
		$params[]     = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_number, service_id, total, vendor_earnings, commission_rate, platform_fee, status, completed_at, created_at
				FROM {$orders_table}
				WHERE {$where_clause}
				ORDER BY completed_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map(
			function ( $order ) {
				$service = get_post( $order->service_id );
				// Use vendor_earnings when available (commission calculated), fall back to total for older orders.
				$amount = null !== $order->vendor_earnings ? (float) $order->vendor_earnings : (float) $order->total;
				return array(
					'id'              => (int) $order->id,
					'order_number'    => $order->order_number,
					'service_name'    => $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ),
					'amount'          => $amount,
					'order_total'     => (float) $order->total,
					'commission_rate' => null !== $order->commission_rate ? (float) $order->commission_rate : null,
					'platform_fee'    => null !== $order->platform_fee ? (float) $order->platform_fee : null,
					'status'          => $order->status,
					'date'            => $order->completed_at ?: $order->created_at,
				);
			},
			$orders
		);
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
					COALESCE(SUM(COALESCE(vendor_earnings, 0)), 0) as earnings
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

		return array_map(
			function ( $row ) {
				return array(
					'period'   => $row->period,
					'orders'   => (int) $row->orders,
					'earnings' => (float) $row->earnings,
				);
			},
			$results
		);
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
	public function request_withdrawal( int $vendor_id, float $amount, string $method, array $details = array() ): array {
		// Check minimum withdrawal.
		$min_withdrawal = self::get_min_withdrawal_amount();
		if ( $amount < $min_withdrawal ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: minimum amount */
					__( 'Minimum withdrawal amount is %s.', 'wp-sell-services' ),
					wpss_format_price( $min_withdrawal )
				),
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		// Lock vendor's pending withdrawals to prevent double-withdrawal race conditions.
		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE vendor_id = %d AND status = 'pending' FOR UPDATE",
				$vendor_id
			)
		);

		$summary = $this->get_summary( $vendor_id );

		// Check available balance.
		if ( $amount > $summary['available_balance'] ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Insufficient balance for this withdrawal.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'vendor_id'  => $vendor_id,
				'amount'     => $amount,
				'method'     => sanitize_key( $method ),
				'details'    => wp_json_encode( $details ),
				'status'     => self::WITHDRAWAL_PENDING,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Failed to create withdrawal request.', 'wp-sell-services' ),
			);
		}

		$wpdb->query( 'COMMIT' );

		$withdrawal_id = (int) $wpdb->insert_id;

		// Notify admin (respects email settings).
		( new EmailService() )->send_withdrawal_notification( $vendor_id, $amount, $withdrawal_id );

		/**
		 * Fires when withdrawal is requested.
		 *
		 * @param int   $withdrawal_id Withdrawal ID.
		 * @param int   $vendor_id     Vendor user ID.
		 * @param float $amount        Amount.
		 */
		do_action( 'wpss_withdrawal_requested', $withdrawal_id, $vendor_id, $amount );

		return array(
			'success'       => true,
			'message'       => __( 'Withdrawal request submitted successfully.', 'wp-sell-services' ),
			'withdrawal_id' => $withdrawal_id,
		);
	}

	/**
	 * Get vendor withdrawals.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Withdrawals.
	 */
	public function get_withdrawals( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'vendor_id = %d' );
		$params = array( $vendor_id );

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[]     = $args['limit'];
		$params[]     = $args['offset'];

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

		return array_map(
			function ( $row ) {
				return array(
					'id'           => (int) $row->id,
					'amount'       => (float) $row->amount,
					'method'       => $row->method,
					'details'      => json_decode( $row->details, true ) ?: array(),
					'status'       => $row->status,
					'admin_note'   => $row->admin_note ?? '',
					'processed_at' => $row->processed_at,
					'created_at'   => $row->created_at,
				);
			},
			$withdrawals
		);
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

		$valid_statuses = array( self::WITHDRAWAL_APPROVED, self::WITHDRAWAL_COMPLETED, self::WITHDRAWAL_REJECTED );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid status.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $withdrawal_id )
		);

		if ( ! $withdrawal ) {
			return array(
				'success' => false,
				'message' => __( 'Withdrawal not found.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'status'       => $status,
				'admin_note'   => sanitize_textarea_field( $note ),
				'processed_at' => current_time( 'mysql' ),
				'processed_by' => get_current_user_id(),
			),
			array( 'id' => $withdrawal_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to update withdrawal.', 'wp-sell-services' ),
			);
		}

		// Notify vendor.
		$notification_service = new NotificationService();
		$status_labels        = self::get_withdrawal_statuses();

		$notification_service->create(
			(int) $withdrawal->vendor_id,
			'withdrawal_' . $status,
			__( 'Withdrawal Update', 'wp-sell-services' ),
			sprintf(
				/* translators: 1: amount, 2: status */
				__( 'Your withdrawal request for %1$s has been %2$s.', 'wp-sell-services' ),
				wpss_format_price( (float) $withdrawal->amount ),
				strtolower( $status_labels[ $status ] ?? $status )
			),
			array( 'withdrawal_id' => $withdrawal_id )
		);

		/**
		 * Fires when withdrawal is processed.
		 *
		 * @param int    $withdrawal_id Withdrawal ID.
		 * @param string $status        New status.
		 * @param object $withdrawal    Withdrawal data.
		 */
		do_action( 'wpss_withdrawal_processed', $withdrawal_id, $status, $withdrawal );

		return array(
			'success' => true,
			'message' => __( 'Withdrawal updated successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Get all pending withdrawals (admin).
	 *
	 * @param array $args Query arguments.
	 * @return array Withdrawals.
	 */
	public function get_all_withdrawals( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[]     = $args['limit'];
		$params[]     = $args['offset'];

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

		return array_map(
			function ( $row ) {
				return array(
					'id'           => (int) $row->id,
					'vendor_id'    => (int) $row->vendor_id,
					'vendor_name'  => $row->vendor_name,
					'amount'       => (float) $row->amount,
					'method'       => $row->method,
					'details'      => json_decode( $row->details, true ) ?: array(),
					'status'       => $row->status,
					'admin_note'   => $row->admin_note ?? '',
					'processed_at' => $row->processed_at,
					'created_at'   => $row->created_at,
				);
			},
			$withdrawals
		);
	}

	/**
	 * Get withdrawal methods.
	 *
	 * @return array Available methods.
	 */
	public static function get_withdrawal_methods(): array {
		$methods = array(
			'paypal'        => __( 'PayPal', 'wp-sell-services' ),
			'bank_transfer' => __( 'Bank Transfer', 'wp-sell-services' ),
		);

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
		return array(
			self::WITHDRAWAL_PENDING   => __( 'Pending', 'wp-sell-services' ),
			self::WITHDRAWAL_APPROVED  => __( 'Approved', 'wp-sell-services' ),
			self::WITHDRAWAL_COMPLETED => __( 'Completed', 'wp-sell-services' ),
			self::WITHDRAWAL_REJECTED  => __( 'Rejected', 'wp-sell-services' ),
		);
	}

	/**
	 * Get minimum withdrawal amount from settings.
	 *
	 * Centralized helper to get the min withdrawal amount from the correct option.
	 *
	 * @return float Minimum withdrawal amount.
	 */
	public static function get_min_withdrawal_amount(): float {
		// Primary location: wpss_payouts (new structure).
		$payouts_settings = get_option( 'wpss_payouts', array() );
		if ( isset( $payouts_settings['min_withdrawal'] ) ) {
			return (float) $payouts_settings['min_withdrawal'];
		}

		// Fallback: wpss_vendor (old structure for backward compatibility).
		$vendor_settings = get_option( 'wpss_vendor', array() );
		if ( isset( $vendor_settings['min_payout_amount'] ) ) {
			return (float) $vendor_settings['min_payout_amount'];
		}

		// Default.
		return 50.0;
	}

	/**
	 * Check if auto withdrawal is enabled.
	 *
	 * @return bool True if auto withdrawal is enabled.
	 */
	public static function is_auto_withdrawal_enabled(): bool {
		$payouts_settings = get_option( 'wpss_payouts', array() );
		return ! empty( $payouts_settings['auto_withdrawal_enabled'] );
	}

	/**
	 * Get auto withdrawal threshold.
	 *
	 * @return float Threshold amount.
	 */
	public static function get_auto_withdrawal_threshold(): float {
		$payouts_settings = get_option( 'wpss_payouts', array() );
		return (float) ( $payouts_settings['auto_withdrawal_threshold'] ?? 500 );
	}

	/**
	 * Get auto withdrawal schedule.
	 *
	 * @return string Schedule (weekly, biweekly, or monthly).
	 */
	public static function get_auto_withdrawal_schedule(): string {
		$payouts_settings = get_option( 'wpss_payouts', array() );
		$schedule         = $payouts_settings['auto_withdrawal_schedule'] ?? 'monthly';
		return in_array( $schedule, array( 'weekly', 'biweekly', 'monthly' ), true ) ? $schedule : 'monthly';
	}

	/**
	 * Get vendors eligible for auto withdrawal.
	 *
	 * Returns vendors whose available balance exceeds the threshold.
	 *
	 * @return array Array of vendor data with id and balance.
	 */
	public function get_eligible_vendors_for_auto_withdrawal(): array {
		global $wpdb;
		$orders_table      = $wpdb->prefix . 'wpss_orders';
		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';

		$threshold = self::get_auto_withdrawal_threshold();

		// Get all vendors with completed orders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$vendors = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT vendor_id FROM {$orders_table} WHERE status = %s",
				ServiceOrder::STATUS_COMPLETED
			)
		);

		$eligible = array();

		foreach ( $vendors as $vendor_id ) {
			$summary = $this->get_summary( (int) $vendor_id );

			if ( $summary['available_balance'] >= $threshold ) {
				// Check if vendor has payout method configured.
				$payout_method  = get_user_meta( (int) $vendor_id, 'wpss_payout_method', true );
				$payout_details = get_user_meta( (int) $vendor_id, 'wpss_payout_details', true );

				if ( $payout_method && $payout_details ) {
					$eligible[] = array(
						'vendor_id'         => (int) $vendor_id,
						'available_balance' => $summary['available_balance'],
						'payout_method'     => $payout_method,
						'payout_details'    => is_array( $payout_details ) ? $payout_details : array(),
					);
				}
			}
		}

		return $eligible;
	}

	/**
	 * Process automatic withdrawals.
	 *
	 * Creates withdrawal requests for eligible vendors.
	 *
	 * @return array Processing results.
	 */
	public function process_auto_withdrawals(): array {
		if ( ! self::is_auto_withdrawal_enabled() ) {
			return array(
				'success'   => false,
				'message'   => __( 'Auto withdrawal is not enabled.', 'wp-sell-services' ),
				'processed' => 0,
			);
		}

		$eligible = $this->get_eligible_vendors_for_auto_withdrawal();

		if ( empty( $eligible ) ) {
			return array(
				'success'   => true,
				'message'   => __( 'No vendors eligible for auto withdrawal.', 'wp-sell-services' ),
				'processed' => 0,
			);
		}

		$processed = 0;
		$failed    = 0;
		$results   = array();

		foreach ( $eligible as $vendor ) {
			// Create automatic withdrawal request.
			$result = $this->create_auto_withdrawal(
				$vendor['vendor_id'],
				$vendor['available_balance'],
				$vendor['payout_method'],
				$vendor['payout_details']
			);

			if ( $result['success'] ) {
				++$processed;
			} else {
				++$failed;
			}

			$results[] = array(
				'vendor_id' => $vendor['vendor_id'],
				'amount'    => $vendor['available_balance'],
				'success'   => $result['success'],
				'message'   => $result['message'],
			);
		}

		// Log the auto withdrawal run.
		update_option(
			'wpss_last_auto_withdrawal_run',
			array(
				'timestamp' => current_time( 'mysql' ),
				'processed' => $processed,
				'failed'    => $failed,
			)
		);

		return array(
			'success'   => true,
			'message'   => sprintf(
				/* translators: 1: processed count, 2: failed count */
				__( 'Auto withdrawal completed. Processed: %1$d, Failed: %2$d.', 'wp-sell-services' ),
				$processed,
				$failed
			),
			'processed' => $processed,
			'failed'    => $failed,
			'details'   => $results,
		);
	}

	/**
	 * Create automatic withdrawal request.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param float  $amount    Amount to withdraw.
	 * @param string $method    Withdrawal method.
	 * @param array  $details   Method details (account info).
	 * @return array Result with success status.
	 */
	private function create_auto_withdrawal( int $vendor_id, float $amount, string $method, array $details = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		// Check for any pending auto withdrawal for this vendor.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE vendor_id = %d AND status IN (%s, %s) AND is_auto = 1",
				$vendor_id,
				self::WITHDRAWAL_PENDING,
				self::WITHDRAWAL_APPROVED
			)
		);

		if ( $existing > 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Vendor already has a pending auto withdrawal.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'vendor_id'  => $vendor_id,
				'amount'     => $amount,
				'method'     => sanitize_key( $method ),
				'details'    => wp_json_encode( $details ),
				'status'     => self::WITHDRAWAL_PENDING,
				'is_auto'    => 1,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create auto withdrawal request.', 'wp-sell-services' ),
			);
		}

		$withdrawal_id = (int) $wpdb->insert_id;

		// Notify vendor.
		$notification_service = new NotificationService();
		$notification_service->create(
			$vendor_id,
			'auto_withdrawal_created',
			__( 'Auto Withdrawal Created', 'wp-sell-services' ),
			sprintf(
				/* translators: %s: amount */
				__( 'An automatic withdrawal of %s has been scheduled based on your payout settings.', 'wp-sell-services' ),
				wpss_format_price( $amount )
			),
			array( 'withdrawal_id' => $withdrawal_id )
		);

		// Notify admin (respects email settings).
		( new EmailService() )->send_withdrawal_notification( $vendor_id, $amount, $withdrawal_id, true );

		/**
		 * Fires when auto withdrawal is created.
		 *
		 * @param int   $withdrawal_id Withdrawal ID.
		 * @param int   $vendor_id     Vendor user ID.
		 * @param float $amount        Amount.
		 */
		do_action( 'wpss_auto_withdrawal_created', $withdrawal_id, $vendor_id, $amount );

		return array(
			'success'       => true,
			'message'       => __( 'Auto withdrawal request created successfully.', 'wp-sell-services' ),
			'withdrawal_id' => $withdrawal_id,
		);
	}

	/**
	 * Register custom cron schedules for auto withdrawals.
	 *
	 * Must be called before wp_schedule_event() uses 'biweekly'.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['biweekly'] = array(
			'interval' => 14 * DAY_IN_SECONDS,
			'display'  => __( 'Every 14 Days (Bi-weekly)', 'wp-sell-services' ),
		);
		return $schedules;
	}

	/**
	 * Schedule auto withdrawal cron job.
	 *
	 * @return void
	 */
	public static function schedule_auto_withdrawal_cron(): void {
		// Ensure biweekly schedule is registered before scheduling.
		add_filter( 'cron_schedules', array( self::class, 'add_cron_schedules' ) );

		if ( ! self::is_auto_withdrawal_enabled() ) {
			wp_clear_scheduled_hook( 'wpss_process_auto_withdrawals' );
			return;
		}

		$schedule = self::get_auto_withdrawal_schedule();

		// Clear existing schedule.
		wp_clear_scheduled_hook( 'wpss_process_auto_withdrawals' );

		// Schedule based on settings.
		if ( ! wp_next_scheduled( 'wpss_process_auto_withdrawals' ) ) {
			// Schedule for 1st of month (monthly), 1st/15th (biweekly), or Monday (weekly) at 2 AM.
			$timestamp = self::get_next_schedule_time( $schedule );
			wp_schedule_event( $timestamp, $schedule, 'wpss_process_auto_withdrawals' );
		}
	}

	/**
	 * Get next schedule time for auto withdrawal.
	 *
	 * @param string $schedule Schedule type (weekly, biweekly, or monthly).
	 * @return int Unix timestamp for next run.
	 */
	private static function get_next_schedule_time( string $schedule ): int {
		$timezone = wp_timezone();
		$now      = new \DateTime( 'now', $timezone );

		if ( 'monthly' === $schedule ) {
			// First day of next month at 2 AM.
			$next = new \DateTime( 'first day of next month 02:00:00', $timezone );
		} elseif ( 'biweekly' === $schedule ) {
			// Next 1st or 15th of the month at 2 AM.
			$day = (int) $now->format( 'j' );
			if ( $day < 15 ) {
				$next = new \DateTime( $now->format( 'Y-m' ) . '-15 02:00:00', $timezone );
			} else {
				$next = new \DateTime( 'first day of next month 02:00:00', $timezone );
			}
		} else {
			// Next Monday at 2 AM.
			$next = new \DateTime( 'next monday 02:00:00', $timezone );
		}

		return $next->getTimestamp();
	}

	/**
	 * Unschedule auto withdrawal cron job.
	 *
	 * @return void
	 */
	public static function unschedule_auto_withdrawal_cron(): void {
		wp_clear_scheduled_hook( 'wpss_process_auto_withdrawals' );
	}
}
