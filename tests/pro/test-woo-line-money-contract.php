<?php
/**
 * WooCommerce line-money contract.
 *
 * A WPSS order created from a WooCommerce order must record the money the
 * buyer PAID for that line: Woo's line total after coupons, plus Woo's tax,
 * with the add-ons the buyer picked. Before 1.7.1 the provider recorded the
 * package list price times quantity, subtracted the add-ons from it, and paid
 * the vendor from that number - so a 100 package with a 150 add-on stored a
 * total of 100 and a subtotal of -50 (Basecamp 10264297198).
 *
 * Also covered: two packages of one service in one cart are two WPSS orders,
 * a Woo refund reports to the shared gateway-refund seam (naming the gig when it was made on
 * a line), our own refund screens step aside for a Woo-paid order, and completing every WPSS order on a
 * WC order completes the WC order.
 *
 * Runs the provider directly, so it works on any rail as long as WooCommerce
 * is active. Nothing here goes through checkout.
 *
 * Run: wp eval-file tests/pro/test-woo-line-money-contract.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\CommissionService;
use WPSellServicesPro\Integrations\WooCommerce\WCOrderProvider;
use WPSellServicesPro\Integrations\WooCommerce\WooCommerceAdapter;

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nWooCommerce line-money contract\n\n";

if ( ! function_exists( 'wc_create_order' ) ) {
	echo "  SKIP  WooCommerce is not active.\n";
	return;
}

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';

// A throwaway service: package 0 = 100, package 1 = 300, add-on 0 = 150.
$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'Line-money contract service',
		'post_author' => 1,
	)
);
update_post_meta(
	$service_id,
	'_wpss_packages',
	array(
		array( 'name' => 'Basic', 'price' => 100, 'delivery_time' => 5, 'revisions' => 1 ),
		array( 'name' => 'Pro', 'price' => 300, 'delivery_time' => 10, 'revisions' => 2 ),
	)
);
wpss_save_service_addons( $service_id, array( array( 'title' => 'Landing page', 'price' => 150 ) ) );

$product = new WC_Product_Simple();
$product->set_name( 'Line-money carrier' );
$product->set_virtual( true );
$product->set_regular_price( '0' );
$product_id = $product->save();

$wc_order = wc_create_order( array( 'customer_id' => 1, 'status' => 'processing' ) );

/**
 * Add one WPSS service line to the WC order.
 *
 * @param WC_Order $order    WC order.
 * @param int      $product  Product ID.
 * @param int      $service  Service ID.
 * @param int      $package  Package index.
 * @param array    $addons   Add-on indices.
 * @param float    $subtotal Line subtotal before the coupon.
 * @param float    $total    Line total after the coupon.
 * @return int Item ID.
 */
function wpss_t_add_line( $order, $product, $service, $package, $addons, $subtotal, $total ) {
	$item = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $product ) );
	$item->set_quantity( 1 );
	$item->set_subtotal( (string) $subtotal );
	$item->set_total( (string) $total );
	$item->add_meta_data( '_wpss_service_id', (string) $service, true );
	$item->add_meta_data( '_wpss_package_id', (string) $package, true );
	$item->add_meta_data( '_wpss_addons', $addons, true );
	$order->add_item( $item );
	return (int) $item->save();
}

// Package 0 (100) + add-on (150) = 250, coupon takes 20 → buyer pays 230.
$item_a = wpss_t_add_line( $wc_order, $product_id, $service_id, 0, array( 0 ), 250, 230 );
// Package 1 (300) of the SAME service, no add-ons, no coupon.
$item_b = wpss_t_add_line( $wc_order, $product_id, $service_id, 1, array(), 300, 300 );
$wc_order->calculate_totals( false );
$wc_order->save();
$wc_id = $wc_order->get_id();

$provider = new WCOrderProvider();
$provider->handle_order_processed( $wc_id, array(), $wc_order );

$rows = $wpdb->get_results(
	$wpdb->prepare( "SELECT * FROM {$table} WHERE platform = 'woocommerce' AND platform_order_id = %d ORDER BY platform_item_id", $wc_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);
$by_item = array();
foreach ( $rows as $row ) {
	$by_item[ (int) $row->platform_item_id ] = $row;
}

wpss_t( 2 === count( $rows ), 'two packages of one service create two WPSS orders (got ' . count( $rows ) . ')' );

$a = $by_item[ $item_a ] ?? null;
$b = $by_item[ $item_b ] ?? null;

if ( $a ) {
	$expected = CommissionService::compute_breakdown( 230.0, (object) array( 'id' => 0, 'vendor_id' => 1, 'service_id' => $service_id ) );

	wpss_t( abs( (float) $a->total - 230.0 ) < 0.001, "WPSS total equals the paid Woo line total 230 (got {$a->total})" );
	wpss_t( abs( (float) $a->addons_total - 150.0 ) < 0.001, "addons_total is the add-on the buyer bought, 150 (got {$a->addons_total})" );
	wpss_t( abs( (float) $a->subtotal - 80.0 ) < 0.001, "subtotal is the package share after the coupon, 80 (got {$a->subtotal})" );
	wpss_t( abs( (float) $a->vendor_earnings - $expected['vendor_earnings'] ) < 0.001, "vendor earnings come from the paid 230 (got {$a->vendor_earnings}, expected {$expected['vendor_earnings']})" );
	wpss_t( abs( (float) $a->platform_fee - $expected['platform_fee'] ) < 0.001, "platform fee comes from the paid 230 (got {$a->platform_fee})" );
	$meta = json_decode( (string) $a->meta, true ) ?: array();
	wpss_t( abs( (float) ( $meta['discount'] ?? 0 ) - 20.0 ) < 0.001, 'the coupon amount is kept on the order as discount' );
} else {
	wpss_t( false, 'a WPSS order exists for the package + add-on line' );
}

if ( $b ) {
	wpss_t( abs( (float) $b->total - 300.0 ) < 0.001, "second package records its own paid amount 300 (got {$b->total})" );
	wpss_t( 1 === (int) $b->package_id, 'second WPSS order carries package 1' );
} else {
	wpss_t( false, 'a WPSS order exists for the second package' );
}

// Re-running the checkout hook is idempotent per line.
$provider->handle_order_processed( $wc_id, array(), $wc_order );
$recount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE platform = 'woocommerce' AND platform_order_id = %d", $wc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
wpss_t( 2 === $recount, 'processing the same WC order twice does not duplicate WPSS orders' );

// Woo → WPSS: an amount-only refund on a two-gig order could be for either gig. It is reported
// once as "one of these" and never split by price.
$received = array();
add_action(
	'wpss_gateway_refund_received',
	function ( $gateway, $txn, $amount, $context ) use ( &$received ) {
		$received[] = array( $gateway, $txn, $amount, $context );
	},
	10,
	4
);

$refund = wc_create_refund( array( 'amount' => 53, 'order_id' => $wc_id, 'reason' => 'contract' ) );
wpss_t( ! is_wp_error( $refund ), 'a partial Woo refund of 53 on 530 can be created' );
// On a site whose active rail is WooCommerce the provider is already hooked
// to woocommerce_order_refunded, so wc_create_refund() has reported the
// refund. Calling the handler again there reported it twice.
if ( empty( $received ) ) {
	$provider->handle_order_refunded( $wc_id, is_wp_error( $refund ) ? 0 : $refund->get_id() );
}

wpss_t( 1 === count( $received ), 'an amount-only refund is reported once, not once per gig (got ' . count( $received ) . ')' );
wpss_t( ! empty( $received ) && abs( (float) $received[0][2] - 53.0 ) < 0.001, 'and it carries the whole 53, not a share' );
wpss_t( ! empty( $received ) && 'woocommerce' === $received[0][0] && (string) $wc_id === $received[0][1], 'the seam names the woocommerce rail and the WC order id' );
$candidates = (array) ( $received[0][3]['order_ids'] ?? array() );
wpss_t( $a && $b && in_array( (int) $a->id, $candidates, true ) && in_array( (int) $b->id, $candidates, true ) && ! isset( $received[0][3]['order_id'] ), 'it lists both gigs as candidates and names neither' );

// A refund made on a line names that gig.
$received = array();
$line     = $a ? (int) $a->platform_item_id : 0;
$refund   = wc_create_refund(
	array(
		'amount'     => 20,
		'order_id'   => $wc_id,
		'line_items' => array(
			$line => array(
				'qty'          => 0,
				'refund_total' => 20,
				'refund_tax'   => array(),
			),
		),
	)
);
if ( empty( $received ) && ! is_wp_error( $refund ) ) {
	$provider->handle_order_refunded( $wc_id, $refund->get_id() );
}
wpss_t( 1 === count( $received ) && $a && (int) ( $received[0][3]['order_id'] ?? 0 ) === (int) $a->id && abs( (float) $received[0][2] - 20.0 ) < 0.001, 'a refund made on a line names that gig' );

// WPSS → Woo: WooCommerce collected the money, so our screens refuse and Woo is untouched.
if ( $a ) {
	add_filter( 'wpss_order_money_rail', array( $provider, 'money_rail' ), 10, 2 );
	$before = (float) wc_get_order( $wc_id )->get_total_refunded();
	$result = ( new \WPSellServices\Services\OrderService() )->refund( (int) $a->id, 10.0, \WPSellServices\Models\ServiceOrder::STATUS_PARTIALLY_REFUNDED );
	$after  = (float) wc_get_order( $wc_id )->get_total_refunded();
	remove_filter( 'wpss_order_money_rail', array( $provider, 'money_rail' ), 10 );
	wpss_t( false === $result['ok'] && false !== strpos( (string) $result['message'], 'WooCommerce' ), 'our refund on a Woo-paid order is refused and points to WooCommerce' );
	wpss_t( abs( $after - $before ) < 0.001, 'and nothing is created in WooCommerce' );
	wpss_t( ! method_exists( $provider, 'process_wpss_refund' ), 'the provider no longer creates refunds in WooCommerce' );
}

// WPSS → Woo: completing every WPSS order completes the WC order.
$adapter = new WooCommerceAdapter();
if ( $a && $b && method_exists( $adapter, 'sync_complete_to_wc' ) ) {
	$wpdb->update( $table, array( 'status' => 'completed' ), array( 'id' => (int) $a->id ) );
	$adapter->sync_complete_to_wc( (int) $a->id, 'delivered' );
	wpss_t( wc_get_order( $wc_id )->has_status( 'processing' ), 'WC order stays processing while another WPSS order is open' );
	$wpdb->update( $table, array( 'status' => 'completed' ), array( 'id' => (int) $b->id ) );
	$adapter->sync_complete_to_wc( (int) $b->id, 'delivered' );
	wpss_t( wc_get_order( $wc_id )->has_status( 'completed' ), 'WC order completes once every WPSS order on it is completed' );
} else {
	wpss_t( false, 'the adapter syncs WPSS completed to WooCommerce (sync_complete_to_wc)' );
}

// Clean up.
foreach ( wc_get_order( $wc_id )->get_refunds() as $r ) {
	$r->delete( true );
}
wc_get_order( $wc_id )->delete( true );
wp_delete_post( $product_id, true );
wp_delete_post( $service_id, true );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE platform = 'woocommerce' AND platform_order_id = %d", $wc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( $rows as $row ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => (int) $row->id ) );
}

echo "\n  {$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n\n";
