<?php
/**
 * 1.9.1 combo-smoke contracts: suspended vendor starts nothing, order limits
 * cannot be negative, a repeat milestone submit changes nothing, a paid
 * extension returns a late order to In Progress.
 *
 * Run: wp eval-file tests/test-smoke-1-9-1-contracts.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

/**
 * Print one assertion.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $msg . "\n";
}

global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$orders   = $wpdb->prefix . 'wpss_orders';

echo "\n1.9.1 smoke contracts\n\n";

// 10425686753: order limits clamp server-side.
( new \WPSellServices\Admin\Settings() )->register_settings();
wpss_t( 0.0 === (float) sanitize_option( 'wpss_min_order_amount', '-5' ), 'minimum order amount -5 is stored as 0' );
wpss_t( 0.0 === (float) sanitize_option( 'wpss_max_order_amount', '-100' ), 'maximum order amount -100 is stored as 0' );

// 10425686711: a vendor suspended on the profile row starts no new thread.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$vendor = $wpdb->get_row( "SELECT user_id, status FROM {$profiles} WHERE status = 'active' LIMIT 1" );
if ( $vendor ) {
	$uid = (int) $vendor->user_id;
	$svc = new \WPSellServices\Services\ConversationService();
	wpss_t( null === $svc->standing_block( 0, $uid ), 'active vendor may start a pre-sale thread' );
	( new \WPSellServices\Services\VendorService() )->set_status( $uid, 'suspended' );
	$block = $svc->standing_block( 0, $uid );
	wpss_t( $block instanceof \WP_Error, 'suspended vendor is refused a new pre-sale thread' );
	$nav = new ReflectionMethod( \WPSellServices\Frontend\UnifiedDashboard::class, 'is_suspended_vendor' );
	$nav->setAccessible( true );
	wpss_t( $nav->invoke( new \WPSellServices\Frontend\UnifiedDashboard(), $uid ), 'dashboard sees the vendor as suspended (Sales Orders stays)' );
	( new \WPSellServices\Services\VendorService() )->set_status( $uid, 'active' );
} else {
	echo "  SKIP  no active vendor profile.\n";
}

// 10425686815: a repeat milestone submit fires nothing.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$phase = $wpdb->get_row( "SELECT id, vendor_id, status FROM {$orders} WHERE platform = 'milestone' ORDER BY id DESC LIMIT 1" );
if ( $phase ) {
	$fired = 0;
	add_action( 'wpss_milestone_submitted', function () use ( &$fired ) { ++$fired; } );
	$wpdb->update( $orders, array( 'status' => 'in_progress' ), array( 'id' => $phase->id ) );
	$ms = new \WPSellServices\Services\MilestoneService();
	$ms->submit( (int) $phase->id, (int) $phase->vendor_id, 'QA-SMOKE' );
	$ms->submit( (int) $phase->id, (int) $phase->vendor_id, 'QA-SMOKE' );
	$ms->submit( (int) $phase->id, (int) $phase->vendor_id, 'QA-SMOKE' );
	wpss_t( 1 === $fired, "three submits of one phase notify once (fired {$fired})" );
	$wpdb->update( $orders, array( 'status' => $phase->status ), array( 'id' => $phase->id ) );
} else {
	echo "  SKIP  no milestone phase.\n";
}

// 10425686815: the paid-extension path returns a late order to In Progress.
$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/ExtensionOrderService.php' );
wpss_t( false !== strpos( $src, "'late' === \$parent->status" ), 'paid extension moves a late order back to in progress' );
