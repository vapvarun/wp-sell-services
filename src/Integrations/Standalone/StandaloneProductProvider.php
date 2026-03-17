<?php
/**
 * Standalone Product Provider
 *
 * @package WPSellServices\Integrations\Standalone
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Standalone;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\ProductProviderInterface;
use WPSellServices\Models\Service;

/**
 * Product provider for standalone mode.
 *
 * In standalone mode, services are not linked to any e-commerce product.
 * The service CPT itself acts as the product.
 *
 * @since 1.0.0
 */
class StandaloneProductProvider implements ProductProviderInterface {

	/**
	 * Create a platform product for a service.
	 *
	 * In standalone mode, no external product is created.
	 *
	 * @param Service $service Service model.
	 * @return int|null Always returns null in standalone mode.
	 */
	public function create_product( Service $service ): ?int {
		return null;
	}

	/**
	 * Update a platform product from service.
	 *
	 * @param Service $service Service model.
	 * @return bool
	 */
	public function update_product( Service $service ): bool {
		return true;
	}

	/**
	 * Delete a platform product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function delete_product( int $product_id ): bool {
		return true;
	}

	/**
	 * Get service by platform product ID.
	 *
	 * In standalone mode, the product ID is the service ID.
	 *
	 * @param int $product_id Product ID.
	 * @return Service|null
	 */
	public function get_service_by_product( int $product_id ): ?Service {
		return wpss_get_service( $product_id );
	}

	/**
	 * Get product ID for a service.
	 *
	 * In standalone mode, returns the service ID itself.
	 *
	 * @param int $service_id Service ID.
	 * @return int|null
	 */
	public function get_product_id( int $service_id ): ?int {
		$service = wpss_get_service( $service_id );
		return $service ? $service_id : null;
	}

	/**
	 * Sync service with platform product.
	 *
	 * @param Service $service Service model.
	 * @return bool
	 */
	public function sync_product( Service $service ): bool {
		return true;
	}

	/**
	 * Get product edit URL.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_product_edit_url( int $product_id ): string {
		return get_edit_post_link( $product_id ) ?: '';
	}

	/**
	 * Get product view URL.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_product_view_url( int $product_id ): string {
		return get_permalink( $product_id ) ?: '';
	}

	/**
	 * Check if product exists.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function product_exists( int $product_id ): bool {
		$service = wpss_get_service( $product_id );
		return $service !== null;
	}

	/**
	 * Get product price.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	public function get_product_price( int $product_id ): float {
		$service = wpss_get_service( $product_id );

		if ( ! $service ) {
			return 0.0;
		}

		return $service->get_starting_price();
	}

	/**
	 * Check if product is purchasable.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function is_purchasable( int $product_id ): bool {
		$service = wpss_get_service( $product_id );

		if ( ! $service || ! $service->is_active() ) {
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
	 * Check if a product is marked as a service.
	 *
	 * In standalone mode, all service CPTs are services.
	 *
	 * @param int $product_id Platform product ID.
	 * @return bool
	 */
	public function is_service_product( int $product_id ): bool {
		$post = get_post( $product_id );
		return $post && 'wpss_service' === $post->post_type;
	}

	/**
	 * Get service data from platform product.
	 *
	 * @param int $product_id Platform product ID.
	 * @return Service|null
	 */
	public function get_service( int $product_id ): ?Service {
		return wpss_get_service( $product_id );
	}

	/**
	 * Get vendor/author IDs for a service.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public function get_service_vendors( int $product_id ): array {
		$service = wpss_get_service( $product_id );
		if ( ! $service ) {
			return array();
		}
		return array( $service->vendor_id );
	}

	/**
	 * Get service requirements configuration.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_requirements( int $product_id ): array {
		$service = wpss_get_service( $product_id );
		if ( ! $service ) {
			return array();
		}
		return $service->requirements;
	}

	/**
	 * Get estimated delivery time.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_delivery_time( int $product_id ): string {
		$service = wpss_get_service( $product_id );
		if ( ! $service ) {
			return '';
		}
		$days = $service->get_fastest_delivery();
		if ( 0 === $days ) {
			return '';
		}
		/* translators: %d: number of days */
		return sprintf( _n( '%d day', '%d days', $days, 'wp-sell-services' ), $days );
	}

	/**
	 * Mark a product as service type.
	 *
	 * In standalone mode, this is a no-op since we use our own CPT.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $is_service Whether it's a service.
	 * @return void
	 */
	public function set_service_type( int $product_id, bool $is_service ): void {
		// No-op in standalone mode - the CPT itself is the service.
	}

	/**
	 * Add service type option to product editor.
	 *
	 * Not used in standalone mode.
	 *
	 * @param array $options Existing product type options.
	 * @return array
	 */
	public function add_service_type_option( array $options ): array {
		return $options;
	}

	/**
	 * Save service meta on product save.
	 *
	 * Not used in standalone mode.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function save_service_meta( int $product_id ): void {
		// No-op in standalone mode.
	}

	/**
	 * Sync service CPT with platform product.
	 *
	 * Not used in standalone mode since service IS the product.
	 *
	 * @param int $service_id  Service CPT ID.
	 * @param int $product_id  Platform product ID.
	 * @return bool
	 */
	public function sync_with_service( int $service_id, int $product_id ): bool {
		return true;
	}
}
