<?php
/**
 * A full refund of an order also refunds its paid extensions and tips.
 *
 * Run: wp eval-file tests/test-refund-cascade-contract.php
 *
 * Extensions and tips are separate paid sub-orders pointing at the parent
 * through platform_order_id. The refund seam knew nothing about them, so a
 * fully refunded order left its extension completed and credited: the buyer
 * never got that money back and the vendor kept it (Basecamp 10336467671).
 *
 * Gateways are mocked; every row this script creates is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$ledger = $wpdb->prefix . 'wpss_wallet_transactions';
$buyer  = 999999;
$vendor = 999998;

$seed = static function ( float $total, float $earnings, string $platform = 'standalone', int $parent = 0 ) use ( $wpdb, $orders, $ledger, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'      => 'WPSS-CASCADE-' . wp_generate_password( 6, false ),
			'customer_id'       => $buyer,
			'vendor_id'         => $vendor,
			'service_id'        => 0,
			'platform'          => $platform,
			'platform_order_id' => $parent ? $parent : null,
			'subtotal'          => $total,
			'total'             => $total,
			'currency'          => 'USD',
			'status'            => 'completed',
			'payment_status'    => 'paid',
			'payment_method'    => 'stripe',
			'transaction_id'    => 'pi_cascade_' . wp_generate_password( 8, false ),
			'vendor_earnings'   => $earnings,
			'platform_fee'      => $total - $earnings,
			'created_at'        => current_time( 'mysql' ),
		)
	);
	$id = (int) $wpdb->insert_id;

	wpss_insert_ledger_row(
		array(
			'user_id'        => $vendor,
			'type'           => 'order_earning',
			'amount'         => $earnings,
			'balance_after'  => $earnings,
			'currency'       => 'USD',
			'description'    => 'cascade contract credit',
			'reference_type' => 'order',
			'reference_id'   => $id,
			'status'         => 'completed',
			'created_at'     => current_time( 'mysql' ),
		)
	);

	return $id;
};

$status    = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) );
$reversals = static fn( int $id ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_id = %d AND type = 'order_reversal'", $id ) );

$refunded_orders = array();
$mock            = static function ( $handled, $order ) use ( &$refunded_orders, $vendor ) {
	if ( (int) $order->vendor_id !== $vendor ) {
		return $handled;
	}
	$refunded_orders[] = (int) $order->id;
	return array( 'success' => true, 'refund_id' => 're_cascade_' . $order->id );
};
add_filter( 'wpss_pre_process_gateway_refund', $mock, 10, 2 );

$service = new OrderService();
$ids     = array();

try {
	// --- Full parent refund reaches the extension and the tip ------------------
	$parent    = $seed( 100.0, 90.0 );
	$extension = $seed( 20.0, 18.0, ServiceOrder::SUB_ORDER_TYPE_EXTENSION, $parent );
	$tip       = $seed( 10.0, 9.0, ServiceOrder::SUB_ORDER_TYPE_TIP, $parent );
	array_push( $ids, $parent, $extension, $tip );

	$result = method_exists( $service, 'refund' )
		? $service->refund( $parent, null, ServiceOrder::STATUS_REFUNDED, array( 'origin' => 'admin' ) )
		: array( 'ok' => $service->apply_refund_status( $parent, null, ServiceOrder::STATUS_REFUNDED ) );

	$check( 'the parent is refunded', true === ( $result['ok'] ?? null ) && 'refunded' === $status( $parent ) );
	$check( 'the paid extension is refunded with it', 'refunded' === $status( $extension ) );
	$check( '  its money went back through the gateway', in_array( $extension, $refunded_orders, true ) );
	$check( '  and the vendor credit for it is reversed', 1 === $reversals( $extension ) );
	$check( 'the paid tip is refunded with it', 'refunded' === $status( $tip ) );
	$check( '  and the vendor credit for it is reversed', 1 === $reversals( $tip ) );
	$check( 'the result lists what happened to each child', isset( $result['children'][ $extension ], $result['children'][ $tip ] ) );

	// --- A partial refund leaves the children alone ----------------------------
	$parent2    = $seed( 100.0, 90.0 );
	$extension2 = $seed( 20.0, 18.0, ServiceOrder::SUB_ORDER_TYPE_EXTENSION, $parent2 );
	array_push( $ids, $parent2, $extension2 );

	$service->apply_refund_status( $parent2, 30.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
	$check( 'a partial parent refund leaves the extension completed', 'completed' === $status( $extension2 ) );
	$check( '  and its credit in place', 0 === $reversals( $extension2 ) );

	// --- A refund that started at the rail leaves children for a person --------
	// The rail refunded one charge; the extension was a separate one. Refunding
	// it blind could pay the buyer twice, so it is listed for the owner instead.
	$parent3    = $seed( 100.0, 90.0 );
	$extension3 = $seed( 20.0, 18.0, ServiceOrder::SUB_ORDER_TYPE_EXTENSION, $parent3 );
	array_push( $ids, $parent3, $extension3 );

	$service->apply_refund_status( $parent3, null, ServiceOrder::STATUS_REFUNDED, true );
	$check( 'a rail-initiated full refund does not refund the extension blind', 'completed' === $status( $extension3 ) );

	$review = function_exists( 'wpss_get_refund_review_items' ) ? wpss_get_refund_review_items( 200 ) : array( 'uncascaded' => array() );
	$check( '  and the parent is listed for the owner to review', in_array( $parent3, $review['uncascaded'], true ) );
	$check( '  while the cascaded parent is not', ! in_array( $parent, $review['uncascaded'], true ) );
} finally {
	remove_filter( 'wpss_pre_process_gateway_refund', $mock, 10 );

	foreach ( $ids as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
	foreach ( array( $buyer, $vendor ) as $u ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $u ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
