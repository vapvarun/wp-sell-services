<?php
/**
 * The opener can withdraw a dispute only before it is escalated, and withdrawing one on a
 * completed order does not send the buyer a second "Order completed" notice.
 *
 * Run: wp eval-file tests/test-dispute-cancel-after-escalation.php
 *
 * Seeds fake users 999999/999998 and QA orders; removes them after.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$orders   = $wpdb->prefix . 'wpss_orders';
$disputes = $wpdb->prefix . 'wpss_disputes';
$notes    = $wpdb->prefix . 'wpss_notifications';
$buyer    = 999999;
$vendor   = 999998;

$seed           = static function ( string $status ) use ( $wpdb, $orders, $buyer, $vendor ): int {
	$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-ESC-' . wp_rand(), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'total' => 100.000, 'currency' => 'USD', 'status' => $status, 'payment_status' => 'paid', 'completed_at' => 'completed' === $status ? gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) : null, 'created_at' => current_time( 'mysql' ) ) );
	return (int) $wpdb->insert_id;
};
$dispute_status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$disputes} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
$completed_note = static fn( int $order_id ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notes} WHERE user_id = %d AND title = 'Order completed' AND data LIKE %s", $buyer, '%"order_id":' . $order_id . '%' ) ); // phpcs:ignore WordPress.DB

$service  = new DisputeService();
$workflow = new DisputeWorkflowManager();

$saved = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved, array( 'allow_disputes' => 1, 'dispute_window_days' => 14 ) ) );
wp_set_current_user( 0 );

// Escalated: the opener is refused, the dispute stays escalated.
$o1 = $seed( 'in_progress' );
$d1 = (int) $service->open( $o1, $buyer, 'other', 'contract' );
$workflow->escalate( $d1, 'needs the site team', $buyer );
$check( 'dispute is escalated', 'escalated' === $dispute_status( $d1 ) );
$refused = $workflow->cancel( $d1, $buyer, 'changed my mind' );
$check( 'opener cannot cancel an escalated dispute', empty( $refused['success'] ) );
$check( '  it stays escalated', 'escalated' === $dispute_status( $d1 ) );
wp_set_current_user( 1 );
$check( 'an admin can still close it', ! empty( $workflow->cancel( $d1, 1, 'admin close' )['success'] ) && 'closed' === $dispute_status( $d1 ) );
wp_set_current_user( 0 );

// Before escalation the opener can still withdraw (unchanged behaviour).
$o2 = $seed( 'in_progress' );
$d2 = (int) $service->open( $o2, $buyer, 'other', 'contract' );
$check( 'opener can still cancel an open dispute', ! empty( $workflow->cancel( $d2, $buyer, 'withdrawn' )['success'] ) );

// Completed order: restoring it must not announce completion again.
$o3     = $seed( 'completed' );
$d3     = (int) $service->open( $o3, $buyer, 'other', 'contract' );
$before = $completed_note( $o3 );
$workflow->cancel( $d3, $buyer, 'withdrawn' );
$check( 'withdrawing a dispute on a completed order sends no second Order completed notice', $before === $completed_note( $o3 ) );

update_option( 'wpss_orders', $saved );
foreach ( array( $o1, $o2, $o3 ) as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$disputes} WHERE order_id = %d", $id ) ) ) );
	$wpdb->delete( $disputes, array( 'order_id' => $id ) );
	$wpdb->delete( $orders, array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
}
foreach ( array( $buyer, $vendor ) as $u ) {
	$wpdb->delete( $notes, array( 'user_id' => $u ) );
}
