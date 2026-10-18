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
	 * These endpoints drive the plugin's own gateways, so they exist only when
	 * this site pays through them. If the owner has enabled WooCommerce, EDD,
	 * FluentCart or SureCart to take payment, that plugin processes everything
	 * and none of these routes are registered at all - rather than registering
	 * them and having each one refuse, which leaves a payment surface that
	 * looks half alive.
	 *
	 * The buyer's pay URL on those rails comes back with the order itself, from
	 * wpss_get_pay_order_url().
	 *
	 * @return void
	 */
	public function register_routes(): void {
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
						'service_id' => array(
							'description' => __( 'Service ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'package_id' => array(
							'description' => __( 'Package: stable id or legacy index.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'quantity'   => array(
							'description' => __( 'How many of the package.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'addons'     => array(
							'description' => __( 'Add-on selection: ids, or {id, quantity, option, text} objects. Prices are never read.', 'wp-sell-services' ),
							'type'        => array( 'array', 'string' ),
							'default'     => array(),
						),
						'gateway'    => array(
							'description' => __( 'Payment gateway ID.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'pay_order'  => array(
							'description' => __( 'Existing order ID to pay for (from proposal).', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
					),
				),
			)
		);

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
						'gateway'    => array(
							'description' => __( 'Payment gateway ID.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'payment_id' => array(
							'description' => __( 'Gateway payment/order ID.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'service_id' => array(
							'description' => __( 'Service ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'package_id' => array(
							'description' => __( 'Package index.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
						),
						'pay_order'  => array(
							'description' => __( 'Existing order ID to pay for.', 'wp-sell-services' ),
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
		$gateways = wpss()->get_payment_gateways();
		$currency = wpss_get_currency();
		$methods  = array();

		foreach ( $gateways as $gateway ) {
			if ( ! $gateway->is_enabled() ) {
				continue;
			}

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
		$service_id = (int) $request->get_param( 'service_id' );
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
		$service_id = (int) $request->get_param( 'service_id' );
		$package_id = (int) $request->get_param( 'package_id' );
		$pay_order  = (int) $request->get_param( 'pay_order' );

		$gateway = $this->get_gateway( $gateway_id );

		if ( ! $gateway ) {
			return new WP_Error( 'invalid_gateway', __( 'Payment gateway not found or not enabled.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		switch ( $gateway_id ) {
			default:
				return $gateway->rest_confirm_payment(
					array(
						'payment_id' => $payment_id,
						'service_id' => $service_id,
						'package_id' => $package_id,
						'pay_order'  => $pay_order,
					)
				);
		}
	}

	/**
	 * Get a gateway instance by ID.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return object|null Gateway instance or null if not found/enabled.
	 */
	private function get_gateway( string $gateway_id ): ?object {
		$gateways = wpss()->get_payment_gateways();

		foreach ( $gateways as $gateway ) {
			if ( $gateway->get_id() === $gateway_id && $gateway->is_enabled() ) {
				return $gateway;
			}
		}

		return null;
	}
}
