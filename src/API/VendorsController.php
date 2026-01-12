<?php
/**
 * Vendors REST Controller
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
use WP_User_Query;

/**
 * REST API controller for vendors.
 *
 * @since 1.0.0
 */
class VendorsController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'vendors';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// List vendors.
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

		// Single vendor.
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
							'description' => __( 'Unique identifier for the vendor.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Current user as vendor profile.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_current_vendor' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_current_vendor' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		// Vendor services.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/services',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_services' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page'     => array(
							'default' => 1,
							'type'    => 'integer',
						),
						'per_page' => array(
							'default' => 10,
							'type'    => 'integer',
						),
					),
				),
			)
		);

		// Vendor reviews.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reviews',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_reviews' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page'     => array(
							'default' => 1,
							'type'    => 'integer',
						),
						'per_page' => array(
							'default' => 10,
							'type'    => 'integer',
						),
					),
				),
			)
		);

		// Vendor statistics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_stats' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Become a vendor (registration).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/register',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_vendor' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'bio'    => array(
							'description' => __( 'Vendor bio/description.', 'wp-sell-services' ),
							'type'        => 'string',
						),
						'skills' => array(
							'description' => __( 'Vendor skills.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Get vendors.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_args( $request );

		$args = array(
			'meta_key'   => '_wpss_is_vendor',
			'meta_value' => '1',
			'number'     => $pagination['per_page'],
			'offset'     => $pagination['offset'],
			'orderby'    => 'meta_value_num',
			'order'      => 'DESC',
		);

		// Search by name.
		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'display_name', 'user_nicename' );
		}

		// Filter by skill/category.
		$skill = $request->get_param( 'skill' );
		if ( $skill ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_wpss_vendor_skills',
					'value'   => $skill,
					'compare' => 'LIKE',
				),
			);
		}

		// Order by rating.
		$orderby = $request->get_param( 'orderby' );
		if ( 'rating' === $orderby ) {
			$args['meta_key'] = '_wpss_rating_average';
		} elseif ( 'orders' === $orderby ) {
			$args['meta_key'] = '_wpss_completed_orders';
		}

		$user_query = new WP_User_Query( $args );
		$vendors    = $user_query->get_results();
		$total      = $user_query->get_total();

		// Prime user meta cache to avoid N+1 queries.
		$vendor_ids = wp_list_pluck( $vendors, 'ID' );
		if ( ! empty( $vendor_ids ) ) {
			update_meta_cache( 'user', $vendor_ids );
		}

		$data = array();
		foreach ( $vendors as $vendor ) {
			$data[] = $this->prepare_item_for_response( $vendor, $request )->get_data();
		}

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single vendor.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$vendor_id = (int) $request->get_param( 'id' );
		$vendor    = get_userdata( $vendor_id );

		if ( ! $vendor ) {
			return new WP_Error(
				'rest_vendor_not_found',
				__( 'Vendor not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		// Verify user is a vendor.
		if ( ! get_user_meta( $vendor_id, '_wpss_is_vendor', true ) ) {
			return new WP_Error(
				'rest_vendor_not_found',
				__( 'Vendor not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $vendor, $request );
	}

	/**
	 * Get current user vendor profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_current_vendor( $request ) {
		$user_id = get_current_user_id();
		$vendor  = get_userdata( $user_id );

		if ( ! get_user_meta( $user_id, '_wpss_is_vendor', true ) ) {
			return new WP_Error(
				'rest_not_vendor',
				__( 'You are not registered as a vendor.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_item_for_response( $vendor, $request, true );
	}

	/**
	 * Update current vendor profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_current_vendor( $request ) {
		$user_id = get_current_user_id();

		if ( ! get_user_meta( $user_id, '_wpss_is_vendor', true ) ) {
			return new WP_Error(
				'rest_not_vendor',
				__( 'You are not registered as a vendor.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		// Update bio.
		if ( $request->has_param( 'bio' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_bio', sanitize_textarea_field( $request->get_param( 'bio' ) ) );
		}

		// Update tagline.
		if ( $request->has_param( 'tagline' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_tagline', sanitize_text_field( $request->get_param( 'tagline' ) ) );
		}

		// Update skills.
		if ( $request->has_param( 'skills' ) ) {
			$skills = array_map( 'sanitize_text_field', (array) $request->get_param( 'skills' ) );
			update_user_meta( $user_id, '_wpss_vendor_skills', $skills );
		}

		// Update languages.
		if ( $request->has_param( 'languages' ) ) {
			$languages = array_map( 'sanitize_text_field', (array) $request->get_param( 'languages' ) );
			update_user_meta( $user_id, '_wpss_vendor_languages', $languages );
		}

		// Update social links.
		if ( $request->has_param( 'social_links' ) ) {
			$social    = $request->get_param( 'social_links' );
			$sanitized = array();
			foreach ( $social as $platform => $url ) {
				$sanitized[ sanitize_key( $platform ) ] = esc_url_raw( $url );
			}
			update_user_meta( $user_id, '_wpss_vendor_social', $sanitized );
		}

		// Update response time.
		if ( $request->has_param( 'response_time' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_response_time', sanitize_text_field( $request->get_param( 'response_time' ) ) );
		}

		// Update display name.
		if ( $request->has_param( 'display_name' ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => sanitize_text_field( $request->get_param( 'display_name' ) ),
				)
			);
		}

		$vendor = get_userdata( $user_id );

		return $this->prepare_item_for_response( $vendor, $request, true );
	}

	/**
	 * Register as vendor.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_vendor( $request ) {
		$user_id = get_current_user_id();

		// Check if already a vendor.
		if ( get_user_meta( $user_id, '_wpss_is_vendor', true ) ) {
			return new WP_Error(
				'rest_already_vendor',
				__( 'You are already registered as a vendor.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		// Check if vendor registration is open.
		$registration_open = apply_filters( 'wpss_vendor_registration_open', true );
		if ( ! $registration_open ) {
			return new WP_Error(
				'rest_registration_closed',
				__( 'Vendor registration is currently closed.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		// Auto-approve or require admin approval.
		$auto_approve = apply_filters( 'wpss_auto_approve_vendors', true );
		$status       = $auto_approve ? 'approved' : 'pending';

		// Set vendor meta.
		update_user_meta( $user_id, '_wpss_is_vendor', 1 );
		update_user_meta( $user_id, '_wpss_vendor_status', $status );
		update_user_meta( $user_id, '_wpss_vendor_since', current_time( 'mysql' ) );

		// Optional profile data.
		if ( $request->has_param( 'bio' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_bio', sanitize_textarea_field( $request->get_param( 'bio' ) ) );
		}

		if ( $request->has_param( 'skills' ) ) {
			$skills = array_map( 'sanitize_text_field', (array) $request->get_param( 'skills' ) );
			update_user_meta( $user_id, '_wpss_vendor_skills', $skills );
		}

		// Add vendor role.
		$user = get_userdata( $user_id );
		$user->add_role( 'wpss_vendor' );

		do_action( 'wpss_vendor_registered', $user_id, $status );

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $status,
				'message' => 'approved' === $status
					? __( 'You are now registered as a vendor.', 'wp-sell-services' )
					: __( 'Your vendor application has been submitted for review.', 'wp-sell-services' ),
			),
			201
		);
	}

	/**
	 * Get vendor services.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_vendor_services( $request ) {
		$vendor_id  = (int) $request->get_param( 'id' );
		$pagination = $this->get_pagination_args( $request );

		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'author'         => $vendor_id,
			'posts_per_page' => $pagination['per_page'],
			'paged'          => $pagination['page'],
		);

		$query = new \WP_Query( $args );

		// Prime post meta and term caches to avoid N+1 queries.
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_meta_cache( 'post', $post_ids );
			update_object_term_cache( $post_ids, 'wpss_service' );
		}

		$data = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->prepare_service_for_response( $post );
		}

		return $this->paginated_response( $data, $query->found_posts, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get vendor reviews.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_vendor_reviews( $request ) {
		global $wpdb;

		$vendor_id  = (int) $request->get_param( 'id' );
		$pagination = $this->get_pagination_args( $request );
		$table      = $wpdb->prefix . 'wpss_reviews';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d AND status = 'approved'",
				$vendor_id
			)
		);

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE vendor_id = %d AND status = 'approved'
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				$pagination['per_page'],
				$pagination['offset']
			)
		);

		// Batch load customers and services to avoid N+1 queries.
		$customer_ids = array_unique( array_filter( wp_list_pluck( $reviews, 'customer_id' ) ) );
		$service_ids  = array_unique( array_filter( wp_list_pluck( $reviews, 'service_id' ) ) );

		// Prime user cache.
		if ( ! empty( $customer_ids ) ) {
			cache_users( $customer_ids );
		}

		// Prime post cache.
		if ( ! empty( $service_ids ) ) {
			_prime_post_caches( $service_ids, false, false );
		}

		$data = array();
		foreach ( $reviews as $review ) {
			$customer = get_userdata( (int) $review->customer_id );
			$service  = get_post( (int) $review->service_id );

			$data[] = array(
				'id'              => (int) $review->id,
				'service_id'      => (int) $review->service_id,
				'service_title'   => $service ? $service->post_title : '',
				'customer_name'   => $customer ? $customer->display_name : '',
				'customer_avatar' => get_avatar_url( (int) $review->customer_id, array( 'size' => 48 ) ),
				'rating'          => (int) $review->rating,
				'review'          => $review->review,
				'vendor_reply'    => $review->vendor_reply,
				'created_at'      => $review->created_at,
			);
		}

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get vendor statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_vendor_stats( $request ) {
		global $wpdb;

		$vendor_id = (int) $request->get_param( 'id' );

		// Services count.
		$services_count  = (int) wp_count_posts( 'wpss_service' )->publish;
		$vendor_services = count(
			get_posts(
				array(
					'post_type'      => 'wpss_service',
					'post_status'    => 'publish',
					'author'         => $vendor_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);

		// Orders stats.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$order_stats  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_orders,
					SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
				FROM {$orders_table}
				WHERE vendor_id = %d",
				$vendor_id
			)
		);

		// Reviews stats.
		$reviews_table = $wpdb->prefix . 'wpss_reviews';
		$review_stats  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as total, AVG(rating) as average
				FROM {$reviews_table}
				WHERE vendor_id = %d AND status = 'approved'",
				$vendor_id
			)
		);

		// Response time (average from meta).
		$avg_response_time = get_user_meta( $vendor_id, '_wpss_avg_response_time', true );

		// Completion rate.
		$total_orders     = (int) $order_stats->total_orders;
		$completed_orders = (int) $order_stats->completed_orders;
		$completion_rate  = $total_orders > 0 ? round( ( $completed_orders / $total_orders ) * 100, 1 ) : 0;

		// On-time delivery rate.
		$on_time_deliveries = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE vendor_id = %d AND status = 'completed' AND completed_at <= delivery_deadline",
				$vendor_id
			)
		);
		$on_time_rate       = $completed_orders > 0 ? round( ( $on_time_deliveries / $completed_orders ) * 100, 1 ) : 0;

		return new WP_REST_Response(
			array(
				'vendor_id'         => $vendor_id,
				'services_count'    => $vendor_services,
				'total_orders'      => $total_orders,
				'completed_orders'  => $completed_orders,
				'active_orders'     => (int) $order_stats->active_orders,
				'cancelled_orders'  => (int) $order_stats->cancelled_orders,
				'completion_rate'   => $completion_rate,
				'on_time_rate'      => $on_time_rate,
				'total_reviews'     => (int) $review_stats->total,
				'average_rating'    => round( (float) $review_stats->average, 1 ),
				'avg_response_time' => $avg_response_time ?: null,
				'member_since'      => get_user_meta( $vendor_id, '_wpss_vendor_since', true ),
			)
		);
	}

	/**
	 * Prepare vendor for response.
	 *
	 * @param \WP_User        $vendor  Vendor user object.
	 * @param WP_REST_Request $request Request object.
	 * @param bool            $is_self Whether this is the current user.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $vendor, $request, bool $is_self = false ): WP_REST_Response {
		$vendor_id = $vendor->ID;

		$data = array(
			'id'               => $vendor_id,
			'display_name'     => $vendor->display_name,
			'username'         => $vendor->user_nicename,
			'avatar'           => get_avatar_url( $vendor_id, array( 'size' => 96 ) ),
			'avatar_large'     => get_avatar_url( $vendor_id, array( 'size' => 256 ) ),
			'tagline'          => get_user_meta( $vendor_id, '_wpss_vendor_tagline', true ) ?: '',
			'bio'              => get_user_meta( $vendor_id, '_wpss_vendor_bio', true ) ?: '',
			'skills'           => get_user_meta( $vendor_id, '_wpss_vendor_skills', true ) ?: array(),
			'languages'        => get_user_meta( $vendor_id, '_wpss_vendor_languages', true ) ?: array(),
			'response_time'    => get_user_meta( $vendor_id, '_wpss_vendor_response_time', true ) ?: '',
			'social_links'     => get_user_meta( $vendor_id, '_wpss_vendor_social', true ) ?: array(),
			'rating_average'   => (float) get_user_meta( $vendor_id, '_wpss_rating_average', true ) ?: 0,
			'rating_count'     => (int) get_user_meta( $vendor_id, '_wpss_rating_count', true ) ?: 0,
			'completed_orders' => (int) get_user_meta( $vendor_id, '_wpss_completed_orders', true ) ?: 0,
			'member_since'     => get_user_meta( $vendor_id, '_wpss_vendor_since', true ) ?: $vendor->user_registered,
			'is_verified'      => (bool) get_user_meta( $vendor_id, '_wpss_vendor_verified', true ),
			'country'          => get_user_meta( $vendor_id, '_wpss_vendor_country', true ) ?: '',
		);

		// Add private data for self.
		if ( $is_self ) {
			$data['email']  = $vendor->user_email;
			$data['status'] = get_user_meta( $vendor_id, '_wpss_vendor_status', true ) ?: 'approved';
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Prepare service for response (minimal).
	 *
	 * @param \WP_Post $post Service post.
	 * @return array
	 */
	private function prepare_service_for_response( \WP_Post $post ): array {
		$service_id = $post->ID;

		return array(
			'id'             => $service_id,
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'excerpt'        => get_the_excerpt( $post ),
			'thumbnail'      => get_the_post_thumbnail_url( $service_id, 'medium' ) ?: '',
			'price'          => (float) get_post_meta( $service_id, '_wpss_starting_price', true ) ?: 0,
			'rating_average' => (float) get_post_meta( $service_id, '_wpss_rating_average', true ) ?: 0,
			'rating_count'   => (int) get_post_meta( $service_id, '_wpss_rating_count', true ) ?: 0,
			'category'       => wp_get_post_terms( $service_id, 'wpss_service_category', array( 'fields' => 'names' ) )[0] ?? '',
		);
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array(
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'search'   => array(
				'description' => __( 'Search by name.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'skill'    => array(
				'description' => __( 'Filter by skill.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'orderby'  => array(
				'description' => __( 'Order by field.', 'wp-sell-services' ),
				'type'        => 'string',
				'default'     => 'rating',
				'enum'        => array( 'rating', 'orders', 'registered' ),
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
			'title'      => 'vendor',
			'type'       => 'object',
			'properties' => array(
				'id'            => array(
					'description' => __( 'Vendor ID.', 'wp-sell-services' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'display_name'  => array(
					'description' => __( 'Display name.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'bio'           => array(
					'description' => __( 'Vendor bio.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'tagline'       => array(
					'description' => __( 'Vendor tagline.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'skills'        => array(
					'description' => __( 'Vendor skills.', 'wp-sell-services' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view', 'edit' ),
				),
				'languages'     => array(
					'description' => __( 'Languages spoken.', 'wp-sell-services' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view', 'edit' ),
				),
				'social_links'  => array(
					'description' => __( 'Social media links.', 'wp-sell-services' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
				'response_time' => array(
					'description' => __( 'Typical response time.', 'wp-sell-services' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
	}
}
