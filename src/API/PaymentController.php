<?php
/**
 * Payment REST Controller
 *
 * Provides REST endpoints for payment operations needed by mobile apps.
 * Mirrors the existing AJAX payment flows from StripeGateway and PayPalGateway
 * as proper REST endpoints.
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

/**
 * REST controller for payments.
 *
 * @since 1.1.0
 */
class PaymentController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments';

	/**
	 * Register routes.
	 *
	 * The start legs (methods, create-intent) exist only when this site pays
	 * through our gateways; if WooCommerce, EDD or FluentCart takes payment,
	 * they are not registered. Confirm finishes a payment the buyer already
	 * made, so it is registered on every route: switching the route mid-payment
	 * must not strand a charge (1.9.4).
	 *
	 * The buyer's pay URL on those rails comes back with the order itself, from
	 * wpss_get_pay_order_url().
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /payments/confirm - Confirm payment and create/update order.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/confirm',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'confirm_payment' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'gateway'           => array(
							'description' => __( 'Payment gateway ID.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'payment_id'        => array(
							'description' => __( 'Gateway payment/order ID.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'service_id'        => array(
							'description' => __( 'Service ID. Same as product_id for the service type.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'product_id'        => array(
							'description' => __( 'ID of the product being bought.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'product_type'      => array(
							'description' => __( 'Product type; "service" is the only type registered.', 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => 'service',
						),
						'package_id'        => array(
							'description' => __( 'Package index.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'pay_order'         => array(
							'description' => __( 'Existing order ID to pay for.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'provider_order_id' => array(
							'description' => __( "The provider's own order id, for gateways that verify against it (Razorpay).", 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => '',
						),
						'signature'         => array(
							'description' => __( 'Payment signature, for gateways that sign the result (Razorpay).', 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => '',
						),
					),
				),
			)
		);

		// Starting a new payment is for the route that takes payment; the legs above finish one
		// already made and stay on every route (Basecamp 10550988413).
		if ( ! wpss_uses_standalone_payments() ) {
			return;
		}

		// GET /payments/methods - Available payment gateways.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/methods',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_methods' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET|POST /payments/settings - The owner's Payment methods table: order and on/off.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn() => new WP_REST_Response( array( 'methods' => wpss_payment_method_rows() ) ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'order'   => array(
							'description' => __( 'Payment method ids in the order buyers see them; the first is the default.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
						),
						'enabled' => array(
							'description' => __( 'Payment method id => on (true) or off (false).', 'wp-sell-services' ),
							'type'        => 'object',
						),
					),
				),
			)
		);

		// POST /payments/create-intent - Create payment intent (Stripe) or order (PayPal).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/create-intent',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_intent' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'service_id'   => array(
							'description' => __( 'Service ID. Same as product_id for the service type.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'product_id'   => array(
							'description' => __( 'ID of the product being bought.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'product_type' => array(
							'description' => __( 'Product type; "service" is the only type registered.', 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => 'service',
						),
						'package_id'   => array(
							'description' => __( 'Package: stable id or legacy index.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'quantity'     => array(
							'description' => __( 'How many of the package.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'addons'       => array(
							'description' => __( 'Add-on selection: ids, or {id, quantity, option, text} objects. Prices are never read.', 'wp-sell-services' ),
							'type'        => array( 'array', 'string' ),
							'default'     => array(),
						),
						'gateway'      => array(
							'description' => __( 'Payment gateway ID.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'pay_order'    => array(
							'description' => __( 'Existing order ID to pay for (from proposal).', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
					),
				),
			)
		);
	}

	/**
	 * Get available payment methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_methods( WP_REST_Request $request ): WP_REST_Response {
		$currency = wpss_get_currency();
		$methods  = array();

		// The same list the checkout form offers, in the same order.
		foreach ( wpss_get_checkout_gateways( $currency ) as $gateway ) {
			$methods[] = array(
				'id'                => $gateway->get_id(),
				'name'              => $gateway->get_name(),
				'description'       => $gateway->get_description(),
				'supports_currency' => $gateway->supports_currency( $currency ),
			);
		}

		return new WP_REST_Response(
			array(
				'methods'  => $methods,
				'currency' => $currency,
			)
		);
	}

	/**
	 * Save the Payment methods order and on/off switches.
	 *
	 * Answers with the saved order and switches only. Gateways read their settings once per
	 * request, so the status column is fetched fresh with GET.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		$enabled = array_map( 'rest_sanitize_boolean', (array) $request->get_param( 'enabled' ) );
		$saved   = wpss_save_payment_methods( (array) $request->get_param( 'order' ), $enabled );

		return is_wp_error( $saved ) ? $saved : new WP_REST_Response( array( 'saved' => true ) );
	}

	/**
	 * Create payment intent or order for a gateway.
	 *
	 * For Stripe: creates a PaymentIntent, returns client_secret.
	 * For PayPal: creates a PayPal order, returns approval_url.
	 * For Offline/Test: creates the service order directly.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_intent( WP_REST_Request $request ) {
		$service_id = $this->product_id( $request );
		if ( is_wp_error( $service_id ) ) {
			return $service_id;
		}
		$package_id = (int) $request->get_param( 'package_id' );
		$gateway_id = sanitize_text_field( $request->get_param( 'gateway' ) );
		$pay_order  = (int) $request->get_param( 'pay_order' );

		// Priced once, on the server, by the checkout's own resolver: package
		// (stable id or index), quantity, add-ons and tax, or the stored order
		// total. This route priced new purchases from the raw package price -
		// no tax, no add-ons - and 404'd on a stable package id (Basecamp
		// 10336467402), so the app was charged differently from the web.
		$checkout_request = array(
			'pay_order'  => $pay_order,
			'service_id' => $service_id,
			'package_id' => $package_id,
			'quantity'   => max( 1, (int) $request->get_param( 'quantity' ) ),
			'addons'     => $request->get_param( 'addons' ),
		);

		if ( ! $pay_order ) {
			$service = get_post( $service_id );
			if ( ! $service || 'wpss_service' !== $service->post_type || 'publish' !== $service->post_status ) {
				return new WP_Error( 'invalid_service', __( 'Service not found or not available.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}
		}

		// resolve() also refuses a self-purchase and applies the order limits.
		$intent = ( new \WPSellServices\Checkout\CheckoutIntentService() )->resolve( $checkout_request );

		if ( is_wp_error( $intent ) ) {
			$intent->add_data( array( 'status' => 400 ) );
			return $intent;
		}

		// Find the gateway.
		$gateway = $this->get_gateway( $gateway_id );

		if ( ! $gateway ) {
			return new WP_Error( 'invalid_gateway', __( 'Payment gateway not found or not enabled.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		return $gateway->rest_create_intent( $intent, $checkout_request );
	}

	/**
	 * Confirm payment and create/update the service order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_payment( WP_REST_Request $request ) {
		$gateway_id = sanitize_text_field( $request->get_param( 'gateway' ) );
		$payment_id = sanitize_text_field( $request->get_param( 'payment_id' ) );
		$service_id = $this->product_id( $request );
		if ( is_wp_error( $service_id ) ) {
			return $service_id;
		}
		$package_id = (int) $request->get_param( 'package_id' );
		$pay_order  = (int) $request->get_param( 'pay_order' );

		// The buyer already paid: a gateway switched off since then still finishes it, if it asks the provider.
		$gateway = $this->get_gateway( $gateway_id, false );

		if ( ! $gateway ) {
			return new WP_Error( 'invalid_gateway', __( 'Payment gateway not found or not enabled.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// One rule for every gateway, the web checkout's: only the order's buyer pays it.
		// Stripe used to let an admin through and PayPal did not (Basecamp 10551488326).
		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );

			if ( ! $order ) {
				return new WP_Error( 'rest_order_not_found', __( 'Order not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}

			if ( get_current_user_id() !== (int) $order->customer_id ) {
				return new WP_Error( 'wpss_not_owner', __( 'You can only pay for your own order.', 'wp-sell-services' ), array( 'status' => 403 ) );
			}
		}

		return $gateway->rest_confirm_payment(
			array(
				'payment_id'        => $payment_id,
				'service_id'        => $service_id,
				'package_id'        => $package_id,
				'pay_order'         => $pay_order,
				'provider_order_id' => sanitize_text_field( (string) $request->get_param( 'provider_order_id' ) ),
				'signature'         => sanitize_text_field( (string) $request->get_param( 'signature' ) ),
			)
		);
	}

	/**
	 * The id of the product being bought, from product_id or its service alias.
	 *
	 * "service" is the only registered product type, so another type is refused
	 * here rather than priced as a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return int|WP_Error
	 */
	private function product_id( WP_REST_Request $request ) {
		$type = (string) $request->get_param( 'product_type' );
		// A registered type other than "service" is known but cannot be bought until
		// the checkout intent is item-based, so it is refused with the same error.
		if ( '' !== $type && ( 'service' !== $type || null === wpss()->get_product_type( $type ) ) ) {
			return new WP_Error( 'wpss_unsupported_product_type', __( 'This product type is not supported.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$id = (int) $request->get_param( 'product_id' ) ?: (int) $request->get_param( 'service_id' );
		if ( ! $id && ! (int) $request->get_param( 'pay_order' ) ) {
			return new WP_Error( 'rest_missing_callback_param', __( 'Missing parameter(s): product_id', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		return $id;
	}

	/**
	 * Get a gateway instance by ID.
	 *
	 * "Enabled" gates starting a payment only. Confirming one the buyer already made never
	 * checks it, or a gateway switched off mid-payment leaves a charge with no order.
	 *
	 * @param string $gateway_id      Gateway ID.
	 * @param bool   $require_enabled False for legs that finish a payment already taken: an off gateway is
	 *                                then served only if it supports verified_confirmation (asks the provider),
	 *                                never one that settles on the caller's word, such as Test.
	 * @return object|null Gateway instance or null if not found/enabled.
	 */
	private function get_gateway( string $gateway_id, bool $require_enabled = true ): ?object {
		$gateway = wpss()->get_payment_gateways()[ $gateway_id ] ?? null;

		if ( ! $gateway ) {
			return null;
		}

		return $gateway->is_enabled() || ( ! $require_enabled && wpss_gateway_supports( $gateway, 'verified_confirmation' ) ) ? $gateway : null;
	}
}
