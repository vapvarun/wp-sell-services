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

$event = static function ( string $id, float $amount ) use ( $buyer, $package, $service_id ) {
	return array(
		'type' => 'payment_intent.succeeded',
		'data' => array(
			'object' => array(
				'id'              => $id,
				'amount'          => (int) round( $amount * 100 ),
				'amount_received' => (int) round( $amount * 100 ),
				'currency'        => strtolower( wpss_get_currency() ),
				'metadata'        => array(
					'service_id'  => $service_id,
					'package_id'  => (int) ( $package['id'] ?? 0 ),
					'quantity'    => 1,
					'customer_id' => $buyer->ID,
				),
			),
		),
	);
};

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
} finally {
	foreach ( $made as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => (int) $id ) );
	}
	false === $health ? delete_option( 'wpss_stripe_webhook_health' ) : update_option( 'wpss_stripe_webhook_health', $health, false );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
