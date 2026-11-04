<?php
/**
 * The admin package editor lets a vendor-facing -1 (Unlimited) revisions value
 * be saved: the input must not forbid it, or the browser blocks the save.
 *
 * Run: wp eval-file tests/test-package-revisions-unlimited-input.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Metaboxes/ServiceMetabox.php' );
preg_match( '/\[revisions\]"[\s\S]{0,400}?min="(-?\d+)"/', $src, $m );
$ok = isset( $m[1] ) && (int) $m[1] <= -1;
echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . 'revisions input allows -1 (min=' . ( $m[1] ?? '?' ) . ")\n";
exit( $ok ? 0 : 1 );
