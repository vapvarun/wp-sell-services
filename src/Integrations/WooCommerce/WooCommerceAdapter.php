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
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Services\DisputeService;

/**
 * WooCommerce integration adapter.
 *
 * Provides integration with WooCommerce for services, orders, and checkout.
 *
 * @since 1.0.0
 */
class WooCommerceAdapter implements EcommerceAdapterInterface {

	/**
	 * Flag to prevent infinite loop when syncing WC order status.
	 *
	 * When a WC status change triggers a service status change, which would
	 * trigger a WC status change again, this flag breaks the cycle.
	 *
	 * @var bool
	 */
	private static bool $syncing_wc_status = false;

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

		// Order status changes - create orders early so requirements redirect works.
		add_action( 'woocommerce_order_status_pending', array( $this, 'handle_order_created' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'handle_order_created' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_paid' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_order_cancelled' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_order_refunded' ) );

		// Reverse sync: Service order status -> WooCommerce order status.
		add_action( 'wpss_order_status_changed', array( $this, 'sync_wc_order_status' ), 10, 3 );

		// Hook checkout order created to create WPSS order early.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'handle_checkout_order_created' ) );

		// Payment complete hook.
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ) );

		// Process refunds when disputes are resolved.
		add_action( 'wpss_dispute_resolved', array( $this, 'handle_dispute_refund' ), 10, 4 );

		// Enforce quantity=1 for service products.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'enforce_service_quantity' ), 10, 3 );
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'hide_service_quantity_input' ), 10, 2 );
	}

	/**
	 * Enforce quantity=1 for service products during add-to-cart.
	 *
	 * @param bool $passed     Validation result.
	 * @param int  $product_id Product ID being added.
	 * @param int  $quantity   Requested quantity.
	 * @return bool
	 */
	public function enforce_service_quantity( bool $passed, int $product_id, int $quantity ): bool {
		if ( ! $passed ) {
			return $passed;
		}

		if ( $this->product_provider && $this->product_provider->is_service_product( $product_id ) && $quantity > 1 ) {
			wc_add_notice(
				__( 'Service products can only be purchased one at a time.', 'wp-sell-services' ),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Set quantity input to 1 and hide quantity selector for service products.
	 *
	 * @param array<string, mixed> $args    Quantity input arguments.
	 * @param \WC_Product          $product Product object.
	 * @return array<string, mixed> Modified arguments.
	 */
	public function hide_service_quantity_input( array $args, $product ): array {
		if ( $product && $this->product_provider && $this->product_provider->is_service_product( $product->get_id() ) ) {
			$args['min_value']   = 1;
			$args['max_value']   = 1;
			$args['input_value'] = 1;
		}

		return $args;
	}

	/**
	 * Handle order created on checkout - creates WPSS order early.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public function handle_checkout_order_created( $order ): void {
		if ( ! $order ) {
			return;
		}

		$this->handle_order_created( $order->get_id() );
	}

	/**
	 * Handle order created (pending/on-hold status).
	 *
	 * Creates WPSS order early so the requirements redirect works.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_created( int $order_id ): void {
		// Check if service orders already exist.
		$service_orders = $this->order_provider->get_all_by_platform_order( $order_id );

		if ( empty( $service_orders ) ) {
			// Create service orders from WC order (status will be pending_payment).
			$this->order_provider->create_from_platform_order( $order_id );
		}
	}

	/**
	 * Handle order paid status.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_paid( int $order_id ): void {
		// Ensure service orders are created.
		$service_orders = $this->order_provider->get_all_by_platform_order( $order_id );

		if ( empty( $service_orders ) ) {
			$this->order_provider->create_from_platform_order( $order_id );
			$service_orders = $this->order_provider->get_all_by_platform_order( $order_id );
		}

		// Set flag to prevent reverse sync back to WC.
		self::$syncing_wc_status = true;

		foreach ( $service_orders as $service_order ) {
			$this->order_provider->sync_status( $service_order );
		}

		self::$syncing_wc_status = false;
	}

	/**
	 * Handle order cancelled.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_cancelled( int $order_id ): void {
		$service_orders = $this->order_provider->get_all_by_platform_order( $order_id );

		// Set flag to prevent reverse sync back to WC.
		self::$syncing_wc_status = true;

		foreach ( $service_orders as $service_order ) {
			$this->order_provider->sync_status( $service_order );
		}

		self::$syncing_wc_status = false;
	}

	/**
	 * Handle order refunded.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_refunded( int $order_id ): void {
		$service_orders = $this->order_provider->get_all_by_platform_order( $order_id );

		// Set flag to prevent reverse sync back to WC.
		self::$syncing_wc_status = true;

		foreach ( $service_orders as $service_order ) {
			$this->order_provider->sync_status( $service_order );
		}

		self::$syncing_wc_status = false;
	}

	/**
	 * Sync WooCommerce order status when service order status changes.
	 *
	 * This is the reverse sync: when a service order status changes (e.g., via
	 * the frontend dashboard), update the corresponding WooCommerce order status.
	 *
	 * @param int    $order_id   Service order ID.
	 * @param string $new_status New service order status.
	 * @param string $old_status Old service order status.
	 * @return void
	 */
	public function sync_wc_order_status( int $order_id, string $new_status, string $old_status ): void {
		// Prevent infinite loop: WC->Service->WC.
		if ( self::$syncing_wc_status ) {
			return;
		}

		$service_order = ServiceOrder::find( $order_id );

		if ( ! $service_order ) {
			return;
		}

		// Only sync WooCommerce platform orders.
		if ( 'woocommerce' !== $service_order->platform || ! $service_order->platform_order_id ) {
			return;
		}

		// Map service order statuses to WooCommerce statuses.
		$status_map = array(
			ServiceOrder::STATUS_COMPLETED => 'completed',
			ServiceOrder::STATUS_CANCELLED => 'cancelled',
		);

		/**
		 * Filter the service-to-WC status mapping for reverse sync.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $status_map Service status => WC status mapping.
		 * @param string $new_status The new service order status.
		 * @param string $old_status The old service order status.
		 */
		$status_map = apply_filters( 'wpss_service_to_wc_status_map', $status_map, $new_status, $old_status );

		if ( ! isset( $status_map[ $new_status ] ) ) {
			return;
		}

		$wc_order = wc_get_order( $service_order->platform_order_id );

		if ( ! $wc_order ) {
			return;
		}

		$target_wc_status = $status_map[ $new_status ];

		// Skip if WC order already has the target status.
		if ( $wc_order->get_status() === $target_wc_status ) {
			return;
		}

		// Set flag to prevent the WC status change from syncing back to service order.
		self::$syncing_wc_status = true;

		$wc_order->update_status(
			$target_wc_status,
			__( 'Status synced from service order.', 'wp-sell-services' )
		);

		self::$syncing_wc_status = false;
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
	 * Handle dispute refund - process WooCommerce refund when dispute is resolved.
	 *
	 * @param int    $dispute_id    Dispute ID.
	 * @param string $resolution    Resolution type.
	 * @param object $dispute       Dispute object.
	 * @param float  $refund_amount Refund amount.
	 * @return void
	 */
	public function handle_dispute_refund( int $dispute_id, string $resolution, object $dispute, float $refund_amount ): void {
		// Only process refunds for refund resolutions.
		$refund_resolutions = array(
			DisputeService::RESOLUTION_REFUND,
			DisputeService::RESOLUTION_PARTIAL_REFUND,
			DisputeService::RESOLUTION_FAVOR_BUYER,
		);

		if ( ! in_array( $resolution, $refund_resolutions, true ) ) {
			return;
		}

		// Get the service order.
		$order_repo = new OrderRepository();
		$order_data = $order_repo->find( (int) $dispute->order_id );

		if ( ! $order_data ) {
			return;
		}

		// Convert to ServiceOrder model.
		$service_order = ServiceOrder::from_db( $order_data );

		// Only process WooCommerce orders.
		if ( 'woocommerce' !== $service_order->platform || ! $service_order->platform_order_id ) {
			return;
		}

		// Determine refund amount.
		$amount = $refund_amount;

		// For full refund, use order total if no specific amount given.
		if ( DisputeService::RESOLUTION_REFUND === $resolution && $amount <= 0 ) {
			$amount = $service_order->total;
		}

		// For favor buyer without amount, use full total.
		if ( DisputeService::RESOLUTION_FAVOR_BUYER === $resolution && $amount <= 0 ) {
			$amount = $service_order->total;
		}

		if ( $amount <= 0 ) {
			return;
		}

		// Build refund reason.
		$reason = sprintf(
			/* translators: %d: dispute ID */
			__( 'Dispute #%d resolution refund', 'wp-sell-services' ),
			$dispute_id
		);

		// Process the refund through WooCommerce.
		$this->get_order_provider()->process_refund( $service_order, $amount, $reason );
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
