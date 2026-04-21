<?php
/**
 * Milestones REST Controller
 *
 * Mobile-facing endpoints for the paid milestone sub-order flow.
 * Proxies to {@see \WPSellServices\Services\MilestoneService} so the
 * REST layer and the dashboard AJAX endpoints share one code path —
 * any validation or state-transition rule is enforced in the service,
 * never duplicated here.
 *
 * @package WPSellServices\API
 * @since   1.1.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPSellServices\Services\MilestoneService;

/**
 * REST controller for milestone sub-orders.
 *
 * @since 1.1.0
 */
class MilestonesController extends RestController {

	/**
	 * Milestone service instance.
	 *
	 * @var MilestoneService
	 */
	private MilestoneService $milestones;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->milestones = new MilestoneService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$base = '/orders/(?P<order_id>[\d]+)/milestones';

		// List milestones on a parent order (both buyer and vendor).
		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_for_parent' ),
					'permission_callback' => array( $this, 'check_order_access' ),
				),
			)
		);

		// Propose a new milestone on a parent order (vendor only).
		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'propose' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
					'args'                => array(
						'title'        => array( 'type' => 'string', 'required' => true ),
						'description'  => array( 'type' => 'string' ),
						'amount'       => array( 'type' => 'number', 'required' => true ),
						'days'         => array( 'type' => 'integer' ),
						'deliverables' => array( 'type' => 'string' ),
					),
				),
			)
		);

		// Vendor submits a milestone as delivered.
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)/submit',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'submit_milestone' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'note' => array( 'type' => 'string' ),
					),
				),
			)
		);

		// Buyer approves a submitted milestone.
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'approve_milestone' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Buyer declines an unpaid milestone (or vendor cancels proposal).
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_milestone' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * GET /orders/{order_id}/milestones
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function list_for_parent( WP_REST_Request $request ): WP_REST_Response {
		$order_id = (int) $request->get_param( 'order_id' );
		return new WP_REST_Response( $this->milestones->get_for_parent( $order_id ) );
	}

	/**
	 * POST /orders/{order_id}/milestones
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function propose( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$result   = $this->milestones->propose(
			$order_id,
			get_current_user_id(),
			(string) $request->get_param( 'title' ),
			(string) ( $request->get_param( 'description' ) ?? '' ),
			(float) $request->get_param( 'amount' ),
			(int) ( $request->get_param( 'days' ) ?? 0 ),
			(string) ( $request->get_param( 'deliverables' ) ?? '' )
		);

		if ( ! $result['success'] ) {
			return new WP_Error( 'wpss_milestone_failed', $result['message'], array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * POST /milestones/{id}/submit
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_milestone( WP_REST_Request $request ) {
		$result = $this->milestones->submit(
			(int) $request->get_param( 'id' ),
			get_current_user_id(),
			(string) ( $request->get_param( 'note' ) ?? '' )
		);

		if ( ! $result['success'] ) {
			return new WP_Error( 'wpss_milestone_failed', $result['message'], array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * POST /milestones/{id}/approve
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_milestone( WP_REST_Request $request ) {
		$result = $this->milestones->approve( (int) $request->get_param( 'id' ), get_current_user_id() );

		if ( ! $result['success'] ) {
			return new WP_Error( 'wpss_milestone_failed', $result['message'], array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * DELETE /milestones/{id}
	 *
	 * Routes to decline (buyer) or delete_unpaid (vendor) depending on
	 * the current user's relationship to the parent order. Keeps the
	 * client surface to a single endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_milestone( WP_REST_Request $request ) {
		$milestone_id = (int) $request->get_param( 'id' );
		$sub          = wpss_get_order( $milestone_id );

		if ( ! $sub || MilestoneService::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return new WP_Error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$user_id = get_current_user_id();
		if ( (int) $sub->customer_id === $user_id ) {
			$result = $this->milestones->decline( $milestone_id, $user_id );
		} elseif ( (int) $sub->vendor_id === $user_id ) {
			$result = $this->milestones->delete_unpaid( $milestone_id, $user_id );
		} else {
			return new WP_Error( 'wpss_forbidden', __( 'You cannot cancel this milestone.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		if ( ! $result['success'] ) {
			return new WP_Error( 'wpss_milestone_failed', $result['message'], array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Access gate for the parent-order based endpoints — allows either
	 * the buyer or the vendor of the parent order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_order_access( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$order = wpss_get_order( (int) $request->get_param( 'order_id' ) );
		if ( ! $order ) {
			return false;
		}

		$user_id = get_current_user_id();
		return $user_id === (int) $order->customer_id || $user_id === (int) $order->vendor_id;
	}

	/**
	 * Vendor-only gate for proposing a new milestone on a parent order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_vendor_order_access( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$order = wpss_get_order( (int) $request->get_param( 'order_id' ) );
		if ( ! $order ) {
			return false;
		}

		return get_current_user_id() === (int) $order->vendor_id;
	}
}
