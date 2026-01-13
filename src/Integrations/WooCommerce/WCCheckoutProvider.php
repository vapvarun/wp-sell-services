<?php
/**
 * WooCommerce Checkout Provider
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

use WPSellServices\Integrations\Contracts\CheckoutProviderInterface;

/**
 * Provides checkout functionality through WooCommerce.
 *
 * @since 1.0.0
 */
class WCCheckoutProvider implements CheckoutProviderInterface {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart_hook' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		// Removed quantity restrictions - services can be purchased multiple times like on Fiverr.
		// Previously: add_filter( 'woocommerce_quantity_input_args', ... ) and
		// add_filter( 'woocommerce_is_sold_individually', ... ) forced quantity=1.
	}

	/**
	 * Add service-specific data to cart item.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Product ID being added.
	 * @param int   $variation_id   Variation ID (if applicable).
	 * @return array
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		if ( ! $this->is_service_product( $product_id ) ) {
			return $cart_item_data;
		}

		// Check if values already exist in cart_item_data (e.g., from AJAX handler).
		// Fall back to $_REQUEST for front-end form submissions.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $cart_item_data['wpss_package_id'] ) ) {
			$package_id = isset( $_REQUEST['wpss_package_id'] ) ? absint( $_REQUEST['wpss_package_id'] ) : 0;
			if ( $package_id ) {
				$cart_item_data['wpss_package_id'] = $package_id;
			}
		}

		if ( ! isset( $cart_item_data['wpss_addons'] ) ) {
			$addons = isset( $_REQUEST['wpss_addons'] ) ? array_map( 'absint', (array) $_REQUEST['wpss_addons'] ) : array();
			if ( ! empty( $addons ) ) {
				$cart_item_data['wpss_addons'] = $addons;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Store service ID.
		$service_id = get_post_meta( $product_id, '_wpss_service_id', true );
		if ( $service_id ) {
			$cart_item_data['wpss_service_id'] = (int) $service_id;
		}

		// Generate unique key to allow same service with different packages.
		$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );

		return $cart_item_data;
	}

	/**
	 * Validate service can be added to cart.
	 *
	 * @param int $product_id Product ID.
	 * @param int $quantity   Quantity being added.
	 * @return bool
	 */
	public function validate_add_to_cart( int $product_id, int $quantity ): bool {
		if ( ! $this->is_service_product( $product_id ) ) {
			return true;
		}

		// Services should only be purchased in quantity 1.
		if ( $quantity > 1 ) {
			return false;
		}

		// Check if vendor is available.
		$vendors = ( new WCProductProvider() )->get_service_vendors( $product_id );

		if ( empty( $vendors ) ) {
			return false;
		}

		$vendor = wpss_get_vendor( $vendors[0] );

		if ( $vendor && ! $vendor->can_accept_orders() ) {
			return false;
		}

		return true;
	}

	/**
	 * Hook for WooCommerce cart validation.
	 *
	 * @param bool $passed     Validation result.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart_hook( bool $passed, int $product_id, int $quantity ): bool {
		if ( ! $passed ) {
			return false;
		}

		if ( ! $this->is_service_product( $product_id ) ) {
			return true;
		}

		// Services require login - guest checkout not supported.
		// Buyers need to submit requirements, communicate, and access deliveries.
		if ( ! is_user_logged_in() ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: login URL */
					__( 'Please <a href="%s">log in</a> to purchase services. An account is required to submit requirements and communicate with the seller.', 'wp-sell-services' ),
					esc_url( wp_login_url( wc_get_cart_url() ) )
				),
				'error'
			);
			return false;
		}

		if ( ! $this->validate_add_to_cart( $product_id, $quantity ) ) {
			wc_add_notice( __( 'This service cannot be added to cart at this time.', 'wp-sell-services' ), 'error' );
			return false;
		}

		// Prevent duplicate services in cart.
		if ( $this->cart_has_service( $product_id ) ) {
			wc_add_notice( __( 'This service is already in your cart.', 'wp-sell-services' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Get checkout URL for a service.
	 *
	 * @param int   $service_id Service/product ID.
	 * @param array $args       Additional arguments (package_id, addons, etc.).
	 * @return string
	 */
	public function get_checkout_url( int $service_id, array $args = array() ): string {
		$url = wc_get_checkout_url();

		// Get WC product ID from service.
		$service = wpss_get_service( $service_id );

		if ( $service ) {
			$product_id = $service->platform_ids['woocommerce'] ?? 0;

			if ( $product_id ) {
				// Build add-to-cart URL.
				$params = array(
					'add-to-cart' => $product_id,
				);

				if ( ! empty( $args['package_id'] ) ) {
					$params['wpss_package_id'] = $args['package_id'];
				}

				if ( ! empty( $args['addons'] ) ) {
					$params['wpss_addons'] = $args['addons'];
				}

				$url = add_query_arg( $params, wc_get_cart_url() );
			}
		}

		return $url;
	}

	/**
	 * Check if cart contains service items.
	 *
	 * @return bool
	 */
	public function cart_has_services(): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];

			if ( $this->is_service_product( $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if cart already has a specific service.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function cart_has_service( int $product_id ): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( (int) $cart_item['product_id'] === $product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get service items in cart.
	 *
	 * @return array
	 */
	public function get_cart_services(): array {
		$services = array();

		if ( ! WC()->cart ) {
			return $services;
		}

		foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
			$product_id = $cart_item['product_id'];

			if ( ! $this->is_service_product( $product_id ) ) {
				continue;
			}

			$services[ $cart_key ] = array(
				'product_id' => $product_id,
				'service_id' => $cart_item['wpss_service_id'] ?? 0,
				'package_id' => $cart_item['wpss_package_id'] ?? 0,
				'addons'     => $cart_item['wpss_addons'] ?? array(),
			);
		}

		return $services;
	}

	/**
	 * Process checkout for services.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $order_data Order data.
	 * @return void
	 */
	public function process_checkout( int $order_id, array $order_data ): void {
		// Service order creation is handled by WCOrderProvider on order status change.
		// This method can be used for additional processing if needed.

		/**
		 * Fires after checkout processing for service orders.
		 *
		 * @param int   $order_id   WooCommerce order ID.
		 * @param array $order_data Order data.
		 */
		do_action( 'wpss_after_checkout_process', $order_id, $order_data );
	}

	/**
	 * Save service data to order item meta.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item values.
	 * @param \WC_Order              $order         Order object.
	 * @return void
	 */
	public function save_order_item_meta( $item, string $cart_item_key, array $values, $order ): void {
		if ( isset( $values['wpss_package_id'] ) ) {
			$item->add_meta_data( '_wpss_package_id', $values['wpss_package_id'] );
		}

		if ( isset( $values['wpss_addons'] ) ) {
			$item->add_meta_data( '_wpss_addons', $values['wpss_addons'] );
		}

		if ( isset( $values['wpss_service_id'] ) ) {
			$item->add_meta_data( '_wpss_service_id', $values['wpss_service_id'] );
		}
	}

	/**
	 * Display service data in cart.
	 *
	 * @param array $item_data Cart item data for display.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( isset( $cart_item['wpss_package_id'] ) && $cart_item['wpss_package_id'] ) {
			$package_name = $this->get_package_name( $cart_item['product_id'], $cart_item['wpss_package_id'] );

			if ( $package_name ) {
				$item_data[] = array(
					'key'   => __( 'Package', 'wp-sell-services' ),
					'value' => $package_name,
				);
			}
		}

		return $item_data;
	}

	/**
	 * Get package name.
	 *
	 * @param int $product_id Product ID.
	 * @param int $package_id Package ID.
	 * @return string
	 */
	private function get_package_name( int $product_id, int $package_id ): string {
		$service_id = get_post_meta( $product_id, '_wpss_service_id', true );

		if ( ! $service_id ) {
			return '';
		}

		$packages = get_post_meta( (int) $service_id, '_wpss_packages', true ) ?: array();

		// Convert to indexed array and match by position (package_id is the index).
		$packages = array_values( $packages );

		return $packages[ $package_id ]['name'] ?? '';
	}

	/**
	 * Redirect after successful checkout.
	 *
	 * @param int $order_id Order ID.
	 * @return string|null Redirect URL or null for default.
	 */
	public function get_thankyou_redirect( int $order_id ): ?string {
		// Check if order contains services.
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return null;
		}

		foreach ( $wc_order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			if ( $this->is_service_product( $product_id ) ) {
				$service_order_id = wc_get_order_item_meta( $item->get_id(), '_wpss_order_id', true );

				if ( $service_order_id ) {
					// Redirect to service order requirements page.
					return home_url( '/service-order/' . $service_order_id . '/requirements/' );
				}
			}
		}

		return null;
	}

	/**
	 * Enforce quantity limits for services.
	 *
	 * @param int $max_qty    Current max quantity.
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public function filter_quantity_max( int $max_qty, int $product_id ): int {
		if ( $this->is_service_product( $product_id ) ) {
			return 1;
		}

		return $max_qty;
	}

	/**
	 * Filter quantity input args.
	 *
	 * @param array       $args    Quantity args.
	 * @param \WC_Product $product Product object.
	 * @return array
	 */
	public function filter_quantity_args( array $args, $product ): array {
		if ( $product && $this->is_service_product( $product->get_id() ) ) {
			$args['max_value'] = 1;
			$args['min_value'] = 1;
		}

		return $args;
	}

	/**
	 * Mark services as sold individually.
	 *
	 * @param bool        $sold_individually Whether sold individually.
	 * @param \WC_Product $product           Product object.
	 * @return bool
	 */
	public function service_sold_individually( bool $sold_individually, $product ): bool {
		if ( $product && $this->is_service_product( $product->get_id() ) ) {
			return true;
		}

		return $sold_individually;
	}

	/**
	 * Check if product is a service.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_service_product( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_wpss_is_service', true );
	}
}
