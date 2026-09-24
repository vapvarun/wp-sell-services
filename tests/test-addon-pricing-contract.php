<?php
/**
 * Every add-on type is priced the way the vendor set it, by one pricer.
 *
 * Run: wp eval-file tests/test-addon-pricing-contract.php
 *
 * The editor offers flat, percentage-of-order and per-quantity pricing, and
 * checkbox / quantity / dropdown / text fields, but every surface charged every
 * add-on as a flat amount: a 10% add-on on a $50 package cost $10 (Basecamp
 * 10336467507). wpss_price_addons() and CheckoutIntentService::price_service_line()
 * are now the one pricer; this pins its arithmetic, and that the surfaces agree.
 *
 * A throwaway service is created and deleted. Tax is pinned off for exact numbers.
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
$money = static fn( $a, $b ) => abs( (float) $a - (float) $b ) < 0.005;

$vendor = get_user_by( 'login', 'wpss_vendor_maya' );
if ( ! $vendor || ! method_exists( CheckoutIntentService::class, 'price_service_line' ) ) {
	echo $vendor ? "FAIL  CheckoutIntentService::price_service_line() does not exist\n" : "SKIP  needs the QA personas\n";
	return;
}

$no_tax = static fn() => array( 'enable_tax' => false );
add_filter( 'pre_option_wpss_tax', $no_tax );

$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'Add-on pricing contract',
		'post_author' => $vendor->ID,
	)
);

update_post_meta( $service_id, '_wpss_packages', array( array( 'name' => 'Standard', 'price' => 50, 'delivery_days' => 3 ) ) );
update_post_meta(
	$service_id,
	'_wpss_addons',
	array(
		array( 'title' => 'Flat', 'price' => 20 ),
		array( 'title' => 'Pct', 'price' => 10, 'price_type' => 'percentage' ),
		array( 'title' => 'PerQty', 'price' => 5, 'price_type' => 'quantity_based', 'min_quantity' => 1, 'max_quantity' => 4 ),
		array( 'title' => 'Size', 'price' => 7, 'field_type' => 'dropdown', 'options' => 'S, M, L' ),
		array( 'title' => 'Brief', 'price' => 3, 'field_type' => 'text' ),
	)
);

$line = static fn( $selection, int $qty = 1 ) => CheckoutIntentService::price_service_line( $service_id, 0, $qty, $selection );

try {
	$l = $line( array() );
	$check( 'package alone: 50', $money( $l['total'], 50 ) );

	$l = $line( '0' );
	$check( 'flat add-on: 50 + 20 = 70', $money( $l['total'], 70 ) );

	$l = $line( array( 1 ) );
	$check( sprintf( 'percentage add-on is 10%% of the package: 50 + 5 = 55 (got %s)', $l['total'] ), $money( $l['total'], 55 ) );

	$l = $line( array( 1 ), 2 );
	$check( '  and of the package x quantity: 100 + 10 = 110', $money( $l['total'], 110 ) );

	$l = $line( array( array( 'id' => 2, 'quantity' => 3 ) ) );
	$check( 'per-quantity add-on: 50 + 3 x 5 = 65', $money( $l['total'], 65 ) );

	$l = $line( array( array( 'id' => 2, 'quantity' => 99 ) ) );
	$check( '  quantity is clamped to the max (4): 50 + 20 = 70', $money( $l['total'], 70 ) );

	$l = $line( array( array( 'id' => 3, 'option' => 'M' ) ) );
	$check( 'dropdown with a real option: 57, option recorded', $money( $l['total'], 57 ) && 'M' === ( $l['addons'][0]['option'] ?? '' ) );

	$l = $line( array( array( 'id' => 3, 'option' => 'XXL' ) ) );
	$check( '  an option the vendor did not offer is not charged', $money( $l['total'], 50 ) );

	$l = $line( array( array( 'id' => 4, 'text' => 'Blue logo please' ) ) );
	$check( 'text add-on with text: 53, text recorded', $money( $l['total'], 53 ) && 'Blue logo please' === ( $l['addons'][0]['text'] ?? '' ) );

	$l = $line( array( array( 'id' => 1, 'price' => -70 ) ) );
	$check( 'a price in the selection is ignored (still 55)', $money( $l['total'], 55 ) );

	// Required add-ons are always in, and a required text add-on needs text.
	$addons                  = get_post_meta( $service_id, '_wpss_addons', true );
	$addons[4]['is_required'] = true;
	update_post_meta( $service_id, '_wpss_addons', $addons );
	$l = $line( array() );
	$check( 'a required text add-on left empty is refused', is_wp_error( $l ) && 'wpss_addon_required' === $l->get_error_code() );
	$addons[0]['is_required'] = true;
	$addons[4]['is_required'] = false;
	update_post_meta( $service_id, '_wpss_addons', $addons );
	$l = $line( array() );
	$check( 'a required checkbox add-on is always charged: 70', $money( $l['total'], 70 ) );
	$addons[0]['is_required'] = false;
	update_post_meta( $service_id, '_wpss_addons', $addons );

	// The triage case: 10% of $50 at 18% tax on top is 70.80... here tax is off.
	$l = $line( array( 1, 0, array( 'id' => 2, 'quantity' => 2 ) ) );
	$check( 'mixed: 50 + 5 + 20 + 10 = 85', $money( $l['total'], 85 ) );
} finally {
	remove_filter( 'pre_option_wpss_tax', $no_tax );
	wp_delete_post( $service_id, true );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
