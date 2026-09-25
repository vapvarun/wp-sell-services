<?php
/**
 * An order on hold can be disputed by the buyer; a revision runs on its own deadline.
 *
 * Run: wp eval-file tests/test-hold-and-revision-clock.php
 *
 * Basecamp 10336731826 (owner decision 2026-09-25: a revision gets its own
 * deadline). From on_hold every buyer action was refused and no job touched
 * the order, and a revision_requested order was never marked late.
 *
 * Throwaway buyer, vendor and orders; every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DeliveryService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
add_filter( 'pre_wp_mail', '__return_true' );

$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_hr_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_hr_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_hr_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_hr_vendor@example.test' ) );
$made   = array();

$ago   = static fn( int $days ) => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );
$order = static function ( string $status, string $deadline ) use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'       => 'DEVF1-HR-' . wp_generate_password( 6, false ),
			'customer_id'        => $buyer,
			'vendor_id'          => $vendor,
			'service_id'         => 0,
			'platform'           => 'standalone',
			'status'             => $status,
			'payment_status'     => 'paid',
			'subtotal'           => 60,
			'total'              => 60,
			'currency'           => wpss_get_currency(),
			'revisions_included' => 2,
			'delivery_deadline'  => $deadline,
			'meta'               => wp_json_encode( array( 'package_snapshot' => array( 'name' => 'Basic', 'price' => 60, 'delivery_days' => 4 ) ) ),
			'paid_at'            => current_time( 'mysql' ),
			'created_at'         => current_time( 'mysql' ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

try {
	// On hold: the buyer can dispute.
	$held = $order( 'on_hold', $ago( 10 ) );
	wp_set_current_user( $buyer );
	$check( 'on_hold is disputable', ( new DisputeService() )->can_open_dispute( wpss_get_order( $held ) ) );
	$request = new WP_REST_Request( 'POST', '/wpss/v1/orders/' . $held . '/dispute' );
	$request->set_param( 'reason', 'dev_f1: vendor paused and went quiet' );
	$response = rest_do_request( $request );
	$check( sprintf( 'buyer opens a dispute from on_hold (HTTP %d, %s)', $response->get_status(), $status( $held ) ), 'disputed' === $status( $held ) );

	// A revision gets its own deadline: 4 days from now (the package's delivery time).
	$revised = $order( 'pending_approval', $ago( 6 ) );
	( new DeliveryService() )->request_revision( $revised, 'dev_f1: another pass' );
	$deadline = (string) $wpdb->get_var( $wpdb->prepare( "SELECT delivery_deadline FROM {$orders} WHERE id = %d", $revised ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$days     = ( strtotime( $deadline ) - strtotime( current_time( 'mysql' ) ) ) / DAY_IN_SECONDS;
	$check( sprintf( 'revision deadline moves to ~4 days out (%.2f)', $days ), $days > 3.9 && $days < 4.1 );

	// A revision past its own deadline is marked late; one inside it is not.
	$overdue = $order( 'revision_requested', $ago( 2 ) );
	wp_set_current_user( 0 );
	( new OrderWorkflowManager() )->check_late_orders();
	$check( sprintf( 'revision past its deadline -> late (%s)', $status( $overdue ) ), 'late' === $status( $overdue ) );
	$check( sprintf( 'fresh revision stays revision_requested (%s)', $status( $revised ) ), 'revision_requested' === $status( $revised ) );
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_wp_mail', '__return_true' );
	foreach ( $made as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_dispute_messages WHERE dispute_id IN (SELECT id FROM {$wpdb->prefix}wpss_disputes WHERE order_id = %d)", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE JSON_EXTRACT(data, '$.order_id') = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
