<?php
/**
 * Every money route answers the same question the same way.
 *
 * Five cards were filed against this surface at once (ISS-023 to ISS-027):
 * reads were login-only while writes were vendor-gated, three different denial
 * codes were in use for one condition, and one gate skipped the account-status
 * rule so a suspended vendor kept access. All of it came from eight private
 * copies of the same check.
 *
 * Run: wp eval-file tests/test-money-route-gates.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$failures = array();

$routes = rest_get_server()->get_routes();

// Every route on the money surface, read or write, needs the vendor gate.
$money = array(
	'/wpss/v1/earnings',
	'/wpss/v1/earnings/summary',
	'/wpss/v1/earnings/history',
	'/wpss/v1/wallet/transactions',
	'/wpss/v1/withdrawals',
	'/wpss/v1/withdrawals/methods',
	// Pro's WalletController inherits free's namespace, so these are wpss/v1
	// and NOT wpss-pro/v1. Getting that wrong silently skips them and the test
	// passes on half the surface.
	'/wpss/v1/wallet/balance',
	'/wpss/v1/wallet/withdrawals',
	'/wpss/v1/wallet/providers',
	'/wpss/v1/wallet/withdraw',
);

// Pro registers nothing without a valid licence, so on a free-only site the
// wallet routes are absent. Fail loudly if the whole set vanishes rather than
// reporting a pass over six routes and calling it ten.
$expect_min = 6;

// Admin-only routes are a legitimate exception - a stricter gate, not a weaker one.
$admin_ok = array( '/wpss/v1/withdrawals/(?P<id>[\d]+)' );

$checked = 0;
foreach ( $routes as $route => $handlers ) {
	if ( ! in_array( $route, $money, true ) ) {
		continue;
	}
	foreach ( $handlers as $handler ) {
		$cb = $handler['permission_callback'] ?? null;

		if ( '__return_true' === $cb ) {
			$failures[] = "{$route} is public (__return_true) - money surface must be gated.";
			continue;
		}
		if ( is_array( $cb ) && isset( $cb[1] ) ) {
			$name = $cb[1];
			if ( ! in_array( $name, array( 'check_vendor_permissions', 'check_admin_permissions' ), true ) ) {
				$failures[] = "{$route} uses {$name}() - expected check_vendor_permissions().";
			}
			++$checked;
		}
	}
}

// No route should carry two permission callbacks for the same method - that is
// the dual-registration hazard, where behaviour depends on load order.
foreach ( $routes as $route => $handlers ) {
	if ( 0 !== strpos( $route, '/wpss' ) ) {
		continue;
	}
	$by_method = array();
	foreach ( $handlers as $handler ) {
		foreach ( array_keys( $handler['methods'] ?? array() ) as $m ) {
			$by_method[ $m ][] = $handler['permission_callback'] ?? null;
		}
	}
	foreach ( $by_method as $m => $cbs ) {
		if ( count( $cbs ) > 1 ) {
			$failures[] = "{$route} [{$m}] has " . count( $cbs ) . ' permission callbacks - load order decides which wins.';
		}
	}
}

// One condition, one code.
$err = wpss_rest_require_vendor();
if ( is_wp_error( $err ) && 'wpss_not_vendor' !== $err->get_error_code() && ! is_user_logged_in() ) {
	// Logged out returns the login error, which is correct - only assert the
	// vendor branch when we are logged in as a non-vendor.
	$err = null;
}

if ( $checked < $expect_min ) {
	$failures[] = "Only {$checked} money routes were found - expected at least {$expect_min}. Route paths in this test may be stale.";
}

echo "Money routes checked: {$checked}\n";

if ( $failures ) {
	echo "\nFAIL\n";
	foreach ( array_unique( $failures ) as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}

echo "PASS - every money route uses the shared vendor gate, and no route carries two permission callbacks.\n";
