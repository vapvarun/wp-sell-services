<?php
/**
 * GET /wpss/v1/vendors/me/level reports the vendor's real earnings, the same ledger total the Sales tile shows.
 *
 * Run: wp eval-file tests/test-seller-levels-rest-earnings.php
 *
 * Seeds one vendor (prefix QA-RFT) with a 42.50 credit; removes it after.
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

$uid = wp_insert_user( array( 'user_login' => 'qa_rft_lv' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-lv' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $uid, 'display_name' => 'QA-RFT level', 'status' => 'active' ) );
$wpdb->insert( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $uid, 'type' => 'credit', 'amount' => 42.5, 'balance_after' => 42.5, 'status' => 'completed' ) );

wp_set_current_user( $uid );
$res  = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/vendors/me/level' ) );
$data = $res->get_data();
$got  = $data['stats']['total_earnings'] ?? ( $data['progress']['stats']['total_earnings'] ?? null );
if ( null === $got ) {
	array_walk_recursive( $data, static function ( $v, $k ) use ( &$got ) { if ( 'total_earnings' === $k ) { $got = $v; } } );
}
$check( 'REST answers 200 for an active vendor', 200 === $res->get_status() );
$check( 'total_earnings is the ledger total (42.5), got ' . var_export( $got, true ), null !== $got && abs( (float) $got - 42.5 ) < 0.001 );

$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $uid ) );
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $uid ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
