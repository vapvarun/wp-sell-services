<?php
/**
 * A manual order priced by the admin is a custom order, not the package.
 *
 * Run: wp eval-file tests/test-manual-order-custom-price.php
 *
 * Owner decision 2026-10-20: the admin's price becomes the subtotal and the
 * order reads "Custom order" instead of the package name and price.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ManualOrderPage;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$found   = get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_wpss_packages' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$service = (int) ( $found[0] ?? 0 );
$price   = new ReflectionMethod( ManualOrderPage::class, 'price_manual_order' );
$run     = static fn( string $pkg, float $typed, float $override ) => $price->invoke( new ManualOrderPage(), $service, $pkg, 1, $typed, array(), $override, 10.0 );

$pkg = $run( '0', 0.0, 0.0 );
$check( 'no typed price keeps the package', ! is_wp_error( $pkg ) && null !== $pkg['package_id'] );

$typed = $run( '0', 77.0, 0.0 );
$check( 'typed price drops the package link', ! is_wp_error( $typed ) && null === $typed['package_id'] );
$check( 'typed price is the subtotal', ! is_wp_error( $typed ) && 77.0 === (float) $typed['subtotal'] );

$over = $run( '0', 0.0, 55.0 );
$check( 'exact total drops the package link', ! is_wp_error( $over ) && null === $over['package_id'] );
$check( 'exact total is the subtotal and the total', ! is_wp_error( $over ) && 55.0 === (float) $over['subtotal'] && 55.0 === (float) $over['total'] );

$order             = new \WPSellServices\Models\ServiceOrder();
$order->package_id = null;
$order->platform   = 'manual';
$order->meta       = array();
$check( 'order without a package reads Custom order', 'Custom order' === $order->get_package_name() );

exit( $fails ? 1 : 0 );
