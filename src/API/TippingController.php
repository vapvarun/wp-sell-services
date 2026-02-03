<?php
/**
 * Tipping REST Controller
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
	 * Send tip.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_tip( WP_REST_Request $request ) {
		global $wpdb;

		$order_id = (int) $request->get_param( 'order_id' );
		$amount   = (float) $request->get_param( 'amount' );
		$message  = sanitize_textarea_field( $request->get_param( 'message' ) ?: '' );
		$user_id  = get_current_user_id();

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$wallet_table = $wpdb->prefix . 'wpss_wallet_transactions';

		// Verify order is completed.
		$order = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$orders_table} WHERE id = %d", $order_id )
		);

		if ( ! $order || 'completed' !== $order->status ) {
			return new WP_Error( 'invalid_order', __( 'Tips can only be sent for completed orders.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Verify current user is the customer of this order.
		if ( (int) $order->customer_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You can only tip on your own orders.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		// Check if this user already tipped for this order.
		// Use boundary markers to prevent false matches (e.g. tipper_id 12 matching 123).
		$like_pattern = '%"tipper_id":' . $wpdb->esc_like( (string) $user_id ) . ',%';
		$like_pattern_end = '%"tipper_id":' . $wpdb->esc_like( (string) $user_id ) . '}%';
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wallet_table} WHERE reference_type = 'tip' AND reference_id = %d AND (meta LIKE %s OR meta LIKE %s)",
				$order_id,
				$like_pattern,
				$like_pattern_end
			)
		);

		if ( $existing > 0 ) {
			return new WP_Error( 'already_tipped', __( 'You have already tipped for this order.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Record tip as wallet transaction.
		$wpdb->insert(
			$wallet_table,
			array(
				'user_id'        => (int) $order->vendor_id,
				'type'           => 'credit',
				'amount'         => $amount,
				'description'    => $message ?: __( 'Tip received', 'wp-sell-services' ),
				'reference_type' => 'tip',
				'reference_id'   => $order_id,
				'meta'           => wp_json_encode(
					array(
						'tipper_id' => $user_id,
						'message'   => $message,
					)
				),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s' )
		);

		/**
		 * Fires after a tip is sent.
		 *
		 * @param int   $order_id  Order ID.
		 * @param float $amount    Tip amount.
		 * @param int   $vendor_id Vendor ID.
		 * @param int   $user_id   Tipper ID.
		 */
		do_action( 'wpss_tip_sent', $order_id, $amount, (int) $order->vendor_id, $user_id );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'order_id' => $order_id,
				'amount'   => $amount,
				'message'  => $message,
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
			$meta    = json_decode( $tip['meta'] ?? '{}', true );
			$tipper  = ! empty( $meta['tipper_id'] ) ? get_user_by( 'id', $meta['tipper_id'] ) : null;

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

		global $wpdb;
		$order_id = (int) $request->get_param( 'order_id' );
		$order    = $wpdb->get_row(
			$wpdb->prepare( "SELECT customer_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $order_id )
		);

		if ( ! $order || ( (int) $order->customer_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Only the buyer can send tips.', 'wp-sell-services' ), array( 'status' => 403 ) );
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

		$order_id = (int) $request->get_param( 'order_id' );
		if ( ! $this->user_owns_resource( $order_id, 'order' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have access to this order.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
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
			return new WP_Error( 'rest_forbidden', __( 'You can only view your own tips.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
