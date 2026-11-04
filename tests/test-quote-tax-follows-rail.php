<?php
/**
 * The order popup quote charges what the rail charges.
 *
 * Standalone: our tax is on the quote. A store rail (Woo, EDD, FluentCart) taxes
 * the catalog order itself, so the quote carries none of ours (owner, 2026-11-03).
 * Run: wp eval-file wp-content/plugins/wp-sell-services/tests/test-quote-tax-follows-rail.php
 *
 * @package WPSellServices
 */

require __DIR__ . '/exit-on-fail.php';

$fail  = 0;
$check = static function ( string $label, bool $ok ) use ( &$fail ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
};

$service_id = (int) get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
)[0];
$orig_tax   = get_option( 'wpss_tax', null );
$quote      = static function () use ( $service_id ) {
	$req = new WP_REST_Request( 'GET', '/wpss/v1/services/' . $service_id . '/quote' );
	$req->set_query_params( array( 'package' => 0, 'quantity' => 1 ) );
	return rest_do_request( $req )->get_data();
};
update_option( 'wpss_tax', array( 'enable_tax' => 1, 'tax_rate' => 20, 'tax_included' => 0, 'tax_label' => 'VAT' ) );

// The active adapter is resolved once at boot, so swap it in memory; the stored setting is never touched.
$mgr          = wpss()->get_integration_manager();
$prop         = new ReflectionProperty( $mgr, 'active_adapter' );
$orig_adapter = $prop->getValue( $mgr );
$store        = $mgr->get_adapter( 'woocommerce' );
$set_platform = static function ( string $p ) use ( $mgr, $prop, $store ) {
	$prop->setValue( $mgr, 'standalone' === $p ? $mgr->get_adapter( 'standalone' ) : $store );
};

if ( ! $store ) {
	echo "SKIP  WooCommerce adapter not registered (Pro and WooCommerce must be active)\n";
	null === $orig_tax ? delete_option( 'wpss_tax' ) : update_option( 'wpss_tax', $orig_tax );
	exit( 0 );
}

try {
	$set_platform( 'standalone' );
	$q = $quote();
	$check( 'standalone: quote carries VAT 20%', abs( (float) $q['tax'] - 0.2 * ( (float) $q['subtotal'] + (float) $q['addons_total'] ) ) < 0.01 && (float) $q['tax'] > 0 );
	$check( 'standalone: total is subtotal plus VAT', abs( (float) $q['total'] - 1.2 * ( (float) $q['subtotal'] + (float) $q['addons_total'] ) ) < 0.01 );

	$set_platform( 'woocommerce' );
	$check( 'woocommerce rail is active for the test', ! wpss_uses_standalone_payments() );
	$q = $quote();
	$check( 'woocommerce: quote carries no plugin VAT', 0.0 === (float) $q['tax'] && 0.0 === (float) $q['tax_rate'] );
	$check( 'woocommerce: total equals what Woo charges before its own tax', abs( (float) $q['total'] - ( (float) $q['subtotal'] + (float) $q['addons_total'] ) ) < 0.01 );

	// Phases and extensions keep our VAT on a store rail: the sub-order pricer is untouched.
	$sub = \WPSellServices\Checkout\CheckoutIntentService::price_line( $service_id, 50.0, 0.0 );
	$check( 'woocommerce: a phase or extension still prices with our VAT (50 to 60)', abs( (float) $sub['total'] - 60 ) < 0.01 );
} finally {
	$prop->setValue( $mgr, $orig_adapter );
	null === $orig_tax ? delete_option( 'wpss_tax' ) : update_option( 'wpss_tax', $orig_tax );
}

echo $fail ? "FAILED {$fail}\n" : "OK\n";
exit( $fail ? 1 : 0 );
