<?php
/**
 * mark_as_paid() on a legacy row whose payment_status is NULL records the payment and reports
 * success: the race guard compares NULL-safely instead of matching 0 rows.
 *
 * Run: wp eval-file tests/test-mark-paid-null-payment-status.php
 *
 * Throwaway buyer (swept by the harness); the one tip sub-order it inserts is deleted by id.
 * A sub-order fires no status hooks, and the wpss_order_paid listeners are detached for this
 * request, so no ledger row or email results.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = (int) wp_insert_user( array( 'user_login' => 'qa_nullpay' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-nullpay' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->insert( $orders, array( 'order_number' => 'QA-NULLPAY-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => 0, 'service_id' => 0, 'platform' => 'tip', 'status' => 'pending_payment', 'subtotal' => 10, 'total' => 10, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ) ) );
$id = (int) $wpdb->insert_id;
register_shutdown_function( static fn() => $wpdb->delete( $orders, array( 'id' => $id ) ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$orders} SET payment_status = NULL WHERE id = %d", $id ) );
$check( 'fixture: payment_status is NULL', null === $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$orders} WHERE id = %d", $id ) ) );

remove_all_actions( 'wpss_order_paid' );
$ok = ( new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider() )->mark_as_paid( $id, 'qa_null_txn', 'offline' );
$check( 'mark_as_paid() reports the payment confirmed', true === $ok );
$check( 'the row is paid', 'paid' === (string) $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$orders} WHERE id = %d", $id ) ) );
// phpcs:enable

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
