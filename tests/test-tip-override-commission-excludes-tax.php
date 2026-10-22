<?php
/**
 * A tip taxed on top (total 14.40 = 12.00 + 2.40 VAT) with an owner-set tip
 * commission rate: the platform's cut and the vendor's credit are both worked
 * out on the 12.00, never on the tax.
 *
 * Run: wp eval-file tests/test-tip-override-commission-excludes-tax.php
 *
 * Clones the latest tip order into a QA row, credits it, then deletes the QA
 * order, its ledger row and restores the commission option, on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\TippingService;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$orders  = $wpdb->prefix . 'wpss_orders';
$ledger  = $wpdb->prefix . 'wpss_wallet_transactions';
$option  = get_option( 'wpss_commission', array() );
$cloned  = 0;

$restore = static function () use ( $wpdb, $orders, $ledger, $option, &$cloned ) {
	update_option( 'wpss_commission', $option );
	if ( $cloned ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $ledger, array( 'reference_type' => 'order', 'reference_id' => $cloned ) );
		$wpdb->delete( $orders, array( 'id' => $cloned ) );
		// phpcs:enable
	}
};

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$tip = $wpdb->get_row( "SELECT * FROM {$orders} WHERE platform = 'tip' ORDER BY id DESC LIMIT 1", ARRAY_A );
if ( ! $tip ) {
	$check( 'a tip order exists to clone', false, 'none on this site' );
	return;
}

unset( $tip['id'] );
$tip['order_number'] = 'QA-TIP-' . wp_generate_password( 6, false );
$tip['subtotal']     = '12.00';
$tip['total']        = '14.40';
$tip['status']         = 'completed';
$tip['payment_status'] = 'paid';
$tip['paid_at']        = current_time( 'mysql', true );
$tip['addons']       = '[]';
$tip['addons_total'] = '0.00';
$tip['meta']         = wp_json_encode( array( 'tax_rate' => 20, 'tax_amount' => 2.4, 'tax_included' => false ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $orders, $tip );
$cloned = (int) $wpdb->insert_id;
$check( 'QA tip order created', $cloned > 0 );

update_option( 'wpss_commission', array_merge( (array) $option, array( 'tip_commission_rate' => '10' ) ) );

$credited = ( new TippingService() )->credit_tip_on_payment_complete( $cloned );
$check( 'the tip was credited', true === $credited );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$amount = (float) $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d LIMIT 1", $cloned ) );
$check( 'vendor credit is 90% of the 12.00 net (10.80), not of the 14.40 gross (12.96)', abs( $amount - 10.80 ) < 0.001, (string) $amount );
