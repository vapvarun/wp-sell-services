<?php
/**
 * A delivery with a file the site refuses is refused whole, with the file named. It used to be
 * saved without that file and the buyer received an incomplete delivery (vendor sweep, 1.9.0).
 *
 * Run: wp eval-file tests/test-delivery-refuses-bad-file.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f        = wpss_test_fixtures();
$order_id = ( $f->order )( array( 'status' => 'in_progress', 'customer_id' => $f->buyer, 'vendor_id' => $f->vendor, 'service_id' => ( $f->service )( 'publish' ) ) );
$count    = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_deliveries WHERE order_id = %d", $order_id ) );

$tmp = wp_tempnam( 'qa-delivery' );
file_put_contents( $tmp, 'MZ not a real program' );
$bad = array( 'name' => 'qa-setup.exe', 'type' => 'application/x-msdownload', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) );

$service = new \WPSellServices\Services\DeliveryService();
$ok      = $service->submit( $order_id, 'Here is the work', array( $bad ) );
$check( 'a refused file stops the delivery', false === $ok );
$check( 'the seller is told which file', false !== strpos( $service->get_last_error(), 'qa-setup.exe' ) );
$check( 'no half delivery was saved', 0 === $count() );

$ok = $service->submit( $order_id, 'Here is the work', array() );
$check( 'a delivery with no files still goes through', true === $ok && '' === $service->get_last_error() && 1 === $count() );

@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $order_id ) );

exit( $fails ? 1 : 0 );
