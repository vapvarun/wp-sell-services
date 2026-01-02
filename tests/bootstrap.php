<?php
/**
 * PHPUnit bootstrap file for WP Sell Services.
 *
 * Loads the actual WordPress installation from Local by Flywheel.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests;

// Define testing constant.
if ( ! defined( 'WPSS_TESTING' ) ) {
	define( 'WPSS_TESTING', true );
}

// Get the plugin directory.
$plugin_dir = dirname( __DIR__ );

// Load Composer autoloader.
$autoloader = $plugin_dir . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	echo "Composer autoloader not found. Run 'composer install' first.\n";
	exit( 1 );
}
require_once $autoloader;

/*
 * Since we're always in a WordPress environment (Local by Flywheel),
 * load WordPress directly from the installation.
 */

// Find wp-load.php by going up from plugin directory.
$wp_load = dirname( $plugin_dir, 3 ) . '/wp-load.php';

if ( file_exists( $wp_load ) ) {
	// Prevent redirects during testing.
	$_SERVER['REQUEST_URI']  = '/';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['HTTP_HOST']    = 'localhost';

	// Load WordPress.
	require_once $wp_load;

	echo "WordPress loaded from: " . ABSPATH . "\n";
	echo "WordPress version: " . get_bloginfo( 'version' ) . "\n";
	echo "Plugin: WP Sell Services\n";

	// Verify plugin is active.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = 'wp-sell-services/wp-sell-services.php';
	if ( is_plugin_active( $plugin_file ) ) {
		echo "Plugin status: Active\n";
	} else {
		echo "Plugin status: Inactive - activating...\n";
		activate_plugin( $plugin_file );
	}

	// Set up test user context.
	$admin_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( ! empty( $admin_user ) ) {
		wp_set_current_user( $admin_user[0]->ID );
		echo "Test user: " . $admin_user[0]->user_login . " (admin)\n";
	}

	echo "\n";
} else {
	// Fallback to standalone mode with stubs.
	echo "WordPress not found at: {$wp_load}\n";
	echo "Running in standalone mode with stubs.\n\n";

	// Define WordPress constants if not defined.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( $plugin_dir, 3 ) . '/' );
	}

	// Load WordPress functions stub for standalone testing.
	require_once __DIR__ . '/stubs/wordpress-stubs.php';
}

// Load test base classes.
require_once __DIR__ . '/TestCase.php';

echo "WP Sell Services test bootstrap loaded.\n\n";
