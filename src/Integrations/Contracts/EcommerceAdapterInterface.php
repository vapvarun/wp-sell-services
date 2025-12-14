<?php
/**
 * E-Commerce Adapter Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Contracts;

/**
 * Interface for e-commerce platform adapters.
 *
 * All e-commerce integrations (WooCommerce, EDD, etc.) must implement this interface.
 *
 * @since 1.0.0
 */
interface EcommerceAdapterInterface {

	/**
	 * Get the unique adapter identifier.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the adapter display name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Check if the e-commerce platform is installed and active.
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * Initialize the adapter and register hooks.
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Check if the adapter supports a specific feature.
	 *
	 * @param string $feature Feature name (e.g., 'subscriptions', 'variable_products').
	 * @return bool
	 */
	public function supports_feature( string $feature ): bool;

	/**
	 * Get the order provider for this platform.
	 *
	 * @return OrderProviderInterface
	 */
	public function get_order_provider(): OrderProviderInterface;

	/**
	 * Get the product provider for this platform.
	 *
	 * @return ProductProviderInterface
	 */
	public function get_product_provider(): ProductProviderInterface;

	/**
	 * Get the checkout provider for this platform.
	 *
	 * @return CheckoutProviderInterface
	 */
	public function get_checkout_provider(): CheckoutProviderInterface;

	/**
	 * Get the account provider for this platform.
	 *
	 * @return AccountProviderInterface
	 */
	public function get_account_provider(): AccountProviderInterface;
}
