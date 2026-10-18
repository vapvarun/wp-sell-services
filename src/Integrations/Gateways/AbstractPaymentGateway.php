<?php
/**
 * Abstract Payment Gateway
 *
 * Defaults for the parts of PaymentGatewayInterface most gateways do not customise, so a
 * third-party gateway overrides only what it needs. Extend this instead of implementing the
 * interface directly.
 *
 * @package WPSellServices\Integrations\Gateways
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Gateways;

use WPSellServices\Checkout\CheckoutIntent;
use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for payment gateways.
 *
 * @since 1.9.0
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface {

	/**
	 * {@inheritdoc}
	 *
	 * Falls back to the `wpss_rest_create_payment_intent` filter, which is how a gateway added
	 * before 1.9.0 handled REST payments.
	 */
	public function rest_create_intent( CheckoutIntent $intent, array $request ) {
		/** This filter is documented in docs/website/developer-guide/hooks-filters.md. */
		$result = apply_filters(
			'wpss_rest_create_payment_intent',
			null,
			$this,
			(float) $intent->amount,
			(string) $intent->currency,
			(int) ( $request['service_id'] ?? 0 ),
			(int) ( $request['package_id'] ?? 0 ),
			(int) ( $request['pay_order'] ?? 0 )
		);

		if ( null !== $result ) {
			return $result;
		}

		return new \WP_Error( 'unsupported_gateway', __( 'This gateway is not supported for REST payments.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Falls back to the `wpss_rest_confirm_payment` filter, like rest_create_intent().
	 */
	public function rest_confirm_payment( array $params ) {
		/** This filter is documented in docs/website/developer-guide/hooks-filters.md. */
		$result = apply_filters(
			'wpss_rest_confirm_payment',
			null,
			$this,
			(string) ( $params['payment_id'] ?? '' ),
			(int) ( $params['service_id'] ?? 0 ),
			(int) ( $params['package_id'] ?? 0 ),
			(int) ( $params['pay_order'] ?? 0 )
		);

		if ( null !== $result ) {
			return $result;
		}

		return new \WP_Error( 'unsupported_gateway', __( 'This gateway does not support REST confirmation.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_card(): ?array {
		return array(
			'title'        => $this->get_name(),
			'description'  => $this->get_description(),
			'option_group' => 'wpss_' . $this->get_id() . '_settings',
			'order'        => 50,
		);
	}

	/**
	 * Mark an existing order paid from a verified gateway payment.
	 *
	 * Through the same settle() the website checkout uses, not a second copy of
	 * its checks: it compares amount and currency, and holds the per-payment
	 * lock while asking whether this payment has already paid an order. The
	 * copy that lived here checked the amount and nothing else, so one payment
	 * confirmed every equal-priced order the buyer had (Basecamp 10375173905).
	 *
	 * @since 1.8.0
	 *
	 * @param object $order          The order to pay; its owner is already checked.
	 * @param string $gateway_id     Gateway slug.
	 * @param string $transaction_id Verified gateway payment id.
	 * @param float  $amount         Verified paid amount.
	 * @param string $currency       Verified paid currency.
	 * @return true|\WP_Error
	 */
	protected function settle_pay_order( object $order, string $gateway_id, string $transaction_id, float $amount, string $currency ) {
		$checkout = new \WPSellServices\Checkout\CheckoutIntentService();
		$intent   = $checkout->resolve( array( 'pay_order' => (int) $order->id ), (int) $order->customer_id );

		if ( is_wp_error( $intent ) ) {
			return new \WP_Error( $intent->get_error_code(), $intent->get_error_message(), array( 'status' => 400 ) );
		}

		$settle = $checkout->settle( $intent, $gateway_id, $transaction_id, $amount, $currency );

		if ( empty( $settle['success'] ) ) {
			return new \WP_Error( 'rest_amount_mismatch', $settle['error'] ?? __( 'The paid amount does not match the order total.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// settle() answers a payment it has seen before with the order(s) that
		// payment already paid. If this order is not one of them, the payment is spent.
		if ( ! in_array( (int) $order->id, array_map( 'intval', (array) ( $settle['order_ids'] ?? array( $settle['order_id'] ?? 0 ) ) ), true ) ) {
			return new \WP_Error( 'wpss_payment_already_used', __( 'This payment has already been used for another order.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		return true;
	}

	/**
	 * Create order directly for offline/test gateways.
	 *
	 * @param array<string, mixed> $checkout_request Resolve() request.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function create_offline_rest_order( array $checkout_request ) {
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

			if ( ! wpss_record_pending_payment_method( $pay_order, $this->get_id() ) ) {
				return new WP_Error(
					'wpss_payment_method_not_recorded',
					__( 'Could not record the payment method on this order.', 'wp-sell-services' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'gateway'      => $this->get_id(),
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
			$this->get_id()
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
		do_action( 'wpss_rest_offline_order_created', $order->id, $order, $this->get_id() );

		return new WP_REST_Response(
			array(
				'gateway'      => $this->get_id(),
				'order_id'     => (int) $order->id,
				'order_number' => $order->order_number,
				'status'       => 'pending_payment',
			),
			201
		);
	}
}
