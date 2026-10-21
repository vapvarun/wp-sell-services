<?php
/**
 * An unpaid milestone, tip or extension never credits the vendor wallet.
 *
 * Run: wp eval-file tests/test-unpaid-suborder-no-credit.php
 *
 * Basecamp 10421776346 (owner decision 2026-10-20): an admin may complete an
 * unpaid sub-order, but money is never credited for payment not received.
 * MilestoneService::credit_phase() and CommissionService::record() are the two
 * credit doors; both must refuse while payment_status is not paid.
 *
 * Throwaway vendor and orders; every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\CommissionService;
use WPSellServices\Services\MilestoneService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$ledger = $wpdb->prefix . 'wpss_wallet_transactions';
add_filter( 'pre_wp_mail', '__return_true' );

$vendor = wp_insert_user( array( 'user_login' => 'dev_unpaid_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_unpaid_vendor@example.test' ) );
$buyer  = wp_insert_user( array( 'user_login' => 'dev_unpaid_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_unpaid_buyer@example.test' ) );
$made   = array();

$sub = static function ( string $platform, string $payment_status ) use ( $wpdb, $orders, $vendor, $buyer, &$made ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'    => 'DEVUNPAID-' . wp_generate_password( 6, false ),
			'customer_id'     => $buyer,
			'vendor_id'       => $vendor,
			'service_id'      => 0,
			'platform'        => $platform,
			'status'          => 'completed',
			'payment_status'  => $payment_status,
			'paid_at'         => 'paid' === $payment_status ? current_time( 'mysql' ) : null,
			'subtotal'        => 100,
			'total'           => 100,
			'platform_fee'    => 10,
			'vendor_earnings' => 90,
			'currency'        => wpss_get_currency(),
			'created_at'      => current_time( 'mysql' ),
		)
	);
	$made[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$credited = static fn( int $id ): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$milestone = new MilestoneService();
$unpaid    = $sub( 'milestone', 'pending' );
$check( 'unpaid completed phase is not credited', false === $milestone->credit_phase( $unpaid ) && 0 === $credited( $unpaid ) );

$paid = $sub( 'milestone', 'paid' );
$check( 'paid completed phase is credited', true === $milestone->credit_phase( $paid ) && 1 === $credited( $paid ) );
$check( 'crediting a paid phase twice stays one row', true === $milestone->credit_phase( $paid ) && 1 === $credited( $paid ) );

foreach ( array( 'tip', 'extension' ) as $type ) {
	$unpaid_sub = $sub( $type, 'pending' );
	( new CommissionService() )->record( $unpaid_sub );
	$check( "unpaid completed {$type} is not credited", 0 === $credited( $unpaid_sub ) );
}

foreach ( $made as $id ) {
	$wpdb->delete( $ledger, array( 'reference_type' => 'order', 'reference_id' => $id ) );
	$wpdb->delete( $orders, array( 'id' => $id ) );
}
wp_delete_user( $vendor );
wp_delete_user( $buyer );

exit( $fails ? 1 : 0 );
