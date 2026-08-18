<?php
/**
 * Stripe Payment Gateway
 *
 * @package WPSellServices\Integrations\Stripe
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Stripe;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;
use WPSellServices\Assets\ScriptRegistry;

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
	 * Where the last verified webhook receipt is recorded.
	 *
	 * @since 1.4.0
	 * @var string
	 */
	private const HEALTH_OPTION = 'wpss_stripe_webhook_health';

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

		// The standalone checkout form submits to the generic gateway contract
		// `wpss_{gateway}_process_payment` (see StandaloneCheckoutProvider). Only
		// the Offline and Test gateways implemented that name, so clicking "Pay"
		// with Stripe posted to an unregistered action and admin-ajax returned a
		// bare `0` — checkout could never complete. Map the contract onto the
		// existing confirm handler rather than duplicating the finalize logic.
		add_action( 'wp_ajax_wpss_stripe_process_payment', array( $this, 'ajax_confirm_payment' ) );
	}

	/**
	 * Build Stripe shipping details for a buyer.
	 *
	 * Uses the shared billing identity stored on the user - WooCommerce's
	 * billing_* meta keys - so the address a buyer has already given any Wbcom
	 * product is reused rather than asked for again.
	 *
	 * Returns an empty array when there is no usable address; a partial
	 * shipping object is worse than none, because Stripe rejects it outright.
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id Buyer user ID.
	 * @return array<string, mixed> Stripe shipping payload, or empty.
	 */
	private function get_customer_shipping( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array();
		}

		$name = trim( get_user_meta( $user_id, 'billing_first_name', true ) . ' ' . get_user_meta( $user_id, 'billing_last_name', true ) );
		$name = '' !== $name ? $name : $user->display_name;

		$line1   = (string) get_user_meta( $user_id, 'billing_address_1', true );
		$city    = (string) get_user_meta( $user_id, 'billing_city', true );
		$country = (string) get_user_meta( $user_id, 'billing_country', true );

		// Stripe needs at least line1, city and country for the address to be
		// accepted. Anything less is rejected, so send nothing and let the
		// gateway surface its own error rather than a malformed request.
		if ( '' === $line1 || '' === $city || '' === $country ) {
			return array();
		}

		$shipping = array(
			'name'    => $name,
			'address' => array(
				'line1'       => $line1,
				'city'        => $city,
				'country'     => $country,
				'line2'       => (string) get_user_meta( $user_id, 'billing_address_2', true ),
				'state'       => (string) get_user_meta( $user_id, 'billing_state', true ),
				'postal_code' => (string) get_user_meta( $user_id, 'billing_postcode', true ),
			),
		);

		/**
		 * Filter the buyer shipping details sent to Stripe.
		 *
		 * @since 1.4.0
		 *
		 * @param array $shipping Stripe shipping payload.
		 * @param int   $user_id  Buyer user ID.
		 */
		return (array) apply_filters( 'wpss_stripe_customer_shipping', $shipping, $user_id );
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
		$order_id  = (int) ( $metadata['order_id'] ?? 0 );
		$vendor_id = (int) ( $metadata['vendor_id'] ?? 0 );

		// A description is REQUIRED by Stripe for export transactions on Indian
		// accounts (and is good practice everywhere — it shows on the customer's
		// statement/receipt). Without it confirmPayment() fails outright with
		// "As per Indian regulations, export transactions require a description".
		$service_id  = (int) ( $metadata['service_id'] ?? 0 );
		$description = $service_id ? get_the_title( $service_id ) : '';
		if ( '' === trim( (string) $description ) ) {
			$description = __( 'Service purchase', 'wp-sell-services' );
		}
		if ( $order_id ) {
			$description .= ' (#' . $order_id . ')';
		}

		$params = array(
			'amount'                    => $this->format_amount( $amount, $currency ),
			'currency'                  => strtolower( $currency ),
			'automatic_payment_methods' => array( 'enabled' => 'true' ),
			/**
			 * Filter the Stripe PaymentIntent description.
			 *
			 * @since 1.2.2
			 *
			 * @param string $description Statement/receipt description.
			 * @param int    $order_id    Order ID (0 if not yet created).
			 * @param array  $metadata    Payment metadata.
			 */
			'description'               => apply_filters( 'wpss_stripe_payment_description', $description, $order_id, $metadata ),
			'metadata'                  => array_merge(
				array(
					'site_url' => home_url(),
					'platform' => 'wp-sell-services',
				),
				$metadata
			),
		);

		// Buyer name and address on the intent.
		//
		// Not cosmetic: an India-based Stripe account cannot process an export
		// (non-INR) charge without them. Stripe rejects the confirmation with
		// "As per Indian regulations, export transactions require a customer
		// name and address", so on a plugin that sent neither, EVERY USD charge
		// from an Indian merchant failed - and the buyer only found out at the
		// moment they tried to pay. Verified against a live sandbox: identical
		// intent fails without these and passes with them.
		//
		// Read from the shared billing identity on the user (WooCommerce's
		// billing_* meta), so one address serves every Wbcom product rather
		// than each collecting its own.
		$shipping = $this->get_customer_shipping( (int) ( $metadata['customer_id'] ?? get_current_user_id() ) );

		if ( ! empty( $shipping ) ) {
			$params['shipping'] = $shipping;
		}

		/**
		 * Filter Stripe PaymentIntent parameters before creation.
		 *
		 * Pro uses this to add transfer_data for Stripe Connect splits.
		 *
		 * @since 1.1.0
		 *
		 * @param array $params    PaymentIntent parameters.
		 * @param int   $order_id  Order ID (0 if not yet created).
		 * @param int   $vendor_id Vendor user ID (0 if unknown).
		 */
		$params = apply_filters( 'wpss_stripe_payment_intent_args', $params, $order_id, $vendor_id );

		$response = $this->api_request( 'payment_intents', $params );

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
			return array(
				'success' => false,
				'error'   => __( 'Invalid payment ID format.', 'wp-sell-services' ),
			);
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

		// A declined card and an unfinished 3DS are both "not paid yet", but they
		// need OPPOSITE instructions: use a different card, versus finish the
		// step you already started. These three statuses used to collapse into
		// one "requires additional action" message, so a buyer whose card was
		// declined was told to complete an action that does not exist and had no
		// way to learn the real reason. Verified against a live sandbox decline
		// (card_declined / generic_decline) on 2026-08-01.
		//
		// requires_payment_method means "declined" only once an attempt has been
		// made; on a fresh intent it just means nothing was submitted yet.
		// last_payment_error is what distinguishes the two.
		$last_error = $response['last_payment_error'] ?? array();

		if ( 'requires_payment_method' === $status && ! empty( $last_error ) ) {
			// Stripe's own message is written for the cardholder, is already
			// localized by Stripe, and names the actual reason (insufficient
			// funds, expired card) far more precisely than we could infer from
			// a decline code.
			$declined_message = ! empty( $last_error['message'] )
				? (string) $last_error['message']
				: __( 'Your card was declined. Please try a different payment method.', 'wp-sell-services' );

			/**
			 * Filters the message shown to a buyer whose card was declined.
			 *
			 * The default is Stripe's own cardholder-facing message. Site owners
			 * who want their own wording — or who must not surface a bank's
			 * reason verbatim — can replace it here without overriding a
			 * template.
			 *
			 * @since 1.4.0
			 *
			 * @param string $declined_message Message shown to the buyer.
			 * @param array  $last_error       Stripe's last_payment_error payload.
			 * @param array  $response         The full PaymentIntent.
			 */
			$declined_message = (string) apply_filters( 'wpss_payment_declined_message', $declined_message, $last_error, $response );

			return array(
				'success'      => false,
				'status'       => 'failed',
				'error'        => $declined_message,
				'decline_code' => (string) ( $last_error['decline_code'] ?? $last_error['code'] ?? '' ),
			);
		}

		if ( in_array( $status, array( 'requires_payment_method', 'requires_confirmation', 'requires_action' ), true ) ) {
			$action_message = __( 'Payment requires additional action.', 'wp-sell-services' );

			/**
			 * Filters the message shown when a payment still needs a buyer step
			 * (typically 3D Secure authentication).
			 *
			 * @since 1.4.0
			 *
			 * @param string $action_message Message shown to the buyer.
			 * @param string $status         PaymentIntent status.
			 * @param array  $response       The full PaymentIntent.
			 */
			$action_message = (string) apply_filters( 'wpss_payment_action_required_message', $action_message, $status, $response );

			return array(
				'success' => false,
				'status'  => 'pending',
				'error'   => $action_message,
			);
		}

		return array(
			'success' => false,
			'status'  => 'failed',
			'error'   => __( 'Payment failed.', 'wp-sell-services' ),
		);
	}

	/**
	 * Retrieve a PaymentIntent from Stripe.
	 *
	 * Narrow public accessor over the private api_request(). Exists so Pro can
	 * inspect an intent (e.g. StripeConnect reading transfer_data to detect a
	 * direct vendor settlement) without duplicating the API key handling,
	 * endpoint and error conventions that live in this class.
	 *
	 * @since 1.2.3
	 *
	 * @param string $payment_intent_id Stripe PaymentIntent id.
	 * @return array<string, mixed> Intent data, or an empty array when it cannot be retrieved.
	 */
	public function get_payment_intent( string $payment_intent_id ): array {
		if ( '' === $payment_intent_id ) {
			return array();
		}

		$intent = $this->api_request( "payment_intents/{$payment_intent_id}", array(), 'GET' );

		if ( isset( $intent['error'] ) ) {
			wpss_log( "Stripe: could not retrieve PaymentIntent {$payment_intent_id}.", 'warning' );
			return array();
		}

		return $intent;
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
		// Retrieve the original PaymentIntent: needed to resolve the currency
		// for partial refunds AND to detect Stripe Connect split payments
		// (transfer_data) so the refund carries explicit Connect flags.
		$payment_intent = $this->api_request( "payment_intents/{$transaction_id}", array(), 'GET' );

		if ( isset( $payment_intent['error'] ) ) {
			wpss_log( "Stripe refund: could not retrieve PaymentIntent {$transaction_id} before refunding; proceeding without Connect detection.", 'warning' );
			$payment_intent = array();
		}

		$data = $this->build_refund_args( $transaction_id, $amount, $reason, $payment_intent );

		$response = $this->api_request( 'refunds', $data );

		// Did this refund pull funds back out of a connected account? Reported
		// so callers can tell a settled clawback from a failed one. Split
		// payments send the vendor's share directly at charge time, and
		// reverse_transfer fails routinely once they have paid out to their
		// bank — in which case the money must be recovered some other way, and
		// silently assuming success is how a platform eats the loss.
		//
		// null = not a split payment, so there was nothing to reverse.
		$expected_reversal = ! empty( $data['reverse_transfer'] );
		$transfer_reversed = $expected_reversal
			? ! empty( $response['transfer_reversal'] )
			: null;

		if ( isset( $response['error'] ) ) {
			return array(
				'success'           => false,
				'error'             => $response['error']['message'] ?? __( 'Refund failed.', 'wp-sell-services' ),
				'transfer_reversed' => $expected_reversal ? false : null,
			);
		}

		if ( $expected_reversal && ! $transfer_reversed ) {
			wpss_log(
				sprintf(
					'Stripe refund %1$s succeeded but the transfer reversal did NOT settle — the vendor still holds their share.',
					$response['id'] ?? $transaction_id
				),
				'warning'
			);
		}

		return array(
			'success'           => true,
			'refund_id'         => $response['id'],
			'status'            => $response['status'],
			'amount'            => $this->parse_amount( $response['amount'], $response['currency'] ),
			'transfer_reversed' => $transfer_reversed,
		);
	}

	/**
	 * Build the argument array for a POST /v1/refunds request.
	 *
	 * Stripe Connect semantics for destination charges (PaymentIntents created
	 * with `transfer_data` + `application_fee_amount`):
	 *
	 * - `reverse_transfer` — when true, Stripe reverses the transfer to the
	 *   CONNECTED (vendor) account so the buyer's refund is funded from the
	 *   vendor's balance. Stripe's API default is FALSE, which leaves the
	 *   platform out of pocket, so this gateway defaults it to TRUE for
	 *   Connect split payments. On partial refunds Stripe reverses the
	 *   transfer proportionally — only the flag needs to be passed.
	 * - `refund_application_fee` — when true, the platform's application fee
	 *   is also refunded. Stripe's API default is FALSE; this gateway keeps
	 *   FALSE so the platform retains its commission (common marketplace
	 *   policy). Override per refund via the `wpss_stripe_refund_args` filter.
	 *
	 * Both flags are sent only when the original PaymentIntent was a Connect
	 * split (detected via `transfer_data.destination` on the retrieved
	 * PaymentIntent). Non-Connect refunds send the same request as before:
	 * `payment_intent` plus optional `amount`/`reason` — no new parameters.
	 *
	 * Public (rather than private) so the request shape can be inspected in
	 * tests without performing live Stripe calls.
	 *
	 * @since 1.2.0
	 *
	 * @param string               $transaction_id PaymentIntent ID being refunded.
	 * @param float|null           $amount         Refund amount (null for full refund).
	 * @param string               $reason         Refund reason.
	 * @param array<string, mixed> $payment_intent Retrieved PaymentIntent data (empty array if retrieval failed).
	 * @return array<string, mixed> Refund request arguments.
	 */
	public function build_refund_args( string $transaction_id, ?float $amount, string $reason, array $payment_intent ): array {
		$args = array(
			'payment_intent' => $transaction_id,
		);

		if ( null !== $amount ) {
			$currency       = $payment_intent['currency'] ?? 'usd';
			$args['amount'] = $this->format_amount( $amount, $currency );
		}

		if ( $reason ) {
			$args['reason']   = 'requested_by_customer';
			$args['metadata'] = array( 'reason_detail' => $reason );
		}

		// Connect split payment: the intent was created with transfer_data
		// (injected by Pro via the wpss_stripe_payment_intent_args filter).
		if ( ! empty( $payment_intent['transfer_data']['destination'] ) ) {
			$args['reverse_transfer']       = true;
			$args['refund_application_fee'] = false;
		}

		/**
		 * Filter the Stripe refund request arguments.
		 *
		 * Lets platforms (and Pro) override the Connect refund flags per
		 * refund — e.g. set `refund_application_fee` to true to return the
		 * platform commission to the customer, or `reverse_transfer` to
		 * false to fund the refund from the platform balance instead of the
		 * connected vendor account.
		 *
		 * @since 1.2.0
		 *
		 * @param array      $args           Refund arguments sent to POST /v1/refunds.
		 * @param string     $transaction_id PaymentIntent ID being refunded.
		 * @param array      $payment_intent Retrieved PaymentIntent data (empty array if retrieval failed).
		 * @param float|null $amount         Requested refund amount (null for full refund).
		 * @param string     $reason         Refund reason supplied by the caller.
		 */
		$args = apply_filters( 'wpss_stripe_refund_args', $args, $transaction_id, $payment_intent, $amount, $reason );

		// Stripe's form-encoded API requires literal "true"/"false" strings:
		// build_request_body() casts values with (string), and PHP false
		// would encode as an empty string, which Stripe rejects.
		foreach ( array( 'reverse_transfer', 'refund_application_fee' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$args[ $flag ] = filter_var( $args[ $flag ], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false';
			}
		}

		return $args;
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

		// Record that a SIGNATURE-VERIFIED event arrived. This is the only
		// honest evidence the owner's webhook is actually wired up: they follow
		// the setup guide, paste a secret, and otherwise have no way to know it
		// worked until a real buyer's order fails to appear.
		//
		// Recorded here rather than in handle_webhook_callback() so an
		// unsigned or replayed request cannot make the indicator claim health.
		update_option(
			self::HEALTH_OPTION,
			array(
				'last_event_at'   => time(),
				'last_event_type' => (string) $event_type,
				'mode'            => $this->is_test_mode() ? 'test' : 'live',
			),
			false
		);

		/**
		 * Fires when a Stripe webhook event is received.
		 *
		 * Pro uses this for Connect account updates, subscription billing, and recurring service events.
		 *
		 * @since 1.1.0
		 *
		 * @param string $event_type Stripe event type (e.g. 'payment_intent.succeeded').
		 * @param array  $data       Event data object.
		 * @param array  $payload    Full webhook payload.
		 */
		do_action( 'wpss_stripe_webhook_received', $event_type, $data, $payload );

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
		$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';

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
		<?php
		// `data-wpss-own-submit` tells the generic checkout submit handler to
		// stand down for this gateway: Stripe must confirm the card with the PSP
		// (stripe.js -> confirmPayment) BEFORE any order is created. Without it
		// the generic handler raced stripe.js and posted the still-unconfirmed
		// PaymentIntent, so the card was never charged.
		?>
		<div class="wpss-stripe-payment" data-wpss-own-submit="1" data-publishable-key="<?php echo esc_attr( $publishable_key ); ?>">
			<div id="wpss-stripe-payment-element"></div>
			<?php
			// NOTE: no address element here. Billing details are OUR OWN block,
			// rendered above the payment section from
			// templates/partials/billing-fields.php, because the address is
			// account data rather than card data.
			//
			// Stripe's Address Element used to be mounted here and was wrong on
			// three counts: it rendered the address INSIDE the card iframe, it
			// only existed when Stripe was the gateway (so PayPal/Razorpay/Woo
			// buyers had no address at all), and it has no company or tax-number
			// field — which made the GST an invoice needs impossible to collect.
			//
			// Stripe still RECEIVES the values as billing_details at confirm
			// time; it consumes them, it does not own them.
			?>
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

		// Only on checkout page or single service page (for order modal).
		$checkout_page_id = (int) ( get_option( 'wpss_pages', array() )['checkout'] ?? 0 );
		$is_checkout      = ( $checkout_page_id && is_page( $checkout_page_id ) ) || get_query_var( 'wpss_checkout' );
		$is_service       = is_singular( 'wpss_service' );

		if ( ! $is_checkout && ! $is_service ) {
			return;
		}

		wp_enqueue_script(
			'stripe-js',
			'https://js.stripe.com/v3/',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		ScriptRegistry::enqueue(
			'wpss-stripe',
			'assets/js/stripe.js',
			array( 'stripe-js', 'jquery' )
		);

		wp_localize_script(
			'wpss-stripe',
			'wpssStripe',
			array(
				'publishableKey' => $this->get_publishable_key(),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wpss_stripe' ),
				'returnUrl'      => add_query_arg( 'step', 'complete', wpss_get_page_url( 'checkout' ) ),
				// Prefill the Address Element from the buyer's saved profile so
				// a returning customer enters card details and nothing else.
				// Guest checkout is not allowed on a services marketplace (the
				// buyer has to talk to the vendor), so every checkout has a
				// profile to read from.
				'billing'        => $this->get_billing_defaults(),
				'i18n'           => array(
					'processing'      => __( 'Processing...', 'wp-sell-services' ),
					'error'           => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'addressRequired' => __( 'Please complete your billing name and address.', 'wp-sell-services' ),
					'editAddress'     => __( 'Edit billing address', 'wp-sell-services' ),
					'billedTo'        => __( 'Billed to', 'wp-sell-services' ),
					'invalidAmount'   => __( 'Invalid payment amount.', 'wp-sell-services' ),
					'initFailed'      => __( 'Failed to initialize payment.', 'wp-sell-services' ),
					'notInitialized'  => __( 'Payment not initialized. Please refresh and try again.', 'wp-sell-services' ),
					'orderFailed'     => __( 'Failed to create order.', 'wp-sell-services' ),
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
			return array(
				'success' => false,
				'error'   => __( 'Invalid amount.', 'wp-sell-services' ),
			);
		}

		return $this->create_payment(
			$amount,
			$currency,
			array(
				'service_id'  => (int) ( $params['service_id'] ?? 0 ),
				'package_id'  => (int) ( $params['package_id'] ?? 0 ),
				// Carried through to the intent's metadata so a succeeded
				// payment can be matched back to the order it paid. Dropping it
				// here is how a charged card left an order at pending_payment.
				'order_id'    => (int) ( $params['order_id'] ?? 0 ),
				'customer_id' => (int) ( $params['customer_id'] ?? get_current_user_id() ),
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
			return array(
				'success' => false,
				'error'   => __( 'Invalid payment.', 'wp-sell-services' ),
			);
		}

		$payment = $this->process_payment( $payment_intent_id );

		if ( ! $payment['success'] ) {
			return array(
				'success' => false,
				'error'   => $payment['error'] ?? __( 'Payment verification failed.', 'wp-sell-services' ),
			);
		}

		$service = wpss_get_service( $service_id );
		if ( ! $service ) {
			return array(
				'success' => false,
				'error'   => __( 'Service not found.', 'wp-sell-services' ),
			);
		}

		$order_provider = wpss_get_order_provider();

		$order = $order_provider->create_order(
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
			return array(
				'success' => false,
				'error'   => __( 'Failed to create order.', 'wp-sell-services' ),
			);
		}

		$order_provider->mark_as_paid( $order->id, $payment_intent_id, 'stripe' );

		// Clear cart after successful order creation.
		delete_user_meta( get_current_user_id(), '_wpss_cart' );

		return array(
			'success'      => true,
			'order_id'     => $order->id,
			'order_number' => $order->order_number,
			'redirect_url' => wpss_get_order_requirements_url( $order->id ),
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

		// Pricing + routing (single / multi-cart / pay-order) is resolved once,
		// server-side, by the gateway-agnostic CheckoutIntentService — the client
		// amount is never trusted. See audit/PAYMENT-ARCHITECTURE-RND.md.
		$request = array(
			'pay_order'         => absint( $_POST['pay_order'] ?? 0 ),
			'is_multi_checkout' => ! empty( $_POST['is_multi_checkout'] ),
			'service_id'        => absint( $_POST['service_id'] ?? 0 ),
			'package_id'        => absint( $_POST['package_id'] ?? 0 ),
		);

		$intent = ( new \WPSellServices\Checkout\CheckoutIntentService() )->resolve( $request );
		if ( is_wp_error( $intent ) ) {
			wp_send_json_error( array( 'message' => $intent->get_error_message() ) );
			return;
		}

		$result = $this->create_payment( $intent->amount, $intent->currency, $intent->metadata );
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
		// Accept the Stripe nonce (stripe.js flow) OR the checkout nonce (the
		// standalone checkout form, which posts wpss_stripe_process_payment and
		// carries wpss_checkout_nonce). Mirrors OfflineGateway::ajax_create_order.
		$posted_nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $posted_nonce, 'wpss_stripe' )
			&& ! wp_verify_nonce( $posted_nonce, 'wpss_checkout' )
			&& ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_checkout_nonce'] ?? '' ) ), 'wpss_checkout' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-sell-services' ) ) );
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		// stripe.js sends `payment_intent_id`; the checkout form sends
		// `stripe_payment_intent_id`. Accept both.
		$payment_intent_id = sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ?? $_POST['stripe_payment_intent_id'] ?? '' ) );

		if ( ! $payment_intent_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment.', 'wp-sell-services' ) ) );
			return;
		}

		// Verify payment succeeded.
		$payment = $this->process_payment( $payment_intent_id );

		if ( ! $payment['success'] ) {
			wp_send_json_error( array( 'message' => $payment['error'] ?? __( 'Payment verification failed.', 'wp-sell-services' ) ) );
			return;
		}

		// Remember any billing details the buyer corrected at checkout, BEFORE
		// the order is created. mark_as_paid() snapshots the address from the
		// profile, so saving after that point would stamp the order with the
		// stale address and silently discard the correction.
		//
		// Nonce was verified at the top of this handler.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		wpss_save_billing_from_request( $_POST );

		$checkout = new \WPSellServices\Checkout\CheckoutIntentService();
		$intent   = $checkout->resolve(
			array(
				'pay_order'         => absint( $_POST['pay_order'] ?? 0 ),
				'is_multi_checkout' => ! empty( $_POST['is_multi_checkout'] ),
				'service_id'        => absint( $_POST['service_id'] ?? 0 ),
				'package_id'        => absint( $_POST['package_id'] ?? 0 ),
			)
		);

		// Resolve failed after a successful charge — refund and bail.
		if ( is_wp_error( $intent ) ) {
			$this->process_refund( $payment_intent_id );
			wp_send_json_error( array( 'message' => $intent->get_error_message() ) );
			return;
		}

		$settle = $checkout->settle( $intent, 'stripe', $payment_intent_id, (float) $payment['amount'], (string) $payment['currency'] );

		if ( empty( $settle['success'] ) ) {
			$refund = $this->process_refund( $payment_intent_id );
			if ( empty( $refund['success'] ) ) {
				wpss_log( "CRITICAL: Stripe charge {$payment_intent_id} succeeded but order creation AND refund both failed. Manual intervention required.", 'error' );
			}
			wp_send_json_error( array( 'message' => $settle['error'] ?? __( 'Failed to create order.', 'wp-sell-services' ) ) );
			return;
		}

		// Stamp order id(s) on the PaymentIntent so webhooks can recover.
		if ( ! empty( $settle['order_ids'] ) ) {
			$this->api_request( "payment_intents/{$payment_intent_id}", array( 'metadata' => array( 'order_ids' => implode( ',', $settle['order_ids'] ) ) ) );
		} elseif ( ! empty( $settle['order_id'] ) ) {
			$this->api_request( "payment_intents/{$payment_intent_id}", array( 'metadata' => array( 'order_id' => $settle['order_id'] ) ) );
		}

		wp_send_json_success( $settle );
	}

	/**
	 * Report whether the owner's webhook is actually wired up.
	 *
	 * Stripe is the authority for fulfilment, so a Stripe install with no
	 * working webhook is a site where a buyer can be charged and never get an
	 * order. That state was completely silent — this makes it reportable.
	 *
	 * @since 1.4.0
	 *
	 * @return array{state: string, message: string, last_event_at: int}
	 */
	public function get_webhook_health(): array {
		$health   = (array) get_option( self::HEALTH_OPTION, array() );
		$last     = (int) ( $health['last_event_at'] ?? 0 );
		$has_key  = ! empty( $this->settings['webhook_secret'] );
		$mode     = $this->is_test_mode() ? 'test' : 'live';
		$same_env = ( $health['mode'] ?? $mode ) === $mode;

		if ( ! $has_key ) {
			return array(
				'state'         => 'missing',
				'message'       => __( 'No signing secret saved yet. Until the webhook is set up, a buyer who is redirected to their bank can be charged without an order being created.', 'wp-sell-services' ),
				'last_event_at' => $last,
			);
		}

		if ( $last <= 0 || ! $same_env ) {
			return array(
				'state'         => 'untested',
				'message'       => __( 'A signing secret is saved, but no verified event has arrived yet in this mode. Send a test event from your Stripe dashboard to confirm the endpoint is reachable.', 'wp-sell-services' ),
				'last_event_at' => $last,
			);
		}

		return array(
			'state'         => 'healthy',
			'message'       => sprintf(
				/* translators: %s: human-readable time difference, e.g. "5 mins". */
				__( 'Last verified event received %s ago.', 'wp-sell-services' ),
				human_time_diff( $last, time() )
			),
			'last_event_at' => $last,
		);
	}

	/**
	 * Reconcile a buyer who came back from an off-site authentication.
	 *
	 * A redirect-based 3D Secure (or any redirect payment method) sends the
	 * buyer away and Stripe returns them to our checkout URL with its own
	 * `payment_intent` parameter. Nothing consumed it, so the buyer landed on a
	 * fresh checkout form with a live Pay button over a charge that had already
	 * gone through — an invitation to pay twice.
	 *
	 * The webhook remains the authority for fulfilment, exactly as Stripe
	 * prescribes, because a buyer may close the tab and never come back. This
	 * runs the SAME settle path so the two are interchangeable and idempotent:
	 * whichever arrives first creates the order, the other finds it and stops.
	 * An order is looked up by transaction id before anything is created, so a
	 * webhook that already landed cannot be duplicated by the return leg.
	 *
	 * @since 1.4.0
	 *
	 * @param string $payment_intent_id PaymentIntent id from the return URL.
	 * @return array{status: string, order_id: int, message: string}
	 */
	public function reconcile_redirect_return( string $payment_intent_id ): array {
		$fail = static function ( string $status, string $message, int $order_id = 0 ): array {
			return array(
				'status'   => $status,
				'order_id' => $order_id,
				'message'  => $message,
			);
		};

		if ( ! preg_match( '/^pi_[a-zA-Z0-9_]+$/', $payment_intent_id ) ) {
			return $fail( 'failed', __( 'That payment reference is not valid.', 'wp-sell-services' ) );
		}

		$intent = $this->api_request( "payment_intents/{$payment_intent_id}", array(), 'GET' );

		if ( isset( $intent['error'] ) ) {
			// Stripe telling us the intent does not exist is a definite answer —
			// a stale or tampered URL. Anything else (timeout, outage, bad key)
			// is NOT: the charge may well have succeeded, so the buyer waits for
			// the webhook rather than being told their payment failed.
			//
			// Without this split a bogus id sat on "confirming" and reloaded
			// every few seconds forever.
			$error_code = (string) ( $intent['error']['code'] ?? '' );
			$error_type = (string) ( $intent['error']['type'] ?? '' );

			if ( 'resource_missing' === $error_code || 'invalid_request_error' === $error_type ) {
				return $fail( 'failed', __( 'We could not find that payment. If you were charged, contact the site owner before paying again.', 'wp-sell-services' ) );
			}

			return $fail( 'processing', __( 'We could not confirm your payment just yet. It is safe to wait here; do not pay again.', 'wp-sell-services' ) );
		}

		$status = (string) ( $intent['status'] ?? '' );

		if ( 'succeeded' !== $status ) {
			// process_payment() already separates a decline from an unfinished
			// authentication and carries the buyer-facing reason.
			$checked = $this->process_payment( $payment_intent_id );

			return $fail(
				'processing' === $status ? 'processing' : 'failed',
				(string) ( $checked['error'] ?? __( 'Your payment was not completed.', 'wp-sell-services' ) )
			);
		}

		// Already settled — by the webhook, or by an earlier visit to this URL.
		$existing = $this->find_order_by_transaction( $payment_intent_id );

		if ( $existing > 0 ) {
			return array(
				'status'   => 'paid',
				'order_id' => $existing,
				'message'  => '',
			);
		}

		// Not settled yet: run the same handler the webhook runs.
		$this->handle_payment_succeeded( $intent );

		$order_id = $this->find_order_by_transaction( $payment_intent_id );

		if ( $order_id > 0 ) {
			return array(
				'status'   => 'paid',
				'order_id' => $order_id,
				'message'  => '',
			);
		}

		// Charged, but no order yet. Never tell the buyer to pay again.
		wpss_log( "Stripe return: {$payment_intent_id} succeeded but no order resolved yet; leaving it to the webhook.", 'warning' );

		return $fail( 'processing', __( 'Your payment went through and we are preparing your order.', 'wp-sell-services' ) );
	}

	/**
	 * Find an order already settled against a transaction id.
	 *
	 * @since 1.4.0
	 *
	 * @param string $transaction_id Gateway transaction id.
	 * @return int Order ID, or 0.
	 */
	private function find_order_by_transaction( string $transaction_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE transaction_id = %s ORDER BY id ASC LIMIT 1", $transaction_id )
		);
	}

	/**
	 * Handle payment succeeded webhook.
	 *
	 * @param array $payment_intent Payment intent data.
	 * @return array
	 */
	private function handle_payment_succeeded( array $payment_intent ): array {
		$metadata       = $payment_intent['metadata'] ?? array();
		$order_provider = wpss_get_order_provider();

		// Path 1: Order already created via AJAX — just confirm payment.
		if ( ! empty( $metadata['order_id'] ) ) {
			$order_provider->mark_as_paid(
				(int) $metadata['order_id'],
				$payment_intent['id'],
				'stripe'
			);

			return array(
				'success' => true,
				'message' => 'Order confirmed.',
			);
		}

		// Path 2: AJAX path failed — recover by creating the order from metadata.
		if ( ! empty( $metadata['service_id'] ) && ! empty( $metadata['customer_id'] ) ) {
			$amount   = $this->parse_amount( (int) $payment_intent['amount'], $payment_intent['currency'] ?? 'usd' );
			$currency = strtoupper( $payment_intent['currency'] ?? 'usd' );

			$order = $order_provider->create_order(
				array(
					'service_id'     => (int) $metadata['service_id'],
					'package_id'     => (int) ( $metadata['package_id'] ?? 0 ),
					'customer_id'    => (int) $metadata['customer_id'],
					'subtotal'       => $amount,
					'currency'       => $currency,
					'payment_method' => 'stripe',
				)
			);

			if ( $order ) {
				$order_provider->mark_as_paid( $order->id, $payment_intent['id'], 'stripe' );

				// Store order_id back on PaymentIntent for future webhook deliveries.
				$this->api_request(
					"payment_intents/{$payment_intent['id']}",
					array( 'metadata' => array( 'order_id' => $order->id ) )
				);

				wpss_log( "Webhook recovery: Created order {$order->id} for Stripe payment {$payment_intent['id']}.", 'info' );

				return array(
					'success' => true,
					'message' => 'Order recovered via webhook.',
				);
			}

			wpss_log( "Webhook recovery FAILED: Could not create order for Stripe payment {$payment_intent['id']}.", 'error' );

			return array(
				'success' => false,
				'message' => 'Order creation failed in webhook recovery.',
			);
		}

		// No metadata to work with — log and move on.
		wpss_log( "Stripe webhook: payment_intent.succeeded with no actionable metadata. PI: {$payment_intent['id']}", 'warning' );

		return array(
			'success' => true,
			'message' => 'Payment noted, no order action taken.',
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
		$payment_intent_id = (string) ( $charge['payment_intent'] ?? '' );

		if ( '' === $payment_intent_id ) {
			return array(
				'success' => true,
				'message' => 'Refund had no payment intent to resolve.',
			);
		}

		// Resolve the order this charge paid for. Orders store the intent id in
		// transaction_id when they are marked paid.
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE transaction_id = %s LIMIT 1", $payment_intent_id )
		);

		if ( $order_id <= 0 ) {
			return array(
				'success' => true,
				'message' => 'Refund did not match a service order.',
			);
		}

		$order = wpss_get_order( $order_id );

		// Stripe reports refunds in minor units, so convert with the currency's
		// real precision rather than dividing by 100 - wrong for JPY and KWD.
		$currency = (string) ( $charge['currency'] ?? ( $order->currency ?? '' ) );
		$refunded = wpss_amount_from_minor_units( (int) ( $charge['amount_refunded'] ?? 0 ), $currency );

		if ( $refunded <= 0 ) {
			return array(
				'success' => true,
				'message' => 'Refund amount was zero.',
			);
		}

		$total  = (float) ( $order->total ?? 0 );
		$status = wpss_amounts_match( $refunded, $total, $currency ) || $refunded > $total
			? 'refunded'
			: 'partially_refunded';

		// Through the ONE routine that owns this. It clamps an over-refund,
		// rolls the column back when the status will not move, and reverses the
		// vendor's earning. This handler previously fired an action and
		// returned success without touching the order - and nothing listened to
		// that action, so a real Stripe refund returned the buyer's money and
		// left the order marked paid with the vendor still credited. Verified
		// against a live sandbox refund before this fix.
		// $settled_at_rail = true: this refund HAPPENED at Stripe, we are only
		// recording it. Without that flag the status change re-enters
		// attempt_payment_refund() and refunds the buyer a second time.
		( new \WPSellServices\Services\OrderService() )->apply_refund_status( $order_id, $refunded, $status, true );

		/**
		 * Fires when a Stripe refund is processed.
		 *
		 * @param string $payment_intent_id Payment intent ID.
		 * @param array  $charge            Charge data.
		 */
		do_action( 'wpss_stripe_refund_processed', $payment_intent_id, $charge );

		return array(
			'success' => true,
			'message' => 'Refund applied to order #' . $order_id . '.',
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wpss_stripe_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the settings array before persisting.
	 *
	 * Type-driven: checkbox→'1'/'', text/password/email/url/textarea→
	 * sanitize_text_field, number→clamp to min/max, select→whitelist
	 * against options. Unknown keys dropped to defeat hidden-field
	 * tampering.
	 *
	 * @param mixed $value Raw input from the options form.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $value ): array {
		$value = is_array( $value ) ? $value : array();
		$out   = array();

		foreach ( $this->get_settings_fields() as $key => $field ) {
			$raw = $value[ $key ] ?? '';
			switch ( $field['type'] ?? 'text' ) {
				case 'checkbox':
					$out[ $key ] = '1' === (string) $raw ? '1' : '';
					break;
				case 'select':
					$opts        = is_array( $field['options'] ?? null ) ? array_keys( $field['options'] ) : array();
					$raw         = sanitize_text_field( wp_unslash( (string) $raw ) );
					$out[ $key ] = $opts && ! in_array( $raw, $opts, true ) ? ( $field['default'] ?? '' ) : $raw;
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( wp_unslash( (string) $raw ) );
					break;
				case 'email':
					$out[ $key ] = sanitize_email( wp_unslash( (string) $raw ) );
					break;
				case 'url':
					$out[ $key ] = esc_url_raw( wp_unslash( (string) $raw ) );
					break;
				case 'number':
					$num = '' === $raw ? 0.0 : (float) $raw;
					if ( isset( $field['min'] ) && $num < (float) $field['min'] ) {
						$num = (float) $field['min'];
					}
					if ( isset( $field['max'] ) && $num > (float) $field['max'] ) {
						$num = (float) $field['max'];
					}
					$out[ $key ] = (string) $num;
					break;
				case 'password':
					$raw = sanitize_text_field( wp_unslash( (string) $raw ) );
					// Masked secret fields submit empty to mean "keep the
					// saved value" (Basecamp #9985175367).
					if ( '' === $raw ) {
						$saved       = get_option( self::OPTION_NAME, array() );
						$out[ $key ] = (string) ( is_array( $saved ) ? ( $saved[ $key ] ?? '' ) : '' );
					} else {
						$out[ $key ] = $raw;
					}
					break;
				case 'text':
				default:
					$out[ $key ] = sanitize_text_field( wp_unslash( (string) $raw ) );
			}
		}

		return $out;
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

		<div class="wpss-gateway-setup-guide" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c8d8e6; border-radius: 4px;">
			<h4 style="margin-top: 0;"><?php esc_html_e( 'Stripe Setup Guide', 'wp-sell-services' ); ?></h4>

			<p><strong><?php esc_html_e( 'Step 1: Get your API keys', 'wp-sell-services' ); ?></strong></p>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'Go to your Stripe Dashboard → Developers → API keys', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'Copy your Publishable key and Secret key', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'For testing, use the Test mode keys (starting with pk_test_ and sk_test_)', 'wp-sell-services' ); ?></li>
			</ol>

			<p><strong><?php esc_html_e( 'Step 2: Configure Webhook', 'wp-sell-services' ); ?></strong></p>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'Go to your Stripe Dashboard → Developers → Webhooks', 'wp-sell-services' ); ?></li>
				<li><?php esc_html_e( 'Click "Add endpoint" and enter this URL:', 'wp-sell-services' ); ?>
					<br><code style="display: inline-block; margin: 5px 0; padding: 4px 8px; background: #fff;"><?php echo esc_html( home_url( '/wpss-payment/stripe/callback/' ) ); ?></code>
				</li>
				<li><?php esc_html_e( 'Select the following events to listen for:', 'wp-sell-services' ); ?>
					<ul style="margin: 5px 0 5px 20px; list-style: disc;">
						<li><code>payment_intent.succeeded</code></li>
						<li><code>payment_intent.payment_failed</code></li>
						<li><code>charge.refunded</code></li>
					</ul>
				</li>
				<li><?php esc_html_e( 'After creating the endpoint, copy the "Signing secret" (starts with whsec_) and paste it in the Webhook Secret field above.', 'wp-sell-services' ); ?></li>
			</ol>

			<?php
			// Tell the owner whether the steps above actually worked. Following
			// them correctly and following them incorrectly looked identical
			// until a real buyer's order failed to appear.
			$wpss_health = $this->get_webhook_health();
			$wpss_colors = array(
				'healthy'  => array( '#eef7ee', '#46b450' ),
				'untested' => array( '#fff8e5', '#dba617' ),
				'missing'  => array( '#fcf0f1', '#d63638' ),
			);
			$wpss_style  = $wpss_colors[ $wpss_health['state'] ] ?? $wpss_colors['missing'];
			$wpss_labels = array(
				'healthy'  => __( 'Webhook is receiving events', 'wp-sell-services' ),
				'untested' => __( 'Webhook not confirmed yet', 'wp-sell-services' ),
				'missing'  => __( 'Webhook not set up', 'wp-sell-services' ),
			);
			?>
			<p style="margin: 10px 0 0; padding: 10px 12px; background: <?php echo esc_attr( $wpss_style[0] ); ?>; border-left: 4px solid <?php echo esc_attr( $wpss_style[1] ); ?>;">
				<strong><?php echo esc_html( $wpss_labels[ $wpss_health['state'] ] ?? '' ); ?></strong><br>
				<?php echo esc_html( $wpss_health['message'] ); ?>
			</p>

			<p><strong><?php esc_html_e( 'Step 3: Required Permissions (for Restricted API Keys)', 'wp-sell-services' ); ?></strong></p>
			<p style="margin-left: 20px; margin-bottom: 5px;">
				<?php esc_html_e( 'If you use a restricted API key instead of a standard (unrestricted) key, enable these Core resource permissions in your Stripe Dashboard:', 'wp-sell-services' ); ?>
			</p>
			<table style="margin: 5px 0 0 20px; border-collapse: collapse; width: auto;">
				<thead>
					<tr>
						<th style="text-align: left; padding: 6px 12px; border: 1px solid #ddd; background: #f6f7f7;"><?php esc_html_e( 'Resource', 'wp-sell-services' ); ?></th>
						<th style="text-align: center; padding: 6px 12px; border: 1px solid #ddd; background: #f6f7f7;"><?php esc_html_e( 'Read', 'wp-sell-services' ); ?></th>
						<th style="text-align: center; padding: 6px 12px; border: 1px solid #ddd; background: #f6f7f7;"><?php esc_html_e( 'Write', 'wp-sell-services' ); ?></th>
						<th style="text-align: left; padding: 6px 12px; border: 1px solid #ddd; background: #f6f7f7;"><?php esc_html_e( 'Used For', 'wp-sell-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><strong><?php esc_html_e( 'Payment Intents', 'wp-sell-services' ); ?></strong></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd; text-align: center;">&#10003;</td>
						<td style="padding: 6px 12px; border: 1px solid #ddd; text-align: center;">&#10003;</td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Create and retrieve payment intents', 'wp-sell-services' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><strong><?php esc_html_e( 'Charges and Refunds', 'wp-sell-services' ); ?></strong></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd; text-align: center;">&#10003;</td>
						<td style="padding: 6px 12px; border: 1px solid #ddd; text-align: center;">&#10003;</td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Process charge webhooks and create refunds', 'wp-sell-services' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><strong><?php esc_html_e( 'Payment Methods', 'wp-sell-services' ); ?></strong></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd; text-align: center;">&#10003;</td>
						<td style="padding: 6px 12px; border: 1px solid #ddd; text-align: center;"></td>
						<td style="padding: 6px 12px; border: 1px solid #ddd;"><?php esc_html_e( 'Required by automatic payment methods', 'wp-sell-services' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p style="margin: 10px 0 0 20px; color: #646970;">
				<?php esc_html_e( 'Standard (unrestricted) API keys include all permissions by default. Webhook signature verification uses the Signing Secret — no additional API key permission is needed.', 'wp-sell-services' ); ?>
			</p>
			<p style="margin: 5px 0 0 20px;">
				<a href="https://docs.stripe.com/keys#limit-access" target="_blank" rel="noopener"><?php esc_html_e( 'Stripe documentation: Restricted keys &rarr;', 'wp-sell-services' ); ?></a>
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
				// Masked secret field — never echoes the stored value into
				// HTML; empty submission means "keep saved" (Basecamp
				// #9985175367). Rendered by Settings::render_secret_field().
				do_action(
					'wpss_render_secret_field',
					array(
						'option_name' => self::OPTION_NAME,
						'field'       => $key,
						'label'       => $field['label'],
					)
				);
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
		$settings = get_option( self::OPTION_NAME, array() );
		return is_array( $settings ) ? $settings : array();
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
	 * Billing defaults for the Stripe Address Element, from the saved profile.
	 *
	 * Shaped for Stripe's `defaultValues`, so a returning buyer sees their
	 * address already filled and only has to enter card details. `complete`
	 * tells the client whether the address block can start collapsed.
	 *
	 * Reads WooCommerce-compatible user meta, so on a Woo site — or any other
	 * Wbcom product that captured an address — this is already populated and
	 * the buyer never types it twice.
	 *
	 * @since 1.2.3
	 *
	 * @return array{complete:bool, name:string, address:array<string,string>}
	 */
	private function get_billing_defaults(): array {
		$empty = array(
			'complete' => false,
			'name'     => '',
			'address'  => array(),
		);

		if ( ! function_exists( 'wpss_get_billing_address' ) || ! is_user_logged_in() ) {
			return $empty;
		}

		$billing = wpss_get_billing_address( get_current_user_id() );

		if ( empty( $billing ) ) {
			return $empty;
		}

		$name = trim( ( $billing['billing_first_name'] ?? '' ) . ' ' . ( $billing['billing_last_name'] ?? '' ) );

		return array(
			'complete' => wpss_is_billing_address_complete( $billing ),
			'name'     => $name,
			'address'  => array(
				'line1'       => $billing['billing_address_1'] ?? '',
				'line2'       => $billing['billing_address_2'] ?? '',
				'city'        => $billing['billing_city'] ?? '',
				'state'       => $billing['billing_state'] ?? '',
				'postal_code' => $billing['billing_postcode'] ?? '',
				'country'     => $billing['billing_country'] ?? '',
			),
		);
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

		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( is_array( $body ) && ! empty( $body ) ) {
			// Stripe returned a JSON response — if it contains an error key the
			// callers already handle it, so return as-is regardless of status code.
			return $body;
		}

		// Non-2xx with no parseable JSON body — surface the HTTP error.
		if ( $status_code < 200 || $status_code >= 300 ) {
			$response_message = wp_remote_retrieve_response_message( $response );

			return array(
				'error' => array(
					'message' => sprintf(
						/* translators: 1: HTTP status code, 2: HTTP status message. */
						__( 'Stripe API request failed (HTTP %1$d: %2$s).', 'wp-sell-services' ),
						$status_code,
						$response_message ? $response_message : __( 'Unknown error', 'wp-sell-services' )
					),
				),
			);
		}

		// 2xx but empty/invalid body — should not happen, treat as error.
		return array(
			'error' => array(
				'message' => __( 'Stripe returned an empty or invalid response.', 'wp-sell-services' ),
			),
		);
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
		// Delegates to the canonical converter. This used to carry its own copy of
		// the zero-decimal list and assume two decimals for everything else, so
		// three-decimal currencies (BHD/KWD/TND) were charged 10x wrong.
		return wpss_amount_to_minor_units( $amount, $currency );
	}

	/**
	 * Parse amount from Stripe (convert from smallest currency unit).
	 *
	 * @param int    $amount   Amount in smallest unit.
	 * @param string $currency Currency code.
	 * @return float
	 */
	private function parse_amount( int $amount, string $currency ): float {
		// Inverse of format_amount(); same canonical, currency-aware conversion.
		return wpss_amount_from_minor_units( $amount, $currency );
	}
}
