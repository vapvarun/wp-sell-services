<?php
/**
 * The requirements page names a request order after the request it answers,
 * not the generic "Service" (a request order has no service post).
 *
 * Run: wp eval-file tests/test-requirements-title-request-order.php
 *
 * Renders a throwaway request order waiting on requirements, removed on any exit.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

defined( 'ABSPATH' ) || exit;

global $wpdb;

// A throwaway request order waiting on requirements, carrying the proposal
// snapshot BuyerRequestService writes on acceptance; removed on any exit.
$f        = wpss_test_fixtures();
$title    = 'QA request: redesign the onboarding emails';
$order_id = ( $f->order )(
	array(
		'platform'   => 'request',
		'service_id' => 0,
		'status'     => 'pending_requirements',
		'meta'       => wp_json_encode( array( 'proposal_snapshot' => array( 'proposed_price' => 40, 'proposed_days' => 5, 'request_title' => $title ) ) ),
	)
);
$row      = wpss_get_order( $order_id );

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
