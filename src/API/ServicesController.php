<?php
/**
 * Services REST Controller
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
use WP_Query;
use WPSellServices\Services\ModerationService;

/**
 * REST controller for services.
 *
 * @since 1.0.0
 */
class ServicesController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'services';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /services - List all services.
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

		// GET /services/{id} - Get single service.
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
							'description' => __( 'Service ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// POST /services - Create service (vendors only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		// PUT/PATCH /services/{id} - Update service.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		// DELETE /services/{id} - Delete service.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		// GET /services/{id}/packages - Get service packages.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/packages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_packages' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /services/{id}/faqs - Get service FAQs.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/faqs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_faqs' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /services/{id}/reviews - Get service reviews.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reviews',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_reviews' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Get services collection.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_args( $request );

		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'posts_per_page' => $pagination['per_page'],
			'offset'         => $pagination['offset'],
			'orderby'        => $request->get_param( 'orderby' ) ?: 'date',
			'order'          => $request->get_param( 'order' ) ?: 'DESC',
		);

		// Filter by category.
		$category = $request->get_param( 'category' );
		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'wpss_service_category',
					'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
					'terms'    => $category,
				),
			);
		}

		// Filter by vendor.
		$vendor = $request->get_param( 'vendor' );
		if ( $vendor ) {
			$args['author'] = (int) $vendor;
		}

		// Search.
		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		// Price range.
		$min_price = $request->get_param( 'min_price' );
		$max_price = $request->get_param( 'max_price' );

		if ( $min_price || $max_price ) {
			$args['meta_query'] = array();

			if ( $min_price ) {
				$args['meta_query'][] = array(
					'key'     => '_wpss_starting_price',
					'value'   => (float) $min_price,
					'compare' => '>=',
					'type'    => 'DECIMAL',
				);
			}

			if ( $max_price ) {
				$args['meta_query'][] = array(
					'key'     => '_wpss_starting_price',
					'value'   => (float) $max_price,
					'compare' => '<=',
					'type'    => 'DECIMAL',
				);
			}
		}

		$query    = new WP_Query( $args );
		$services = array();

		foreach ( $query->posts as $post ) {
			$services[] = $this->prepare_item_for_response( $post, $request )->get_data();
		}

		return $this->paginated_response(
			$services,
			$query->found_posts,
			$pagination['page'],
			$pagination['per_page']
		);
	}

	/**
	 * Get single service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$service    = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Service not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		// Only show published services publicly; authors and admins can see their own.
		if ( 'publish' !== $service->post_status ) {
			$current_user_id = get_current_user_id();
			if ( (int) $service->post_author !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'not_found',
					__( 'Service not found.', 'wp-sell-services' ),
					array( 'status' => 404 )
				);
			}
		}

		return $this->prepare_item_for_response( $service, $request );
	}

	/**
	 * Create service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		// Determine post status based on moderation setting.
		$post_status = ModerationService::is_enabled() ? 'pending' : 'publish';

		$service_data = array(
			'post_type'    => 'wpss_service',
			'post_title'   => sanitize_text_field( $request->get_param( 'title' ) ),
			'post_content' => wp_kses_post( $request->get_param( 'description' ) ),
			'post_excerpt' => sanitize_textarea_field( $request->get_param( 'excerpt' ) ?: '' ),
			'post_status'  => $post_status,
			'post_author'  => get_current_user_id(),
		);

		$service_id = wp_insert_post( $service_data, true );

		if ( is_wp_error( $service_id ) ) {
			return $service_id;
		}

		// Save meta.
		$this->save_service_meta( $service_id, $request );

		// Set categories.
		$categories = $request->get_param( 'categories' );
		if ( $categories ) {
			wp_set_object_terms( $service_id, array_map( 'intval', $categories ), 'wpss_service_category' );
		}

		// Set tags.
		$tags = $request->get_param( 'tags' );
		if ( $tags ) {
			wp_set_object_terms( $service_id, $tags, 'wpss_service_tag' );
		}

		$service = get_post( $service_id );

		/**
		 * Fires after a service is created via REST API.
		 *
		 * @param int             $service_id Service ID.
		 * @param WP_REST_Request $request    Request object.
		 */
		do_action( 'wpss_rest_service_created', $service_id, $request );

		return new WP_REST_Response(
			$this->prepare_item_for_response( $service, $request )->get_data(),
			201
		);
	}

	/**
	 * Update service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$service    = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Service not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$update_data = array( 'ID' => $service_id );

		if ( $request->has_param( 'title' ) ) {
			$update_data['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
		}

		if ( $request->has_param( 'description' ) ) {
			$update_data['post_content'] = wp_kses_post( $request->get_param( 'description' ) );
		}

		if ( $request->has_param( 'excerpt' ) ) {
			$update_data['post_excerpt'] = sanitize_textarea_field( $request->get_param( 'excerpt' ) );
		}

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta.
		$this->save_service_meta( $service_id, $request );

		// Update categories.
		if ( $request->has_param( 'categories' ) ) {
			wp_set_object_terms( $service_id, array_map( 'intval', $request->get_param( 'categories' ) ), 'wpss_service_category' );
		}

		// Update tags.
		if ( $request->has_param( 'tags' ) ) {
			wp_set_object_terms( $service_id, $request->get_param( 'tags' ), 'wpss_service_tag' );
		}

		/**
		 * Fires after a service is updated via REST API.
		 *
		 * @param int             $service_id Service ID.
		 * @param WP_REST_Request $request    Request object.
		 */
		do_action( 'wpss_rest_service_updated', $service_id, $request );

		return $this->prepare_item_for_response( get_post( $service_id ), $request );
	}

	/**
	 * Delete service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$service    = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Service not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$force = (bool) $request->get_param( 'force' );

		if ( $force ) {
			$result = wp_delete_post( $service_id, true );
		} else {
			$result = wp_trash_post( $service_id );
		}

		if ( ! $result ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete service.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a service is deleted via REST API.
		 *
		 * @param int  $service_id Service ID.
		 * @param bool $force      Whether permanently deleted.
		 */
		do_action( 'wpss_rest_service_deleted', $service_id, $force );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Get service packages.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_packages( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$packages   = get_post_meta( $service_id, '_wpss_packages', true );

		if ( ! is_array( $packages ) ) {
			$packages = array();
		}

		return new WP_REST_Response( $packages, 200 );
	}

	/**
	 * Get service FAQs.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_faqs( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$faqs       = get_post_meta( $service_id, '_wpss_faqs', true );

		if ( ! is_array( $faqs ) ) {
			$faqs = array();
		}

		return new WP_REST_Response( $faqs, 200 );
	}

	/**
	 * Get service reviews.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_reviews( $request ) {
		$service_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE service_id = %d AND status = 'approved' ORDER BY created_at DESC",
				$service_id
			),
			ARRAY_A
		);

		// Add reviewer info.
		foreach ( $reviews as &$review ) {
			$user               = get_user_by( 'id', $review['customer_id'] );
			$review['reviewer'] = array(
				'id'     => $review['customer_id'],
				'name'   => $user ? $user->display_name : 'Anonymous',
				'avatar' => get_avatar_url( $review['customer_id'], array( 'size' => 48 ) ),
			);
		}

		return new WP_REST_Response( $reviews, 200 );
	}

	/**
	 * Check create permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$perm_check = $this->check_permissions( $request );

		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		// Check if user can create services (Pro may restrict by subscription plan).
		/**
		 * Filter whether a vendor can create a new service.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $can_create Whether the vendor can create a service.
		 * @param int  $vendor_id  The vendor user ID.
		 */
		$can_create = apply_filters( 'wpss_vendor_can_create_service', true, get_current_user_id() );

		if ( ! $can_create ) {
			/**
			 * Filter the error message shown when a vendor cannot create more services.
			 *
			 * Pro uses this to inject a subscription upgrade link.
			 *
			 * @since 1.1.0
			 *
			 * @param string $message Default error message.
			 */
			$error_message = apply_filters(
				'wpss_service_limit_error_message',
				__( 'You have reached the maximum number of services allowed. Please remove an existing service before creating a new one.', 'wp-sell-services' )
			);

			return new WP_Error(
				'rest_forbidden',
				$error_message,
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'wpss_manage_services' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to create services.', 'wp-sell-services' ),
				array( 'status' => 403 )
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
	public function update_item_permissions_check( $request ) {
		$perm_check = $this->check_permissions( $request );

		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$service_id = (int) $request->get_param( 'id' );

		if ( ! $this->user_owns_resource( $service_id, 'service' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this service.', 'wp-sell-services' ),
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
	public function delete_item_permissions_check( $request ) {
		return $this->update_item_permissions_check( $request );
	}

	/**
	 * Prepare service for response.
	 *
	 * @param \WP_Post        $service Service post.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $service, $request ) {
		$data = array(
			'id'          => $service->ID,
			'title'       => $service->post_title,
			'slug'        => $service->post_name,
			'description' => $service->post_content,
			'excerpt'     => $service->post_excerpt,
			'status'      => $service->post_status,
			'link'        => get_permalink( $service->ID ),
			'vendor'      => array(
				'id'     => (int) $service->post_author,
				'name'   => get_the_author_meta( 'display_name', $service->post_author ),
				'avatar' => get_avatar_url( $service->post_author, array( 'size' => 96 ) ),
			),
			'pricing'     => array(
				'base_price' => (float) get_post_meta( $service->ID, '_wpss_starting_price', true ),
				'currency'   => wpss_get_currency(),
			),
			'delivery'    => array(
				'time'      => (int) get_post_meta( $service->ID, '_wpss_fastest_delivery', true ) ?: 7,
				'revisions' => (int) get_post_meta( $service->ID, '_wpss_max_revisions', true ),
			),
			'images'      => $this->get_service_images( $service->ID ),
			'categories'  => wp_get_object_terms( $service->ID, 'wpss_service_category', array( 'fields' => 'all' ) ),
			'tags'        => wp_get_object_terms( $service->ID, 'wpss_service_tag', array( 'fields' => 'names' ) ),
			'rating'      => $this->get_service_rating( $service->ID ),
			'created_at'  => $service->post_date_gmt,
			'updated_at'  => $service->post_modified_gmt,
		);

		/**
		 * Filter service REST response data.
		 *
		 * @param array           $data    Response data.
		 * @param \WP_Post        $service Service post.
		 * @param WP_REST_Request $request Request object.
		 */
		$data = apply_filters( 'wpss_rest_service_data', $data, $service, $request );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get service images.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	private function get_service_images( int $service_id ): array {
		$images = array();

		// Featured image.
		$thumbnail_id = get_post_thumbnail_id( $service_id );
		if ( $thumbnail_id ) {
			$images[] = array(
				'id'    => $thumbnail_id,
				'url'   => wp_get_attachment_url( $thumbnail_id ),
				'sizes' => array(
					'thumbnail' => wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ),
					'medium'    => wp_get_attachment_image_url( $thumbnail_id, 'medium' ),
					'large'     => wp_get_attachment_image_url( $thumbnail_id, 'large' ),
				),
			);
		}

		// Gallery images.
		$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true );
		$gallery_ids = wpss_get_gallery_ids( $gallery_raw );

		if ( ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $attachment_id ) {
				if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
					$images[] = array(
						'id'    => $attachment_id,
						'url'   => wp_get_attachment_url( $attachment_id ),
						'sizes' => array(
							'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
							'medium'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
							'large'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
						),
					);
				}
			}
		}

		return $images;
	}

	/**
	 * Get service rating.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	private function get_service_rating( int $service_id ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'wpss_reviews';
		$rating = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) as average, COUNT(*) as count FROM {$table} WHERE service_id = %d AND status = 'approved'",
				$service_id
			)
		);

		return array(
			'average' => $rating ? round( (float) $rating->average, 2 ) : 0,
			'count'   => $rating ? (int) $rating->count : 0,
		);
	}

	/**
	 * Save service meta from request.
	 *
	 * @param int             $service_id Service ID.
	 * @param WP_REST_Request $request    Request object.
	 * @return void
	 */
	private function save_service_meta( int $service_id, WP_REST_Request $request ): void {
		// Save packages (primary source of truth).
		if ( $request->has_param( 'packages' ) ) {
			$raw_packages = $request->get_param( 'packages' );
			$packages     = array();
			if ( is_array( $raw_packages ) ) {
				foreach ( $raw_packages as $pkg ) {
					$packages[] = array(
						'id'            => sanitize_key( $pkg['id'] ?? '' ),
						'name'          => sanitize_text_field( $pkg['name'] ?? '' ),
						'description'   => sanitize_textarea_field( $pkg['description'] ?? '' ),
						'price'         => (float) ( $pkg['price'] ?? 0 ),
						'delivery_days' => absint( $pkg['delivery_days'] ?? 7 ),
						'revisions'     => absint( $pkg['revisions'] ?? 0 ),
						'features'      => isset( $pkg['features'] ) && is_array( $pkg['features'] ) ? array_map( 'sanitize_text_field', $pkg['features'] ) : array(),
					);
				}
			}
			update_post_meta( $service_id, '_wpss_packages', $packages );

			// Compute and store derived values from packages.
			if ( is_array( $packages ) && ! empty( $packages ) ) {
				$prices        = array_filter( wp_list_pluck( $packages, 'price' ) );
				$delivery_days = array_filter( wp_list_pluck( $packages, 'delivery_days' ) );
				$revisions     = wp_list_pluck( $packages, 'revisions' );

				update_post_meta( $service_id, '_wpss_starting_price', ! empty( $prices ) ? min( $prices ) : 0 );
				update_post_meta( $service_id, '_wpss_fastest_delivery', ! empty( $delivery_days ) ? min( $delivery_days ) : 7 );
				update_post_meta( $service_id, '_wpss_max_revisions', ! empty( $revisions ) ? max( $revisions ) : 0 );
			}
		}

		if ( $request->has_param( 'requirements' ) ) {
			$raw_reqs     = $request->get_param( 'requirements' );
			$requirements = array();
			if ( is_array( $raw_reqs ) ) {
				foreach ( $raw_reqs as $req ) {
					$requirements[] = array(
						'field_type'  => sanitize_key( $req['field_type'] ?? 'text' ),
						'label'       => sanitize_text_field( $req['label'] ?? '' ),
						'description' => sanitize_textarea_field( $req['description'] ?? '' ),
						'required'    => ! empty( $req['required'] ),
						'options'     => isset( $req['options'] ) && is_array( $req['options'] ) ? array_map( 'sanitize_text_field', $req['options'] ) : array(),
					);
				}
			}
			update_post_meta( $service_id, '_wpss_requirements', $requirements );
		}
	}

	/**
	 * Get collection params.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'      => array(
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
			),
			'per_page'  => array(
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 10,
				'maximum'     => 100,
			),
			'category'  => array(
				'description' => __( 'Filter by category ID or slug.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'vendor'    => array(
				'description' => __( 'Filter by vendor ID.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
			'search'    => array(
				'description' => __( 'Search term.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'min_price' => array(
				'description' => __( 'Minimum price filter.', 'wp-sell-services' ),
				'type'        => 'number',
			),
			'max_price' => array(
				'description' => __( 'Maximum price filter.', 'wp-sell-services' ),
				'type'        => 'number',
			),
			'orderby'   => array(
				'description' => __( 'Order by field.', 'wp-sell-services' ),
				'type'        => 'string',
				'enum'        => array( 'date', 'title', 'price', 'rating' ),
				'default'     => 'date',
			),
			'order'     => array(
				'description' => __( 'Sort order.', 'wp-sell-services' ),
				'type'        => 'string',
				'enum'        => array( 'ASC', 'DESC' ),
				'default'     => 'DESC',
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
			'title'      => 'service',
			'type'       => 'object',
			'properties' => array_merge(
				$this->get_common_schema_properties(),
				array(
					'title'       => array(
						'description' => __( 'Service title.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'required'    => true,
					),
					'description' => array(
						'description' => __( 'Service description.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
					),
					'excerpt'     => array(
						'description' => __( 'Service excerpt.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
					),
					'base_price'  => array(
						'description' => __( 'Base price.', 'wp-sell-services' ),
						'type'        => 'number',
						'context'     => array( 'view', 'edit' ),
					),
					'categories'  => array(
						'description' => __( 'Category IDs.', 'wp-sell-services' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'context'     => array( 'view', 'edit' ),
					),
					'tags'        => array(
						'description' => __( 'Tags.', 'wp-sell-services' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'context'     => array( 'view', 'edit' ),
					),
				)
			),
		);
	}
}
