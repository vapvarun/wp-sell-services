<?php
/**
 * Tipping REST Controller
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

/**
 * REST controller for tipping.
 *
 * @since 1.0.0
 */
class TippingController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /orders/{order_id}/tip - Send tip.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/tip',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_tip' ),
					'permission_callback' => array( $this, 'check_buyer_access' ),
					'args'                => array(
						'amount'  => array(
							'description' => __( 'Tip amount.', 'wp-sell-services' ),
							'type'        => 'number',
							'required'    => true,
							'minimum'     => 0.01,
						),
						'message' => array(
							'description' => __( 'Tip message.', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order_tip' ),
					'permission_callback' => array( $this, 'check_order_access' ),
				),
			)
		);

		// GET /vendors/{vendor_id}/tips - Get vendor's tips.
		register_rest_route(
			$this->namespace,
			'/vendors/(?P<vendor_id>[\d]+)/tips',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_tips' ),
					'permission_callback' => array( $this, 'check_vendor_tips_access' ),
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

		// GET /vendors/{vendor_id}/tips/total - Get vendor tips total.
		register_rest_route(
			$this->namespace,
			'/vendors/(?P<vendor_id>[\d]+)/tips/total',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vendor_tips_total' ),
					'permission_callback' => array( $this, 'check_vendor_tips_access' ),
				),
			)
		);
	}

	/**
	 * Start a tip flow.
	 *
	 * Creates a pending-payment tip order via {@see \WPSellServices\Services\TippingService::create_pending_tip_order()}
	 * and returns the checkout URL the buyer should be redirected to. Vendor
	 * wallet credit happens only after the gateway confirms payment — no
	 * direct wallet writes here.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_tip( WP_REST_Request $request ) {
		$parent_order_id = (int) $request->get_param( 'order_id' );
		$amount          = (float) $request->get_param( 'amount' );
		$message         = (string) ( $request->get_param( 'message' ) ?? '' );
		$user_id         = get_current_user_id();

		$service = new \WPSellServices\Services\TippingService();
		$result  = $service->create_pending_tip_order( $parent_order_id, $amount, $user_id, $message );

		if ( empty( $result['success'] ) ) {
			return $this->error(
				'wpss_tip_create_failed',
				$result['message'] ?? __( 'Could not start tip flow.', 'wp-sell-services' ),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success'         => true,
				'tip_order_id'    => (int) $result['tip_order_id'],
				'checkout_url'    => $result['checkout_url'],
				'parent_order_id' => $parent_order_id,
				'amount'          => $amount,
				'message'         => $result['message'],
			),
			201
		);
	}

	/**
	 * Get tip for order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_order_tip( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$order_id     = (int) $request->get_param( 'order_id' );
		$wallet_table = $wpdb->prefix . 'wpss_wallet_transactions';

		$tip = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wallet_table} WHERE reference_type = 'tip' AND reference_id = %d",
				$order_id
			),
			ARRAY_A
		);

		if ( ! $tip ) {
			return new WP_REST_Response( array( 'has_tip' => false ) );
		}

		$meta = json_decode( $tip['meta'] ?? '{}', true );

		return new WP_REST_Response(
			array(
				'has_tip'    => true,
				'amount'     => (float) $tip['amount'],
				'message'    => $meta['message'] ?? '',
				'created_at' => $tip['created_at'],
			)
		);
	}

	/**
	 * Get vendor tips.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vendor_tips( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$vendor_id    = (int) $request->get_param( 'vendor_id' );
		$pagination   = $this->get_pagination_args( $request );
		$wallet_table = $wpdb->prefix . 'wpss_wallet_transactions';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wallet_table} WHERE user_id = %d AND reference_type = 'tip'",
				$vendor_id
			)
		);

		$tips = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wallet_table} WHERE user_id = %d AND reference_type = 'tip' ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$vendor_id,
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $tips ?: array() as $tip ) {
			$meta   = json_decode( $tip['meta'] ?? '{}', true );
			$tipper = ! empty( $meta['tipper_id'] ) ? get_user_by( 'id', $meta['tipper_id'] ) : null;

			$items[] = array(
				'amount'     => (float) $tip['amount'],
				'order_id'   => (int) $tip['reference_id'],
				'tipper'     => $tipper
					? array(
						'id'     => $tipper->ID,
						'name'   => $tipper->display_name,
						'avatar' => get_avatar_url( $tipper->ID, array( 'size' => 48 ) ),
					)
					: null,
				'message'    => $meta['message'] ?? '',
				'created_at' => $tip['created_at'],
			);
		}

		return $this->paginated_response( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get vendor tips total.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vendor_tips_total( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$vendor_id    = (int) $request->get_param( 'vendor_id' );
		$wallet_table = $wpdb->prefix . 'wpss_wallet_transactions';

		$total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wallet_table} WHERE user_id = %d AND reference_type = 'tip'",
				$vendor_id
			)
		);

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wallet_table} WHERE user_id = %d AND reference_type = 'tip'",
				$vendor_id
			)
		);

		return new WP_REST_Response(
			array(
				'total_amount' => round( $total, 2 ),
				'total_count'  => $count,
				'currency'     => wpss_get_currency(),
			)
		);
	}

	/**
	 * Check buyer access to order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_buyer_access( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$order_id = (int) $request->get_param( 'order_id' );
		$order    = wpss_get_order( $order_id );

		if ( ! $order ) {
			return $this->error( 'wpss_order_not_found', __( 'Order not found.', 'wp-sell-services' ), 404 );
		}

		if ( (int) $order->customer_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'wpss_forbidden', __( 'Only the buyer can send tips.', 'wp-sell-services' ), 403 );
		}

		return true;
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

		$order = $this->get_order_for_participant( (int) $request->get_param( 'order_id' ) );
		return is_wp_error( $order ) ? $order : true;
	}

	/**
	 * Check vendor tips access.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_tips_access( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$vendor_id = (int) $request->get_param( 'vendor_id' );

		if ( $vendor_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'wpss_forbidden', __( 'You can only view your own tips.', 'wp-sell-services' ), 403 );
		}

		return true;
	}
}
