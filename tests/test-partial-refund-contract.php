<?php
/**
 * A partial refund refunds the amount the admin entered, and nothing else.
 *
 * Run: wp eval-file tests/test-partial-refund-contract.php
 *
 * Guards Basecamp 10240143362: apply_refund_status() read 0/null as "refund
 * everything", which is right for a FULL refund and returned the buyer the
 * whole order on a PARTIAL one. Money path - the check stays.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';

// A throwaway paid order to refund against.
$wpdb->insert(
	$table,
	array(
		'order_number'   => 'WPSS-REFUND-CONTRACT',
		'customer_id'    => 1,
		'vendor_id'      => 1,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 200.000,
		'currency'       => 'USD',
		'status'         => 'completed',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

if ( ! $order_id ) {
	echo "could not seed an order; skipping\n";
	return;
}

$service = new OrderService();
$reset   = static function () use ( $wpdb, $table, $order_id ) {
	$wpdb->update(
		$table,
		array( 'status' => 'completed', 'refunded_amount' => 0, 'payment_status' => 'paid' ),
		array( 'id' => $order_id )
	);
};
$refunded = static function () use ( $wpdb, $table, $order_id ) {
	return (float) $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM {$table} WHERE id = %d", $order_id ) );
};

// --- The defect: partial with no amount -----------------------------------
$reset();
$ok = $service->apply_refund_status( $order_id, 0.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'partial refund of 0 is refused', false === $ok );
$check( '  and nothing was refunded', 0.0 === $refunded() );

$reset();
$ok = $service->apply_refund_status( $order_id, null, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'partial refund of null is refused', false === $ok );
$check( '  and nothing was refunded', 0.0 === $refunded() );

// A "partial" for the whole total is a full refund - say so instead.
$reset();
$ok = $service->apply_refund_status( $order_id, 200.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'partial refund for the full total is refused', false === $ok );
$check( '  and nothing was refunded', 0.0 === $refunded() );

// --- The real thing still works -------------------------------------------
$reset();
$service->apply_refund_status( $order_id, 50.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'a real partial refunds exactly what was entered', 50.0 === $refunded() );

$reset();
$service->apply_refund_status( $order_id, null, ServiceOrder::STATUS_REFUNDED );
$check( 'a full refund still means the total', 200.0 === $refunded() );

$wpdb->delete( $table, array( 'id' => $order_id ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
