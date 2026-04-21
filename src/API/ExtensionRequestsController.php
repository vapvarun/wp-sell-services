<?php
/**
 * Extension Requests REST Controller
 *
 * Paid-extension sub-order flow. Vendor raises a quote (extra money + extra
 * days), buyer accepts by paying via the same checkout pattern tips and
 * milestones use.
 *
 * Proxies to {@see \WPSellServices\Services\ExtensionOrderService} so the
 * REST surface and the dashboard AJAX handlers (ajax_request_extension /
 * ajax_decline_extension) share one code path. HTTP statuses:
 *   200  list / read
 *   201  create (sub-order + request row)
 *   400  validation error from the service
 *   401  not logged in
 *   403  wrong role for the action
 *   404  order / request not found
 *   409  state conflict (already answered, wrong parent status)
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
use WPSellServices\Services\ExtensionOrderService;
use WPSellServices\Services\ExtensionRequestService;

/**
 * REST controller for paid extension sub-orders.
 *
 * @since 1.0.0
 */
class ExtensionRequestsController extends RestController {

	/**
	 * Extension sub-order service.
	 *
	 * @var ExtensionOrderService
	 */
	private ExtensionOrderService $extensions;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->extensions = new ExtensionOrderService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /orders/{order_id}/extensions — list.
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

		// POST /orders/{order_id}/extension — vendor creates the quote
		// (singular path mirrors the verb the mobile app is calling).
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/extension',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
					'args'                => array(
						'amount'     => array(
							'description' => __( 'Additional amount the buyer will pay.', 'wp-sell-services' ),
							'type'        => 'number',
							'required'    => true,
							'minimum'     => 0.01,
						),
						'extra_days' => array(
							'description' => __( 'Number of additional days requested.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
							'minimum'     => 1,
						),
						'reason'     => array(
							'description' => __( 'Reason shown to the buyer.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// Keep the legacy plural POST /orders/{id}/extensions alias so
		// older mobile clients don't break while they roll forward.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/extensions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_vendor_order_access' ),
					'args'                => array(
						'amount'     => array(
							'type'     => 'number',
							'required' => true,
							'minimum'  => 0.01,
						),
						'extra_days' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
						'reason'     => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /extensions/{id}/decline — buyer declines the quote.
		register_rest_route(
			$this->namespace,
			'/extensions/(?P<id>[\d]+)/decline',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'decline_item' ),
					'permission_callback' => array( $this, 'check_buyer_extension_access' ),
					'args'                => array(
						'note' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * GET /orders/{order_id}/extensions
	 *
	 * Signature matches WP_REST_Controller::get_items — no type declaration
	 * on the parameter, to stay compatible with the parent class.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;

		$order_id = (int) $request->get_param( 'order_id' );
		$table    = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			),
			ARRAY_A
		);

		return new WP_REST_Response( array_map( array( $this, 'format_item' ), $items ?: array() ), 200 );
	}

	/**
	 * POST /orders/{order_id}/extension
	 *
	 * Vendor asks the buyer for more money + more time. Returns the new
	 * sub-order ID and checkout URL so the client can direct the buyer to
	 * pay.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$order_id   = (int) $request->get_param( 'order_id' );
		$amount     = (float) $request->get_param( 'amount' );
		$extra_days = (int) $request->get_param( 'extra_days' );
		$reason     = (string) $request->get_param( 'reason' );

		$result = $this->extensions->create_extension_request(
			$order_id,
			$amount,
			$extra_days,
			get_current_user_id(),
			$reason
		);

		if ( ! $result['success'] ) {
			return $this->error( 'wpss_extension_create_failed', $result['message'], 400 );
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'request_id'   => (int) $result['request_id'],
				'pay_order_id' => (int) $result['pay_order_id'],
				'checkout_url' => $result['checkout_url'],
				'message'      => $result['message'],
			),
			201
		);
	}

	/**
	 * POST /extensions/{id}/decline
	 *
	 * Buyer declines an unpaid extension request. Cancels the pending
	 * sub-order and marks the history row rejected.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function decline_item( WP_REST_Request $request ) {
		$request_id = (int) $request->get_param( 'id' );
		$note       = (string) ( $request->get_param( 'note' ) ?? '' );

		$result = $this->extensions->decline( $request_id, get_current_user_id(), $note );

		if ( ! $result['success'] ) {
			// Service layer returns "already answered" as a soft failure;
			// translate to 409 so the client can decide whether to refresh.
			$status = str_contains( $result['message'], 'already' ) ? 409 : 400;
			return $this->error( 'wpss_extension_decline_failed', $result['message'], $status );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result['message'],
			),
			200
		);
	}

	// ---------------------------------------------------------------------
	// Permission gates.
	// ---------------------------------------------------------------------

	/**
	 * Buyer or vendor of the parent order can list.
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
	 * Vendor-only gate for creating an extension quote.
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
			return $this->error( 'wpss_forbidden', __( 'Only the seller can request an extension on this order.', 'wp-sell-services' ), 403 );
		}

		return true;
	}

	/**
	 * Buyer-only gate for declining an extension quote.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_buyer_extension_access( WP_REST_Request $request ) {
		$perm = $this->check_permissions( $request );
		if ( is_wp_error( $perm ) ) {
			return $perm;
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'wpss_extension_requests';
		$request_id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id )
		);

		if ( ! $row ) {
			return $this->error( 'wpss_extension_not_found', __( 'Extension request not found.', 'wp-sell-services' ), 404 );
		}

		$parent = wpss_get_order( (int) $row->order_id );
		if ( ! $parent ) {
			return $this->error( 'wpss_order_not_found', __( 'Parent order not found.', 'wp-sell-services' ), 404 );
		}

		if ( (int) $parent->customer_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'wpss_forbidden', __( 'Only the buyer can decline this request.', 'wp-sell-services' ), 403 );
		}

		return true;
	}

	/**
	 * Format a raw extension-request row for the REST response.
	 *
	 * @param array $item Raw DB row.
	 * @return array
	 */
	private function format_item( array $item ): array {
		$requester = get_user_by( 'id', (int) $item['requested_by'] );
		$responder = ! empty( $item['responded_by'] ) ? get_user_by( 'id', (int) $item['responded_by'] ) : null;

		return array(
			'id'                => (int) $item['id'],
			'order_id'          => (int) $item['order_id'],
			'pay_order_id'      => isset( $item['pay_order_id'] ) ? (int) $item['pay_order_id'] : null,
			'requested_by'      => array(
				'id'   => (int) $item['requested_by'],
				'name' => $requester ? $requester->display_name : __( 'Unknown', 'wp-sell-services' ),
			),
			'extra_days'        => (int) $item['extra_days'],
			'amount'            => isset( $item['amount'] ) ? (float) $item['amount'] : null,
			'reason'            => $item['reason'] ?? '',
			'status'            => $item['status'],
			'responded_by'      => $responder
				? array(
					'id'   => (int) $item['responded_by'],
					'name' => $responder->display_name,
				)
				: null,
			'response_message'  => $item['response_message'] ?? '',
			'responded_at'      => $item['responded_at'] ?? null,
			'original_due_date' => $item['original_due_date'] ?? null,
			'new_due_date'      => $item['new_due_date'] ?? null,
			'created_at'        => $item['created_at'],
		);
	}
}
