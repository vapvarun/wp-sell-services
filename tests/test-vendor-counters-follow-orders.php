<?php
/**
 * A vendor's stored order counters follow orders at once (Basecamp 10431939228).
 *
 * Run: wp eval-file tests/test-vendor-counters-follow-orders.php
 *
 * Only the 12-hour sweep refreshed total_orders / completed_orders, so a new sale - and every
 * seeded order - read 0 on the profile, directory, admin and REST until it ran.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$orders  = $wpdb->prefix . 'wpss_orders';
$profile = static function ( int $vendor_id ) use ( $wpdb ): array {
	return (array) $wpdb->get_row( $wpdb->prepare( "SELECT total_orders, completed_orders FROM {$wpdb->prefix}wpss_vendor_profiles WHERE user_id = %d", $vendor_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
};

// Copy a real order so every required column is filled the way the plugin fills it.
$template = $wpdb->get_row( "SELECT * FROM {$orders} WHERE status = 'in_progress' AND COALESCE( platform, '' ) NOT IN ( 'tip', 'extension', 'milestone' ) ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
if ( ! $template ) {
	echo "SKIP  no in_progress order to copy\n";
	return;
}

// Run only the stats listeners (priority 30): the rest would email and notify about a throwaway order.
global $wp_filter;
$saved = array();
foreach ( array( 'wpss_order_created', 'wpss_order_status_changed' ) as $hook ) {
	$saved[ $hook ] = $wp_filter[ $hook ]->callbacks;
	$wp_filter[ $hook ]->callbacks = array_intersect_key( $saved[ $hook ], array( 30 => true ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
}

$vendor_id = (int) $template['vendor_id'];
( new \WPSellServices\Services\VendorService() )->update_stats( $vendor_id );
$before = $profile( $vendor_id );

unset( $template['id'] );
$template['order_number'] = 'TEST-COUNTERS-' . wp_generate_password( 6, false );
$wpdb->insert( $orders, $template ); // phpcs:ignore WordPress.DB
$order_id = (int) $wpdb->insert_id;

try {
	wpss_after_order_created( $order_id, array() );
	$after_create = $profile( $vendor_id );
	$check( 'a new order counts toward orders placed straight away', (int) $after_create['total_orders'] === (int) $before['total_orders'] + 1 );

	$wpdb->update( $orders, array( 'status' => 'completed' ), array( 'id' => $order_id ) ); // phpcs:ignore WordPress.DB
	wp_cache_flush();
	do_action( 'wpss_order_status_changed', $order_id, 'completed', 'in_progress' );
	$after_complete = $profile( $vendor_id );
	$check( 'a completed order counts toward completed orders straight away', (int) $after_complete['completed_orders'] === (int) $before['completed_orders'] + 1 );

	$seeder = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Demo/MarketplaceSeeder.php' );
	$check( 'the seeder refreshes counters through the production writer', str_contains( $seeder, '->update_stats( $vendor_id )' ) && ! str_contains( $seeder, "'completed_orders'      =>" ) );
} finally {
	foreach ( $saved as $hook => $callbacks ) {
		$wp_filter[ $hook ]->callbacks = $callbacks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}
	$wpdb->delete( $orders, array( 'id' => $order_id ) ); // phpcs:ignore WordPress.DB
	wp_cache_flush();
	( new \WPSellServices\Services\VendorService() )->update_stats( $vendor_id );
	if ( $profile( $vendor_id ) != $before ) { // phpcs:ignore Universal.Operators.StrictComparisons
		echo "WARN  counters did not return to their starting values after cleanup\n";
	}
}

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
