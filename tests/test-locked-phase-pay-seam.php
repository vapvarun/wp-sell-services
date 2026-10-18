<?php
/**
 * A locked milestone phase gets no payment page on any rail: wpss_ensure_pay_order() is the one
 * seam every store rail's pay link passes through (vendor sweep, 1.9.0).
 *
 * Run: wp eval-file tests/test-locked-phase-pay-seam.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$f      = wpss_test_fixtures();
$parent = ( $f->order )( array( 'status' => 'in_progress', 'platform' => 'request', 'customer_id' => $f->buyer, 'vendor_id' => $f->vendor, 'service_id' => ( $f->service )( 'publish' ), 'total' => 100 ) );

$service = new \WPSellServices\Services\MilestoneService();
$one     = $service->propose( $parent, $f->vendor, 'Phase one', 'First part', 20, 3 );
$two     = $service->propose( $parent, $f->vendor, 'Phase two', 'Second part', 30, 3 );
$ids     = array_map( static fn( $r ) => (int) $r['id'], $service->get_for_parent( $parent ) );
foreach ( $ids as $id ) {
	$f->orders[] = $id;
}
$check( 'two phases were proposed', 2 === count( $ids ) );

$reached = array();
$spy     = static function ( $url, $order_id ) use ( &$reached ) {
	$reached[] = (int) $order_id;
	return $url;
};
add_filter( 'wpss_ensure_pay_order', $spy, 10, 2 );

if ( 2 === count( $ids ) ) {
	$first  = $ids[0];
	$second = $ids[1];
	$check( 'phase one is open', ! $service->is_locked( $first ) );
	$check( 'phase two is locked', $service->is_locked( $second ) );
	wpss_ensure_pay_order( $first );
	$check( 'an open phase reaches the rail', in_array( $first, $reached, true ) );
	$url = wpss_ensure_pay_order( $second );
	$check( 'a locked phase never reaches the rail', ! in_array( $second, $reached, true ) );
	$check( 'a locked phase sends the buyer to the order, not a payment page', false === strpos( $url, 'pay_order' ) && false === strpos( $url, 'order-pay' ) );
}
remove_filter( 'wpss_ensure_pay_order', $spy, 10 );

exit( $fails ? 1 : 0 );
