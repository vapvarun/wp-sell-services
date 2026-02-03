<?php
/**
 * Favorites REST Controller
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
 * REST controller for service favorites/wishlist.
 *
 * @since 1.0.0
 */
class FavoritesController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'favorites';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /favorites - Get user's favorites.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'maximum' => 100,
						),
					),
				),
			)
		);

		// POST /favorites/{service_id} - Add to favorites.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<service_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_favorite' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// DELETE /favorites/{service_id} - Remove from favorites.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<service_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_favorite' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /services/{id}/favorited - Check if favorited.
		register_rest_route(
			$this->namespace,
			'/services/(?P<service_id>[\d]+)/favorited',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'check_favorited' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get user's favorite services.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$user_id    = get_current_user_id();
		$pagination = $this->get_pagination_args( $request );
		$favorites  = get_user_meta( $user_id, '_wpss_favorites', true );

		if ( ! is_array( $favorites ) || empty( $favorites ) ) {
			return $this->paginated_response( array(), 0, $pagination['page'], $pagination['per_page'] );
		}

		$total = count( $favorites );

		// Paginate the favorites.
		$paged_ids = array_slice( $favorites, $pagination['offset'], $pagination['per_page'] );

		if ( empty( $paged_ids ) ) {
			return $this->paginated_response( array(), $total, $pagination['page'], $pagination['per_page'] );
		}

		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'publish',
				'post__in'       => array_map( 'intval', $paged_ids ),
				'posts_per_page' => count( $paged_ids ),
				'orderby'        => 'post__in',
			)
		);

		$items = array();
		foreach ( $services as $service ) {
			$items[] = array(
				'id'        => $service->ID,
				'title'     => $service->post_title,
				'slug'      => $service->post_name,
				'excerpt'   => wp_trim_words( $service->post_excerpt ?: $service->post_content, 20 ),
				'thumbnail' => get_the_post_thumbnail_url( $service->ID, 'medium' ) ?: '',
				'price'     => (float) get_post_meta( $service->ID, '_wpss_starting_price', true ),
				'rating'    => (float) get_post_meta( $service->ID, '_wpss_rating_average', true ) ?: 0,
				'vendor'    => array(
					'id'     => (int) $service->post_author,
					'name'   => get_the_author_meta( 'display_name', $service->post_author ),
					'avatar' => get_avatar_url( $service->post_author, array( 'size' => 48 ) ),
				),
			);
		}

		return $this->paginated_response( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Add service to favorites.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_favorite( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$user_id    = get_current_user_id();

		// Verify service exists.
		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$favorites = get_user_meta( $user_id, '_wpss_favorites', true );
		if ( ! is_array( $favorites ) ) {
			$favorites = array();
		}

		if ( ! in_array( $service_id, $favorites, true ) ) {
			$favorites[] = $service_id;
			update_user_meta( $user_id, '_wpss_favorites', $favorites );
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'favorited'  => true,
				'service_id' => $service_id,
			),
			201
		);
	}

	/**
	 * Remove service from favorites.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function remove_favorite( WP_REST_Request $request ): WP_REST_Response {
		$service_id = (int) $request->get_param( 'service_id' );
		$user_id    = get_current_user_id();
		$favorites  = get_user_meta( $user_id, '_wpss_favorites', true );

		if ( is_array( $favorites ) ) {
			$favorites = array_values( array_diff( $favorites, array( $service_id ) ) );
			update_user_meta( $user_id, '_wpss_favorites', $favorites );
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'favorited'  => false,
				'service_id' => $service_id,
			)
		);
	}

	/**
	 * Check if service is favorited.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_favorited( WP_REST_Request $request ): WP_REST_Response {
		$service_id = (int) $request->get_param( 'service_id' );
		$user_id    = get_current_user_id();
		$favorites  = get_user_meta( $user_id, '_wpss_favorites', true );

		$is_favorited = is_array( $favorites ) && in_array( $service_id, $favorites, true );

		return new WP_REST_Response( array( 'favorited' => $is_favorited ) );
	}
}
