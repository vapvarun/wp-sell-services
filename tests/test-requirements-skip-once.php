<?php
/**
 * Skipping the requirements step works once; a second call is refused and writes nothing.
 *
 * Run: wp eval-file tests/test-requirements-skip-once.php
 *
 * Seeds one buyer and one QA-RFT order; removes them after.
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

$buyer  = wp_insert_user( array( 'user_login' => 'qa_rft_skip' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-skip' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$orders = $wpdb->prefix . 'wpss_orders';
$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-SKIP-' . wp_rand(), 'customer_id' => $buyer, 'vendor_id' => 999998, 'service_id' => 0, 'platform' => 'standalone', 'total' => 10, 'currency' => 'USD', 'status' => 'pending_requirements', 'payment_status' => 'paid', 'created_at' => current_time( 'mysql' ) ) );
$order  = (int) $wpdb->insert_id;
$audit  = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id = %d", $order ) ); // phpcs:ignore WordPress.DB
$status = static fn() => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $order ) ); // phpcs:ignore WordPress.DB
$skip   = static function () use ( $order, $buyer ) {
	wp_set_current_user( $buyer );
	return rest_do_request( new WP_REST_Request( 'POST', "/wpss/v1/orders/{$order}/requirements/skip" ) );
};

$first = $skip();
$check( 'first skip succeeds', ! $first->is_error() && 'in_progress' === $status() );
$rows = $audit();
$again = $skip();
$check( 'second skip is refused', $again->is_error() && 'wpss_requirements_not_pending' === $again->as_error()->get_error_code() );
$check( '  nothing was written the second time', $rows === $audit() && 'in_progress' === $status() );

wp_set_current_user( 0 );
$wpdb->delete( $orders, array( 'id' => $order ) );
$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $order ) );
$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $buyer ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer );
