<?php
/**
 * The Menu Visibility card carries no inline style, and each role row is a real disclosure whose status is a
 * styled badge (Basecamp 10403389297).
 *
 * Run: wp eval-file tests/test-menu-visibility-markup.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( 1 );
ob_start();
( new \WPSellServices\Frontend\MenuVisibility() )->render_settings_ui();
$html = (string) ob_get_clean();

$check( 'the card renders a row per role', substr_count( $html, 'class="wpss-visibility-role"' ) >= 2 );
$check( 'no inline style attribute in the card', 0 === preg_match( '/\sstyle=/', $html ) );
$check( 'each row is a details with a summary control', substr_count( $html, '<details' ) === substr_count( $html, '<summary class="wpss-visibility-role__summary"' ) );
$check( 'the status is a badge element', false !== strpos( $html, 'wpss-visibility-role__state' ) );

$css = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/admin-settings.css' );
$check( 'the summary has a focus ring and a hover guard', false !== strpos( $css, '.wpss-visibility-role__summary:focus-visible' ) && false !== strpos( $css, '@media (hover: hover)' ) );
$check( 'the rtl and min builds carry the rule', false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/admin-settings-rtl.min.css' ), 'wpss-visibility-role__summary' ) && false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/admin-settings.min.css' ), 'wpss-visibility-role__summary' ) );

exit( $fails ? 1 : 0 );
