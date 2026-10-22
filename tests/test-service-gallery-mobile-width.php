<?php
/**
 * On a phone the single-service main column's children are capped to the layout
 * width, so a long gallery thumbnail strip scrolls inside the gallery instead of
 * stretching the page. Browser-verified at 390px; this keeps the rule in place.
 *
 * Run: wp eval-file tests/test-service-gallery-mobile-width.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/single-service.css' );
$ok  = (bool) preg_match( '/\.wpss-service-main\s*>\s*\*\s*\{[^}]*max-width:\s*100%/', $css );
echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . "service main children are width-capped on mobile\n";
exit( $ok ? 0 : 1 );
