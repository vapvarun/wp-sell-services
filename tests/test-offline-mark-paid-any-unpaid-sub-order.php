<?php
/**
 * An unpaid offline extension or phase that an admin forced to Completed can still be confirmed:
 * the admin box offers Mark as Paid, the AJAX accepts it, the vendor is credited exactly once, and
 * a completed phase stays completed instead of being reopened.
 *
 * Run: wp eval-file tests/test-offline-mark-paid-any-unpaid-sub-order.php
 *
 * Throwaway users (swept by the harness); the orders, extension request and ledger rows it makes
 * are deleted by id on exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Gateways\OfflineGateway;
use WPSellServices\Services\CommissionService;
use WPSellServices\Services\ExtensionOrderService;
use WPSellServices\Services\MilestoneService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders   = $wpdb->prefix . 'wpss_orders';
$ledger   = $wpdb->prefix . 'wpss_wallet_transactions';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$mk       = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_mkpaid_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-mkpaid-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor   = $mk( 'v' );
$buyer    = $mk( 'b' );
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'QA mark paid', 'status' => 'active' ) );
register_shutdown_function(
	static function () use ( $wpdb, $orders, $ledger, $profiles, $vendor, $buyer ) {
		foreach ( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$orders} WHERE customer_id = %d", $buyer ) ) ) as $id ) {
			$wpdb->delete( $ledger, array( 'reference_type' => 'order', 'reference_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'wpss_extension_requests', array( 'order_id' => $id ) );
			$wpdb->delete( $orders, array( 'id' => $id ) );
		}
		$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
		$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
	}
);
$parent = static function ( string $platform ) use ( $wpdb, $orders, $vendor, $buyer ): int {
	$wpdb->insert( $orders, array( 'order_number' => 'QA-MKPAID-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => $platform, 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ), 'delivery_deadline' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) ) );
	return (int) $wpdb->insert_id;
};
$credits = static fn( int $id, string $type ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND type = %s", $id, $type ) );
// An admin forces the unpaid offline sub-order to Completed, through the completion pipeline.
$force_complete = static function ( int $id ) use ( $wpdb, $orders ): void {
	$wpdb->update( $orders, array( 'payment_method' => 'offline', 'status' => 'completed', 'completed_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
	( new CommissionService() )->record( $id );
};
// phpcs:enable

wp_set_current_user( 1 );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'done' ); } );
$gateway   = new OfflineGateway();
$mark_paid = static function ( int $id ) use ( $gateway ): array {
	$_POST    = array( 'nonce' => wp_create_nonce( 'wpss_admin_mark_paid' ), 'order_id' => $id );
	$_REQUEST = $_POST;
	ob_start();
	try {
		$gateway->ajax_admin_mark_paid();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	return (array) json_decode( (string) ob_get_clean(), true );
};
$box = static function ( int $id ) use ( $gateway ): string {
	$order = wpss_get_order( $id );
	ob_start();
	$gateway->render_admin_order_actions( $order, (string) $order->status );
	return (string) ob_get_clean();
};

// Extension.
$res = ( new ExtensionOrderService() )->create_extension_request( $parent( 'standalone' ), 12.0, 2, $vendor, 'QA mark paid extension' );
$ext = (int) ( $res['pay_order_id'] ?? 0 );
if ( ! $ext ) {
	echo 'SKIP  extension: could not create one (' . esc_html( (string) ( $res['message'] ?? '' ) ) . ")\n";
} else {
	$force_complete( $ext );
	$check( 'extension: forced Completed while unpaid credits nothing', 0 === $credits( $ext, ExtensionOrderService::TYPE_EXTENSION ) + $credits( $ext, 'order_earning' ) );
	$check( 'extension: the admin box offers Mark as Paid', str_contains( $box( $ext ), 'wpss-mark-paid-btn' ) );
	$json = $mark_paid( $ext );
	$check( 'extension: Mark as Paid is accepted', ! empty( $json['success'] ), wp_json_encode( $json ) );
	$check( 'extension: credited exactly once', 1 === $credits( $ext, ExtensionOrderService::TYPE_EXTENSION ) && 0 === $credits( $ext, 'order_earning' ) );
	$mark_paid( $ext );
	$check( 'extension: a second Mark as Paid credits nothing more', 1 === $credits( $ext, ExtensionOrderService::TYPE_EXTENSION ) );
	$check( 'extension: once paid the box is gone', ! str_contains( $box( $ext ), 'wpss-mark-paid-btn' ) );
}

// Phase.
$res = ( new MilestoneService() )->propose( $parent( 'request' ), $vendor, 'QA mark paid phase', 'd', 30.0, 3 );
$ms  = (int) ( $res['milestone_id'] ?? 0 );
if ( ! $ms ) {
	echo 'SKIP  phase: could not propose one (' . esc_html( (string) ( $res['message'] ?? '' ) ) . ")\n";
} else {
	$force_complete( $ms );
	$check( 'phase: forced Completed while unpaid credits nothing', 0 === $credits( $ms, MilestoneService::TYPE_MILESTONE ) );
	$check( 'phase: the admin box offers Mark as Paid', str_contains( $box( $ms ), 'wpss-mark-paid-btn' ) );
	$json = $mark_paid( $ms );
	$check( 'phase: Mark as Paid is accepted', ! empty( $json['success'] ), wp_json_encode( $json ) );
	$check( 'phase: credited exactly once', 1 === $credits( $ms, MilestoneService::TYPE_MILESTONE ) );
	$check( 'phase: stays completed, not reopened', 'completed' === (string) wpss_get_order( $ms )->status, (string) wpss_get_order( $ms )->status );
}

// Not a door for anything else: a paid service order is still refused.
$paid = $parent( 'standalone' );
$json = $mark_paid( $paid );
$check( 'a paid, in-progress service order is still refused', empty( $json['success'] ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
