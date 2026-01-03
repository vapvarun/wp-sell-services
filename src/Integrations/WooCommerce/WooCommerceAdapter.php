<?php
/**
 * WooCommerce Adapter
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

use WPSellServices\Integrations\Contracts\EcommerceAdapterInterface;
use WPSellServices\Integrations\Contracts\OrderProviderInterface;
use WPSellServices\Integrations\Contracts\ProductProviderInterface;
use WPSellServices\Integrations\Contracts\CheckoutProviderInterface;
use WPSellServices\Integrations\Contracts\AccountProviderInterface;

/**
 * WooCommerce integration adapter.
 *
 * Provides integration with WooCommerce for services, orders, and checkout.
 *
 * @since 1.0.0
 */
class WooCommerceAdapter implements EcommerceAdapterInterface {

	/**
	 * Supported features.
	 *
	 * @var array<string>
	 */
	private const SUPPORTED_FEATURES = array(
		'subscriptions',
		'variable_products',
		'cart',
		'checkout',
		'account',
		'emails',
		'hpos',
	);

	/**
	 * Order provider instance.
	 *
	 * @var WCOrderProvider|null
	 */
	private ?WCOrderProvider $order_provider = null;

	/**
	 * Product provider instance.
	 *
	 * @var WCProductProvider|null
	 */
	private ?WCProductProvider $product_provider = null;

	/**
	 * Checkout provider instance.
	 *
	 * @var WCCheckoutProvider|null
	 */
	private ?WCCheckoutProvider $checkout_provider = null;

	/**
	 * Account provider instance.
	 *
	 * @var WCAccountProvider|null
	 */
	private ?WCAccountProvider $account_provider = null;

	/**
	 * Email provider instance.
	 *
	 * @var WCEmailProvider|null
	 */
	private ?WCEmailProvider $email_provider = null;

	/**
	 * Service carrier instance.
	 *
	 * @var WCServiceCarrier|null
	 */
	private ?WCServiceCarrier $service_carrier = null;

	/**
	 * Get the unique adapter identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'woocommerce';
	}

	/**
	 * Get the adapter display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'WooCommerce', 'wp-sell-services' );
	}

	/**
	 * Check if WooCommerce is installed, active, and enabled in settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		// Check if WooCommerce is installed.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// Check if WooCommerce integration is enabled in settings.
		$general_settings = get_option( 'wpss_general', array() );

		// Default to enabled if setting not yet set (for backward compatibility).
		return $general_settings['enable_woocommerce'] ?? true;
	}

	/**
	 * Initialize the WooCommerce adapter.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		// Initialize providers.
		$this->order_provider    = new WCOrderProvider();
		$this->product_provider  = new WCProductProvider();
		$this->checkout_provider = new WCCheckoutProvider();
		$this->account_provider  = new WCAccountProvider();
		$this->email_provider    = new WCEmailProvider();
		$this->service_carrier   = new WCServiceCarrier();

		// Register WooCommerce-specific hooks.
		$this->register_hooks();

		/**
		 * Fires after WooCommerce adapter is initialized.
		 *
		 * @since 1.0.0
		 *
		 * @param WooCommerceAdapter $adapter The adapter instance.
		 */
		do_action( 'wpss_woocommerce_adapter_init', $this );
	}

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Initialize provider-specific hooks.
		$this->product_provider->init();
		$this->checkout_provider->init();
		$this->account_provider->init();
		$this->email_provider->init();
		$this->service_carrier->init();

		// Register adapter filter for global access.
		add_filter( 'wpss_ecommerce_adapter', array( $this, 'provide_adapter' ), 10, 2 );

		// Order status changes.
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_paid' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_order_cancelled' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_order_refunded' ) );

		// Payment complete hook.
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ) );
	}

	/**
	 * Handle order paid status.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_paid( int $order_id ): void {
		// Check if service order already exists.
		$service_order = $this->order_provider->get_by_platform_order( $order_id );

		if ( ! $service_order ) {
			// Create service order from WC order.
			$service_order = $this->order_provider->create_from_platform_order( $order_id );
		}

		if ( $service_order ) {
			$this->order_provider->sync_status( $service_order );
		}
	}

	/**
	 * Handle order cancelled.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_cancelled( int $order_id ): void {
		$service_order = $this->order_provider->get_by_platform_order( $order_id );

		if ( $service_order ) {
			$this->order_provider->sync_status( $service_order );
		}
	}

	/**
	 * Handle order refunded.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_refunded( int $order_id ): void {
		$service_order = $this->order_provider->get_by_platform_order( $order_id );

		if ( $service_order ) {
			$this->order_provider->sync_status( $service_order );
		}
	}

	/**
	 * Handle payment complete.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_payment_complete( int $order_id ): void {
		$this->handle_order_paid( $order_id );
	}

	/**
	 * Check if the adapter supports a specific feature.
	 *
	 * @param string $feature Feature name.
	 * @return bool
	 */
	public function supports_feature( string $feature ): bool {
		return in_array( $feature, self::SUPPORTED_FEATURES, true );
	}

	/**
	 * Get the order provider.
	 *
	 * @return OrderProviderInterface
	 */
	public function get_order_provider(): OrderProviderInterface {
		if ( null === $this->order_provider ) {
			$this->order_provider = new WCOrderProvider();
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
			$this->product_provider = new WCProductProvider();
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
			$this->checkout_provider = new WCCheckoutProvider();
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
			$this->account_provider = new WCAccountProvider();
		}
		return $this->account_provider;
	}

	/**
	 * Get the email provider.
	 *
	 * @return WCEmailProvider
	 */
	public function get_email_provider(): WCEmailProvider {
		if ( null === $this->email_provider ) {
			$this->email_provider = new WCEmailProvider();
		}
		return $this->email_provider;
	}

	/**
	 * Get the service carrier.
	 *
	 * @return WCServiceCarrier
	 */
	public function get_service_carrier(): WCServiceCarrier {
		if ( null === $this->service_carrier ) {
			$this->service_carrier = new WCServiceCarrier();
		}
		return $this->service_carrier;
	}

	/**
	 * Provide this adapter via filter.
	 *
	 * @param object|null $adapter    Existing adapter.
	 * @param string|null $adapter_id Requested adapter ID.
	 * @return object|null This adapter if requested or no other adapter.
	 */
	public function provide_adapter( ?object $adapter, ?string $adapter_id ): ?object {
		// Return this adapter if specifically requested or if no adapter set.
		if ( null === $adapter_id || 'woocommerce' === $adapter_id ) {
			return $this;
		}

		return $adapter;
	}
}
