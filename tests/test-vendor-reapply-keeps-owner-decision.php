<?php
/**
 * A seller the owner suspended or rejected cannot reinstate themselves by applying again,
 * and a member whose account is blocked cannot apply: VendorService::register(), the REST
 * door POST /wpss/v1/vendors/register and the service wrapper all refuse, REST with a 403.
 *
 * Run: wp eval-file tests/test-vendor-reapply-keeps-owner-decision.php
 *
 * Uses throwaway users (swept by the harness) and deletes their profile rows.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\VendorService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( 'closed' === wpss_get_option( 'vendor', 'vendor_registration' ) ) {
	echo "SKIP  vendor registration is closed on this site\n";
	return;
}

$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$users    = array();
$mk       = static function ( string $n ) use ( &$users ) {
	$id      = wp_insert_user( array( 'user_login' => "qa_reapply_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-reapply-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
	$users[] = (int) $id;
	return (int) $id;
};
register_shutdown_function(
	static function () use ( &$users, $wpdb, $profiles ) {
		foreach ( $users as $id ) {
			$wpdb->delete( $profiles, array( 'user_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
);
$status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$profiles} WHERE user_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rest   = static function ( int $user_id ) {
	wp_set_current_user( $user_id );
	$res = rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1/vendors/register' ) );
	wp_set_current_user( 0 );
	return $res;
};

foreach ( array( 'suspended', 'rejected' ) as $state ) {
	$uid = $mk( $state );
	$wpdb->insert( $profiles, array( 'user_id' => $uid, 'display_name' => "QA reapply {$state}", 'status' => $state ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	\WPSellServices\Models\VendorProfile::flush_memo( $uid );

	$ok = ( new VendorService() )->register( $uid );
	\WPSellServices\Models\VendorProfile::flush_memo( $uid );
	$check( "{$state}: register() refuses", false === $ok );
	$check( "{$state}: the profile keeps the owner's decision", $state === $status( $uid ), $status( $uid ) );
	$check( "{$state}: no vendor role was granted", ! in_array( VendorService::ROLE, (array) get_userdata( $uid )->roles, true ) );

	$wrapped = ( new VendorService() )->register_vendor( $uid );
	$check( "{$state}: register_vendor() says why", empty( $wrapped['success'] ) && false !== stripos( (string) $wrapped['message'], 'vendor account' ), (string) ( $wrapped['message'] ?? '' ) );

	$res = $rest( $uid );
	$check( "{$state}: REST register answers 403", 403 === $res->get_status(), $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
	\WPSellServices\Models\VendorProfile::flush_memo( $uid );
	$check( "{$state}: still {$state} after the REST attempt", $state === $status( $uid ), $status( $uid ) );
}

// A member the owner banned has no profile to protect, but may not start selling either.
$banned = $mk( 'banned' );
update_user_meta( $banned, 'wpss_account_status', 'banned' );
$res = $rest( $banned );
$check( 'banned member: REST register answers 403', 403 === $res->get_status(), $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
$check( 'banned member: no profile was written', '' === $status( $banned ) );

// Not over-blocked: a member in good standing with no profile can still apply.
$fresh = $mk( 'fresh' );
$check( 'good standing, no profile: register() succeeds', true === ( new VendorService() )->register( $fresh ) );
$check( 'good standing: a profile now exists', in_array( $status( $fresh ), array( 'active', 'pending' ), true ), $status( $fresh ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
