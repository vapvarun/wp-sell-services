<?php
/**
 * A charge that already paid an order is never refunded as "unsettled".
 *
 * Run: wp eval-file tests/test-refund-unsettled-guard.php
 *
 * Every gateway refunds a verified charge it cannot turn into an order. None
 * asked first whether the charge had already paid one, so a buyer could
 * re-send the confirm for a settled payment with a request that fails and be
 * refunded while the order stood (security review of 1.8.0). Stripe is
 * stubbed and refund requests are counted. Makes its own order and removes it.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntent;
use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Integrations\Stripe\StripeGateway;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table    = $wpdb->prefix . 'wpss_orders';
$currency = wpss_get_currency();
$settled  = 'pi_test_settled_' . wp_generate_password( 10, false, false );
$loose    = 'pi_test_loose_' . wp_generate_password( 10, false, false );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->insert(
	$table,
	array(
		'order_number'   => 'WPSS-UNSETTLED-' . wp_generate_password( 6, false, false ),
		'customer_id'    => 1,
		'vendor_id'      => 1,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'subtotal'       => 23.60,
		'total'          => 23.60,
		'currency'       => $currency,
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'payment_method' => 'stripe',
		'transaction_id' => $settled,
		'created_at'     => current_time( 'mysql', true ),
	)
);
$order_id = (int) $wpdb->insert_id;

$refunds = 0;
$stub    = static function ( $pre, $args, $url ) use ( &$refunds ) {
	if ( false === strpos( (string) $url, 'api.stripe.com' ) ) {
		return $pre;
	}
	$refunds += false !== strpos( (string) $url, '/refunds' ) ? 1 : 0;

	return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( array( 'id' => 're_stub', 'status' => 'succeeded', 'amount' => 2360 ) ), 'headers' => array(), 'cookies' => array() );
};
add_filter( 'pre_http_request', $stub, 10, 3 );

try {
	$service = new CheckoutIntentService();
	$called  = 0;
	$refund  = static function () use ( &$called ): bool {
		++$called;
		return true;
	};

	$check( 'a charge that paid an order is not handed to the gateway for a refund', false === $service->refund_unsettled( 'stripe', $settled, $refund ) && 0 === $called );

	// The gateway's own helper, the one every Stripe confirm path calls.
	$helper = new ReflectionMethod( StripeGateway::class, 'refund_unsettled_charge' );
	$helper->setAccessible( true );
	$helper->invoke( new StripeGateway(), $settled );
	$check( '  and Stripe is sent no refund request for it', 0 === $refunds );

	$check( 'a charge that paid nothing is refunded', true === $service->refund_unsettled( 'stripe', $loose, $refund ) && 1 === $called );

	// Given back, it cannot pay for an order afterwards.
	$intent = CheckoutIntent::order( $order_id, 23.60, $currency, 1, array() );
	$result = $service->settle( $intent, 'stripe', $loose, 23.60, $currency );
	$check( '  and cannot settle an order afterwards', empty( $result['success'] ) );

	// A refund the gateway refused leaves the charge free to settle on a retry.
	$refused = 'pi_test_refused_' . wp_generate_password( 10, false, false );
	$service->refund_unsettled( 'stripe', $refused, static fn(): bool => false );
	$check( 'a refund the gateway refused is not remembered as refunded', false === get_transient( 'wpss_unsettled_' . md5( 'stripe|' . $refused ) ) );

	// A replay of the settled charge answers with the order it paid, even
	// when the amount no longer matches what the request would price.
	$replay = $service->settle( CheckoutIntent::order( $order_id, 99.00, $currency, 1, array() ), 'stripe', $settled, 23.60, $currency );
	$check( 'a replay of a settled charge returns its order instead of failing the amount check', ! empty( $replay['success'] ) && $order_id === (int) $replay['order_id'] );
} finally {
	remove_filter( 'pre_http_request', $stub, 10 );
	$wpdb->delete( $table, array( 'id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	delete_transient( 'wpss_unsettled_' . md5( 'stripe|' . $loose ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
