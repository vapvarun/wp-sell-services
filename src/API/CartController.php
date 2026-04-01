<?php
/**
 * Cart REST Controller
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
 * REST controller for service cart and checkout.
 *
 * @since 1.0.0
 */
class CartController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'cart';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /cart/add - Add service to cart.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/add',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_to_cart' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'service_id' => array(
							'description' => __( 'Service ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'package_id' => array(
							'description' => __( 'Package index/ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'addons'     => array(
							'description' => __( 'Selected addon IDs.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		// GET /cart - Get cart contents.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cart' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// DELETE /cart/{item_key} - Remove item.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<item_key>[a-z0-9]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /cart/checkout - Initiate checkout.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/checkout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'checkout' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'payment_method' => array(
							'description' => __( 'Payment gateway ID.', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Add service to cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_to_cart( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$package_id = (int) $request->get_param( 'package_id' );
		$addon_ids  = $request->get_param( 'addons' ) ?: array();

		// Verify service exists.
		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type || 'publish' !== $service->post_status ) {
			return new WP_Error( 'invalid_service', __( 'Service not found or not available.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		// Cannot buy own service.
		if ( (int) $service->post_author === get_current_user_id() ) {
			return new WP_Error( 'own_service', __( 'You cannot purchase your own service.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Get package.
		$packages = get_post_meta( $service_id, '_wpss_packages', true );
		if ( ! is_array( $packages ) || ! isset( $packages[ $package_id ] ) ) {
			return new WP_Error( 'invalid_package', __( 'Package not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$package = $packages[ $package_id ];
		$total   = (float) $package['price'];

		// Calculate addon totals.
		$selected_addons = array();
		if ( ! empty( $addon_ids ) ) {
			global $wpdb;
			$addons_table = $wpdb->prefix . 'wpss_service_addons';

			foreach ( $addon_ids as $addon_id ) {
				$addon = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$addons_table} WHERE id = %d AND service_id = %d",
						(int) $addon_id,
						$service_id
					)
				);

				if ( $addon ) {
					$total            += (float) $addon->price;
					$selected_addons[] = array(
						'id'    => (int) $addon->id,
						'title' => $addon->title,
						'price' => (float) $addon->price,
					);
				}
			}
		}

		// Standalone cart (stored in user meta).
		$user_id = get_current_user_id();

		/**
		 * Validates whether a service can be added to the cart.
		 *
		 * Return a WP_Error to prevent the item from being added.
		 *
		 * @since 1.4.0
		 *
		 * @param true|WP_Error $valid      True if valid, WP_Error to block.
		 * @param int           $service_id Service post ID.
		 * @param int           $package_id Package ID.
		 * @param int           $user_id    Current user ID.
		 */
		$valid = apply_filters( 'wpss_validate_add_to_cart', true, $service_id, $package_id, $user_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$cart = get_user_meta( $user_id, '_wpss_cart', true );

		if ( ! is_array( $cart ) ) {
			$cart = array();
		}

		$item_key  = md5( $service_id . '-' . $package_id . '-' . wp_json_encode( $addon_ids ) );
		$cart_item = array(
			'service_id' => $service_id,
			'package_id' => $package_id,
			'package'    => $package,
			'addons'     => $selected_addons,
			'total'      => $total,
			'added_at'   => current_time( 'mysql', true ),
		);

		/**
		 * Filters cart item data before it is saved.
		 *
		 * Allows adding custom data to cart items or modifying existing values.
		 *
		 * @since 1.4.0
		 *
		 * @param array $cart_item  Cart item data.
		 * @param int   $service_id Service post ID.
		 * @param int   $package_id Package ID.
		 */
		$cart_item = apply_filters( 'wpss_cart_item_data', $cart_item, $service_id, $package_id );

		$cart[ $item_key ] = $cart_item;

		update_user_meta( $user_id, '_wpss_cart', $cart );

		return new WP_REST_Response(
			array(
				'success'       => true,
				'cart_item_key' => $item_key,
				'service'       => $service->post_title,
				'package'       => $package['name'] ?? '',
				'total'         => $total,
				'currency'      => wpss_get_currency(),
			),
			201
		);
	}

	/**
	 * Get cart contents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_cart( WP_REST_Request $request ): WP_REST_Response {
		// Standalone cart.
		$cart       = get_user_meta( get_current_user_id(), '_wpss_cart', true );
		$items      = array();
		$cart_total = 0;

		if ( is_array( $cart ) ) {
			foreach ( $cart as $key => $item ) {
				$service = get_post( $item['service_id'] );

				// Resolve package name from stored package data or from service meta.
				$package_name = '';
				if ( ! empty( $item['package']['name'] ) ) {
					$package_name = $item['package']['name'];
				} else {
					$packages = get_post_meta( $item['service_id'], '_wpss_packages', true );
					if ( is_array( $packages ) && isset( $packages[ $item['package_id'] ] ) ) {
						$package_name = $packages[ $item['package_id'] ]['name'] ?? '';
					}
				}

				$items[] = array(
					'key'          => $key,
					'service_id'   => $item['service_id'],
					'service'      => $service ? $service->post_title : '',
					'package_id'   => $item['package_id'],
					'package_name' => $package_name,
					'addons'       => $item['addons'] ?? array(),
					'total'        => (float) $item['total'],
				);

				$cart_total += (float) $item['total'];
			}
		}

		return new WP_REST_Response(
			array(
				'items'    => $items,
				'total'    => $cart_total,
				'currency' => wpss_get_currency(),
			)
		);
	}

	/**
	 * Remove item from cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_item( WP_REST_Request $request ) {
		$item_key = sanitize_text_field( $request->get_param( 'item_key' ) );

		// Standalone cart.
		$user_id = get_current_user_id();
		$cart    = get_user_meta( $user_id, '_wpss_cart', true );

		if ( ! is_array( $cart ) || ! isset( $cart[ $item_key ] ) ) {
			return new WP_Error( 'not_found', __( 'Cart item not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		unset( $cart[ $item_key ] );
		update_user_meta( $user_id, '_wpss_cart', $cart );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Initiate checkout.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function checkout( WP_REST_Request $request ) {
		// Standalone checkout - create order directly.
		$user_id = get_current_user_id();
		$cart    = get_user_meta( $user_id, '_wpss_cart', true );

		if ( ! is_array( $cart ) || empty( $cart ) ) {
			return new WP_Error( 'empty_cart', __( 'Cart is empty.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) ?: '' );

		/**
		 * Filter to create order from cart during standalone checkout.
		 *
		 * @param array  $cart            Cart items.
		 * @param int    $user_id         Customer ID.
		 * @param string $payment_method  Selected gateway.
		 */
		$order_result = apply_filters( 'wpss_cart_checkout', null, $cart, $user_id, $payment_method );

		if ( is_wp_error( $order_result ) ) {
			return $order_result;
		}

		// Clear cart after checkout.
		delete_user_meta( $user_id, '_wpss_cart' );

		if ( $order_result ) {
			return new WP_REST_Response( $order_result );
		}

		return new WP_REST_Response(
			array(
				'method'          => 'pending',
				'message'         => __( 'Order created. Select a payment method to complete.', 'wp-sell-services' ),
				'payment_methods' => apply_filters( 'wpss_available_payment_methods', array() ),
			)
		);
	}
}
