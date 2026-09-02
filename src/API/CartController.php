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
							'description' => __( 'Stable package id from GET /services/{id}/packages. A legacy array index is still accepted for older clients.', 'wp-sell-services' ),
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

		// Paused services are not purchasable. The listing hides the CTA, but
		// enforce it server-side too so a paused service can't be added via a
		// direct API call (parity with the vendor-vacation block).
		if ( 'paused' === wpss_get_service_status( $service_id ) ) {
			return new WP_Error( 'service_paused', __( 'This service is not accepting orders right now.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Get package, by STABLE ID or by legacy index.
		//
		// GET /services/{id}/packages now publishes a stable `id`, but shipped
		// clients still send the array index and saved carts may hold either.
		// The resolver tries the stable id first and falls back to the index, so
		// old and new clients both work during the transition
		// (Basecamp #10154919857).
		$resolved = wpss_resolve_service_package( $service_id, $package_id );

		if ( null === $resolved ) {
			return new WP_Error( 'invalid_package', __( 'Package not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$package = $resolved['package'];
		$total   = (float) $package['price'];

		// Store the POSITION, because that is what the rest of the order
		// pipeline and every historical row still mean by package_id. The stable
		// id is how the client NAMES a package; converting it here keeps that
		// change at the edge instead of rewriting the storage format mid-release.
		$package_id = $resolved['index'];

		// Calculate addon totals.
		$selected_addons = array();
		if ( ! empty( $addon_ids ) ) {
			// Add-on ids are indices into the service's `_wpss_addons` meta, the
			// same ones the order modal and checkout use.
			$all_addons = wpss_get_service_extras( $service_id );

			foreach ( $addon_ids as $addon_id ) {
				$addon = $all_addons[ (int) $addon_id ] ?? null;

				if ( $addon ) {
					$total            += (float) $addon['price'];
					$selected_addons[] = array(
						'id'    => (int) $addon_id,
						'title' => $addon['title'],
						'price' => (float) $addon['price'],
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

				// Report the package the way /services/{id} does.
				//
				// The cart ACCEPTS a stable id, then stored and returned the
				// array position, so a client that round-tripped what we handed
				// back was sending a positional value: fine today, wrong the
				// moment a vendor reorders their tiers. Service detail taught
				// the stable id and cart taught the index.
				//
				// Only the RESPONSE changes. The stored value stays positional
				// because the pipeline genuinely still means position -- notably
				// CheckoutIntentService does `$packages[ (int) $item['package_id'] ]`,
				// a direct positional lookup that would fall through to
				// reset($packages) and silently charge the FIRST tier if this
				// were switched underneath it. Migrating storage is a separate,
				// larger change; misreporting the contract is the bug here.
				$stable_id = 0;
				if ( isset( $item['package']['id'] ) ) {
					$stable_id = (int) $item['package']['id'];
				} else {
					$service_packages = get_post_meta( $item['service_id'], '_wpss_packages', true );
					if ( is_array( $service_packages ) && isset( $service_packages[ $item['package_id'] ]['id'] ) ) {
						$stable_id = (int) $service_packages[ $item['package_id'] ]['id'];
					}
				}

				$items[] = array(
					'key'           => $key,
					'service_id'    => $item['service_id'],
					'service'       => $service ? $service->post_title : '',
					// Stable id when the service has one; falls back to the
					// position for a service whose packages predate ids, so this
					// never reports an id that cannot be resolved.
					'package_id'    => $stable_id ?: (int) $item['package_id'],
					// The positional value, named for what it is. Published so
					// the transition is visible rather than implied.
					'package_index' => (int) $item['package_id'],
					'package_name'  => $package_name,
					'addons'        => $item['addons'] ?? array(),
					'total'         => (float) $item['total'],
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

		// Nothing is wired to wpss_cart_checkout on a stock install, so create
		// the orders here — on EVERY rail, not just standalone.
		//
		// Handing the cart to WooCommerce and returning /checkout/ instead was
		// wrong for the same reason it is wrong for tips and milestones: that
		// URL only shows a cart to a browser that already carries the WC
		// session cookie, so a native client opening it in a fresh WebView
		// sees an empty cart and cannot pay at all.
		//
		// So the WPSS order is created first and is the canonical record on
		// every rail, and the URL where the buyer pays is resolved through
		// wpss_ensure_pay_order() — the one seam tips, milestones and
		// extensions already use. On Woo that filter returns a real order-pay
		// URL carrying its own key, which needs no session; on standalone it
		// stays the local pay page. One checkout, one contract, whatever the
		// site sells through.
		if ( empty( $order_result ) ) {
			$provider  = new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider();
			$order_ids = $provider->create_orders_from_cart( $cart, $payment_method, '', $user_id );

			if ( empty( $order_ids ) ) {
				return new WP_Error(
					'checkout_unavailable',
					__( 'Checkout could not be completed right now. Please try again or contact the site owner.', 'wp-sell-services' ),
					array( 'status' => 501 )
				);
			}

			// Only now is it safe to drop the cart — an order exists.
			delete_user_meta( $user_id, '_wpss_cart' );

			$orders = array();

			foreach ( $order_ids as $order_id ) {
				$order = wpss_get_order( (int) $order_id );

				$orders[] = array(
					'order_id'     => (int) $order_id,
					'order_number' => $order->order_number ?? '',
					'status'       => $order->status ?? '',
					'total'        => (float) ( $order->total ?? 0 ),
					'currency'     => (string) ( $order->currency ?? wpss_get_currency() ),
					// Where the buyer actually pays. Resolved through the shared
					// seam, so it is right on whichever rail the site runs.
					'checkout_url' => wpss_ensure_pay_order( (int) $order_id ),
				);
			}

			$adapter = function_exists( 'wpss_get_ecommerce_adapter' ) ? wpss_get_ecommerce_adapter() : null;

			return new WP_REST_Response(
				array(
					'orders'       => $orders,
					// One cart can hold services from several vendors, which is
					// several orders — but a client with a single-order screen
					// needs somewhere to send the buyer, so surface the first.
					'order_id'     => $orders[0]['order_id'],
					'checkout_url' => $orders[0]['checkout_url'],
					// Informational only. The client opens checkout_url the same
					// way regardless of what this says.
					'rail'         => $adapter ? $adapter->get_id() : 'standalone',
				),
				201
			);
		}

		// Clear the cart only after an order has actually been created.
		delete_user_meta( $user_id, '_wpss_cart' );

		return new WP_REST_Response( $order_result );
	}
}
