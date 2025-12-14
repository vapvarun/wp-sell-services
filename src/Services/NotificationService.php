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
	public function create( int $user_id, string $type, string $title, string $message, array $data = [] ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			[
				'user_id'    => $user_id,
				'type'       => $type,
				'title'      => $title,
				'message'    => $message,
				'data'       => wp_json_encode( $data ),
				'is_read'    => 0,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		if ( ! $result ) {
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

		// Send email notification if enabled.
		if ( $this->should_send_email( $user_id, $type ) ) {
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
	public function get_user_notifications( int $user_id, array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$defaults = [
			'unread_only' => false,
			'limit'       => 20,
			'offset'      => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'user_id = %d' ];
		$params = [ $user_id ];

		if ( $args['unread_only'] ) {
			$where[] = 'is_read = 0';
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, [ $args['limit'], $args['offset'] ] )
			)
		);
	}

	/**
	 * Get unread count.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_unread_count( int $user_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			[ 'is_read' => 1 ],
			[ 'id' => $notification_id ]
		);
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
		return (bool) $wpdb->update(
			$table,
			[ 'is_read' => 1 ],
			[ 'user_id' => $user_id, 'is_read' => 0 ]
		);
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
		return (bool) $wpdb->delete( $table, [ 'id' => $notification_id ] );
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

		// Notify vendor.
		$this->create(
			$order->vendor_id,
			self::TYPE_ORDER_CREATED,
			__( 'New Order Received', 'wp-sell-services' ),
			/* translators: %s: order number */
			sprintf( __( 'You have received a new order #%s', 'wp-sell-services' ), $order->order_number ),
			[
				'order_id'     => $order_id,
				'order_number' => $order->order_number,
			]
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

		$statuses = \WPSellServices\Models\ServiceOrder::get_statuses();
		$status_label = $statuses[ $new_status ] ?? $new_status;

		// Notify both customer and vendor.
		$users = [ $order->customer_id, $order->vendor_id ];

		foreach ( $users as $user_id ) {
			$this->create(
				$user_id,
				self::TYPE_ORDER_STATUS,
				__( 'Order Status Updated', 'wp-sell-services' ),
				/* translators: 1: order number, 2: status */
				sprintf( __( 'Order #%1$s status changed to %2$s', 'wp-sell-services' ), $order->order_number, $status_label ),
				[
					'order_id'     => $order_id,
					'order_number' => $order->order_number,
					'new_status'   => $new_status,
					'old_status'   => $old_status,
				]
			);
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
	public function notify_new_message( int $conversation_id, int $sender_id, int $recipient_id ): void {
		$sender = get_user_by( 'id', $sender_id );
		$sender_name = $sender ? $sender->display_name : __( 'Someone', 'wp-sell-services' );

		$this->create(
			$recipient_id,
			self::TYPE_NEW_MESSAGE,
			__( 'New Message', 'wp-sell-services' ),
			/* translators: %s: sender name */
			sprintf( __( '%s sent you a message', 'wp-sell-services' ), $sender_name ),
			[
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
			]
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
		// Check user preferences.
		$email_preferences = get_user_meta( $user_id, 'wpss_email_notifications', true );

		if ( is_array( $email_preferences ) && isset( $email_preferences[ $type ] ) ) {
			return (bool) $email_preferences[ $type ];
		}

		// Default: send emails for important notifications.
		$important_types = [
			self::TYPE_ORDER_CREATED,
			self::TYPE_DELIVERY_SUBMITTED,
			self::TYPE_DELIVERY_ACCEPTED,
			self::TYPE_DISPUTE_OPENED,
			self::TYPE_DEADLINE_WARNING,
		];

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
	private function send_email( int $user_id, string $subject, string $message, array $data = [] ): bool {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

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
	private function build_email_content( string $message, array $data = [] ): string {
		$content = '<html><body>';
		$content .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
		$content .= '<h2 style="color: #333;">' . esc_html( get_bloginfo( 'name' ) ) . '</h2>';
		$content .= '<p>' . wp_kses_post( $message ) . '</p>';

		// Add action link if order ID is available.
		if ( ! empty( $data['order_id'] ) ) {
			$order_url = home_url( '/service-order/' . $data['order_id'] . '/' );
			$content .= '<p><a href="' . esc_url( $order_url ) . '" style="background: #0073aa; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">';
			$content .= esc_html__( 'View Order', 'wp-sell-services' );
			$content .= '</a></p>';
		}

		$content .= '</div>';
		$content .= '</body></html>';

		return $content;
	}
}
