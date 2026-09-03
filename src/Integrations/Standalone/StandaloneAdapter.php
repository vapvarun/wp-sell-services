<?php
/**
 * Standalone Adapter
 *
 * @package WPSellServices\Integrations\Standalone
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Standalone;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\EcommerceAdapterInterface;
use WPSellServices\Integrations\Contracts\ProductProviderInterface;
use WPSellServices\Integrations\Contracts\CheckoutProviderInterface;
use WPSellServices\Integrations\Contracts\AccountProviderInterface;

/**
 * Standalone mode adapter.
 *
 * Allows selling services without any e-commerce platform dependency.
 *
 * @since 1.0.0
 */
class StandaloneAdapter implements EcommerceAdapterInterface {

	/**
	 * Default checkout URL slug.
	 *
	 * @var string
	 */
	private const DEFAULT_CHECKOUT_SLUG = 'service-checkout';

	/**
	 * Get the checkout slug. Filterable so site owners can customize it.
	 *
	 * @return string
	 */
	public static function get_checkout_slug(): string {
		/**
		 * Filter the checkout URL slug.
		 *
		 * @since 1.2.0
		 * @param string $slug Default checkout slug.
		 */
		return apply_filters( 'wpss_checkout_slug', self::DEFAULT_CHECKOUT_SLUG );
	}

	/*
	 * There is deliberately NO $order_provider property here. Orders have one
	 * authority regardless of which rail took the money, so callers go through
	 * wpss_get_order_provider(), which always returns StandaloneOrderProvider -
	 * see the reasoning on that function. This class used to construct one in
	 * init() and never read it again.
	 */

	/**
	 * Product provider instance.
	 *
	 * @var StandaloneProductProvider|null
	 */
	private ?StandaloneProductProvider $product_provider = null;

	/**
	 * Checkout provider instance.
	 *
	 * @var StandaloneCheckoutProvider|null
	 */
	private ?StandaloneCheckoutProvider $checkout_provider = null;

	/**
	 * Account provider instance.
	 *
	 * @var StandaloneAccountProvider|null
	 */
	private ?StandaloneAccountProvider $account_provider = null;

	/**
	 * Get the unique adapter identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'standalone';
	}

	/**
	 * Get the adapter display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Standalone', 'wp-sell-services' );
	}

	/**
	 * Standalone is always available.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return true;
	}

	/**
	 * Check if the adapter supports a specific feature.
	 *
	 * @param string $feature Feature name.
	 * @return bool
	 */
	public function supports_feature( string $feature ): bool {
		$supported = [
			'manual_orders'     => true,
			'subscriptions'     => false,
			'variable_products' => true,
			'multi_vendor'      => true,
		];

		return $supported[ $feature ] ?? false;
	}

	/**
	 * Initialize the standalone adapter.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->product_provider  = new StandaloneProductProvider();
		$this->checkout_provider = new StandaloneCheckoutProvider();
		$this->account_provider  = new StandaloneAccountProvider();

		// Register shortcodes.
		add_shortcode( 'wpss_checkout', [ $this->checkout_provider, 'render_checkout_shortcode' ] );
		add_shortcode( 'wpss_account', [ $this->account_provider, 'render_account_shortcode' ] );

		// Register rewrite rules.
		// If init has already fired, register immediately. Otherwise hook to init.
		if ( did_action( 'init' ) ) {
			$this->register_rewrite_rules();
		} else {
			add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		}
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_template_redirect' ] );

		if ( self::should_use_marketplace_cart_link() ) {
			add_filter( 'woocommerce_get_cart_url', [ $this, 'filter_cart_url' ], 20 );
		}

		/**
		 * Fires after standalone adapter is initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wpss_standalone_adapter_init', $this );
	}

	/**
	 * Whether the theme's cart link should point at the marketplace cart.
	 *
	 * Two carts exist on purpose: /service-cart/ was created so the marketplace
	 * does not fight WooCommerce for /cart/. What was wrong was which one the
	 * theme's cart icon pointed at. With the standalone rail taking payment, a
	 * buyer with a service in their cart clicked that icon and was told their
	 * cart was empty, because the icon is hardcoded to Woo's page.
	 *
	 * This used to be a checkbox that was off by default, so the common case
	 * shipped broken and the owner had to know to go and find the switch. It is
	 * derived now, and the checkbox overrides the derivation either way:
	 *
	 *  - Woo owns checkout      -> leave Woo's cart alone. It holds the order.
	 *  - Standalone, no Woo products -> point at the marketplace cart. Woo is
	 *    installed for something else and its cart is always empty.
	 *  - Standalone, Woo products exist -> leave it alone. Two real shops; only
	 *    the owner knows which cart the header should serve.
	 *
	 * The last case is why this is a link filter and never a redirect on
	 * /cart/ - taking over another plugin's URL is exactly the collision the
	 * separate page exists to avoid.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	public static function should_use_marketplace_cart_link(): bool {
		$general = get_option( 'wpss_general', array() );

		// An explicit choice always wins, in both directions.
		if ( array_key_exists( 'use_marketplace_cart_link', $general ) && '' !== $general['use_marketplace_cart_link'] ) {
			return ! empty( $general['use_marketplace_cart_link'] );
		}

		if ( ! function_exists( 'wpss_uses_standalone_payments' ) || ! wpss_uses_standalone_payments() ) {
			return false;
		}

		if ( ! function_exists( 'wp_count_posts' ) || ! post_type_exists( 'product' ) ) {
			return false;
		}

		$products = wp_count_posts( 'product' );

		return empty( $products->publish );
	}

	/**
	 * Register rewrite rules for standalone mode.
	 *
	 * @return void
	 */
	public function register_rewrite_rules(): void {
		$checkout_slug = self::get_checkout_slug();
		add_rewrite_rule(
			'^' . $checkout_slug . '/([0-9]+)/?$',
			'index.php?wpss_checkout=1&wpss_service_id=$matches[1]',
			'top'
		);

		// Pretty pay-one-order: /{checkout}/pay/{id}/.
		// Prefer the mapped checkout page path so a renamed slug still matches.
		$checkout_id   = function_exists( 'wpss_get_page_id' ) ? (int) wpss_get_page_id( 'checkout' ) : 0;
		$checkout_path = '';
		if ( $checkout_id ) {
			$uri = get_page_uri( $checkout_id );
			if ( is_string( $uri ) && '' !== $uri ) {
				$checkout_path = trim( $uri, '/' );
			}
		}
		if ( '' === $checkout_path ) {
			$checkout_path = $checkout_slug;
		}

		$segments  = array_map( 'preg_quote', explode( '/', $checkout_path ) );
		$path_re   = implode( '/', $segments );
		$pay_regex = '^' . $path_re . '/pay/([0-9]+)/?$';
		$pay_query = $checkout_id
			? 'index.php?page_id=' . $checkout_id . '&wpss_pay_order=$matches[1]'
			: 'index.php?wpss_checkout=1&wpss_pay_order=$matches[1]';

		add_rewrite_rule( $pay_regex, $pay_query, 'top' );

		// Note: /service-order/{id}/ is handled by Plugin::register_rewrite_rules()
		// via the wpss_service_order query var → TemplateLoader → order-view.php.

		// Account pages.
		add_rewrite_rule(
			'^account/([^/]+)/?$',
			'index.php?wpss_account=1&wpss_account_page=$matches[1]',
			'top'
		);

		// Payment callback.
		add_rewrite_rule(
			'^wpss-payment/([^/]+)/callback/?$',
			'index.php?wpss_payment_callback=1&wpss_gateway=$matches[1]',
			'top'
		);

		// Self-heal when the pay rule is missing from the live option.
		$live_rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $live_rules ) || ! isset( $live_rules[ $pay_regex ] ) ) {
			add_action(
				'wp_loaded',
				static function (): void {
					flush_rewrite_rules( false );
				}
			);
		}
	}

	/**
	 * Point the site's cart link at the marketplace cart.
	 *
	 * Opt-in (Settings -> General -> "Use the marketplace cart for the site's
	 * cart link"), so an owner who really does sell WooCommerce products keeps
	 * Woo's cart untouched. Returns the original URL when no marketplace cart
	 * page is mapped, rather than handing back an empty href.
	 *
	 * @since 1.6.0
	 *
	 * @param string $url Cart URL supplied by WooCommerce.
	 * @return string
	 */
	public function filter_cart_url( string $url ): string {
		$cart_url = wpss_get_page_url( 'cart' );

		return $cart_url ? $cart_url : $url;
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'wpss_checkout';
		$vars[] = 'wpss_service_id';
		$vars[] = 'wpss_account';
		$vars[] = 'wpss_account_page';
		$vars[] = 'wpss_payment_callback';
		$vars[] = 'wpss_gateway';
		$vars[] = 'wpss_pay_order';

		return $vars;
	}

	/**
	 * Handle template redirect.
	 *
	 * @return void
	 */
	public function handle_template_redirect(): void {
		// Handle checkout page.
		if ( get_query_var( 'wpss_checkout' ) ) {
			$service_id = (int) get_query_var( 'wpss_service_id' );
			$package_id = isset( $_GET['package_id'] ) ? (int) $_GET['package_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $service_id > 0 ) {
				$this->render_checkout_page( $service_id, $package_id );
				exit;
			}
		}

		// Handle payment callbacks.
		if ( get_query_var( 'wpss_payment_callback' ) ) {
			$gateway_id = sanitize_text_field( get_query_var( 'wpss_gateway' ) );

			/**
			 * Fires when a payment callback is received.
			 *
			 * @param string $gateway_id Gateway ID.
			 */
			do_action( 'wpss_payment_callback', $gateway_id );
			do_action( "wpss_payment_callback_{$gateway_id}" );
			exit;
		}
	}

	/**
	 * Render the standalone checkout page.
	 *
	 * @param int $service_id Service ID.
	 * @param int $package_id Package ID.
	 * @return void
	 */
	private function render_checkout_page( int $service_id, int $package_id ): void {
		// Set query vars for the shortcode to read.
		set_query_var( 'wpss_service_id', $service_id );

		// Named after the mapped checkout page, which is what the pay-one-order
		// route renders through - the tab here used to read the bare site name.
		$page_id = function_exists( 'wpss_get_page_id' ) ? wpss_get_page_id( 'checkout' ) : 0;
		$title   = $page_id ? (string) get_the_title( $page_id ) : '';

		$this->render_standalone_page(
			'' !== $title ? $title : __( 'Checkout', 'wp-sell-services' ),
			$this->checkout_provider->render_checkout_shortcode( [] )
		);
	}

	/**
	 * Render a standalone page with theme wrapper.
	 *
	 * @param string $title   Page title.
	 * @param string $content Page content.
	 * @return void
	 */
	private function render_standalone_page( string $title, string $content ): void {
		// Enqueue frontend assets for proper styling and functionality.
		wpss_enqueue_frontend_assets();

		// This route matches no post, so the document title fell back to the
		// bare site name. Name the tab after the page.
		add_filter(
			'document_title_parts',
			static function ( array $parts ) use ( $title ): array {
				$parts['title'] = $title;
				return $parts;
			}
		);

		// Use get_header/get_footer for theme integration.
		get_header();
		?>
		<main id="primary" class="site-main">
			<?php
			/*
			 * OUR container, not the theme's (Basecamp 10208392848).
			 *
			 * get_header() prints whatever the theme puts in header.php and
			 * nothing more. Some themes happen to open their content wrapper
			 * there - BuddyX free does, which is why this route looked fine on
			 * it - but plenty open it inside the page template instead, via a
			 * hook this route never reaches. Those get a checkout spanning the
			 * whole viewport: reproduced at 1790px on BuddyX Pro by QA and on
			 * stock Twenty Twenty-Four here, so it is not one theme's quirk.
			 *
			 * Firing a theme's own before-content hook would fix the two themes
			 * we happened to test and leave every other one broken. Constraining
			 * it ourselves works everywhere, and .wpss-container is the width
			 * the rest of the plugin already uses - one definition, not a second
			 * one invented for this route.
			 *
			 * Where a theme DOES provide a container, ours stands down rather
			 * than nesting inside it and adding a second gutter - the theme's
			 * width wins on its own site. CSS cannot ask whether an ancestor is
			 * already constraining, so WPSS.relaxRedundantContainer() walks the
			 * real ancestors on load, the same approach enableSticky() takes.
			 * data-wpss-auto-container is what marks this one as ours to relax.
			 */
			?>
			<div class="wpss-container" data-wpss-auto-container>
				<article class="wpss-standalone-page">
					<header class="entry-header">
						<h1 class="entry-title"><?php echo esc_html( $title ); ?></h1>
					</header>
					<div class="entry-content">
						<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped in provider. ?>
					</div>
				</article>
			</div>
		</main>
		<?php
		get_footer();
	}

	/**
	 * Get the product provider.
	 *
	 * @return ProductProviderInterface
	 */
	public function get_product_provider(): ProductProviderInterface {
		if ( null === $this->product_provider ) {
			$this->product_provider = new StandaloneProductProvider();
		}
		return $this->product_provider;
	}

	/**
	 * Get the checkout provider.
	 *
	 * @return CheckoutProviderInterface
	 */
	public function get_checkout_provider(): CheckoutProviderInterface {
		if ( null === $this->checkout_provider ) {
			$this->checkout_provider = new StandaloneCheckoutProvider();
		}
		return $this->checkout_provider;
	}

	/**
	 * Get the account provider.
	 *
	 * @return AccountProviderInterface
	 */
	public function get_account_provider(): AccountProviderInterface {
		if ( null === $this->account_provider ) {
			$this->account_provider = new StandaloneAccountProvider();
		}
		return $this->account_provider;
	}
}
