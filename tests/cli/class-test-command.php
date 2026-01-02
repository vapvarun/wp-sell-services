<?php
/**
 * WP-CLI command for running WP Sell Services tests.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\CLI;

use WP_CLI;
use WP_CLI_Command;

/**
 * Run WP Sell Services tests and gap detection.
 */
class Test_Command extends WP_CLI_Command {

	/**
	 * Run the test suite.
	 *
	 * ## OPTIONS
	 *
	 * [<suite>]
	 * : Test suite to run (unit, integration, api, gaps). Default: all.
	 *
	 * [--filter=<filter>]
	 * : Filter tests by name.
	 *
	 * [--testdox]
	 * : Output in testdox format.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run all tests
	 *     wp wpss test run
	 *
	 *     # Run only integration tests
	 *     wp wpss test run integration
	 *
	 *     # Run gap detection
	 *     wp wpss test run gaps
	 *
	 *     # Run specific test
	 *     wp wpss test run --filter=test_create_simple_service
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		$plugin_dir = dirname( __DIR__, 2 );
		$phpunit    = $plugin_dir . '/vendor/bin/phpunit';

		if ( ! file_exists( $phpunit ) ) {
			WP_CLI::error( 'PHPUnit not found. Run: composer install' );
		}

		// Get PHP binary path (works with Local by Flywheel).
		$php_binary = PHP_BINARY;
		if ( empty( $php_binary ) || ! file_exists( $php_binary ) ) {
			$php_binary = 'php'; // Fallback.
		}

		$suite    = $args[0] ?? 'all';
		$test_dir = '';

		// Add suite-specific path.
		switch ( $suite ) {
			case 'unit':
				$test_dir = 'tests/Unit';
				break;
			case 'integration':
				$test_dir = 'tests/Integration';
				break;
			case 'api':
				$test_dir = 'tests/API';
				break;
			case 'gaps':
				$test_dir = 'tests/Integration/FunctionalityGapTest.php';
				break;
			case 'all':
			default:
				// Run all tests.
				break;
		}

		// Build command with proper escaping.
		// Use PHP binary directly to avoid env issues with Local.
		$cmd_parts = array(
			escapeshellarg( $php_binary ),
			escapeshellarg( $phpunit ),
		);

		if ( $test_dir ) {
			$cmd_parts[] = escapeshellarg( $test_dir );
		}

		// Add filter if provided.
		if ( ! empty( $assoc_args['filter'] ) ) {
			$cmd_parts[] = '--filter=' . escapeshellarg( $assoc_args['filter'] );
		}

		// Add testdox format.
		if ( isset( $assoc_args['testdox'] ) ) {
			$cmd_parts[] = '--testdox';
		}

		$cmd = implode( ' ', $cmd_parts );

		WP_CLI::log( "Running tests from: {$plugin_dir}" );
		WP_CLI::log( '' );

		// Change to plugin directory and run.
		$escaped_dir = escapeshellarg( $plugin_dir );
		$result      = WP_CLI::launch( "cd {$escaped_dir} && {$cmd}", false, true );

		echo $result->stdout;

		if ( ! empty( $result->stderr ) ) {
			WP_CLI::warning( $result->stderr );
		}

		if ( $result->return_code !== 0 ) {
			WP_CLI::error( 'Tests failed.', false );
		} else {
			WP_CLI::success( 'All tests passed!' );
		}
	}

	/**
	 * Show functionality gaps in the plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss test gaps
	 *
	 * @return void
	 */
	public function gaps(): void {
		$this->run( array( 'gaps' ), array( 'testdox' => true ) );
	}

	/**
	 * Check database tables exist.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss test tables
	 *
	 * @return void
	 */
	public function tables(): void {
		global $wpdb;

		// Note: Messages stored in conversations table (merged design).
		$tables = array(
			'wpss_orders'           => 'Service orders',
			'wpss_service_packages' => 'Service packages',
			'wpss_service_addons'   => 'Service add-ons',
			'wpss_conversations'    => 'Conversations (includes messages)',
			'wpss_deliveries'       => 'Deliveries',
			'wpss_reviews'          => 'Reviews',
			'wpss_disputes'         => 'Disputes',
			'wpss_vendor_profiles'  => 'Vendor profiles',
		);

		WP_CLI::log( 'Checking database tables...' );
		WP_CLI::log( '' );

		$found   = 0;
		$missing = 0;

		foreach ( $tables as $table => $description ) {
			$full_table = $wpdb->prefix . $table;
			$exists     = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table )
			);

			if ( $exists ) {
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table}" );
				WP_CLI::log( WP_CLI::colorize( "%g✓%n {$full_table} ({$count} rows) - {$description}" ) );
				++$found;
			} else {
				WP_CLI::log( WP_CLI::colorize( "%r✗%n {$full_table} - {$description} (MISSING)" ) );
				++$missing;
			}
		}

		WP_CLI::log( '' );
		WP_CLI::log( "Found: {$found}, Missing: {$missing}" );
	}

	/**
	 * Create test data for manual testing.
	 *
	 * ## OPTIONS
	 *
	 * [--services=<count>]
	 * : Number of test services to create. Default: 5.
	 *
	 * [--orders=<count>]
	 * : Number of test orders to create. Default: 3.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create default test data
	 *     wp wpss test seed
	 *
	 *     # Create 10 services and 5 orders
	 *     wp wpss test seed --services=10 --orders=5
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function seed( array $args, array $assoc_args ): void {
		$service_count = (int) ( $assoc_args['services'] ?? 5 );
		$order_count   = (int) ( $assoc_args['orders'] ?? 3 );

		WP_CLI::log( "Creating {$service_count} test services..." );

		// Check if ServiceManager exists.
		if ( ! class_exists( 'WPSellServices\\Services\\ServiceManager' ) ) {
			WP_CLI::error( 'ServiceManager class not found. Plugin may not be fully loaded.' );
		}

		// Create vendor user if needed.
		$vendor = get_user_by( 'login', 'test_vendor' );
		if ( ! $vendor ) {
			$vendor_id = wp_create_user( 'test_vendor', 'test123', 'vendor@test.local' );
			if ( is_wp_error( $vendor_id ) ) {
				WP_CLI::error( 'Could not create test vendor user.' );
			}
			$vendor = get_user_by( 'ID', $vendor_id );
			WP_CLI::log( "Created test vendor: test_vendor" );
		}

		// Create customer user if needed.
		$customer = get_user_by( 'login', 'test_customer' );
		if ( ! $customer ) {
			$customer_id = wp_create_user( 'test_customer', 'test123', 'customer@test.local' );
			if ( is_wp_error( $customer_id ) ) {
				WP_CLI::error( 'Could not create test customer user.' );
			}
			$customer = get_user_by( 'ID', $customer_id );
			WP_CLI::log( "Created test customer: test_customer" );
		}

		// Create test services.
		$service_titles = array(
			'Professional Logo Design',
			'WordPress Website Development',
			'SEO Optimization Service',
			'Social Media Marketing',
			'Content Writing Service',
			'Video Editing Service',
			'Mobile App Development',
			'Graphic Design Package',
			'Virtual Assistant Services',
			'Translation Services',
		);

		for ( $i = 0; $i < $service_count; $i++ ) {
			$title = $service_titles[ $i % count( $service_titles ) ];
			if ( $i >= count( $service_titles ) ) {
				$title .= ' ' . ( $i + 1 );
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => 'Test service description for ' . $title,
					'post_status'  => 'publish',
					'post_type'    => 'wpss_service',
					'post_author'  => $vendor->ID,
				)
			);

			if ( ! is_wp_error( $post_id ) ) {
				WP_CLI::log( "  Created service: {$title} (ID: {$post_id})" );
			}
		}

		WP_CLI::success( "Created {$service_count} test services and test users." );
	}

	/**
	 * Clean up test data.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss test clean --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function clean( array $args, array $assoc_args ): void {
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( 'This will delete all test data. Continue?' );
		}

		// Delete test users.
		$test_users = array( 'test_vendor', 'test_customer' );
		foreach ( $test_users as $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user ) {
				wp_delete_user( $user->ID );
				WP_CLI::log( "Deleted user: {$login}" );
			}
		}

		// Delete test services.
		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$count = count( $services );
		foreach ( $services as $id ) {
			wp_delete_post( $id, true );
		}

		WP_CLI::success( "Cleaned up {$count} services and test users." );
	}
}

// Register command if WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'wpss test', __NAMESPACE__ . '\\Test_Command' );
}
