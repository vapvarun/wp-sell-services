<?php
/**
 * The single gig H1 shows the whole title (Basecamp 10463493457, Zoho #41859).
 *
 * A two-line clamp written for block cards in blocks.css matched the H1, which shares the
 * .wpss-service-title class, so long titles were cut on every screen size.
 *
 * Run: wp eval-file tests/test-gig-title-not-clamped.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$css_dir = WP_PLUGIN_DIR . '/wp-sell-services/assets/css/';
$h1_rule = '/(^|[\s,}])\.wpss-service-title\s*(,[^{]*)?\{[^}]*line-clamp/';

foreach ( glob( $css_dir . '*.css' ) as $file ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $file ) );
	if ( preg_match( $h1_rule, $css ) ) {
		$check( 'no stylesheet clamps a bare .wpss-service-title (' . basename( $file ) . ')', false );
	}
}
$check( 'no stylesheet clamps a bare .wpss-service-title', 0 === $fails );

$view = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/SingleServiceView.php' );
$check( 'the single gig H1 still carries that class (what the check above protects)', str_contains( $view, '<h1 class="wpss-service-title">' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
