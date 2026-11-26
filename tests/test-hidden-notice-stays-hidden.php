<?php
/**
 * A notice rendered with the hidden attribute stays hidden (Basecamp 10549776724).
 *
 * .wpss-notice sets display:flex, which outranks the browser's [hidden] rule, so the reorder
 * error placeholder showed as an empty pink box under "Order again" on every completed order.
 * Every built copy of design-system.css (source, min, rtl, rtl min) must carry the reset.
 *
 * Run: wp eval-file tests/test-hidden-notice-stays-hidden.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
foreach ( array( 'design-system.css', 'design-system.min.css', 'design-system-rtl.css', 'design-system-rtl.min.css' ) as $file ) {
	$css = preg_replace( '/\s+/', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $file ) );
	$ok  = false !== strpos( $css, '.wpss-notice[hidden]{display:none' );
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . "{$file} hides a notice that carries the hidden attribute\n";
	$fails += $ok ? 0 : 1;
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
