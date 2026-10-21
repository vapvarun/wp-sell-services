<?php
/**
 * A vendor can choose a time zone: it saves through the shared builder and REST, only real zones are kept,
 * and every time the vendor sees follows it.
 *
 * Run: wp eval-file tests/test-vendor-timezone.php
 *
 * Seeds one vendor (prefix QA-RFT); removes it after.
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

$check( 'builder keeps a real zone', 'Asia/Kolkata' === ( wpss_build_vendor_profile_update( array( 'timezone' => 'Asia/Kolkata' ), 0, 0 )['timezone'] ?? null ) );
$check( 'builder clears an invented zone instead of storing it', '' === ( wpss_build_vendor_profile_update( array( 'timezone' => 'Mars/Olympus' ), 0, 0 )['timezone'] ?? null ) );
$check( 'builder clears a UTC offset (wp_date would not take it as a name)', '' === ( wpss_build_vendor_profile_update( array( 'timezone' => 'UTC+5:30' ), 0, 0 )['timezone'] ?? null ) );
$check( 'builder leaves the zone alone when not sent', ! array_key_exists( 'timezone', wpss_build_vendor_profile_update( array( 'city' => 'x' ), 0, 0 ) ) );

$uid = wp_insert_user( array( 'user_login' => 'qa_rft_tz' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-tz' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $uid, 'display_name' => 'QA-RFT tz', 'status' => 'active' ) );
wp_set_current_user( $uid );

$site = wp_timezone_string();
$check( 'no zone chosen: times use the site zone', $site === wpss_display_timezone( $uid )->getName() );

$req = new WP_REST_Request( 'PUT', '/wpss/v1/vendors/me' );
$req->set_param( 'timezone', 'Pacific/Auckland' );
$res = rest_do_request( $req );
$check( 'REST PUT /vendors/me saves the zone (status ' . $res->get_status() . ')', 200 === $res->get_status() );
$row = $wpdb->get_var( $wpdb->prepare( "SELECT timezone FROM {$wpdb->prefix}wpss_vendor_profiles WHERE user_id = %d", $uid ) );
$check( 'the zone is stored on the profile row', 'Pacific/Auckland' === $row );

$check( 'wpss_display_timezone follows the vendor (Pacific/Auckland, got ' . wpss_display_timezone( $uid )->getName() . ')', 'Pacific/Auckland' === wpss_display_timezone( $uid )->getName() );
$other = wp_insert_user( array( 'user_login' => 'qa_rft_tz2' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-tz2' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$check( 'another member keeps the site zone', $site === wpss_display_timezone( $other )->getName() );
$stamp = '2026-01-01 00:00:00'; // UTC midnight is 13:00 in Auckland (NZDT, +13).
$check( 'a stored UTC time reads in the vendor zone (got ' . wpss_date_label( $stamp, '-', 'H:i', $uid ) . ')', '13:00' === wpss_date_label( $stamp, '-', 'H:i', $uid ) );
$check( 'the same time for another member is not 13:00', '13:00' !== wpss_date_label( $stamp, '-', 'H:i', $other ) || 'Pacific/Auckland' === $site );

$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $uid ) );
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );
wp_delete_user( $other );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
