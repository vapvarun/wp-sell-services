<?php
/**
 * Phases and extensions carry the site's tax; tips only when "Tax tips" is on.
 *
 * Run: wp eval-file tests/test-sub-order-tax.php
 *
 * Seeds one vendor, one buyer and one parent order (prefix QA-RFT); sets wpss_tax to 20% and restores it after.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ExtensionOrderService;
use WPSellServices\Services\MilestoneService;
use WPSellServices\Services\TippingService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$near = static fn( $a, $b ) => abs( (float) $a - (float) $b ) < 0.005;

$orders = $wpdb->prefix . 'wpss_orders';
$mk     = static fn( string $n ) => wp_insert_user( array( 'user_login' => "qa_rft_tx_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-rft-tx-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor = $mk( 'v' );
$buyer  = $mk( 'b' );
$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor, 'display_name' => 'QA-RFT tax', 'status' => 'active' ) );
$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-TX-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => 'USD', 'created_at' => current_time( 'mysql' ) ) );
$parent = (int) $wpdb->insert_id;
// Phases need a custom-project order, tips a completed one, extensions an active catalog one.
$wpdb->update( $orders, array( 'platform' => 'request' ), array( 'id' => $parent ) );
$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-TX-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'completed', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => 'USD', 'created_at' => current_time( 'mysql' ) ) );
$done = (int) $wpdb->insert_id;
$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-TX-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => 'USD', 'created_at' => current_time( 'mysql' ) ) );
$active = (int) $wpdb->insert_id;

$orig = get_option( 'wpss_tax', null );
$row  = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders} WHERE id = %d", $id ) );
$meta = static fn( $r ) => (array) json_decode( (string) $r->meta, true );
$made = array( $parent, $done, $active );

// Exclusive tax, 20%.
update_option( 'wpss_tax', array( 'enable_tax' => 1, 'tax_rate' => 20, 'tax_included' => 0, 'tax_label' => 'VAT' ) );

$res = ( new MilestoneService() )->propose( $parent, $vendor, 'QA-RFT phase', 'd', 50.0, 7 );
$ms  = $row( (int) ( $res['milestone_id'] ?? 0 ) );
$made[] = (int) ( $ms->id ?? 0 );
$check( 'phase: pre-tax subtotal 50, taxed total 60', $ms && $near( $ms->subtotal, 50 ) && $near( $ms->total, 60 ) );
$check( 'phase: rate and amount stored for the pay page', $ms && $near( $meta( $ms )['tax_amount'] ?? 0, 10 ) && $near( $meta( $ms )['tax_rate'] ?? 0, 20 ) );
$calc = $ms ? ( new \WPSellServices\Services\CommissionService() )->calculate( (int) $ms->id ) : array();
$check( 'phase: commission is taken on the pre-tax 50, not 60', $near( ( $calc['platform_fee'] ?? 0 ) + ( $calc['vendor_earnings'] ?? 0 ), 50 ) );

// The Free pay page shows the VAT line the total carries.
wp_set_current_user( $buyer );
$provider = new \WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider();
$html     = (string) ( new ReflectionMethod( $provider, 'render_pay_order_checkout' ) )->invoke( $provider, (int) $ms->id );
$check( 'pay page: Subtotal, VAT (20%) and Order total lines', false !== strpos( $html, 'VAT (20%)' ) && false !== strpos( $html, 'Subtotal' ) && false !== strpos( $html, 'Order total' ) );
wp_set_current_user( 0 );

$res = ( new ExtensionOrderService() )->create_extension_request( $active, 30.0, 3, $vendor, 'QA-RFT extension' );
$ex  = $row( (int) ( $res['pay_order_id'] ?? 0 ) );
$made[] = (int) ( $ex->id ?? 0 );
$check( 'extension: subtotal 30, taxed total 36', $ex && $near( $ex->subtotal, 30 ) && $near( $ex->total, 36 ) && $near( $meta( $ex )['tax_amount'] ?? 0, 6 ) );

$res = ( new TippingService() )->create_pending_tip_order( $done, 12.0, $buyer );
$tp  = $row( (int) ( $res['tip_order_id'] ?? 0 ) );
$made[] = (int) ( $tp->id ?? 0 );
$check( 'tip: untaxed by default (12 stays 12)', $tp && $near( $tp->subtotal, 12 ) && $near( $tp->total, 12 ) && empty( $meta( $tp )['tax_amount'] ) );

// Tax tips on: a tip is taxed too. Remove the first tip so a second can be created.
$wpdb->delete( $orders, array( 'id' => (int) $tp->id ) );
update_option( 'wpss_tax', array( 'enable_tax' => 1, 'tax_rate' => 20, 'tax_included' => 0, 'tax_tips' => 1, 'tax_label' => 'VAT' ) );
$res = ( new TippingService() )->create_pending_tip_order( $done, 12.0, $buyer );
$tp  = $row( (int) ( $res['tip_order_id'] ?? 0 ) );
$made[] = (int) ( $tp->id ?? 0 );
$check( 'tip with Tax tips on: total 14.40', $tp && $near( $tp->subtotal, 12 ) && $near( $tp->total, 14.4 ) );

// Inclusive tax: the buyer pays the quoted 50; the tax inside it comes out of the pre-tax subtotal.
update_option( 'wpss_tax', array( 'enable_tax' => 1, 'tax_rate' => 20, 'tax_included' => 1, 'tax_label' => 'VAT' ) );
$res = ( new MilestoneService() )->propose( $parent, $vendor, 'QA-RFT phase 2', 'd', 60.0, 7 );
$ms2 = $row( (int) ( $res['milestone_id'] ?? 0 ) );
$made[] = (int) ( $ms2->id ?? 0 );
$check( 'inclusive: buyer pays the quoted 60, tax 10 flagged as included', $ms2 && $near( $ms2->total, 60 ) && $near( $ms2->subtotal, 60 ) && ! empty( $meta( $ms2 )['tax_included'] ) && $near( $meta( $ms2 )['tax_amount'] ?? 0, 10 ) );
$calc2 = $ms2 ? ( new \WPSellServices\Services\CommissionService() )->calculate( (int) $ms2->id ) : array();
$check( 'inclusive: commission is taken on the 50 left after tax, once', $near( ( $calc2['platform_fee'] ?? 0 ) + ( $calc2['vendor_earnings'] ?? 0 ), 50 ) );

// Tax off: nothing changes.
update_option( 'wpss_tax', array( 'enable_tax' => 0, 'tax_rate' => 20 ) );
$res = ( new MilestoneService() )->propose( $parent, $vendor, 'QA-RFT phase 3', 'd', 40.0, 7 );
$ms3 = $row( (int) ( $res['milestone_id'] ?? 0 ) );
$made[] = (int) ( $ms3->id ?? 0 );
$check( 'tax off: phase total equals the quote', $ms3 && $near( $ms3->total, 40 ) && $near( $ms3->subtotal, 40 ) && empty( $meta( $ms3 )['tax_amount'] ) );

// The setting survives a save, and is off when the box is unticked.
$settings = new \WPSellServices\Admin\Settings();
$check( 'Tax tips saves when ticked', ! empty( $settings->sanitize_tax_settings( array( 'enable_tax' => 1, 'tax_tips' => 1 ) )['tax_tips'] ) );
$check( 'Tax tips is off when unticked', empty( $settings->sanitize_tax_settings( array( 'enable_tax' => 1 ) )['tax_tips'] ) );

// Clean up.
null === $orig ? delete_option( 'wpss_tax' ) : update_option( 'wpss_tax', $orig );
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_extension_requests WHERE order_id = " . (int) $active );
foreach ( array_filter( $made ) as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) );
}
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor );
wp_delete_user( $buyer );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
