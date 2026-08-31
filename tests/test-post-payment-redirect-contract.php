<?php
/**
 * Post-payment redirect contract.
 *
 * Every gateway routes its after-payment redirect through
 * wpss_get_order_requirements_url(), which appended /requirements/ to whatever
 * it was given. A tip, a paid extension and a milestone phase have no
 * requirements step - they are payments against work whose brief already
 * exists - so the buyer landed on a URL and page chrome describing something
 * the screen was not. The content underneath was a correct tip or phase
 * receipt; only the address lied.
 *
 * Run: wp eval-file tests/test-post-payment-redirect-contract.php
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

echo "\nPost-payment redirect contract\n\n";

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';

// Sub-orders go to the order, never to a requirements step they do not have.
foreach ( wpss_get_sub_order_platforms() as $platform ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE platform = %s ORDER BY id DESC LIMIT 1", $platform ) );

	if ( ! $id ) {
		echo "  SKIP  no {$platform} order on this install\n";
		continue;
	}

	$url = wpss_get_order_requirements_url( $id );
	wpss_t( '' !== $url, sprintf( '%s order resolves a redirect', $platform ) );
	wpss_t( false === strpos( $url, '/requirements' ), sprintf( '%s order is NOT sent to a requirements step', $platform ) );
	wpss_t( false !== strpos( $url, (string) $id ), sprintf( '%s order is sent to its own order page', $platform ) );
}

// A catalog order still goes to requirements - that step is real for it.
$catalog = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$table} WHERE ( platform IS NULL OR platform NOT IN ( '" . implode( "','", array_map( 'esc_sql', wpss_get_sub_order_platforms() ) ) . "' ) ) AND service_id > 0 ORDER BY id DESC LIMIT %d",
		1
	)
);

if ( $catalog ) {
	$url = wpss_get_order_requirements_url( $catalog );
	wpss_t( false !== strpos( $url, '/requirements' ), sprintf( 'a catalog order (#%d) still goes to requirements', $catalog ) );
}

// One helper, so a gateway cannot grow its own redirect.
$plugin_dir = dirname( __DIR__ );
$callers    = array(
	'src/Checkout/CheckoutIntentService.php',
	'src/Integrations/Stripe/StripeGateway.php',
	'src/Integrations/PayPal/PayPalGateway.php',
	'src/Integrations/Standalone/StandaloneCheckoutProvider.php',
);

$hardcoded = array();
foreach ( $callers as $rel ) {
	$file = $plugin_dir . '/' . $rel;
	if ( ! file_exists( $file ) ) {
		continue;
	}
	$src = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $file ) );
	if ( preg_match( "#'/requirements|/requirements/'#", $src ) ) {
		$hardcoded[] = basename( $rel );
	}
}

wpss_t( empty( $hardcoded ), 'no gateway builds a requirements URL of its own (' . ( $hardcoded ? implode( ', ', $hardcoded ) : 'all use the helper' ) . ')' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
