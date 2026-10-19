<?php
/**
 * The notification list ships its CSS as a stylesheet (not an inline style block) and an
 * unread row keeps muted text readable in dark mode (UX rules 6 and 10, found in the 1.9.0
 * dark-mode walk).
 *
 * Run: wp eval-file tests/test-notification-list-style.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$read = static fn( string $rel ): string => (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );

$partial = $read( 'templates/partials/notifications-list.php' );
$check( 'the partial carries no inline <style>', false === stripos( $partial, '<style' ) );
$check( 'the partial enqueues the notification sheet', false !== strpos( $partial, 'wpss_enqueue_notifications_style()' ) );
$check( 'the enqueue helper exists', function_exists( 'wpss_enqueue_notifications_style' ) );

$css = $read( 'assets/css/notifications.css' );
$check( 'the unread row is tinted from the background, not the saturated primary-light token', (bool) preg_match( '/\.wpss-notif-row--unread \{[^}]*color-mix\(/', $css ) && ! preg_match( '/\.wpss-notif-row--unread \{[^}]*primary-light/', $css ) );
$check( 'the sheet has an RTL twin', is_file( WPSS_PLUGIN_DIR . 'assets/css/notifications-rtl.css' ) );
$check( 'the sheet uses at most one @media block', 1 === preg_match_all( '/@media/', $css ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
