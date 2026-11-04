<?php
/**
 * Tests never call the live store.
 *
 * Switches off the free plugin's preset-key activation and refuses every request
 * to wbcomdesigns.com and updates.wbcomdesigns.com before it leaves the machine.
 * Without it, every CI run activated the preset key on the live store as
 * http://localhost and sent the owner a licence alert each time.
 *
 * Loaded by tests/bootstrap.php (Free PHPUnit), by Pro's tests/bootstrap.php,
 * and copied into wp-content/mu-plugins by the CI job that runs the
 * `wp eval-file` contract scripts. tests/Integration/NoLiveStoreTest.php fails
 * if it stops loading.
 *
 * @package WPSellServices\Tests
 */

if ( ! defined( 'WPSS_NO_LICENSE_PHONE_HOME' ) ) {
	define( 'WPSS_NO_LICENSE_PHONE_HOME', true );
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return in_array( $host, array( 'wbcomdesigns.com', 'updates.wbcomdesigns.com' ), true )
			? new WP_Error( 'wpss_tests_no_store', 'Tests do not call the live store.' )
			: $pre;
	},
	1,
	3
);
