<?php
/**
 * Admin page shell contract (2.0.0 desktop unification, batch 2).
 *
 * One look and one place for the owner's notices across wp-admin:
 *   - Pro's licence reminder shows on the WPSS Dashboard only.
 *   - Refund lists live on the Dashboard; other screens print no summary line.
 *   - Admin tiles render through the shared .wpss-stat-card primitive.
 *   - The Orders list with no type chosen shows service orders, as the Dashboard counts them.
 *   - Service moderation off still lists services.
 *
 * Run: wp eval-file tests/test-admin-shell-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ): void {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$capture = static function ( callable $fn ): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
};

wp_set_current_user( 1 );

// Licence reminder: Dashboard only.
if ( function_exists( 'WPSellServicesPro\\wpss_pro_license_notice' ) ) {
	set_current_screen( 'sell-services_page_wpss-orders' );
	$check( 'Licence reminder is not printed on another WPSS screen', '' === trim( $capture( 'WPSellServicesPro\\wpss_pro_license_notice' ) ) );
	set_current_screen( 'dashboard' );
	$check( 'Licence reminder is not printed on the WordPress dashboard', '' === trim( $capture( 'WPSellServicesPro\\wpss_pro_license_notice' ) ) );
	set_current_screen( 'toplevel_page_wp-sell-services' );
	$check( 'Licence reminder is printed on the WPSS Dashboard', false !== stripos( $capture( 'WPSellServicesPro\\wpss_pro_license_notice' ), 'license' ) );
} else {
	echo "SKIP  Pro is not active, licence reminder not checked\n";
}

// No "Needs your attention" line repeated on every screen.
$admin = new \WPSellServices\Admin\Admin();
$check( 'The deferred summary line is gone', ! method_exists( $admin, 'deferred_notices_summary' ) );
set_current_screen( 'sell-services_page_wpss-orders' );
$check(
	'Refund notices print nothing off the Dashboard',
	'' === trim( $capture( array( $admin, 'pending_manual_refunds_notice' ) ) . $capture( array( $admin, 'refund_review_notice' ) ) )
);

// Dashboard tiles use the primitive.
set_current_screen( 'toplevel_page_wp-sell-services' );
$page = $capture( array( $admin, 'render_dashboard_page' ) );
$check( 'Dashboard tiles render as .wpss-stat-card', false !== strpos( $page, 'wpss-stat-card wpss-stat-card--icon' ) );
$check( 'Dashboard carries no retired tile class', ! preg_match( '/wpss-(admin-stat-card|stats-row|stat-number)(?![\w-])/', $page ) );

// Orders list: no type chosen = service orders; "all" adds sub-orders.
$get_was = $_GET;
set_current_screen( 'sell-services_page_wpss-orders' );
$_GET  = array( 'page' => 'wpss-orders' );
$table = new \WPSellServices\Admin\Tables\OrdersListTable();
$check( 'Orders with no type chosen are filtered to service orders', false !== strpos( $table->get_filter_sql()[0], wpss_order_rows_sql() ) );
$_GET  = array(
	'page'          => 'wpss-orders',
	'suborder_type' => 'all',
);
$table = new \WPSellServices\Admin\Tables\OrdersListTable();
$check( 'Orders with type "all" include tips, milestones and extensions', false === strpos( $table->get_filter_sql()[0], wpss_order_rows_sql() ) );
$_GET = $get_was;

// Service moderation off still lists services.
add_filter( 'wpss_require_service_moderation', '__return_false', 99 );
set_current_screen( 'sell-services_page_wpss-moderation' );
$moderation = $capture( array( new \WPSellServices\Admin\Pages\ServiceModerationPage(), 'render_page' ) );
remove_filter( 'wpss_require_service_moderation', '__return_false', 99 );
$check( 'Moderation off says so', false !== strpos( $moderation, 'Service moderation is currently' ) );
$check( 'Moderation off still shows the status links and list', false !== strpos( $moderation, 'subsubsub' ) );

echo $fails ? "{$fails} FAILED\n" : "all passed\n";
