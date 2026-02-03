<?php
/**
 * Seller Levels REST Controller
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
 * REST controller for seller levels and progression.
 *
 * @since 1.0.0
 */
class SellerLevelsController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'seller-levels';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /seller-levels - Get all level definitions (public).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_levels' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /seller-levels/{level} - Get specific level requirements (public).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<level>[a-z_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_level' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /vendors/me/level - Get current vendor level and progress.
		register_rest_route(
			$this->namespace,
			'/vendors/me/level',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_my_level' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
				),
			)
		);

		// GET /vendors/{id}/level - Get vendor level (public).
		register_rest_route(
			$this->namespace,
			'/vendors/(?P<vendor_id>[\d]+)/level',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_level' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Get all level definitions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_levels( WP_REST_Request $request ): WP_REST_Response {
		$levels = $this->get_level_definitions();

		return new WP_REST_Response( $levels );
	}

	/**
	 * Get specific level definition.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_level( WP_REST_Request $request ) {
		$level_key = sanitize_text_field( $request->get_param( 'level' ) );
		$levels    = $this->get_level_definitions();

		if ( ! isset( $levels[ $level_key ] ) ) {
			return new WP_Error( 'not_found', __( 'Level not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $levels[ $level_key ] );
	}

	/**
	 * Get current vendor's level and progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_my_level( WP_REST_Request $request ): WP_REST_Response {
		$vendor_id = get_current_user_id();

		return new WP_REST_Response( $this->build_vendor_level_data( $vendor_id, true ) );
	}

	/**
	 * Get vendor level (public).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vendor_level( WP_REST_Request $request ): WP_REST_Response {
		$vendor_id = (int) $request->get_param( 'vendor_id' );

		return new WP_REST_Response( $this->build_vendor_level_data( $vendor_id, false ) );
	}

	/**
	 * Build vendor level data.
	 *
	 * @param int  $vendor_id     Vendor ID.
	 * @param bool $include_progress Whether to include progress details.
	 * @return array
	 */
	private function build_vendor_level_data( int $vendor_id, bool $include_progress ): array {
		$current_level = get_user_meta( $vendor_id, '_wpss_seller_level', true ) ?: 'new';
		$levels        = $this->get_level_definitions();
		$label         = $levels[ $current_level ]['label'] ?? ucfirst( $current_level );

		$data = array(
			'current_level' => $current_level,
			'label'         => $label,
		);

		if ( $include_progress ) {
			$stats = $this->get_vendor_stats( $vendor_id );

			// Determine next level.
			$level_keys = array_keys( $levels );
			$current_idx = array_search( $current_level, $level_keys, true );
			$next_level  = isset( $level_keys[ $current_idx + 1 ] ) ? $level_keys[ $current_idx + 1 ] : null;

			$data['stats'] = $stats;

			if ( $next_level && isset( $levels[ $next_level ]['requirements'] ) ) {
				$requirements = $levels[ $next_level ]['requirements'];
				$progress     = array();

				foreach ( $requirements as $metric => $target ) {
					$current_value = $stats[ $metric ] ?? 0;
					$progress[ $metric ] = array(
						'current'    => $current_value,
						'target'     => $target,
						'percentage' => $target > 0 ? round( min( 100, ( $current_value / $target ) * 100 ), 1 ) : 100,
					);
				}

				$data['next_level'] = array(
					'key'          => $next_level,
					'label'        => $levels[ $next_level ]['label'],
					'progress'     => $progress,
				);
			} else {
				$data['next_level'] = null;
			}
		}

		return $data;
	}

	/**
	 * Get vendor stats.
	 *
	 * @param int $vendor_id Vendor ID.
	 * @return array
	 */
	private function get_vendor_stats( int $vendor_id ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					AVG(CASE WHEN status = 'completed' THEN total ELSE NULL END) as avg_order_value,
					SUM(CASE WHEN status = 'completed' THEN vendor_earning ELSE 0 END) as total_earnings
				FROM {$orders_table}
				WHERE vendor_id = %d",
				$vendor_id
			)
		);

		$rating_avg   = (float) get_user_meta( $vendor_id, '_wpss_rating_average', true ) ?: 0;
		$rating_count = (int) get_user_meta( $vendor_id, '_wpss_rating_count', true ) ?: 0;

		$completion_rate = $stats && (int) $stats->total_orders > 0
			? round( ( (int) $stats->completed_orders / (int) $stats->total_orders ) * 100, 1 )
			: 0;

		return array(
			'completed_orders' => (int) ( $stats->completed_orders ?? 0 ),
			'total_earnings'   => round( (float) ( $stats->total_earnings ?? 0 ), 2 ),
			'avg_rating'       => round( $rating_avg, 2 ),
			'total_reviews'    => $rating_count,
			'completion_rate'  => $completion_rate,
		);
	}

	/**
	 * Get level definitions.
	 *
	 * @return array
	 */
	private function get_level_definitions(): array {
		$levels = array(
			'new'       => array(
				'key'          => 'new',
				'label'        => __( 'New Seller', 'wp-sell-services' ),
				'requirements' => array(),
			),
			'level_1'   => array(
				'key'          => 'level_1',
				'label'        => __( 'Level 1 Seller', 'wp-sell-services' ),
				'requirements' => array(
					'completed_orders' => 10,
					'avg_rating'       => 4.0,
					'completion_rate'  => 80,
				),
			),
			'level_2'   => array(
				'key'          => 'level_2',
				'label'        => __( 'Level 2 Seller', 'wp-sell-services' ),
				'requirements' => array(
					'completed_orders' => 50,
					'total_earnings'   => 2000,
					'avg_rating'       => 4.5,
					'completion_rate'  => 90,
				),
			),
			'top_rated' => array(
				'key'          => 'top_rated',
				'label'        => __( 'Top Rated Seller', 'wp-sell-services' ),
				'requirements' => array(
					'completed_orders' => 100,
					'total_earnings'   => 10000,
					'avg_rating'       => 4.7,
					'completion_rate'  => 95,
				),
			),
		);

		/**
		 * Filter seller level definitions.
		 *
		 * @param array $levels Level definitions.
		 */
		return apply_filters( 'wpss_seller_levels', $levels );
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
			return new WP_Error( 'rest_forbidden', __( 'Only vendors can access this endpoint.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
