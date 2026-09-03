<?php
/**
 * One lockout counter for the website and the app.
 *
 * Run: wp eval-file tests/test-login-lockout.php
 *
 * 1.7.1 shipped the account lockout inside POST /auth/login only: six wrong
 * passwords in the app locked the account, six wrong passwords at wp-login.php
 * for the same account in the same window did nothing, and the correct password
 * signed that account in while the plugin still called it locked (Basecamp
 * 10267994010). Both rails now read and write the same counter.
 *
 * Every user and transient this file creates is removed again at the end.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  [{$detail}]" : '' ) . "\n";
	$fails += $ok ? 0 : 1;
};

$call = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};

$error_code = static function ( $response ): string {
	if ( $response instanceof WP_REST_Response ) {
		$data = $response->get_data();
		return is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
	}
	return $response instanceof WP_Error ? $response->get_error_code() : '';
};

require_once ABSPATH . 'wp-admin/includes/user.php';

$original_ip            = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR'] = '198.51.100.77';

$made_users = array();
$made_keys  = array();

$make_user = static function ( string $slug, string $password ) use ( &$made_users, &$made_keys ) {
	$login = 'wpss-fu4-' . $slug . '-' . wp_rand( 1000, 9999 );
	$id    = wp_create_user( $login, $password, $login . '@example.test' );
	if ( ! is_wp_error( $id ) ) {
		$made_users[] = (int) $id;
	}
	$made_keys[] = $login;
	return is_wp_error( $id ) ? null : $login;
};

$password = wp_generate_password( 20 );
$login    = $make_user( 'known', $password );

if ( null === $login ) {
	echo "could not create a throwaway user; aborting\n";
	return;
}

// --- 1. Five wrong web sign-ins lock the account -------------------------
for ( $i = 1; $i <= 5; $i++ ) {
	$attempt = wp_authenticate( $login, 'wrong-' . $i );
	if ( 1 === $i ) {
		$check( 'first wrong web password is a plain failure', 'wpss_account_locked' !== $error_code( $attempt ), $error_code( $attempt ) );
	}
}

$check( 'five wrong web sign-ins lock the account', wpss_login_is_locked( $login ) );

$sixth = wp_authenticate( $login, 'wrong-6' );
$check( 'sixth web sign-in is refused with the plugin error', 'wpss_account_locked' === $error_code( $sixth ), $error_code( $sixth ) );

// --- 2. The API agrees, from a different address -------------------------
$_SERVER['REMOTE_ADDR'] = '198.51.100.78';
$response               = $call( 'POST', '/wpss/v1/auth/login', array( 'username' => $login, 'password' => 'wrong-7' ) );
$check( 'REST reports the same account locked', 'wpss_account_locked' === $error_code( $response ), (string) $response->get_status() . ' ' . $error_code( $response ) );
$check( 'REST lockout answers 423', 423 === $response->get_status(), (string) $response->get_status() );

// --- 3. The correct password is refused on both rails --------------------
$attempt = wp_authenticate( $login, $password );
$check( 'correct password on the website is refused while locked', 'wpss_account_locked' === $error_code( $attempt ), $error_code( $attempt ) );

$response = $call( 'POST', '/wpss/v1/auth/login', array( 'username' => $login, 'password' => $password ) );
$check( 'correct password over REST is refused while locked', 423 === $response->get_status(), (string) $response->get_status() . ' ' . $error_code( $response ) );

// --- 4. Clearing the lock restores access --------------------------------
wpss_login_clear_failures( $login );
$check( 'clearing the transient unlocks the account', ! wpss_login_is_locked( $login ) );

$attempt = wp_authenticate( $login, $password );
$check( 'correct password signs in once the lock has gone', $attempt instanceof WP_User, $error_code( $attempt ) );

// --- 5. A successful sign-in clears the counter --------------------------
wp_authenticate( $login, 'wrong-again' );
wp_authenticate( $login, 'wrong-again-2' );
$key = wpss_login_lock_key( $login );
$check( 'failures accumulate before a success', 2 === (int) get_transient( 'wpss_login_fails_' . $key ), (string) get_transient( 'wpss_login_fails_' . $key ) );

do_action( 'wp_login', $login, get_user_by( 'login', $login ) );
$check( 'a successful sign-in clears the counter', false === get_transient( 'wpss_login_fails_' . $key ) );

// --- 6. An unknown username answers exactly like a known one -------------
// Counted the same rather than skipped: if only real accounts locked, the
// sixth attempt would say "locked" for one that exists and "invalid" for one
// that does not, which is an account-enumeration oracle.
$ghost       = 'wpss-fu4-ghost-' . wp_rand( 1000, 9999 );
$made_keys[] = $ghost;

for ( $i = 1; $i <= 5; $i++ ) {
	wp_authenticate( $ghost, 'wrong-' . $i );
}

$sixth_ghost = wp_authenticate( $ghost, 'wrong-6' );
$check( 'an unknown username locks and answers like a real one', $error_code( $sixth_ghost ) === $error_code( $sixth ), $error_code( $sixth_ghost ) );

$_SERVER['REMOTE_ADDR'] = '198.51.100.79';
$response               = $call( 'POST', '/wpss/v1/auth/login', array( 'username' => $ghost, 'password' => 'wrong-7' ) );
$check( 'REST says the same for an unknown username', 423 === $response->get_status(), (string) $response->get_status() . ' ' . $error_code( $response ) );

// --- 7. The filter turns the website rail off ----------------------------
$password_b = wp_generate_password( 20 );
$login_b    = $make_user( 'filtered', $password_b );

if ( null === $login_b ) {
	$check( 'could create a second throwaway user', false );
} else {
	for ( $i = 1; $i <= 5; $i++ ) {
		wp_authenticate( $login_b, 'wrong-' . $i );
	}
	$check( 'second account is locked', wpss_login_is_locked( $login_b ) );

	add_filter( 'wpss_web_login_lock', '__return_false' );
	$attempt = wp_authenticate( $login_b, $password_b );
	$check( 'wpss_web_login_lock=false lets the website sign-in through', $attempt instanceof WP_User, $error_code( $attempt ) );

	$_SERVER['REMOTE_ADDR'] = '198.51.100.80';
	$response               = $call( 'POST', '/wpss/v1/auth/login', array( 'username' => $login_b, 'password' => $password_b ) );
	$check( 'the API lockout is unaffected by that filter', 423 === $response->get_status(), (string) $response->get_status() . ' ' . $error_code( $response ) );
	remove_filter( 'wpss_web_login_lock', '__return_false' );

	$attempt = wp_authenticate( $login_b, $password_b );
	$check( 'removing the filter restores the website lock', 'wpss_account_locked' === $error_code( $attempt ), $error_code( $attempt ) );
}

// --- Cleanup -------------------------------------------------------------
foreach ( $made_keys as $made_key ) {
	wpss_login_clear_failures( $made_key );
}

foreach ( array( '198.51.100.77', '198.51.100.78', '198.51.100.79', '198.51.100.80' ) as $ip ) {
	delete_transient( 'wpss_rate_login_' . md5( $ip ) );
}

foreach ( $made_users as $made_user ) {
	wp_delete_user( $made_user );
}

if ( null === $original_ip ) {
	unset( $_SERVER['REMOTE_ADDR'] );
} else {
	$_SERVER['REMOTE_ADDR'] = $original_ip;
}

echo "\n" . ( 0 === $fails ? "ALL PASS\n" : "{$fails} FAILED\n" );
