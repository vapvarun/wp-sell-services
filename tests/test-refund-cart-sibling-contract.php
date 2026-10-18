<?php
/**
 * A refund reported by a payment rail lands on the gig it is for, never on a guess.
 *
 * Run: wp eval-file tests/test-refund-cart-sibling-contract.php
 *
 * One cart is one charge with a gig order per line. A rail that reports a running total for the
 * charge must not re-apply what we already recorded to the sibling gigs, and a refund that names
 * no gig waits for an admin (PendingRefundService) instead of being split by price.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\PendingRefundService;

require_once __DIR__ . '/exit-on-fail.php';

if ( ! class_exists( PendingRefundService::class ) ) {
	echo "FAIL  PendingRefundService is missing\n";
	exit( 1 );
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';
$txn   = 'pi_sibling_' . wp_generate_password( 8, false );
$ids   = array();

$seed = static function ( float $total, string $transaction_id ) use ( $wpdb, $table, &$ids ): int {
	$wpdb->insert(
		$table,
		array(
			'order_number'    => 'WPSS-SIB-' . wp_generate_password( 6, false ),
			'customer_id'     => 1,
			'vendor_id'       => 1,
			'service_id'      => 0,
			'platform'        => 'standalone',
			'total'           => $total,
			'subtotal'        => $total,
			'currency'        => 'USD',
			'status'          => 'completed',
			'payment_status'  => 'paid',
			'payment_method'  => 'stripe',
			'transaction_id'  => $transaction_id,
			'commission_rate' => 10,
			'platform_fee'    => $total / 10,
			'vendor_earnings' => $total * 0.9,
			'created_at'      => current_time( 'mysql' ),
		)
	);
	$ids[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};
$row   = static fn( int $id ): ?object => $wpdb->get_row( $wpdb->prepare( "SELECT status, refunded_amount FROM {$table} WHERE id = %d", $id ) );
$got   = static fn( int $id ): float => (float) ( $row( $id )->refunded_amount ?? 0 );
$rail  = static function ( float $total, array $ctx = array() ) use ( $txn ): void {
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn, $total, array_merge( array( 'cumulative' => true, 'currency' => 'USD' ), $ctx ) );
};

// wpss_gateway_refund_unallocated fires once for each refund that is parked.
$parked = 0;
$count  = static function () use ( &$parked ): void {
	++$parked;
};
add_action( 'wpss_gateway_refund_unallocated', $count );

$a = $seed( 100.0, $txn );
$b = $seed( 50.0, $txn );

try {
	// 1. We refund A ourselves (money moved at the rail). The rail then reports the charge total.
	( new OrderService() )->refund( $a, null, ServiceOrder::STATUS_REFUNDED, array( 'settled_at_rail' => true ) );
	$rail( 100.0, array( 'refund_id' => 're_1' ) );
	$check( 'a running total we already recorded does not touch the sibling gig', 0.0 === $got( $b ) && 'completed' === $row( $b )->status );
	$check( '  and nothing is parked', null === PendingRefundService::get( $b ) );

	// 2. A dashboard refund of 20 more names no gig, and two gigs share the charge.
	$rail( 120.0, array( 'refund_id' => 're_2' ) );
	$check( 'a refund that names no gig is not applied to either gig', 0.0 === $got( $b ) );
	$pending = PendingRefundService::get( $b );
	$check( '  it waits for an admin on every gig of the charge', null !== $pending && 20.0 === (float) $pending['remaining'] && null !== PendingRefundService::get( $a ) );
	$check( '  and wpss_gateway_refund_unallocated fired once', 1 === $parked );

	// 3. The same event again, and a larger total, add only the new money.
	$rail( 120.0, array( 'refund_id' => 're_2' ) );
	$check( 'the same event reported twice parks nothing more', 20.0 === (float) PendingRefundService::get( $b )['remaining'] );
	$rail( 130.0, array( 'refund_id' => 're_3' ) );
	$check( 'a later running total adds only its difference', 30.0 === (float) PendingRefundService::get( $b )['remaining'] );

	// 4. The admin assigns it. Wrong gig, over-assign and a second click are all refused.
	$service = new PendingRefundService();
	$other   = $seed( 40.0, 'pi_other_' . $txn );
	$check( 'a gig that is not on the charge is refused', false === $service->assign( $other, 10.0 )['ok'] );

	// Over REST (POST /wpss/v1/orders/{x}/{x}, action assign-refund): admin only.
	$route = static function ( int $order_id, array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wpss/v1/orders/' . $order_id . '/assign-refund' );
		$request->set_body_params( $params );
		return rest_do_request( $request );
	};
	wp_set_current_user( 0 );
	$check( 'a visitor cannot assign a refund over REST', 200 !== $route( $b, array( 'amount' => 10 ) )->get_status() && 0.0 === $got( $b ) );
	wp_set_current_user( 1 );
	$reply = $route( $b, array( 'amount' => 10 ) );
	$check( 'an admin assigns 10 of the waiting 30 over REST', 200 === $reply->get_status() && 10.0 === $got( $b ) );
	wp_set_current_user( 0 );

	// The CLI door (wpss refunds pending / assign) is the same service.
	$check( 'wpss refunds has a pending and an assign command', method_exists( \WPSellServices\CLI\RefundsCommand::class, 'pending' ) && method_exists( \WPSellServices\CLI\RefundsCommand::class, 'assign' ) );

	$check( 'assigning 10 more refunds that gig 20 in all', true === $service->assign( $b, 10.0 )['ok'] && 20.0 === $got( $b ) );
	$check( '  and 10 is still waiting', 10.0 === (float) PendingRefundService::get( $b )['remaining'] );
	$over = $service->assign( $b, 500.0 );
	$check( '  assigning more than is waiting is capped at what is waiting', true === $over['ok'] && 10.0 === $over['amount'] && 30.0 === $got( $b ) );
	$check( '  and nothing stays parked afterwards', null === PendingRefundService::get( $b ) && false === $service->assign( $b, 5.0 )['ok'] );

	// 5. A refund covering everything left on the charge refunds each gig in full.
	$txn2 = 'pi_full_' . wp_generate_password( 6, false );
	$c    = $seed( 60.0, $txn2 );
	$d    = $seed( 40.0, $txn2 );
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn2, 100.0, array( 'cumulative' => true, 'currency' => 'USD', 'refund_id' => 're_full' ) );
	$check( 'a refund of the whole charge refunds every gig', 'refunded' === $row( $c )->status && 'refunded' === $row( $d )->status );

	// 6. A refund we are sending right now is not recorded again when the rail echoes it.
	$txn3 = 'pi_lock_' . wp_generate_password( 6, false );
	$e    = $seed( 80.0, $txn3 );
	set_transient( 'wpss_refund_lock_' . $e, 1, 60 );
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn3, 30.0, array( 'cumulative' => true, 'currency' => 'USD' ) );
	$check( 'the echo of a refund in flight is skipped', 0.0 === $got( $e ) );
	$locked = ( new OrderService() )->refund( $e, 30.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
	$check( '  and a second refund on the same order is refused', false === $locked['ok'] && 'refused' === $locked['outcome'] );
	delete_transient( 'wpss_refund_lock_' . $e );
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn3, 30.0, array( 'cumulative' => true, 'currency' => 'USD' ) );
	$check( '  with the lock gone a refund from the rail is recorded', 30.0 === $got( $e ) );

	// 7. An order paid through a shop platform is refunded there, not from our screens.
	$f      = $seed( 70.0, 'wc_' . $txn3 );
	$filter = static fn() => 'WooCommerce';
	add_filter( 'wpss_order_money_rail', $filter );
	$refused = ( new OrderService() )->refund( $f, 70.0, ServiceOrder::STATUS_REFUNDED );
	$check( 'our refund on a platform-paid order is refused and names the platform', false === $refused['ok'] && false !== strpos( $refused['message'], 'WooCommerce' ) && 'completed' === $row( $f )->status );
	do_action( 'wpss_gateway_refund_received', 'woocommerce', 'wc_' . $txn3, 20.0, array( 'order_id' => $f, 'cumulative' => true, 'currency' => 'USD', 'refund_id' => 'wc_refund_1' ) );
	remove_filter( 'wpss_order_money_rail', $filter );
	$check( '  but the platform\'s own refund is recorded', 20.0 === $got( $f ) );
} finally {
	remove_action( 'wpss_gateway_refund_unallocated', $count );
	foreach ( $ids as $id ) {
		wpss_get_order_provider()->update_item_meta( $id, PendingRefundService::META, '' );
		$wpdb->delete( $table, array( 'id' => $id ) );
	}
}

exit( $fails ? 1 : 0 );
