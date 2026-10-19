<?php
/**
 * Razorpay settle-path contract.
 *
 * Razorpay used to settle a payment two ways: the AJAX path went through
 * CheckoutIntentService (add-ons, tax, pay-order, cart) while the REST
 * /payments/razorpay/verify route hand-rolled create_order() from the gateway
 * amount alone. On top of that, payment.captured and order.paid each called
 * mark_as_paid() under their own dedup key, and the AJAX handlers registered
 * on every rail while the REST routes were standalone-only.
 *
 * This script asserts the three invariants that close that card:
 *   1. REST and AJAX verify share ONE private settle method.
 *   2. Only order.paid marks an order paid; payment.captured is acknowledged.
 *   3. The AJAX actions exist only on the standalone rail.
 *
 * Run: wp eval-file tests/pro/test-razorpay-settle-path.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

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

/**
 * Source text of one RazorpayGateway method, as loaded on this site.
 *
 * @param string $method Method name.
 * @return string Empty when the method does not exist.
 */
function wpss_rzp_method_source( string $method ): string {
	if ( ! method_exists( \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway::class, $method ) ) {
		return '';
	}
	$ref   = new \ReflectionMethod( \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway::class, $method );
	$lines = file( $ref->getFileName() );
	return implode( '', array_slice( $lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 ) );
}

echo "\nRazorpay settle-path contract\n\n";

$gateway_class = \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway::class;

// 1. One settle method behind both entry points.
$shared = 'settle_verified_payment';
wpss_t( method_exists( $gateway_class, $shared ), "RazorpayGateway::{$shared}() exists" );

$rest_src = wpss_rzp_method_source( 'verify_payment' );
$ajax_src = wpss_rzp_method_source( 'ajax_verify_payment' );
wpss_t( false !== strpos( $rest_src, $shared . '(' ), "REST verify_payment() calls {$shared}()" );
wpss_t( false !== strpos( $ajax_src, $shared . '(' ), "AJAX ajax_verify_payment() calls {$shared}()" );
wpss_t(
	false === strpos( $rest_src, 'create_order(' ) && false === strpos( $rest_src, 'mark_as_paid(' ),
	'REST verify_payment() no longer creates or marks the order itself'
);
wpss_t(
	false !== strpos( wpss_rzp_method_source( $shared ), '->settle(' ),
	"{$shared}() settles through CheckoutIntentService::settle()"
);

// 2. One webhook event marks the order paid.
$gateway = new $gateway_class();
$result  = $gateway->handle_webhook(
	array(
		'event'   => 'payment.captured',
		'payload' => array(
			'payment' => array(
				'entity' => array(
					'id'    => 'pay_contract_test',
					'notes' => array( 'order_id' => 999999999 ),
				),
			),
		),
	)
);
wpss_t(
	! empty( $result['success'] ) && false !== stripos( (string) ( $result['message'] ?? '' ), 'not handled' ),
	'payment.captured is acknowledged and ignored'
);
wpss_t( ! method_exists( $gateway_class, 'handle_payment_captured' ), 'the payment.captured handler is gone' );
wpss_t( method_exists( $gateway_class, 'handle_order_paid' ), 'order.paid still has its handler' );

// 3. AJAX handlers exist only on the standalone rail.
//
// wpss_uses_standalone_payments() reads the adapter IntegrationManager picked
// at boot from wpss_general[ecommerce_platform], so a rail change is only
// visible to a fresh process. Flip the option, ask a child WP-CLI process,
// restore - the finally block runs even if the child throws.
$probe = 'echo has_action( "wp_ajax_wpss_razorpay_create_order" ) && has_action( "wp_ajax_wpss_razorpay_verify_payment" ) ? "registered" : "absent";';

$general  = get_option( 'wpss_general', array() );
$general  = is_array( $general ) ? $general : array();
$original = $general['ecommerce_platform'] ?? 'auto';

$non_standalone = '';
foreach ( array( 'woocommerce', 'edd', 'fluentcart' ) as $candidate ) {
	$adapter = wpss_get_ecommerce_adapter( $candidate );
	if ( $adapter && $adapter->is_active() ) {
		$non_standalone = $candidate;
		break;
	}
}

if ( ! $non_standalone ) {
	echo "  SKIP  no non-standalone rail (WooCommerce/EDD/FluentCart) is installed to switch to.\n";
} elseif ( ! class_exists( 'WP_CLI' ) ) {
	echo "  SKIP  run via WP-CLI to probe a fresh process.\n";
} else {
	try {
		$general['ecommerce_platform'] = 'standalone';
		update_option( 'wpss_general', $general );
		$on_standalone = trim( (string) WP_CLI::runcommand( 'eval ' . escapeshellarg( $probe ), array( 'launch' => true, 'return' => true, 'exit_error' => false ) ) );
		wpss_t( 'registered' === $on_standalone, "AJAX actions registered on the standalone rail (got '{$on_standalone}')" );

		$general['ecommerce_platform'] = $non_standalone;
		update_option( 'wpss_general', $general );
		$on_other = trim( (string) WP_CLI::runcommand( 'eval ' . escapeshellarg( $probe ), array( 'launch' => true, 'return' => true, 'exit_error' => false ) ) );
		wpss_t( 'absent' === $on_other, "AJAX actions absent on the {$non_standalone} rail (got '{$on_other}')" );
	} finally {
		$general['ecommerce_platform'] = $original;
		update_option( 'wpss_general', $general );
	}
}

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
