<?php
/**
 * A refused refund is described by what happened to the order (Basecamp 10553825648).
 *
 * A refund refused during an accepted cancellation leaves the order cancelled; any other
 * refused refund leaves it as it was. The order view's Refund failed box and the owner
 * notice both said "the order was not changed" on a cancelled order, so an owner could
 * take the order for live. Both now read the origin stored in the failed-refund flag.
 *
 * Run: wp eval-file tests/test-refund-failed-wording.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

use WPSellServices\Services\OrderWorkflowManager;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f = wpss_test_fixtures();
wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
set_current_screen( 'toplevel_page_wp-sell-services' ); // The screen that shows every notice in full.

$cancelled = ( $f->order )( array( 'status' => 'cancelled' ) );
$live      = ( $f->order )( array( 'status' => 'in_progress' ) );
OrderWorkflowManager::flag_refund_failed( $cancelled, 40, 'invalid request sent', array( 'origin' => 'cancel' ) );
OrderWorkflowManager::flag_refund_failed( $live, 40, 'invalid request sent', array( 'origin' => 'admin' ) );

$admin = new \WPSellServices\Admin\Admin();
$view  = static function ( int $order_id ) use ( $admin ): string {
	$render = new ReflectionMethod( $admin, 'render_order_detail' );
	ob_start();
	$render->invoke( $admin, $order_id );
	$html = (string) ob_get_clean();
	return preg_match( '#<div class="postbox wpss-refund-failed">.*?</p>#s', $html, $m ) ? wp_strip_all_tags( $m[0] ) : '';
};

try {
	$box = $view( $cancelled );
	$check( 'cancelled order: the box says the order was cancelled', false !== strpos( $box, 'The order was cancelled but the buyer has not been refunded' ), $box );
	$check( 'cancelled order: the box does not say the order was not changed', '' !== $box && false === strpos( $box, 'not changed' ), $box );

	$box = $view( $live );
	$check( 'admin-started refund: the box still says the order was not changed', false !== strpos( $box, 'the order was not changed' ), $box );

	ob_start();
	$admin->refund_review_notice();
	$notice = wp_strip_all_tags( (string) ob_get_clean() );
	$check( 'owner notice marks the cancelled order', 1 === preg_match( '/Order #' . $cancelled . ' \(order cancelled\)/', $notice ), $notice );
	$check( 'owner notice does not mark the live order', false !== strpos( $notice, 'Order #' . $live . ':' ), $notice );
	$check( 'owner notice no longer claims nothing changed', '' !== $notice && false === strpos( $notice, 'nothing was changed' ), $notice );
} finally {
	foreach ( array( $cancelled, $live ) as $order_id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $order_id ) );
	}
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
