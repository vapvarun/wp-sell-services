<?php
/**
 * The sample bank transfer gateway.
 *
 * A pay-later method: the buyer places the order and sees the account details, pays from their
 * bank, and the site owner confirms the money with Mark as paid. Declaring `manual_confirmation`
 * is what gives it everything else from WP Sell Services: the Mark as paid button, the buyer's
 * "waiting for confirmation" notice and proof-of-payment upload, the Payments to confirm count,
 * and protection from the abandoned-order sweep.
 *
 * @package WPSSSampleBankTransfer
 */

declare(strict_types=1);

namespace WPSSSampleBankTransfer;

use WPSellServices\Checkout\CheckoutIntent;
use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Checkout\ServiceCart;
use WPSellServices\Integrations\Gateways\AbstractPaymentGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Bank transfer, confirmed by hand.
 */
class Gateway extends AbstractPaymentGateway {

	/**
	 * Gateway id: the registry key, the order's payment_method, and the checkout action name.
	 */
	public const ID = 'sample_bank_transfer';

	/**
	 * Option holding the settings, and the settings group of its card (the base default).
	 */
	public const OPTION = 'wpss_' . self::ID . '_settings';

	/**
	 * The money arrives outside the site and the owner confirms it.
	 *
	 * @var string[]
	 */
	protected array $supports = array( 'manual_confirmation' );

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Bank transfer', 'wpss-sample-bank-transfer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The buyer pays into your bank account; you mark the order paid when the money arrives.', 'wpss-sample-bank-transfer' );
	}

	/**
	 * On when the owner switched it on and gave an account to pay into.
	 */
	public function is_enabled(): bool {
		$settings = $this->settings();

		return ! empty( $settings['enabled'] ) && '' !== $settings['account'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_currency( string $currency ): bool {
		return true; // A bank takes whatever the account holds; narrow this for a real provider.
	}

	/**
	 * Nothing is charged at checkout, so the button does not say "Pay".
	 *
	 * @param float  $total    Order total.
	 * @param string $currency Currency code.
	 */
	public function get_checkout_button_label( float $total, string $currency ): string {
		return __( 'Place order', 'wpss-sample-bank-transfer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wpss_gateway_settings_' . self::ID, array( $this, 'render_settings_fields' ) );
		// The checkout form posts wpss_{id}_process_payment.
		add_action( 'wp_ajax_wpss_' . self::ID . '_process_payment', array( $this, 'ajax_place_order' ) );
		add_action( 'wpss_order_view_details', array( $this, 'show_account_details' ), 9 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_payment( float $amount, string $currency, array $metadata = array() ): array {
		return array(
			'success' => true,
			'status'  => 'pending',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function process_payment( string $payment_id ): array {
		return array(
			'success'        => true,
			'transaction_id' => $payment_id,
			'status'         => 'pending',
		);
	}

	/**
	 * No money can be sent back from here: `manual` tells WP Sell Services to record the refund
	 * for the owner to send by bank, instead of claiming it was paid out.
	 *
	 * @param string     $transaction_id Payment reference.
	 * @param float|null $amount         Amount, null for all of it.
	 * @param string     $reason         Reason.
	 */
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array {
		return array(
			'success'        => true,
			'manual'         => true,
			'transaction_id' => $transaction_id,
			'status'         => 'manual_refund',
			'message'        => __( 'Send this refund from your bank account.', 'wpss-sample-bank-transfer' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle_webhook( array $payload ): array {
		return array(
			'success' => true,
			'message' => 'Bank transfers have no webhook.',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled' => array(
				'type'  => 'checkbox',
				'label' => __( 'Offer bank transfer at checkout', 'wpss-sample-bank-transfer' ),
			),
			'account' => array(
				'type'  => 'textarea',
				'label' => __( 'Account details buyers pay into', 'wpss-sample-bank-transfer' ),
			),
		);
	}

	/**
	 * What the buyer sees under the method at checkout.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @param int    $order_id Order being paid, 0 for a new purchase.
	 */
	public function render_payment_form( float $amount, string $currency, int $order_id ): string {
		return '<p>' . esc_html__( 'Place the order, then pay into this account. Your order starts once the payment is confirmed.', 'wpss-sample-bank-transfer' ) . '</p>'
			. '<p>' . nl2br( esc_html( $this->settings()['account'] ) ) . '</p>';
	}

	/**
	 * The app: POST /payments/create-intent with gateway=sample_bank_transfer opens the order.
	 *
	 * @param CheckoutIntent       $intent  Priced checkout.
	 * @param array<string, mixed> $request Checkout request.
	 */
	public function rest_create_intent( CheckoutIntent $intent, array $request ) {
		return $this->create_offline_rest_order( $request );
	}

	/**
	 * The website checkout: open the pending order (or one per cart line) and send the buyer to it.
	 *
	 * @return void
	 */
	public function ajax_place_order(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpss_checkout' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpss-sample-bank-transfer' ) ) );
		}

		wpss_gateway_require_enabled( $this ); // An off method starts no new orders.

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wpss-sample-bank-transfer' ) ) );
		}

		wpss_save_billing_from_request( $_POST );
		$request = CheckoutIntentService::request_from_post( $_POST );

		if ( ! $request['pay_order'] && $request['is_multi_checkout'] ) {
			$order_ids = $this->open_pending_cart_orders( get_current_user_id() );

			if ( is_wp_error( $order_ids ) ) {
				wp_send_json_error( array( 'message' => $order_ids->get_error_message() ) );
			}

			wp_send_json_success( array( 'redirect_url' => wpss_get_dashboard_url( 'orders' ) ) );
		}

		// Prices the purchase, or puts an existing order (tip, milestone, extension) on this method.
		$order = $this->open_pending_order( $request );

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
		}

		if ( ! $request['pay_order'] ) {
			ServiceCart::remove_line( get_current_user_id(), (int) $request['service_id'], (int) $order->package_id );
		}

		wp_send_json_success( array( 'redirect_url' => wpss_get_post_checkout_url( (int) $order->id, wpss_get_order_url( (int) $order->id ), self::ID ) ) );
	}

	/**
	 * The account details on the buyer's order page while it waits for their payment.
	 *
	 * @param object $order Order.
	 * @return void
	 */
	public function show_account_details( $order ): void {
		if ( self::ID !== ( $order->payment_method ?? '' ) || (int) $order->customer_id !== get_current_user_id() || ! wpss_order_awaits_payment_confirmation( $order ) ) {
			return;
		}

		/* translators: %s: amount to pay */
		echo '<p>' . esc_html( sprintf( __( 'Pay %s into:', 'wpss-sample-bank-transfer' ), wp_strip_all_tags( wpss_format_price( (float) $order->total, (string) $order->currency ) ) ) ) . '</p>';
		echo '<p>' . nl2br( esc_html( $this->settings()['account'] ) ) . '</p>';
	}

	/**
	 * Register the option the card saves.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Keep only the fields this gateway has. The Payment methods switch saves through here too.
	 *
	 * @param mixed $input Posted settings.
	 * @return array{enabled: string, account: string}
	 */
	public function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled' => empty( $input['enabled'] ) ? '' : '1',
			'account' => sanitize_textarea_field( (string) ( $input['account'] ?? '' ) ),
		);
	}

	/**
	 * The card body, inside the settings form WP Sell Services draws.
	 *
	 * @return void
	 */
	public function render_settings_fields(): void {
		$settings = $this->settings();
		?>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>>
				<?php esc_html_e( 'Offer bank transfer at checkout', 'wpss-sample-bank-transfer' ); ?>
			</label>
		</p>
		<p>
			<label for="wpss-sample-bank-account"><?php esc_html_e( 'Account details buyers pay into', 'wpss-sample-bank-transfer' ); ?></label><br>
			<textarea id="wpss-sample-bank-account" class="large-text" rows="4" name="<?php echo esc_attr( self::OPTION ); ?>[account]"><?php echo esc_textarea( $settings['account'] ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Saved settings with every key present.
	 *
	 * @return array{enabled: string, account: string}
	 */
	private function settings(): array {
		return $this->sanitize_settings( get_option( self::OPTION, array() ) );
	}
}
