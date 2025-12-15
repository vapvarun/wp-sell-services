<?php
/**
 * Extension Request Repository
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Database\Repositories;

use WPSellServices\Models\ExtensionRequest;

/**
 * Repository for extension request data access.
 *
 * @since 1.0.0
 */
class ExtensionRequestRepository extends AbstractRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table_name(): string {
		return 'wpss_extension_requests';
	}

	/**
	 * Find extension request by ID.
	 *
	 * @param int $id Extension request ID.
	 * @return ExtensionRequest|null
	 */
	public function find( int $id ): ?ExtensionRequest {
		$row = $this->find_by_id( $id );

		return $row ? ExtensionRequest::from_row( $row ) : null;
	}

	/**
	 * Find extension requests for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return ExtensionRequest[]
	 */
	public function find_by_order( int $order_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		return array_map( [ ExtensionRequest::class, 'from_row' ], $results );
	}

	/**
	 * Find pending extension request for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return ExtensionRequest|null
	 */
	public function find_pending_for_order( int $order_id ): ?ExtensionRequest {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_id = %d AND status = 'pending'
				ORDER BY created_at DESC
				LIMIT 1",
				$order_id
			)
		);

		return $row ? ExtensionRequest::from_row( $row ) : null;
	}

	/**
	 * Find extension requests by user.
	 *
	 * @param int    $user_id User ID (requester).
	 * @param string $status  Status filter (optional).
	 * @param int    $limit   Limit.
	 * @param int    $offset  Offset.
	 * @return ExtensionRequest[]
	 */
	public function find_by_requester( int $user_id, string $status = '', int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$where = $wpdb->prepare( 'requested_by = %d', $user_id );

		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return array_map( [ ExtensionRequest::class, 'from_row' ], $results );
	}

	/**
	 * Find pending requests awaiting user's response.
	 *
	 * @param int $user_id User ID (responder).
	 * @return ExtensionRequest[]
	 */
	public function find_pending_for_responder( int $user_id ): array {
		global $wpdb;

		// Find orders where user is the other party.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM {$this->table} e
				INNER JOIN {$wpdb->prefix}wpss_orders o ON e.order_id = o.id
				WHERE e.status = 'pending'
				AND e.requested_by != %d
				AND (o.buyer_id = %d OR o.vendor_id = %d)
				ORDER BY e.created_at DESC",
				$user_id,
				$user_id,
				$user_id
			)
		);

		return array_map( [ ExtensionRequest::class, 'from_row' ], $results );
	}

	/**
	 * Create an extension request.
	 *
	 * @param array $data Extension request data.
	 * @return int|false Extension request ID or false on failure.
	 */
	public function create( array $data ) {
		$defaults = [
			'status'     => 'pending',
			'created_at' => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		return $this->insert( $data );
	}

	/**
	 * Approve extension request.
	 *
	 * @param int    $id           Extension request ID.
	 * @param int    $responder_id Responder user ID.
	 * @param string $new_due_date New due date.
	 * @param string $message      Response message (optional).
	 * @return bool
	 */
	public function approve( int $id, int $responder_id, string $new_due_date, string $message = '' ): bool {
		return $this->update(
			$id,
			[
				'status'           => 'approved',
				'responded_by'     => $responder_id,
				'new_due_date'     => $new_due_date,
				'response_message' => $message,
				'responded_at'     => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Reject extension request.
	 *
	 * @param int    $id           Extension request ID.
	 * @param int    $responder_id Responder user ID.
	 * @param string $message      Rejection reason.
	 * @return bool
	 */
	public function reject( int $id, int $responder_id, string $message = '' ): bool {
		return $this->update(
			$id,
			[
				'status'           => 'rejected',
				'responded_by'     => $responder_id,
				'response_message' => $message,
				'responded_at'     => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Mark expired extension requests.
	 *
	 * @param int $hours Hours after which to expire.
	 * @return int Number of requests expired.
	 */
	public function expire_old_requests( int $hours = 48 ): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				SET status = 'expired', responded_at = %s
				WHERE status = 'pending' AND created_at < %s",
				current_time( 'mysql' ),
				$cutoff
			)
		);
	}

	/**
	 * Check if order has pending extension request.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function has_pending_request( int $order_id ): bool {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE order_id = %d AND status = 'pending'",
				$order_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Count extension requests by status.
	 *
	 * @param int $order_id Order ID (optional).
	 * @return array Status counts.
	 */
	public function count_by_status( int $order_id = 0 ): array {
		global $wpdb;

		$where = '1=1';
		if ( $order_id ) {
			$where = $wpdb->prepare( 'order_id = %d', $order_id );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} WHERE {$where} GROUP BY status"
		);

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get total extra days granted for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Total extra days.
	 */
	public function get_total_extra_days( int $order_id ): int {
		global $wpdb;

		$days = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(extra_days) FROM {$this->table}
				WHERE order_id = %d AND status = 'approved'",
				$order_id
			)
		);

		return (int) $days;
	}

	/**
	 * Get extension request statistics.
	 *
	 * @param string $period Period (week, month, year).
	 * @return array
	 */
	public function get_statistics( string $period = 'month' ): array {
		global $wpdb;

		$start_date = $this->get_period_start( $period );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
					SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
					SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
					AVG(CASE WHEN status = 'approved' THEN extra_days ELSE NULL END) as avg_extra_days
				FROM {$this->table}
				WHERE created_at >= %s",
				$start_date
			)
		);

		return [
			'total'          => (int) $row->total,
			'approved'       => (int) $row->approved,
			'rejected'       => (int) $row->rejected,
			'expired'        => (int) $row->expired,
			'approval_rate'  => $row->total > 0 ? round( ( $row->approved / $row->total ) * 100, 1 ) : 0,
			'avg_extra_days' => $row->avg_extra_days ? round( (float) $row->avg_extra_days, 1 ) : 0,
		];
	}

	/**
	 * Get period start date.
	 *
	 * @param string $period Period.
	 * @return string
	 */
	private function get_period_start( string $period ): string {
		switch ( $period ) {
			case 'week':
				return gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
			case 'year':
				return gmdate( 'Y-01-01 00:00:00' );
			case 'month':
			default:
				return gmdate( 'Y-m-01 00:00:00' );
		}
	}
}
