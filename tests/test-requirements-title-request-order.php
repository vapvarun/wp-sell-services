<?php
/**
 * The requirements page names a request order after the request it answers,
 * not the generic "Service" (a request order has no service post).
 *
 * Run: wp eval-file tests/test-requirements-title-request-order.php
 *
 * Flips one finished request order to pending_requirements for the render and
 * restores its status on any exit.
 *
 * @package WPSellServices
 */

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$row = $wpdb->get_row( "SELECT id, customer_id, status, meta FROM {$wpdb->prefix}wpss_orders WHERE service_id = 0 AND meta LIKE '%request_title%' LIMIT 1" );

if ( ! $row ) {
	echo "SKIP  no request order with a proposal snapshot on this install\n";
	exit( 0 );
}

$title = (string) ( json_decode( (string) $row->meta, true )['proposal_snapshot']['request_title'] ?? '' );

$order_id = (int) $row->id;
$restore  = static function () use ( $wpdb, $order_id, $row ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => $row->status ), array( 'id' => $order_id ) );
	wp_cache_flush();
};
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => 'pending_requirements' ), array( 'id' => $order_id ) );
wp_cache_flush();

wp_set_current_user( (int) $row->customer_id );

// The visitor is on a page (the Dashboard): get_post( 0 ) and get_the_title( 0 ) answer with it.
$page_id = (int) get_option( 'page_on_front' ) ?: (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$GLOBALS['post'] = get_post( $page_id );
setup_postdata( $GLOBALS['post'] );
$page_title = (string) get_the_title( $page_id );

ob_start();
include dirname( __DIR__ ) . '/templates/order/order-requirements.php';
$html = (string) ob_get_clean();

$email_title = function_exists( 'wpss_get_order_title' ) ? wpss_get_order_title( wpss_get_order( $order_id ) ) : get_the_title( 0 );

$ok = '' !== $title && $title !== $page_title && $title === $email_title && false !== strpos( $html, esc_html( $title ) )
	&& false === strpos( $html, 'wpss-requirements-page__summary-title">' . esc_html( $page_title ) . '<' );
echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . "requirements summary and order title name the request ('{$title}')\n";
exit( $ok ? 0 : 1 );
