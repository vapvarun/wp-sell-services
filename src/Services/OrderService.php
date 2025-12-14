<?php
/**
 * Order Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

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
	public function get_customer_orders( int $customer_id, array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = [
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'customer_id = %d' ];
		$params = [ $customer_id ];

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'created_at DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, [ $args['limit'], $args['offset'] ] )
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
	public function get_vendor_orders( int $vendor_id, array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = [
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'vendor_id = %d' ];
		$params = [ $vendor_id ];

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'created_at DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, [ $args['limit'], $args['offset'] ] )
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

		// Validate status transition.
		if ( ! $this->can_transition( $old_status, $new_status ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$data = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		// Set timestamps based on status.
		if ( ServiceOrder::STATUS_IN_PROGRESS === $new_status && ! $order->started_at ) {
			$data['started_at'] = current_time( 'mysql' );
		}

		if ( ServiceOrder::STATUS_COMPLETED === $new_status ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $table, $data, [ 'id' => $order_id ] );

		if ( false === $result ) {
			return false;
		}

		// Log status change.
		$this->log_status_change( $order_id, $old_status, $new_status, $note );

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
		$transitions = [
			ServiceOrder::STATUS_PENDING_PAYMENT => [
				ServiceOrder::STATUS_PENDING_REQUIREMENTS,
				ServiceOrder::STATUS_CANCELLED,
			],
			ServiceOrder::STATUS_PENDING_REQUIREMENTS => [
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_ON_HOLD,
			],
			ServiceOrder::STATUS_IN_PROGRESS => [
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_ON_HOLD,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_LATE,
			],
			ServiceOrder::STATUS_PENDING_APPROVAL => [
				ServiceOrder::STATUS_COMPLETED,
				ServiceOrder::STATUS_REVISION_REQUESTED,
				ServiceOrder::STATUS_DISPUTED,
			],
			ServiceOrder::STATUS_REVISION_REQUESTED => [
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_DISPUTED,
			],
			ServiceOrder::STATUS_LATE => [
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_DISPUTED,
			],
			ServiceOrder::STATUS_ON_HOLD => [
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
			],
			ServiceOrder::STATUS_DISPUTED => [
				ServiceOrder::STATUS_COMPLETED,
				ServiceOrder::STATUS_CANCELLED,
			],
		];

		return isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}

	/**
	 * Start order work.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function start_work( int $order_id ): bool {
		$order = $this->get( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Calculate delivery deadline from now.
		$service = $order->get_service();
		$delivery_days = 7;

		if ( $order->package_id && $service ) {
			$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: [];
			foreach ( $packages as $package ) {
				if ( (int) ( $package['id'] ?? 0 ) === $order->package_id ) {
					$delivery_days = (int) ( $package['delivery_days'] ?? 7 );
					break;
				}
			}
		}

		$deadline = new \DateTimeImmutable( '+' . $delivery_days . ' days' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			[
				'delivery_deadline'  => $deadline->format( 'Y-m-d H:i:s' ),
				'original_deadline'  => $deadline->format( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $order_id ]
		);

		return $this->update_status( $order_id, ServiceOrder::STATUS_IN_PROGRESS );
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
			[
				'delivery_deadline' => $new_deadline->format( 'Y-m-d H:i:s' ),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $order_id ]
		);
	}

	/**
	 * Log status change.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param string $note       Optional note.
	 * @return void
	 */
	private function log_status_change( int $order_id, string $old_status, string $new_status, string $note = '' ): void {
		// Create system message in conversation.
		$conversation_service = new ConversationService();
		$conversation = $conversation_service->get_by_order( $order_id );

		if ( $conversation ) {
			$statuses = ServiceOrder::get_statuses();
			$old_label = $statuses[ $old_status ] ?? $old_status;
			$new_label = $statuses[ $new_status ] ?? $new_status;

			/* translators: 1: old status, 2: new status */
			$message = sprintf(
				__( 'Order status changed from %1$s to %2$s', 'wp-sell-services' ),
				$old_label,
				$new_label
			);

			if ( $note ) {
				$message .= ': ' . $note;
			}

			$conversation_service->add_system_message( $conversation->id, $message );
		}
	}
}
