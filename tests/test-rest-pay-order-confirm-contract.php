<?php
/**
 * One gateway payment pays one order on the REST pay-order confirm.
 *
 * Run: wp eval-file tests/test-rest-pay-order-confirm-contract.php
 *
 * POST wpss/v1/payments/confirm with pay_order checked owner, unpaid status
 * and amount, then called mark_as_paid() itself - never asking whether the
 * payment had already paid something. A buyer with two equal-priced orders
 * (milestone phases, repeat tips) paid once and confirmed the same payment
 * against both (Basecamp 10375173905). Stripe is stubbed; the test makes its
 * own buyer, seller and orders and removes them.
 *
 * @package WPSellServices
 */

use WPSellServices\API\PaymentController;
use WPSellServices\Integrations\Stripe\StripeGateway;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table    = $wpdb->prefix . 'wpss_orders';
$suffix   = wp_generate_password( 6, false, false );
$buyer    = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_payorder_buyer_' . $suffix, 'user_email' => 'wpss_payorder_buyer_' . $suffix . '@example.test' ) );
$seller   = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_payorder_seller_' . $suffix, 'user_email' => 'wpss_payorder_seller_' . $suffix . '@example.test' ) );
$currency = wpss_get_currency();
$pi_id    = 'pi_test_payorder_' . wp_generate_password( 10, false, false );
$made     = array();

$seed = static function ( float $total ) use ( $wpdb, $table, $buyer, $seller, $currency, $suffix, &$made ): int {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		$table,
		array(
			'order_number'   => 'WPSS-PAYORDER-' . $suffix . '-' . count( $made ),
			'customer_id'    => $buyer->ID,
			'vendor_id'      => $seller->ID,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'subtotal'       => $total,
			'total'          => $total,
			'currency'       => $currency,
			'status'         => 'pending_payment',
			'payment_status' => 'pending',
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$made[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$payment_status = static function ( int $id ) use ( $wpdb, $table ): string {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$table} WHERE id = %d", $id ) );
};

// Stripe answers "succeeded, 23.60, made on this site" for any intent.
$stub = static function ( $pre, $args, $url ) use ( $currency ) {
	if ( false === strpos( (string) $url, 'api.stripe.com' ) ) {
		return $pre;
	}

	return array(
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body'     => wp_json_encode(
			array(
				'id'       => basename( (string) wp_parse_url( (string) $url, PHP_URL_PATH ) ),
				'status'   => 'succeeded',
				'amount'   => wpss_amount_to_minor_units( 23.60, $currency ),
				'currency' => strtolower( $currency ),
				'metadata' => array( 'site_url' => get_option( 'home' ), 'platform' => 'wp-sell-services' ),
			)
		),
		'headers'  => array(),
		'cookies'  => array(),
	);
};
add_filter( 'pre_http_request', $stub, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );

// The route hands straight to this method; calling it keeps the test off the
// site's own gateway settings, so it runs on a site with no Stripe keys too.
$confirm = static function ( string $payment_id, int $pay_order ) {
	$method = new ReflectionMethod( PaymentController::class, 'confirm_stripe_payment' );
	return $method->invoke( new PaymentController(), new StripeGateway(), $payment_id, 0, 0, $pay_order );
};

$paid_fired = 0;
add_action(
	'wpss_order_paid',
	static function () use ( &$paid_fired ) {
		++$paid_fired;
	}
);

try {
	$first  = $seed( 23.60 );
	$second = $seed( 23.60 );
	$dearer = $seed( 99.00 );

	wp_set_current_user( $buyer->ID );

	$r = $confirm( $pi_id, $first );
	$check( 'a payment confirms the order it paid', ! is_wp_error( $r ) && 'paid' === $payment_status( $first ) );

	$r = $confirm( $pi_id, $second );
	$check( 'the same payment is refused for a second order of the same price', is_wp_error( $r ) );
	$check( '  and that order stays unpaid', 'pending' === $payment_status( $second ) );
	$check( '  and wpss_order_paid fired once', 1 === $paid_fired );

	// The webhook for that payment names the order it was CREATED for. If the
	// buyer confirmed it against a different order, the webhook must not pay
	// the named one as well.
	( new StripeGateway() )->handle_webhook(
		array(
			'type' => 'payment_intent.succeeded',
			'data' => array(
				'object' => array(
					'id'       => $pi_id,
					'amount'   => wpss_amount_to_minor_units( 23.60, $currency ),
					'currency' => strtolower( $currency ),
					'metadata' => array( 'order_id' => $second, 'site_url' => get_option( 'home' ), 'platform' => 'wp-sell-services' ),
				),
			),
		)
	);
	$check( 'a webhook naming another order does not pay it with a spent payment', 'pending' === $payment_status( $second ) && 1 === $paid_fired );

	$r = $confirm( $pi_id, $first );
	$check( 'repeating the confirm for the order it paid still answers paid', ! is_wp_error( $r ) && 'paid' === ( $r->get_data()['status'] ?? '' ) );

	$r = $confirm( $pi_id . 'b', $dearer );
	$check( 'a payment for less than the order total is refused', is_wp_error( $r ) && 'pending' === $payment_status( $dearer ) );

	( new StripeGateway() )->handle_webhook(
		array(
			'type' => 'payment_intent.succeeded',
			'data' => array(
				'object' => array(
					'id'       => $pi_id . 'd',
					'amount'   => wpss_amount_to_minor_units( 23.60, $currency ),
					'currency' => strtolower( $currency ),
					'metadata' => array( 'order_id' => $dearer, 'site_url' => get_option( 'home' ), 'platform' => 'wp-sell-services' ),
				),
			),
		)
	);
	$check( 'a webhook for less than the order total does not pay it', 'pending' === $payment_status( $dearer ) );

	wp_set_current_user( $seller->ID );
	$r = $confirm( $pi_id . 'c', $second );
	$check( 'someone else cannot pay the order', is_wp_error( $r ) && 'pending' === $payment_status( $second ) );
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_http_request', $stub, 10 );
	remove_filter( 'pre_wp_mail', '__return_true' );

	foreach ( $made as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => (int) $id ) );
	}

	\WPSellServices\Tests\Factories\UserFactory::cleanup();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
