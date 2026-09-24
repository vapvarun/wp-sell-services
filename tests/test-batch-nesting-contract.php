<?php
/**
 * A batch cannot contain a batch.
 *
 * Run: wp eval-file tests/test-batch-nesting-contract.php
 *
 * POST /wpss/v1/batch caps a call at 25 sub-requests, but it only checked that a
 * sub-request's path started with /wpss/v1/ - and /wpss/v1/batch does. Each
 * level got its own 25, so one call fanned out to 625+ dispatches (Basecamp
 * 10336370365). Read-only requests are used; nothing is written.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$member = get_user_by( 'login', 'wpss_buyer_ella' );

if ( ! $member ) {
	echo "SKIP  needs the QA personas (bin/qa-fixtures.sh)\n";
	return;
}

wp_set_current_user( $member->ID );

$inner = array(
	'requests' => array(
		array( 'method' => 'GET', 'path' => '/wpss/v1/categories' ),
		array( 'method' => 'GET', 'path' => '/wpss/v1/categories' ),
		array( 'method' => 'GET', 'path' => '/wpss/v1/categories' ),
	),
);
$outer = array(
	'requests' => array(
		array( 'method' => 'POST', 'path' => '/wpss/v1/batch', 'body' => $inner ),
		array( 'method' => 'POST', 'path' => '/wpss/v1/batch', 'body' => $inner ),
		array( 'method' => 'POST', 'path' => '/wpss/v1/batch', 'body' => $inner ),
	),
);

$dispatched = 0;
$count      = static function ( $result ) use ( &$dispatched ) {
	++$dispatched;
	return $result;
};
add_filter( 'rest_pre_dispatch', $count );

$request = new WP_REST_Request( 'POST', '/wpss/v1/batch' );
$request->set_body_params( $outer );
$response = rest_ensure_response( rest_do_request( $request ) );

remove_filter( 'rest_pre_dispatch', $count );
wp_set_current_user( 0 );

$data  = (array) $response->get_data();
$codes = array_map( static fn( $r ) => $r['body']['code'] ?? '', (array) ( $data['responses'] ?? array() ) );

$check( 'the outer batch still answers 200', 200 === $response->get_status() );
$check( sprintf( 'a batch of 3 batches of 3 dispatches 4 times, not 13 (%d)', $dispatched ), $dispatched <= 4 );
$check( '  each nested batch is refused as nested_batch_not_allowed', array( 'nested_batch_not_allowed', 'nested_batch_not_allowed', 'nested_batch_not_allowed' ) === $codes );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
