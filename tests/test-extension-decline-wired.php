<?php
/**
 * Decline on an extra-work quote works on both pages that show it (Basecamp 10545751953).
 *
 * The only Decline handler was an inline script printed by order-view.php, so
 * the Decline button on the extension page itself did nothing. And once a quote
 * that cannot be paid lost its pay link, Decline was the only way out there.
 * One handler now lives in the enqueued frontend.js, the extension page's button
 * carries the request id the endpoint needs, and the inline copy is gone.
 *
 * Run: wp eval-file tests/test-extension-decline-wired.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$read = static fn( string $path ): string => (string) file_get_contents( WPSS_PLUGIN_DIR . $path );

$check( 'the extension page button carries data-request', false !== strpos( $read( 'templates/order/extension-view.php' ), 'data-request="<?php echo esc_attr( (int) $extension_request->id ); ?>"' ) );
$check( 'the extension page finds the request through the existing finder', is_callable( array( new \WPSellServices\Services\ExtensionOrderService(), 'get_request_by_pay_order' ) ) );
$check( 'order-view.php no longer prints its own decline script', false === strpos( $read( 'templates/order/order-view.php' ), 'wpss_decline_extension' ) );

foreach ( array( 'assets/js/frontend.js', 'assets/js/frontend.min.js' ) as $file ) {
	$js = $read( $file );
	$check( "{$file}: one delegated Decline handler posts wpss_decline_extension with the localized nonce", false !== strpos( $js, 'wpss-extension-decline-btn' ) && false !== strpos( $js, 'wpss_decline_extension' ) && false !== strpos( $js, 'declineExtensionNonce' ) );
}

$check( 'frontend.js gets the decline nonce and strings', false !== strpos( $read( 'src/Frontend/Frontend.php' ), "wp_create_nonce( 'wpss_decline_extension' )" ) && false !== strpos( $read( 'src/Frontend/Frontend.php' ), "'confirmDeclineExtension'" ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
