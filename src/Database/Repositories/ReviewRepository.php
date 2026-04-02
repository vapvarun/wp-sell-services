<?php
/**
 * Review Repository
 *
 * Database operations for reviews.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * ReviewRepository class.
 *
 * @since 1.0.0
 */
class ReviewRepository extends AbstractRepository {

	/**
	 * Allowed columns for ordering and filtering.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array(
		'id',
		'order_id',
		'service_id',
		'reviewer_id',
		'vendor_id',
		'rating',
		'status',
		'review_type',
		'helpful_count',
		'created_at',
		'updated_at',
	);

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->table_name( 'reviews' );
	}

	/**
	 * Get reviews for a service.
	 *
	 * @param int                  $service_id Service post ID.
	 * @param array<string, mixed> $args       Query arguments.
	 * @return array<object> Array of reviews.
	 */
	public function get_by_service( int $service_id, array $args = array() ): array {
		$defaults = array(
			'status'  => 'approved',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 10,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE service_id = %d AND status = %s
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$service_id,
				$args['status'],
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Get reviews for a vendor.
	 *
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $args      Query arguments.
	 * @return array<object> Array of reviews.
	 */
	public function get_by_vendor( int $vendor_id, array $args = array() ): array {
		$defaults = array(
			'status'  => 'approved',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 10,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE vendor_id = %d AND status = %s AND review_type = 'customer_to_vendor'
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$vendor_id,
				$args['status'],
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Get review by order ID.
	 *
	 * @param int    $order_id    Order ID.
	 * @param string $review_type Review type.
	 * @return object|null Review object or null.
	 */
	public function get_by_order( int $order_id, string $review_type = 'customer_to_vendor' ): ?object {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_id = %d AND review_type = %s",
				$order_id,
				$review_type
			)
		);

		return $result ?: null;
	}

	/**
	 * Check if order has been reviewed.
	 *
	 * @param int    $order_id    Order ID.
	 * @param string $review_type Review type.
	 * @return bool True if reviewed.
	 */
	public function order_has_review( int $order_id, string $review_type = 'customer_to_vendor' ): bool {
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE order_id = %d AND review_type = %s",
				$order_id,
				$review_type
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get rating summary for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return array<string, mixed> Rating summary.
	 */
	public function get_service_rating_summary( int $service_id ): array {
		$cache_key = 'wpss_service_rating_' . $service_id;
		$result    = get_transient( $cache_key );

		if ( false !== $result ) {
			return $result;
		}

		$summary = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as total_reviews,
					AVG(rating) as average_rating,
					AVG(communication_rating) as avg_communication,
					AVG(quality_rating) as avg_quality,
					AVG(delivery_rating) as avg_delivery
				FROM {$this->table}
				WHERE service_id = %d AND status = 'approved'",
				$service_id
			),
			ARRAY_A
		);

		// Get rating breakdown.
		$breakdown = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT rating, COUNT(*) as count
				FROM {$this->table}
				WHERE service_id = %d AND status = 'approved'
				GROUP BY rating
				ORDER BY rating DESC",
				$service_id
			),
			ARRAY_A
		);

		$rating_breakdown = array();
		foreach ( $breakdown as $row ) {
			$rating_breakdown[ (int) $row['rating'] ] = (int) $row['count'];
		}

		$result = array(
			'total_reviews'     => (int) ( $summary['total_reviews'] ?? 0 ),
			'average_rating'    => round( (float) ( $summary['average_rating'] ?? 0 ), 1 ),
			'avg_communication' => round( (float) ( $summary['avg_communication'] ?? 0 ), 1 ),
			'avg_quality'       => round( (float) ( $summary['avg_quality'] ?? 0 ), 1 ),
			'avg_delivery'      => round( (float) ( $summary['avg_delivery'] ?? 0 ), 1 ),
			'breakdown'         => $rating_breakdown,
		);

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get rating summary for a vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array<string, mixed> Rating summary.
	 */
	public function get_vendor_rating_summary( int $vendor_id ): array {
		$cache_key = 'wpss_vendor_rating_' . $vendor_id;
		$result    = get_transient( $cache_key );

		if ( false !== $result ) {
			return $result;
		}

		$summary = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) as total_reviews,
					AVG(rating) as average_rating,
					AVG(communication_rating) as avg_communication,
					AVG(quality_rating) as avg_quality,
					AVG(delivery_rating) as avg_delivery
				FROM {$this->table}
				WHERE vendor_id = %d AND status = 'approved' AND review_type = 'customer_to_vendor'",
				$vendor_id
			),
			ARRAY_A
		);

		$result = array(
			'total_reviews'     => (int) ( $summary['total_reviews'] ?? 0 ),
			'average_rating'    => round( (float) ( $summary['average_rating'] ?? 0 ), 1 ),
			'avg_communication' => round( (float) ( $summary['avg_communication'] ?? 0 ), 1 ),
			'avg_quality'       => round( (float) ( $summary['avg_quality'] ?? 0 ), 1 ),
			'avg_delivery'      => round( (float) ( $summary['avg_delivery'] ?? 0 ), 1 ),
		);

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Add vendor reply to a review.
	 *
	 * @param int    $review_id Review ID.
	 * @param string $reply     Reply text.
	 * @return bool True on success.
	 */
	public function add_vendor_reply( int $review_id, string $reply ): bool {
		return $this->update(
			$review_id,
			array(
				'vendor_reply'    => $reply,
				'vendor_reply_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get reviews pending moderation.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of reviews.
	 */
	public function get_pending_moderation( array $args = array() ): array {
		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status = 'pending'
				ORDER BY created_at ASC
				LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Update review status.
	 *
	 * @param int    $review_id Review ID.
	 * @param string $status    New status.
	 * @return bool True on success.
	 */
	public function update_status( int $review_id, string $status ): bool {
		return $this->update( $review_id, array( 'status' => $status ) );
	}

	/**
	 * Increment helpful count.
	 *
	 * @param int $review_id Review ID.
	 * @return bool True on success.
	 */
	public function increment_helpful( int $review_id ): bool {
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET helpful_count = helpful_count + 1 WHERE id = %d",
				$review_id
			)
		);

		return false !== $result;
	}

	/**
	 * Clear rating summary cache for a service and/or vendor.
	 *
	 * @param int|null $service_id Service ID to clear cache for.
	 * @param int|null $vendor_id  Vendor ID to clear cache for.
	 * @return void
	 */
	public function clear_rating_cache( ?int $service_id = null, ?int $vendor_id = null ): void {
		if ( null !== $service_id ) {
			delete_transient( 'wpss_service_rating_' . $service_id );
		}
		if ( null !== $vendor_id ) {
			delete_transient( 'wpss_vendor_rating_' . $vendor_id );
		}
	}

	/**
	 * Insert a review and clear related caches.
	 *
	 * @param array<string, mixed> $data   Review data.
	 * @param array<string>        $format Optional format array for wpdb->insert.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function insert( array $data, array $format = array() ): int|false {
		$result = parent::insert( $data, $format );

		if ( $result ) {
			// Clear caches after successful insert.
			$this->clear_rating_cache(
				$data['service_id'] ?? null,
				$data['vendor_id'] ?? null
			);
		}

		return $result;
	}
}
