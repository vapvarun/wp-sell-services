<?php
/**
 * Cancelling a paid offline order queues a manual refund and tells the buyer.
 *
 * Run: wp eval-file tests/test-offline-cancel-refund.php
 *
 * Basecamp 10336731713 (owner decision 2026-09-25): a paid offline order that
 * is cancelled - by the admin, by the buyer before requirements, by the vendor
 * accepting a cancellation request, or by the 48-hour request timeout - must
 * land in the admin's "Refunds are waiting to be sent manually" list for the
 * full amount, and the buyer's cancellation email must say the payment is
 * being refunded. Marking the refund sent closes the payment.
 *
 * Throwaway buyer, vendor and orders; outgoing mail is captured in-process and
 * never sent. Every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';

$mail    = array();
$capture = static function ( $short, $atts ) use ( &$mail ) {
	$mail[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $capture, 10, 2 );

$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_ocr_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_ocr_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_ocr_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_ocr_vendor@example.test' ) );
$made   = array();

$order = static function ( string $status, array $extra = array() ) use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		$extra + array(
			'order_number'   => 'DEVF1-OCR-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'status'         => $status,
			'payment_status' => 'paid',
			'payment_method' => 'offline',
			'transaction_id' => 'BANK-REF-1',
			'subtotal'       => 80,
			'total'          => 80,
			'currency'       => wpss_get_currency(),
			'paid_at'        => current_time( 'mysql' ),
			'started_at'     => current_time( 'mysql' ),
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$buyer_mail = static function () use ( &$mail ): string {
	foreach ( array_reverse( $mail ) as $m ) {
		if ( 'dev_f1_ocr_buyer@example.test' === ( is_array( $m['to'] ) ? $m['to'][0] : $m['to'] ) && false !== stripos( (string) $m['subject'], 'cancelled' ) ) {
			return wp_strip_all_tags( (string) $m['message'] );
		}
	}
	return '';
};

$assert_queued = static function ( string $path, int $id ) use ( $check, $buyer_mail, $wpdb, $orders, &$mail ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, payment_status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( sprintf( '%s: cancelled (%s)', $path, $row->status ), 'cancelled' === $row->status );
	$check( sprintf( '%s: $80 in the manual-refund list', $path ), 80.0 === ( wpss_get_pending_manual_refunds()[ $id ] ?? null ) );
	$body = $buyer_mail();
	$check( sprintf( '%s: buyer email says the payment will be refunded', $path ), false !== strpos( $body, 'will be refunded' ) );
	$mail = array();
};

try {
	// 1. Admin cancels an order in progress.
	$id = $order( 'in_progress' );
	wp_set_current_user( 1 );
	( new OrderService() )->update_status( $id, 'cancelled', 'dev_f1 admin cancel' );
	$assert_queued( '1 admin', $id );

	// 2. Buyer cancels before submitting requirements.
	$id = $order( 'pending_requirements' );
	wp_set_current_user( $buyer );
	( new OrderService() )->cancel( $id, $buyer, 'dev_f1 changed my mind' );
	$assert_queued( '2 buyer', $id );

	// 3. Buyer requests cancellation, vendor accepts over REST.
	$id = $order( 'in_progress' );
	( new OrderService() )->request_cancellation( $id, $buyer, 'other', 'dev_f1 request' );
	wp_set_current_user( $vendor );
	rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1/orders/' . $id . '/accept-cancellation' ) );
	$assert_queued( '3 vendor accepts', $id );

	// 4. Buyer requests cancellation, 48 hours pass.
	$id = $order( 'in_progress' );
	wp_set_current_user( $buyer );
	( new OrderService() )->request_cancellation( $id, $buyer, 'other', 'dev_f1 request' );
	$meta = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM {$orders} WHERE id = %d", $id ) ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$meta['cancellation_request']['requested_at'] = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 49 * HOUR_IN_SECONDS );
	$wpdb->update( $orders, array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $id ) );
	wp_set_current_user( 0 );
	( new OrderWorkflowManager() )->process_cancellation_timeouts();
	$assert_queued( '4 timeout', $id );

	// The buyer's order page says the refund is on its way.
	$state = wpss_get_order_refund_state( wpss_get_order( $id ) );
	$check( sprintf( 'order page state: pending $80 (%s %s)', $state['state'], $state['amount'] ), 'pending' === $state['state'] && 80.0 === $state['amount'] );

	// Marking it sent closes the payment.
	wp_set_current_user( 1 );
	$_POST['order_id']               = $id;
	$_POST['wpss_refund_sent_nonce'] = wp_create_nonce( 'wpss_mark_refund_sent_' . $id );
	$stop = static function () {
		throw new RuntimeException( 'stopped' );
	};
	add_filter( 'wp_redirect', $stop );
	add_filter( 'wp_die_handler', static fn() => $stop );
	try {
		( new \WPSellServices\Admin\Admin() )->handle_mark_refund_sent();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$paid = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( sprintf( 'mark sent: out of the list, payment refunded (%s)', $paid ), ! isset( wpss_get_pending_manual_refunds()[ $id ] ) && 'refunded' === $paid );
} finally {
	wp_set_current_user( 0 );
	unset( $_POST['order_id'], $_POST['wpss_refund_sent_nonce'] );
	remove_filter( 'pre_wp_mail', $capture, 10 );
	foreach ( $made as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE JSON_EXTRACT(data, '$.order_id') = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
