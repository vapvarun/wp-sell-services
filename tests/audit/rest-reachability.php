<?php
/**
 * REST reachability + permission smoke over EVERY registered WP Sell Services route.
 *
 * The catalogue (docs/api/openapi.json) is generated from these same routes, so
 * this is the coverage counterpart: instead of documenting the surface, it
 * exercises it. For every route under wpss/v1 + wpss-pro/v1 it asserts
 *
 *   1. the route carries a permission_callback (a missing one defaults a route
 *      to public - the exact class of leak the 1.7.0 audit found), and
 *   2. every GET responds WITHOUT a 5xx / fatal when dispatched (a concrete
 *      value is substituted for each path param). 4xx is fine - a 400/401/403/
 *      404 means the endpoint answered; only a 500 (or a thrown error) is a bug.
 *
 * Mutating methods (POST/PUT/PATCH/DELETE) are permission-checked but NOT
 * dispatched, so the smoke never writes or deletes data.
 *
 * RUN:   wp eval-file wp-content/plugins/wp-sell-services/tests/audit/rest-reachability.php
 *        (run on a COMBO install to cover both namespaces)
 * EXIT:  non-zero if any route 5xxs / throws / lacks a permission_callback.
 *
 * @package WPSellServices
 */

namespace WPSellServices\Tests\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$namespaces = array( 'wpss/v1', 'wpss-pro/v1' );

// Act as an administrator so admin-gated GETs are reachable (we are testing that
// they RESPOND cleanly, not that they reject - rejection is a 4xx, which passes).
wp_set_current_user( 1 );

$server = rest_get_server();

$checked_routes = 0;
$get_ok         = 0;
$get_failed     = 0;
$no_permission  = 0;
$skipped_mutate = 0;
$skipped_stream = 0;
$failures       = array();

// GETs that stream a file / redirect / call wp_die() and TERMINATE the request
// (a PDF, a badge image, a CSV export, an oEmbed proxy). They can't be
// dispatched in-process without halting the run, so they are permission-checked
// but not fetched. Add a pattern here if a new streaming endpoint is introduced.
$no_dispatch = '#(/download|/badge|\.json$|/verify$|/export|/oembed|/fetch-url|/fetchUrl|/media(/|$)|/credly-status|/anchor-status)#i';

/** Build a concrete path by substituting a value that matches each param regex. */
$concrete_path = static function ( string $route ): string {
	return (string) preg_replace_callback(
		'/\(\?P<([a-z0-9_]+)>([^)]*)\)/i',
		static function ( $m ) {
			$name = $m[1];
			$re   = $m[2];
			if ( false !== strpos( $re, '{36}' ) || false !== stripos( $name, 'uuid' ) ) {
				return '00000000-0000-0000-0000-000000000000';
			}
			if ( false !== strpos( $re, '\\d' ) || false !== strpos( $re, '[0-9' ) || false !== strpos( $re, '\\\\d' ) ) {
				return '1';
			}
			if ( 0 === strpos( $re, '[a-z]' ) ) {
				return 'stripe';
			}
			return 'test';
		},
		$route
	);
};

echo "REST reachability + permission smoke\n====================================\n";

foreach ( $namespaces as $ns ) {
	$routes = $server->get_routes( $ns );
	if ( empty( $routes ) ) {
		echo "  (namespace {$ns} not registered - is the plugin active?)\n";
		continue;
	}

	foreach ( $routes as $route => $handlers ) {
		if ( rtrim( $route, '/' ) === '/' . $ns ) {
			continue; // the namespace index route itself
		}
		++$checked_routes;

		// (1) permission contract — every handler must carry a permission_callback.
		$serves_get = false;
		foreach ( (array) $handlers as $h ) {
			if ( ! array_key_exists( 'permission_callback', $h ) || null === $h['permission_callback'] ) {
				++$no_permission;
				$failures[] = "NO permission_callback: {$route}";
			}
			foreach ( (array) ( $h['methods'] ?? array() ) as $m => $on ) {
				if ( $on && 'GET' === strtoupper( (string) $m ) ) {
					$serves_get = true;
				}
			}
		}

		// (2) reachability — dispatch a GET (read-only) and require no 5xx.
		if ( ! $serves_get ) {
			++$skipped_mutate;
			continue;
		}
		if ( preg_match( $no_dispatch, $route ) ) {
			++$skipped_stream; // streaming/terminating GET — permission-checked above, not fetched
			continue;
		}

		$path = $concrete_path( $route );
		try {
			$response = rest_do_request( new \WP_REST_Request( 'GET', $path ) );
			$status   = (int) $response->get_status();
			if ( $status >= 500 ) {
				++$get_failed;
				$failures[] = "GET {$path} -> {$status}";
			} else {
				++$get_ok;
			}
		} catch ( \Throwable $e ) {
			++$get_failed;
			$failures[] = "GET {$path} -> THROWN: " . $e->getMessage();
		}
	}
}

echo "  routes checked:            {$checked_routes}\n";
echo "  GET endpoints reachable:   {$get_ok}\n";
echo "  GET endpoints 5xx/threw:   {$get_failed}\n";
echo "  routes w/o permission cb:  {$no_permission}\n";
echo "  mutation-only (perm-only): {$skipped_mutate}\n";
echo "  streaming GET (perm-only): {$skipped_stream}\n";

if ( $failures ) {
	echo "\nFAILURES:\n";
	foreach ( $failures as $f ) {
		echo "  FAIL  {$f}\n";
	}
}

echo "====================================\n";
$fail_total = $get_failed + $no_permission;
echo 0 === $fail_total ? "OK - every route is permission-gated and every GET responds without a 5xx.\n" : "FAILED - {$fail_total} problem(s) above.\n";
exit( $fail_total > 0 ? 1 : 0 );
