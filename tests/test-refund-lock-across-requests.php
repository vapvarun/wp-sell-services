<?php
/**
 * One refund per order at a time, across requests, on every path (Basecamp 10551462962).
 *
 * The per-order refund lock was get_transient then set_transient, so two requests (an admin refund and a
 * dispute ruling, or a double submit) both passed it; a refund the rail reported took no lock at all, so two
 * charge.refunded events on two workers both read the same "already recorded". Now a MySQL named lock, taken
 * by every path. And a ledger row that collides with the unique index for a different amount is not "done".
 *
 * A second database connection stands in for the other request.
 *
 * Run: wp eval-file tests/test-refund-lock-across-requests.php
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f     = wpss_test_fixtures();
$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$name  = static fn( int $id ): string => wpss_lock_name( 'wpss_refund_' . $id );
$hold  = static fn( int $id ): bool => '1' === (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK( %s, 0 )', $name( $id ) ) );
$free  = static fn( int $id ) => $other->query( $other->prepare( 'SELECT RELEASE_LOCK( %s )', $name( $id ) ) );
$got   = static fn( int $id ): float => (float) $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM {$f->orders_t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

try {
	// 1. An admin refund while another request is refunding the same order is refused.
	$a = ( $f->order )( array( 'status' => 'completed', 'total' => 80, 'subtotal' => 80, 'payment_method' => 'offline' ) );
	$check( 'The other request holds the order\'s refund lock', $hold( $a ) );
	$check( '  and this request sees it as locked', OrderService::is_refund_locked( $a ) );
	$res = ( new OrderService() )->refund( $a, 40.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED, array( 'origin' => 'admin' ) );
	$check( '  a second refund is refused, nothing recorded', false === $res['ok'] && 'refused' === $res['outcome'] && 0.0 === $got( $a ), $res['outcome'] . ' ' . $got( $a ) );
	$free( $a );
	$check( 'Lock released: this request no longer sees it locked', ! OrderService::is_refund_locked( $a ) );

	// 2. A rail-reported refund while another request holds the lock waits instead of recording.
	$txn = 'ch_qa_462962_' . wp_generate_password( 6, false );
	$b   = ( $f->order )( array( 'status' => 'completed', 'total' => 90, 'subtotal' => 90, 'payment_method' => 'stripe', 'transaction_id' => $txn ) );
	$hold( $b );
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn, 30.0, array( 'cumulative' => true, 'refund_id' => 're_qa_a_' . $b ) );
	$check( 'Rail refund while another request holds the lock: not recorded now', 0.0 === $got( $b ), (string) $got( $b ) );
	$free( $b );
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn, 30.0, array( 'cumulative' => true, 'refund_id' => 're_qa_a_' . $b ) );
	$check( '  and recorded once the lock is free (30)', 30.0 === $got( $b ), (string) $got( $b ) );
	$check( '  the rail path released its own lock', ! OrderService::is_refund_locked( $b ) && '1' === (string) $other->get_var( $other->prepare( 'SELECT IS_FREE_LOCK( %s )', $name( $b ) ) ) );

	// 3. A ledger row that collides on the unique index with a different amount is not success.
	$ref  = array(
		'user_id'        => $f->vendor,
		'type'           => 'order_reversal',
		'reference_type' => 'qa_462962',
		'reference_id'   => $b,
		'currency'       => 'USD',
	);
	$one  = wpss_insert_ledger_row( $ref + array( 'amount' => -10.0 ) );
	$same = wpss_insert_ledger_row( $ref + array( 'amount' => -10.0 ) );
	$diff = wpss_insert_ledger_row( $ref + array( 'amount' => -25.0 ) );
	$check( 'Ledger: first row written', $one );
	$check( '  a replay of the same row is still fine', $same );
	$check( '  a different amount under the same reference is reported as NOT written', false === $diff );
} finally {
	$free( $a ?? 0 );
	$free( $b ?? 0 );
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_type' => 'qa_462962' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( array( $a ?? 0, $b ?? 0 ) as $oid ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => $oid, 'user_id' => $f->vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
