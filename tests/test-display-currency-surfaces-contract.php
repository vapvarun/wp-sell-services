<?php
/**
 * Display-currency surface coverage contract.
 *
 * Pro's switcher converts prices in the browser by reading data-wbcom-amount,
 * which only wpss_catalog_price_html() emits. Two templates called it; every
 * other price called wpss_format_price() directly and could never be
 * converted, so a shopper who switched currency saw the hint on a service card
 * and base currency everywhere else (Basecamp 10213237263).
 *
 * This asserts the SURFACES, not a count - a count goes stale the moment
 * somebody adds a price, and the thing that matters is which pages a buyer
 * sees a convertible price on.
 *
 * Run: wp eval-file tests/test-display-currency-surfaces-contract.php
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

echo "\nDisplay-currency surface coverage contract\n\n";

// 1. The wrapper must carry the amount the JS reads, and the base price the
//    shopper is actually charged. Losing either breaks the feature silently.
$html = wpss_catalog_price_html( 1234.5, 'test' );
wpss_t( false !== strpos( $html, 'data-wbcom-amount="1234.5000"' ), 'the wrapper emits the raw amount for the browser converter' );
wpss_t( false !== strpos( $html, wpss_format_price( 1234.5 ) ), 'the wrapper still shows the base price the buyer is charged' );

// 2. The currency parameter. Without it, swapping a call site that passed an
//    order's own currency would silently relabel it in store currency.
$eur = wpss_catalog_price_html( 100.0, 'test', 'EUR' );
$usd = wpss_catalog_price_html( 100.0, 'test', 'USD' );
wpss_t( $eur !== $usd, 'the wrapper honours an explicit currency instead of assuming the store one' );
wpss_t(
	false !== strpos( $eur, wpss_format_price( 100.0, 'EUR' ) ),
	'an amount in EUR renders as EUR, matching what wpss_format_price would have said'
);

// 3. Buyer-facing surfaces. Asserted against the templates, because the
//    failure mode is a template quietly going back to wpss_format_price().
$surfaces = array(
	'request card budget'      => 'templates/content-request-card.php',
	'single request budget'    => 'templates/single-request.php',
	'dashboard my requests'    => 'templates/dashboard/sections/requests.php',
	'cart lines and totals'    => 'templates/cart/cart.php',
	'order confirmation'       => 'templates/order/order-confirmation.php',
	'service card'             => 'templates/content-service-card.php',
	'package tiers'            => 'templates/partials/service-packages.php',
);

foreach ( $surfaces as $label => $file ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . $file );
	wpss_t( false !== strpos( $src, 'wpss_catalog_price_html(' ), "{$label} emits a convertible price" );
}

// 4. Surfaces that must NOT convert. These show money that has already moved,
//    or is owed to a vendor in the store's own currency - a converted hint
//    there reads as though the amount itself changed.
$base_only = array(
	'vendor earnings' => 'templates/dashboard/sections/earnings.php',
	'vendor sales'    => 'templates/dashboard/sections/sales.php',
	'order emails'    => 'templates/emails/order-completed.php',
	'tip email'       => 'templates/emails/tip-received.php',
);

foreach ( $base_only as $label => $file ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . $file );
	wpss_t( false === strpos( $src, 'wpss_catalog_price_html(' ), "{$label} stays in base currency" );
}

// 5. Escaping. Every converted surface now emits HTML, so a site still running
//    esc_html() on it would print the markup as visible text.
$escaped = array();
foreach ( array( 'templates/content-request-card.php', 'templates/single-request.php', 'templates/cart/cart.php', 'templates/order/order-confirmation.php', 'templates/dashboard/sections/requests.php' ) as $file ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . $file );
	if ( preg_match( '/esc_html\(\s*wpss_catalog_price_html\(/', $src ) ) {
		$escaped[] = $file;
	}
}
wpss_t( empty( $escaped ), 'no surface escapes the price markup into visible text (' . ( $escaped ? implode( ', ', $escaped ) : 'none' ) . ')' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
