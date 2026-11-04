<?php
/**
 * A long email is still retried, and a long held message still sent; other plugins' mail is not ours to retry.
 *
 * The retry job and the held new-message job carried the mail body as their
 * Action Scheduler arguments. Action Scheduler drops any action whose args
 * encode to more than 8000 characters (it logs and returns 0), so a long
 * email was never retried and a long chat message was never emailed, while
 * the code reported it queued. The retry also fired for every failed mail on
 * the site - password resets included - copying it into the actions table.
 * Both jobs now carry only a key to a stashed payload, and only our own mail
 * is retried. A held job whose payload is gone sends nothing.
 *
 * Run: wp eval-file tests/test-mail-jobs-carry-no-mail-in-args.php
 *
 * Sends nothing: wp_mail is short-circuited. Queues and deletes its own jobs.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EmailService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$marker = 'QA-MAIL-JOB-' . wp_generate_password( 6, false );
$long   = $marker . ' ' . str_repeat( 'A long message that a buyer might really write. ', 250 ); // ~12k chars.

// The mail server: refuses while $refuse is set, records what it is handed.
$refuse = true;
$sent   = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$refuse, &$sent ) {
		if ( $refuse ) {
			do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'refused by test', $atts + array( 'to' => (array) $atts['to'] ) ) );
			return false;
		}
		$sent[] = $atts;
		return true;
	},
	10,
	2
);

$jobs = static function ( string $hook ): array {
	return as_get_scheduled_actions( array( 'hook' => $hook, 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => 100 ), 'ids' );
};
$before_retry = $jobs( EmailService::RETRY_HOOK );

// 1. Our long email fails: a retry is queued, with a key for args, and delivers the whole mail.
( new EmailService() )->send( 'qa-mail-job@example.test', $marker, 'generic', array( 'email_heading' => 'QA', 'message' => $long, 'content' => $long ) );
$new = array_values( array_diff( $jobs( EmailService::RETRY_HOOK ), $before_retry ) );
$check( 'a failed long email of ours is queued for retry', 1 === count( $new ), count( $new ) . ' jobs' );
$args = $new ? ActionScheduler::store()->fetch_action( $new[0] )->get_args() : array();
$check( 'its job carries a key, not the mail', 1 === count( $args ) && strlen( (string) wp_json_encode( $args ) ) < 100, (string) wp_json_encode( $args ) );

$refuse = false;
if ( $new ) {
	do_action_ref_array( EmailService::RETRY_HOOK, $args );
	ActionScheduler::store()->delete_action( $new[0] );
}
$check( 'the retry sends the original mail', 1 === count( $sent ) && $marker === $sent[0]['subject'] && false !== strpos( (string) $sent[0]['message'], 'A long message' ) );

// 2. A job queued before this change (the mail in its args) still sends.
$sent = array();
do_action( EmailService::RETRY_HOOK, array( 'qa-mail-job@example.test' ), 'legacy ' . $marker, 'body', array() );
$check( 'a pre-1.9.2 retry job still sends', 1 === count( $sent ) && 'legacy ' . $marker === $sent[0]['subject'] );

// 3. Someone else's mail fails: not ours to retry.
$refuse = true;
$before = $jobs( EmailService::RETRY_HOOK );
wp_mail( 'qa-mail-job@example.test', 'Password reset ' . $marker, 'reset key: abc123' );
$check( "another plugin's failed mail is not queued", array() === array_diff( $jobs( EmailService::RETRY_HOOK ), $before ) );

// 4. A long chat message held by the message-email delay still reaches the inbox.
$refuse = false;
$sent   = array();
global $wpdb;
// An order with no thread yet: a thread the vendor has read rightly holds the mail back.
$order_id = (int) $wpdb->get_var( "SELECT o.id FROM {$wpdb->prefix}wpss_orders o JOIN {$wpdb->users} c ON c.ID = o.customer_id JOIN {$wpdb->users} v ON v.ID = o.vendor_id WHERE o.customer_id <> o.vendor_id AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}wpss_conversations cv WHERE cv.order_id = o.id ) ORDER BY o.id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$order    = wpss_get_order( $order_id );
$check( 'found an order between two people', null !== $order );
$to       = get_userdata( (int) $order->vendor_id )->user_email;
$cooldown = 'wpss_email_cooldown_' . md5( $to . '|' . EmailService::TYPE_NEW_MESSAGE );
delete_transient( $cooldown );

$delay = static fn() => 10;
add_filter( 'wpss_message_email_delay_minutes', $delay );
$before_held = $jobs( 'wpss_send_delayed_message_email' );
( new EmailService() )->send_new_message( $order_id, (int) $order->customer_id, $long );
remove_filter( 'wpss_message_email_delay_minutes', $delay );
$held = array_values( array_diff( $jobs( 'wpss_send_delayed_message_email' ), $before_held ) );
$check( 'a long held message is queued', 1 === count( $held ), count( $held ) . ' jobs' );
$held_args = $held ? ActionScheduler::store()->fetch_action( $held[0] )->get_args() : array();
$check( 'its job carries no message text', strlen( (string) wp_json_encode( $held_args ) ) < 120, (string) wp_json_encode( $held_args ) );

add_filter( 'wpss_should_skip_message_email', '__return_false' );
if ( $held ) {
	do_action_ref_array( 'wpss_send_delayed_message_email', $held_args );
	ActionScheduler::store()->delete_action( $held[0] );
}
$check( 'and is emailed with the whole message', 1 === count( $sent ) && false !== strpos( (string) $sent[0]['message'], $marker ), count( $sent ) . ' sent' );
delete_transient( $cooldown );

// 5. The same job firing again (its payload already taken) sends nothing, not an empty message.
$sent = array();
if ( $held_args ) {
	do_action_ref_array( 'wpss_send_delayed_message_email', $held_args );
}
$check( 'a held job that runs twice does not send a second, empty email', array() === $sent, count( $sent ) . ' sent' );
delete_transient( $cooldown );
remove_filter( 'wpss_should_skip_message_email', '__return_false' );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
