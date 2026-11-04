<?php
/**
 * A revision request or a delivery announces itself once.
 *
 * The buyer's note is saved as a revision message in the order chat, and that
 * message used to fire the generic "new message" bell on top of the dedicated
 * revision bell, so the vendor got two notifications for one request. The
 * delivery message did the same to the buyer, and both also sent the generic
 * "new message" email beside their own delivery / revision email.
 *
 * Run: wp eval-file tests/test-revision-one-bell.php
 *
 * Seeds one buyer, one vendor and one QA-RFT order; removes them after.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\Message;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\OrderService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$mk      = static fn( string $n ) => wp_insert_user( array( 'user_login' => "qa_rft_rb_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-rft-rb-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$buyer   = $mk( 'b' );
$vendor  = $mk( 'v' );
$orders  = $wpdb->prefix . 'wpss_orders';
$notes   = $wpdb->prefix . 'wpss_notifications';
$service = (int) get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-RB-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => $service, 'platform' => 'standalone', 'total' => 10, 'currency' => 'USD', 'status' => 'delivered', 'payment_status' => 'paid', 'revisions_included' => 2, 'revisions_used' => 0, 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
$order_id = (int) $wpdb->insert_id;
$types    = static fn() => $wpdb->get_col( $wpdb->prepare( "SELECT type FROM {$notes} WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB
$mails    = array();
add_action(
	'wpss_new_order_message',
	static function ( $order, $sender, $content ) use ( &$mails ) {
		$mails[] = (string) $content;
	},
	1,
	3
);

try {
	$ok = ( new OrderService() )->request_revision( $order_id, 'QA-RFT please change the colour' );
	$check( 'request_revision() succeeds', $ok );

	$got = $types();
	$check( 'vendor gets the revision bell', in_array( 'revision_requested', $got, true ) );
	$check( 'vendor gets no extra new_message bell (got: ' . implode( ',', $got ) . ')', ! in_array( 'new_message', $got, true ) );
	$check( 'and no generic new-message email', array() === $mails );

	$conv = ( new ConversationService() )->get_by_order( $order_id );
	$check( 'the note is still in the order chat', $conv && false !== strpos( (string) $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}wpss_messages WHERE conversation_id = %d AND type = 'revision'", $conv->id ) ), 'colour' ) ); // phpcs:ignore WordPress.DB

	// A delivery message: the buyer's bell and email come from the delivery itself.
	$buyer_bells = static fn() => $wpdb->get_col( $wpdb->prepare( "SELECT type FROM {$notes} WHERE user_id = %d", $buyer ) ); // phpcs:ignore WordPress.DB
	( new ConversationService() )->send_message( $conv->id, $vendor, 'QA-RFT here is the work', array(), Message::TYPE_DELIVERY );
	$check( 'a delivery message rings no new_message bell for the buyer', ! in_array( 'new_message', $buyer_bells(), true ) );
	$check( 'and sends no generic new-message email', array() === $mails );

	// Control: an ordinary chat message still rings the new_message bell and sends the email.
	( new ConversationService() )->send_message( $conv->id, $buyer, 'QA-RFT plain hello' );
	$check( 'a plain message still rings new_message', in_array( 'new_message', $types(), true ) );
	$check( 'and still sends the new-message email', array( 'QA-RFT plain hello' ) === $mails );
} finally {
	$conv = ( new ConversationService() )->get_by_order( $order_id );
	if ( $conv ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $conv->id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => $conv->id ) );
	}
	$wpdb->delete( $orders, array( 'id' => $order_id ) );
	$wpdb->delete( $notes, array( 'user_id' => $vendor ) );
	$wpdb->delete( $notes, array( 'user_id' => $buyer ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "FAILED {$fails}\n" : "OK\n";
