<?php
/**
 * An admin's manual order records the package it was priced from, and prices
 * add-ons and tax the way checkout does.
 *
 * Run: wp eval-file tests/test-manual-order-package-contract.php
 *
 * ManualOrderPage gated the package on `if ( $package_id )`, so the FIRST
 * package (index 0) was never recorded - the order read "Package: Custom" -
 * and with the subtotal left blank it fell back to the starting price: a $180
 * first package was billed $30 (Basecamp 10330901576). It also priced add-ons
 * itself and applied no tax.
 *
 * Drives the real AJAX handler. A throwaway service and every order this
 * script creates are removed at the end. Tax is pinned off for exact numbers.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ManualOrderPage;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$admin  = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
$buyer  = get_user_by( 'login', 'wpss_buyer_ella' );
$vendor = get_user_by( 'login', 'wpss_vendor_maya' );

if ( ! $admin || ! $buyer || ! $vendor ) {
	echo "SKIP  needs an administrator and the QA personas\n";
	return;
}

if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}

$no_tax = static fn() => array( 'enable_tax' => false );
add_filter( 'pre_option_wpss_tax', $no_tax );
// wp_send_json() ends in wp_die(); turn that into an exception so the script continues.
$die = static fn() => static function () {
	throw new RuntimeException( 'wp_die' );
};
add_filter( 'wp_die_ajax_handler', $die );

$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'Manual order package contract',
		'post_author' => $vendor->ID,
	)
);
update_post_meta(
	$service_id,
	'_wpss_packages',
	array(
		array( 'name' => 'Business', 'price' => 180, 'delivery_days' => 7, 'revisions' => 3 ),
		array( 'name' => 'Growth', 'price' => 80, 'delivery_days' => 5, 'revisions' => 2 ),
		array( 'name' => 'Starter', 'price' => 30, 'delivery_days' => 3, 'revisions' => 1 ),
	)
);
update_post_meta( $service_id, '_wpss_starting_price', 30 );
update_post_meta( $service_id, '_wpss_addons', array( array( 'title' => 'Rush', 'price' => 10, 'price_type' => 'percentage' ) ) );

$notif_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wpss_notifications" );
$created     = array();
$create      = static function ( array $post ) use ( $service_id, $buyer, &$created ): ?object {
	global $wpdb;
	$_POST = $post + array(
		'service_id'     => $service_id,
		'customer_id'    => $buyer->ID,
		'status'         => 'pending_requirements',
		'payment_status' => 'paid',
		'nonce'          => wp_create_nonce( 'wpss_create_manual_order' ),
	);
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new ManualOrderPage() )->handle_create_order();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$json = json_decode( (string) ob_get_clean(), true );
	$id   = (int) ( $json['data']['order_id'] ?? 0 );
	if ( $id ) {
		$created[] = $id;
		return $wpdb->get_row( $wpdb->prepare( "SELECT package_id, subtotal, addons_total, total, revisions_included FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $id ) );
	}
	echo '      handler said: ' . wp_json_encode( $json ) . "\n";
	return null;
};

wp_set_current_user( $admin->ID );

try {
	$row = $create( array( 'package_id' => '0' ) );
	$check( 'the FIRST package is recorded (package_id 0, not NULL)', $row && null !== $row->package_id && 0 === (int) $row->package_id );
	$check( sprintf( '  and billed at its own price, 180, with no subtotal typed (got %s)', $row ? $row->total : 'none' ), $row && abs( (float) $row->total - 180 ) < 0.01 );
	$check( '  with its own revisions (3)', $row && 3 === (int) $row->revisions_included );

	$row = $create( array( 'package_id' => '0', 'addons' => array( 0 => array( 'selected' => 1 ) ) ) );
	$check( 'a 10% add-on on the first package: 180 + 18 = 198', $row && abs( (float) $row->total - 198 ) < 0.01 && abs( (float) $row->addons_total - 18 ) < 0.01 );

	$row = $create( array( 'package_id' => '0', 'subtotal' => '100', 'addons' => array( 0 => array( 'selected' => 1 ) ) ) );
	$check( 'an admin-typed price is the base: 100 + 10% = 110', $row && abs( (float) $row->total - 110 ) < 0.01 );

	$row = $create( array( 'package_id' => '0', 'total_override' => '42', 'addons' => array( 0 => array( 'selected' => 1 ) ) ) );
	$check( 'an override total is what is charged: 42', $row && abs( (float) $row->total - 42 ) < 0.01 );

	$row = $create( array( 'package_id' => '' ) );
	$check( 'no package chosen: nothing recorded, starting price 30', $row && null === $row->package_id && abs( (float) $row->total - 30 ) < 0.01 );
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_option_wpss_tax', $no_tax );
	remove_filter( 'wp_die_ajax_handler', $die );
	foreach ( $created as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
		// Only the notifications about this script's orders: other sessions share the table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE id > %d AND JSON_EXTRACT( data, '$.order_id' ) = %d", $notif_floor, $id ) );
	}
	wp_delete_post( $service_id, true );
	$_POST    = array();
	$_REQUEST = array();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
