<?php
/**
 * Order-number search is a starts-with match that the unique index can serve.
 *
 * The admin Orders list, the Disputes list and the dashboard search box all
 * matched order_number LIKE '%term%', which reads every order row. They now
 * share wpss_order_number_search_sql(): the number as typed, with or without
 * the WPSS- prefix and a leading '#'.
 *
 * Run: wp eval-file tests/test-order-number-search-uses-index.php
 *
 * Inserts one order row and deletes it.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Tables\OrdersListTable;
use WPSellServices\Database\Repositories\OrderRepository;

require_once __DIR__ . '/exit-on-fail.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$admin = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $admin );
set_current_screen( 'toplevel_page_wpss-orders' );

$table  = $wpdb->prefix . 'wpss_orders';
$code   = 'QZ' . strtoupper( wp_generate_password( 6, false ) );
$number = 'WPSS-' . $code;
$wpdb->insert(
	$table,
	array(
		'order_number' => $number,
		'customer_id'  => $admin,
		'vendor_id'    => $admin,
		'service_id'   => 0,
		'status'       => 'pending_payment',
		'total'        => 10,
		'created_at'   => current_time( 'mysql', true ),
	)
);
$order_id = (int) $wpdb->insert_id;
$check( 'staged an order row', $order_id > 0, $wpdb->last_error );

// Capture the SQL a surface runs, so the test reads the flow and not the helper.
$captured = array();
$spy      = static function ( $sql ) use ( &$captured, $table, $wpdb ) {
	$sql = $wpdb->remove_placeholder_escape( $sql );
	if ( false !== strpos( $sql, $table ) && false !== stripos( $sql, 'order_number LIKE' ) ) {
		$captured[] = $sql;
	}
	return $sql;
};
add_filter( 'query', $spy );

$admin_list = static function ( string $s ): array {
	$_GET  = array(
		'page' => 'wpss-orders',
		's'    => $s,
	);
	$list = new OrdersListTable();
	$list->prepare_items();
	return wp_list_pluck( $list->items, 'id' );
};

foreach ( array( $number, $code, '#' . $number, strtolower( $code ) ) as $typed ) {
	$check( "admin Orders list finds the order typed as '$typed'", in_array( $order_id, array_map( 'intval', $admin_list( $typed ) ), true ) );
}
$check( 'admin Orders list still finds the order by its id', in_array( $order_id, array_map( 'intval', $admin_list( (string) $order_id ) ), true ) );

$repo = new OrderRepository();
$mine = wp_list_pluck( $repo->get_by_customer( $admin, array( 'search' => $code ) ), 'id' );
$check( 'dashboard search finds the order without the prefix', in_array( $order_id, array_map( 'intval', $mine ), true ) );

remove_filter( 'query', $spy );
$check( 'the surfaces ran an order-number match', count( $captured ) > 0 );

foreach ( array_unique( $captured ) as $sql ) {
	$check( 'no contains match on order_number', ! preg_match( "/order_number LIKE '%/", $sql ), substr( $sql, 0, 200 ) );
}

// The rule itself can use the unique index (a range read, not a full table read).
list( $sql, $params ) = wpss_order_number_search_sql( $code );
$plan                 = $wpdb->get_row( $wpdb->prepare( "EXPLAIN SELECT id FROM {$table} WHERE {$sql}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'EXPLAIN reads the order_number index', $plan && 'ALL' !== $plan->type && false !== strpos( (string) $plan->key, 'order_number' ), $plan ? "type={$plan->type} key={$plan->key}" : 'no plan' );

$check( 'an empty term adds no clause', array( '', array() ) === wpss_order_number_search_sql( ' # ' ) );

$wpdb->delete( $table, array( 'id' => $order_id ) );
$_GET = array();

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
