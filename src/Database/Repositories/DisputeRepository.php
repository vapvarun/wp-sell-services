<?php
/**
 * Dispute Repository
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\Dispute;

/**
 * Repository for dispute data access.
 *
 * @since 1.0.0
 */
class DisputeRepository extends AbstractRepository {

	/**
	 * Allowed columns for ordering and filtering.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array(
		'id',
		'order_id',
		'initiated_by',
		'reason',
		'status',
		'resolved_by',
		'resolution',
		'created_at',
		'updated_at',
		'resolved_at',
	);

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table_name(): string {
		return $this->schema->get_table_name( 'disputes' );
	}

	/**
	 * Find dispute by ID.
	 *
	 * @param int $id Dispute ID.
	 * @return Dispute|null
	 */
	public function find( int $id ): ?Dispute {
		$row = $this->find_by_id( $id );

		return $row ? Dispute::from_db( $row ) : null;
	}

	/**
	 * Find disputes by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return Dispute[]
	 */
	public function find_by_order( int $order_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		return array_map( array( Dispute::class, 'from_db' ), $results );
	}

	/**
	 * Find disputes by user (buyer or vendor).
	 *
	 * Joins with orders table to find disputes where user is customer or vendor.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Status filter (optional).
	 * @param int    $limit   Limit (optional).
	 * @param int    $offset  Offset (optional).
	 * @return Dispute[]
	 */
	public function find_by_user( int $user_id, string $status = '', int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$orders_table = $this->schema->get_table_name( 'orders' );

		$status_clause = '';
		if ( $status ) {
			$status_clause = $wpdb->prepare( ' AND d.status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.* FROM {$this->table} d
				INNER JOIN {$orders_table} o ON d.order_id = o.id
				WHERE (o.customer_id = %d OR o.vendor_id = %d) {$status_clause}
				ORDER BY d.created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$limit,
				$offset
			)
		);

		return array_map( array( Dispute::class, 'from_db' ), $results );
	}

	/**
	 * Find disputes by status.
	 *
	 * @param string $status Status.
	 * @param int    $limit  Limit (optional).
	 * @param int    $offset Offset (optional).
	 * @return Dispute[]
	 */
	public function find_by_status( string $status, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$status,
				$limit,
				$offset
			)
		);

		return array_map( array( Dispute::class, 'from_db' ), $results );
	}

	/**
	 * Find disputes assigned to admin.
	 *
	 * @param int $admin_id Admin user ID.
	 * @return Dispute[]
	 */
	public function find_by_assigned_admin( int $admin_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE assigned_admin = %d ORDER BY created_at DESC",
				$admin_id
			)
		);

		return array_map( array( Dispute::class, 'from_db' ), $results );
	}

	/**
	 * Find disputes pending response.
	 *
	 * Uses updated_at as proxy for last activity since last_response_at doesn't exist.
	 *
	 * @param int $hours Hours since last activity.
	 * @return Dispute[]
	 */
	public function find_pending_response( int $hours = 48 ): array {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status = 'open'
				AND updated_at < %s
				ORDER BY created_at ASC",
				$cutoff
			)
		);

		return array_map( array( Dispute::class, 'from_db' ), $results );
	}

	/**
	 * Create a new dispute.
	 *
	 * @param array $data Dispute data.
	 * @return int|false Dispute ID or false on failure.
	 */
	public function create( array $data ) {
		$defaults = array(
			'status'     => 'open',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		return $this->insert( $data );
	}

	/**
	 * Update dispute status.
	 *
	 * @param int    $id     Dispute ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function update_status( int $id, string $status ): bool {
		return $this->update(
			$id,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Assign dispute to admin.
	 *
	 * @param int $id       Dispute ID.
	 * @param int $admin_id Admin user ID.
	 * @return bool
	 */
	public function assign_to_admin( int $id, int $admin_id ): bool {
		return $this->update(
			$id,
			array(
				'assigned_admin' => $admin_id,
				'updated_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Record response time.
	 *
	 * Updates updated_at timestamp to track last activity.
	 *
	 * @param int $id Dispute ID.
	 * @return bool
	 */
	public function record_response( int $id ): bool {
		return $this->update(
			$id,
			array(
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Resolve dispute.
	 *
	 * @param int    $id         Dispute ID.
	 * @param string $resolution Resolution type.
	 * @param string $notes      Resolution notes.
	 * @return bool
	 */
	public function resolve( int $id, string $resolution, string $notes = '' ): bool {
		return $this->update(
			$id,
			array(
				'status'           => $resolution,
				'resolution_notes' => $notes,
				'resolved_at'      => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Count disputes by status.
	 *
	 * @return array Status counts.
	 */
	public function count_by_status(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status"
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get dispute statistics.
	 *
	 * @param string $period Period (day, week, month, year).
	 * @return array
	 */
	public function get_statistics( string $period = 'month' ): array {
		global $wpdb;

		$date_format = $this->get_date_format( $period );
		$start_date  = $this->get_period_start( $period );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(created_at, %s) as period,
					COUNT(*) as total,
					SUM(CASE WHEN status LIKE %s THEN 1 ELSE 0 END) as resolved
				FROM {$this->table}
				WHERE created_at >= %s
				GROUP BY period
				ORDER BY period ASC",
				$date_format,
				$wpdb->esc_like( 'resolved' ) . '%',
				$start_date
			)
		);

		return $results;
	}

	/**
	 * Get date format for period.
	 *
	 * @param string $period Period.
	 * @return string MySQL date format.
	 */
	private function get_date_format( string $period ): string {
		switch ( $period ) {
			case 'day':
				return '%Y-%m-%d %H:00';
			case 'week':
				return '%Y-%u';
			case 'year':
				return '%Y-%m';
			case 'month':
			default:
				return '%Y-%m-%d';
		}
	}

	/**
	 * Get period start date.
	 *
	 * @param string $period Period.
	 * @return string
	 */
	private function get_period_start( string $period ): string {
		switch ( $period ) {
			case 'day':
				return gmdate( 'Y-m-d 00:00:00' );
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
