<?php
/**
 * Product Type Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A kind of thing the marketplace sells. "service" is the first; a later type
 * (physical goods) registers through the `wpss_product_types` filter, so core
 * never branches on a type name.
 *
 * @since 1.9.0
 */
interface ProductTypeInterface {

	/**
	 * Unique type id, stored in the orders table `product_type` column.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Singular display name.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Whether this id is a product of this type.
	 *
	 * @param int $product_id Product id.
	 * @return bool
	 */
	public function owns( int $product_id ): bool;

	/**
	 * What an order line is called on receipts and earnings screens.
	 *
	 * @param int $product_id Product id.
	 * @return string Empty when the product no longer exists.
	 */
	public function get_title( int $product_id ): string;

	/**
	 * Who gets paid for this product.
	 *
	 * @param int $product_id Product id.
	 * @return int User id, 0 when unknown.
	 */
	public function get_vendor_id( int $product_id ): int;
}
