<?php
/**
 * The buyer is reminded the day before a delivered order completes on its own (Basecamp 10550887837).
 *
 * The auto-complete date showed only on the order page and in the Delivery Ready email; a buyer who
 * missed that email lost the revision they paid for without another word. The auto-complete sweep
 * now sends one reminder when the order completes within 24 hours: once per delivery (kept in the
 * order meta against the latest delivery id, so a revision delivery re-arms it), none when
 * auto-complete is off, and none when the Delivery notifications are switched off.
 *
 * Run: wp eval-file tests/test-auto-complete-reminder.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Services\OrderWorkflowManager;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f          = wpss_test_fixtures();
$order_id   = ( $f->order )( array( 'status' => 'pending_approval', 'paid_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$deliveries = array();
$deliver    = static function ( int $seconds_ago ) use ( $wpdb, $order_id, $f, &$deliveries ): void {
	$wpdb->insert(
		$wpdb->prefix . 'wpss_deliveries',
		array(
			'order_id'    => $order_id,
			'vendor_id'   => $f->vendor,
			'message'     => 'QA-RFT auto-complete reminder, deleted after the run.',
			'attachments' => '[]',
			'version'     => count( $deliveries ) + 1,
			'status'      => 'pending',
			'created_at'  => gmdate( 'Y-m-d H:i:s', time() - $seconds_ago ),
		)
	);
	$deliveries[] = (int) $wpdb->insert_id;
};
$buyer_mail = (string) get_userdata( $f->buyer )->user_email;
$mails      = 0;
$catch      = static function ( $short, array $atts ) use ( $buyer_mail, &$mails ) {
	if ( in_array( $buyer_mail, (array) $atts['to'], true ) && false !== stripos( (string) $atts['subject'] . $atts['message'], 'completes' ) ) {
		++$mails;
	}
	return true;
};
add_filter( 'pre_wp_mail', $catch, 10, 2 );

$rows       = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND type = 'auto_complete_reminder'", $f->buyer ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$run        = static function (): void {
	$m = new OrderWorkflowManager();
	method_exists( $m, 'send_auto_complete_reminders' ) ? $m->send_auto_complete_reminders() : $m->auto_complete_orders();
};
$orders_was = get_option( 'wpss_orders' );
$notif_was  = get_option( 'wpss_notifications' );
$days       = static function ( int $n ) use ( $orders_was ): void {
	update_option( 'wpss_orders', array_merge( (array) $orders_was, array( 'auto_complete_days' => $n ) ) );
};

try {
	$days( 3 );

	$deliver( HOUR_IN_SECONDS );
	$run();
	$check( 'A fresh delivery (completes in ~3 days): no reminder yet', 0 === $rows() );

	// Move the delivery back so the order completes in 12 hours.
	$wpdb->update( $wpdb->prefix . 'wpss_deliveries', array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS ) ), array( 'id' => $deliveries[0] ) );
	$run();
	$check( 'Completes within 24 hours: exactly one reminder', 1 === $rows(), (string) $rows() );
	$check( '  and one email to the buyer', 1 === $mails, (string) $mails );
	$msg = (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND type = 'auto_complete_reminder'", $f->buyer ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$label = wpss_datetime_label( wpss_get_auto_complete_at( wpss_get_order( $order_id ) ), '', (int) $f->buyer );
	$check( '  naming the same date the order page shows', false !== strpos( $msg, $label ), $msg . ' / ' . $label );

	$run();
	$check( 'Run again: still one', 1 === $rows() && 1 === $mails );
	$check( 'Through the real sweep too: still one', ( ( new OrderWorkflowManager() )->auto_complete_orders() || true ) && 1 === $rows() );

	// A revision: a later delivery, completing in 18 hours.
	$deliver( 3 * DAY_IN_SECONDS - 18 * HOUR_IN_SECONDS );
	$run();
	$check( 'A revision delivery re-arms it: one more', 2 === $rows() && 2 === $mails, $rows() . '/' . $mails );

	// Delivery notifications switched off by the owner: nothing.
	$deliver( 3 * DAY_IN_SECONDS - 20 * HOUR_IN_SECONDS );
	update_option( 'wpss_notifications', array_merge( (array) $notif_was, array( 'notify_delivery_submitted' => 0 ) ) );
	$run();
	$check( 'Delivery notifications off: no reminder', 2 === $mails, (string) $mails );
	update_option( 'wpss_notifications', $notif_was );

	$deliver( 3 * DAY_IN_SECONDS - 22 * HOUR_IN_SECONDS );
	$days( 0 );
	$run();
	$check( 'Auto-complete off: no reminder', 2 === $mails, (string) $mails );
} finally {
	remove_filter( 'pre_wp_mail', $catch, 10 );
	update_option( 'wpss_orders', $orders_was );
	update_option( 'wpss_notifications', $notif_was );
	foreach ( $deliveries as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'id' => $id ) );
	}
	if ( '1.0' === get_option( 'wpss_order_meta_table_version' ) ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $order_id ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
