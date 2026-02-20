<?php
/**
 * Notification Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

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

	/**
	 * Create notification.
	 *
	 * @param int    $user_id  User to notify.
	 * @param string $type     Notification type.
	 * @param string $title    Notification title.
	 * @param string $message  Notification message.
	 * @param array  $data     Additional data.
	 * @return int|false Notification ID or false on failure.
	 */
	public function create( int|string $user_id, string $type, string $title, string $message, array $data = array() ) {
		$user_id = (int) $user_id;
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'type'       => $type,
				'title'      => $title,
				'message'    => $message,
				'data'       => wp_json_encode( $data ),
				'is_read'    => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

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
			$this->send_email( $user_id, $title, $message, $data );
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

		// Get service and user details.
		$service      = get_post( $order->service_id );
		$service_name = $service ? $service->post_title : __( 'Service', 'wp-sell-services' );
		$buyer        = get_user_by( 'id', $order->customer_id );
		$buyer_name   = $buyer ? $buyer->display_name : __( 'Customer', 'wp-sell-services' );
		$vendor       = get_user_by( 'id', $order->vendor_id );
		$vendor_name  = $vendor ? $vendor->display_name : __( 'Vendor', 'wp-sell-services' );
		$amount       = wpss_format_price( $order->total );

		// Notify vendor with detailed message.
		$vendor_message = sprintf(
			/* translators: 1: buyer name, 2: service name, 3: order number, 4: amount */
			__( 'Great news! %1$s has placed an order for your service.<br><br><strong>Order Details:</strong><br>Service: %2$s<br>Order Number: #%3$s<br>Amount: %4$s<br><br>The buyer will submit their requirements shortly. You\'ll be notified when they do so you can start working on the order.', 'wp-sell-services' ),
			esc_html( $buyer_name ),
			esc_html( $service_name ),
			esc_html( $order->order_number ),
			esc_html( $amount )
		);

		$this->create(
			$order->vendor_id,
			self::TYPE_ORDER_CREATED,
			__( 'New Order Received', 'wp-sell-services' ),
			$vendor_message,
			array(
				'order_id'     => $order_id,
				'order_number' => $order->order_number,
				'service_name' => $service_name,
				'amount'       => $amount,
			)
		);

		// Notify buyer with confirmation.
		$buyer_message = sprintf(
			/* translators: 1: service name, 2: vendor name, 3: order number, 4: amount */
			__( 'Thank you for your order!<br><br><strong>Order Confirmation:</strong><br>Service: %1$s<br>Seller: %2$s<br>Order Number: #%3$s<br>Amount: %4$s<br><br><strong>Next Step:</strong> Please submit your requirements so the seller can start working on your order.', 'wp-sell-services' ),
			esc_html( $service_name ),
			esc_html( $vendor_name ),
			esc_html( $order->order_number ),
			esc_html( $amount )
		);

		$this->create(
			$order->customer_id,
			'order_confirmation',
			__( 'Order Confirmed', 'wp-sell-services' ),
			$buyer_message,
			array(
				'order_id'     => $order_id,
				'order_number' => $order->order_number,
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

		// Get service and user details.
		$service      = get_post( $order->service_id );
		$service_name = $service ? $service->post_title : __( 'Service', 'wp-sell-services' );
		$buyer        = get_user_by( 'id', $order->customer_id );
		$buyer_name   = $buyer ? $buyer->display_name : __( 'Customer', 'wp-sell-services' );
		$vendor       = get_user_by( 'id', $order->vendor_id );
		$vendor_name  = $vendor ? $vendor->display_name : __( 'Vendor', 'wp-sell-services' );

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
					sprintf(
						/* translators: 1: buyer name, 2: order number, 3: service name */
						__( '%1$s has submitted the requirements for Order #%2$s.<br><br><strong>Service:</strong> %3$s<br><br>You can now start working on this order. Please deliver within the agreed timeframe.', 'wp-sell-services' ),
						esc_html( $buyer_name ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				// Notify buyer that work has started.
				$this->create(
					$order->customer_id,
					'order_in_progress',
					__( 'Your Order is In Progress', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: vendor name, 2: order number, 3: service name */
						__( '%1$s has received your requirements and started working on Order #%2$s.<br><br><strong>Service:</strong> %3$s<br><br>You\'ll be notified when the delivery is ready for your review.', 'wp-sell-services' ),
						esc_html( $vendor_name ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				break;

			case 'pending_approval':
				// Notify buyer that delivery is ready.
				$this->create(
					$order->customer_id,
					self::TYPE_DELIVERY_SUBMITTED,
					__( 'Delivery Ready for Review', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: vendor name, 2: order number, 3: service name */
						__( '%1$s has submitted the delivery for Order #%2$s.<br><br><strong>Service:</strong> %3$s<br><br>Please review the delivery and either accept it to complete the order, or request a revision if changes are needed.', 'wp-sell-services' ),
						esc_html( $vendor_name ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				break;

			case 'completed':
				// Notify both parties.
				$this->create(
					$order->customer_id,
					self::TYPE_DELIVERY_ACCEPTED,
					__( 'Order Completed', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: order number, 2: service name, 3: vendor name */
						__( 'Order #%1$s has been completed successfully!<br><br><strong>Service:</strong> %2$s<br><strong>Seller:</strong> %3$s<br><br>Thank you for your business. If you\'re satisfied with the service, please consider leaving a review to help other buyers.', 'wp-sell-services' ),
						esc_html( $order->order_number ),
						esc_html( $service_name ),
						esc_html( $vendor_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				$this->create(
					$order->vendor_id,
					'order_completed_vendor',
					__( 'Order Completed - Payment Released', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: buyer name, 2: order number, 3: service name */
						__( 'Congratulations! %1$s has accepted the delivery for Order #%2$s.<br><br><strong>Service:</strong> %3$s<br><br>The payment has been released to your account. Thank you for providing excellent service!', 'wp-sell-services' ),
						esc_html( $buyer_name ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				break;

			case 'revision_requested':
				// Notify vendor about revision request.
				$this->create(
					$order->vendor_id,
					self::TYPE_REVISION_REQUESTED,
					__( 'Revision Requested', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: buyer name, 2: order number, 3: service name */
						__( '%1$s has requested a revision for Order #%2$s.<br><br><strong>Service:</strong> %3$s<br><br>Please review their feedback and submit an updated delivery.', 'wp-sell-services' ),
						esc_html( $buyer_name ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				break;

			case 'cancellation_requested':
				// Parse cancellation reason.
				$cancel_data  = json_decode( $order->vendor_notes ?? '', true );
				$reason       = $cancel_data['reason'] ?? '';
				$reason_labels = array(
					'changed_mind'         => __( 'Changed my mind', 'wp-sell-services' ),
					'found_alternative'    => __( 'Found an alternative', 'wp-sell-services' ),
					'taking_too_long'      => __( 'Taking too long', 'wp-sell-services' ),
					'wrong_order'          => __( 'Ordered by mistake', 'wp-sell-services' ),
					'communication_issues' => __( 'Communication issues with vendor', 'wp-sell-services' ),
					'other'                => __( 'Other', 'wp-sell-services' ),
				);
				$reason_label = $reason_labels[ $reason ] ?? $reason;

				// Notify vendor.
				$this->create(
					$order->vendor_id,
					'cancellation_requested',
					__( 'Cancellation Requested', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: buyer name, 2: order number, 3: service name, 4: reason */
						__( '%1$s has requested to cancel Order #%2$s.<br><br><strong>Service:</strong> %3$s<br><strong>Reason:</strong> %4$s<br><br>You have 48 hours to accept or dispute this cancellation request.', 'wp-sell-services' ),
						esc_html( $buyer_name ),
						esc_html( $order->order_number ),
						esc_html( $service_name ),
						esc_html( $reason_label )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				// Notify buyer.
				$this->create(
					$order->customer_id,
					'cancellation_submitted',
					__( 'Cancellation Request Submitted', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: order number, 2: service name */
						__( 'Your cancellation request for Order #%1$s has been submitted.<br><br><strong>Service:</strong> %2$s<br><br>The vendor has 48 hours to respond. If they don\'t respond, the order will be automatically cancelled.', 'wp-sell-services' ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				break;

			case 'cancelled':
				// Notify both parties.
				$this->create(
					$order->customer_id,
					'order_cancelled',
					__( 'Order Cancelled', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: order number, 2: service name */
						__( 'Order #%1$s has been cancelled.<br><br><strong>Service:</strong> %2$s<br><br>If you have any questions about this cancellation, please contact support.', 'wp-sell-services' ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				$this->create(
					$order->vendor_id,
					'order_cancelled',
					__( 'Order Cancelled', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: order number, 2: service name, 3: buyer name */
						__( 'Order #%1$s from %3$s has been cancelled.<br><br><strong>Service:</strong> %2$s<br><br>If you have any questions about this cancellation, please contact support.', 'wp-sell-services' ),
						esc_html( $order->order_number ),
						esc_html( $service_name ),
						esc_html( $buyer_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				break;

			case 'disputed':
				// Notify both parties about dispute.
				$this->create(
					$order->customer_id,
					self::TYPE_DISPUTE_OPENED,
					__( 'Dispute Opened', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: order number, 2: service name */
						__( 'A dispute has been opened for Order #%1$s.<br><br><strong>Service:</strong> %2$s<br><br>Our support team will review the case and get back to you soon.', 'wp-sell-services' ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
				);
				$this->create(
					$order->vendor_id,
					self::TYPE_DISPUTE_OPENED,
					__( 'Dispute Opened', 'wp-sell-services' ),
					sprintf(
						/* translators: 1: order number, 2: service name */
						__( 'A dispute has been opened for Order #%1$s.<br><br><strong>Service:</strong> %2$s<br><br>Our support team will review the case and get back to you soon. Please prepare any relevant information.', 'wp-sell-services' ),
						esc_html( $order->order_number ),
						esc_html( $service_name )
					),
					array( 'order_id' => $order_id, 'order_number' => $order->order_number )
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
						sprintf(
							/* translators: 1: order number, 2: status, 3: service name */
							__( 'Order #%1$s status has been updated to: <strong>%2$s</strong><br><br><strong>Service:</strong> %3$s', 'wp-sell-services' ),
							esc_html( $order->order_number ),
							esc_html( $status_label ),
							esc_html( $service_name )
						),
						array( 'order_id' => $order_id, 'order_number' => $order->order_number, 'new_status' => $new_status )
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
				$service = get_post( $conversation->service_id );
				$service_name = $service ? $service->post_title : '';
			}
		}

		// Build detailed notification message.
		$notification = sprintf(
			/* translators: %s: sender name */
			__( 'You have received a new message from <strong>%s</strong>.', 'wp-sell-services' ),
			esc_html( $sender_name )
		);

		if ( $order_number ) {
			$notification .= '<br><br>';
			$notification .= sprintf(
				/* translators: %s: order number */
				__( '<strong>Order:</strong> #%s', 'wp-sell-services' ),
				esc_html( $order_number )
			);
		}

		if ( $service_name ) {
			$notification .= '<br>';
			$notification .= sprintf(
				/* translators: %s: service name */
				__( '<strong>Service:</strong> %s', 'wp-sell-services' ),
				esc_html( $service_name )
			);
		}

		// Include the actual message content.
		if ( ! empty( $message_content ) ) {
			// Truncate long messages for email preview.
			$preview = wp_trim_words( wp_strip_all_tags( $message_content ), 50, '...' );
			$notification .= '<br><br>';
			$notification .= '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #1e3a5f; margin: 10px 0;">';
			$notification .= '<strong>' . esc_html__( 'Message:', 'wp-sell-services' ) . '</strong><br>';
			$notification .= '<em>"' . esc_html( $preview ) . '"</em>';
			$notification .= '</div>';
		}

		$notification .= '<br>';
		$notification .= __( 'Log in to your dashboard to view the full conversation and reply.', 'wp-sell-services' );

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

		// Get reviewer info.
		$reviewer      = get_user_by( 'id', $review->customer_id );
		$reviewer_name = $reviewer ? $reviewer->display_name : __( 'A customer', 'wp-sell-services' );

		// Get service info.
		$service      = get_post( $review->service_id );
		$service_name = $service ? $service->post_title : __( 'your service', 'wp-sell-services' );

		// Format rating display.
		$rating       = (int) $review->rating;
		$rating_stars = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );

		// Build message.
		$message = sprintf(
			/* translators: 1: reviewer name, 2: service name */
			__( '<strong>%1$s</strong> has left a review for <strong>%2$s</strong>.', 'wp-sell-services' ),
			esc_html( $reviewer_name ),
			esc_html( $service_name )
		);

		$message .= '<br><br>';
		$message .= sprintf(
			/* translators: %s: star rating */
			__( '<strong>Rating:</strong> %s', 'wp-sell-services' ),
			$rating_stars . ' (' . $rating . '/5)'
		);

		if ( ! empty( $review->comment ) ) {
			$message .= '<br><br>';
			$message .= sprintf(
				/* translators: %s: review comment */
				__( '<strong>Review:</strong><br><em>"%s"</em>', 'wp-sell-services' ),
				esc_html( wp_trim_words( $review->comment, 50 ) )
			);
		}

		$message .= '<br><br>';
		$message .= __( 'Thank you for providing excellent service! Reviews help build your reputation and attract more customers.', 'wp-sell-services' );

		$this->create(
			(int) $review->vendor_id,
			self::TYPE_REVIEW_RECEIVED,
			__( 'New Review Received', 'wp-sell-services' ),
			$message,
			array(
				'review_id'    => $review_id,
				'order_id'     => $order_id,
				'service_id'   => (int) $review->service_id,
				'rating'       => $rating,
				'reviewer_id'  => (int) $review->customer_id,
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
		$resolution_label = $resolution_labels[ $resolution ] ?? __( 'resolved', 'wp-sell-services' );

		// Notify customer.
		$customer_message = sprintf(
			/* translators: 1: order number, 2: resolution */
			__( 'The dispute for Order #%1$s has been <strong>%2$s</strong>.', 'wp-sell-services' ),
			esc_html( $order->order_number ),
			$resolution_label
		);

		$customer_message .= '<br><br>';
		$customer_message .= sprintf(
			/* translators: %s: service name */
			__( '<strong>Service:</strong> %s', 'wp-sell-services' ),
			esc_html( $service_name )
		);

		if ( $refund_amount > 0 ) {
			$customer_message .= '<br>';
			$customer_message .= sprintf(
				/* translators: %s: refund amount */
				__( '<strong>Refund Amount:</strong> %s', 'wp-sell-services' ),
				wpss_format_price( $refund_amount )
			);
			$customer_message .= '<br><br>';
			$customer_message .= __( 'The refund will be processed according to our refund policy.', 'wp-sell-services' );
		}

		$customer_message .= '<br><br>';
		$customer_message .= __( 'If you have any questions about this resolution, please contact our support team.', 'wp-sell-services' );

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
		$vendor_message = sprintf(
			/* translators: 1: order number, 2: resolution */
			__( 'The dispute for Order #%1$s has been <strong>%2$s</strong>.', 'wp-sell-services' ),
			esc_html( $order->order_number ),
			$resolution_label
		);

		$vendor_message .= '<br><br>';
		$vendor_message .= sprintf(
			/* translators: %s: service name */
			__( '<strong>Service:</strong> %s', 'wp-sell-services' ),
			esc_html( $service_name )
		);

		if ( $refund_amount > 0 ) {
			$vendor_message .= '<br>';
			$vendor_message .= sprintf(
				/* translators: %s: refund amount */
				__( '<strong>Refund Amount:</strong> %s (deducted from earnings)', 'wp-sell-services' ),
				wpss_format_price( $refund_amount )
			);
		}

		$vendor_message .= '<br><br>';
		$vendor_message .= __( 'Thank you for your cooperation in resolving this dispute. If you have any questions, please contact our support team.', 'wp-sell-services' );

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
		$message = '';

		switch ( $type ) {
			case 'dispute_opened':
				$title = __( 'Dispute Opened', 'wp-sell-services' );
				$opener = get_user_by( 'id', $data['opened_by'] ?? 0 );
				$opener_name = $opener ? $opener->display_name : __( 'The other party', 'wp-sell-services' );
				$message = sprintf(
					/* translators: 1: opener name, 2: order ID */
					__( '<strong>%1$s</strong> has opened a dispute for Order #%2$d.', 'wp-sell-services' ),
					esc_html( $opener_name ),
					$data['order_id'] ?? 0
				);
				if ( ! empty( $data['reason'] ) ) {
					$message .= '<br><br>';
					$message .= sprintf(
						/* translators: %s: dispute reason */
						__( '<strong>Reason:</strong> %s', 'wp-sell-services' ),
						esc_html( $data['reason'] )
					);
				}
				if ( ! empty( $data['response_deadline'] ) ) {
					$message .= '<br><br>';
					$deadline = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $data['response_deadline'] ) );
					$message .= sprintf(
						/* translators: %s: deadline date */
						__( 'Please respond by <strong>%s</strong> to avoid automatic escalation.', 'wp-sell-services' ),
						$deadline
					);
				}
				break;

			case 'dispute_response_received':
				$title = __( 'Dispute Response Received', 'wp-sell-services' );
				$from_user = get_user_by( 'id', $data['from_user'] ?? 0 );
				$from_name = $from_user ? $from_user->display_name : __( 'The other party', 'wp-sell-services' );
				$message = sprintf(
					/* translators: 1: responder name, 2: order ID */
					__( '<strong>%1$s</strong> has responded to the dispute for Order #%2$d.', 'wp-sell-services' ),
					esc_html( $from_name ),
					$data['order_id'] ?? 0
				);
				$message .= '<br><br>';
				$message .= __( 'Please log in to your dashboard to view the response and continue the discussion if needed.', 'wp-sell-services' );
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
				$message = sprintf(
					/* translators: %d: order ID */
					__( 'This is a reminder that you have a pending dispute for Order #%d that requires your response.', 'wp-sell-services' ),
					$data['order_id'] ?? 0
				);
				$message .= '<br><br>';
				$message .= __( 'Please log in to your dashboard to respond to the dispute to avoid automatic escalation.', 'wp-sell-services' );
				break;

			case 'deadline_warning':
				$title = __( 'Order Deadline Approaching', 'wp-sell-services' );
				$message = sprintf(
					/* translators: %d: order ID */
					__( 'The delivery deadline for Order #%d is approaching.', 'wp-sell-services' ),
					$data['order_id'] ?? 0
				);
				$message .= '<br><br>';
				$message .= __( 'Please ensure you deliver the order on time to maintain your seller rating.', 'wp-sell-services' );
				break;

			default:
				$title = __( 'Notification', 'wp-sell-services' );
				$message = __( 'You have a new notification. Please check your dashboard for details.', 'wp-sell-services' );
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

		// Create notification for the new vendor.
		$this->create(
			$user_id,
			self::TYPE_VENDOR_REGISTERED,
			__( 'Welcome to the Marketplace!', 'wp-sell-services' ),
			/* translators: %s: platform name */
			sprintf(
				__( 'Congratulations! Your vendor account on %s has been created. You can now start creating services and accepting orders.', 'wp-sell-services' ),
				$platform_name
			),
			array(
				'user_id'      => $user_id,
				'display_name' => $display_name,
			)
		);

		// Send welcome email directly to vendor.
		$this->send_vendor_welcome_email( $user, $display_name, $platform_name );

		// Notify admin of new vendor registration.
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

		$dashboard_url = home_url( '/vendor-dashboard/' );

		$content  = '<html><body>';
		$content .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">';
		$content .= '<h2 style="color: #333;">' . esc_html( $platform_name ) . '</h2>';
		$content .= '<p>' . sprintf(
			/* translators: %s: vendor display name */
			esc_html__( 'Hello %s,', 'wp-sell-services' ),
			esc_html( $display_name )
		) . '</p>';
		$content .= '<p>' . esc_html__( 'Congratulations! Your vendor account has been successfully created.', 'wp-sell-services' ) . '</p>';
		$content .= '<p>' . esc_html__( 'You can now:', 'wp-sell-services' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Create and publish services', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Receive and manage orders', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Communicate with customers', 'wp-sell-services' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Track your earnings', 'wp-sell-services' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p><a href="' . esc_url( $dashboard_url ) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px;">';
		$content .= esc_html__( 'Go to Your Dashboard', 'wp-sell-services' );
		$content .= '</a></p>';
		$content .= '<p style="color: #666; font-size: 14px;">' . esc_html__( 'If you have any questions, please don\'t hesitate to contact us.', 'wp-sell-services' ) . '</p>';
		$content .= '</div>';
		$content .= '</body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Filter vendor welcome email content.
		 *
		 * @param string   $content  Email content.
		 * @param \WP_User $user     User object.
		 * @param string   $platform Platform name.
		 */
		$content = apply_filters( 'wpss_vendor_welcome_email_content', $content, $user, $platform_name );

		return wp_mail( $user->user_email, $subject, $content, $headers );
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

		$vendors_url = admin_url( 'admin.php?page=wpss-vendors' );

		$content  = '<html><body>';
		$content .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">';
		$content .= '<h2 style="color: #333;">' . esc_html__( 'New Vendor Registration', 'wp-sell-services' ) . '</h2>';
		$content .= '<p>' . esc_html__( 'A new vendor has registered on your marketplace.', 'wp-sell-services' ) . '</p>';
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
		$content .= '<p><a href="' . esc_url( $vendors_url ) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px;">';
		$content .= esc_html__( 'View All Vendors', 'wp-sell-services' );
		$content .= '</a></p>';
		$content .= '</div>';
		$content .= '</body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Filter admin vendor notification email content.
		 *
		 * @param string   $content Email content.
		 * @param \WP_User $user    User object.
		 */
		$content = apply_filters( 'wpss_admin_vendor_notification_content', $content, $user );

		return wp_mail( $admin_email, $subject, $content, $headers );
	}

	/**
	 * Check if email should be sent.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type.
	 * @return bool
	 */
	private function should_send_email( int $user_id, string $type ): bool {
		// First check admin notification settings (global toggle).
		$notification_settings = get_option( 'wpss_notifications' );

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
			'new_order'                   => 'notify_new_order',
			'order_created'               => 'notify_new_order',
			'order_confirmation'          => 'notify_new_order',
			'order_started'               => 'notify_new_order',
			'order_in_progress'           => 'notify_new_order',
			'submit_requirements'         => 'notify_new_order',
			'order_completed'             => 'notify_order_completed',
			'order_completed_vendor'      => 'notify_order_completed',
			'order_auto_completed'        => 'notify_order_completed',
			'order_cancelled'             => 'notify_order_cancelled',
			'delivery_received'           => 'notify_delivery_submitted',
			'revision_requested'          => 'notify_revision_requested',
			'order_late'                  => 'notify_new_order',
			'deadline_reminder'           => 'notify_new_order',
			// Dispute types used by DisputeWorkflowManager.
			'dispute_opened'              => 'notify_dispute_opened',
			'dispute_response_received'   => 'notify_dispute_opened',
			'dispute_resolved'            => 'notify_dispute_opened',
			'dispute_reminder'            => 'notify_dispute_opened',
			// Cancellation types.
			'cancellation_requested'      => 'notify_order_cancelled',
			'cancellation_submitted'      => 'notify_order_cancelled',
			'cancellation_auto_approved'  => 'notify_order_cancelled',
		);

		// Check if admin has disabled this notification type globally.
		if ( isset( $type_to_setting[ $type ] ) ) {
			$setting_key = $type_to_setting[ $type ];

			// Option never saved (fresh install) → allow sending (fall through to user prefs).
			if ( false !== $notification_settings ) {
				// Option was saved but is corrupted or not an array → allow sending.
				if ( is_array( $notification_settings ) ) {
					// Option was saved — missing key means unchecked (disabled).
					if ( ! array_key_exists( $setting_key, $notification_settings ) ) {
						return false;
					}

					if ( empty( $notification_settings[ $setting_key ] ) ) {
						return false;
					}
				}
			}
		}

		// Check user preferences.
		$email_preferences = get_user_meta( $user_id, 'wpss_email_notifications', true );

		if ( is_array( $email_preferences ) && isset( $email_preferences[ $type ] ) ) {
			return (bool) $email_preferences[ $type ];
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
			self::TYPE_VENDOR_REGISTERED,
			// OrderWorkflowManager types and status notifications.
			'new_order',
			'order_created',
			'order_confirmation',
			'order_started',
			'order_in_progress',
			'submit_requirements',
			'order_completed',
			'order_completed_vendor',
			'order_auto_completed',
			'order_cancelled',
			'delivery_received',
			'revision_requested',
			'order_late',
			'deadline_reminder',
			// Dispute types used by DisputeWorkflowManager.
			'dispute_opened',
			'dispute_response_received',
			'dispute_resolved',
			'dispute_reminder',
			// Cancellation types.
			'cancellation_requested',
			'cancellation_submitted',
			'cancellation_auto_approved',
		);

		return in_array( $type, $important_types, true );
	}

	/**
	 * Send email notification.
	 *
	 * @param int    $user_id User ID.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @param array  $data    Additional data.
	 * @return bool
	 */
	private function send_email( int $user_id, string $subject, string $message, array $data = array() ): bool {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Build email content.
		$email_content = $this->build_email_content( $message, $data );

		/**
		 * Filter email content before sending.
		 *
		 * @param string $email_content Email content.
		 * @param string $subject       Email subject.
		 * @param int    $user_id       User ID.
		 * @param array  $data          Additional data.
		 */
		$email_content = apply_filters( 'wpss_notification_email_content', $email_content, $subject, $user_id, $data );

		return wp_mail( $user->user_email, $subject, $email_content, $headers );
	}

	/**
	 * Build email content.
	 *
	 * @param string $message Message.
	 * @param array  $data    Additional data.
	 * @return string
	 */
	private function build_email_content( string $message, array $data = array() ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// Professional email template.
		$content = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, sans-serif; background-color: #f5f5f5;">
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px 0;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<!-- Header -->
					<tr>
						<td style="background-color: #1e3a5f; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
							<h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">' . esc_html( $site_name ) . '</h1>
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td style="padding: 40px 30px;">
							<div style="color: #333333; font-size: 16px; line-height: 1.6;">
								' . wp_kses_post( $message ) . '
							</div>';

		// Add action button if order ID is available.
		if ( ! empty( $data['order_id'] ) ) {
			$order_url = wpss_get_order_url( (int) $data['order_id'] );
			$content  .= '
							<div style="text-align: center; margin-top: 30px;">
								<a href="' . esc_url( $order_url ) . '" style="display: inline-block; background-color: #1e3a5f; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">View Order Details</a>
							</div>';
		}

		$content .= '
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="background-color: #f8f9fa; padding: 25px 30px; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">
							<p style="color: #6c757d; font-size: 14px; margin: 0 0 10px 0; text-align: center;">
								This email was sent from <a href="' . esc_url( $site_url ) . '" style="color: #1e3a5f; text-decoration: none;">' . esc_html( $site_name ) . '</a>
							</p>
							<p style="color: #adb5bd; font-size: 12px; margin: 0; text-align: center;">
								If you have any questions, please contact our support team.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		return $content;
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
		$type_to_wc_class = array(
			self::TYPE_ORDER_CREATED      => 'WPSS_Email_New_Order',
			'new_order'                   => 'WPSS_Email_New_Order',
			'order_created'               => 'WPSS_Email_New_Order',
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
			'revision_requested'          => 'WPSS_Email_Revision_Requested',
			self::TYPE_DISPUTE_OPENED     => 'WPSS_Email_Dispute_Opened',
			'dispute_opened'              => 'WPSS_Email_Dispute_Opened',
			self::TYPE_DISPUTE_RESOLVED   => 'WPSS_Email_Dispute_Opened',
			'dispute_response_received'   => 'WPSS_Email_Dispute_Opened',
			'dispute_resolved'            => 'WPSS_Email_Dispute_Opened',
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
		// EmailService hooks into wpss_order_status_changed at priority 20.
		// If it is not hooked, it is not active — allow NotificationService emails.
		if ( ! has_action( 'wpss_order_status_changed', array( 'WPSellServices\Services\EmailService', 'handle_status_change' ) ) ) {
			// EmailService registers an instance method, so check by inspecting all callbacks.
			global $wp_filter;

			$is_email_service_active = false;

			if ( isset( $wp_filter['wpss_order_status_changed'] ) ) {
				foreach ( $wp_filter['wpss_order_status_changed']->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] )
							&& is_object( $callback['function'][0] )
							&& $callback['function'][0] instanceof EmailService
						) {
							$is_email_service_active = true;
							break 2;
						}
					}
				}
			}

			if ( ! $is_email_service_active ) {
				return false;
			}
		}

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
		);

		return in_array( $type, $covered_types, true );
	}
}
