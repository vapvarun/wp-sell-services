<?php
/**
 * The "waiting for your payment" notice depends on the order, not on the Offline gateway being
 * enabled: a buyer who paid offline still sees it (once) after the owner switches the gateway off.
 * The order, phase, extension and tip views all print it through `wpss_order_view_details`.
 *
 * Run: wp eval-file tests/test-offline-waiting-notice-without-gateway.php
 *
 * Throwaway buyer (swept by the harness); the one order it inserts is deleted by id. The live
 * gateway's in-memory setting is flipped for this request only; the stored option is not touched.
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

$gateway = wpss()->get_payment_gateways()['offline'] ?? null;
if ( ! $gateway ) {
	echo "SKIP  the Offline gateway is not registered\n";
	return;
}

$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = (int) wp_insert_user( array( 'user_login' => 'qa_offnotice' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-offnotice' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $orders, array( 'order_number' => 'QA-OFFNOTICE-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => 1, 'service_id' => 0, 'platform' => 'extension', 'status' => 'pending_payment', 'payment_status' => 'pending', 'payment_method' => 'offline', 'subtotal' => 10, 'total' => 10, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ) ) );
$id = (int) $wpdb->insert_id;
register_shutdown_function( static fn() => $wpdb->delete( $orders, array( 'id' => $id ) ) );
// phpcs:enable

$prop  = new ReflectionProperty( $gateway, 'settings' );
$saved = $prop->getValue( $gateway );
$view  = static function () use ( $id ): string {
	ob_start();
	do_action( 'wpss_order_view_details', wpss_get_order( $id ) );
	return (string) ob_get_clean();
};

wp_set_current_user( $buyer );
$check( 'the order is awaiting confirmation', wpss_order_awaits_payment_confirmation( wpss_get_order( $id ) ) );

$prop->setValue( $gateway, array_merge( $saved, array( 'enabled' => '1' ) ) );
$html = $view();
$check( 'gateway on: the waiting notice shows once', 1 === substr_count( $html, 'wpss-offline-awaiting' ), (string) substr_count( $html, 'wpss-offline-awaiting' ) );

$prop->setValue( $gateway, array_merge( $saved, array( 'enabled' => '' ) ) );
$html = $view();
$check( 'gateway switched off after the buyer paid: the notice still shows, once', 1 === substr_count( $html, 'wpss-offline-awaiting' ), (string) substr_count( $html, 'wpss-offline-awaiting' ) );

$prop->setValue( $gateway, $saved );
wp_set_current_user( 0 );
$check( 'not the buyer: no notice', 0 === substr_count( $view(), 'wpss-offline-awaiting' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
