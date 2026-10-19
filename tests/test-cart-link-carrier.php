<?php
/**
 * The derived cart-link default ignores Pro's hidden carrier product (Basecamp 10416198408).
 *
 * Run on the standalone rail (it skips otherwise): wp eval-file tests/test-cart-link-carrier.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Integrations\Standalone\StandaloneAdapter;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! post_type_exists( 'product' ) || ! wpss_uses_standalone_payments() ) {
	echo "SKIP  needs the standalone rail with a product post type\n";
	return;
}

$general  = get_option( 'wpss_general', array() );
$carrier0 = get_option( 'wpss_wc_carrier_product_id', null );
update_option( 'wpss_general', array_merge( (array) $general, array( 'use_marketplace_cart_link' => '' ) ) );

$ids = array();
$mk  = static function () use ( &$ids ): int {
	$ids[] = $id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'wpss-test-product' ) );
	return $id;
};

// Neutral baseline: every real product on this site is drafted for the test, then restored.
$real = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $real as $id ) {
	wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
}

$carrier = $mk();
update_option( 'wpss_wc_carrier_product_id', $carrier );
$check( 'only the carrier published: the site sells no Woo products, so the marketplace cart link is on', StandaloneAdapter::should_use_marketplace_cart_link() );

$mk();
$check( 'a real product alongside the carrier: the owner decides, default stays off', ! StandaloneAdapter::should_use_marketplace_cart_link() );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
foreach ( $real as $id ) {
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
}
null === $carrier0 ? delete_option( 'wpss_wc_carrier_product_id' ) : update_option( 'wpss_wc_carrier_product_id', $carrier0 );
update_option( 'wpss_general', $general );

exit( $fails ? 1 : 0 );
