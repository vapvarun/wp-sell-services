<?php
/**
 * Repeating the decision an owner already made is a no-op: no second mail,
 * hook or audit row. Covers withdrawal processing, vendor status, member
 * standing and dispute assignment (and a resolved dispute is not assignable).
 *
 * Run: wp eval-file tests/test-same-state-repeat-is-noop.php
 *
 * Writes one QA withdrawal row and nothing else that persists; audit rows the
 * test wrote are deleted on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeWorkflowManager;
use WPSellServices\Services\EarningsService;
use WPSellServices\Services\VendorService;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$audit       = $wpdb->prefix . 'wpss_audit_log';
$withdrawals = $wpdb->prefix . 'wpss_withdrawals';
$disputes    = $wpdb->prefix . 'wpss_disputes';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$audit_before  = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$audit}" );
$withdrawal_id = 0;
$dispute_meta  = array();

$restore = static function () use ( $wpdb, $audit, $audit_before, $withdrawals, &$withdrawal_id, $disputes, &$dispute_meta ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE id > %d", $audit_before ) );
	if ( $withdrawal_id ) {
		$wpdb->delete( $withdrawals, array( 'id' => $withdrawal_id ) );
	}
	foreach ( $dispute_meta as $id => $meta ) {
		$wpdb->update( $disputes, array( 'meta' => $meta ), array( 'id' => $id ) );
	}
	// phpcs:enable
};

$rows_since = static function ( string $where ) use ( $wpdb, $audit, &$audit_before ): int {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE id > %d AND {$where}", $audit_before ) );
};

wp_set_current_user( 1 );

$qa_vendor = wpss_test_fixture_user( 'vendor' );
$qa_member = wpss_test_fixture_user( 'buyer' );
if ( ! $qa_vendor || ! $qa_member ) {
	echo "SKIP  no seeded vendor or buyer on this site\n";
	return;
}

// 1. Withdrawal: approved -> approved sends nothing the second time.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->insert(
	$withdrawals,
	array(
		'vendor_id'  => $qa_vendor,
		'amount'     => 1.00,
		'method'     => 'manual',
		'details'    => '{}',
		'status'     => 'approved',
		'created_at' => current_time( 'mysql', true ),
	)
);
$withdrawal_id = (int) $wpdb->insert_id;
$fired         = 0;
$count         = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_withdrawal_processed', $count );
$res = ( new EarningsService() )->process_withdrawal( $withdrawal_id, 'approved' );
remove_action( 'wpss_withdrawal_processed', $count );
$check( 'repeat approve reports success without a change', ! empty( $res['success'] ) && 'unchanged' === ( $res['code'] ?? '' ), wp_json_encode( $res ) );
$check( 'repeat approve fires no processed hook', 0 === $fired, (string) $fired );
$check( 'repeat approve writes no audit row', 0 === $rows_since( "object_type = 'withdrawal'" ) );

// 2. Vendor status: same status again.
$fired = 0;
$count = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_vendor_status_updated', $count );
$vendors = new VendorService();
$current = $vendors->get_vendor_status( $qa_vendor );
$ok      = $vendors->set_status( $qa_vendor, (string) $current );
remove_action( 'wpss_vendor_status_updated', $count );
$check( 'same vendor status returns true', true === $ok );
$check( 'same vendor status fires no hook', 0 === $fired, (string) $fired );
$check( 'same vendor status writes no audit row', 0 === $rows_since( "object_type = 'vendor'" ) );

// 3. Member standing: re-posting the current standing.
$fired = 0;
$count = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_account_status_changed', $count );
$standing = (string) ( get_user_meta( $qa_member, 'wpss_account_status', true ) ?: 'active' );
$method   = new ReflectionMethod( '\WPSellServices\Admin\Pages\ReportsPage', 'set_account_status' );
$method->invoke( null, $qa_member, $standing );
remove_action( 'wpss_account_status_changed', $count );
$check( 'same member standing fires no hook', 0 === $fired, (string) $fired );
$check( 'same member standing writes no audit row', 0 === $rows_since( "event_type = 'member.account_status'" ) );

// 4. Dispute assignment.
$manager = new DisputeWorkflowManager();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$open = $wpdb->get_row( "SELECT id, meta FROM {$disputes} WHERE status = 'escalated' ORDER BY id DESC LIMIT 1" );
if ( $open ) {
	$dispute_meta[ $open->id ] = $open->meta;
	$meta                      = json_decode( (string) $open->meta, true ) ?: array();
	$meta['assigned_to']       = 1;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $disputes, array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $open->id ) );
	$res = $manager->assign_to_admin( (int) $open->id, 1 );
	$check( 'assigning the same admin again succeeds', ! empty( $res['success'] ), wp_json_encode( $res ) );
	$check( 'assigning the same admin again writes no audit row', 0 === $rows_since( "event_type = 'dispute.assign'" ) );
} else {
	$check( 'an assignable dispute exists for the test', false, 'no escalated dispute on this site' );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$done = $wpdb->get_row( "SELECT id, meta FROM {$disputes} WHERE status = 'resolved' ORDER BY id DESC LIMIT 1" );
if ( $done ) {
	$dispute_meta[ $done->id ] = $done->meta;
	$res                       = $manager->assign_to_admin( (int) $done->id, 1 );
	$check( 'a resolved dispute cannot be assigned', empty( $res['success'] ), wp_json_encode( $res ) );
}
