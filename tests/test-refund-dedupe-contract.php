<?php
/**
 * One refund is counted once, whichever path reports it.
 *
 * Run: wp eval-file tests/test-refund-dedupe-contract.php
 *
 * - A wp-admin partial refund on PayPal/Razorpay, then that gateway's own
 *   webhook for the same refund id: counted once (Basecamp 10375174271).
 * - WooCommerce rail (skipped without WooCommerce + Pro): two partial refunds
 *   in Woo record Woo's total, not the sum of running totals (10375174481);
 *   a refund started in wp-admin is recorded once and reports success
 *   (10372722693).
 *
 * Gateways are mocked; every row this script creates is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = 999997;
$vendor = 999996;

$seed = static function ( float $total, string $method, string $platform = 'standalone', int $platform_order_id = 0 ) use ( $wpdb, $orders, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'      => 'WPSS-DEDUPE-' . wp_generate_password( 6, false ),
			'customer_id'       => $buyer,
			'vendor_id'         => $vendor,
			'service_id'        => 0,
			'platform'          => $platform,
			'platform_order_id' => $platform_order_id ? $platform_order_id : null,
			'subtotal'          => $total,
			'total'             => $total,
			'currency'          => 'USD',
			'status'            => 'in_progress',
			'payment_status'    => 'paid',
			'payment_method'    => $method,
			'transaction_id'    => 'txn_dedupe_' . wp_generate_password( 8, false ),
			'created_at'        => current_time( 'mysql' ),
		)
	);

	return (int) $wpdb->insert_id;
};

$refunded = static fn( int $id ): float => (float) $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM {$orders} WHERE id = %d", $id ) );

$mock = static function ( $handled, $order ) use ( $vendor ) {
	if ( (int) $order->vendor_id !== $vendor || 'woocommerce' === $order->platform ) {
		return $handled;
	}
	return array(
		'success'   => true,
		'refund_id' => 'REF-' . $order->id,
	);
};
add_filter( 'wpss_pre_process_gateway_refund', $mock, 10, 2 );

$service     = new OrderService();
$ids         = array();
$dispute_ids = array();
$wc_orders   = array();

try {
	// --- wp-admin partial refund, then the gateway's webhook for it ------------
	foreach ( array( 'paypal', 'razorpay' ) as $gateway ) {
		$id    = $seed( 20.0, $gateway );
		$ids[] = $id;
		$txn   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM {$orders} WHERE id = %d", $id ) );

		$service->refund( $id, 10.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED, array( 'origin' => 'admin' ) );
		( new OrderWorkflowManager() )->handle_gateway_refund(
			$gateway,
			$txn,
			10.0,
			array(
				'order_id'  => $id,
				'refund_id' => 'REF-' . $id,
				'currency'  => 'USD',
			)
		);

		$check( "{$gateway}: admin refund + its own webhook records 10, not 20", 10.0 === $refunded( $id ) );
	}

	// --- A full refund resolves the order's open dispute (10372723332) ---------
	// Rail refund (webhook) and admin refund both leave nothing to dispute; the
	// dispute is resolved as full_refund without moving money again.
	$disputes = new \WPSellServices\Services\DisputeService();
	foreach ( array( 'rail', 'admin' ) as $path ) {
		$id    = $seed( 30.0, 'stripe' );
		$ids[] = $id;
		$did   = (int) $disputes->open( $id, $buyer, 'other', 'dedupe contract dispute' );
		$txn   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM {$orders} WHERE id = %d", $id ) );

		if ( 'rail' === $path ) {
			( new OrderWorkflowManager() )->handle_gateway_refund( 'stripe', $txn, 30.0, array( 'order_id' => $id, 'currency' => 'USD', 'cumulative' => true, 'refund_id' => 're_dispute_' . $id ) );
		} else {
			$service->refund( $id, null, ServiceOrder::STATUS_REFUNDED, array( 'origin' => 'admin' ) );
		}

		$state = $wpdb->get_row( $wpdb->prepare( "SELECT status, resolution FROM {$wpdb->prefix}wpss_disputes WHERE id = %d", $did ) );
		$check( "{$path} full refund resolves the open dispute as full_refund", $did > 0 && $state && 'resolved' === $state->status && 'full_refund' === $state->resolution );
		$dispute_ids[] = $did;
	}

	// --- WooCommerce rail ------------------------------------------------------
	$wc_provider = class_exists( '\WPSellServicesPro\Integrations\WooCommerce\WCOrderProvider' ) && function_exists( 'wc_create_order' )
		? new \WPSellServicesPro\Integrations\WooCommerce\WCOrderProvider()
		: null;

	if ( ! $wc_provider ) {
		echo "SKIP  WooCommerce rail checks (needs WooCommerce + Pro)\n";
	} else {
		// The rail hooks only register on a Woo-rail site; wire them for this run.
		$loop = array( $wc_provider, 'handle_order_refunded' );
		$pre  = array( $wc_provider, 'process_wpss_refund' );
		add_action( 'woocommerce_order_refunded', $loop, 10, 2 );
		add_filter( 'wpss_pre_process_gateway_refund', $pre, 10, 4 );

		$make_wc = static function () use ( &$wc_orders ): \WC_Order {
			$wc  = wc_create_order();
			$fee = new \WC_Order_Item_Fee();
			$fee->set_name( 'QA dedupe' );
			$fee->set_total( 100 );
			$wc->add_item( $fee );
			$wc->set_payment_method( 'cod' );
			$wc->calculate_totals( false );
			$wc->set_status( 'processing' );
			$wc->save();
			$wc_orders[] = $wc->get_id();
			return $wc;
		};

		// Two partial refunds made in Woo.
		$wc    = $make_wc();
		$id    = $seed( 100.0, 'cod', 'woocommerce', $wc->get_id() );
		$ids[] = $id;
		wc_create_refund( array( 'order_id' => $wc->get_id(), 'amount' => 10, 'refund_payment' => false ) );
		wc_create_refund( array( 'order_id' => $wc->get_id(), 'amount' => 5, 'refund_payment' => false ) );
		$check( 'woo: refunds of 10 then 5 in Woo record 15, not 25', 15.0 === $refunded( $id ) );

		// A refund started in wp-admin.
		$wc     = $make_wc();
		$id     = $seed( 100.0, 'cod', 'woocommerce', $wc->get_id() );
		$ids[]  = $id;
		$result = $service->refund( $id, 10.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED, array( 'origin' => 'admin' ) );
		$check( 'woo: a wp-admin refund reports success', true === ( $result['ok'] ?? null ) );
		$check( '  and records 10 once', 10.0 === $refunded( $id ) );
		$check( '  and Woo shows 10 refunded', 10.0 === (float) wc_get_order( $wc->get_id() )->get_total_refunded() );

		// A later refund made in Woo adds only its own amount.
		wc_create_refund( array( 'order_id' => $wc->get_id(), 'amount' => 5, 'refund_payment' => false ) );
		$check( '  then 5 more refunded in Woo records 15', 15.0 === $refunded( $id ) );

		remove_action( 'woocommerce_order_refunded', $loop, 10 );
		remove_filter( 'wpss_pre_process_gateway_refund', $pre, 10 );
	}
} finally {
	remove_filter( 'wpss_pre_process_gateway_refund', $mock, 10 );

	foreach ( $ids as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	foreach ( $dispute_ids as $did ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $did ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'dispute', 'object_id' => $did ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'id' => $did ) );
	}
	foreach ( $wc_orders as $wc_id ) {
		$wc = wc_get_order( $wc_id );
		if ( $wc ) {
			foreach ( $wc->get_refunds() as $r ) {
				$r->delete( true );
			}
			$wc->delete( true );
		}
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $vendor ) );
	foreach ( array( $buyer, $vendor ) as $u ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $u ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
