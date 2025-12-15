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

use WPSellServices\Models\ServiceOrder;

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

		// Status change hooks.
		add_action( 'wpss_order_status_changed', [ $this, 'handle_status_change' ], 10, 3 );
		add_action( 'wpss_order_status_completed', [ $this, 'handle_order_completed' ], 10, 2 );
		add_action( 'wpss_order_status_cancelled', [ $this, 'handle_order_cancelled' ], 10, 2 );

		// Payment hooks.
		add_action( 'wpss_order_payment_complete', [ $this, 'handle_payment_complete' ] );
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
			'display'  => __( 'Every Hour', 'wp-sell-services' ),
		];

		$schedules['wpss_twice_daily'] = [
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily', 'wp-sell-services' ),
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
			$this->notification_service->send(
				(int) $order->vendor_id,
				'order_late',
				__( 'Order Overdue', 'wp-sell-services' ),
				__( 'Your order is past the delivery deadline. Please deliver as soon as possible.', 'wp-sell-services' ),
				[ 'order_id' => $order->id ]
			);

			// Notify customer.
			$this->notification_service->send(
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
			$this->notification_service->send(
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

			$this->notification_service->send(
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
	 * Handle status change notifications.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function handle_status_change( int $order_id, string $new_status, string $old_status ): void {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return;
		}

		$statuses = ServiceOrder::get_statuses();
		$status_label = $statuses[ $new_status ] ?? $new_status;

		// Notify relevant party based on status.
		switch ( $new_status ) {
			case ServiceOrder::STATUS_IN_PROGRESS:
				$this->notification_service->send(
					$order->vendor_id,
					'order_started',
					__( 'Order Started', 'wp-sell-services' ),
					__( 'Requirements received. You can now start working on the order.', 'wp-sell-services' ),
					[ 'order_id' => $order_id ]
				);
				break;

			case ServiceOrder::STATUS_PENDING_APPROVAL:
				$this->notification_service->send(
					$order->customer_id,
					'delivery_received',
					__( 'Delivery Received', 'wp-sell-services' ),
					__( 'The vendor has submitted a delivery. Please review and accept or request revision.', 'wp-sell-services' ),
					[ 'order_id' => $order_id ]
				);
				break;

			case ServiceOrder::STATUS_REVISION_REQUESTED:
				$this->notification_service->send(
					$order->vendor_id,
					'revision_requested',
					__( 'Revision Requested', 'wp-sell-services' ),
					__( 'The customer has requested a revision on your delivery.', 'wp-sell-services' ),
					[ 'order_id' => $order_id ]
				);
				break;

			case ServiceOrder::STATUS_DISPUTED:
				// Notify admin.
				$admin_email = get_option( 'admin_email' );
				wp_mail(
					$admin_email,
					__( '[WP Sell Services] New Dispute Opened', 'wp-sell-services' ),
					sprintf(
						/* translators: %d: order ID */
						__( 'A dispute has been opened for Order #%d. Please review in the admin panel.', 'wp-sell-services' ),
						$order_id
					)
				);
				break;
		}

		/**
		 * Fires after status change notifications are sent.
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

		// Update vendor stats.
		$this->update_vendor_stats( $order->vendor_id );

		// Notify both parties.
		$this->notification_service->send(
			$order->customer_id,
			'order_completed',
			__( 'Order Completed', 'wp-sell-services' ),
			__( 'Your order has been completed. Please consider leaving a review!', 'wp-sell-services' ),
			[ 'order_id' => $order_id ]
		);

		$this->notification_service->send(
			$order->vendor_id,
			'order_completed',
			__( 'Order Completed', 'wp-sell-services' ),
			__( 'Congratulations! Your order has been marked as complete.', 'wp-sell-services' ),
			[ 'order_id' => $order_id ]
		);

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

		// Notify both parties.
		$this->notification_service->send(
			$order->customer_id,
			'order_cancelled',
			__( 'Order Cancelled', 'wp-sell-services' ),
			__( 'Your order has been cancelled.', 'wp-sell-services' ),
			[ 'order_id' => $order_id ]
		);

		$this->notification_service->send(
			$order->vendor_id,
			'order_cancelled',
			__( 'Order Cancelled', 'wp-sell-services' ),
			__( 'An order has been cancelled.', 'wp-sell-services' ),
			[ 'order_id' => $order_id ]
		);

		/**
		 * Fires when order is cancelled.
		 *
		 * @param int          $order_id Order ID.
		 * @param ServiceOrder $order    Order object.
		 */
		do_action( 'wpss_order_cancelled', $order_id, $order );
	}

	/**
	 * Handle payment completion.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_payment_complete( int $order_id ): void {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return;
		}

		// Transition to pending requirements.
		$this->order_service->update_status(
			$order_id,
			ServiceOrder::STATUS_PENDING_REQUIREMENTS,
			__( 'Payment received', 'wp-sell-services' )
		);

		// Notify customer to submit requirements.
		$this->notification_service->send(
			$order->customer_id,
			'submit_requirements',
			__( 'Submit Order Requirements', 'wp-sell-services' ),
			__( 'Payment received! Please submit your requirements so the vendor can start working.', 'wp-sell-services' ),
			[ 'order_id' => $order_id ]
		);

		// Notify vendor of new order.
		$this->notification_service->send(
			$order->vendor_id,
			'new_order',
			__( 'New Order Received', 'wp-sell-services' ),
			__( 'You have a new order! Waiting for customer to submit requirements.', 'wp-sell-services' ),
			[ 'order_id' => $order_id ]
		);
	}

	/**
	 * Update vendor statistics.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function update_vendor_stats( int $vendor_id ): void {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// Get completed orders count and total earnings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as completed_orders,
					COALESCE(SUM(total), 0) as total_earnings
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_orders = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE vendor_id = %d",
				$vendor_id
			)
		);

		// Update vendor profile.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$profiles_table,
			[
				'total_orders'     => (int) $total_orders,
				'completed_orders' => (int) $stats->completed_orders,
				'total_earnings'   => (float) $stats->total_earnings,
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
	}
}
