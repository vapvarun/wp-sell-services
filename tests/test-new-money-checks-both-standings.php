<?php
/**
 * Every door that starts new money checks standing in the service, not per caller:
 * - the Offline rail's order builder (checkout form, REST create-intent, Pro /payments/offline/submit) refuses a blocked buyer;
 * - a phase and an extension are refused when the seller or the buyer is blocked;
 * - a tip is refused when the seller is blocked, not only the buyer.
 *
 * Run: wp eval-file tests/test-new-money-checks-both-standings.php
 *
 * Throwaway users (swept by the harness); the orders, profile rows and extension
 * requests it makes are deleted by id on exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Gateways\OfflineGateway;
use WPSellServices\Services\ExtensionOrderService;
use WPSellServices\Services\MilestoneService;
use WPSellServices\Services\TippingService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders   = $wpdb->prefix . 'wpss_orders';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$mk       = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_stand_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-stand-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor   = $mk( 'v' );
$buyer    = $mk( 'b' );
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'QA standing', 'status' => 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// phpcs:disable WordPress.DB.DirectDatabaseQuery
register_shutdown_function(
	static function () use ( $wpdb, $orders, $profiles, $vendor, $buyer ) {
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$orders} WHERE customer_id = %d", $buyer ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $ids as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_extension_requests', array( 'order_id' => $id ) );
			$wpdb->delete( $orders, array( 'id' => $id ) );
		}
		$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
	}
);
$order = static function ( string $platform, string $status ) use ( $wpdb, $orders, $vendor, $buyer ): int {
	$wpdb->insert( $orders, array( 'order_number' => 'QA-STAND-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => $platform, 'status' => $status, 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql' ) ) );
	return (int) $wpdb->insert_id;
};
// phpcs:enable
$set = static function ( int $user_id, string $status ): void {
	'' === $status ? delete_user_meta( $user_id, 'wpss_account_status' ) : update_user_meta( $user_id, 'wpss_account_status', $status );
};

$project = $order( 'request', 'in_progress' );
$catalog = $order( 'standalone', 'in_progress' );
$done    = $order( 'standalone', 'completed' );

// Phases.
$ms = new MilestoneService();
$set( $vendor, 'suspended' );
$res = $ms->propose( $project, $vendor, 'QA phase', 'd', 20.0, 3 );
$check( 'phase: a suspended seller cannot propose one', empty( $res['success'] ) && empty( $res['milestone_id'] ), wp_json_encode( $res ) );
$set( $vendor, '' );
$set( $buyer, 'banned' );
$res = $ms->propose( $project, $vendor, 'QA phase', 'd', 20.0, 3 );
$check( 'phase: no new phase for a banned buyer', empty( $res['success'] ) && empty( $res['milestone_id'] ), wp_json_encode( $res ) );
$set( $buyer, '' );
$res = $ms->propose( $project, $vendor, 'QA phase', 'd', 20.0, 3 );
$check( 'phase: both in good standing still works', ! empty( $res['success'] ), wp_json_encode( $res ) );

// Extensions.
$ext = new ExtensionOrderService();
$set( $vendor, 'suspended' );
$res = $ext->create_extension_request( $catalog, 15.0, 2, $vendor, 'QA extension reason text' );
$check( 'extension: a suspended seller cannot request one', empty( $res['success'] ) && empty( $res['pay_order_id'] ), wp_json_encode( $res ) );
$set( $vendor, '' );
$set( $buyer, 'suspended' );
$res = $ext->create_extension_request( $catalog, 15.0, 2, $vendor, 'QA extension reason text' );
$check( 'extension: no new extension for a suspended buyer', empty( $res['success'] ) && empty( $res['pay_order_id'] ), wp_json_encode( $res ) );
$set( $buyer, '' );
$res = $ext->create_extension_request( $catalog, 15.0, 2, $vendor, 'QA extension reason text' );
$check( 'extension: both in good standing still works', ! empty( $res['success'] ), wp_json_encode( $res ) );

// Tips: the seller's standing as well as the buyer's.
add_filter( 'wpss_tipping_enabled', '__return_true' );
$set( $vendor, 'banned' );
$res = ( new TippingService() )->create_pending_tip_order( $done, 5.0, $buyer );
$check( 'tip: no tip to a banned seller', empty( $res['success'] ) && empty( $res['tip_order_id'] ), wp_json_encode( $res ) );
$set( $vendor, '' );
$res = ( new TippingService() )->create_pending_tip_order( $done, 5.0, $buyer );
$check( 'tip: a seller in good standing can be tipped', ! empty( $res['success'] ), wp_json_encode( $res ) );

// The Offline rail's order builder, shared by every offline door that creates an order.
$seller  = wpss_test_fixture_user( 'vendor' );
$service = $seller ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' AND post_author = %d ORDER BY ID ASC LIMIT 1", $seller ) ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( ! $service ) {
	echo "SKIP  offline rail: no published service by an active vendor\n";
} else {
	wp_set_current_user( $buyer );
	$set( $buyer, 'banned' );
	$made = ( new OfflineGateway() )->create_service_order( $service, 0, 1, array(), 'offline' );
	$check( 'offline rail: a banned buyer gets the standing refusal, no order', is_wp_error( $made ) && 'wpss_account_banned' === $made->get_error_code() && 403 === ( $made->get_error_data()['status'] ?? 0 ), is_wp_error( $made ) ? $made->get_error_code() : 'order #' . (int) ( $made->id ?? 0 ) );
	$set( $buyer, '' );
	$made = ( new OfflineGateway() )->create_service_order( $service, 0, 1, array(), 'offline' );
	$check( 'offline rail: a buyer in good standing can still order', ! is_wp_error( $made ) && ! empty( $made->id ), is_wp_error( $made ) ? $made->get_error_message() : '' );
	wp_set_current_user( 0 );
}

// The cart branch of the offline AJAX handler carries the same gate (it exits, so read the source).
$src  = (string) file_get_contents( dirname( __DIR__ ) . '/src/Integrations/Gateways/OfflineGateway.php' );
$cart = substr( $src, (int) strpos( $src, '$is_multi = ' ), 1200 );
$check( 'offline cart branch checks the buyer standing', str_contains( $cart, 'wpss_account_status_block( $customer_id )' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
