<?php
/**
 * The admin dashboard's order tiles equal the Orders list they link to.
 *
 * The dashboard counts service orders (tips, extensions and milestone phases
 * excluded); the Orders list counted every row, so "Total Orders 77" opened on
 * "All (106)". The list now has a Service orders only view, its tab counts follow
 * the type filter, and an unbound filter is no longer dropped from the query.
 *
 * Run: wp eval-file tests/test-dashboard-counts-match-orders-list.php
 *
 * Reads the site's own orders; writes nothing.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Tables\OrdersListTable;
use WPSellServices\Database\Repositories\OrderRepository;

require_once __DIR__ . '/exit-on-fail.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
set_current_screen( 'toplevel_page_wpss-orders' );

$list_total = static function ( array $query ): int {
	$_GET = $query + array( 'page' => 'wpss-orders' );
	$table = new OrdersListTable();
	$table->prepare_items();
	return (int) $table->get_pagination_arg( 'total_items' );
};

$agg = ( new OrderRepository() )->get_aggregates();

$check( 'Total Orders tile = Service orders only list', (int) $agg->total === $list_total( array( 'suborder_type' => 'main' ) ), $agg->total . ' vs ' . $list_total( array( 'suborder_type' => 'main' ) ) );
$check( 'In Progress tile = its list', (int) $agg->in_progress === $list_total( array( 'suborder_type' => 'main', 'status' => 'in_progress' ) ) );
$check( 'Completed tile = its list', (int) $agg->completed === $list_total( array( 'suborder_type' => 'main', 'status' => 'completed' ) ) );

global $wpdb;
$all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders" ); // phpcs:ignore WordPress.DB
$check( 'the unfiltered list still shows every row', $all === $list_total( array() ) );

$_GET = array();
echo $fails ? "FAILED {$fails}\n" : "OK\n";
