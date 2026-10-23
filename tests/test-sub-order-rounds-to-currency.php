<?php
/**
 * A taxed phase stores its total and its tax amount to the currency's minor unit, not the raw
 * float (the column keeps 3 places) and not always 2 places: 0 for JPY, 3 for KWD.
 *
 * Run: wp eval-file tests/test-sub-order-rounds-to-currency.php
 *
 * Throwaway users (swept by the harness); sets wpss_tax to 7.5% exclusive and restores it; the
 * currency is switched with the `wpss_currency` filter for this request only. Orders deleted by id.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\MilestoneService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders   = $wpdb->prefix . 'wpss_orders';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$mk       = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_round_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-round-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor   = $mk( 'v' );
$buyer    = $mk( 'b' );
$orig     = get_option( 'wpss_tax', null );
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'QA round', 'status' => 'active' ) );
register_shutdown_function(
	static function () use ( $wpdb, $orders, $profiles, $vendor, $buyer, $orig ) {
		null === $orig ? delete_option( 'wpss_tax' ) : update_option( 'wpss_tax', $orig );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders} WHERE customer_id = %d", $buyer ) );
		$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
	}
);
update_option( 'wpss_tax', array( 'enable_tax' => 1, 'tax_rate' => 7.5, 'tax_included' => 0, 'tax_label' => 'VAT' ) );

$phase = static function ( string $currency, float $amount ) use ( $wpdb, $orders, $vendor, $buyer ): ?object {
	$pin = static fn() => $currency;
	add_filter( 'wpss_currency', $pin );
	$wpdb->insert( $orders, array( 'order_number' => 'QA-ROUND-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'request', 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => 100, 'total' => 100, 'currency' => $currency, 'created_at' => current_time( 'mysql', true ) ) );
	$res = ( new MilestoneService() )->propose( (int) $wpdb->insert_id, $vendor, 'QA round phase', 'd', $amount, 3 );
	remove_filter( 'wpss_currency', $pin );
	return $res['milestone_id'] ?? 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT total, meta FROM {$orders} WHERE id = %d", (int) $res['milestone_id'] ) ) : null;
};
// phpcs:enable

// JPY, 0 decimals: 1001 + 7.5% = 1076.075.
$row = $phase( 'JPY', 1001.0 );
$tax = $row ? (array) json_decode( (string) $row->meta, true ) : array();
$check( 'JPY: total stored as 1076, not 1076.075', $row && '1076.000' === (string) $row->total, (string) ( $row->total ?? 'none' ) );
$check( 'JPY: tax amount 75, not 75.08', isset( $tax['tax_amount'] ) && 75.0 === (float) $tax['tax_amount'], (string) ( $tax['tax_amount'] ?? 'none' ) );

// KWD, 3 decimals: 10.01 + 7.5% tax = 0.75075.
$row = $phase( 'KWD', 10.01 );
$tax = $row ? (array) json_decode( (string) $row->meta, true ) : array();
$check( 'KWD: tax amount 0.751 (3 places), not 0.75', isset( $tax['tax_amount'] ) && abs( (float) $tax['tax_amount'] - 0.751 ) < 0.0001, (string) ( $tax['tax_amount'] ?? 'none' ) );
$check( 'KWD: total 10.761', $row && '10.761' === (string) $row->total, (string) ( $row->total ?? 'none' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
