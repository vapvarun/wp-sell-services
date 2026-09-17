<?php
/**
 * Portfolio REST Controller
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

/**
 * REST controller for vendor portfolios.
 *
 * @since 1.0.0
 */
class PortfolioController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'portfolio';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /vendors/{vendor_id}/portfolio - Get vendor portfolio (public).
		register_rest_route(
			$this->namespace,
			'/vendors/(?P<vendor_id>[\d]+)/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_portfolio' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'vendor_id' => array(
							'description' => __( 'Vendor ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'page'      => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'default' => 10,
							'maximum' => 100,
						),
					),
				),
			)
		);

		// GET /portfolio/{id} - Get single item (public).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /portfolio - Create item (vendor).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				// The collection was POST-only, so a client that listed before
				// creating got a 404 and no clue that /vendors/{id}/portfolio
				// was the read route. This is the caller's own portfolio, which
				// is what a "My portfolio" screen needs.
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_own_portfolio' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'page'     => array(
							'description' => __( 'Current page of the collection.', 'wp-sell-services' ),
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'title'        => array(
							'description' => __( 'Portfolio item title.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'description'  => array(
							'description' => __( 'Portfolio item description.', 'wp-sell-services' ),
							'type'        => 'string',
						),
						'media'        => array(
							'description' => __( 'Attachment IDs.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
						),
						'service_id'   => array(
							'description' => __( 'Related service ID.', 'wp-sell-services' ),
							'type'        => 'integer',
						),
						'external_url' => array(
							'description' => __( 'External project URL.', 'wp-sell-services' ),
							'type'        => 'string',
							'format'      => 'uri',
						),
					),
				),
			)
		);

		// PUT /portfolio/{id} - Update item (owner).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_owner_permissions' ),
				),
			)
		);

		// DELETE /portfolio/{id} - Delete item (owner).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_owner_permissions' ),
				),
			)
		);

		// POST /portfolio/{id}/featured - Toggle featured.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/featured',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_featured' ),
					'permission_callback' => array( $this, 'check_owner_permissions' ),
				),
			)
		);

		// POST /portfolio/reorder - Reorder items.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'order' => array(
							'description' => __( 'Array of item IDs in desired order.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * List the current user's own portfolio.
	 *
	 * Delegates to get_vendor_portfolio() so there is one query, one shape and
	 * one pagination contract — the only difference is whose portfolio it is.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_own_portfolio( WP_REST_Request $request ) {
		$request->set_param( 'vendor_id', get_current_user_id() );

		return $this->get_vendor_portfolio( $request );
	}

	/**
	 * Get vendor portfolio.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_vendor_portfolio( WP_REST_Request $request ) {
		$vendor_id  = (int) $request->get_param( 'vendor_id' );
		$pagination = $this->get_pagination_args( $request );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d", $vendor_id )
		);

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE vendor_id = %d ORDER BY sort_order ASC, created_at DESC LIMIT %d OFFSET %d",
				$vendor_id,
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);

		$portfolio = array_map( array( $this, 'format_item' ), $items ?: array() );

		return $this->paginated_response( $portfolio, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single portfolio item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$item = $this->get_portfolio_item( (int) $request->get_param( 'id' ) );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Portfolio item not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_item( $item ) );
	}

	/**
	 * Create portfolio item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		$vendor_id = get_current_user_id();
		$media     = $request->get_param( 'media' );

		$wpdb->insert(
			$table,
			array(
				'vendor_id'    => $vendor_id,
				'title'        => sanitize_text_field( $request->get_param( 'title' ) ),
				'description'  => sanitize_textarea_field( $request->get_param( 'description' ) ?: '' ),
				'media'        => wp_json_encode( is_array( $media ) ? array_map( 'intval', $media ) : array() ),
				'service_id'   => (int) $request->get_param( 'service_id' ),
				'external_url' => esc_url_raw( $request->get_param( 'external_url' ) ?: '' ),

				/*
				 * Read from the form's "Mark as Featured" checkbox rather than
				 * written as a hardcoded zero. The portfolio service has always
				 * honoured this field; the route ignored it and stored zero every
				 * time, so the checkbox in the Add Portfolio modal did nothing at
				 * all (Basecamp 10300287069). The owner's featured limit still
				 * applies.
				 */
				'is_featured'  => ( $request->get_param( 'is_featured' ) && $this->can_feature_another( $vendor_id ) ) ? 1 : 0,
				'sort_order'   => 0,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
		);

		$item_id = (int) $wpdb->insert_id;

		if ( ! $item_id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create portfolio item.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		$item = $this->get_portfolio_item( $item_id );

		return new WP_REST_Response( $this->format_item( $item ), 201 );
	}

	/**
	 * Update portfolio item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	/**
	 * Whether this vendor may feature one more item.
	 *
	 * Both create_item() and update_item() write is_featured directly, so the
	 * limit PortfolioService::toggle_featured() enforces has to be applied here
	 * too - otherwise the cap holds on the toggle and not on the form beside it.
	 *
	 * @since 1.7.1
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @param int $exclude_id Item being edited, excluded from the count.
	 * @return bool
	 */
	private function can_feature_another( int $vendor_id, int $exclude_id = 0 ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_portfolio_items';
		$max   = (int) get_option( 'wpss_max_featured_portfolio', 6 );

		if ( $max <= 0 ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted $wpdb->prefix concat, as everywhere else in this class.
				"SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d AND is_featured = 1 AND id <> %d",
				$vendor_id,
				$exclude_id
			)
		);

		return $count < $max;
	}

	/**
	 * Update portfolio item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$item_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		$update = array();
		$format = array();

		if ( $request->has_param( 'title' ) ) {
			$update['title'] = sanitize_text_field( $request->get_param( 'title' ) );
			$format[]        = '%s';
		}

		if ( $request->has_param( 'description' ) ) {
			$update['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
			$format[]              = '%s';
		}

		if ( $request->has_param( 'media' ) ) {
			$media           = $request->get_param( 'media' );
			$update['media'] = wp_json_encode( is_array( $media ) ? array_map( 'intval', $media ) : array() );
			$format[]        = '%s';
		}

		if ( $request->has_param( 'service_id' ) ) {
			$update['service_id'] = (int) $request->get_param( 'service_id' );
			$format[]             = '%d';
		}

		/*
		 * Editing an existing item could not change its featured state either -
		 * the modal populates the checkbox correctly on open and then discarded
		 * whatever the vendor did with it (Basecamp 10300287069). Unfeaturing is
		 * always allowed; featuring is capped, with the item itself excluded
		 * from the count so re-saving an already-featured item is not blocked by
		 * its own row.
		 */
		if ( $request->has_param( 'is_featured' ) ) {
			$wants_featured = (bool) $request->get_param( 'is_featured' );

			// $item is not fetched until after this block, so resolve the owner
			// directly rather than reaching for a variable that does not exist yet.
			// The cast binds tighter than ??, so `(int) $x['vendor_id'] ?? 0`
			// never reaches the fallback and reads an offset on a possible null.
			$portfolio_item = $this->get_portfolio_item( $item_id );
			$owner_id       = isset( $portfolio_item['vendor_id'] ) ? (int) $portfolio_item['vendor_id'] : 0;

			$update['is_featured'] = ( $wants_featured && $this->can_feature_another( $owner_id, $item_id ) ) ? 1 : 0;
			$format[]              = '%d';
		}

		if ( $request->has_param( 'external_url' ) ) {
			$update['external_url'] = esc_url_raw( $request->get_param( 'external_url' ) );
			$format[]               = '%s';
		}

		if ( ! empty( $update ) ) {
			$wpdb->update( $table, $update, array( 'id' => $item_id ), $format, array( '%d' ) );
		}

		$item = $this->get_portfolio_item( $item_id );

		return new WP_REST_Response( $this->format_item( $item ) );
	}

	/**
	 * Delete portfolio item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$item_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		$wpdb->delete( $table, array( 'id' => $item_id ), array( '%d' ) );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Toggle featured status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_featured( WP_REST_Request $request ) {
		$item_id = (int) $request->get_param( 'id' );

		/*
		 * Through the service, not a raw UPDATE.
		 *
		 * PortfolioService::toggle_featured() already enforces
		 * wpss_max_featured_portfolio and already checks the item belongs to the
		 * caller. This route did neither: it flipped the column directly, so the
		 * owner's limit was enforced on whichever surface happened to call the
		 * service and ignored on the dashboard, which calls this (Basecamp
		 * 10304836976). The vendor-ownership check it was also missing matters
		 * more than the limit.
		 */
		$result = ( new \WPSellServices\Services\PortfolioService() )->toggle_featured(
			$item_id,
			get_current_user_id()
		);

		if ( empty( $result['success'] ) ) {
			$message = (string) ( $result['message'] ?? __( 'Portfolio item not found.', 'wp-sell-services' ) );

			// A refused limit is a 409, not a 404: the item exists and the caller
			// owns it, the marketplace rule is what says no.
			$is_missing = false !== strpos( $message, 'not found' );

			return new WP_Error(
				$is_missing ? 'not_found' : 'wpss_featured_limit',
				$message,
				array( 'status' => $is_missing ? 404 : 409 )
			);
		}

		$item = $this->get_portfolio_item( $item_id );

		return new WP_REST_Response( $this->format_item( $item ) );
	}

	/**
	 * Reorder portfolio items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder( WP_REST_Request $request ) {
		$order = $request->get_param( 'order' );

		global $wpdb;
		$table     = $wpdb->prefix . 'wpss_portfolio_items';
		$vendor_id = get_current_user_id();

		foreach ( $order as $position => $item_id ) {
			$wpdb->update(
				$table,
				array( 'sort_order' => $position ),
				array(
					'id'        => (int) $item_id,
					'vendor_id' => $vendor_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		return new WP_REST_Response( array( 'success' => true ) );
	}


	/**
	 * Check owner permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_owner_permissions( WP_REST_Request $request ) {
		$perm_check = $this->check_vendor_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$item = $this->get_portfolio_item( (int) $request->get_param( 'id' ) );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Portfolio item not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( (int) $item['vendor_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wpss_not_owner', __( 'You do not own this portfolio item.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Get portfolio item from DB.
	 *
	 * @param int $item_id Item ID.
	 * @return array|null
	 */
	private function get_portfolio_item( int $item_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $item_id ),
			ARRAY_A
		);

		return $item ?: null;
	}

	/**
	 * Format item for response.
	 *
	 * @param array $item Raw portfolio data.
	 * @return array
	 */
	private function format_item( array $item ): array {
		$media      = json_decode( $item['media'] ?? '[]', true );
		$media_urls = array();

		if ( is_array( $media ) ) {
			foreach ( $media as $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$media_urls[] = array(
						'id'        => $attachment_id,
						'url'       => $url,
						'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					);
				}
			}
		}

		return array(
			'id'           => (int) $item['id'],
			'vendor_id'    => (int) $item['vendor_id'],
			'title'        => $item['title'],
			'description'  => $item['description'] ?? '',
			'media'        => $media_urls,
			'service_id'   => (int) ( $item['service_id'] ?? 0 ),
			'external_url' => $item['external_url'] ?? '',
			'is_featured'  => (bool) ( $item['is_featured'] ?? false ),
			'sort_order'   => (int) ( $item['sort_order'] ?? 0 ),
			'created_at'   => $this->format_datetime( $item['created_at'] ?? null ),
		);
	}
}
