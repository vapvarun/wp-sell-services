<?php
/**
 * The buyer-requests empty state brings the icon runtime with it: its Lucide
 * placeholder renders blank on a page that loaded no other icon.
 *
 * Run: wp eval-file tests/test-empty-state-loads-icon-runtime.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

do_action( 'wp_enqueue_scripts' );
wp_dequeue_script( 'wpss-icons' );
wp_dequeue_script( 'lucide' );

ob_start();
include dirname( __DIR__ ) . '/templates/content-no-requests.php';
ob_end_clean();

$ok = wp_script_is( 'wpss-icons', 'enqueued' ) && wp_script_is( 'lucide', 'enqueued' );
echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . "empty state enqueues the icon runtime\n";
exit( $ok ? 0 : 1 );
