<?php
/**
 * Account Provider Interface
 *
 * @package WPSellServices\Integrations\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for account/dashboard providers.
 *
 * Implementations handle user account integration for different platforms.
 *
 * @since 1.0.0
 */
interface AccountProviderInterface {

	/**
	 * Add menu items to account navigation.
	 *
	 * @param array $items Existing menu items.
	 * @return array
	 */
	public function add_menu_items( array $items ): array;

	/**
	 * Register account endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints(): void;

	/**
	 * Get account page URL.
	 *
	 * @param string $endpoint Optional endpoint to append.
	 * @return string
	 */
	public function get_account_url( string $endpoint = '' ): string;

	/**
	 * Get service orders endpoint URL.
	 *
	 * @return string
	 */
	public function get_orders_url(): string;

	/**
	 * Get vendor dashboard URL.
	 *
	 * @return string
	 */
	public function get_vendor_dashboard_url(): string;

	/**
	 * Render orders endpoint content.
	 *
	 * @return void
	 */
	public function render_orders_endpoint(): void;

	/**
	 * Render vendor services endpoint content.
	 *
	 * @return void
	 */
	public function render_services_endpoint(): void;

	/**
	 * Render notifications endpoint content.
	 *
	 * @return void
	 */
	public function render_notifications_endpoint(): void;

	/**
	 * Check if current user can access vendor dashboard.
	 *
	 * @return bool
	 */
	public function can_access_vendor_dashboard(): bool;

	/**
	 * Get login URL.
	 *
	 * @param string $redirect Redirect URL after login.
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ): string;

	/**
	 * Get registration URL.
	 *
	 * @return string
	 */
	public function get_register_url(): string;
}
