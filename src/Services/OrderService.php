<?php
/**
 * Order Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\Service;

/**
 * Handles service order business logic.
 *
 * @since 1.0.0
 */
class OrderService {

	/**
	 * Get order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return ServiceOrder|null
	 */
	public function get( int $order_id ): ?ServiceOrder {
		return wpss_get_order( $order_id );
	}

	/**
	 * Get order by order number.
	 *
	 * @param string $order_number Order number.
	 * @return ServiceOrder|null
	 */
	public function get_by_number( string $order_number ): ?ServiceOrder {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_number = %s",
				$order_number
			)
		);

		return $row ? ServiceOrder::from_db( $row ) : null;
	}

	/**
	 * Get orders for customer.
	 *
	 * @param int   $customer_id Customer user ID.
	 * @param array $args        Query args.
	 * @return ServiceOrder[]
	 */
	public function get_customer_orders( int $customer_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'customer_id = %d' );
		$params = array( $customer_id );

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'created_at DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, array( $args['limit'], $args['offset'] ) )
			)
		);

		return array_map( fn( $row ) => ServiceOrder::from_db( $row ), $rows );
	}

	/**
	 * Get orders for vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query args.
	 * @return ServiceOrder[]
	 */
	public function get_vendor_orders( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'vendor_id = %d' );
		$params = array( $vendor_id );

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'created_at DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, array( $args['limit'], $args['offset'] ) )
			)
		);

		return array_map( fn( $row ) => ServiceOrder::from_db( $row ), $rows );
	}

	/**
	 * Update order status.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @param string $note       Optional status note.
	 * @return bool
	 */
	public function update_status( int $order_id, string $new_status, string $note = '' ): bool {
		$order = $this->get( $order_id );

		if ( ! $order ) {
			return false;
		}

		$old_status = $order->status;

		// Skip if already in the target status (prevents duplicate system messages from cron re-runs).
		if ( $old_status === $new_status ) {
			return true;
		}

		// Validate status transition.
		if ( ! $this->can_transition( $old_status, $new_status ) ) {
			return false;
		}

		/**
		 * Filter whether an order status change should proceed.
		 *
		 * Return false to prevent the status change, or a WP_Error to prevent
		 * and provide an error reason.
		 *
		 * @since 1.1.0
		 * @param bool|WP_Error $allow      Whether to allow the status change. Default true.
		 * @param int           $order_id   Order ID.
		 * @param string        $new_status New status being set.
		 * @param string        $old_status Current order status.
		 */
		$allow = apply_filters( 'wpss_pre_order_status_change', true, $order_id, $new_status, $old_status );

		if ( false === $allow || is_wp_error( $allow ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$data = array(
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		);

		// Set timestamps based on status.
		if ( ServiceOrder::STATUS_IN_PROGRESS === $new_status && ! $order->started_at ) {
			$data['started_at'] = current_time( 'mysql' );
		}

		if ( ServiceOrder::STATUS_COMPLETED === $new_status ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $table, $data, array( 'id' => $order_id ) );

		if ( false === $result ) {
			return false;
		}

		// Determine whether the transition bypassed the natural state machine.
		// can_transition() above already approved it, so false from
		// can_transition_naturally() here means the admin/manage_orders cap
		// short-circuit at L237 accepted something the map would have rejected.
		$is_forced = ! $this->can_transition_naturally( $old_status, $new_status );
		$actor_id  = (int) get_current_user_id();

		// Log status change to the conversation system message trail.
		$this->log_status_change( $order_id, $old_status, $new_status, $note, $actor_id, $is_forced );

		/**
		 * Fires when order status changes.
		 *
		 * @param int    $order_id   Order ID.
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 */
		do_action( 'wpss_order_status_changed', $order_id, $new_status, $old_status );
		do_action( "wpss_order_status_{$new_status}", $order_id, $old_status );

		return true;
	}

	/**
	 * Check if status transition is valid.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return bool
	 */
	public function can_transition( string $from, string $to ): bool {
		// Admins and vendors with order management capability can force any
		// status transition. The forcing is audited downstream via
		// can_transition_naturally() → log_status_change()/AuditLogService so
		// forensics can tell a natural transition from a cap-bypass.
		if ( current_user_can( 'manage_options' ) || current_user_can( 'wpss_manage_orders' ) ) {
			return true;
		}

		return $this->can_transition_naturally( $from, $to );
	}

	/**
	 * Check whether a transition is permitted by the natural state machine,
	 * without honoring the admin/manage_orders capability bypass.
	 *
	 * Public so callers (update_status, audit log) can distinguish between a
	 * transition that the workflow rules actually allow and a transition that
	 * only succeeded because an admin was on the request.
	 *
	 * @since 1.1.0
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return bool True if the transition is in the rule map.
	 */
	public function can_transition_naturally( string $from, string $to ): bool {
		$transitions = $this->get_natural_transitions( $from, $to );

		return isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}

	/**
	 * Get the natural order-status transition map.
	 *
	 * @since 1.1.0
	 *
	 * @param string $from Current status (passed to the filter for context).
	 * @param string $to   Target status (passed to the filter for context).
	 * @return array<string, array<int, string>>
	 */
	private function get_natural_transitions( string $from = '', string $to = '' ): array {
		$transitions = array(
			// Standard workflow statuses.
			ServiceOrder::STATUS_PENDING_PAYMENT        => array(
				ServiceOrder::STATUS_PENDING_REQUIREMENTS,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_PENDING,
			),
			ServiceOrder::STATUS_PENDING_REQUIREMENTS   => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_ON_HOLD,
				ServiceOrder::STATUS_REQUIREMENTS_SUBMITTED,
			),
			ServiceOrder::STATUS_IN_PROGRESS            => array(
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_ON_HOLD,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_LATE,
				ServiceOrder::STATUS_CANCELLATION_REQUESTED,
				ServiceOrder::STATUS_DELIVERED,
				ServiceOrder::STATUS_DISPUTED,
			),
			ServiceOrder::STATUS_PENDING_APPROVAL       => array(
				ServiceOrder::STATUS_COMPLETED,
				ServiceOrder::STATUS_REVISION_REQUESTED,
				ServiceOrder::STATUS_DISPUTED,
				ServiceOrder::STATUS_CANCELLED,
			),
			ServiceOrder::STATUS_REVISION_REQUESTED     => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_DISPUTED,
			),
			ServiceOrder::STATUS_LATE                   => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_DISPUTED,
				ServiceOrder::STATUS_DELIVERED,
			),
			ServiceOrder::STATUS_ON_HOLD                => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
			),
			ServiceOrder::STATUS_CANCELLATION_REQUESTED => array(
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_DISPUTED,
				ServiceOrder::STATUS_IN_PROGRESS,
			),
			ServiceOrder::STATUS_DISPUTED               => array(
				ServiceOrder::STATUS_COMPLETED,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_REFUNDED,
				ServiceOrder::STATUS_PARTIALLY_REFUNDED,
			),
			// REST API workflow statuses.
			ServiceOrder::STATUS_PENDING                => array(
				ServiceOrder::STATUS_ACCEPTED,
				ServiceOrder::STATUS_REJECTED,
				ServiceOrder::STATUS_CANCELLED,
			),
			ServiceOrder::STATUS_ACCEPTED               => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_REQUIREMENTS_SUBMITTED,
				ServiceOrder::STATUS_CANCELLED,
			),
			ServiceOrder::STATUS_REQUIREMENTS_SUBMITTED => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
			),
			ServiceOrder::STATUS_DELIVERED              => array(
				ServiceOrder::STATUS_COMPLETED,
				ServiceOrder::STATUS_REVISION_REQUESTED,
				ServiceOrder::STATUS_DISPUTED,
			),
		);

		/**
		 * Filter allowed status transitions.
		 *
		 * @param array  $transitions Allowed transitions map.
		 * @param string $from        Current status.
		 * @param string $to          Target status.
		 */
		return apply_filters( 'wpss_order_status_transitions', $transitions, $from, $to );
	}

	/**
	 * Start order work.
	 *
	 * The delivery deadline is NOT reset here — it was already set when
	 * requirements were submitted (the real clock start). This method
	 * only transitions the status to in_progress and records started_at.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function start_work( int $order_id ): bool {
		$order = $this->get( $order_id );

		if ( ! $order ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Record when work actually started.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'started_at' => current_time( 'mysql' ) ),
			array( 'id' => $order_id )
		);

		return $this->update_status( $order_id, ServiceOrder::STATUS_IN_PROGRESS );
	}

	/**
	 * Set the delivery deadline when requirements are submitted.
	 *
	 * This is where the real delivery clock starts — vendors cannot begin
	 * work until they have requirements, so the deadline runs from here.
	 * The deadline set at mark_as_paid() is only a placeholder.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $field_data Submitted requirements data.
	 * @param array $attachments Uploaded attachments.
	 * @return void
	 */
	public function set_deadline_on_requirements( int $order_id, array $field_data, array $attachments ): void {
		$order = $this->get( $order_id );

		if ( ! $order ) {
			return;
		}

		$service       = $order->get_service();
		$delivery_days = 7;

		if ( $service ) {
			$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: array();
			if ( isset( $packages[ $order->package_id ] ) ) {
				$delivery_days = (int) ( $packages[ $order->package_id ]['delivery_days'] ?? 7 );
			}
		}

		// Add addon delivery days (can be negative for rush delivery).
		if ( ! empty( $order->addons ) && is_array( $order->addons ) ) {
			foreach ( $order->addons as $addon ) {
				$delivery_days += (int) ( $addon['delivery_days_extra'] ?? 0 );
			}
			$delivery_days = max( 1, $delivery_days );
		}

		$deadline = new \DateTimeImmutable( '+' . $delivery_days . ' days' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'delivery_deadline' => $deadline->format( 'Y-m-d H:i:s' ),
				'original_deadline' => $deadline->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $order_id )
		);
	}

	/**
	 * Request revision.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $reason   Revision reason.
	 * @return bool
	 */
	public function request_revision( int $order_id, string $reason = '' ): bool {
		$order = $this->get( $order_id );

		if ( ! $order || ! $order->can_request_revision() ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Increment revision count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET revisions_used = revisions_used + 1, updated_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				$order_id
			)
		);

		return $this->update_status( $order_id, ServiceOrder::STATUS_REVISION_REQUESTED, $reason );
	}

	/**
	 * Cancel an order.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $user_id  User ID requesting cancellation.
	 * @param string $reason   Cancellation reason.
	 * @return array{success: bool, message?: string}
	 */
	public function cancel( int $order_id, int $user_id, string $reason = '' ): array {
		$order = $this->get( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => __( 'Order not found.', 'wp-sell-services' ),
			);
		}

		// Check if cancellation is allowed from current status.
		if ( ! $this->can_transition( $order->status, ServiceOrder::STATUS_CANCELLED ) ) {
			return array(
				'success' => false,
				'message' => __( 'This order cannot be cancelled in its current status.', 'wp-sell-services' ),
			);
		}

		// Update status to cancelled.
		$updated = $this->update_status( $order_id, ServiceOrder::STATUS_CANCELLED, $reason );

		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to cancel order.', 'wp-sell-services' ),
			);
		}

		return array( 'success' => true );
	}

	/**
	 * Request cancellation for an in-progress order.
	 *
	 * Buyer can request cancellation within 24h of work starting and before any delivery.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $user_id  User ID requesting cancellation.
	 * @param string $reason   Cancellation reason key.
	 * @param string $note     Optional additional note.
	 * @return array{success: bool, message?: string}
	 */
	public function request_cancellation( int $order_id, int $user_id, string $reason, string $note = '' ): array {
		$order = $this->get( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => __( 'Order not found.', 'wp-sell-services' ),
			);
		}

		if ( ServiceOrder::STATUS_IN_PROGRESS !== $order->status ) {
			return array(
				'success' => false,
				'message' => __( 'Order is not in progress.', 'wp-sell-services' ),
			);
		}

		// Check 24h window from started_at.
		if ( ! $order->started_at ) {
			return array(
				'success' => false,
				'message' => __( 'Order has not been started yet.', 'wp-sell-services' ),
			);
		}

		$now         = new \DateTimeImmutable( 'now', $order->started_at->getTimezone() );
		$hours_since = ( $now->getTimestamp() - $order->started_at->getTimestamp() ) / 3600;

		if ( $hours_since > 24 ) {
			return array(
				'success' => false,
				'message' => __( 'Cancellation window has expired. You can only request cancellation within 24 hours of work starting.', 'wp-sell-services' ),
			);
		}

		// Check no delivery exists.
		$delivery_service = new DeliveryService();
		$deliveries       = $delivery_service->get_order_deliveries( $order_id );

		if ( ! empty( $deliveries ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot request cancellation after a delivery has been submitted.', 'wp-sell-services' ),
			);
		}

		// Store reason in vendor_notes first so status-change hooks can access it.
		$cancel_data = wp_json_encode(
			array(
				'reason'       => sanitize_key( $reason ),
				'note'         => sanitize_textarea_field( $note ),
				'requested_by' => $user_id,
				'requested_at' => current_time( 'mysql' ),
			)
		);

		global $wpdb;
		$table     = $wpdb->prefix . 'wpss_orders';
		$old_notes = $order->vendor_notes;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$notes_written = $wpdb->update(
			$table,
			array( 'vendor_notes' => $cancel_data ),
			array( 'id' => $order_id )
		);

		if ( false === $notes_written ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to save cancellation details.', 'wp-sell-services' ),
			);
		}

		$updated = $this->update_status(
			$order_id,
			ServiceOrder::STATUS_CANCELLATION_REQUESTED,
			$reason
		);

		if ( ! $updated ) {
			// Rollback vendor_notes on status transition failure.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'vendor_notes' => $old_notes ),
				array( 'id' => $order_id )
			);

			return array(
				'success' => false,
				'message' => __( 'Failed to request cancellation.', 'wp-sell-services' ),
			);
		}

		/**
		 * Fires when a buyer requests order cancellation.
		 *
		 * @param int    $order_id Order ID.
		 * @param int    $user_id  User who requested.
		 * @param string $reason   Cancellation reason key.
		 * @param string $note     Additional note.
		 */
		do_action( 'wpss_cancellation_requested', $order_id, $user_id, $reason, $note );

		return array( 'success' => true );
	}

	/**
	 * Extend deadline.
	 *
	 * @param int $order_id   Order ID.
	 * @param int $extra_days Extra days to add.
	 * @return bool
	 */
	public function extend_deadline( int $order_id, int $extra_days ): bool {
		$order = $this->get( $order_id );

		if ( ! $order || ! $order->delivery_deadline ) {
			return false;
		}

		$new_deadline = $order->delivery_deadline->modify( "+{$extra_days} days" );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'delivery_deadline' => $new_deadline->format( 'Y-m-d H:i:s' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $order_id )
		);
	}

	/**
	 * Log status change.
	 *
	 * Tracks which status transitions have already been logged during this request
	 * to prevent duplicate system messages when both OrderService::update_status()
	 * and ServiceOrder::update() fire for the same order in the same request.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param string $note       Optional note.
	 * @param int    $actor_id   Optional actor user ID. Falls back to current user when 0.
	 * @param bool   $is_forced  Optional. Whether this transition bypassed the natural state machine.
	 * @return void
	 */
	public function log_status_change( int $order_id, string $old_status, string $new_status, string $note = '', int $actor_id = 0, bool $is_forced = false ): void {
		// Deduplicate within the same request (static) AND across requests (transient).
		// Prevents duplicate system messages from race conditions (concurrent cron + AJAX).
		static $logged = array();
		$key           = $order_id . ':' . $old_status . ':' . $new_status;
		if ( isset( $logged[ $key ] ) ) {
			return;
		}
		$logged[ $key ] = true;

		// Cross-request deduplication via transient (30-second window).
		$transient_key = 'wpss_status_log_' . md5( $key );
		if ( get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, 30 );

		if ( 0 === $actor_id ) {
			$actor_id = (int) get_current_user_id();
		}

		// Write to the permanent audit log — this is the source of truth for
		// forensic/compliance queries regardless of whether a conversation
		// thread exists for the order.
		( new AuditLogService() )->log(
			'order.status_change',
			'order',
			$order_id,
			array(
				'action'     => $is_forced ? 'force' : 'update',
				'from_value' => $old_status,
				'to_value'   => $new_status,
				'is_forced'  => $is_forced,
				'context'    => array(
					'note' => $note,
				),
			)
		);

		// Create system message in conversation (user-facing history).
		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->get_by_order( $order_id );

		if ( $conversation ) {
			$statuses  = ServiceOrder::get_statuses();
			$old_label = $statuses[ $old_status ] ?? $old_status;
			$new_label = $statuses[ $new_status ] ?? $new_status;

			/* translators: 1: old status, 2: new status */
			$message = sprintf(
				__( 'Order status changed from %1$s to %2$s', 'wp-sell-services' ),
				$old_label,
				$new_label
			);

			if ( $is_forced ) {
				$message .= ' ' . __( '(admin override)', 'wp-sell-services' );
			}

			if ( $note ) {
				$message .= ': ' . $note;
			}

			$conversation_service->add_system_message(
				$conversation->id,
				$message,
				array(
					'event'     => 'status_change',
					'actor_id'  => $actor_id,
					'old'       => $old_status,
					'new'       => $new_status,
					'is_forced' => $is_forced,
				)
			);
		}
	}
}
