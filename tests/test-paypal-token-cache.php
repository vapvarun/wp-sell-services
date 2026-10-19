<?php
/**
 * A PayPal access token PayPal refuses is replaced, not reused for an hour.
 *
 * Run: wp eval-file tests/test-paypal-token-cache.php
 *
 * Found in the sandbox walk: a token cached for other keys answered 401 invalid_token to every call, and
 * the buyer only saw "PayPal API error." until the cache expired. The token is now cached per mode and
 * client id, a 401 drops it and repeats the call once, and PayPal's own error text reaches the log.
 * PayPal is stubbed.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\PayPal\PayPalGateway;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$original = get_option( 'wpss_paypal_settings' );
update_option( 'wpss_paypal_settings', array( 'enabled' => 1, 'sandbox_mode' => 1, 'sandbox_client_id' => 'test-client', 'sandbox_client_secret' => 'test-secret' ) );

$calls = array();
$stub  = static function ( $pre, $args, $url ) use ( &$calls ) {
	if ( false === strpos( (string) $url, 'paypal.com' ) ) {
		return $pre;
	}

	$auth    = (string) ( $args['headers']['Authorization'] ?? '' );
	$calls[] = ( false !== strpos( $url, 'oauth2/token' ) ? 'token' : 'api:' . $auth );
	$resp    = static fn( int $code, array $body ) => array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => array(), 'cookies' => array() );

	if ( false !== strpos( $url, 'oauth2/token' ) ) {
		return $resp( 200, array( 'access_token' => 'fresh-token', 'expires_in' => 3600 ) );
	}

	return 'Bearer fresh-token' === $auth ? $resp( 200, array( 'id' => 'ORDER1' ) ) : $resp( 401, array( 'error' => 'invalid_token', 'error_description' => 'Token signature verification failed' ) );
};
add_filter( 'pre_http_request', $stub, 10, 3 );

try {
	$gateway = new PayPalGateway();
	$call    = new ReflectionMethod( $gateway, 'api_request' );
	$call->setAccessible( true );
	$key = new ReflectionMethod( $gateway, 'token_cache_key' );
	$key->setAccessible( true );

	// A token left over from other keys sits under the old, unkeyed name and is never read.
	set_transient( 'wpss_paypal_access_token', 'stale-token', HOUR_IN_SECONDS );
	$r = $call->invoke( $gateway, 'v2/checkout/orders/ORDER1', array(), 'GET' );
	$check( 'a token cached for other keys is not sent', 'ORDER1' === ( $r['id'] ?? '' ) && ! in_array( 'api:Bearer stale-token', $calls, true ) );
	delete_transient( 'wpss_paypal_access_token' );

	// A token under THIS key that PayPal refuses is dropped and the call repeated once.
	$calls = array();
	set_transient( $key->invoke( $gateway ), 'revoked-token', HOUR_IN_SECONDS );
	$gateway = new PayPalGateway();
	$r       = $call->invoke( $gateway, 'v2/checkout/orders/ORDER1', array(), 'GET' );
	$check( 'a refused token is replaced and the call succeeds', 'ORDER1' === ( $r['id'] ?? '' ) && array( 'api:Bearer revoked-token', 'token', 'api:Bearer fresh-token' ) === $calls );
	$check( '  and the fresh token is what stays cached', 'fresh-token' === get_transient( $key->invoke( $gateway ) ) );

	// Changing the keys changes the cache name.
	$before = $key->invoke( $gateway );
	update_option( 'wpss_paypal_settings', array( 'enabled' => 1, 'sandbox_mode' => 1, 'sandbox_client_id' => 'other-client', 'sandbox_client_secret' => 'test-secret' ) );
	$check( 'other keys use another cache entry', $before !== $key->invoke( new PayPalGateway() ) );

	// A call that still fails after the repeat reports PayPal's own words.
	remove_filter( 'pre_http_request', $stub, 10 );
	$always = static fn( $pre, $args, $url ) => false === strpos( (string) $url, 'paypal.com' ) ? $pre : ( false !== strpos( $url, 'oauth2/token' ) ? array( 'response' => array( 'code' => 200, 'message' => '' ), 'body' => '{"access_token":"x","expires_in":3600}', 'headers' => array(), 'cookies' => array() ) : array( 'response' => array( 'code' => 401, 'message' => '' ), 'body' => '{"error":"invalid_token","error_description":"Token signature verification failed"}', 'headers' => array(), 'cookies' => array() ) );
	add_filter( 'pre_http_request', $always, 10, 3 );
	$r = $call->invoke( new PayPalGateway(), 'v2/checkout/orders', array( 'a' => 1 ), 'POST' );
	$check( 'a call that keeps failing reports PayPal\'s own error text', false !== strpos( (string) ( $r['error']['message'] ?? '' ), 'Token signature verification failed' ) );
	remove_filter( 'pre_http_request', $always, 10 );
} finally {
	remove_filter( 'pre_http_request', $stub, 10 );
	false === $original ? delete_option( 'wpss_paypal_settings' ) : update_option( 'wpss_paypal_settings', $original );
	foreach ( array( 'test-client', 'other-client' ) as $id ) {
		delete_transient( 'wpss_paypal_token_' . substr( md5( 'sandbox|' . $id ), 0, 12 ) );
	}
	delete_transient( 'wpss_paypal_access_token' );
}

exit( $fails ? 1 : 0 );
