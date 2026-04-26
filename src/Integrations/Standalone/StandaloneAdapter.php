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
use WPSellServices\Integrations\Contracts\OrderProviderInterface;
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

	/**
	 * Order provider instance.
	 *
	 * @var StandaloneOrderProvider|null
	 */
	private ?StandaloneOrderProvider $order_provider = null;

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
		$this->order_provider    = new StandaloneOrderProvider();
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

		/**
		 * Fires after standalone adapter is initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wpss_standalone_adapter_init', $this );
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

		// Render the page.
		$this->render_standalone_page(
			__( 'Checkout', 'wp-sell-services' ),
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

		// Use get_header/get_footer for theme integration.
		get_header();
		?>
		<main id="primary" class="site-main">
			<article class="wpss-standalone-page">
				<header class="entry-header">
					<h1 class="entry-title"><?php echo esc_html( $title ); ?></h1>
				</header>
				<div class="entry-content">
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped in provider. ?>
				</div>
			</article>
		</main>
		<?php
		get_footer();
	}

	/**
	 * Get the order provider.
	 *
	 * @return OrderProviderInterface
	 */
	public function get_order_provider(): OrderProviderInterface {
		if ( null === $this->order_provider ) {
			$this->order_provider = new StandaloneOrderProvider();
		}
		return $this->order_provider;
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
