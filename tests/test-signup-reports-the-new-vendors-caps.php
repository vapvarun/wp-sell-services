<?php
/**
 * Signing up as a vendor over REST answers with the vendor's real capabilities:
 * can_create_services and can_manage_orders are true in the response of the
 * request that created the account, not false from a stale current user.
 *
 * Run: wp eval-file tests/test-signup-reports-the-new-vendors-caps.php
 *
 * Creates one QA user, opens registration for the call, and deletes the user
 * and restores users_can_register on any exit.
 *
 * @package WPSellServices
 */

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

require_once ABSPATH . 'wp-admin/includes/user.php';

$was_open = get_option( 'users_can_register' );
$user_id  = 0;
$restore  = static function () use ( $was_open, &$user_id ) {
	update_option( 'users_can_register', $was_open );
	if ( $user_id ) {
		wp_delete_user( $user_id );
	}
};

update_option( 'users_can_register', 1 );
wp_set_current_user( 0 );
add_filter( 'pre_wp_mail', '__return_false' );

$mode = wpss_get_option( 'vendor', 'vendor_registration' );
if ( 'closed' === $mode ) {
	echo "SKIP  vendor registration is closed on this site\n";
	return;
}

$tag     = 'qa' . wp_rand( 1000, 9999 );
$request = new WP_REST_Request( 'POST', '/wpss/v1/auth/register' );
$request->set_body_params(
	array(
		'username'     => $tag,
		'email'        => $tag . '@example.test',
		'password'     => wp_generate_password( 20 ),
		'display_name' => $tag,
		'role'         => 'vendor',
	)
);
$response = rest_do_request( $request );
$data     = $response->get_data();
$user_id  = (int) ( $data['user']['id'] ?? 0 );

$check( 'the vendor signup succeeded', 201 === $response->get_status() && $user_id > 0, (string) $response->get_status() );
$user = $user_id ? get_userdata( $user_id ) : false;
if ( $user && 'pending' === ( wpss_get_vendor_status( $user_id ) ?: '' ) ) {
	echo "SKIP  vendor registration needs approval on this site, so the caps are not granted yet\n";
	return;
}
$check( 'the account really holds the vendor caps', $user && $user->has_cap( 'wpss_manage_services' ) && $user->has_cap( 'wpss_vendor_orders' ) );
$check( 'the response says can_create_services', true === ( $data['user']['capabilities']['can_create_services'] ?? null ) );
$check( 'the response says can_manage_orders', true === ( $data['user']['capabilities']['can_manage_orders'] ?? null ) );
