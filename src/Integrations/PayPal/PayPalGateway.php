<?php
/**
 * PayPal Payment Gateway
 *
 * @package WPSellServices\Integrations\PayPal
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\PayPal;

use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;

/**
 * PayPal payment gateway implementation using PayPal REST API.
 *
 * @since 1.0.0
 */
class PayPalGateway implements PaymentGatewayInterface {

	/**
	 * Gateway ID.
	 */
	private const GATEWAY_ID = 'paypal';

	/**
	 * Settings option name.
	 */
	private const OPTION_NAME = 'wpss_paypal_settings';

	/**
	 * API endpoints.
	 */
	private const SANDBOX_API_URL = 'https://api-m.sandbox.paypal.com';
	private const LIVE_API_URL    = 'https://api-m.paypal.com';

	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * Access token.
	 *
	 * @var string|null
	 */
	private ?string $access_token = null;

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
		return __( 'PayPal', 'wp-sell-services' );
	}

	/**
	 * Get gateway description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Pay securely using PayPal or credit/debit card.', 'wp-sell-services' );
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return ! empty( $this->settings['enabled'] ) && $this->has_required_credentials();
	}

	/**
	 * Check if gateway supports the given currency.
	 *
	 * @param string $currency Currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency ): bool {
		$supported = array(
			'AUD',
			'BRL',
			'CAD',
			'CNY',
			'CZK',
			'DKK',
			'EUR',
			'HKD',
			'HUF',
			'ILS',
			'JPY',
			'MYR',
			'MXN',
			'TWD',
			'NZD',
			'NOK',
			'PHP',
			'PLN',
			'GBP',
			'RUB',
			'SGD',
			'SEK',
			'CHF',
			'THB',
			'USD',
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
		add_action( 'wpss_gateway_settings_paypal', array( $this, 'render_settings_fields' ) );

		// Handle webhook.
		add_action( 'wpss_payment_callback_paypal', array( $this, 'handle_webhook_callback' ) );

		// Handle return from PayPal.
		add_action( 'wp_ajax_wpss_paypal_capture', array( $this, 'ajax_capture_order' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_paypal_create_order', array( $this, 'ajax_create_order' ) );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Create a PayPal order.
	 *
	 * @param float  $amount   Amount to charge.
	 * @param string $currency Currency code.
	 * @param array  $metadata Additional metadata.
	 * @return array Order data.
	 */
	public function create_payment( float $amount, string $currency, array $metadata = array() ): array {
		$order_data = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'amount'      => array(
						'currency_code' => strtoupper( $currency ),
						'value'         => number_format( $amount, 2, '.', '' ),
					),
					'description' => $metadata['description'] ?? __( 'Service Purchase', 'wp-sell-services' ),
					'custom_id'   => wp_json_encode( $metadata ),
				),
			),
			'payment_source' => array(
				'paypal' => array(
					'experience_context' => array(
						'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
						'brand_name'                => wpss_get_platform_name(),
						'locale'                    => str_replace( '_', '-', get_locale() ),
						'landing_page'              => 'LOGIN',
						'user_action'               => 'PAY_NOW',
						'return_url'                => add_query_arg(
							array(
								'action' => 'wpss_paypal_capture',
								'nonce'  => wp_create_nonce( 'wpss_paypal_capture' ),
							),
							admin_url( 'admin-ajax.php' )
						),
						'cancel_url'                => home_url( '/checkout/cancelled/' ),
					),
				),
			),
		);

		$response = $this->api_request( 'v2/checkout/orders', $order_data );

		if ( isset( $response['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? __( 'Failed to create PayPal order.', 'wp-sell-services' ),
			);
		}

		// Find approval URL.
		$approval_url = '';
		foreach ( $response['links'] ?? array() as $link ) {
			if ( 'payer-action' === $link['rel'] ) {
				$approval_url = $link['href'];
				break;
			}
		}

		return array(
			'success'      => true,
			'id'           => $response['id'],
			'status'       => $response['status'],
			'approval_url' => $approval_url,
		);
	}

	/**
	 * Process (capture) a payment.
	 *
	 * @param string $payment_id PayPal order ID.
	 * @return array Payment result.
	 */
	public function process_payment( string $payment_id ): array {
		$response = $this->api_request( "v2/checkout/orders/{$payment_id}/capture", array() );

		if ( isset( $response['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? __( 'Failed to capture payment.', 'wp-sell-services' ),
			);
		}

		$status = $response['status'] ?? '';

		if ( 'COMPLETED' === $status ) {
			$capture = $response['purchase_units'][0]['payments']['captures'][0] ?? array();

			return array(
				'success'        => true,
				'transaction_id' => $capture['id'] ?? $response['id'],
				'status'         => 'completed',
				'amount'         => (float) ( $capture['amount']['value'] ?? 0 ),
				'currency'       => $capture['amount']['currency_code'] ?? 'USD',
				'payer_email'    => $response['payer']['email_address'] ?? '',
			);
		}

		return array(
			'success' => false,
			'status'  => strtolower( $status ),
			'error'   => __( 'Payment was not completed.', 'wp-sell-services' ),
		);
	}

	/**
	 * Process a refund.
	 *
	 * @param string     $transaction_id Original capture/transaction ID.
	 * @param float|null $amount         Refund amount (null for full refund).
	 * @param string     $reason         Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array {
		$data = array();

		if ( null !== $amount ) {
			// Get original transaction to determine currency.
			$capture  = $this->api_request( "v2/payments/captures/{$transaction_id}", array(), 'GET' );
			$currency = $capture['amount']['currency_code'] ?? 'USD';

			$data['amount'] = array(
				'value'         => number_format( $amount, 2, '.', '' ),
				'currency_code' => $currency,
			);
		}

		if ( $reason ) {
			$data['note_to_payer'] = substr( $reason, 0, 255 );
		}

		$response = $this->api_request( "v2/payments/captures/{$transaction_id}/refund", $data );

		if ( isset( $response['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? __( 'Refund failed.', 'wp-sell-services' ),
			);
		}

		return array(
			'success'   => true,
			'refund_id' => $response['id'],
			'status'    => strtolower( $response['status'] ?? 'completed' ),
			'amount'    => (float) ( $response['amount']['value'] ?? $amount ),
		);
	}

	/**
	 * Handle webhook callback.
	 *
	 * @param array $payload Webhook payload.
	 * @return array Processing result.
	 */
	public function handle_webhook( array $payload ): array {
		$event_type = $payload['event_type'] ?? '';
		$resource   = $payload['resource'] ?? array();

		switch ( $event_type ) {
			case 'CHECKOUT.ORDER.APPROVED':
				return $this->handle_order_approved( $resource );

			case 'PAYMENT.CAPTURE.COMPLETED':
				return $this->handle_capture_completed( $resource );

			case 'PAYMENT.CAPTURE.REFUNDED':
				return $this->handle_refund_completed( $resource );

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
		$payload = file_get_contents( 'php://input' );

		// Verify webhook signature if configured.
		$webhook_id = $this->settings['webhook_id'] ?? '';

		if ( ! $webhook_id ) {
			status_header( 500 );
			echo wp_json_encode( array( 'error' => 'Webhook ID not configured' ) );
			exit;
		}

		$verified = $this->verify_webhook_signature( $payload );

		if ( ! $verified ) {
			status_header( 401 );
			echo wp_json_encode( array( 'error' => 'Invalid signature' ) );
			exit;
		}

		$event    = json_decode( $payload, true );
		$event_id = $event['id'] ?? '';

		// Prevent webhook replay attacks.
		if ( $event_id && get_transient( 'wpss_paypal_event_' . $event_id ) ) {
			status_header( 200 );
			echo wp_json_encode( array( 'message' => 'Already processed' ) );
			exit;
		}

		$result = $this->handle_webhook( $event );

		// Mark event as processed (48-hour dedup window).
		if ( $event_id ) {
			set_transient( 'wpss_paypal_event_' . $event_id, true, 48 * HOUR_IN_SECONDS );
		}

		status_header( 200 );
		echo wp_json_encode( $result );
		exit;
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload Raw payload.
	 * @return bool
	 */
	private function verify_webhook_signature( string $payload ): bool {
		$webhook_id = $this->settings['webhook_id'] ?? '';

		if ( ! $webhook_id ) {
			return true;
		}

		$verification_data = array(
			'auth_algo'         => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
			'cert_url'          => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
			'transmission_id'   => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
			'transmission_sig'  => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
			'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
			'webhook_id'        => $webhook_id,
			'webhook_event'     => json_decode( $payload, true ),
		);

		$response = $this->api_request( 'v1/notifications/verify-webhook-signature', $verification_data );

		return 'SUCCESS' === ( $response['verification_status'] ?? '' );
	}

	/**
	 * Get gateway settings fields.
	 *
	 * @return array Settings fields configuration.
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled'               => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable PayPal', 'wp-sell-services' ),
				'description' => __( 'Enable PayPal payment gateway.', 'wp-sell-services' ),
			),
			'sandbox_mode'          => array(
				'type'        => 'checkbox',
				'label'       => __( 'Sandbox Mode', 'wp-sell-services' ),
				'description' => __( 'Use PayPal sandbox for testing.', 'wp-sell-services' ),
			),
			'sandbox_client_id'     => array(
				'type'        => 'text',
				'label'       => __( 'Sandbox Client ID', 'wp-sell-services' ),
				'description' => __( 'PayPal sandbox app client ID.', 'wp-sell-services' ),
			),
			'sandbox_client_secret' => array(
				'type'        => 'password',
				'label'       => __( 'Sandbox Client Secret', 'wp-sell-services' ),
				'description' => __( 'PayPal sandbox app client secret.', 'wp-sell-services' ),
			),
			'live_client_id'        => array(
				'type'        => 'text',
				'label'       => __( 'Live Client ID', 'wp-sell-services' ),
				'description' => __( 'PayPal live app client ID.', 'wp-sell-services' ),
			),
			'live_client_secret'    => array(
				'type'        => 'password',
				'label'       => __( 'Live Client Secret', 'wp-sell-services' ),
				'description' => __( 'PayPal live app client secret.', 'wp-sell-services' ),
			),
			'webhook_id'            => array(
				'type'        => 'text',
				'label'       => __( 'Webhook ID', 'wp-sell-services' ),
				'description' => __( 'PayPal webhook ID for signature verification.', 'wp-sell-services' ),
			),
			'pass_fees_to_buyer'    => array(
				'type'        => 'checkbox',
				'label'       => __( 'Pass Gateway Fees to Buyer', 'wp-sell-services' ),
				'description' => __( 'Add gateway processing fees to the buyer\'s total instead of deducting from vendor earnings.', 'wp-sell-services' ),
			),
			'gateway_fee_percent'   => array(
				'type'        => 'number',
				'label'       => __( 'Gateway Fee (%)', 'wp-sell-services' ),
				'description' => __( 'Percentage fee charged by PayPal (default: 2.9% for US domestic).', 'wp-sell-services' ),
				'default'     => '2.9',
				'step'        => '0.01',
				'min'         => '0',
				'max'         => '10',
			),
			'gateway_fee_fixed'     => array(
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

		$client_id = $this->get_client_id();

		ob_start();
		?>
		<div class="wpss-paypal-payment" data-client-id="<?php echo esc_attr( $client_id ); ?>">
			<div id="wpss-paypal-button-container"></div>
			<div id="wpss-paypal-error" class="wpss-payment-error" style="display: none;"></div>
			<input type="hidden" name="paypal_order_id" id="wpss-paypal-order-id">
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

		$client_id = $this->get_client_id();
		$currency  = wpss_get_currency();

		wp_enqueue_script(
			'paypal-sdk',
			add_query_arg(
				array(
					'client-id' => $client_id,
					'currency'  => $currency,
					'intent'    => 'capture',
				),
				'https://www.paypal.com/sdk/js'
			),
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		wp_enqueue_script(
			'wpss-paypal',
			WPSS_PLUGIN_URL . 'assets/js/paypal.js',
			array( 'paypal-sdk', 'jquery' ),
			WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-paypal',
			'wpssPayPal',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_paypal' ),
				'i18n'    => array(
					'processing' => __( 'Processing...', 'wp-sell-services' ),
					'error'      => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * REST: Create PayPal order (called by PaymentController).
	 *
	 * @param array $params Validated request params (amount, currency, service_id, package_id).
	 * @return array Result array.
	 */
	public function create_order( array $params ): array {
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
				'description' => sprintf(
					/* translators: %d: Service ID */
					__( 'Service #%d', 'wp-sell-services' ),
					(int) ( $params['service_id'] ?? 0 )
				),
			)
		);
	}

	/**
	 * REST: Capture PayPal order and create service order (called by PaymentController).
	 *
	 * @param array $params Validated request params (paypal_order_id, service_id, package_id).
	 * @return array Result array.
	 */
	public function capture_order( array $params ): array {
		$paypal_order_id = sanitize_text_field( $params['paypal_order_id'] ?? '' );
		$service_id      = (int) ( $params['service_id'] ?? 0 );
		$package_id      = (int) ( $params['package_id'] ?? 0 );

		if ( ! $paypal_order_id ) {
			return array( 'success' => false, 'error' => __( 'Invalid PayPal order.', 'wp-sell-services' ) );
		}

		$payment = $this->process_payment( $paypal_order_id );

		if ( ! $payment['success'] ) {
			return array( 'success' => false, 'error' => $payment['error'] ?? __( 'Payment capture failed.', 'wp-sell-services' ) );
		}

		$order_provider = wpss_get_order_provider();

		if ( ! $order_provider ) {
			return array( 'success' => false, 'error' => __( 'No order provider available.', 'wp-sell-services' ) );
		}

		$order = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $payment['amount'],
				'currency'       => $payment['currency'],
				'payment_method' => 'paypal',
			)
		);

		if ( ! $order ) {
			$this->process_refund( $payment['transaction_id'] );
			return array( 'success' => false, 'error' => __( 'Failed to create order.', 'wp-sell-services' ) );
		}

		$order_provider->mark_as_paid( $order->id, $payment['transaction_id'], 'paypal' );

		return array(
			'success'      => true,
			'order_id'     => $order->id,
			'order_number' => $order->order_number,
			'redirect_url' => wpss_get_order_requirements_url( $order->id ),
		);
	}

	/**
	 * AJAX: Create PayPal order.
	 *
	 * @return void
	 */
	public function ajax_create_order(): void {
		check_ajax_referer( 'wpss_paypal', 'nonce' );

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
				'description' => sprintf(
					/* translators: %d: Service ID */
					__( 'Service #%d', 'wp-sell-services' ),
					$service_id
				),
			)
		);

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}
	}

	/**
	 * AJAX: Capture PayPal order.
	 *
	 * @return void
	 */
	public function ajax_capture_order(): void {
		// Handle both GET (return URL) and POST (AJAX).
		$nonce           = $_REQUEST['nonce'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paypal_order_id = sanitize_text_field( $_REQUEST['token'] ?? $_REQUEST['paypal_order_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, 'wpss_paypal_capture' ) && ! wp_verify_nonce( $nonce, 'wpss_paypal' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
				return; // Explicit return for defensive coding.
			} else {
				wp_safe_redirect( home_url( '/checkout/error/' ) );
				exit;
			}
		}

		if ( ! $paypal_order_id ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => __( 'Invalid PayPal order.', 'wp-sell-services' ) ) );
				return; // Explicit return for defensive coding.
			} else {
				wp_safe_redirect( home_url( '/checkout/error/' ) );
				exit;
			}
		}

		// Capture the payment.
		$payment = $this->process_payment( $paypal_order_id );

		if ( ! $payment['success'] ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => $payment['error'] ?? __( 'Payment capture failed.', 'wp-sell-services' ) ) );
				return; // Explicit return for defensive coding.
			} else {
				wp_safe_redirect( home_url( '/checkout/error/' ) );
				exit;
			}
		}

		// Get metadata from PayPal order.
		$order_details = $this->api_request( "v2/checkout/orders/{$paypal_order_id}", array(), 'GET' );
		$custom_id     = $order_details['purchase_units'][0]['custom_id'] ?? '{}';
		$metadata      = json_decode( $custom_id, true ) ?: array();

		$service_id  = (int) ( $metadata['service_id'] ?? 0 );
		$package_id  = (int) ( $metadata['package_id'] ?? 0 );
		$customer_id = (int) ( $metadata['customer_id'] ?? get_current_user_id() );

		// Create service order.
		$order_provider = wpss_get_order_provider();

		if ( ! $order_provider ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => __( 'No order provider available.', 'wp-sell-services' ) ) );
				return;
			} else {
				wp_safe_redirect( home_url( '/checkout/error/' ) );
				exit;
			}
		}

		$order = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'customer_id'    => $customer_id,
				'subtotal'       => $payment['amount'],
				'currency'       => $payment['currency'],
				'payment_method' => 'paypal',
			)
		);

		if ( ! $order ) {
			// Refund if order creation fails.
			$this->process_refund( $payment['transaction_id'] );

			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
				return; // Explicit return for defensive coding.
			} else {
				wp_safe_redirect( home_url( '/checkout/error/' ) );
				exit;
			}
		}

		// Mark as paid.
		$order_provider->mark_as_paid( $order->id, $payment['transaction_id'], 'paypal' );

		$redirect_url = wpss_get_order_requirements_url( $order->id );

		if ( wp_doing_ajax() ) {
			wp_send_json_success(
				array(
					'order_id'     => $order->id,
					'order_number' => $order->order_number,
					'redirect_url' => $redirect_url,
				)
			);
		} else {
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Handle order approved webhook.
	 *
	 * @param array $resource Order resource.
	 * @return array
	 */
	private function handle_order_approved( array $resource ): array {
		// Order approved, ready to capture - usually handled client-side.
		return array(
			'success' => true,
			'message' => 'Order approved.',
		);
	}

	/**
	 * Handle capture completed webhook.
	 *
	 * @param array $resource Capture resource.
	 * @return array
	 */
	private function handle_capture_completed( array $resource ): array {
		$custom_id = $resource['custom_id'] ?? '';
		$metadata  = json_decode( $custom_id, true ) ?: array();

		if ( ! empty( $metadata['order_id'] ) ) {
			$order_provider = wpss_get_order_provider();

			if ( $order_provider ) {
				$order_provider->mark_as_paid(
					(int) $metadata['order_id'],
					$resource['id'],
					'paypal'
				);
			}
		}

		return array(
			'success' => true,
			'message' => 'Capture processed.',
		);
	}

	/**
	 * Handle refund completed webhook.
	 *
	 * @param array $resource Refund resource.
	 * @return array
	 */
	private function handle_refund_completed( array $resource ): array {
		/**
		 * Fires when a PayPal refund is processed.
		 *
		 * @param string $capture_id Capture ID.
		 * @param array  $resource   Refund resource.
		 */
		do_action( 'wpss_paypal_refund_processed', $resource['links'][0]['href'] ?? '', $resource );

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
		register_setting( 'wpss_paypal_settings', self::OPTION_NAME );
	}

	/**
	 * Render settings fields for the consolidated Gateways tab.
	 *
	 * Called via wpss_gateway_settings_paypal action from Pro.php.
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

		<div class="wpss-gateway-setup-guide" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c8d8e6; border-radius: 4px;">
			<h4 style="margin-top: 0;"><?php esc_html_e( 'PayPal Setup Guide', 'wp-sell-services' ); ?></h4>

			<p><strong><?php esc_html_e( 'Step 1: Create a REST API App', 'wp-sell-services' ); ?></strong></p>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'Go to PayPal Developer Dashboard → My Apps & Credentials', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'Click "Create App" under REST API apps', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'Copy the Client ID and Secret from the app details', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'For testing, switch to "Sandbox" mode to get sandbox credentials', 'wp-sell-services' ); ?></li>
			</ol>

			<p><strong><?php esc_html_e( 'Step 2: Configure Webhook', 'wp-sell-services' ); ?></strong></p>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'In your REST API app, scroll to "Webhooks" and click "Add Webhook"', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'Enter this URL:', 'wp-sell-services' ); ?>
					<br><code style="display: inline-block; margin: 5px 0; padding: 4px 8px; background: #fff;"><?php echo esc_html( home_url( '/wpss-payment/paypal/callback/' ) ); ?></code>
				</li>
				<li><?php esc_html_e( 'Select the following events to subscribe:', 'wp-sell-services' ); ?>
					<ul style="margin: 5px 0 5px 20px; list-style: disc;">
						<li><code>CHECKOUT.ORDER.APPROVED</code></li>
						<li><code>PAYMENT.CAPTURE.COMPLETED</code></li>
						<li><code>PAYMENT.CAPTURE.REFUNDED</code></li>
					</ul>
				</li>
				<li><?php esc_html_e( 'After creating the webhook, copy the "Webhook ID" and paste it in the Webhook ID field above.', 'wp-sell-services' ); ?></li>
			</ol>

			<p><strong><?php esc_html_e( 'Step 3: Required API Scopes', 'wp-sell-services' ); ?></strong></p>
			<p style="margin-bottom: 5px; margin-left: 20px;">
				<?php esc_html_e( 'Your REST API app needs these scopes enabled. Configure them in the PayPal Developer Dashboard under your app settings:', 'wp-sell-services' ); ?>
			</p>
			<table style="margin: 5px 0 0 20px; border-collapse: collapse; width: auto;">
				<thead>
					<tr>
						<th style="text-align: left; padding: 6px 12px; border: 1px solid #ddd; background: #f6f7f7;"><?php esc_html_e( 'Scope', 'wp-sell-services' ); ?></th>
						<th style="text-align: left; padding: 6px 12px; border: 1px solid #ddd; background: #f6f7f7;"><?php esc_html_e( 'Used For', 'wp-sell-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><code style="font-size: 12px;">payments/payment</code></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Create checkout orders (Orders v2 API)', 'wp-sell-services' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><code style="font-size: 12px;">payments/payment/authcapture</code></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Capture approved payments', 'wp-sell-services' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><code style="font-size: 12px;">payments/refund</code></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Issue full or partial refunds', 'wp-sell-services' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><code style="font-size: 12px;">payments</code></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Read capture details for refund calculations', 'wp-sell-services' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><code style="font-size: 12px;">webhooks</code></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Verify webhook signatures', 'wp-sell-services' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p style="margin: 10px 0 0 20px; color: #646970;">
				<?php esc_html_e( 'Full scope URIs are prefixed with https://uri.paypal.com/services/. Most scopes are enabled by default for new apps.', 'wp-sell-services' ); ?>
			</p>
			<p style="margin: 5px 0 0 20px;">
				<a href="https://docs.paypal.ai/developer/how-to/apps-scopes-credentials" target="_blank" rel="noopener"><?php esc_html_e( 'PayPal documentation: Apps & scopes &rarr;', 'wp-sell-services' ); ?></a>
			</p>
		</div>
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
	 * Check if required credentials are configured.
	 *
	 * @return bool
	 */
	private function has_required_credentials(): bool {
		if ( $this->is_sandbox_mode() ) {
			return ! empty( $this->settings['sandbox_client_id'] ) && ! empty( $this->settings['sandbox_client_secret'] );
		}

		return ! empty( $this->settings['live_client_id'] ) && ! empty( $this->settings['live_client_secret'] );
	}

	/**
	 * Check if sandbox mode is enabled.
	 *
	 * @return bool
	 */
	private function is_sandbox_mode(): bool {
		return ! empty( $this->settings['sandbox_mode'] );
	}

	/**
	 * Get API base URL.
	 *
	 * @return string
	 */
	private function get_api_url(): string {
		return $this->is_sandbox_mode() ? self::SANDBOX_API_URL : self::LIVE_API_URL;
	}

	/**
	 * Get client ID.
	 *
	 * @return string
	 */
	private function get_client_id(): string {
		return $this->is_sandbox_mode()
			? ( $this->settings['sandbox_client_id'] ?? '' )
			: ( $this->settings['live_client_id'] ?? '' );
	}

	/**
	 * Get client secret.
	 *
	 * @return string
	 */
	private function get_client_secret(): string {
		return $this->is_sandbox_mode()
			? ( $this->settings['sandbox_client_secret'] ?? '' )
			: ( $this->settings['live_client_secret'] ?? '' );
	}

	/**
	 * Get access token.
	 *
	 * @return string|null
	 */
	private function get_access_token(): ?string {
		if ( $this->access_token ) {
			return $this->access_token;
		}

		// Check cached token.
		$cached = get_transient( 'wpss_paypal_access_token' );

		if ( $cached ) {
			$this->access_token = $cached;
			return $cached;
		}

		// Request new token.
		$client_id     = $this->get_client_id();
		$client_secret = $this->get_client_secret();

		$response = wp_remote_post(
			$this->get_api_url() . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$client_id}:{$client_secret}" ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['access_token'] ) ) {
			$this->access_token = $body['access_token'];
			$expires_in         = (int) ( $body['expires_in'] ?? 3600 ) - 60; // Buffer of 60 seconds.

			set_transient( 'wpss_paypal_access_token', $this->access_token, $expires_in );

			return $this->access_token;
		}

		return null;
	}

	/**
	 * Make PayPal API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 * @return array
	 */
	private function api_request( string $endpoint, array $data = array(), string $method = 'POST' ): array {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return array(
				'error' => array(
					'message' => __( 'Failed to authenticate with PayPal.', 'wp-sell-services' ),
				),
			);
		}

		$url = $this->get_api_url() . '/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( 'POST' === $method && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => array(
					'message' => $response->get_error_message(),
				),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			return array(
				'error' => array(
					'message' => $body['message'] ?? $body['details'][0]['description'] ?? __( 'PayPal API error.', 'wp-sell-services' ),
					'details' => $body,
				),
			);
		}

		return $body;
	}
}
