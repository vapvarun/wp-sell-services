<?php
/**
 * An extension quote waiting on an offline payment confirmation reads as that, not as declined.
 *
 * Run: wp eval-file tests/test-extension-awaiting-view.php
 *
 * Renders templates/order/extension-view.php for the buyer and the vendor of a throwaway
 * extension order the buyer has chosen an offline method for, removed on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// A quote on a catalog order, paid offline and not yet confirmed: what
// ExtensionOrderService writes, plus the method the buyer chose at checkout.
$f             = wpss_test_fixtures();
$parent        = ( $f->order )( array( 'service_id' => ( $f->service )( 'draft' ) ) );
$extension     = ( $f->order )(
	array(
		'platform'          => 'extension',
		'platform_order_id' => $parent,
		'status'            => 'pending_payment',
		'payment_status'    => 'pending',
		'payment_method'    => 'offline',
		'paid_at'           => null,
		'subtotal'          => 25,
		'total'             => 25,
		'meta'              => wp_json_encode( array( 'extra_days' => 2, 'reason' => 'QA extra work' ) ),
	)
);
$current_order = ServiceOrder::find( $extension );
$check( 'the staged extension is awaiting offline confirmation', $current_order && wpss_order_awaits_payment_confirmation( $current_order ) );
if ( ! $current_order ) {
	exit( 1 );
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
