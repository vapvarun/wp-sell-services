<?php
/**
 * Every owner queue shows the same count in its menu as on its Dashboard card (Basecamp 10550910764).
 *
 * The Dashboard's Action items counted vendors awaiting approval, escalated disputes and withdrawals
 * to approve, but their menus carried no bubble, though Orders, Moderation, Review Moderation and
 * Member reports did. One wpss_count_* helper per queue now feeds both, and one
 * wpss_admin_menu_title() draws every bubble.
 *
 * Run: wp eval-file tests/test-admin-menu-queue-counts.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f      = wpss_test_fixtures();
$repo   = new \WPSellServices\Database\Repositories\VendorProfileRepository();
$menu   = static function (): array {
	global $submenu, $_registered_pages;
	$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- rebuilt below.
	$admin   = new \WPSellServices\Admin\Admin();
	if ( method_exists( $admin, 'init' ) ) {
		$admin->init();
	}
	add_action( 'admin_menu', array( $admin, 'add_admin_menu' ) );
	do_action( 'admin_menu' );
	remove_all_actions( 'admin_menu' );
	$titles = array();
	foreach ( (array) ( $submenu['wp-sell-services'] ?? array() ) as $item ) {
		$titles[ $item[2] ] = $item[0];
	}
	return $titles;
};
$bubble = static fn( string $title ): int => preg_match( '#awaiting-mod">([\d,]+)<#', $title, $m ) ? (int) str_replace( ',', '', $m[1] ) : 0;
$dash   = static function (): string {
	set_current_screen( 'toplevel_page_wp-sell-services' );
	ob_start();
	( new \WPSellServices\Admin\Admin() )->render_dashboard_page();
	return (string) ob_get_clean();
};
$card   = static fn( string $html, string $label ): int => preg_match( '#wpss-stat-card__value">([\d,]+)</span>\s*<span class="wpss-stat-card__label">' . preg_quote( $label, '#' ) . '#', $html, $m ) ? (int) str_replace( ',', '', $m[1] ) : -1;
$withdrawal = 0;
$dispute    = 0;

try {
	wp_set_current_user( 1 );

	// Something waiting in every queue.
	$repo->upsert( $f->vendor, array( 'status' => 'pending' ) );
	$wpdb->insert( $wpdb->prefix . 'wpss_withdrawals', array( 'vendor_id' => $f->vendor, 'amount' => 10, 'method' => 'paypal', 'status' => 'pending', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
	$withdrawal = (int) $wpdb->insert_id;
	// Own escalated dispute, so the disputes queue is never empty by luck of the site's data.
	$wpdb->insert( $wpdb->prefix . 'wpss_disputes', array( 'order_id' => 0, 'initiated_by' => $f->vendor, 'reason' => 'qa', 'description' => 'qa queue-count fixture', 'status' => 'escalated' ) );
	$dispute = (int) $wpdb->insert_id;

	$titles = $menu();
	$html   = $dash();
	$queues = array(
		'wpss-vendors'     => array( 'Vendors awaiting approval', 'wpss_count_vendors_awaiting_approval' ),
		'wpss-disputes'    => array( 'Disputes waiting on you', 'wpss_count_disputes_awaiting_admin' ),
		'wpss-withdrawals' => array( 'Withdrawals to approve', 'wpss_count_withdrawals_to_approve' ),
	);

	foreach ( $queues as $slug => list( $label, $helper ) ) {
		$expected = function_exists( $helper ) ? $helper() : -2;
		$check( "{$slug}: the menu bubble shows the waiting count", $expected > 0 && $bubble( (string) ( $titles[ $slug ] ?? '' ) ) === $expected, ( $titles[ $slug ] ?? 'no menu' ) . " / {$helper}={$expected}" );
		$check( "  and matches the Dashboard card \"{$label}\"", $card( $html, $label ) === $expected, $card( $html, $label ) . ' vs ' . $expected );
	}

	// Nothing waiting: no bubble.
	$repo->upsert( $f->vendor, array( 'status' => 'active' ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $withdrawal ) );
	$withdrawal = 0;
	if ( 0 === wpss_count_vendors_awaiting_approval() ) {
		$check( 'An empty queue has no bubble', false === strpos( (string) ( $menu()['wpss-vendors'] ?? '' ), 'awaiting-mod' ) );
	}

	$check( 'Every bubble is drawn by one helper', 1 === count( array_filter( array_map( static fn( $p ) => false !== strpos( (string) file_get_contents( $p ), 'awaiting-mod' ), array_merge( ...array_map( static fn( $d ) => glob( dirname( __DIR__ ) . "/src/$d/*.php" ) ?: array(), array( 'Admin', 'Admin/Pages', 'functions' ) ) ) ) ) ) );
} finally {
	$repo->upsert( $f->vendor, array( 'status' => 'active' ) );
	if ( $withdrawal ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $withdrawal ) );
	}
	if ( $dispute ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'id' => $dispute ) );
	}
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
