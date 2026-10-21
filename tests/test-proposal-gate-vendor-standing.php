<?php
/**
 * Submitting a proposal is refused with the vendor's own standing, not "You must be a vendor".
 *
 * Run: wp eval-file tests/test-proposal-gate-vendor-standing.php
 *
 * Seeds five users (prefix QA-RFT): active, suspended, pending vendor, a non-vendor and an administrator; removes them after.
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

$users = array();
foreach ( array( 'active', 'suspended', 'pending', 'none' ) as $status ) {
	$id = wp_insert_user( array( 'user_login' => 'qa_rft_pg_' . $status . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-rft-pg-{$status}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
	if ( 'none' !== $status ) {
		$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $id, 'display_name' => 'QA-RFT ' . $status, 'status' => $status ) );
	}
	$users[ $status ] = $id;
}
$users['admin'] = wp_insert_user( array( 'user_login' => 'qa_rft_pg_admin' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-pg-admin' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'administrator' ) );
$code = static fn( int $id ) => function_exists( 'wpss_proposal_vendor_block' ) && ( $e = wpss_proposal_vendor_block( $id ) ) ? $e->get_error_code() : '';

// The two routes a client actually calls: the permission check answers before any handler runs.
$route_code = static function ( string $route, int $as ): string {
	wp_set_current_user( $as );
	$req = new WP_REST_Request( 'POST', $route );
	$req->set_body_params( array( 'request_id' => 1, 'cover_letter' => 'QA-RFT cover letter', 'price' => 10, 'delivery_days' => 3 ) );
	$res = rest_do_request( $req );
	return $res->is_error() ? (string) $res->as_error()->get_error_code() : 'ok';
};
foreach ( array( '/wpss/v1/proposals' => array( 'request_id' => 1 ), '/wpss/v1/buyer-requests/1/proposals' => array() ) as $route => $unused ) {
	$check( "POST {$route} as a suspended vendor says suspended", 'wpss_vendor_suspended' === $route_code( $route, $users['suspended'] ) );
}
wp_set_current_user( 0 );

$check( 'an active vendor may submit', '' === $code( $users['active'] ) );
$check( 'a suspended vendor is told the account is suspended', 'wpss_vendor_suspended' === $code( $users['suspended'] ) );
$check( 'a pending vendor is told the account is pending approval', 'wpss_vendor_pending' === $code( $users['pending'] ) );
$check( 'someone with no vendor profile is refused as not a vendor', 'wpss_not_vendor' === $code( $users['none'] ) );
$check( 'an administrator who is not a vendor is still refused', 'wpss_not_vendor' === $code( $users['admin'] ) );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $users as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $id ) );
	wp_delete_user( $id );
}
