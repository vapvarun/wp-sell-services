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

// Route: the finish legs register on every route, the start legs only on ours (QA bounce of 10550988413).
$routes = static function ( string $file ): array {
	$src  = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	$body = preg_match( '/function register_routes\(\): void \{(.*?)\n\t\}\n/s', $src, $m ) ? $m[1] : '';
	$gate = strpos( $body, 'wpss_uses_standalone_payments()' );
	return array( $body, false === $gate ? -1 : $gate );
};
foreach ( array( array( $free . 'API/PaymentController.php', array( '/confirm' ), array( '/methods', '/create-intent' ) ), array( $pro . 'API/PaymentController.php', array( '/stripe/confirm', '/paypal/capture', '/razorpay/verify' ), array( '/stripe/create-intent', '/paypal/create-order', '/razorpay/create-order', '/offline/submit' ) ) ) as list( $file, $finish_routes, $start_routes ) ) {
	list( $b, $gate ) = $routes( $file );
	foreach ( $finish_routes as $r ) {
		$at = strpos( $b, "rest_base . '{$r}'" );
		$check( basename( dirname( $file, 3 ) ) . " {$r} registers on every route (before the route gate)", false !== $at && $gate > $at );
	}
	foreach ( $start_routes as $r ) {
		$at = strpos( $b, "rest_base . '{$r}'" );
		$check( "  {$r} only on our route (after the gate)", false !== $at && $gate > -1 && $at > $gate );
	}
}
$check( 'Start helper refuses while a shop plugin takes payment', str_contains( $body( $free . 'functions/payments.php', 'wpss_gateway_require_enabled' ), 'wpss_uses_standalone_payments() &&' ) );
$rz = (string) @file_get_contents( $pro . 'Integrations/Razorpay/RazorpayGateway.php' );
$check( 'Razorpay verify AJAX is hooked on every route', (bool) preg_match( "/\n\t\tadd_action\( 'wp_ajax_wpss_razorpay_verify_payment'/", $rz ) );

// Live: pretend a shop plugin took over payment, then rebuild the REST server.
$manager = wpss()->get_integration_manager();
$prop    = new ReflectionProperty( $manager, 'active_adapter' );
$prop->setAccessible( true );
$was     = $prop->getValue( $manager );
$shop    = new class() extends \WPSellServices\Integrations\Standalone\StandaloneAdapter {
	public function get_id(): string {
		return 'woocommerce';
	}
};
$prop->setValue( $manager, $shop );
$GLOBALS['wp_rest_server'] = null;
$live                      = rest_get_server()->get_routes();
$check( 'Live, shop route: our gateways are not the route', ! wpss_uses_standalone_payments() );
$check( '  POST /payments/confirm still answers', isset( $live['/wpss/v1/payments/confirm'] ) );
$check( '  POST /payments/create-intent does not', ! isset( $live['/wpss/v1/payments/create-intent'] ) );
$prop->setValue( $manager, $was );
$GLOBALS['wp_rest_server'] = null;
rest_get_server();

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
