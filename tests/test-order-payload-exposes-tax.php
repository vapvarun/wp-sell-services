<?php
/**
 * The REST order payload carries the tax inside `total`, read by the same
 * wpss_get_order_tax() as the pay page, and a taxless order reports zero.
 *
 * Run: wp eval-file tests/test-order-payload-exposes-tax.php
 *
 * Reads one order and sets its tax in memory only; nothing is written.
 *
 * @package WPSellServices
 */

use WPSellServices\API\OrdersController;

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$id    = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}wpss_orders WHERE service_id > 0 ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$order = wpss_get_order( $id );

if ( ! $order ) {
	$check( 'an order exists to read', false );
	return;
}

wp_set_current_user( 1 );
$controller = new OrdersController();
$request    = new WP_REST_Request( 'GET', '/wpss/v1/orders/' . $id );

$order->meta = array_diff_key( (array) $order->meta, array_flip( array( 'tax_rate', 'tax_amount', 'tax_included', 'tax_label' ) ) );
$none        = $controller->prepare_item_for_response( $order, $request )->get_data();
$check( 'a taxless order reports zero tax', isset( $none['tax'] ) && 0.0 === (float) $none['tax']['amount'], wp_json_encode( $none['tax'] ?? null ) );

$order->meta = array_merge( (array) $order->meta, wpss_order_tax_meta( 20.0, 2.4, false, 'VAT' ) );
$taxed       = $controller->prepare_item_for_response( $order, $request )->get_data()['tax'] ?? array();
$check( 'a taxed order reports the amount', 2.4 === (float) ( $taxed['amount'] ?? 0 ), wp_json_encode( $taxed ) );
$check( 'and the rate, label and inclusive flag', 20.0 === (float) ( $taxed['rate'] ?? 0 ) && 'VAT' === ( $taxed['label'] ?? '' ) && false === ( $taxed['included'] ?? null ) );
$check( 'and the exact minor units', wpss_amount_to_minor_units( 2.4, (string) $order->currency ) === ( $taxed['amount_minor'] ?? -1 ) );
