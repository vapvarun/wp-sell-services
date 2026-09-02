<?php
/**
 * Marking an order paid twice leaves the first payment untouched.
 *
 * Run: wp eval-file tests/test-mark-as-paid-idempotent.php
 *
 * Guards Basecamp 10264283100: gateways retry webhooks, and a second
 * mark_as_paid() on a paid order rewrote paid_at / transaction_id, re-fired
 * the status hooks and resent the "new order" notifications. Money path -
 * the check stays.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';

// A throwaway unpaid order to pay twice.
$wpdb->insert(
	$table,
	array(
		'order_number'   => 'WPSS-PAID-TWICE-CONTRACT',
		'customer_id'    => 1,
		'vendor_id'      => 1,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'pending_payment',
		'payment_status' => 'pending',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

if ( ! $order_id ) {
	echo "could not seed an order; skipping\n";
	return;
}

$paid_fired = 0;
add_action(
	'wpss_order_paid',
	static function ( $id ) use ( $order_id, &$paid_fired ) {
		if ( (int) $id === $order_id ) {
			++$paid_fired;
		}
	}
);

$row = static function () use ( $wpdb, $table, $order_id ) {
	return $wpdb->get_row( $wpdb->prepare( "SELECT status, payment_status, paid_at, transaction_id FROM {$table} WHERE id = %d", $order_id ) );
};

$provider = new StandaloneOrderProvider();

$check( 'first mark_as_paid succeeds', $provider->mark_as_paid( $order_id, 'txn_first', 'stripe' ) );
$first = $row();
$check( '  and the order is paid', 'paid' === $first->payment_status && 'txn_first' === $first->transaction_id );

// paid_at is second-granular; a second call in the same second would hide a rewrite.
$wpdb->update( $table, array( 'paid_at' => '2020-01-01 00:00:00' ), array( 'id' => $order_id ) );
$first = $row();

$check( 'second mark_as_paid still reports success', $provider->mark_as_paid( $order_id, 'txn_retry', 'stripe' ) );
$second = $row();
$check( '  status unchanged', $first->status === $second->status );
$check( '  paid_at unchanged', $first->paid_at === $second->paid_at );
$check( '  transaction_id unchanged', 'txn_first' === $second->transaction_id );
$check( '  wpss_order_paid fired once', 1 === $paid_fired );

$wpdb->delete( $table, array( 'id' => $order_id ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
