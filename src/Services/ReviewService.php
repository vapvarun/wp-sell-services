<?php
/**
 * Review Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\Review;
use WPSellServices\Models\ServiceOrder;

/**
 * Handles review business logic.
 *
 * @since 1.0.0
 */
class ReviewService {

	/**
	 * Create review for order.
	 *
	 * @param int   $order_id    Order ID.
	 * @param int   $reviewer_id Reviewer user ID.
	 * @param array $data        Review data.
	 * @return Review|null
	 */
	public function create( int $order_id, int $reviewer_id, array $data ): ?Review {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		// Verify order is completed.
		if ( ServiceOrder::STATUS_COMPLETED !== $order->status ) {
			return null;
		}

		// Verify reviewer is the customer.
		if ( $order->customer_id !== $reviewer_id ) {
			return null;
		}

		// Check if review already exists.
		if ( $this->has_review( $order_id ) ) {
			return null;
		}

		// Validate rating.
		$rating = (int) ( $data['rating'] ?? 0 );
		if ( $rating < 1 || $rating > 5 ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'order_id'             => $order_id,
				'service_id'           => $order->service_id,
				'reviewer_id'          => $reviewer_id,
				'reviewed_id'          => $order->vendor_id,
				'rating'               => $rating,
				'rating_communication' => ! empty( $data['rating_communication'] ) ? (int) $data['rating_communication'] : null,
				'rating_quality'       => ! empty( $data['rating_quality'] ) ? (int) $data['rating_quality'] : null,
				'rating_value'         => ! empty( $data['rating_value'] ) ? (int) $data['rating_value'] : null,
				'title'                => sanitize_text_field( $data['title'] ?? '' ),
				'content'              => wp_kses_post( $data['content'] ?? '' ),
				'status'               => $this->requires_moderation() ? Review::STATUS_PENDING : Review::STATUS_APPROVED,
				'is_verified'          => 1,
				'created_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$review_id = (int) $wpdb->insert_id;

		if ( ! $review_id ) {
			return null;
		}

		// Update service and vendor ratings.
		$this->update_service_rating( $order->service_id );
		$this->update_vendor_rating( $order->vendor_id );

		// Notify vendor.
		$notification_service = new NotificationService();
		$notification_service->create(
			$order->vendor_id,
			NotificationService::TYPE_REVIEW_RECEIVED,
			__( 'New Review Received', 'wp-sell-services' ),
			/* translators: %d: star rating */
			sprintf( __( 'You received a %d-star review', 'wp-sell-services' ), $rating ),
			array(
				'review_id'  => $review_id,
				'order_id'   => $order_id,
				'service_id' => $order->service_id,
				'rating'     => $rating,
			)
		);

		/**
		 * Fires when review is created.
		 *
		 * @param int $review_id Review ID.
		 * @param int $order_id  Order ID.
		 */
		do_action( 'wpss_review_created', $review_id, $order_id );

		return $this->get( $review_id );
	}

	/**
	 * Get review by ID.
	 *
	 * @param int $review_id Review ID.
	 * @return Review|null
	 */
	public function get( int $review_id ): ?Review {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$review_id
			)
		);

		return $row ? Review::from_db( $row ) : null;
	}

	/**
	 * Get review for order.
	 *
	 * @param int $order_id Order ID.
	 * @return Review|null
	 */
	public function get_by_order( int $order_id ): ?Review {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $row ? Review::from_db( $row ) : null;
	}

	/**
	 * Check if order has review.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function has_review( int $order_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get reviews for service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $args       Query args.
	 * @return Review[]
	 */
	public function get_service_reviews( int $service_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		$defaults = array(
			'status' => Review::STATUS_APPROVED,
			'limit'  => 10,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE service_id = %d AND status = %s
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$service_id,
				$args['status'],
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( fn( $row ) => Review::from_db( $row ), $rows );
	}

	/**
	 * Get reviews for vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query args.
	 * @return Review[]
	 */
	public function get_vendor_reviews( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		$defaults = array(
			'status' => Review::STATUS_APPROVED,
			'limit'  => 10,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE reviewed_id = %d AND status = %s
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				$args['status'],
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( fn( $row ) => Review::from_db( $row ), $rows );
	}

	/**
	 * Add vendor response to review.
	 *
	 * @param int    $review_id Review ID.
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $response  Response content.
	 * @return bool
	 */
	public function add_response( int $review_id, int $vendor_id, string $response ): bool {
		$review = $this->get( $review_id );

		if ( ! $review || $review->reviewed_id !== $vendor_id ) {
			return false;
		}

		if ( $review->has_response() ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'response'    => wp_kses_post( $response ),
				'response_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $review_id )
		);
	}

	/**
	 * Update service rating.
	 *
	 * @param int $service_id Service ID.
	 * @return void
	 */
	private function update_service_rating( int $service_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
				FROM {$table}
				WHERE service_id = %d AND status = %s",
				$service_id,
				Review::STATUS_APPROVED
			)
		);

		if ( $stats ) {
			update_post_meta( $service_id, '_wpss_rating_average', round( (float) $stats->avg_rating, 2 ) );
			update_post_meta( $service_id, '_wpss_review_count', (int) $stats->review_count );
		}
	}

	/**
	 * Update vendor rating.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function update_vendor_rating( int $vendor_id ): void {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'wpss_reviews';
		$vendors_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
				FROM {$reviews_table}
				WHERE reviewed_id = %d AND status = %s",
				$vendor_id,
				Review::STATUS_APPROVED
			)
		);

		if ( $stats ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$vendors_table,
				array(
					'rating'       => round( (float) $stats->avg_rating, 2 ),
					'review_count' => (int) $stats->review_count,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'user_id' => $vendor_id )
			);
		}
	}

	/**
	 * Check if reviews require moderation.
	 *
	 * @return bool
	 */
	private function requires_moderation(): bool {
		return (bool) get_option( 'wpss_moderate_reviews', false );
	}
}
