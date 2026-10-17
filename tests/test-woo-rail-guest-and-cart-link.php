<?php
/**
 * An unticked "Site cart link" no longer saves an explicit off, and a guest's
 * Add to cart on a rail that cannot rebuild the selection returns to the service
 * page after login (Basecamp 10407576283, 10407576066).
 *
 * Run: wp eval-file tests/test-woo-rail-guest-and-cart-link.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$settings = new \WPSellServices\Admin\Settings();
$saved    = $settings->sanitize_general_settings( array( 'use_marketplace_cart_link' => '0' ) );
$check( 'unticked saves "" so the derived default stays in charge', '' === ( $saved['use_marketplace_cart_link'] ?? null ) );
$saved = $settings->sanitize_general_settings( array( 'use_marketplace_cart_link' => '1' ) );
$check( 'ticked saves true', true === ( $saved['use_marketplace_cart_link'] ?? null ) );

$before = get_option( 'wpss_general', array() );
$set    = static function ( $value ) use ( $before ) {
	update_option( 'wpss_general', array_merge( (array) $before, array( 'use_marketplace_cart_link' => $value ) ) );
};
$set( true );
$check( 'ticked forces the marketplace cart link on', true === \WPSellServices\Integrations\Standalone\StandaloneAdapter::should_use_marketplace_cart_link() );
$set( false );
$derived_false = \WPSellServices\Integrations\Standalone\StandaloneAdapter::should_use_marketplace_cart_link();
$set( '' );
$derived_blank = \WPSellServices\Integrations\Standalone\StandaloneAdapter::should_use_marketplace_cart_link();
$check( 'a stored false (1.8.x) is treated as automatic, same as ""', $derived_false === $derived_blank );
update_option( 'wpss_general', $before );

$svc = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_title' => 'QA guest return', 'post_status' => 'publish', 'post_author' => 1 ) );
$m   = new ReflectionMethod( \WPSellServices\Frontend\AjaxHandlers::class, 'guest_return_url' );
$m->setAccessible( true );
$handler = ( new ReflectionClass( \WPSellServices\Frontend\AjaxHandlers::class ) )->newInstanceWithoutConstructor();
$url     = (string) $m->invoke( $handler, $svc, 'http://example.test/checkout/?x=1' );
if ( wpss_uses_standalone_payments() ) {
	$check( 'standalone rail keeps the checkout URL (it rebuilds the selection)', 'http://example.test/checkout/?x=1' === $url );
} else {
	$check( 'a non-standalone rail returns to the service page', get_permalink( $svc ) === $url );
}
wp_delete_post( $svc, true );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
