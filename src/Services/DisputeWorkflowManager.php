<?php
/**
 * Dispute Workflow Manager
 *
 * Handles dispute escalation, deadlines, and automated workflows.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

/**
 * Manages dispute workflows, escalation, and automated actions.
 *
 * @since 1.0.0
 */
class DisputeWorkflowManager {

	/**
	 * Dispute service.
	 *
	 * @var DisputeService
	 */
	private DisputeService $dispute_service;

	/**
	 * Notification service.
	 *
	 * @var NotificationService
	 */
	private NotificationService $notification_service;

	/**
	 * Messages table name.
	 *
	 * @var string
	 */
	private string $messages_table;

	/**
	 * Disputes table name.
	 *
	 * @var string
	 */
	private string $disputes_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->disputes_table       = $wpdb->prefix . 'wpss_disputes';
		$this->messages_table       = $wpdb->prefix . 'wpss_dispute_messages';
		$this->dispute_service      = new DisputeService();
		$this->notification_service = new NotificationService();
	}

	/**
	 * Initialize workflow hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Cron jobs.
		add_action( 'wpss_cron_daily', [ $this, 'check_response_deadlines' ] );
		add_action( 'wpss_cron_daily', [ $this, 'auto_escalate_disputes' ] );
		add_action( 'wpss_cron_daily', [ $this, 'send_reminder_notifications' ] );

		// Register cron schedules.
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

		// Schedule events on plugin activation.
		if ( ! wp_next_scheduled( 'wpss_cron_daily' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_cron_daily' );
		}

		// Hooks for dispute events.
		add_action( 'wpss_dispute_opened', [ $this, 'on_dispute_opened' ], 10, 4 );
		add_action( 'wpss_dispute_response_submitted', [ $this, 'on_response_submitted' ], 10, 3 );
		add_action( 'wpss_dispute_evidence_added', [ $this, 'on_evidence_added' ], 10, 3 );
		add_action( 'wpss_dispute_resolved', [ $this, 'on_dispute_resolved' ], 10, 4 );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['twice_daily'] = [
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily', 'wp-sell-services' ),
		];
		return $schedules;
	}

	/**
	 * Submit a response to a dispute.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $user_id Responder user ID.
	 * @param string $response Response text.
	 * @param array  $attachments Attachment IDs.
	 * @return array Result with success status.
	 */
	public function submit_response( int $dispute_id, int $user_id, string $response, array $attachments = [] ): array {
		global $wpdb;

		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return [
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			];
		}

		// Check if dispute is still open for responses.
		$closed_statuses = [ DisputeService::STATUS_RESOLVED, DisputeService::STATUS_CLOSED ];
		if ( in_array( $dispute->status, $closed_statuses, true ) ) {
			return [
				'success' => false,
				'message' => __( 'This dispute is no longer accepting responses.', 'wp-sell-services' ),
			];
		}

		// Check if user is part of the dispute.
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$dispute->order_id
			)
		);

		if ( ! $order || ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
			return [
				'success' => false,
				'message' => __( 'You are not authorized to respond to this dispute.', 'wp-sell-services' ),
			];
		}

		// Determine response type.
		$response_type = 'response';
		if ( current_user_can( 'manage_options' ) ) {
			$response_type = 'admin_response';
		} elseif ( (int) $dispute->initiated_by === $user_id ) {
			$response_type = 'opener_response';
		}

		// Insert message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->messages_table,
			[
				'dispute_id'  => $dispute_id,
				'sender_id'   => $user_id,
				'message'     => wp_kses_post( $response ),
				'sender_role' => $response_type,
				'attachments' => ! empty( $attachments ) ? wp_json_encode( $attachments ) : null,
				'created_at'  => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to submit response.', 'wp-sell-services' ),
			];
		}

		$message_id = (int) $wpdb->insert_id;

		// Update dispute response deadline.
		$this->update_response_deadline( $dispute_id, $user_id );

		// Update dispute status if it was awaiting response.
		if ( DisputeService::STATUS_OPEN === $dispute->status && (int) $dispute->initiated_by !== $user_id ) {
			$this->dispute_service->update_status( $dispute_id, DisputeService::STATUS_PENDING );
		}

		/**
		 * Fires when a dispute response is submitted.
		 *
		 * @param int    $message_id Message ID.
		 * @param int    $dispute_id Dispute ID.
		 * @param int    $user_id    User ID.
		 */
		do_action( 'wpss_dispute_response_submitted', $message_id, $dispute_id, $user_id );

		return [
			'success'    => true,
			'message'    => __( 'Response submitted successfully.', 'wp-sell-services' ),
			'message_id' => $message_id,
		];
	}

	/**
	 * Get dispute messages.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array Messages.
	 */
	public function get_messages( int $dispute_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name, u.user_email
				FROM {$this->messages_table} m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.dispute_id = %d
				ORDER BY m.created_at ASC",
				$dispute_id
			)
		);

		foreach ( $messages as $message ) {
			if ( $message->attachments ) {
				$message->attachments = json_decode( $message->attachments, true );
				$message->attachment_urls = $this->get_attachment_urls( $message->attachments );
			}
		}

		return $messages;
	}

	/**
	 * Get attachment URLs.
	 *
	 * @param array $attachment_ids Attachment IDs.
	 * @return array Attachment URLs with metadata.
	 */
	private function get_attachment_urls( array $attachment_ids ): array {
		$urls = [];
		foreach ( $attachment_ids as $id ) {
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				$urls[] = [
					'id'        => $id,
					'url'       => $url,
					'filename'  => basename( get_attached_file( $id ) ),
					'type'      => get_post_mime_type( $id ),
					'thumbnail' => wp_get_attachment_image_url( $id, 'thumbnail' ),
				];
			}
		}
		return $urls;
	}

	/**
	 * Escalate dispute to admin/support.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param string $reason Escalation reason.
	 * @param int    $escalated_by User ID.
	 * @return array Result with success status.
	 */
	public function escalate( int $dispute_id, string $reason, int $escalated_by ): array {
		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return [
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			];
		}

		if ( DisputeService::STATUS_ESCALATED === $dispute->status ) {
			return [
				'success' => false,
				'message' => __( 'This dispute is already escalated.', 'wp-sell-services' ),
			];
		}

		// Update dispute meta with escalation info.
		global $wpdb;

		$meta = $dispute->meta ?? [];
		$meta['escalation'] = [
			'reason'       => sanitize_textarea_field( $reason ),
			'escalated_by' => $escalated_by,
			'escalated_at' => current_time( 'mysql' ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->disputes_table,
			[
				'status'     => DisputeService::STATUS_ESCALATED,
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $dispute_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to escalate dispute.', 'wp-sell-services' ),
			];
		}

		// Notify admins.
		$this->notify_admins_of_escalation( $dispute_id, $dispute, $reason );

		/**
		 * Fires when a dispute is escalated.
		 *
		 * @param int    $dispute_id   Dispute ID.
		 * @param string $reason       Escalation reason.
		 * @param int    $escalated_by User ID.
		 */
		do_action( 'wpss_dispute_escalated', $dispute_id, $reason, $escalated_by );

		return [
			'success' => true,
			'message' => __( 'Dispute has been escalated to support.', 'wp-sell-services' ),
		];
	}

	/**
	 * Assign dispute to admin.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @param int $admin_id Admin user ID.
	 * @return array Result with success status.
	 */
	public function assign_to_admin( int $dispute_id, int $admin_id ): array {
		if ( ! user_can( $admin_id, 'manage_options' ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid admin user.', 'wp-sell-services' ),
			];
		}

		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return [
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			];
		}

		global $wpdb;

		$meta = $dispute->meta ?? [];
		$meta['assigned_to'] = $admin_id;
		$meta['assigned_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->disputes_table,
			[
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $dispute_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to assign dispute.', 'wp-sell-services' ),
			];
		}

		// Notify assigned admin.
		$this->notification_service->send( $admin_id, 'dispute_assigned', [
			'dispute_id' => $dispute_id,
			'order_id'   => $dispute->order_id,
		] );

		return [
			'success' => true,
			'message' => __( 'Dispute assigned successfully.', 'wp-sell-services' ),
		];
	}

	/**
	 * Cancel a dispute (by opener only).
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $user_id User ID.
	 * @param string $reason Cancellation reason.
	 * @return array Result with success status.
	 */
	public function cancel( int $dispute_id, int $user_id, string $reason = '' ): array {
		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return [
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			];
		}

		// Only opener can cancel, or admin.
		if ( (int) $dispute->initiated_by !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'message' => __( 'You are not authorized to cancel this dispute.', 'wp-sell-services' ),
			];
		}

		// Can't cancel resolved disputes.
		if ( DisputeService::STATUS_RESOLVED === $dispute->status ) {
			return [
				'success' => false,
				'message' => __( 'Resolved disputes cannot be cancelled.', 'wp-sell-services' ),
			];
		}

		global $wpdb;

		$meta = $dispute->meta ?? [];
		$meta['cancellation'] = [
			'reason'       => sanitize_textarea_field( $reason ),
			'cancelled_by' => $user_id,
			'cancelled_at' => current_time( 'mysql' ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->disputes_table,
			[
				'status'     => DisputeService::STATUS_CLOSED,
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $dispute_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to cancel dispute.', 'wp-sell-services' ),
			];
		}

		// Restore order status to previous state if possible.
		$this->restore_order_status( $dispute->order_id );

		/**
		 * Fires when a dispute is cancelled.
		 *
		 * @param int    $dispute_id Dispute ID.
		 * @param int    $user_id    Cancelled by user ID.
		 * @param string $reason     Cancellation reason.
		 */
		do_action( 'wpss_dispute_cancelled', $dispute_id, $user_id, $reason );

		return [
			'success' => true,
			'message' => __( 'Dispute cancelled successfully.', 'wp-sell-services' ),
		];
	}

	/**
	 * Check response deadlines and send reminders.
	 *
	 * @return void
	 */
	public function check_response_deadlines(): void {
		global $wpdb;

		$response_days = (int) get_option( 'wpss_dispute_response_days', 3 );
		$deadline      = gmdate( 'Y-m-d H:i:s', strtotime( "-{$response_days} days" ) );

		// Find disputes awaiting response past deadline.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$disputes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, o.customer_id, o.vendor_id
				FROM {$this->disputes_table} d
				LEFT JOIN {$wpdb->prefix}wpss_orders o ON d.order_id = o.id
				WHERE d.status = %s
				AND d.response_deadline < %s
				AND d.response_deadline IS NOT NULL",
				DisputeService::STATUS_OPEN,
				current_time( 'mysql' )
			)
		);

		foreach ( $disputes as $dispute ) {
			// Auto-escalate if no response after deadline.
			$this->escalate(
				$dispute->id,
				__( 'Auto-escalated: No response from other party within deadline.', 'wp-sell-services' ),
				0 // System action.
			);
		}
	}

	/**
	 * Auto-escalate disputes after configurable days.
	 *
	 * @return void
	 */
	public function auto_escalate_disputes(): void {
		global $wpdb;

		$auto_escalate_days = (int) get_option( 'wpss_dispute_auto_escalate_days', 7 );

		if ( $auto_escalate_days <= 0 ) {
			return;
		}

		$deadline = gmdate( 'Y-m-d H:i:s', strtotime( "-{$auto_escalate_days} days" ) );

		// Find disputes in pending status for too long.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$disputes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->disputes_table}
				WHERE status = %s
				AND updated_at < %s",
				DisputeService::STATUS_PENDING,
				$deadline
			)
		);

		foreach ( $disputes as $dispute ) {
			$this->escalate(
				$dispute->id,
				__( 'Auto-escalated: Dispute unresolved for extended period.', 'wp-sell-services' ),
				0 // System action.
			);
		}
	}

	/**
	 * Send reminder notifications.
	 *
	 * @return void
	 */
	public function send_reminder_notifications(): void {
		global $wpdb;

		// Remind parties of pending disputes approaching deadline.
		$reminder_days = (int) get_option( 'wpss_dispute_reminder_days', 2 );

		if ( $reminder_days <= 0 ) {
			return;
		}

		$reminder_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$reminder_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$disputes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, o.customer_id, o.vendor_id
				FROM {$this->disputes_table} d
				LEFT JOIN {$wpdb->prefix}wpss_orders o ON d.order_id = o.id
				WHERE d.status IN (%s, %s)
				AND d.response_deadline BETWEEN %s AND %s
				AND (d.meta NOT LIKE %s OR d.meta IS NULL)",
				DisputeService::STATUS_OPEN,
				DisputeService::STATUS_PENDING,
				current_time( 'mysql' ),
				$reminder_date,
				'%reminder_sent%'
			)
		);

		foreach ( $disputes as $dispute ) {
			// Determine who needs reminder.
			$remind_user = $this->get_awaiting_response_user( $dispute );

			if ( $remind_user ) {
				$this->notification_service->send( $remind_user, 'dispute_response_reminder', [
					'dispute_id'        => $dispute->id,
					'order_id'          => $dispute->order_id,
					'response_deadline' => $dispute->response_deadline,
				] );

				// Mark reminder as sent.
				$meta = json_decode( $dispute->meta, true ) ?? [];
				$meta['reminder_sent'] = current_time( 'mysql' );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$this->disputes_table,
					[ 'meta' => wp_json_encode( $meta ) ],
					[ 'id' => $dispute->id ],
					[ '%s' ],
					[ '%d' ]
				);
			}
		}
	}

	/**
	 * Get user awaiting response.
	 *
	 * @param object $dispute Dispute object.
	 * @return int|null User ID or null.
	 */
	private function get_awaiting_response_user( object $dispute ): ?int {
		global $wpdb;

		// Get latest message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest_message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT sender_id FROM {$this->messages_table}
				WHERE dispute_id = %d
				ORDER BY created_at DESC
				LIMIT 1",
				$dispute->id
			)
		);

		if ( ! $latest_message ) {
			// No messages yet, remind the other party (not opener).
			return (int) $dispute->initiated_by === (int) $dispute->customer_id
				? (int) $dispute->vendor_id
				: (int) $dispute->customer_id;
		}

		// Remind the party who hasn't responded.
		$last_responder = (int) $latest_message->sender_id;

		if ( $last_responder === (int) $dispute->customer_id ) {
			return (int) $dispute->vendor_id;
		} elseif ( $last_responder === (int) $dispute->vendor_id ) {
			return (int) $dispute->customer_id;
		}

		return null;
	}

	/**
	 * Update response deadline.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @param int $responder_id Responder user ID.
	 * @return void
	 */
	private function update_response_deadline( int $dispute_id, int $responder_id ): void {
		global $wpdb;

		$response_days = (int) get_option( 'wpss_dispute_response_days', 3 );
		$new_deadline  = gmdate( 'Y-m-d H:i:s', strtotime( "+{$response_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->disputes_table,
			[
				'response_deadline' => $new_deadline,
				'last_response_by'  => $responder_id,
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $dispute_id ],
			[ '%s', '%d', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Restore order status after dispute cancellation.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function restore_order_status( int $order_id ): void {
		global $wpdb;

		// Get order's previous status from meta if available.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return;
		}

		$meta            = json_decode( $order->meta, true ) ?? [];
		$previous_status = $meta['status_before_dispute'] ?? 'in_progress';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			[ 'status' => $previous_status ],
			[ 'id' => $order_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Notify admins of escalation.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param object $dispute Dispute object.
	 * @param string $reason Escalation reason.
	 * @return void
	 */
	private function notify_admins_of_escalation( int $dispute_id, object $dispute, string $reason ): void {
		$admin_email = get_option( 'wpss_dispute_admin_email', get_option( 'admin_email' ) );

		$subject = sprintf(
			/* translators: %d: dispute ID */
			__( '[Action Required] Dispute #%d has been escalated', 'wp-sell-services' ),
			$dispute_id
		);

		$message = sprintf(
			/* translators: 1: dispute ID, 2: order ID, 3: reason */
			__( "Dispute #%1\$d for Order #%2\$d has been escalated.\n\nReason: %3\$s\n\nPlease review and resolve this dispute.", 'wp-sell-services' ),
			$dispute_id,
			$dispute->order_id,
			$reason
		);

		if ( EmailService::is_type_enabled( 'dispute_admin' ) ) {
			wp_mail( $admin_email, $subject, $message );
		}
	}

	/**
	 * Handle dispute opened event.
	 *
	 * @param int   $dispute_id Dispute ID.
	 * @param int   $order_id Order ID.
	 * @param int   $opened_by User ID.
	 * @param array $data Dispute data.
	 * @return void
	 */
	public function on_dispute_opened( int $dispute_id, int $order_id, int $opened_by, array $data ): void {
		global $wpdb;

		// Store previous order status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, customer_id, vendor_id, meta FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$order_id
			)
		);

		if ( $order ) {
			$meta                          = json_decode( $order->meta, true ) ?? [];
			$meta['status_before_dispute'] = $order->status;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'wpss_orders',
				[ 'meta' => wp_json_encode( $meta ) ],
				[ 'id' => $order_id ],
				[ '%s' ],
				[ '%d' ]
			);

			// Set initial response deadline.
			$response_days = (int) get_option( 'wpss_dispute_response_days', 3 );
			$deadline      = gmdate( 'Y-m-d H:i:s', strtotime( "+{$response_days} days" ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->disputes_table,
				[ 'response_deadline' => $deadline ],
				[ 'id' => $dispute_id ],
				[ '%s' ],
				[ '%d' ]
			);

			// Notify the other party.
			$notify_user = (int) $opened_by === (int) $order->customer_id
				? (int) $order->vendor_id
				: (int) $order->customer_id;

			$this->notification_service->send( $notify_user, 'dispute_opened', [
				'dispute_id'        => $dispute_id,
				'order_id'          => $order_id,
				'opened_by'         => $opened_by,
				'reason'            => $data['reason'] ?? '',
				'response_deadline' => $deadline,
			] );
		}
	}

	/**
	 * Handle response submitted event.
	 *
	 * @param int $message_id Message ID.
	 * @param int $dispute_id Dispute ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_response_submitted( int $message_id, int $dispute_id, int $user_id ): void {
		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$dispute->order_id
			)
		);

		if ( ! $order ) {
			return;
		}

		// Notify the other party.
		$notify_user = (int) $user_id === (int) $order->customer_id
			? (int) $order->vendor_id
			: (int) $order->customer_id;

		$this->notification_service->send( $notify_user, 'dispute_response_received', [
			'dispute_id' => $dispute_id,
			'order_id'   => $dispute->order_id,
			'from_user'  => $user_id,
		] );
	}

	/**
	 * Handle evidence added event.
	 *
	 * @param int $evidence_id Evidence ID.
	 * @param int $dispute_id Dispute ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_evidence_added( int $evidence_id, int $dispute_id, int $user_id ): void {
		// Similar to response submitted - notify other party.
		$this->on_response_submitted( $evidence_id, $dispute_id, $user_id );
	}

	/**
	 * Handle dispute resolved event.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param string $resolution Resolution type.
	 * @param object $dispute Dispute object.
	 * @param float  $refund_amount Refund amount.
	 * @return void
	 */
	public function on_dispute_resolved( int $dispute_id, string $resolution, object $dispute, float $refund_amount ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$dispute->order_id
			)
		);

		if ( ! $order ) {
			return;
		}

		// Notify both parties.
		$users = [ (int) $order->customer_id, (int) $order->vendor_id ];

		foreach ( $users as $user_id ) {
			$this->notification_service->send( $user_id, 'dispute_resolved', [
				'dispute_id'    => $dispute_id,
				'order_id'      => $dispute->order_id,
				'resolution'    => $resolution,
				'refund_amount' => $refund_amount,
			] );
		}
	}

	/**
	 * Get dispute timeline.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array Timeline events.
	 */
	public function get_timeline( int $dispute_id ): array {
		$dispute  = $this->dispute_service->get( $dispute_id );
		$evidence = $this->dispute_service->get_evidence( $dispute_id );
		$messages = $this->get_messages( $dispute_id );

		$timeline = [];

		// Add dispute creation.
		if ( $dispute ) {
			$timeline[] = [
				'type'       => 'dispute_opened',
				'user_id'    => $dispute->initiated_by,
				'content'    => $dispute->description,
				'created_at' => $dispute->created_at,
			];
		}

		// Add evidence items.
		foreach ( $evidence as $item ) {
			$timeline[] = [
				'type'       => 'evidence',
				'user_id'    => $item->user_id,
				'content'    => $item->description,
				'data'       => [
					'evidence_type' => $item->type,
					'content'       => $item->content,
				],
				'created_at' => $item->created_at,
			];
		}

		// Add messages.
		foreach ( $messages as $message ) {
			$timeline[] = [
				'type'        => 'message',
				'user_id'     => $message->sender_id,
				'content'     => $message->message,
				'attachments' => $message->attachment_urls ?? [],
				'created_at'  => $message->created_at,
			];
		}

		// Sort by date.
		usort( $timeline, function ( $a, $b ) {
			return strtotime( $a['created_at'] ) - strtotime( $b['created_at'] );
		} );

		// Enrich with user data.
		foreach ( $timeline as &$event ) {
			$user = get_userdata( $event['user_id'] );
			$event['user_name']   = $user ? $user->display_name : __( 'System', 'wp-sell-services' );
			$event['user_avatar'] = get_avatar_url( $event['user_id'], [ 'size' => 48 ] );
		}

		return $timeline;
	}
}
