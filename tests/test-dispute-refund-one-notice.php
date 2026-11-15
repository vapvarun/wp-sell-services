<?php
/**
 * A dispute ruling that refunds money sends "Dispute resolved" only, not a second refund notice.
 *
 * Run: wp eval-file tests/test-dispute-refund-one-notice.php
 *
 * Other refund paths (admin refund, gateway webhook) keep their "Order refunded" and
 * "Order partly refunded" notices; only the ruling itself is flagged.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeWorkflowManager;
use WPSellServices\Services\NotificationService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$order = $wpdb->get_row( "SELECT id, customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
if ( ! $order ) {
	echo "SKIP  needs an existing order\n";
	return;
}
$order_id = (int) $order->id;

$count_refund_notices = static function () use ( $order_id ): int {
	$before = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}wpss_notifications WHERE title LIKE '%refunded%'" ); // phpcs:ignore
	( new NotificationService() )->notify_order_status( $order_id, 'partially_refunded', 'completed' );
	$after = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}wpss_notifications WHERE title LIKE '%refunded%'" ); // phpcs:ignore
	return $after - $before;
};

$plain   = $count_refund_notices();
$ruling  = DisputeWorkflowManager::while_resolving( $order_id, $count_refund_notices );
$check( 'an ordinary partial refund still announces itself to both parties', $plain >= 2, (string) $plain );
$check( 'the same status change inside a dispute ruling announces nothing', 0 === $ruling, (string) $ruling );
$check( 'the flag is cleared after the ruling', false === DisputeWorkflowManager::is_resolving_order( $order_id ) );

$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE title LIKE '%partly refunded%' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)" ); // phpcs:ignore
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
