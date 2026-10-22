<?php
/**
 * The service list's thumbnail column renders without a list query behind it
 * (the Quick Edit row refresh), with no PHP warning.
 *
 * Run: wp eval-file tests/test-service-list-column-without-query.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$warnings = array();
set_error_handler(
	static function ( $no, $str ) use ( &$warnings ) {
		$warnings[] = $str;
		return true;
	}
);

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$service_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' LIMIT 1" );

$GLOBALS['wp_query'] = new WP_Query(); // No posts: a fresh query, as in admin-ajax inline-save.

ob_start();
( new \WPSellServices\Admin\ServiceListScreen() )->render_column( 'wpss_thumb', $service_id );
ob_end_clean();
restore_error_handler();

$ok = array() === $warnings;
echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . 'thumbnail column renders without a list query' . ( $ok ? '' : ' - ' . $warnings[0] ) . "\n";
exit( $ok ? 0 : 1 );
