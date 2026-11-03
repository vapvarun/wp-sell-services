<?php
/**
 * An extension quote cannot be paid while its order is not active.
 *
 * Order 2289 went into dispute with a pending extension, and the order page still
 * offered "Accept & Pay". Every pay door now asks wpss_get_order_payment_refusal(),
 * and the quote comes back once the order is in progress again.
 *
 * Run: wp eval-file tests/test-extension-on-hold-while-order-inactive.php
 *
 * Seeds one buyer, one vendor, one order and one extension; removes them after.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Services\ExtensionOrderService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$orders   = $wpdb->prefix . 'wpss_orders';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$mk       = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_rft_hold_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-rft-hold-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor   = $mk( 'v' );
$buyer    = $mk( 'b' );
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'QA hold', 'status' => 'active' ) );
register_shutdown_function(
	static function () use ( $wpdb, $orders, $profiles, $vendor, $buyer ) {
		foreach ( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$orders} WHERE customer_id = %d", $buyer ) ) ) as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_extension_requests', array( 'order_id' => $id ) );
			$wpdb->delete( $orders, array( 'id' => $id ) );
		}
		$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $vendor ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $buyer ) );
		$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $vendor );
		wp_delete_user( $buyer );
	}
);

$service = (int) get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-HOLD-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => $service, 'platform' => 'standalone', 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ), 'delivery_deadline' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) ) );
$parent = (int) $wpdb->insert_id;
$res    = ( new ExtensionOrderService() )->create_extension_request( $parent, 40.0, 2, $vendor, 'QA-RFT extra work' );
$sub_id = (int) ( $res['pay_order_id'] ?? 0 );
$check( 'an extension is quoted on the active order', $sub_id > 0, (string) ( $res['message'] ?? '' ) );

$doors = static function () use ( $sub_id, $buyer ): array {
	wp_set_current_user( $buyer );
	$sub    = wpss_get_order( $sub_id );
	$intent = ( new CheckoutIntentService() )->resolve( array( 'pay_order' => $sub_id ), $buyer );
	$rest   = rest_do_request( new WP_REST_Request( 'POST', "/wpss/v1/orders/{$sub_id}/pay" ) );
	$url    = wpss_ensure_pay_order( $sub_id );
	wp_set_current_user( 0 );
	return array(
		'refusal' => wpss_get_order_payment_refusal( $sub ),
		'intent'  => is_wp_error( $intent ) ? $intent->get_error_code() : 'ok',
		'rest'    => $rest->get_status(),
		'url'     => $url,
	);
};

$page = static function () use ( $parent, $buyer ): string {
	wp_set_current_user( $buyer );
	ob_start();
	wpss_get_template( 'order/order-view.php', array( 'order_id' => $parent, 'order' => wpss_get_order( $parent ) ) );
	wp_set_current_user( 0 );
	return (string) ob_get_clean();
};

$active = $doors();
$check( 'active order: the order page offers Accept & Pay (control)', false !== strpos( $page(), 'Accept &amp; Pay' ) );
$check( 'active order: the extension is payable', '' === $active['refusal'] && 'ok' === $active['intent'], wp_json_encode( $active ) );

$wpdb->update( $orders, array( 'status' => 'disputed' ), array( 'id' => $parent ) );
$held = $doors();
$check( 'disputed order: the rule refuses the extension', '' !== $held['refusal'] );
$check( 'disputed order: checkout intent says on hold', 'wpss_order_on_hold' === $held['intent'], $held['intent'] );
$check( 'disputed order: the store-rail seam gives no pay page', false === strpos( $held['url'], 'pay' ) || false !== strpos( $held['url'], 'dashboard' ), $held['url'] );
$check( 'disputed order: the order page shows no Accept & Pay', false === strpos( $page(), 'Accept &amp; Pay' ) );

$wpdb->update( $orders, array( 'status' => 'in_progress' ), array( 'id' => $parent ) );
$back = $doors();
$check( 'back in progress: the quote is payable again', '' === $back['refusal'] && 'ok' === $back['intent'], wp_json_encode( $back ) );

echo $fails ? "FAILED {$fails}\n" : "OK\n";
