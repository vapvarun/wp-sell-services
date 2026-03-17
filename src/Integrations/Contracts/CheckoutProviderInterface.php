<?php
/**
 * Checkout Provider Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for checkout/cart providers.
 *
 * Implementations handle cart and checkout integration for different platforms.
 *
 * @since 1.0.0
 */
interface CheckoutProviderInterface {

	/**
	 * Add service-specific data to cart item.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Product ID being added.
	 * @param int   $variation_id   Variation ID (if applicable).
	 * @return array
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array;

	/**
	 * Validate service can be added to cart.
	 *
	 * @param int $product_id Product ID.
	 * @param int $quantity   Quantity being added.
	 * @return bool
	 */
	public function validate_add_to_cart( int $product_id, int $quantity ): bool;

	/**
	 * Get checkout URL for a service.
	 *
	 * @param int   $service_id Service/product ID.
	 * @param array $args       Additional arguments (package_id, addons, etc.).
	 * @return string
	 */
	public function get_checkout_url( int $service_id, array $args = [] ): string;

	/**
	 * Check if cart contains service items.
	 *
	 * @return bool
	 */
	public function cart_has_services(): bool;

	/**
	 * Get service items in cart.
	 *
	 * @return array
	 */
	public function get_cart_services(): array;

	/**
	 * Process checkout for services.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $order_data Order data.
	 * @return void
	 */
	public function process_checkout( int $order_id, array $order_data ): void;

	/**
	 * Redirect after successful checkout.
	 *
	 * @param int $order_id Order ID.
	 * @return string|null Redirect URL or null for default.
	 */
	public function get_thankyou_redirect( int $order_id ): ?string;

	/**
	 * Enforce quantity limits for services.
	 *
	 * @param int $max_qty    Current max quantity.
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public function filter_quantity_max( int $max_qty, int $product_id ): int;
}
