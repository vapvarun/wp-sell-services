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
		return $this->settings['title'] ?? __( 'Offline Payment', 'wp-sell-services' );
	}

	/**
	 * Get gateway description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->settings['description'] ?? __( 'Pay via bank transfer, cash, or other offline methods.', 'wp-sell-services' );
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

		// Admin order actions.
		add_action( 'wpss_admin_order_actions', array( $this, 'render_admin_order_actions' ), 10, 2 );
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
		return array(
			'success' => true,
			'status'  => 'manual_refund',
			'message' => __( 'Offline payments must be refunded manually outside of this system.', 'wp-sell-services' ),
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
				'description' => __( 'Payment method title shown to buyers.', 'wp-sell-services' ),
				'default'     => __( 'Offline Payment', 'wp-sell-services' ),
			),
			'description'     => array(
				'type'        => 'textarea',
				'label'       => __( 'Description', 'wp-sell-services' ),
				'description' => __( 'Brief description shown on checkout page.', 'wp-sell-services' ),
				'default'     => __( 'Pay via bank transfer, cash, or other offline methods. Your order will be processed after payment is confirmed.', 'wp-sell-services' ),
			),
			'instructions'    => array(
				'type'        => 'editor',
				'label'       => __( 'Payment Instructions', 'wp-sell-services' ),
				'description' => __( 'Detailed instructions shown after order is placed (bank account details, etc.).', 'wp-sell-services' ),
				'default'     => '',
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
			<input type="hidden" name="wpss_gateway" value="offline">
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

		$instructions = $this->settings['instructions'] ?? '';

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
		check_ajax_referer( 'wpss_offline_payment', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$service_id = isset( $_POST['service_id'] ) ? (int) wp_unslash( $_POST['service_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$package_id = isset( $_POST['package_id'] ) ? (int) wp_unslash( $_POST['package_id'] ) : 0;

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

		// Fallback to base price.
		if ( $price <= 0 ) {
			$price = (float) get_post_meta( $service_id, '_wpss_base_price', true );
		}

		// Get order provider.
		$order_provider = wpss_get_order_provider();

		if ( ! $order_provider ) {
			wp_send_json_error( array( 'message' => __( 'No order provider available.', 'wp-sell-services' ) ) );
			return;
		}

		// Create order (stays in pending_payment status).
		$order = $order_provider->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => $package_id,
				'customer_id'    => get_current_user_id(),
				'subtotal'       => $price,
				'currency'       => wpss_get_currency(),
				'payment_method' => 'offline',
			)
		);

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
			return;
		}

		/**
		 * Fires when an offline order is created.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $order    Order object.
		 */
		do_action( 'wpss_offline_order_created', $order->id, $order );

		// Return success with order view redirect (shows payment instructions).
		wp_send_json_success(
			array(
				'order_id'     => $order->id,
				'order_number' => $order->order_number,
				'redirect_url' => home_url( '/service-order/' . $order->id . '/' ),
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

		if ( ! $order_provider ) {
			wp_send_json_error( array( 'message' => __( 'No order provider available.', 'wp-sell-services' ) ) );
			return;
		}

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
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'wp-sell-services' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Mark as Paid', 'wp-sell-services' ) ); ?>');
					}
				}).fail(function() {
					alert('<?php echo esc_js( __( 'Request failed. Please try again.', 'wp-sell-services' ) ); ?>');
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Mark as Paid', 'wp-sell-services' ) ); ?>');
				});
			});
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
		$sanitized['instructions']    = wp_kses_post( $input['instructions'] ?? '' );
		$sanitized['auto_hold_hours'] = absint( $input['auto_hold_hours'] ?? 0 );

		return $sanitized;
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
		return get_option( self::OPTION_NAME, array() );
	}
}
