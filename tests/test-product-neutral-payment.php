<?php
/**
 * Payment REST + intent are product-neutral: product_id/product_type are accepted, another type is
 * refused, and the intent carries its own receipt description so gateways never look a service up.
 *
 * Run: wp eval-file tests/test-product-neutral-payment.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$service = get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 1, 'author__not_in' => array( 1 ) ) )[0] ?? null;
if ( ! $service ) {
	echo "SKIP  needs a published service\n";
	return;
}
// User 1 buys; it cannot own the service (self-purchase is refused).
$buyer = 1;
wp_set_current_user( $buyer );

$controller = new WPSellServices\API\PaymentController();
$call       = static function ( array $params ) use ( $controller ) {
	$req = new WP_REST_Request( 'POST', '/wpss/v1/payments/create-intent' );
	$req->set_body_params( $params + array( 'gateway' => 'nope', 'pay_order' => 0, 'quantity' => 1 ) );
	return $controller->create_intent( $req );
};

$r = $call( array( 'product_id' => $service->ID, 'product_type' => 'goods' ) );
$check( 'another product type is refused', is_wp_error( $r ) && 'wpss_unsupported_product_type' === $r->get_error_code() );

$r = $call( array( 'product_id' => $service->ID ) );
$check( 'product_id alone reaches the gateway lookup', is_wp_error( $r ) && 'invalid_gateway' === $r->get_error_code() );

$r = $call( array( 'service_id' => $service->ID ) );
$check( 'service_id alias still works', is_wp_error( $r ) && 'invalid_gateway' === $r->get_error_code() );

$r = $call( array() );
$check( 'no product and no pay_order is a 400', is_wp_error( $r ) && 'rest_missing_callback_param' === $r->get_error_code() );

$intent = ( new WPSellServices\Checkout\CheckoutIntentService() )->resolve( array( 'service_id' => $service->ID, 'package_id' => 0 ) );
$check( 'intent carries the service title as its description', ! is_wp_error( $intent ) && wp_strip_all_tags( get_the_title( $service ) ) === $intent->metadata['description'] );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
