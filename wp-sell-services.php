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
 * Plugin URI:        https://wbcomdesigns.com/downloads/wp-sell-services/
 * Description:       A complete Fiverr-style service marketplace platform for WordPress. Create a service marketplace with built-in standalone checkout, order management, messaging, reviews, and more.
 * Version:           1.3.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Wbcom Designs
 * Author URI:        https://wbcomdesigns.com
 * Text Domain:       wp-sell-services
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wbcomdesigns.com/downloads/wp-sell-services/
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
define( 'WPSS_VERSION', '1.3.1' );

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
 * Load Action Scheduler at file-load time.
 *
 * Action Scheduler bootstraps its own internals on `plugins_loaded` at
 * priority 1 — registering the `every_minute` cron interval used by its
 * queue runner. If we require it from a later hook (e.g. plugins_loaded:10
 * inside our own init) AS's bootstrap never runs, its queue runner can't
 * reschedule itself, and WordPress logs:
 *   Cron reschedule event error for hook: action_scheduler_run_queue,
 *   Error code: invalid_schedule
 *
 * The require has to happen while WordPress is loading our plugin file
 * (before any `plugins_loaded` hook fires) so AS's own hook registration
 * beats its bootstrap hook. Keep this above every other include.
 */
$wpss_action_scheduler = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( file_exists( $wpss_action_scheduler ) ) {
	require_once $wpss_action_scheduler;
}
unset( $wpss_action_scheduler );

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
 * Display admin notice when vendor/autoload.php is missing entirely.
 *
 * @return void
 */
function wpss_vendor_missing_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'WP Sell Services failed to load.', 'wp-sell-services' ); ?></strong>
			<?php esc_html_e( 'The plugin\'s Composer autoloader is missing. Re-download the plugin ZIP from the official release page or run `composer install` from the plugin directory if installing from source.', 'wp-sell-services' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Display admin notice when vendor/autoload.php references files that
 * don't exist (corrupt/dev autoloader shipped without the dev packages —
 * Basecamp #9828326478).
 *
 * @return void
 */
function wpss_vendor_corrupt_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'WP Sell Services failed to load.', 'wp-sell-services' ); ?></strong>
			<?php esc_html_e( 'The plugin\'s Composer autoloader references files that are not present in this build. This usually means a release ZIP was packaged with development dependencies still referenced in the autoloader. Re-download the latest plugin ZIP from the official release page.', 'wp-sell-services' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Load Composer autoloader safely.
 *
 * Belt-and-braces guard: if the bundled autoloader is missing OR if it
 * references files that aren't on disk (e.g. a release ZIP that shipped
 * the dev autoloader without the dev vendor packages — see Basecamp
 * #9828326478), we surface an admin notice instead of fataling out on
 * `register_activation_hook`. The release pipeline is the real fix
 * (Gruntfile composer:nodev + verify:dist-autoloader); this guard just
 * keeps a corrupt install from white-screening the site.
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
		add_action( 'admin_notices', __NAMESPACE__ . '\\wpss_vendor_missing_notice' );
		$loaded = false;
		return false;
	}

	// Pre-flight check: the generated autoloader eagerly `require`s every
	// file in $files (PHP polyfills, helpers, etc.). If the ZIP was built
	// without `composer install --no-dev` regenerating the autoloader,
	// $files will reference dev-only paths that don't exist — fatal on
	// the first `require`. Validate them before triggering the autoloader.
	$autoload_files = WPSS_PLUGIN_DIR . 'vendor/composer/autoload_files.php';
	if ( file_exists( $autoload_files ) ) {
		$files = include $autoload_files;
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( ! is_string( $file ) || ! file_exists( $file ) ) {
					add_action( 'admin_notices', __NAMESPACE__ . '\\wpss_vendor_corrupt_notice' );
					$loaded = false;
					return false;
				}
			}
		}
	}

	require_once $autoloader;

	// Note: Action Scheduler itself is required at the top of this plugin
	// file (outside any hook) so its plugins_loaded:1 bootstrap fires — see
	// the require_once block near the WPSS_* constants. Loading AS from
	// here would be too late.

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

	// Respect the guard's verdict. It pre-flights every file the autoloader
	// eagerly requires and returns false (having queued an admin notice) when
	// one is missing — but this caller discarded that and carried on to
	// `require_once src/Core/Plugin.php`, which then fataled on the first
	// namespaced class. The notice written to prevent a white screen was being
	// rendered underneath one. See Basecamp #9828326478.
	if ( ! wpss_load_composer_autoloader() ) {
		return;
	}

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

	// Only migrate users who explicitly had WooCommerce selected.
	// 'auto' is the default for fresh installs — no migration needed.
	if ( 'woocommerce' === $platform ) {
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

	// If the autoloader is missing or corrupt, abort activation cleanly
	// rather than continuing on and fataling inside Activator::activate()
	// when it tries to resolve a namespaced class. See Basecamp #9828326478.
	if ( ! wpss_load_composer_autoloader() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'WP Sell Services could not be activated: its Composer autoloader is missing or corrupt. Please re-download the plugin ZIP from the official release page.', 'wp-sell-services' ),
			esc_html__( 'Plugin Activation Error', 'wp-sell-services' ),
			array( 'back_link' => true )
		);
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
	// Same reason as wpss_init(): Deactivator is a namespaced class, so
	// continuing past a failed autoloader turns "deactivate the broken plugin"
	// — the one action left to a site owner in this state — into a fatal.
	if ( ! wpss_load_composer_autoloader() ) {
		return;
	}

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

// Daily audit log retention cleanup. No-op unless the
// wpss_audit_log_retention_days option is set to a positive value.
add_action(
	'wpss_audit_log_cleanup',
	static function (): void {
		if ( class_exists( Services\AuditLogService::class ) ) {
			( new Services\AuditLogService() )->cleanup_expired();
		}
	}
);

// ---------------------------------------------------------------------------
// EDD Software Licensing SDK — automatic updates for free and Pro.
//
// The SDK is vendored at libs/edd-sl-sdk and is the single source of truth for
// the product family: WP Sell Services Pro registers its own product on the
// same registry hook and requires this same file (require_once makes the double
// load safe). It is NOT a Composer dependency — see
// libs/edd-sl-sdk/WBCOM-PATCHES.md for the three patches we carry and the rule
// that keeps it out of vendor/.
//
// The free product ships with a preset, unlimited-activation key so updates
// work with zero customer setup. License state never gates functionality — it
// only authorises update downloads.
// ---------------------------------------------------------------------------

/**
 * Preset license key for the free plugin (unlimited activations).
 *
 * Shared by the SDK registration and the first-run activation below so the two
 * can never drift apart.
 */
const WPSS_PRESET_LICENSE_KEY = 'wbcomfree3c8a1f7e5d2b9a4c6e0f1d8b7a2c9e66';

/**
 * EDD download ID for the free plugin on wbcomdesigns.com.
 */
const WPSS_EDD_ITEM_ID = 1660955;

add_action(
	'edd_sl_sdk_registry',
	function ( $registry ) {
		$registry->register(
			array(
				'id'          => 'wp-sell-services',
				'url'         => 'https://wbcomdesigns.com',
				'item_id'     => WPSS_EDD_ITEM_ID,
				'version'     => WPSS_VERSION,
				'file'        => WPSS_PLUGIN_FILE,
				'license'     => WPSS_PRESET_LICENSE_KEY,
				'option_name' => 'wpss_license_key',
			)
		);
	}
);

// Load the vendored SDK only when the package is COMPLETE. A partial build or
// extract that keeps the entry file but drops libs/edd-sl-sdk/src would fatal
// inside the SDK the moment it instantiates a src class (the failure mode that
// has bitten stripped bundled-SDK releases). Guard on the source being present
// and degrade to "updates disabled" with a soft admin notice instead of a white
// screen — licensing only gates updates, never features, so the marketplace
// keeps working.
if ( file_exists( WPSS_PLUGIN_DIR . 'libs/edd-sl-sdk/edd-sl-sdk.php' )
	&& file_exists( WPSS_PLUGIN_DIR . 'libs/edd-sl-sdk/src/Versions.php' ) ) {
	require_once WPSS_PLUGIN_DIR . 'libs/edd-sl-sdk/edd-sl-sdk.php';
} elseif ( is_admin() ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'WP Sell Services: the bundled updater library is incomplete, so automatic updates are disabled. Re-install the plugin from a complete zip. All marketplace features continue to work.', 'wp-sell-services' );
			echo '</p></div>';
		}
	);
}

// Activate the preset key once so the store recognises this site for updates.
//
// Runs at most once per day until it succeeds: without the backoff an
// unreachable store meant a blocking 15-second POST on EVERY admin page load,
// forever.
add_action(
	'admin_init',
	function () {
		$activated = 'wpss_preset_activated';
		$attempted = 'wpss_preset_activation_retry';

		if ( get_option( $activated ) || get_transient( $attempted ) ) {
			return;
		}

		// Claim the window up front so a failure (or a fatal mid-request)
		// cannot produce a retry storm across concurrent admin requests.
		set_transient( $attempted, 1, DAY_IN_SECONDS );

		update_option( 'wpss_license_key', WPSS_PRESET_LICENSE_KEY, false );

		$response = wp_remote_post(
			'https://wbcomdesigns.com',
			array(
				'timeout' => 15,
				'body'    => array(
					'edd_action' => 'activate_license',
					'license'    => WPSS_PRESET_LICENSE_KEY,
					'item_id'    => WPSS_EDD_ITEM_ID,
					'url'        => home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 'valid' !== ( $body['license'] ?? '' ) ) {
			return;
		}

		update_option( $activated, 1, false );
		delete_transient( $attempted );

		// Auto-enable usage tracking checkbox.
		update_option(
			'wpss_license_key_allow_tracking',
			array(
				'allowed'   => true,
				'timestamp' => time(),
			),
			false
		);
	}
);
