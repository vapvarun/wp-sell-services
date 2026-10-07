<?php
/**
 * A Stripe webhook that arrives before the browser builds the order the way
 * checkout does.
 *
 * Run: wp eval-file tests/test-stripe-webhook-recovery.php
 *
 * The webhook's recovery path created the order from the charged amount, which
 * already includes tax, so tax was added a second time and add-ons were lost -
 * found in the 1.8.0 sandbox run, where two of five orders came out at 27.848
 * for a 23.60 charge because the webhook beat the browser. It now resolves and
 * settles through CheckoutIntentService. Makes its own buyer, seller and service;
 * the orders it creates are deleted at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Stripe\StripeGateway;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

// Own fixtures, so the test runs on any site, CI included (it used to skip
// anywhere but the dev site that had service 6205 and dev_f1_rev_buyer).
require_once __DIR__ . '/Factories/UserFactory.php';
require_once __DIR__ . '/Factories/ServiceFactory.php';
$suffix     = wp_generate_password( 6, false, false );
$buyer      = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_recovery_buyer_' . $suffix, 'user_email' => 'wpss_recovery_buyer_' . $suffix . '@example.test' ) );
$seller     = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_recovery_seller_' . $suffix, 'user_email' => 'wpss_recovery_seller_' . $suffix . '@example.test' ) );
$made_svc   = \WPSellServices\Tests\Factories\ServiceFactory::single_plan( array( 'vendor_id' => $seller->ID ) );
$service_id = is_object( $made_svc ) ? (int) $made_svc->id : 0;
register_shutdown_function( array( \WPSellServices\Tests\Factories\ServiceFactory::class, 'cleanup' ) );

// A site with moderation on queues the fixture; approve it the way an admin would.
if ( $service_id && 'publish' !== get_post_status( $service_id ) ) {
	wp_set_current_user( 1 );
	( new \WPSellServices\Services\ModerationService() )->approve( $service_id );
	wp_set_current_user( 0 );
	clean_post_cache( $service_id );
}

if ( ! $service_id || 'publish' !== get_post_status( $service_id ) ) {
	echo "FAIL  could not create a published fixture service\n";
	\WPSellServices\Tests\Factories\ServiceFactory::cleanup();
	\WPSellServices\Tests\Factories\UserFactory::cleanup();
	exit( 1 );
}

// Tax on, exclusive, for this run: the double-count check below means nothing
// at 0% (a fresh install, CI included, ships with tax off).
add_filter(
	'option_wpss_tax',
	static fn ( $o ) => array_merge(
		(array) $o,
		array(
			'enable_tax'   => true,
			'tax_rate'     => 10,
			'tax_included' => false,
		)
	)
);

$package = (array) ( get_post_meta( $service_id, '_wpss_packages', true )[0] ?? array() );
$line    = \WPSellServices\Checkout\CheckoutIntentService::price_service_line( $service_id, (int) ( $package['id'] ?? 0 ), 1, array() );
$charged = (float) $line['total'];
$health  = get_option( 'wpss_stripe_webhook_health' );
$table   = $wpdb->prefix . 'wpss_orders';
$pi_id   = 'pi_test_recovery_' . wp_generate_password( 10, false );
$made    = array();

$event = static function ( string $id, float $amount, array $meta = array() ) use ( $buyer, $package, $service_id ) {
	return array(
		'type' => 'payment_intent.succeeded',
		'data' => array(
			'object' => array(
				'id'              => $id,
				'amount'          => (int) round( $amount * 100 ),
				'amount_received' => (int) round( $amount * 100 ),
				'currency'        => strtolower( wpss_get_currency() ),
				'metadata'        => $meta + array(
					'service_id'  => $service_id,
					'package_id'  => (int) ( $package['id'] ?? 0 ),
					'quantity'    => 1,
					'customer_id' => $buyer->ID,
					'site_url'    => get_option( 'home' ),
					'platform'    => 'wp-sell-services',
				),
			),
		),
	);
};

// Stripe is stubbed: the test used to call the live API with the site's keys.
// $refund_calls counts refund requests; $refund_reply is what Stripe answers.
$refund_calls = 0;
$refund_reply = array( 'id' => 're_stub', 'status' => 'succeeded', 'amount' => 100, 'currency' => 'usd' );
$stub         = static function ( $pre, $args, $url ) use ( &$refund_calls, &$refund_reply ) {
	if ( false === strpos( (string) $url, 'api.stripe.com' ) ) {
		return $pre;
	}

	$is_refund     = false !== strpos( (string) $url, '/refunds' );
	$refund_calls += $is_refund ? 1 : 0;
	$body          = $is_refund ? $refund_reply : array( 'id' => 'pi_stub', 'status' => 'succeeded', 'amount' => 100, 'currency' => 'usd', 'metadata' => array() );

	return array(
		'response' => array( 'code' => isset( $body['error'] ) ? 400 : 200, 'message' => 'OK' ),
		'body'     => wp_json_encode( $body ),
		'headers'  => array(),
		'cookies'  => array(),
	);
};
add_filter( 'pre_http_request', $stub, 10, 3 );

$orders_for = static function ( string $id ) use ( $wpdb, $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare( "SELECT id, subtotal, total FROM {$table} WHERE transaction_id = %s", $id ) );
};

try {
	$gateway = new StripeGateway();

	$gateway->handle_webhook( $event( $pi_id, $charged ) );
	$rows = $orders_for( $pi_id );
	$made = array_merge( $made, wp_list_pluck( $rows, 'id' ) );
	$check( 'webhook creates the order when the browser has not', 1 === count( $rows ) );
	$check( sprintf( '  and its total is what was charged (%s = %s)', $rows[0]->total ?? '-', (string) $charged ), isset( $rows[0] ) && wpss_amounts_match( (float) $rows[0]->total, $charged, wpss_get_currency() ) );
	$check( '  and tax is not counted twice (subtotal is pre-tax)', isset( $rows[0] ) && (float) $rows[0]->subtotal < $charged );

	$gateway->handle_webhook( $event( $pi_id, $charged ) );
	$check( 'a second delivery for the same charge does not add an order', 1 === count( $orders_for( $pi_id ) ) );

	$short = $pi_id . '_short';
	$gateway->handle_webhook( $event( $short, $charged - 5 ) );
	$rows = $orders_for( $short );
	$made = array_merge( $made, wp_list_pluck( $rows, 'id' ) );
	$check( 'a charge that does not match the price creates no order', 0 === count( $rows ) );
	$check( '  and this site\'s own unsettled charge is refunded once', 1 === $refund_calls );

	// Stripe sends every payment_intent.succeeded on the account here. A charge
	// this site did not create is not ours to settle or refund (10375174172).
	$refund_calls = 0;
	$other        = $gateway->handle_webhook( $event( $pi_id . '_other', $charged - 5, array( 'site_url' => 'https://another-store.example' ) ) );
	$check( 'another site\'s charge on the same account is not refunded', 0 === $refund_calls && ! empty( $other['success'] ) );
	$check( '  and creates no order here', 0 === count( $orders_for( $pi_id . '_other' ) ) );

	$plugin = $gateway->handle_webhook( $event( $pi_id . '_plugin', $charged - 5, array( 'site_url' => '', 'platform' => '' ) ) );
	$check( 'another plugin\'s charge carrying customer_id is not refunded', 0 === $refund_calls && ! empty( $plugin['success'] ) );

	$pending = (int) $wpdb->get_var( "SELECT id FROM {$table} WHERE payment_status = 'pending' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
	if ( $pending ) {
		$gateway->handle_webhook( $event( $pi_id . '_foreign_order', $charged, array( 'order_id' => $pending, 'site_url' => 'https://another-store.example' ) ) );
		$check( 'another site\'s charge naming a local order id does not mark it paid', 'pending' === $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$table} WHERE id = %d", $pending ) ) ); // phpcs:ignore WordPress.DB
	}

	$refund_calls = 0;
	$refund_reply = array( 'error' => array( 'code' => 'charge_already_refunded', 'message' => 'Charge has already been refunded.', 'type' => 'invalid_request_error' ) );
	$again        = $gateway->handle_webhook( $event( $pi_id . '_again', $charged - 5 ) );
	$check( 'an already-refunded charge is done, not a retry', 1 === $refund_calls && empty( $again['retry'] ) );
} finally {
	remove_filter( 'pre_http_request', $stub, 10 );
	foreach ( $made as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => (int) $id ) );
	}
	false === $health ? delete_option( 'wpss_stripe_webhook_health' ) : update_option( 'wpss_stripe_webhook_health', $health, false );
	\WPSellServices\Tests\Factories\ServiceFactory::cleanup();
	\WPSellServices\Tests\Factories\UserFactory::cleanup();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
