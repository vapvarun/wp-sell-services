<?php
/**
 * Payment Gateway Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for payment gateway implementations.
 *
 * @since 1.0.0
 */
interface PaymentGatewayInterface {

	/**
	 * Get the unique gateway identifier.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the gateway display name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get gateway description.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Check if gateway supports the given currency.
	 *
	 * @param string $currency Currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency ): bool;

	/**
	 * Initialize the gateway.
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Create a payment intent/session.
	 *
	 * @param float  $amount   Amount to charge.
	 * @param string $currency Currency code.
	 * @param array  $metadata Additional metadata.
	 * @return array Payment intent data (id, client_secret, etc.).
	 */
	public function create_payment( float $amount, string $currency, array $metadata = [] ): array;

	/**
	 * Process a payment.
	 *
	 * @param string $payment_id Payment intent ID.
	 * @return array Payment result (success, transaction_id, etc.).
	 */
	public function process_payment( string $payment_id ): array;

	/**
	 * Process a refund.
	 *
	 * @param string     $transaction_id Original transaction ID.
	 * @param float|null $amount         Refund amount (null for full refund).
	 * @param string     $reason         Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array;

	/**
	 * Handle webhook callback.
	 *
	 * @param array $payload Webhook payload.
	 * @return array Processing result.
	 */
	public function handle_webhook( array $payload ): array;

	/**
	 * Get gateway settings fields.
	 *
	 * @return array Settings fields configuration.
	 */
	public function get_settings_fields(): array;

	/**
	 * Render payment form/button.
	 *
	 * @param float  $amount   Amount to pay.
	 * @param string $currency Currency code.
	 * @param int    $order_id Order ID.
	 * @return string HTML output.
	 */
	public function render_payment_form( float $amount, string $currency, int $order_id ): string;

	/**
	 * Start a payment from the REST API (POST /payments/intent).
	 *
	 * Core resolves and prices the purchase, then hands the gateway the finished intent. Return the
	 * response the client needs to continue (a client secret, an approval URL, or the created order).
	 * Core never names a gateway; a gateway that cannot be driven over REST returns a WP_Error.
	 *
	 * @since 1.9.0
	 *
	 * @param \WPSellServices\Checkout\CheckoutIntent $intent  Server-priced intent.
	 * @param array<string, mixed>                    $request Resolver request: pay_order, service_id, package_id, quantity, addons.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_create_intent( \WPSellServices\Checkout\CheckoutIntent $intent, array $request );

	/**
	 * Confirm a payment from the REST API (POST /payments/confirm).
	 *
	 * @since 1.9.0
	 *
	 * @param array<string, mixed> $params payment_id, service_id, package_id, pay_order.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_confirm_payment( array $params );

	/**
	 * How this gateway appears in Settings > Payments, or null to stay off that screen.
	 *
	 * The card body is the gateway's own `wpss_gateway_settings_{id}` fields.
	 *
	 * @since 1.9.0
	 *
	 * @return array{title: string, description: string, option_group: string, order: int}|null
	 */
	public function get_settings_card(): ?array;
}
