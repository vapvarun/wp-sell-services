<?php
/**
 * WooCommerce Account Provider
 *
 * Adds WP Sell Services links to WooCommerce My Account navigation.
 * All endpoints redirect to the standalone vendor dashboard.
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

use WPSellServices\Integrations\Contracts\AccountProviderInterface;

/**
 * Provides account/dashboard functionality through WooCommerce.
 *
 * @since 1.0.0
 */
class WCAccountProvider implements AccountProviderInterface {

	/**
	 * Endpoint to dashboard section mapping.
	 *
	 * @var array<string, string>
	 */
	private const ENDPOINT_MAP = array(
		'service-orders'        => '',
		'vendor-dashboard'      => '',
		'vendor-services'       => 'services',
		'service-notifications' => 'messages',
		'service-disputes'      => '',
	);

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register endpoints directly since init() is called during the init hook.
		$this->register_endpoints();
		$this->maybe_flush_rewrite_rules();

		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_items' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirect_to_dashboard' ) );
	}

	/**
	 * Add menu items to account navigation.
	 *
	 * @param array $items Existing menu items.
	 * @return array
	 */
	public function add_menu_items( array $items ): array {
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );

		$items['service-orders'] = __( 'Service Orders', 'wp-sell-services' );

		if ( $this->can_access_vendor_dashboard() ) {
			$items['vendor-dashboard'] = __( 'Vendor Dashboard', 'wp-sell-services' );
			$items['vendor-services']  = __( 'My Services', 'wp-sell-services' );
		}

		$items['service-disputes'] = __( 'Disputes', 'wp-sell-services' );

		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Register account endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		add_rewrite_endpoint( 'service-orders', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'vendor-dashboard', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'vendor-services', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'service-notifications', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'service-disputes', EP_ROOT | EP_PAGES );
	}

	/**
	 * Flush rewrite rules if needed after activation.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_transient( 'wpss_flush_rewrite_rules' ) ) {
			delete_transient( 'wpss_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Add query vars for endpoints.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		foreach ( array_keys( self::ENDPOINT_MAP ) as $endpoint ) {
			$vars[ $endpoint ] = $endpoint;
		}

		return $vars;
	}

	/**
	 * Redirect WooCommerce endpoints to the standalone dashboard.
	 *
	 * @return void
	 */
	public function redirect_to_dashboard(): void {
		if ( ! is_account_page() ) {
			return;
		}

		$dashboard_url = wpss_get_page_url( 'dashboard' );

		if ( ! $dashboard_url ) {
			return;
		}

		global $wp_query;

		foreach ( self::ENDPOINT_MAP as $endpoint => $section ) {
			// WooCommerce sets query var key when endpoint is active.
			if ( ! isset( $wp_query->query_vars[ $endpoint ] ) ) {
				continue;
			}

			$redirect_url = $section
				? add_query_arg( 'section', $section, $dashboard_url )
				: $dashboard_url;

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Render orders endpoint content.
	 *
	 * Not used — endpoints redirect to standalone dashboard via template_redirect.
	 *
	 * @return void
	 */
	public function render_orders_endpoint(): void {}

	/**
	 * Render services endpoint content.
	 *
	 * Not used — endpoints redirect to standalone dashboard via template_redirect.
	 *
	 * @return void
	 */
	public function render_services_endpoint(): void {}

	/**
	 * Render notifications endpoint content.
	 *
	 * Not used — endpoints redirect to standalone dashboard via template_redirect.
	 *
	 * @return void
	 */
	public function render_notifications_endpoint(): void {}

	/**
	 * Get account page URL.
	 *
	 * @param string $endpoint Optional endpoint to append.
	 * @return string
	 */
	public function get_account_url( string $endpoint = '' ): string {
		$page_id = wc_get_page_id( 'myaccount' );

		if ( ! $page_id ) {
			return home_url( '/my-account/' . $endpoint );
		}

		$base_url = get_permalink( $page_id );

		if ( $endpoint ) {
			return wc_get_endpoint_url( $endpoint, '', $base_url );
		}

		return $base_url;
	}

	/**
	 * Get service orders endpoint URL.
	 *
	 * @return string
	 */
	public function get_orders_url(): string {
		return $this->get_account_url( 'service-orders' );
	}

	/**
	 * Get vendor dashboard URL.
	 *
	 * @return string
	 */
	public function get_vendor_dashboard_url(): string {
		$dashboard_url = wpss_get_page_url( 'dashboard' );

		return $dashboard_url ?: $this->get_account_url( 'vendor-dashboard' );
	}

	/**
	 * Check if current user can access vendor dashboard.
	 *
	 * @return bool
	 */
	public function can_access_vendor_dashboard(): bool {
		return wpss_is_vendor();
	}

	/**
	 * Get login URL.
	 *
	 * @param string $redirect Redirect URL after login.
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ): string {
		$url = wc_get_page_permalink( 'myaccount' );

		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}

		return $url;
	}

	/**
	 * Get registration URL.
	 *
	 * @return string
	 */
	public function get_register_url(): string {
		$page_id = wc_get_page_id( 'myaccount' );

		if ( ! $page_id ) {
			return wp_registration_url();
		}

		return get_permalink( $page_id );
	}
}
