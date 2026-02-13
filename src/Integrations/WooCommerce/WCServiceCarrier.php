<?php
/**
 * WooCommerce Service Carrier Product
 *
 * Creates and manages a virtual "carrier" product that handles all service purchases.
 * This eliminates the need to create individual WooCommerce products for each service.
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

/**
 * Service Carrier Product Manager.
 *
 * @since 1.0.0
 */
class WCServiceCarrier {

	/**
	 * Option key for storing carrier product ID.
	 *
	 * @var string
	 */
	private const CARRIER_OPTION = 'wpss_wc_carrier_product_id';

	/**
	 * Carrier product ID.
	 *
	 * @var int|null
	 */
	private ?int $carrier_id = null;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Dynamic pricing based on service/package.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20 );

		// Force service products to be sold individually (qty=1, no increment buttons).
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'force_service_sold_individually' ), 10, 2 );

		// Display service info in cart.
		add_filter( 'woocommerce_cart_item_name', array( $this, 'cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 2 );

		// Hide carrier from shop catalog.
		add_action( 'woocommerce_product_query', array( $this, 'hide_carrier_from_shop' ) );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_carrier_visibility' ), 10, 2 );

		// Save service data to order.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );

		// Display service data in order.
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );

		// Validate vendor status at checkout.
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_vendor_status_at_checkout' ) );
	}

	/**
	 * Get or create the carrier product.
	 *
	 * @return int Carrier product ID.
	 */
	public function get_carrier_id(): int {
		if ( null !== $this->carrier_id ) {
			return $this->carrier_id;
		}

		$this->carrier_id = (int) get_option( self::CARRIER_OPTION, 0 );

		// Verify product exists.
		if ( $this->carrier_id && 'product' !== get_post_type( $this->carrier_id ) ) {
			$this->carrier_id = 0;
			delete_option( self::CARRIER_OPTION );
		}

		// Create if doesn't exist.
		if ( ! $this->carrier_id ) {
			$this->carrier_id = $this->create_carrier_product();
		}

		return $this->carrier_id;
	}

	/**
	 * Create the carrier product.
	 *
	 * @return int Product ID.
	 */
	private function create_carrier_product(): int {
		$product = new \WC_Product_Simple();

		$product->set_name( __( 'Service Order', 'wp-sell-services' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_virtual( true );
		// Removed: set_sold_individually(true) - services can be purchased multiple times.
		$product->set_manage_stock( false );
		$product->set_reviews_allowed( false );

		// Mark as service carrier.
		$product->update_meta_data( '_wpss_is_carrier', 'yes' );
		$product->update_meta_data( '_wpss_is_service', 'yes' );

		$product_id = $product->save();

		update_option( self::CARRIER_OPTION, $product_id );

		return $product_id;
	}

	/**
	 * Check if product is the carrier.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function is_carrier( int $product_id ): bool {
		return $product_id === $this->get_carrier_id();
	}

	/**
	 * Add service to cart via carrier product.
	 *
	 * @param int   $service_id Service CPT ID.
	 * @param int   $package_id Package index (0, 1, 2 for basic/standard/premium).
	 * @param array $addons     Optional addon IDs.
	 * @return string|false Cart item key or false on failure.
	 */
	public function add_to_cart( int $service_id, int $package_id = 0, array $addons = array() ) {
		// Ensure WC cart is properly initialized FIRST (critical for AJAX context).
		if ( null === WC()->cart || null === WC()->session ) {
			wc_load_cart();
		}

		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			return false;
		}

		$carrier_id = $this->get_carrier_id();

		// Check if service already in cart.
		if ( $this->cart_has_service( $service_id ) ) {
			wc_add_notice( __( 'This service is already in your cart.', 'wp-sell-services' ), 'error' );
			return false;
		}

		// Prepare cart item data.
		$cart_item_data = array(
			'wpss_service_id' => $service_id,
			'wpss_package_id' => $package_id,
			'wpss_addons'     => array_map( 'absint', $addons ),
			'wpss_vendor_id'  => $service->vendor_id,
			'unique_key'      => md5( 'service_' . $service_id . '_' . $package_id . '_' . microtime() ),
		);

		return WC()->cart->add_to_cart( $carrier_id, 1, 0, array(), $cart_item_data );
	}

	/**
	 * Check if cart has a specific service.
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public function cart_has_service( int $service_id ): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['wpss_service_id'] ) && (int) $cart_item['wpss_service_id'] === $service_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Force service products to be sold individually (quantity = 1).
	 *
	 * @param bool        $sold_individually Current value.
	 * @param \WC_Product $product          Product object.
	 * @return bool
	 */
	public function force_service_sold_individually( bool $sold_individually, \WC_Product $product ): bool {
		if ( $product->get_id() === $this->get_carrier_id() || 'yes' === $product->get_meta( '_wpss_is_service' ) ) {
			return true;
		}

		return $sold_individually;
	}

	/**
	 * Set dynamic price based on service package.
	 *
	 * @param \WC_Cart $cart Cart object.
	 * @return void
	 */
	public function set_cart_item_price( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['wpss_service_id'] ) ) {
				continue;
			}

			$price = $this->calculate_service_price(
				(int) $cart_item['wpss_service_id'],
				(int) ( $cart_item['wpss_package_id'] ?? 0 ),
				$cart_item['wpss_addons'] ?? array()
			);

			$cart_item['data']->set_price( $price );
		}
	}

	/**
	 * Calculate total price for service with package and addons.
	 *
	 * @param int   $service_id Service ID.
	 * @param int   $package_id Package index.
	 * @param array $addons     Addon IDs.
	 * @return float
	 */
	public function calculate_service_price( int $service_id, int $package_id, array $addons = array() ): float {
		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			return 0.0;
		}

		$packages = get_post_meta( $service_id, '_wpss_packages', true );
		$packages = is_array( $packages ) ? array_values( $packages ) : array();

		$base_price = 0.0;

		if ( isset( $packages[ $package_id ]['price'] ) ) {
			$base_price = (float) $packages[ $package_id ]['price'];
		}

		// Add addon prices.
		$addon_total = 0.0;
		$all_addons  = get_post_meta( $service_id, '_wpss_addons', true );
		$all_addons  = is_array( $all_addons ) ? $all_addons : array();

		foreach ( $addons as $addon_id ) {
			if ( isset( $all_addons[ $addon_id ]['price'] ) ) {
				$addon_total += (float) $all_addons[ $addon_id ]['price'];
			}
		}

		return $base_price + $addon_total;
	}

	/**
	 * Display service name instead of carrier name in cart.
	 *
	 * @param string $name      Product name.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_key  Cart item key (unused but required by WC filter).
	 * @return string
	 */
	public function cart_item_name( string $name, array $cart_item, string $cart_key ): string {
		unset( $cart_key ); // Required by WooCommerce filter signature.

		if ( ! isset( $cart_item['wpss_service_id'] ) ) {
			return $name;
		}

		$service = wpss_get_service( (int) $cart_item['wpss_service_id'] );

		if ( ! $service ) {
			return $name;
		}

		$service_name = esc_html( $service->title );

		// Add package name.
		$package_name = $this->get_package_name( (int) $cart_item['wpss_service_id'], (int) ( $cart_item['wpss_package_id'] ?? 0 ) );

		if ( $package_name ) {
			$service_name .= ' <small>(' . esc_html( $package_name ) . ')</small>';
		}

		// Link to service.
		$permalink = get_permalink( $cart_item['wpss_service_id'] );

		if ( $permalink ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $permalink ), $service_name );
		}

		return $service_name;
	}

	/**
	 * Display service name in order items.
	 *
	 * @param string                 $name Product name.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return string
	 */
	public function order_item_name( string $name, $item ): string {
		$service_id = $item->get_meta( '_wpss_service_id' );

		if ( ! $service_id ) {
			return $name;
		}

		$service = wpss_get_service( (int) $service_id );

		if ( ! $service ) {
			return $name;
		}

		$service_name = esc_html( $service->title );

		// Add package name.
		$package_id   = $item->get_meta( '_wpss_package_id' );
		$package_name = $this->get_package_name( (int) $service_id, (int) $package_id );

		if ( $package_name ) {
			$service_name .= ' (' . esc_html( $package_name ) . ')';
		}

		return $service_name;
	}

	/**
	 * Get package name by index.
	 *
	 * @param int $service_id Service ID.
	 * @param int $package_id Package index.
	 * @return string
	 */
	private function get_package_name( int $service_id, int $package_id ): string {
		$packages = get_post_meta( $service_id, '_wpss_packages', true );
		$packages = is_array( $packages ) ? array_values( $packages ) : array();

		return isset( $packages[ $package_id ]['name'] ) ? $packages[ $package_id ]['name'] : '';
	}

	/**
	 * Hide carrier product from shop catalog.
	 *
	 * @param \WP_Query $query Product query.
	 * @return void
	 */
	public function hide_carrier_from_shop( $query ): void {
		$carrier_id = $this->get_carrier_id();

		if ( $carrier_id ) {
			$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), array( $carrier_id ) ) );
		}
	}

	/**
	 * Hide carrier from visibility checks.
	 *
	 * @param bool $visible  Is visible.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public function hide_carrier_visibility( bool $visible, int $product_id ): bool {
		if ( $this->is_carrier( $product_id ) ) {
			return false;
		}

		return $visible;
	}

	/**
	 * AJAX handler for adding service to cart.
	 *
	 * @return void
	 */
	public function ajax_add_to_cart(): void {
		check_ajax_referer( 'wpss_nonce', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
		$addons     = isset( $_POST['addons'] ) ? array_map( 'absint', (array) $_POST['addons'] ) : array();

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		// Check user can purchase from this vendor.
		$service = wpss_get_service( $service_id );

		if ( ! $service ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
		}

		// Can't buy own service.
		if ( get_current_user_id() === $service->vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'You cannot purchase your own service.', 'wp-sell-services' ) ) );
		}

		// Verify vendor is still active and approved.
		$vendor_service = new \WPSellServices\Services\VendorService();
		if ( ! $vendor_service->is_vendor( $service->vendor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This vendor is no longer active. Please try a different service.', 'wp-sell-services' ) ) );
		}

		// Check vendor is not suspended.
		$vendor_profile = wpss_get_vendor( $service->vendor_id );
		if ( $vendor_profile && 'suspended' === ( $vendor_profile->status ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'This vendor is currently unavailable.', 'wp-sell-services' ) ) );
		}

		$cart_key = $this->add_to_cart( $service_id, $package_id, $addons );

		if ( ! $cart_key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add service to cart.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Service added to cart!', 'wp-sell-services' ),
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
				'cart_count'   => WC()->cart->get_cart_contents_count(),
			)
		);
	}

	/**
	 * AJAX handler for guests - require login.
	 *
	 * @return void
	 */
	public function ajax_add_to_cart_guest(): void {
		wp_send_json_error(
			array(
				'message'   => __( 'Please log in to purchase services.', 'wp-sell-services' ),
				'login_url' => wp_login_url( wc_get_checkout_url() ),
			)
		);
	}

	/**
	 * Save service data to order item meta.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key (unused but required).
	 * @param array                  $values        Cart item values.
	 * @param \WC_Order              $order         Order object (unused but required).
	 * @return void
	 */
	public function save_order_item_meta( $item, string $cart_item_key, array $values, $order ): void {
		unset( $cart_item_key, $order ); // Required by WooCommerce hook signature.

		if ( isset( $values['wpss_service_id'] ) ) {
			$item->add_meta_data( '_wpss_service_id', $values['wpss_service_id'] );
		}

		if ( isset( $values['wpss_package_id'] ) ) {
			$item->add_meta_data( '_wpss_package_id', $values['wpss_package_id'] );
		}

		if ( isset( $values['wpss_addons'] ) && ! empty( $values['wpss_addons'] ) ) {
			$item->add_meta_data( '_wpss_addons', $values['wpss_addons'] );
		}

		if ( isset( $values['wpss_vendor_id'] ) ) {
			$item->add_meta_data( '_wpss_vendor_id', $values['wpss_vendor_id'] );
		}
	}

	/**
	 * Format meta data for display in order.
	 *
	 * @param array                  $formatted_meta Formatted meta.
	 * @param \WC_Order_Item_Product $item           Order item.
	 * @return array
	 */
	public function format_order_item_meta( array $formatted_meta, $item ): array {
		// Hide internal meta keys from display.
		$hidden_keys = array( '_wpss_service_id', '_wpss_package_id', '_wpss_addons', '_wpss_vendor_id' );

		foreach ( $formatted_meta as $key => $meta ) {
			if ( in_array( $meta->key, $hidden_keys, true ) ) {
				unset( $formatted_meta[ $key ] );
			}
		}

		// Add readable service info.
		$service_id = $item->get_meta( '_wpss_service_id' );
		$package_id = $item->get_meta( '_wpss_package_id' );

		if ( $service_id ) {
			$package_name = $this->get_package_name( (int) $service_id, (int) $package_id );

			if ( $package_name ) {
				$formatted_meta[] = (object) array(
					'key'           => '_wpss_package_display',
					'value'         => $package_name,
					'display_key'   => __( 'Package', 'wp-sell-services' ),
					'display_value' => esc_html( $package_name ),
				);
			}

			// Show vendor.
			$service = wpss_get_service( (int) $service_id );

			if ( $service ) {
				$vendor = wpss_get_vendor( $service->vendor_id );

				if ( $vendor ) {
					$formatted_meta[] = (object) array(
						'key'           => '_wpss_vendor_display',
						'value'         => $vendor->display_name,
						'display_key'   => __( 'Seller', 'wp-sell-services' ),
						'display_value' => esc_html( $vendor->display_name ),
					);
				}
			}
		}

		return $formatted_meta;
	}

	/**
	 * Validate vendor status for all service items in cart at checkout.
	 *
	 * Prevents checkout if any vendor is no longer active or has been suspended.
	 *
	 * @return void
	 */
	public function validate_vendor_status_at_checkout(): void {
		if ( ! WC()->cart ) {
			return;
		}

		$vendor_service = new \WPSellServices\Services\VendorService();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			// Skip non-service items.
			if ( ! isset( $cart_item['wpss_service_id'] ) ) {
				continue;
			}

			$service_id = (int) $cart_item['wpss_service_id'];
			$vendor_id  = isset( $cart_item['wpss_vendor_id'] ) ? (int) $cart_item['wpss_vendor_id'] : 0;

			// Get vendor ID from service if not in cart data.
			if ( ! $vendor_id ) {
				$service = wpss_get_service( $service_id );
				if ( $service ) {
					$vendor_id = $service->vendor_id;
				}
			}

			if ( ! $vendor_id ) {
				continue;
			}

			// Check vendor is still active.
			if ( ! $vendor_service->is_vendor( $vendor_id ) ) {
				$service       = wpss_get_service( $service_id );
				$service_title = $service ? $service->title : __( 'Service', 'wp-sell-services' );

				wc_add_notice(
					sprintf(
						/* translators: %s: Service title */
						__( '"%s" cannot be purchased because the vendor is no longer active. Please remove this item from your cart.', 'wp-sell-services' ),
						esc_html( $service_title )
					),
					'error'
				);
				continue;
			}

			// Check vendor is not suspended.
			$vendor_profile = wpss_get_vendor( $vendor_id );
			if ( $vendor_profile && 'suspended' === ( $vendor_profile->status ?? '' ) ) {
				$service       = wpss_get_service( $service_id );
				$service_title = $service ? $service->title : __( 'Service', 'wp-sell-services' );

				wc_add_notice(
					sprintf(
						/* translators: %s: Service title */
						__( '"%s" cannot be purchased because the vendor is currently unavailable. Please remove this item from your cart.', 'wp-sell-services' ),
						esc_html( $service_title )
					),
					'error'
				);
			}
		}
	}

	/**
	 * Setup carrier product on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$carrier = new self();
		$carrier->get_carrier_id();
	}
}
