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
		return $this->table_name( 'orders' );
	}

	/**
	 * Insert an order, keeping platform_order_ref in step with platform_order_id.
	 *
	 * The ref must mirror platform_order_id on every numeric rail, and that
	 * invariant is the whole reason a single lookup can serve both numeric
	 * and string rails. Deriving it here rather than at the call sites is
	 * deliberate: there are eight places that insert an order with a platform id
	 * (four rails plus tips, milestones, extensions and buyer-request conversion),
	 * every one of them would have to remember, and the failure mode of forgetting
	 * is invisible — the order inserts fine and simply never resolves by ref
	 * later, which surfaces as a duplicate order on webhook replay rather than as
	 * an error anyone would notice.
	 *
	 * A caller that supplies its own ref keeps it: that is how SureCart stores
	 * 'ord_a1b2c3' with no numeric id at all.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $data   Column data.
	 * @param array<string>        $format Optional explicit formats.
	 * @return int|false Inserted ID or false.
	 */
	public function insert( array $data, array $format = array() ): int|false {
		// Only when formats are inferred. An explicit $format array is positional
		// against $data, so appending a key here would shift every format by one
		// and quietly write the wrong types.
		// isset() already excludes null, so no separate null check is needed.
		if ( empty( $format )
			&& ! isset( $data['platform_order_ref'] )
			&& isset( $data['platform_order_id'] ) ) {
			$data['platform_order_ref'] = (string) $data['platform_order_id'];
		}

		return parent::insert( $data, $format );
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
			'status'           => '',
			// A group of statuses, for the dashboard filter chips. Single
			// 'status' stays for every existing caller.
			'status__in'       => array(),
			'platform'         => '',
			'orderby'          => 'created_at',
			'order'            => 'DESC',
			// Sort the buyer's own list so the orders waiting on THEM come
			// first, instead of newest-wins burying an unpaid invoice under a
			// year of cancellations. Nothing is hidden - only reordered.
			'actionable_first' => false,
			'limit'            => 20,
			'offset'           => 0,
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

		if ( ! empty( $args['status__in'] ) ) {
			$in_statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $args['status__in'] ) ) );

			if ( $in_statuses ) {
				$sql   .= ' AND status IN ( ' . implode( ', ', array_fill( 0, count( $in_statuses ), '%s' ) ) . ' )';
				$params = array_merge( $params, $in_statuses );
			}
		}

		if ( ! empty( $args['platform'] ) ) {
			$sql     .= ' AND platform = %s';
			$params[] = $args['platform'];
		}

		$sql = $this->exclude_sub_orders( $sql, $params, $args );

		if ( ! empty( $args['actionable_first'] ) && function_exists( 'wpss_get_order_status_priority' ) ) {
			// FIELD() returns the 1-based position of status in the list and 0
			// for anything absent, so ordering by it ascending would put the
			// unlisted statuses FIRST. Sorting descending on the negated value
			// keeps unknown statuses last without a CASE.
			$priority = array_values( array_filter( array_map( 'sanitize_key', wpss_get_order_status_priority() ) ) );

			if ( $priority ) {
				$sql   .= ' ORDER BY FIELD( status, ' . implode( ', ', array_fill( 0, count( $priority ), '%s' ) ) . ' ) = 0, FIELD( status, ' . implode( ', ', array_fill( 0, count( $priority ), '%s' ) ) . ' ) ASC, created_at DESC';
				$params = array_merge( $params, $priority, $priority );
			} else {
				$sql .= " ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		} else {
			$sql .= " ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

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
			'status'    => '',
			'platform'  => '',
			'date_from' => '', // Y-m-d H:i:s — orders created at or after this timestamp (VS10 from plans/ORDER-FLOW-AUDIT.md).
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'limit'     => 20,
			'offset'    => 0,
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

		if ( ! empty( $args['platform'] ) ) {
			$sql     .= ' AND platform = %s';
			$params[] = $args['platform'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$sql     .= ' AND created_at >= %s';
			$params[] = $args['date_from'];
		}

		$sql = $this->exclude_sub_orders( $sql, $params, $args );

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
	 * Exclude sub-order rows from a list or count query.
	 *
	 * Tips, milestones and extensions live in this table as their own rows but
	 * they are not orders anyone placed — they hang off a parent order. Listing
	 * them alongside real orders is what made the seller dashboard show a
	 * "Total Orders" stat that disagreed with its own list, and put
	 * "Milestone: Phase 2" in the buyer's My Orders as if it were a purchase.
	 *
	 * get_vendor_stats() has always excluded them; this is the same rule for
	 * the list and count paths, from one shared definition.
	 *
	 * Callers that genuinely want sub-orders pass include_sub_orders => true,
	 * or ask for a specific platform.
	 *
	 * @since 1.4.0
	 *
	 * @param string               $sql    SQL built so far.
	 * @param array<int, mixed>    $params Prepare params, appended in place.
	 * @param array<string, mixed> $args   Query args.
	 * @return string SQL with the exclusion applied.
	 */
	private function exclude_sub_orders( string $sql, array &$params, array $args ): string {
		if ( ! empty( $args['include_sub_orders'] ) || ! empty( $args['platform'] ) ) {
			return $sql;
		}

		$sub_platforms = function_exists( 'wpss_get_sub_order_platforms' )
			? wpss_get_sub_order_platforms()
			: array();

		if ( empty( $sub_platforms ) ) {
			return $sql;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $sub_platforms ), '%s' ) );
		$sql         .= " AND ( platform IS NULL OR platform NOT IN ( {$placeholders} ) )";

		foreach ( $sub_platforms as $platform ) {
			$params[] = $platform;
		}

		return $sql;
	}

	/**
	 * Count orders for a customer matching optional filters.
	 *
	 * Counterpart to get_by_customer() — used by the paginated buyer "My Orders"
	 * dashboard view to compute the total page count without re-running the full
	 * SELECT.
	 *
	 * @since 1.2.2
	 *
	 * @param int                  $customer_id Customer user ID.
	 * @param array<string, mixed> $args        Filter arguments (status, platform).
	 * @return int Total matching row count.
	 */
	public function count_by_customer( int $customer_id, array $args = array() ): int {
		$defaults = array(
			'status'     => '',
			'status__in' => array(),
			'platform'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$sql    = "SELECT COUNT(*) FROM {$this->table} WHERE customer_id = %d";
		$params = array( $customer_id );

		if ( ! empty( $args['status'] ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['status__in'] ) ) {
			$in_statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $args['status__in'] ) ) );

			if ( $in_statuses ) {
				$sql   .= ' AND status IN ( ' . implode( ', ', array_fill( 0, count( $in_statuses ), '%s' ) ) . ' )';
				$params = array_merge( $params, $in_statuses );
			}
		}

		if ( ! empty( $args['platform'] ) ) {
			$sql     .= ' AND platform = %s';
			$params[] = $args['platform'];
		}

		$sql = $this->exclude_sub_orders( $sql, $params, $args );

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Count a customer's orders per status, in ONE query.
	 *
	 * The filter chips each need a count. Asking count_by_customer() once per
	 * chip is six queries that grow with the chip row; this is one GROUP BY
	 * that the caller buckets in PHP.
	 *
	 * @since 1.7.0
	 *
	 * @param int                  $customer_id Customer user ID.
	 * @param array<string, mixed> $args        Query arguments (platform, include_sub_orders).
	 * @return array<string, int> Status => count.
	 */
	public function count_by_customer_grouped( int $customer_id, array $args = array() ): array {
		$args = wp_parse_args( $args, array( 'platform' => '' ) );

		$sql    = "SELECT status, COUNT(*) AS c FROM {$this->table} WHERE customer_id = %d";
		$params = array( $customer_id );

		if ( ! empty( $args['platform'] ) ) {
			$sql     .= ' AND platform = %s';
			$params[] = $args['platform'];
		}

		$sql = $this->exclude_sub_orders( $sql, $params, $args );

		$sql .= ' GROUP BY status';

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->status ] = (int) $row->c;
		}

		return $out;
	}

	/**
	 * Count orders for a vendor matching optional filters.
	 *
	 * Counterpart to get_by_vendor() — used by paginated dashboard views to
	 * compute total page count without re-running the SELECT for full rows.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $args      Filter arguments (status, platform, date_from).
	 * @return int Total matching row count.
	 */
	public function count_by_vendor( int $vendor_id, array $args = array() ): int {
		$defaults = array(
			'status'    => '',
			'platform'  => '',
			'date_from' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$sql    = "SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d";
		$params = array( $vendor_id );

		if ( ! empty( $args['status'] ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['platform'] ) ) {
			$sql     .= ' AND platform = %s';
			$params[] = $args['platform'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$sql     .= ' AND created_at >= %s';
			$params[] = $args['date_from'];
		}

		$sql = $this->exclude_sub_orders( $sql, $params, $args );

		return (int) $this->wpdb->get_var(
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
		// Sub-order platforms are counted differently from the row they
		// represent:
		// * Tips never count towards service stats — they live in
		// get_vendor_tip_stats() so analytics can surface them separately.
		// * Extensions are NOT separate orders (they top up an existing
		// service order) but the extra money the vendor earned IS real
		// revenue, so they are excluded from the counts but summed into
		// total_earnings.
		// Revenue uses vendor_earnings (NET, post-commission) so the number
		// the seller sees matches what they can actually withdraw. Falls back
		// to total for legacy rows written before CommissionService populated
		// vendor_earnings.
		$tip_platform       = \WPSellServices\Services\TippingService::ORDER_TYPE;
		$extension_platform = \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE;
		$milestone_platform = \WPSellServices\Services\MilestoneService::ORDER_TYPE;

		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					SUM(CASE WHEN platform NOT IN (%s, %s, %s) THEN 1 ELSE 0 END) as total_orders,
					SUM(CASE WHEN platform NOT IN (%s, %s, %s) AND status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN platform NOT IN (%s, %s, %s) AND status IN ('in_progress', 'pending_approval') THEN 1 ELSE 0 END) as active_orders,
					SUM(CASE WHEN platform != %s AND status = 'completed' THEN COALESCE(vendor_earnings, total) ELSE 0 END) as total_earnings,
					AVG(CASE WHEN platform NOT IN (%s, %s, %s) AND status = 'completed' THEN TIMESTAMPDIFF(HOUR, started_at, completed_at) END) as avg_completion_hours
				FROM {$this->table}
				WHERE vendor_id = %d",
				$tip_platform,
				$extension_platform,
				$milestone_platform,
				$tip_platform,
				$extension_platform,
				$milestone_platform,
				$tip_platform,
				$extension_platform,
				$milestone_platform,
				$tip_platform,
				$tip_platform,
				$extension_platform,
				$milestone_platform,
				$vendor_id
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return array(
				'total_orders'         => 0,
				'completed_orders'     => 0,
				'active_orders'        => 0,
				'total_earnings'       => 0.0,
				'avg_completion_hours' => 0.0,
			);
		}

		// Cast the aggregates before returning.
		//
		// $wpdb hands back SUM()/AVG() as strings (and NULL when no rows match).
		// Consumers run under strict_types and pass these straight into typed
		// helpers - wpss_format_price( float $price ) threw "must be of type
		// float, string given" and fataled the [wpss_account] vendor dashboard.
		// Cast once here so every consumer gets real numbers.
		return array(
			'total_orders'         => (int) ( $stats['total_orders'] ?? 0 ),
			'completed_orders'     => (int) ( $stats['completed_orders'] ?? 0 ),
			'active_orders'        => (int) ( $stats['active_orders'] ?? 0 ),
			'total_earnings'       => (float) ( $stats['total_earnings'] ?? 0 ),
			'avg_completion_hours' => (float) ( $stats['avg_completion_hours'] ?? 0 ),
		);
	}

	/**
	 * Get a vendor's tip-only statistics.
	 *
	 * Separate from {@see self::get_vendor_stats()} so analytics surfaces
	 * can report tip revenue as its own metric without conflating it with
	 * service-order revenue. Values are based on completed tip sub-orders
	 * (platform='tip' AND status='completed').
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array<string, mixed> Keys: tips_received, tips_total_gross, tips_total_net.
	 */
	public function get_vendor_tip_stats( int $vendor_id ): array {
		$tip_platform = \WPSellServices\Services\TippingService::ORDER_TYPE;

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as tips_received,
					COALESCE(SUM(total), 0) as tips_total_gross,
					COALESCE(SUM(COALESCE(vendor_earnings, total)), 0) as tips_total_net
				FROM {$this->table}
				WHERE vendor_id = %d AND platform = %s AND status = 'completed'",
				$vendor_id,
				$tip_platform
			),
			ARRAY_A
		);

		return $row ?: array(
			'tips_received'    => 0,
			'tips_total_gross' => 0,
			'tips_total_net'   => 0,
		);
	}

	/**
	 * Get the datetime of a vendor's most recent completed order.
	 *
	 * Backs the "Last Delivery" display on the single-service page and the
	 * vendor card partial. Tip sub-orders are excluded because a tip is a
	 * payment receipt, not delivered work; extension and milestone sub-orders
	 * count because completing them means the vendor delivered something.
	 *
	 * @since 1.2.0
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return string|null MySQL datetime of the last completed order, or null.
	 */
	public function get_last_completed_date( int $vendor_id ): ?string {
		if ( array_key_exists( $vendor_id, self::$last_completed_memo ) ) {
			return self::$last_completed_memo[ $vendor_id ];
		}

		$tip_platform = \WPSellServices\Services\TippingService::ORDER_TYPE;

		$date = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MAX(completed_at)
				FROM {$this->table}
				WHERE vendor_id = %d AND status = 'completed' AND platform != %s",
				$vendor_id,
				$tip_platform
			)
		);

		self::$last_completed_memo[ $vendor_id ] = $date ? (string) $date : null;

		return self::$last_completed_memo[ $vendor_id ];
	}

	/**
	 * Request-scoped memo of last-completed dates, keyed by vendor ID.
	 *
	 * @var array<int, string|null>
	 */
	private static array $last_completed_memo = array();

	/**
	 * Resolve the last completed delivery for many vendors in one query.
	 *
	 * Every vendor card calls get_last_completed_date(), so a grid of 8 vendors
	 * fired 8 separate MAX(completed_at) scans. This answers all of them with a
	 * single GROUP BY and fills the memo the single-vendor method reads.
	 *
	 * Vendors with no completed order are memoised as null, so they do not fall
	 * through to a query of their own afterwards.
	 *
	 * The memo is request-scoped on purpose. This is display-only data ("last
	 * delivery 3 days ago"); the worst case is that an order completing DURING
	 * the same request is not reflected until the next one, which is not worth
	 * the invalidation surface a persistent cache would need.
	 *
	 * @since 1.5.1
	 *
	 * @param int[] $vendor_ids Vendor user IDs about to be rendered.
	 * @return void
	 */
	public function prime_last_completed_dates( array $vendor_ids ): void {
		$vendor_ids = array_values( array_unique( array_filter( array_map( 'intval', $vendor_ids ) ) ) );

		$missing = array_values(
			array_filter(
				$vendor_ids,
				static fn ( int $id ): bool => ! array_key_exists( $id, self::$last_completed_memo )
			)
		);

		if ( empty( $missing ) ) {
			return;
		}

		$tip_platform = \WPSellServices\Services\TippingService::ORDER_TYPE;
		$placeholders = implode( ', ', array_fill( 0, count( $missing ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated from the ID count; every value is bound.
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT vendor_id, MAX(completed_at) AS last_completed
				FROM {$this->table}
				WHERE vendor_id IN ({$placeholders}) AND status = 'completed' AND platform != %s
				GROUP BY vendor_id",
				array_merge( $missing, array( $tip_platform ) )
			)
		);

		foreach ( $missing as $id ) {
			self::$last_completed_memo[ $id ] = null;
		}

		foreach ( (array) $rows as $row ) {
			self::$last_completed_memo[ (int) $row->vendor_id ] = $row->last_completed ? (string) $row->last_completed : null;
		}
	}

	/**
	 * Build a parenthesised %s placeholder list for an IN clause.
	 *
	 * Never returns an empty (), which is a MySQL syntax error - an empty
	 * status list becomes a condition that matches nothing instead.
	 *
	 * @since 1.7.0
	 *
	 * @param string[] $statuses Statuses, by reference so an empty list is normalised.
	 * @return string Placeholder list, e.g. "( %s, %s )".
	 */
	private function status_in_placeholders( array &$statuses ): string {
		$statuses = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );

		if ( ! $statuses ) {
			$statuses = array( '__wpss_no_status__' );
		}

		return '( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )';
	}

	/**
	 * Get customer order statistics.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return array<string, mixed> Statistics.
	 */
	public function get_customer_stats( int $customer_id ): array {
		// Exclude tip sub-orders for the same reason as get_vendor_stats() —
		// they are payment receipts, not orders in the "things I bought"
		// sense. Tips are retrievable separately via the tipping service.
		$tip_platform = \WPSellServices\Services\TippingService::ORDER_TYPE;

		/*
		 * The status lists come from wpss_get_order_status_groups(), the same
		 * definition the dashboard filter chips use.
		 *
		 * They used to be written out here by hand, and they had drifted: the
		 * stat counted 'in_progress, pending_approval, pending_requirements' as
		 * Active while the list showed nine statuses as active work, so a buyer
		 * read "0 Active" above three visibly active orders. That is the second
		 * half of Basecamp 10240019463 - the stats disagreeing with the rows
		 * underneath them - and it can only be fixed for good by the card and
		 * the chip reading one definition.
		 *
		 * awaiting_payment and disputed stay broken out because they are the
		 * two states that need the BUYER to act.
		 */
		$groups = function_exists( 'wpss_get_order_status_groups' ) ? wpss_get_order_status_groups() : array();

		$active_statuses    = $groups['active']['statuses'] ?? array( 'in_progress', 'pending_approval', 'pending_requirements' );
		$awaiting_statuses  = $groups['awaiting']['statuses'] ?? array( 'pending_payment' );
		$attention_statuses = $groups['disputed']['statuses'] ?? array( 'disputed' );
		$completed_statuses = $groups['completed']['statuses'] ?? array( 'completed' );

		$active_sql    = $this->status_in_placeholders( $active_statuses );
		$awaiting_sql  = $this->status_in_placeholders( $awaiting_statuses );
		$attention_sql = $this->status_in_placeholders( $attention_statuses );
		$completed_sql = $this->status_in_placeholders( $completed_statuses );

		$params = array_merge(
			$completed_statuses,
			$active_statuses,
			$awaiting_statuses,
			$attention_statuses,
			array( $customer_id, $tip_platform )
		);

		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the interpolated parts are %s placeholder lists built from a fixed status map.
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status IN {$completed_sql} THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status IN {$active_sql} THEN 1 ELSE 0 END) as active_orders,
					SUM(CASE WHEN status IN {$awaiting_sql} THEN 1 ELSE 0 END) as awaiting_payment_orders,
					SUM(CASE WHEN status IN {$attention_sql} THEN 1 ELSE 0 END) as disputed_orders,
					SUM(total) as total_spent
				FROM {$this->table}
				WHERE customer_id = %d AND platform != %s",
				...$params
			),
			ARRAY_A
		);

		return $stats ?: array(
			'total_orders'            => 0,
			'completed_orders'        => 0,
			'active_orders'           => 0,
			'awaiting_payment_orders' => 0,
			'disputed_orders'         => 0,
			'total_spent'             => 0,
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
	 * Find orders by the external order reference, as the rail spells it.
	 *
	 * The string counterpart to {@see get_by_external_order()}, for rails whose
	 * order ids are not numbers — SureCart's 'ord_a1b2c3' and anything else that
	 * hands out opaque ids. Numeric rails write their id here too (as a string),
	 * so a caller that has a reference never has to ask which kind of rail it
	 * came from.
	 *
	 * Returns ALL matching orders, not one: a single cart order can carry several
	 * service line items, and each becomes its own WPSS order. Every caller so far
	 * has wanted all of them (marking a payment paid has to cover every order on
	 * the receipt), and a LIMIT 1 here is how you silently credit one vendor out
	 * of three.
	 *
	 * @since 1.6.0
	 *
	 * @param string $platform_order_ref External order reference.
	 * @param string $platform           Platform identifier.
	 * @return array<int, object> Matching order rows, oldest first.
	 */
	public function get_all_by_external_ref( string $platform_order_ref, string $platform ): array {
		if ( '' === $platform_order_ref ) {
			return array();
		}

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE platform_order_ref = %s AND platform = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$platform_order_ref,
				$platform
			)
		);

		return is_array( $rows ) ? $rows : array();
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
