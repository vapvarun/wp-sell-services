<?php
/**
 * Payment webhooks reach their gateway on every rail, and a shop's orders are refunded once (Basecamp 10550988379).
 *
 * The callback route lived in StandaloneAdapter, which runs only while standalone is the rail, so after a
 * switch to WooCommerce, EDD or FluentCart every Stripe/PayPal/Razorpay webhook for an older order 404ed (or
 * got an empty 200, which stops the provider retrying). Now Plugin registers it on every rail and answers 503
 * when nothing listens. A shop order belongs to its shop while that shop is active, and our own gateway
 * reporting the same charge as the shop is skipped, so a refund is recorded once. Once the shop is
 * deactivated nothing names it, so the order can be refunded here.
 *
 * Run: wp eval-file tests/test-payment-callback-every-rail.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb, $wp_rewrite;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$dir      = dirname( __DIR__ ) . '/src/';
$regex    = '^wpss-payment/([^/]+)/callback/?$';
$adapter  = (string) file_get_contents( $dir . 'Integrations/Standalone/StandaloneAdapter.php' );
$plugin   = (string) file_get_contents( $dir . 'Core/Plugin.php' );

// 1. The route belongs to the always-on core, not to one rail's adapter.
$check( 'The callback rule is registered by Plugin (every rail)', str_contains( $plugin, "'^wpss-payment/([^/]+)/callback/?\$'" ) );
$check( '  and no longer by StandaloneAdapter (standalone rail only)', ! str_contains( $adapter, "'^wpss-payment/" ) && ! str_contains( $adapter, "'wpss_payment_callback'" ) );
$check( '  the rule is live in this request', isset( $wp_rewrite->extra_rules_top[ $regex ] ) );
$check( '  the dispatcher is hooked on template_redirect', false !== has_action( 'template_redirect', array( wpss(), 'dispatch_payment_callback' ) ) );

// 2. Over HTTP: an unknown gateway gets 503 (provider retries), Stripe reaches Stripe's handler.
// Inside a container the site's own host name may not resolve: WPSS_QA_BASE_URL (e.g. http://localhost) replaces the scheme and host.
$probe  = static fn( string $u ): string => getenv( 'WPSS_QA_BASE_URL' ) ? (string) preg_replace( '#^https?://[^/]+#', rtrim( (string) getenv( 'WPSS_QA_BASE_URL' ), '/' ), $u ) : $u;
$none   = wp_remote_post( $probe( wpss_get_payment_callback_url( 'qa-no-such-gateway' ) ), array( 'timeout' => 20 ) );
$stripe = wp_remote_post( $probe( wpss_get_payment_callback_url( 'stripe' ) ), array( 'timeout' => 20, 'body' => '{}' ) );
$code   = static fn( $r ) => is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_response_code( $r );
$check( 'Unknown gateway answers 503, not an empty 200', '503' === $code( $none ), $code( $none ) );
$body   = is_wp_error( $stripe ) ? '' : wp_remote_retrieve_body( $stripe );
$check( 'Stripe callback reaches Stripe\'s own handler (its JSON error, not 404/503)', str_contains( $body, '"error"' ) && in_array( $code( $stripe ), array( '400', '500' ), true ), $code( $stripe ) . ' ' . substr( $body, 0, 80 ) );

// 3. A shop's order belongs to the shop only while that shop's adapter listens; once the shop is gone it is ours.
$f      = wpss_test_fixtures();
$saved  = $GLOBALS['wp_filter']['wpss_order_money_rail'] ?? null;
unset( $GLOBALS['wp_filter']['wpss_order_money_rail'] );
$rails = array();
foreach ( array( 'woocommerce', 'edd', 'fluentcart', 'standalone' ) as $p ) {
	$rails[ $p ] = wpss_order_money_rail( (object) array( 'platform' => $p ) );
}
if ( $saved ) {
	$GLOBALS['wp_filter']['wpss_order_money_rail'] = $saved;
}
$check( 'Shop deactivated: its WooCommerce order is ours to refund (no rail named)', '' === $rails['woocommerce'], $rails['woocommerce'] );
$check( '  EDD and FluentCart orders too', '' === $rails['edd'] && '' === $rails['fluentcart'] );
$check( '  a standalone order stays ours', '' === $rails['standalone'] );

// 4. The same refund reported by our Stripe and by WooCommerce is recorded once, while the shop is active
// (stood in for by the adapter's own callback shape, as Pro's register_order_sync() adds it).
$woo_active = static fn( string $rail, object $o ): string => '' === $rail && 'woocommerce' === ( $o->platform ?? '' ) ? 'WooCommerce' : $rail;
add_filter( 'wpss_order_money_rail', $woo_active, 10, 2 );
$txn   = 'ch_qa_988379_' . wp_generate_password( 6, false );
$order = ( $f->order )(
	array(
		'platform'       => 'woocommerce',
		'payment_method' => 'stripe',
		'transaction_id' => $txn,
		'status'         => 'completed',
	)
);
$refunded = static fn() => (float) $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM {$f->orders_t} WHERE id = %d", $order ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

do_action( 'wpss_gateway_refund_received', 'stripe', $txn, 10.0, array( 'refund_id' => 're_qa_' . $order ) );
$check( 'Our Stripe reporting a WooCommerce order: nothing recorded', 0.0 === $refunded(), (string) $refunded() );

do_action( 'wpss_gateway_refund_received', 'woocommerce', $txn, 10.0, array( 'order_id' => $order, 'refund_id' => 'wc_qa_' . $order ) );
$check( 'WooCommerce reporting it: recorded once (10)', 10.0 === $refunded(), (string) $refunded() );
remove_filter( 'wpss_order_money_rail', $woo_active, 10 );

$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => $order ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
