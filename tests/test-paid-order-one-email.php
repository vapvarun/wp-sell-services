<?php
/**
 * Paying for an order sends the branded pair only (Basecamp 10475484149).
 *
 * Run: wp eval-file tests/test-paid-order-one-email.php
 *
 * mark_as_paid() moves pending_payment -> pending_requirements on every rail. That
 * status had no case in NotificationService::notify_order_status(), so its default
 * branch wrote an "Order status updated" row per party and emailed it beside the
 * branded "Order Confirmed" / "New Service Order" mails. The late sweep had the
 * same shape: its own order_late rows plus a generic row from the default branch.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;
use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_po_vendor_' . $suffix, 'user_email' => 'wpss_po_vendor_' . $suffix . '@example.test' ) );
$buyer  = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_po_buyer_' . $suffix, 'user_email' => 'wpss_po_buyer_' . $suffix . '@example.test' ) );
$mails  = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = array( 'to' => (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ), 'subject' => (string) $atts['subject'] );

		return true;
	},
	10,
	2
);
$to = static function ( string $addr ) use ( &$mails ): array {
	return array_values( array_filter( $mails, static fn( $m ) => str_contains( $m['to'], $addr ) ) );
};

// A service WITH requirement questions, so payment lands on pending_requirements.
$service = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA paid order ' . $suffix, 'post_author' => $vendor->ID ) );
wpss_save_service_requirements( $service, array( array( 'question' => 'Brand name?', 'type' => 'text', 'required' => true ) ) );

$wpdb->insert(
	$wpdb->prefix . 'wpss_orders',
	array(
		'order_number'   => 'PO-' . $suffix,
		'customer_id'    => $buyer->ID,
		'vendor_id'      => $vendor->ID,
		'service_id'     => $service,
		'package_id'     => 0,
		'subtotal'       => 50,
		'total'          => 50,
		'currency'       => wpss_get_currency(),
		'status'         => ServiceOrder::STATUS_PENDING_PAYMENT,
		'payment_status' => 'pending',
		'platform'       => 'standalone',
		'created_at'     => current_time( 'mysql', true ),
		'updated_at'     => current_time( 'mysql', true ),
	)
);
$order_id = (int) $wpdb->insert_id;
$rows     = static fn( int $user, string $type = '' ): array => $wpdb->get_col(
	$wpdb->prepare( "SELECT type FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND data LIKE %s" . ( $type ? ' AND type = %s' : ' AND %s = %s' ), $type ? array( $user, '%"order_id":' . $order_id . '%', $type ) : array( $user, '%"order_id":' . $order_id . '%', 'x', 'x' ) )
);

( new StandaloneOrderProvider() )->mark_as_paid( $order_id, 'txn_po_' . $suffix, 'test' );

$check( 'the order waits for requirements once paid', ServiceOrder::STATUS_PENDING_REQUIREMENTS === (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $order_id ) ) );

$all_subjects = implode( ' | ', array_column( $mails, 'subject' ) );
$check( 'no "Order status updated" mail goes out', false === stripos( $all_subjects, 'Order status updated' ), $all_subjects );
$check( 'the buyer gets exactly one mail', 1 === count( $to( $buyer->user_email ) ), wp_json_encode( $to( $buyer->user_email ) ) );
$check( 'the vendor gets exactly one mail', 1 === count( $to( $vendor->user_email ) ), wp_json_encode( $to( $vendor->user_email ) ) );
$check( 'the vendor in-app row is order_created', array( 'order_created' ) === $rows( $vendor->ID ), wp_json_encode( $rows( $vendor->ID ) ) );
$check( 'the buyer in-app row is order_confirmation', array( 'order_confirmation' ) === $rows( $buyer->ID ), wp_json_encode( $rows( $buyer->ID ) ) );

// The late sweep writes its own order_late rows; the status hook adds nothing on top.
do_action( 'wpss_order_status_changed', $order_id, ServiceOrder::STATUS_LATE, ServiceOrder::STATUS_IN_PROGRESS );
$check( 'going late writes no generic order_status row', ! $rows( $buyer->ID, 'order_status' ) && ! $rows( $vendor->ID, 'order_status' ) );
// One writer for the overdue rows (10504265935): a manual move to late is no longer silent, and not doubled.
$check( 'going late gives each party exactly one order_late row', 1 === count( $rows( $buyer->ID, 'order_late' ) ) && 1 === count( $rows( $vendor->ID, 'order_late' ) ), wp_json_encode( array( $rows( $buyer->ID ), $rows( $vendor->ID ) ) ) );

// A buyer who skipped the requirements has submitted nothing to name.
update_post_meta( $order_id, '_wpss_requirements_skipped', true );
do_action( 'wpss_order_status_changed', $order_id, ServiceOrder::STATUS_IN_PROGRESS, ServiceOrder::STATUS_PENDING_REQUIREMENTS );
$started = (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND type = 'order_started' ORDER BY id DESC LIMIT 1", $vendor->ID ) );
$check( 'a skipped brief does not read "has submitted the requirements"', '' !== $started && false === stripos( $started, 'submitted the requirements' ), $started );
delete_post_meta( $order_id, '_wpss_requirements_skipped' );

$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $order_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE user_id IN (%d, %d)", $buyer->ID, $vendor->ID ) );
wp_delete_post( $service, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor->ID );
wp_delete_user( $buyer->ID );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
