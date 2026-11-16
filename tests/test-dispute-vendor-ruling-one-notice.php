<?php
/**
 * A dispute ruling for the vendor sends "Dispute resolved" only, not an extra "Order completed" notice.
 *
 * Run: wp eval-file tests/test-dispute-vendor-ruling-one-notice.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Services\DisputeWorkflowManager;
use WPSellServices\Services\NotificationService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f   = wpss_test_fixtures();
$sid = (int) ( $f->service )( 'publish' );
$oid = (int) ( $f->order )( array( 'service_id' => $sid, 'status' => 'completed' ) );

$count = static function () use ( $oid, $wpdb ): int {
	$t      = $wpdb->prefix . 'wpss_notifications';
	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE title LIKE 'Order completed%' OR title LIKE 'Order Completed%'" ); // phpcs:ignore
	( new NotificationService() )->notify_order_status( $oid, 'completed', 'disputed' );
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE title LIKE 'Order completed%' OR title LIKE 'Order Completed%'" ) - $before; // phpcs:ignore
};

$plain  = $count();
$ruling = DisputeWorkflowManager::while_resolving( $oid, $count );
$check( 'an ordinary completion still tells both parties', $plain >= 2, (string) $plain );
$check( 'the completion a ruling causes announces nothing', 0 === $ruling, (string) $ruling );

$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/DisputeService.php' );
$check( 'favor_vendor completion runs inside while_resolving', (bool) preg_match( '/while_resolving\(\s*\$order_id,\s*fn\(\)\s*=>\s*\$this->order_service->update_status\(\s*\$order_id,\s*ServiceOrder::STATUS_COMPLETED/', $src ) );

$mail = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/EmailService.php' );
$check( 'the completed email is skipped while a ruling resolves the order', (bool) preg_match( '/is_resolving_order\(\s*\$order_id\s*\)\s*\)\s*\{\s*\$this->send_order_completed/', $mail ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
