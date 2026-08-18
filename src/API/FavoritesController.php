<?php
/**
 * Favorites REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

use WPSellServices\Services\FavoritesService;
defined( 'ABSPATH' ) || exit;

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
		$favorites  = FavoritesService::get_ids( $user_id );

		if ( empty( $favorites ) ) {
			return $this->paginated_response( array(), 0, $pagination['page'], $pagination['per_page'] );
		}

		// Count what the client will actually receive, not what the meta holds.
		// The stored ID list can name services that were since deleted or
		// unpublished, while the body below is filtered to published ones — so
		// the total described a different set than the items, and a single
		// orphaned ID produced a "Favorites (1)" badge over an empty list.
		//
		// Bounded by one user's own favourites, so the unbounded query is the
		// size of their list, not the catalogue.
		$favorites = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'publish',
				'post__in'       => array_map( 'intval', $favorites ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'post__in',
			)
		);

		if ( empty( $favorites ) ) {
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
			/*
			 * A favourite IS a service card, so it is built from the one card
			 * shape rather than a third hand-rolled copy (Basecamp 10154919636).
			 * This endpoint used to return `thumbnail` as a bare string where
			 * /services returned `images[]`, a flat `price` where /services
			 * returned `pricing{}`, and its own {id,name,avatar} vendor - not
			 * out of disagreement, but because the pieces it needed were
			 * PRIVATE methods on ServicesController and could not be called
			 * from here. They are shared functions now.
			 *
			 * The legacy keys are merged ON TOP and deliberately kept. Removing
			 * or retyping a field is a contract change, and src/API/API.php is
			 * explicit that bumping contract_version bricks every build already
			 * shipped. So a client gets the canonical shape AND everything it
			 * reads today.
			 */
			$items[] = array_merge(
				wpss_rest_service_card( $service ),
				array(
					// Kept at 20 words. /services returns the excerpt whole, so
					// this is the one field whose MEANING still differs between
					// the two endpoints - and lengthening the text a shipped app
					// is already laying out is not a change to make silently.
					'excerpt'   => wp_trim_words( $service->post_excerpt ?: $service->post_content, 20 ),
					// Superseded by images[], which carries the same URL under
					// images[0].sizes.medium plus the gallery and the ids.
					'thumbnail' => get_the_post_thumbnail_url( $service->ID, 'medium' ) ?: '',
				),
				// Superseded by pricing{}. Same number, flat keys: price,
				// price_minor, currency. Services carry no currency of their
				// own - they are priced in the store currency, the helper's
				// default.
				wpss_rest_money( 'price', (float) get_post_meta( $service->ID, '_wpss_starting_price', true ) ),
				array(
					/*
					 * THE ONE KEY THAT COULD NOT BE UNIFIED.
					 *
					 * Canonically `rating` is {average, count}; here it is a
					 * float. Same name, different TYPE, so this is the single
					 * case where additive is impossible - a client doing
					 * rating.toFixed() would crash on an object. It therefore
					 * stays a float until the next contract_version bump, at
					 * which point deleting these four lines is the whole fix.
					 *
					 * Note it also reads a different source: the cached
					 * _wpss_rating_average meta, where the canonical shape
					 * counts approved reviews live. They can disagree.
					 */
					'rating' => (float) get_post_meta( $service->ID, '_wpss_rating_average', true ) ?: 0,
				)
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

		$favorites = FavoritesService::add( $user_id, $service_id );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'favorited'  => true,
				'service_id' => $service_id,
				'count'      => count( $favorites ),
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
		$favorites  = FavoritesService::remove( $user_id, $service_id );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'favorited'  => false,
				'service_id' => $service_id,
				'count'      => count( $favorites ),
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
		$service_id   = (int) $request->get_param( 'service_id' );
		$user_id      = get_current_user_id();
		$is_favorited = FavoritesService::is_favorited( $user_id, $service_id );

		return new WP_REST_Response( array( 'favorited' => $is_favorited ) );
	}
}
