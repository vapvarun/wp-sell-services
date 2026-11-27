<?php
/**
 * One REST path for every gateway (Basecamp 10551488326, payment modules phase 2, T7).
 *
 * Free's POST /payments/create-intent and /payments/confirm serve every gateway, Razorpay included.
 * Who may pay an existing order is one rule in PaymentController::confirm_payment(): only its buyer
 * (Stripe used to let an admin through, PayPal did not). Pro's per-gateway routes are aliases that
 * forward to Free's and keep no gateway logic of their own.
 *
 * Run: wp eval-file tests/test-payment-rest-one-path.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! wpss_uses_standalone_payments() ) {
	echo "SKIP  the payment routes belong to the standalone route\n";
	return;
}

$post = static function ( string $route, array $body ) {
	$req = new WP_REST_Request( 'POST', '/wpss/v1/payments/' . $route );
	$req->set_body_params( $body );
	$res = rest_do_request( $req );
	return array( $res->get_status(), (string) ( $res->as_error() ? $res->as_error()->get_error_code() : '' ), $res->get_data() );
};

$subs  = get_users( array( 'role' => 'subscriber', 'number' => 2, 'fields' => 'ID' ) );
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
if ( count( $subs ) < 2 || ! $admin ) {
	echo "SKIP  needs two subscribers and an administrator\n";
	return;
}
list( $buyer, $other ) = array_map( 'intval', $subs );

global $wpdb;
$wpdb->insert(
	$wpdb->prefix . 'wpss_orders',
	array(
		'order_number'   => 'QA-ONE-PATH',
		'customer_id'    => $buyer,
		'vendor_id'      => $admin,
		'service_id'     => 0,
		'subtotal'       => 10,
		'total'          => 10,
		'currency'       => wpss_get_currency(),
		'status'         => 'pending_payment',
		'payment_status' => 'pending',
		'platform'       => 'tip',
	)
);
$order_id = (int) $wpdb->insert_id;

// 1. One rule, before any provider is asked.
$gateways = array_intersect( array( 'stripe', 'paypal', 'razorpay' ), array_keys( wpss()->get_payment_gateways() ) );
foreach ( $gateways as $gw ) {
	wp_set_current_user( $other );
	list( $status, $code ) = $post( 'confirm', array( 'gateway' => $gw, 'payment_id' => 'qa_x', 'pay_order' => $order_id ) );
	$check( "{$gw}: another member cannot confirm a payment against this order", 403 === $status && 'wpss_not_owner' === $code, "{$status} {$code}" );
	wp_set_current_user( $admin );
	list( $status, $code ) = $post( 'confirm', array( 'gateway' => $gw, 'payment_id' => 'qa_x', 'pay_order' => $order_id ) );
	$check( "{$gw}:   nor can an admin (the web checkout's rule)", 403 === $status && 'wpss_not_owner' === $code, "{$status} {$code}" );
}
wp_set_current_user( $buyer );
list( $status, $code ) = $post( 'confirm', array( 'gateway' => 'stripe', 'payment_id' => 'qa_x', 'pay_order' => 999999999 ) );
$check( 'a missing order answers 404', 404 === $status && 'rest_order_not_found' === $code, "{$status} {$code}" );

// 2. Razorpay on Free's routes.
if ( isset( wpss()->get_payment_gateways()['razorpay'] ) ) {
	$rzp = wpss()->get_payment_gateways()['razorpay'];
	$check( 'Razorpay implements the REST legs itself', ( new ReflectionMethod( $rzp, 'rest_create_intent' ) )->getDeclaringClass()->getName() === get_class( $rzp ) && ( new ReflectionMethod( $rzp, 'rest_confirm_payment' ) )->getDeclaringClass()->getName() === get_class( $rzp ) );

	list( $status, $code ) = $post( 'confirm', array( 'gateway' => 'razorpay', 'payment_id' => 'pay_qa', 'provider_order_id' => 'order_qa', 'signature' => 'bad', 'pay_order' => $order_id ) );
	$check( 'Razorpay confirm through /payments/confirm checks the signature', 400 === $status && 'razorpay_confirm_error' === $code, "{$status} {$code}" );

	list( $status, $code ) = $post( 'razorpay/verify', array( 'razorpay_order_id' => 'order_qa', 'razorpay_payment_id' => 'pay_qa', 'razorpay_signature' => 'bad', 'pay_order' => $order_id ) );
	$check( '  the Pro alias /razorpay/verify gives the same answer', 400 === $status && 'razorpay_confirm_error' === $code, "{$status} {$code}" );
	wp_set_current_user( $other );
	list( $status, $code ) = $post( 'razorpay/verify', array( 'razorpay_order_id' => 'order_qa', 'razorpay_payment_id' => 'pay_qa', 'razorpay_signature' => 'bad', 'pay_order' => $order_id ) );
	$check( '  and the same one rule', 403 === $status && 'wpss_not_owner' === $code, "{$status} {$code}" );

	// Create: Razorpay switched on with test keys; its API answered here, never called.
	$keep = get_option( 'wpss_razorpay_settings', false );
	update_option( 'wpss_razorpay_settings', array_merge( (array) $keep, array( 'enabled' => '1', 'test_mode' => '1', 'test_key_id' => 'rzp_test_qa', 'test_key_secret' => 'qa_secret' ) ) );
	$fresh = new \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway();
	// Gateways are built once at boot, so the filter is too late: swap the cached instance.
	$list = new ReflectionProperty( wpss(), 'payment_gateways' );
	$list->setAccessible( true );
	$was  = $list->getValue( wpss() );
	$list->setValue( wpss(), array_merge( $was, array( 'razorpay' => $fresh ) ) );
	add_filter(
		'pre_http_request',
		$fake = static fn( $pre, $args, $url ) => str_contains( $url, 'api.razorpay.com' ) ? array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'id' => 'order_qa_new', 'amount' => 1000, 'currency' => 'INR' ) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		) : $pre,
		10,
		3
	);
	wp_set_current_user( $buyer );
	list( $status, $code, $data ) = $post( 'create-intent', array( 'gateway' => 'razorpay', 'pay_order' => $order_id ) );
	$supported = $fresh->supports_currency( wpss_get_currency() );
	$check( 'Razorpay create through /payments/create-intent returns its order', ! $supported || ( 200 === $status && 'order_qa_new' === ( $data['id'] ?? '' ) && 'rzp_test_qa' === ( $data['key_id'] ?? '' ) ), "{$status} {$code} " . wp_json_encode( $data ) );
	list( $status, $code, $data ) = $post( 'razorpay/create-order', array( 'pay_order' => $order_id ) );
	$check( '  and so does the Pro alias', ! $supported || ( 200 === $status && 'order_qa_new' === ( $data['id'] ?? '' ) ), "{$status} {$code}" );
	remove_filter( 'pre_http_request', $fake, 10 );
	$list->setValue( wpss(), $was );
	false === $keep ? delete_option( 'wpss_razorpay_settings' ) : update_option( 'wpss_razorpay_settings', $keep );
}
wp_set_current_user( 0 );
$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $order_id ) );

// 3. Pro's routes keep no gateway logic of their own.
$pro = WP_PLUGIN_DIR . '/wp-sell-services-pro/src/API/PaymentController.php';
if ( is_readable( $pro ) ) {
	$src = (string) file_get_contents( $pro ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$check( 'Pro PaymentController has no gateway calls or order builder of its own', ! preg_match( '/delegate_to_gateway|enabled_gateway|create_service_order|->verify_payment\(|->create_order\(/', $src ) );
	foreach ( array( 'stripe_create_intent', 'stripe_confirm', 'paypal_create_order', 'paypal_capture', 'razorpay_create_order', 'razorpay_verify', 'offline_submit' ) as $method ) {
		$body = preg_match( '/function ' . $method . '\(.*?\n\t\}/s', $src, $m ) ? $m[0] : '';
		$check( "  {$method}() forwards to Free", str_contains( $body, 'forward_to_free(' ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
