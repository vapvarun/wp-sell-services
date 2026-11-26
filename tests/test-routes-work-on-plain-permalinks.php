<?php
/**
 * Plugin routes still work with Plain permalinks (Basecamp 10550842296).
 *
 * The service checkout link was always /checkout/{id}/, which only a rewrite rule serves, so on a
 * site with Plain permalinks "Continue" landed on the blog home. The same was true of the webhook
 * URLs the gateway settings tell owners to paste, and the order / requirements / vendor fallbacks.
 * Each is checked under Plain: it must be a query on the home URL that WordPress parses back
 * into the route's query vars. Pretty permalinks keep the pretty path.
 *
 * Run: wp eval-file tests/test-routes-work-on-plain-permalinks.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// What WordPress would set as query vars for this URL, through its own request parser.
$parse = static function ( string $url ): array {
	$parts = wp_parse_url( $url );
	if ( untrailingslashit( (string) ( $parts['path'] ?? '' ) ) !== untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) ) {
		return array( '__not_home__' => $parts['path'] ?? '' );
	}
	parse_str( (string) ( $parts['query'] ?? '' ), $get );
	$saved_get    = $_GET;
	$_GET         = $get; // phpcs:ignore WordPress.Security.NonceVerification
	$wp           = new WP();
	$wp->parse_request();
	$_GET         = $saved_get;
	return $wp->query_vars;
};

$service = (int) get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
$vendor  = get_userdata( (int) get_post_field( 'post_author', $service ) );

$plain = static fn() => '';
add_filter( 'pre_option_permalink_structure', $plain );
try {
	$checkout = ( new StandaloneCheckoutProvider() )->get_checkout_url( $service, array( 'package_id' => 0 ) );
	$vars     = $parse( $checkout );
	$check( 'Plain: the service checkout link is served by the checkout handler', '1' === (string) ( $vars['wpss_checkout'] ?? '' ) && (string) $service === (string) ( $vars['wpss_service_id'] ?? '' ), $checkout );
	$check( '  and keeps the chosen package', false !== strpos( $checkout, 'package=0' ), $checkout );

	foreach ( array( 'stripe', 'paypal', 'razorpay' ) as $gateway ) {
		$url  = wpss_get_payment_callback_url( $gateway );
		$vars = $parse( $url );
		$check( "Plain: the {$gateway} webhook URL reaches the payment callback", '1' === (string) ( $vars['wpss_payment_callback'] ?? '' ) && $gateway === ( $vars['wpss_gateway'] ?? '' ), $url );
	}

	$route = wpss_route_url( 'service-order/7/requirements/', array( 'wpss_service_order' => 7, 'wpss_order_action' => 'requirements' ) );
	$vars  = $parse( $route );
	$check( 'Plain: an order route parses back to its query vars', '7' === (string) ( $vars['wpss_service_order'] ?? '' ) && 'requirements' === ( $vars['wpss_order_action'] ?? '' ), $route );

	$route = wpss_route_url( 'provider/' . $vendor->user_nicename, array( 'wpss_vendor' => $vendor->user_nicename ) );
	$check( 'Plain: a vendor route parses back to its query var', $vendor->user_nicename === ( $parse( $route )['wpss_vendor'] ?? '' ), $route );
} finally {
	remove_filter( 'pre_option_permalink_structure', $plain );
}

$pretty = static fn() => '/%postname%/';
add_filter( 'pre_option_permalink_structure', $pretty );
try {
	$checkout = ( new StandaloneCheckoutProvider() )->get_checkout_url( $service );
	$check( 'Pretty: the checkout link keeps its pretty path', 0 === strpos( $checkout, home_url( '/' . \WPSellServices\Integrations\Standalone\StandaloneAdapter::get_checkout_slug() . '/' . $service . '/' ) ), $checkout );
	$check( 'Pretty: the Stripe webhook URL is unchanged', home_url( '/wpss-payment/stripe/callback/' ) === wpss_get_payment_callback_url( 'stripe' ), wpss_get_payment_callback_url( 'stripe' ) );
} finally {
	remove_filter( 'pre_option_permalink_structure', $pretty );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
