<?php
/**
 * REST API Manager
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\API;

/**
 * Registers and manages all REST API endpoints.
 *
 * @since 1.0.0
 */
class API {

	/**
	 * Registered controllers.
	 *
	 * @var array<RestController>
	 */
	private array $controllers = [];

	/**
	 * Initialize the API.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Add CORS headers for frontend apps.
		add_action( 'rest_api_init', [ $this, 'add_cors_headers' ] );

		// Filter REST response.
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_request' ], 10, 4 );
	}

	/**
	 * Register all API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$controllers = [
			new ServicesController(),
			new OrdersController(),
			new ReviewsController(),
			new VendorsController(),
			new ConversationsController(),
			new DisputesController(),
			new BuyerRequestsController(),
			new ProposalsController(),
			new NotificationsController(),
			new PortfolioController(),
			new EarningsController(),
			new ExtensionRequestsController(),
			new MilestonesController(),
			new TippingController(),
			new SellerLevelsController(),
			new ModerationController(),
			new FavoritesController(),
			new MediaController(),
			new CartController(),
			new AuthController(),
		];

		/**
		 * Filter registered API controllers.
		 *
		 * @param array<RestController> $controllers Array of controller instances.
		 */
		$this->controllers = apply_filters( 'wpss_api_controllers', $controllers );

		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}

		// Register generic endpoints.
		$this->register_generic_endpoints();
	}

	/**
	 * Register generic API endpoints.
	 *
	 * @return void
	 */
	private function register_generic_endpoints(): void {
		// Categories endpoint.
		register_rest_route(
			'wpss/v1',
			'/categories',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_categories' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'parent' => [
							'description' => __( 'Parent category ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						],
						'hide_empty' => [
							'description' => __( 'Hide empty categories.', 'wp-sell-services' ),
							'type'        => 'boolean',
							'default'     => true,
						],
					],
				],
			]
		);

		// Tags endpoint.
		register_rest_route(
			'wpss/v1',
			'/tags',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_tags' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'search' => [
							'description' => __( 'Search tags.', 'wp-sell-services' ),
							'type'        => 'string',
						],
					],
				],
			]
		);

		// Settings endpoint (public).
		register_rest_route(
			'wpss/v1',
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_public_settings' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		// Current user info.
		register_rest_route(
			'wpss/v1',
			'/me',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_current_user' ],
					'permission_callback' => 'is_user_logged_in',
				],
			]
		);

		// Dashboard stats (for vendors).
		register_rest_route(
			'wpss/v1',
			'/dashboard',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_dashboard' ],
					'permission_callback' => 'is_user_logged_in',
				],
			]
		);

		// Batch endpoint for mobile apps.
		register_rest_route(
			'wpss/v1',
			'/batch',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_batch' ],
					'permission_callback' => 'is_user_logged_in',
					'args'                => [
						'requests' => [
							'description' => __( 'Array of sub-requests.', 'wp-sell-services' ),
							'type'        => 'array',
							'required'    => true,
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'method' => [ 'type' => 'string', 'enum' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ] ],
									'path'   => [ 'type' => 'string' ],
									'body'   => [ 'type' => 'object' ],
								],
							],
						],
					],
				],
			]
		);

		// Search endpoint.
		register_rest_route(
			'wpss/v1',
			'/search',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'search' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'q' => [
							'description' => __( 'Search query.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						],
						'type' => [
							'description' => __( 'Search type.', 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => 'all',
							'enum'        => [ 'all', 'services', 'vendors' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Get service categories.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_categories( \WP_REST_Request $request ): \WP_REST_Response {
		$parent     = (int) $request->get_param( 'parent' );
		$hide_empty = (bool) $request->get_param( 'hide_empty' );

		$terms = get_terms(
			[
				'taxonomy'   => 'wpss_service_category',
				'parent'     => $parent,
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) ) {
			return new \WP_REST_Response( [] );
		}

		$data = [];
		foreach ( $terms as $term ) {
			$icon  = get_term_meta( $term->term_id, '_wpss_icon', true );
			$image = get_term_meta( $term->term_id, '_wpss_image', true );

			$data[] = [
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'count'       => $term->count,
				'parent'      => $term->parent,
				'icon'        => $icon ?: '',
				'image'       => $image ? wp_get_attachment_url( $image ) : '',
			];
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Get service tags.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_tags( \WP_REST_Request $request ): \WP_REST_Response {
		$search = $request->get_param( 'search' );

		$args = [
			'taxonomy'   => 'wpss_service_tag',
			'hide_empty' => false,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 50,
		];

		if ( $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return new \WP_REST_Response( [] );
		}

		$data = [];
		foreach ( $terms as $term ) {
			$data[] = [
				'id'    => $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			];
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Get public settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_public_settings(): \WP_REST_Response {
		$settings = [
			'currency'           => get_option( 'wpss_currency', 'USD' ),
			'currency_symbol'    => wpss_get_currency_symbol(),
			'currency_position'  => get_option( 'wpss_currency_position', 'before' ),
			'decimal_places'     => (int) get_option( 'wpss_decimal_places', 2 ),
			'min_order_amount'   => (float) get_option( 'wpss_min_order_amount', 5 ),
			'max_order_amount'   => (float) get_option( 'wpss_max_order_amount', 10000 ),
			'vendor_registration' => (bool) get_option( 'wpss_vendor_registration', true ),
			'service_moderation' => (bool) get_option( 'wpss_service_moderation', false ),
			'review_moderation'  => (bool) get_option( 'wpss_review_moderation', false ),
			'max_file_size'      => (int) get_option( 'wpss_max_file_size', 10 ) * 1024 * 1024, // MB to bytes.
			'allowed_file_types' => explode( ',', get_option( 'wpss_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,zip' ) ),
			'pages'              => [
				'services'    => (int) get_option( 'wpss_services_page' ),
				'vendors'     => (int) get_option( 'wpss_vendors_page' ),
				'dashboard'   => (int) get_option( 'wpss_dashboard_page' ),
				'checkout'    => (int) get_option( 'wpss_checkout_page' ),
				'terms'       => (int) get_option( 'wpss_terms_page' ),
			],
		];

		/**
		 * Filter public API settings.
		 *
		 * @param array $settings Settings array.
		 */
		return new \WP_REST_Response( apply_filters( 'wpss_api_public_settings', $settings ) );
	}

	/**
	 * Get current user info.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_current_user(): \WP_REST_Response {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		$data = [
			'id'           => $user_id,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'avatar'       => get_avatar_url( $user_id, [ 'size' => 96 ] ),
			'is_vendor'    => (bool) get_user_meta( $user_id, '_wpss_is_vendor', true ),
			'is_admin'     => current_user_can( 'manage_options' ),
			'capabilities' => [
				'can_create_services' => current_user_can( 'publish_posts' ) && get_user_meta( $user_id, '_wpss_is_vendor', true ),
				'can_manage_orders'   => current_user_can( 'manage_options' ),
			],
		];

		if ( $data['is_vendor'] ) {
			$data['vendor_status'] = get_user_meta( $user_id, '_wpss_vendor_status', true ) ?: 'approved';
			$data['rating']        = (float) get_user_meta( $user_id, '_wpss_rating_average', true ) ?: 0;
			$data['review_count']  = (int) get_user_meta( $user_id, '_wpss_rating_count', true ) ?: 0;
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Get dashboard data.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard(): \WP_REST_Response {
		global $wpdb;

		$user_id   = get_current_user_id();
		$is_vendor = get_user_meta( $user_id, '_wpss_is_vendor', true );

		$orders_table = $wpdb->prefix . 'wpss_orders';

		$data = [
			'user_id'   => $user_id,
			'is_vendor' => (bool) $is_vendor,
		];

		// Customer stats (orders placed).
		$customer_orders = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN status IN ('pending', 'accepted', 'in_progress', 'delivered') THEN 1 ELSE 0 END) as active
				FROM {$orders_table}
				WHERE customer_id = %d",
				$user_id
			)
		);

		$data['as_customer'] = [
			'total_orders'     => (int) $customer_orders->total,
			'active_orders'    => (int) $customer_orders->active,
			'completed_orders' => (int) $customer_orders->completed,
		];

		// Vendor stats.
		if ( $is_vendor ) {
			$vendor_orders = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
						SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
						SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
						SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as earnings
					FROM {$orders_table}
					WHERE vendor_id = %d",
					$user_id
				)
			);

			$services_count = count(
				get_posts(
					[
						'post_type'      => 'wpss_service',
						'author'         => $user_id,
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'post_status'    => 'any',
					]
				)
			);

			$data['as_vendor'] = [
				'services_count'   => $services_count,
				'total_orders'     => (int) $vendor_orders->total,
				'pending_orders'   => (int) $vendor_orders->pending,
				'active_orders'    => (int) $vendor_orders->in_progress,
				'completed_orders' => (int) $vendor_orders->completed,
				'total_earnings'   => (float) $vendor_orders->earnings,
				'rating'           => (float) get_user_meta( $user_id, '_wpss_rating_average', true ) ?: 0,
				'review_count'     => (int) get_user_meta( $user_id, '_wpss_rating_count', true ) ?: 0,
			];

			// Recent orders needing action.
			$pending_orders = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, order_number, service_id, total, currency, created_at
					FROM {$orders_table}
					WHERE vendor_id = %d AND status = 'pending'
					ORDER BY created_at DESC
					LIMIT 5",
					$user_id
				)
			);

			$data['pending_orders'] = array_map(
				function ( $order ) {
					$service = get_post( $order->service_id );
					return [
						'id'           => (int) $order->id,
						'order_number' => $order->order_number,
						'service'      => $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ),
						'total'        => wpss_format_currency( (float) $order->total, $order->currency ),
						'created_at'   => $order->created_at,
					];
				},
				$pending_orders
			);
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Search services and vendors.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function search( \WP_REST_Request $request ): \WP_REST_Response {
		$query = sanitize_text_field( $request->get_param( 'q' ) );
		$type  = $request->get_param( 'type' );

		$results = [
			'query' => $query,
		];

		// Search services.
		if ( 'all' === $type || 'services' === $type ) {
			$services_query = new \WP_Query(
				[
					'post_type'      => 'wpss_service',
					'post_status'    => 'publish',
					's'              => $query,
					'posts_per_page' => 10,
				]
			);

			$services = [];
			foreach ( $services_query->posts as $post ) {
				$services[] = [
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'slug'      => $post->post_name,
					'thumbnail' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
					'price'     => wpss_format_currency( (float) get_post_meta( $post->ID, '_wpss_starting_price', true ) ),
					'rating'    => (float) get_post_meta( $post->ID, '_wpss_rating_average', true ) ?: 0,
					'url'       => get_permalink( $post->ID ),
				];
			}

			$results['services'] = $services;
		}

		// Search vendors.
		if ( 'all' === $type || 'vendors' === $type ) {
			$vendors_query = new \WP_User_Query(
				[
					'meta_key'       => '_wpss_is_vendor',
					'meta_value'     => '1',
					'search'         => '*' . $query . '*',
					'search_columns' => [ 'user_login', 'display_name', 'user_nicename' ],
					'number'         => 10,
				]
			);

			$vendors = [];
			foreach ( $vendors_query->get_results() as $user ) {
				$vendors[] = [
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'avatar'       => get_avatar_url( $user->ID, [ 'size' => 48 ] ),
					'tagline'      => get_user_meta( $user->ID, '_wpss_vendor_tagline', true ) ?: '',
					'rating'       => (float) get_user_meta( $user->ID, '_wpss_rating_average', true ) ?: 0,
					'url'          => home_url( '/vendor/' . $user->user_nicename ),
				];
			}

			$results['vendors'] = $vendors;
		}

		return new \WP_REST_Response( $results );
	}

	/**
	 * Handle batch requests for mobile efficiency.
	 *
	 * Accepts an array of sub-requests and executes them internally,
	 * returning all responses in a single HTTP response.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_batch( \WP_REST_Request $request ): \WP_REST_Response {
		$requests  = $request->get_param( 'requests' );
		$responses = [];
		$server    = rest_get_server();

		$max_requests = apply_filters( 'wpss_batch_max_requests', 25 );

		if ( count( $requests ) > $max_requests ) {
			return new \WP_REST_Response(
				[
					'code'    => 'batch_limit_exceeded',
					'message' => sprintf(
						/* translators: %d: maximum number of batch requests */
						__( 'Batch requests limited to %d operations.', 'wp-sell-services' ),
						$max_requests
					),
				],
				400
			);
		}

		foreach ( $requests as $index => $sub ) {
			$method = strtoupper( $sub['method'] ?? 'GET' );
			$path   = $sub['path'] ?? '';
			$body   = $sub['body'] ?? [];

			// Only allow requests within our namespace.
			if ( ! str_starts_with( $path, '/wpss/v1/' ) ) {
				$responses[] = [
					'status' => 400,
					'body'   => [ 'code' => 'invalid_path', 'message' => __( 'Path must start with /wpss/v1/', 'wp-sell-services' ) ],
				];
				continue;
			}

			$sub_request = new \WP_REST_Request( $method, $path );

			if ( ! empty( $body ) ) {
				foreach ( $body as $key => $value ) {
					$sub_request->set_param( $key, $value );
				}
			}

			// Inherit auth from parent request.
			$sub_request->set_header( 'Authorization', $request->get_header( 'authorization' ) );

			$result = $server->dispatch( $sub_request );

			$responses[] = [
				'status' => $result->get_status(),
				'body'   => $result->get_data(),
			];
		}

		return new \WP_REST_Response( [ 'responses' => $responses ] );
	}

	/**
	 * Add CORS headers.
	 *
	 * @return void
	 */
	public function add_cors_headers(): void {
		// Only apply to our namespace.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( strpos( $request_uri, '/wp-json/wpss/' ) === false ) {
			return;
		}

		/**
		 * Filter allowed CORS origins.
		 *
		 * @param array $origins Allowed origins.
		 */
		$allowed_origins = apply_filters( 'wpss_api_cors_origins', [ home_url() ] );

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		if ( in_array( $origin, $allowed_origins, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
		}
	}

	/**
	 * Handle preflight requests.
	 *
	 * @param bool              $served  Whether the request was served.
	 * @param \WP_REST_Response $result  Response object.
	 * @param \WP_REST_Request  $request Request object.
	 * @param \WP_REST_Server   $server  Server object.
	 * @return bool
	 */
	public function serve_request( $served, $result, $request, $server ): bool {
		if ( 'OPTIONS' === $request->get_method() ) {
			$response = new \WP_REST_Response( null, 200 );
			$server->send_headers( $response );
			return true;
		}

		return $served;
	}
}
