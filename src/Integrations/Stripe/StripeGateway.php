<?php
/**
 * Stripe Payment Gateway
 *
 * @package WPSellServices\Integrations\Stripe
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Stripe;

use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;

/**
 * Stripe payment gateway implementation.
 *
 * @since 1.0.0
 */
class StripeGateway implements PaymentGatewayInterface {

	/**
	 * Gateway ID.
	 */
	private const GATEWAY_ID = 'stripe';

	/**
	 * Stripe API version.
	 */
	private const API_VERSION = '2023-10-16';

	/**
	 * Settings option name.
	 */
	private const OPTION_NAME = 'wpss_stripe_settings';

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
		return __( 'Stripe', 'wp-sell-services' );
	}

	/**
	 * Get gateway description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Pay securely using your credit or debit card via Stripe.', 'wp-sell-services' );
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return ! empty( $this->settings['enabled'] ) && $this->has_required_keys();
	}

	/**
	 * Check if gateway supports the given currency.
	 *
	 * @param string $currency Currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency ): bool {
		// Stripe supports most major currencies.
		$supported = array(
			'USD',
			'EUR',
			'GBP',
			'AUD',
			'CAD',
			'CHF',
			'CNY',
			'DKK',
			'HKD',
			'INR',
			'JPY',
			'MXN',
			'NOK',
			'NZD',
			'PLN',
			'SEK',
			'SGD',
			'BRL',
		);

		return in_array( strtoupper( $currency ), $supported, true );
	}

	/**
	 * Initialize the gateway.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Hook into consolidated Gateways tab (via Pro.php).
		add_action( 'wpss_gateway_settings_stripe', array( $this, 'render_settings_fields' ) );

		// Handle webhook.
		add_action( 'wpss_payment_callback_stripe', array( $this, 'handle_webhook_callback' ) );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_stripe_create_payment_intent', array( $this, 'ajax_create_payment_intent' ) );
		add_action( 'wp_ajax_wpss_stripe_confirm_payment', array( $this, 'ajax_confirm_payment' ) );
	}

	/**
	 * Create a payment intent.
	 *
	 * @param float  $amount   Amount to charge.
	 * @param string $currency Currency code.
	 * @param array  $metadata Additional metadata.
	 * @return array Payment intent data.
	 */
	public function create_payment( float $amount, string $currency, array $metadata = array() ): array {
		$response = $this->api_request(
			'payment_intents',
			array(
				'amount'                    => $this->format_amount( $amount, $currency ),
				'currency'                  => strtolower( $currency ),
				'automatic_payment_methods' => array( 'enabled' => true ),
				'metadata'                  => array_merge(
					array(
						'site_url' => home_url(),
						'platform' => 'wp-sell-services',
					),
					$metadata
				),
			)
		);

		if ( isset( $response['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? __( 'Failed to create payment intent.', 'wp-sell-services' ),
			);
		}

		return array(
			'success'       => true,
			'id'            => $response['id'],
			'client_secret' => $response['client_secret'],
			'status'        => $response['status'],
		);
	}

	/**
	 * Process a payment.
	 *
	 * @param string $payment_id Payment intent ID.
	 * @return array Payment result.
	 */
	public function process_payment( string $payment_id ): array {
		if ( ! preg_match( '/^pi_[a-zA-Z0-9_]+$/', $payment_id ) ) {
			return array( 'success' => false, 'error' => __( 'Invalid payment ID format.', 'wp-sell-services' ) );
		}

		$response = $this->api_request( "payment_intents/{$payment_id}", array(), 'GET' );

		if ( isset( $response['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? __( 'Failed to process payment.', 'wp-sell-services' ),
			);
		}

		$status = $response['status'] ?? '';

		if ( 'succeeded' === $status ) {
			return array(
				'success'        => true,
				'transaction_id' => $response['id'],
				'status'         => 'completed',
				'amount'         => $this->parse_amount( $response['amount'], $response['currency'] ),
				'currency'       => strtoupper( $response['currency'] ),
			);
		}

		if ( in_array( $status, array( 'requires_payment_method', 'requires_confirmation', 'requires_action' ), true ) ) {
			return array(
				'success' => false,
				'status'  => 'pending',
				'error'   => __( 'Payment requires additional action.', 'wp-sell-services' ),
			);
		}

		return array(
			'success' => false,
			'status'  => 'failed',
			'error'   => __( 'Payment failed.', 'wp-sell-services' ),
		);
	}

	/**
	 * Process a refund.
	 *
	 * @param string     $transaction_id Original transaction ID.
	 * @param float|null $amount         Refund amount (null for full refund).
	 * @param string     $reason         Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array {
		$data = array(
			'payment_intent' => $transaction_id,
		);

		if ( null !== $amount ) {
			// Get original currency from payment intent.
			$payment        = $this->api_request( "payment_intents/{$transaction_id}", array(), 'GET' );
			$currency       = $payment['currency'] ?? 'usd';
			$data['amount'] = $this->format_amount( $amount, $currency );
		}

		if ( $reason ) {
			$data['reason']   = 'requested_by_customer';
			$data['metadata'] = array( 'reason_detail' => $reason );
		}

		$response = $this->api_request( 'refunds', $data );

		if ( isset( $response['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? __( 'Refund failed.', 'wp-sell-services' ),
			);
		}

		return array(
			'success'   => true,
			'refund_id' => $response['id'],
			'status'    => $response['status'],
			'amount'    => $this->parse_amount( $response['amount'], $response['currency'] ),
		);
	}

	/**
	 * Handle webhook callback.
	 *
	 * @param array $payload Webhook payload.
	 * @return array Processing result.
	 */
	public function handle_webhook( array $payload ): array {
		$event_type = $payload['type'] ?? '';
		$data       = $payload['data']['object'] ?? array();

		switch ( $event_type ) {
			case 'payment_intent.succeeded':
				return $this->handle_payment_succeeded( $data );

			case 'payment_intent.payment_failed':
				return $this->handle_payment_failed( $data );

			case 'charge.refunded':
				return $this->handle_refund( $data );

			default:
				return array(
					'success' => true,
					'message' => 'Event type not handled.',
				);
		}
	}

	/**
	 * Handle webhook callback via URL.
	 *
	 * @return void
	 */
	public function handle_webhook_callback(): void {
		$payload    = file_get_contents( 'php://input' );
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

		// Verify webhook signature.
		$endpoint_secret = $this->settings['webhook_secret'] ?? '';

		if ( ! $endpoint_secret ) {
			status_header( 500 );
			echo wp_json_encode( array( 'error' => 'Webhook secret not configured' ) );
			exit;
		}

		$verified = $this->verify_webhook_signature( $payload, $sig_header, $endpoint_secret );

		if ( ! $verified ) {
			status_header( 400 );
			echo wp_json_encode( array( 'error' => 'Invalid signature' ) );
			exit;
		}

		$event    = json_decode( $payload, true );
		$event_id = $event['id'] ?? '';

		// Prevent webhook replay attacks.
		if ( $event_id && get_transient( 'wpss_stripe_event_' . $event_id ) ) {
			status_header( 200 );
			echo wp_json_encode( array( 'message' => 'Already processed' ) );
			exit;
		}

		$result = $this->handle_webhook( $event );

		// Mark event as processed (48-hour dedup window).
		if ( $event_id ) {
			set_transient( 'wpss_stripe_event_' . $event_id, true, 48 * HOUR_IN_SECONDS );
		}

		status_header( 200 );
		echo wp_json_encode( $result );
		exit;
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload    Raw payload.
	 * @param string $sig_header Signature header.
	 * @param string $secret     Webhook secret.
	 * @return bool
	 */
	private function verify_webhook_signature( string $payload, string $sig_header, string $secret ): bool {
		$elements   = explode( ',', $sig_header );
		$timestamp  = null;
		$signatures = array();

		foreach ( $elements as $element ) {
			$parts = explode( '=', $element, 2 );
			if ( 2 === count( $parts ) ) {
				if ( 't' === $parts[0] ) {
					$timestamp = $parts[1];
				} elseif ( 'v1' === $parts[0] ) {
					$signatures[] = $parts[1];
				}
			}
		}

		if ( null === $timestamp || empty( $signatures ) ) {
			return false;
		}

		// Check timestamp is within 5 minutes.
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$signed_payload = "{$timestamp}.{$payload}";
		$expected_sig   = hash_hmac( 'sha256', $signed_payload, $secret );

		foreach ( $signatures as $sig ) {
			if ( hash_equals( $expected_sig, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get gateway settings fields.
	 *
	 * @return array Settings fields configuration.
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled'              => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable Stripe', 'wp-sell-services' ),
				'description' => __( 'Enable Stripe payment gateway.', 'wp-sell-services' ),
			),
			'test_mode'            => array(
				'type'        => 'checkbox',
				'label'       => __( 'Test Mode', 'wp-sell-services' ),
				'description' => __( 'Use Stripe test environment.', 'wp-sell-services' ),
			),
			'test_secret_key'      => array(
				'type'        => 'password',
				'label'       => __( 'Test Secret Key', 'wp-sell-services' ),
				'description' => __( 'Your Stripe test secret key (starts with sk_test_).', 'wp-sell-services' ),
			),
			'test_publishable_key' => array(
				'type'        => 'text',
				'label'       => __( 'Test Publishable Key', 'wp-sell-services' ),
				'description' => __( 'Your Stripe test publishable key (starts with pk_test_).', 'wp-sell-services' ),
			),
			'live_secret_key'      => array(
				'type'        => 'password',
				'label'       => __( 'Live Secret Key', 'wp-sell-services' ),
				'description' => __( 'Your Stripe live secret key (starts with sk_live_).', 'wp-sell-services' ),
			),
			'live_publishable_key' => array(
				'type'        => 'text',
				'label'       => __( 'Live Publishable Key', 'wp-sell-services' ),
				'description' => __( 'Your Stripe live publishable key (starts with pk_live_).', 'wp-sell-services' ),
			),
			'webhook_secret'       => array(
				'type'        => 'password',
				'label'       => __( 'Webhook Secret', 'wp-sell-services' ),
				'description' => __( 'Webhook signing secret for verifying events.', 'wp-sell-services' ),
			),
			'pass_fees_to_buyer'   => array(
				'type'        => 'checkbox',
				'label'       => __( 'Pass Gateway Fees to Buyer', 'wp-sell-services' ),
				'description' => __( 'Add gateway processing fees to the buyer\'s total instead of deducting from vendor earnings.', 'wp-sell-services' ),
			),
			'gateway_fee_percent'  => array(
				'type'        => 'number',
				'label'       => __( 'Gateway Fee (%)', 'wp-sell-services' ),
				'description' => __( 'Percentage fee charged by Stripe (default: 2.9% for US cards).', 'wp-sell-services' ),
				'default'     => '2.9',
				'step'        => '0.01',
				'min'         => '0',
				'max'         => '10',
			),
			'gateway_fee_fixed'    => array(
				'type'        => 'number',
				'label'       => __( 'Gateway Fee (Fixed)', 'wp-sell-services' ),
				'description' => __( 'Fixed fee per transaction in your currency (default: $0.30 for US).', 'wp-sell-services' ),
				'default'     => '0.30',
				'step'        => '0.01',
				'min'         => '0',
				'max'         => '5',
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

		$publishable_key = $this->get_publishable_key();

		ob_start();
		?>
		<div class="wpss-stripe-payment" data-publishable-key="<?php echo esc_attr( $publishable_key ); ?>">
			<div id="wpss-stripe-payment-element"></div>
			<div id="wpss-stripe-error" class="wpss-payment-error" style="display: none;"></div>
			<input type="hidden" name="stripe_payment_intent_id" id="wpss-stripe-payment-intent-id">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Only on checkout pages.
		if ( ! is_page() && ! get_query_var( 'wpss_checkout' ) ) {
			return;
		}

		wp_enqueue_script(
			'stripe-js',
			'https://js.stripe.com/v3/',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		wp_enqueue_script(
			'wpss-stripe',
			WPSS_PLUGIN_URL . 'assets/js/stripe.js',
			array( 'stripe-js', 'jquery' ),
			WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-stripe',
			'wpssStripe',
			array(
				'publishableKey' => $this->get_publishable_key(),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wpss_stripe' ),
				'returnUrl'      => home_url( '/checkout/complete/' ),
				'i18n'           => array(
					'processing' => __( 'Processing...', 'wp-sell-services' ),
					'error'      => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * REST: Create payment intent (called by PaymentController).
	 *
	 * @param array $params Validated request params (amount, currency, service_id, package_id).
	 * @return array Result array.
	 */
	public function create_payment_intent( array $params ): array {
		$amount   = (float) ( $params['amount'] ?? 0 );
		$currency = sanitize_text_field( $params['currency'] ?? wpss_get_currency() );

		if ( $amount <= 0 ) {
			return array( 'success' => false, 'error' => __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		return $this->create_payment(
			$amount,
			$currency,
			array(
				'service_id'  => (int) ( $params['service_id'] ?? 0 ),
				'package_id'  => (int) ( $params['package_id'] ?? 0 ),
				'customer_id' => get_current_user_id(),
			)
		);
	}

	/**
	 * REST: Confirm payment and create order (called by PaymentController).
	 *
	 * @param array $params Validated request params (payment_intent_id, service_id, package_id).
	 * @return array Result array.
	 */
	public function confirm_payment( array $params ): array {
		$payment_intent_id = sanitize_text_field( $params['payment_intent_id'] ?? '' );
		$service_id        = (int) ( $params['service_id'] ?? 0 );
		$package_id        = (int) ( $params['package_id'] ?? 0 );

		if ( ! $payment_intent_id ) {
			return array( 'success' => false, 'error' => __( 'Invalid payment.', 'wp-sell-services' ) );
		}

		$payment = $this->process_payment( $payment_intent_id );

		if ( ! $payment['success'] ) {
			return array( 'success' => false, 'error' => $payment['error'] ?? __( 'Payment verification failed.', 'wp-sell-services' ) );
		}

		$service = wpss_get_service( $service_id );
		if ( ! $service ) {
			return array( 'success' => false, 'error' => __( 'Service not found.', 'wp-sell-services' ) );
		}

		$order_provider = wpss_get_order_provider();
		$order          = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $payment['amount'],
				'currency'       => $payment['currency'],
				'payment_method' => 'stripe',
			)
		);

		if ( ! $order ) {
			$this->process_refund( $payment_intent_id );
			return array( 'success' => false, 'error' => __( 'Failed to create order.', 'wp-sell-services' ) );
		}

		$order_provider->mark_as_paid( $order->id, $payment_intent_id, 'stripe' );

		return array(
			'success'      => true,
			'order_id'     => $order->id,
			'order_number' => $order->order_number,
			'redirect_url' => home_url( '/service-order/' . $order->id . '/requirements/' ),
		);
	}

	/**
	 * AJAX: Create payment intent.
	 *
	 * @return void
	 */
	public function ajax_create_payment_intent(): void {
		check_ajax_referer( 'wpss_stripe', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$currency   = sanitize_text_field( $_POST['currency'] ?? wpss_get_currency() );
		$service_id = (int) ( $_POST['service_id'] ?? 0 );
		$package_id = (int) ( $_POST['package_id'] ?? 0 );

		// Verify amount server-side from package price.
		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type || 'publish' !== $service->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
			return;
		}

		$packages = get_post_meta( $service_id, '_wpss_packages', true );
		if ( ! is_array( $packages ) || ! isset( $packages[ $package_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package.', 'wp-sell-services' ) ) );
			return;
		}

		$amount = (float) $packages[ $package_id ]['price'];
		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid amount.', 'wp-sell-services' ) ) );
			return;
		}

		$result = $this->create_payment(
			$amount,
			$currency,
			array(
				'service_id'  => $service_id,
				'package_id'  => $package_id,
				'customer_id' => get_current_user_id(),
			)
		);

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}
	}

	/**
	 * AJAX: Confirm payment and create order.
	 *
	 * @return void
	 */
	public function ajax_confirm_payment(): void {
		check_ajax_referer( 'wpss_stripe', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$payment_intent_id = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );
		$service_id        = (int) ( $_POST['service_id'] ?? 0 );
		$package_id        = (int) ( $_POST['package_id'] ?? 0 );

		if ( ! $payment_intent_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		// Verify payment succeeded.
		$payment = $this->process_payment( $payment_intent_id );

		if ( ! $payment['success'] ) {
			wp_send_json_error( array( 'message' => $payment['error'] ?? __( 'Payment verification failed.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		// Get service and package details.
		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		// Create order.
		$order_provider = wpss_get_order_provider();
		$order          = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $payment['amount'],
				'currency'       => $payment['currency'],
				'payment_method' => 'stripe',
			)
		);

		if ( ! $order ) {
			// Refund if order creation fails.
			$this->process_refund( $payment_intent_id );
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		// Mark as paid.
		$order_provider->mark_as_paid( $order->id, $payment_intent_id, 'stripe' );

		wp_send_json_success(
			array(
				'order_id'     => $order->id,
				'order_number' => $order->order_number,
				'redirect_url' => home_url( '/service-order/' . $order->id . '/requirements/' ),
			)
		);
	}

	/**
	 * Handle payment succeeded webhook.
	 *
	 * @param array $payment_intent Payment intent data.
	 * @return array
	 */
	private function handle_payment_succeeded( array $payment_intent ): array {
		$metadata = $payment_intent['metadata'] ?? array();

		// If order was already created via AJAX, just confirm.
		if ( ! empty( $metadata['order_id'] ) ) {
			$order_provider = wpss_get_order_provider();
			$order_provider->mark_as_paid(
				(int) $metadata['order_id'],
				$payment_intent['id'],
				'stripe'
			);
		}

		return array(
			'success' => true,
			'message' => 'Payment processed.',
		);
	}

	/**
	 * Handle payment failed webhook.
	 *
	 * @param array $payment_intent Payment intent data.
	 * @return array
	 */
	private function handle_payment_failed( array $payment_intent ): array {
		// Log failure for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Stripe payment failed: ' . wp_json_encode( $payment_intent ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return array(
			'success' => true,
			'message' => 'Payment failure logged.',
		);
	}

	/**
	 * Handle refund webhook.
	 *
	 * @param array $charge Charge data.
	 * @return array
	 */
	private function handle_refund( array $charge ): array {
		$payment_intent_id = $charge['payment_intent'] ?? '';

		if ( $payment_intent_id ) {
			/**
			 * Fires when a Stripe refund is processed.
			 *
			 * @param string $payment_intent_id Payment intent ID.
			 * @param array  $charge            Charge data.
			 */
			do_action( 'wpss_stripe_refund_processed', $payment_intent_id, $charge );
		}

		return array(
			'success' => true,
			'message' => 'Refund processed.',
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'wpss_stripe_settings', self::OPTION_NAME );
	}

	/**
	 * Render settings fields for the consolidated Gateways tab.
	 *
	 * Called via wpss_gateway_settings_stripe action from Pro.php.
	 *
	 * @return void
	 */
	public function render_settings_fields(): void {
		$fields = $this->get_settings_fields();
		?>
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

		<h4><?php esc_html_e( 'Webhook URL', 'wp-sell-services' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Add this URL to your Stripe webhook settings:', 'wp-sell-services' ); ?>
		</p>
		<code><?php echo esc_html( home_url( '/wpss-payment/stripe/callback/' ) ); ?></code>
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
		$value   = $this->settings[ $key ] ?? ( $field['default'] ?? '' );
		$name    = self::OPTION_NAME . "[{$key}]";
		$step    = $field['step'] ?? '1';
		$min_val = $field['min'] ?? '';
		$max_val = $field['max'] ?? '';

		switch ( $field['type'] ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, '1' ); ?>>
					<?php echo esc_html( $field['label'] ); ?>
				</label>
				<?php
				break;

			case 'password':
				?>
				<input type="password" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
				<?php
				break;

			case 'number':
				?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" step="<?php echo esc_attr( $step ); ?>" <?php echo '' !== $min_val ? 'min="' . esc_attr( $min_val ) . '"' : ''; ?> <?php echo '' !== $max_val ? 'max="' . esc_attr( $max_val ) . '"' : ''; ?>>
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

	/**
	 * Check if required keys are configured.
	 *
	 * @return bool
	 */
	private function has_required_keys(): bool {
		if ( $this->is_test_mode() ) {
			return ! empty( $this->settings['test_secret_key'] ) && ! empty( $this->settings['test_publishable_key'] );
		}

		return ! empty( $this->settings['live_secret_key'] ) && ! empty( $this->settings['live_publishable_key'] );
	}

	/**
	 * Check if test mode is enabled.
	 *
	 * @return bool
	 */
	private function is_test_mode(): bool {
		return ! empty( $this->settings['test_mode'] );
	}

	/**
	 * Get secret key.
	 *
	 * @return string
	 */
	private function get_secret_key(): string {
		return $this->is_test_mode()
			? ( $this->settings['test_secret_key'] ?? '' )
			: ( $this->settings['live_secret_key'] ?? '' );
	}

	/**
	 * Get publishable key.
	 *
	 * @return string
	 */
	private function get_publishable_key(): string {
		return $this->is_test_mode()
			? ( $this->settings['test_publishable_key'] ?? '' )
			: ( $this->settings['live_publishable_key'] ?? '' );
	}

	/**
	 * Make Stripe API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 * @return array
	 */
	private function api_request( string $endpoint, array $data = array(), string $method = 'POST' ): array {
		$url = 'https://api.stripe.com/v1/' . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization'  => 'Bearer ' . $this->get_secret_key(),
				'Stripe-Version' => self::API_VERSION,
				'Content-Type'   => 'application/x-www-form-urlencoded',
			),
			'timeout' => 30,
		);

		if ( 'POST' === $method && ! empty( $data ) ) {
			$args['body'] = $this->build_request_body( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => array(
					'message' => $response->get_error_message(),
				),
			);
		}

		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
	}

	/**
	 * Build request body for Stripe API.
	 *
	 * @param array  $data   Data to encode.
	 * @param string $prefix Key prefix.
	 * @return string
	 */
	private function build_request_body( array $data, string $prefix = '' ): string {
		$result = array();

		foreach ( $data as $key => $value ) {
			$full_key = $prefix ? "{$prefix}[{$key}]" : $key;

			if ( is_array( $value ) ) {
				$result[] = $this->build_request_body( $value, $full_key );
			} else {
				$result[] = rawurlencode( $full_key ) . '=' . rawurlencode( (string) $value );
			}
		}

		return implode( '&', array_filter( $result ) );
	}

	/**
	 * Format amount for Stripe (convert to smallest currency unit).
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return int
	 */
	private function format_amount( float $amount, string $currency ): int {
		// Zero-decimal currencies.
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Parse amount from Stripe (convert from smallest currency unit).
	 *
	 * @param int    $amount   Amount in smallest unit.
	 * @param string $currency Currency code.
	 * @return float
	 */
	private function parse_amount( int $amount, string $currency ): float {
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (float) $amount;
		}

		return $amount / 100.0;
	}
}
