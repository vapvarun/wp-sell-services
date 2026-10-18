<?php
/**
 * wpss_withdrawal_processed hands its listeners the withdrawal as it now stands: the new status and
 * the admin's note. It handed over the row read before the update, so the rejection email left out
 * the reason (admin sweep, 1.9.0).
 *
 * Run: wp eval-file tests/test-withdrawal-processed-carries-note.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f     = wpss_test_fixtures();
$table = $wpdb->prefix . 'wpss_withdrawals';
$wpdb->insert(
	$table,
	array(
		'vendor_id'  => $f->vendor,
		'amount'     => 25,
		'method'     => 'paypal',
		'status'     => 'pending',
		'created_at' => gmdate( 'Y-m-d H:i:s' ),
	)
);
$id = (int) $wpdb->insert_id;

$seen = null;
$spy  = static function ( $withdrawal_id, $status, $withdrawal ) use ( &$seen ) {
	$seen = $withdrawal;
};
add_action( 'wpss_withdrawal_processed', $spy, 99, 3 );
wp_set_current_user( $f->admin );

$result = ( new \WPSellServices\Services\EarningsService() )->process_withdrawal( $id, \WPSellServices\Services\EarningsService::WITHDRAWAL_REJECTED, 'IBAN is missing' );
$check( 'the rejection went through', ! empty( $result['success'] ) );
$check( 'the listener sees the new status', is_object( $seen ) && 'rejected' === $seen->status );
$check( 'the listener sees the admin note', is_object( $seen ) && 'IBAN is missing' === $seen->admin_note );

$check( 'the listener still knows the state it moved from', is_object( $seen ) && 'pending' === ( $seen->previous_status ?? '' ) );

remove_action( 'wpss_withdrawal_processed', $spy, 99 );
$wpdb->delete( $table, array( 'id' => $id ) );

exit( $fails ? 1 : 0 );
