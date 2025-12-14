<?php
/**
 * WP Sell Services
 *
 * A complete Fiverr-style service marketplace platform for WordPress.
 *
 * @package     WPSellServices
 * @author      Wbcom Designs
 * @copyright   2024 Wbcom Designs
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP Sell Services
 * Plugin URI:        https://developer.developer/wp-sell-services
 * Description:       A complete Fiverr-style service marketplace platform for WordPress. Create a service marketplace with WooCommerce integration, order management, messaging, reviews, and more.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Wbcom Designs
 * Author URI:        https://developer.developer
 * Text Domain:       wp-sell-services
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://developer.developer/wp-sell-services
 */

declare(strict_types=1);

namespace WPSellServices;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'WPSS_VERSION', '1.0.0' );

/**
 * Plugin file path.
 *
 * @var string
 */
define( 'WPSS_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 *
 * @var string
 */
define( 'WPSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @var string
 */
define( 'WPSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @var string
 */
define( 'WPSS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 *
 * @var string
 */
define( 'WPSS_MIN_PHP_VERSION', '8.1' );

/**
 * Minimum WordPress version required.
 *
 * @var string
 */
define( 'WPSS_MIN_WP_VERSION', '6.4' );

/**
 * Check PHP version requirement.
 *
 * @return bool
 */
function wpss_check_php_version(): bool {
	return version_compare( PHP_VERSION, WPSS_MIN_PHP_VERSION, '>=' );
}

/**
 * Check WordPress version requirement.
 *
 * @return bool
 */
function wpss_check_wp_version(): bool {
	return version_compare( get_bloginfo( 'version' ), WPSS_MIN_WP_VERSION, '>=' );
}

/**
 * Display admin notice for PHP version requirement.
 *
 * @return void
 */
function wpss_php_version_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				esc_html__( 'WP Sell Services requires PHP version %1$s or higher. Your current PHP version is %2$s. Please upgrade PHP to use this plugin.', 'wp-sell-services' ),
				esc_html( WPSS_MIN_PHP_VERSION ),
				esc_html( PHP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display admin notice for WordPress version requirement.
 *
 * @return void
 */
function wpss_wp_version_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required WordPress version, 2: Current WordPress version */
				esc_html__( 'WP Sell Services requires WordPress version %1$s or higher. Your current WordPress version is %2$s. Please upgrade WordPress to use this plugin.', 'wp-sell-services' ),
				esc_html( WPSS_MIN_WP_VERSION ),
				esc_html( get_bloginfo( 'version' ) )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function wpss_init(): void {
	// Check PHP version.
	if ( ! wpss_check_php_version() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\wpss_php_version_notice' );
		return;
	}

	// Check WordPress version.
	if ( ! wpss_check_wp_version() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\wpss_wp_version_notice' );
		return;
	}

	// Load Composer autoloader.
	$autoloader = WPSS_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
	}

	// Load helper functions.
	require_once WPSS_PLUGIN_DIR . 'src/functions.php';

	// Load the plugin.
	require_once WPSS_PLUGIN_DIR . 'src/Core/Plugin.php';

	// Initialize plugin.
	$plugin = Core\Plugin::get_instance();
	$plugin->init();

	/**
	 * Fires after WP Sell Services is fully loaded.
	 *
	 * Use this hook to extend the plugin with Pro features or third-party integrations.
	 *
	 * @since 1.0.0
	 *
	 * @param Core\Plugin $plugin The plugin instance.
	 */
	do_action( 'wpss_loaded', $plugin );
}

// Initialize on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpss_init', 10 );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function wpss_activate(): void {
	// Check requirements before activation.
	if ( ! wpss_check_php_version() || ! wpss_check_wp_version() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'WP Sell Services requires PHP 8.1+ and WordPress 6.4+.', 'wp-sell-services' ),
			esc_html__( 'Plugin Activation Error', 'wp-sell-services' ),
			array( 'back_link' => true )
		);
	}

	// Load autoloader for activation.
	$autoloader = WPSS_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
	}

	// Run activator.
	require_once WPSS_PLUGIN_DIR . 'src/Core/Activator.php';
	Core\Activator::activate();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\wpss_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wpss_deactivate(): void {
	// Load autoloader for deactivation.
	$autoloader = WPSS_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
	}

	// Run deactivator.
	require_once WPSS_PLUGIN_DIR . 'src/Core/Deactivator.php';
	Core\Deactivator::deactivate();
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\wpss_deactivate' );
