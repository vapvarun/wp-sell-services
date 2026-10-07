<?php
/**
 * Every REST route and method answers without a 5xx, and no write succeeds
 * for a visitor who is not logged in unless it is public by design.
 *
 * Run: wp eval-file tests/test-rest-matrix.php
 *
 * tests/audit/rest-reachability.php fetched GETs only and sat outside the CI
 * loop, so every write route was unexercised. Here:
 *   - GET as an administrator, a real id substituted (read only);
 *   - every POST / PUT / PATCH / DELETE as a logged-out visitor, then as a
 *     logged-in buyer, with an empty body and ids that do not exist, so
 *     nothing on the site can match and nothing is written.
 * Outbound HTTP is refused for the whole run, so no route reaches Stripe,
 * PayPal or any other service: an empty buyer POST to /vendors/register once
 * made the buyer a vendor, and the next route started a real Stripe Connect
 * onboarding. A 500 or a thrown error fails; 502-504 is the honest answer of
 * a route whose upstream is unreachable. A 2xx write for a logged-out visitor
 * fails unless the route is in $public_writes with its reason.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

use WPSellServices\Tests\Factories\UserFactory;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	if ( ! $ok ) {
		echo 'FAIL  ' . $label . "\n";
		++$fails;
	}
};

// GETs that stream a file or end the request; dispatching them halts the run.
$no_dispatch = '#(/download|/badge|\.json$|/verify$|/export|/oembed|/fetch-url|/media(/|$))#i';

// Writes a logged-out visitor may make, each for a stated reason.
$public_writes = array();

$concrete = static function ( string $route, string $id ): string {
	return (string) preg_replace_callback(
		'/\(\?P<([a-z0-9_]+)>([^()]*(?:\([^()]*\)[^()]*)*)\)/i',
		static function ( $m ) use ( $id ) {
			if ( str_contains( $m[2], '{36}' ) || false !== stripos( $m[1], 'uuid' ) ) {
				return '00000000-0000-0000-0000-000000000000';
			}
			if ( str_contains( $m[2], '\d' ) || str_contains( $m[2], '[0-9' ) ) {
				return $id;
			}
			return 'wpss-matrix-none';
		},
		$route
	);
};

$dispatch = static function ( string $method, string $path ) {
	try {
		return (int) rest_do_request( new WP_REST_Request( $method, $path ) )->get_status();
	} catch ( Throwable $e ) {
		return 'threw ' . get_class( $e ) . ': ' . $e->getMessage();
	}
};

add_filter( 'pre_http_request', static fn() => new WP_Error( 'wpss_matrix_offline', 'Outbound HTTP is blocked during the REST matrix.' ), 1 );

add_filter( 'pre_wp_mail', '__return_false' );

global $wpdb;
$in_rollback = false;
// A handler's own START TRANSACTION would commit ours; its COMMIT would make
// the admin pass permanent. Inside the matrix's transaction both are no-ops.
add_filter(
	'query',
	static function ( $sql ) use ( &$in_rollback ) {
		return $in_rollback && preg_match( '/^\s*(START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK)\b/i', $sql ) ? 'SELECT 1' : $sql;
	}
);
$rolled_back = static function ( callable $fn ) use ( &$in_rollback, $wpdb ) {
	$wpdb->query( 'START TRANSACTION' );
	$in_rollback = true;
	try {
		return $fn();
	} finally {
		$in_rollback = false;
		$wpdb->query( 'ROLLBACK' );
		wp_cache_flush();
	}
};

$buyer_wrote = array();
$buyer = UserFactory::customer( array( 'user_login' => 'wpss_restmatrix_' . wp_generate_password( 6, false, false ) ) );
$calls = 0;

try {
	foreach ( array( 'wpss/v1', 'wpss-pro/v1' ) as $ns ) {
		foreach ( rest_get_server()->get_routes( $ns ) as $route => $handlers ) {
			if ( rtrim( $route, '/' ) === '/' . $ns ) {
				continue;
			}

			$methods = array();
			foreach ( $handlers as $h ) {
				$methods = array_merge( $methods, array_keys( array_filter( (array) ( $h['methods'] ?? array() ) ) ) );
			}

			foreach ( array_unique( array_map( 'strtoupper', $methods ) ) as $method ) {
				if ( 'GET' === $method ) {
					if ( preg_match( $no_dispatch, $route ) ) {
						continue;
					}
					wp_set_current_user( 1 );
					$status = $dispatch( 'GET', $concrete( $route, '1' ) );
					++$calls;
					$check( "GET {$route} as admin -> {$status}", is_int( $status ) && 500 !== $status );
					continue;
				}

				$path = $concrete( $route, '999999999' );

				wp_set_current_user( 0 );
				$status = $dispatch( $method, $path );
				++$calls;
				$check( "{$method} {$route} logged out -> {$status}", is_int( $status ) && 500 !== $status );
				$check( "{$method} {$route} logged out was accepted ({$status}); add it to \$public_writes with a reason if that is by design", ! is_int( $status ) || $status >= 300 || isset( $public_writes[ "{$method} {$route}" ] ) );

				wp_set_current_user( $buyer->ID );
				$status = $dispatch( $method, $path );
				++$calls;
				$check( "{$method} {$route} as a buyer -> {$status}", is_int( $status ) && 500 !== $status );
				if ( is_int( $status ) && $status < 300 ) {
					$buyer_wrote[] = "{$method} {$route} -> {$status}";
				}

				wp_set_current_user( 1 );
				$status = $rolled_back( static fn() => $dispatch( $method, $path ) );
				++$calls;
				$check( "{$method} {$route} as admin -> {$status}", is_int( $status ) && 500 !== $status );
			}
		}
	}
} finally {
	wp_set_current_user( 0 );
	global $wpdb;
	// The buyer may have registered as a vendor (an empty POST to
	// /vendors/register is a valid "become a vendor").
	$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $buyer->ID ) ); // phpcs:ignore WordPress.DB
	UserFactory::cleanup();
}

foreach ( $buyer_wrote as $line ) {
	echo "INFO  buyer write accepted with an empty body: {$line}\n";
}

echo "{$calls} dispatches\n";
echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
