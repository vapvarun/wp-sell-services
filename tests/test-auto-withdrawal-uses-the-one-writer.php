<?php
/**
 * The auto-withdrawal run obeys every rule a vendor's own withdrawal request does.
 *
 * The scheduled run inserted withdrawal rows on its own: no lock, an amount
 * read before its loop, no minimum, saved payout details unchecked, a "one
 * open request" rule that ignored manual requests, and no check that the
 * vendor was still in good standing. The owner's payout queue filled with
 * second requests, sub-minimum requests, requests with no usable destination
 * and requests for suspended vendors. It now writes through the same locked
 * writer as a manual request. An approved request still waiting to be paid
 * counts as open, and the threshold is re-read under the lock.
 *
 * Run: wp eval-file tests/test-auto-withdrawal-uses-the-one-writer.php
 *
 * Uses throwaway fixture users; the run is confined to them, so no real vendor is paid out.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f           = wpss_test_fixtures();
$withdrawals = $wpdb->prefix . 'wpss_withdrawals';
$ledger      = $wpdb->prefix . 'wpss_wallet_transactions';
$vendor      = $f->vendor;
$service     = new EarningsService();

// Payouts: auto on, threshold 10, minimum 50 unless a case lowers it.
$min       = 50;
$threshold = 10;
$settings  = static function ( $value ) use ( &$min, &$threshold ) {
	return array(
		'auto_withdrawal_enabled'   => 1,
		'auto_withdrawal_threshold' => $threshold,
		'min_withdrawal'            => $min,
	) + (array) $value;
};
add_filter( 'option_wpss_payouts', $settings );
add_filter( 'default_option_wpss_payouts', $settings );
add_filter( 'pre_wp_mail', '__return_true' );

// A completed order puts the vendor in the run; a ledger credit gives a balance.
( $f->order )( array( 'status' => 'completed', 'vendor_id' => $vendor ) );
wpss_insert_ledger_row( array( 'user_id' => $vendor, 'type' => 'credit', 'amount' => 120, 'description' => 'QA auto-withdrawal balance', 'reference_type' => 'qa_auto_withdrawal', 'reference_id' => $vendor ) );

$methods = array_keys( EarningsService::get_withdrawal_methods() );
$method  = in_array( 'paypal', $methods, true ) ? 'paypal' : (string) reset( $methods );
$details = array();
foreach ( EarningsService::get_payout_fields( $method ) as $key => $field ) {
	$details[ $key ] = 'email' === $field['type'] ? 'qa-payout@example.test' : 'QA';
}
$check( 'the vendor can save a payout profile', ! is_wp_error( EarningsService::save_payout_profile( $vendor, $method, $details ) ) );
$check( 'the vendor is in good standing', null === wpss_vendor_status_block( $vendor ) );
$check( 'the vendor has 120 available', abs( 120 - (float) $service->get_summary( $vendor )['available_balance'] ) < 0.01, (string) $service->get_summary( $vendor )['available_balance'] );

// Run the scheduled job, its vendor scan confined to this vendor.
$run = static function () use ( $service, $vendor, $wpdb, $withdrawals ): array {
	$filter = static function ( $sql ) use ( $vendor ) {
		if ( false !== strpos( $sql, 'SELECT DISTINCT vendor_id FROM' ) ) {
			$sql .= ' AND vendor_id = ' . (int) $vendor;
		}
		return $sql;
	};
	add_filter( 'query', $filter );
	$service->process_auto_withdrawals();
	remove_filter( 'query', $filter );
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$withdrawals} WHERE vendor_id = %d ORDER BY id", $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};
$reset = static fn() => $wpdb->delete( $withdrawals, array( 'vendor_id' => $vendor ) );

// 1. A pending manual request already reserves the money: no second request.
$manual = $service->request_withdrawal( $vendor, 60, $method );
$check( 'the vendor asked for 60 by hand', ! empty( $manual['success'] ), wp_json_encode( $manual ) );
$rows = $run();
$check( 'the run adds no second request beside a pending manual one', 1 === count( $rows ), count( $rows ) . ' rows' );
$reset();

// 1b. An approved request still waiting to be paid is open too: no second request, by hand or by the run.
$manual = $service->request_withdrawal( $vendor, 60, $method );
$wpdb->update( $withdrawals, array( 'status' => EarningsService::WITHDRAWAL_APPROVED ), array( 'id' => (int) ( $manual['withdrawal_id'] ?? 0 ) ) );
$second = $service->request_withdrawal( $vendor, 50, $method );
$check( 'a manual request beside an approved, unpaid one is refused', empty( $second['success'] ) && 'pending_exists' === ( $second['code'] ?? '' ), wp_json_encode( $second ) );
$rows = $run();
$check( 'the run adds no request beside an approved, unpaid one', 1 === count( $rows ), count( $rows ) . ' rows' );
$reset();

// 1c. The balance drops below the threshold after the run listed the vendor: re-checked under the lock.
$raise = static function ( $sql ) use ( &$threshold ) {
	if ( false !== strpos( $sql, 'FOR UPDATE' ) ) {
		$threshold = 1000;
	}
	return $sql;
};
add_filter( 'query', $raise );
$rows = $run();
remove_filter( 'query', $raise );
$threshold = 10;
$check( 'the run does not request a payout that fell below the threshold', array() === $rows, count( $rows ) . ' rows' );
$reset();

// 2. Below the minimum: 120 available, minimum raised to 500.
$min  = 500;
$rows = $run();
$check( 'the run does not request less than the minimum', array() === $rows, count( $rows ) . ' rows' );
$min = 50;
$reset();

// 3. Saved payout details that no longer validate.
update_user_meta( $vendor, 'wpss_payout_details', wpss_encrypt_secret( (string) wp_json_encode( array_fill_keys( array_keys( $details ), 'not-an-email' ) ) ) );
$rows = $run();
$check( 'the run does not request a payout with unusable details', array() === $rows, count( $rows ) . ' rows' );
EarningsService::save_payout_profile( $vendor, $method, $details );
$reset();

// 4. A suspended vendor's money waits for the owner.
update_user_meta( $vendor, 'wpss_account_status', 'suspended' );
$rows = $run();
delete_user_meta( $vendor, 'wpss_account_status' );
$check( 'the run does not request a payout for a suspended vendor', array() === $rows, count( $rows ) . ' rows' );
$reset();

// 5. Control: a healthy vendor gets one auto request for the full balance.
$rows = $run();
$check( 'a healthy vendor gets one auto request', 1 === count( $rows ) && 1 === (int) $rows[0]->is_auto, count( $rows ) . ' rows' );
$check( 'for the balance available at that moment', isset( $rows[0] ) && abs( 120 - (float) $rows[0]->amount ) < 0.01 );
$reset();

remove_filter( 'option_wpss_payouts', $settings );
remove_filter( 'default_option_wpss_payouts', $settings );
$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
