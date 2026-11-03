<?php
/**
 * POST /orders/{id}/mark-paid: admin only, settles through the same path as the admin button, safe to repeat.
 *
 * Run: wp eval-file tests/test-rest-mark-order-paid.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table  = $wpdb->prefix . 'wpss_orders';
// Throwaway members and service, so the gate is proven on a fresh site, not on this site's seeded ids.
$fx      = wpss_test_fixtures();
$buyer   = (int) $fx->buyer;
$vendor  = (int) $fx->vendor;
$service = (int) ( $fx->service )();
$ids    = array();
$make   = static function ( string $platform, string $status, int $parent = 0 ) use ( $wpdb, $table, $buyer, $vendor, $service, &$ids ): int {
	$wpdb->insert(
		$table,
		array(
			'order_number'      => 'TEST-' . strtoupper( wp_generate_password( 6, false ) ),
			'customer_id'       => $buyer,
			'vendor_id'         => $vendor,
			'service_id'        => $service,
			'platform'          => $platform,
			'platform_order_id' => $parent,
			'status'            => $status,
			'payment_status'    => 'pending',
			'payment_method'    => '',
			'subtotal'          => 50,
			'total'             => 50,
			'currency'          => 'USD',
			'created_at'        => current_time( 'mysql', true ),
		)
	);
	$ids[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$call = static function ( int $order, array $params = array() ): WP_REST_Response {
	$req = new WP_REST_Request( 'POST', "/wpss/v1/orders/{$order}/mark-paid" );
	$req->set_body_params( $params );
	return rest_do_request( $req );
};
$row = static fn( int $id ): object => $wpdb->get_row( $wpdb->prepare( "SELECT status, payment_status, payment_method, transaction_id FROM {$table} WHERE id = %d", $id ) );

add_filter( 'pre_wp_mail', '__return_true' );
$admin = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];

$whole = $make( 'standalone', 'pending_payment' );

wp_set_current_user( 0 );
$check( 'logged out is refused with 401', 401 === $call( $whole )->get_status() );
wp_set_current_user( $buyer );
$check( 'the buyer is refused with 403', 403 === $call( $whole )->get_status() );
wp_set_current_user( $vendor );
$check( 'the vendor is refused with 403', 403 === $call( $whole )->get_status() );
$check( 'a refused call leaves the order unpaid', 'pending' === $row( $whole )->payment_status );

wp_set_current_user( $admin );
$res = $call( $whole, array( 'transaction_id' => 'FLW-123', 'payment_method' => 'flutterwave' ) );
$check( 'an administrator settles it (200)', 200 === $res->get_status() );
$check( 'the order is paid and moved on from pending payment', 'paid' === $row( $whole )->payment_status && 'pending_payment' !== $row( $whole )->status );
$check( 'the provider reference and method are kept', 'FLW-123' === $row( $whole )->transaction_id && 'flutterwave' === $row( $whole )->payment_method );

$again = $call( $whole, array( 'transaction_id' => 'FLW-OTHER' ) );
$check( 'a retry answers 200 already_paid', 200 === $again->get_status() && true === ( $again->get_data()['already_paid'] ?? null ) );
$check( 'a retry does not overwrite the reference', 'FLW-123' === $row( $whole )->transaction_id );

$check( 'an unknown order is 404', 404 === $call( 999999999 )->get_status() );

$running = $make( 'standalone', 'in_progress' );
$wpdb->update( $table, array( 'payment_status' => 'pending' ), array( 'id' => $running ) );
$check( 'an order that is not awaiting payment is refused with 409', 409 === $call( $running )->get_status() );

$phase = $make( 'milestone', 'completed', $running );
$check( 'an unpaid phase an admin already moved on can still be settled', 200 === $call( $phase )->get_status() && 'paid' === $row( $phase )->payment_status );

$default = $make( 'standalone', 'pending_payment' );
$call( $default );
$check( 'method defaults to offline', 'offline' === $row( $default )->payment_method );

remove_filter( 'pre_wp_mail', '__return_true' );
$wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
