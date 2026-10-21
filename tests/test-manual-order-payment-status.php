<?php
/**
 * A manual order set to Pending Payment is saved unpaid, not Paid.
 *
 * Run: wp eval-file tests/test-manual-order-payment-status.php
 *
 * The form defaults Payment Status to Paid, so choosing Pending Payment for the order
 * status saved an order that was both. Creates two orders and deletes them by id.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ManualOrderPage;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$service = 0;
$vendor  = 0;
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 50 ) ) as $p ) {
	if ( wpss_is_vendor( (int) $p->post_author ) ) {
		$service = $p->ID;
		$vendor  = (int) $p->post_author;
		break;
	}
}
$customer = 0;
foreach ( get_users( array( 'number' => 50, 'exclude' => array( $vendor ) ) ) as $u ) {
	if ( ! user_can( $u, 'manage_options' ) ) {
		$customer = $u->ID;
		break;
	}
}
wp_set_current_user( 1 );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'done' ); } );

$made   = array();
$create = static function ( string $status, string $payment ) use ( $service, $customer, &$made ) {
	$_POST    = array(
		'nonce'          => wp_create_nonce( 'wpss_create_manual_order' ),
		'service_id'     => $service,
		'customer_id'    => $customer,
		'subtotal'       => '40',
		'status'         => $status,
		'payment_status' => $payment,
	);
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new ManualOrderPage() )->handle_create_order();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	$id = (int) ( json_decode( ob_get_clean(), true )['data']['order_id'] ?? 0 );
	$made[] = $id;
	return $id ? (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT payment_status FROM {$GLOBALS['wpdb']->prefix}wpss_orders WHERE id = %d", $id ) ) : '';
};

$check( 'Pending Payment with the default Paid is saved unpaid', 'pending' === $create( 'pending_payment', 'paid' ) );
$check( 'In Progress with Paid stays paid', 'paid' === $create( 'in_progress', 'paid' ) );

foreach ( array_filter( $made ) as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
}
exit( $fails ? 1 : 0 );
