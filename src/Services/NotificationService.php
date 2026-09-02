<?php
/**
 * Notification Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Handles notification business logic.
 *
 * @since 1.0.0
 */
class NotificationService {

	/**
	 * Notification types.
	 */
	public const TYPE_ORDER_CREATED      = 'order_created';
	public const TYPE_ORDER_STATUS       = 'order_status';
	public const TYPE_NEW_MESSAGE        = 'new_message';
	public const TYPE_DELIVERY_SUBMITTED = 'delivery_submitted';
	public const TYPE_DELIVERY_ACCEPTED  = 'delivery_accepted';
	public const TYPE_REVISION_REQUESTED = 'revision_requested';
	public const TYPE_REVIEW_RECEIVED    = 'review_received';
	public const TYPE_DISPUTE_OPENED     = 'dispute_opened';
	public const TYPE_DISPUTE_RESOLVED   = 'dispute_resolved';
	public const TYPE_DEADLINE_WARNING   = 'deadline_warning';
	public const TYPE_VENDOR_REGISTERED  = 'vendor_registered';
	public const TYPE_VENDOR_APPROVED    = 'vendor_approved';
	public const TYPE_VENDOR_REJECTED    = 'vendor_rejected';
	public const TYPE_VENDOR_SUSPENDED   = 'vendor_suspended';
	public const TYPE_TIP_RECEIVED       = 'tip_received';

	/**
	 * Create notification.
	 *
	 * The stored row is always plain text — it feeds the REST API, the mobile
	 * app and the dashboard list, none of which should ever receive email
	 * markup. Pass a {@see NotificationMessage} and the HTML body for the email
	 * is composed from the same structure at send time; a plain string still
	 * works for callers that have nothing to format.
	 *
	 * @param int                        $user_id  User to notify.
	 * @param string                     $type     Notification type.
	 * @param string                     $title    Notification title.
	 * @param string|NotificationMessage $message  Notification body.
	 * @param array                      $data     Additional data.
	 * @return int|false Notification ID or false on failure.
	 */
	public function create( int|string $user_id, string $type, string $title, string|NotificationMessage $message, array $data = array() ): int|false {
		$user_id = (int) $user_id;
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		if ( $message instanceof NotificationMessage ) {
			$stored_message = $message->to_plain_text();
			$email_message  = $message->to_html();
		} else {
			// Plain callers store (and email) exactly what they wrote. A caller
			// that still hands over markup — a legacy add-on, say — keeps its
			// email body but never puts tags in the row.
			$stored_message = NotificationMessage::to_plain( $message );
			$email_message  = $message;
		}

		$row = array(
			'user_id'    => $user_id,
			'type'       => $type,
			'title'      => $title,
			'message'    => $stored_message,
			'data'       => wp_json_encode( $data ),
			'is_read'    => 0,
			'created_at' => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' );

		// `action_url` has been a column since the first schema, is read back
		// into the model and published in the REST payload — and nothing ever
		// wrote it, so every notification arrived with nowhere to go. Callers
		// pass it in $data; this is the one place it is persisted.
		if ( ! empty( $data['action_url'] ) ) {
			$row['action_url'] = esc_url_raw( (string) $data['action_url'] );
			$formats[]         = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $row, $formats );

		if ( ! $result ) {
			wpss_log( "Failed to create notification (type: {$type}) for user {$user_id}: " . $wpdb->last_error, 'error' );
			return false;
		}

		$notification_id = (int) $wpdb->insert_id;

		/**
		 * Fires when notification is created.
		 *
		 * @param int    $notification_id Notification ID.
		 * @param int    $user_id         User ID.
		 * @param string $type            Notification type.
		 * @param array  $data            Notification data.
		 */
		do_action( 'wpss_notification_created', $notification_id, $user_id, $type, $data );

		// Invalidate unread count cache.
		$this->invalidate_unread_cache( $user_id );

		// Send email notification if enabled and neither WooCommerce nor
		// the branded EmailService is handling this type already.
		if ( $this->should_send_email( $user_id, $type )
			&& ! $this->is_wc_handling_email( $type )
			&& ! $this->is_email_service_handling( $type )
		) {
			$this->send_email( $user_id, $title, $email_message, $data, $type );
		}

		return $notification_id;
	}

	/**
	 * Get notifications for user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Query args.
	 * @return array
	 */
	public function get_user_notifications( int $user_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$defaults = array(
			'unread_only' => false,
			'limit'       => 20,
			'offset'      => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'user_id = %d' );
		$params = array( $user_id );

		if ( $args['unread_only'] ) {
			$where[] = 'is_read = 0';
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, array( $args['limit'], $args['offset'] ) )
			)
		);
	}

	/**
	 * Get unread count with caching.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_unread_count( int $user_id ): int {
		$cache_key = 'wpss_unread_notifications_' . $user_id;
		$count     = wp_cache_get( $cache_key, 'wpss' );

		if ( false === $count ) {
			global $wpdb;
			$table = $wpdb->prefix . 'wpss_notifications';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
					$user_id
				)
			);

			wp_cache_set( $cache_key, $count, 'wpss', HOUR_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Invalidate unread count cache.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function invalidate_unread_cache( int $user_id ): void {
		wp_cache_delete( 'wpss_unread_notifications_' . $user_id, 'wpss' );
	}

	/**
	 * Mark notification as read.
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool
	 */
	public function mark_as_read( int $notification_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// Get user_id before update to invalidate cache.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d", $notification_id )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = (bool) $wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array( 'id' => $notification_id )
		);

		if ( $result && $user_id ) {
			$this->invalidate_unread_cache( $user_id );
		}

		return $result;
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function mark_all_as_read( int $user_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = (bool) $wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array(
				'user_id' => $user_id,
				'is_read' => 0,
			)
		);

		$this->invalidate_unread_cache( $user_id );

		return $result;
	}

	/**
	 * Delete notification.
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool
	 */
	public function delete( int $notification_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( $table, array( 'id' => $notification_id ) );
	}

	/**
	 * Write one notification to every administrator.
	 *
	 * Alerts that need a human - a refund the gateway could not make, a manual
	 * refund to send - were written to user 0, which no dashboard ever reads.
	 *
	 * @since 1.7.1
	 *
	 * @param string                     $type    Notification type.
	 * @param string                     $title   Title.
	 * @param string|NotificationMessage $message Body.
	 * @param array<string, mixed>       $data    Additional data.
	 * @return void
	 */
	public function notify_admins( string $type, string $title, string|NotificationMessage $message, array $data = array() ): void {
		$admin_ids = get_users(
			array(
				'capability' => 'manage_options',
				'fields'     => 'ID',
			)
		);

		foreach ( $admin_ids as $admin_id ) {
			$this->create( (int) $admin_id, $type, $title, $message, $data );
		}
	}

	/**
	 * Notify order created.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function notify_order_created( int $order_id ): void {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Sub-order platforms (tip, extension) use dedicated notification
		// flows. The generic "new order received" notification would be
		// misleading here — it would tell the vendor "you have a new order
		// to fulfill" when the buyer actually just paid a tip or an
		// extension top-up on an existing in-progress order.
		$platform = $order->platform ?? '';
		if (
			\WPSellServices\Services\TippingService::ORDER_TYPE === $platform
			|| \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE === $platform
			|| \WPSellServices\Services\MilestoneService::ORDER_TYPE === $platform
		) {
			return;
		}

		// Get service and user details (null-safe to prevent PHP 8.1+ deprecation
		// notices in string functions, which would corrupt AJAX JSON responses).
		$service      = get_post( $order->service_id );
		$service_name = $service ? $service->post_title : __( 'Service', 'wp-sell-services' );
		$buyer        = get_user_by( 'id', $order->customer_id );
		$buyer_name   = $buyer ? $buyer->display_name : __( 'Customer', 'wp-sell-services' );
		$vendor       = get_user_by( 'id', $order->vendor_id );
		$vendor_name  = $vendor ? $vendor->display_name : __( 'Vendor', 'wp-sell-services' );
		$order_number = (string) ( $order->order_number ?? '' );
		$amount       = wpss_format_price( (float) ( $order->total ?? 0 ) );

		// Notify vendor with detailed message.
		$vendor_message = NotificationMessage::make()
			->line(
				/* translators: %s: buyer name */
				__( 'Great news! %s has placed an order for your service.', 'wp-sell-services' ),
				$buyer_name
			)
			->heading( __( 'Order Details:', 'wp-sell-services' ) )
			->line(
				/* translators: %s: service name */
				__( 'Service: %s', 'wp-sell-services' ),
				$service_name
			)
			->line(
				/* translators: %s: order number */
				__( 'Order Number: #%s', 'wp-sell-services' ),
				$order_number
			)
			->line(
				/* translators: %s: formatted monetary amount */
				__( 'Amount: %s', 'wp-sell-services' ),
				$amount
			)
			->paragraph( __( 'The buyer will submit their requirements shortly. You\'ll be notified when they do so you can start working on the order.', 'wp-sell-services' ) );

		$this->create(
			$order->vendor_id,
			self::TYPE_ORDER_CREATED,
			__( 'New Order Received', 'wp-sell-services' ),
			$vendor_message,
			array(
				'order_id'     => $order_id,
				'order_number' => $order_number,
				'service_name' => $service_name,
				'amount'       => $amount,
			)
		);

		// Notify buyer with confirmation.
		$buyer_message = NotificationMessage::make()
			->line( __( 'Thank you for your order!', 'wp-sell-services' ) )
			->heading( __( 'Order Confirmation:', 'wp-sell-services' ) )
			->line(
				/* translators: %s: service name */
				__( 'Service: %s', 'wp-sell-services' ),
				$service_name
			)
			->line(
				/* translators: %s: seller name */
				__( 'Seller: %s', 'wp-sell-services' ),
				$vendor_name
			)
			->line(
				/* translators: %s: order number */
				__( 'Order Number: #%s', 'wp-sell-services' ),
				$order_number
			)
			->line(
				/* translators: %s: formatted monetary amount */
				__( 'Amount: %s', 'wp-sell-services' ),
				$amount
			)
			->block()
			->field(
				__( 'Next Step:', 'wp-sell-services' ),
				__( 'Please submit your requirements so the seller can start working on your order.', 'wp-sell-services' )
			);

		$this->create(
			$order->customer_id,
			'order_confirmation',
			__( 'Order Confirmed', 'wp-sell-services' ),
			$buyer_message,
			array(
				'order_id'     => $order_id,
				'order_number' => $order_number,
				'service_name' => $service_name,
				'amount'       => $amount,
			)
		);
	}

	/**
	 * Notify order status change.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function notify_order_status( int $order_id, string $new_status, string $old_status ): void {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Sub-order platforms (tip, extension) move through
		// pending_payment → completed very quickly and have their own
		// targeted notifications. The generic status emails would send
		// the buyer a "your order is complete" notice for what they just
		// paid as a tip or an extension — noise. Bail here; the feature-
		// specific notify_* methods own those surfaces.
		$platform = $order->platform ?? '';
		if (
			\WPSellServices\Services\TippingService::ORDER_TYPE === $platform
			|| \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE === $platform
			|| \WPSellServices\Services\MilestoneService::ORDER_TYPE === $platform
		) {
			return;
		}

		// Get service and user details (null-safe for PHP 8.1+ compat).
		$service      = get_post( $order->service_id );
		$service_name = $service ? $service->post_title : __( 'Service', 'wp-sell-services' );
		$buyer        = get_user_by( 'id', $order->customer_id );
		$buyer_name   = $buyer ? $buyer->display_name : __( 'Customer', 'wp-sell-services' );
		$vendor       = get_user_by( 'id', $order->vendor_id );
		$vendor_name  = $vendor ? $vendor->display_name : __( 'Vendor', 'wp-sell-services' );
		$order_number = (string) ( $order->order_number ?? '' );

		$statuses     = \WPSellServices\Models\ServiceOrder::get_statuses();
		$status_label = $statuses[ $new_status ] ?? $new_status;

		// Generate context-specific messages based on status.
		switch ( $new_status ) {
			case 'in_progress':
				// Notify vendor that requirements are received.
				$this->create(
					$order->vendor_id,
					'order_started',
					__( 'Order Ready to Start', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: buyer name, 2: order number */
							__( '%1$s has submitted the requirements for Order #%2$s.', 'wp-sell-services' ),
							$buyer_name,
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'You can now start working on this order. Please deliver within the agreed timeframe.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				// Notify buyer that work has started.
				$this->create(
					$order->customer_id,
					'order_in_progress',
					__( 'Your Order is In Progress', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: vendor name, 2: order number */
							__( '%1$s has received your requirements and started working on Order #%2$s.', 'wp-sell-services' ),
							$vendor_name,
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'You\'ll be notified when the delivery is ready for your review.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				break;

			case 'pending_approval':
				// Notify buyer that delivery is ready.
				$this->create(
					$order->customer_id,
					self::TYPE_DELIVERY_SUBMITTED,
					__( 'Delivery Ready for Review', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: vendor name, 2: order number */
							__( '%1$s has submitted the delivery for Order #%2$s.', 'wp-sell-services' ),
							$vendor_name,
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'Please review the delivery and either accept it to complete the order, or request a revision if changes are needed.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				break;

			case 'completed':
				// Notify both parties.
				$this->create(
					$order->customer_id,
					self::TYPE_DELIVERY_ACCEPTED,
					__( 'Order Completed', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: %s: order number */
							__( 'Order #%s has been completed successfully!', 'wp-sell-services' ),
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->field( __( 'Seller:', 'wp-sell-services' ), $vendor_name )
						->paragraph( __( 'Thank you for your business. If you\'re satisfied with the service, please consider leaving a review to help other buyers.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				$this->create(
					$order->vendor_id,
					'order_completed_vendor',
					__( 'Order Completed - Payment Released', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: buyer name, 2: order number */
							__( 'Congratulations! %1$s has accepted the delivery for Order #%2$s.', 'wp-sell-services' ),
							$buyer_name,
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'The payment has been released to your account. Thank you for providing excellent service!', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				break;

			case 'revision_requested':
				// Notify vendor about revision request.
				$this->create(
					$order->vendor_id,
					self::TYPE_REVISION_REQUESTED,
					__( 'Revision Requested', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: buyer name, 2: order number */
							__( '%1$s has requested a revision for Order #%2$s.', 'wp-sell-services' ),
							$buyer_name,
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'Please review their feedback and submit an updated delivery.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				break;

			case 'cancellation_requested':
				// Parse cancellation reason.
				$cancel_data   = json_decode( $order->vendor_notes ?? '', true );
				$reason        = $cancel_data['reason'] ?? '';
				$reason_labels = array(
					'changed_mind'         => __( 'Changed my mind', 'wp-sell-services' ),
					'found_alternative'    => __( 'Found an alternative', 'wp-sell-services' ),
					'taking_too_long'      => __( 'Taking too long', 'wp-sell-services' ),
					'wrong_order'          => __( 'Ordered by mistake', 'wp-sell-services' ),
					'communication_issues' => __( 'Communication issues with vendor', 'wp-sell-services' ),
					'other'                => __( 'Other', 'wp-sell-services' ),
				);
				$reason_label  = $reason_labels[ $reason ] ?? $reason;

				// Notify vendor.
				$this->create(
					$order->vendor_id,
					'cancellation_requested',
					__( 'Cancellation Requested', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: buyer name, 2: order number */
							__( '%1$s has requested to cancel Order #%2$s.', 'wp-sell-services' ),
							$buyer_name,
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->field( __( 'Reason:', 'wp-sell-services' ), $reason_label )
						->paragraph( __( 'You have 48 hours to accept or dispute this cancellation request.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				// Notify buyer.
				$this->create(
					$order->customer_id,
					'cancellation_submitted',
					__( 'Cancellation Request Submitted', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: %s: order number */
							__( 'Your cancellation request for Order #%s has been submitted.', 'wp-sell-services' ),
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'The vendor has 48 hours to respond. If they don\'t respond, the order will be automatically cancelled.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				break;

			case 'cancelled':
				// Notify both parties.
				$this->create(
					$order->customer_id,
					'order_cancelled',
					__( 'Order Cancelled', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: %s: order number */
							__( 'Order #%s has been cancelled.', 'wp-sell-services' ),
							$order_number
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'If you have any questions about this cancellation, please contact support.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				$this->create(
					$order->vendor_id,
					'order_cancelled',
					__( 'Order Cancelled', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: order number, 2: buyer name */
							__( 'Order #%1$s from %2$s has been cancelled.', 'wp-sell-services' ),
							$order_number,
							$buyer_name
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->paragraph( __( 'If you have any questions about this cancellation, please contact support.', 'wp-sell-services' ) ),
					array(
						'order_id'     => $order_id,
						'order_number' => $order_number,
					)
				);
				break;

			case 'disputed':
				// DisputeWorkflowManager::on_dispute_opened() writes one row per
				// party with the reason and the response deadline. Writing a
				// second, thinner "dispute opened" row here gave the other party
				// two entries for one event.
				break;

			case 'refunded':
				// The generic "status updated to Refunded" line said nothing about
				// money. Both parties get the amount: what was refunded when the
				// gateway has already answered, the order total otherwise.
				$refunded = (float) ( $order->refunded_amount ?? 0 ) > 0
					? (float) $order->refunded_amount
					: (float) ( $order->total ?? 0 );
				$amount   = wpss_format_price( $refunded, (string) ( $order->currency ?? '' ) );

				$this->create(
					$order->customer_id,
					self::TYPE_ORDER_STATUS,
					__( 'Order Refunded', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: order number, 2: amount */
							__( 'Order #%1$s has been refunded. %2$s is on its way back to your original payment method.', 'wp-sell-services' ),
							$order_number,
							NotificationMessage::strong( $amount )
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->field( __( 'Refund Amount:', 'wp-sell-services' ), $amount ),
					array(
						'order_id'      => $order_id,
						'order_number'  => $order_number,
						'new_status'    => $new_status,
						'refund_amount' => $refunded,
					)
				);
				$this->create(
					$order->vendor_id,
					self::TYPE_ORDER_STATUS,
					__( 'Order Refunded', 'wp-sell-services' ),
					NotificationMessage::make()
						->line(
							/* translators: 1: order number, 2: buyer name, 3: amount */
							__( 'Order #%1$s from %2$s has been refunded (%3$s).', 'wp-sell-services' ),
							$order_number,
							$buyer_name,
							NotificationMessage::strong( $amount )
						)
						->block()
						->field( __( 'Service:', 'wp-sell-services' ), $service_name )
						->field( __( 'Refund Amount:', 'wp-sell-services' ), $amount ),
					array(
						'order_id'      => $order_id,
						'order_number'  => $order_number,
						'new_status'    => $new_status,
						'refund_amount' => $refunded,
					)
				);
				break;

			default:
				// Generic status update for any other status.
				$users = array( $order->customer_id, $order->vendor_id );
				foreach ( $users as $user_id ) {
					$this->create(
						$user_id,
						self::TYPE_ORDER_STATUS,
						__( 'Order Status Updated', 'wp-sell-services' ),
						NotificationMessage::make()
							->line(
								/* translators: 1: order number, 2: status label */
								__( 'Order #%1$s status has been updated to: %2$s', 'wp-sell-services' ),
								$order_number,
								NotificationMessage::strong( $status_label )
							)
							->block()
							->field( __( 'Service:', 'wp-sell-services' ), $service_name ),
						array(
							'order_id'     => $order_id,
							'order_number' => $order_number,
							'new_status'   => $new_status,
						)
					);
				}
				break;
		}
	}

	/**
	 * Notify new message.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $sender_id       Sender user ID.
	 * @param int $recipient_id    Recipient user ID.
	 * @return void
	 */
	public function notify_new_message( int $conversation_id, int $sender_id, int $recipient_id, string $message_content = '' ): void {
		global $wpdb;

		$sender      = get_user_by( 'id', $sender_id );
		$sender_name = $sender ? $sender->display_name : __( 'Someone', 'wp-sell-services' );

		// Get conversation details for context.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, o.order_number, o.service_id
				FROM {$wpdb->prefix}wpss_conversations c
				LEFT JOIN {$wpdb->prefix}wpss_orders o ON c.order_id = o.id
				WHERE c.id = %d",
				$conversation_id
			)
		);

		$service_name = '';
		$order_number = '';

		if ( $conversation ) {
			$order_number = $conversation->order_number ?? '';
			if ( ! empty( $conversation->service_id ) ) {
				$service      = get_post( $conversation->service_id );
				$service_name = $service ? $service->post_title : '';
			}
		}

		// Build detailed notification message.
		$notification = NotificationMessage::make()->line(
			/* translators: %s: sender name */
			__( 'You have received a new message from %s.', 'wp-sell-services' ),
			NotificationMessage::strong( $sender_name )
		);

		if ( $order_number || $service_name ) {
			$notification->block();
		}

		if ( $order_number ) {
			$notification->field( __( 'Order:', 'wp-sell-services' ), '#' . $order_number );
		}

		if ( $service_name ) {
			$notification->field( __( 'Service:', 'wp-sell-services' ), $service_name );
		}

		// Include the actual message content.
		if ( ! empty( $message_content ) ) {
			// Truncate long messages for the preview.
			$notification->callout(
				__( 'Message:', 'wp-sell-services' ),
				wp_trim_words( wp_strip_all_tags( $message_content ), 50, '...' )
			);
		}

		$notification->paragraph( __( 'Log in to your dashboard to view the full conversation and reply.', 'wp-sell-services' ) );

		$this->create(
			$recipient_id,
			self::TYPE_NEW_MESSAGE,
			__( 'New Message Received', 'wp-sell-services' ),
			$notification,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'order_id'        => $conversation ? $conversation->order_id : null,
			)
		);
	}

	/**
	 * Notify review received.
	 *
	 * Sends notification to vendor when they receive a review.
	 *
	 * @param int $review_id Review ID.
	 * @param int $order_id  Order ID.
	 * @return void
	 */
	public function notify_review_received( int $review_id, int $order_id ): void {
		global $wpdb;

		// Get review details.
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_reviews WHERE id = %d",
				$review_id
			)
		);

		if ( ! $review ) {
			return;
		}

		// Get order details.
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Get reviewer info. Migrated guest reviews (customer_id = 0) carry the
		// name in reviewer_name; resolve through the shared helper.
		$reviewer_name = \WPSellServices\Models\Review::resolve_reviewer_name( (int) $review->customer_id, $review->reviewer_name ?? null );

		// Get service info.
		$service      = get_post( $review->service_id );
		$service_name = $service ? $service->post_title : __( 'your service', 'wp-sell-services' );

		// Format rating display.
		$rating       = (int) $review->rating;
		$rating_stars = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );

		// Build message.
		$message = NotificationMessage::make()
			->line(
				/* translators: 1: reviewer name, 2: service name */
				__( '%1$s has left a review for %2$s.', 'wp-sell-services' ),
				NotificationMessage::strong( $reviewer_name ),
				NotificationMessage::strong( $service_name )
			)
			->block()
			->field(
				__( 'Rating:', 'wp-sell-services' ),
				sprintf(
					/* translators: 1: star rating glyphs, 2: numeric rating out of five */
					__( '%1$s (%2$d/5)', 'wp-sell-services' ),
					$rating_stars,
					$rating
				)
			);

		if ( ! empty( $review->review ) ) {
			$message->quote(
				__( 'Review:', 'wp-sell-services' ),
				wp_trim_words( $review->review, 50 )
			);
		}

		$message->paragraph( __( 'Thank you for providing excellent service! Reviews help build your reputation and attract more customers.', 'wp-sell-services' ) );

		$this->create(
			(int) $review->vendor_id,
			self::TYPE_REVIEW_RECEIVED,
			__( 'New Review Received', 'wp-sell-services' ),
			$message,
			array(
				'review_id'   => $review_id,
				'order_id'    => $order_id,
				'service_id'  => (int) $review->service_id,
				'rating'      => $rating,
				'reviewer_id' => (int) $review->customer_id,
			)
		);
	}

	/**
	 * Notify the vendor that a tip sub-order has been paid and credited.
	 *
	 * Bound to the `wpss_tip_sent` action (fired from
	 * {@see \WPSellServices\Services\TippingService::credit_tip_on_payment_complete()}).
	 * Creates an in-app notification and hands off to
	 * {@see \WPSellServices\Services\EmailService::send_tip_received()} for the
	 * transactional email — the standard dual-surface pattern used by every
	 * other notification in this service.
	 *
	 * @param int    $wallet_txn_id   Wallet transaction row ID created by the credit.
	 * @param int    $parent_order_id Original service order that was tipped against.
	 * @param int    $vendor_id       Vendor user ID receiving the tip.
	 * @param int    $customer_id     Buyer user ID who sent the tip.
	 * @param float  $net_amount      Amount actually credited to the vendor wallet (after commission).
	 * @param string $note            Optional buyer message attached to the tip.
	 * @return void
	 */
	public function notify_tip_received( int $wallet_txn_id, int $parent_order_id, int $vendor_id, int $customer_id, float $net_amount, string $note = '' ): void {
		unset( $wallet_txn_id );

		global $wpdb;

		// Locate the tip sub-order — it carries the authoritative gross
		// amount and commission breakdown. Falls back gracefully if the
		// row has been removed (e.g. admin cleanup) by using the net
		// amount as both gross and net for the message.
		$tip_order_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_orders
				WHERE platform = %s AND platform_order_id = %d AND customer_id = %d
				ORDER BY id DESC LIMIT 1",
				\WPSellServices\Services\TippingService::ORDER_TYPE,
				$parent_order_id,
				$customer_id
			)
		);

		$tip_order = $tip_order_row ? \WPSellServices\Models\ServiceOrder::from_db( $tip_order_row ) : null;
		$gross     = $tip_order ? (float) $tip_order->total : $net_amount;
		$currency  = $tip_order ? ( $tip_order->currency ?? wpss_get_currency() ) : wpss_get_currency();

		$buyer      = get_user_by( 'id', $customer_id );
		$buyer_name = $buyer ? $buyer->display_name : __( 'A buyer', 'wp-sell-services' );

		$net_display = function_exists( 'wpss_format_price' )
			? wpss_format_price( $net_amount, $currency )
			: number_format_i18n( $net_amount, 2 ) . ' ' . $currency;

		$message = NotificationMessage::make()->line(
			/* translators: 1: buyer name, 2: net amount credited */
			__( '%1$s just sent you a tip of %2$s. It has been added to your earnings balance.', 'wp-sell-services' ),
			NotificationMessage::strong( $buyer_name ),
			NotificationMessage::strong( $net_display )
		);
		if ( ! empty( $note ) ) {
			$message->note( $note );
		}

		$this->create(
			$vendor_id,
			self::TYPE_TIP_RECEIVED,
			__( 'You received a tip!', 'wp-sell-services' ),
			$message,
			array(
				'parent_order_id' => $parent_order_id,
				'tip_order_id'    => $tip_order ? $tip_order->id : 0,
				'amount'          => $net_amount,
				'gross'           => $gross,
				'customer_id'     => $customer_id,
			)
		);

		if ( $tip_order ) {
			( new EmailService() )->send_tip_received( $tip_order, $gross, $net_amount, $note );
		}

		// The buyer's receipt. Plain path: in-app row plus the generic mail.
		$vendor       = get_user_by( 'id', $vendor_id );
		$gross_amount = function_exists( 'wpss_format_price' )
			? wpss_format_price( $gross, $currency )
			: number_format_i18n( $gross, 2 ) . ' ' . $currency;
		$this->create(
			$customer_id,
			'tip_receipt',
			__( 'Tip sent', 'wp-sell-services' ),
			NotificationMessage::make()->line(
				/* translators: 1: amount, 2: vendor name, 3: order number */
				__( 'Your tip of %1$s to %2$s on Order #%3$s has been paid.', 'wp-sell-services' ),
				NotificationMessage::strong( $gross_amount ),
				NotificationMessage::strong( $vendor ? $vendor->display_name : __( 'your seller', 'wp-sell-services' ) ),
				$this->order_ref( $parent_order_id )
			),
			array(
				'order_id'     => $parent_order_id,
				'tip_order_id' => $tip_order ? $tip_order->id : 0,
				'amount'       => $gross,
				'vendor_id'    => $vendor_id,
			)
		);
	}

	/**
	 * Notify dispute resolved.
	 *
	 * Sends notification to both parties when a dispute is resolved.
	 *
	 * @param int    $dispute_id    Dispute ID.
	 * @param string $resolution    Resolution type.
	 * @param object $dispute       Dispute object.
	 * @param float  $refund_amount Refund amount.
	 * @return void
	 */
	public function notify_dispute_resolved( int $dispute_id, string $resolution, object $dispute, float $refund_amount ): void {
		global $wpdb;

		// Get order details.
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$dispute->order_id
			)
		);

		if ( ! $order ) {
			return;
		}

		// Get service name.
		$service      = get_post( $order->service_id );
		$service_name = $service ? $service->post_title : __( 'Service', 'wp-sell-services' );

		// Get resolution label.
		$resolution_labels = array(
			'full_refund'      => __( 'resolved with a full refund', 'wp-sell-services' ),
			'partial_refund'   => __( 'resolved with a partial refund', 'wp-sell-services' ),
			'favor_buyer'      => __( 'resolved in favor of the buyer', 'wp-sell-services' ),
			'favor_vendor'     => __( 'resolved in favor of the seller', 'wp-sell-services' ),
			'mutual_agreement' => __( 'resolved by mutual agreement', 'wp-sell-services' ),
		);
		$resolution_label  = $resolution_labels[ $resolution ] ?? __( 'resolved', 'wp-sell-services' );

		// Notify customer.
		$customer_message = NotificationMessage::make()
			->line(
				/* translators: 1: order number, 2: resolution */
				__( 'The dispute for Order #%1$s has been %2$s.', 'wp-sell-services' ),
				(string) $order->order_number,
				NotificationMessage::strong( $resolution_label )
			)
			->block()
			->field( __( 'Service:', 'wp-sell-services' ), $service_name );

		if ( $refund_amount > 0 ) {
			$customer_message->field( __( 'Refund Amount:', 'wp-sell-services' ), wpss_format_price( $refund_amount ) );
			$customer_message->paragraph( __( 'The refund will be processed according to our refund policy.', 'wp-sell-services' ) );
		}

		$customer_message->paragraph( __( 'If you have any questions about this resolution, please contact our support team.', 'wp-sell-services' ) );

		$this->create(
			$order->customer_id,
			self::TYPE_DISPUTE_RESOLVED,
			__( 'Dispute Resolved', 'wp-sell-services' ),
			$customer_message,
			array(
				'dispute_id'    => $dispute_id,
				'order_id'      => $dispute->order_id,
				'resolution'    => $resolution,
				'refund_amount' => $refund_amount,
			)
		);

		// Notify vendor.
		$vendor_message = NotificationMessage::make()
			->line(
				/* translators: 1: order number, 2: resolution */
				__( 'The dispute for Order #%1$s has been %2$s.', 'wp-sell-services' ),
				(string) $order->order_number,
				NotificationMessage::strong( $resolution_label )
			)
			->block()
			->field( __( 'Service:', 'wp-sell-services' ), $service_name );

		if ( $refund_amount > 0 ) {
			$vendor_message->field(
				__( 'Refund Amount:', 'wp-sell-services' ),
				sprintf(
					/* translators: %s: refund amount */
					__( '%s (deducted from earnings)', 'wp-sell-services' ),
					wpss_format_price( $refund_amount )
				)
			);
		}

		$vendor_message->paragraph( __( 'Thank you for your cooperation in resolving this dispute. If you have any questions, please contact our support team.', 'wp-sell-services' ) );

		$this->create(
			$order->vendor_id,
			self::TYPE_DISPUTE_RESOLVED,
			__( 'Dispute Resolved', 'wp-sell-services' ),
			$vendor_message,
			array(
				'dispute_id'    => $dispute_id,
				'order_id'      => $dispute->order_id,
				'resolution'    => $resolution,
				'refund_amount' => $refund_amount,
			)
		);
	}

	/**
	 * Send notification (alias for create with more descriptive name).
	 *
	 * Used by DisputeWorkflowManager and other services for semantic clarity.
	 *
	 * @param int    $user_id User to notify.
	 * @param string $type    Notification type.
	 * @param array  $data    Notification data.
	 * @return int|false Notification ID or false on failure.
	 */
	public function send( int $user_id, string $type, array $data = array() ) {
		// Build title and message based on type.
		$title   = '';
		$message = NotificationMessage::make();

		switch ( $type ) {
			case 'dispute_opened':
				$title       = __( 'Dispute Opened', 'wp-sell-services' );
				$opener      = get_user_by( 'id', $data['opened_by'] ?? 0 );
				$opener_name = $opener ? $opener->display_name : __( 'The other party', 'wp-sell-services' );
				$message->line(
					/* translators: 1: opener name, 2: order ID */
					__( '%1$s has opened a dispute for Order #%2$s.', 'wp-sell-services' ),
					NotificationMessage::strong( $opener_name ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				if ( ! empty( $data['reason'] ) ) {
					$message->block()->field( __( 'Reason:', 'wp-sell-services' ), (string) $data['reason'] );
				}
				if ( ! empty( $data['response_deadline'] ) ) {
					$deadline = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $data['response_deadline'] ) );
					$message->paragraph(
						/* translators: %s: deadline date */
						__( 'Please respond by %s to avoid automatic escalation.', 'wp-sell-services' ),
						NotificationMessage::strong( $deadline )
					);
				}
				break;

			case 'dispute_response_received':
				$title     = __( 'Dispute Response Received', 'wp-sell-services' );
				$from_user = get_user_by( 'id', $data['from_user'] ?? 0 );
				$from_name = $from_user ? $from_user->display_name : __( 'The other party', 'wp-sell-services' );
				$message->line(
					/* translators: 1: responder name, 2: order ID */
					__( '%1$s has responded to the dispute for Order #%2$s.', 'wp-sell-services' ),
					NotificationMessage::strong( $from_name ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				$message->paragraph( __( 'Please log in to your dashboard to view the response and continue the discussion if needed.', 'wp-sell-services' ) );
				break;

			case 'dispute_resolved':
				// Use dedicated method for resolved disputes.
				return $this->notify_dispute_resolved(
					$data['dispute_id'] ?? 0,
					$data['resolution'] ?? 'resolved',
					(object) array( 'order_id' => $data['order_id'] ?? 0 ),
					$data['refund_amount'] ?? 0.0
				);

			case 'dispute_reminder':
				$title = __( 'Dispute Response Reminder', 'wp-sell-services' );
				$message->line(
					/* translators: %s: order number */
					__( 'This is a reminder that you have a pending dispute for Order #%s that requires your response.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				$message->paragraph( __( 'Please log in to your dashboard to respond to the dispute to avoid automatic escalation.', 'wp-sell-services' ) );
				break;

			case 'deadline_warning':
				$title = __( 'Order Deadline Approaching', 'wp-sell-services' );
				$message->line(
					/* translators: %s: order number */
					__( 'The delivery deadline for Order #%s is approaching.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				$message->paragraph( __( 'Please ensure you deliver the order on time to maintain your seller rating.', 'wp-sell-services' ) );
				break;

			case 'extension_requested':
				$title = __( 'Quote for extra work', 'wp-sell-services' );
				$message->line(
					/* translators: %s: parent order number */
					__( 'Your seller sent a quote for additional work on Order #%s. Open the order to review — Accept & Pay to expand the scope, or Decline to keep things as-is.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'extension_approved':
				$title = __( 'Extra work paid', 'wp-sell-services' );
				$message->line(
					/* translators: 1: net amount, 2: extra days, 3: parent order ID */
					__( 'Buyer approved your quote. %1$s credited to your wallet, deadline on Order #%3$s extended by %2$d days.', 'wp-sell-services' ),
					function_exists( 'wpss_format_price' ) ? wpss_format_price( (float) ( $data['net_amount'] ?? 0 ) ) : (string) ( $data['net_amount'] ?? 0 ),
					(int) ( $data['extra_days'] ?? 0 ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'milestone_proposed':
				$title = __( 'Milestone proposed', 'wp-sell-services' );
				$message->line(
					/* translators: 1: phase name and amount, 2: parent order number */
					__( 'Your seller proposed %1$s on Order #%2$s. Open the order to review and Accept & Pay.', 'wp-sell-services' ),
					$this->milestone_label( $data['milestone_id'] ?? 0 ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'milestone_paid':
				$title = __( 'Milestone paid — start work', 'wp-sell-services' );
				$message->line(
					/* translators: 1: net amount, 2: parent order ID */
					__( 'Buyer paid the phase on Order #%2$s. %1$s credited to your wallet — you can start work and submit when delivered.', 'wp-sell-services' ),
					function_exists( 'wpss_format_price' ) ? wpss_format_price( (float) ( $data['net_amount'] ?? 0 ) ) : (string) ( $data['net_amount'] ?? 0 ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'milestone_submitted':
				$title = __( 'Milestone delivered', 'wp-sell-services' );
				$message->line(
					/* translators: %s: parent order number */
					__( 'Your seller submitted a phase delivery on Order #%s. Review it and approve, or request a revision in chat.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'milestone_approved':
				$title = __( 'Milestone approved', 'wp-sell-services' );
				$message->line(
					/* translators: %s: parent order number */
					__( 'Buyer approved your phase on Order #%s.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'extension_rejected':
				$title = __( 'Quote declined', 'wp-sell-services' );
				$note  = (string) ( $data['response_note'] ?? '' );
				if ( '' !== trim( $note ) ) {
					$message->line(
						/* translators: 1: order number, 2: buyer's note */
						__( 'Buyer declined your quote on Order #%1$s. Their note: %2$s', 'wp-sell-services' ),
						$this->order_ref( $data['order_id'] ?? 0 ),
						$note
					);
				} else {
					$message->line(
						/* translators: %s: order number */
						__( 'Buyer declined your quote on Order #%s.', 'wp-sell-services' ),
						$this->order_ref( $data['order_id'] ?? 0 )
					);
				}
				break;

			// The three proposal types were declared on the Notification model
			// (constant, icon and label) but had no case here, so the one that
			// did get sent — proposal_accepted, from convert_to_order() — fell
			// through to the default below and reached the vendor as
			// "Notification / You have a new notification."
			case 'proposal_received':
				$title  = __( 'New proposal received', 'wp-sell-services' );
				$vendor = get_user_by( 'id', $data['vendor_id'] ?? 0 );
				$message->line(
					/* translators: 1: vendor name, 2: request title */
					__( '%1$s sent a proposal on your request "%2$s".', 'wp-sell-services' ),
					NotificationMessage::strong( $vendor ? $vendor->display_name : __( 'A seller', 'wp-sell-services' ) ),
					(string) ( $data['request_title'] ?? '' )
				);
				if ( ! empty( $data['bid_amount'] ) ) {
					$message->block()->field(
						__( 'Their price:', 'wp-sell-services' ),
						function_exists( 'wpss_format_price' ) ? wpss_format_price( (float) $data['bid_amount'] ) : (string) $data['bid_amount']
					);
				}
				$message->paragraph( __( 'Open the request to compare proposals and hire when you are ready.', 'wp-sell-services' ) );
				break;

			case 'proposal_accepted':
				$title = __( 'Your proposal was accepted', 'wp-sell-services' );
				$message->line(
					/* translators: %s: request title */
					__( 'The buyer accepted your proposal on "%s" and the order is now open.', 'wp-sell-services' ),
					(string) ( $data['request_title'] ?? '' )
				);
				$message->paragraph(
					/* translators: %s: order number */
					__( 'Order #%s is yours — open it to see the brief and start work.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				break;

			case 'proposal_rejected':
				$title  = __( 'Proposal not selected', 'wp-sell-services' );
				$reason = trim( (string) ( $data['reason'] ?? '' ) );
				$message->line(
					/* translators: %s: request title */
					__( 'The buyer went with another seller on "%s".', 'wp-sell-services' ),
					(string) ( $data['request_title'] ?? '' )
				);
				if ( '' !== $reason ) {
					$message->block()->field( __( 'Their note:', 'wp-sell-services' ), $reason );
				}
				$message->paragraph( __( 'Nothing is wrong with your account — browse open requests and send another proposal.', 'wp-sell-services' ) );
				break;

			case 'review_reply':
				$title = __( 'Your review got a reply', 'wp-sell-services' );
				$message->line(
					/* translators: 1: vendor name, 2: service name */
					__( '%1$s replied to your review of "%2$s".', 'wp-sell-services' ),
					NotificationMessage::strong( (string) ( $data['vendor_name'] ?? '' ) ),
					(string) ( $data['service_title'] ?? '' )
				);
				if ( ! empty( $data['reply'] ) ) {
					$message->quote( __( 'Reply:', 'wp-sell-services' ), wp_trim_words( (string) $data['reply'], 50 ) );
				}
				break;

			case 'request_expired':
				$title = __( 'Your request has expired', 'wp-sell-services' );
				$message->line(
					/* translators: %s: request title */
					__( 'Your request "%s" reached its closing date and is no longer open to proposals.', 'wp-sell-services' ),
					(string) ( $data['request_title'] ?? '' )
				);
				$message->paragraph( __( 'Post it again if you still need the work done.', 'wp-sell-services' ) );
				break;

			case 'dispute_escalated':
				$title = __( 'Dispute Escalated', 'wp-sell-services' );
				$message->line(
					/* translators: %s: order number */
					__( 'The dispute for Order #%s has been escalated to the marketplace team for a decision.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				if ( ! empty( $data['reason'] ) ) {
					$message->block()->field( __( 'Reason:', 'wp-sell-services' ), (string) $data['reason'] );
				}
				$message->paragraph( __( 'A member of the team will review the case and both parties will be told the outcome.', 'wp-sell-services' ) );
				break;

			case 'dispute_cancelled':
				$title = __( 'Dispute Cancelled', 'wp-sell-services' );
				$message->line(
					/* translators: %s: order number */
					__( 'The dispute for Order #%s has been withdrawn and the order is back to where it was.', 'wp-sell-services' ),
					$this->order_ref( $data['order_id'] ?? 0 )
				);
				if ( ! empty( $data['reason'] ) ) {
					$message->block()->field( __( 'Reason:', 'wp-sell-services' ), (string) $data['reason'] );
				}
				break;

			case 'withdrawal_requested':
				$title = __( 'Withdrawal Requested', 'wp-sell-services' );
				$message->line(
					/* translators: %s: amount */
					__( 'Your withdrawal request for %s has been received and is waiting for review.', 'wp-sell-services' ),
					NotificationMessage::strong( wpss_format_price( (float) ( $data['amount'] ?? 0 ) ) )
				);
				break;

			default:
				$title = __( 'Notification', 'wp-sell-services' );
				$message->line( __( 'You have a new notification. Please check your dashboard for details.', 'wp-sell-services' ) );
				break;
		}

		return $this->create( $user_id, $type, $title, $message, $data );
	}

	/**
	 * Notify vendor registration.
	 *
	 * Sends welcome email to the vendor and notification to admin.
	 *
	 * @param int   $user_id      User ID.
	 * @param array $profile_data Profile data.
	 * @return void
	 */
	public function notify_vendor_registered( int $user_id, array $profile_data ): void {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		$platform_name = wpss_get_option( 'general', 'platform_name', get_bloginfo( 'name' ) );
		$display_name  = $profile_data['display_name'] ?? $user->display_name;
		$is_pending    = 'pending' === ( $profile_data['status'] ?? 'active' );

		if ( $is_pending ) {
			// Pending approval: send "under review" notification and email.
			$this->create(
				$user_id,
				self::TYPE_VENDOR_REGISTERED,
				__( 'Vendor Registration Received', 'wp-sell-services' ),
				sprintf(
					/* translators: %s: platform name */
					__( 'Thank you for registering as a vendor on %s. Your application has been submitted and is pending admin review. We will notify you once your application is approved.', 'wp-sell-services' ),
					$platform_name
				),
				array(
					'user_id'      => $user_id,
					'display_name' => $display_name,
					'status'       => 'pending',
				)
			);

			$this->send_vendor_pending_email( $user, $display_name, $platform_name );
		} else {
			// Auto-approved: send full welcome notification and email.
			$this->create(
				$user_id,
				self::TYPE_VENDOR_REGISTERED,
				__( 'Welcome to the Marketplace!', 'wp-sell-services' ),
				sprintf(
					/* translators: %s: platform name */
					__( 'Congratulations! Your vendor account on %s has been created. You can now start creating services and accepting orders.', 'wp-sell-services' ),
					$platform_name
				),
				array(
					'user_id'      => $user_id,
					'display_name' => $display_name,
				)
			);

			$this->send_vendor_welcome_email( $user, $display_name, $platform_name );
		}

		// Always notify admin of new vendor registration.
		$this->send_admin_vendor_notification( $user, $display_name );
	}

	/**
	 * Send welcome email to new vendor.
	 *
	 * @param \WP_User $user          User object.
	 * @param string   $display_name  Vendor display name.
	 * @param string   $platform_name Platform name.
	 * @return bool
	 */
	private function send_vendor_welcome_email( \WP_User $user, string $display_name, string $platform_name ): bool {
		$subject = sprintf(
			/* translators: %s: platform name */
			__( 'Welcome to %s - Your Vendor Account is Ready!', 'wp-sell-services' ),
			$platform_name
		);

		$content  = '<p>' . esc_html__( 'Congratulations! Your vendor account has been successfully created.', 'wp-sell-services' ) . '</p>';
		$content .= '<p>' . esc_html__( 'You can now:', 'wp-sell-services' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Create and publish services', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Receive and manage orders', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Communicate with customers', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Track your earnings', 'wp-sell-services' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . esc_html__( 'If you have any questions, please don\'t hesitate to contact us.', 'wp-sell-services' ) . '</p>';

		/**
		 * Filter vendor welcome email content.
		 *
		 * @param string   $content  Email content.
		 * @param \WP_User $user     User object.
		 * @param string   $platform Platform name.
		 */
		$content = apply_filters( 'wpss_vendor_welcome_email_content', $content, $user, $platform_name );

		return ( new EmailService() )->send(
			$user->user_email,
			$subject,
			self::TYPE_VENDOR_REGISTERED,
			array(
				'recipient'     => $user,
				'email_heading' => __( 'Welcome aboard', 'wp-sell-services' ),
				'content'       => $content,
				'button_url'    => wpss_get_dashboard_url(),
				'button_text'   => __( 'Go to Your Dashboard', 'wp-sell-services' ),
				'template'      => 'generic',
			)
		);
	}

	/**
	 * Send pending review email to vendor awaiting approval.
	 *
	 * @param \WP_User $user          User object.
	 * @param string   $display_name  Vendor display name.
	 * @param string   $platform_name Platform name.
	 * @return bool
	 */
	private function send_vendor_pending_email( \WP_User $user, string $display_name, string $platform_name ): bool {
		$subject = sprintf(
			/* translators: %s: platform name */
			__( 'Vendor Registration Received - %s', 'wp-sell-services' ),
			$platform_name
		);

		$content  = '<p>' . esc_html__( 'Thank you for registering as a vendor on our marketplace!', 'wp-sell-services' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Your application has been received and is currently under review by our team. We will carefully review your profile and get back to you as soon as possible.', 'wp-sell-services' ) . '</p>';
		$content .= '<p><strong>' . esc_html__( 'What happens next?', 'wp-sell-services' ) . '</strong></p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Our team will review your application', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'You will receive an email once a decision has been made', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'If approved, you can start creating services immediately', 'wp-sell-services' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . esc_html__( 'If you have any questions in the meantime, please don\'t hesitate to contact us.', 'wp-sell-services' ) . '</p>';

		/**
		 * Filter vendor pending review email content.
		 *
		 * @param string   $content  Email content.
		 * @param \WP_User $user     User object.
		 * @param string   $platform Platform name.
		 */
		$content = apply_filters( 'wpss_vendor_pending_email_content', $content, $user, $platform_name );

		return ( new EmailService() )->send(
			$user->user_email,
			$subject,
			self::TYPE_VENDOR_REGISTERED,
			array(
				'recipient'     => $user,
				'email_heading' => __( 'Application received', 'wp-sell-services' ),
				'content'       => $content,
				'template'      => 'generic',
			)
		);
	}

	/**
	 * Send admin notification for new vendor.
	 *
	 * @param \WP_User $user         User object.
	 * @param string   $display_name Vendor display name.
	 * @return bool
	 */
	private function send_admin_vendor_notification( \WP_User $user, string $display_name ): bool {
		$admin_email = get_option( 'admin_email' );

		if ( ! $admin_email ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: vendor display name */
			__( 'New Vendor Registration: %s', 'wp-sell-services' ),
			$display_name
		);

		$content  = '<p>' . esc_html__( 'A new vendor has registered on your marketplace.', 'wp-sell-services' ) . '</p>';
		$content .= '<table style="border-collapse: collapse; width: 100%; margin: 20px 0;">';
		$content .= '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__( 'Display Name', 'wp-sell-services' ) . '</strong></td>';
		$content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $display_name ) . '</td></tr>';
		$content .= '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__( 'Username', 'wp-sell-services' ) . '</strong></td>';
		$content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $user->user_login ) . '</td></tr>';
		$content .= '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__( 'Email', 'wp-sell-services' ) . '</strong></td>';
		$content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $user->user_email ) . '</td></tr>';
		$content .= '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__( 'Registration Date', 'wp-sell-services' ) . '</strong></td>';
		$content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( current_time( 'F j, Y g:i a' ) ) . '</td></tr>';
		$content .= '</table>';

		/**
		 * Filter admin vendor notification email content.
		 *
		 * @param string   $content Email content.
		 * @param \WP_User $user    User object.
		 */
		$content = apply_filters( 'wpss_admin_vendor_notification_content', $content, $user );

		return ( new EmailService() )->send(
			$admin_email,
			$subject,
			self::TYPE_VENDOR_REGISTERED,
			array(
				'recipient'     => get_user_by( 'email', $admin_email ),
				'email_heading' => __( 'New Vendor Registration', 'wp-sell-services' ),
				'content'       => $content,
				'button_url'    => admin_url( 'admin.php?page=wpss-vendors' ),
				'button_text'   => __( 'View All Vendors', 'wp-sell-services' ),
				'template'      => 'generic',
			)
		);
	}

	/**
	 * Notify vendor that their application has been approved.
	 *
	 * Sends in-app notification and email to the vendor.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function notify_vendor_approved( int $user_id ): void {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		$platform_name = wpss_get_platform_name();
		$dashboard_url = wpss_get_dashboard_url();

		$this->create(
			$user_id,
			self::TYPE_VENDOR_APPROVED,
			__( 'Vendor Application Approved', 'wp-sell-services' ),
			sprintf(
				/* translators: %s: platform name */
				__( 'Your vendor application on %s has been approved! You can now create services and start accepting orders.', 'wp-sell-services' ),
				$platform_name
			),
			array(
				'user_id'       => $user_id,
				'dashboard_url' => $dashboard_url,
				'action_url'    => $dashboard_url,
			)
		);

		( new EmailService() )->send(
			$user->user_email,
			sprintf(
				/* translators: %s: platform name */
				__( 'Your Vendor Application Has Been Approved - %s', 'wp-sell-services' ),
				$platform_name
			),
			EmailService::TYPE_VENDOR_APPROVED,
			array(
				'recipient'     => $user,
				'email_heading' => __( 'Application approved', 'wp-sell-services' ),
				'dashboard_url' => $dashboard_url,
			)
		);
	}

	/**
	 * Notify vendor that their application has been rejected.
	 *
	 * Sends in-app notification and email to the vendor.
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason  Reason given by the reviewer, if any.
	 * @return void
	 */
	public function notify_vendor_rejected( int $user_id, string $reason = '' ): void {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		$platform_name = wpss_get_platform_name();

		$this->create(
			$user_id,
			self::TYPE_VENDOR_REJECTED,
			__( 'Vendor Application Update', 'wp-sell-services' ),
			sprintf(
				/* translators: %s: platform name */
				__( 'Your vendor application on %s was not approved at this time.', 'wp-sell-services' ),
				$platform_name
			),
			array( 'user_id' => $user_id )
		);

		( new EmailService() )->send(
			$user->user_email,
			sprintf(
				/* translators: %s: platform name */
				__( 'Vendor Application Update - %s', 'wp-sell-services' ),
				$platform_name
			),
			EmailService::TYPE_VENDOR_REJECTED,
			array(
				'recipient'        => $user,
				'email_heading'    => __( 'Application not approved', 'wp-sell-services' ),
				'rejection_reason' => $reason,
			)
		);
	}

	/**
	 * Notify a vendor that their account has been suspended.
	 *
	 * A suspended vendor used to get the rejection wording - "we are unable to
	 * approve your application" - for an account that had been selling.
	 *
	 * @since 1.7.1
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason  Reason given by the admin, if any.
	 * @return void
	 */
	public function notify_vendor_suspended( int $user_id, string $reason = '' ): void {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		$platform_name = wpss_get_platform_name();

		$this->create(
			$user_id,
			self::TYPE_VENDOR_SUSPENDED,
			__( 'Vendor Account Suspended', 'wp-sell-services' ),
			sprintf(
				/* translators: %s: platform name */
				__( 'Your vendor account on %s has been suspended. Your services are hidden and you cannot take new orders until it is reinstated.', 'wp-sell-services' ),
				$platform_name
			),
			array( 'user_id' => $user_id )
		);

		( new EmailService() )->send(
			$user->user_email,
			sprintf(
				/* translators: %s: platform name */
				__( 'Your Vendor Account Has Been Suspended - %s', 'wp-sell-services' ),
				$platform_name
			),
			EmailService::TYPE_VENDOR_SUSPENDED,
			array(
				'recipient'         => $user,
				'email_heading'     => __( 'Account suspended', 'wp-sell-services' ),
				'suspension_reason' => $reason,
			)
		);
	}

	/**
	 * Check if email should be sent.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type.
	 * @return bool
	 */
	private function should_send_email( int $user_id, string $type ): bool {
		// Map notification types to admin setting keys.
		// Includes both constants and types used by OrderWorkflowManager.
		$type_to_setting = array(
			// NotificationService constants.
			self::TYPE_ORDER_CREATED      => 'notify_new_order',
			self::TYPE_ORDER_STATUS       => 'notify_new_order',
			self::TYPE_DELIVERY_SUBMITTED => 'notify_delivery_submitted',
			self::TYPE_DELIVERY_ACCEPTED  => 'notify_order_completed',
			self::TYPE_DISPUTE_OPENED     => 'notify_dispute_opened',
			self::TYPE_DISPUTE_RESOLVED   => 'notify_dispute_opened',
			self::TYPE_REVISION_REQUESTED => 'notify_revision_requested',
			self::TYPE_NEW_MESSAGE        => 'notify_new_message',
			self::TYPE_REVIEW_RECEIVED    => 'notify_new_review',
			// Types used by OrderWorkflowManager and status notifications.
			//
			// Only the ones with NO constant above. 'order_created',
			// 'revision_requested', 'dispute_opened' and 'dispute_resolved' used to
			// be repeated here as literals, but each is exactly the value of the
			// constant already keyed above and mapped to the same setting, so PHP
			// silently overwrote one with an identical entry. Harmless, but it read
			// as though the constant and the literal were different types.
			'new_order'                   => 'notify_new_order',
			'order_confirmation'          => 'notify_new_order',
			'order_started'               => 'notify_new_order',
			'order_in_progress'           => 'notify_new_order',
			'submit_requirements'         => 'notify_new_order',
			'order_completed'             => 'notify_order_completed',
			'order_completed_vendor'      => 'notify_order_completed',
			'order_auto_completed'        => 'notify_order_completed',
			'order_cancelled'             => 'notify_order_cancelled',
			'delivery_received'           => 'notify_delivery_submitted',
			'order_late'                  => 'notify_new_order',
			'deadline_reminder'           => 'notify_new_order',
			// Dispute types used by DisputeWorkflowManager.
			'dispute_response_received'   => 'notify_dispute_opened',
			'dispute_reminder'            => 'notify_dispute_opened',
			// Cancellation types.
			'cancellation_requested'      => 'notify_order_cancelled',
			'cancellation_submitted'      => 'notify_order_cancelled',
			'cancellation_auto_approved'  => 'notify_order_cancelled',
			// Events added in 1.7.1, each with its own toggle.
			'review_reply'                => 'notify_review_reply',
			'request_expired'             => 'notify_request_expired',
			'dispute_escalated'           => 'notify_dispute_escalated',
			'dispute_cancelled'           => 'notify_dispute_cancelled',
			'tip_receipt'                 => 'notify_tip_receipt',
		);

		// Admin toggle. A key nobody has unticked is on - the same reading
		// EmailService uses, through the same helper.
		if ( isset( $type_to_setting[ $type ] ) && ! wpss_notification_type_enabled( $type_to_setting[ $type ] ) ) {
			return false;
		}

		// Check user preferences. Saved by the dashboard profile form as a
		// category=>bool array under `wpss_email_preferences` (the previous
		// `wpss_email_notifications` key was never written anywhere, so user
		// preferences silently had no effect — Basecamp #9983528063). The
		// category mapping mirrors EmailService::get_user_pref_category();
		// types without a category are not user-controllable.
		$email_preferences = get_user_meta( $user_id, 'wpss_email_preferences', true );
		$category          = $this->get_user_pref_category( $type );

		if ( null !== $category && is_array( $email_preferences ) && array_key_exists( $category, $email_preferences ) ) {
			return ! empty( $email_preferences[ $category ] );
		}

		// Default: send emails for important notifications (WooCommerce-independent).
		$important_types = array(
			// NotificationService constants - ALL types send emails by default.
			self::TYPE_ORDER_CREATED,
			self::TYPE_ORDER_STATUS,
			self::TYPE_NEW_MESSAGE,
			self::TYPE_DELIVERY_SUBMITTED,
			self::TYPE_DELIVERY_ACCEPTED,
			self::TYPE_REVISION_REQUESTED,
			self::TYPE_REVIEW_RECEIVED,
			self::TYPE_DISPUTE_OPENED,
			self::TYPE_DISPUTE_RESOLVED,
			self::TYPE_DEADLINE_WARNING,
			// TYPE_VENDOR_REGISTERED is not here: notify_vendor_registered()
			// sends the welcome / pending mail itself, so the row must not.
			// OrderWorkflowManager types and status notifications. Again only the
			// ones without a constant above - this is an in_array() haystack, so the
			// duplicates were inert, just misleading.
			'new_order',
			'order_confirmation',
			'order_started',
			'order_in_progress',
			'submit_requirements',
			'order_completed',
			'order_completed_vendor',
			'order_auto_completed',
			'order_cancelled',
			'delivery_received',
			'order_late',
			'deadline_reminder',
			// Dispute types used by DisputeWorkflowManager.
			'dispute_response_received',
			'dispute_reminder',
			// Cancellation types.
			'cancellation_requested',
			'cancellation_submitted',
			'cancellation_auto_approved',
			// Events added in 1.7.1.
			'review_reply',
			'request_expired',
			'dispute_escalated',
			'dispute_cancelled',
			'tip_receipt',
		);

		return in_array( $type, $important_types, true );
	}

	/**
	 * Map a notification type to its user-facing email preference category.
	 *
	 * The dashboard profile form stores preferences as a category=>bool array
	 * (`orders`, `messages`, `completion`, `cancellation`, `disputes`, `tips`,
	 * `withdrawals`, `proposals`) in `wpss_email_preferences` user meta — the
	 * same store EmailService::get_user_pref_category() consults. Covers both
	 * the TYPE_* constants and the raw workflow strings that
	 * OrderWorkflowManager / DisputeWorkflowManager pass through notify().
	 * Types without a category (vendor approval, deadline warnings) are not
	 * user-controllable and always send.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Notification type.
	 * @return string|null Preference category, or null when not user-controllable.
	 */
	private function get_user_pref_category( string $type ): ?string {
		$type_to_category = array(
			self::TYPE_ORDER_CREATED      => 'orders',
			self::TYPE_ORDER_STATUS       => 'orders',
			'new_order'                   => 'orders',
			'order_confirmation'          => 'orders',
			'order_started'               => 'orders',
			'order_in_progress'           => 'orders',
			'submit_requirements'         => 'orders',
			self::TYPE_NEW_MESSAGE        => 'messages',
			self::TYPE_DELIVERY_SUBMITTED => 'completion',
			self::TYPE_DELIVERY_ACCEPTED  => 'completion',
			self::TYPE_REVISION_REQUESTED => 'completion',
			self::TYPE_REVIEW_RECEIVED    => 'completion',
			'order_completed'             => 'completion',
			'order_completed_vendor'      => 'completion',
			'order_auto_completed'        => 'completion',
			'delivery_received'           => 'completion',
			'order_cancelled'             => 'cancellation',
			'cancellation_requested'      => 'cancellation',
			'cancellation_submitted'      => 'cancellation',
			'cancellation_auto_approved'  => 'cancellation',
			self::TYPE_DISPUTE_OPENED     => 'disputes',
			self::TYPE_DISPUTE_RESOLVED   => 'disputes',
			'dispute_response_received'   => 'disputes',
			'dispute_reminder'            => 'disputes',
			self::TYPE_TIP_RECEIVED       => 'tips',
			'review_reply'                => 'completion',
			'dispute_escalated'           => 'disputes',
			'dispute_cancelled'           => 'disputes',
		);

		return $type_to_category[ $type ] ?? null;
	}

	/**
	 * Describe a milestone phase well enough to tell two of them apart.
	 *
	 * A contract with several phases produced a run of notifications reading
	 * "Your seller proposed a new phase on Order #74", identical down to the
	 * word. A buyer could not tell which phase each referred to, or what any of
	 * them cost, without opening the order.
	 *
	 * @since 1.4.0
	 *
	 * @param mixed $milestone_id Milestone sub-order ID.
	 * @return string Phase name with amount, or a generic fallback.
	 */
	private function milestone_label( $milestone_id ): string {
		$milestone = ( (int) $milestone_id > 0 && function_exists( 'wpss_get_order' ) )
			? wpss_get_order( (int) $milestone_id )
			: null;

		if ( ! $milestone ) {
			return __( 'a new phase', 'wp-sell-services' );
		}

		// ServiceOrder decodes meta on load, so it is already an array here.
		$meta  = (array) $milestone->meta;
		$name  = (string) ( $meta['title'] ?? '' );
		$price = function_exists( 'wpss_format_price' )
			? wpss_format_price( (float) $milestone->total, (string) $milestone->currency )
			: (string) $milestone->total;

		if ( '' === $name ) {
			/* translators: %s: formatted amount */
			return sprintf( __( 'a new phase (%s)', 'wp-sell-services' ), $price );
		}

		/* translators: 1: phase name, 2: formatted amount */
		return sprintf( __( '"%1$s" (%2$s)', 'wp-sell-services' ), $name, $price );
	}

	/**
	 * Resolve an order id to the reference a member actually recognises.
	 *
	 * Half of the notification catalogue printed the raw primary key — "Order
	 * #74" — while the other half printed the order number, so the same list
	 * showed a buyer two different identifiers for their orders and one of them
	 * appears nowhere else in the product. The order number is what the order
	 * page, emails and invoices show, so it is what a notification must say.
	 *
	 * Falls back to the id only when the order has since been deleted, which is
	 * better than rendering an empty "Order #".
	 *
	 * @since 1.4.0
	 *
	 * @param mixed $order_id Order ID from the notification payload.
	 * @return string
	 */
	private function order_ref( $order_id ): string {
		$order_id = (int) $order_id;

		if ( $order_id <= 0 ) {
			return '';
		}

		$order = function_exists( 'wpss_get_order' ) ? wpss_get_order( $order_id ) : null;

		return ( $order && ! empty( $order->order_number ) ) ? (string) $order->order_number : (string) $order_id;
	}

	/**
	 * Send the email for an in-app row.
	 *
	 * ONE send path. This used to build its own HTML document and call
	 * wp_mail() directly, beside EmailService doing the same with templates;
	 * now every mail leaves through {@see EmailService::send()}, so the
	 * subject / header / from-name filters, the failure log and the retry apply
	 * to all of it. The row's type is the email type, so the admin toggle and
	 * the recipient's preferences are read the same way for both surfaces; the
	 * body renders through generic.php.
	 *
	 * @param int    $user_id User ID.
	 * @param string $subject Email subject.
	 * @param string $message Email message (HTML).
	 * @param array  $data    Additional data.
	 * @param string $type    Notification type.
	 * @return bool
	 */
	private function send_email( int $user_id, string $subject, string $message, array $data = array(), string $type = '' ): bool {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		/**
		 * Filter email content before sending.
		 *
		 * @param string $message Email body (HTML fragment).
		 * @param string $subject Email subject.
		 * @param int    $user_id User ID.
		 * @param array  $data    Additional data.
		 */
		$message = apply_filters( 'wpss_notification_email_content', $message, $subject, $user_id, $data );

		$button_url  = '';
		$button_text = '';
		if ( ! empty( $data['order_id'] ) ) {
			$button_url  = wpss_get_order_url( (int) $data['order_id'] );
			$button_text = __( 'View Order Details', 'wp-sell-services' );
		} elseif ( ! empty( $data['action_url'] ) ) {
			$button_url  = (string) $data['action_url'];
			$button_text = __( 'View Details', 'wp-sell-services' );
		}

		return ( new EmailService() )->send(
			$user->user_email,
			$subject,
			$type,
			array(
				'recipient'     => $user,
				'email_heading' => $subject,
				'content'       => $message,
				'button_url'    => $button_url,
				'button_text'   => $button_text,
				'template'      => 'generic',
			)
		);
	}

	/**
	 * Check if WooCommerce is handling emails for this notification type.
	 *
	 * Only returns true if the specific WPSS WC email class for this type
	 * is actually registered in WooCommerce's mailer. If the class isn't
	 * registered, NotificationService sends the email independently.
	 *
	 * @param string $type Notification type.
	 * @return bool True if a WPSS WC email class is registered for this type.
	 */
	private function is_wc_handling_email( string $type ): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return false;
		}

		// Map notification types to WPSS WC email class keys.
		//
		// Literals here are only the types that have NO constant. The four that
		// did ('order_created', 'revision_requested', 'dispute_opened',
		// 'dispute_resolved') were listed twice, each time resolving to the same
		// class as its constant, so PHP kept one and discarded an identical twin.
		$type_to_wc_class = array(
			self::TYPE_ORDER_CREATED      => 'WPSS_Email_New_Order',
			'new_order'                   => 'WPSS_Email_New_Order',
			'order_confirmation'          => 'WPSS_Email_New_Order',
			'order_started'               => 'WPSS_Email_Order_In_Progress',
			'order_in_progress'           => 'WPSS_Email_Order_In_Progress',
			self::TYPE_DELIVERY_SUBMITTED => 'WPSS_Email_Delivery_Ready',
			'delivery_received'           => 'WPSS_Email_Delivery_Ready',
			self::TYPE_DELIVERY_ACCEPTED  => 'WPSS_Email_Order_Completed',
			'order_completed'             => 'WPSS_Email_Order_Completed',
			'order_completed_vendor'      => 'WPSS_Email_Order_Completed',
			'order_auto_completed'        => 'WPSS_Email_Order_Completed',
			'order_cancelled'             => 'WPSS_Email_Order_Cancelled',
			self::TYPE_REVISION_REQUESTED => 'WPSS_Email_Revision_Requested',
			self::TYPE_DISPUTE_OPENED     => 'WPSS_Email_Dispute_Opened',
			self::TYPE_DISPUTE_RESOLVED   => 'WPSS_Email_Dispute_Opened',
			'dispute_response_received'   => 'WPSS_Email_Dispute_Opened',
			self::TYPE_NEW_MESSAGE        => 'WPSS_Email_New_Message',
		);

		$class_key = $type_to_wc_class[ $type ] ?? null;

		if ( ! $class_key ) {
			return false;
		}

		// Only defer to WC if the WPSS email class is actually registered.
		$wc_emails = WC()->mailer()->get_emails();

		return isset( $wc_emails[ $class_key ] );
	}

	/**
	 * Check if EmailService is handling branded emails for this notification type.
	 *
	 * EmailService sends branded HTML emails for order lifecycle events.
	 * When it is active, NotificationService should only create the in-app
	 * notification and skip its own simpler email to avoid duplicates.
	 *
	 * @since 1.2.2
	 *
	 * @param string $type Notification type.
	 * @return bool True if EmailService covers this type.
	 */
	private function is_email_service_handling( string $type ): bool {
		// EmailService is wired unconditionally in Plugin.php (closures, not
		// array callables). The old "is it hooked?" probe looked for array
		// callables, never found one, and answered false for every type - so
		// this guard never guarded anything and the plain mail went out beside
		// the branded one.
		// Notification types that EmailService covers with branded templates.
		$covered_types = array(
			// Order status change types (EmailService::handle_status_change).
			self::TYPE_ORDER_CREATED,
			'new_order',
			'order_created',
			'order_confirmation',
			'order_started',
			'order_in_progress',
			'order_completed',
			'order_completed_vendor',
			'order_auto_completed',
			'order_cancelled',
			self::TYPE_REVISION_REQUESTED,
			'revision_requested',
			self::TYPE_DISPUTE_OPENED,
			'dispute_opened',
			'cancellation_requested',
			'cancellation_submitted',
			// Specific event types (EmailService hooks dedicated actions).
			'submit_requirements',
			self::TYPE_DELIVERY_SUBMITTED,
			'delivery_received',
			self::TYPE_NEW_MESSAGE,
			// Proposals. EmailService has hooked wpss_proposal_submitted /
			// _accepted / _rejected with branded templates since 1.0.0, so the
			// in-app rows must NOT email as well — the same person would get
			// the branded mail and a plain duplicate for one event.
			'proposal_received',
			'proposal_accepted',
			'proposal_rejected',
			// Reviews: Plugin.php calls EmailService::send_review_received()
			// on wpss_review_created; the row must not mail a second time.
			self::TYPE_REVIEW_RECEIVED,
		);

		return in_array( $type, $covered_types, true );
	}
}
