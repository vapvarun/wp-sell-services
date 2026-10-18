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
}
