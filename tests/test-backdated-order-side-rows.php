<?php
/**
 * Backdated orders: relative filter values are stored as real datetimes, and the wallet row and due date follow the order.
 *
 * Run: wp eval-file tests/test-backdated-order-side-rows.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Services\OrderService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// A relative string is a valid time but not a MySQL datetime: it must be normalised, never stored raw.
add_filter( 'wpss_order_timestamp', static fn() => 'yesterday' );
$stamp = wpss_order_timestamp( 'created_at' );
remove_all_filters( 'wpss_order_timestamp' );
$check( "'yesterday' becomes a UTC MySQL datetime", (bool) preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $stamp ) && strtotime( $stamp . ' UTC' ) < time() - HOUR_IN_SECONDS, $stamp );

add_filter( 'wpss_order_timestamp', static fn() => '2026-01-05T10:00:00+02:00' );
$check( 'an ISO date with offset is converted to UTC', '2026-01-05 08:00:00' === wpss_order_timestamp( 'created_at' ) );
remove_all_filters( 'wpss_order_timestamp' );

global $wpdb;
$f    = wpss_test_fixtures();
$past = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
$sid  = (int) ( $f->service )( 'publish' );
$oid  = (int) ( $f->order )( array( 'service_id' => $sid, 'status' => 'in_progress', 'started_at' => $past ) );

// Due date counts from started_at, not from now.
( new OrderService() )->set_deadline_on_requirements( $oid, array(), array() );
$deadline = (string) $wpdb->get_var( $wpdb->prepare( "SELECT delivery_deadline FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $oid ) ); // phpcs:ignore
$check( 'due date is counted from started_at', '' !== $deadline && strtotime( $deadline . ' UTC' ) < time() + DAY_IN_SECONDS && strtotime( $deadline . ' UTC' ) > strtotime( $past . ' UTC' ), $deadline );

// Wallet row takes the order's stamp.
add_filter( 'wpss_order_timestamp', static fn() => $past );
( new OrderService() )->update_status( $oid, 'completed' );
remove_all_filters( 'wpss_order_timestamp' );
$row = (string) $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$wpdb->prefix}wpss_wallet_transactions WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $oid ) ); // phpcs:ignore
$check( 'order_earning ledger row carries the backdated stamp', $past === $row, $row );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
