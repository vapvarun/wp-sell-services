<?php
/**
 * A captured Stripe charge that a pay-order confirm cannot settle is refunded (Basecamp 10415810929).
 *
 * Run: wp eval-file tests/test-pay-order-refusal-refunds.php
 *
 * The order is cancelled or repriced between the intent and the confirm, so the confirm is refused
 * after the money moved. The buyer gets it back; a payment that did pay an order, and a payment that
 * was made for a different order, are never refunded. Stripe is stubbed.
 *
 * @package WPSellServices
 */

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

$made_for = 0;
$refunds  = array();
$stub     = static function ( $pre, $args, $url ) use ( $currency, $buyer, &$made_for, &$refunds ) {
	if ( false === strpos( (string) $url, 'api.stripe.com' ) ) {
		return $pre;
	}

	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );

	if ( false !== strpos( $path, '/refunds' ) ) {
		$refunds[] = (string) ( $args['body']['payment_intent'] ?? $args['body'] ?? '' );

		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => wp_json_encode( array( 'id' => 're_test', 'status' => 'succeeded' ) ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	return array(
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body'     => wp_json_encode(
			array(
				'id'       => basename( $path ),
				'status'   => 'succeeded',
				'amount'   => wpss_amount_to_minor_units( 23.60, $currency ),
				'currency' => strtolower( $currency ),
				'metadata' => array( 'site_url' => get_option( 'home' ), 'platform' => 'wp-sell-services', 'customer_id' => $buyer->ID, 'order_id' => $made_for ),
			)
		),
		'headers'  => array(),
		'cookies'  => array(),
	);
};
add_filter( 'pre_http_request', $stub, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );

$confirm = static fn( string $payment_id, int $pay_order ) => ( new StripeGateway() )->rest_confirm_payment( array( 'payment_id' => $payment_id, 'pay_order' => $pay_order ) );
$set     = static fn( int $id, array $row ) => $wpdb->update( $table, $row, array( 'id' => $id ) );

try {
	wp_set_current_user( $buyer->ID );

	// 1. Cancelled between create-intent and confirm.
	$a        = $seed( 23.60 );
	$made_for = $a;
	$set( $a, array( 'status' => 'cancelled' ) );
	$r = $confirm( $pi_id . 'a', $a );
	$check( 'a cancelled order refuses the confirm', is_wp_error( $r ) && 'pending' === $payment_status( $a ) );
	$check( '  and the captured charge is refunded', 1 === count( $refunds ) );

	// 2. Repriced between create-intent and confirm.
	$b        = $seed( 23.60 );
	$made_for = $b;
	$set( $b, array( 'total' => 30.00, 'subtotal' => 30.00 ) );
	$r = $confirm( $pi_id . 'b', $b );
	$check( 'a repriced order refuses the confirm', is_wp_error( $r ) && 'rest_amount_mismatch' === $r->get_error_code() );
	$check( '  and the charge is refunded', 2 === count( $refunds ) );

	// 3. A good confirm, then the same payment confirmed again: nothing more is refunded.
	$c        = $seed( 23.60 );
	$made_for = $c;
	$r        = $confirm( $pi_id . 'c', $c );
	$check( 'a payment that fits pays the order and is not refunded', ! is_wp_error( $r ) && 'paid' === $payment_status( $c ) && 2 === count( $refunds ) );
	$r = $confirm( $pi_id . 'c', $c );
	$check( 'the same payment confirmed again is not refunded', ! is_wp_error( $r ) && 2 === count( $refunds ) );
	$d        = $seed( 23.60 );
	$made_for = $d;
	$r        = $confirm( $pi_id . 'c', $d );
	$check( 'a spent payment aimed at a second order is refused and not refunded', is_wp_error( $r ) && 'wpss_payment_already_used' === $r->get_error_code() && 2 === count( $refunds ) );

	// 4. Someone else's payment confirmed against my order: never refunded.
	$e        = $seed( 23.60 );
	$made_for = $c;
	$r        = $confirm( $pi_id . 'e', $e );
	$check( 'a payment made for another order is refused and not refunded', is_wp_error( $r ) && 'wpss_payment_not_for_order' === $r->get_error_code() && 2 === count( $refunds ) );

	// 5. The webhook for a charge its order refuses gives it back.
	$f        = $seed( 99.00 );
	$made_for = $f;
	( new StripeGateway() )->handle_webhook(
		array(
			'type' => 'payment_intent.succeeded',
			'data' => array(
				'object' => array(
					'id'       => $pi_id . 'f',
					'amount'   => wpss_amount_to_minor_units( 23.60, $currency ),
					'currency' => strtolower( $currency ),
					'metadata' => array( 'order_id' => $f, 'site_url' => get_option( 'home' ), 'platform' => 'wp-sell-services' ),
				),
			),
		)
	);
	$check( 'a webhook charge the order refuses (wrong amount) is refunded', 'pending' === $payment_status( $f ) && 3 === count( $refunds ) );
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

exit( $fails ? 1 : 0 );
