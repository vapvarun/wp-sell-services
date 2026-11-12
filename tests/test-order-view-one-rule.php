<?php
/**
 * One rule for who may view an order (Basecamp 10504406392).
 *
 * Run: wp eval-file tests/test-order-view-one-rule.php
 *
 * A shop manager (wpss_manage_orders) can change an order's status in wp-admin
 * but got a 403 on GET /orders/{id} and the frontend order page, because the
 * view gates were hand-written copies that only knew administrators.
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

$order = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wpss_orders ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( ! $order ) {
	echo "SKIP  no order on this site\n";
	return;
}

$make = static fn( string $role ): int => (int) wp_insert_user( array( 'user_login' => 'qa_ov_' . $role . wp_generate_password( 5, false, false ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
$manager  = $make( 'shop_manager' );
$stranger = $make( 'subscriber' );
$admin    = $make( 'administrator' );

$check( 'buyer may view', wpss_user_can_view_order( $order, (int) $order->customer_id ) );
$check( 'vendor may view', wpss_user_can_view_order( (int) $order->id, (int) $order->vendor_id ) );
$check( 'administrator may view', wpss_user_can_view_order( $order, $admin ) );
$check( 'shop manager (wpss_manage_orders) may view', user_can( $manager, 'wpss_manage_orders' ) && wpss_user_can_view_order( $order, $manager ) );
$check( 'a stranger may not', ! wpss_user_can_view_order( $order, $stranger ) );
$check( 'logged out may not', ! wpss_user_can_view_order( $order, 0 ) );

$rest = static function ( int $user ) use ( $order ) {
	wp_set_current_user( $user );

	return rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/orders/' . (int) $order->id ) )->get_status();
};
$check( 'GET /orders/{id}: shop manager 200', 200 === $rest( $manager ), (string) $rest( $manager ) );
$check( 'GET /orders/{id}: stranger 403', 403 === $rest( $stranger ), (string) $rest( $stranger ) );

wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $manager, $stranger, $admin ) as $id ) {
	wp_delete_user( $id );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
