<?php
/**
 * wpss_order_timestamp lets an importer or demo installer date orders.
 *
 * Run: wp eval-file tests/test-order-timestamp-filter.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\OrderService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';
$order = $wpdb->get_row( "SELECT id, status FROM {$table} ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
if ( ! $order ) {
	echo "SKIP  needs an existing order\n";
	return;
}
$id     = (int) $order->id;
$before = $wpdb->get_row( $wpdb->prepare( "SELECT status, updated_at, started_at, completed_at FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore

$past = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
$wpdb->update( $table, array( 'status' => 'in_progress', 'completed_at' => null ), array( 'id' => $id ) ); // phpcs:ignore
$check( 'no filter: helper returns now', abs( strtotime( wpss_order_timestamp( 'paid_at' ) . ' UTC' ) - time() ) < 5 );

add_filter( 'wpss_order_timestamp', static fn() => $past );
$check( 'filter: helper returns the hooked date', $past === wpss_order_timestamp( 'created_at' ) );
$service = new OrderService();
$moved = $service->update_status( $id, 'completed' );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT updated_at, completed_at FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
$check( 'filter: completing an order stamps updated_at and completed_at with the hooked date', $moved && $past === $row['updated_at'] && $past === $row['completed_at'], wp_json_encode( array( $moved, $row ) ) );
remove_all_filters( 'wpss_order_timestamp' );

add_filter( 'wpss_order_timestamp', static fn() => 'not a date' );
$check( 'a bad filter value falls back to now', abs( strtotime( wpss_order_timestamp( 'paid_at' ) . ' UTC' ) - time() ) < 5 );
remove_all_filters( 'wpss_order_timestamp' );

// The MySQL zero date is "no date" (legacy exports), not a date to store.
add_filter( 'wpss_order_timestamp', static fn() => '0000-00-00 00:00:00' );
$check( 'the MySQL zero date falls back to now', abs( strtotime( wpss_order_timestamp( 'paid_at' ) . ' UTC' ) - time() ) < 5 );
remove_all_filters( 'wpss_order_timestamp' );

// Restore the row.
$wpdb->update( $table, array( 'status' => $before['status'], 'updated_at' => $before['updated_at'], 'started_at' => $before['started_at'], 'completed_at' => $before['completed_at'] ), array( 'id' => $id ) ); // phpcs:ignore

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
