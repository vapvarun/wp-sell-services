<?php
/**
 * Orders collection schema contract.
 *
 * status__not_in was declared in the args of GET /orders/{id}/messages, which
 * never reads it, and absent from GET /orders, which does. Nothing was broken -
 * WordPress hands undeclared query params to get_param() anyway - but the
 * public schema pointed app developers at the one route where the filter does
 * nothing.
 *
 * A declared arg that the handler ignores, and a read param that the schema
 * hides, are the same defect from opposite ends. This asserts both directions.
 *
 * Run: wp eval-file tests/test-orders-schema-contract.php
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nOrders collection schema contract\n\n";

/**
 * Read the declared GET args for one registered route.
 *
 * @param string $route Route pattern.
 * @return array
 */
function wpss_route_get_args( $route ) {
	$routes = rest_get_server()->get_routes();
	foreach ( $routes[ $route ] ?? array() as $handler ) {
		if ( ! empty( $handler['methods']['GET'] ) ) {
			return $handler['args'] ?? array();
		}
	}
	return array();
}

$collection = wpss_route_get_args( '/wpss/v1/orders' );
$messages   = wpss_route_get_args( '/wpss/v1/orders/(?P<id>[\d]+)/messages' );

wpss_t( ! empty( $collection ), 'the orders collection route is registered' );
wpss_t( isset( $collection['status__not_in'] ), 'status__not_in is declared on the route that reads it' );
wpss_t( ! isset( $messages['status__not_in'] ), 'status__not_in is gone from the messages route, which ignores it' );
wpss_t(
	isset( $collection['status__not_in']['type'] ) && 'array' === $collection['status__not_in']['type'],
	'it is typed as an array, so it is validated rather than arriving raw'
);

// Every declared arg on the messages route must actually be read by
// get_messages(), or we have simply moved the same defect somewhere else.
$body = file_get_contents( dirname( __DIR__ ) . '/src/API/OrdersController.php' );
$fn   = substr( $body, strpos( $body, 'public function get_messages' ) );
$fn   = substr( $fn, 0, strpos( $fn, "\n\t}\n" ) );
foreach ( array_keys( $messages ) as $arg ) {
	if ( 'id' === $arg ) {
		continue;
	}
	wpss_t(
		false !== strpos( $fn, "'" . $arg . "'" ),
		sprintf( 'get_messages() actually reads its declared arg "%s"', $arg )
	);
}

// And the filter still filters.
wp_set_current_user( 1 );
$req = new WP_REST_Request( 'GET', '/wpss/v1/orders' );
$req->set_param( 'per_page', 100 );
$all = rest_do_request( $req );
$all = $all->is_error() ? array() : ( $all->get_data()['items'] ?? $all->get_data() );

$req2 = new WP_REST_Request( 'GET', '/wpss/v1/orders' );
$req2->set_param( 'per_page', 100 );
$req2->set_param( 'status__not_in', array( 'completed', 'cancelled', 'refunded' ) );
$filtered = rest_do_request( $req2 );
$filtered = $filtered->is_error() ? array() : ( $filtered->get_data()['items'] ?? $filtered->get_data() );

$leaked = array_filter(
	(array) $filtered,
	static function ( $o ) {
		$st = is_array( $o ) ? ( $o['status'] ?? '' ) : ( $o->status ?? '' );
		return in_array( $st, array( 'completed', 'cancelled', 'refunded' ), true );
	}
);

wpss_t( count( (array) $filtered ) < count( (array) $all ), 'the filter removes rows' );
wpss_t( empty( $leaked ), 'no excluded status survives the filter' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
