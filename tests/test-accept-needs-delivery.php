<?php
/**
 * A buyer cannot accept an order that has nothing delivered.
 *
 * Run: wp eval-file tests/test-accept-needs-delivery.php
 *
 * Basecamp 10337217098 (owner decision 2026-09-25): a pending_approval order
 * with no delivery offered "Accept & Complete", so the buyer approved - and
 * released the money - without seeing any work. DeliveryService::accept() is
 * the one approval path (order view, dashboard AJAX, REST) and now refuses.
 *
 * Throwaway buyer, vendor and orders; every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DeliveryService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
add_filter( 'pre_wp_mail', '__return_true' );

$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_acc_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_acc_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_acc_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_acc_vendor@example.test' ) );
$made   = array();

$order  = static function () use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'DEVF1-ACC-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'status'         => 'pending_approval',
			'payment_status' => 'paid',
			'subtotal'       => 40,
			'total'          => 40,
			'currency'       => wpss_get_currency(),
			'paid_at'        => current_time( 'mysql' ),
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

try {
	$bare = $order();
	$check( 'no delivery: accept() refuses', false === ( new DeliveryService() )->accept( $bare ) );
	$check( sprintf( 'no delivery: order stays pending_approval (%s)', $status( $bare ) ), 'pending_approval' === $status( $bare ) );

	wp_set_current_user( $buyer );
	$response = rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1/orders/' . $bare . '/complete' ) );
	$check( sprintf( 'no delivery: REST complete is refused (HTTP %d, %s)', $response->get_status(), $status( $bare ) ), $response->get_status() >= 400 && 'pending_approval' === $status( $bare ) );
	wp_set_current_user( 0 );

	$delivered = $order();
	$wpdb->insert( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $delivered, 'vendor_id' => $vendor, 'message' => 'dev_f1 delivery', 'status' => 'pending', 'version' => 1, 'created_at' => current_time( 'mysql' ) ) );
	$check( 'with a delivery: accept() completes', true === ( new DeliveryService() )->accept( $delivered ) );
	$check( sprintf( 'with a delivery: order is completed (%s)', $status( $delivered ) ), 'completed' === $status( $delivered ) );
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_wp_mail', '__return_true' );
	foreach ( $made as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_wallet_transactions WHERE reference_id = %d AND reference_type LIKE 'order%%'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE JSON_EXTRACT(data, '$.order_id') = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $vendor ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
