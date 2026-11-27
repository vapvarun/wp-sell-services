<?php
/**
 * Commission is "recorded" only when the vendor's credit is on the ledger (Basecamp 10551463043).
 *
 * create_earnings_transaction() rolled back silently and record() still returned true and fired
 * wpss_commission_recorded, on which Pro's Stripe Connect writes its offsetting debit: a failed credit
 * left the vendor uncredited (Free) or negative (Connect). And the ledger row was stamped with the
 * store's currency, not the order's, so a currency change booked USD earnings as EUR.
 *
 * Run: wp eval-file tests/test-commission-credit-must-land.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\CommissionService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f      = wpss_test_fixtures();
$ledger = $wpdb->prefix . 'wpss_wallet_transactions';
$other  = 'USD' === wpss_get_currency() ? 'EUR' : 'USD';
$order  = ( $f->order )( array( 'status' => 'completed', 'total' => 50, 'subtotal' => 50, 'currency' => $other, 'payment_status' => 'paid' ) );
$fired  = 0;
$count  = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_commission_recorded', $count );

$credit = static fn() => $wpdb->get_row( $wpdb->prepare( "SELECT amount, currency, description FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $order ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

try {
	// 1. The ledger insert fails (deadlock, lost connection): not recorded, no hook.
	$break = static fn( string $q ): string => ( 0 === stripos( ltrim( $q ), 'INSERT' ) && str_contains( $q, $ledger ) && str_contains( $q, 'order_earning' ) ) ? 'SELECT qa_forced_failure FROM qa_no_such_table' : $q;
	add_filter( 'query', $break );
	$res = ( new CommissionService() )->record( $order );
	remove_filter( 'query', $break );
	$check( 'Credit failed: record() says false', false === $res );
	$check( '  wpss_commission_recorded did not fire (Connect would debit a credit that is not there)', 0 === $fired, (string) $fired );
	$check( '  no credit row', null === $credit() );
	$check( '  a retry is scheduled for 5 minutes on (attempt 1 of 3)', \WPSellServices\Services\Scheduler::has_pending( 'wpss_retry_commission', array( $order, 1 ) ) );
	$check( '  and the retry job is hooked to record()', false !== has_action( 'wpss_retry_commission' ) );

	// 2. The retry runs: credited once, in the order's currency.
	$res = ( new CommissionService() )->record( $order, 1 );
	$row = $credit();
	$check( 'Retry: record() true, hook fired once', true === $res && 1 === $fired, var_export( $res, true ) . " {$fired}" );
	$check( "  the credit is booked in the order's currency ({$other}), not the store's", $row && $other === $row->currency, $row ? $row->currency : 'none' );
	$check( '  and its description shows that currency', $row && str_contains( $row->description, wpss_format_price( 50.0, $other ) ), $row ? $row->description : '' );

	// 3. A retry for an order refunded in the meantime credits nothing.
	$late = ( $f->order )( array( 'status' => 'refunded', 'total' => 50, 'subtotal' => 50, 'payment_status' => 'paid' ) );
	$res  = ( new CommissionService() )->record( $late, 1 );
	$got  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $late ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( 'Retry on an order refunded meanwhile: dropped, no credit', false === $res && '0' === (string) $got, var_export( $res, true ) . " {$got}" );
} finally {
	remove_action( 'wpss_commission_recorded', $count );
	\WPSellServices\Services\Scheduler::unschedule( 'wpss_retry_commission', array( $order, 1 ) );
	$wpdb->delete( $ledger, array( 'reference_id' => $order, 'user_id' => $f->vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
