<?php
/**
 * Reviews REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\API;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for reviews.
 *
 * @since 1.0.0
 */
class ReviewsController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reviews';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// List reviews.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Single review.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the review.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_update_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_delete_permissions' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Create review for order.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/review',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_create_permissions' ),
					'args'                => array(
						'order_id' => array(
							'description' => __( 'Order ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'rating'   => array(
							'description' => __( 'Rating (1-5).', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
							'minimum'     => 1,
							'maximum'     => 5,
						),
						'review'   => array(
							'description' => __( 'Review text.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// Vendor reply to review.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reply',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_reply' ),
					'permission_callback' => array( $this, 'check_reply_permissions' ),
					'args'                => array(
						'reply' => array(
							'description' => __( 'Reply text.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// Service reviews summary.
		register_rest_route(
			$this->namespace,
			'/services/(?P<service_id>[\d]+)/reviews/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_service_summary' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Vendor reviews summary.
		register_rest_route(
			$this->namespace,
			'/vendors/(?P<vendor_id>[\d]+)/reviews/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_summary' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Get reviews.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		global $wpdb;

		$pagination = $this->get_pagination_args( $request );
		$table      = $wpdb->prefix . 'wpss_reviews';

		$where  = array( '1=1' );
		$values = array();

		// Filter by service.
		$service_id = $request->get_param( 'service_id' );
		if ( $service_id ) {
			$where[]  = 'service_id = %d';
			$values[] = (int) $service_id;
		}

		// Filter by vendor.
		$vendor_id = $request->get_param( 'vendor_id' );
		if ( $vendor_id ) {
			$where[]  = 'vendor_id = %d';
			$values[] = (int) $vendor_id;
		}

		// Filter by rating.
		$rating = $request->get_param( 'rating' );
		if ( $rating ) {
			$where[]  = 'rating = %d';
			$values[] = (int) $rating;
		}

		// Only approved reviews for public.
		if ( ! current_user_can( 'manage_options' ) ) {
			$where[] = "status = 'approved'";
		}

		$where_sql = implode( ' AND ', $where );

		// Get total.
		$count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$count_query = $wpdb->prepare( $count_query, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Order by.
		$orderby = $request->get_param( 'orderby' ) ?: 'created_at';
		$order   = $request->get_param( 'order' ) ?: 'DESC';

		$allowed_orderby = array( 'created_at', 'rating', 'helpful_count' );
		$allowed_order   = array( 'ASC', 'DESC' );

		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}
		if ( ! in_array( strtoupper( $order ), $allowed_order, true ) ) {
			$order = 'DESC';
		}

		// Get reviews.
		$query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$args  = array_merge( $values, array( $pagination['per_page'], $pagination['offset'] ) );

		$reviews = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$data = array();
		foreach ( $reviews as $review ) {
			$data[] = $this->prepare_item_for_response( $review, $request )->get_data();
		}

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$review = $this->get_review( (int) $request->get_param( 'id' ) );

		if ( ! $review ) {
			return new WP_Error(
				'rest_review_not_found',
				__( 'Review not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		// Check if approved or user has permission.
		if ( 'approved' !== $review->status && ! current_user_can( 'manage_options' ) ) {
			$user_id = get_current_user_id();
			if ( (int) $review->customer_id !== $user_id && (int) $review->vendor_id !== $user_id ) {
				return new WP_Error(
					'rest_review_not_found',
					__( 'Review not found.', 'wp-sell-services' ),
					array( 'status' => 404 )
				);
			}
		}

		return $this->prepare_item_for_response( $review, $request );
	}

	/**
	 * Create review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		global $wpdb;

		$order_id = (int) $request->get_param( 'order_id' );
		$rating   = (int) $request->get_param( 'rating' );
		$review   = sanitize_textarea_field( $request->get_param( 'review' ) );
		$user_id  = get_current_user_id();

		// Get order.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$order        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		// Verify user is the customer.
		if ( (int) $order->customer_id !== $user_id ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You can only review orders you placed.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		// Check order is completed.
		if ( 'completed' !== $order->status ) {
			return new WP_Error(
				'rest_order_not_completed',
				__( 'You can only review completed orders.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		// Check if already reviewed.
		$reviews_table = $wpdb->prefix . 'wpss_reviews';
		$existing      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$reviews_table} WHERE order_id = %d",
				$order_id
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'rest_already_reviewed',
				__( 'You have already reviewed this order.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		// Validate rating.
		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error(
				'rest_invalid_rating',
				__( 'Rating must be between 1 and 5.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		// Auto-approve or require moderation.
		$auto_approve = apply_filters( 'wpss_auto_approve_reviews', true );
		$status       = $auto_approve ? 'approved' : 'pending';

		// Create review.
		$result = $wpdb->insert(
			$reviews_table,
			array(
				'order_id'    => $order_id,
				'service_id'  => $order->service_id,
				'reviewer_id' => $user_id,
				'reviewee_id' => $order->vendor_id,
				'vendor_id'   => $order->vendor_id,
				'customer_id' => $user_id,
				'review_type' => 'customer_to_vendor',
				'rating'      => $rating,
				'review'      => $review,
				'status'      => $status,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error(
				'rest_review_failed',
				__( 'Failed to create review.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		$review_id = $wpdb->insert_id;

		// Update service rating cache.
		$this->update_rating_cache( (int) $order->service_id, (int) $order->vendor_id );

		// Trigger actions.
		do_action( 'wpss_review_created', $review_id, $order_id );

		$new_review = $this->get_review( $review_id );

		return new WP_REST_Response(
			$this->prepare_item_for_response( $new_review, $request )->get_data(),
			201
		);
	}

	/**
	 * Update review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		global $wpdb;

		$review_id = (int) $request->get_param( 'id' );
		$review    = $this->get_review( $review_id );

		if ( ! $review ) {
			return new WP_Error(
				'rest_review_not_found',
				__( 'Review not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$updates = array();

		// Customers can update rating and review text.
		if ( (int) $review->customer_id === get_current_user_id() ) {
			if ( $request->has_param( 'rating' ) ) {
				$rating = (int) $request->get_param( 'rating' );
				if ( $rating >= 1 && $rating <= 5 ) {
					$updates['rating'] = $rating;
				}
			}

			if ( $request->has_param( 'review' ) ) {
				$updates['review'] = sanitize_textarea_field( $request->get_param( 'review' ) );
			}
		}

		// Admins can update status.
		if ( current_user_can( 'manage_options' ) && $request->has_param( 'status' ) ) {
			$status = $request->get_param( 'status' );
			if ( in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
				$updates['status'] = $status;
			}
		}

		if ( ! empty( $updates ) ) {
			$updates['updated_at'] = current_time( 'mysql' );

			$wpdb->update(
				$wpdb->prefix . 'wpss_reviews',
				$updates,
				array( 'id' => $review_id ),
				null,
				array( '%d' )
			);

			// Update rating cache if rating changed.
			if ( isset( $updates['rating'] ) ) {
				$this->update_rating_cache( (int) $review->service_id, (int) $review->vendor_id );
			}
		}

		$updated_review = $this->get_review( $review_id );

		return $this->prepare_item_for_response( $updated_review, $request );
	}

	/**
	 * Delete review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$review_id = (int) $request->get_param( 'id' );
		$review    = $this->get_review( $review_id );

		if ( ! $review ) {
			return new WP_Error(
				'rest_review_not_found',
				__( 'Review not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$service_id = (int) $review->service_id;
		$vendor_id  = (int) $review->vendor_id;

		$wpdb->delete(
			$wpdb->prefix . 'wpss_reviews',
			array( 'id' => $review_id ),
			array( '%d' )
		);

		// Update rating cache.
		$this->update_rating_cache( $service_id, $vendor_id );

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $review_id,
			)
		);
	}

	/**
	 * Create vendor reply.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_reply( $request ) {
		global $wpdb;

		$review_id = (int) $request->get_param( 'id' );
		$reply     = sanitize_textarea_field( $request->get_param( 'reply' ) );
		$review    = $this->get_review( $review_id );

		if ( ! $review ) {
			return new WP_Error(
				'rest_review_not_found',
				__( 'Review not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		if ( ! empty( $review->vendor_reply ) ) {
			return new WP_Error(
				'rest_already_replied',
				__( 'You have already replied to this review.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'wpss_reviews',
			array(
				'vendor_reply'    => $reply,
				'vendor_reply_at' => current_time( 'mysql' ),
			),
			array( 'id' => $review_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'wpss_review_reply_created', $review_id );

		$updated_review = $this->get_review( $review_id );

		return $this->prepare_item_for_response( $updated_review, $request );
	}

	/**
	 * Get service reviews summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_service_summary( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'service_id' );
		$table      = $wpdb->prefix . 'wpss_reviews';

		// Get aggregate data.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					AVG(rating) as average,
					SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
					SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
					SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
					SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
					SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
				FROM {$table}
				WHERE service_id = %d AND status = 'approved'",
				$service_id
			)
		);

		$total   = (int) $stats->total;
		$average = $total > 0 ? round( (float) $stats->average, 1 ) : 0;

		return new WP_REST_Response(
			array(
				'service_id'     => $service_id,
				'total_reviews'  => $total,
				'average_rating' => $average,
				'breakdown'      => array(
					5 => (int) $stats->five_star,
					4 => (int) $stats->four_star,
					3 => (int) $stats->three_star,
					2 => (int) $stats->two_star,
					1 => (int) $stats->one_star,
				),
				'percentages'    => $total > 0 ? array(
					5 => round( ( (int) $stats->five_star / $total ) * 100, 1 ),
					4 => round( ( (int) $stats->four_star / $total ) * 100, 1 ),
					3 => round( ( (int) $stats->three_star / $total ) * 100, 1 ),
					2 => round( ( (int) $stats->two_star / $total ) * 100, 1 ),
					1 => round( ( (int) $stats->one_star / $total ) * 100, 1 ),
				) : array(
					5 => 0,
					4 => 0,
					3 => 0,
					2 => 0,
					1 => 0,
				),
			)
		);
	}

	/**
	 * Get vendor reviews summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_vendor_summary( $request ) {
		global $wpdb;

		$vendor_id = (int) $request->get_param( 'vendor_id' );
		$table     = $wpdb->prefix . 'wpss_reviews';

		// Get aggregate data.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					AVG(rating) as average,
					SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
					SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
					SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
					SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
					SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
				FROM {$table}
				WHERE vendor_id = %d AND status = 'approved'",
				$vendor_id
			)
		);

		$total   = (int) $stats->total;
		$average = $total > 0 ? round( (float) $stats->average, 1 ) : 0;

		// Get total completed orders for response rate.
		$orders_table     = $wpdb->prefix . 'wpss_orders';
		$completed_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE vendor_id = %d AND status = 'completed'",
				$vendor_id
			)
		);

		$response_rate = $completed_orders > 0 ? round( ( $total / $completed_orders ) * 100, 1 ) : 0;

		return new WP_REST_Response(
			array(
				'vendor_id'        => $vendor_id,
				'total_reviews'    => $total,
				'average_rating'   => $average,
				'completed_orders' => $completed_orders,
				'response_rate'    => $response_rate,
				'breakdown'        => array(
					5 => (int) $stats->five_star,
					4 => (int) $stats->four_star,
					3 => (int) $stats->three_star,
					2 => (int) $stats->two_star,
					1 => (int) $stats->one_star,
				),
			)
		);
	}

	/**
	 * Get review by ID.
	 *
	 * @param int $review_id Review ID.
	 * @return object|null
	 */
	private function get_review( int $review_id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_reviews WHERE id = %d",
				$review_id
			)
		);
	}

	/**
	 * Update rating cache for service and vendor.
	 *
	 * @param int $service_id Service ID.
	 * @param int $vendor_id  Vendor ID.
	 * @return void
	 */
	private function update_rating_cache( int $service_id, int $vendor_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// Update service rating.
		$service_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as average
				FROM {$table}
				WHERE service_id = %d AND status = 'approved'",
				$service_id
			)
		);

		update_post_meta( $service_id, '_wpss_rating_count', (int) $service_stats->count );
		update_post_meta( $service_id, '_wpss_rating_average', round( (float) $service_stats->average, 1 ) );

		// Update vendor rating.
		$vendor_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as average
				FROM {$table}
				WHERE vendor_id = %d AND status = 'approved'",
				$vendor_id
			)
		);

		update_user_meta( $vendor_id, '_wpss_rating_count', (int) $vendor_stats->count );
		update_user_meta( $vendor_id, '_wpss_rating_average', round( (float) $vendor_stats->average, 1 ) );
	}

	/**
	 * Check create permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_create_permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to create reviews.', 'wp-sell-services' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Check update permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_update_permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in.', 'wp-sell-services' ),
				array( 'status' => 401 )
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$review = $this->get_review( (int) $request->get_param( 'id' ) );

		if ( ! $review || (int) $review->customer_id !== get_current_user_id() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You can only edit your own reviews.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check delete permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_delete_permissions( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Only administrators can delete reviews.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check reply permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_reply_permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in.', 'wp-sell-services' ),
				array( 'status' => 401 )
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$review = $this->get_review( (int) $request->get_param( 'id' ) );

		if ( ! $review || (int) $review->vendor_id !== get_current_user_id() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Only the vendor can reply to reviews.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Prepare review for response.
	 *
	 * @param object          $review  Review object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $review, $request ): WP_REST_Response {
		$customer = get_userdata( (int) $review->customer_id );
		$vendor   = get_userdata( (int) $review->vendor_id );
		$service  = get_post( (int) $review->service_id );

		$data = array(
			'id'              => (int) $review->id,
			'order_id'        => (int) $review->order_id,
			'service_id'      => (int) $review->service_id,
			'service_title'   => $service ? $service->post_title : '',
			'vendor_id'       => (int) $review->vendor_id,
			'vendor_name'     => $vendor ? $vendor->display_name : '',
			'customer_id'     => (int) $review->customer_id,
			'customer_name'   => $customer ? $customer->display_name : '',
			'customer_avatar' => get_avatar_url( (int) $review->customer_id, array( 'size' => 48 ) ),
			'rating'          => (int) $review->rating,
			'review'          => $review->review,
			'status'          => $review->status,
			'helpful_count'   => (int) ( $review->helpful_count ?? 0 ),
			'vendor_reply'    => $review->vendor_reply ?? null,
			'vendor_reply_at' => $review->vendor_reply_at ?? null,
			'created_at'      => $review->created_at,
			'updated_at'      => $review->updated_at ?? null,
		);

		return new WP_REST_Response( $data );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'       => array(
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'   => array(
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'service_id' => array(
				'description' => __( 'Filter by service.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
			'vendor_id'  => array(
				'description' => __( 'Filter by vendor.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
			'rating'     => array(
				'description' => __( 'Filter by rating.', 'wp-sell-services' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 5,
			),
			'orderby'    => array(
				'description' => __( 'Order by field.', 'wp-sell-services' ),
				'type'        => 'string',
				'default'     => 'created_at',
				'enum'        => array( 'created_at', 'rating', 'helpful_count' ),
			),
			'order'      => array(
				'description' => __( 'Order direction.', 'wp-sell-services' ),
				'type'        => 'string',
				'default'     => 'DESC',
				'enum'        => array( 'ASC', 'DESC' ),
			),
		);
	}

	/**
	 * Get item schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'review',
			'type'       => 'object',
			'properties' => array(
				'id'           => array(
					'description' => __( 'Review ID.', 'wp-sell-services' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'order_id'     => array(
					'description' => __( 'Order ID.', 'wp-sell-services' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'service_id'   => array(
					'description' => __( 'Service ID.', 'wp-sell-services' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'rating'       => array(
					'description' => __( 'Rating (1-5).', 'wp-sell-services' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'minimum'     => 1,
					'maximum'     => 5,
				),
				'review'       => array(
					'description' => __( 'Review text.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'status'       => array(
					'description' => __( 'Review status.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'enum'        => array( 'pending', 'approved', 'rejected' ),
				),
				'vendor_reply' => array(
					'description' => __( 'Vendor reply.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'created_at'   => array(
					'description' => __( 'Created date.', 'wp-sell-services' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);
	}
}
