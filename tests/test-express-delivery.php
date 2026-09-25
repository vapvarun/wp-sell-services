<?php
/**
 * A package's Express delivery replaces its delivery time and is priced once.
 *
 * Run: wp eval-file tests/test-express-delivery.php
 *
 * Basecamp 10337201764: an "Express 24h" add-on could only ADD days, so the
 * buyer who paid for speed was promised a slower order. Express is now set on
 * the package and rides as an add-on row (WPSS_EXPRESS_ADDON_ID) through every
 * rail that carries add-ons. No rows are written.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntentService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$package = array(
	'price'         => 20,
	'delivery_days' => 3,
	'express_price' => 30,
	'express_days'  => 1,
);

// Service 0 has no add-ons of its own, so only Express can be priced.
$none    = wpss_price_addons( 0, array(), 20, $package );
$express = wpss_price_addons( 0, array( array( 'id' => WPSS_EXPRESS_ADDON_ID ) ), 20, $package );

$check( 'not picked: nothing charged, package days (3)', 0.0 === (float) $none['addons_total'] && 3 === wpss_line_delivery_days( $package, $none['addons'] ) );
$check( 'picked: +30 charged', 30.0 === (float) $express['addons_total'] );
$check( 'picked: delivery REPLACED by 1 day, not added', 1 === wpss_line_delivery_days( $package, $express['addons'] ) );
$check( 'an add-on +1 day adds to the Express base (2)', 2 === wpss_line_delivery_days( $package, array_merge( $express['addons'], array( array( 'delivery_days_extra' => 1 ) ) ) ) );
$check( 'an add-on +1 day adds to the package base (4)', 4 === wpss_line_delivery_days( $package, array( array( 'delivery_days_extra' => 1 ) ) ) );

$check( 'without the package, Express is not offered (no charge)', 0.0 === (float) wpss_price_addons( 0, array( array( 'id' => WPSS_EXPRESS_ADDON_ID ) ), 20 )['addons_total'] );
$check( 'Express not faster than the package is not offered', null === wpss_get_package_express( array( 'delivery_days' => 3, 'express_price' => 30, 'express_days' => 3 ) ) );
$check( 'Express with no price is not offered', null === wpss_get_package_express( array( 'delivery_days' => 3, 'express_days' => 1 ) ) );

// Every transport shape carries the Express id: checkout CSV, JSON, and the
// metadata a gateway return leg re-prices from.
$check( 'CSV selection "0,-1" keeps Express', isset( wpss_normalize_addon_selection( '0,-1' )[ WPSS_EXPRESS_ADDON_ID ] ) );
$check( 'JSON selection keeps Express', isset( wpss_normalize_addon_selection( '[{"id":-1}]' )[ WPSS_EXPRESS_ADDON_ID ] ) );
$check( 'other negative ids are still dropped', array() === wpss_normalize_addon_selection( '-2' ) );
$meta = CheckoutIntentService::selection_metadata( $express['addons'] );
$check( 'gateway metadata round-trips Express', isset( CheckoutIntentService::request_selection( $meta )[ WPSS_EXPRESS_ADDON_ID ] ) );

// The one validator refuses a priced Express that is not faster.
$errors = wpss_validate_service_publishable( array( 'packages' => array( array( 'name' => 'Basic', 'price' => 20, 'delivery_days' => 3, 'express_price' => 30, 'express_days' => 3 ) ) ) );
$check( 'publish refuses Express that is not faster', (bool) preg_grep( '/Express delivery/', $errors ) );
$errors = wpss_validate_service_publishable( array( 'packages' => array( array( 'name' => 'Basic', 'price' => 20, 'delivery_days' => 3, 'express_price' => 30, 'express_days' => 1 ) ) ) );
$check( 'publish accepts a real Express', ! preg_grep( '/Express delivery/', $errors ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
