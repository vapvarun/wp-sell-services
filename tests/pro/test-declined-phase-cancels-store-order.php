<?php
/**
 * Declining or deleting an unpaid milestone phase cancels its pending WooCommerce order, so an old pay
 * link cannot take money for a phase that no longer exists (combo walk, 1.9.0).
 *
 * Run: wp eval-file tests/pro/test-declined-phase-cancels-store-order.php
 *
 * @package WPSellServices
 */

require_once dirname( __DIR__ ) . '/exit-on-fail.php';
require_once dirname( __DIR__ ) . '/fixtures.php';

if ( ! class_exists( '\WPSellServicesPro\Integrations\WooCommerce\WCPayOrderResolver' ) || ! function_exists( 'wc_create_order' ) ) {
	echo "SKIP  Pro or WooCommerce is not active.\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f      = wpss_test_fixtures();
$parent = ( $f->order )( array( 'status' => 'in_progress', 'platform' => 'request', 'customer_id' => $f->buyer, 'vendor_id' => $f->vendor, 'service_id' => ( $f->service )( 'publish' ), 'total' => 100 ) );

$milestones = new \WPSellServices\Services\MilestoneService();
$milestones->propose( $parent, $f->vendor, 'Phase one', 'First part', 20, 3 );
$ids = array_map( static fn( $r ) => (int) $r['id'], $milestones->get_for_parent( $parent ) );
foreach ( $ids as $id ) {
	$f->orders[] = $id;
}
$phase = $ids[0] ?? 0;

$wc_order = wc_create_order();
$wc_order->set_total( 20 );
$wc_order->set_status( 'pending' );
$wc_order->save();
$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'meta' => wp_json_encode( array( 'wc_pay_order_id' => $wc_order->get_id() ) ) ), array( 'id' => $phase ) );

$check( 'the store order is waiting for payment', wc_get_order( $wc_order->get_id() )->needs_payment() );
$milestones->decline( $phase, $f->buyer );
$check( 'declining the phase cancels its store order', 'cancelled' === wc_get_order( $wc_order->get_id() )->get_status() );

$wc_order->delete( true );

exit( $fails ? 1 : 0 );
