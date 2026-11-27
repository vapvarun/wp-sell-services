<?php
/**
 * A webhook that failed part-way is sent again, not marked done (Basecamp 10550988500).
 *
 * PayPal and Razorpay always answered 200 and stored the dedup key, even when marking the order paid failed,
 * so the event was never retried: the buyer paid and the order stayed unpaid. Stripe's retry contract (500,
 * no dedup key) now lives in AbstractPaymentGateway::respond_to_webhook(), and settle_webhook_payment() says
 * when to retry: a failed write retries, a gone or closed order or a payment spent elsewhere is final, and a
 * payment for the wrong amount is given back (Razorpay used to ignore that).
 *
 * Run: wp eval-file tests/test-webhook-retry-on-failure.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$free = dirname( __DIR__ ) . '/src/';
$pro  = dirname( __DIR__, 2 ) . '/wp-sell-services-pro/src/';

// 1. Every gateway answers its webhook through the one responder; none stores the dedup key itself.
$callback = static function ( string $file ): string {
	$src = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	return preg_match( '/function handle_webhook_callback\(\): void \{.*?\n\t\}\n/s', $src, $m ) ? $m[0] : '';
};
foreach ( array( 'Stripe' => $free . 'Integrations/Stripe/StripeGateway.php', 'PayPal' => $free . 'Integrations/PayPal/PayPalGateway.php', 'Razorpay' => $pro . 'Integrations/Razorpay/RazorpayGateway.php' ) as $name => $file ) {
	$body = $callback( $file );
	$check( "{$name} answers through respond_to_webhook()", str_contains( $body, '$this->respond_to_webhook(' ) );
	$check( "  and does not store the dedup key before knowing it worked", '' !== $body && ! str_contains( $body, 'set_transient(' ) );
}
$check( 'Razorpay order.paid goes through settle_webhook_payment() (mismatch is given back)', str_contains( (string) @file_get_contents( $pro . 'Integrations/Razorpay/RazorpayGateway.php' ), 'return $this->settle_webhook_payment(' ) );

// 2. Behaviour of the shared settle step, on PayPal.
$gateway = wpss()->get_payment_gateways()['paypal'] ?? null;
if ( ! $gateway || ! method_exists( $gateway, 'settle_webhook_payment' ) ) {
	$check( 'AbstractPaymentGateway::settle_webhook_payment() exists', false );
	echo "\n" . ( $fails + 0 ) . " FAILED\n";
	return;
}
$settle = new ReflectionMethod( $gateway, 'settle_webhook_payment' );
$settle->setAccessible( true );

$f   = wpss_test_fixtures();
$new = static fn( array $o = array() ): int => ( $f->order )( $o + array( 'status' => 'pending_payment', 'payment_status' => 'pending', 'paid_at' => null, 'total' => 40, 'subtotal' => 40 ) );
$cur = wpss_get_currency();

// A write that fails: the order row will not take the paid update.
$a     = $new();
$break = static fn( string $q ): string => ( str_contains( $q, "{$f->orders_t}" ) && 0 === stripos( ltrim( $q ), 'UPDATE' ) && str_contains( $q, 'payment_status' ) ) ? 'SELECT qa_forced_failure FROM qa_no_such_table' : $q;
add_filter( 'query', $break );
$res = $settle->invoke( $gateway, $a, 'QA-CAP-A-' . $a, 40.0, $cur );
remove_filter( 'query', $break );
$check( 'Marking paid failed: the provider is asked to send it again (retry)', ! empty( $res['retry'] ), wp_json_encode( $res ) );

// The same event delivered again, once the database is fine: paid, once.
$res = $settle->invoke( $gateway, $a, 'QA-CAP-A-' . $a, 40.0, $cur );
$paid = $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$f->orders_t} WHERE id = %d", $a ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$check( '  the resend marks it paid', ! empty( $res['success'] ) && empty( $res['retry'] ) && 'paid' === $paid, wp_json_encode( $res ) . ' ' . $paid );

// A cancelled order is final: no retry.
$b   = $new( array( 'status' => 'cancelled' ) );
$res = $settle->invoke( $gateway, $b, 'QA-CAP-B-' . $b, 40.0, $cur );
$check( 'Cancelled order: final, not retried', empty( $res['retry'] ), wp_json_encode( $res ) );

// A payment already spent on another order is final.
$c   = $new();
$res = $settle->invoke( $gateway, $c, 'QA-CAP-A-' . $a, 40.0, $cur );
$check( 'Payment already spent on another order: final, not retried', empty( $res['retry'] ) && ! empty( $res['success'] ), wp_json_encode( $res ) );

$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => $a ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
