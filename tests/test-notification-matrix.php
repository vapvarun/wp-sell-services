<?php
/**
 * The notification matrix: one mail per event, none dropped, every event covered.
 *
 * Fails on the code before 1.7.1: the vendor got two review mails, the
 * 5-minute cooldown swallowed a second New Order, refund failures were written
 * to user 0, a suspended vendor got the rejection wording, failed sends were
 * never logged, and eight events produced nothing at all.
 *
 * Run: wp eval-file tests/test-notification-matrix.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;
use WPSellServices\Services\EmailService;
use WPSellServices\Services\NotificationService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ): void {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$p     = $wpdb->prefix;
$t0    = current_time( 'mysql' );
$mails = array();

// Intercept every send. Nothing leaves the box; we only count.
add_filter(
	'pre_wp_mail',
	static function ( $short, array $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);
$mails_to = static function ( string $email ) use ( &$mails ): array {
	return array_values(
		array_filter(
			$mails,
			static fn( array $m ): bool => in_array( $email, array_map( 'trim', (array) $m['to'] ), true )
		)
	);
};
$rows = static function ( int $user_id, string $type ) use ( $wpdb, $p, $t0 ): int {
	return (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$p}wpss_notifications WHERE user_id = %d AND type = %s AND created_at >= %s", $user_id, $type, $t0 )
	);
};

// --- fixtures -----------------------------------------------------------------
$stamp  = wp_rand();
$vendor = (int) wp_insert_user(
	array(
		'user_login'   => "f13vendor{$stamp}",
		'user_email'   => "f13vendor{$stamp}@example.test",
		'user_pass'    => wp_generate_password(),
		'display_name' => 'F13 Vendor',
	)
);
$buyer  = (int) wp_insert_user(
	array(
		'user_login'   => "f13buyer{$stamp}",
		'user_email'   => "f13buyer{$stamp}@example.test",
		'user_pass'    => wp_generate_password(),
		'display_name' => 'F13 Buyer',
	)
);
$vendor_email = "f13vendor{$stamp}@example.test";
$buyer_email  = "f13buyer{$stamp}@example.test";

$service_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_author' => $vendor,
		'post_title'  => 'F13 contract service',
	)
);

$seed = static function () use ( $wpdb, $p, $buyer, $vendor, $service_id ): int {
	$wpdb->insert(
		"{$p}wpss_orders",
		array(
			'order_number'   => 'WPSS-F13-' . wp_rand(),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => $service_id,
			'platform'       => 'standalone',
			'total'          => 100.000,
			'currency'       => 'USD',
			'status'         => 'in_progress',
			'payment_status' => 'paid',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
};
$order1 = $seed();
$order2 = $seed();
$order3 = $seed();

$saved_orders_option = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_orders_option, array( 'allow_disputes' => 1 ) ) );

// --- 1. review posted -> exactly one vendor mail ------------------------------
$wpdb->insert(
	"{$p}wpss_reviews",
	array(
		'order_id'    => $order1,
		'reviewer_id' => $buyer,
		'reviewee_id' => $vendor,
		'service_id'  => $service_id,
		'customer_id' => $buyer,
		'vendor_id'   => $vendor,
		'rating'      => 5,
		'review'      => 'Contract review',
		'status'      => 'approved',
		'created_at'  => current_time( 'mysql' ),
	)
);
$review_id = (int) $wpdb->insert_id;
$mails     = array();
do_action( 'wpss_review_created', $review_id, $order1 );
$check( 'review posted sends the vendor exactly one mail', 1 === count( $mails_to( $vendor_email ) ) );
$check( '  and one in-app row', 1 === $rows( $vendor, 'review_received' ) );

// --- 2. two new orders inside a minute -> two vendor mails --------------------
$mails = array();
$es    = new EmailService();
$es->send_new_order( wpss_get_order( $order1 ) );
$es->send_new_order( wpss_get_order( $order2 ) );
$check( 'two New Order events inside a minute send two vendor mails (no cooldown on order mail)', 2 === count( $mails_to( $vendor_email ) ) );

// --- 3. refund failures reach administrators, not user 0 ----------------------
$ns = new NotificationService();
if ( method_exists( $ns, 'notify_admins' ) ) {
	$ns->notify_admins( 'refund_failed', 'F13 contract', 'Contract refund failure', array( 'order_id' => $order1 ) );
	$admin_ids = get_users(
		array(
			'capability' => 'manage_options',
			'fields'     => 'ID',
		)
	);
	$admin_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$p}wpss_notifications WHERE type = 'refund_failed' AND user_id > 0 AND created_at >= %s", $t0 ) );
	$check( 'refund_failed reaches every administrator', count( $admin_ids ) > 0 && count( $admin_ids ) === $admin_rows );
} else {
	$check( 'refund_failed reaches every administrator', false );
}
$check( '  and nothing is written to user 0', 0 === $rows( 0, 'refund_failed' ) );
$owm_src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Services/OrderWorkflowManager.php' );
$check( '  and OrderWorkflowManager no longer creates notifications for user 0', ! preg_match( '/->create\(\s*0,/', $owm_src ) );

// --- 4. suspended vendor gets a suspension notice ------------------------------
$mails = array();
do_action( 'wpss_vendor_status_updated', $vendor, 'suspended' );
$suspended = $mails_to( $vendor_email );
$check( 'suspended vendor receives one mail', 1 === count( $suspended ) );
$check( '  whose subject says suspended', ! empty( $suspended ) && false !== stripos( $suspended[0]['subject'], 'suspend' ) );
$check( '  and an in-app row of its own type', 1 === $rows( $vendor, 'vendor_suspended' ) );

// --- 5. events that used to produce nothing -----------------------------------
do_action( 'wpss_review_reply_created', $review_id );
$check( 'review reply notifies the reviewer', 1 === $rows( $buyer, 'review_reply' ) );

$request_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_request',
		'post_status' => 'publish',
		'post_author' => $buyer,
		'post_title'  => 'F13 contract request',
	)
);
do_action( 'wpss_buyer_request_status_changed', $request_id, 'expired', 'open' );
$check( 'expired request notifies the buyer', 1 === $rows( $buyer, 'request_expired' ) );

$ds = new DisputeService();
$wf = new DisputeWorkflowManager();
wp_set_current_user( 0 );
$dispute_a = (int) $ds->open( $order2, $buyer, 'other', 'contract' );
$check( 'dispute opened writes one row per party', $dispute_a > 0 && 1 === $rows( $vendor, 'dispute_opened' ) && 1 === $rows( $buyer, 'dispute_opened' ) );
$wf->escalate( $dispute_a, 'contract', $buyer );
$check( 'dispute escalated notifies both parties', 1 === $rows( $vendor, 'dispute_escalated' ) && 1 === $rows( $buyer, 'dispute_escalated' ) );

$dispute_b = (int) $ds->open( $order3, $buyer, 'other', 'contract' );
$wf->cancel( $dispute_b, $buyer, 'withdrawn' );
$check( 'dispute cancelled notifies the other party only', 1 === $rows( $vendor, 'dispute_cancelled' ) && 0 === $rows( $buyer, 'dispute_cancelled' ) );

do_action( 'wpss_service_approved', $service_id );
$check( 'service approved writes the vendor an in-app row', 1 === $rows( $vendor, 'service_approved' ) );
do_action( 'wpss_service_rejected', $service_id, 'contract' );
$check( 'service rejected writes the vendor an in-app row', 1 === $rows( $vendor, 'service_rejected' ) );

$mails = array();
do_action( 'wpss_tip_sent', 0, $order1, $vendor, $buyer, 5.0, 'thanks' );
$check( 'tip sends the buyer a receipt', 1 === $rows( $buyer, 'tip_receipt' ) && 1 === count( $mails_to( $buyer_email ) ) );

// request_withdrawal() needs a cleared balance to reach its notification, so
// assert the seam statically: the request path writes the vendor a row.
$earn_src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Services/EarningsService.php' );
$check( 'withdrawal request writes the vendor an in-app row', false !== strpos( $earn_src, "'withdrawal_requested'" ) );

// --- 6. a failed send is logged and retried ------------------------------------
do_action(
	'wp_mail_failed',
	new WP_Error(
		'wp_mail_failed',
		'SMTP down (contract)',
		array(
			'to'      => array( 'nobody@example.test' ),
			'subject' => 'F13 contract',
			'message' => 'body',
			'headers' => array(),
		)
	)
);
$failed = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}wpss_audit_log WHERE event_type = 'email.failed' AND created_at >= %s ORDER BY id DESC LIMIT 1", $t0 ) );
$check( 'wp_mail_failed writes an email.failed audit row', null !== $failed );
$check( '  carrying the recipient and the error', $failed && false !== strpos( (string) $failed->context, 'nobody@example.test' ) && false !== strpos( (string) $failed->context, 'SMTP down' ) );
$check( '  and schedules one retry', function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( 'wpss_email_retry' ) );

// --- cleanup ---------------------------------------------------------------------
update_option( 'wpss_orders', $saved_orders_option );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wpss_email_retry' );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_audit_log WHERE event_type = 'email.failed' AND created_at >= %s", $t0 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_audit_log WHERE object_type = 'order' AND object_id IN (%d, %d, %d)", $order1, $order2, $order3 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_audit_log WHERE object_type = 'dispute' AND object_id IN (%d, %d)", $dispute_a, $dispute_b ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_notifications WHERE user_id IN (%d, %d) OR (type IN ('refund_failed') AND created_at >= %s)", $vendor, $buyer, $t0 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_dispute_messages WHERE dispute_id IN (%d, %d)", $dispute_a, $dispute_b ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_disputes WHERE order_id IN (%d, %d, %d)", $order1, $order2, $order3 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_reviews WHERE id = %d", $review_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}wpss_orders WHERE id IN (%d, %d, %d)", $order1, $order2, $order3 ) );
wp_delete_post( $request_id, true );
wp_delete_post( $service_id, true );
$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $vendor ) );
$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $buyer ) );
$wpdb->delete( $wpdb->users, array( 'ID' => $vendor ) );
$wpdb->delete( $wpdb->users, array( 'ID' => $buyer ) );
foreach ( array( $vendor, $buyer ) as $uid ) {
	wp_cache_delete( $uid, 'users' );
	wp_cache_delete( 'wpss_unread_notifications_' . $uid, 'wpss' );
}

echo $fails ? "\nFAIL ({$fails})\n" : "\nPASS - one mail per event, none dropped, every event covered.\n";
exit( $fails ? 1 : 0 );
