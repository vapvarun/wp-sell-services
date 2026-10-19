<?php
/**
 * A service line restored from the WooCommerce session carries its package price, so the
 * mini cart does not print "1 x $0.00" beside a correct subtotal.
 *
 * Run: wp eval-file tests/pro/test-woo-cart-session-price.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "SKIP  WooCommerce is not active\n";
	return;
}

$service = 0;
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 20, 'fields' => 'ids' ) ) as $id ) {
	$package = wpss_resolve_service_package( (int) $id, 0 );
	if ( (float) ( $package['package']['price'] ?? 0 ) > 0 ) {
		$service = (int) $id;
		break;
	}
}
if ( ! $service ) {
	echo "SKIP  no priced published service\n";
	return;
}

$expected = (float) wpss_resolve_service_package( $service, 0 )['package']['price'];
$product  = new \WC_Product_Simple();
$product->set_price( 0 );
$item = array( 'wpss_service_id' => $service, 'wpss_package_id' => 0, 'wpss_addons' => array(), 'data' => $product );

$provider = new \WPSellServicesPro\Integrations\WooCommerce\WCCheckoutProvider();
$provider->filter_cart_item_from_session( $item, $item, 'k' );

$check( 'restored line is priced at the package price, not the carrier 0', abs( (float) $product->get_price() - $expected ) < 0.001 );
