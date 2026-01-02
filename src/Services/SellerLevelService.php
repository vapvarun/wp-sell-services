<?php
/**
 * Seller Level Service
 *
 * Handles seller level/tier calculation and management.
 * Implements Fiverr-style seller levels: New, Level 1, Level 2, Top Rated.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

/**
 * Manages seller level calculations and tier promotions.
 *
 * @since 1.0.0
 */
class SellerLevelService {

	/**
	 * Seller level constants.
	 */
	public const LEVEL_NEW       = 'new';
	public const LEVEL_ONE       = 'level_1';
	public const LEVEL_TWO       = 'level_2';
	public const LEVEL_TOP_RATED = 'top_rated';

	/**
	 * Level requirements.
	 *
	 * @var array<string, array>
	 */
	private array $requirements = array(
		self::LEVEL_NEW       => array(
			'min_orders'        => 0,
			'min_rating'        => 0,
			'min_reviews'       => 0,
			'min_response_rate' => 0,
			'min_delivery_rate' => 0,
			'min_days_active'   => 0,
		),
		self::LEVEL_ONE       => array(
			'min_orders'        => 5,
			'min_rating'        => 4.0,
			'min_reviews'       => 3,
			'min_response_rate' => 80,
			'min_delivery_rate' => 80,
			'min_days_active'   => 30,
		),
		self::LEVEL_TWO       => array(
			'min_orders'        => 25,
			'min_rating'        => 4.5,
			'min_reviews'       => 10,
			'min_response_rate' => 90,
			'min_delivery_rate' => 90,
			'min_days_active'   => 90,
		),
		self::LEVEL_TOP_RATED => array(
			'min_orders'        => 100,
			'min_rating'        => 4.8,
			'min_reviews'       => 50,
			'min_response_rate' => 95,
			'min_delivery_rate' => 95,
			'min_days_active'   => 180,
		),
	);

	/**
	 * Calculate and return the seller's current level.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return string Current seller level.
	 */
	public function calculate_level( int $user_id ): string {
		$stats = $this->get_vendor_stats( $user_id );

		if ( ! $stats ) {
			return self::LEVEL_NEW;
		}

		// Check levels from highest to lowest.
		$levels_to_check = array(
			self::LEVEL_TOP_RATED,
			self::LEVEL_TWO,
			self::LEVEL_ONE,
		);

		foreach ( $levels_to_check as $level ) {
			if ( $this->meets_level_requirements( $stats, $level ) ) {
				return $level;
			}
		}

		return self::LEVEL_NEW;
	}

	/**
	 * Check if vendor stats meet a specific level's requirements.
	 *
	 * @param object $stats Vendor statistics.
	 * @param string $level Level to check against.
	 * @return bool True if requirements are met.
	 */
	public function meets_level_requirements( object $stats, string $level ): bool {
		if ( ! isset( $this->requirements[ $level ] ) ) {
			return false;
		}

		$reqs = $this->requirements[ $level ];

		// Check each requirement.
		if ( $stats->completed_orders < $reqs['min_orders'] ) {
			return false;
		}

		if ( $stats->avg_rating < $reqs['min_rating'] ) {
			return false;
		}

		if ( $stats->total_reviews < $reqs['min_reviews'] ) {
			return false;
		}

		if ( $stats->response_rate < $reqs['min_response_rate'] ) {
			return false;
		}

		if ( $stats->delivery_rate < $reqs['min_delivery_rate'] ) {
			return false;
		}

		if ( $stats->days_active < $reqs['min_days_active'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get vendor statistics for level calculation.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return object|null Statistics object or null.
	 */
	public function get_vendor_stats( int $user_id ): ?object {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
		$orders_table   = $wpdb->prefix . 'wpss_orders';
		$reviews_table  = $wpdb->prefix . 'wpss_reviews';

		// Get vendor profile.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$profiles_table} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $profile ) {
			return null;
		}

		// Get order stats.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status = 'completed' AND completed_at <= delivery_deadline THEN 1 ELSE 0 END) as on_time_orders
				FROM {$orders_table}
				WHERE vendor_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get review stats.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_reviews,
					COALESCE(AVG(rating), 0) as avg_rating
				FROM {$reviews_table}
				WHERE vendor_id = %d AND status = 'approved'",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Calculate days active.
		$created_at  = strtotime( $profile->created_at ?? 'now' );
		$days_active = (int) floor( ( time() - $created_at ) / DAY_IN_SECONDS );

		// Calculate delivery rate.
		$delivery_rate = 0;
		if ( $order_stats->completed_orders > 0 ) {
			$delivery_rate = ( $order_stats->on_time_orders / $order_stats->completed_orders ) * 100;
		}

		// Response rate from profile (default to 100 if not tracked).
		$response_rate = $profile->response_rate ?? 100;

		return (object) array(
			'user_id'          => $user_id,
			'total_orders'     => (int) $order_stats->total_orders,
			'completed_orders' => (int) $order_stats->completed_orders,
			'total_reviews'    => (int) $review_stats->total_reviews,
			'avg_rating'       => (float) $review_stats->avg_rating,
			'response_rate'    => (float) $response_rate,
			'delivery_rate'    => (float) $delivery_rate,
			'days_active'      => $days_active,
		);
	}

	/**
	 * Get requirements for a specific level.
	 *
	 * @param string $level Level to get requirements for.
	 * @return array|null Requirements array or null.
	 */
	public function get_level_requirements( string $level ): ?array {
		return $this->requirements[ $level ] ?? null;
	}

	/**
	 * Get all level requirements.
	 *
	 * @return array<string, array> All requirements.
	 */
	public function get_all_requirements(): array {
		return $this->requirements;
	}

	/**
	 * Get human-readable level labels.
	 *
	 * @return array<string, string> Level labels.
	 */
	public static function get_level_labels(): array {
		return array(
			self::LEVEL_NEW       => __( 'New Seller', 'wp-sell-services' ),
			self::LEVEL_ONE       => __( 'Level 1 Seller', 'wp-sell-services' ),
			self::LEVEL_TWO       => __( 'Level 2 Seller', 'wp-sell-services' ),
			self::LEVEL_TOP_RATED => __( 'Top Rated Seller', 'wp-sell-services' ),
		);
	}

	/**
	 * Get label for a specific level.
	 *
	 * @param string $level Level code.
	 * @return string Level label.
	 */
	public static function get_level_label( string $level ): string {
		$labels = self::get_level_labels();
		return $labels[ $level ] ?? __( 'New Seller', 'wp-sell-services' );
	}

	/**
	 * Update vendor's level in the database.
	 *
	 * @param int    $user_id Vendor user ID.
	 * @param string $level   New level.
	 * @return bool True on success.
	 */
	public function update_vendor_level( int $user_id, string $level ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'verification_tier' => $level,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			/**
			 * Fires when a vendor's level is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $user_id Vendor user ID.
			 * @param string $level   New level.
			 */
			do_action( 'wpss_vendor_level_updated', $user_id, $level );
		}

		return false !== $updated;
	}

	/**
	 * Recalculate and update all vendor levels.
	 *
	 * Intended to be run via cron job.
	 *
	 * @return int Number of vendors updated.
	 */
	public function recalculate_all_levels(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$vendors = $wpdb->get_col(
			"SELECT user_id FROM {$table}"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$updated = 0;

		foreach ( $vendors as $user_id ) {
			$new_level     = $this->calculate_level( (int) $user_id );
			$current_level = $this->get_current_level( (int) $user_id );

			if ( $new_level !== $current_level ) {
				if ( $this->update_vendor_level( (int) $user_id, $new_level ) ) {
					++$updated;
				}
			}
		}

		return $updated;
	}

	/**
	 * Get vendor's current level from database.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return string Current level.
	 */
	public function get_current_level( int $user_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$level = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT verification_tier FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $level ? $level : self::LEVEL_NEW;
	}

	/**
	 * Get progress to next level.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return array Progress details.
	 */
	public function get_progress_to_next_level( int $user_id ): array {
		$current_level = $this->calculate_level( $user_id );
		$stats         = $this->get_vendor_stats( $user_id );

		// Determine next level.
		$level_order = array(
			self::LEVEL_NEW,
			self::LEVEL_ONE,
			self::LEVEL_TWO,
			self::LEVEL_TOP_RATED,
		);

		$current_index = array_search( $current_level, $level_order, true );
		$next_level    = null;

		if ( false !== $current_index && count( $level_order ) - 1 > $current_index ) {
			$next_level = $level_order[ $current_index + 1 ];
		}

		if ( ! $next_level || ! $stats ) {
			return array(
				'current_level' => $current_level,
				'next_level'    => null,
				'progress'      => array(),
				'is_max_level'  => self::LEVEL_TOP_RATED === $current_level,
			);
		}

		$next_reqs = $this->requirements[ $next_level ];
		$progress  = array();

		// Calculate progress for each metric.
		$metrics = array(
			'orders'        => array(
				'current'  => $stats->completed_orders,
				'required' => $next_reqs['min_orders'],
			),
			'rating'        => array(
				'current'  => $stats->avg_rating,
				'required' => $next_reqs['min_rating'],
			),
			'reviews'       => array(
				'current'  => $stats->total_reviews,
				'required' => $next_reqs['min_reviews'],
			),
			'response_rate' => array(
				'current'  => $stats->response_rate,
				'required' => $next_reqs['min_response_rate'],
			),
			'delivery_rate' => array(
				'current'  => $stats->delivery_rate,
				'required' => $next_reqs['min_delivery_rate'],
			),
			'days_active'   => array(
				'current'  => $stats->days_active,
				'required' => $next_reqs['min_days_active'],
			),
		);

		foreach ( $metrics as $key => $data ) {
			$percent          = $data['required'] > 0 ? min( 100, ( $data['current'] / $data['required'] ) * 100 ) : 100;
			$progress[ $key ] = array(
				'current'  => $data['current'],
				'required' => $data['required'],
				'percent'  => round( $percent, 1 ),
				'met'      => $data['current'] >= $data['required'],
			);
		}

		return array(
			'current_level' => $current_level,
			'next_level'    => $next_level,
			'progress'      => $progress,
			'is_max_level'  => false,
		);
	}
}
