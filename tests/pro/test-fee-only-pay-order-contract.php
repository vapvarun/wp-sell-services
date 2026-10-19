<?php
/**
 * Fee-only pay-order totals contract.
 *
 * A tip, a paid extension and a milestone phase have no catalog product behind
 * them, so WCPayOrderResolver mints a WC order carrying one fee line and no
 * products. WooCommerce prints its `cart_subtotal` row unconditionally, so the
 * buyer met "Subtotal: $0.00" directly above "Tip for your seller: $5.00" and
 * "Total: $5.00" - a money screen that reads as broken on a charge that is
 * correct (Basecamp 10254720064).
 *
 * The guard is deliberately two-part: our meta AND an empty line-item set. A
 * one-part guard on the meta alone would strip a legitimate subtotal the day a
 * pay-order carries a real product.
 *
 * Requires the WooCommerce rail to be the active platform, because that is
 * what loads WooCommerceAdapter and therefore the filter.
 *
 * Run: wp eval-file tests/pro/test-fee-only-pay-order-contract.php
 *
 * @package WPSellServices
 */

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

echo "\nFee-only pay-order totals contract\n\n";

if ( ! function_exists( 'wc_create_order' ) ) {
	echo "  SKIP  WooCommerce is not active.\n";
	return;
}

/*
 * The filter under test lives on WooCommerceAdapter, which only loads when
 * WooCommerce is the ACTIVE RAIL. Running this on a standalone site used to
 * report two hard failures rather than a skip - and that false red is exactly
 * what sent this card back to Bugs once already. Skip honestly instead.
 */
$rail = (string) ( get_option( 'wpss_general', array() )['ecommerce_platform'] ?? '' );

if ( 'woocommerce' !== $rail ) {
	echo "  SKIP  the WooCommerce rail is not active (ecommerce_platform = '{$rail}').\n";
	echo "        Set it to 'woocommerce' to run this contract.\n";
	return;
}

$meta_key = \WPSellServicesPro\Integrations\WooCommerce\WCPayOrderResolver::WC_META_WPSS_PAY_ORDER;

wpss_t(
	false !== has_filter( 'woocommerce_get_order_item_totals' ),
	'the totals filter is registered on the WooCommerce rail'
);

/**
 * Build a throwaway WC order and report which total rows it renders.
 *
 * @param bool $ours        Carry the WPSS pay-order marker.
 * @param bool $add_product Add a real product line beside the fee.
 * @return array{rows: array<string, mixed>, order: \WC_Order}
 */
function wpss_probe_order( bool $ours, bool $add_product ): array {
	$order = wc_create_order( array( 'status' => 'pending' ) );

	if ( $ours ) {
		$order->update_meta_data(
			\WPSellServicesPro\Integrations\WooCommerce\WCPayOrderResolver::WC_META_WPSS_PAY_ORDER,
			'999999'
		);
	}

	$fee = new \WC_Order_Item_Fee();
	$fee->set_name( 'Tip for your seller' );
	$fee->set_amount( '5' );
	$fee->set_total( '5' );
	$order->add_item( $fee );

	if ( $add_product ) {
		$products = wc_get_products( array( 'limit' => 1, 'status' => 'publish' ) );
		if ( $products ) {
			$order->add_product( $products[0], 1 );
		}
	}

	$order->calculate_totals( false );
	$order->save();

	return array( 'rows' => $order->get_order_item_totals(), 'order' => $order );
}

// 1. The reported defect.
$fee_only = wpss_probe_order( true, false );
wpss_t(
	! array_key_exists( 'cart_subtotal', $fee_only['rows'] ),
	'a fee-only pay-order no longer prints Subtotal: $0.00'
);
wpss_t(
	array_key_exists( 'order_total', $fee_only['rows'] ),
	'the total the buyer is charged is still shown'
);
$fee_rows = 0;
foreach ( array_keys( $fee_only['rows'] ) as $row_key ) {
	if ( 0 === strpos( $row_key, 'fee_' ) ) {
		++$fee_rows;
	}
}
wpss_t( 1 === $fee_rows, 'the one fee line survives, so the buyer sees what they are paying for' );
$fee_only['order']->delete( true );

// 2. The guard's second half. Stripping on our meta alone would hide a real
//    subtotal the day one of these orders carries a product.
$with_product = wpss_probe_order( true, true );
if ( $with_product['order']->get_items() ) {
	wpss_t(
		array_key_exists( 'cart_subtotal', $with_product['rows'] ),
		'a pay-order that DOES carry a product keeps its subtotal'
	);
} else {
	echo "  SKIP  no published product to build the control order from.\n";
}
$with_product['order']->delete( true );

// 3. Somebody else's order is none of our business.
$foreign = wpss_probe_order( false, false );
wpss_t(
	array_key_exists( 'cart_subtotal', $foreign['rows'] ),
	'a WC order without our marker is left exactly as WooCommerce built it'
);
$foreign['order']->delete( true );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
