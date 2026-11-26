<?php
/**
 * Buyer and vendor see when a delivered order completes on its own (Basecamp 10550841989).
 *
 * The sweep completes an order awaiting approval auto_complete_days after its latest delivery,
 * and with clearance at 0 that frees the vendor's money, yet neither the order page nor the
 * Delivery Ready email said so. One helper now gives that date to the page (both parties), the
 * email and the REST order; with the setting at 0 nothing is shown.
 *
 * Run: wp eval-file tests/test-auto-complete-date-shown.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f         = wpss_test_fixtures();
$order_id  = ( $f->order )(
	array(
		'status'  => 'pending_approval',
		'paid_at' => gmdate( 'Y-m-d H:i:s' ),
	)
);
$delivered = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
$wpdb->insert(
	$wpdb->prefix . 'wpss_deliveries',
	array(
		'order_id'    => $order_id,
		'vendor_id'   => $f->vendor,
		'message'     => 'QA-RFT auto-complete date, deleted after the run.',
		'attachments' => '[]',
		'version'     => 1,
		'status'      => 'pending',
		'created_at'  => $delivered,
	)
);
$delivery_id = (int) $wpdb->insert_id;
$orders_was  = get_option( 'wpss_orders' );
$days        = static function ( int $n ) use ( $orders_was ): void {
	update_option( 'wpss_orders', array_merge( (array) $orders_was, array( 'auto_complete_days' => $n ) ) );
};
$at          = static fn() => function_exists( 'wpss_get_auto_complete_at' ) ? wpss_get_auto_complete_at( wpss_get_order( $order_id ) ) : 'missing';
$page        = static function ( string $viewer ) use ( $order_id ): string {
	$order = wpss_get_order( $order_id );
	ob_start();
	wpss_get_template_part(
		'order/deliveries',
		'',
		array(
			'wpss_order'      => $order,
			'wpss_deliveries' => ( new \WPSellServices\Services\DeliveryService() )->get_order_deliveries( $order_id ),
			'wpss_viewer'     => $viewer,
		)
	);
	return (string) ob_get_clean();
};
$email = static function () use ( $order_id, $f ): string {
	$order     = wpss_get_order( $order_id );
	$recipient = get_userdata( $f->buyer );
	$vendor    = get_userdata( $f->vendor );
	ob_start();
	include dirname( __DIR__ ) . '/templates/emails/delivery-ready.php';
	return (string) ob_get_clean();
};

try {
	$days( 3 );
	$expect = gmdate( 'Y-m-d H:i:s', strtotime( $delivered . ' UTC' ) + 3 * DAY_IN_SECONDS );
	$check( 'The deadline is the latest delivery plus the setting', $expect === $at(), (string) $at() );

	$label = wpss_datetime_label( $expect, '', (int) $f->buyer );
	$check( 'Buyer\'s order page says when it completes on its own', false !== strpos( $page( 'buyer' ), 'wpss-order-auto-complete' ) && false !== strpos( $page( 'buyer' ), esc_html( $label ) ) );
	$check( '  and so does the vendor\'s', false !== strpos( $page( 'vendor' ), 'If the buyer doesn' ) );
	$check( 'The Delivery Ready email carries the date', false !== strpos( $email(), esc_html( $label ) ) );

	wp_set_current_user( $f->buyer );
	$rest = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/orders/' . $order_id ) )->get_data();
	wp_set_current_user( 0 );
	$check( 'The REST order gives the app the same date', ( $rest['auto_complete_at'] ?? null ) === wpss_rest_date( $expect ), wp_json_encode( $rest['auto_complete_at'] ?? null ) );

	$days( 0 );
	$check( 'Auto-complete off: no date, no line on the page or in the email', null === $at() && false === strpos( $page( 'buyer' ), 'wpss-order-auto-complete' ) && false === strpos( $email(), 'completes automatically' ) );
	$days( 3 );
	$wpdb->update( $f->orders_t, array( 'status' => 'in_progress' ), array( 'id' => $order_id ) );
	$check( 'An order no longer awaiting approval has no date', null === $at() );
} finally {
	update_option( 'wpss_orders', $orders_was );
	$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'id' => $delivery_id ) );
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
