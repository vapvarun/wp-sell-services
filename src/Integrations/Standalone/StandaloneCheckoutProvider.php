<?php
/**
 * Standalone Checkout Provider
 *
 * @package WPSellServices\Integrations\Standalone
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Standalone;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\CheckoutProviderInterface;
use WPSellServices\Models\ServiceOrder;

/**
 * Checkout provider for standalone mode.
 *
 * @since 1.0.0
 */
class StandaloneCheckoutProvider implements CheckoutProviderInterface {

	/**
	 * User meta key for cart data.
	 */
	private const CART_META_KEY = '_wpss_cart';

	/**
	 * Add service-specific data to cart item.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Product/Service ID.
	 * @param int   $variation_id   Variation ID (not used).
	 * @return array
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		return $cart_item_data;
	}

	/**
	 * Validate service can be added to cart.
	 *
	 * @param int $product_id Service ID.
	 * @param int $quantity   Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( int $product_id, int $quantity ): bool {
		$service = wpss_get_service( $product_id );

		if ( ! $service || ! $service->is_active() ) {
			return false;
		}

		// Services are quantity 1 only.
		if ( $quantity > 1 ) {
			return false;
		}

		// Check vendor availability.
		$vendor = $service->get_vendor();
		if ( $vendor && ! $vendor->can_accept_orders() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get checkout URL for a service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $args       Additional arguments.
	 * @return string
	 */
	public function get_checkout_url( int $service_id, array $args = [] ): string {
		$url = home_url( '/service-checkout/' . $service_id . '/' );

		if ( ! empty( $args['package_id'] ) ) {
			$url = add_query_arg( 'package', $args['package_id'], $url );
		}

		if ( ! empty( $args['addons'] ) ) {
			$url = add_query_arg( 'addons', implode( ',', $args['addons'] ), $url );
		}

		return $url;
	}

	/**
	 * Check if cart contains service items.
	 *
	 * @return bool
	 */
	public function cart_has_services(): bool {
		$cart = $this->get_cart();
		return ! empty( $cart );
	}

	/**
	 * Get service items in cart.
	 *
	 * @return array
	 */
	public function get_cart_services(): array {
		return $this->get_cart();
	}

	/**
	 * Process checkout for services.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $order_data Order data.
	 * @return void
	 */
	public function process_checkout( int $order_id, array $order_data ): void {
		// Clear cart after successful checkout.
		$this->clear_cart();

		/**
		 * Fires after standalone checkout processing.
		 *
		 * @param int   $order_id   Order ID.
		 * @param array $order_data Order data.
		 */
		do_action( 'wpss_standalone_checkout_processed', $order_id, $order_data );
	}

	/**
	 * Redirect after successful checkout.
	 *
	 * @param int $order_id Order ID.
	 * @return string|null
	 */
	public function get_thankyou_redirect( int $order_id ): ?string {
		return wpss_get_order_requirements_url( $order_id );
	}

	/**
	 * Enforce quantity limits for services.
	 *
	 * @param int $max_qty    Current max quantity.
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public function filter_quantity_max( int $max_qty, int $product_id ): int {
		return 1;
	}

	/**
	 * Render checkout shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_checkout_shortcode( array $atts ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$service_id = isset( $_GET['service_id'] ) ? absint( wp_unslash( $_GET['service_id'] ) ) : absint( get_query_var( 'wpss_service_id' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$package_id = isset( $_GET['package'] ) ? absint( wp_unslash( $_GET['package'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$quantity = isset( $_GET['quantity'] ) ? absint( wp_unslash( $_GET['quantity'] ) ) : 1;
		$quantity = max( 1, min( $quantity, 10 ) ); // Clamp 1–10.

		// If no service_id in URL, try to load from user's cart.
		if ( ! $service_id ) {
			$cart = $this->get_cart();

			if ( ! empty( $cart ) ) {
				// Use the most recently added cart item.
				$cart_item  = end( $cart );
				$service_id = (int) ( $cart_item['service_id'] ?? 0 );
				$package_id = $package_id ?: (int) ( $cart_item['package_id'] ?? 0 );
				$quantity   = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
			}
		}

		if ( ! $service_id ) {
			return '<p>' . esc_html__( 'No service selected.', 'wp-sell-services' ) . '</p>';
		}

		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			return '<p>' . esc_html__( 'Service not found.', 'wp-sell-services' ) . '</p>';
		}

		ob_start();
		$this->render_checkout_form( $service, $package_id, $quantity );
		return ob_get_clean();
	}

	/**
	 * Render checkout form.
	 *
	 * @param \WPSellServices\Models\Service $service    Service.
	 * @param int                            $package_id Selected package ID.
	 * @return void
	 */
	private function render_checkout_form( $service, int $package_id = 0, int $quantity = 1 ): void {
		$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: [];
		$selected_package = null;

		if ( isset( $packages[ $package_id ] ) ) {
			$selected_package = $packages[ $package_id ];
		}

		if ( ! $selected_package && ! empty( $packages ) ) {
			$selected_package = reset( $packages );
			$package_id       = (int) array_key_first( $packages );
		}

		$unit_price = (float) ( $selected_package['price'] ?? 0 );
		$price      = $unit_price * $quantity;
		$currency   = wpss_get_currency();

		// Calculate tax.
		$tax_settings = get_option( 'wpss_tax', [] );
		$tax_enabled  = ! empty( $tax_settings['enable_tax'] );
		$tax_rate     = $tax_enabled ? (float) ( $tax_settings['tax_rate'] ?? 0 ) : 0;
		$tax_included = ! empty( $tax_settings['tax_included'] );
		$tax_label    = $tax_settings['tax_label'] ?? __( 'Tax', 'wp-sell-services' );

		/** This filter is documented in StandaloneOrderProvider::create_order() */
		$tax_rate = (float) apply_filters( 'wpss_checkout_tax_rate', $tax_rate, $service->vendor_id, $service->id );

		$tax_amount = 0;
		if ( $tax_rate > 0 ) {
			if ( $tax_included ) {
				$tax_amount = $price - ( $price / ( 1 + $tax_rate / 100 ) );
			} else {
				$tax_amount = $price * ( $tax_rate / 100 );
			}
		}
		$total = $tax_included ? $price : $price + $tax_amount;

		// Get available payment gateways.
		$gateways = wpss()->get_payment_gateways();
		$enabled_gateways = array_filter( $gateways, fn( $g ) => $g->is_enabled() );
		?>
		<div class="wpss-standalone-checkout">
			<div class="wpss-checkout-summary">
				<h3><?php esc_html_e( 'Order Summary', 'wp-sell-services' ); ?></h3>

				<div class="wpss-checkout-service">
					<?php if ( $service->thumbnail_id ) : ?>
						<img src="<?php echo esc_url( $service->get_thumbnail_url( 'thumbnail' ) ); ?>" alt="">
					<?php endif; ?>
					<div class="wpss-service-info">
						<h4><?php echo esc_html( $service->title ); ?></h4>
						<?php if ( $selected_package ) : ?>
							<span class="wpss-package-name"><?php echo esc_html( $selected_package['name'] ?? '' ); ?></span>
						<?php endif; ?>
						<?php if ( $quantity > 1 ) : ?>
							<span class="wpss-quantity-label">&times; <?php echo esc_html( $quantity ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $tax_amount > 0 ) : ?>
					<div class="wpss-checkout-subtotal">
						<span><?php esc_html_e( 'Subtotal', 'wp-sell-services' ); ?></span>
						<span><?php echo esc_html( wpss_format_price( $price, $currency ) ); ?></span>
					</div>
					<div class="wpss-checkout-tax">
						<span><?php echo esc_html( $tax_label ); ?> (<?php echo esc_html( $tax_rate ); ?>%)</span>
						<span><?php echo esc_html( wpss_format_price( $tax_amount, $currency ) ); ?></span>
					</div>
				<?php endif; ?>

				<div class="wpss-checkout-total">
					<span><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></span>
					<span class="wpss-total-amount"><?php echo esc_html( wpss_format_price( $total, $currency ) ); ?></span>
				</div>
			</div>

			<?php if ( ! is_user_logged_in() ) : ?>
				<div class="wpss-checkout-login">
					<p><?php esc_html_e( 'Please log in or create an account to continue.', 'wp-sell-services' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( $this->get_checkout_url( $service->id, [ 'package_id' => $package_id ] ) ) ); ?>" class="button">
						<?php esc_html_e( 'Log In', 'wp-sell-services' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="button">
						<?php esc_html_e( 'Register', 'wp-sell-services' ); ?>
					</a>
				</div>
			<?php elseif ( empty( $enabled_gateways ) ) : ?>
				<div class="wpss-checkout-error">
					<p><?php esc_html_e( 'No payment methods available. Please contact support.', 'wp-sell-services' ); ?></p>
				</div>
			<?php else : ?>
				<form method="post" class="wpss-checkout-form" id="wpss-checkout-form">
					<?php wp_nonce_field( 'wpss_checkout', 'wpss_checkout_nonce' ); ?>
					<input type="hidden" name="service_id" value="<?php echo esc_attr( $service->id ); ?>">
					<input type="hidden" name="package_id" value="<?php echo esc_attr( $package_id ); ?>">
					<input type="hidden" name="quantity" value="<?php echo esc_attr( $quantity ); ?>">
					<input type="hidden" name="amount" value="<?php echo esc_attr( $total ); ?>">
					<input type="hidden" name="tax_amount" value="<?php echo esc_attr( round( $tax_amount, 2 ) ); ?>">
					<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">

					<div class="wpss-payment-methods">
						<h3><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></h3>

						<?php foreach ( $enabled_gateways as $gateway_id => $gateway ) : ?>
							<div class="wpss-payment-method">
								<label>
									<input type="radio" name="payment_method" value="<?php echo esc_attr( $gateway_id ); ?>" required>
									<?php echo esc_html( $gateway->get_name() ); ?>
								</label>
								<div class="wpss-gateway-form" data-gateway="<?php echo esc_attr( $gateway_id ); ?>" style="display: none;">
									<?php echo $gateway->render_payment_form( $price, $currency, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<button type="submit" class="button button-primary wpss-checkout-button">
						<?php
						/* translators: %s: formatted price */
						printf( esc_html__( 'Pay %s', 'wp-sell-services' ), esc_html( wpss_format_price( $price, $currency ) ) );
						?>
					</button>
				</form>

				<script>
				(function() {
					var form = document.getElementById('wpss-checkout-form');
					var submitBtn = form.querySelector('.wpss-checkout-button');
					var originalText = submitBtn.textContent;

					// Show/hide gateway forms on radio change
					document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
						radio.addEventListener('change', function() {
							document.querySelectorAll('.wpss-gateway-form').forEach(function(gform) {
								gform.style.display = 'none';
							});
							var selected = document.querySelector('.wpss-gateway-form[data-gateway="' + this.value + '"]');
							if (selected) selected.style.display = 'block';
						});
					});

					// Handle form submission
					form.addEventListener('submit', function(e) {
						e.preventDefault();

						var paymentMethod = form.querySelector('input[name="payment_method"]:checked');
						if (!paymentMethod) {
							alert('<?php echo esc_js( __( 'Please select a payment method.', 'wp-sell-services' ) ); ?>');
							return;
						}

						submitBtn.disabled = true;
						submitBtn.textContent = '<?php echo esc_js( __( 'Processing...', 'wp-sell-services' ) ); ?>';

						var formData = new FormData(form);
						formData.append('action', 'wpss_' + paymentMethod.value + '_process_payment');
						// Use gateway-specific nonce if available (e.g., wpss_test_nonce), otherwise checkout nonce.
						var gatewayNonce = form.querySelector('[name="wpss_' + paymentMethod.value + '_nonce"]');
						if (gatewayNonce) {
							formData.append('nonce', gatewayNonce.value);
						} else {
							formData.append('nonce', form.querySelector('[name="wpss_checkout_nonce"]').value);
						}

						fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							body: formData,
							credentials: 'same-origin'
						})
						.then(function(response) { return response.json(); })
						.then(function(data) {
							if (data.success && data.data.redirect_url) {
								window.location.href = data.data.redirect_url;
							} else {
								alert(data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Payment failed. Please try again.', 'wp-sell-services' ) ); ?>');
								submitBtn.disabled = false;
								submitBtn.textContent = originalText;
							}
						})
						.catch(function(error) {
							console.error('Checkout error:', error);
							alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
							submitBtn.disabled = false;
							submitBtn.textContent = originalText;
						});
					});
				})();
				</script>
			<?php endif; ?>
		</div>

		<style>
			.wpss-standalone-checkout {
				max-width: 600px;
				margin: 0 auto;
			}
			.wpss-checkout-summary {
				background: #f8f9fa;
				padding: 20px;
				border-radius: 8px;
				margin-bottom: 20px;
			}
			.wpss-checkout-service {
				display: flex;
				gap: 15px;
				margin: 15px 0;
			}
			.wpss-checkout-service img {
				width: 80px;
				height: 80px;
				object-fit: cover;
				border-radius: 4px;
			}
			.wpss-checkout-total {
				display: flex;
				justify-content: space-between;
				font-size: 18px;
				font-weight: 600;
				padding-top: 15px;
				border-top: 1px solid #ddd;
			}
			.wpss-payment-methods {
				margin: 20px 0;
			}
			.wpss-payment-method {
				padding: 15px;
				border: 1px solid #ddd;
				border-radius: 4px;
				margin-bottom: 10px;
			}
			.wpss-payment-method label {
				display: flex;
				align-items: center;
				gap: 10px;
				cursor: pointer;
			}
			.wpss-gateway-form {
				margin-top: 15px;
				padding-top: 15px;
				border-top: 1px solid #eee;
			}
			.wpss-checkout-button {
				width: 100%;
				padding: 15px !important;
				font-size: 16px !important;
			}
			.wpss-checkout-login {
				text-align: center;
				padding: 30px;
			}
			.wpss-checkout-login .button {
				margin: 5px;
			}
		</style>
		<?php
	}

	/**
	 * Get cart data from user meta.
	 *
	 * @return array
	 */
	private function get_cart(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$cart = get_user_meta( $user_id, self::CART_META_KEY, true );

		return is_array( $cart ) ? $cart : array();
	}

	/**
	 * Clear cart from user meta.
	 *
	 * @return void
	 */
	private function clear_cart(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		delete_user_meta( $user_id, self::CART_META_KEY );
	}
}
