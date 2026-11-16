<?php
/**
 * POST /orders/{id}/deliver carries the files the client posts, and refuses a bad one with a 400.
 *
 * Run: wp eval-file tests/test-order-action-deliver-files.php
 *
 * The action route used to submit an empty file list, so an app's files[] vanished while the
 * answer was 200 and the buyer was told a delivery was ready.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f        = wpss_test_fixtures();
$order_id = ( $f->order )( array( 'status' => 'in_progress', 'customer_id' => $f->buyer, 'vendor_id' => $f->vendor, 'service_id' => ( $f->service )( 'publish' ) ) );
$count    = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_deliveries WHERE order_id = %d", $order_id ) );
$status   = static fn() => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $order_id ) );

$tmp = wp_tempnam( 'qa-deliver' );
file_put_contents( $tmp, 'MZ not a real program' );
$bad = array( 'name' => 'qa-setup.exe', 'type' => 'application/x-msdownload', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) );

wp_set_current_user( (int) $f->vendor );
$call = static function ( array $files ) use ( $order_id ) {
	$request = new WP_REST_Request( 'POST', "/wpss/v1/orders/{$order_id}/deliver" );
	$request->set_param( 'message', 'Here is the work' );
	$request->set_file_params( $files ? array( 'files' => array(
		'name'     => array( $files[0]['name'] ),
		'type'     => array( $files[0]['type'] ),
		'tmp_name' => array( $files[0]['tmp_name'] ),
		'error'    => array( 0 ),
		'size'     => array( $files[0]['size'] ),
	) ) : array() );
	return rest_do_request( $request );
};

$response = $call( array( $bad ) );
$check( 'a refused file answers 4xx, not 200', $response->get_status() >= 400 && $response->get_status() < 500, (string) $response->get_status() );
$check( 'the seller is told which file', false !== strpos( wp_json_encode( $response->get_data() ), 'qa-setup.exe' ), wp_json_encode( $response->get_data() ) );
$check( 'no empty delivery was saved and the order did not move', 0 === $count() && 'in_progress' === $status(), $count() . ' ' . $status() );

$response = $call( array() );
$check( 'a delivery with no files still goes through', 200 === $response->get_status() && 1 === $count(), (string) $response->get_status() );

// A good upload is not exercised here: PHP only moves genuine HTTP uploads, so it needs a browser or curl run.
@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $order_id ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
