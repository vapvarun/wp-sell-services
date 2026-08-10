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
							'description' => __( 'Package index.', 'wp-sell-services' ),
							'type'        => 'integer',
							'default'     => 0,
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

		// Resolve amount: from existing order or from service package.
		$amount   = 0.0;
		$currency = wpss_get_currency();

		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );
			if ( ! $order || (int) $order->customer_id !== get_current_user_id() ) {
				return new WP_Error( 'invalid_order', __( 'Invalid order.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}
			if ( 'pending_payment' !== $order->status ) {
				return new WP_Error( 'already_paid', __( 'This order has already been paid.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}
			$amount   = (float) $order->total;
			$currency = $order->currency;
		} else {
			// Validate service.
			$service = get_post( $service_id );
			if ( ! $service || 'wpss_service' !== $service->post_type || 'publish' !== $service->post_status ) {
				return new WP_Error( 'invalid_service', __( 'Service not found or not available.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}

			// Cannot buy own service.
			if ( (int) $service->post_author === get_current_user_id() ) {
				return new WP_Error( 'own_service', __( 'You cannot purchase your own service.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}

			// Get price from package.
			$packages = get_post_meta( $service_id, '_wpss_packages', true );
			if ( ! is_array( $packages ) || ! isset( $packages[ $package_id ] ) ) {
				return new WP_Error( 'invalid_package', __( 'Package not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}

			$amount = (float) $packages[ $package_id ]['price'];
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Find the gateway.
		$gateway = $this->get_gateway( $gateway_id );

		if ( ! $gateway ) {
			return new WP_Error( 'invalid_gateway', __( 'Payment gateway not found or not enabled.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Route to the appropriate gateway.
		switch ( $gateway_id ) {
			case 'stripe':
				return $this->create_stripe_intent( $gateway, $amount, $currency, $service_id, $package_id, $pay_order );

			case 'paypal':
				return $this->create_paypal_order( $gateway, $amount, $currency, $service_id, $package_id, $pay_order );

			case 'offline':
			case 'test':
				return $this->create_offline_order( $gateway, $amount, $currency, $service_id, $package_id, $pay_order );

			default:
				/**
				 * Filter to handle custom payment gateway intent creation via REST.
				 *
				 * @since 1.1.0
				 *
				 * @param WP_REST_Response|WP_Error|null $result     Default null.
				 * @param object                         $gateway    Gateway instance.
				 * @param float                          $amount     Payment amount.
				 * @param string                         $currency   Currency code.
				 * @param int                            $service_id Service ID.
				 * @param int                            $package_id Package index.
				 * @param int                            $pay_order  Existing order ID (0 if new).
				 */
				$result = apply_filters( 'wpss_rest_create_payment_intent', null, $gateway, $amount, $currency, $service_id, $package_id, $pay_order );

				if ( null !== $result ) {
					return $result;
				}

				return new WP_Error( 'unsupported_gateway', __( 'This gateway is not supported for REST payments.', 'wp-sell-services' ), array( 'status' => 400 ) );
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
			case 'stripe':
				return $this->confirm_stripe_payment( $gateway, $payment_id, $service_id, $package_id, $pay_order );

			case 'paypal':
				return $this->confirm_paypal_payment( $gateway, $payment_id, $service_id, $package_id, $pay_order );

			default:
				/**
				 * Filter to handle custom payment gateway confirmation via REST.
				 *
				 * @since 1.1.0
				 *
				 * @param WP_REST_Response|WP_Error|null $result     Default null.
				 * @param object                         $gateway    Gateway instance.
				 * @param string                         $payment_id Gateway payment ID.
				 * @param int                            $service_id Service ID.
				 * @param int                            $package_id Package index.
				 * @param int                            $pay_order  Existing order ID (0 if new).
				 */
				$result = apply_filters( 'wpss_rest_confirm_payment', null, $gateway, $payment_id, $service_id, $package_id, $pay_order );

				if ( null !== $result ) {
					return $result;
				}

				return new WP_Error( 'unsupported_gateway', __( 'This gateway does not support REST confirmation.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Create Stripe payment intent.
	 *
	 * @param object $gateway    Stripe gateway instance.
	 * @param float  $amount     Payment amount.
	 * @param string $currency   Currency code.
	 * @param int    $service_id Service ID.
	 * @param int    $package_id Package index.
	 * @param int    $pay_order  Existing order ID (0 if new).
	 * @return WP_REST_Response|WP_Error
	 */
	private function create_stripe_intent( object $gateway, float $amount, string $currency, int $service_id, int $package_id, int $pay_order ) {
		$result = $gateway->create_payment_intent(
			array(
				'amount'      => $amount,
				'currency'    => $currency,
				'service_id'  => $service_id,
				'package_id'  => $package_id,
				// The order this intent pays for. Without it the charge cannot
				// be matched back to an order: the webhook handler resolves the
				// order from metadata['order_id'], so a succeeded payment
				// arrived with nothing to apply it to. Verified against a live
				// Stripe sandbox - the card was charged, Stripe delivered
				// payment_intent.succeeded, and the order sat at
				// pending_payment with no transaction id and no vendor credit.
				// Money in, order unpaid, silently.
				'order_id'    => $pay_order,
				'customer_id' => get_current_user_id(),
			)
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'stripe_error', $result['error'] ?? __( 'Failed to create payment intent.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'gateway'           => 'stripe',
				'client_secret'     => $result['client_secret'],
				'payment_intent_id' => $result['id'],
			),
			201
		);
	}

	/**
	 * Create PayPal order.
	 *
	 * @param object $gateway    PayPal gateway instance.
	 * @param float  $amount     Payment amount.
	 * @param string $currency   Currency code.
	 * @param int    $service_id Service ID.
	 * @param int    $package_id Package index.
	 * @param int    $pay_order  Existing order ID (0 if new).
	 * @return WP_REST_Response|WP_Error
	 */
	private function create_paypal_order( object $gateway, float $amount, string $currency, int $service_id, int $package_id, int $pay_order ) {
		$result = $gateway->create_order(
			array(
				'amount'     => $amount,
				'currency'   => $currency,
				'service_id' => $service_id,
				'package_id' => $package_id,
			)
		);

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
	 * @param object $gateway    Gateway instance.
	 * @param float  $amount     Payment amount.
	 * @param string $currency   Currency code.
	 * @param int    $service_id Service ID.
	 * @param int    $package_id Package index.
	 * @param int    $pay_order  Existing order ID (0 if new).
	 * @return WP_REST_Response|WP_Error
	 */
	private function create_offline_order( object $gateway, float $amount, string $currency, int $service_id, int $package_id, int $pay_order ) {
		// For existing orders (pay_order), just confirm and return.
		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );

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

		// Create a new service order.
		$order_provider = wpss_get_order_provider();

		$order = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $amount,
				'currency'       => $currency,
				'payment_method' => $gateway->get_id(),
			)
		);

		if ( ! $order ) {
			return new WP_Error( 'order_failed', __( 'Failed to create order.', 'wp-sell-services' ), array( 'status' => 500 ) );
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
	 * Confirm Stripe payment and create/update order.
	 *
	 * @param object $gateway    Stripe gateway instance.
	 * @param string $payment_id Payment intent ID.
	 * @param int    $service_id Service ID.
	 * @param int    $package_id Package index.
	 * @param int    $pay_order  Existing order ID (0 if new).
	 * @return WP_REST_Response|WP_Error
	 */
	private function confirm_stripe_payment( object $gateway, string $payment_id, int $service_id, int $package_id, int $pay_order ) {
		// For existing orders (pay_order), verify the payment without creating a new
		// WPSS order. confirm_payment() creates an order internally, so we use
		// process_payment() for the verify-only path.
		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );

			// The order must exist, belong to the caller, and still be awaiting
			// payment — none of which was checked before. Without these guards a
			// buyer could confirm a small payment intent (e.g. a $5 capture)
			// against someone else's $500 order and have it marked paid.
			if ( ! $order ) {
				return new WP_Error( 'rest_order_not_found', __( 'Order not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}

			if ( get_current_user_id() !== (int) $order->customer_id && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'wpss_not_owner', __( 'You can only pay for your own order.', 'wp-sell-services' ), array( 'status' => 403 ) );
			}

			// Idempotency: never re-process an order that is already paid.
			if ( 'paid' === (string) ( $order->payment_status ?? '' ) ) {
				return new WP_REST_Response(
					array(
						'gateway'      => 'stripe',
						'order_id'     => $pay_order,
						'order_number' => $order->order_number,
						'status'       => 'paid',
					)
				);
			}

			$payment = $gateway->process_payment( $payment_id );

			if ( empty( $payment['success'] ) ) {
				return new WP_Error( 'stripe_confirm_error', $payment['error'] ?? __( 'Payment confirmation failed.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}

			// The captured amount MUST match the order total. Compared in the
			// ORDER's currency via integer minor units (wpss_amounts_match), not a
			// hardcoded 0.01 epsilon — that would be wrong for zero-decimal
			// currencies (JPY/KRW) and three-decimal ones (BHD/KWD).
			$captured = (float) ( $payment['amount'] ?? 0 );
			$expected = (float) ( $order->total ?? 0 );
			if ( ! wpss_amounts_match( $captured, $expected, (string) ( $order->currency ?? '' ) ) ) {
				return new WP_Error(
					'rest_amount_mismatch',
					__( 'The paid amount does not match the order total.', 'wp-sell-services' ),
					array( 'status' => 400 )
				);
			}

			$order_provider = wpss_get_order_provider();
			$order_provider->mark_as_paid( $pay_order, $payment_id, 'stripe' );
			$order = wpss_get_order( $pay_order );

			return new WP_REST_Response(
				array(
					'gateway'      => 'stripe',
					'order_id'     => $pay_order,
					'order_number' => $order ? $order->order_number : '',
					'status'       => 'paid',
				)
			);
		}

		// New order: verify + create order via gateway.
		$result = $gateway->confirm_payment(
			array(
				'payment_intent_id' => $payment_id,
				'service_id'        => $service_id,
				'package_id'        => $package_id,
			)
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'stripe_confirm_error', $result['error'] ?? __( 'Payment confirmation failed.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'gateway'      => 'stripe',
				'order_id'     => (int) $result['order_id'],
				'order_number' => $result['order_number'] ?? '',
				'status'       => 'paid',
			)
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
		// For existing orders (pay_order), capture the payment directly without creating
		// a new WPSS order. capture_order() creates an order internally, so we use
		// process_payment() for the capture-only path.
		if ( $pay_order ) {
			$order = wpss_get_order( $pay_order );

			// Same three guards as the Stripe twin (see confirm_stripe_payment).
			// Without them a buyer could capture a small PayPal payment (e.g. $5)
			// against someone else's $500 order and have it marked paid. This hole
			// was closed on Stripe but left open here — fixing the class, not just
			// the instance.
			if ( ! $order ) {
				return new WP_Error( 'rest_order_not_found', __( 'Order not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
			}

			if ( get_current_user_id() !== (int) $order->customer_id && ! current_user_can( 'manage_options' ) ) {
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

			$capture = $gateway->process_payment( $payment_id );

			if ( empty( $capture['success'] ) ) {
				return new WP_Error( 'paypal_confirm_error', $capture['error'] ?? __( 'Payment capture failed.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}

			// Same currency-aware comparison as the Stripe twin.
			$captured = (float) ( $capture['amount'] ?? 0 );
			$expected = (float) ( $order->total ?? 0 );
			if ( ! wpss_amounts_match( $captured, $expected, (string) ( $order->currency ?? '' ) ) ) {
				return new WP_Error(
					'rest_amount_mismatch',
					__( 'The paid amount does not match the order total.', 'wp-sell-services' ),
					array( 'status' => 400 )
				);
			}

			$transaction_id = $capture['transaction_id'] ?? $payment_id;
			$order_provider = wpss_get_order_provider();
			$order_provider->mark_as_paid( $pay_order, $transaction_id, 'paypal' );
			$order = wpss_get_order( $pay_order );

			return new WP_REST_Response(
				array(
					'gateway'      => 'paypal',
					'order_id'     => $pay_order,
					'order_number' => $order ? $order->order_number : '',
					'status'       => 'paid',
				)
			);
		}

		// New order: capture + create order via gateway.
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
