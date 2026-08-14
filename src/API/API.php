<?php
/**
 * REST API Manager
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

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
	/**
	 * NOT CALLED. Kept for reference, and as a record of a real gap.
	 *
	 * Core\Plugin::define_api_hooks() constructs this class and hooks
	 * register_routes() directly, so this method has never run on any install.
	 * That means add_cors_headers() and the rest_pre_serve_request filter below
	 * have never been applied either - the CORS support this plugin advertises
	 * for frontend apps does not exist at runtime.
	 *
	 * Do not simply call this to "fix" it: switching CORS on for every REST
	 * response is a behaviour change that needs its own release and testing,
	 * not a side effect of an unrelated bug fix. The 405 handler is wired from
	 * define_api_hooks() for that reason.
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
	 * Turn "route not found" into "method not allowed" where that is the truth.
	 *
	 * WordPress answers a wrong-method request with `404 rest_no_route`, because
	 * its dispatcher matches on path AND method together - so `DELETE /services`
	 * looks identical to a path that does not exist. A client cannot tell "this
	 * endpoint is gone" from "I used the wrong verb", and retrying or
	 * re-authenticating never helps.
	 *
	 * Scoped to our own namespace: this inspects the route table only after core
	 * has already decided it has nothing, and only rewrites the answer when the
	 * same path IS registered under other methods. Anything genuinely unknown
	 * keeps its 404, and no other plugin's routes are touched.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_HTTP_Response $result  Response to send.
	 * @param \WP_REST_Server   $server  Server instance.
	 * @param \WP_REST_Request  $request Request used to generate the response.
	 * @return \WP_HTTP_Response
	 */
	public function clarify_method_not_allowed( $result, $server, $request ) {
		if ( ! $result instanceof \WP_REST_Response || 404 !== $result->get_status() ) {
			return $result;
		}

		$data = $result->get_data();

		if ( ! is_array( $data ) || 'rest_no_route' !== ( $data['code'] ?? '' ) ) {
			return $result;
		}

		$path = $request->get_route();

		// Both namespaces: Pro registers four cart-adapter routes under
		// wpss-pro/v1, and a wrong verb on those deserves the same 405 as
		// anything under wpss/v1.
		if ( 0 !== strpos( $path, '/wpss/v1' ) && 0 !== strpos( $path, '/wpss-pro/v1' ) ) {
			return $result;
		}

		$allowed = [];

		foreach ( $server->get_routes() as $route => $handlers ) {
			// get_routes() keys are regex patterns for parameterised routes.
			if ( ! preg_match( '@^' . $route . '$@i', $path ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				$allowed = array_merge( $allowed, array_keys( array_filter( $handler['methods'] ) ) );
			}
		}

		$allowed = array_values( array_unique( $allowed ) );

		if ( empty( $allowed ) ) {
			// Genuinely no such path - core's 404 is the right answer.
			return $result;
		}

		$response = new \WP_REST_Response(
			[
				'code'    => 'rest_method_not_allowed',
				'message' => sprintf(
					/* translators: 1: HTTP method used, 2: comma-separated list of allowed methods. */
					__( '%1$s is not supported on this endpoint. Allowed: %2$s.', 'wp-sell-services' ),
					$request->get_method(),
					implode( ', ', $allowed )
				),
				'data'    => [
					'status'  => 405,
					'allowed' => $allowed,
				],
			],
			405
		);

		// RFC 9110 requires Allow on a 405.
		$response->header( 'Allow', implode( ', ', $allowed ) );

		return $response;
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
			new PaymentController(),
			new AuditLogController(),
			new RealtimeController(),
			new ReportsController(),
			new BlocksController(),
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
						'parent'     => [
							'description' => __( 'Parent category ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						],
						'hide_empty' => [
							'description' => __( 'Hide categories with no published services.', 'wp-sell-services' ),
							'type'        => 'boolean',
							// Defaulted to true, which meant a site whose services
							// are not categorised — or a new site with no services
							// yet — served an EMPTY browse tree to every client,
							// with no error to explain it. A category list is
							// navigation, not search results: show the taxonomy and
							// let the caller opt into filtering.
							'default'     => false,
						],
						'per_page'   => [
							'description' => __( 'Maximum number of categories to return.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 100,
						],
						'page'       => [
							'description' => __( 'Current page of the collection.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
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
						'search'   => [
							'description' => __( 'Search tags.', 'wp-sell-services' ),
							'type'        => 'string',
						],
						'per_page' => [
							'description' => __( 'Maximum number of tags to return.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 100,
						],
						'page'     => [
							'description' => __( 'Current page of the collection.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
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

		// Current user info, and self-service account deletion.
		register_rest_route(
			'wpss/v1',
			'/me',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_current_user' ],
					'permission_callback' => 'wpss_rest_require_login',
				],
				[

					/*
					 * App Store Guideline 5.1.1(v) and Google Play's data
					 * deletion policy: an account that can be created in the app
					 * must be deletable in the app. "Email the site owner" is
					 * the answer both of them reject.
					 */
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_current_user' ],
					'permission_callback' => 'wpss_rest_require_login',
					'args'                => [
						'password' => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The account password, re-entered to confirm.', 'wp-sell-services' ),
						],
						'confirm'  => [
							'required'    => false,
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Set true to delete. False returns what deletion would cost, and deletes nothing.', 'wp-sell-services' ),
						],
					],
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
					'permission_callback' => 'wpss_rest_require_login',
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
					'permission_callback' => 'wpss_rest_require_login',
					'args'                => [
						'requests' => [
							'description' => __( 'Array of sub-requests.', 'wp-sell-services' ),
							'type'        => 'array',
							'required'    => true,
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'method' => [
										'type' => 'string',
										'enum' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ],
									],
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
						'q'        => [
							'description' => __( 'Search query.', 'wp-sell-services' ),
							'type'        => 'string',
							// Not `required`: /services calls this same thing
							// `search`, and either name is accepted here, so the
							// request is valid with one or the other.
							'required'    => false,
						],
						'search'   => [
							'description' => __( 'Search query. Alias of `q`, accepted for parity with /services.', 'wp-sell-services' ),
							'type'        => 'string',
						],
						'type'     => [
							'description' => __( 'Search type.', 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => 'all',
							'enum'        => [ 'all', 'services', 'vendors' ],
						],
						'per_page' => [
							'description' => __( 'Results per type.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						],
						'page'     => [
							'description' => __( 'Current page of the results.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
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
		$per_page   = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 100 ) );
		$page       = max( 1, (int) $request->get_param( 'page' ) );

		// Shared with the count below so the total can never describe a
		// different set than the page. This endpoint used to take a per_page
		// but no page, so nothing past the first 100 categories was reachable
		// and the client had no way to learn how many there were.
		$query_args = [
			'taxonomy'   => 'wpss_service_category',
			'parent'     => $parent,
			'hide_empty' => $hide_empty,
		];

		$total = (int) wp_count_terms(
			array_merge( $query_args, [ 'hide_empty' => $hide_empty ] )
		);

		$terms = get_terms(
			array_merge(
				$query_args,
				[
					'orderby' => 'name',
					'order'   => 'ASC',
					'number'  => $per_page,
					'offset'  => ( $page - 1 ) * $per_page,
				]
			)
		);

		if ( is_wp_error( $terms ) ) {
			return new \WP_REST_Response( [] );
		}

		$data = [];
		foreach ( $terms as $term ) {
			$data[] = wpss_prepare_term_for_rest( $term );
		}

		$response = new \WP_REST_Response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Get service tags.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_tags( \WP_REST_Request $request ): \WP_REST_Response {
		$search   = $request->get_param( 'search' );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 50 ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		// Was hardcoded to 50 with no page argument, so tag 51 onwards simply
		// did not exist as far as any client was concerned.
		$base_args = [
			'taxonomy'   => 'wpss_service_tag',
			'hide_empty' => false,
		];

		if ( $search ) {
			$base_args['search'] = $search;
		}

		$total = (int) wp_count_terms( $base_args );

		$terms = get_terms(
			array_merge(
				$base_args,
				[
					'orderby' => 'count',
					'order'   => 'DESC',
					'number'  => $per_page,
					'offset'  => ( $page - 1 ) * $per_page,
				]
			)
		);

		if ( is_wp_error( $terms ) ) {
			return new \WP_REST_Response( [] );
		}

		$data = [];
		foreach ( $terms as $term ) {
			$data[] = [
				'id'    => $term->term_id,
				'name'  => wpss_rest_text( $term->name ),
				'slug'  => $term->slug,
				'count' => $term->count,
			];
		}

		$response = new \WP_REST_Response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Resolve the plugin's page map to ids a client can actually navigate to.
	 *
	 * THE one place the page map is authored. `pages` and `page_urls` used to
	 * each build their own copy of this list, so they could name different
	 * screens for the same key.
	 *
	 * vendors, checkout and cart are resolved rather than read straight from the
	 * option: vendors_page was a key nothing ever wrote, so it was permanently
	 * 0, and checkout/cart in the option are the STANDALONE pages, which on a
	 * WooCommerce site are not the pages a client should be sent to.
	 *
	 * Every value is an id of a PUBLISHED page or null. A 0 was never navigable
	 * — an app opening a legal or vendor-directory WebView from `pages` landed
	 * on an invalid post id and 404'd — and an id whose page has since been
	 * trashed or unpublished is just as broken, so both collapse to null and the
	 * client can hide the entry instead of linking nowhere.
	 *
	 * @since 1.4.0
	 *
	 * @param array<string, mixed> $pages_settings Stored page map.
	 * @return array<string, int|null>
	 */
	private function get_page_ids( array $pages_settings ): array {
		$ids = array(
			'services'      => (int) ( $pages_settings['services_page'] ?? 0 ),
			'vendors'       => function_exists( 'wpss_get_vendors_page_id' ) ? wpss_get_vendors_page_id() : (int) ( $pages_settings['vendors_page'] ?? 0 ),
			'dashboard'     => (int) ( $pages_settings['dashboard'] ?? 0 ),
			'checkout'      => function_exists( 'wpss_get_active_store_page_id' ) ? wpss_get_active_store_page_id( 'checkout' ) : (int) ( $pages_settings['checkout'] ?? 0 ),
			'cart'          => function_exists( 'wpss_get_active_store_page_id' ) ? wpss_get_active_store_page_id( 'cart' ) : (int) ( $pages_settings['cart'] ?? 0 ),
			'become_vendor' => (int) ( $pages_settings['become_vendor'] ?? 0 ),
			'terms'         => (int) get_option( 'wpss_terms_page' ),
		);

		foreach ( $ids as $key => $id ) {
			$ids[ $key ] = ( $id > 0 && 'publish' === get_post_status( $id ) ) ? $id : null;
		}

		return $ids;
	}

	/**
	 * Resolve each mapped page to a URL a client can navigate to.
	 *
	 * @since 1.4.0
	 *
	 * @param array<string, mixed> $pages_settings Stored page map.
	 * @return array<string, string|null>
	 */
	private function get_page_urls( array $pages_settings ): array {
		$urls = array();

		foreach ( $this->get_page_ids( $pages_settings ) as $key => $id ) {
			$url = $id ? get_permalink( $id ) : '';

			$urls[ $key ] = $url ?: null;
		}

		// Checkout and cart belong to whichever rail owns the store, so read
		// them through the same resolvers the rest of the plugin uses rather
		// than trusting our own page map — on a WooCommerce site these point at
		// WooCommerce's pages, not the standalone ones.
		if ( function_exists( 'wpss_get_checkout_base_url' ) ) {
			$urls['checkout'] = wpss_get_checkout_base_url() ?: $urls['checkout'];
		}

		if ( function_exists( 'wpss_get_cart_url' ) ) {
			$urls['cart'] = wpss_get_cart_url() ?: $urls['cart'];
		}

		if ( function_exists( 'wpss_get_vendors_url' ) ) {
			$urls['vendors'] = wpss_get_vendors_url() ?: $urls['vendors'];
		}

		return $urls;
	}

	/**
	 * Get public settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_public_settings(): \WP_REST_Response {
		$vendor_settings = get_option( 'wpss_vendor', array() );
		$pages_settings  = get_option( 'wpss_pages', array() );

		$settings = [
			'currency'            => wpss_get_currency(),
			'currency_symbol'     => wpss_get_currency_symbol(),
			'currency_position'   => get_option( 'wpss_currency_position', 'before' ),
			'decimal_places'      => (int) get_option( 'wpss_decimal_places', 2 ),
			'min_order_amount'    => (float) get_option( 'wpss_min_order_amount', 5 ),
			'max_order_amount'    => (float) get_option( 'wpss_max_order_amount', 10000 ),
			'vendor_registration' => $vendor_settings['vendor_registration'] ?? 'open',
			'service_moderation'  => ! empty( $vendor_settings['require_service_moderation'] ),
			'review_moderation'   => ! empty( $vendor_settings['moderate_reviews'] ),
			'max_file_size'       => (int) get_option( 'wpss_max_file_size', 10 ) * 1024 * 1024, // MB to bytes.
			'allowed_file_types'  => explode( ',', get_option( 'wpss_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx' ) ),
			// Page IDs, or NULL where the site has no published page for that
			// key. `vendors` and `terms` are never created by the installer, so
			// they are null on a stock install until the owner maps them —
			// never 0, which is not a post id a client can open.
			'pages'               => $this->get_page_ids( $pages_settings ),
			// Resolved URLs for the same keys, because an ID of 0 is not
			// something a client can navigate to and an ID alone still needs a
			// second round trip. NULL where the site genuinely has no such page,
			// so a client can hide the entry rather than link to nowhere.
			'page_urls'           => $this->get_page_urls( $pages_settings ),
			// Non-sensitive realtime (WebSocket) client config — never the secret.
			'realtime'            => ( new \WPSellServices\Services\RealtimeService() )->get_client_config(),
			// Bootstrap contract for native app clients.
			'app'                 => $this->get_app_contract( $pages_settings ),
		];

		/**
		 * Filter public API settings.
		 *
		 * @param array $settings Settings array.
		 */
		return new \WP_REST_Response( apply_filters( 'wpss_api_public_settings', $settings ) );
	}

	/**
	 * Bootstrap contract a native app reads before it renders anything.
	 *
	 * Anonymous by design: the app needs this BEFORE a user logs in, to decide
	 * whether it may run against this site at all, whether it must force an
	 * upgrade first, and what to brand itself as.
	 *
	 * FAIL CLOSED. `app_enabled` defaults to false here and only Pro flips it, on
	 * a real license check. An app that cannot find the key, because it is talking
	 * to a plugin older than this release, must also treat it as false. Defaulting
	 * to true anywhere in that chain would let an app run against a site that never
	 * agreed to host it — and the failure would be silent on the server side, which
	 * is the worst place for it to be.
	 *
	 * Everything here is world-readable, so it carries only what is already public.
	 * Secrets stay behind the admin-only white-label routes.
	 *
	 * @since 1.5.1
	 *
	 * @param array<string,mixed> $pages_settings Configured page mapping.
	 * @return array<string,mixed>
	 */
	private function get_app_contract( array $pages_settings ): array {
		$page_urls = $this->get_page_urls( $pages_settings );

		$branding = array(
			'brand_name'    => get_bloginfo( 'name' ),
			'logo_url'      => $this->get_site_logo_url(),
			'primary_color' => '',
		);

		// Resolved once: wpss_get_order_statuses() applies a filter, and calling
		// it twice would run third-party code twice for one response.
		$order_statuses = array();

		foreach ( wpss_get_order_statuses() as $slug => $label ) {
			$order_statuses[] = array(
				'slug'  => (string) $slug,
				'label' => (string) $label,
			);
		}

		// Same {slug,label} shape as the statuses above, and a list for the same
		// reason: JSON has no guaranteed key order, so a map would lose the
		// site's ordering the moment a client parsed it.
		$report_reasons = array();

		foreach ( wpss_get_report_reasons() as $slug => $label ) {
			$report_reasons[] = array(
				'slug'  => (string) $slug,
				'label' => (string) $label,
			);
		}

		$report_targets = array();

		foreach ( wpss_get_report_target_types() as $slug => $label ) {
			$report_targets[] = array(
				'slug'  => (string) $slug,
				'label' => (string) $label,
			);
		}

		$adapter = wpss_get_active_adapter();
		$rail    = $adapter ? (string) $adapter->get_id() : 'standalone';

		/*
		 * Can a buyer pay for ONE existing order on this site?
		 *
		 * Milestones, tips and paid extensions all reach the buyer the same way:
		 * a link to pay a single already-created order. Standalone answers it
		 * with `?pay_order=N`, and WooCommerce replaces the URL with a real
		 * order-pay link through the `wpss_pay_order_url` seam. EDD, FluentCart
		 * and SureCart implement neither, so the link falls back to a standalone
		 * checkout that is not the active rail — a dead end for the buyer.
		 *
		 * Asked as "has anyone implemented the seam?" rather than by naming
		 * rails, so an integration added later turns the capability on by
		 * implementing it, instead of by someone remembering to edit this list.
		 */
		$can_pay_single_order = wpss_uses_standalone_payments() || has_filter( 'wpss_pay_order_url' );

		// Milestone contracts only exist on buyer-request orders, so an owner who
		// turns buyer requests off has turned milestones off whether they meant
		// to or not. Better the app hears that here than discovers it by
		// rendering a control nobody can reach.
		$buyer_requests = (bool) wpss_get_option( 'general', 'enable_buyer_requests', true );

		return array(

			/*
			 * Bump when a FIELD changes shape or meaning, never for a value change.
			 * It exists so an app can refuse a payload it does not understand rather
			 * than silently misread one.
			 */
			'contract_version' => 1,

			/*
			 * Force-upgrade floor. '0.0.0' means "no floor" — every build is
			 * acceptable — which is the right default for a site that has never
			 * thought about it. Owners raise it when a release breaks a client.
			 */
			'min_app_version'  => (string) apply_filters( 'wpss_app_min_version', '0.0.0' ),

			/*
			 * Free NEVER enables an app client. Pro flips this against
			 * License\Manager::is_valid(). See the fail-closed note above.
			 */
			'app_enabled'      => (bool) apply_filters( 'wpss_app_enabled', false ),

			/** Public subset only. Pro's white label supplies the real values. */
			'branding'         => (array) apply_filters( 'wpss_app_branding', $branding ),

			'legal'            => array(
				'privacy_policy_url'  => get_privacy_policy_url() ?: null,
				'terms_url'           => $page_urls['terms'] ?? null,

				/*
				 * Deliberately NOT defaulted to admin_email. This endpoint is
				 * anonymous and world-readable, so defaulting would publish the
				 * site owner's inbox to every scraper that finds the route — a
				 * privacy leak the owner never opted into, in exchange for a field
				 * only app-store review actually needs. Empty until set on purpose.
				 */
				'abuse_contact_email' => (string) apply_filters( 'wpss_app_abuse_contact', '' ),
			),

			/*
			 * Booleans an app may gate screens on. Only flags derivable from real
			 * state belong here — a flag that is always true tells a client nothing
			 * and becomes a lie the first time the feature is disabled.
			 */
			'features'         => (array) apply_filters(
				'wpss_app_features',
				array(
					'buyer_requests' => $buyer_requests,
					'disputes'       => (bool) wpss_get_option( 'general', 'enable_disputes', true ),
					'realtime'       => ! empty( ( new \WPSellServices\Services\RealtimeService() )->get_client_config()['enabled'] ),

					/*
					 * The three money-between-members features. Each is false
					 * when the active rail cannot bill a single order, because
					 * on those rails the buyer reaches a dead end rather than a
					 * payment screen — and a dead end an owner cannot see is how
					 * this arrives as "the app is broken" instead of "this rail
					 * does not support that yet".
					 */
					'milestones'     => $buyer_requests && $can_pay_single_order,
					'tips'           => $can_pay_single_order,
					'extensions'     => $can_pay_single_order,

					/*
					 * NOT PUBLISHED HERE, deliberately: portfolio, reviews and
					 * seller levels. None of them has an owner-facing switch, so
					 * a flag for them would be hardcoded true on every site —
					 * which tells a client nothing and becomes a lie the day one
					 * of them gains a toggle and this line is not updated. Add
					 * one at the moment the setting appears, not before.
					 *
					 * Review and service moderation ARE published, alongside the
					 * other site-owned values below, because those are real
					 * settings an owner already changes.
					 */
				)
			),

			/*
			 * What owns payment on this site, and what that implies.
			 *
			 * The three booleans above are the answer; this is the reason. An
			 * owner reading a support thread — or a developer reading a bug
			 * report — can see "rail is fluentcart, single-order billing is not
			 * available" instead of inferring it from three flags that happen to
			 * be false together.
			 */
			'payments'         => array(
				'rail'                 => $rail,
				'can_pay_single_order' => $can_pay_single_order,
			),

			/*
			 * The order-status vocabulary, so a client renders what THIS site
			 * calls each state instead of carrying its own copy.
			 *
			 * A copy is what every client had to keep, because the API published
			 * no list — and a client-side copy of a server-owned, filterable
			 * vocabulary is the defect that has now cost three products in this
			 * portfolio real customer time. It always reads as working: the
			 * screen renders a plausible word, so nothing looks broken until
			 * someone notices the site says something else. The mobile app's
			 * copy is hardcoded English, so every localised site renders its
			 * order statuses untranslated today.
			 *
			 * Ordered as `wpss_get_order_statuses()` returns them, which is the
			 * same map the web renders and the same one `wpss_order_statuses`
			 * filters — so an owner who renames a status sees it change in the
			 * app too.
			 *
			 * A LIST of objects, not a slug => label map: JSON objects have no
			 * guaranteed key order, so a map would lose the site's ordering the
			 * moment a client parsed it.
			 *
			 * Additive, so contract_version stays at 1 — a client that does not
			 * know this key ignores it and keeps working. Bumping would brick
			 * every build already shipped, because clients refuse a contract
			 * newer than the one they understand.
			 */
			'order_statuses'   => $order_statuses,

			/*
			 * The report vocabulary, published for the same reason the order
			 * statuses above are: a client that hardcodes its own copy of a
			 * filterable, translatable list drifts from what the site says, and
			 * scores as working the whole time it is wrong.
			 *
			 * Both halves travel together because a report sheet needs both to
			 * render: what can be reported, and why.
			 */
			'report_reasons'   => $report_reasons,
			'report_targets'   => $report_targets,
		);
	}

	/**
	 * The site's own logo, for a client that has no white-label branding to use.
	 *
	 * @since 1.5.1
	 *
	 * @return string Absolute URL, or empty string when the site has no logo.
	 */
	private function get_site_logo_url(): string {
		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );

			if ( ! empty( $src[0] ) ) {
				return (string) $src[0];
			}
		}

		$icon = get_site_icon_url();

		return $icon ? (string) $icon : '';
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
			// Canonical check, not the raw meta. A vendor created by role — which
			// is every vendor the wizard, admin screen or demo seeder makes — has
			// no _wpss_is_vendor meta, so /me told real sellers they were not
			// sellers while /orders and /vendors/me said otherwise.
			'is_vendor'    => wpss_is_vendor( $user_id ),
			'is_admin'     => current_user_can( 'manage_options' ),
			'capabilities' => [
				'can_create_services' => current_user_can( 'wpss_manage_services' ) && wpss_is_vendor( $user_id ),
				// The capability the vendor role actually grants, not the admin
				// one. Every vendor holds wpss_manage_orders and manages their
				// own orders daily, but this reported false for all of them —
				// so a client gating its order screens on it hid the seller's
				// core workflow. Admins keep access through manage_options.
				'can_manage_orders'   => current_user_can( 'wpss_manage_orders' ) || current_user_can( 'manage_options' ),
			],
		];

		// The other current-user endpoint, /auth/me, also returns these. Both
		// answer the same question, so both carry the same fields — a client
		// should not have to know which one it called.
		$user_object        = get_userdata( $user_id );
		$data['username']   = $user_object ? $user_object->user_login : '';
		$data['registered'] = $user_object ? $user_object->user_registered : '';

		// Always present, so a client can read them without branching on role.
		$data['vendor_status'] = null;
		$data['rating']        = 0.0;
		$data['review_count']  = 0;

		if ( $data['is_vendor'] ) {
			// Canonical profile status — _wpss_vendor_status was never written.
			$data['vendor_status'] = wpss_get_vendor_status( $user_id ) ?: 'active';
			$data['rating']        = (float) get_user_meta( $user_id, '_wpss_rating_average', true ) ?: 0;
			$data['review_count']  = (int) get_user_meta( $user_id, '_wpss_rating_count', true ) ?: 0;
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Delete the current member's account.
	 *
	 * Two-step by design. `confirm: false` — the default — answers what
	 * deletion would cost and deletes nothing, so the app can show a member
	 * their open orders and the wallet balance they are about to forfeit
	 * BEFORE they commit. `confirm: true` performs it.
	 *
	 * The password re-entry is checked against `user_pass` through
	 * wp_check_password() rather than wp_authenticate(). That is deliberate and
	 * load-bearing: wp_authenticate() also accepts an Application Password, and
	 * the app's own token IS one. Checking the real password hash means a
	 * stolen token can read this account but can never destroy it.
	 *
	 * @since 1.5.2
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_current_user( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error(
				'wpss_user_not_found',
				__( 'That account no longer exists.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		/*
		 * Rate limit, charged on FAILURE only.
		 *
		 * This route takes a password, so it is a guessing surface and needs a
		 * ceiling. But the legitimate flow spends two calls — the dry run that
		 * shows the member what they are about to lose, then the confirmation —
		 * and charging those would lock out an honest member who mistyped once
		 * while barely inconveniencing anyone actually guessing.
		 *
		 * So: refuse when already over, and only spend budget when the password
		 * was wrong.
		 */
		if ( \WPSellServices\Core\RateLimiter::is_limited( 'account_delete', $user_id ) ) {
			return new \WP_Error(
				'wpss_rate_limited',
				__( 'Too many attempts. Please wait a few minutes and try again.', 'wp-sell-services' ),
				[ 'status' => 429 ]
			);
		}

		$password = (string) $request->get_param( 'password' );

		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			\WPSellServices\Core\RateLimiter::track( 'account_delete', $user_id );

			return new \WP_Error(
				'wpss_bad_password',
				__( 'That password is not correct.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		$service     = new \WPSellServices\Services\AccountDeletionService();
		$obligations = $service->get_obligations( $user_id );

		// The dry run: what would happen, and what it would cost.
		if ( ! $request->get_param( 'confirm' ) ) {
			return new \WP_REST_Response(
				[
					'deleted'          => false,
					'can_delete'       => ! $obligations['blocked'],
					'orders'           => $obligations['orders'],
					'order_count'      => $obligations['order_count'],
					'open_withdrawals' => $obligations['open_withdrawals'],
					'wallet_balance'   => $obligations['wallet_balance'],
					'currency'         => $obligations['currency'],
				]
			);
		}

		$deleted = $service->delete( $user_id );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return new \WP_REST_Response(
			[
				'deleted' => true,
				'message' => __( 'Your account and personal data have been deleted.', 'wp-sell-services' ),
			]
		);
	}

	/**
	 * Get dashboard data.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard(): \WP_REST_Response {
		global $wpdb;

		$user_id   = get_current_user_id();
		$is_vendor = wpss_is_vendor( $user_id );

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
		$query    = sanitize_text_field( $request->get_param( 'q' ) ?: $request->get_param( 'search' ) );
		$type     = $request->get_param( 'type' );
		$per_page = (int) $request->get_param( 'per_page' ) ?: 10;
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Results were capped at per_page with no page argument, so there was
		// no second page of anything. Totals are returned per type so a client
		// can tell "no more results" from "the cap cut you off".
		$results = [
			'query' => $query,
			'page'  => $page,
		];

		// Search services.
		if ( 'all' === $type || 'services' === $type ) {
			$services_query = new \WP_Query(
				[
					'post_type'      => 'wpss_service',
					'post_status'    => 'publish',
					's'              => $query,
					'posts_per_page' => $per_page,
					'offset'         => $offset,
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

			$results['services']       = $services;
			$results['services_total'] = (int) $services_query->found_posts;
		}

		// Search vendors.
		if ( 'all' === $type || 'vendors' === $type ) {
			global $wpdb;

			// Match the vendor directory: role OR the legacy meta. Searching on
			// the meta alone missed every vendor created by role — which is
			// every vendor the wizard, the admin screen and the seeder make —
			// so they were unfindable by name.
			$vendors_query = new \WP_User_Query(
				[
					'meta_query'     => [
						'relation' => 'OR',
						[
							'key'     => $wpdb->prefix . 'capabilities',
							'value'   => '"' . \WPSellServices\Services\VendorService::ROLE . '"',
							'compare' => 'LIKE',
						],
						[
							'key'   => '_wpss_is_vendor',
							'value' => '1',
						],
					],
					'search'         => '*' . $query . '*',
					'search_columns' => [ 'user_login', 'display_name', 'user_nicename' ],
					'number'         => $per_page,
					'offset'         => $offset,
					'count_total'    => true,
				]
			);

			$vendors = [];
			foreach ( $vendors_query->get_results() as $user ) {
				// Tagline lives on the canonical wpss_vendor_profiles table —
				// the _wpss_vendor_tagline user-meta key was never written.
				$vendor_profile = wpss_get_vendor( $user->ID );

				$vendors[] = [
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'avatar'       => get_avatar_url( $user->ID, [ 'size' => 48 ] ),
					'tagline'      => $vendor_profile ? $vendor_profile->title : '',
					'rating'       => (float) get_user_meta( $user->ID, '_wpss_rating_average', true ) ?: 0,
					'url'          => wpss_get_vendor_url( $user->ID ),
				];
			}

			$results['vendors']       = $vendors;
			$results['vendors_total'] = (int) $vendors_query->get_total();
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
					'body'   => [
						'code'    => 'invalid_path',
						'message' => __( 'Path must start with /wpss/v1/', 'wp-sell-services' ),
					],
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
