<?php
/**
 * Moderation REST Controller
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

/**
 * REST controller for service moderation (admin).
 *
 * @since 1.0.0
 */
class ModerationController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'moderation';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /moderation/pending - Get pending services.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pending',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pending' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
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

		// GET /moderation/count - Get pending count.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_count' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		// POST /moderation/{service_id}/approve - Approve service.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<service_id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'notes' => array(
							'description' => __( 'Approval notes.', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
			)
		);

		// POST /moderation/{service_id}/reject - Reject service.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<service_id>[\d]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'reason' => array(
							'description' => __( 'Rejection reason.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// GET /moderation/{service_id} - Get moderation history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<service_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_history' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);
	}

	/**
	 * Get pending services.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_pending( WP_REST_Request $request ): WP_REST_Response {
		$pagination = $this->get_pagination_args( $request );

		$query = new WP_Query(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'pending',
				'posts_per_page' => $pagination['per_page'],
				'offset'         => $pagination['offset'],
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		$services = array();
		foreach ( $query->posts as $post ) {
			$author = get_user_by( 'id', $post->post_author );

			$services[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'excerpt'     => wp_trim_words( $post->post_content, 30 ),
				'vendor'      => array(
					'id'     => (int) $post->post_author,
					'name'   => $author ? $author->display_name : __( 'Unknown', 'wp-sell-services' ),
					'avatar' => get_avatar_url( $post->post_author, array( 'size' => 48 ) ),
				),
				'categories'  => wp_get_object_terms( $post->ID, 'wpss_service_category', array( 'fields' => 'names' ) ),
				'price'       => (float) get_post_meta( $post->ID, '_wpss_starting_price', true ),
				'submitted_at' => $post->post_date_gmt,
			);
		}

		return $this->paginated_response( $services, $query->found_posts, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get pending count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_count( WP_REST_Request $request ): WP_REST_Response {
		$count = wp_count_posts( 'wpss_service' );

		return new WP_REST_Response(
			array(
				'pending' => (int) ( $count->pending ?? 0 ),
			)
		);
	}

	/**
	 * Approve service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$notes      = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );
		$service    = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'publish',
			)
		);

		// Store moderation history.
		$this->add_moderation_entry( $service_id, 'approved', $notes );

		/**
		 * Fires after a service is approved via moderation.
		 *
		 * @param int    $service_id Service ID.
		 * @param string $notes      Approval notes.
		 */
		do_action( 'wpss_service_approved', $service_id, $notes );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'service_id' => $service_id,
				'status'     => 'publish',
			)
		);
	}

	/**
	 * Reject service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$reason     = sanitize_textarea_field( $request->get_param( 'reason' ) );
		$service    = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'draft',
			)
		);

		$this->add_moderation_entry( $service_id, 'rejected', $reason );

		/**
		 * Fires after a service is rejected via moderation.
		 *
		 * @param int    $service_id Service ID.
		 * @param string $reason     Rejection reason.
		 */
		do_action( 'wpss_service_rejected', $service_id, $reason );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'service_id' => $service_id,
				'status'     => 'draft',
			)
		);
	}

	/**
	 * Get moderation history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_history( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$service    = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$history = get_post_meta( $service_id, '_wpss_moderation_history', true );

		return new WP_REST_Response( is_array( $history ) ? $history : array() );
	}

	/**
	 * Add moderation entry.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $action     Action taken.
	 * @param string $notes      Notes/reason.
	 * @return void
	 */
	private function add_moderation_entry( int $service_id, string $action, string $notes ): void {
		$history = get_post_meta( $service_id, '_wpss_moderation_history', true );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = array(
			'action'     => $action,
			'notes'      => $notes,
			'admin_id'   => get_current_user_id(),
			'admin_name' => wp_get_current_user()->display_name,
			'date'       => current_time( 'mysql', true ),
		);

		update_post_meta( $service_id, '_wpss_moderation_history', $history );
	}
}
