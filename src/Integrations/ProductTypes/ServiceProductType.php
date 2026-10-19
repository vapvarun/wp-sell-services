<?php
/**
 * Service Product Type
 *
 * @package WPSellServices\Integrations\ProductTypes
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\ProductTypes;

use WPSellServices\Integrations\Contracts\ProductTypeInterface;
use WPSellServices\PostTypes\ServicePostType;

defined( 'ABSPATH' ) || exit;

/**
 * The first product type: a service sold through packages.
 *
 * @since 1.9.0
 */
class ServiceProductType implements ProductTypeInterface {

	/**
	 * Type id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'service';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Service', 'wp-sell-services' );
	}

	/**
	 * Whether the id is a service post.
	 *
	 * @param int $product_id Product id.
	 * @return bool
	 */
	public function owns( int $product_id ): bool {
		return ServicePostType::POST_TYPE === get_post_type( $product_id );
	}

	/**
	 * Service title.
	 *
	 * @param int $product_id Product id.
	 * @return string
	 */
	public function get_title( int $product_id ): string {
		return $this->owns( $product_id ) ? get_the_title( $product_id ) : '';
	}

	/**
	 * Service author.
	 *
	 * @param int $product_id Product id.
	 * @return int
	 */
	public function get_vendor_id( int $product_id ): int {
		return $this->owns( $product_id ) ? (int) get_post_field( 'post_author', $product_id ) : 0;
	}
}
