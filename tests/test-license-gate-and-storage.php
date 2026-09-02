<?php
/**
 * A lapsed Pro license must not take Free's money routes or bucket files with it.
 *
 * Three cards from the 2026-09-02 audit (P1, P2, P3): Pro gated REST by base
 * prefix and so refused Free's /payments/* and /wallet/transactions; the
 * storage drivers unhooked on lapse, so every delivery already in a bucket
 * answered 503; and a second upload implementation used the raw filename as
 * the bucket key and reported driver failures as success.
 *
 * Run: wp eval-file tests/test-license-gate-and-storage.php
 *
 * Run it twice - once licensed, once with the license status flipped to
 * expired - and the route assertions branch on the state Pro booted with.
 * The storage assertions hold in both states. Needs Pro active and a vendor
 * account on the site.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$failures = array();

if ( ! class_exists( '\WPSellServicesPro\License\Manager' ) ) {
	echo "SKIP - WP Sell Services Pro is not active; nothing to gate.\n";
	exit( 0 );
}

$licensed = ( new \WPSellServicesPro\License\Manager() )->is_valid();
echo 'Pro license: ' . ( $licensed ? 'valid' : 'invalid' ) . "\n";

$vendor = get_users(
	array(
		'role'   => 'wpss_vendor',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( empty( $vendor ) ) {
	echo "SKIP - no vendor account to dispatch as.\n";
	exit( 0 );
}
wp_set_current_user( (int) $vendor[0] );

$server = rest_get_server();
$routes = $server->get_routes( 'wpss/v1' );

/**
 * Dispatch one route and hand back its error code ('' when it succeeded).
 *
 * @param string $method HTTP method.
 * @param string $route  Route.
 * @return string
 */
$dispatch = function ( string $method, string $route ): string {
	$response = rest_do_request( new WP_REST_Request( $method, $route ) );
	if ( $response->is_error() ) {
		return (string) $response->as_error()->get_error_code();
	}
	return '';
};

// --- P1: Free routes never see the Pro gate; Pro routes always do while unlicensed.
$free_routes = array(
	'/wpss/v1/payments/methods',
	'/wpss/v1/wallet/transactions',
);
foreach ( $free_routes as $route ) {
	if ( ! isset( $routes[ $route ] ) ) {
		// /payments/* only registers on the standalone rail - absent is fine.
		echo "  (skip) {$route} is not registered on this rail\n";
		continue;
	}
	$code = $dispatch( 'GET', $route );
	if ( 'wpss_pro_license_required' === $code ) {
		$failures[] = "Free route {$route} is refused by the Pro license gate.";
	}
	echo "  GET {$route} -> " . ( '' === $code ? '200' : $code ) . "\n";
}

$pro_route = '/wpss/v1/wallet/balance';
if ( ! isset( $routes[ $pro_route ] ) ) {
	$failures[] = "Pro route {$pro_route} is not registered - it must exist and answer 403 while unlicensed.";
} else {
	$code = $dispatch( 'GET', $pro_route );
	echo "  GET {$pro_route} -> " . ( '' === $code ? '200' : $code ) . "\n";
	if ( ! $licensed && 'wpss_pro_license_required' !== $code ) {
		$failures[] = "Pro route {$pro_route} answered '{$code}' while unlicensed - expected wpss_pro_license_required.";
	}
	if ( $licensed && 'wpss_pro_license_required' === $code ) {
		$failures[] = "Pro route {$pro_route} is refused while licensed.";
	}
}

// --- P3: the second upload implementation is gone.
foreach ( array_keys( $routes ) as $route ) {
	if ( 0 === strpos( $route, '/wpss/v1/storage' ) ) {
		$failures[] = "{$route} still registers - the REST storage upload flow was deleted in 1.7.1.";
	}
}

// --- P2: drivers resolve by id regardless of license and of the active provider.
if ( ! function_exists( 'wpss_get_storage_provider' ) ) {
	$failures[] = 'wpss_get_storage_provider() does not exist - file records cannot resolve the provider that holds them.';
} else {
	$s3 = wpss_get_storage_provider( 's3' );
	if ( ! is_object( $s3 ) ) {
		$failures[] = "wpss_get_storage_provider('s3') returned nothing - the providers filter is unhooked (license: " . ( $licensed ? 'valid' : 'invalid' ) . ').';
	}

	// Provider switch: active provider is local, a record still names s3.
	$local_only = static fn() => 'local';
	add_filter( 'pre_option_wpss_active_storage_provider', $local_only );
	$record = array(
		'remote_path' => 'wpss/1/delivery/final.zip',
		'provider'    => 's3',
	);
	$active   = wpss_get_active_storage_provider();
	$resolved = wpss_get_storage_provider( (string) $record['provider'] );
	remove_filter( 'pre_option_wpss_active_storage_provider', $local_only );

	if ( null !== $active ) {
		$failures[] = 'Active provider is not null while the active option is local.';
	}
	if ( ! is_object( $resolved ) || 's3' !== $resolved->get_id() ) {
		$failures[] = "A record with provider 's3' did not resolve the s3 driver while the active provider is local.";
	}
	if ( null !== wpss_get_storage_provider( 'nope' ) ) {
		$failures[] = "An unregistered provider id resolved to something - the download path must 503, not sign with the wrong bucket.";
	}
	echo "  provider switch: active=local, record(provider=s3) -> " . ( is_object( $resolved ) ? get_class( $resolved ) : 'null' ) . "\n";
}

// --- P3: a driver failure is a WP_Error, never an array that looks like success.
if ( class_exists( '\WPSellServicesPro\Storage\S3Storage' ) ) {
	$result = ( new \WPSellServicesPro\Storage\S3Storage() )->upload( '/nonexistent/file.zip', 'wpss/1/delivery/file.zip' );
	echo '  S3Storage::upload() on a missing file -> ' . ( is_wp_error( $result ) ? 'WP_Error ' . $result->get_error_code() : gettype( $result ) ) . "\n";
	if ( ! is_wp_error( $result ) ) {
		$failures[] = 'S3Storage::upload() reported a failure as an array; Free would have deleted the local copy on a "success" without a key.';
	}
}

if ( $failures ) {
	echo "\nFAIL\n";
	foreach ( array_unique( $failures ) as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}

echo "PASS - Free money routes are not license-gated, Pro routes are, and bucket files resolve their own provider.\n";
