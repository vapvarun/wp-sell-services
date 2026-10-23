<?php
/**
 * A ruling that leaves an already-completed order completed (in favour of the
 * vendor, or mutual) must not announce completion a second time: no second
 * wpss_order_completed, no second "Order completed" notice or email, one
 * earning row, and the original completed_at kept. Withdrawing a dispute on a
 * completed order sends no completion email either. Opening a dispute emails
 * the owner once, to the dispute admin email when set.
 *
 * Run: wp eval-file tests/test-dispute-ruling-on-completed-order-fires-once.php
 *
 * Seeds two throwaway users and QA orders; removes them after.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$orders   = $wpdb->prefix . 'wpss_orders';
$disputes = $wpdb->prefix . 'wpss_disputes';
$notes    = $wpdb->prefix . 'wpss_notifications';
$ledger   = $wpdb->prefix . 'wpss_wallet_transactions';
// Real users: the order emails are only sent to members who exist. Swept by exit-on-fail.
$buyer    = (int) wp_create_user( 'qa_rul_buyer_' . wp_rand(), wp_generate_password(), 'qa-rul-buyer-' . wp_rand() . '@example.test' );
$vendor   = (int) wp_create_user( 'qa_rul_vendor_' . wp_rand(), wp_generate_password(), 'qa-rul-vendor-' . wp_rand() . '@example.test' );
$made     = array();

// Record every email and send none.
$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);
$completed_mails = static function () use ( &$mails ): int {
	return count( array_filter( $mails, static fn( $m ) => false !== stripos( (string) $m['subject'], 'Completed' ) ) );
};
$mails_to = static function ( string $to ) use ( &$mails ): int {
	return count( array_filter( $mails, static fn( $m ) => in_array( $to, (array) $m['to'], true ) ) );
};
$saved_dispute_email = get_option( 'wpss_dispute_admin_email', null );
update_option( 'wpss_dispute_admin_email', 'qa-disputes@example.test' );

$service = new DisputeService();
$saved   = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved, array( 'allow_disputes' => 1, 'dispute_window_days' => 14 ) ) );
wp_set_current_user( 1 );

// [resolution, status before the dispute]. The in_progress row is the control: a ruling that
// completes an order for the first time must still announce and credit it, once.
// 'withdraw' is the opener cancelling the dispute: the order goes back the same quiet way.
foreach ( array( array( DisputeService::RESOLUTION_FAVOR_VENDOR, 'completed' ), array( DisputeService::RESOLUTION_MUTUAL, 'completed' ), array( 'withdraw', 'completed' ), array( DisputeService::RESOLUTION_FAVOR_VENDOR, 'in_progress' ) ) as list( $resolution, $before ) ) {
	$first        = 'in_progress' === $before;
	$completed_at = $first ? null : gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( $orders, array( 'order_number' => 'QA-RUL-' . wp_rand(), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'subtotal' => 100, 'total' => 100, 'vendor_earnings' => 90, 'platform_fee' => 10, 'currency' => 'USD', 'status' => $before, 'payment_status' => 'paid', 'completed_at' => $completed_at, 'created_at' => current_time( 'mysql' ) ) );
	$order_id = (int) $wpdb->insert_id;
	$made[]   = $order_id;

	// The first completion already happened and credited the vendor once.
	if ( ! $first ) {
		( new \WPSellServices\Services\CommissionService() )->record( $order_id );
	}

	$mails      = array();
	$dispute_id = (int) $service->open( $order_id, $buyer, 'other', 'contract' );
	$check( "[$resolution/$before] opening the dispute emails the owner once, at the dispute admin email", 1 === $mails_to( 'qa-disputes@example.test' ) && 0 === $mails_to( (string) get_option( 'admin_email' ) ), 'dispute email ' . $mails_to( 'qa-disputes@example.test' ) . ', admin email ' . $mails_to( (string) get_option( 'admin_email' ) ) );

	$mails = array();
	$fired      = 0;
	$count      = static function ( $id ) use ( &$fired, $order_id ) {
		if ( (int) $id === $order_id ) {
			++$fired;
		}
	};
	add_action( 'wpss_order_completed', $count );
	if ( 'withdraw' === $resolution ) {
		$resolved = ! empty( ( new DisputeWorkflowManager() )->cancel( $dispute_id, $buyer, 'changed my mind' )['success'] );
	} else {
		$resolved = $service->resolve( $dispute_id, $resolution, 'ruling', 0.0 );
	}
	remove_action( 'wpss_order_completed', $count );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$status  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $order_id ) );
	$done_at = $wpdb->get_var( $wpdb->prepare( "SELECT completed_at FROM {$orders} WHERE id = %d", $order_id ) );
	$earning = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $order_id ) );
	$notices = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notes} WHERE user_id = %d AND title = 'Order completed' AND data LIKE %s", $buyer, '%"order_id":' . $order_id . '%' ) );
	// phpcs:enable

	$check( "[$resolution/$before] ruling applied and order is completed", $resolved && 'completed' === $status, "resolved=" . var_export( $resolved, true ) . " status=$status" );
	$check( "[$resolution/$before] wpss_order_completed fires " . ( $first ? 'once' : 'no second time' ), $first ? 1 === $fired : 0 === $fired, "fired $fired" );
	$check( "[$resolution/$before] one earning row", 1 === $earning, "rows $earning" );
	$check( "[$resolution/$before] Order completed emails: " . ( $first ? 'sent' : 'none new' ), $first ? $completed_mails() >= 1 : 0 === $completed_mails(), 'emails ' . $completed_mails() );
	if ( ! $first ) {
		$check( "[$resolution/$before] original completed_at kept", $completed_at === $done_at, "was $completed_at now $done_at" );
	}
	$check( "[$resolution/$before] Order completed notices: " . ( $first ? 'one' : 'none new' ), $first ? $notices >= 1 : 0 === $notices, "notices $notices" );
}

update_option( 'wpss_orders', $saved );
null === $saved_dispute_email ? delete_option( 'wpss_dispute_admin_email' ) : update_option( 'wpss_dispute_admin_email', $saved_dispute_email );
foreach ( $made as $id ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$did = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$disputes} WHERE order_id = %d", $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $did ) );
	$wpdb->delete( $disputes, array( 'order_id' => $id ) );
	$wpdb->delete( $ledger, array( 'reference_type' => 'order', 'reference_id' => $id ) );
	$wpdb->delete( $orders, array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
	// phpcs:enable
}
foreach ( array( $buyer, $vendor ) as $u ) {
	$wpdb->delete( $notes, array( 'user_id' => $u ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $ledger, array( 'user_id' => $u ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
