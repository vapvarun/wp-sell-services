<?php
/**
 * Order Repository
 *
 * Database operations for orders.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * OrderRepository class.
 *
 * @since 1.0.0
 */
class OrderRepository extends AbstractRepository {

	/**
	 * Allowed columns for ordering and filtering.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array(
		'id',
		'order_number',
		'service_id',
		'package_id',
		'customer_id',
		'vendor_id',
		'status',
		'total',
		'created_at',
		'updated_at',
		'delivery_deadline',
		'completed_at',
	);

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->schema->get_table_name( 'orders' );
	}

	/**
	 * Generate a unique order number.
	 *
	 * @return string Order number.
	 */
	public function generate_order_number(): string {
		$prefix = apply_filters( 'wpss_order_number_prefix', 'WPSS-' );
		$number = $prefix . strtoupper( wp_generate_password( 8, false ) );

		// Ensure uniqueness.
		while ( $this->find_by_order_number( $number ) ) {
			$number = $prefix . strtoupper( wp_generate_password( 8, false ) );
		}

		return $number;
	}

	/**
	 * Find order by order number.
	 *
	 * @param string $order_number Order number.
	 * @return object|null Order object or null.
	 */
	public function find_by_order_number( string $order_number ): ?object {
		return $this->find_one_by( 'order_number', $order_number );
	}

	/**
	 * Get orders by customer ID.
	 *
	 * @param int                  $customer_id Customer user ID.
	 * @param array<string, mixed> $args        Query arguments.
	 * @return array<object> Array of orders.
	 */
	public function get_by_customer( int $customer_id, array $args = array() ): array {
		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		$sql    = "SELECT * FROM {$this->table} WHERE customer_id = %d";
		$params = array( $customer_id );

		if ( ! empty( $args['status'] ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql .= " ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $args['limit'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $args['limit'];
			$params[] = $args['offset'];
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get orders by vendor ID.
	 *
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $args      Query arguments.
	 * @return array<object> Array of orders.
	 */
	public function get_by_vendor( int $vendor_id, array $args = array() ): array {
		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		$sql    = "SELECT * FROM {$this->table} WHERE vendor_id = %d";
		$params = array( $vendor_id );

		if ( ! empty( $args['status'] ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql .= " ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $args['limit'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $args['limit'];
			$params[] = $args['offset'];
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get orders by service ID.
	 *
	 * @param int $service_id Service post ID.
	 * @return array<object> Array of orders.
	 */
	public function get_by_service( int $service_id ): array {
		return $this->find_by( 'service_id', $service_id );
	}

	/**
	 * Get orders by status.
	 *
	 * @param string               $status Order status.
	 * @param array<string, mixed> $args   Query arguments.
	 * @return array<object> Array of orders.
	 */
	public function get_by_status( string $status, array $args = array() ): array {
		$defaults = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE status = %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$status,
			$args['limit'],
			$args['offset']
		);

		return $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get orders pending auto-completion.
	 *
	 * @param int $days Days after delivery to auto-complete.
	 * @return array<object> Array of orders.
	 */
	public function get_pending_auto_complete( int $days = 3 ): array {
		$deadline = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status = 'pending_approval'
				AND updated_at <= %s",
				$deadline
			)
		);
	}

	/**
	 * Get overdue orders.
	 *
	 * @return array<object> Array of overdue orders.
	 */
	public function get_overdue(): array {
		$now = current_time( 'mysql' );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status IN ('in_progress', 'revision_requested')
				AND delivery_deadline < %s",
				$now
			)
		);
	}

	/**
	 * Update order status.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @return bool True on success.
	 */
	public function update_status( int $order_id, string $new_status ): bool {
		$data = array( 'status' => $new_status );

		// Add timestamps based on status.
		switch ( $new_status ) {
			case 'in_progress':
				$data['started_at'] = current_time( 'mysql' );
				break;
			case 'completed':
				$data['completed_at'] = current_time( 'mysql' );
				break;
		}

		return $this->update( $order_id, $data );
	}

	/**
	 * Get vendor order statistics.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array<string, mixed> Statistics.
	 */
	public function get_vendor_stats( int $vendor_id ): array {
		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status IN ('in_progress', 'pending_approval') THEN 1 ELSE 0 END) as active_orders,
					SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as total_earnings,
					AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(HOUR, started_at, completed_at) END) as avg_completion_hours
				FROM {$this->table}
				WHERE vendor_id = %d",
				$vendor_id
			),
			ARRAY_A
		);

		return $stats ?: array(
			'total_orders'         => 0,
			'completed_orders'     => 0,
			'active_orders'        => 0,
			'total_earnings'       => 0,
			'avg_completion_hours' => 0,
		);
	}

	/**
	 * Get customer order statistics.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return array<string, mixed> Statistics.
	 */
	public function get_customer_stats( int $customer_id ): array {
		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status IN ('in_progress', 'pending_approval', 'pending_requirements') THEN 1 ELSE 0 END) as active_orders,
					SUM(total) as total_spent
				FROM {$this->table}
				WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);

		return $stats ?: array(
			'total_orders'     => 0,
			'completed_orders' => 0,
			'active_orders'    => 0,
			'total_spent'      => 0,
		);
	}

	/**
	 * Search orders.
	 *
	 * @param string               $search Search term.
	 * @param array<string, mixed> $args   Query arguments.
	 * @return array<object> Array of orders.
	 */
	public function search( string $search, array $args = array() ): array {
		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);

		$args        = wp_parse_args( $args, $defaults );
		$search_like = '%' . $this->wpdb->esc_like( $search ) . '%';

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_number LIKE %s
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$search_like,
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Get orders for a date range.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<object> Array of orders.
	 */
	public function get_by_date_range( string $start_date, string $end_date ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE DATE(created_at) BETWEEN %s AND %s
				ORDER BY created_at DESC",
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * Find order by external platform order ID.
	 *
	 * @param int    $platform_order_id External platform order ID.
	 * @param string $platform          Platform identifier.
	 * @return object|null Order object or null.
	 */
	public function get_by_external_order( int $platform_order_id, string $platform ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE platform_order_id = %d AND platform = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$platform_order_id,
				$platform
			)
		);
	}

	/**
	 * Count orders by status.
	 *
	 * @return array<string, int> Status counts.
	 */
	public function count_by_status(): array {
		$results = $this->wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status",
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = (int) $row['count'];
		}

		return $counts;
	}
}
