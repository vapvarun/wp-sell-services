<?php
/**
 * PHPUnit bootstrap file for WP Sell Services.
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

// Try to load WordPress test library.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wp_tests_dir ) {
	// Try common locations.
	$possible_paths = array(
		'/tmp/wordpress-tests-lib',
		dirname( $plugin_dir, 4 ) . '/tests/phpunit',
		getenv( 'HOME' ) . '/wordpress-tests-lib',
	);

	foreach ( $possible_paths as $path ) {
		if ( file_exists( $path . '/includes/functions.php' ) ) {
			$wp_tests_dir = $path;
			break;
		}
	}
}

if ( $wp_tests_dir && file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	// WordPress test framework available - load it.

	// Give access to tests_add_filter() function.
	require_once $wp_tests_dir . '/includes/functions.php';

	/**
	 * Manually load the plugin being tested.
	 */
	tests_add_filter(
		'muplugins_loaded',
		function () use ( $plugin_dir ): void {
			require $plugin_dir . '/wp-sell-services.php';
		}
	);

	// Start up the WP testing environment.
	require $wp_tests_dir . '/includes/bootstrap.php';

	echo "WordPress test framework loaded.\n";
} else {
	// No WordPress test framework - run in standalone mode.
	// Load WordPress stubs for type hints to work.

	echo "Running in standalone mode (no WordPress test framework).\n";
	echo "For full integration tests, set WP_TESTS_DIR environment variable.\n\n";

	// Define WordPress constants if not defined.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( $plugin_dir, 3 ) . '/' );
	}

	// Load WordPress functions stub for standalone testing.
	require_once __DIR__ . '/stubs/wordpress-stubs.php';
}

// Load test base classes.
require_once __DIR__ . '/TestCase.php';

echo "WP Sell Services test bootstrap loaded.\n";
