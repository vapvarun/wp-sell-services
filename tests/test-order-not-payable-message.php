<?php
/**
 * A cancelled or refunded order is not "already paid" (Basecamp 10416548799).
 *
 * Run: wp eval-file tests/test-order-not-payable-message.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table = $wpdb->prefix . 'wpss_orders';
$f     = wpss_test_fixtures();
$id    = ( $f->order )( array( 'status' => 'in_progress', 'customer_id' => $f->buyer, 'vendor_id' => $f->vendor, 'service_id' => ( $f->service )( 'publish' ), 'total' => 100 ) );
$buyer = (int) $f->buyer;
$svc   = new \WPSellServices\Checkout\CheckoutIntentService();
$ask   = static function ( string $status ) use ( $wpdb, $table, $id, $svc, $buyer ) {
	$wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
	return $svc->resolve( array( 'pay_order' => $id ), $buyer );
};

$cancelled = $ask( 'cancelled' );
$check( 'cancelled: its own code', is_wp_error( $cancelled ) && 'wpss_order_closed' === $cancelled->get_error_code() );
$check( 'cancelled: says cancelled, not paid', is_wp_error( $cancelled ) && str_contains( $cancelled->get_error_message(), 'cancelled' ) && ! str_contains( $cancelled->get_error_message(), 'already been paid' ) );

$refunded = $ask( 'refunded' );
$check( 'refunded: says refunded', is_wp_error( $refunded ) && str_contains( $refunded->get_error_message(), 'refunded' ) );

$paid = $ask( 'in_progress' );
$check( 'in progress keeps "already been paid"', is_wp_error( $paid ) && 'wpss_already_paid' === $paid->get_error_code() && str_contains( $paid->get_error_message(), 'already been paid' ) );

foreach ( array( 'cancelled', 'refunded', 'in_progress' ) as $status ) {
	$wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
	$msg = wpss_get_order_not_payable_message( wpss_get_order( $id ) );
	$check( "shared helper, {$status}: matches the intent guard", ( 'in_progress' === $status ) === str_contains( $msg, 'already been paid' ) );
}

exit( $fails ? 1 : 0 );
