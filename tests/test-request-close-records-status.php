<?php
/**
 * Closing a buyer request from the dashboard records it as closed (Basecamp 10549397728).
 *
 * The dashboard Close only set post_status to draft, so the admin Buyer Requests list read
 * "Draft ... Open". The handler now also writes the status through BuyerRequestService,
 * open <-> closed only, so a hired request keeps its state, and vendors are not notified.
 *
 * Run: wp eval-file tests/test-request-close-records-status.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Frontend\AjaxHandlers;
use WPSellServices\Services\BuyerRequestService;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f       = wpss_test_fixtures();
$request = wp_insert_post(
	array(
		'post_type'   => 'wpss_request',
		'post_status' => 'publish',
		'post_title'  => 'QA-RFT close records status',
		'post_author' => $f->buyer,
	)
);
$f->posts[] = (int) $request;
update_post_meta( $request, '_wpss_status', BuyerRequestService::STATUS_OPEN );

$die = static function () {
	return static function () {
		throw new RuntimeException( 'done' );
	};
};
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', $die );

$vendor_notes = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d", $f->vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$send         = static function ( string $status ) use ( $request ): void {
	$_POST    = array(
		'nonce'      => wp_create_nonce( 'wpss_dashboard_nonce' ),
		'request_id' => $request,
		'status'     => $status,
	);
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new AjaxHandlers() )->update_request_status();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	ob_end_clean();
	clean_post_cache( $request );
};
$state = static fn() => get_post_status( $request ) . '/' . get_post_meta( $request, '_wpss_status', true );

try {
	wp_set_current_user( $f->buyer );
	$before = $vendor_notes();

	$send( 'draft' );
	$check( 'Close: the request is a draft and its status reads closed', 'draft/' . BuyerRequestService::STATUS_CLOSED === $state(), $state() );
	$check( '  and no vendor was notified', $vendor_notes() === $before );

	$send( 'publish' );
	$check( 'Reopen: the request is published and open again', 'publish/' . BuyerRequestService::STATUS_OPEN === $state(), $state() );

	update_post_meta( $request, '_wpss_status', BuyerRequestService::STATUS_HIRED );
	$send( 'draft' );
	$check( 'Closing a hired request keeps it hired', 'draft/' . BuyerRequestService::STATUS_HIRED === $state(), $state() );
	$send( 'publish' );
	$check( '  and reopening it does not make it open for proposals', 'publish/' . BuyerRequestService::STATUS_HIRED === $state(), $state() );
} finally {
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die );
	$_POST    = array();
	$_REQUEST = array();
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
