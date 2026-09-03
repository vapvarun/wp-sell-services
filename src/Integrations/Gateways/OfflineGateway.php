<?php
/**
 * Offline Payment Gateway
 *
 * Production-ready gateway for manual/offline payments (bank transfer, cash, invoice).
 *
 * @package WPSellServices\Integrations\Gateways
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Gateways;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;

/**
 * Offline payment gateway implementation.
 *
 * Supports bank transfer, cash on delivery, and other manual payment methods.
 * Orders stay in pending_payment status until admin manually marks them as paid.
 *
 * @since 1.0.0
 */
class OfflineGateway implements PaymentGatewayInterface {

	/**
	 * Gateway ID.
	 */
	private const GATEWAY_ID = 'offline';

	/**
	 * Settings option name.
	 */
	private const OPTION_NAME = 'wpss_offline_settings';

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
		return ! empty( $this->settings['title'] ) ? $this->settings['title'] : __( 'Offline Payment', 'wp-sell-services' );
	}

	/**
	 * Get gateway description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return ! empty( $this->settings['description'] ) ? $this->settings['description'] : __( 'Pay via bank transfer, cash, or other offline methods. Your order will be processed after payment is confirmed.', 'wp-sell-services' );
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Check if gateway supports the given currency.
	 *
	 * Offline gateway supports all currencies.
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
		add_action( 'wpss_gateway_settings_offline', array( $this, 'render_settings_fields' ) );

		// AJAX handlers - uses _process_payment suffix to match checkout JS pattern.
		add_action( 'wp_ajax_wpss_offline_process_payment', array( $this, 'ajax_create_order' ) );
		add_action( 'wp_ajax_wpss_admin_mark_order_paid', array( $this, 'ajax_admin_mark_paid' ) );
		add_action( 'wp_ajax_wpss_review_payment_receipt', array( $this, 'ajax_review_payment_receipt' ) );

		// Admin order actions.
		add_action( 'wpss_admin_order_actions', array( $this, 'render_admin_order_actions' ), 10, 2 );

		// Display payment instructions on order view for offline orders.
		add_action( 'wpss_order_view_details', array( $this, 'display_order_payment_instructions' ), 10, 1 );

		// Proof-of-payment upload. Registered SEPARATELY from the instructions
		// above, because that method bails when the owner has written no
		// instruction text - and a buyer still needs somewhere to send evidence
		// whether or not the owner wrote bank details into a settings box.
		add_action( 'wpss_order_view_details', array( $this, 'display_buyer_receipt_upload' ), 11, 1 );

		// Printable receipt, once the money is confirmed.
		add_action( 'wpss_order_view_details', array( $this, 'display_payment_receipt' ), 12, 1 );
	}

	/**
	 * Create a payment (placeholder for offline).
	 *
	 * @param float  $amount   Amount to charge.
	 * @param string $currency Currency code.
	 * @param array  $metadata Additional metadata.
	 * @return array Payment data.
	 */
	public function create_payment( float $amount, string $currency, array $metadata = array() ): array {
		return array(
			'success' => true,
			'id'      => 'offline_' . wp_generate_uuid4(),
			'status'  => 'awaiting_payment',
		);
	}

	/**
	 * Process a payment (manual only).
	 *
	 * @param string $payment_id Payment ID.
	 * @return array Payment result.
	 */
	public function process_payment( string $payment_id ): array {
		return array(
			'success' => true,
			'status'  => 'awaiting_payment',
			'message' => __( 'Awaiting offline payment confirmation.', 'wp-sell-services' ),
		);
	}

	/**
	 * Process a refund (manual only).
	 *
	 * @param string     $transaction_id Original transaction ID.
	 * @param float|null $amount         Refund amount (null for full refund).
	 * @param string     $reason         Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array {
		// No money moves here. `manual` tells OrderWorkflowManager to flag the
		// amount for the admin instead of logging a refund that never happened.
		return array(
			'success'        => true,
			'manual'         => true,
			'transaction_id' => $transaction_id,
			'status'         => 'manual_refund',
			'message'        => __( 'Offline payments must be refunded manually outside of this system.', 'wp-sell-services' ),
		);
	}

	/**
	 * Handle webhook callback (no-op for offline gateway).
	 *
	 * @param array $payload Webhook payload.
	 * @return array Processing result.
	 */
	public function handle_webhook( array $payload ): array {
		return array(
			'success' => true,
			'message' => 'Offline gateway does not use webhooks.',
		);
	}

	/**
	 * Get gateway settings fields.
	 *
	 * @return array Settings fields configuration.
	 */
	/**
	 * The offline methods an owner has defined.
	 *
	 * One generic "Offline Payment" meant a buyer could not say which route
	 * they used, the owner had to cram bank details, cash and UPI into a single
	 * instructions block, and every order landed as `payment_method = offline`
	 * so reporting could not tell them apart.
	 *
	 * Falls back to a single method built from the legacy title/instructions
	 * fields, so a site that never touches this keeps exactly what it had.
	 *
	 * @since 1.7.0
	 *
	 * @param bool $enabled_only Only methods the owner has left enabled.
	 * @return array<int, array{id:string,label:string,instructions:string,enabled:bool}>
	 */
	/**
	 * Freeze the chosen offline method onto an order.
	 *
	 * `payment_method` stays 'offline' on every one of these, untouched. Live
	 * sites already carry a mix of `offline` and `bacs` in that column, and
	 * reinterpreting it would relabel orders that were paid years ago. The
	 * named method lives beside it in `meta`, the same way a package snapshot
	 * does, so history keeps saying what actually happened.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $method_id Method the buyer chose.
	 * @return void
	 */
	private function record_order_method( int $order_id, string $method_id ): void {
		$method_id = sanitize_key( $method_id );

		if ( $order_id <= 0 || '' === $method_id ) {
			return;
		}

		foreach ( self::get_methods( false ) as $method ) {
			if ( $method['id'] !== $method_id ) {
				continue;
			}

			$order = wpss_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$meta                   = $order->meta;
			$meta['offline_method'] = array(
				'id'           => $method['id'],
				'label'        => $method['label'],
				'instructions' => $method['instructions'],
			);

			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'wpss_orders',
				array( 'meta' => wp_json_encode( $meta ) ),
				array( 'id' => $order_id )
			);

			return;
		}
	}

	/**
	 * The offline payment methods this site offers.
	 *
	 * Falls back to the legacy single unnamed method (title + instructions) on a
	 * site that has never opened the editor, so the owner's own wording keeps
	 * working and seeds row one when they do.
	 *
	 * @since 1.7.0
	 *
	 * @param bool $enabled_only Exclude methods the owner has switched off.
	 * @return array<int,array<string,mixed>> Methods with id, label, instructions, enabled.
	 */
	public static function get_methods( bool $enabled_only = true ): array {
		$settings = get_option( 'wpss_offline_settings', array() );
		$methods  = $settings['methods'] ?? array();

		if ( ! is_array( $methods ) || ! $methods ) {
			// Legacy shape: one unnamed method. Keep the owner's own wording.
			$methods = array(
				array(
					'id'           => 'offline',
					'label'        => $settings['title'] ?? __( 'Offline Payment', 'wp-sell-services' ),
					'instructions' => $settings['instructions'] ?? '',
					'enabled'      => true,
				),
			);
		}

		$clean = array();

		foreach ( $methods as $method ) {
			$id = sanitize_key( $method['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$enabled = ! isset( $method['enabled'] ) || ! empty( $method['enabled'] );

			if ( $enabled_only && ! $enabled ) {
				continue;
			}

			$clean[] = array(
				'id'           => $id,
				'label'        => (string) ( $method['label'] ?? $id ),
				'instructions' => (string) ( $method['instructions'] ?? '' ),
				'enabled'      => $enabled,
			);
		}

		/**
		 * Filter the offline payment methods.
		 *
		 * @since 1.7.0
		 *
		 * @param array $clean        Methods.
		 * @param bool  $enabled_only Whether disabled methods were filtered out.
		 */
		return apply_filters( 'wpss_offline_methods', $clean, $enabled_only );
	}

	/**
	 * What to show for the method an order was actually paid with.
	 *
	 * Reads the snapshot frozen on the order, exactly as package details are
	 * frozen at purchase. Renaming or deleting a method later must not rewrite
	 * what an old receipt says the buyer chose.
	 *
	 * @since 1.7.0
	 *
	 * @param \WPSellServices\Models\ServiceOrder|object $order Order.
	 * @return array{id:string,label:string,instructions:string}|null
	 */
	public static function get_order_method( $order ): ?array {
		$meta = is_array( $order->meta ?? null ) ? $order->meta : array();
		$snap = $meta['offline_method'] ?? null;

		if ( is_array( $snap ) && ! empty( $snap['id'] ) ) {
			return array(
				'id'           => (string) $snap['id'],
				'label'        => (string) ( $snap['label'] ?? $snap['id'] ),
				'instructions' => (string) ( $snap['instructions'] ?? '' ),
			);
		}

		// Orders placed before named methods existed, including the `bacs` rows
		// some sites carry. Nothing is rewritten - they are simply unnamed.
		return null;
	}

	/**
	 * Field definitions for the gateway's own accordion.
	 *
	 * Per-method instructions are NOT here - they live on each method in
	 * render_methods_editor(). `title` is the heading the methods sit under.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>> Field key => config.
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled'         => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable Offline Payment', 'wp-sell-services' ),
				'description' => __( 'Enable offline/manual payment methods.', 'wp-sell-services' ),
			),
			'title'           => array(
				'type'        => 'text',
				'label'       => __( 'Title', 'wp-sell-services' ),
				'description' => __( 'Heading shown above the offline options at checkout.', 'wp-sell-services' ),
				'default'     => __( 'Offline Payment', 'wp-sell-services' ),
			),
			'description'     => array(
				'type'        => 'textarea',
				'label'       => __( 'Description', 'wp-sell-services' ),
				'description' => __( 'Brief description shown on checkout page.', 'wp-sell-services' ),
				'default'     => __( 'Pay via bank transfer, cash, or other offline methods. Your order will be processed after payment is confirmed.', 'wp-sell-services' ),
			),
			'auto_hold_hours' => array(
				'type'        => 'number',
				'label'       => __( 'Auto-Cancel (Hours)', 'wp-sell-services' ),
				'description' => __( 'Automatically cancel unpaid orders after this many hours. Set to 0 to disable.', 'wp-sell-services' ),
				'default'     => '0',
				'min'         => '0',
				'max'         => '720',
			),
		);
	}

	/**
	 * Render payment form.
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

		$description = $this->get_description();

		ob_start();
		?>
		<div class="wpss-offline-gateway-form">
			<div class="wpss-offline-gateway-description" style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 16px; border-radius: 4px; margin-bottom: 16px;">
				<p style="margin: 0;">
					<?php echo esc_html( $description ); ?>
				</p>
			</div>
			<?php
			$offline_methods = self::get_methods();

			/*
			 * Only ask when there is a choice to make - but "one method" covers
			 * two different sites, and counting alone cannot tell them apart.
			 *
			 * A site that configured nothing gets one SYNTHESISED method from
			 * the legacy fallback, and has nothing to show beyond the generic
			 * description above. A site that deliberately named a single method
			 * has a label and instructions the buyer needs: "Bank Transfer -
			 * send to ACC 123" is the whole point of enabling it. Hiding that
			 * behind a count is why an owner configured a manual method, saw it
			 * enabled in the backend, and had customers tell them it was not
			 * there.
			 *
			 * So: the radio group still appears only for a real choice, and a
			 * lone CONFIGURED method renders its name and instructions instead
			 * of disappearing into a hidden field.
			 */
			$configured = ! empty( get_option( self::OPTION_NAME, array() )['methods'] );

			if ( count( $offline_methods ) > 1 ) :
				?>
				<fieldset class="wpss-offline-methods" style="border:0;padding:0;margin:0 0 16px;">
					<legend style="font-weight:600;margin-bottom:8px;">
						<?php esc_html_e( 'How will you pay?', 'wp-sell-services' ); ?>
					</legend>
					<?php foreach ( $offline_methods as $index => $offline_method ) : ?>
						<label style="display:flex;gap:8px;align-items:flex-start;padding:8px 0;">
							<input
								type="radio"
								name="offline_method"
								value="<?php echo esc_attr( $offline_method['id'] ); ?>"
								<?php checked( 0, $index ); ?>
								required
							>
							<span>
								<strong><?php echo esc_html( $offline_method['label'] ); ?></strong>
								<?php if ( '' !== $offline_method['instructions'] ) : ?>
									<span style="display:block;color:#555;font-size:.9em;">
										<?php echo esc_html( wp_strip_all_tags( $offline_method['instructions'] ) ); ?>
									</span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php elseif ( $configured && $offline_methods ) : ?>
				<div class="wpss-offline-methods wpss-offline-methods--single" style="margin:0 0 16px;">
					<strong><?php echo esc_html( $offline_methods[0]['label'] ); ?></strong>
					<?php if ( '' !== $offline_methods[0]['instructions'] ) : ?>
						<span style="display:block;color:#555;font-size:.9em;margin-top:4px;">
							<?php echo esc_html( wp_strip_all_tags( $offline_methods[0]['instructions'] ) ); ?>
						</span>
					<?php endif; ?>
				</div>
				<input type="hidden" name="offline_method" value="<?php echo esc_attr( $offline_methods[0]['id'] ); ?>">
			<?php else : ?>
				<input type="hidden" name="offline_method" value="<?php echo esc_attr( $offline_methods[0]['id'] ?? 'offline' ); ?>">
			<?php endif; ?>

			<input type="hidden" name="wpss_offline_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpss_offline_payment' ) ); ?>">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render payment instructions after order is placed.
	 *
	 * @param int $order_id Order ID.
	 * @return string HTML output.
	 */
	public function render_buyer_instructions( int $order_id ): string {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		// The buyer gets the instructions for the method they actually picked,
		// not the one generic block. Falls back to the global instructions for
		// orders placed before named methods existed.
		$order_method = self::get_order_method( $order );
		$instructions = ( $order_method && '' !== $order_method['instructions'] )
			? $order_method['instructions']
			: ( $this->settings['instructions'] ?? '' );

		// Replace placeholders in instructions.
		$replacements = array(
			'{order_number}' => $order->order_number,
			'{order_id}'     => (string) $order->id,
			'{total}'        => wpss_format_price( (float) $order->total, $order->currency ),
			'{currency}'     => $order->currency,
		);

		$instructions = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$instructions
		);

		ob_start();
		?>
		<div class="wpss-offline-instructions" style="background: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 4px; margin: 20px 0;">
			<h3 style="margin-top: 0; color: #856404;">
				<?php esc_html_e( 'Payment Instructions', 'wp-sell-services' ); ?>
			</h3>
			<div class="wpss-offline-instructions-content">
				<?php echo wp_kses_post( wpautop( $instructions ) ); ?>
			</div>
			<p style="margin-bottom: 0; color: #856404;">
				<strong><?php esc_html_e( 'Order Reference:', 'wp-sell-services' ); ?></strong>
				<?php echo esc_html( $order->order_number ); ?>
			</p>
			<p style="margin-bottom: 0; color: #856404;">
				<strong><?php esc_html_e( 'Amount Due:', 'wp-sell-services' ); ?></strong>
				<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Create order for offline payment.
	 *
	 * Creates order in pending_payment status (NOT marked as paid).
	 *
	 * @return void
	 */
	public function ajax_create_order(): void {
		// Accept both offline payment nonce and checkout nonce (for pay_order flow).
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpss_offline_payment' )
			&& ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_checkout_nonce'] ?? '' ) ), 'wpss_checkout' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-sell-services' ) ) );
			return;
		}

		// A disabled gateway does not start new money. Refunds and webhooks
		// stay registered for historical orders; this does not.
		wpss_gateway_require_enabled( $this );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
			return;
		}

		// Remember any billing the buyer entered or corrected at checkout, so
		// their NEXT checkout prefills and collapses to a one-line summary
		// instead of asking for the same address again. The billing block is
		// ours, not the gateway's, so every gateway completing a checkout has
		// to persist it — Stripe did, offline and PayPal did not, so only
		// Stripe buyers ever built up a saved address.
		//
		// Deliberately called here, after the nonce check, rather than from one
		// shared pre-dispatch hook on all checkout actions: running before each
		// gateway verifies its own nonce would let a forged cross-site POST
		// overwrite a logged-in buyer's saved address.
		//
		// Nonce was verified at the top of this handler.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		wpss_save_billing_from_request( $_POST );

		// Handle payment for existing order (from proposal acceptance).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$pay_order_id = isset( $_POST['pay_order'] ) ? (int) wp_unslash( $_POST['pay_order'] ) : 0;
		if ( $pay_order_id ) {
			$order = wpss_get_order( $pay_order_id );
			if ( ! $order || (int) $order->customer_id !== get_current_user_id() ) {
				wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
				return;
			}
			if ( 'pending_payment' !== $order->status ) {
				wp_send_json_error( array( 'message' => __( 'This order has already been paid.', 'wp-sell-services' ) ) );
				return;
			}

			// Record the method the buyer chose.
			//
			// Offline is the one gateway that does NOT settle here - the order
			// stays pending_payment until an admin confirms the money arrived.
			// Every other gateway writes payment_method as a side effect of
			// mark_as_paid(), so this branch was the only path that left it
			// NULL, and three things downstream key off it being 'offline':
			// the admin "Awaiting Confirmation" box with Mark as Paid
			// (render_admin_order_actions()), the buyer's proof-of-payment
			// upload (PaymentReceiptService::can_submit()), and the payment
			// instructions. With it NULL the order could never be confirmed by
			// anyone - a dead end, not just a cosmetic gap (Basecamp
			// 10208094640).
			if ( ! wpss_record_pending_payment_method( (int) $order->id, self::GATEWAY_ID ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not record your payment method. Please try again.', 'wp-sell-services' ) ) );
				return;
			}

			// Freeze WHICH offline method they picked, not just that it was
			// offline.
			//
			// Both other callers of record_order_method() are order-CREATION
			// paths, so this branch - proposals, milestone phases, tips,
			// extensions, and anything else paid through /checkout/pay/{id}/ -
			// was writing no snapshot at all. Those buyers then read the
			// current global instructions instead of the method they chose,
			// and renaming or deleting that method rewrote what their order
			// said afterwards. That is precisely the guarantee the snapshot
			// exists to make, missing for an entire population.
			$this->record_order_method(
				(int) $order->id,
				sanitize_key( wp_unslash( $_POST['offline_method'] ?? '' ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked at the top of this handler.
			);

			/**
			 * Fires when an existing order is put on the offline rail.
			 *
			 * Same hook the cart path fires, so listeners do not need to know
			 * which entry point the buyer came through.
			 *
			 * @since 1.6.0
			 *
			 * @param int    $order_id Order ID.
			 * @param object $order    Order object.
			 */
			do_action( 'wpss_offline_order_created', (int) $order->id, $order );

			wp_send_json_success(
				array(
					'order_id'     => $order->id,
					'order_number' => $order->order_number,
					'redirect'     => wpss_get_order_url( $order->id ),
					'instructions' => $this->render_buyer_instructions( (int) $order->id ),
					'message'      => __( 'Please complete your payment using the instructions below. Your order will be activated once payment is confirmed.', 'wp-sell-services' ),
				)
			);
			return;
		}

		// Multi-service checkout: create one order per cart item.
		$is_multi = ! empty( $_POST['is_multi_checkout'] );
		if ( $is_multi ) {
			$order_provider = wpss_get_order_provider();

			$customer_id = get_current_user_id();
			$cart        = get_user_meta( $customer_id, '_wpss_cart', true );
			$cart        = is_array( $cart ) ? $cart : array();

			if ( empty( $cart ) ) {
				wp_send_json_error( array( 'message' => __( 'Your cart is empty.', 'wp-sell-services' ) ) );
				return;
			}

			// For offline payments there is no shared transaction ID yet — generate a placeholder.
			$transaction_id = 'offline_multi_' . wp_generate_uuid4();

			$order_ids = $order_provider->create_orders_from_cart( $cart, 'offline', $transaction_id, $customer_id );

			if ( empty( $order_ids ) ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create orders. Please try again.', 'wp-sell-services' ) ) );
				return;
			}

			$chosen_method = sanitize_key( wp_unslash( $_POST['offline_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by the calling handler.

			foreach ( $order_ids as $created_order_id ) {
				$this->record_order_method( (int) $created_order_id, $chosen_method );
			}

			/**
			 * Fires after multi-service offline orders are created.
			 *
			 * @param int[]  $order_ids   Created order IDs.
			 * @param int    $customer_id Buyer user ID.
			 */
			do_action( 'wpss_offline_multi_orders_created', $order_ids, $customer_id );

			// Clear entire cart.
			delete_user_meta( $customer_id, '_wpss_cart' );

			wp_send_json_success(
				array(
					'order_ids'    => $order_ids,
					'redirect_url' => add_query_arg( 'tab', 'orders', wpss_get_page_url( 'dashboard' ) ),
					'message'      => __( 'Your orders have been placed. Please complete your payment using the instructions for each order.', 'wp-sell-services' ),
				)
			);
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

		// Self-purchase check: vendors cannot buy their own service.
		$service_post = get_post( $service_id );
		if ( $service_post && (int) $service_post->post_author === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot purchase your own service.', 'wp-sell-services' ) ) );
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

		// Resolve selected addons from POST data.
		$addon_data   = wpss_resolve_checkout_addons( $service_id );
		$addons_total = $addon_data['addons_total'];

		// Get order provider.
		$order_provider = wpss_get_order_provider();

		// Create order (stays in pending_payment status).
		// subtotal = package price only; addons_total is separate — StandaloneOrderProvider sums them.
		$order = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'quantity'       => $quantity,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $price,
				'addons'         => $addon_data['addons'],
				'addons_total'   => $addons_total,
				'currency'       => wpss_get_currency(),
				'payment_method' => 'offline',
			)
		);

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
			return;
		}

		$this->record_order_method(
			(int) ( is_object( $order ) ? ( $order->id ?? 0 ) : (int) $order ),
			sanitize_key( wp_unslash( $_POST['offline_method'] ?? '' ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by the calling handler.
		);

		/**
		 * Fires when an offline order is created.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $order    Order object.
		 */
		do_action( 'wpss_offline_order_created', $order->id, $order );

		// Clear cart after successful order creation.
		delete_user_meta( get_current_user_id(), '_wpss_cart' );

		// Return success with order view redirect (shows payment instructions).
		wp_send_json_success(
			array(
				'order_id'     => $order->id,
				'order_number' => $order->order_number,
				'redirect_url' => wpss_get_order_url( $order->id ),
				'instructions' => $this->render_buyer_instructions( $order->id ),
			)
		);
	}

	/**
	 * AJAX: Admin marks order as paid.
	 *
	 * @return void
	 */
	public function ajax_admin_mark_paid(): void {
		check_ajax_referer( 'wpss_admin_mark_paid', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$order_id       = isset( $_POST['order_id'] ) ? (int) wp_unslash( $_POST['order_id'] ) : 0;
		$transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
			return;
		}

		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
			return;
		}

		// Verify order is pending payment.
		if ( 'pending_payment' !== $order->status ) {
			wp_send_json_error( array( 'message' => __( 'Order is not awaiting payment.', 'wp-sell-services' ) ) );
			return;
		}

		// Generate transaction ID if not provided.
		if ( empty( $transaction_id ) ) {
			$transaction_id = 'offline_manual_' . time();
		}

		// Get order provider and mark as paid.
		$order_provider = wpss_get_order_provider();

		$result = $order_provider->mark_as_paid( $order_id, $transaction_id, 'offline' );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to mark order as paid.', 'wp-sell-services' ) ) );
			return;
		}

		/**
		 * Fires when an offline order is marked as paid.
		 *
		 * @param int    $order_id       Order ID.
		 * @param string $transaction_id Transaction ID.
		 */
		do_action( 'wpss_offline_order_paid', $order_id, $transaction_id );

		wp_send_json_success(
			array(
				'message'    => __( 'Order marked as paid successfully.', 'wp-sell-services' ),
				'new_status' => 'pending_requirements',
			)
		);
	}

	/**
	 * Render admin order actions for offline orders.
	 *
	 * @param object $order  Order object.
	 * @param string $status Current order status.
	 * @return void
	 */
	public function render_admin_order_actions( object $order, string $status ): void {
		// Only show for offline orders in pending_payment status.
		if ( 'offline' !== $order->payment_method || 'pending_payment' !== $status ) {
			return;
		}

		$nonce = wp_create_nonce( 'wpss_admin_mark_paid' );
		?>
		<div class="wpss-offline-admin-actions" style="background: #fff3cd; border: 1px solid #ffc107; padding: 16px; border-radius: 4px; margin: 16px 0;">
			<h4 style="margin-top: 0; color: #856404;">
				<?php esc_html_e( 'Offline Payment - Awaiting Confirmation', 'wp-sell-services' ); ?>
			</h4>
			<p style="color: #856404; margin-bottom: 12px;">
				<?php esc_html_e( 'This order is awaiting offline payment. Mark it as paid once you receive the payment.', 'wp-sell-services' ); ?>
			</p>

			<?php
			// Buyer-submitted proof, and the decision on it (Basecamp #10194890682).
			//
			// Rendered INSIDE the existing Payment Actions box rather than as a
			// new screen: an admin deciding whether money arrived wants the
			// evidence and the Mark-as-Paid control in one place, not two.
			//
			// Approving routes through PaymentReceiptService::verify(), which
			// settles via the same mark_as_paid() path this box already uses -
			// one money-writing path, not two.
			$this->render_admin_receipt_review( (int) $order->id );
			?>
			<div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
				<div>
					<label for="wpss-transaction-id" style="display: block; margin-bottom: 4px; font-weight: 500;">
						<?php esc_html_e( 'Transaction ID (optional)', 'wp-sell-services' ); ?>
					</label>
					<input type="text"
						id="wpss-transaction-id"
						name="transaction_id"
						placeholder="<?php esc_attr_e( 'Bank ref, receipt #, etc.', 'wp-sell-services' ); ?>"
						style="width: 200px;">
				</div>
				<button type="button"
					class="button button-primary wpss-mark-paid-btn"
					data-order-id="<?php echo esc_attr( $order->id ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Mark as Paid', 'wp-sell-services' ); ?>
				</button>
			</div>
		</div>

		<script>
		function wpssAdminNotice(msg, type) {
			type = type || 'error';
			var cls = type === 'success' ? 'notice-success' : 'notice-error';
			var $notice = jQuery('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
			jQuery('.wrap h1, .wrap h2').first().after($notice);
			$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
			setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
		}
		jQuery(function($) {
			$('.wpss-mark-paid-btn').on('click', function() {
				var $btn = $(this);
				var orderId = $btn.data('order-id');
				var nonce = $btn.data('nonce');
				var transactionId = $('#wpss-transaction-id').val();

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to mark this order as paid?', 'wp-sell-services' ) ); ?>')) {
					return;
				}

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'wp-sell-services' ) ); ?>');

				$.post(ajaxurl, {
					action: 'wpss_admin_mark_order_paid',
					order_id: orderId,
					transaction_id: transactionId,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						// Success - page will reload to show updated state.
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'wp-sell-services' ) ); ?>', 'error');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Mark as Paid', 'wp-sell-services' ) ); ?>');
					}
				}).fail(function() {
					wpssAdminNotice('<?php echo esc_js( __( 'Request failed. Please try again.', 'wp-sell-services' ) ); ?>', 'error');
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Mark as Paid', 'wp-sell-services' ) ); ?>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render buyer-submitted payment proof and the approve / reject controls.
	 *
	 * Nothing renders when the feature is off or no proof has been submitted -
	 * an admin on a card-only marketplace should never see this.
	 *
	 * @since 1.6.0
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function render_admin_receipt_review( int $order_id ): void {
		if ( ! \WPSellServices\Services\PaymentReceiptService::is_enabled() ) {
			return;
		}

		$service  = new \WPSellServices\Services\PaymentReceiptService();
		$receipts = $service->get_for_order( $order_id );

		if ( ! $receipts ) {
			printf(
				'<p style="color:#856404;margin:0 0 12px;"><em>%s</em></p>',
				esc_html__( 'The buyer has not uploaded proof of payment yet.', 'wp-sell-services' )
			);
			return;
		}

		$nonce = wp_create_nonce( 'wpss_receipt_review' );

		echo '<div class="wpss-receipt-review" style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:12px;margin:0 0 12px;">';
		printf( '<h4 style="margin:0 0 8px;">%s</h4>', esc_html__( 'Payment proof', 'wp-sell-services' ) );

		foreach ( $receipts as $receipt ) {
			$file   = wpss_get_receipt_file( $receipt );
			$url    = $file['url'];
			$is_img = $file['is_image'];
			$who    = get_userdata( (int) $receipt->uploaded_by );

			echo '<div class="wpss-receipt-review__item" style="border-top:1px solid #eee;padding:10px 0;">';

			printf(
				'<p style="margin:0 0 6px;"><strong>%s</strong> &middot; %s</p>',
				esc_html( ucfirst( (string) $receipt->status ) ),
				esc_html(
					sprintf(
						/* translators: 1: uploader name, 2: date */
						__( 'uploaded by %1$s on %2$s', 'wp-sell-services' ),
						$who ? $who->display_name : __( 'a buyer', 'wp-sell-services' ),
						wp_date( get_option( 'date_format' ), strtotime( (string) $receipt->created_at ) )
					)
				)
			);

			if ( $receipt->note ) {
				printf( '<p style="margin:0 0 6px;">%s</p>', esc_html( (string) $receipt->note ) );
			}

			if ( $url && $is_img ) {
				printf(
					'<a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" style="max-width:220px;height:auto;border:1px solid #ddd;border-radius:3px;"></a>',
					esc_url( $url ),
					esc_attr__( 'Payment proof', 'wp-sell-services' )
				);
			} elseif ( $url ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $url ),
					esc_html__( 'Open the uploaded file', 'wp-sell-services' )
				);
			}

			if ( 'submitted' === (string) $receipt->status ) {
				?>
				<div style="margin-top:10px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
					<div>
						<label for="wpss-receipt-note-<?php echo esc_attr( (string) $receipt->id ); ?>" style="display:block;margin-bottom:4px;font-weight:500;">
							<?php esc_html_e( 'Note to buyer (required to reject)', 'wp-sell-services' ); ?>
						</label>
						<input type="text" id="wpss-receipt-note-<?php echo esc_attr( (string) $receipt->id ); ?>" class="wpss-receipt-note" style="width:260px;">
					</div>
					<button type="button" class="button button-primary wpss-receipt-action" data-action="verify"
						data-receipt="<?php echo esc_attr( (string) $receipt->id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Approve + mark paid', 'wp-sell-services' ); ?>
					</button>
					<button type="button" class="button wpss-receipt-action" data-action="reject"
						data-receipt="<?php echo esc_attr( (string) $receipt->id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Reject', 'wp-sell-services' ); ?>
					</button>
				</div>
				<?php
			} elseif ( $receipt->admin_note ) {
				printf(
					'<p style="margin:6px 0 0;color:#666;"><em>%s</em></p>',
					esc_html( (string) $receipt->admin_note )
				);
			}

			echo '</div>';
		}

		echo '</div>';
		?>
		<script>
		jQuery(function($){
			$('.wpss-receipt-action').off('click.wpssReceipt').on('click.wpssReceipt', function(){
				var $b = $(this),
					action = $b.data('action'),
					note = $b.closest('div').find('.wpss-receipt-note').val() || '';

				if (action === 'reject' && !note.trim()) {
					wpssAdminNotice('<?php echo esc_js( __( 'Give the buyer a reason so they know what to send instead.', 'wp-sell-services' ) ); ?>', 'error');
					return;
				}

				$b.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'wpss_review_payment_receipt',
					receipt_id: $b.data('receipt'),
					decision: action,
					note: note,
					nonce: $b.data('nonce')
				}).done(function(res){
					if (res && res.success) { location.reload(); }
					else {
						wpssAdminNotice((res && res.data && res.data.message) || '<?php echo esc_js( __( 'Could not record that decision.', 'wp-sell-services' ) ); ?>', 'error');
						$b.prop('disabled', false);
					}
				}).fail(function(){
					wpssAdminNotice('<?php echo esc_js( __( 'Could not record that decision.', 'wp-sell-services' ) ); ?>', 'error');
					$b.prop('disabled', false);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: record an admin decision on a payment receipt.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	public function ajax_review_payment_receipt(): void {
		check_ajax_referer( 'wpss_receipt_review', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'wp-sell-services' ) ), 403 );
		}

		$receipt_id = isset( $_POST['receipt_id'] ) ? absint( $_POST['receipt_id'] ) : 0;
		$decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! in_array( $decision, array( 'verify', 'reject' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown decision.', 'wp-sell-services' ) ), 400 );
		}

		$service = new \WPSellServices\Services\PaymentReceiptService();
		$result  = 'verify' === $decision
			? $service->verify( $receipt_id, get_current_user_id(), $note )
			: $service->reject( $receipt_id, get_current_user_id(), $note );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				(int) ( $result->get_error_data()['status'] ?? 400 )
			);
		}

		wp_send_json_success();
	}

	/**
	 * Display payment instructions on order view for offline orders.
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function display_order_payment_instructions( $order ): void {
		// Only show for offline orders in pending_payment status
		if ( 'pending_payment' !== $order->status ) {
			return;
		}

		// Check if this order was paid with offline gateway
		if ( 'offline' !== $order->payment_method ) {
			return;
		}

		// Only show to the customer
		if ( (int) $order->customer_id !== get_current_user_id() ) {
			return;
		}

		// Check if gateway is enabled and has instructions
		if ( ! $this->is_enabled() ) {
			return;
		}

		/*
		 * Read the method the buyer actually chose, then fall back.
		 *
		 * This used to read $this->settings['instructions'] - the LEGACY global
		 * block - and nothing else. The sanitizer drops that key the moment an
		 * owner defines named methods, deliberately, so one setting does not
		 * have two homes. The two together meant that defining methods silently
		 * left every offline buyer with NO payment instructions: the global key
		 * was gone, this read found nothing, and the early return below hid the
		 * whole section. An offline buyer with no instructions cannot pay.
		 *
		 * get_order_method() existed for exactly this and had no callers - the
		 * snapshot was being written and never read.
		 */
		$method       = self::get_order_method( $order );
		$instructions = $method['instructions'] ?? '';

		if ( '' === $instructions ) {
			// Orders placed before named methods existed, and sites that never
			// opened the editor.
			$instructions = $this->settings['instructions'] ?? '';
		}

		if ( '' === $instructions ) {
			// Nothing on the order and nothing global - but the owner may still
			// have named methods, in which case show the one the order is on.
			foreach ( self::get_methods( false ) as $configured ) {
				if ( isset( $method['id'] ) && $configured['id'] === $method['id'] ) {
					$instructions = $configured['instructions'];
					break;
				}
			}
		}

		if ( empty( $instructions ) ) {
			return;
		}

		// Replace placeholders in instructions
		$replacements = array(
			'{order_number}' => $order->order_number,
			'{order_id}'     => (string) $order->id,
			'{total}'        => wpss_format_price( (float) $order->total, $order->currency ),
			'{currency}'     => $order->currency,
		);

		$instructions = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$instructions
		);

		// Display the instructions
		?>
		<section class="wpss-order-section">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
					<?php
					if ( ! empty( $method['label'] ) ) {
						printf(
							/* translators: %s: the offline payment method the buyer chose, e.g. Bank Transfer */
							esc_html__( 'How to pay by %s', 'wp-sell-services' ),
							esc_html( $method['label'] )
						);
					} else {
						esc_html_e( 'Payment Instructions', 'wp-sell-services' );
					}
					?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<div class="wpss-offline-instructions" style="background: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 4px; margin: 0;">
					<div class="wpss-offline-instructions-content">
						<?php echo wp_kses_post( wpautop( $instructions ) ); ?>
					</div>
					<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0c874;">
						<p style="margin-bottom: 8px; color: #856404;">
							<strong><?php esc_html_e( 'Order Reference:', 'wp-sell-services' ); ?></strong>
							<?php echo esc_html( $order->order_number ); ?>
						</p>
						<p style="margin-bottom: 0; color: #856404;">
							<strong><?php esc_html_e( 'Amount Due:', 'wp-sell-services' ); ?></strong>
							<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
						</p>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Buyer-facing proof-of-payment upload, and the status of anything sent.
	 *
	 * @since 1.6.0
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function display_buyer_receipt_upload( $order ): void {
		$service = new \WPSellServices\Services\PaymentReceiptService();
		$user_id = get_current_user_id();

		if ( ! $service->can_submit( $order, $user_id ) ) {
			return;
		}

		$receipts = $service->get_for_order( (int) $order->id );
		$latest   = $receipts[0] ?? null;
		$pending  = $latest && 'submitted' === (string) $latest->status;
		$rejected = $latest && 'rejected' === (string) $latest->status;
		?>
		<section class="wpss-order-section">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<i data-lucide="receipt" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Proof of payment', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<?php if ( $pending ) : ?>
					<div class="wpss-alert wpss-alert--info">
						<p><?php esc_html_e( 'Your receipt has been sent and is waiting to be checked. We will email you once it has been reviewed.', 'wp-sell-services' ); ?></p>
					</div>
				<?php else : ?>
					<?php if ( $rejected ) : ?>
						<div class="wpss-alert wpss-alert--error">
							<p><strong><?php esc_html_e( 'Your last receipt was not accepted.', 'wp-sell-services' ); ?></strong></p>
							<?php if ( $latest->admin_note ) : ?>
								<p><?php echo esc_html( (string) $latest->admin_note ); ?></p>
							<?php endif; ?>
							<p><?php esc_html_e( 'Please upload a clearer copy below.', 'wp-sell-services' ); ?></p>
						</div>
					<?php else : ?>
						<p><?php esc_html_e( 'Paid by bank transfer or another offline method? Upload your receipt and we will confirm your order once it is checked.', 'wp-sell-services' ); ?></p>
					<?php endif; ?>

					<form id="wpss-receipt-form" enctype="multipart/form-data">
						<?php wp_nonce_field( 'wpss_submit_receipt', 'wpss_receipt_nonce' ); ?>
						<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->id ); ?>">
						<div class="wpss-form-group">
							<label for="wpss-receipt-file"><?php esc_html_e( 'Receipt or screenshot', 'wp-sell-services' ); ?></label>
							<input type="file" name="attachments[]" id="wpss-receipt-file" accept="image/*,.pdf" required>
						</div>
						<div class="wpss-form-group">
							<label for="wpss-receipt-note"><?php esc_html_e( 'Reference number (optional)', 'wp-sell-services' ); ?></label>
							<input type="text" name="note" id="wpss-receipt-note" class="wpss-input" placeholder="<?php esc_attr_e( 'Bank reference, transaction id…', 'wp-sell-services' ); ?>">
						</div>
						<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Send receipt', 'wp-sell-services' ); ?></button>
					</form>

					<script>
					jQuery(function($){
						$('#wpss-receipt-form').on('submit', function(e){
							e.preventDefault();
							var $f = $(this), $b = $f.find('button[type="submit"]');
							$b.prop('disabled', true);
							$.ajax({
								url: '<?php echo esc_url_raw( rest_url( 'wpss/v1/orders/' ) ); ?>' + $f.find('[name="order_id"]').val() + '/receipts',
								method: 'POST',
								data: new FormData(this),
								processData: false,
								contentType: false,
								beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'); },
								success: function(){ location.reload(); },
								error: function(xhr){
									var m = (xhr.responseJSON && xhr.responseJSON.message) || '<?php echo esc_js( __( 'Could not send that receipt.', 'wp-sell-services' ) ); ?>';
									if (window.wpssToast) { wpssToast(m, 'error'); } else { alert(m); }
									$b.prop('disabled', false);
								}
							});
						});
					});
					</script>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Printable payment receipt, shown once the order is paid.
	 *
	 * A PRINT VIEW, not a generated PDF. A PDF library is a dependency decision
	 * of its own - this plugin ships its runtime vendor directory in the repo,
	 * so adding one is a size and maintenance cost on every install for a
	 * document the browser can already produce. window.print() gives the buyer
	 * a PDF through their own print dialog on every platform, and the print
	 * stylesheet keeps it to the receipt alone.
	 *
	 * @since 1.6.0
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function display_payment_receipt( $order ): void {
		if ( ! \WPSellServices\Services\PaymentReceiptService::is_enabled() ) {
			return;
		}

		if ( 'offline' !== (string) ( $order->payment_method ?? '' ) ) {
			return;
		}

		if ( 'paid' !== (string) ( $order->payment_status ?? '' ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// The buyer and the site owner. A vendor is paid through the wallet and
		// has their own earnings record; this is the buyer's proof of purchase.
		if ( (int) $order->customer_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$service  = new \WPSellServices\Services\PaymentReceiptService();
		$receipts = $service->get_for_order( (int) $order->id );
		$verified = null;

		foreach ( $receipts as $receipt ) {
			if ( 'verified' === (string) $receipt->status ) {
				$verified = $receipt;
				break;
			}
		}

		// The model hydrates dates into DateTimeImmutable, so casting to string
		// fatals - the same hydration boundary this release already had to fix
		// across the dispute timeline and the order model. Normalise, never
		// assume a string.
		$paid_on = $order->paid_at ?? null;

		if ( $paid_on instanceof \DateTimeInterface ) {
			$paid_ts = $paid_on->getTimestamp();
		} else {
			$paid_ts = $paid_on ? (int) strtotime( (string) $paid_on ) : 0;
		}
		?>
		<section class="wpss-order-section wpss-payment-receipt" id="wpss-payment-receipt">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<i data-lucide="receipt" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Payment receipt', 'wp-sell-services' ); ?>
				</h2>
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-btn--sm wpss-receipt-print">
					<?php esc_html_e( 'Print or save as PDF', 'wp-sell-services' ); ?>
				</button>
			</div>
			<div class="wpss-order-section__body">
				<table class="wpss-receipt-table" style="width:100%;border-collapse:collapse;">
					<tbody>
						<tr>
							<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Receipt for', 'wp-sell-services' ); ?></th>
							<td style="text-align:right;"><?php echo esc_html( wpss_get_platform_name() ); ?></td>
						</tr>
						<tr>
							<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
							<td style="text-align:right;">#<?php echo esc_html( (string) $order->order_number ); ?></td>
						</tr>
						<tr>
							<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Amount paid', 'wp-sell-services' ); ?></th>
							<td style="text-align:right;"><?php echo esc_html( wpss_format_price( (float) $order->total, (string) $order->currency ) ); ?></td>
						</tr>
						<tr>
							<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Method', 'wp-sell-services' ); ?></th>
							<td style="text-align:right;"><?php esc_html_e( 'Offline payment', 'wp-sell-services' ); ?></td>
						</tr>
						<?php if ( ! empty( $order->transaction_id ) ) : ?>
							<tr>
								<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Reference', 'wp-sell-services' ); ?></th>
								<td style="text-align:right;"><?php echo esc_html( (string) $order->transaction_id ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $paid_ts ) : ?>
							<tr>
								<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Paid on', 'wp-sell-services' ); ?></th>
								<td style="text-align:right;"><?php echo esc_html( wp_date( get_option( 'date_format' ), $paid_ts ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php
						$verified_ts = 0;
						if ( $verified && $verified->verified_at ) {
							$verified_ts = $verified->verified_at instanceof \DateTimeInterface
								? $verified->verified_at->getTimestamp()
								: (int) strtotime( (string) $verified->verified_at );
						}
						?>
						<?php if ( $verified_ts ) : ?>
							<tr>
								<th style="text-align:left;padding:6px 0;"><?php esc_html_e( 'Verified on', 'wp-sell-services' ); ?></th>
								<td style="text-align:right;"><?php echo esc_html( wp_date( get_option( 'date_format' ), $verified_ts ) ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<style>
		@media print {
			body * { visibility: hidden; }
			#wpss-payment-receipt, #wpss-payment-receipt * { visibility: visible; }
			#wpss-payment-receipt { position: absolute; left: 0; top: 0; width: 100%; }
			.wpss-receipt-print { display: none !important; }
		}
		</style>
		<script>
		jQuery(function($){
			$('.wpss-receipt-print').on('click', function(){ window.print(); });
		});
		</script>
		<?php
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wpss_offline_settings',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		$sanitized['enabled']         = ! empty( $input['enabled'] ) ? '1' : '';
		$sanitized['title']           = sanitize_text_field( $input['title'] ?? '' );
		$sanitized['description']     = sanitize_textarea_field( $input['description'] ?? '' );
		$sanitized['auto_hold_hours'] = absint( $input['auto_hold_hours'] ?? 0 );

		$methods = $this->sanitize_methods( (array) ( $input['methods'] ?? array() ) );

		if ( $methods ) {
			$sanitized['methods'] = $methods;

			// Migrated. The legacy single-method key is deliberately NOT carried
			// forward: leaving it would give one setting two homes, which is how
			// a value ends up written to one key and read from another.
			// get_methods() only falls back to it while methods[] is absent.
			return $sanitized;
		}

		// No methods named yet - keep the legacy shape intact so an owner who
		// saves the tab before filling the editor does not lose their
		// instructions.
		$sanitized['instructions'] = wp_kses_post( $input['instructions'] ?? '' );

		return $sanitized;
	}

	/**
	 * Sanitize the posted method rows.
	 *
	 * Ids are derived from the label once and then frozen: renaming "Bank
	 * Transfer" to "Wire Transfer" keeps bank_transfer, so nothing holding the
	 * id sees a rename as a different method. Rows are fixed and cannot be
	 * reordered, so the row index is a stable place to carry that id forward.
	 *
	 * @since 1.7.0
	 *
	 * @param array<int|string,mixed> $rows Raw method rows, straight off the settings POST.
	 * @return array<int,array<string,mixed>> Clean methods, blank rows dropped.
	 */
	private function sanitize_methods( array $rows ): array {
		$existing = (array) ( get_option( self::OPTION_NAME, array() )['methods'] ?? array() );
		$clean    = array();
		$used     = array();

		foreach ( array_values( $rows ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );

			// A row with no name is an empty slot, not a method.
			if ( '' === $label ) {
				continue;
			}

			$id = sanitize_key( (string) ( $existing[ $index ]['id'] ?? '' ) );

			if ( '' === $id ) {
				// sanitize_title() first: sanitize_key() alone DELETES the space
				// rather than replacing it, so "Bank Transfer" became
				// "banktransfer". Ids are frozen the moment they are assigned, so
				// getting this right matters only before the first save.
				$id = sanitize_key( sanitize_title( $label ) );
			}

			if ( '' === $id ) {
				$id = 'method_' . ( $index + 1 );
			}

			// Two methods called the same thing would otherwise share an id, and
			// the order snapshot would point at whichever came first.
			$base = $id;
			$n    = 2;

			while ( in_array( $id, $used, true ) ) {
				$id = $base . '_' . $n;
				++$n;
			}

			$used[]  = $id;
			$clean[] = array(
				'id'           => $id,
				'label'        => $label,
				'instructions' => wp_kses_post( (string) ( $row['instructions'] ?? '' ) ),
				'enabled'      => ! empty( $row['enabled'] ),
			);
		}

		return $clean;
	}

	/**
	 * Render settings fields for the consolidated Gateways tab.
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

		<?php $this->render_methods_editor(); ?>

		<h4><?php esc_html_e( 'Available Placeholders for Instructions', 'wp-sell-services' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'You can use these placeholders in the Payment Instructions:', 'wp-sell-services' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>{order_number}</code> - <?php esc_html_e( 'Order reference number', 'wp-sell-services' ); ?></li>
			<li><code>{order_id}</code> - <?php esc_html_e( 'Order ID', 'wp-sell-services' ); ?></li>
			<li><code>{total}</code> - <?php esc_html_e( 'Formatted order total with currency', 'wp-sell-services' ); ?></li>
			<li><code>{currency}</code> - <?php esc_html_e( 'Currency code', 'wp-sell-services' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Named offline methods.
	 *
	 * Fixed slots rather than a JavaScript repeater. Free has no repeater
	 * anywhere in its admin, and a marketplace that takes bank transfer, cheque
	 * and cash has three methods, not thirty - so the ceiling buys nothing that
	 * the JS, the CSS and a variable-length sanitizer would cost. Blank rows are
	 * dropped on save. Raise WPSS_OFFLINE_METHOD_SLOTS if a site ever needs more.
	 *
	 * Row one arrives pre-filled on a site that has only ever had the single
	 * unnamed method, because get_methods() falls back to the legacy title and
	 * instructions - so the owner sees their own wording and saving migrates it.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	private function render_methods_editor(): void {
		$methods = self::get_methods( false );
		$slots   = (int) apply_filters( 'wpss_offline_method_slots', 4 );
		$slots   = max( count( $methods ), max( 1, $slots ) );
		?>
		<h4><?php esc_html_e( 'Payment Methods', 'wp-sell-services' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Name each way a buyer can pay you offline. Leave a slot blank to skip it. Buyers choose from the enabled ones at checkout, and the method they picked is recorded on the order, so renaming or removing one later never changes what a past order says.', 'wp-sell-services' ); ?>
		</p>

		<table class="form-table wpss-offline-methods-editor">
			<?php for ( $i = 0; $i < $slots; $i++ ) : ?>
				<?php
				$method   = $methods[ $i ] ?? array();
				$label    = (string) ( $method['label'] ?? '' );
				$instr    = (string) ( $method['instructions'] ?? '' );
				$on       = ! isset( $method['enabled'] ) || ! empty( $method['enabled'] );
				$name     = self::OPTION_NAME . '[methods][' . $i . ']';
				$field_id = 'wpss-offline-method-' . $i;
				?>
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $field_id ); ?>">
							<?php
							/* translators: %d: method slot number. */
							printf( esc_html__( 'Method %d', 'wp-sell-services' ), (int) ( $i + 1 ) );
							?>
						</label>
					</th>
					<td>
						<p>
							<input
								type="text"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $name ); ?>[label]"
								value="<?php echo esc_attr( $label ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Bank Transfer', 'wp-sell-services' ); ?>"
							>
						</p>
						<p>
							<label for="<?php echo esc_attr( $field_id ); ?>-instructions" class="screen-reader-text">
								<?php esc_html_e( 'Payment instructions', 'wp-sell-services' ); ?>
							</label>
							<textarea
								id="<?php echo esc_attr( $field_id ); ?>-instructions"
								name="<?php echo esc_attr( $name ); ?>[instructions]"
								rows="4"
								class="large-text code"
								placeholder="<?php esc_attr_e( 'Account name, sort code, account number, and the reference to quote.', 'wp-sell-services' ); ?>"
							><?php echo esc_textarea( $instr ); ?></textarea>
						</p>
						<p>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( $name ); ?>[enabled]"
									value="1"
									<?php checked( $on ); ?>
								>
								<?php esc_html_e( 'Offer this method at checkout', 'wp-sell-services' ); ?>
							</label>
						</p>
					</td>
				</tr>
			<?php endfor; ?>
		</table>
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

			case 'textarea':
				?>
				<textarea name="<?php echo esc_attr( $name ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
				<?php
				break;

			case 'editor':
				wp_editor(
					$value,
					'wpss_offline_instructions',
					array(
						'textarea_name' => $name,
						'textarea_rows' => 8,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				break;

			case 'number':
				?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" <?php echo '' !== $min_val ? 'min="' . esc_attr( $min_val ) . '"' : ''; ?> <?php echo '' !== $max_val ? 'max="' . esc_attr( $max_val ) . '"' : ''; ?>>
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
}
