<?php
/**
 * An extension quote waiting on an offline payment confirmation reads as that, not as declined.
 *
 * Run: wp eval-file tests/test-extension-awaiting-view.php
 *
 * Renders templates/order/extension-view.php for the buyer of an extension order that
 * wpss_order_awaits_payment_confirmation() reports as waiting. Skips when the site has none.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$current_order = null;
foreach ( $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}wpss_orders WHERE platform = 'extension' AND status = 'pending_payment' ORDER BY id DESC LIMIT 50" ) as $id ) {
	$o = ServiceOrder::find( (int) $id );
	if ( $o && wpss_order_awaits_payment_confirmation( $o ) ) {
		$current_order = $o;
		break;
	}
}
if ( ! $current_order ) {
	echo "SKIP  no extension order is awaiting offline confirmation on this site\n";
	return;
}

$user_id = (int) $current_order->customer_id;
wp_set_current_user( $user_id );
$fired = 0;
add_action( 'wpss_order_view_details', static function () use ( &$fired ) { ++$fired; } );

ob_start();
include WPSS_PLUGIN_DIR . 'templates/order/extension-view.php';
$html = ob_get_clean();

$check( 'buyer heading is not "Quote declined"', ! str_contains( $html, 'Quote declined' ) );
$check( 'buyer heading says payment submitted', str_contains( $html, 'Payment submitted' ) );
$check( 'bank instructions and receipt hook fires once', 1 === $fired );
$check( 'pay another way stays reachable', str_contains( $html, 'Pay by another method' ) );

wp_set_current_user( (int) $current_order->vendor_id );
$user_id = (int) $current_order->vendor_id;
ob_start();
include WPSS_PLUGIN_DIR . 'templates/order/extension-view.php';
$vendor_html = ob_get_clean();
$check( 'vendor heading is not "Quote declined"', ! str_contains( $vendor_html, 'Quote declined' ) );
$check( 'vendor heading says awaiting confirmation', str_contains( $vendor_html, 'awaiting your confirmation' ) );

exit( $fails ? 1 : 0 );
