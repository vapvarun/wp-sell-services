<?php
/**
 * Test Payment Gateway
 *
 * Development-only gateway for testing the full order flow without real payments.
 *
 * @package WPSellServices\Integrations\Gateways
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Gateways;

use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;

/**
 * Test payment gateway implementation.
 *
 * Only available when WP_DEBUG is enabled. Auto-completes payments for testing.
 *
 * @since 1.2.0
 */
class TestGateway implements PaymentGatewayInterface {

	/**
	 * Gateway ID.
	 */
	private const GATEWAY_ID = 'test';

	/**
	 * Settings option name.
	 */
	private const OPTION_NAME = 'wpss_test_gateway_settings';

	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();
	}

	/**
	 * Get the unique gateway identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return self::GATEWAY_ID;
	}

	/**
	 * Get the gateway display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Test Gateway', 'wp-sell-services' );
	}

	/**
	 * Get gateway description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Test payment gateway for development. Payments complete instantly without real charges.', 'wp-sell-services' );
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * Only enabled when WP_DEBUG is true and settings have it enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		// Only available in debug mode.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Check if gateway supports the given currency.
	 *
	 * Test gateway supports all currencies.
	 *
	 * @param string $currency Currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency ): bool {
		return true;
	}

	/**
	 * Initialize the gateway.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Hook into consolidated Gateways tab.
		add_action( 'wpss_gateway_settings_test', array( $this, 'render_settings_fields' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_test_process_payment', array( $this, 'ajax_process_payment' ) );
	}

	/**
	 * Create a payment (mock).
	 *
	 * @param float  $amount   Amount to charge.
	 * @param string $currency Currency code.
	 * @param array  $metadata Additional metadata.
	 * @return array Payment data.
	 */
	public function create_payment( float $amount, string $currency, array $metadata = array() ): array {
		return array(
			'success' => true,
			'id'      => 'test_' . wp_generate_uuid4(),
			'status'  => 'pending',
		);
	}

	/**
	 * Process a payment (always succeeds).
	 *
	 * @param string $payment_id Payment ID.
	 * @return array Payment result.
	 */
	public function process_payment( string $payment_id ): array {
		return array(
			'success'        => true,
			'transaction_id' => $payment_id,
			'status'         => 'completed',
		);
	}

	/**
	 * Process a refund (mock - just logs).
	 *
	 * @param string     $transaction_id Original transaction ID.
	 * @param float|null $amount         Refund amount (null for full refund).
	 * @param string     $reason         Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array {
		wpss_log( sprintf( 'Test refund processed: %s, amount: %s, reason: %s', $transaction_id, $amount ?? 'full', $reason ), 'info' );

		return array(
			'success'   => true,
			'refund_id' => 'test_refund_' . wp_generate_uuid4(),
			'status'    => 'completed',
		);
	}

	/**
	 * Handle webhook callback (no-op for test gateway).
	 *
	 * @param array $payload Webhook payload.
	 * @return array Processing result.
	 */
	public function handle_webhook( array $payload ): array {
		return array(
			'success' => true,
			'message' => 'Test gateway does not use webhooks.',
		);
	}

	/**
	 * Get gateway settings fields.
	 *
	 * @return array Settings fields configuration.
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Gateway', 'wp-sell-services' ),
				'description' => __( 'Enable test payment gateway (only works when WP_DEBUG is true).', 'wp-sell-services' ),
			),
		);
	}

	/**
	 * Render payment form/button.
	 *
	 * @param float  $amount   Amount to pay.
	 * @param string $currency Currency code.
	 * @param int    $order_id Order ID (0 if not yet created).
	 * @return string HTML output.
	 */
	public function render_payment_form( float $amount, string $currency, int $order_id ): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		ob_start();
		?>
		<div class="wpss-test-gateway-form">
			<div class="wpss-test-gateway-notice" style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
				<strong style="color: #856404;">
					<?php esc_html_e( 'Development Mode', 'wp-sell-services' ); ?>
				</strong>
				<p style="color: #856404; margin: 8px 0 0;">
					<?php esc_html_e( 'This is a test payment. No real charges will be made.', 'wp-sell-services' ); ?>
				</p>
			</div>
			<input type="hidden" name="wpss_gateway" value="test">
			<input type="hidden" name="wpss_test_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpss_test_payment' ) ); ?>">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Process test payment.
	 *
	 * Creates order and immediately marks as paid.
	 *
	 * @return void
	 */
	public function ajax_process_payment(): void {
		check_ajax_referer( 'wpss_test_payment', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
			return;
		}

		// Verify debug mode.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			wp_send_json_error( array( 'message' => __( 'Test gateway is only available in debug mode.', 'wp-sell-services' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$service_id = isset( $_POST['service_id'] ) ? (int) wp_unslash( $_POST['service_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$package_id = isset( $_POST['package_id'] ) ? (int) wp_unslash( $_POST['package_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$quantity = isset( $_POST['quantity'] ) ? max( 1, (int) wp_unslash( $_POST['quantity'] ) ) : 1;

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
			return;
		}

		// Get service and package details.
		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
			return;
		}

		// Calculate price from package.
		$packages = wpss_get_service_packages( $service_id );
		$price    = 0;

		if ( isset( $packages[ $package_id ] ) ) {
			$price = (float) ( $packages[ $package_id ]['price'] ?? 0 );
		}

		// Fallback to starting price.
		if ( $price <= 0 ) {
			$price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
		}

		// Apply quantity.
		$price *= $quantity;

		// Get order provider.
		$order_provider = wpss_get_order_provider();

		if ( ! $order_provider ) {
			wp_send_json_error( array( 'message' => __( 'No order provider available.', 'wp-sell-services' ) ) );
			return;
		}

		// Generate test transaction ID.
		$transaction_id = 'test_' . wp_generate_uuid4();

		// Create order.
		$order = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'quantity'       => $quantity,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $price,
				'currency'       => wpss_get_currency(),
				'payment_method' => 'test',
			)
		);

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
			return;
		}

		// Immediately mark as paid.
		$order_provider->mark_as_paid( $order->id, $transaction_id, 'test' );

		wpss_log( sprintf( 'Test payment completed: Order #%d, Transaction: %s', $order->id, $transaction_id ), 'info' );

		wp_send_json_success(
			array(
				'order_id'     => $order->id,
				'order_number' => $order->order_number,
				'redirect_url' => wpss_get_order_requirements_url( $order->id ),
			)
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'wpss_test_gateway_settings', self::OPTION_NAME );
	}

	/**
	 * Render settings fields for the consolidated Gateways tab.
	 *
	 * @return void
	 */
	public function render_settings_fields(): void {
		$fields = $this->get_settings_fields();
		?>
		<div class="wpss-test-gateway-warning" style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
			<strong style="color: #856404;">
				<?php esc_html_e( 'Development Only', 'wp-sell-services' ); ?>
			</strong>
			<p style="color: #856404; margin: 8px 0 0;">
				<?php esc_html_e( 'This gateway is only available when WP_DEBUG is enabled. It should never be used in production.', 'wp-sell-services' ); ?>
			</p>
		</div>

		<table class="form-table">
			<?php foreach ( $fields as $key => $field ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $field['label'] ); ?></th>
					<td>
						<?php $this->render_field( $key, $field ); ?>
						<?php if ( ! empty( $field['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) : ?>
			<p class="description" style="color: #dc3545;">
				<?php esc_html_e( 'WP_DEBUG is currently disabled. Enable it in wp-config.php to use this gateway.', 'wp-sell-services' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a settings field.
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field config.
	 * @return void
	 */
	private function render_field( string $key, array $field ): void {
		$value = $this->settings[ $key ] ?? ( $field['default'] ?? '' );
		$name  = self::OPTION_NAME . "[{$key}]";

		switch ( $field['type'] ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, '1' ); ?>>
					<?php echo esc_html( $field['label'] ); ?>
				</label>
				<?php
				break;

			default:
				?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
				<?php
		}
	}

	/**
	 * Get gateway settings.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		return get_option( self::OPTION_NAME, array() );
	}
}
