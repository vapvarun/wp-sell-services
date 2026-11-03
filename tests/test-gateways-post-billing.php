<?php
/**
 * Every checkout gateway posts the billing block and saves it.
 *
 * The billing fields belong to our checkout form, not to any one gateway, so
 * every gateway's payment request has to carry them and its handler has to
 * save them before the order is made (mark_as_paid() snapshots the profile
 * address). Before 1.9.2 only Stripe did: PayPal's handler saved an empty
 * request, and Razorpay neither posted nor saved, so those buyers' addresses,
 * on a service checkout or an extension pay page alike, were never kept.
 *
 * Run: wp eval-file tests/test-gateways-post-billing.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$free = dirname( __DIR__ );
$pro  = dirname( $free ) . '/wp-sell-services-pro';
$read = static fn( string $f ) => is_readable( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions

$check( 'checkout.js defines the one billing collector', str_contains( $read( "$free/assets/js/checkout.js" ), 'window.wpssBillingFields = function' ) );

$gateways = array(
	'Stripe' => array( "$free/assets/js/stripe.js", "$free/src/Integrations/Stripe/StripeGateway.php" ),
	'PayPal' => array( "$free/assets/js/paypal.js", "$free/src/Integrations/PayPal/PayPalGateway.php" ),
);
if ( is_dir( $pro ) ) {
	$gateways['Razorpay'] = array( "$pro/assets/js/razorpay.js", "$pro/src/Integrations/Razorpay/RazorpayGateway.php" );
}

foreach ( $gateways as $name => list( $js, $php ) ) {
	foreach ( array( $js, preg_replace( '/\.js$/', '.min.js', $js ) ) as $file ) {
		$check( "{$name}: " . basename( $file ) . ' posts the billing fields', str_contains( $read( $file ), 'wpssBillingFields()' ) );
	}
	$check( "{$name}: its handler saves them", str_contains( $read( $php ), 'wpss_save_billing_from_request( $_POST )' ) );
}

// The shared collector reads exactly what wpss_save_billing_from_request() accepts.
$saved = wpss_save_billing_from_request( array() );
$check( 'an empty post saves nothing (why a gateway that posts no fields kept no address)', false === $saved );

echo $fails ? "FAILED {$fails}\n" : "OK\n";
