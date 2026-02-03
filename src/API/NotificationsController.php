<?php
/**
 * Notifications REST Controller
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
 * REST controller for notifications.
 *
 * @since 1.0.0
 */
class NotificationsController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'notifications';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /notifications - List user notifications.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET /notifications/unread-count - Get unread count.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unread-count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_unread_count' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /notifications/{id}/read - Mark as read.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/read',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_as_read' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Notification ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);

		// POST /notifications/read-all - Mark all as read.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/read-all',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_all_as_read' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// DELETE /notifications/{id} - Delete notification.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get user notifications.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_args( $request );
		$user_id    = get_current_user_id();

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

		// Filter by type.
		$type = $request->get_param( 'type' );
		if ( $type ) {
			$where .= $wpdb->prepare( ' AND type = %s', sanitize_text_field( $type ) );
		}

		// Filter by read status.
		$is_read = $request->get_param( 'is_read' );
		if ( null !== $is_read ) {
			$where .= $wpdb->prepare( ' AND is_read = %d', (int) $is_read );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);

		$notifications = array_map( array( $this, 'format_notification' ), $items ?: array() );

		return $this->paginated_response(
			$notifications,
			$total,
			$pagination['page'],
			$pagination['per_page']
		);
	}

	/**
	 * Get unread count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_unread_count( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
				get_current_user_id()
			)
		);

		return new WP_REST_Response( array( 'unread_count' => $count ) );
	}

	/**
	 * Mark notification as read.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_as_read( WP_REST_Request $request ) {
		$notification_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$notification = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
				$notification_id,
				get_current_user_id()
			)
		);

		if ( ! $notification ) {
			return new WP_Error(
				'not_found',
				__( 'Notification not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array( 'id' => $notification_id ),
			array( '%d' ),
			array( '%d' )
		);

		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function mark_all_as_read( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array(
				'user_id' => get_current_user_id(),
				'is_read' => 0,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Delete notification.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$notification_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		$deleted = $wpdb->delete(
			$table,
			array(
				'id'      => $notification_id,
				'user_id' => get_current_user_id(),
			),
			array( '%d', '%d' )
		);

		if ( ! $deleted ) {
			return new WP_Error(
				'not_found',
				__( 'Notification not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Format notification for response.
	 *
	 * @param array $notification Raw notification data.
	 * @return array
	 */
	private function format_notification( array $notification ): array {
		$data = json_decode( $notification['data'] ?? '{}', true );

		return array(
			'id'         => (int) $notification['id'],
			'type'       => $notification['type'],
			'title'      => $notification['title'],
			'message'    => $notification['message'],
			'data'       => $data ?: array(),
			'is_read'    => (bool) $notification['is_read'],
			'created_at' => $notification['created_at'],
		);
	}

	/**
	 * Get collection params.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array(
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 20,
				'maximum'     => 100,
			),
			'type'     => array(
				'description' => __( 'Filter by notification type.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'is_read'  => array(
				'description' => __( 'Filter by read status.', 'wp-sell-services' ),
				'type'        => 'boolean',
			),
		);
	}
}
