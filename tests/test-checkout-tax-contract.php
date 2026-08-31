<?php
/**
 * Checkout tax contract.
 *
 * A buyer was shown $100.30 and charged $85.00. Tax was written out inline in
 * four places and missing from a fifth - CheckoutIntentService, which is the
 * figure the gateway actually charges - so the 18% was displayed, recorded on
 * the order, and never collected from anybody (Basecamp 10254444011).
 *
 * The property that matters is simple and absolute: THE CHARGE EQUALS WHAT THE
 * BUYER WAS SHOWN. Everything below exists to hold that.
 *
 * Run: wp eval-file tests/test-checkout-tax-contract.php
 *
 * @package WPSellServices
 */

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

echo "\nCheckout tax contract\n\n";

$service = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);

if ( ! $service ) {
	echo "  SKIP  no published service on this install\n";
	return;
}

$service_id = (int) $service[0];
$packages   = get_post_meta( $service_id, '_wpss_packages', true );
$price      = (float) ( $packages[0]['price'] ?? 0 );

$saved = get_option( 'wpss_tax', array() );

$intent_amount = static function () use ( $service_id ) {
	$svc = new \WPSellServices\Checkout\CheckoutIntentService();
	$ref = new ReflectionMethod( $svc, 'resolve_single' );
	$ref->setAccessible( true );
	$intent = $ref->invoke( $svc, array( 'service_id' => $service_id, 'package_id' => 0 ), 1 );
	return is_wp_error( $intent ) ? null : (float) $intent->amount;
};

// --- Exclusive tax: added on top -----------------------------------------
update_option(
	'wpss_tax',
	array( 'enable_tax' => true, 'tax_rate' => 18, 'tax_included' => false, 'tax_label' => 'Tax' )
);

$calc   = wpss_calculate_tax( $price, 0, $service_id );
$charge = $intent_amount();

wpss_t( abs( $calc['total'] - ( $price * 1.18 ) ) < 0.01, sprintf( 'exclusive tax adds 18%% (%.2f -> %.2f)', $price, $calc['total'] ) );
wpss_t( null !== $charge && abs( $charge - $calc['total'] ) < 0.01, sprintf( 'THE CHARGE EQUALS THE DISPLAYED TOTAL (%.2f)', (float) $charge ) );
wpss_t( null !== $charge && $charge > $price, 'the charge is more than the pre-tax price, i.e. tax is actually collected' );

// --- Inclusive tax: already inside the price ------------------------------
update_option(
	'wpss_tax',
	array( 'enable_tax' => true, 'tax_rate' => 18, 'tax_included' => true, 'tax_label' => 'Tax' )
);

$inc    = wpss_calculate_tax( $price, 0, $service_id );
$charge = $intent_amount();

wpss_t( abs( $inc['total'] - $price ) < 0.01, 'inclusive tax leaves the total at the sticker price' );
wpss_t( $inc['amount'] > 0 && $inc['amount'] < $price, 'inclusive tax is extracted from the price, not added to it' );
wpss_t( null !== $charge && abs( $charge - $price ) < 0.01, 'an inclusive-tax site is not overcharged by the rate' );

// --- Tax off --------------------------------------------------------------
update_option( 'wpss_tax', array( 'enable_tax' => false, 'tax_rate' => 18 ) );

$off    = wpss_calculate_tax( $price, 0, $service_id );
$charge = $intent_amount();

wpss_t( 0.0 === (float) $off['amount'], 'no tax is applied when tax is switched off' );
wpss_t( null !== $charge && abs( $charge - $price ) < 0.01, 'the charge is the bare price when tax is off' );

// --- One implementation ---------------------------------------------------
update_option( 'wpss_tax', $saved );

$roots = array(
	dirname( __DIR__ ) . '/src/Integrations/Standalone/StandaloneCheckoutProvider.php',
	dirname( __DIR__ ) . '/src/Integrations/Standalone/StandaloneOrderProvider.php',
	dirname( __DIR__ ) . '/src/Checkout/CheckoutIntentService.php',
);

$inline = array();
foreach ( $roots as $file ) {
	$src = file_get_contents( $file );
	// The shape of the old inline maths, in either direction.
	if ( preg_match( '#/ \( 1 \+ \$[a-z_]*tax_rate / 100 \)|\$[a-z_]*tax_rate / 100 \)#', $src ) ) {
		$inline[] = basename( $file );
	}
}

wpss_t( empty( $inline ), 'no site computes tax inline any more (' . ( $inline ? implode( ', ', $inline ) : 'all use wpss_calculate_tax' ) . ')' );

foreach ( $roots as $file ) {
	wpss_t(
		false !== strpos( file_get_contents( $file ), 'wpss_calculate_tax(' ),
		basename( $file ) . ' uses the shared helper'
	);
}

// --- Commission is untouched ---------------------------------------------
// Tax is not revenue to split. CommissionService works from subtotal +
// addons_total, which is the pre-tax base, and must stay that way.
$commission_src = file_get_contents( dirname( __DIR__ ) . '/src/Services/CommissionService.php' );
wpss_t(
	false !== strpos( $commission_src, '$order->subtotal + (float) $order->addons_total' ),
	'commission is still calculated on the pre-tax base'
);
wpss_t(
	false === strpos( $commission_src, 'wpss_calculate_tax' ),
	'commission does not consult tax at all'
);

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
