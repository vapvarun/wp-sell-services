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
 * Description:       A complete Fiverr-style service marketplace platform for WordPress. Create a service marketplace with built-in standalone checkout, order management, messaging, reviews, and more.
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
 * Register PSR-4 autoloader for WPSellServices namespace.
 *
 * This provides a fallback autoloader that works even when
 * Composer's vendor directory is incomplete (e.g., in distributions
 * without dev dependencies).
 *
 * @return void
 */
function wpss_register_autoloader(): void {
	static $registered = false;

	if ( $registered ) {
		return;
	}

	spl_autoload_register(
		function ( string $class_name ): void {
			$prefix   = 'WPSellServices\\';
			$base_dir = WPSS_PLUGIN_DIR . 'src/';

			// Check if the class uses the namespace prefix.
			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
				return;
			}

			// Get the relative class name.
			$relative_class = substr( $class_name, $len );

			// Replace namespace separators with directory separators.
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			// If the file exists, require it.
			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	);

	$registered = true;
}

/**
 * Load Composer autoloader safely.
 *
 * Only loads if the autoloader exists and doesn't reference missing files.
 * This handles distributions without dev dependencies.
 *
 * @return bool Whether the autoloader was loaded successfully.
 */
function wpss_load_composer_autoloader(): bool {
	static $loaded = null;

	if ( null !== $loaded ) {
		return $loaded;
	}

	$autoloader = WPSS_PLUGIN_DIR . 'vendor/autoload.php';

	if ( ! file_exists( $autoloader ) ) {
		$loaded = false;
		return false;
	}

	// Check if autoload_files.php references files that don't exist.
	$autoload_files = WPSS_PLUGIN_DIR . 'vendor/composer/autoload_files.php';
	if ( file_exists( $autoload_files ) ) {
		$files = require $autoload_files;
		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				// Dev dependency file missing - skip Composer autoloader.
				$loaded = false;
				return false;
			}
		}
	}

	require_once $autoloader;
	$loaded = true;
	return true;
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

	// Register custom PSR-4 autoloader (always works).
	wpss_register_autoloader();

	// Try to load Composer autoloader (for dev environments).
	wpss_load_composer_autoloader();

	// Load WP-CLI commands.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// Test commands.
		$cli_command = WPSS_PLUGIN_DIR . 'tests/cli/class-test-command.php';
		if ( file_exists( $cli_command ) ) {
			require_once $cli_command;
		}

		// Service management commands.
		$service_commands = WPSS_PLUGIN_DIR . 'src/CLI/ServiceCommands.php';
		if ( file_exists( $service_commands ) ) {
			require_once $service_commands;
		}
	}

	// Load helper functions.
	require_once WPSS_PLUGIN_DIR . 'src/functions.php';

	// Load the plugin.
	require_once WPSS_PLUGIN_DIR . 'src/Core/Plugin.php';

	// Initialize plugin.
	// Note: wpss_loaded action is fired inside Plugin::init() - do not fire it again here.
	$plugin = Core\Plugin::get_instance();
	$plugin->init();

	// Run migration for existing WooCommerce users.
	wpss_maybe_migrate_to_standalone();
}

// Initialize on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpss_init', 10 );

/**
 * Migrate existing WooCommerce users to standalone.
 *
 * Runs once on update. If the user had WooCommerce as the platform and
 * Pro is not active, switches to standalone and shows a notice.
 *
 * @return void
 */
function wpss_maybe_migrate_to_standalone(): void {
	// Only run once.
	if ( get_option( 'wpss_standalone_migrated' ) ) {
		return;
	}

	$settings = get_option( 'wpss_general', array() );
	$platform = $settings['ecommerce_platform'] ?? 'auto';

	// If user had WooCommerce or auto (which would have selected WC).
	if ( in_array( $platform, array( 'woocommerce', 'auto' ), true ) ) {
		// If Pro is active with WC, no action needed - Pro handles WC now.
		if ( defined( 'WPSS_PRO_VERSION' ) && class_exists( 'WooCommerce' ) ) {
			update_option( 'wpss_standalone_migrated', true );
			return;
		}

		// Switch to standalone.
		$settings['ecommerce_platform'] = 'standalone';
		update_option( 'wpss_general', $settings );

		// Show a one-time notice.
		set_transient( 'wpss_standalone_migration_notice', true, 0 );
	}

	update_option( 'wpss_standalone_migrated', true );
}

/**
 * Display migration notice for users switching from WooCommerce to standalone.
 *
 * @return void
 */
function wpss_standalone_migration_notice(): void {
	if ( ! get_transient( 'wpss_standalone_migration_notice' ) ) {
		return;
	}

	?>
	<div class="notice notice-info is-dismissible" id="wpss-standalone-notice">
		<p>
			<strong><?php esc_html_e( 'WP Sell Services', 'wp-sell-services' ); ?></strong> &mdash;
			<?php esc_html_e( 'The plugin now works standalone with built-in checkout! WooCommerce integration has moved to Pro. Your marketplace continues working with the built-in checkout system.', 'wp-sell-services' ); ?>
		</p>
	</div>
	<script>
	jQuery(document).on('click', '#wpss-standalone-notice .notice-dismiss', function() {
		jQuery.post(ajaxurl, { action: 'wpss_dismiss_standalone_notice', _wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpss_dismiss_notice' ) ); ?>' });
	});
	</script>
	<?php
}

add_action( 'admin_notices', __NAMESPACE__ . '\\wpss_standalone_migration_notice' );

/**
 * Dismiss migration notice via AJAX.
 *
 * @return void
 */
function wpss_dismiss_standalone_notice(): void {
	check_ajax_referer( 'wpss_dismiss_notice' );
	delete_transient( 'wpss_standalone_migration_notice' );
	wp_send_json_success();
}

add_action( 'wp_ajax_wpss_dismiss_standalone_notice', __NAMESPACE__ . '\\wpss_dismiss_standalone_notice' );

/**
 * Add plugin action links (shown on Plugins page).
 *
 * @param array<string, string> $links Existing action links.
 * @return array<string, string>
 */
function wpss_plugin_action_links( array $links ): array {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wpss-settings' ) ) . '">'
		. esc_html__( 'Settings', 'wp-sell-services' ) . '</a>';
	array_unshift( $links, $settings_link );

	if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
		$links['go_pro'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpss-upgrade' ) ) . '" style="color:#1dbf73;font-weight:600;">'
			. esc_html__( 'Go Pro', 'wp-sell-services' ) . '</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links_' . WPSS_PLUGIN_BASENAME, __NAMESPACE__ . '\\wpss_plugin_action_links' );

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

	// Register custom PSR-4 autoloader (always works).
	wpss_register_autoloader();

	// Try to load Composer autoloader (for dev environments).
	wpss_load_composer_autoloader();

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
	// Register custom PSR-4 autoloader (always works).
	wpss_register_autoloader();

	// Try to load Composer autoloader (for dev environments).
	wpss_load_composer_autoloader();

	// Run deactivator.
	require_once WPSS_PLUGIN_DIR . 'src/Core/Deactivator.php';
	Core\Deactivator::deactivate();

	// Also deactivate Pro plugin if active (Pro depends on Free).
	$pro_plugin = 'wp-sell-services-pro/wp-sell-services-pro.php';
	if ( is_plugin_active( $pro_plugin ) ) {
		deactivate_plugins( $pro_plugin );
	}
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\wpss_deactivate' );
