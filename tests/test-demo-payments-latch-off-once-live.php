<?php
/**
 * Demo (Test) payments never come back once the store has been live (Basecamp 10551463173).
 *
 * wpss_demo_payments_enabled() was true whenever no real gateway was enabled, so an owner turning Stripe
 * off to rotate keys put a free "pay" button at checkout: any buyer could order, vendors were credited.
 * It now latches off the first time the store is live (a real gateway enabled, or an order not made
 * with Test). Offline alone does not latch: it is on from activation, and a fresh install must still
 * offer the demo checkout.
 *
 * Run: wp eval-file tests/test-demo-payments-latch-off-once-live.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

$plugin = wpss();
$prop   = new ReflectionProperty( $plugin, 'payment_gateways' );
$prop->setAccessible( true );
$all    = $prop->getValue( $plugin );
$saved  = get_option( 'wpss_demo_payments', null );
// A fresh install: no order rows yet.
$no_orders = static fn( string $q ): string => str_contains( $q, 'wpss_orders WHERE payment_method' ) ? 'SELECT NULL' : $q;
// A store holding only what Import demo data seeds: paid orders on Test, unpaid ones with no method yet.
$demo_only = static function ( string $q ) use ( $wpdb ): string {
	return str_contains( $q, 'wpss_orders WHERE payment_method' )
		? str_replace( "{$wpdb->prefix}wpss_orders", "(SELECT 'test' AS payment_method UNION ALL SELECT NULL) AS demo_orders", $q )
		: $q;
};

try {
	if ( ! function_exists( 'wpss_store_is_live' ) ) {
		$check( 'wpss_store_is_live() exists', false );
	}

	// 1. Fresh install, Offline on (as at activation): demo is off but NOT latched.
	delete_option( 'wpss_demo_payments' );
	add_filter( 'query', $no_orders );
	$prop->setValue( $plugin, array_intersect_key( $all, array_flip( array( 'offline', 'test' ) ) ) );
	wpss_demo_payments_enabled();
	$check( 'Fresh install with Offline on: nothing latched', false === get_option( 'wpss_demo_payments', false ) );

	// 2. Fresh install with no real method: the demo checkout is offered.
	$prop->setValue( $plugin, array_intersect_key( $all, array_flip( array( 'test' ) ) ) );
	$check( 'Fresh install, no method on: demo payments offered', wpss_demo_payments_enabled() );
	remove_filter( 'query', $no_orders );

	// 2b. Right after Import demo data (Test-paid orders plus unpaid ones with no method): still offered, nothing latched.
	delete_option( 'wpss_demo_payments' );
	add_filter( 'query', $demo_only );
	$check( 'Demo-seeded orders only: demo payments still offered', wpss_demo_payments_enabled() );
	$check( '  and nothing latched', false === get_option( 'wpss_demo_payments', false ) );
	remove_filter( 'query', $demo_only );

	// 3. The store has real orders: latched off, and it stays off with every method switched off.
	$check( 'Store with a real order: demo off', ! wpss_demo_payments_enabled() );
	$check( '  and latched (wpss_demo_payments = no)', 'no' === get_option( 'wpss_demo_payments' ) );
	add_filter( 'query', $no_orders );
	$check( '  still off later with no method enabled (Stripe off to rotate keys)', ! wpss_demo_payments_enabled() );
	remove_filter( 'query', $no_orders );

	// 4. The Terms notice reads the same rule: Offline alone on a fresh site is not due.
	delete_option( 'wpss_demo_payments' );
	add_filter( 'query', $no_orders );
	$prop->setValue( $plugin, array_intersect_key( $all, array_flip( array( 'offline', 'test' ) ) ) );
	$check( 'Terms notice not due on a fresh Offline-only site', ! wpss_terms_notice_due() );
	remove_filter( 'query', $no_orders );
} finally {
	remove_filter( 'query', $no_orders );
	remove_filter( 'query', $demo_only );
	$prop->setValue( $plugin, $all );
	null === $saved ? delete_option( 'wpss_demo_payments' ) : update_option( 'wpss_demo_payments', $saved );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
