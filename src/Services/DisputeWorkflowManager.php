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

defined( 'ABSPATH' ) || exit;

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

	/*
	 * NOTE: there is deliberately no init()/define_hooks() here.
	 *
	 * Every hook this class responds to is wired in Plugin.php — the cron
	 * handlers at ~:1888 and the dispute events at ~:1900, both through the
	 * lazy-init closure so the manager is only constructed on first fire.
	 *
	 * A second init() used to exist that registered all of them again. It was
	 * never called (nothing invoked it), so it was dead weight that would have
	 * double-fired every handler the moment anyone did call it. Plugin.php is
	 * the single wiring point — same rule as the comment at Plugin.php ~:1795
	 * about duplicate log_status_change listeners.
	 */

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['twice_daily'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => 'Twice Daily',
		);
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
	public function submit_response( int $dispute_id, int $user_id, string $response, array $attachments = array() ): array {
		global $wpdb;

		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return array(
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			);
		}

		// Check if dispute is still open for responses.
		$closed_statuses = array( DisputeService::STATUS_RESOLVED, DisputeService::STATUS_CLOSED );
		if ( in_array( $dispute->status, $closed_statuses, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'This dispute is no longer accepting responses.', 'wp-sell-services' ),
			);
		}

		// Check if user is part of the dispute.
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$dispute->order_id
			)
		);

		if ( ! $order || ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'You are not authorized to respond to this dispute.', 'wp-sell-services' ),
			);
		}

		// Determine response type.
		$response_type = 'response';
		if ( current_user_can( 'manage_options' ) ) {
			$response_type = 'admin_response';
		} elseif ( (int) $dispute->initiator_id === $user_id ) {
			$response_type = 'opener_response';
		}

		// Insert message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->messages_table,
			array(
				'dispute_id'  => $dispute_id,
				'sender_id'   => $user_id,
				'message'     => wp_kses_post( $response ),
				'sender_role' => $response_type,
				'attachments' => ! empty( $attachments ) ? wp_json_encode( $attachments ) : null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to submit response.', 'wp-sell-services' ),
			);
		}

		$message_id = (int) $wpdb->insert_id;

		// Update dispute response deadline.
		$this->update_response_deadline( $dispute_id, $user_id );

		// Update dispute status if it was awaiting response.
		if ( DisputeService::STATUS_OPEN === $dispute->status && (int) $dispute->initiator_id !== $user_id ) {
			$this->dispute_service->transition( $dispute_id, DisputeService::STATUS_PENDING );
		}

		/**
		 * Fires when a dispute response is submitted.
		 *
		 * @param int    $message_id Message ID.
		 * @param int    $dispute_id Dispute ID.
		 * @param int    $user_id    User ID.
		 */
		do_action( 'wpss_dispute_response_submitted', $message_id, $dispute_id, $user_id );

		return array(
			'success'    => true,
			'message'    => __( 'Response submitted successfully.', 'wp-sell-services' ),
			'message_id' => $message_id,
		);
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
				$message->attachments     = $this->decode_json_array( $message->attachments );
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
		$urls = array();
		foreach ( $attachment_ids as $id ) {
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				$urls[] = array(
					'id'        => $id,
					'url'       => $url,
					'filename'  => basename( get_attached_file( $id ) ?: '' ),
					'type'      => get_post_mime_type( $id ),
					'thumbnail' => wp_get_attachment_image_url( $id, 'thumbnail' ),
				);
			}
		}
		return $urls;
	}

	/**
	 * Decode JSON into an array safely.
	 *
	 * @param mixed $json JSON string.
	 * @return array
	 */
	private function decode_json_array( $json ): array {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
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
			return array(
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			);
		}

		$meta               = $dispute->meta;
		$meta['escalation'] = array(
			'reason'       => sanitize_textarea_field( $reason ),
			'escalated_by' => $escalated_by,
			'escalated_at' => current_time( 'mysql' ),
		);

		// The state machine refuses escalated, resolved and closed disputes.
		$moved = $this->dispute_service->transition(
			$dispute_id,
			DisputeService::STATUS_ESCALATED,
			array( 'fields' => array( 'meta' => wp_json_encode( $meta ) ) )
		);

		if ( ! $moved ) {
			return array(
				'success' => false,
				'message' => $this->dispute_service->last_error(),
			);
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

		return array(
			'success' => true,
			'message' => __( 'Dispute has been escalated to support.', 'wp-sell-services' ),
		);
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
			return array(
				'success' => false,
				'message' => __( 'Invalid admin user.', 'wp-sell-services' ),
			);
		}

		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return array(
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			);
		}

		global $wpdb;

		$meta                = $dispute->meta;
		$meta['assigned_to'] = $admin_id;
		$meta['assigned_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->disputes_table,
			array(
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $dispute_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to assign dispute.', 'wp-sell-services' ),
			);
		}

		// Notify assigned admin.
		$this->notification_service->send(
			$admin_id,
			'dispute_assigned',
			array(
				'dispute_id' => $dispute_id,
				'order_id'   => $dispute->order_id,
			)
		);

		return array(
			'success' => true,
			'message' => __( 'Dispute assigned successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Close a dispute without a ruling and give the order back.
	 *
	 * Every close goes through here - the opener withdrawing over REST, the
	 * admin picking Closed on the dispute screen, the bulk Close action - so
	 * the order is always restored to its pre-dispute status. Closing the
	 * dispute and releasing the order are ONE unit of work: either both land
	 * or neither does.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $user_id User ID.
	 * @param string $reason Cancellation reason.
	 * @return array Result with success status.
	 */
	public function cancel( int $dispute_id, int $user_id, string $reason = '' ): array {
		$dispute = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return array(
				'success' => false,
				'message' => __( 'Dispute not found.', 'wp-sell-services' ),
			);
		}

		// Only opener can cancel, or admin.
		if ( (int) $dispute->initiator_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'You are not authorized to cancel this dispute.', 'wp-sell-services' ),
			);
		}

		if ( ! $this->dispute_service->can_transition( (string) $dispute->status, DisputeService::STATUS_CLOSED ) ) {
			return array(
				'success' => false,
				'message' => __( 'This dispute has already been resolved or closed.', 'wp-sell-services' ),
			);
		}

		global $wpdb;

		$meta                 = $dispute->meta;
		$meta['cancellation'] = array(
			'reason'       => sanitize_textarea_field( $reason ),
			'cancelled_by' => $user_id,
			'cancelled_at' => current_time( 'mysql' ),
		);

		$wpdb->query( 'START TRANSACTION' );

		try {
			$restored = $this->restore_order_status( (int) $dispute->order_id );
		} catch ( \Throwable $e ) {
			$restored = false;
		}

		if ( ! $restored ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'The order could not be restored, so the dispute stays open.', 'wp-sell-services' ),
			);
		}

		$closed = $this->dispute_service->transition(
			$dispute_id,
			DisputeService::STATUS_CLOSED,
			array(
				'note'   => $reason,
				'fields' => array( 'meta' => wp_json_encode( $meta ) ),
			)
		);

		if ( ! $closed ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => $this->dispute_service->last_error(),
			);
		}

		$wpdb->query( 'COMMIT' );

		/**
		 * Fires when a dispute is cancelled.
		 *
		 * @param int    $dispute_id Dispute ID.
		 * @param int    $user_id    Cancelled by user ID.
		 * @param string $reason     Cancellation reason.
		 */
		do_action( 'wpss_dispute_cancelled', $dispute_id, $user_id, $reason );

		return array(
			'success' => true,
			'message' => __( 'Dispute cancelled successfully.', 'wp-sell-services' ),
		);
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
				(int) $dispute->id,
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
				(int) $dispute->id,
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
				$this->notification_service->send(
					$remind_user,
					'dispute_response_reminder',
					array(
						'dispute_id'        => $dispute->id,
						'order_id'          => $dispute->order_id,
						'response_deadline' => $dispute->response_deadline,
					)
				);

				// Mark reminder as sent.
					$meta                  = $this->decode_json_array( $dispute->meta );
					$meta['reminder_sent'] = current_time( 'mysql' );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$this->disputes_table,
					array( 'meta' => wp_json_encode( $meta ) ),
					array( 'id' => $dispute->id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Auto-open disputes for orders stuck in late status.
	 *
	 * When an order remains in "late" status beyond a configurable number of days,
	 * a dispute is automatically opened on behalf of the buyer.
	 *
	 * @return void
	 */
	public function auto_open_disputes_for_late_orders(): void {
		$allow_disputes    = (bool) wpss_get_option( 'orders', 'allow_disputes' );
		$auto_dispute_days = (int) wpss_get_option( 'orders', 'auto_dispute_late_days' );

		// Bail if disputes are disabled or auto-dispute is turned off (0 = disabled).
		if ( ! $allow_disputes || $auto_dispute_days <= 0 ) {
			return;
		}

		global $wpdb;
		$orders_table   = $wpdb->prefix . 'wpss_orders';
		$disputes_table = $this->disputes_table;

		// Find orders in "late" status for longer than the configured threshold
		// that do NOT already have a dispute. Use delivery_deadline (not updated_at)
		// to measure actual days since order became overdue, unaffected by status changes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$late_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.id, o.customer_id, o.vendor_id
				FROM {$orders_table} o
				LEFT JOIN {$disputes_table} d ON d.order_id = o.id
				WHERE o.status = %s
				AND o.delivery_deadline < DATE_SUB( %s, INTERVAL %d DAY )
				AND d.id IS NULL",
				\WPSellServices\Models\ServiceOrder::STATUS_LATE,
				current_time( 'mysql' ),
				$auto_dispute_days
			)
		);

		foreach ( $late_orders as $order ) {
			$this->dispute_service->open(
				(int) $order->id,
				(int) $order->customer_id,
				// The SLUG, not the label. This passed
				// __( 'Late delivery', 'wp-sell-services' ), so the auto-opened
				// dispute stored a translated display string in a column that
				// holds one of Dispute::get_reasons()' keys. Two consequences:
				// the dispute list matched no key and fell back to printing the
				// raw value, and because the string was translated AT WRITE TIME
				// the database kept whatever locale the cron happened to run in
				// - a German site stored "Verspatete Lieferung" permanently, and
				// no later locale switch could ever re-translate it.
				\WPSellServices\Models\Dispute::REASON_LATE_DELIVERY,
				sprintf(
					/* translators: %d: number of days the order has been late */
					__( 'Dispute auto-opened: Order has been late for more than %d days without delivery.', 'wp-sell-services' ),
					$auto_dispute_days
				)
			);
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
			array(
				'response_deadline' => $new_deadline,
				'last_response_by'  => $responder_id,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $dispute_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Orders currently being handed back by cancel(), keyed by order ID.
	 *
	 * Read by OrderWorkflowManager::handle_order_completed(): an order that was
	 * completed before the dispute already had its commission recorded and its
	 * completion hooks fired, so restoring it to completed must not run them
	 * again.
	 *
	 * @since 1.7.1
	 * @var array<int, true>
	 */
	private static array $restoring = array();

	/**
	 * Whether this order is being restored from a cancelled dispute right now.
	 *
	 * @since 1.7.1
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function is_restoring_order( int $order_id ): bool {
		return isset( self::$restoring[ $order_id ] );
	}

	/**
	 * Restore the order to its pre-dispute status.
	 *
	 * Through OrderService::update_status() so the move is validated, logged
	 * and announced like any other; disputed -> each disputable status is in
	 * the natural map, so the opener can withdraw without admin rights.
	 *
	 * @param int $order_id Order ID.
	 * @return bool True when the order is back on its pre-dispute status.
	 */
	private function restore_order_status( int $order_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return false;
		}

		$meta            = $this->decode_json_array( $order->meta );
		$previous_status = (string) ( $meta['status_before_dispute'] ?? '' );

		// Rows written by the pre-1.6 bug recorded `disputed` as the status to
		// go back to; that would leave the order stuck, so treat it as unknown.
		if ( '' === $previous_status || \WPSellServices\Models\ServiceOrder::STATUS_DISPUTED === $previous_status ) {
			$previous_status = \WPSellServices\Models\ServiceOrder::STATUS_IN_PROGRESS;
		}

		self::$restoring[ $order_id ] = true;

		try {
			return ( new OrderService() )->update_status( $order_id, $previous_status, __( 'Dispute closed; order restored.', 'wp-sell-services' ) );
		} finally {
			unset( self::$restoring[ $order_id ] );
		}
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
			( new EmailService() )->send(
				$admin_email,
				$subject,
				EmailService::TYPE_DISPUTE_ESCALATED,
				array(
					'recipient'  => get_user_by( 'email', $admin_email ),
					'dispute_id' => $dispute_id,
					'order_id'   => $dispute->order_id,
					'reason'     => $reason,
				)
			);
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
			$meta = $this->decode_json_array( $order->meta );

			// Only record a fallback if nothing captured the real pre-dispute
			// status. DisputeService::open() now writes it BEFORE flipping the
			// order to `disputed`; this hook runs afterwards, so reading
			// $order->status here yields `disputed` itself. Overwriting with that
			// is what made cancel/resolve restore an order to `disputed` and
			// leave it permanently stuck. Never clobber the good value.
			if ( ! isset( $meta['status_before_dispute'] ) && \WPSellServices\Models\ServiceOrder::STATUS_DISPUTED !== $order->status ) {
				$meta['status_before_dispute'] = $order->status;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'wpss_orders',
				array( 'meta' => wp_json_encode( $meta ) ),
				array( 'id' => $order_id ),
				array( '%s' ),
				array( '%d' )
			);

			// Set initial response deadline.
			$response_days = (int) get_option( 'wpss_dispute_response_days', 3 );
			$deadline      = gmdate( 'Y-m-d H:i:s', strtotime( "+{$response_days} days" ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->disputes_table,
				array( 'response_deadline' => $deadline ),
				array( 'id' => $dispute_id ),
				array( '%s' ),
				array( '%d' )
			);

			// Notify the other party.
			$notify_user = (int) $opened_by === (int) $order->customer_id
				? (int) $order->vendor_id
				: (int) $order->customer_id;

			$this->notification_service->send(
				$notify_user,
				'dispute_opened',
				array(
					'dispute_id'        => $dispute_id,
					'order_id'          => $order_id,
					'opened_by'         => $opened_by,
					'reason'            => $data['reason'] ?? '',
					'response_deadline' => $deadline,
				)
			);
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

		$this->notification_service->send(
			$notify_user,
			'dispute_response_received',
			array(
				'dispute_id' => $dispute_id,
				'order_id'   => $dispute->order_id,
				'from_user'  => $user_id,
			)
		);
	}

	/**
	 * Handle evidence added event.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_evidence_added( int $dispute_id, int $user_id ): void {
		// Similar to response submitted - notify other party.
		$this->on_response_submitted( 0, $dispute_id, $user_id );
	}


	/**
	 * Get dispute timeline.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array Timeline events.
	 */
	public function get_timeline( int $dispute_id ): array {
		$dispute = $this->dispute_service->get( $dispute_id );

		// ONE read. Evidence and messages used to be two stores, so this
		// merged both; they are now the same table, and reading it twice would
		// show every message on the timeline twice.
		$conversation = $this->dispute_service->get_evidence( $dispute_id );

		$timeline = array();

		// Add dispute creation.
		if ( $dispute ) {
			$timeline[] = array(
				'type'       => 'dispute_opened',
				'user_id'    => $dispute->initiator_id,
				'content'    => $dispute->description,
				'created_at' => self::timeline_datetime( $dispute->created_at ),
			);
		}

		foreach ( $conversation as $item ) {
			$item_type = (string) ( $item['type'] ?? 'text' );
			$is_text   = '' === $item_type || 'text' === $item_type;

			// A plain reply reads as a message; anything carrying a URL (an
			// upload or a link) reads as evidence, with its caption as the
			// line and the URL kept in data for the renderer.
			$timeline[] = $is_text
				? array(
					'type'        => 'message',
					'user_id'     => (int) ( $item['user_id'] ?? 0 ),
					'content'     => (string) ( $item['content'] ?? '' ),
					'attachments' => $item['attachments'] ?? array(),
					'created_at'  => self::timeline_datetime( $item['created_at'] ?? '' ),
				)
				: array(
					'type'       => 'evidence',
					'user_id'    => (int) ( $item['user_id'] ?? 0 ),
					'content'    => (string) ( $item['description'] ?? '' ),
					'data'       => array(
						'evidence_type' => $item_type,
						'content'       => (string) ( $item['content'] ?? '' ),
					),
					'created_at' => self::timeline_datetime( $item['created_at'] ?? '' ),
				);
		}

		// Sort by date.
		usort(
			$timeline,
			function ( $a, $b ) {
				return strtotime( $a['created_at'] ) <=> strtotime( $b['created_at'] );
			}
		);

		// Enrich with user data.
		foreach ( $timeline as &$event ) {
			$user                 = get_userdata( $event['user_id'] );
			$event['user_name']   = $user ? $user->display_name : __( 'System', 'wp-sell-services' );
			$event['user_avatar'] = get_avatar_url( $event['user_id'], array( 'size' => 48 ) );
		}

		return $timeline;
	}

	/**
	 * Normalise a timeline entry's created_at to a MySQL datetime string.
	 *
	 * The timeline is assembled from three sources that do NOT agree on type:
	 * the Dispute model and the Message model hydrate created_at to a
	 * DateTimeImmutable, while evidence rows are JSON-decoded associative arrays
	 * whose created_at is still a string.
	 *
	 * That mixture fataled twice over. The usort() comparator called strtotime()
	 * on it - "strtotime(): Argument #1 must be of type string, DateTimeImmutable
	 * given" - which 500'd the dispute detail view. And
	 * templates/dashboard/sections/disputes.php hands the same value to
	 * mysql2date(), which would fatal on the object entries for the same reason.
	 *
	 * Normalising here, as each entry is built, fixes every consumer at once
	 * instead of teaching each one to handle both shapes.
	 *
	 * @since 1.5.1
	 *
	 * @param mixed $value Raw created_at from a model or an array.
	 * @return string MySQL datetime, or '' when there is nothing usable.
	 */
	private static function timeline_datetime( $value ): string {
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d H:i:s' );
		}

		return is_string( $value ) ? $value : '';
	}
}
