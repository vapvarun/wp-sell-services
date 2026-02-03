<?php
/**
 * Milestones REST Controller
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
 * REST controller for order milestones.
 *
 * @since 1.0.0
 */
class MilestonesController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$base = '/orders/(?P<order_id>[\d]+)/milestones';

		// GET /orders/{order_id}/milestones - List milestones.
		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_order_access' ),
				),
			)
		);

		// POST /orders/{order_id}/milestones - Create milestone.
		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
					'args'                => array(
						'title'        => array(
							'type'     => 'string',
							'required' => true,
						),
						'description'  => array(
							'type' => 'string',
						),
						'amount'       => array(
							'type'     => 'number',
							'required' => true,
						),
						'due_date'     => array(
							'type'   => 'string',
							'format' => 'date',
						),
					),
				),
			)
		);

		// GET /orders/{order_id}/milestones/progress - Get progress.
		register_rest_route(
			$this->namespace,
			$base . '/progress',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_progress' ),
					'permission_callback' => array( $this, 'check_order_access' ),
				),
			)
		);

		// PUT /orders/{order_id}/milestones/{id} - Update milestone.
		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
				),
			)
		);

		// DELETE /orders/{order_id}/milestones/{id} - Delete milestone.
		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
				),
			)
		);

		// POST /orders/{order_id}/milestones/{id}/submit - Submit for approval.
		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)/submit',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
					'args'                => array(
						'message'     => array(
							'type' => 'string',
						),
						'attachments' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		// POST /orders/{order_id}/milestones/{id}/approve - Approve.
		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve' ),
					'permission_callback' => array( $this, 'check_buyer_order_access' ),
				),
			)
		);

		// POST /orders/{order_id}/milestones/{id}/reject - Reject.
		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject' ),
					'permission_callback' => array( $this, 'check_buyer_order_access' ),
					'args'                => array(
						'feedback' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Get milestones for order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$order_id   = (int) $request->get_param( 'order_id' );
		$milestones = $this->get_order_milestones( $order_id );

		return new WP_REST_Response( $milestones );
	}

	/**
	 * Create milestone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$order_id   = (int) $request->get_param( 'order_id' );
		$milestones = $this->get_order_milestones( $order_id );

		$milestone = array(
			'id'          => wp_generate_uuid4(),
			'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
			'description' => sanitize_textarea_field( $request->get_param( 'description' ) ?: '' ),
			'amount'      => (float) $request->get_param( 'amount' ),
			'due_date'    => sanitize_text_field( $request->get_param( 'due_date' ) ?: '' ),
			'status'      => 'pending',
			'created_at'  => current_time( 'mysql', true ),
		);

		$milestones[] = $milestone;
		$this->save_order_milestones( $order_id, $milestones );

		return new WP_REST_Response( $milestone, 201 );
	}

	/**
	 * Update milestone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$order_id     = (int) $request->get_param( 'order_id' );
		$milestone_id = $request->get_param( 'id' );
		$milestones   = $this->get_order_milestones( $order_id );

		$index = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return new WP_Error( 'not_found', __( 'Milestone not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( $request->has_param( 'title' ) ) {
			$milestones[ $index ]['title'] = sanitize_text_field( $request->get_param( 'title' ) );
		}
		if ( $request->has_param( 'description' ) ) {
			$milestones[ $index ]['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
		}
		if ( $request->has_param( 'amount' ) ) {
			$milestones[ $index ]['amount'] = (float) $request->get_param( 'amount' );
		}
		if ( $request->has_param( 'due_date' ) ) {
			$milestones[ $index ]['due_date'] = sanitize_text_field( $request->get_param( 'due_date' ) );
		}

		$this->save_order_milestones( $order_id, $milestones );

		return new WP_REST_Response( $milestones[ $index ] );
	}

	/**
	 * Delete milestone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$order_id     = (int) $request->get_param( 'order_id' );
		$milestone_id = $request->get_param( 'id' );
		$milestones   = $this->get_order_milestones( $order_id );

		$index = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return new WP_Error( 'not_found', __( 'Milestone not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		array_splice( $milestones, $index, 1 );
		$this->save_order_milestones( $order_id, $milestones );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Submit milestone for approval.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit( WP_REST_Request $request ) {
		$order_id     = (int) $request->get_param( 'order_id' );
		$milestone_id = $request->get_param( 'id' );
		$milestones   = $this->get_order_milestones( $order_id );

		$index = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return new WP_Error( 'not_found', __( 'Milestone not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( $milestones[ $index ]['status'], array( 'pending', 'in_progress', 'rejected' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Milestone cannot be submitted in current status.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$milestones[ $index ]['status']       = 'submitted';
		$milestones[ $index ]['submitted_at']  = current_time( 'mysql', true );
		$milestones[ $index ]['message']       = sanitize_textarea_field( $request->get_param( 'message' ) ?: '' );
		$milestones[ $index ]['attachments']   = array_map( 'intval', $request->get_param( 'attachments' ) ?: array() );

		$this->save_order_milestones( $order_id, $milestones );

		do_action( 'wpss_milestone_submitted', $milestone_id, $order_id );

		return new WP_REST_Response( $milestones[ $index ] );
	}

	/**
	 * Approve milestone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		$order_id     = (int) $request->get_param( 'order_id' );
		$milestone_id = $request->get_param( 'id' );
		$milestones   = $this->get_order_milestones( $order_id );

		$index = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return new WP_Error( 'not_found', __( 'Milestone not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( 'submitted' !== $milestones[ $index ]['status'] ) {
			return new WP_Error( 'invalid_status', __( 'Only submitted milestones can be approved.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$milestones[ $index ]['status']      = 'approved';
		$milestones[ $index ]['approved_at']  = current_time( 'mysql', true );

		$this->save_order_milestones( $order_id, $milestones );

		do_action( 'wpss_milestone_approved', $milestone_id, $order_id );

		return new WP_REST_Response( $milestones[ $index ] );
	}

	/**
	 * Reject milestone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject( WP_REST_Request $request ) {
		$order_id     = (int) $request->get_param( 'order_id' );
		$milestone_id = $request->get_param( 'id' );
		$milestones   = $this->get_order_milestones( $order_id );

		$index = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return new WP_Error( 'not_found', __( 'Milestone not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( 'submitted' !== $milestones[ $index ]['status'] ) {
			return new WP_Error( 'invalid_status', __( 'Only submitted milestones can be rejected.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$milestones[ $index ]['status']   = 'rejected';
		$milestones[ $index ]['feedback']  = sanitize_textarea_field( $request->get_param( 'feedback' ) );

		$this->save_order_milestones( $order_id, $milestones );

		do_action( 'wpss_milestone_rejected', $milestone_id, $order_id );

		return new WP_REST_Response( $milestones[ $index ] );
	}

	/**
	 * Get milestone progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_progress( WP_REST_Request $request ): WP_REST_Response {
		$order_id   = (int) $request->get_param( 'order_id' );
		$milestones = $this->get_order_milestones( $order_id );

		$total    = count( $milestones );
		$approved = 0;

		foreach ( $milestones as $m ) {
			if ( 'approved' === ( $m['status'] ?? '' ) ) {
				++$approved;
			}
		}

		return new WP_REST_Response(
			array(
				'total'      => $total,
				'approved'   => $approved,
				'percentage' => $total > 0 ? round( ( $approved / $total ) * 100, 1 ) : 0,
			)
		);
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
	 * Check vendor access to order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_order_access( WP_REST_Request $request ) {
		$perm_check = $this->check_order_access( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$order_id = (int) $request->get_param( 'order_id' );
		$order    = $this->get_order( $order_id );

		if ( $order && (int) $order->vendor_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Only the vendor can perform this action.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check buyer access to order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_buyer_order_access( WP_REST_Request $request ) {
		$perm_check = $this->check_order_access( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$order_id = (int) $request->get_param( 'order_id' );
		$order    = $this->get_order( $order_id );

		if ( $order && (int) $order->customer_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Only the buyer can perform this action.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Get order from DB.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	private function get_order( int $order_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id )
		);
	}

	/**
	 * Get milestones for order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	private function get_order_milestones( int $order_id ): array {
		$order = $this->get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		$milestones = json_decode( $order->milestones ?? '[]', true );

		return is_array( $milestones ) ? $milestones : array();
	}

	/**
	 * Save milestones for order.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $milestones Milestones data.
	 * @return void
	 */
	private function save_order_milestones( int $order_id, array $milestones ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$wpdb->update(
			$table,
			array( 'milestones' => wp_json_encode( $milestones ) ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Find milestone index by ID.
	 *
	 * @param array  $milestones  Milestones array.
	 * @param string $milestone_id Milestone ID (UUID or numeric).
	 * @return int|false
	 */
	private function find_milestone_index( array $milestones, string $milestone_id ) {
		foreach ( $milestones as $index => $milestone ) {
			if ( ( $milestone['id'] ?? '' ) === $milestone_id ) {
				return $index;
			}
		}

		return false;
	}
}
