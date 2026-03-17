<?php
/**
 * Extension Request Service
 *
 * Handles order deadline extension requests.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages deadline extension requests.
 *
 * @since 1.0.0
 */
class ExtensionRequestService {

	/**
	 * Extension request statuses.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

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
	 * Get extension request by ID.
	 *
	 * @param int $request_id Request ID.
	 * @return array|null
	 */
	public function get( int $request_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id )
		);

		return $row ? $this->format_request( $row ) : null;
	}

	/**
	 * Get extension requests for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_by_order( int $order_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		return array_map( [ $this, 'format_request' ], $rows );
	}

	/**
	 * Get pending extension request for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function get_pending( int $order_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1",
				$order_id,
				self::STATUS_PENDING
			)
		);

		return $row ? $this->format_request( $row ) : null;
	}

	/**
	 * Create an extension request.
	 *
	 * @param int    $order_id     Order ID.
	 * @param int    $requested_by User ID requesting extension.
	 * @param int    $extra_days   Number of extra days.
	 * @param string $reason       Reason for extension.
	 * @return array Result with success status.
	 */
	public function create( int $order_id, int $requested_by, int $extra_days, string $reason ): array {
		$order = $this->order_service->get( $order_id );

		if ( ! $order ) {
			return [
				'success' => false,
				'message' => __( 'Order not found.', 'wp-sell-services' ),
			];
		}

		// Validate order status.
		$allowed_statuses = [
			ServiceOrder::STATUS_IN_PROGRESS,
			ServiceOrder::STATUS_LATE,
			ServiceOrder::STATUS_REVISION_REQUESTED,
		];

		if ( ! in_array( $order->status, $allowed_statuses, true ) ) {
			return [
				'success' => false,
				'message' => __( 'Extension cannot be requested for this order status.', 'wp-sell-services' ),
			];
		}

		// Check if there's already a pending request.
		$pending = $this->get_pending( $order_id );
		if ( $pending ) {
			return [
				'success' => false,
				'message' => __( 'There is already a pending extension request for this order.', 'wp-sell-services' ),
			];
		}

		// Validate extra days.
		$max_extension_days = (int) get_option( 'wpss_max_extension_days', 14 );
		if ( $extra_days < 1 || $extra_days > $max_extension_days ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: maximum days */
					__( 'Extension must be between 1 and %d days.', 'wp-sell-services' ),
					$max_extension_days
				),
			];
		}

		// Validate reason.
		if ( strlen( trim( $reason ) ) < 10 ) {
			return [
				'success' => false,
				'message' => __( 'Please provide a detailed reason for the extension request.', 'wp-sell-services' ),
			];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$table,
			[
				'order_id'     => $order_id,
				'requested_by' => $requested_by,
				'extra_days'   => $extra_days,
				'reason'       => sanitize_textarea_field( $reason ),
				'status'       => self::STATUS_PENDING,
				'created_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to create extension request. Please try again.', 'wp-sell-services' ),
			];
		}

		$request_id = (int) $wpdb->insert_id;

		// Determine who to notify.
		$is_vendor_request = $requested_by === $order->vendor_id;
		$notify_user_id    = $is_vendor_request ? $order->customer_id : $order->vendor_id;

		// Send notification.
		$this->notification_service->send(
			$notify_user_id,
			'extension_requested',
			__( 'Extension Request', 'wp-sell-services' ),
			sprintf(
				/* translators: %d: number of days */
				__( 'A %d-day extension has been requested for your order. Please review and respond.', 'wp-sell-services' ),
				$extra_days
			),
			[
				'order_id'   => $order_id,
				'request_id' => $request_id,
			]
		);

		// Log in conversation.
		$conversation_service = new ConversationService();
		$conversation = $conversation_service->get_by_order( $order_id );

		if ( $conversation ) {
			$conversation_service->add_system_message(
				$conversation->id,
				sprintf(
					/* translators: 1: days, 2: reason */
					__( 'Extension requested: %1$d days. Reason: %2$s', 'wp-sell-services' ),
					$extra_days,
					$reason
				)
			);
		}

		/**
		 * Fires after extension request is created.
		 *
		 * @param int   $request_id Request ID.
		 * @param int   $order_id   Order ID.
		 * @param array $data       Request data.
		 */
		do_action( 'wpss_extension_request_created', $request_id, $order_id, [
			'requested_by' => $requested_by,
			'extra_days'   => $extra_days,
			'reason'       => $reason,
		] );

		return [
			'success'    => true,
			'message'    => __( 'Extension request submitted successfully.', 'wp-sell-services' ),
			'request_id' => $request_id,
		];
	}

	/**
	 * Approve an extension request.
	 *
	 * @param int    $request_id       Request ID.
	 * @param int    $responded_by     User ID responding.
	 * @param string $response_message Optional response message.
	 * @return array Result with success status.
	 */
	public function approve( int $request_id, int $responded_by, string $response_message = '' ): array {
		$request = $this->get( $request_id );

		if ( ! $request ) {
			return [
				'success' => false,
				'message' => __( 'Extension request not found.', 'wp-sell-services' ),
			];
		}

		if ( self::STATUS_PENDING !== $request['status'] ) {
			return [
				'success' => false,
				'message' => __( 'This extension request has already been processed.', 'wp-sell-services' ),
			];
		}

		$order = $this->order_service->get( $request['order_id'] );
		if ( ! $order ) {
			return [
				'success' => false,
				'message' => __( 'Order not found.', 'wp-sell-services' ),
			];
		}

		// Update request status.
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[
				'status'           => self::STATUS_APPROVED,
				'responded_by'     => $responded_by,
				'response_message' => sanitize_textarea_field( $response_message ),
				'responded_at'     => current_time( 'mysql' ),
			],
			[ 'id' => $request_id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to update extension request.', 'wp-sell-services' ),
			];
		}

		// Extend the deadline.
		$this->order_service->extend_deadline( $request['order_id'], $request['extra_days'] );

		// If order was late, move back to in progress.
		if ( ServiceOrder::STATUS_LATE === $order->status ) {
			$this->order_service->update_status(
				$request['order_id'],
				ServiceOrder::STATUS_IN_PROGRESS,
				__( 'Deadline extended', 'wp-sell-services' )
			);
		}

		// Notify requester.
		$this->notification_service->send(
			$request['requested_by'],
			'extension_approved',
			__( 'Extension Approved', 'wp-sell-services' ),
			sprintf(
				/* translators: %d: number of days */
				__( 'Your %d-day extension request has been approved.', 'wp-sell-services' ),
				$request['extra_days']
			),
			[ 'order_id' => $request['order_id'] ]
		);

		// Log in conversation.
		$conversation_service = new ConversationService();
		$conversation = $conversation_service->get_by_order( $request['order_id'] );

		if ( $conversation ) {
			$conversation_service->add_system_message(
				$conversation->id,
				sprintf(
					/* translators: %d: number of days */
					__( 'Extension approved: Deadline extended by %d days.', 'wp-sell-services' ),
					$request['extra_days']
				)
			);
		}

		/**
		 * Fires after extension request is approved.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $request    Request data.
		 */
		do_action( 'wpss_extension_request_approved', $request_id, $request );

		return [
			'success' => true,
			'message' => __( 'Extension request approved.', 'wp-sell-services' ),
		];
	}

	/**
	 * Reject an extension request.
	 *
	 * @param int    $request_id       Request ID.
	 * @param int    $responded_by     User ID responding.
	 * @param string $response_message Rejection reason.
	 * @return array Result with success status.
	 */
	public function reject( int $request_id, int $responded_by, string $response_message = '' ): array {
		$request = $this->get( $request_id );

		if ( ! $request ) {
			return [
				'success' => false,
				'message' => __( 'Extension request not found.', 'wp-sell-services' ),
			];
		}

		if ( self::STATUS_PENDING !== $request['status'] ) {
			return [
				'success' => false,
				'message' => __( 'This extension request has already been processed.', 'wp-sell-services' ),
			];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[
				'status'           => self::STATUS_REJECTED,
				'responded_by'     => $responded_by,
				'response_message' => sanitize_textarea_field( $response_message ),
				'responded_at'     => current_time( 'mysql' ),
			],
			[ 'id' => $request_id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to update extension request.', 'wp-sell-services' ),
			];
		}

		// Notify requester.
		$this->notification_service->send(
			$request['requested_by'],
			'extension_rejected',
			__( 'Extension Rejected', 'wp-sell-services' ),
			__( 'Your extension request has been rejected.', 'wp-sell-services' ),
			[ 'order_id' => $request['order_id'] ]
		);

		// Log in conversation.
		$conversation_service = new ConversationService();
		$conversation = $conversation_service->get_by_order( $request['order_id'] );

		if ( $conversation ) {
			$message = __( 'Extension request rejected.', 'wp-sell-services' );
			if ( $response_message ) {
				$message .= ' ' . $response_message;
			}
			$conversation_service->add_system_message( $conversation->id, $message );
		}

		/**
		 * Fires after extension request is rejected.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $request    Request data.
		 */
		do_action( 'wpss_extension_request_rejected', $request_id, $request );

		return [
			'success' => true,
			'message' => __( 'Extension request rejected.', 'wp-sell-services' ),
		];
	}

	/**
	 * Format request row to array.
	 *
	 * @param object $row Database row.
	 * @return array
	 */
	private function format_request( object $row ): array {
		return [
			'id'               => (int) $row->id,
			'order_id'         => (int) $row->order_id,
			'requested_by'     => (int) $row->requested_by,
			'extra_days'       => (int) $row->extra_days,
			'reason'           => $row->reason,
			'status'           => $row->status,
			'responded_by'     => $row->responded_by ? (int) $row->responded_by : null,
			'response_message' => $row->response_message,
			'responded_at'     => $row->responded_at,
			'created_at'       => $row->created_at,
		];
	}

	/**
	 * Get status labels.
	 *
	 * @return array
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		];
	}
}
