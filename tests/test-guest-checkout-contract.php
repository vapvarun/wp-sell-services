<?php
/**
 * Guest checkout contract.
 *
 * A logged-out buyer pressing "Continue to Checkout" used to land on
 * wp-login.php with redirect_to pointing at the SERVICE page, throwing away the
 * package and add-ons they had just picked. They now reach the single-service
 * checkout with the selection intact, and the account is created there from the
 * billing fields (wpss_checkout_creates_accounts).
 *
 * The failure this pins is silent: gateways that own their own submit (Stripe,
 * PayPal) return from the generic handler BEFORE the account step. None of
 * their AJAX actions register a nopriv handler, so a guest hitting them gets a
 * bare "0" back and checkout dead-ends with no error anyone would notice in
 * code review.
 *
 * Run: wp eval-file tests/test-guest-checkout-contract.php
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

$free = dirname( __DIR__ );

echo "\nGuest checkout contract\n\n";

// 1. The URL must carry the selection. This is the whole point of the card:
//    package 2 chosen on the service page must still be package 2 at checkout.
$service = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
if ( $service ) {
	$url = wpss_get_service_checkout_url( (int) $service[0], 2, array( 1, 3 ) );
	wpss_t( false !== strpos( $url, (string) $service[0] ), 'checkout URL carries the service id' );
	wpss_t( '2' === (string) ( wp_parse_args( wp_parse_url( $url, PHP_URL_QUERY ) )['package'] ?? '' ), 'checkout URL preserves the chosen package (not the default)' );
	wpss_t( false !== strpos( $url, 'addons=1%2C3' ) || false !== strpos( $url, 'addons=1,3' ), 'checkout URL preserves add-ons' );
}

// 2. The guest gate must not send anyone to the service page any more.
$ajax = file_get_contents( $free . '/src/Frontend/AjaxHandlers.php' );
wpss_t(
	false !== strpos( $ajax, 'handle_guest_checkout_intent' ),
	'add_service_to_cart delegates the guest case to one place'
);
// The referer survives only as a last resort. What must never happen again is
// the referer being the FIRST choice, which is what dropped the selection.
$guest_fn = substr( $ajax, strpos( $ajax, 'private function handle_guest_checkout_intent' ) );
$guest_fn = substr( $guest_fn, 0, strpos( $guest_fn, "\n\t}\n" ) );
wpss_t(
	strpos( $guest_fn, '$checkout_url ?: ( wp_get_referer()' ) !== false,
	'the login redirect prefers the checkout URL and falls back to the referer, not the reverse'
);
wpss_t(
	strpos( $guest_fn, "check_ajax_referer( 'wpss_service_nonce', 'nonce' )" ) !== false,
	'the guest path still verifies the nonce before trusting any posted selection'
);

// 3. The account seam is published at IIFE scope, NOT inside the submit
//    handler - that handler returns early for exactly the gateways that need
//    it, so publishing from in there would define it only for buyers who don't.
$checkout = file_get_contents( $free . '/src/Integrations/Standalone/StandaloneCheckoutProvider.php' );
$seam_pos = strpos( $checkout, 'window.wpssEnsureCheckoutAccount = ensureAccount;' );
$call_pos = strpos( $checkout, "ensureAccount().then(function() {" );
wpss_t( false !== $seam_pos, 'checkout publishes window.wpssEnsureCheckoutAccount' );
wpss_t(
	false !== $seam_pos && false !== $call_pos && $seam_pos < $call_pos,
	'the seam is published before the submit handler runs it'
);

// 4. Both own-submit gateways await it. Without this they charge - or fail -
//    with no account behind the order.
foreach ( array( 'stripe', 'paypal' ) as $gw ) {
	foreach ( array( '.js', '.min.js' ) as $ext ) {
		$f = $free . '/assets/js/' . $gw . $ext;
		if ( ! file_exists( $f ) ) {
			continue;
		}
		wpss_t(
			false !== strpos( file_get_contents( $f ), 'wpssEnsureCheckoutAccount' ),
			sprintf( '%s%s awaits the account seam', $gw, $ext )
		);
	}
}

// 5. Why 4 matters: neither gateway answers a logged-out request at all. If
//    this ever changes the seam stops being load-bearing - but until then, a
//    guest reaching these actions without an account gets a bare "0".
foreach ( array( 'wpss_stripe_create_payment_intent', 'wpss_paypal_create_order' ) as $action ) {
	wpss_t(
		! has_action( 'wp_ajax_nopriv_' . $action ),
		sprintf( '%s has no nopriv handler, so the account must exist first', $action )
	);
}

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
