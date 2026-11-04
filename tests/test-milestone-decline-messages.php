<?php
/**
 * Declining a phase says what happened: a cancelled phase is "already declined", a paid one is "already paid".
 *
 * Run: wp eval-file tests/test-milestone-decline-messages.php
 *
 * Seeds one buyer, one parent order and two phases (prefix QA-RFT); removes them after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$buyer  = wp_insert_user( array( 'user_login' => 'qa_rft_dec' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-dec' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$orders = $wpdb->prefix . 'wpss_orders';
$made   = array();
$row    = static function ( array $over ) use ( $wpdb, $orders, $buyer, &$made ): int {
	$wpdb->insert( $orders, array_merge( array( 'order_number' => 'QA-RFT-DEC-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => 999998, 'service_id' => 0, 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 10, 'total' => 10, 'currency' => 'USD', 'created_at' => current_time( 'mysql' ) ), $over ) );
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$parent    = $row( array( 'platform' => 'standalone' ) );
$cancelled = $row( array( 'platform' => 'milestone', 'platform_order_id' => $parent, 'status' => 'cancelled', 'payment_status' => 'pending' ) );
$paid      = $row( array( 'platform' => 'milestone', 'platform_order_id' => $parent ) );

$decline = static function ( int $id ) use ( $buyer ): string {
	wp_set_current_user( $buyer );
	$res = rest_do_request( new WP_REST_Request( 'POST', "/wpss/v1/milestones/{$id}/decline" ) );
	return $res->is_error() ? $res->as_error()->get_error_message() : 'ok';
};

$msg = $decline( $cancelled );
$check( 'a cancelled phase reads already declined', false !== stripos( $msg, 'already been declined' ) );
$msg = $decline( $paid );
$check( 'a paid phase still reads already paid', false !== stripos( $msg, 'already been paid' ) );

wp_set_current_user( 0 );
foreach ( $made as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer );
