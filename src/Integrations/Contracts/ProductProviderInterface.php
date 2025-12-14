<?php
/**
 * Product Provider Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Contracts;

use WPSellServices\Models\Service;

/**
 * Interface for product/service data providers.
 *
 * Implementations provide product data from different e-commerce platforms.
 *
 * @since 1.0.0
 */
interface ProductProviderInterface {

	/**
	 * Check if a product is marked as a service.
	 *
	 * @param int $product_id Platform product ID.
	 * @return bool
	 */
	public function is_service_product( int $product_id ): bool;

	/**
	 * Get service data from platform product.
	 *
	 * @param int $product_id Platform product ID.
	 * @return Service|null
	 */
	public function get_service( int $product_id ): ?Service;

	/**
	 * Get vendor/author IDs for a service.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public function get_service_vendors( int $product_id ): array;

	/**
	 * Get service requirements configuration.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_requirements( int $product_id ): array;

	/**
	 * Get estimated delivery time.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_delivery_time( int $product_id ): string;

	/**
	 * Mark a product as service type.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $is_service Whether it's a service.
	 * @return void
	 */
	public function set_service_type( int $product_id, bool $is_service ): void;

	/**
	 * Add service type option to product editor.
	 *
	 * @param array $options Existing product type options.
	 * @return array
	 */
	public function add_service_type_option( array $options ): array;

	/**
	 * Save service meta on product save.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function save_service_meta( int $product_id ): void;

	/**
	 * Sync service CPT with platform product.
	 *
	 * @param int $service_id  Service CPT ID.
	 * @param int $product_id  Platform product ID.
	 * @return bool
	 */
	public function sync_with_service( int $service_id, int $product_id ): bool;
}
