<?php
/**
 * Extension Requests REST Controller
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
 * REST controller for order deadline extension requests.
 *
 * @since 1.0.0
 */
class ExtensionRequestsController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /orders/{order_id}/extensions - Get extension requests.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/extensions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_order_access' ),
				),
			)
		);

		// POST /orders/{order_id}/extensions - Create extension request.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/extensions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_order_access' ),
					'args'                => array(
						'extra_days' => array(
							'description' => __( 'Number of additional days requested.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
							'minimum'     => 1,
						),
						'reason'     => array(
							'description' => __( 'Reason for the extension.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// POST /orders/{order_id}/extensions/{id}/approve - Approve.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/extensions/(?P<id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve' ),
					'permission_callback' => array( $this, 'check_order_access' ),
					'args'                => array(
						'response_message' => array(
							'description' => __( 'Response message.', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
			)
		);

		// POST /orders/{order_id}/extensions/{id}/reject - Reject.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/extensions/(?P<id>[\d]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject' ),
					'permission_callback' => array( $this, 'check_order_access' ),
					'args'                => array(
						'response_message' => array(
							'description' => __( 'Reason for rejection.', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Get extension requests for order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;

		$order_id = (int) $request->get_param( 'order_id' );
		$table    = $wpdb->prefix . 'wpss_extension_requests';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			),
			ARRAY_A
		);

		$extensions = array_map( array( $this, 'format_item' ), $items ?: array() );

		return new WP_REST_Response( $extensions );
	}

	/**
	 * Create extension request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		global $wpdb;

		$order_id   = (int) $request->get_param( 'order_id' );
		$extra_days = (int) $request->get_param( 'extra_days' );
		$reason     = sanitize_textarea_field( $request->get_param( 'reason' ) );
		$user_id    = get_current_user_id();
		$table      = $wpdb->prefix . 'wpss_extension_requests';

		// Check for existing pending request.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND status = 'pending'",
				$order_id
			)
		);

		if ( $existing > 0 ) {
			return new WP_Error( 'pending_exists', __( 'There is already a pending extension request for this order.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$table,
			array(
				'order_id'     => $order_id,
				'requested_by' => $user_id,
				'extra_days'   => $extra_days,
				'reason'       => $reason,
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$request_id = (int) $wpdb->insert_id;

		if ( ! $request_id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create extension request.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after an extension request is created.
		 *
		 * @param int $request_id Extension request ID.
		 * @param int $order_id   Order ID.
		 * @param int $user_id    User who requested.
		 */
		do_action( 'wpss_extension_requested', $request_id, $order_id, $user_id );

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->format_item( $item ), 201 );
	}

	/**
	 * Approve extension request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		return $this->respond_to_request( $request, 'approved' );
	}

	/**
	 * Reject extension request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject( WP_REST_Request $request ) {
		return $this->respond_to_request( $request, 'rejected' );
	}

	/**
	 * Respond to extension request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $status  New status.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond_to_request( WP_REST_Request $request, string $status ) {
		global $wpdb;

		$ext_id           = (int) $request->get_param( 'id' );
		$order_id         = (int) $request->get_param( 'order_id' );
		$response_message = sanitize_textarea_field( $request->get_param( 'response_message' ) ?: '' );
		$table            = $wpdb->prefix . 'wpss_extension_requests';

		$ext_request = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND order_id = %d", $ext_id, $order_id ),
			ARRAY_A
		);

		if ( ! $ext_request ) {
			return new WP_Error( 'not_found', __( 'Extension request not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( 'pending' !== $ext_request['status'] ) {
			return new WP_Error( 'invalid_status', __( 'This request has already been responded to.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Requester cannot approve/reject own request.
		if ( (int) $ext_request['requested_by'] === get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You cannot respond to your own request.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		$wpdb->update(
			$table,
			array(
				'status'           => $status,
				'responded_by'     => get_current_user_id(),
				'response_message' => $response_message,
				'responded_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => $ext_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		// If approved, extend the order due date.
		if ( 'approved' === $status ) {
			$orders_table = $wpdb->prefix . 'wpss_orders';

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$orders_table} SET due_date = DATE_ADD(due_date, INTERVAL %d DAY) WHERE id = %d",
					(int) $ext_request['extra_days'],
					$order_id
				)
			);

			/**
			 * Fires after an extension is approved and due date updated.
			 *
			 * @param int $ext_id    Extension request ID.
			 * @param int $order_id  Order ID.
			 * @param int $extra_days Days added.
			 */
			do_action( 'wpss_extension_approved', $ext_id, $order_id, (int) $ext_request['extra_days'] );
		}

		$updated = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $ext_id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->format_item( $updated ) );
	}

	/**
	 * Check order access.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_order_access( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$order_id = (int) $request->get_param( 'order_id' );

		if ( ! $this->user_owns_resource( $order_id, 'order' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have access to this order.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Format item for response.
	 *
	 * @param array $item Raw data.
	 * @return array
	 */
	private function format_item( array $item ): array {
		$requester = get_user_by( 'id', $item['requested_by'] );
		$responder = ! empty( $item['responded_by'] ) ? get_user_by( 'id', $item['responded_by'] ) : null;

		return array(
			'id'               => (int) $item['id'],
			'order_id'         => (int) $item['order_id'],
			'requested_by'     => array(
				'id'   => (int) $item['requested_by'],
				'name' => $requester ? $requester->display_name : __( 'Unknown', 'wp-sell-services' ),
			),
			'extra_days'       => (int) $item['extra_days'],
			'reason'           => $item['reason'],
			'status'           => $item['status'],
			'responded_by'     => $responder
				? array(
					'id'   => (int) $item['responded_by'],
					'name' => $responder->display_name,
				)
				: null,
			'response_message' => $item['response_message'] ?? '',
			'responded_at'     => $item['responded_at'] ?? null,
			'created_at'       => $item['created_at'],
		);
	}
}
