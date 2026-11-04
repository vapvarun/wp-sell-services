<?php
/**
 * Declining or deleting an unpaid milestone phase cancels its pending WooCommerce order, so an old pay
 * link cannot take money for a phase that no longer exists (combo walk, 1.9.0).
 *
 * 1.9.2: the same for an abandoned tip, an abandoned extension and a declined extension. Those closed
 * their sub-order with no signal, so the store order stayed payable and a buyer who paid it was
 * charged with nobody credited. Every path now fires wpss_unpaid_sub_order_closed.
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

// The resolver is wired only while WooCommerce is the rail; on another rail
// there is no store order to cancel, which is a skip, not a failure.
$wired = false;
foreach ( $GLOBALS['wp_filter']['wpss_unpaid_sub_order_closed']->callbacks ?? array() as $callbacks ) {
	foreach ( $callbacks as $callback ) {
		$wired = $wired || ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \WPSellServicesPro\Integrations\WooCommerce\WCPayOrderResolver );
	}
}
if ( ! $wired ) {
	echo "SKIP  WooCommerce is not the active rail.\n";
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

// A pending store order linked to a sub-order.
$store_order = static function ( int $sub_id ) use ( $wpdb ): WC_Order {
	$o = wc_create_order();
	$o->set_total( 5 );
	$o->set_status( 'pending' );
	$o->save();
	$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'meta' => wp_json_encode( array( 'wc_pay_order_id' => $o->get_id() ) ) ), array( 'id' => $sub_id ) );
	return $o;
};
// Run a site-wide sweep against one seeded row only.
$confined = static function ( int $id, callable $sweep ) {
	$filter = static function ( $sql ) use ( $id ) {
		if ( 0 === stripos( ltrim( $sql ), 'SELECT' ) && false !== strpos( $sql, "'pending_payment'" ) && false !== strpos( $sql, 'created_at <' ) ) {
			$sql .= ' AND id = ' . $id;
		}
		return $sql;
	};
	add_filter( 'query', $filter );
	$sweep();
	remove_filter( 'query', $filter );
};
$old = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

$tip       = ( $f->order )( array( 'platform' => \WPSellServices\Services\TippingService::ORDER_TYPE, 'platform_order_id' => $parent, 'status' => 'pending_payment', 'payment_status' => 'pending', 'total' => 5, 'created_at' => $old ) );
$tip_store = $store_order( $tip );
$confined( $tip, array( new \WPSellServices\Services\TippingService(), 'cleanup_abandoned_tips' ) );
$check( 'an abandoned tip cancels its store order', 'cancelled' === wc_get_order( $tip_store->get_id() )->get_status() );
$tip_store->delete( true );

$ext_table = $wpdb->prefix . 'wpss_extension_requests';
$extension = static function ( string $created ) use ( $f, $parent, $wpdb, $ext_table ): array {
	$sub = ( $f->order )( array( 'platform' => \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE, 'platform_order_id' => $parent, 'status' => 'pending_payment', 'payment_status' => 'pending', 'total' => 5, 'created_at' => $created ) );
	$wpdb->insert( $ext_table, array( 'order_id' => $parent, 'requested_by' => $f->vendor, 'extra_days' => 2, 'amount' => 5, 'pay_order_id' => $sub, 'reason' => 'QA', 'status' => \WPSellServices\Services\ExtensionRequestService::STATUS_PENDING ) );
	return array( $sub, (int) $wpdb->insert_id );
};

list( $ext_sub, $ext_id ) = $extension( $old );
$ext_store                = $store_order( $ext_sub );
$confined( $ext_sub, array( new \WPSellServices\Services\ExtensionOrderService(), 'cleanup_abandoned_extensions' ) );
$check( 'an abandoned extension cancels its store order', 'cancelled' === wc_get_order( $ext_store->get_id() )->get_status() );
$ext_store->delete( true );
$wpdb->delete( $ext_table, array( 'id' => $ext_id ) );

list( $ext_sub, $ext_id ) = $extension( current_time( 'mysql', true ) );
$ext_store                = $store_order( $ext_sub );
$declined                 = ( new \WPSellServices\Services\ExtensionOrderService() )->decline( $ext_id, $f->buyer );
$check( 'the buyer can decline the extension', ! empty( $declined['success'] ) );
$check( 'a declined extension cancels its store order', 'cancelled' === wc_get_order( $ext_store->get_id() )->get_status() );
$ext_store->delete( true );
$wpdb->delete( $ext_table, array( 'id' => $ext_id ) );

exit( $fails ? 1 : 0 );
