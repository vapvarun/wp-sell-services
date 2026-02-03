<?php
/**
 * Portfolio REST Controller
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'title'       => array(
							'description' => __( 'Portfolio item title.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'description' => array(
							'description' => __( 'Portfolio item description.', 'wp-sell-services' ),
							'type'        => 'string',
						),
						'images'      => array(
							'description' => __( 'Attachment IDs.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
						),
						'service_id'  => array(
							'description' => __( 'Related service ID.', 'wp-sell-services' ),
							'type'        => 'integer',
						),
						'url'         => array(
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
		$images    = $request->get_param( 'images' );

		$wpdb->insert(
			$table,
			array(
				'vendor_id'   => $vendor_id,
				'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
				'description' => sanitize_textarea_field( $request->get_param( 'description' ) ?: '' ),
				'images'      => wp_json_encode( is_array( $images ) ? array_map( 'intval', $images ) : array() ),
				'service_id'  => (int) $request->get_param( 'service_id' ),
				'url'         => esc_url_raw( $request->get_param( 'url' ) ?: '' ),
				'is_featured' => 0,
				'sort_order'  => 0,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
		);

		$item_id = $wpdb->insert_id;

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

		if ( $request->has_param( 'images' ) ) {
			$images          = $request->get_param( 'images' );
			$update['images'] = wp_json_encode( is_array( $images ) ? array_map( 'intval', $images ) : array() );
			$format[]        = '%s';
		}

		if ( $request->has_param( 'service_id' ) ) {
			$update['service_id'] = (int) $request->get_param( 'service_id' );
			$format[]             = '%d';
		}

		if ( $request->has_param( 'url' ) ) {
			$update['url'] = esc_url_raw( $request->get_param( 'url' ) );
			$format[]      = '%s';
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

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		$item = $this->get_portfolio_item( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Portfolio item not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$new_featured = $item['is_featured'] ? 0 : 1;
		$wpdb->update( $table, array( 'is_featured' => $new_featured ), array( 'id' => $item_id ), array( '%d' ), array( '%d' ) );

		$item['is_featured'] = $new_featured;

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
	 * Check vendor permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_permissions( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		if ( ! get_user_meta( get_current_user_id(), '_wpss_is_vendor', true ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Only vendors can manage portfolio items.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
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
			return new WP_Error( 'rest_forbidden', __( 'You do not own this portfolio item.', 'wp-sell-services' ), array( 'status' => 403 ) );
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
		$images     = json_decode( $item['images'] ?? '[]', true );
		$image_urls = array();

		if ( is_array( $images ) ) {
			foreach ( $images as $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$image_urls[] = array(
						'id'        => $attachment_id,
						'url'       => $url,
						'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					);
				}
			}
		}

		return array(
			'id'          => (int) $item['id'],
			'vendor_id'   => (int) $item['vendor_id'],
			'title'       => $item['title'],
			'description' => $item['description'] ?? '',
			'images'      => $image_urls,
			'service_id'  => (int) ( $item['service_id'] ?? 0 ),
			'url'         => $item['url'] ?? '',
			'is_featured' => (bool) ( $item['is_featured'] ?? false ),
			'sort_order'  => (int) ( $item['sort_order'] ?? 0 ),
			'created_at'  => $item['created_at'] ?? '',
		);
	}
}
