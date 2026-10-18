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

		// Route to the appropriate gateway.
		switch ( $gateway_id ) {
			case 'paypal':
				return $this->create_paypal_order( $gateway, $checkout_request );

			case 'offline':
			case 'test':
				return $this->create_offline_order( $gateway, $checkout_request );

			default:
				return $gateway->rest_create_intent( $intent, $checkout_request );
		}
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
			case 'paypal':
				return $this->confirm_paypal_payment( $gateway, $payment_id, $service_id, $package_id, $pay_order );

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
	 * Create PayPal order.
	 *
	 * @param object               $gateway          PayPal gateway instance.
	 * @param array<string, mixed> $checkout_request Resolve() request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function create_paypal_order( object $gateway, array $checkout_request ) {
		// PayPalGateway::create_order() resolves the request itself and keeps
		// the add-on selection for its capture leg.
		$result = $gateway->create_order( $checkout_request );

		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'paypal_error', $result['error'] ?? __( 'Failed to create PayPal order.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'gateway'         => 'paypal',
				'paypal_order_id' => $result['id'],
				'approval_url'    => $result['approval_url'],
			),
			201
		);
	}

	/**
	 * Create order directly for offline/test gateways.
	 *
	 * @param object               $gateway          Gateway instance.
	 * @param array<string, mixed> $checkout_request Resolve() request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function create_offline_order( object $gateway, array $checkout_request ) {
		$pay_order = (int) $checkout_request['pay_order'];

		// For existing orders (pay_order), record the rail and return. Ownership
		// and pending_payment status are already verified by create_intent().
		//
		// Out-of-band rails settle later, so the order has to remember which one
		// it is waiting on: the admin confirmation box and the buyer's proof
		// upload both key off payment_method being the gateway id, and this
		// branch used to leave it NULL - the same dead end the AJAX checkout had
		// (Basecamp 10208094640). One writer serves both entry points.
		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );

			if ( ! wpss_record_pending_payment_method( $pay_order, $gateway->get_id() ) ) {
				return new WP_Error(
					'wpss_payment_method_not_recorded',
					__( 'Could not record the payment method on this order.', 'wp-sell-services' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'gateway'      => $gateway->get_id(),
					'order_id'     => (int) $order->id,
					'order_number' => $order->order_number,
					'status'       => 'pending_payment',
					'message'      => __( 'Please complete your payment using the offline instructions. Your order will be activated once payment is confirmed.', 'wp-sell-services' ),
				),
				201
			);
		}

		// A new order, priced and created by the checkout form's own path.
		$offline = wpss()->get_payment_gateways()['offline'] ?? new \WPSellServices\Integrations\Gateways\OfflineGateway();
		$order   = $offline->create_service_order(
			(int) $checkout_request['service_id'],
			(int) $checkout_request['package_id'],
			(int) $checkout_request['quantity'],
			\WPSellServices\Checkout\CheckoutIntentService::request_selection( $checkout_request ),
			$gateway->get_id()
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		/**
		 * Fires when an offline order is created via REST API.
		 *
		 * @param int    $order_id   Order ID.
		 * @param object $order      Order object.
		 * @param string $gateway_id Gateway ID.
		 */
		do_action( 'wpss_rest_offline_order_created', $order->id, $order, $gateway->get_id() );

		return new WP_REST_Response(
			array(
				'gateway'      => $gateway->get_id(),
				'order_id'     => (int) $order->id,
				'order_number' => $order->order_number,
				'status'       => 'pending_payment',
			),
			201
		);
	}

	/**
	 * Confirm PayPal payment (capture) and create order.
	 *
	 * @param object $gateway    PayPal gateway instance.
	 * @param string $payment_id PayPal order ID.
	 * @param int    $service_id Service ID.
	 * @param int    $package_id Package index.
	 * @param int    $pay_order  Existing order ID (0 if new).
	 * @return WP_REST_Response|WP_Error
	 */
	private function confirm_paypal_payment( object $gateway, string $payment_id, int $service_id, int $package_id, int $pay_order ) {
		// Paying an existing order: answer the plain refusals before PayPal is
		// asked for anything.
		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );

			if ( ! $order ) {
				return new WP_Error( 'rest_order_not_found', __( 'Order not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}

			if ( get_current_user_id() !== (int) $order->customer_id ) {
				return new WP_Error( 'wpss_not_owner', __( 'You can only pay for your own order.', 'wp-sell-services' ), array( 'status' => 403 ) );
			}

			// Idempotency: never re-process an order that is already paid.
			if ( 'paid' === (string) ( $order->payment_status ?? '' ) ) {
				return new WP_REST_Response(
					array(
						'gateway'      => 'paypal',
						'order_id'     => $pay_order,
						'order_number' => $order->order_number,
						'status'       => 'paid',
					)
				);
			}
		}

		// One capture path for a new purchase and an existing order, the one the
		// website uses. It reads the buyer and the order from the PayPal payment
		// itself, so a payment pays what it was started for and only for the
		// member who started it. This route used to capture whatever PayPal
		// payment it was handed and mark the named order paid.
		$result = $gateway->capture_order(
			array(
				'paypal_order_id' => $payment_id,
				'service_id'      => $service_id,
				'package_id'      => $package_id,
			)
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'paypal_confirm_error', $result['error'] ?? __( 'Payment capture failed.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'gateway'      => 'paypal',
				'order_id'     => (int) $result['order_id'],
				'order_number' => $result['order_number'] ?? '',
				'status'       => 'paid',
			)
		);
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
