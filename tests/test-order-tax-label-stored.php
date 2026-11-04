<?php
/**
 * Every order creator stores the tax label with the order, and the reader falls
 * back to the site's current label (not a hard "Tax") for orders that predate it.
 *
 * Run: wp eval-file tests/test-order-tax-label-stored.php
 *
 * @package WPSellServices
 */

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

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orig_tax = get_option( 'wpss_tax', null );
$restore  = static function () use ( $orig_tax ) {
	null === $orig_tax ? delete_option( 'wpss_tax' ) : update_option( 'wpss_tax', $orig_tax );
};

update_option( 'wpss_tax', array_merge( (array) $orig_tax, array( 'enable_tax' => true, 'tax_label' => 'GST', 'tax_rate' => 10 ) ) );

$has = function_exists( 'wpss_order_tax_meta' );
$check( 'wpss_order_tax_meta exists', $has );

if ( $has ) {
	$meta = wpss_order_tax_meta( 10.0, 5.0, false );
	$check( 'meta carries the current label when none is given', 'GST' === $meta['tax_label'], (string) $meta['tax_label'] );
	$check( 'meta keeps an explicit label', 'VAT' === wpss_order_tax_meta( 10.0, 5.0, false, 'VAT' )['tax_label'] );

	update_option( 'wpss_tax', array_merge( (array) get_option( 'wpss_tax' ), array( 'tax_label' => 'Sales tax' ) ) );
	$order = (object) array( 'meta' => wp_json_encode( $meta ) );
	$check( 'an order keeps its label after the setting changes', 'GST' === wpss_get_order_tax( $order )['label'] );
}

$legacy = (object) array( 'meta' => wp_json_encode( array( 'tax_rate' => 10, 'tax_amount' => 5 ) ) );
$check( 'an order without a stored label reads the current setting label', 'Sales tax' === wpss_get_order_tax( $legacy )['label'], wpss_get_order_tax( $legacy )['label'] );

// The order screens print that label, not a generic "Tax".
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$any = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}wpss_orders LIMIT 1" );
$po  = $any ? wpss_get_order( $any ) : null;
if ( $po ) {
	$po->meta = array_merge( (array) $po->meta, array( 'tax_amount' => 5.0, 'tax_label' => 'GST', 'tax_included' => false ) );
	$wpss_order = $po;
	ob_start();
	include dirname( __DIR__ ) . '/templates/order/line-items.php';
	$html = (string) ob_get_clean();
	$check( 'line items print the order tax label', false !== strpos( $html, '>GST<' ) || false !== strpos( $html, 'GST' ) );
}

$mail = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/EmailService.php' );
$check( 'order emails take the title from wpss_get_order_title()', false === strpos( $mail, 'get_the_title( $order->service_id )' ) );

exit( $fails ? 1 : 0 );
