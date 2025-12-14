<?php
/**
 * WooCommerce Account Provider
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
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_endpoints' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_items' ] );
		add_filter( 'woocommerce_get_query_vars', [ $this, 'add_query_vars' ] );

		// Endpoint content.
		add_action( 'woocommerce_account_service-orders_endpoint', [ $this, 'render_orders_endpoint' ] );
		add_action( 'woocommerce_account_vendor-services_endpoint', [ $this, 'render_services_endpoint' ] );
		add_action( 'woocommerce_account_service-notifications_endpoint', [ $this, 'render_notifications_endpoint' ] );
		add_action( 'woocommerce_account_vendor-dashboard_endpoint', [ $this, 'render_vendor_dashboard' ] );

		// Endpoint titles.
		add_filter( 'woocommerce_endpoint_service-orders_title', [ $this, 'service_orders_title' ] );
		add_filter( 'woocommerce_endpoint_vendor-services_title', [ $this, 'vendor_services_title' ] );
		add_filter( 'woocommerce_endpoint_service-notifications_title', [ $this, 'notifications_title' ] );
		add_filter( 'woocommerce_endpoint_vendor-dashboard_title', [ $this, 'vendor_dashboard_title' ] );
	}

	/**
	 * Add menu items to account navigation.
	 *
	 * @param array $items Existing menu items.
	 * @return array
	 */
	public function add_menu_items( array $items ): array {
		// Insert service-related items before logout.
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );

		$items['service-orders'] = __( 'Service Orders', 'wp-sell-services' );

		// Add vendor items if user is a vendor.
		if ( $this->can_access_vendor_dashboard() ) {
			$items['vendor-dashboard'] = __( 'Vendor Dashboard', 'wp-sell-services' );
			$items['vendor-services']  = __( 'My Services', 'wp-sell-services' );
		}

		$items['service-notifications'] = __( 'Notifications', 'wp-sell-services' );

		// Add logout back at the end.
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
	}

	/**
	 * Add query vars for endpoints.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars['service-orders']        = 'service-orders';
		$vars['vendor-dashboard']      = 'vendor-dashboard';
		$vars['vendor-services']       = 'vendor-services';
		$vars['service-notifications'] = 'service-notifications';

		return $vars;
	}

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
		return $this->get_account_url( 'vendor-dashboard' );
	}

	/**
	 * Render orders endpoint content.
	 *
	 * @return void
	 */
	public function render_orders_endpoint(): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);

		wpss_get_template(
			'myaccount/service-orders.php',
			[
				'orders'  => $orders,
				'user_id' => $user_id,
			]
		);
	}

	/**
	 * Render vendor services endpoint content.
	 *
	 * @return void
	 */
	public function render_services_endpoint(): void {
		if ( ! $this->can_access_vendor_dashboard() ) {
			echo '<p>' . esc_html__( 'You do not have permission to access this page.', 'wp-sell-services' ) . '</p>';
			return;
		}

		$user_id = get_current_user_id();

		$services = get_posts(
			[
				'post_type'      => \WPSellServices\PostTypes\ServicePostType::POST_TYPE,
				'author'         => $user_id,
				'posts_per_page' => -1,
				'post_status'    => [ 'publish', 'pending', 'draft' ],
			]
		);

		wpss_get_template(
			'myaccount/vendor-services.php',
			[
				'services' => $services,
				'user_id'  => $user_id,
			]
		);
	}

	/**
	 * Render notifications endpoint content.
	 *
	 * @return void
	 */
	public function render_notifications_endpoint(): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
				$user_id
			)
		);

		wpss_get_template(
			'myaccount/notifications.php',
			[
				'notifications' => $notifications,
				'user_id'       => $user_id,
			]
		);
	}

	/**
	 * Render vendor dashboard.
	 *
	 * @return void
	 */
	public function render_vendor_dashboard(): void {
		if ( ! $this->can_access_vendor_dashboard() ) {
			echo '<p>' . esc_html__( 'You do not have permission to access this page.', 'wp-sell-services' ) . '</p>';
			return;
		}

		$user_id = get_current_user_id();
		$vendor  = wpss_get_vendor( $user_id );

		// Get vendor stats.
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
					SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_orders,
					SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as total_earnings
				FROM {$orders_table}
				WHERE vendor_id = %d",
				$user_id
			)
		);

		wpss_get_template(
			'myaccount/vendor-dashboard.php',
			[
				'vendor'  => $vendor,
				'stats'   => $stats,
				'user_id' => $user_id,
			]
		);
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

	/**
	 * Service orders endpoint title.
	 *
	 * @return string
	 */
	public function service_orders_title(): string {
		return __( 'Service Orders', 'wp-sell-services' );
	}

	/**
	 * Vendor services endpoint title.
	 *
	 * @return string
	 */
	public function vendor_services_title(): string {
		return __( 'My Services', 'wp-sell-services' );
	}

	/**
	 * Notifications endpoint title.
	 *
	 * @return string
	 */
	public function notifications_title(): string {
		return __( 'Notifications', 'wp-sell-services' );
	}

	/**
	 * Vendor dashboard endpoint title.
	 *
	 * @return string
	 */
	public function vendor_dashboard_title(): string {
		return __( 'Vendor Dashboard', 'wp-sell-services' );
	}
}
