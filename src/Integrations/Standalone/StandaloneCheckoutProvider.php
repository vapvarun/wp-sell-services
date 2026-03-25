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
		$url = home_url( '/' . StandaloneAdapter::get_checkout_slug() . '/' . $service_id . '/' );

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
	 * Handles both regular service purchase and pay_order flow (from proposal acceptance).
	 * Both flows render through the same render_checkout_form() template.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_checkout_shortcode( array $atts ): string {
		// Check if paying for an existing order (from proposal acceptance).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pay_order_id = isset( $_GET['pay_order'] ) ? absint( wp_unslash( $_GET['pay_order'] ) ) : 0;

		if ( $pay_order_id ) {
			return $this->render_pay_order_checkout( $pay_order_id );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$service_id = isset( $_GET['service_id'] ) ? absint( wp_unslash( $_GET['service_id'] ) ) : 0;
		// Fallback: frontend.js WPSS.checkout() sends 'service' param.
		if ( ! $service_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$service_id = isset( $_GET['service'] ) ? absint( wp_unslash( $_GET['service'] ) ) : absint( get_query_var( 'wpss_service_id' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$package_id = isset( $_GET['package'] ) ? absint( wp_unslash( $_GET['package'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$quantity = isset( $_GET['quantity'] ) ? absint( wp_unslash( $_GET['quantity'] ) ) : 1;
		$quantity = max( 1, min( $quantity, 10 ) ); // Clamp 1-10.

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$addon_ids_raw = isset( $_GET['addons'] ) ? sanitize_text_field( wp_unslash( $_GET['addons'] ) ) : '';

		// If no service_id in URL, try to load from user's cart.
		if ( ! $service_id ) {
			$cart = $this->get_cart();

			if ( ! empty( $cart ) ) {
				// Use the most recently added cart item.
				$cart_item  = end( $cart );
				$service_id = (int) ( $cart_item['service_id'] ?? 0 );
				$package_id = $package_id ?: (int) ( $cart_item['package_id'] ?? 0 );
				$quantity   = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );

				// Restore addons from cart item if not provided via URL.
				if ( ! $addon_ids_raw && ! empty( $cart_item['addons'] ) ) {
					$addon_ids_raw = implode( ',', array_column( $cart_item['addons'], 'id' ) );
				}
			}
		}

		if ( ! $service_id ) {
			return '<p class="wpss-alert wpss-alert-error">' . esc_html__( 'No service selected.', 'wp-sell-services' ) . '</p>';
		}

		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			return '<p>' . esc_html__( 'Service not found.', 'wp-sell-services' ) . '</p>';
		}

		// Resolve selected addons from URL param (comma-separated IDs).
		$selected_addons = array();
		if ( $addon_ids_raw ) {
			$addon_ids     = array_map( 'absint', explode( ',', $addon_ids_raw ) );
			$addon_ids     = array_filter( $addon_ids );
			$addon_service = new \WPSellServices\Services\ServiceAddonService();

			foreach ( $addon_ids as $addon_id ) {
				$addon = $addon_service->get( $addon_id );
				if ( $addon && (int) $addon->service_id === $service->id && ! empty( $addon->is_active ) ) {
					$selected_addons[] = $addon;
				}
			}
		}

		ob_start();
		$this->render_checkout_form( $service, $package_id, $quantity, null, $selected_addons );
		return ob_get_clean();
	}

	/**
	 * Load and validate pay_order, then render the same checkout form.
	 *
	 * @param int $order_id Order ID to pay.
	 * @return string HTML content.
	 */
	private function render_pay_order_checkout( int $order_id ): string {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return '<p class="wpss-alert wpss-alert-error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</p>';
		}

		// Only the customer who owns this order can pay.
		if ( (int) $order->customer_id !== get_current_user_id() ) {
			return '<p class="wpss-alert wpss-alert-error">' . esc_html__( 'You do not have permission to pay for this order.', 'wp-sell-services' ) . '</p>';
		}

		// Only pending_payment orders can be paid.
		if ( 'pending_payment' !== $order->status ) {
			return '<p class="wpss-alert wpss-alert-info">' . esc_html__( 'This order has already been paid.', 'wp-sell-services' ) . '</p>';
		}

		$service = $order->get_service();

		// For proposal-based orders, service_id may be 0. Build a placeholder service
		// from the order/proposal metadata so the checkout form can still render.
		if ( ! $service ) {
			$service = $this->build_proposal_service_placeholder( $order );
		}

		if ( ! $service ) {
			return '<p class="wpss-alert wpss-alert-error">' . esc_html__( 'Service not found.', 'wp-sell-services' ) . '</p>';
		}

		ob_start();
		$this->render_checkout_form( $service, 0, 1, $order );
		return ob_get_clean();
	}

	/**
	 * Build a lightweight Service-like placeholder for proposal-based orders.
	 *
	 * When a buyer request is converted to an order the service_id is typically 0
	 * because the order originated from a proposal, not a service listing. This method
	 * extracts the request title from the order meta snapshot so the checkout template
	 * has something meaningful to display.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return \WPSellServices\Models\Service|null Placeholder service or null.
	 */
	private function build_proposal_service_placeholder( ServiceOrder $order ): ?\WPSellServices\Models\Service {
		// Extract request title from order meta → proposal_snapshot.
		$title = '';
		$meta  = $order->meta;

		if ( ! empty( $meta['proposal_snapshot']['request_title'] ) ) {
			$title = $meta['proposal_snapshot']['request_title'];
		}

		if ( ! $title ) {
			/* translators: %s: order number */
			$title = sprintf( __( 'Order %s', 'wp-sell-services' ), $order->order_number );
		}

		// Create a minimal WP_Post to pass to Service::from_post().
		$now              = current_time( 'mysql', true );
		$placeholder_post = new \WP_Post(
			(object) array(
				'ID'                => 0,
				'post_title'        => $title,
				'post_status'       => 'publish',
				'post_type'         => 'wpss_service',
				'post_content'      => '',
				'post_excerpt'      => '',
				'post_author'       => $order->vendor_id,
				'post_date_gmt'     => $now,
				'post_modified_gmt' => $now,
			)
		);

		return \WPSellServices\Models\Service::from_post( $placeholder_post );
	}

	/**
	 * Render checkout form.
	 *
	 * Used for both regular checkout and pay_order flow. When $pay_order is provided,
	 * the order total is used directly (skipping package price and tax calculation).
	 *
	 * @param \WPSellServices\Models\Service $service    Service.
	 * @param int                            $package_id Selected package ID (ignored when $pay_order is set).
	 * @param int                            $quantity   Quantity (ignored when $pay_order is set).
	 * @param \WPSellServices\Models\ServiceOrder|null $pay_order       Existing order to pay (from proposal acceptance).
	 * @param array                          $selected_addons Validated addon objects from the addons table.
	 * @return void
	 */
	private function render_checkout_form( $service, int $package_id = 0, int $quantity = 1, ?ServiceOrder $pay_order = null, array $selected_addons = array() ): void {
		$is_pay_order = null !== $pay_order;

		if ( $is_pay_order ) {
			// Pay-order flow: use the order total directly (tax already included).
			$total      = (float) $pay_order->total;
			$currency   = $pay_order->currency ?: wpss_get_currency();
			$tax_amount = 0;
			$tax_rate   = 0;
			$tax_label  = '';
			$price      = $total;

			$selected_package = null;
			$vendor           = get_user_by( 'id', $pay_order->vendor_id );
			$vendor_name      = $vendor ? $vendor->display_name : '';
		} else {
			// Regular checkout flow: calculate price from package.
			$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: [];
			$selected_package = null;

			if ( isset( $packages[ $package_id ] ) ) {
				$selected_package = $packages[ $package_id ];
			}

			if ( ! $selected_package && ! empty( $packages ) ) {
				$selected_package = reset( $packages );
				$package_id       = (int) array_key_first( $packages );
			}

			$unit_price   = (float) ( $selected_package['price'] ?? 0 );
			$price        = $unit_price * $quantity;
			$addons_total = 0;
			$addons_data  = array();

			foreach ( $selected_addons as $addon ) {
				$addon_price   = (float) $addon->price;
				$addons_total += $addon_price;
				$addons_data[] = array(
					'id'                  => (int) $addon->id,
					'name'                => $addon->title ?? $addon->name ?? '',
					'price'               => $addon_price,
					'delivery_days_extra' => (int) ( $addon->delivery_days_extra ?? $addon->extra_days ?? 0 ),
				);
			}

			$price   += $addons_total;
			$currency = wpss_get_currency();

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
			$total       = $tax_included ? $price : $price + $tax_amount;
			$vendor_name = '';
		}

		// Get available payment gateways.
		$gateways = wpss()->get_payment_gateways();
		$enabled_gateways = array_filter( $gateways, fn( $g ) => $g->is_enabled() );

		// Vendor data for regular checkout.
		if ( ! $is_pay_order ) {
			$vendor            = get_userdata( $service->vendor_id );
			$vendor_name       = $vendor ? $vendor->display_name : '';
			$vendor_avatar_url = get_avatar_url( $service->vendor_id, array( 'size' => 48 ) );
		} else {
			$vendor_avatar_url = get_avatar_url( $pay_order->vendor_id, array( 'size' => 48 ) );
		}

		// Delivery and revision info from the selected package.
		$delivery_days = $selected_package['delivery_days'] ?? 0;
		$revisions     = $selected_package['revisions'] ?? 0;

		// Review stats for the service.
		$review_count = (int) get_post_meta( $service->id, '_wpss_review_count', true );
		$review_avg   = (float) get_post_meta( $service->id, '_wpss_review_avg', true );
		?>

		<script>document.body.classList.add('wpss-checkout-page');</script>
		<style>
			/* Force full-width checkout — hide theme sidebar. */
			body.wpss-checkout-page #secondary,
			body.wpss-checkout-page aside.widget-area,
			body.wpss-checkout-page .left-sidebar,
			body.wpss-checkout-page .right-sidebar { display: none !important; }
			body.wpss-checkout-page .site-content { display: block !important; }
			body.wpss-checkout-page .content-area,
			body.wpss-checkout-page #primary { width: 100% !important; max-width: 100% !important; float: none !important; flex: 1 !important; }

			/* Header bar */
			.wpss-co-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: var(--wpss-space-4) 0;
				margin-bottom: var(--wpss-space-6);
				border-bottom: 1px solid var(--wpss-border-light);
			}
			.wpss-co-header__back {
				display: inline-flex;
				align-items: center;
				gap: var(--wpss-space-2);
				font-size: var(--wpss-text-base);
				font-weight: 500;
				color: var(--wpss-text-muted);
				text-decoration: none;
				transition: color var(--wpss-ease);
			}
			.wpss-co-header__back:hover { color: var(--wpss-primary); text-decoration: none; }
			.wpss-co-header__secure {
				display: inline-flex;
				align-items: center;
				gap: var(--wpss-space-2);
				font-size: var(--wpss-text-sm);
				font-weight: 600;
				color: var(--wpss-success);
			}

			/* Service info card */
			.wpss-co-service { display: flex; gap: var(--wpss-space-5); align-items: flex-start; }
			.wpss-co-service__thumb {
				width: 120px; height: 80px; object-fit: cover;
				border-radius: var(--wpss-radius); flex-shrink: 0;
			}
			.wpss-co-service__details { flex: 1; min-width: 0; }
			.wpss-co-service__title {
				font-size: var(--wpss-text-lg); font-weight: 600; color: var(--wpss-text);
				margin: 0 0 var(--wpss-space-2); line-height: 1.3;
			}
			.wpss-co-vendor {
				display: flex; align-items: center; gap: var(--wpss-space-2);
				margin-bottom: var(--wpss-space-3);
			}
			.wpss-co-vendor__avatar {
				width: 28px; height: 28px; border-radius: var(--wpss-radius-full); object-fit: cover;
			}
			.wpss-co-vendor__name { font-size: var(--wpss-text-sm); color: var(--wpss-text-secondary); font-weight: 500; }
			.wpss-co-meta {
				display: flex; flex-wrap: wrap; gap: var(--wpss-space-4);
				font-size: var(--wpss-text-sm); color: var(--wpss-text-muted);
			}
			.wpss-co-meta__item { display: inline-flex; align-items: center; gap: var(--wpss-space-1); }
			.wpss-co-stars { color: var(--wpss-star); letter-spacing: 1px; }

			/* Payment method cards */
			.wpss-co-methods { display: flex; flex-direction: column; gap: var(--wpss-space-3); }
			.wpss-co-method {
				position: relative;
				border: 2px solid var(--wpss-border);
				border-radius: var(--wpss-radius-lg);
				padding: var(--wpss-space-4) var(--wpss-space-5);
				cursor: pointer;
				transition: border-color var(--wpss-ease), box-shadow var(--wpss-ease), background var(--wpss-ease);
			}
			.wpss-co-method:hover { border-color: var(--wpss-text-hint); }
			.wpss-co-method.wpss-co-method--active {
				border-color: var(--wpss-primary);
				background: var(--wpss-primary-light);
				box-shadow: 0 0 0 3px var(--wpss-primary-50);
			}
			.wpss-co-method__label {
				display: flex; align-items: center; gap: var(--wpss-space-3);
				cursor: pointer; font-size: var(--wpss-text-base); font-weight: 500; color: var(--wpss-text);
			}
			.wpss-co-method__label input[type="radio"] {
				width: 18px; height: 18px; accent-color: var(--wpss-primary); cursor: pointer; margin: 0; flex-shrink: 0;
			}
			.wpss-co-method__form {
				margin-top: var(--wpss-space-4);
				padding-top: var(--wpss-space-4);
				border-top: 1px solid var(--wpss-border-light);
			}

			/* Order summary sidebar */
			.wpss-co-summary-line {
				display: flex; justify-content: space-between; align-items: center;
				padding: var(--wpss-space-2) 0;
				font-size: var(--wpss-text-base); color: var(--wpss-text-secondary);
			}
			.wpss-co-summary-line--addon { font-size: var(--wpss-text-sm); color: var(--wpss-text-muted); }
			.wpss-co-summary-line--tax { font-size: var(--wpss-text-sm); color: var(--wpss-text-muted); }
			.wpss-co-summary-total {
				display: flex; justify-content: space-between; align-items: center;
				padding: var(--wpss-space-4) 0 0;
				margin-top: var(--wpss-space-3);
				border-top: 2px solid var(--wpss-text);
				font-size: var(--wpss-text-xl); font-weight: 700; color: var(--wpss-text);
			}

			/* Trust indicators */
			.wpss-co-trust {
				display: flex; flex-direction: column; gap: var(--wpss-space-3);
				padding: var(--wpss-space-4) 0;
			}
			.wpss-co-trust__item {
				display: flex; align-items: center; gap: var(--wpss-space-3);
				font-size: var(--wpss-text-sm); color: var(--wpss-text-muted);
			}
			.wpss-co-trust__icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }

			/* What happens next — step indicator */
			.wpss-co-steps { margin-top: var(--wpss-space-8); }
			.wpss-co-steps__track {
				display: flex; align-items: flex-start; justify-content: space-between;
				position: relative; padding: 0;
			}
			.wpss-co-steps__track::before {
				content: '';
				position: absolute; top: 16px; left: 24px; right: 24px;
				height: 2px; background: var(--wpss-border);
			}
			.wpss-co-step {
				display: flex; flex-direction: column; align-items: center;
				gap: var(--wpss-space-2); position: relative; flex: 1; text-align: center;
			}
			.wpss-co-step__dot {
				width: 32px; height: 32px; border-radius: var(--wpss-radius-full);
				background: var(--wpss-bg); border: 2px solid var(--wpss-border);
				display: flex; align-items: center; justify-content: center;
				font-size: var(--wpss-text-sm); font-weight: 600; color: var(--wpss-text-muted);
				position: relative; z-index: 1;
			}
			.wpss-co-step:first-child .wpss-co-step__dot {
				background: var(--wpss-primary); border-color: var(--wpss-primary); color: #fff;
			}
			.wpss-co-step__label { font-size: var(--wpss-text-xs); color: var(--wpss-text-muted); font-weight: 500; }

			/* Login card */
			.wpss-co-login { text-align: center; padding: var(--wpss-space-10) var(--wpss-space-6); }
			.wpss-co-login__actions { display: flex; gap: var(--wpss-space-3); justify-content: center; margin-top: var(--wpss-space-5); }

			/* Responsive */
			@media (max-width: 768px) {
				.wpss-co-header { flex-direction: column; gap: var(--wpss-space-2); align-items: flex-start; }
				.wpss-co-service { flex-direction: column; }
				.wpss-co-service__thumb { width: 100%; height: 160px; }
				.wpss-co-steps__track { flex-wrap: wrap; gap: var(--wpss-space-3); }
				.wpss-co-steps__track::before { display: none; }
				.wpss-co-step { flex-direction: row; text-align: left; }
			}
		</style>

		<div class="wpss-checkout-page">
			<!-- Header bar -->
			<div class="wpss-co-header">
				<a href="<?php echo esc_url( get_permalink( $service->id ) ); ?>" class="wpss-co-header__back">
					<span aria-hidden="true">&larr;</span>
					<?php esc_html_e( 'Back to service', 'wp-sell-services' ); ?>
				</a>
				<span class="wpss-co-header__secure">
					<span aria-hidden="true">&#128274;</span>
					<?php esc_html_e( 'Secure Checkout', 'wp-sell-services' ); ?>
				</span>
			</div>

			<?php if ( ! is_user_logged_in() ) : ?>
				<!-- Login required -->
				<div class="wpss-card wpss-co-login">
					<div class="wpss-empty__icon" aria-hidden="true">&#128100;</div>
					<h3 class="wpss-heading-3"><?php esc_html_e( 'Sign in to continue', 'wp-sell-services' ); ?></h3>
					<p class="wpss-caption" style="margin-top:var(--wpss-space-2);">
						<?php esc_html_e( 'Please log in or create an account to complete your purchase.', 'wp-sell-services' ); ?>
					</p>
					<div class="wpss-co-login__actions">
						<a href="<?php echo esc_url( wp_login_url( $this->get_checkout_url( $service->id, array( 'package_id' => $package_id ) ) ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Log In', 'wp-sell-services' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="wpss-btn wpss-btn--outline">
							<?php esc_html_e( 'Register', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>

			<?php elseif ( empty( $enabled_gateways ) ) : ?>
				<!-- No gateways -->
				<div class="wpss-notice wpss-notice--error">
					<?php esc_html_e( 'No payment methods available. Please contact support.', 'wp-sell-services' ); ?>
				</div>

			<?php else : ?>
				<!-- Notice area -->
				<div id="wpss-checkout-notice" class="wpss-notice wpss-notice--error" style="display:none;" role="alert"></div>

				<form method="post" class="wpss-checkout-form" id="wpss-checkout-form">
					<?php wp_nonce_field( 'wpss_checkout', 'wpss_checkout_nonce' ); ?>
					<input type="hidden" name="service_id" value="<?php echo esc_attr( $service->id ); ?>">
					<?php if ( $is_pay_order ) : ?>
						<input type="hidden" name="pay_order" value="<?php echo esc_attr( $pay_order->id ); ?>">
					<?php else : ?>
						<input type="hidden" name="package_id" value="<?php echo esc_attr( $package_id ); ?>">
						<input type="hidden" name="quantity" value="<?php echo esc_attr( $quantity ); ?>">
						<input type="hidden" name="tax_amount" value="<?php echo esc_attr( round( $tax_amount, 2 ) ); ?>">
						<?php if ( ! empty( $addons_data ) ) : ?>
							<input type="hidden" name="addon_ids" value="<?php echo esc_attr( implode( ',', array_column( $addons_data, 'id' ) ) ); ?>">
							<input type="hidden" name="addons_total" value="<?php echo esc_attr( round( $addons_total, 2 ) ); ?>">
							<input type="hidden" name="addons_data" value="<?php echo esc_attr( wp_json_encode( $addons_data ) ); ?>">
						<?php endif; ?>
					<?php endif; ?>
					<input type="hidden" name="amount" value="<?php echo esc_attr( $total ); ?>">
					<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">

					<!-- Two-column layout -->
					<div class="wpss-layout wpss-layout--sidebar-right">

						<!-- LEFT COLUMN: Service info + payment methods -->
						<div class="wpss-stack wpss-stack--lg">

							<!-- Service info card -->
							<div class="wpss-card">
								<div class="wpss-card__header">
									<h3 class="wpss-card__title">
										<?php echo $is_pay_order ? esc_html__( 'Order Details', 'wp-sell-services' ) : esc_html__( 'Service Details', 'wp-sell-services' ); ?>
									</h3>
								</div>
								<div class="wpss-card__body">
									<div class="wpss-co-service">
										<?php if ( $service->thumbnail_id ) : ?>
											<img class="wpss-co-service__thumb" src="<?php echo esc_url( $service->get_thumbnail_url( 'medium' ) ); ?>" alt="<?php echo esc_attr( $service->title ); ?>">
										<?php endif; ?>

										<div class="wpss-co-service__details">
											<h4 class="wpss-co-service__title"><?php echo esc_html( $service->title ); ?></h4>

											<?php if ( $vendor_name ) : ?>
												<div class="wpss-co-vendor">
													<?php if ( $vendor_avatar_url ) : ?>
														<img class="wpss-co-vendor__avatar" src="<?php echo esc_url( $vendor_avatar_url ); ?>" alt="<?php echo esc_attr( $vendor_name ); ?>">
													<?php endif; ?>
													<span class="wpss-co-vendor__name"><?php echo esc_html( $vendor_name ); ?></span>
												</div>
											<?php endif; ?>

											<div class="wpss-co-meta">
												<?php if ( $review_count > 0 ) : ?>
													<span class="wpss-co-meta__item">
														<span class="wpss-co-stars" aria-hidden="true">&#9733;</span>
														<strong><?php echo esc_html( number_format( $review_avg, 1 ) ); ?></strong>
														<span>(<?php echo esc_html( $review_count ); ?>)</span>
													</span>
												<?php endif; ?>

												<?php if ( $is_pay_order ) : ?>
													<span class="wpss-co-meta__item">
														<?php echo esc_html( $pay_order->order_number ); ?>
													</span>
												<?php else : ?>
													<?php if ( $delivery_days > 0 ) : ?>
														<span class="wpss-co-meta__item">
															<span aria-hidden="true">&#128337;</span>
															<?php
															/* translators: %d: number of days */
															printf( esc_html__( '%d-day delivery', 'wp-sell-services' ), (int) $delivery_days );
															?>
														</span>
													<?php endif; ?>

													<?php if ( $revisions > 0 ) : ?>
														<span class="wpss-co-meta__item">
															<span aria-hidden="true">&#128260;</span>
															<?php
															/* translators: %d: number of revisions */
															printf( esc_html( _n( '%d revision', '%d revisions', (int) $revisions, 'wp-sell-services' ) ), (int) $revisions );
															?>
														</span>
													<?php endif; ?>
												<?php endif; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Payment methods -->
							<div class="wpss-card">
								<div class="wpss-card__header">
									<h3 class="wpss-card__title"><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></h3>
								</div>
								<div class="wpss-card__body">
									<div class="wpss-co-methods">
										<?php foreach ( $enabled_gateways as $gateway_id => $gateway ) : ?>
											<div class="wpss-co-method" data-method="<?php echo esc_attr( $gateway_id ); ?>">
												<label class="wpss-co-method__label">
													<input type="radio" name="payment_method" value="<?php echo esc_attr( $gateway_id ); ?>" required>
													<?php echo esc_html( $gateway->get_name() ); ?>
												</label>
												<div class="wpss-co-method__form wpss-gateway-form" data-gateway="<?php echo esc_attr( $gateway_id ); ?>" style="display: none;">
													<?php
													$gateway_order_id = $is_pay_order ? $pay_order->id : 0;
													echo $gateway->render_payment_form( $total, $currency, $gateway_order_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													?>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>

						</div><!-- /left column -->

						<!-- RIGHT COLUMN: Order summary (sticky) -->
						<div class="wpss-sticky">
							<div class="wpss-card">
								<div class="wpss-card__header">
									<h3 class="wpss-card__title">
										<?php echo $is_pay_order ? esc_html__( 'Order Payment', 'wp-sell-services' ) : esc_html__( 'Order Summary', 'wp-sell-services' ); ?>
									</h3>
								</div>
								<div class="wpss-card__body">

									<?php if ( $is_pay_order ) : ?>
										<!-- Pay-order: just the total -->
										<div class="wpss-co-summary-line">
											<span><?php esc_html_e( 'Order Total', 'wp-sell-services' ); ?></span>
											<span><strong><?php echo esc_html( wpss_format_price( $total, $currency ) ); ?></strong></span>
										</div>
									<?php else : ?>
										<!-- Package line -->
										<?php if ( $selected_package ) : ?>
											<div class="wpss-co-summary-line">
												<span>
													<?php echo esc_html( $selected_package['name'] ?? '' ); ?>
													<?php if ( $quantity > 1 ) : ?>
														<span class="wpss-caption">&times; <?php echo esc_html( $quantity ); ?></span>
													<?php endif; ?>
												</span>
												<span><?php echo esc_html( wpss_format_price( $price - ( $addons_total ?? 0 ), $currency ) ); ?></span>
											</div>
										<?php endif; ?>

										<!-- Addon lines -->
										<?php if ( ! empty( $addons_data ) ) : ?>
											<?php foreach ( $addons_data as $addon_item ) : ?>
												<div class="wpss-co-summary-line wpss-co-summary-line--addon">
													<span><?php echo esc_html( $addon_item['name'] ); ?></span>
													<span><?php echo esc_html( wpss_format_price( $addon_item['price'], $currency ) ); ?></span>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>

										<!-- Tax line -->
										<?php if ( $tax_amount > 0 ) : ?>
											<div class="wpss-co-summary-line wpss-co-summary-line--tax">
												<span><?php echo esc_html( $tax_label ); ?> (<?php echo esc_html( $tax_rate ); ?>%)</span>
												<span><?php echo esc_html( wpss_format_price( $tax_amount, $currency ) ); ?></span>
											</div>
										<?php endif; ?>
									<?php endif; ?>

									<!-- Total -->
									<div class="wpss-co-summary-total">
										<span><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></span>
										<span><?php echo esc_html( wpss_format_price( $total, $currency ) ); ?></span>
									</div>
								</div>

								<div class="wpss-card__footer" style="flex-direction:column;align-items:stretch;">
									<!-- CTA button -->
									<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-btn--full wpss-checkout-button">
										<span class="wpss-checkout-button__text">
											<?php
											/* translators: %s: formatted price */
											printf( esc_html__( 'Pay %s', 'wp-sell-services' ), esc_html( wpss_format_price( $total, $currency ) ) );
											?>
										</span>
									</button>

									<!-- Trust section -->
									<div class="wpss-co-trust">
										<div class="wpss-co-trust__item">
											<span class="wpss-co-trust__icon" aria-hidden="true">&#128274;</span>
											<span><?php esc_html_e( 'Secure payment', 'wp-sell-services' ); ?></span>
										</div>
										<div class="wpss-co-trust__item">
											<span class="wpss-co-trust__icon" aria-hidden="true">&#128737;</span>
											<span><?php esc_html_e( 'Order protection', 'wp-sell-services' ); ?></span>
										</div>
									</div>
								</div>
							</div>
						</div><!-- /right column -->

					</div><!-- /layout -->
				</form>

				<!-- What happens next -->
				<div class="wpss-co-steps">
					<div class="wpss-card">
						<div class="wpss-card__header">
							<h3 class="wpss-card__title"><?php esc_html_e( 'What happens next?', 'wp-sell-services' ); ?></h3>
						</div>
						<div class="wpss-card__body">
							<div class="wpss-co-steps__track">
								<div class="wpss-co-step">
									<span class="wpss-co-step__dot">1</span>
									<span class="wpss-co-step__label"><?php esc_html_e( 'Pay', 'wp-sell-services' ); ?></span>
								</div>
								<div class="wpss-co-step">
									<span class="wpss-co-step__dot">2</span>
									<span class="wpss-co-step__label"><?php esc_html_e( 'Requirements', 'wp-sell-services' ); ?></span>
								</div>
								<div class="wpss-co-step">
									<span class="wpss-co-step__dot">3</span>
									<span class="wpss-co-step__label"><?php esc_html_e( 'Seller Works', 'wp-sell-services' ); ?></span>
								</div>
								<div class="wpss-co-step">
									<span class="wpss-co-step__dot">4</span>
									<span class="wpss-co-step__label"><?php esc_html_e( 'Review', 'wp-sell-services' ); ?></span>
								</div>
								<div class="wpss-co-step">
									<span class="wpss-co-step__dot">5</span>
									<span class="wpss-co-step__label"><?php esc_html_e( 'Complete', 'wp-sell-services' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<script>
				(function() {
					var form = document.getElementById('wpss-checkout-form');
					if (!form) return;

					var submitBtn = form.querySelector('.wpss-checkout-button');
					var submitBtnText = submitBtn.querySelector('.wpss-checkout-button__text');
					var originalText = submitBtnText.textContent;
					var noticeEl = document.getElementById('wpss-checkout-notice');

					function showNotice(msg, type) {
						noticeEl.className = 'wpss-notice wpss-notice--' + (type || 'error');
						noticeEl.textContent = msg;
						noticeEl.style.display = 'flex';
						noticeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}

					function hideNotice() {
						noticeEl.style.display = 'none';
					}

					// Show/hide gateway forms + active state on radio change.
					document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
						radio.addEventListener('change', function() {
							hideNotice();
							document.querySelectorAll('.wpss-co-method').forEach(function(m) {
								m.classList.remove('wpss-co-method--active');
							});
							document.querySelectorAll('.wpss-gateway-form').forEach(function(gform) {
								gform.style.display = 'none';
							});
							var method = this.closest('.wpss-co-method');
							if (method) method.classList.add('wpss-co-method--active');
							var selected = document.querySelector('.wpss-gateway-form[data-gateway="' + this.value + '"]');
							if (selected) selected.style.display = 'block';
						});
					});

					// Click anywhere on method card to select radio.
					document.querySelectorAll('.wpss-co-method').forEach(function(method) {
						method.addEventListener('click', function(e) {
							if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'A') return;
							var radio = this.querySelector('input[type="radio"]');
							if (radio && !radio.checked) {
								radio.checked = true;
								radio.dispatchEvent(new Event('change', { bubbles: true }));
							}
						});
					});

					// Handle form submission.
					form.addEventListener('submit', function(e) {
						e.preventDefault();
						hideNotice();

						var paymentMethod = form.querySelector('input[name="payment_method"]:checked');
						if (!paymentMethod) {
							showNotice('<?php echo esc_js( __( 'Please select a payment method.', 'wp-sell-services' ) ); ?>');
							return;
						}

						submitBtn.disabled = true;
						submitBtnText.textContent = '<?php echo esc_js( __( 'Processing...', 'wp-sell-services' ) ); ?>';

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
							if (data.success && data.data && data.data.redirect_url) {
								window.location.href = data.data.redirect_url;
							} else if (data.success && data.data && data.data.redirect) {
								window.location.href = data.data.redirect;
							} else {
								var msg = (data.data && data.data.message) ? data.data.message : '<?php echo esc_js( __( 'Payment failed. Please try again.', 'wp-sell-services' ) ); ?>';
								showNotice(msg);
								submitBtn.disabled = false;
								submitBtnText.textContent = originalText;
							}
						})
						.catch(function(error) {
							console.error('Checkout error:', error);
							showNotice('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
							submitBtn.disabled = false;
							submitBtnText.textContent = originalText;
						});
					});
				})();
				</script>
			<?php endif; ?>
		</div>
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
