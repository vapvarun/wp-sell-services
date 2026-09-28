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
 * settles through CheckoutIntentService. Needs the dev_f1 fixtures (service
 * 6205, buyer dev_f1_rev_buyer); the orders it creates are deleted at the end.
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

$buyer   = get_user_by( 'login', 'dev_f1_rev_buyer' );
$service = get_post( 6205 );

if ( ! $buyer || ! $service ) {
	echo "SKIP  dev_f1 fixtures missing\n";
	return;
}

$package = (array) ( get_post_meta( 6205, '_wpss_packages', true )[0] ?? array() );
$line    = \WPSellServices\Checkout\CheckoutIntentService::price_service_line( 6205, (int) ( $package['id'] ?? 0 ), 1, array() );
$charged = (float) $line['total'];
$health  = get_option( 'wpss_stripe_webhook_health' );
$table   = $wpdb->prefix . 'wpss_orders';
$pi_id   = 'pi_test_recovery_' . wp_generate_password( 10, false );
$made    = array();

$event = static function ( string $id, float $amount ) use ( $buyer, $package ) {
	return array(
		'type' => 'payment_intent.succeeded',
		'data' => array(
			'object' => array(
				'id'              => $id,
				'amount'          => (int) round( $amount * 100 ),
				'amount_received' => (int) round( $amount * 100 ),
				'currency'        => strtolower( wpss_get_currency() ),
				'metadata'        => array(
					'service_id'  => 6205,
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
