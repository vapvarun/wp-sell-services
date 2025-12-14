<?php
/**
 * Admin Class
 *
 * @package WPSellServices\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

/**
 * Handles all admin-side functionality.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		// Only load on plugin pages.
		if ( ! $this->is_plugin_page( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'wpss-admin',
			WPSS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPSS_VERSION
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on plugin pages.
		if ( ! $this->is_plugin_page( $hook ) ) {
			return;
		}

		wp_enqueue_script(
			'wpss-admin',
			WPSS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-admin',
			'wpssAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_admin_nonce' ),
			)
		);
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'WP Sell Services', 'wp-sell-services' ),
			__( 'Sell Services', 'wp-sell-services' ),
			'manage_options',
			'wp-sell-services',
			array( $this, 'render_dashboard_page' ),
			'dashicons-store',
			30
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Dashboard', 'wp-sell-services' ),
			__( 'Dashboard', 'wp-sell-services' ),
			'manage_options',
			'wp-sell-services',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Orders', 'wp-sell-services' ),
			__( 'Orders', 'wp-sell-services' ),
			'wpss_manage_orders',
			'wpss-orders',
			array( $this, 'render_orders_page' )
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Disputes', 'wp-sell-services' ),
			__( 'Disputes', 'wp-sell-services' ),
			'wpss_manage_disputes',
			'wpss-disputes',
			array( $this, 'render_disputes_page' )
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Settings', 'wp-sell-services' ),
			__( 'Settings', 'wp-sell-services' ),
			'wpss_manage_settings',
			'wpss-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'wpss_general_settings', 'wpss_general_settings' );
		register_setting( 'wpss_notification_settings', 'wpss_notification_settings' );
		register_setting( 'wpss_vendor_settings', 'wpss_vendor_settings' );
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return bool
	 */
	private function is_plugin_page( string $hook ): bool {
		$plugin_pages = array(
			'toplevel_page_wp-sell-services',
			'sell-services_page_wpss-orders',
			'sell-services_page_wpss-disputes',
			'sell-services_page_wpss-settings',
		);

		return in_array( $hook, $plugin_pages, true );
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'WP Sell Services Dashboard', 'wp-sell-services' ) . '</h1></div>';
	}

	/**
	 * Render orders page.
	 *
	 * @return void
	 */
	public function render_orders_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Orders', 'wp-sell-services' ) . '</h1></div>';
	}

	/**
	 * Render disputes page.
	 *
	 * @return void
	 */
	public function render_disputes_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Disputes', 'wp-sell-services' ) . '</h1></div>';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'wp-sell-services' ) . '</h1></div>';
	}
}
