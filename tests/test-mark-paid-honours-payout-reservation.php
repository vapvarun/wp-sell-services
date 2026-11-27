<?php
/**
 * Marking a withdrawal paid leaves out money a payout rail has in flight (Basecamp 10551463105).
 *
 * EarningsService::mark_paid() compared the withdrawal against the raw ledger balance. Pro's PayPal batch
 * reserves an in-flight item through wpss_vendor_reserved_payouts and debits only when a later sync sees
 * success, so an admin could mark a manual withdrawal of the same money paid meanwhile: paid twice. The
 * request and auto-withdrawal paths already read available_balance, which subtracts the reservation.
 *
 * Run: wp eval-file tests/test-mark-paid-honours-payout-reservation.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$ledger  = $wpdb->prefix . 'wpss_wallet_transactions';
$table   = $wpdb->prefix . 'wpss_withdrawals';
$service = new EarningsService();
$vendor  = (int) wp_insert_user(
	array(
		'user_login' => 'wpss_rsv_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'rsv' . wp_generate_password( 6, false ) . '@example.test',
	)
);
$reserve = static fn( float $r, int $v ): float => $v === $vendor ? $r + 100.0 : $r;

try {
	// Earned 100, cleared long ago.
	wpss_insert_ledger_row( array( 'user_id' => $vendor, 'type' => 'order_earning', 'amount' => 100, 'reference_type' => 'rsv_test', 'reference_id' => $vendor, 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
	$wpdb->insert( $table, array( 'vendor_id' => $vendor, 'amount' => 100, 'method' => 'bank_transfer', 'details' => '', 'status' => 'approved', 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wid = (int) $wpdb->insert_id;

	// A PayPal batch has the same 100 in flight.
	add_filter( 'wpss_vendor_reserved_payouts', $reserve, 10, 2 );
	$paid = $service->mark_paid( $wid, 'sent by bank' );
	$check( 'Reserved 100 of 100: marking the manual withdrawal paid is refused', false === $paid['success'] && 'insufficient_balance' === ( $paid['code'] ?? '' ), wp_json_encode( $paid ) );
	$check( '  no withdrawal debit written', '0' === (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE user_id = %d AND reference_type = 'withdrawal'", $vendor ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( '  a new request for that money is refused too (same rule)', false === $service->request_withdrawal( $vendor, 50.0, 'bank_transfer', array() )['success'] );
	remove_filter( 'wpss_vendor_reserved_payouts', $reserve, 10 );

	// The batch failed and released the money: paying by hand now works.
	$paid = $service->mark_paid( $wid, 'sent by bank' );
	$check( 'Reservation released: marked paid', ! empty( $paid['success'] ), wp_json_encode( $paid ) );
} finally {
	remove_filter( 'wpss_vendor_reserved_payouts', $reserve, 10 );
	$wpdb->delete( $ledger, array( 'user_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $table, array( 'vendor_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
