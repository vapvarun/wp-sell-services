<?php
/**
 * Offline Payment text stays readable in dark mode (Basecamp 10546236606).
 *
 * The checkout description box hard-coded a light background inline while its
 * text inherited the dark-mode token colour: 1.40:1 contrast. The box now uses a
 * class styled with tokens. The yellow order-page instructions box stays inline
 * (it is also emailed), so its content text carries a fixed dark colour.
 *
 * Run: wp eval-file tests/test-offline-text-readable-in-dark-mode.php
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Gateways\OfflineGateway;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$enable = static fn( $value ) => array_merge( is_array( $value ) ? $value : array(), array( 'enabled' => '1' ) );
add_filter( 'option_' . 'wpss_offline_settings', $enable );
add_filter( 'default_option_' . 'wpss_offline_settings', $enable );

$html = ( new OfflineGateway() )->render_payment_form( 10.0, 'USD', 0 );
preg_match( '/<div class="wpss-offline-gateway-description"([^>]*)>/', $html, $box );
$check( 'checkout description box renders', ! empty( $box ) );
$check( 'checkout description box carries no hard-coded light colours', ! empty( $box ) && false === strpos( $box[1], 'style=' ) );

$css = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/frontend.min.css' );
preg_match( '/\.wpss-offline-gateway-description\{([^}]*)\}/', $css, $rule );
$check( 'shipped CSS styles the box with the surface and text tokens', ! empty( $rule[1] ) && false !== strpos( $rule[1], 'var(--wpss-bg-subtle)' ) && false !== strpos( $rule[1], 'var(--wpss-text-secondary)' ) && false !== strpos( $rule[1], 'var(--wpss-border)' ) );
$check( 'RTL CSS carries the same rule', false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/frontend-rtl.min.css' ), '.wpss-offline-gateway-description{' ) );

// Both yellow instruction boxes (after checkout + order page, which also feeds email).
$src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Integrations/Gateways/OfflineGateway.php' );
preg_match_all( '/<div class="wpss-offline-instructions-content"([^>]*)>/', $src, $content );
$check( 'both instruction content blocks exist', 2 === count( $content[1] ) );
$check( 'both instruction content blocks set a dark text colour on the yellow box', 2 === count( array_filter( $content[1], static fn( $a ) => false !== strpos( $a, 'color: #856404' ) ) ) );

remove_filter( 'option_' . 'wpss_offline_settings', $enable );
remove_filter( 'default_option_' . 'wpss_offline_settings', $enable );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
