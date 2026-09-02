<?php
/**
 * Vendor Profile Repository
 *
 * Database operations for vendor profiles.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * VendorProfileRepository class.
 *
 * @since 1.0.0
 */
class VendorProfileRepository extends AbstractRepository {

	/**
	 * Allowed columns for ordering and filtering.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array(
		'id',
		'user_id',
		'display_name',
		'status',
		'avg_rating',
		'total_reviews',
		'total_orders',
		'completed_orders',
		'total_earnings',
		'on_time_delivery_rate',
		'verification_tier',
		'is_available',
		'country',
		'created_at',
		'updated_at',
	);

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->table_name( 'vendor_profiles' );
	}

	/**
	 * Get profile by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return object|null Profile object or null.
	 */
	public function get_by_user( int $user_id ): ?object {
		return $this->find_one_by( 'user_id', $user_id );
	}

	/**
	 * Create or update vendor profile.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $data    Profile data.
	 * @return int|false Profile ID or false on failure.
	 */
	public function upsert( int $user_id, array $data ): int|false {
		$existing = $this->get_by_user( $user_id );

		if ( $existing ) {
			$success = $this->update( $existing->id, $data );

			if ( $success ) {
				self::flush_avatar_map_cache();
				// VendorProfile::get_by_user_id() memoises for the request; drop
				// this user's entry so a read after this write is not stale.
				// Every other writer (update_stats, set_vacation_mode,
				// set_availability, update_verification_tier) routes through
				// here, so this one call covers all of them.
				\WPSellServices\Models\VendorProfile::flush_memo( $user_id );
			}

			return $success ? $existing->id : false;
		}

		$data['user_id'] = $user_id;
		$inserted        = $this->insert( $data );

		if ( $inserted ) {
			self::flush_avatar_map_cache();
			\WPSellServices\Models\VendorProfile::flush_memo( $user_id );
		}

		return $inserted;
	}

	/**
	 * Object-cache key for the user_id => avatar_id map.
	 *
	 * @since 1.4.0
	 * @var   string
	 */
	public const AVATAR_MAP_CACHE_KEY = 'wpss_vendor_avatar_map';

	/**
	 * Object-cache group shared by the plugin.
	 *
	 * @since 1.4.0
	 * @var   string
	 */
	public const CACHE_GROUP = 'wpss';

	/**
	 * Invalidate the cached vendor avatar map.
	 *
	 * The avatar filter (see Plugin::define_avatar_filter) caches the whole
	 * user_id => avatar_id map so rendering many avatars costs one query
	 * instead of one per avatar. Any write that can change an avatar_id has to
	 * drop it, or vendors keep showing a stale picture.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function flush_avatar_map_cache(): void {
		wp_cache_delete( self::AVATAR_MAP_CACHE_KEY, self::CACHE_GROUP );
	}

	/**
	 * One page of the public vendor directory.
	 *
	 * @since 1.7.1
	 *
	 * @param array<string, mixed> $args Filters: status (default 'active', '' for any),
	 *                                   available (bool: taking orders and not on vacation),
	 *                                   country, plus orderby/order/limit/offset.
	 * @return array<object>
	 */
	public function get_directory( array $args = array() ): array {
		$args    = wp_parse_args(
			$args,
			array(
				'orderby' => 'avg_rating',
				'order'   => 'DESC',
				'limit'   => 12,
				'offset'  => 0,
			)
		);
		$orderby = $this->validate_orderby( (string) $args['orderby'] );
		$order   = $this->validate_order( (string) $args['order'] );
		$where   = $this->directory_where( $args );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $where is prepared below; identifiers are allow-listed.
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				max( 1, (int) $args['limit'] ),
				max( 0, (int) $args['offset'] )
			)
		);
	}

	/**
	 * Total rows behind get_directory() for the same filters.
	 *
	 * @since 1.7.1
	 *
	 * @param array<string, mixed> $args See get_directory().
	 * @return int
	 */
	public function count_directory( array $args = array() ): int {
		$where = $this->directory_where( $args );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $where is prepared; the table name is plugin-controlled.
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} {$where}" );
	}

	/**
	 * Prepared WHERE clause shared by get_directory() and count_directory().
	 *
	 * @param array<string, mixed> $args Directory filters.
	 * @return string '' or a prepared ' WHERE ...' fragment.
	 */
	private function directory_where( array $args ): string {
		$status  = (string) ( $args['status'] ?? 'active' );
		$country = (string) ( $args['country'] ?? '' );
		$clauses = array();
		$values  = array();

		if ( '' !== $status ) {
			$clauses[] = 'status = %s';
			$values[]  = $status;
		}
		if ( ! empty( $args['available'] ) ) {
			$clauses[] = 'is_available = 1 AND vacation_mode = 0';
		}
		if ( '' !== $country ) {
			$clauses[] = 'country = %s';
			$values[]  = $country;
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		$where = ' WHERE ' . implode( ' AND ', $clauses );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders only; values bound here.
		return empty( $values ) ? $where : (string) $this->wpdb->prepare( $where, ...$values );
	}

	/**
	 * Get verified vendors.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of vendor profiles.
	 */
	public function get_verified( array $args = array() ): array {
		$defaults = array(
			'tier'    => 'rising',
			'orderby' => 'avg_rating',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table}
			WHERE verification_tier = %s AND is_available = 1 AND vacation_mode = 0
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$args['tier'],
			$args['limit'],
			$args['offset']
		);

		return $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get top vendors by rating.
	 *
	 * @param int $limit Number of vendors.
	 * @return array<object> Array of top vendor profiles.
	 */
	public function get_top_rated( int $limit = 10 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE is_available = 1 AND total_reviews >= 5
				ORDER BY avg_rating DESC, total_reviews DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get vendors by country.
	 *
	 * @param string               $country Country name.
	 * @param array<string, mixed> $args    Query arguments.
	 * @return array<object> Array of vendor profiles.
	 */
	public function get_by_country( string $country, array $args = array() ): array {
		$defaults = array(
			'orderby' => 'avg_rating',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE country = %s AND is_available = 1
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$country,
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Update vendor statistics.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function update_stats( int $user_id ): bool {
		$orders_table  = $this->table_name( 'orders' );
		$reviews_table = $this->table_name( 'reviews' );

		// Calculate order stats.
		$order_stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as total_earnings,
					SUM(CASE WHEN status = 'completed' THEN COALESCE(vendor_earnings, total) ELSE 0 END) as net_earnings,
					SUM(CASE WHEN status = 'completed' THEN COALESCE(platform_fee, 0) ELSE 0 END) as total_commission
				FROM {$orders_table}
				WHERE vendor_id = %d",
				$user_id
			),
			ARRAY_A
		);

		// Calculate review stats.
		$review_stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					AVG(rating) as avg_rating,
					COUNT(*) as total_reviews
				FROM {$reviews_table}
				WHERE vendor_id = %d AND status = 'approved' AND review_type = 'customer_to_vendor'",
				$user_id
			),
			ARRAY_A
		);

		// Calculate on-time delivery rate.
		$delivery_stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as total_completed,
					SUM(CASE WHEN completed_at <= delivery_deadline THEN 1 ELSE 0 END) as on_time_count
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = 'completed' AND delivery_deadline IS NOT NULL",
				$user_id
			),
			ARRAY_A
		);

		$on_time_rate = 0;
		if ( (int) $delivery_stats['total_completed'] > 0 ) {
			$on_time_rate = ( (int) $delivery_stats['on_time_count'] / (int) $delivery_stats['total_completed'] ) * 100;
		}

		return $this->upsert(
			$user_id,
			array(
				'total_orders'          => (int) ( $order_stats['total_orders'] ?? 0 ),
				'completed_orders'      => (int) ( $order_stats['completed_orders'] ?? 0 ),
				'total_earnings'        => (float) ( $order_stats['total_earnings'] ?? 0 ),
				'net_earnings'          => (float) ( $order_stats['net_earnings'] ?? 0 ),
				'total_commission'      => (float) ( $order_stats['total_commission'] ?? 0 ),
				'avg_rating'            => round( (float) ( $review_stats['avg_rating'] ?? 0 ), 2 ),
				'total_reviews'         => (int) ( $review_stats['total_reviews'] ?? 0 ),
				'on_time_delivery_rate' => round( $on_time_rate, 2 ),
			)
		) !== false;
	}

	/**
	 * Set vacation mode.
	 *
	 * @param int         $user_id     User ID.
	 * @param bool        $enabled     Whether to enable vacation mode.
	 * @param string      $message     Vacation message.
	 * @param string|null $return_date Optional buyer-facing return date (Y-m-d).
	 *                                 Empty/invalid stores NULL.
	 * @return bool True on success.
	 */
	public function set_vacation_mode( int $user_id, bool $enabled, string $message = '', ?string $return_date = null ): bool {
		return $this->upsert(
			$user_id,
			array(
				'vacation_mode'        => $enabled ? 1 : 0,
				'vacation_message'     => $message,
				// Reuse the shared validator so REST/AJAX/admin all normalize identically.
				'vacation_return_date' => wpss_sanitize_date( (string) $return_date ),
			)
		) !== false;
	}

	/**
	 * Set availability status.
	 *
	 * @param int  $user_id   User ID.
	 * @param bool $available Whether available.
	 * @return bool True on success.
	 */
	public function set_availability( int $user_id, bool $available ): bool {
		return $this->upsert(
			$user_id,
			array( 'is_available' => $available ? 1 : 0 )
		) !== false;
	}

	/**
	 * Update verification tier.
	 *
	 * @param int    $user_id User ID.
	 * @param string $tier    Verification tier (new, rising, top_rated, pro).
	 * @return bool True on success.
	 */
	public function update_verification_tier( int $user_id, string $tier ): bool {
		$data = array( 'verification_tier' => $tier );

		if ( 'new' !== $tier ) {
			$data['verified_at'] = current_time( 'mysql' );
		}

		return $this->upsert( $user_id, $data ) !== false;
	}

	/**
	 * Search vendors.
	 *
	 * @param string               $search Search term.
	 * @param array<string, mixed> $args   Query arguments.
	 * @return array<object> Array of vendor profiles.
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
				WHERE (display_name LIKE %s OR tagline LIKE %s OR bio LIKE %s)
				AND is_available = 1
				AND vacation_mode = 0
				ORDER BY avg_rating DESC
				LIMIT %d OFFSET %d",
				$search_like,
				$search_like,
				$search_like,
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Get vendors pending verification.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of vendor profiles.
	 */
	public function get_pending_verification( array $args = array() ): array {
		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE verification_tier = 'new' AND verified_at IS NULL
				ORDER BY created_at ASC
				LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Count vendors by verification tier.
	 *
	 * @return array<string, int> Counts by tier.
	 */
	public function count_by_tier(): array {
		$results = $this->wpdb->get_results(
			"SELECT verification_tier, COUNT(*) as count
			FROM {$this->table}
			GROUP BY verification_tier",
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['verification_tier'] ] = (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Get distinct countries.
	 *
	 * @return array<string> Array of country names.
	 */
	public function get_countries(): array {
		$results = $this->wpdb->get_col(
			"SELECT DISTINCT country FROM {$this->table}
			WHERE country IS NOT NULL AND country != ''
			ORDER BY country ASC"
		);

		return $results ?: array();
	}
}
