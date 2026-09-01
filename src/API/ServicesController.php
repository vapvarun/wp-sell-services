<?php
/**
 * Services REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;
use WPSellServices\Services\ModerationService;
use WPSellServices\Models\Review;

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

		// GET /services/grid - Server-rendered services grid for the services
		// block (cards + pagination HTML). Public read; mirrors the legacy
		// wpss_load_services admin-ajax response shape so the block's
		// progressive enhancement (filters / pagination) works without
		// admin-ajax. Rendering stays server-side so the card template's
		// extension hooks + theme overrides are preserved.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/grid',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_grid' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page'         => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'postsPerPage' => array(
							'type'              => 'integer',
							'default'           => 12,
							'minimum'           => 1,
							'maximum'           => 48,
							'sanitize_callback' => 'absint',
						),
						'orderBy'      => array(
							'type'              => 'string',
							'default'           => 'date',
							'sanitize_callback' => 'sanitize_key',
						),
						'order'        => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
						'category'     => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /services/limits - what one service may contain.
		//
		// The app needs these before it can render a create-service screen: how
		// many gallery images the vendor may add, how many packages, add-ons,
		// FAQs and buyer requirements. They were previously reachable only as a
		// template var inside the web wizard, so a client had to discover each
		// ceiling by being rejected. Public read - a limit is not a secret, and
		// the web wizard already prints the same numbers to anyone loading it.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/limits',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_limits' ),
					'permission_callback' => '__return_true',
				),
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
					'args'                => array_merge(
						$this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
						array(
							'status' => array(
								'description' => __( 'Vendor-controllable publish state.', 'wp-sell-services' ),
								'type'        => 'string',
								'enum'        => array( 'publish', 'draft' ),
							),
						)
					),
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
					'args'                => array(
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
					),
				),
			)
		);

		// GET /services/{id}/addons - List addons.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/addons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_addons' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /services/{id}/addons - Create addon (vendor only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/addons',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_addon' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'title'       => array(
							'description' => __( 'Addon title.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'description' => array(
							'description' => __( 'Addon description.', 'wp-sell-services' ),
							'type'        => 'string',
						),
						'price'       => array(
							'description' => __( 'Addon price.', 'wp-sell-services' ),
							'type'        => 'number',
							'required'    => true,
						),
					),
				),
			)
		);

		// PUT /services/{id}/addons/{addon_id} - Update addon.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/addons/(?P<addon_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_addon' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'title'       => array(
							'description' => __( 'Addon title.', 'wp-sell-services' ),
							'type'        => 'string',
						),
						'description' => array(
							'description' => __( 'Addon description.', 'wp-sell-services' ),
							'type'        => 'string',
						),
						'price'       => array(
							'description' => __( 'Addon price.', 'wp-sell-services' ),
							'type'        => 'number',
						),
					),
				),
			)
		);

		// DELETE /services/{id}/addons/{addon_id} - Delete addon.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/addons/(?P<addon_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_addon' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
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
		$search = $request->get_param( 'search' ) ?: $request->get_param( 'q' );
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

		// Max delivery days filter.
		$max_delivery_days = $request->get_param( 'max_delivery_days' );
		if ( $max_delivery_days ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}
			$args['meta_query'][] = array(
				'key'     => '_wpss_fastest_delivery',
				'value'   => (int) $max_delivery_days,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		// Minimum rating filter.
		$min_rating = $request->get_param( 'min_rating' );
		if ( $min_rating ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}
			$args['meta_query'][] = array(
				'key'     => '_wpss_rating_average',
				'value'   => (float) $min_rating,
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
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
	 * Get the server-rendered services grid (cards + pagination HTML).
	 *
	 * Powers the services block's progressive enhancement (filters /
	 * pagination) over REST instead of admin-ajax. Returns the same
	 * { html, pagination } shape the legacy wpss_load_services handler
	 * produced. Rendering is delegated to wpss_render_services_grid() so the
	 * card template + extension hooks + theme overrides are identical to a
	 * first server-side paint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_grid( $request ): WP_REST_Response {
		$attributes = array(
			'postsPerPage' => (int) $request->get_param( 'postsPerPage' ),
			'orderBy'      => (string) $request->get_param( 'orderBy' ),
			'order'        => (string) $request->get_param( 'order' ),
			'category'     => (int) $request->get_param( 'category' ),
		);

		// Pagination links should point back at the page the block lives on,
		// not at the REST URL. Use the request Referer when present, falling
		// back to the services archive.
		$referer  = (string) $request->get_header( 'referer' );
		$base_url = ( '' !== $referer ) ? esc_url_raw( $referer ) : get_post_type_archive_link( 'wpss_service' );

		$grid = wpss_render_services_grid( $attributes, (int) $request->get_param( 'page' ), (string) $base_url );

		return new WP_REST_Response(
			array(
				'html'       => $grid['html'],
				'pagination' => $grid['pagination'],
				'total'      => $grid['total'],
				'pages'      => $grid['pages'],
			)
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

		// The detail response is the LIST shape plus the fields a service page
		// needs. Both routes share prepare_item_for_response(), which is exactly
		// why /services/{id} returned a payload byte-identical to a list item:
		// 16 keys either way, nothing a client could not already see.
		//
		// Enriched here rather than inside the shared method on purpose. Adding
		// packages, add-ons and requirements to prepare_item_for_response()
		// would put them on every card in a grid too, undoing the query work in
		// 1.6.0 that took a services grid from 70 queries to 30.
		$response = $this->prepare_item_for_response( $service, $request );
		$response->set_data( array_merge( $response->get_data(), $this->get_detail_fields( $service ) ) );

		return $response;
	}

	/**
	 * Fields that belong on a single service, not on a card in a grid.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_Post $service Service post.
	 * @return array<string, mixed>
	 */
	private function get_detail_fields( \WP_Post $service ): array {
		$service_id = (int) $service->ID;

		// Packages carry the STABLE id introduced in 1.6.0, not the array index.
		// A client that stores an index sends the wrong tier the moment a vendor
		// reorders their packages, which is the bug that release repaired on ten
		// live orders.
		$packages = function_exists( 'wpss_get_service_packages' )
			? (array) wpss_get_service_packages( $service_id )
			: array();

		return array(
			'content'        => apply_filters( 'the_content', $service->post_content ),
			'packages'       => array_values( $packages ),
			'extras'         => function_exists( 'wpss_get_service_extras' )
				? array_values( (array) wpss_get_service_extras( $service_id ) )
				: array(),
			'requirements'   => function_exists( 'wpss_get_service_requirements' )
				? array_values( (array) wpss_get_service_requirements( $service_id ) )
				: array(),
			// The full vendor profile, not the compact actor shape already on the
			// card. A service page shows the seller's tagline, rating and
			// completed orders; without this a client had to make a second call
			// to /vendors/{id} to render the page it just fetched.
			'vendor_profile' => $this->get_vendor_profile( (int) $service->post_author ),
		);
	}

	/**
	 * Public vendor profile for a service detail response.
	 *
	 * @since 1.6.0
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array<string, mixed>|null
	 */
	private function get_vendor_profile( int $vendor_id ): ?array {
		if ( $vendor_id <= 0 || ! function_exists( 'wpss_get_vendor' ) ) {
			return null;
		}

		$profile = wpss_get_vendor( $vendor_id );

		return array(
			'id'               => $vendor_id,
			'tagline'          => $profile ? (string) $profile->title : '',
			'bio'              => $profile ? (string) $profile->bio : '',
			'country'          => $profile ? (string) $profile->country : '',
			'is_verified'      => $profile ? (bool) $profile->is_verified : false,
			'completed_orders' => $profile ? (int) $profile->orders_completed : 0,
			'rating_average'   => (float) get_user_meta( $vendor_id, '_wpss_rating_average', true ),
			'rating_count'     => (int) get_user_meta( $vendor_id, '_wpss_rating_count', true ),
			'response_time'    => (string) ( get_user_meta( $vendor_id, '_wpss_vendor_response_time', true ) ?: '' ),
		);
	}

	/**
	 * Resolve requested categories to term IDs.
	 *
	 * Accepts term IDs or slugs, because /categories hands the client both and
	 * nothing said which one to send back. The previous intval() turned every
	 * slug into 0 and assigned nothing at all — the service saved "successfully"
	 * and came back uncategorised, which is the worst of both outcomes.
	 * Unknown terms are dropped rather than created: taxonomy is the site
	 * owner's, and an API that invents categories fills the catalog with
	 * one-off duplicates.
	 *
	 * @since 1.4.0
	 *
	 * @param mixed $categories Term IDs or slugs.
	 * @return int[] Term IDs that exist.
	 */
	private function resolve_category_terms( $categories ): array {
		if ( ! is_array( $categories ) ) {
			$categories = ( null === $categories || '' === $categories ) ? array() : array( $categories );
		}

		$term_ids = array();

		foreach ( $categories as $category ) {
			if ( is_numeric( $category ) ) {
				$term = get_term( (int) $category, 'wpss_service_category' );
			} else {
				$term = get_term_by( 'slug', sanitize_title( (string) $category ), 'wpss_service_category' );
			}

			if ( $term instanceof \WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Create service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		// The web wizard refuses to publish a service without a category, and
		// this endpoint must refuse for the same reason: an uncategorised
		// service is invisible to category browse, so it is published and
		// unfindable at the same time. Letting the app create one produced
		// exactly the "categories are empty, discovery is broken" catalogue
		// the web form was written to prevent.
		$requested_categories = $this->resolve_category_terms( $request->get_param( 'categories' ) );

		if ( empty( $requested_categories ) ) {
			return new WP_Error(
				'wpss_category_required',
				__( 'Select at least one category. A service without one cannot be found by buyers browsing the catalog.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

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

		wp_set_object_terms( $service_id, $requested_categories, 'wpss_service_category' );

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

		// Status toggle (publish <-> draft) - the REST twin of the legacy
		// wpss_update_service_status AJAX handler the dashboard "pause/publish"
		// control used. Restricted to the two vendor-controllable states; any
		// other value is rejected so the route cannot be used to force a
		// rejected/pending service live.
		if ( $request->has_param( 'status' ) ) {
			$requested_status = sanitize_key( (string) $request->get_param( 'status' ) );
			if ( ! in_array( $requested_status, array( 'publish', 'draft' ), true ) ) {
				return new WP_Error(
					'invalid_status',
					__( 'Status must be either publish or draft.', 'wp-sell-services' ),
					array( 'status' => 400 )
				);
			}
			$update_data['post_status'] = $requested_status;
		}

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta.
		$this->save_service_meta( $service_id, $request );

		// Update categories.
		if ( $request->has_param( 'categories' ) ) {
			wp_set_object_terms( $service_id, $this->resolve_category_terms( $request->get_param( 'categories' ) ), 'wpss_service_category' );
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

		// Publish a STABLE id with every package.
		//
		// This response carried no id at all, while POST /cart/add required
		// `package_id` - so every client had to send the array INDEX and inherit
		// its instability: reorder the tiers and saved carts, deep links and
		// order history all repoint at a different package (Basecamp
		// #10154919857).
		//
		// Assigning here rather than only in the backfill means a service always
		// answers with ids the moment a client asks for them, including services
		// created after the upgrade pass ran. It writes only when an id is
		// missing, so a normal request does no work.
		$packages = wpss_assign_package_ids( $service_id );

		return new WP_REST_Response( array_values( $packages ), 200 );
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
		$pagination = $this->get_pagination_args( $request );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE service_id = %d AND status = 'approved'",
				$service_id
			)
		);

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE service_id = %d AND status = 'approved' ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$service_id,
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);

		// Add reviewer info. Migrated guest reviews (customer_id = 0) carry the
		// original author name in reviewer_name; resolve through the shared
		// helper so the API never returns "Anonymous" for a known name.
		foreach ( $reviews as &$review ) {
			$review['reviewer']   = array(
				'id'     => (int) $review['customer_id'],
				'name'   => Review::resolve_reviewer_name( (int) $review['customer_id'], $review['reviewer_name'] ?? null ),
				'avatar' => get_avatar_url( $review['customer_id'], array( 'size' => 48 ) ),
			);
			$review['created_at'] = $this->format_datetime( $review['created_at'] ?? null );
		}

		return $this->paginated_response( $reviews, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get service addons.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_addons( $request ) {
		$service_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_service_addons';

		$addons = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE service_id = %d ORDER BY id ASC",
				$service_id
			)
		);

		$data = array();
		foreach ( $addons as $addon ) {
			// Addons have no currency column - they are always priced in the
			// store currency, so the helper's default is the right one.
			$data[] = array_merge(
				array(
					'id'          => (int) $addon->id,
					'service_id'  => (int) $addon->service_id,
					'title'       => $addon->title,
					'description' => $addon->description ?? '',
				),
				wpss_rest_money( 'price', (float) $addon->price )
			);
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create a service addon.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_addon( $request ) {
		$service_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_service_addons';

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'service_id'  => $service_id,
				'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
				'description' => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
				'price'       => (float) $request->get_param( 'price' ),
			),
			array( '%d', '%s', '%s', '%f' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'addon_create_failed', __( 'Failed to create addon.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array_merge(
				array(
					'id'          => (int) $wpdb->insert_id,
					'service_id'  => $service_id,
					'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
					'description' => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
				),
				wpss_rest_money( 'price', (float) $request->get_param( 'price' ) )
			),
			201
		);
	}

	/**
	 * Update a service addon.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_addon( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$addon_id   = (int) $request->get_param( 'addon_id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_service_addons';

		// Verify addon belongs to service.
		$addon = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND service_id = %d",
				$addon_id,
				$service_id
			)
		);

		if ( ! $addon ) {
			return new WP_Error( 'addon_not_found', __( 'Addon not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$updates = array();
		$formats = array();

		if ( $request->has_param( 'title' ) ) {
			$updates['title'] = sanitize_text_field( $request->get_param( 'title' ) );
			$formats[]        = '%s';
		}

		if ( $request->has_param( 'description' ) ) {
			$updates['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
			$formats[]              = '%s';
		}

		if ( $request->has_param( 'price' ) ) {
			$updates['price'] = (float) $request->get_param( 'price' );
			$formats[]        = '%f';
		}

		if ( ! empty( $updates ) ) {
			$wpdb->update( $table, $updates, array( 'id' => $addon_id ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// Return updated addon.
		$updated = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$addon_id
			)
		);

		return new WP_REST_Response(
			array_merge(
				array(
					'id'          => (int) $updated->id,
					'service_id'  => (int) $updated->service_id,
					'title'       => $updated->title,
					'description' => $updated->description ?? '',
				),
				wpss_rest_money( 'price', (float) $updated->price )
			)
		);
	}

	/**
	 * Delete a service addon.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_addon( $request ) {
		$service_id = (int) $request->get_param( 'id' );
		$addon_id   = (int) $request->get_param( 'addon_id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_service_addons';

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'id'         => $addon_id,
				'service_id' => $service_id,
			),
			array( '%d', '%d' )
		);

		if ( ! $deleted ) {
			return new WP_Error( 'addon_not_found', __( 'Addon not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ) );
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

		// A suspended, pending or rejected vendor cannot list new work. The web
		// wizard has always enforced this; this route did not, so the rule was
		// reachable around by anyone holding an Application Password.
		$status_block = wpss_vendor_status_block();

		if ( $status_block ) {
			return $status_block;
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

			// Its own code: the caller is permitted, they have simply hit their
			// plan's service limit, and a client should offer "remove one" rather
			// than "you are not allowed".
			return new WP_Error(
				'wpss_service_limit_reached',
				$error_message,
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'wpss_manage_services' ) ) {
			return new WP_Error(
				'wpss_cannot_create',
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
				'wpss_not_owner',
				__( 'You do not have permission to edit this service.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		// Editing is supply too: a suspended vendor re-pricing or rewriting a
		// listing is the same act as publishing one. Ownership is checked
		// first so the caller learns "not yours" before "not active", and
		// administrators pass both (they edit on a vendor's behalf).
		$status_block = wpss_vendor_status_block();

		if ( $status_block ) {
			return $status_block;
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
		// Delegating means delete inherits the vendor-status gate, which is the
		// behaviour we want: a vendor suspended for abuse should not be able to
		// clear their listings out from under a moderator who is still looking
		// at them. Administrators are exempt, so the owner can always remove
		// content on their behalf.
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
		/*
		 * This shape used to be written out here, which is how /favorites came to
		 * return a different one: the pieces it needed were private methods on
		 * this class, so it invented a `thumbnail` string and a flat `price`
		 * instead. The definition now lives in wpss_rest_service_card() and both
		 * endpoints read it, which is the point of Basecamp 10154919636 - a client
		 * should be able to write ONE renderer for "a service card".
		 *
		 * Byte-for-byte the same keys this endpoint already returned.
		 */
		$data = wpss_rest_service_card( $service );

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
				// Both delivery meta keys are kept in sync so meta-query
				// filters (archive + REST) match services from either path.
				$fastest_delivery = ! empty( $delivery_days ) ? min( $delivery_days ) : 7;
				update_post_meta( $service_id, '_wpss_fastest_delivery', $fastest_delivery );
				update_post_meta( $service_id, '_wpss_delivery_days', $fastest_delivery );
				// Both revision meta keys are kept in sync so the wizard key
				// and the admin/REST key agree regardless of creation path.
				$max_revisions = ! empty( $revisions ) ? max( $revisions ) : 0;
				update_post_meta( $service_id, '_wpss_max_revisions', $max_revisions );
				update_post_meta( $service_id, '_wpss_revisions', $max_revisions );
			}
		}

		// Save gallery (array of media IDs).
		if ( $request->has_param( 'gallery' ) ) {
			$gallery_ids = array_map( 'absint', (array) $request->get_param( 'gallery' ) );
			update_post_meta( $service_id, '_wpss_gallery', $gallery_ids );
		}

		// Save addons (array of addon objects).
		if ( $request->has_param( 'addons' ) ) {
			$raw_addons = $request->get_param( 'addons' );
			if ( is_array( $raw_addons ) ) {
				global $wpdb;
				$addons_table = $wpdb->prefix . 'wpss_service_addons';

				foreach ( $raw_addons as $addon_data ) {
					$addon_insert = array(
						'service_id'  => $service_id,
						'title'       => sanitize_text_field( $addon_data['title'] ?? '' ),
						'description' => sanitize_textarea_field( $addon_data['description'] ?? '' ),
						'price'       => (float) ( $addon_data['price'] ?? 0 ),
					);

					if ( ! empty( $addon_data['id'] ) ) {
						// Update existing addon.
						$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$addons_table,
							$addon_insert,
							array(
								'id'         => (int) $addon_data['id'],
								'service_id' => $service_id,
							),
							array( '%d', '%s', '%s', '%f' ),
							array( '%d', '%d' )
						);
					} else {
						// Insert new addon.
						$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
							$addons_table,
							$addon_insert,
							array( '%d', '%s', '%s', '%f' )
						);
					}
				}
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
			'page'              => array(
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
			),
			'per_page'          => array(
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 10,
				'maximum'     => 100,
			),
			'category'          => array(
				'description' => __( 'Filter by category ID or slug.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'vendor'            => array(
				'description' => __( 'Filter by vendor ID.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
			'search'            => array(
				'description' => __( 'Search term.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			// /search names this same thing `q`. Rather than have one endpoint
			// silently ignore the other's parameter name — which returns 200
			// and the whole unfiltered catalogue, so it reads as "search
			// matched everything" — both names work on both endpoints.
			'q'                 => array(
				'description' => __( 'Search term. Alias of `search`, accepted for parity with /search.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'min_price'         => array(
				'description' => __( 'Minimum price filter.', 'wp-sell-services' ),
				'type'        => 'number',
			),
			'max_price'         => array(
				'description' => __( 'Maximum price filter.', 'wp-sell-services' ),
				'type'        => 'number',
			),
			'orderby'           => array(
				'description' => __( 'Order by field.', 'wp-sell-services' ),
				'type'        => 'string',
				'enum'        => array( 'date', 'title', 'price', 'rating' ),
				'default'     => 'date',
			),
			'order'             => array(
				'description' => __( 'Sort order.', 'wp-sell-services' ),
				'type'        => 'string',
				'enum'        => self::sort_directions(),
				'default'     => self::SORT_DESC,
			),
			'max_delivery_days' => array(
				'description' => __( 'Maximum delivery days filter.', 'wp-sell-services' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'min_rating'        => array(
				'description' => __( 'Minimum rating filter (0-5).', 'wp-sell-services' ),
				'type'        => 'number',
				'minimum'     => 0,
				'maximum'     => 5,
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
						// IDs or slugs. /categories hands the client both and
						// never said which to send back, so rejecting slugs
						// here meant the API refused half of what it published.
						'description' => __( 'Category IDs or slugs.', 'wp-sell-services' ),
						'type'        => 'array',
						'items'       => array( 'type' => array( 'integer', 'string' ) ),
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

	/**
	 * Return the limits that govern how much one service may contain.
	 *
	 * Reads the same wpss_get_service_limits() the web wizard reads, so the two
	 * surfaces cannot drift. -1 means unlimited.
	 *
	 * @since 1.7.1
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Limits keyed by limit name.
	 */
	public function get_limits( $request ) {
		unset( $request );

		return rest_ensure_response( wpss_get_service_limits() );
	}
}
