<?php
/**
 * Milestones REST Controller
 *
 * Mobile-facing endpoints for the paid milestone sub-order flow. Every route
 * proxies to {@see \WPSellServices\Services\MilestoneService} so the REST
 * surface and the dashboard AJAX handlers share one code path — any
 * validation or state-transition rule is enforced in the service, never
 * duplicated here.
 *
 * Error bodies follow the plugin convention:
 *   {
 *     "code":    "wpss_milestone_locked",
 *     "message": "...",
 *     "data":    { "status": 409 }
 *   }
 *
 * Lock-step mirrors {@see \WPSellServices\Services\MilestoneService::is_locked()}
 * and the checkout-trigger guard inside {@see StandaloneCheckoutProvider::render_pay_order_checkout()}:
 * a buyer cannot REST-initiate payment on phase N+1 until phase N is
 * completed or cancelled. Returning HTTP 409 here keeps the mobile client
 * and the web client on the same contract.
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
		$parent_base = '/orders/(?P<order_id>[\d]+)/milestones';

		// List milestones on a parent order (buyer OR vendor).
		register_rest_route(
			$this->namespace,
			$parent_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_for_parent' ),
					'permission_callback' => array( $this, 'check_order_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'propose' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
					'args'                => array(
						'title'        => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'amount'       => array(
							'type'     => 'number',
							'required' => true,
							'minimum'  => 0.01,
						),
						'days'         => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'deliverables' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// Buyer initiates payment on a specific milestone — guards the
		// lock-step rule so a crafted REST call cannot leapfrog phases.
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)/pay',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trigger_payment' ),
					'permission_callback' => array( $this, 'check_buyer_milestone_access' ),
				),
			)
		);

		// Vendor submits a milestone as delivered.
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)/submit',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_milestone' ),
					'permission_callback' => array( $this, 'check_vendor_milestone_access' ),
					'args'                => array(
						'note' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
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
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_milestone' ),
					'permission_callback' => array( $this, 'check_buyer_milestone_access' ),
				),
			)
		);

		// Buyer declines a pending_payment milestone (dedicated route).
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)/decline',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'decline_milestone' ),
					'permission_callback' => array( $this, 'check_buyer_milestone_access' ),
				),
			)
		);

		// Vendor cancels an unpaid milestone proposal (delete).
		register_rest_route(
			$this->namespace,
			'/milestones/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_milestone' ),
					'permission_callback' => array( $this, 'check_participant_milestone_access' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_milestone' ),
					'permission_callback' => array( $this, 'check_participant_milestone_access' ),
				),
			)
		);
	}

	/**
	 * GET /orders/{order_id}/milestones
	 *
	 * Returns the exact payload shape `MilestoneService::get_for_parent()`
	 * produces for the dashboard template — including the computed
	 * `is_locked` field that drives the lock-step UI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function list_for_parent( WP_REST_Request $request ): WP_REST_Response {
		$order_id   = (int) $request->get_param( 'order_id' );
		$milestones = $this->milestones->get_for_parent( $order_id );

		return new WP_REST_Response(
			array(
				'order_id'   => $order_id,
				'milestones' => $milestones,
				'count'      => count( $milestones ),
			),
			200
		);
	}

	/**
	 * GET /milestones/{id}
	 *
	 * Returns a single milestone decorated the same way the list endpoint
	 * does (reads from the parent's lock-step view so `is_locked` stays
	 * correct).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_milestone( WP_REST_Request $request ) {
		$milestone_id = (int) $request->get_param( 'id' );
		$sub          = wpss_get_order( $milestone_id );

		if ( ! $sub || MilestoneService::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return $this->error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), 404 );
		}

		foreach ( $this->milestones->get_for_parent( (int) $sub->platform_order_id ) as $row ) {
			if ( (int) $row['id'] === $milestone_id ) {
				return new WP_REST_Response( $row, 200 );
			}
		}

		return $this->error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), 404 );
	}

	/**
	 * POST /orders/{order_id}/milestones
	 *
	 * Vendor proposes an ad-hoc phase on an active parent order. Returns
	 * HTTP 201 with the new sub-order id + checkout URL the client can
	 * surface to the buyer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function propose( WP_REST_Request $request ) {
		$order_id    = (int) $request->get_param( 'order_id' );
		$title       = (string) $request->get_param( 'title' );
		$description = (string) ( $request->get_param( 'description' ) ?? '' );
		$amount      = (float) $request->get_param( 'amount' );
		$days        = (int) ( $request->get_param( 'days' ) ?? 0 );
		$deliverable = (string) ( $request->get_param( 'deliverables' ) ?? '' );

		$result = $this->milestones->propose(
			$order_id,
			get_current_user_id(),
			$title,
			$description,
			$amount,
			$days,
			$deliverable
		);

		if ( ! $result['success'] ) {
			return $this->error( 'wpss_milestone_propose_failed', $result['message'], 400 );
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'milestone_id' => (int) $result['milestone_id'],
				'checkout_url' => $result['checkout_url'],
				'message'      => $result['message'],
			),
			201
		);
	}

	/**
	 * POST /milestones/{id}/submit
	 *
	 * Vendor marks the milestone as delivered — flips the sub-order from
	 * in_progress (or revision_requested) to pending_approval.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_milestone( WP_REST_Request $request ) {
		$milestone_id = (int) $request->get_param( 'id' );
		$note         = (string) ( $request->get_param( 'note' ) ?? '' );

		$result = $this->milestones->submit( $milestone_id, get_current_user_id(), $note );

		if ( ! $result['success'] ) {
			return $this->error( 'wpss_milestone_submit_failed', $result['message'], 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result['message'],
			),
			200
		);
	}

	/**
	 * POST /milestones/{id}/approve
	 *
	 * Buyer approves a submitted delivery — flips the sub-order to
	 * completed (no wallet movement; commission settled at payment time).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_milestone( WP_REST_Request $request ) {
		$result = $this->milestones->approve( (int) $request->get_param( 'id' ), get_current_user_id() );

		if ( ! $result['success'] ) {
			return $this->error( 'wpss_milestone_approve_failed', $result['message'], 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result['message'],
			),
			200
		);
	}

	/**
	 * POST /milestones/{id}/decline
	 *
	 * Buyer declines a pending-payment milestone. Once a milestone has
	 * been paid, declines must go through the dispute flow instead —
	 * the service layer enforces this rule.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function decline_milestone( WP_REST_Request $request ) {
		$milestone_id = (int) $request->get_param( 'id' );
		$sub          = wpss_get_order( $milestone_id );

		if ( ! $sub || MilestoneService::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return $this->error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), 404 );
		}

		// State-conflict: milestone is already paid (or beyond). Returning
		// 409 here signals the client to push the buyer into the dispute
		// flow instead of surfacing a generic 400.
		if ( 'pending_payment' !== $sub->status ) {
			return $this->error(
				'wpss_milestone_not_declinable',
				__( 'This milestone has already been paid and cannot be declined here. Open a dispute if there is a problem.', 'wp-sell-services' ),
				409
			);
		}

		$result = $this->milestones->decline( $milestone_id, get_current_user_id() );

		if ( ! $result['success'] ) {
			return $this->error( 'wpss_milestone_decline_failed', $result['message'], 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result['message'],
			),
			200
		);
	}

	/**
	 * DELETE /milestones/{id}
	 *
	 * Routes to decline (buyer) or delete_unpaid (vendor) depending on the
	 * current user's relationship to the parent order. Keeps the mobile
	 * client surface to a single tidy endpoint for "get rid of this
	 * unpaid milestone".
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_milestone( WP_REST_Request $request ) {
		$milestone_id = (int) $request->get_param( 'id' );
		$sub          = wpss_get_order( $milestone_id );

		if ( ! $sub || MilestoneService::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return $this->error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), 404 );
		}

		if ( 'pending_payment' !== $sub->status ) {
			return $this->error(
				'wpss_milestone_not_cancellable',
				__( 'Only unpaid milestones can be cancelled here. Open a dispute if there is a problem with a paid phase.', 'wp-sell-services' ),
				409
			);
		}

		$user_id = get_current_user_id();
		if ( (int) $sub->customer_id === $user_id ) {
			$result = $this->milestones->decline( $milestone_id, $user_id );
		} elseif ( (int) $sub->vendor_id === $user_id ) {
			$result = $this->milestones->delete_unpaid( $milestone_id, $user_id );
		} else {
			return $this->error( 'wpss_forbidden', __( 'You cannot cancel this milestone.', 'wp-sell-services' ), 403 );
		}

		if ( ! $result['success'] ) {
			return $this->error( 'wpss_milestone_cancel_failed', $result['message'], 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result['message'],
			),
			200
		);
	}

	/**
	 * POST /milestones/{id}/pay
	 *
	 * Buyer-side endpoint that mirrors the browser's
	 * `?pay_order=<id>` route but enforces the same lock-step guard the
	 * checkout template runs. When a buyer tries to pay phase N+1 while
	 * phase N is still outstanding, we return HTTP 409
	 * `wpss_milestone_locked` so the mobile app can render the exact same
	 * "pay previous phase first" hint the website shows.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function trigger_payment( WP_REST_Request $request ) {
		$milestone_id = (int) $request->get_param( 'id' );
		$sub          = wpss_get_order( $milestone_id );

		if ( ! $sub || MilestoneService::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return $this->error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), 404 );
		}

		if ( 'pending_payment' !== $sub->status ) {
			return $this->error(
				'wpss_milestone_not_payable',
				__( 'This milestone is not awaiting payment.', 'wp-sell-services' ),
				409
			);
		}

		// Lock-step: refuse if an earlier phase is still open. This is the
		// REST-surface equivalent of the guard in
		// StandaloneCheckoutProvider::render_pay_order_checkout().
		if ( $this->milestones->is_locked( $milestone_id ) ) {
			return $this->error(
				'wpss_milestone_locked',
				__( 'This phase is locked. Pay the previous phase first — once it is approved or cancelled, this one will unlock.', 'wp-sell-services' ),
				409
			);
		}

		$base_url     = function_exists( 'wpss_get_checkout_base_url' ) ? wpss_get_checkout_base_url() : home_url( '/checkout/' );
		$checkout_url = add_query_arg( 'pay_order', $milestone_id, $base_url );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'milestone_id' => $milestone_id,
				'checkout_url' => $checkout_url,
			),
			200
		);
	}

	// ---------------------------------------------------------------------
	// Permission gates.
	// ---------------------------------------------------------------------

	/**
	 * Buyer OR vendor of the parent order can list / view milestones.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_order_access( WP_REST_Request $request ) {
		$perm = $this->check_permissions( $request );
		if ( is_wp_error( $perm ) ) {
			return $perm;
		}

		$order = $this->get_order_for_participant( (int) $request->get_param( 'order_id' ) );
		return is_wp_error( $order ) ? $order : true;
	}

	/**
	 * Vendor-only gate for proposing a new milestone on a parent order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_vendor_order_access( WP_REST_Request $request ) {
		$perm = $this->check_permissions( $request );
		if ( is_wp_error( $perm ) ) {
			return $perm;
		}

		$order_id = (int) $request->get_param( 'order_id' );
		$order    = wpss_get_order( $order_id );

		if ( ! $order ) {
			return $this->error( 'wpss_order_not_found', __( 'Order not found.', 'wp-sell-services' ), 404 );
		}

		if ( (int) $order->vendor_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'wpss_forbidden', __( 'Only the seller can propose milestones on this order.', 'wp-sell-services' ), 403 );
		}

		return true;
	}

	/**
	 * Vendor-only gate for milestone submit.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_vendor_milestone_access( WP_REST_Request $request ) {
		return $this->check_milestone_role( $request, 'vendor' );
	}

	/**
	 * Buyer-only gate for milestone approve / decline / pay.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_buyer_milestone_access( WP_REST_Request $request ) {
		return $this->check_milestone_role( $request, 'buyer' );
	}

	/**
	 * Either-party gate for the shared DELETE route.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_participant_milestone_access( WP_REST_Request $request ) {
		return $this->check_milestone_role( $request, 'either' );
	}

	/**
	 * Shared ownership check for `/milestones/{id}/...` routes.
	 *
	 * Loads the sub-order, verifies it is a milestone, and enforces the
	 * role the caller declared. Keeps the 401 / 403 / 404 distinction
	 * consistent across every endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $role    'vendor' | 'buyer' | 'either'.
	 * @return true|WP_Error
	 */
	private function check_milestone_role( WP_REST_Request $request, string $role ) {
		$perm = $this->check_permissions( $request );
		if ( is_wp_error( $perm ) ) {
			return $perm;
		}

		$milestone_id = (int) $request->get_param( 'id' );
		$sub          = wpss_get_order( $milestone_id );

		if ( ! $sub || MilestoneService::ORDER_TYPE !== ( $sub->platform ?? '' ) ) {
			return $this->error( 'wpss_milestone_not_found', __( 'Milestone not found.', 'wp-sell-services' ), 404 );
		}

		$user_id = get_current_user_id();
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$is_vendor = (int) $sub->vendor_id === $user_id;
		$is_buyer  = (int) $sub->customer_id === $user_id;

		$allowed = match ( $role ) {
			'vendor' => $is_vendor,
			'buyer'  => $is_buyer,
			default  => ( $is_vendor || $is_buyer ),
		};

		if ( ! $allowed ) {
			return $this->error( 'wpss_forbidden', __( 'You cannot perform this action on this milestone.', 'wp-sell-services' ), 403 );
		}

		return true;
	}
}
