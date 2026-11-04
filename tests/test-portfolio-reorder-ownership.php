<?php
/**
 * A vendor cannot reorder another vendor's portfolio items: the request is refused and nothing moves.
 *
 * Run: wp eval-file tests/test-portfolio-reorder-ownership.php
 *
 * Seeds two vendors with two items each (prefix QA-RFT), removes them after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\PortfolioService;

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$mk    = static fn( string $n ) => wp_insert_user( array( 'user_login' => 'qa_rft_ro_' . $n . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-rft-ro-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$a     = $mk( 'a' );
$b     = $mk( 'b' );
$table = $wpdb->prefix . 'wpss_portfolio_items';
$ids   = array();
foreach ( array( $a, $a, $b, $b ) as $i => $owner ) {
	$wpdb->insert( $table, array( 'vendor_id' => $owner, 'title' => 'QA-RFT item ' . $i, 'sort_order' => $i + 1 ) );
	$ids[] = (int) $wpdb->insert_id;
}
$order_of = static fn( int $id ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT sort_order FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB

$svc = new PortfolioService();
$check( 'own items reorder and report success', true === $svc->reorder( $a, array( $ids[1], $ids[0] ) ) && 1 === $order_of( $ids[1] ) && 2 === $order_of( $ids[0] ) );
$check( 'a request naming another vendor\'s item is refused', false === $svc->reorder( $a, array( $ids[2], $ids[0] ) ) );
$check( 'and nothing moved, including the vendor\'s own item', 3 === $order_of( $ids[2] ) && 2 === $order_of( $ids[0] ) );
$check( 'an empty order is not an error', true === $svc->reorder( $a, array() ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE vendor_id IN (%d,%d)", $a, $b ) ); // phpcs:ignore WordPress.DB
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $a );
wp_delete_user( $b );
