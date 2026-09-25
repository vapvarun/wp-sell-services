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

use WPSellServices\Checkout\CheckoutIntentService;
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

		// Index 0 is a real package, so the arg must survive it.
		if ( isset( $args['package_id'] ) && '' !== $args['package_id'] ) {
			$url = add_query_arg( 'package', (int) $args['package_id'], $url );
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
		// Remove only the purchased service from cart, keeping other items.
		$service_id = (int) ( $order_data['service_id'] ?? 0 );
		if ( $service_id ) {
			$this->remove_from_cart( $service_id );
		} else {
			$this->clear_cart();
		}

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
	 * Render the outcome for a buyer returning from off-site authentication.
	 *
	 * Display only — fulfilment stays with the webhook, which is what Stripe
	 * prescribes because a buyer may close the tab before ever getting back
	 * here. The gateway reconciles idempotently, so this page reports what
	 * happened rather than deciding it.
	 *
	 * Every branch avoids offering a second payment for a charge that already
	 * succeeded. "Processing" deliberately does not, either: the money moved,
	 * and the honest instruction is to wait, not to pay again.
	 *
	 * @since 1.4.0
	 *
	 * @param string $payment_intent_id PaymentIntent id from the return URL.
	 * @return string
	 */
	private function render_payment_return( string $payment_intent_id ): string {
		$gateways = function_exists( 'wpss' ) ? wpss()->get_payment_gateways() : array();
		$gateway  = $gateways['stripe'] ?? null;

		if ( ! $gateway || ! method_exists( $gateway, 'reconcile_redirect_return' ) ) {
			return '<div class="wpss-checkout-return wpss-notice wpss-notice--info"><p>'
				. esc_html__( 'We are confirming your payment. Please check your orders in a moment — do not pay again.', 'wp-sell-services' )
				. '</p></div>';
		}

		$result   = $gateway->reconcile_redirect_return( $payment_intent_id );
		$orders   = wpss_get_page_url( 'dashboard' );
		$continue = '';

		ob_start();

		if ( 'paid' === $result['status'] ) {
			$order_url = $result['order_id'] > 0 ? wpss_get_order_url( (int) $result['order_id'] ) : $orders;
			?>
			<div class="wpss-checkout-return wpss-checkout-return--paid">
				<h2><?php esc_html_e( 'Payment received', 'wp-sell-services' ); ?></h2>
				<p><?php esc_html_e( 'Thank you. Your payment is confirmed and your order is on its way to the seller.', 'wp-sell-services' ); ?></p>
				<p><a class="wpss-btn wpss-btn-primary" href="<?php echo esc_url( $order_url ); ?>"><?php esc_html_e( 'View your order', 'wp-sell-services' ); ?></a></p>
			</div>
			<?php
		} elseif ( 'processing' === $result['status'] ) {
			// Refresh rather than ask for another payment. The webhook usually
			// settles within a second or two.
			?>
			<div class="wpss-checkout-return wpss-checkout-return--processing" data-wpss-return-poll="1">
				<h2><?php esc_html_e( 'Confirming your payment', 'wp-sell-services' ); ?></h2>
				<p><?php echo esc_html( $result['message'] ); ?></p>
				<p><strong><?php esc_html_e( 'Please do not pay again.', 'wp-sell-services' ); ?></strong></p>
				<p><a class="wpss-btn" href="<?php echo esc_url( $orders ); ?>"><?php esc_html_e( 'Go to your orders', 'wp-sell-services' ); ?></a></p>
			</div>
			<script>setTimeout(function(){ window.location.reload(); }, 5000);</script>
			<?php
		} else {
			// Genuinely not paid — this is the ONE branch where offering to pay
			// again is correct.
			$continue = wpss_get_checkout_base_url();
			?>
			<div class="wpss-checkout-return wpss-checkout-return--failed">
				<h2><?php esc_html_e( 'Payment not completed', 'wp-sell-services' ); ?></h2>
				<p><?php echo esc_html( $result['message'] ); ?></p>
				<?php if ( $continue ) : ?>
					<p><a class="wpss-btn wpss-btn-primary" href="<?php echo esc_url( $continue ); ?>"><?php esc_html_e( 'Try another payment method', 'wp-sell-services' ); ?></a></p>
				<?php endif; ?>
			</div>
			<?php
		}

		return (string) ob_get_clean();
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
		// Enqueue frontend assets for proper styling and functionality.
		wpss_enqueue_frontend_assets();

		// A buyer coming back from an off-site authentication (redirect-based 3D
		// Secure, or any redirect payment method) lands here with the gateway's
		// own parameters on the URL. Show them the OUTCOME — never the checkout
		// form again. The charge has already happened, so re-rendering a live
		// Pay button over it is an invitation to pay twice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$returned_intent = isset( $_GET['payment_intent'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_intent'] ) ) : '';

		if ( '' !== $returned_intent ) {
			return $this->render_payment_return( $returned_intent );
		}

		// Check if paying for an existing order (from proposal acceptance).
		$pay_order_id = (int) get_query_var( 'wpss_pay_order', 0 );
		if ( ! $pay_order_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pay_order_id = isset( $_GET['pay_order'] ) ? absint( wp_unslash( $_GET['pay_order'] ) ) : 0;
		}

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

		/*
		 * Nothing unsellable gets a Pay button - from the cart or from a link.
		 *
		 * Checked here, before any of the render paths below, because there are
		 * several of them (single service, multi-cart, package preselected) and
		 * a guard inside one is a guard the other two do not have. Paying for
		 * an existing order and returning from a gateway have already been
		 * handled above and are deliberately not affected: that money has moved.
		 */
		$blocked = $this->blocked_checkout_notice( $service_id );

		if ( '' !== $blocked ) {
			return $blocked;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$package_id = isset( $_GET['package'] ) ? absint( wp_unslash( $_GET['package'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$quantity = isset( $_GET['quantity'] ) ? absint( wp_unslash( $_GET['quantity'] ) ) : 1;
		$quantity = max( 1, min( $quantity, 10 ) ); // Clamp 1-10.

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$addon_ids_raw = isset( $_GET['addons'] ) ? sanitize_text_field( wp_unslash( $_GET['addons'] ) ) : '';
		// The full selection (quantity, option, text) when the order modal sent
		// one; ids alone otherwise. Normalised by the pricer, never a price.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised by wpss_normalize_addon_selection().
		$addon_sel = isset( $_GET['addon_sel'] ) ? wp_unslash( $_GET['addon_sel'] ) : '';
		if ( is_string( $addon_sel ) && '' !== $addon_sel ) {
			$addon_ids_raw = $addon_sel;
		}

		// If no service_id in URL, try to load from user's cart.
		if ( ! $service_id ) {
			$cart = $this->get_cart();

			if ( count( $cart ) > 1 ) {
				// Multi-service checkout: render all cart items together.
				return $this->render_multi_checkout_form( $cart );
			}

			if ( ! empty( $cart ) ) {
				// Use the most recently added cart item.
				$cart_item  = end( $cart );
				$service_id = (int) ( $cart_item['service_id'] ?? 0 );
				$package_id = $package_id ?: (int) ( $cart_item['package_id'] ?? 0 );
				$quantity   = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );

				// Restore addons from cart item if not provided via URL.
				// '' means the URL carried none; "0" means it carried the FIRST
				// add-on, and a truthiness test here discarded that selection in
				// favour of the cart's - see the comment at the resolve site below.
				if ( '' === $addon_ids_raw && ! empty( $cart_item['addons'] ) ) {
					$addon_ids_raw = $cart_item['addons'];
				}
			}
		}

		if ( ! $service_id ) {
			/*
			 * Last line of defence for the pay-one-order route.
			 *
			 * A buyer following /{checkout}/pay/{id}/ is answered by
			 * render_pay_order_checkout() above, which has no path to the empty
			 * state below - every failure it has returns a specific message. But
			 * that depends on the rewrite rule resolving, and if the rule is
			 * missing or stale the request lands here instead, where the buyer
			 * is told their cart is empty while they are trying to pay a
			 * specific invoice (Basecamp 10240017271).
			 *
			 * The rule self-heals in StandaloneAdapter and I could not reproduce
			 * the report on current code, so this should be unreachable. It is
			 * cheap, and "your cart is empty" in front of someone holding a bill
			 * is the kind of thing that costs a sale rather than a bug report.
			 */
			$pay_path_id = 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( $request_uri && preg_match( '#/pay/([0-9]+)/?(?:\?|$)#', $request_uri, $m ) ) {
				$pay_path_id = (int) $m[1];
			}

			if ( $pay_path_id > 0 ) {
				return $this->render_pay_order_checkout( $pay_path_id );
			}

			// Reaching checkout with an empty cart is an ordinary state, not a
			// failure: a red "No service selected." error alert blamed the buyer
			// for something that had not gone wrong and offered no way forward.
			// Mirror the cart page's empty state — same icon/title/text/CTA
			// shape — so the dead end becomes a route back to browsing.
			ob_start();
			?>
			<div class="wpss-cart-empty">
				<div class="wpss-cart-empty__icon">
					<i data-lucide="shopping-cart" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
				</div>
				<h2 class="wpss-cart-empty__title"><?php esc_html_e( 'Your cart is empty', 'wp-sell-services' ); ?></h2>
				<p class="wpss-cart-empty__text"><?php esc_html_e( 'Choose a service and a package to check out.', 'wp-sell-services' ); ?></p>
				<a href="<?php echo esc_url( wpss_get_page_url( 'services_page' ) ? wpss_get_page_url( 'services_page' ) : home_url( '/' ) ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
				</a>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			return '<p>' . esc_html__( 'Service not found.', 'wp-sell-services' ) . '</p>';
		}

		ob_start();
		$this->render_checkout_form( $service, $package_id, $quantity, null, $addon_ids_raw );
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

		$parent_order_id = ! empty( $order->platform_order_id ) ? (int) $order->platform_order_id : (int) $order->id;
		$back_to_order   = wpss_get_order_url( $parent_order_id );

		// Only pending_payment orders can be paid.
		if ( 'pending_payment' !== $order->status ) {
			return sprintf(
				'<p class="wpss-alert wpss-alert-info">%s</p><p><a class="wpss-btn wpss-btn--secondary" href="%s">%s</a></p>',
				esc_html__( 'This order has already been paid.', 'wp-sell-services' ),
				esc_url( $back_to_order ),
				esc_html__( 'Back to order', 'wp-sell-services' )
			);
		}

		// Lock-step backstop on milestone sub-orders: even though the on-page
		// timeline hides the Pay button on locked phases, a buyer could craft
		// the URL by hand to skip ahead. The server is the only authority.
		if ( \WPSellServices\Services\MilestoneService::ORDER_TYPE === ( $order->platform ?? '' ) ) {
			$milestones = new \WPSellServices\Services\MilestoneService();
			if ( $milestones->is_locked( $order_id ) ) {
				return sprintf(
					'<p class="wpss-alert wpss-alert-info">%s</p><p><a class="wpss-btn wpss-btn--secondary" href="%s">%s</a></p>',
					esc_html__( 'This phase is locked. Pay the previous phase first — once it is approved or cancelled, this one will unlock.', 'wp-sell-services' ),
					esc_url( $back_to_order ),
					esc_html__( 'Back to order', 'wp-sell-services' )
				);
			}
		}

		// Sub-orders (tip, extension, milestone) are one-shot credits or
		// top-ups on an existing project. They don't have the "Requirements
		// → Seller Works → Review → Complete" lifecycle, so the general
		// 5-step "What happens next?" stepper misleads buyers. Detect once
		// here and the template block below swaps in a 2-step flow.
		$sub_order_platforms = array(
			\WPSellServices\Services\TippingService::ORDER_TYPE,
			\WPSellServices\Services\ExtensionOrderService::ORDER_TYPE,
			\WPSellServices\Services\MilestoneService::ORDER_TYPE,
		);
		$is_sub_order        = in_array( (string) ( $order->platform ?? '' ), $sub_order_platforms, true );

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
	 * @param \WPSellServices\Models\Service           $service    Service.
	 * @param int                                      $package_id Selected package ID (ignored when $pay_order is set).
	 * @param int                                      $quantity   Quantity (ignored when $pay_order is set).
	 * @param \WPSellServices\Models\ServiceOrder|null $pay_order       Existing order to pay (from proposal acceptance).
	 * @param mixed                                    $selection       Add-on selection (see wpss_normalize_addon_selection()).
	 * @return void
	 */
	private function render_checkout_form( $service, int $package_id = 0, int $quantity = 1, ?ServiceOrder $pay_order = null, $selection = array() ): void {
		$is_pay_order = null !== $pay_order;

		// Sub-orders (tip, extension, milestone) skip the 5-step "Pay →
		// Requirements → Seller Works → Review → Complete" stepper — those
		// lifecycle steps don't apply to a one-shot credit or top-up.
		$is_sub_order = false;
		if ( $is_pay_order ) {
			$sub_order_platforms = array(
				\WPSellServices\Services\TippingService::ORDER_TYPE,
				\WPSellServices\Services\ExtensionOrderService::ORDER_TYPE,
				\WPSellServices\Services\MilestoneService::ORDER_TYPE,
			);
			$is_sub_order        = in_array( (string) ( $pay_order->platform ?? '' ), $sub_order_platforms, true );
		}

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
			// Regular checkout flow: priced by the one pricer the gateways charge through.
			$line = CheckoutIntentService::price_service_line( (int) $service->id, $package_id, $quantity, $selection );

			if ( is_wp_error( $line ) ) {
				echo '<p class="wpss-alert wpss-alert-error">' . esc_html( $line->get_error_message() ) . '</p>';
				return;
			}

			$selected_package = $line['package'];
			$package_id       = (int) $line['package_id'];
			$addon_lines      = $line['addons'];
			$addons_total     = (float) $line['addons_total'];
			$price            = (float) $line['subtotal'] + $addons_total;
			$currency         = wpss_get_currency();
			$tax_rate         = (float) $line['tax_rate'];
			$tax_amount       = (float) $line['tax'];
			$tax_label        = (string) $line['tax_label'];
			$total            = (float) $line['total'];
			$vendor_name      = '';
		}

		// Get available payment gateways.
		$gateways         = wpss()->get_payment_gateways();
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

		// Review stats from actual reviews table (not post meta which may be stale).
		$review_repo    = new \WPSellServices\Database\Repositories\ReviewRepository();
		$rating_summary = $review_repo->get_service_rating_summary( $service->id );
		$review_count   = (int) ( $rating_summary['total_reviews'] ?? 0 );
		$review_avg     = round( (float) ( $rating_summary['average_rating'] ?? 0 ), 1 );

		// Summary lines for templates/checkout/summary.php.
		$summary_lines = array();
		if ( $is_pay_order ) {
			$summary_lines[] = array(
				'label'  => __( 'Order Total', 'wp-sell-services' ),
				'amount' => $total,
				'strong' => true,
			);
		} else {
			if ( $selected_package ) {
				$summary_lines[] = array(
					'label'  => (string) ( $selected_package['name'] ?? '' ),
					'note'   => $quantity > 1 ? "\u{00D7} " . $quantity : '',
					'amount' => $price - $addons_total,
				);
			}
			foreach ( $addon_lines as $addon_item ) {
				$summary_lines[] = array(
					'label'  => $addon_item['title'],
					'note'   => $addon_item['quantity'] > 1 ? "\u{00D7} " . $addon_item['quantity'] : (string) $addon_item['option'],
					'amount' => $addon_item['price'],
					'type'   => 'addon',
				);
			}
			if ( $tax_amount > 0 ) {
				$summary_lines[] = $this->tax_summary_line( $tax_label, $tax_rate, $tax_amount );
			}
		}

		$this->enqueue_checkout_script();
		?>

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
			.wpss-co-stars { color: var(--wpss-star); fill: currentColor; }

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
			.wpss-co-trust__icon { width: 20px; flex-shrink: 0; }

			/* Seller stats */
			.wpss-co-seller-stats {
				display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
				gap: var(--wpss-space-3); text-align: center;
			}
			.wpss-co-seller-stat__value {
				display: block; font-size: var(--wpss-text-xl); font-weight: 700;
				color: var(--wpss-text); line-height: 1.3;
			}
			.wpss-co-seller-stat__label {
				display: block; font-size: var(--wpss-text-xs);
				color: var(--wpss-text-muted); margin-top: 2px;
			}

			/* Testimonial */
			.wpss-co-testimonial {
				margin-top: var(--wpss-space-4); padding-top: var(--wpss-space-4);
				border-top: 1px solid var(--wpss-border-light);
			}
			.wpss-co-testimonial__text {
				font-size: var(--wpss-text-sm); color: var(--wpss-text-secondary);
				font-style: italic; line-height: 1.6; margin: 0 0 var(--wpss-space-2);
			}
			.wpss-co-testimonial__author {
				font-size: var(--wpss-text-xs); color: var(--wpss-text-muted); font-weight: 500;
			}

			/* Guarantee badges — horizontal bar below layout */
			.wpss-co-guarantees-bar {
				/*
				 * wrap, because three badges on one line do not fit a phone.
				 * Without it this bar forced 401px of content into a 390px
				 * viewport and the whole checkout scrolled sideways - the buyer
				 * had to pan to read the total (Basecamp 10208392848).
				 */
				display: flex; flex-wrap: wrap; justify-content: space-around; gap: var(--wpss-space-6);
				padding: var(--wpss-space-6);
				background: var(--wpss-bg-subtle); border-radius: var(--wpss-radius-lg);
				margin-top: var(--wpss-space-6);
			}
			.wpss-co-guarantees {
				display: flex; flex-direction: column; gap: var(--wpss-space-4);
			}
			.wpss-co-guarantee {
				display: flex; align-items: flex-start; gap: var(--wpss-space-3);
			}
			.wpss-co-guarantee > span:first-child {
				font-size: 20px; line-height: 1; flex-shrink: 0; margin-top: 2px;
			}
			.wpss-co-guarantee strong {
				display: block; font-size: var(--wpss-text-sm); font-weight: 600;
				color: var(--wpss-text); margin-bottom: 1px;
			}
			.wpss-co-guarantee span {
				font-size: var(--wpss-text-xs); color: var(--wpss-text-muted);
			}

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
				background: var(--wpss-primary); border-color: var(--wpss-primary); color: var(--wpss-white, #fff);
			}
			.wpss-co-step__label { font-size: var(--wpss-text-xs); color: var(--wpss-text-muted); font-weight: 500; }

			/* Login card */
			.wpss-co-login { text-align: center; padding: var(--wpss-space-10) var(--wpss-space-6); }
			.wpss-co-login__actions { display: flex; gap: var(--wpss-space-3); justify-content: center; margin-top: var(--wpss-space-5); }

			/*
			 * Stacking order once the sidebar drops below the form.
			 *
			 * .wpss-layout--sidebar-right collapses to one column at 1024px, and
			 * a collapsed grid emits the columns in source order - so the whole
			 * sidebar landed after Payment Method and the buyer chose how to pay
			 * BEFORE seeing what they were paying. Measured at 390: Payment
			 * Method y=2001, Order Summary y=2274 (Basecamp 10304335437).
			 *
			 * display: contents lifts the two columns out of the box tree so all
			 * six cards become siblings that `order` can sequence. The sticky
			 * column has nothing to stick to at this width, so losing position:
			 * sticky here is the intent, not a side effect.
			 */
			@media (max-width: 1024px) {
				.wpss-checkout-page .wpss-layout--sidebar-right { display: flex; flex-direction: column; }
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-stack,
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-sticky { display: contents; }
				/*
				 * Service details, the total, what happens next, then the form.
				 *
				 * The steps are deliberately shown before the buyer pays. On a
				 * wide screen the sidebar puts them beside the summary and above
				 * the payment form for free, but once the columns stack, every
				 * remaining sidebar card fell below the form - so on a phone the
				 * one place the explanation mattered most was the one place it
				 * came too late.
				 */
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-stack > * { order: 4; }
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-stack > :first-child { order: 1; }
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-sticky > * { order: 5; }
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-sticky > .wpss-co-card--summary { order: 2; }
				.wpss-checkout-page .wpss-layout--sidebar-right > .wpss-sticky > .wpss-co-steps { order: 3; }
			}

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
					<i data-lucide="lock" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
					<?php esc_html_e( 'Secure Checkout', 'wp-sell-services' ); ?>
				</span>
			</div>

			<?php
			/*
			 * The sign-in wall, shown only when the owner has NOT enabled
			 * account-at-checkout. With it enabled the form renders normally for a
			 * logged-out buyer: the billing block already collects first name, last
			 * name and email — the three locked billing fields — and the checkout
			 * script creates the account from them before paying, so `customer_id`
			 * is a real user from the first moment. See
			 * wpss_checkout_creates_accounts().
			 */
			?>
			<?php if ( ! is_user_logged_in() && ! wpss_checkout_creates_accounts() ) : ?>
				<!-- Login required -->
				<div class="wpss-card wpss-co-login">
					<div class="wpss-empty__icon"><i data-lucide="user" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i></div>
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
				<?php
				// Multi-cart safeguard: warn if other items remain in cart.
				if ( ! $is_pay_order ) :
					$remaining_cart  = $this->get_cart();
					$remaining_count = 0;
					foreach ( $remaining_cart as $cart_item ) {
						if ( (int) ( $cart_item['service_id'] ?? 0 ) !== $service->id ) {
							++$remaining_count;
						}
					}
					if ( $remaining_count > 0 ) :
						?>
						<div class="wpss-notice wpss-notice--info" role="status">
							<span>
								<?php
								printf(
									esc_html(
										/* translators: 1: number of remaining items, 2: current service title */
										_n(
											'You have %1$d more item in your cart. This checkout is for %2$s only. Your other item will remain in your cart for separate checkout.',
											'You have %1$d more items in your cart. This checkout is for %2$s only. Your other items will remain in your cart for separate checkout.',
											$remaining_count,
											'wp-sell-services'
										)
									),
									absint( $remaining_count ),
									'<strong>' . esc_html( $service->title ) . '</strong>'
								);
								?>
							</span>
						</div>
						<?php
					endif;
				endif;
				?>

				<!-- Notice area -->
				<div id="wpss-checkout-notice" class="wpss-notice wpss-notice--error" style="display:none;" role="alert"></div>

				<form method="post" class="wpss-checkout-form" id="wpss-checkout-form" data-wpss-checkout-notice="wpss-checkout-notice">
					<?php wp_nonce_field( 'wpss_checkout', 'wpss_checkout_nonce' ); ?>
					<input type="hidden" name="service_id" value="<?php echo esc_attr( $service->id ); ?>">
					<?php if ( $is_pay_order ) : ?>
						<input type="hidden" name="pay_order" value="<?php echo esc_attr( $pay_order->id ); ?>">
					<?php else : ?>
						<input type="hidden" name="package_id" value="<?php echo esc_attr( $package_id ); ?>">
						<input type="hidden" name="quantity" value="<?php echo esc_attr( $quantity ); ?>">
						<input type="hidden" name="tax_amount" value="<?php echo esc_attr( round( $tax_amount, 2 ) ); ?>">
						<?php if ( ! empty( $addon_lines ) ) : ?>
							<?php foreach ( CheckoutIntentService::selection_metadata( $addon_lines ) as $sel_key => $sel_value ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $sel_key ); ?>" value="<?php echo esc_attr( $sel_value ); ?>">
							<?php endforeach; ?>
							<input type="hidden" name="addons_total" value="<?php echo esc_attr( round( $addons_total, 2 ) ); ?>">
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
														<i data-lucide="star" class="wpss-icon wpss-icon--sm wpss-co-stars" aria-hidden="true"></i>
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
															<i data-lucide="clock" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
															<?php
															/* translators: %d: number of days */
															printf( esc_html__( '%d-day delivery', 'wp-sell-services' ), (int) $delivery_days );
															?>
														</span>
													<?php endif; ?>

													<?php if ( $revisions > 0 ) : ?>
														<span class="wpss-co-meta__item">
															<i data-lucide="refresh-cw" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
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

							<?php
							// Billing details — OUR block, above the payment
							// block, identical on every gateway. Prefilled from
							// the buyer's profile and collapsed to a summary
							// when complete, so a returning customer only has
							// to enter card details.
							//
							// Deliberately not a gateway element: an address
							// rendered by Stripe would not exist for PayPal,
							// Razorpay or Woo buyers, and carries no company or
							// GST field for the invoice.
							//
							// A logged-out buyer is told what is about to happen
							// before the fields, not after. An account appearing
							// without warning is worse than a sign-up step.
							if ( ! is_user_logged_in() && wpss_checkout_creates_accounts() ) :
								?>
								<div class="wpss-notice wpss-notice--info wpss-co-account-note">
									<?php esc_html_e( 'We will create your account from the name and email below, so you can send the seller your requirements and follow your order. You will get an email to choose a password.', 'wp-sell-services' ); ?>
								</div>
								<?php
							endif;

							wpss_get_template_part( 'partials/billing', 'fields' );
							?>


							<?php
							wpss_get_template(
								'checkout/payment-methods.php',
								array(
									'wpss_gateways' => $enabled_gateways,
									'wpss_amount'   => $total,
									'wpss_currency' => $currency,
									'wpss_order_id' => $is_pay_order ? (int) $pay_order->id : 0,
								)
							);
							?>

						</div><!-- /left column -->

						<!-- RIGHT COLUMN: Order summary (sticky) -->
						<div class="wpss-sticky">
							<?php
							wpss_get_template(
								'checkout/summary.php',
								array(
									'wpss_title'    => $is_pay_order ? __( 'Order Payment', 'wp-sell-services' ) : __( 'Order Summary', 'wp-sell-services' ),
									'wpss_lines'    => $summary_lines,
									'wpss_total'    => $total,
									'wpss_currency' => $currency,
								)
							);
							?>
							<?php
							// Seller stats card.
							$vendor_id_for_stats = $is_pay_order ? $pay_order->vendor_id : ( $service->vendor_id ?? 0 );
							if ( $vendor_id_for_stats ) :
								global $wpdb;
								$orders_table      = $wpdb->prefix . 'wpss_orders';
								$reviews_table     = $wpdb->prefix . 'wpss_reviews';
								$vendor_orders     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table} WHERE vendor_id = %d AND status = 'completed'", $vendor_id_for_stats ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$vendor_member_obj = get_userdata( $vendor_id_for_stats );
								$vendor_joined     = $vendor_member_obj ? wp_date( 'M Y', strtotime( $vendor_member_obj->user_registered ) ) : '';

								// Get latest review for this vendor.
								$latest_review = $wpdb->get_row( $wpdb->prepare( "SELECT r.review as content, r.rating, COALESCE(NULLIF(u.display_name, ''), r.reviewer_name) as reviewer_display_name FROM {$reviews_table} r LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID WHERE r.vendor_id = %d AND r.status = 'approved' ORDER BY r.created_at DESC LIMIT 1", $vendor_id_for_stats ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								?>
							<div class="wpss-card" style="margin-top: var(--wpss-space-4);">
								<div class="wpss-card__header">
									<h3 class="wpss-card__title"><?php esc_html_e( 'About the Seller', 'wp-sell-services' ); ?></h3>
								</div>
								<div class="wpss-card__body">
									<div class="wpss-co-seller-stats">
										<div class="wpss-co-seller-stat">
											<span class="wpss-co-seller-stat__value"><?php echo esc_html( $vendor_orders ); ?></span>
											<span class="wpss-co-seller-stat__label"><?php esc_html_e( 'Orders Completed', 'wp-sell-services' ); ?></span>
										</div>
										<?php if ( $review_count > 0 ) : ?>
										<div class="wpss-co-seller-stat">
											<span class="wpss-co-seller-stat__value">
												<i data-lucide="star" class="wpss-icon wpss-star filled" aria-hidden="true"></i>
												<?php echo esc_html( number_format( $review_avg, 1 ) ); ?>
											</span>
											<span class="wpss-co-seller-stat__label">
												<?php
												/* translators: %d: number of reviews */
												printf( esc_html( _n( '%d Review', '%d Reviews', $review_count, 'wp-sell-services' ) ), absint( $review_count ) );
												?>
											</span>
										</div>
										<?php endif; ?>
										<?php if ( $vendor_joined ) : ?>
										<div class="wpss-co-seller-stat">
											<span class="wpss-co-seller-stat__value"><?php echo esc_html( $vendor_joined ); ?></span>
											<span class="wpss-co-seller-stat__label"><?php esc_html_e( 'Member Since', 'wp-sell-services' ); ?></span>
										</div>
										<?php endif; ?>
									</div>

									<?php if ( $latest_review ) : ?>
									<div class="wpss-co-testimonial">
										<p class="wpss-co-testimonial__text">&ldquo;<?php echo esc_html( wp_trim_words( $latest_review->content ?? $latest_review->review ?? '', 30 ) ); ?>&rdquo;</p>
										<span class="wpss-co-testimonial__author">&mdash; <?php echo esc_html( $latest_review->reviewer_display_name ?? __( 'Verified Buyer', 'wp-sell-services' ) ); ?></span>
									</div>
									<?php endif; ?>
								</div>
							</div>
							<?php endif; ?>


							<?php
							/*
							 * Reassurance belongs where the decision is made. This block used
							 * to render after the form closed, last on the page - 2063px down a
							 * 2302px checkout, below the payment method - so the only buyers who
							 * ever read what happens after paying were the ones who had already
							 * decided to pay. In the right column under the order summary it is
							 * visible without scrolling, while the payment form keeps the main
							 * column and nothing is pushed further down.
							 * See Basecamp 10289700826.
							 */
							?>
				<!-- What happens next -->
				<div class="wpss-co-steps">
					<div class="wpss-card">
						<div class="wpss-card__header">
							<h3 class="wpss-card__title"><?php esc_html_e( 'What happens next?', 'wp-sell-services' ); ?></h3>
						</div>
						<div class="wpss-card__body">
							<?php if ( $is_sub_order ) : ?>
								<div class="wpss-co-steps__track">
									<div class="wpss-co-step">
										<span class="wpss-co-step__dot">1</span>
										<span class="wpss-co-step__label"><?php esc_html_e( 'Pay', 'wp-sell-services' ); ?></span>
									</div>
									<div class="wpss-co-step">
										<span class="wpss-co-step__dot">2</span>
										<span class="wpss-co-step__label">
											<?php
											switch ( (string) ( $pay_order->platform ?? '' ) ) {
												case \WPSellServices\Services\TippingService::ORDER_TYPE:
													esc_html_e( 'Seller credited', 'wp-sell-services' );
													break;
												case \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE:
													esc_html_e( 'Work continues on extended scope', 'wp-sell-services' );
													break;
												case \WPSellServices\Services\MilestoneService::ORDER_TYPE:
													esc_html_e( 'Seller works on this phase', 'wp-sell-services' );
													break;
												default:
													esc_html_e( 'Seller credited', 'wp-sell-services' );
													break;
											}
											?>
										</span>
									</div>
								</div>
							<?php else : ?>
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
							<?php endif; ?>
						</div>
					</div>
				</div>

						</div><!-- /right column -->

					</div><!-- /layout -->
				</form>

				<?php
				/*
				 * Reassurance badges are the SITE OWNER'S words, edited under
				 * Settings > General > Checkout Reassurance. This is a public page a
				 * buyer reads while paying, so the plugin must not put claims in the
				 * owner's mouth - it previously printed "On-time Delivery / Or your
				 * money back", a refund promise nothing in the code honours, and
				 * "Unlimited revisions" on packages that include two.
				 *
				 * Where the owner has left a row blank we fall back to facts about
				 * the package being bought, so a badge can never contradict the order
				 * beside it.
				 */
				$badges = wpss_get_checkout_badges( is_array( $selected_package ) ? $selected_package : array() );
				?>
				<?php if ( ! empty( $badges ) ) : ?>
				<div class="wpss-co-guarantees-bar">
					<?php foreach ( $badges as $badge ) : ?>
						<div class="wpss-co-guarantee">
							<span aria-hidden="true"><?php echo esc_html( $badge['icon'] ); ?></span>
							<div>
								<strong><?php echo esc_html( $badge['title'] ); ?></strong>
								<span><?php echo esc_html( $badge['note'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>


			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a checkout form for multiple cart items.
	 *
	 * All items are paid in a single gateway transaction. After payment succeeds each
	 * cart item gets its own independent service order sharing the same transaction_id.
	 *
	 * @param array $cart_items Cart items from user meta.
	 * @return string HTML output.
	 */
	private function render_multi_checkout_form( array $cart_items ): string {
		$currency         = wpss_get_currency();
		$gateways         = wpss()->get_payment_gateways();
		$enabled_gateways = array_filter( $gateways, fn( $g ) => $g->is_enabled() );

		// Build enriched item list with service/package data and calculate totals.
		$enriched_items = array();
		$grand_total    = 0.0;

		// Tax settings.
		$tax_settings = get_option( 'wpss_tax', array() );
		$tax_enabled  = ! empty( $tax_settings['enable_tax'] );
		$tax_rate     = $tax_enabled ? (float) ( $tax_settings['tax_rate'] ?? 0 ) : 0;
		$tax_included = ! empty( $tax_settings['tax_included'] );
		$tax_label    = $tax_settings['tax_label'] ?? __( 'Tax', 'wp-sell-services' );
		$tax_amount   = 0.0;

		foreach ( $cart_items as $key => $item ) {
			$service_id = (int) ( $item['service_id'] ?? 0 );
			$package_id = (int) ( $item['package_id'] ?? 0 );
			$quantity   = max( 1, (int) ( $item['quantity'] ?? 1 ) );

			$service = wpss_get_service( $service_id );
			if ( ! $service ) {
				continue;
			}

			// The same pricer resolve_cart() charges through, so the page and the charge agree.
			$line = CheckoutIntentService::price_service_line( $service_id, $package_id, $quantity, $item['addons'] ?? array() );
			if ( is_wp_error( $line ) ) {
				continue;
			}

			$selected_package = $line['package'];
			$unit_price       = (float) ( $selected_package['price'] ?? 0 );
			$line_price       = (float) $line['subtotal'];
			$addon_lines      = $line['addons'];
			$addons_total     = (float) $line['addons_total'];
			$line_total       = (float) $line['total'];
			$tax_amount      += (float) $line['tax'];

			$vendor        = get_userdata( $service->vendor_id );
			$vendor_name   = $vendor ? $vendor->display_name : '';
			$vendor_avatar = get_avatar_url( $service->vendor_id, array( 'size' => 40 ) );

			$enriched_items[ $key ] = array(
				'cart_key'      => $key,
				'service_id'    => $service_id,
				'package_id'    => $package_id,
				'quantity'      => $quantity,
				'service'       => $service,
				'package'       => $selected_package,
				'unit_price'    => $unit_price,
				'line_price'    => $line_price,
				'addons'        => $addon_lines,
				'addons_total'  => $addons_total,
				'line_total'    => $line_total,
				'vendor_name'   => $vendor_name,
				'vendor_avatar' => $vendor_avatar,
			);

			$grand_total += $line_total;
		}

		if ( empty( $enriched_items ) ) {
			return '<p class="wpss-alert wpss-alert-error">' . esc_html__( 'Your cart contains no valid services.', 'wp-sell-services' ) . '</p>';
		}

		// Summary lines for templates/checkout/summary.php: one per cart item.
		$summary_lines = array();
		foreach ( $enriched_items as $ei ) {
			$summary_lines[] = array(
				'label'  => wp_trim_words( $ei['service']->title, 6 ),
				'amount' => $ei['line_total'],
			);
		}
		if ( $tax_amount > 0 ) {
			$summary_lines[] = $this->tax_summary_line( (string) $tax_label, $tax_rate, $tax_amount );
		}

		$this->enqueue_checkout_script();

		ob_start();
		?>
		<style>
			/* Inherit base checkout page styles. */
			body.wpss-checkout-page #secondary,
			body.wpss-checkout-page aside.widget-area,
			body.wpss-checkout-page .left-sidebar,
			body.wpss-checkout-page .right-sidebar { display: none !important; }
			body.wpss-checkout-page .site-content { display: block !important; }
			body.wpss-checkout-page .content-area,
			body.wpss-checkout-page #primary { width: 100% !important; max-width: 100% !important; float: none !important; flex: 1 !important; }

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
			.wpss-co-multi-item {
				display: flex;
				align-items: flex-start;
				gap: var(--wpss-space-4);
				padding: var(--wpss-space-4) 0;
				border-bottom: 1px solid var(--wpss-border-light);
			}
			.wpss-co-multi-item:last-child { border-bottom: none; }
			.wpss-co-multi-item__thumb {
				width: 80px; height: 56px; object-fit: cover;
				border-radius: var(--wpss-radius); flex-shrink: 0;
			}
			.wpss-co-multi-item__placeholder {
				width: 80px; height: 56px;
				background: var(--wpss-bg-subtle);
				border-radius: var(--wpss-radius); flex-shrink: 0;
				display: flex; align-items: center; justify-content: center;
				font-size: 24px;
			}
			.wpss-co-multi-item__details { flex: 1; min-width: 0; }
			.wpss-co-multi-item__title {
				font-size: var(--wpss-text-base); font-weight: 600;
				color: var(--wpss-text); margin: 0 0 var(--wpss-space-1); line-height: 1.3;
			}
			.wpss-co-multi-item__vendor {
				display: flex; align-items: center; gap: var(--wpss-space-2);
				font-size: var(--wpss-text-sm); color: var(--wpss-text-secondary);
				margin-bottom: var(--wpss-space-1);
			}
			.wpss-co-multi-item__vendor img {
				width: 20px; height: 20px;
				border-radius: var(--wpss-radius-full); object-fit: cover;
			}
			.wpss-co-multi-item__package {
				font-size: var(--wpss-text-sm); color: var(--wpss-text-muted);
			}
			.wpss-co-multi-item__price {
				font-size: var(--wpss-text-base); font-weight: 700;
				color: var(--wpss-text); white-space: nowrap; flex-shrink: 0;
			}
			.wpss-co-summary-line {
				display: flex; justify-content: space-between; align-items: center;
				padding: var(--wpss-space-2) 0;
				font-size: var(--wpss-text-base); color: var(--wpss-text-secondary);
			}
			.wpss-co-summary-line--tax { font-size: var(--wpss-text-sm); color: var(--wpss-text-muted); }
			.wpss-co-summary-total {
				display: flex; justify-content: space-between; align-items: center;
				padding: var(--wpss-space-4) 0 0;
				margin-top: var(--wpss-space-3);
				border-top: 2px solid var(--wpss-text);
				font-size: var(--wpss-text-xl); font-weight: 700; color: var(--wpss-text);
			}
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
			.wpss-co-trust {
				display: flex; flex-direction: column; gap: var(--wpss-space-3);
				padding: var(--wpss-space-4) 0;
			}
			.wpss-co-trust__item {
				display: flex; align-items: center; gap: var(--wpss-space-3);
				font-size: var(--wpss-text-sm); color: var(--wpss-text-muted);
			}
			.wpss-co-trust__icon { width: 20px; flex-shrink: 0; }
			@media (max-width: 768px) {
				.wpss-co-header { flex-direction: column; gap: var(--wpss-space-2); align-items: flex-start; }
			}
		</style>

		<div class="wpss-checkout-page">
			<!-- Header bar -->
			<div class="wpss-co-header">
				<a href="<?php echo esc_url( wpss_get_page_url( 'dashboard' ) ); ?>" class="wpss-co-header__back">
					<span aria-hidden="true">&larr;</span>
					<?php esc_html_e( 'Back to cart', 'wp-sell-services' ); ?>
				</a>
				<span class="wpss-co-header__secure">
					<i data-lucide="lock" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
					<?php esc_html_e( 'Secure Checkout', 'wp-sell-services' ); ?>
				</span>
			</div>

			<?php
			/*
			 * Deliberately NOT gated on wpss_checkout_creates_accounts().
			 *
			 * This is the multi-item cart checkout, and a logged-out visitor cannot
			 * have a cart: `_wpss_cart` is user meta and add-to-cart requires login.
			 * So there is nothing here for them to buy, and offering the
			 * account-at-checkout form would be a route to an empty purchase. The
			 * single-service checkout is the one a logged-out buyer can reach, and
			 * that is where the flow lives.
			 */
			?>
			<?php if ( ! is_user_logged_in() ) : ?>
				<div class="wpss-card wpss-co-login" style="text-align:center;padding:var(--wpss-space-10) var(--wpss-space-6);">
					<div class="wpss-empty__icon"><i data-lucide="user" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i></div>
					<h3 class="wpss-heading-3"><?php esc_html_e( 'Sign in to continue', 'wp-sell-services' ); ?></h3>
					<p class="wpss-caption" style="margin-top:var(--wpss-space-2);">
						<?php esc_html_e( 'Please log in or create an account to complete your purchase.', 'wp-sell-services' ); ?>
					</p>
					<div style="display:flex;gap:var(--wpss-space-3);justify-content:center;margin-top:var(--wpss-space-5);">
						<a href="<?php echo esc_url( wp_login_url( wpss_get_page_url( 'checkout' ) ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Log In', 'wp-sell-services' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="wpss-btn wpss-btn--outline">
							<?php esc_html_e( 'Register', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>

			<?php elseif ( empty( $enabled_gateways ) ) : ?>
				<div class="wpss-notice wpss-notice--error">
					<?php esc_html_e( 'No payment methods available. Please contact support.', 'wp-sell-services' ); ?>
				</div>

			<?php else : ?>
				<!-- Notice area -->
				<div id="wpss-multi-checkout-notice" class="wpss-notice wpss-notice--error" style="display:none;" role="alert"></div>

				<form method="post" class="wpss-checkout-form" id="wpss-multi-checkout-form" data-wpss-checkout-notice="wpss-multi-checkout-notice">
					<?php wp_nonce_field( 'wpss_checkout', 'wpss_checkout_nonce' ); ?>
					<input type="hidden" name="is_multi_checkout" value="1">
					<input type="hidden" name="amount" value="<?php echo esc_attr( round( $grand_total, 2 ) ); ?>">
					<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">

					<?php foreach ( $enriched_items as $key => $ei ) : ?>
						<input type="hidden" name="cart_items[<?php echo esc_attr( $key ); ?>][service_id]" value="<?php echo esc_attr( $ei['service_id'] ); ?>">
						<input type="hidden" name="cart_items[<?php echo esc_attr( $key ); ?>][package_id]" value="<?php echo esc_attr( $ei['package_id'] ); ?>">
						<input type="hidden" name="cart_items[<?php echo esc_attr( $key ); ?>][quantity]" value="<?php echo esc_attr( $ei['quantity'] ); ?>">
					<?php endforeach; ?>

					<!-- Two-column layout -->
					<div class="wpss-layout wpss-layout--sidebar-right">

						<!-- LEFT COLUMN: Items + payment methods -->
						<div class="wpss-stack wpss-stack--lg">

							<!-- Cart items card -->
							<div class="wpss-card">
								<div class="wpss-card__header">
									<h3 class="wpss-card__title">
										<?php
										printf(
											/* translators: %d: number of items */
											esc_html( _n( 'Your Order (%d Service)', 'Your Order (%d Services)', count( $enriched_items ), 'wp-sell-services' ) ),
											absint( count( $enriched_items ) )
										);
										?>
									</h3>
								</div>
								<div class="wpss-card__body">
									<?php foreach ( $enriched_items as $ei ) : ?>
										<div class="wpss-co-multi-item">
											<?php if ( $ei['service']->thumbnail_id ) : ?>
												<img class="wpss-co-multi-item__thumb"
													src="<?php echo esc_url( $ei['service']->get_thumbnail_url( 'thumbnail' ) ); ?>"
													alt="<?php echo esc_attr( $ei['service']->title ); ?>">
											<?php else : ?>
												<div class="wpss-co-multi-item__placeholder"><i data-lucide="package" class="wpss-icon" aria-hidden="true"></i></div>
											<?php endif; ?>

											<div class="wpss-co-multi-item__details">
												<h4 class="wpss-co-multi-item__title"><?php echo esc_html( $ei['service']->title ); ?></h4>
												<?php if ( $ei['vendor_name'] ) : ?>
													<div class="wpss-co-multi-item__vendor">
														<?php if ( $ei['vendor_avatar'] ) : ?>
															<img src="<?php echo esc_url( $ei['vendor_avatar'] ); ?>" alt="<?php echo esc_attr( $ei['vendor_name'] ); ?>">
														<?php endif; ?>
														<span><?php echo esc_html( $ei['vendor_name'] ); ?></span>
													</div>
												<?php endif; ?>
												<div class="wpss-co-multi-item__package">
													<?php echo esc_html( $ei['package']['name'] ?? '' ); ?>
													<?php if ( $ei['quantity'] > 1 ) : ?>
														<span>&times; <?php echo esc_html( $ei['quantity'] ); ?></span>
													<?php endif; ?>
													<?php if ( ! empty( $ei['addons'] ) ) : ?>
														<span> + <?php echo esc_html( count( $ei['addons'] ) ); ?> <?php esc_html_e( 'add-on(s)', 'wp-sell-services' ); ?></span>
													<?php endif; ?>
												</div>
											</div>

											<div class="wpss-co-multi-item__price">
												<?php echo esc_html( wpss_format_price( $ei['line_total'], $currency ) ); ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>

							<?php
							// Billing details — OUR block, above the payment
							// block, identical on every gateway. Prefilled from
							// the buyer's profile and collapsed to a summary
							// when complete, so a returning customer only has
							// to enter card details.
							//
							// Deliberately not a gateway element: an address
							// rendered by Stripe would not exist for PayPal,
							// Razorpay or Woo buyers, and carries no company or
							// GST field for the invoice.
							wpss_get_template_part( 'partials/billing', 'fields' );
							?>


							<?php
							wpss_get_template(
								'checkout/payment-methods.php',
								array(
									'wpss_gateways' => $enabled_gateways,
									'wpss_amount'   => $grand_total,
									'wpss_currency' => $currency,
									'wpss_order_id' => 0,
								)
							);
							?>

						</div><!-- /left column -->

						<!-- RIGHT COLUMN: Order summary -->
						<div class="wpss-sticky">
							<?php
							wpss_get_template(
								'checkout/summary.php',
								array(
									'wpss_lines'    => $summary_lines,
									'wpss_total'    => $grand_total,
									'wpss_currency' => $currency,
								)
							);
							?>
						</div><!-- /right column -->

					</div><!-- /layout -->
				</form>

			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build the tax line for templates/checkout/summary.php.
	 *
	 * @param string $label  Tax label.
	 * @param float  $rate   Tax rate in percent.
	 * @param float  $amount Tax amount.
	 * @return array
	 */
	private function tax_summary_line( string $label, float $rate, float $amount ): array {
		return array(
			'label'  => sprintf( '%s (%s%%)', $label, $rate ),
			'amount' => $amount,
			'type'   => 'tax',
		);
	}

	/**
	 * Enqueue the one checkout script shared by both checkout forms.
	 *
	 * Called from each renderer rather than on wp_enqueue_scripts, so it only
	 * loads where a checkout actually renders; enqueued mid-content it prints
	 * in the footer, after the form markup it binds to.
	 *
	 * @return void
	 */
	private function enqueue_checkout_script(): void {
		\WPSellServices\Assets\ScriptRegistry::enqueue( 'wpss-checkout', 'assets/js/checkout.js' );

		wp_localize_script(
			'wpss-checkout',
			'wpssCheckout',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				// Server decides, never the client: true only when the visitor
				// is logged out AND the owner enabled account-at-checkout.
				'needsAccount' => ! is_user_logged_in() && wpss_checkout_creates_accounts(),
				'i18n'         => array(
					'selectMethod'  => __( 'Please select a payment method.', 'wp-sell-services' ),
					'processing'    => __( 'Processing...', 'wp-sell-services' ),
					'paymentFailed' => __( 'Payment failed. Please try again.', 'wp-sell-services' ),
					'genericError'  => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'logIn'         => __( 'Log in', 'wp-sell-services' ),
					'accountFailed' => __( 'We could not create your account. Please check your details.', 'wp-sell-services' ),
				),
			)
		);
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

		/*
		 * The ONE cart reader, not a second copy of it.
		 *
		 * This read the meta row directly, so every availability rule added to
		 * wpss_get_user_cart() reached the cart screen and stopped there. A
		 * cart holding one live service and one the seller had paused rendered
		 * BOTH at checkout, priced, under a live Pay button - the cart page
		 * said one of them could not be bought and the very next screen
		 * charged for it. Same shape as the two cart bugs already fixed this
		 * cycle: the flow implemented twice, and the second copy never got the
		 * fix.
		 *
		 * wpss_get_user_cart() without keep_paused returns only what can
		 * actually be sold, which is the right answer for every caller here -
		 * the checkout form, the totals, the remaining-items notice, and order
		 * creation downstream. The buyer is not left wondering where the line
		 * went: render_checkout_shortcode() refuses to show a Pay button at
		 * all while an unsellable line is still in the cart, and says so.
		 */
		return wpss_get_user_cart( $user_id );
	}

	/**
	 * Lines in the cart that cannot be sold, with the reason for each.
	 *
	 * Read from the RAW meta rather than get_cart(), which by design no longer
	 * returns them - the point here is to notice that they exist.
	 *
	 * @return array<int, array{title: string, reason: string}> Keyed by service id.
	 */
	private function unsellable_cart_lines(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$raw   = get_user_meta( $user_id, self::CART_META_KEY, true );
		$stuck = array();

		foreach ( ( is_array( $raw ) ? $raw : array() ) as $item ) {
			$service_id = (int) ( $item['service_id'] ?? 0 );
			$reason     = wpss_service_unavailable_reason( $service_id );

			if ( '' !== $reason ) {
				$stuck[ $service_id ] = array(
					'title'  => (string) get_the_title( $service_id ),
					'reason' => $reason,
				);
			}
		}

		return $stuck;
	}

	/**
	 * Refuse checkout while the cart holds something that cannot be sold.
	 *
	 * Returns '' when checkout may proceed, or the notice to render instead of
	 * the form. A buyer who is told on the cart screen that a line is no longer
	 * available must not then be shown a Pay button that charges for it.
	 *
	 * @param int $url_service_id Service id taken from the URL, if any.
	 * @return string
	 */
	private function blocked_checkout_notice( int $url_service_id = 0 ): string {
		$stuck = $this->unsellable_cart_lines();

		// A direct link to a paused or deleted service skips the cart entirely.
		if ( $url_service_id > 0 && ! isset( $stuck[ $url_service_id ] ) ) {
			$reason = wpss_service_unavailable_reason( $url_service_id );

			if ( '' !== $reason ) {
				$stuck[ $url_service_id ] = array(
					'title'  => (string) get_the_title( $url_service_id ),
					'reason' => $reason,
				);
			}
		}

		if ( ! $stuck ) {
			return '';
		}

		ob_start();
		?>
		<div class="wpss-checkout-blocked wpss-notice wpss-notice--warning">
			<p>
				<?php esc_html_e( 'Checkout is on hold because something in your cart is no longer for sale.', 'wp-sell-services' ); ?>
			</p>
			<ul class="wpss-checkout-blocked__list">
				<?php foreach ( $stuck as $line ) : ?>
					<li>
						<?php
						$title = '' !== $line['title'] ? $line['title'] : __( 'A service that has since been removed', 'wp-sell-services' );
						printf(
							/* translators: 1: service name, 2: the reason it cannot be bought. */
							esc_html__( '%1$s - %2$s', 'wp-sell-services' ),
							esc_html( $title ),
							esc_html( $line['reason'] )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="wpss-btn wpss-btn--primary" href="<?php echo esc_url( wpss_get_cart_url() ); ?>">
					<?php esc_html_e( 'Go to your cart to remove it', 'wp-sell-services' ); ?>
				</a>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Clear entire cart from user meta.
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

	/**
	 * Remove a specific service from the cart, keeping other items.
	 *
	 * If the cart becomes empty after removal, deletes the meta entirely.
	 * Falls back to clearing the entire cart if the service is not found.
	 *
	 * @param int $service_id Service ID to remove.
	 * @return void
	 */
	private function remove_from_cart( int $service_id ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$cart    = $this->get_cart();
		$updated = array();

		foreach ( $cart as $key => $item ) {
			if ( (int) ( $item['service_id'] ?? 0 ) !== $service_id ) {
				$updated[ $key ] = $item;
			}
		}

		if ( empty( $updated ) ) {
			delete_user_meta( $user_id, self::CART_META_KEY );
		} else {
			update_user_meta( $user_id, self::CART_META_KEY, $updated );
		}
	}
}
