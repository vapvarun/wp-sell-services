<?php
/**
 * No PUT/PATCH route argument carries a 'default'.
 *
 * WP_REST_Request::has_param() is true for a defaulted argument, so an update
 * handler that checks has_param() (the correct way to ask "did the client send
 * this?") treats every default as sent and overwrites the stored value with it.
 * That is how PUT {"is_active":false} reset a plan's billing period and how
 * PUT {"fee":5} switched Stripe Connect off (Basecamp 10408191220, 10407786815).
 * Defaults belong on create routes only.
 *
 * Run: wp eval-file tests/test-no-defaults-on-update-routes.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$offenders = array();
$routes    = 0;

foreach ( rest_get_server()->get_routes( 'wpss/v1' ) as $route => $handlers ) {
	foreach ( $handlers as $handler ) {
		$methods = array_keys( $handler['methods'] ?? array() );
		if ( ! array_intersect( $methods, array( 'PUT', 'PATCH' ) ) ) {
			continue;
		}
		++$routes;
		foreach ( (array) ( $handler['args'] ?? array() ) as $name => $arg ) {
			if ( is_array( $arg ) && array_key_exists( 'default', $arg ) ) {
				$offenders[] = implode( '|', $methods ) . " {$route} :: {$name}";
			}
		}
	}
}

foreach ( $offenders as $o ) {
	echo "FAIL  defaulted argument on an update route: {$o}\n";
}
echo $offenders ? count( $offenders ) . " FAILED\n" : "PASS  no update route defaults any argument ({$routes} PUT/PATCH routes checked)\nAll passed\n";
