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
	 * Whether an admin-imported review with this reviewer and text already exists.
	 *
	 * Imported reviews have no order, so the unique key cannot stop a repeat;
	 * this is the one check every import path shares.
	 *
	 * @since 1.9.1
	 *
	 * @param int    $vendor_id     Vendor user ID.
	 * @param int    $service_id    Service post ID.
	 * @param string $reviewer_name Reviewer name.
	 * @param string $content       Review text.
	 * @return bool
	 */
	public function imported_exists( int $vendor_id, int $service_id, string $reviewer_name, string $content ): bool {
		return (bool) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE order_id IS NULL AND vendor_id = %d AND service_id = %d AND reviewer_name = %s AND review = %s LIMIT 1",
				$vendor_id,
				$service_id,
				$reviewer_name,
				$content
			)
		);
	}

	/**
	 * Average rating and count of the approved buyer reviews for a vendor or service.
	 *
	 * The one query behind every stored and displayed rating, so the vendor card,
	 * the REST stats, seller levels and the profile row cannot disagree on which
	 * reviews count.
	 *
	 * @param string $column 'vendor_id' or 'service_id'.
	 * @param int    $id     Vendor user ID or service post ID.
	 * @return array{average: float, count: int}
	 */
	public function rating_stats( string $column, int $id ): array {
		$column = 'service_id' === $column ? 'service_id' : 'vendor_id';

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT AVG(rating) AS average, COUNT(*) AS total FROM {$this->table} WHERE {$column} = %d AND status = 'approved' AND review_type = 'customer_to_vendor'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column is whitelisted above.
				$id
			)
		);

		return array(
			'average' => (float) ( $row->average ?? 0 ),
			'count'   => (int) ( $row->total ?? 0 ),
		);
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
	 * Option name that records one reader's "Helpful" vote on one review.
	 *
	 * @param int    $review_id Review ID.
	 * @param int    $user_id   Voter, or 0 for a signed-out reader.
	 * @param string $ip        Reader IP, used only when signed out.
	 * @return string
	 */
	public static function helpful_vote_key( int $review_id, int $user_id, string $ip = '' ): string {
		return '_wpss_vote_' . $review_id . '_' . ( $user_id ? 'u' . $user_id : 'ip' . md5( $ip ) );
	}

	/**
	 * Toggle a reader's "Helpful" vote: first call adds it, the next removes it.
	 *
	 * The one writer behind the REST route and the AJAX action, so the two doors
	 * cannot disagree. The vote row is claimed or released atomically (INSERT
	 * IGNORE / DELETE on a unique option name) and the counter moves only when
	 * a row actually changed, so a double click or a retry never double counts.
	 * A signed-in voter's row has no expiry: it is the record that lets the
	 * reader take the vote back, and it must outlive the daily sweep. Signed-out
	 * rows expire after a week.
	 *
	 * @param int    $review_id Review ID.
	 * @param int    $user_id   Voter, or 0 for a signed-out reader.
	 * @param string $ip        Reader IP, used only when signed out.
	 * @return array{count:int, marked:bool}|string Result, or 'not_found' / 'own_review'.
	 */
	public function toggle_helpful( int $review_id, int $user_id, string $ip = '' ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
		$author = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT reviewer_id FROM {$this->table} WHERE id = %d AND status = 'approved'", $review_id ) );

		if ( null === $author ) {
			return 'not_found';
		}

		if ( $user_id && (int) $author === $user_id ) {
			return 'own_review';
		}

		$key = self::helpful_vote_key( $review_id, $user_id, $ip );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic claim of a unique option name.
		$this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT IGNORE INTO {$this->wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$key,
				$user_id ? 0 : time() + WEEK_IN_SECONDS,
				'no'
			)
		);

		$marked = 1 === (int) $this->wpdb->rows_affected;
		$delta  = $marked ? '+ 1' : '- 1';

		if ( ! $marked ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic release of a unique option name.
			$released = (int) $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->wpdb->options} WHERE option_name = %s", $key ) );

			if ( ! $released ) {
				// A concurrent request already took it back; nothing to move.
				$delta = '+ 0';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; $delta is one of three literals above.
		$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->table} SET helpful_count = GREATEST( helpful_count {$delta}, 0 ) WHERE id = %d", $review_id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
		$count = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT helpful_count FROM {$this->table} WHERE id = %d", $review_id ) );

		return array(
			'count'  => $count,
			'marked' => $marked,
		);
	}

	/**
	 * Which of these reviews a signed-in reader has already marked helpful.
	 *
	 * One query for the whole page, so a list of reviews costs one lookup, not one per row.
	 *
	 * @param int[] $review_ids Review IDs on the page.
	 * @param int   $user_id    Reader.
	 * @return int[] The subset the reader has voted on.
	 */
	public function helpful_voted_ids( array $review_ids, int $user_id ): array {
		$review_ids = array_values( array_unique( array_filter( array_map( 'intval', $review_ids ) ) ) );

		if ( ! $user_id || ! $review_ids ) {
			return array();
		}

		$keys = array_map( fn( int $id ): string => self::helpful_vote_key( $id, $user_id ), $review_ids );
		$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Exact option_name lookup; placeholders built from the key count.
		$found = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT option_name FROM {$this->wpdb->options} WHERE option_name IN ({$in})", ...$keys ) );

		$voted = array();
		foreach ( $review_ids as $id ) {
			if ( in_array( self::helpful_vote_key( $id, $user_id ), $found, true ) ) {
				$voted[] = $id;
			}
		}

		return $voted;
	}

	/**
	 * Sweep expired signed-out "marked review helpful" idempotency rows from
	 * `wp_options`. Signed-in voters' rows are never swept: they are what lets a
	 * reader undo a vote.
	 *
	 * `toggle_helpful()` writes a row per (review × user/IP)
	 * with the `option_value` set to `time() + WEEK_IN_SECONDS` so a guest
	 * can't double-vote within a week. The rows are stored as `autoload=no`
	 * options (so they don't bloat the autoloaded set), but without a sweep
	 * they accumulate forever — over time on a busy marketplace this turns
	 * into millions of dead rows.
	 *
	 * Hooked to the daily `wpss_cleanup_review_votes` Action Scheduler job.
	 * Returns the deleted row count for log/test inspection.
	 *
	 * @return int Number of expired vote rows deleted.
	 */
	public function cleanup_expired_helpful_votes(): int {
		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Targeted cleanup of plugin-owned option rows; option_value is a stored unix timestamp set by mark_review_helpful().
		$deleted = (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->options}
				 WHERE option_name LIKE %s
				 AND option_name LIKE %s
				 AND CAST(option_value AS UNSIGNED) < %d",
				$this->wpdb->esc_like( '_wpss_vote_' ) . '%',
				'%' . $this->wpdb->esc_like( '_ip' ) . '%',
				$now
			)
		);

		return $deleted;
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
