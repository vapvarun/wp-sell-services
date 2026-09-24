<?php
/**
 * An order only ever holds a real status, and only the flows that own a field
 * may write it.
 *
 * Run: wp eval-file tests/test-order-write-integrity.php
 *
 * PATCH /orders/{id} let an admin save any string as the status ("banana" was
 * stored and the order lost every action), reported success when the change
 * was refused, silently ignored due_date, and let the BUYER overwrite
 * vendor_notes (Basecamp 10336370527, 10336370631).
 *
 * Every row this script creates is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\OrderService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = get_user_by( 'login', 'wpss_buyer_ella' );
$vendor = get_user_by( 'login', 'wpss_vendor_maya' );
$admin  = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;

if ( ! $buyer || ! $vendor || ! $admin ) {
	echo "SKIP  needs the QA personas (bin/qa-fixtures.sh) and an administrator\n";
	return;
}

$notif_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wpss_notifications" );

$wpdb->insert(
	$orders,
	array(
		'order_number'   => 'WPSS-WRITE-INTEGRITY-' . wp_generate_password( 6, false ),
		'customer_id'    => $buyer->ID,
		'vendor_id'      => $vendor->ID,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 50.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$field = static fn( string $col ) => $wpdb->get_var( $wpdb->prepare( "SELECT {$col} FROM {$orders} WHERE id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$patch = static function ( int $user_id, array $body ) use ( $order_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( 'PATCH', '/wpss/v1/orders/' . $order_id );
	$request->set_body_params( $body );
	return rest_ensure_response( rest_do_request( $request ) );
};

try {
	// --- The service refuses a status that does not exist, admin included ---
	wp_set_current_user( $admin->ID );
	$service = new OrderService();
	$check( 'update_status() refuses "banana" even for an admin', false === $service->update_status( $order_id, 'banana' ) );
	$check( '  and the order keeps its status', 'in_progress' === $field( 'status' ) );

	// --- REST: a bogus status is a 400, not a 200 ----------------------------
	$res = $patch( $admin->ID, array( 'status' => 'banana' ) );
	$check( 'PATCH status=banana as admin answers 400', 400 === $res->get_status() );
	$check( '  and nothing is stored', 'in_progress' === $field( 'status' ) );

	// --- REST: refund and dispute statuses belong to their own flows ---------
	$res = $patch( $admin->ID, array( 'status' => 'refunded' ) );
	$check( 'PATCH status=refunded answers 400 (refunds go through the refund action)', 400 === $res->get_status() );
	$check( '  and the order is not refunded', 'in_progress' === $field( 'status' ) );

	// --- REST: a refused transition is reported, not hidden ------------------
	$wpdb->update( $orders, array( 'status' => 'completed' ), array( 'id' => $order_id ) );
	wp_set_current_user( 0 );
	$res = $patch( $vendor->ID, array( 'status' => 'pending_payment' ) );
	$check( 'a status change the caller may not make is not reported as success', 200 !== $res->get_status() );
	$wpdb->update( $orders, array( 'status' => 'in_progress' ), array( 'id' => $order_id ) );

	// --- REST: fields no one may PATCH --------------------------------------
	$res = $patch( $buyer->ID, array( 'vendor_notes' => 'BUYER WROTE THIS' ) );
	$check( 'the buyer cannot PATCH vendor_notes (400)', 400 === $res->get_status() );
	$check( '  and vendor_notes is untouched', 'BUYER WROTE THIS' !== (string) $field( 'vendor_notes' ) );

	$deadline = (string) $field( 'delivery_deadline' );
	$res      = $patch( $admin->ID, array( 'due_date' => '2030-01-01 00:00:00' ) );
	$check( 'PATCH due_date answers 400 instead of a silent 200', 400 === $res->get_status() );
	$check( '  and the deadline is untouched', $deadline === (string) $field( 'delivery_deadline' ) );

	// --- Control: a real change still works ---------------------------------
	$res = $patch( $admin->ID, array( 'status' => 'on_hold' ) );
	$check( 'an admin can still put an order on hold (200)', 200 === $res->get_status() && 'on_hold' === $field( 'status' ) );
} finally {
	wp_set_current_user( 0 );
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $order_id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $order_id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $order_id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE id > %d", $notif_floor ) );
	$wpdb->delete( $orders, array( 'id' => $order_id ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
