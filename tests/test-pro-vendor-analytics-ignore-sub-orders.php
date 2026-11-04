<?php
/**
 * Pro's vendor analytics count orders, not the tips, extensions and milestone
 * phases paid on them (overview totals and per-service totals).
 *
 * Run: wp eval-file tests/test-pro-vendor-analytics-ignore-sub-orders.php
 *
 * Needs Pro active (SKIPs otherwise). Clones the latest tip and extension into
 * QA rows for the same vendor and deletes them on any exit.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\Analytics\AnalyticsManager;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

if ( ! class_exists( AnalyticsManager::class ) ) {
	echo "SKIP  Pro analytics not loaded\n";
	return;
}

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$cloned = array();

$restore = static function () use ( $wpdb, $orders, &$cloned ) {
	foreach ( $cloned as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	AnalyticsManager::flush_cache();
};

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$vendor_id = (int) $wpdb->get_var( "SELECT vendor_id FROM {$orders} WHERE platform = 'tip' AND vendor_id IN (SELECT ID FROM {$wpdb->users}) ORDER BY id DESC LIMIT 1" );
$check( 'a vendor with a tip order exists', $vendor_id > 0 );
if ( ! $vendor_id ) {
	return;
}

wp_set_current_user( $vendor_id );

$call = static function ( string $route ) use ( $vendor_id ) {
	AnalyticsManager::flush_cache();
	$request = new WP_REST_Request( 'GET', '/wpss/v1/analytics/vendor/' . $route );
	$request->set_param( 'period', '30days' );
	return rest_do_request( $request )->get_data();
};

$before   = $call( 'overview' );
$services = $call( 'services' );

// One sub-order of each kind, today, completed, for a service of this vendor.
foreach ( array( 'tip', 'extension' ) as $platform ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders} WHERE platform = %s AND vendor_id = %d ORDER BY id DESC LIMIT 1", $platform, $vendor_id ), ARRAY_A );
	if ( ! $row ) {
		continue;
	}
	unset( $row['id'] );
	$row['order_number'] = 'QA-SUB-' . strtoupper( $platform ) . '-' . wp_generate_password( 5, false );
	$row['status']       = 'completed';
	$row['created_at']   = gmdate( 'Y-m-d H:i:s' );
	$wpdb->insert( $orders, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$cloned[] = (int) $wpdb->insert_id;
}
$check( 'QA sub-orders were created', count( $cloned ) > 0 );

$after       = $call( 'overview' );
$services_af = $call( 'services' );

$sum = static fn( $payload ) => array_sum( array_map( static fn( $r ) => (int) ( $r['total_orders'] ?? 0 ), (array) ( $payload['services'] ?? $payload ) ) );

$check( 'overview total_orders unchanged by tips and extensions', (int) $before['total_orders'] === (int) $after['total_orders'], $before['total_orders'] . ' -> ' . $after['total_orders'] );
$check( 'overview completed unchanged by tips and extensions', (int) $before['completed'] === (int) $after['completed'], $before['completed'] . ' -> ' . $after['completed'] );
$check( 'per-service total_orders unchanged by tips and extensions', $sum( $services ) === $sum( $services_af ), $sum( $services ) . ' -> ' . $sum( $services_af ) );
