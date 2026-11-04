<?php
/**
 * Presentation contracts found in the 1.9.1 browser walk, checked in source so a
 * rebuild cannot quietly undo them:
 * - select boxes grow with their text (a fixed height clipped the descenders);
 * - the order modal shows a refusal even when its body is hidden;
 * - the order modal shows the tax line the total includes;
 * - Woo, EDD and FluentCart refuse a banned or suspended buyer like the standalone cart.
 *
 * Run: wp eval-file tests/test-order-modal-and-selects-presentation.php
 *
 * Read-only.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$free = WP_PLUGIN_DIR . '/wp-sell-services/';
$pro  = WP_PLUGIN_DIR . '/wp-sell-services-pro/';
$read = static fn( string $f ): string => is_file( $f ) ? (string) file_get_contents( $f ) : '';

foreach ( array( 'assets/css/frontend.css', 'assets/css/design-system.css' ) as $css ) {
	$source = $read( $free . $css );
	preg_match_all( '/(?<![-\w])select[^{}\/]*\{[^}]*(?<![-\w])height:\s*(\d+)px/', $source, $fixed );
	$check( "{$css}: no select has a fixed pixel height", empty( $fixed[0] ), implode( ' | ', array_map( 'trim', $fixed[0] ) ) );
}

$js = $read( $free . 'assets/js/single-service.js' );
$check( 'the order modal puts a refusal above the footer when the body is hidden', false !== strpos( $js, ".wpss-modal__body:visible" ) && false !== strpos( $js, ".wpss-modal__footer').first().before(" ) );
$check( 'the order modal fills a tax row from the quote', false !== strpos( $js, 'wpss-summary-tax' ) && false !== strpos( $read( $free . 'src/Frontend/SingleServiceView.php' ), 'wpss-summary-tax' ) );

if ( is_dir( $pro ) ) {
	foreach ( array( 'WooCommerce/WCCheckoutProvider', 'EDD/EDDCheckoutProvider', 'FluentCart/FluentCartCheckoutProvider' ) as $rail ) {
		$check( "{$rail} refuses a banned or suspended buyer", false !== strpos( $read( $pro . "src/Integrations/{$rail}.php" ), 'wpss_account_status_block' ) );
	}
} else {
	echo "SKIP  Pro not installed\n";
}
