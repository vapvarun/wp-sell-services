<?php
/**
 * A milestone phase is earned when the buyer approves it, not when it is paid.
 *
 * Run: wp eval-file tests/test-milestone-credit-on-approval.php
 *
 * Basecamp 10336732073 (owner decision 2026-09-25): a paid phase is held as
 * clearing and credited on approval, the same as an order on completion. It
 * used to be credited - and withdrawable - the moment the buyer paid.
 *
 * Throwaway buyer, vendor and phase rows; every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;
use WPSellServices\Services\MilestoneService;
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
add_filter( 'pre_wp_mail', '__return_true' );

$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_ms_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_ms_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_ms_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_ms_vendor@example.test', 'role' => 'wpss_vendor' ) );
$made   = array();

$row = static function ( array $data ) use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		$data + array(
			'order_number'   => 'DEVF1-MS-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'currency'       => wpss_get_currency(),
			'subtotal'       => $data['total'] ?? 100,
			'payment_method' => 'offline',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$credits = static fn( int $id ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND amount > 0", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$status  = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$phase   = static fn( int $parent ) => $row(
	array(
		'platform'          => MilestoneService::ORDER_TYPE,
		'platform_order_id' => $parent,
		'status'            => 'pending_payment',
		'payment_status'    => 'paid',
		'total'             => 100,
		'paid_at'           => current_time( 'mysql' ),
		'meta'              => wp_json_encode( array( 'title' => 'dev_f1 phase' ) ),
	)
);

try {
	$parent  = $row( array( 'platform' => 'standalone', 'status' => 'in_progress', 'payment_status' => 'paid', 'total' => 0 ) );
	$service = new MilestoneService();

	// 1. Payment starts the phase and credits nothing.
	$p1 = $phase( $parent );
	$service->start_paid_phase( $p1 );
	$earn = (float) $wpdb->get_var( $wpdb->prepare( "SELECT vendor_earnings FROM {$orders} WHERE id = %d", $p1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( sprintf( '1. paid phase is in_progress with its earnings recorded (%s, %.2f)', $status( $p1 ), $earn ), 'in_progress' === $status( $p1 ) && $earn > 0 );
	$check( '1. no wallet credit on payment', 0 === $credits( $p1 ) );
	$summary = ( new EarningsService() )->get_summary( $vendor );
	$check( sprintf( '1. it shows as clearing, not available (clearing %.2f, available %.2f)', $summary['pending_clearance'], $summary['available_balance'] ), $summary['pending_clearance'] >= $earn && $summary['available_balance'] < $earn );
	$service->start_paid_phase( $p1 );
	$check( '1. a webhook retry changes nothing', 0 === $credits( $p1 ) && 'in_progress' === $status( $p1 ) );

	// 2. Approval credits it, once.
	$wpdb->update( $orders, array( 'status' => 'pending_approval' ), array( 'id' => $p1 ) );
	$approved = $service->approve( $p1, $buyer );
	$check( sprintf( '2. approval completes the phase (%s)', $status( $p1 ) ), ! empty( $approved['success'] ) && 'completed' === $status( $p1 ) );
	$check( '2. and credits the vendor once', 1 === $credits( $p1 ) );
	$service->credit_phase( $p1 );
	$check( '2. crediting again writes nothing', 1 === $credits( $p1 ) );

	// 3. Completed another way (dispute ruling, admin): credited once, by the same path.
	$p2 = $phase( $parent );
	$service->start_paid_phase( $p2 );
	wp_set_current_user( 1 );
	( new OrderService() )->update_status( $p2, 'completed', 'dev_f1 ruling' );
	wp_set_current_user( 0 );
	$types = $wpdb->get_col( $wpdb->prepare( "SELECT type FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND amount > 0", $p2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( sprintf( '3. completed by status change: one credit (%s)', implode( ',', $types ) ), array( MilestoneService::TYPE_MILESTONE ) === $types );

	// 4. A phase credited on payment before 1.8.0 is not credited again on approval.
	$p3 = $phase( $parent );
	$service->start_paid_phase( $p3 );
	wpss_insert_ledger_row(
		array(
			'user_id'        => $vendor,
			'type'           => MilestoneService::TYPE_MILESTONE,
			'amount'         => 90,
			'balance_after'  => 0,
			'currency'       => wpss_get_currency(),
			'description'    => 'dev_f1 legacy credit',
			'reference_type' => 'order',
			'reference_id'   => $p3,
			'status'         => 'completed',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$wpdb->update( $orders, array( 'status' => 'pending_approval' ), array( 'id' => $p3 ) );
	$service->approve( $p3, $buyer );
	$check( '4. legacy phase: still one credit after approval', 1 === $credits( $p3 ) );

	// 5. Refunded before approval: nothing was credited, so nothing is taken back.
	$p4 = $phase( $parent );
	$service->start_paid_phase( $p4 );
	wp_set_current_user( 1 );
	( new OrderService() )->update_status( $p4, 'refunded', 'dev_f1 refund before approval' );
	wp_set_current_user( 0 );
	$net = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$ledger} WHERE reference_id = %d AND reference_type LIKE 'order%%'", $p4 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( sprintf( '5. refund before approval leaves the vendor at 0 on it (%.2f)', $net ), 0.0 === $net );
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_wp_mail', '__return_true' );
	foreach ( $made as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$ledger} WHERE reference_id = %d AND reference_type LIKE 'order%%'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE JSON_EXTRACT(data, '$.order_id') = %d OR JSON_EXTRACT(data, '$.milestone_id') = %d", $id, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
