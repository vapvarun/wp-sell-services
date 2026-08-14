<?php
/**
 * Vendors REST Controller
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
use WP_User_Query;
use WPSellServices\Models\Review;

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
					// Schema-derived args plus the profile-form fields that map to
					// wpss_vendor_profiles columns (handler sanitizes each).
					'args'                => array_merge(
						$this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
						array(
							'country'              => array( 'type' => 'string' ),
							'city'                 => array( 'type' => 'string' ),
							'website'              => array( 'type' => 'string' ),
							'intro_video_url'      => array( 'type' => 'string' ),
							'vacation_mode'        => array( 'type' => 'boolean' ),
							'vacation_message'     => array( 'type' => 'string' ),
							'vacation_return_date' => array( 'type' => 'string' ),
							'cover_image_id'       => array( 'type' => 'integer' ),
							'cover_id'             => array( 'type' => 'integer' ),
						)
					),
				),
			)
		);

		// Toggle vacation mode for the current vendor.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me/vacation',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_vacation_mode' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'enabled'     => array(
							'description' => __( 'Whether vacation mode is enabled.', 'wp-sell-services' ),
							'type'        => 'boolean',
							'required'    => true,
						),
						'message'     => array(
							'description'       => __( 'Optional vacation message shown to buyers.', 'wp-sell-services' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'return_date' => array(
							'description' => __( 'Optional buyer-facing return date (Y-m-d). Empty/invalid clears it.', 'wp-sell-services' ),
							'type'        => 'string',
							'format'      => 'date',
						),
					),
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
		global $wpdb;
		$pagination = $this->get_pagination_args( $request );

		// Identify vendors by EITHER the wpss_vendor role (matched via the
		// capabilities meta, the same way WP_User_Query implements role queries)
		// OR the legacy _wpss_is_vendor meta. Role-based and demo-seeded vendors
		// never carry the legacy meta, so a meta-only clause hid them from the
		// directory even though they show in the admin vendor list. Stays a
		// SQL-paginated meta_query (big-site safe); AND-composes with the skill
		// filter and the rating/orders sort JOIN below.
		$args = array(
			'meta_query' => array(
				'relation'      => 'AND',
				'vendor_clause' => array(
					'relation' => 'OR',
					array(
						'key'     => $wpdb->prefix . 'capabilities',
						'value'   => '"' . \WPSellServices\Services\VendorService::ROLE . '"',
						'compare' => 'LIKE',
					),
					array(
						'key'   => '_wpss_is_vendor',
						'value' => '1',
					),
				),
			),
			'number'     => $pagination['per_page'],
			'offset'     => $pagination['offset'],
			'orderby'    => 'registered',
			'order'      => 'DESC',
		);

		// Search by name.
		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['search'] = '*' . $search . '*';
			// Deliberately NOT user_login. Searching that column turns a public
			// endpoint into a login-name oracle: an unauthenticated caller can
			// probe one letter at a time and learn which WordPress usernames
			// exist, which is the first half of a credential-stuffing attempt.
			// display_name and user_nicename are both already public (nicename
			// is the author slug in URLs), so buyer-facing search is unaffected
			// -- what is lost is only the ability to find a vendor by a private
			// login name, which is not something a buyer knows to search for.
			$args['search_columns'] = array( 'display_name', 'user_nicename' );
		}

		// Filter by skill/category.
		$skill = $request->get_param( 'skill' );
		if ( $skill ) {
			$args['meta_query']['skill_clause'] = array(
				'key'     => '_wpss_vendor_skills',
				'value'   => $skill,
				'compare' => 'LIKE',
			);
		}

		// Order by rating or orders (uses custom query modifier to LEFT JOIN).
		$orderby = $request->get_param( 'orderby' );
		if ( 'rating' === $orderby || 'orders' === $orderby ) {
			add_action(
				'pre_user_query',
				function ( $query ) use ( $orderby ) {
					global $wpdb;

					if ( 'orders' === $orderby ) {
						// completed_orders lives in the vendor profiles table. The
						// _wpss_completed_orders user meta was never written, so the
						// old meta join sorted every vendor as 0 (BC #10110742943).
						$profiles             = $wpdb->prefix . 'wpss_vendor_profiles';
						$query->query_from   .= " LEFT JOIN {$profiles} AS sort_prof ON ( {$wpdb->users}.ID = sort_prof.user_id )";
						$query->query_orderby = 'ORDER BY COALESCE(sort_prof.completed_orders, 0) DESC';
						return;
					}

					// Rating still comes from the _wpss_rating_average user meta,
					// which does have a writer.
					$query->query_from   .= $wpdb->prepare(
						" LEFT JOIN {$wpdb->usermeta} AS sort_meta ON ( {$wpdb->users}.ID = sort_meta.user_id AND sort_meta.meta_key = %s )",
						'_wpss_rating_average'
					);
					$query->query_orderby = 'ORDER BY COALESCE(sort_meta.meta_value+0, 0) DESC';
				}
			);
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

		// Verify user is a vendor. wpss_is_vendor() checks the capability and
		// role, not just the legacy _wpss_is_vendor meta — vendors created via
		// role grant (e.g. demo seeder, admin approval) have no meta row and
		// were 404ing here.
		if ( ! wpss_is_vendor( $vendor_id ) ) {
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

		// Canonical vendor check (matches PUT /vendors/me) so role-based / demo
		// vendors without the legacy _wpss_is_vendor meta are not 404'd.
		if ( ! wpss_is_vendor( $user_id ) ) {
			return new WP_Error(
				'wpss_not_vendor',
				__( 'You are not registered as a vendor.', 'wp-sell-services' ),
				// 403, not 404: the caller exists and the route exists, they are
				// simply not a vendor. A 404 reads as "gone" and a client will
				// happily cache it, so the moment the user becomes a vendor the
				// app still believes the endpoint is missing.
				array( 'status' => 403 )
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

		// Canonical vendor check (capability/role), not the raw _wpss_is_vendor
		// meta - role-based vendors (seeded or granted via role) do not always
		// carry that meta, which previously rejected legitimate vendors here and
		// silently skipped the vendor block in the AJAX twin.
		if ( ! wpss_is_vendor( $user_id ) ) {
			return new WP_Error(
				'wpss_not_vendor',
				__( 'You are not registered as a vendor.', 'wp-sell-services' ),
				// 403, not 404: the caller exists and the route exists, they are
				// simply not a vendor. A 404 reads as "gone" and a client will
				// happily cache it, so the moment the user becomes a vendor the
				// app still believes the endpoint is missing.
				array( 'status' => 403 )
			);
		}

		// A vendor profile is public marketing copy — tagline, bio, avatar,
		// cover, social links. A suspended vendor does not get to keep editing
		// what the marketplace shows about them. Vacation mode is deliberately
		// NOT gated: it only tells buyers the seller is away, which is true and
		// useful whatever their standing. See wpss_vendor_status_block().
		$status_block = wpss_vendor_status_block( $user_id );

		if ( $status_block ) {
			return $status_block;
		}

		// Resolve avatar/cover attachment ids (accept both cover_image_id and
		// the form's legacy cover_id name).
		$avatar_id = $request->has_param( 'avatar_id' ) ? absint( $request->get_param( 'avatar_id' ) ) : 0;
		$cover_id  = 0;
		if ( $request->has_param( 'cover_image_id' ) ) {
			$cover_id = absint( $request->get_param( 'cover_image_id' ) );
		} elseif ( $request->has_param( 'cover_id' ) ) {
			$cover_id = absint( $request->get_param( 'cover_id' ) );
		}

		// Global avatar user-meta (the source get_avatar_url reads). Mirrors the
		// legacy form/AJAX path; the table avatar_id is set via the builder below.
		if ( $request->has_param( 'avatar_id' ) ) {
			if ( $avatar_id && wp_attachment_is_image( $avatar_id ) ) {
				update_user_meta( $user_id, '_wpss_avatar_id', $avatar_id );
			} elseif ( 0 === $avatar_id ) {
				delete_user_meta( $user_id, '_wpss_avatar_id' );
			}
		}

		// Build the table-backed field set from the request (only present
		// params), then persist through the canonical VendorService::update_profile()
		// - the SAME path the form/AJAX uses. Previously these wrote to user_meta,
		// which split storage and dropped intro_video/country/city/website/
		// vacation/cover entirely (KG-5 data-loss). Now one store: the
		// wpss_vendor_profiles table.
		$src = array();
		foreach ( array( 'tagline', 'bio', 'country', 'city', 'website', 'intro_video_url', 'vacation_mode', 'vacation_message', 'vacation_return_date' ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$src[ $key ] = $request->get_param( $key );
			}
		}
		if ( $request->has_param( 'avatar_id' ) ) {
			$src['avatar_id'] = $avatar_id;
		}
		if ( $request->has_param( 'cover_image_id' ) || $request->has_param( 'cover_id' ) ) {
			$src['cover_id'] = $cover_id;
		}

		$profile_data = wpss_build_vendor_profile_update( $src, $avatar_id, $cover_id );

		// social_links has a table column too - route it through the canonical
		// writer so it lives with the rest of the profile, not in user_meta.
		if ( $request->has_param( 'social_links' ) ) {
			$social = array();
			foreach ( (array) $request->get_param( 'social_links' ) as $platform => $url ) {
				$social[ sanitize_key( $platform ) ] = esc_url_raw( $url );
			}
			$profile_data['social_links'] = $social;
		}

		if ( ! empty( $profile_data ) ) {
			$updated = ( new \WPSellServices\Services\VendorService() )->update_profile( $user_id, $profile_data );

			// Surface DB-level failures (missing column, constraint violation)
			// instead of returning HTTP 200 with stale data — a silent failure
			// here is how the vacation-mode persistence bug went unnoticed.
			if ( ! $updated ) {
				return new WP_Error(
					'wpss_profile_update_failed',
					__( 'Your profile could not be saved. Please try again or contact support.', 'wp-sell-services' ),
					array( 'status' => 500 )
				);
			}
		}

		// Fields without a wpss_vendor_profiles column stay in user_meta
		// (API-only; not part of the profile form). Additive, no regression.
		if ( $request->has_param( 'skills' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_skills', array_map( 'sanitize_text_field', (array) $request->get_param( 'skills' ) ) );
		}
		if ( $request->has_param( 'languages' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_languages', array_map( 'sanitize_text_field', (array) $request->get_param( 'languages' ) ) );
		}
		if ( $request->has_param( 'response_time' ) ) {
			update_user_meta( $user_id, '_wpss_vendor_response_time', sanitize_text_field( $request->get_param( 'response_time' ) ) );
		}

		// Display name (WordPress user record).
		if ( $request->has_param( 'display_name' ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => sanitize_text_field( $request->get_param( 'display_name' ) ),
				)
			);
		}

		/** This action is documented in src/Frontend/AjaxHandlers.php */
		do_action( 'wpss_vendor_profile_saved', $user_id, $request->get_params() );

		$vendor = get_userdata( $user_id );

		return $this->prepare_item_for_response( $vendor, $request, true );
	}

	/**
	 * Toggle vacation mode for the current vendor.
	 *
	 * Additive REST surface over the existing
	 * `VendorService::set_vacation_mode()` flow so app/web clients can pause a
	 * vendor account without the legacy form/AJAX path.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_vacation_mode( $request ) {
		$user_id = get_current_user_id();

		if ( ! wpss_is_vendor( $user_id ) ) {
			return new WP_Error(
				'wpss_not_vendor',
				__( 'You are not registered as a vendor.', 'wp-sell-services' ),
				// 403, not 404: the caller exists and the route exists, they are
				// simply not a vendor. A 404 reads as "gone" and a client will
				// happily cache it, so the moment the user becomes a vendor the
				// app still believes the endpoint is missing.
				array( 'status' => 403 )
			);
		}

		$enabled = (bool) $request->get_param( 'enabled' );
		$message = $request->has_param( 'message' )
			? sanitize_textarea_field( (string) $request->get_param( 'message' ) )
			: '';

		// Strict Y-m-d (empty/invalid -> null/cleared) via the shared validator.
		$return_date = $request->has_param( 'return_date' )
			? wpss_sanitize_date( (string) $request->get_param( 'return_date' ) )
			: null;

		$vendor_service = new \WPSellServices\Services\VendorService();
		$result         = $vendor_service->set_vacation_mode( $user_id, $enabled, $message, $return_date );

		if ( ! $result ) {
			return new WP_Error(
				'rest_vacation_update_failed',
				__( 'Could not update vacation mode. Please try again.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'enabled'     => $enabled,
				'message'     => $message,
				'return_date' => $return_date,
			)
		);
	}

	/**
	 * Register as vendor.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_vendor( $request ) {
		$user_id        = get_current_user_id();
		$vendor_service = new \WPSellServices\Services\VendorService();

		// Check if already a vendor.
		if ( $vendor_service->is_vendor( $user_id ) ) {
			return new WP_Error(
				'rest_already_vendor',
				__( 'You are already registered as a vendor.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		// Check for existing pending application.
		if ( $vendor_service->has_pending_application( $user_id ) ) {
			return new WP_Error(
				'rest_pending_application',
				__( 'You already have a pending vendor application. Please wait for admin approval.', 'wp-sell-services' ),
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

		// Build profile data from request.
		$data = array();

		if ( $request->has_param( 'bio' ) ) {
			$data['bio'] = sanitize_textarea_field( $request->get_param( 'bio' ) );
		}

		if ( $request->has_param( 'display_name' ) ) {
			$data['display_name'] = sanitize_text_field( $request->get_param( 'display_name' ) );
		}

		if ( $request->has_param( 'tagline' ) ) {
			$data['tagline'] = sanitize_text_field( $request->get_param( 'tagline' ) );
		}

		// Register via VendorService (handles role/caps based on vendor_registration mode).
		$result = $vendor_service->register_vendor( $user_id, $data );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'rest_registration_failed',
				$result['message'],
				array( 'status' => 400 )
			);
		}

		$vendor_status = $vendor_service->get_vendor_status( $user_id );

		// `wpss_vendor_registered` is NOT fired here. VendorService::register_vendor()
		// already fires it with the profile array the listener declares, so this
		// line was both a duplicate and the wrong type: it passed the status
		// STRING, and the notification listener is typed
		// `function ( int $user_id, array $profile_data )`.
		//
		// The result was a 500 on every REST vendor registration, AFTER the
		// vendor had been created — so the member became a seller and saw
		// "There has been a critical error on this website." Nothing on the web
		// path hit it, because the web path goes through the service and never
		// re-fired the hook.

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $vendor_status,
				'message' => 'active' === $vendor_status
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
			$service = get_post( (int) $review->service_id );

			$data[] = array(
				'id'              => (int) $review->id,
				'service_id'      => (int) $review->service_id,
				'service_title'   => $service ? $service->post_title : '',
				'customer_name'   => Review::resolve_reviewer_name( (int) $review->customer_id, $review->reviewer_name ?? null ),
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

		// Response time (response_time_hours column on the canonical
		// wpss_vendor_profiles table — the _wpss_avg_response_time user-meta
		// key was never written).
		$stats_profile     = wpss_get_vendor( $vendor_id );
		$avg_response_time = $stats_profile && $stats_profile->response_time > 0
			? $stats_profile->get_response_time_label()
			: null;

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
				'avg_response_time' => $avg_response_time,
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

		// Canonical vendor data lives in the wpss_vendor_profiles table (1.2.0
		// migration). The old _wpss_vendor_tagline/bio/social/verified/country
		// and _wpss_completed_orders user-meta keys were never written, so
		// those fields are sourced from the profile row. skills, languages,
		// response_time, rating_average, rating_count, and member_since keep
		// their user-meta source because those keys DO have write paths.
		$profile = wpss_get_vendor( $vendor_id );

		$data = array(
			'id'               => $vendor_id,
			'display_name'     => $vendor->display_name,
			'username'         => $vendor->user_nicename,
			'avatar'           => get_avatar_url( $vendor_id, array( 'size' => 96 ) ),
			'avatar_large'     => get_avatar_url( $vendor_id, array( 'size' => 256 ) ),
			'tagline'          => $profile ? $profile->title : '',
			'bio'              => $profile ? $profile->bio : '',
			'skills'           => get_user_meta( $vendor_id, '_wpss_vendor_skills', true ) ?: array(),
			'languages'        => get_user_meta( $vendor_id, '_wpss_vendor_languages', true ) ?: array(),
			'response_time'    => get_user_meta( $vendor_id, '_wpss_vendor_response_time', true ) ?: '',
			'social_links'     => $profile ? $profile->social_links : array(),
			'rating_average'   => (float) get_user_meta( $vendor_id, '_wpss_rating_average', true ) ?: 0,
			'rating_count'     => (int) get_user_meta( $vendor_id, '_wpss_rating_count', true ) ?: 0,
			'completed_orders' => $profile ? $profile->orders_completed : 0,
			'member_since'     => get_user_meta( $vendor_id, '_wpss_vendor_since', true ) ?: $vendor->user_registered,
			'is_verified'      => $profile ? $profile->is_verified : false,
			'country'          => $profile ? $profile->country : '',
		);

		// Add private data for self.
		if ( $is_self ) {
			$data['email'] = $vendor->user_email;
			// Canonical profile status. The old _wpss_vendor_status user meta was
			// never written, so this always fell through to 'approved' and the
			// endpoint reported every vendor as approved regardless of reality.
			$data['status'] = wpss_get_vendor_status( $vendor_id ) ?: 'active';
		}

		/**
		 * Filters the vendor data returned in REST API responses.
		 *
		 * @since 1.4.0
		 *
		 * @param array           $data    Prepared vendor response data.
		 * @param \WP_User        $vendor  Vendor user object.
		 * @param WP_REST_Request $request REST request object.
		 */
		$data = apply_filters( 'wpss_rest_vendor_data', $data, $vendor, $request );

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

		return array_merge(
			array(
				'id'        => $service_id,
				'title'     => $post->post_title,
				'slug'      => $post->post_name,
				'excerpt'   => get_the_excerpt( $post ),
				'thumbnail' => get_the_post_thumbnail_url( $service_id, 'medium' ) ?: '',
			),
			// Services carry no currency of their own - they are priced in
			// the store currency, which is the helper's default.
			wpss_rest_money( 'price', (float) get_post_meta( $service_id, '_wpss_starting_price', true ) ?: 0 ),
			array(
				'rating_average' => (float) get_post_meta( $service_id, '_wpss_rating_average', true ) ?: 0,
				'rating_count'   => (int) get_post_meta( $service_id, '_wpss_rating_count', true ) ?: 0,
				'category'       => $this->get_service_category_name( $service_id ),
			)
		);
	}

	/**
	 * Get the first category name for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return string Category name or empty string.
	 */
	private function get_service_category_name( int $service_id ): string {
		$terms = wp_get_post_terms( $service_id, 'wpss_service_category', array( 'fields' => 'names' ) );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : '';
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
				'avatar_id'     => array(
					'description' => __( 'Avatar media attachment ID.', 'wp-sell-services' ),
					'type'        => 'integer',
					'context'     => array( 'edit' ),
				),
			),
		);
	}
}
