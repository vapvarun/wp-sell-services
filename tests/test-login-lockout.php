<?php
/**
 * The plugin does not lock sign-ins (Basecamp 10504362544).
 *
 * Run: wp eval-file tests/test-login-lockout.php
 *
 * 1.7.1 locked an account for 15 minutes after five wrong passwords, on
 * wp-login.php too, administrators included. Locking sign-ins is a security
 * plugin's job. The app's POST /auth/login keeps only the per-IP RateLimiter.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  [{$detail}]" : '' ) . "\n";
	$fails += $ok ? 0 : 1;
};

$original_ip = $_SERVER['REMOTE_ADDR'] ?? null;
$password    = wp_generate_password( 20 );
$login       = 'wpss-nolock-' . wp_rand( 1000, 9999 );
$user_id     = wp_create_user( $login, $password, $login . '@example.test' );

if ( is_wp_error( $user_id ) ) {
	echo "could not create a throwaway user; aborting\n";
	return;
}

// 1. wp-login.php path: six wrong passwords, then the right one signs in.
for ( $i = 1; $i <= 6; $i++ ) {
	wp_authenticate( $login, 'wrong-' . $i );
}
$user = wp_authenticate( $login, $password );
$check( 'six wrong passwords on the web path do not lock the account', $user instanceof WP_User, is_wp_error( $user ) ? $user->get_error_code() : '' );

// 2. REST path: six wrong passwords from two addresses (the per-IP limiter never trips), then the right one.
$call = static function ( string $password_try ) use ( $login ) {
	$request = new WP_REST_Request( 'POST', '/wpss/v1/auth/login' );
	$request->set_param( 'username', $login );
	$request->set_param( 'password', $password_try );

	return rest_do_request( $request );
};
$last = null;
for ( $i = 1; $i <= 6; $i++ ) {
	$_SERVER['REMOTE_ADDR'] = $i <= 3 ? '198.51.100.1' : '198.51.100.2';
	$last                   = $call( 'wrong-' . $i );
}
$check( 'the sixth wrong password over REST is a plain 401, not a 423 lock', 401 === $last->get_status(), (string) $last->get_status() );
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$ok = $call( $password );
$check( 'and the right password still signs in over REST', 200 === $ok->get_status(), (string) $ok->get_status() );

// 3. The code is gone, the shipped filter is a deprecated no-op.
$check( 'lock helpers are removed', ! function_exists( 'wpss_login_is_locked' ) && ! function_exists( 'wpss_login_record_failure' ) && ! function_exists( 'wpss_login_lock_error' ) );
$check( 'nothing is hooked to the web sign-in for locking', false === has_filter( 'authenticate', '__return_false' ) && ! get_transient( 'wpss_login_lock_' . md5( strtolower( $login ) ) ) );

foreach ( array( '198.51.100.1', '198.51.100.2', '203.0.113.10' ) as $ip ) {
	delete_transient( 'wpss_rate_auth_login_ip_' . md5( $ip ) );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $user_id );
if ( null === $original_ip ) {
	unset( $_SERVER['REMOTE_ADDR'] );
} else {
	$_SERVER['REMOTE_ADDR'] = $original_ip;
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
