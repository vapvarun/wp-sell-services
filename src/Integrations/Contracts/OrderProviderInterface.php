<?php
/**
 * Order Provider Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\ServiceItem;

/**
 * Interface for order data providers.
 *
 * Implementations provide order data from different e-commerce platforms.
 *
 * @since 1.0.0
 */
interface OrderProviderInterface {

	/**
	 * Get order by platform order ID.
	 *
	 * @param int $order_id Platform-specific order ID.
	 * @return ServiceOrder|null
	 */
	public function get_order( int $order_id ): ?ServiceOrder;

	/**
	 * Get order item by platform item ID.
	 *
	 * @param int $item_id Platform-specific item ID.
	 * @return ServiceItem|null
	 */
	public function get_order_item( int $item_id ): ?ServiceItem;

	/**
	 * Get orders for a customer.
	 *
	 * @param int   $user_id Customer user ID.
	 * @param array $args    Query arguments.
	 * @return ServiceOrder[]
	 */
	public function get_customer_orders( int $user_id, array $args = [] ): array;

	/**
	 * Get orders for a vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return ServiceOrder[]
	 */
	public function get_vendor_orders( int $vendor_id, array $args = [] ): array;

	/**
	 * Check if order contains service items.
	 *
	 * @param int $order_id Platform order ID.
	 * @return bool
	 */
	public function has_service_items( int $order_id ): bool;

	/**
	 * Get all service items from an order.
	 *
	 * @param int $order_id Platform order ID.
	 * @return ServiceItem[]
	 */
	public function get_service_items( int $order_id ): array;

	/**
	 * Update order item meta.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	public function update_item_meta( int $item_id, string $key, mixed $value ): bool;

	/**
	 * Get order item meta.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return single value.
	 * @return mixed
	 */
	public function get_item_meta( int $item_id, string $key, bool $single = true ): mixed;

	/**
	 * Get customer data from order.
	 *
	 * @param int $order_id Order ID.
	 * @return array{id: int, email: string, name: string}|null
	 */
	public function get_customer_data( int $order_id ): ?array;

	/**
	 * Handle order completion callback.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_complete( int $order_id ): void;
}
