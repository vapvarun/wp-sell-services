<?php
/**
 * A payment the buyer already made finishes even if the gateway was switched off meanwhile (Basecamp 10550988413).
 *
 * "Enabled" was checked on the legs that finish a payment too: Stripe confirm, PayPal capture, Razorpay
 * verify and the REST confirm (through PaymentController::get_gateway()). An owner turning Stripe off while a
 * buyer was paying left a charged card with no order. Now only the start legs check it.
 *
 * Run: wp eval-file tests/test-finish-payment-on-disabled-gateway.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$free = dirname( __DIR__ ) . '/src/';
$pro  = dirname( __DIR__, 2 ) . '/wp-sell-services-pro/src/';

// The body of one method, to see which legs call the gate.
$body = static function ( string $file, string $method ): string {
	$src = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	return preg_match( '/function ' . $method . '\(.*?\n\t\}\n/s', $src, $m ) ? $m[0] : '';
};

$legs = array(
	array( 'Stripe', $free . 'Integrations/Stripe/StripeGateway.php', 'ajax_create_payment_intent', 'ajax_confirm_payment' ),
	array( 'PayPal', $free . 'Integrations/PayPal/PayPalGateway.php', 'ajax_create_order', 'ajax_capture_order' ),
	array( 'Razorpay', $pro . 'Integrations/Razorpay/RazorpayGateway.php', 'ajax_create_order', 'ajax_verify_payment' ),
);
foreach ( $legs as list( $name, $file, $start, $finish ) ) {
	$s = $body( $file, $start );
	$f = $body( $file, $finish );
	$check( "{$name}: starting a payment refuses a disabled gateway", str_contains( $s, 'wpss_gateway_require_enabled(' ) );
	$check( "  finishing one ({$finish}) does not", '' !== $f && ! str_contains( $f, 'wpss_gateway_require_enabled(' ) );
}

// Live: call the finish handlers on disabled gateways and see they get past the gate.
$f        = wpss_test_fixtures();
$gateways = wpss()->get_payment_gateways();
$die      = static fn() => static function () {
	throw new RuntimeException( 'done' );
};
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', $die );
add_filter( 'wp_die_handler', $die );
wp_set_current_user( $f->buyer );

$ajax = static function ( object $gateway, string $method, array $post ): string {
	$_POST    = $post;
	$_REQUEST = $post;
	ob_start();
	try {
		$gateway->$method();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	return (string) ob_get_clean();
};

try {
	foreach ( array( 'stripe' => array( 'ajax_create_payment_intent', 'ajax_confirm_payment', 'wpss_stripe', array( 'payment_intent_id' => 'pi_qa_none' ) ), 'paypal' => array( 'ajax_create_order', 'ajax_capture_order', 'wpss_paypal', array( 'paypal_order_id' => 'QA-NONE' ) ) ) as $id => list( $start, $finish, $action, $extra ) ) {
		$gateway = $gateways[ $id ] ?? null;
		if ( ! $gateway || $gateway->is_enabled() ) {
			echo "SKIP  live {$id} (needs it registered and disabled)\n";
			continue;
		}
		$post = array( 'nonce' => wp_create_nonce( $action ), 'service_id' => 0 ) + $extra;
		$out  = $ajax( $gateway, $start, $post );
		$check( "Live {$id}: start on a disabled gateway is refused", str_contains( $out, 'wpss_gateway_disabled' ), substr( $out, 0, 120 ) );
		$out = $ajax( $gateway, $finish, $post );
		$check( "  finish ({$finish}) goes past the gate", '' !== $out && ! str_contains( $out, 'wpss_gateway_disabled' ), substr( $out, 0, 120 ) );
	}

	// REST: confirm finds a disabled gateway; creating an intent does not.
	if ( wpss_uses_standalone_payments() && isset( $gateways['stripe'] ) && ! $gateways['stripe']->is_enabled() ) {
		$req     = new WP_REST_Request( 'POST', '/wpss/v1/payments/confirm' );
		$req->set_body_params( array( 'gateway' => 'stripe', 'payment_id' => 'pi_qa_none', 'service_id' => 1 ) );
		$confirm = rest_do_request( $req );
		$code    = $confirm->is_error() ? (string) $confirm->as_error()->get_error_code() : 'ok';
		$check( 'REST confirm on disabled Stripe is not refused as invalid_gateway', 'invalid_gateway' !== $code, $code );
	}
} finally {
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_die_handler', $die );
	$_POST    = array();
	$_REQUEST = array();
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
