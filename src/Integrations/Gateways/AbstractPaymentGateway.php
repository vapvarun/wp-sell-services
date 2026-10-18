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
}
