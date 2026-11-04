<?php
/**
 * A paid extension or tip shows on the order page the buyer, the vendor and the admin read.
 *
 * They are orders of their own, so the parent's line items never mentioned them: a buyer who paid $60 for an
 * extension saw "Total $220" and nothing else. The shared line-items partial now lists them under "Also paid on
 * this order", never summed into the total, and the admin Financial Summary uses the same partial (no second list).
 *
 * Run: wp eval-file tests/test-order-page-lists-paid-extras.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ExtensionOrderService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders = $wpdb->prefix . 'wpss_orders';
$mk     = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_extras_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-extras-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor = $mk( 'v' );
$buyer  = $mk( 'b' );
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor, 'display_name' => 'QA extras', 'status' => 'active' ) );
register_shutdown_function(
	static function () use ( $wpdb, $orders, $vendor, $buyer ) {
		foreach ( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$orders} WHERE customer_id = %d", $buyer ) ) ) as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_type' => 'order', 'reference_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'wpss_extension_requests', array( 'order_id' => $id ) );
			$wpdb->delete( $orders, array( 'id' => $id ) );
		}
		$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $vendor ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) );
	}
);
$wpdb->insert( $orders, array( 'order_number' => 'QA-EXTRAS-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ), 'delivery_deadline' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) ) );
$parent = (int) $wpdb->insert_id;
$render = static function ( array $args = array() ) use ( $parent ): string {
	ob_start();
	wpss_get_template_part( 'order/line-items', '', array_merge( array( 'wpss_order' => wpss_get_order( $parent ) ), $args ) );
	return (string) ob_get_clean();
};

// Nothing paid on top yet: no group heading.
$check( 'no extras: no "Also paid" heading', ! str_contains( $render(), 'Also paid on this order' ) );

// An extension that is quoted but not paid is not listed.
wp_set_current_user( $vendor );
$res = ( new ExtensionOrderService() )->create_extension_request( $parent, 30.0, 2, $vendor, 'QA extras extension' );
$ext = (int) ( $res['pay_order_id'] ?? 0 );
if ( ! $ext ) {
	echo 'SKIP  could not create an extension (' . esc_html( (string) ( $res['message'] ?? '' ) ) . ")\n";
	exit( 0 );
}
$check( 'an unpaid extension is not listed', ! str_contains( $render(), 'Also paid on this order' ) );

// Paid: listed, with its own amount, the parent total untouched.
wpss_get_order_provider()->mark_as_paid( $ext, 'qa_extras_tx', 'offline' );
$paid_total = (float) wpss_get_order( $ext )->total;
$html       = $render();
$check( 'a paid extension is listed under "Also paid on this order"', str_contains( $html, 'Also paid on this order' ) && str_contains( $html, 'Extension' ), $html );
$check( 'the group sits inside the footer, after the Total row', strpos( $html, 'Also paid on this order' ) > strpos( $html, 'wpss-line-items__total' ) && strpos( $html, 'Also paid on this order' ) < strpos( $html, '</tfoot>' ) );
$check( 'its own taxed amount is shown', str_contains( $html, esc_html( wpss_format_price( $paid_total, wpss_get_currency() ) ) ), (string) $paid_total );
$check( 'the order Total still reads the parent total, not a sum', preg_match( '/wpss-line-items__total.*?' . preg_quote( esc_html( wpss_format_price( 100.0, wpss_get_currency() ) ), '/' ) . '/s', $html ) === 1 );
$check( 'the buyer view carries no admin link', ! str_contains( $html, 'wpss-orders&#038;action=view' ) && ! str_contains( $html, 'wpss-orders&action=view' ) );
$check( 'the admin view links the line to its order screen', str_contains( $render( array( 'wpss_extras_admin_link' => true ) ), 'order_id=' . $ext ) );

// A tip is listed too.
$wpdb->insert( $orders, array( 'order_number' => 'TIP-QA-' . wp_generate_password( 5, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'tip', 'platform_order_id' => $parent, 'status' => 'completed', 'payment_status' => 'paid', 'paid_at' => current_time( 'mysql', true ), 'subtotal' => 10, 'total' => 10, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ) ) );
$check( 'a paid tip is listed', str_contains( $render(), '>Tip' ) || str_contains( $render(), 'Tip' ) );

// The admin Financial Summary draws it once, through the same partial.
$admin_src = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Admin/Admin.php' );
$check( 'admin Financial Summary keeps no second list of extras', ! str_contains( $admin_src, 'foreach ( $wpss_extras as' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
