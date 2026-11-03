<?php
/**
 * A pay-order settled another way cancels its pending WooCommerce order.
 *
 * An admin's Mark as Paid (or the settle REST route) on a tip, extension or phase left its
 * WooCommerce pay-order pending, so the buyer's old pay link still took the money a second time.
 * Once the service order is paid, a store order that still asks for payment is cancelled; one that
 * took the payment itself is left alone.
 *
 * And WooCommerce's own pay page now asks Free's one refusal rule: an extension quote on hold while
 * its order is in dispute cannot be paid through an old store link, and can again once it is active.
 *
 * Run: wp eval-file tests/pro/test-paid-elsewhere-cancels-store-order.php
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Gateways\OfflineGateway;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\ExtensionOrderService;

require_once dirname( __DIR__ ) . '/exit-on-fail.php';
require_once dirname( __DIR__ ) . '/fixtures.php';

if ( ! class_exists( '\WPSellServicesPro\Integrations\WooCommerce\WCPayOrderResolver' ) || ! function_exists( 'wc_create_order' ) ) {
	echo "SKIP  Pro or WooCommerce is not active.\n";
	return;
}

// Wired only while WooCommerce is the rail; elsewhere there is no store order to cancel.
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
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
add_filter( 'pre_wp_mail', '__return_true' );
$f      = wpss_test_fixtures();
$parent = ( $f->order )( array( 'status' => 'completed', 'service_id' => ( $f->service )( 'publish' ) ) );

// An unpaid tip sub-order whose pay-order lives in WooCommerce.
$store_order = static function ( string $status ): WC_Order {
	$o = wc_create_order();
	$o->set_total( 5 );
	$o->set_status( $status );
	$o->save();
	return $o;
};
$tip = static function ( WC_Order $wc ) use ( $f, $parent ): int {
	return ( $f->order )(
		array(
			'platform'          => ServiceOrder::SUB_ORDER_TYPE_TIP,
			'platform_order_id' => $parent,
			'status'            => 'pending_payment',
			'payment_status'    => 'pending',
			'payment_method'    => '',
			'paid_at'           => null,
			'total'             => 5,
			'subtotal'          => 5,
			'meta'              => wp_json_encode( array( 'wc_pay_order_id' => $wc->get_id() ) ),
		)
	);
};

$wc  = $store_order( 'pending' );
$sub = $tip( $wc );
$check( 'the store order is waiting for payment', wc_get_order( $wc->get_id() )->needs_payment() );
$result = ( new OfflineGateway() )->settle_manually( $sub, 'qa-bank-ref' );
$check( 'the admin settles the tip by hand', is_array( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
$check( 'its pending store order is cancelled, so the old pay link cannot charge again', 'cancelled' === wc_get_order( $wc->get_id() )->get_status(), wc_get_order( $wc->get_id() )->get_status() );
$wc->delete( true );

// Control: paid through the store order itself, which then no longer needs payment.
$wc2  = $store_order( 'processing' );
$sub2 = $tip( $wc2 );
do_action( 'wpss_order_paid', $sub2, 'qa-wc-txn' );
$check( 'a store order that took the payment is left alone', 'processing' === wc_get_order( $wc2->get_id() )->get_status() );
$wc2->delete( true );

// An extension quote on hold: its order went into dispute.
$active = ( $f->order )( array( 'status' => 'in_progress', 'service_id' => ( $f->service )( 'publish' ) ) );
$wc3    = $store_order( 'pending' );
$ext    = ( $f->order )(
	array(
		'platform'          => ExtensionOrderService::ORDER_TYPE,
		'platform_order_id' => $active,
		'status'            => 'pending_payment',
		'payment_status'    => 'pending',
		'payment_method'    => '',
		'paid_at'           => null,
		'total'             => 5,
		'subtotal'          => 5,
		'meta'              => wp_json_encode( array( 'wc_pay_order_id' => $wc3->get_id() ) ),
	)
);
$wc3->update_meta_data( '_wpss_pay_order_id', (string) $ext );
$wc3->save();
$fresh = static fn() => wc_get_order( $wc3->get_id() );
$check( 'an extension quote on an active order can be paid in the store', $fresh()->needs_payment() );
$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => ServiceOrder::STATUS_DISPUTED ), array( 'id' => $active ) );
$check( 'once its order is in dispute, the store pay page refuses it', ! $fresh()->needs_payment() );
$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => ServiceOrder::STATUS_IN_PROGRESS ), array( 'id' => $active ) );
$check( 'and takes it again when the order is active', $fresh()->needs_payment() );
$wc3->delete( true );

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
