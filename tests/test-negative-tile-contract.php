<?php
/**
 * The negative balance tile is readable and reconciles with the admin figure (Basecamp 10416610833).
 *
 * Run: wp eval-file tests/test-negative-tile-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/design-system.css' ) );

// Red on the accent fill read 1.3:1; the negative tile must repaint its own surface.
$check( 'the negative tile sets its own background', 1 === preg_match( '/\.wpss-stat-card\.wpss-stat-card--negative\s*\{[^}]*background\s*:\s*var\(--wpss-surface\)/', $css ) );
$check( 'its figure uses the theme-aware danger text token', 1 === preg_match( '/\.wpss-stat-card--negative \.wpss-stat-card__value\s*\{\s*color\s*:\s*var\(--wpss-danger-text\)/', $css ) );

$tpl = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/dashboard/sections/earnings.php' );
$check( 'the debt notices state the ledger amount the admin sees', str_contains( $tpl, "\$earnings['ledger_balance']" ) && ! str_contains( $tpl, "abs( \$available_balance ) ) )\n				);" ) );
$check( 'a withdrawal in progress is named in the notice', str_contains( $tpl, '$wpss_pending_note' ) );

exit( $fails ? 1 : 0 );
