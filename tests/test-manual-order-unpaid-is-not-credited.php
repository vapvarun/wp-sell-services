<?php
/**
 * An admin-created order past Pending Payment means the money arrived: Completed (or In Progress)
 * with payment Pending/Failed is refused with a clear notice, so no `order_earning` is credited
 * for money nobody received. Pending Payment unpaid and Completed + Paid still save.
 *
 * Run: wp eval-file tests/test-manual-order-unpaid-is-not-credited.php
 *
 * Creates at most a few orders and deletes them (and any ledger row they made) by id.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ManualOrderPage;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$vendor   = wpss_test_fixture_user( 'vendor' );
$customer = wpss_test_fixture_user( 'buyer' );
$service  = $vendor ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' AND post_author = %d ORDER BY ID ASC LIMIT 1", $vendor ) ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( ! $service || ! $customer ) {
	echo "SKIP  no seeded vendor service or buyer on this site\n";
	return;
}

wp_set_current_user( 1 );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'done' ); } );

$made = array();
register_shutdown_function(
	static function () use ( $wpdb, &$made ) {
		foreach ( array_filter( $made ) as $id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_type' => 'order', 'reference_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
			// phpcs:enable
		}
	}
);
$create = static function ( string $status, string $payment ) use ( $service, $customer, &$made ): array {
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
	$json   = (array) json_decode( (string) ob_get_clean(), true );
	$id     = (int) ( $json['data']['order_id'] ?? 0 );
	$made[] = $id;
	return array( $id, (string) ( $json['data']['message'] ?? '' ) );
};
$earned = static fn( int $id ) => (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}wpss_wallet_transactions WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

foreach ( array( 'pending', 'failed' ) as $payment ) {
	list( $id, $msg ) = $create( 'completed', $payment );
	$check( "Completed + {$payment}: refused, no order", 0 === $id, "order #{$id}" );
	$check( "Completed + {$payment}: no earning credited", 0 === $id || 0 === $earned( $id ) );
	$check( "Completed + {$payment}: the notice says what to do", false !== stripos( $msg, 'Pending Payment' ), $msg );
}
list( $id ) = $create( 'in_progress', 'pending' );
$check( 'In Progress + pending: refused', 0 === $id, "order #{$id}" );

list( $id ) = $create( 'pending_payment', 'pending' );
$check( 'Pending Payment + pending still saves', $id > 0 );
list( $id ) = $create( 'completed', 'paid' );
$check( 'Completed + Paid still saves', $id > 0 );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
