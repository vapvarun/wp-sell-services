<?php
/**
 * Hold/resume are admin only, a suspended seller keeps paid work but gets no new
 * activity, and the top-level admin menu is for administrators (Basecamp
 * 10399370449, 10384354109, 10379867128).
 *
 * Run: wp eval-file tests/test-account-standing.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$orders  = $wpdb->prefix . 'wpss_orders';
$profile = $wpdb->prefix . 'wpss_vendor_profiles';
$order   = $wpdb->get_row( "SELECT id, vendor_id, status FROM {$orders} ORDER BY id DESC LIMIT 1" );
if ( ! $order ) {
	echo "SKIP  needs an order\n";
	return;
}
$vendor = (int) $order->vendor_id;
$orig   = $order->status;
$call   = static function ( string $path ) {
	return rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1' . $path ) );
};

// --- Hold / resume: admin only ---------------------------------------------
$wpdb->update( $orders, array( 'status' => 'in_progress' ), array( 'id' => $order->id ) );
wp_set_current_user( $vendor );
$res = $call( "/orders/{$order->id}/hold" );
$check( 'vendor cannot put their own order on hold', 200 !== $res->get_status() || empty( $res->get_data()['success'] ) );
$check( 'order still in progress after the vendor tried', 'in_progress' === $wpdb->get_var( "SELECT status FROM {$orders} WHERE id = {$order->id}" ) );

wp_set_current_user( 1 );
$res = $call( "/orders/{$order->id}/hold" );
$check( 'admin can hold', 'on_hold' === $wpdb->get_var( "SELECT status FROM {$orders} WHERE id = {$order->id}" ) );

wp_set_current_user( $vendor );
$call( "/orders/{$order->id}/resume" );
$check( 'vendor cannot resume', 'on_hold' === $wpdb->get_var( "SELECT status FROM {$orders} WHERE id = {$order->id}" ) );

wp_set_current_user( 1 );
$call( "/orders/{$order->id}/resume" );
$check( 'admin can resume', 'in_progress' === $wpdb->get_var( "SELECT status FROM {$orders} WHERE id = {$order->id}" ) );
$wpdb->update( $orders, array( 'status' => $orig ), array( 'id' => $order->id ) );

// --- Admin menu cap ---------------------------------------------------------
global $menu;
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
( new \WPSellServices\Admin\Admin() )->add_admin_menu();
$cap = '';
foreach ( (array) $menu as $item ) {
	if ( 'wp-sell-services' === ( $item[2] ?? '' ) ) {
		$cap = $item[1];
	}
}
$check( 'top-level menu needs manage_options, not edit_posts', 'manage_options' === $cap );

// --- Suspended seller: new activity blocked, paid work open -----------------
$prev_status = $wpdb->get_var( "SELECT status FROM {$profile} WHERE user_id = {$vendor}" );
$wpdb->update( $profile, array( 'status' => 'suspended' ), array( 'user_id' => $vendor ) );
wp_cache_flush();

$proposals = new \WPSellServices\Services\ProposalService();
$res       = $proposals->submit( 999999, $vendor, array( 'description' => 'x', 'price' => 10, 'delivery_days' => 3 ) );
$check( 'ProposalService::submit refuses a suspended seller', is_wp_error( $res ) && 'wpss_vendor_suspended' === $res->get_error_code() );

$conversations = new \WPSellServices\Services\ConversationService();
$direct        = $conversations->create_direct( $vendor, 1, 'QA standing', 0 );
update_user_meta( $vendor, 'wpss_account_status', 'suspended' );
$check( 'pre-sale thread: standing_block refuses a suspended account', $direct && $conversations->standing_block( (int) $direct->id, $vendor ) instanceof WP_Error );
$check( 'pre-sale thread: send_message refuses it', null === $conversations->send_message( (int) $direct->id, $vendor, 'hello' ) );
$order_conv = $conversations->get_by_order( (int) $order->id );
$check( 'order thread stays open to a suspended seller', ! $order_conv || null === $conversations->standing_block( (int) $order_conv->id, $vendor ) );

// Restore.
delete_user_meta( $vendor, 'wpss_account_status' );
$wpdb->update( $profile, array( 'status' => $prev_status ), array( 'user_id' => $vendor ) );
if ( $direct ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => $direct->id ) );
}
wp_cache_flush();
wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
