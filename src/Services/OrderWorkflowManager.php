<?php
/**
 * Order Workflow Manager
 *
 * Handles automated order status transitions, cron jobs, and workflow rules.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\VendorProfile;

/**
 * Manages order workflow automation.
 *
 * @since 1.0.0
 */
class OrderWorkflowManager {

	/**
	 * Order service instance.
	 *
	 * @var OrderService
	 */
	private OrderService $order_service;

	/**
	 * Notification service instance.
	 *
	 * @var NotificationService
	 */
	private NotificationService $notification_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order_service        = new OrderService();
		$this->notification_service = new NotificationService();
	}

	/**
	 * Initialize workflow hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register cron schedules.
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

		// Schedule cron events.
		add_action( 'init', [ $this, 'schedule_cron_events' ] );

		// Cron handlers.
		add_action( 'wpss_check_late_orders', [ $this, 'check_late_orders' ] );
		add_action( 'wpss_auto_complete_orders', [ $this, 'auto_complete_orders' ] );
		add_action( 'wpss_send_deadline_reminders', [ $this, 'send_deadline_reminders' ] );
		add_action( 'wpss_send_requirements_reminders', [ $this, 'send_requirements_reminders' ] );
		add_action( 'wpss_check_requirements_timeout', [ $this, 'check_requirements_timeout' ] );
		add_action( 'wpss_recalculate_seller_levels', [ $this, 'recalculate_seller_levels' ] );
		add_action( 'wpss_process_cancellation_timeouts', [ $this, 'process_cancellation_timeouts' ] );
		add_action( 'wpss_cleanup_expired_requests', [ $this, 'cleanup_expired_requests' ] );
		add_action( 'wpss_update_vendor_stats', [ $this, 'update_vendor_stats' ] );

		// Status change hooks.
		add_action( 'wpss_order_status_changed', [ $this, 'handle_status_change' ], 10, 3 );
		add_action( 'wpss_order_status_completed', [ $this, 'handle_order_completed' ], 10, 2 );
		add_action( 'wpss_order_status_cancelled', [ $this, 'handle_order_cancelled' ], 10, 2 );
		add_action( 'wpss_order_status_cancellation_requested', [ $this, 'handle_cancellation_requested' ], 10, 2 );

		// Log status changes to conversation system messages.
		// This ensures changes made via ServiceOrder::update() (REST API path) also
		// get logged. The static deduplication in log_status_change() prevents double
		// messages when OrderService::update_status() already logged the change.
		add_action( 'wpss_order_status_changed', [ $this->order_service, 'log_status_change' ], 5, 3 );

		// Payment hooks.
		add_action( 'wpss_order_paid', [ $this, 'handle_payment_complete' ], 10, 2 );

		// Set delivery deadline when requirements are submitted (real clock start).
		add_action( 'wpss_requirements_submitted', [ $this->order_service, 'set_deadline_on_requirements' ], 10, 3 );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['wpss_hourly'] = [
			'interval' => HOUR_IN_SECONDS,
			'display'  => 'Every Hour (WPSS)',
		];

		$schedules['wpss_twice_daily'] = [
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => 'Twice Daily (WPSS)',
		];

		$schedules['wpss_weekly'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => 'Once Weekly (WPSS)',
		];

		$schedules['weekly'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => 'Weekly',
		];

		$schedules['monthly'] = [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'Monthly',
		];

		return $schedules;
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	public function schedule_cron_events(): void {
		if ( ! wp_next_scheduled( 'wpss_check_late_orders' ) ) {
			wp_schedule_event( time(), 'wpss_hourly', 'wpss_check_late_orders' );
		}

		if ( ! wp_next_scheduled( 'wpss_auto_complete_orders' ) ) {
			wp_schedule_event( time(), 'wpss_twice_daily', 'wpss_auto_complete_orders' );
		}

		if ( ! wp_next_scheduled( 'wpss_send_deadline_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_send_deadline_reminders' );
		}

		if ( ! wp_next_scheduled( 'wpss_send_requirements_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_send_requirements_reminders' );
		}

		if ( ! wp_next_scheduled( 'wpss_check_requirements_timeout' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_check_requirements_timeout' );
		}

		if ( ! wp_next_scheduled( 'wpss_recalculate_seller_levels' ) ) {
			wp_schedule_event( time(), 'wpss_weekly', 'wpss_recalculate_seller_levels' );
		}

		if ( ! wp_next_scheduled( 'wpss_process_cancellation_timeouts' ) ) {
			wp_schedule_event( time(), 'wpss_hourly', 'wpss_process_cancellation_timeouts' );
		}

		if ( ! wp_next_scheduled( 'wpss_cleanup_expired_requests' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_cleanup_expired_requests' );
		}

		if ( ! wp_next_scheduled( 'wpss_update_vendor_stats' ) ) {
			wp_schedule_event( time(), 'wpss_twice_daily', 'wpss_update_vendor_stats' );
		}
	}

	/**
	 * Check for late orders and update their status.
	 *
	 * @return void
	 */
	public function check_late_orders(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Find orders past deadline that are still in progress.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$late_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, vendor_id, customer_id FROM {$table}
				WHERE status = %s
				AND delivery_deadline < %s",
				ServiceOrder::STATUS_IN_PROGRESS,
				current_time( 'mysql' )
			)
		);

		foreach ( $late_orders as $order ) {
			$this->order_service->update_status(
				(int) $order->id,
				ServiceOrder::STATUS_LATE,
				__( 'Order marked as late - deadline exceeded', 'wp-sell-services' )
			);

			// Notify vendor.
			$this->notification_service->create(
				(int) $order->vendor_id,
				'order_late',
				__( 'Order Overdue', 'wp-sell-services' ),
				__( 'Your order is past the delivery deadline. Please deliver as soon as possible.', 'wp-sell-services' ),
				[ 'order_id' => $order->id ]
			);

			// Notify customer.
			$this->notification_service->create(
				(int) $order->customer_id,
				'order_late',
				__( 'Order Delayed', 'wp-sell-services' ),
				__( 'Your order is past the expected delivery date. We have notified the vendor.', 'wp-sell-services' ),
				[ 'order_id' => $order->id ]
			);
		}
	}

	/**
	 * Auto-complete orders after configured days.
	 *
	 * @return void
	 */
	public function auto_complete_orders(): void {
		$auto_complete_days = (int) get_option( 'wpss_auto_complete_days', 3 );

		if ( $auto_complete_days <= 0 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';
		$deliveries_table = $wpdb->prefix . 'wpss_deliveries';

		// Find orders pending approval with delivery older than X days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orders_to_complete = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.id, o.customer_id, o.vendor_id
				FROM {$table} o
				INNER JOIN {$deliveries_table} d ON d.order_id = o.id
				WHERE o.status = %s
				AND d.status = 'pending'
				AND d.created_at < DATE_SUB(%s, INTERVAL %d DAY)
				GROUP BY o.id",
				ServiceOrder::STATUS_PENDING_APPROVAL,
				current_time( 'mysql' ),
				$auto_complete_days
			)
		);

		foreach ( $orders_to_complete as $order ) {
			$this->order_service->update_status(
				(int) $order->id,
				ServiceOrder::STATUS_COMPLETED,
				sprintf(
					/* translators: %d: number of days */
					__( 'Order auto-completed after %d days without response', 'wp-sell-services' ),
					$auto_complete_days
				)
			);

			// Notify customer.
			$this->notification_service->create(
				(int) $order->customer_id,
				'order_auto_completed',
				__( 'Order Auto-Completed', 'wp-sell-services' ),
				sprintf(
					/* translators: %d: number of days */
					__( 'Your order has been automatically completed after %d days. You can still leave a review.', 'wp-sell-services' ),
					$auto_complete_days
				),
				[ 'order_id' => $order->id ]
			);
		}
	}

	/**
	 * Send deadline reminders to vendors.
	 *
	 * @return void
	 */
	public function send_deadline_reminders(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Find orders due within 24 hours.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$upcoming_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, vendor_id, delivery_deadline FROM {$table}
				WHERE status = %s
				AND delivery_deadline BETWEEN %s AND DATE_ADD(%s, INTERVAL 24 HOUR)",
				ServiceOrder::STATUS_IN_PROGRESS,
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);

		foreach ( $upcoming_orders as $order ) {
			$deadline = new \DateTime( $order->delivery_deadline );
			$hours_left = max( 0, ( $deadline->getTimestamp() - time() ) / 3600 );

			$this->notification_service->create(
				(int) $order->vendor_id,
				'deadline_reminder',
				__( 'Deadline Approaching', 'wp-sell-services' ),
				sprintf(
					/* translators: %d: hours remaining */
					__( 'Your order deadline is in %d hours. Please ensure timely delivery.', 'wp-sell-services' ),
					(int) $hours_left
				),
				[ 'order_id' => $order->id ]
			);
		}
	}

	/**
	 * Send reminders to buyers who haven't submitted requirements.
	 *
	 * Sends reminders at day 1, 3, and 5 after purchase.
	 *
	 * @return void
	 */
	public function send_requirements_reminders(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Find orders stuck in pending_requirements status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, vendor_id, created_at FROM {$table}
				WHERE status = %s
				AND created_at < DATE_SUB(%s, INTERVAL 1 DAY)",
				ServiceOrder::STATUS_PENDING_REQUIREMENTS,
				current_time( 'mysql' )
			)
		);

		foreach ( $pending_orders as $order ) {
			$days_since_order = (int) floor( ( time() - strtotime( $order->created_at ) ) / DAY_IN_SECONDS );
			$reminder_key     = 'wpss_requirements_reminder_' . $order->id;
			$reminders_sent   = (int) get_option( $reminder_key, 0 );

			// Determine which reminder to send based on days elapsed.
			$should_send = false;
			$message     = '';
			$subject     = '';

			if ( $days_since_order >= 1 && $reminders_sent < 1 ) {
				$should_send = true;
				$subject     = __( 'Submit Your Requirements', 'wp-sell-services' );
				$message     = __( 'Your vendor is waiting for your project requirements. Please submit them so work can begin.', 'wp-sell-services' );
			} elseif ( $days_since_order >= 3 && $reminders_sent < 2 ) {
				$should_send = true;
				$subject     = __( 'Reminder: Requirements Needed', 'wp-sell-services' );
				$message     = __( 'Your vendor is still waiting for requirements. Please submit them to avoid delays.', 'wp-sell-services' );
			} elseif ( $days_since_order >= 5 && $reminders_sent < 3 ) {
				$should_send = true;
				$subject     = __( 'Final Reminder: Action Required', 'wp-sell-services' );
				$message     = __( 'This is your final reminder. Please submit your requirements or contact the vendor if you need assistance.', 'wp-sell-services' );
			}

			if ( $should_send ) {
				$reminder_num = $reminders_sent + 1;

				// Send notification to buyer.
				$this->notification_service->create(
					(int) $order->customer_id,
					'requirements_reminder',
					$subject,
					$message,
					[ 'order_id' => $order->id ]
				);

				// Trigger email via EmailService.
				do_action( 'wpss_send_requirements_reminder_email', (int) $order->id, $reminder_num, $message );

				// Update reminder count.
				update_option( $reminder_key, $reminders_sent + 1, false );

				// Also notify vendor on first reminder.
				if ( 0 === $reminders_sent ) {
					$this->notification_service->create(
						(int) $order->vendor_id,
						'requirements_pending',
						__( 'Buyer Requirements Pending', 'wp-sell-services' ),
						__( 'The buyer has not yet submitted requirements. We have sent them a reminder.', 'wp-sell-services' ),
						[ 'order_id' => $order->id ]
					);
				}
			}
		}
	}

	/**
	 * Auto-start orders when requirements timeout is reached.
	 *
	 * If enabled, orders stuck in pending_requirements beyond the configured
	 * timeout will be automatically transitioned to in_progress.
	 *
	 * @return void
	 */
	public function check_requirements_timeout(): void {
		$order_settings = get_option( 'wpss_orders', [] );
		$timeout_days   = (int) ( $order_settings['requirements_timeout_days'] ?? 0 );

		if ( $timeout_days <= 0 ) {
			return;
		}

		$auto_start = ! empty( $order_settings['auto_start_on_timeout'] );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Find orders stuck in pending_requirements past the timeout.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$timed_out_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, vendor_id FROM {$table}
				WHERE status = %s
				AND created_at < DATE_SUB(%s, INTERVAL %d DAY)",
				ServiceOrder::STATUS_PENDING_REQUIREMENTS,
				current_time( 'mysql' ),
				$timeout_days
			)
		);

		foreach ( $timed_out_orders as $order ) {
			$order_id = (int) $order->id;

			if ( $auto_start ) {
				// Auto-start the order without requirements.
				$this->order_service->update_status(
					$order_id,
					ServiceOrder::STATUS_IN_PROGRESS,
					sprintf(
						/* translators: %d: number of days */
						__( 'Order auto-started after %d days without requirements submission', 'wp-sell-services' ),
						$timeout_days
					)
				);

				$this->notification_service->create(
					(int) $order->vendor_id,
					'requirements_timeout',
					__( 'Order Auto-Started', 'wp-sell-services' ),
					sprintf(
						/* translators: %d: number of days */
						__( 'The buyer did not submit requirements within %d days. The order has been auto-started. You may contact the buyer for details.', 'wp-sell-services' ),
						$timeout_days
					),
					[ 'order_id' => $order_id ]
				);

				$this->notification_service->create(
					(int) $order->customer_id,
					'requirements_timeout',
					__( 'Order Started Without Requirements', 'wp-sell-services' ),
					sprintf(
						/* translators: %d: number of days */
						__( 'You did not submit requirements within %d days. The order has been started. Please contact the vendor with your project details.', 'wp-sell-services' ),
						$timeout_days
					),
					[ 'order_id' => $order_id ]
				);
			} else {
				// Cancel the order instead.
				$this->order_service->update_status(
					$order_id,
					ServiceOrder::STATUS_CANCELLED,
					sprintf(
						/* translators: %d: number of days */
						__( 'Order cancelled - requirements not submitted within %d days', 'wp-sell-services' ),
						$timeout_days
					)
				);

				$this->notification_service->create(
					(int) $order->customer_id,
					'requirements_timeout_cancelled',
					__( 'Order Cancelled', 'wp-sell-services' ),
					sprintf(
						/* translators: %d: number of days */
						__( 'Your order was cancelled because requirements were not submitted within %d days.', 'wp-sell-services' ),
						$timeout_days
					),
					[ 'order_id' => $order_id ]
				);

				$this->notification_service->create(
					(int) $order->vendor_id,
					'requirements_timeout_cancelled',
					__( 'Order Cancelled', 'wp-sell-services' ),
					sprintf(
						/* translators: %d: number of days */
						__( 'An order was cancelled because the buyer did not submit requirements within %d days.', 'wp-sell-services' ),
						$timeout_days
					),
					[ 'order_id' => $order_id ]
				);
			}

			/**
			 * Fires when a requirements timeout action is taken.
			 *
			 * @param int  $order_id   Order ID.
			 * @param bool $auto_start Whether the order was auto-started (true) or cancelled (false).
			 */
			do_action( 'wpss_requirements_timeout', $order_id, $auto_start );
		}
	}

	/**
	 * Recalculate seller levels for all vendors.
	 *
	 * Runs weekly to check if vendors qualify for level promotions.
	 *
	 * @return void
	 */
	public function recalculate_seller_levels(): void {
		$seller_level_service = new SellerLevelService();

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$vendors = $wpdb->get_results(
			"SELECT user_id, verification_tier FROM {$table}"
		);

		foreach ( $vendors as $vendor ) {
			$user_id       = (int) $vendor->user_id;
			$current_level = $vendor->verification_tier ?? VendorProfile::TIER_NEW;
			$new_level     = $seller_level_service->calculate_level( $user_id );

			// Skip Pro vendors — their tier is admin-granted only.
			if ( VendorProfile::TIER_PRO === $current_level ) {
				continue;
			}

			// Only update if level changed.
			if ( $new_level !== $current_level ) {
				$seller_level_service->update_vendor_level( $user_id, $new_level );

				// Check if this is a promotion (not demotion).
				$level_order = [
					VendorProfile::TIER_NEW,
					VendorProfile::TIER_RISING,
					VendorProfile::TIER_TOP_RATED,
				];

				$current_index = array_search( $current_level, $level_order, true );
				$new_index     = array_search( $new_level, $level_order, true );

				if ( false !== $new_index && false !== $current_index && $new_index > $current_index ) {
					// This is a promotion - notify vendor.
					$level_label = SellerLevelService::get_level_label( $new_level );

					$this->notification_service->create(
						$user_id,
						'seller_level_promotion',
						__( 'Congratulations! Level Up!', 'wp-sell-services' ),
						sprintf(
							/* translators: %s: new seller level */
							__( 'You have been promoted to %s! Keep up the great work.', 'wp-sell-services' ),
							$level_label
						),
						[ 'new_level' => $new_level ]
					);

					/**
					 * Fires when a vendor is promoted to a higher level.
					 *
					 * @since 1.0.0
					 *
					 * @param int    $user_id       Vendor user ID.
					 * @param string $new_level     New seller level.
					 * @param string $current_level Previous seller level.
					 */
					do_action( 'wpss_vendor_level_promoted', $user_id, $new_level, $current_level );
				}
			}
		}
	}

	/**
	 * Handle status change notifications.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function handle_status_change( int $order_id, string $new_status, string $old_status ): void {
		// Note: In-app notifications are handled by Plugin.php's define_notification_hooks()
		// via NotificationService::notify_order_status(). This method only handles
		// non-notification side effects like admin emails.

		switch ( $new_status ) {
			case ServiceOrder::STATUS_DISPUTED:
				// Notify admin via direct email (respects email settings).
				if ( EmailService::is_type_enabled( 'dispute_admin' ) ) {
					$admin_email = get_option( 'admin_email' );
					wp_mail(
						$admin_email,
						/* translators: %s: platform name */
						sprintf( __( '[%s] New Dispute Opened', 'wp-sell-services' ), wpss_get_platform_name() ),
						sprintf(
							/* translators: %d: order ID */
							__( 'A dispute has been opened for Order #%d. Please review in the admin panel.', 'wp-sell-services' ),
							$order_id
						)
					);
				}
				break;
		}

		/**
		 * Fires after status change processing.
		 *
		 * @param int    $order_id   Order ID.
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 */
		do_action( 'wpss_after_status_change_notification', $order_id, $new_status, $old_status );
	}

	/**
	 * Handle order completion.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function handle_order_completed( int $order_id, string $old_status ): void {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return;
		}

		// Calculate and record commission.
		$commission_service = new CommissionService();
		$commission_service->record( $order_id );

		// Update vendor stats (basic counts only - earnings handled by CommissionService).
		$this->update_single_vendor_stats( $order->vendor_id );

		// Note: Notifications handled by Plugin.php → NotificationService::notify_order_status().

		/**
		 * Fires when order is completed.
		 *
		 * @param int          $order_id Order ID.
		 * @param ServiceOrder $order    Order object.
		 */
		do_action( 'wpss_order_completed', $order_id, $order );
	}

	/**
	 * Handle order cancellation.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function handle_order_cancelled( int $order_id, string $old_status ): void {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return;
		}

		// Reverse earnings if commission was already recorded for this order.
		if ( null !== $order->vendor_earnings && '' !== $order->vendor_earnings ) {
			$this->reverse_order_earnings( $order_id, $order );
		}

		// Note: Notifications handled by Plugin.php → NotificationService::notify_order_status().

		/**
		 * Fires when order is cancelled.
		 *
		 * @param int          $order_id Order ID.
		 * @param ServiceOrder $order    Order object.
		 */
		do_action( 'wpss_order_cancelled', $order_id, $order );
	}

	/**
	 * Reverse earnings for a cancelled order.
	 *
	 * Subtracts the vendor earnings from the vendor profile totals and creates
	 * a reversal wallet transaction.
	 *
	 * @param int          $order_id Order ID.
	 * @param ServiceOrder $order    Order object.
	 * @return void
	 */
	private function reverse_order_earnings( int $order_id, ServiceOrder $order ): void {
		global $wpdb;

		$vendor_id       = $order->vendor_id;
		$vendor_earnings = (float) $order->vendor_earnings;
		$order_total     = (float) $order->total;
		$platform_fee    = (float) ( $order->platform_fee ?? 0 );
		$currency        = $order->currency ? $order->currency : wpss_get_currency();

		// Skip zero-amount reversals.
		if ( $vendor_earnings <= 0 && $order_total <= 0 ) {
			return;
		}

		$transactions_table = $wpdb->prefix . 'wpss_wallet_transactions';
		$profiles_table     = $wpdb->prefix . 'wpss_vendor_profiles';
		$orders_table       = $wpdb->prefix . 'wpss_orders';

		// Idempotency: check if reversal already exists for this order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_reversal = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$transactions_table} WHERE reference_type = 'order' AND reference_id = %d AND type IN ('order_reversal', 'refund')",
				$order_id
			)
		);

		if ( $existing_reversal > 0 ) {
			return;
		}

		// All operations in a single transaction.
		$wpdb->query( 'START TRANSACTION' );

		// 1. Update vendor profile earnings (verify profile exists).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$profile_updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$profiles_table}
				SET
					total_earnings = GREATEST(0, total_earnings - %f),
					net_earnings = GREATEST(0, net_earnings - %f),
					total_commission = GREATEST(0, total_commission - %f),
					updated_at = %s
				WHERE user_id = %d",
				$order_total,
				$vendor_earnings,
				$platform_fee,
				current_time( 'mysql' ),
				$vendor_id
			)
		);

		if ( 0 === $profile_updated ) {
			wpss_log( "Earnings reversal: vendor profile not found for user {$vendor_id}, order {$order_id}.", 'warning' );
		}

		// 2. Create reversal wallet transaction.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_balance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(balance_after, 0)
				FROM {$transactions_table}
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT 1
				FOR UPDATE",
				$vendor_id
			)
		);

		$new_balance = max( 0, $current_balance - $vendor_earnings );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$transactions_table,
			array(
				'user_id'        => $vendor_id,
				'type'           => 'order_reversal',
				'amount'         => -$vendor_earnings,
				'balance_after'  => $new_balance,
				'currency'       => $currency,
				'description'    => sprintf(
					/* translators: %d: order ID */
					__( 'Earnings reversed for cancelled order #%d', 'wp-sell-services' ),
					$order_id
				),
				'reference_type' => 'order',
				'reference_id'   => $order_id,
				'status'         => 'completed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		// 3. Clear order commission fields to prevent double-reversal.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$orders_table,
			array(
				'vendor_earnings' => null,
				'platform_fee'    => null,
				'commission_rate' => null,
			),
			array( 'id' => $order_id )
		);

		$wpdb->query( 'COMMIT' );
	}

	/**
	 * Handle cancellation requested status.
	 *
	 * Notifies vendor and buyer when a cancellation request is submitted.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function handle_cancellation_requested( int $order_id, string $old_status ): void {
		// Note: Notifications handled by Plugin.php → NotificationService::notify_order_status().
		// EmailService also sends branded emails for this status via handle_status_change().

		// Note: wpss_cancellation_requested hook is fired by OrderService::request_cancellation()
		// with full context (order_id, user_id, reason, note). Not re-fired here to avoid duplication.
	}

	/**
	 * Process cancellation request timeouts.
	 *
	 * Auto-cancels orders that have been in cancellation_requested status for 48+ hours.
	 *
	 * @return void
	 */
	public function process_cancellation_timeouts(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Find all orders in cancellation_requested status.
		// We check the requested_at timestamp from vendor_notes JSON for accurate 48h enforcement.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, vendor_id, vendor_notes, updated_at FROM {$table}
				WHERE status = %s",
				ServiceOrder::STATUS_CANCELLATION_REQUESTED
			)
		);

		$now             = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$timed_out_orders = array();

		foreach ( $pending_orders as $order ) {
			$cancel_data  = json_decode( $order->vendor_notes ?? '', true );
			$requested_at = ! empty( $cancel_data['requested_at'] )
				? strtotime( $cancel_data['requested_at'] )
				: 0;

			// Fall back to updated_at if vendor_notes JSON is missing or corrupt.
			if ( $requested_at <= 0 && ! empty( $order->updated_at ) ) {
				$requested_at = strtotime( $order->updated_at );
			}

			if ( $requested_at > 0 && ( $now - $requested_at ) >= 48 * HOUR_IN_SECONDS ) {
				$timed_out_orders[] = $order;
			}
		}

		foreach ( $timed_out_orders as $order ) {
			$order_id = (int) $order->id;

			$updated = $this->order_service->update_status(
				$order_id,
				ServiceOrder::STATUS_CANCELLED,
				__( 'Order auto-cancelled - vendor did not respond to cancellation request within 48 hours', 'wp-sell-services' )
			);

			if ( ! $updated ) {
				continue;
			}

			// Notify vendor.
			$this->notification_service->create(
				(int) $order->vendor_id,
				'cancellation_auto_approved',
				__( 'Order Auto-Cancelled', 'wp-sell-services' ),
				__( 'The cancellation request was automatically approved because you did not respond within 48 hours.', 'wp-sell-services' ),
				[ 'order_id' => $order_id ]
			);

			// Notify buyer.
			$this->notification_service->create(
				(int) $order->customer_id,
				'cancellation_auto_approved',
				__( 'Cancellation Approved', 'wp-sell-services' ),
				__( 'Your cancellation request has been automatically approved. The vendor did not respond within 48 hours.', 'wp-sell-services' ),
				[ 'order_id' => $order_id ]
			);
		}
	}

	/**
	 * Clean up expired buyer requests.
	 *
	 * Cron handler for wpss_cleanup_expired_requests.
	 *
	 * @return void
	 */
	public function cleanup_expired_requests(): void {
		$service = new \WPSellServices\Services\BuyerRequestService();
		$service->expire_old_requests();
	}

	/**
	 * Update all vendor statistics.
	 *
	 * Cron handler for wpss_update_vendor_stats.
	 *
	 * @return void
	 */
	public function update_vendor_stats(): void {
		$vendor_service = new \WPSellServices\Services\VendorService();
		$vendors        = get_users(
			array(
				'meta_key'   => '_wpss_is_vendor', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
			)
		);

		foreach ( $vendors as $vendor_id ) {
			$vendor_service->update_stats( (int) $vendor_id );
		}
	}

	/**
	 * Handle payment completion.
	 *
	 * @param int    $order_id       Order ID.
	 * @param string $transaction_id Transaction ID.
	 * @return void
	 */
	public function handle_payment_complete( int $order_id, string $transaction_id = '' ): void {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return;
		}

		// Transition to pending requirements.
		// This fires wpss_order_status_changed → Plugin.php handles notifications.
		$this->order_service->update_status(
			$order_id,
			ServiceOrder::STATUS_PENDING_REQUIREMENTS,
			__( 'Payment received', 'wp-sell-services' )
		);
	}

	/**
	 * Update vendor statistics (order counts only).
	 *
	 * Note: Earnings are handled by CommissionService::record() which properly
	 * calculates vendor_earnings after platform commission deduction.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function update_single_vendor_stats( int $vendor_id ): void {
		global $wpdb;
		$orders_table   = $wpdb->prefix . 'wpss_orders';
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// Get completed orders count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$completed_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE vendor_id = %d",
				$vendor_id
			)
		);

		// Update vendor profile order counts only (earnings updated by CommissionService).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$profiles_table,
			[
				'total_orders'     => $total_orders,
				'completed_orders' => $completed_orders,
				'updated_at'       => current_time( 'mysql' ),
			],
			[ 'user_id' => $vendor_id ]
		);
	}

	/**
	 * Clear scheduled cron events on deactivation.
	 *
	 * @return void
	 */
	public static function clear_scheduled_events(): void {
		wp_clear_scheduled_hook( 'wpss_check_late_orders' );
		wp_clear_scheduled_hook( 'wpss_auto_complete_orders' );
		wp_clear_scheduled_hook( 'wpss_send_deadline_reminders' );
		wp_clear_scheduled_hook( 'wpss_send_requirements_reminders' );
		wp_clear_scheduled_hook( 'wpss_check_requirements_timeout' );
		wp_clear_scheduled_hook( 'wpss_recalculate_seller_levels' );
		wp_clear_scheduled_hook( 'wpss_process_cancellation_timeouts' );
	}
}
