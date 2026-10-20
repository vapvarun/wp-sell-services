<?php
/**
 * The vendor profile's order counts use the sales list's rule: tips and extensions are not orders.
 *
 * Run: wp eval-file tests/test-vendor-profile-order-counts.php
 *
 * Seeds one vendor with 2 completed orders and 2 completed sub-orders (extension, tip), removes it after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Database\Repositories\VendorProfileRepository;

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$vendor = wp_insert_user( array( 'user_login' => 'qa_rft_counts_' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-counts-' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$orders = $wpdb->prefix . 'wpss_orders';
foreach ( array( 'standalone', 'standalone', 'extension', 'tip' ) as $i => $platform ) {
	$wpdb->insert( $orders, array( 'order_number' => "QA-RFT-CNT-{$vendor}-{$i}", 'customer_id' => 1, 'vendor_id' => $vendor, 'service_id' => 1, 'platform' => $platform, 'status' => 'completed', 'subtotal' => 10, 'total' => 10 ) );
}

$repo = new VendorProfileRepository();
$repo->update_stats( $vendor );
$profile = $repo->get_by_user( $vendor );

$check( 'completed_orders counts the 2 orders, not the 2 sub-orders', 2 === (int) ( $profile->completed_orders ?? -1 ) );
$check( 'total_orders counts the 2 orders', 2 === (int) ( $profile->total_orders ?? -1 ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders} WHERE vendor_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) ); // phpcs:ignore WordPress.DB
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor );

exit( $fails ? 1 : 0 );
