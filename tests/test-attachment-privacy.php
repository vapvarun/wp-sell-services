<?php
/**
 * Every order attachment is private, and payout details are encrypted.
 *
 * Message, contact and dispute uploads used to land in the public media
 * library while deliveries were private; the dispute reply route accepted any
 * attachment id on the site; withdrawal bank details were plaintext JSON
 * (Basecamp 10264291163).
 *
 * Run: wp eval-file tests/test-attachment-privacy.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ConversationService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;
use WPSellServices\Services\EarningsService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

$buyer  = 999997; // Nobody. Rows are removed at the end.
$vendor = 999996;

$wpdb->insert(
	$wpdb->prefix . 'wpss_orders',
	array(
		'order_number'   => 'WPSS-ATTACH-CONTRACT-' . wp_rand(),
		'customer_id'    => $buyer,
		'vendor_id'      => $vendor,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

// --- message attachment is a private order record -------------------------
$record = array(
	'id'          => wp_generate_uuid4(),
	'name'        => 'brief.pdf',
	'type'        => 'application/pdf',
	'size'        => 12,
	'path'        => 'brief.pdf',
	'order_id'    => $order_id,
	'kind'        => 'message',
	'user_id'     => $buyer,
	'remote_path' => null,
	'provider'    => null,
);

$conversations = new ConversationService();
$conversation  = $conversations->create_for_order( $order_id );
$message       = $conversation ? $conversations->send_message( (int) $conversation->id, $buyer, 'see attached', array( $record ) ) : null;

$check( 'message with a stored record can be sent', null !== $message );

$stored = $message ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_messages WHERE id = %d", (int) $message->id ) ) : '';
$check( 'no public URL is stored on the message row', '' !== $stored && false === strpos( $stored, '/uploads/' ) && false === strpos( $stored, '"url"' ) );
$check( 'record kind is message', false !== strpos( $stored, '"kind":"message"' ) );

$found = function_exists( 'wpss_find_order_file' ) ? wpss_find_order_file( $order_id, $record['id'] ) : null;
$check( 'the order read gate can find a message attachment', null !== $found && 'message' === ( $found['kind'] ?? '' ) );

$html = $message ? wpss_render_message_row( $message, $buyer ) : '';
$check( 'message row links through the gated endpoint', false !== strpos( $html, 'action=wpss_order_file' ) );
$check( 'message row leaks no uploads path', false === strpos( $html, '/uploads/' ) );

$check( 'attachment helper accepts an order id', ( new ReflectionFunction( 'wpss_handle_message_attachments' ) )->getNumberOfParameters() >= 2 );

// --- dispute reply refuses a file that is not yours -----------------------
$saved_settings = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_settings, array( 'allow_disputes' => 1 ) ) );

$dispute_id = (int) ( new DisputeService() )->open( $order_id, $buyer, 'other', 'contract' );
$workflow   = new DisputeWorkflowManager();

$check( 'dispute opens', $dispute_id > 0 );

wp_set_current_user( 0 );
$foreign = $workflow->submit_response( $dispute_id, $vendor, 'not mine', array( $record['id'] ) );
$check( 'reply with another user\'s file id is refused', empty( $foreign['success'] ) && 'forbidden' === ( $foreign['code'] ?? '' ) );

$nowhere = $workflow->submit_response( $dispute_id, $buyer, 'ghost', array( 'no-such-file' ) );
$check( 'reply with an unknown file id is refused', empty( $nowhere['success'] ) );

$own = $workflow->submit_response( $dispute_id, $buyer, 'mine', array( $record['id'] ) );
$check( 'reply with your own file id is accepted', ! empty( $own['success'] ) );

$reply = ! empty( $own['message_id'] ) ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_dispute_messages WHERE id = %d", (int) $own['message_id'] ) ) : '';
$check( 'the accepted reply stores the record, not a bare id', false !== strpos( $reply, '"kind":"message"' ) );

// --- withdrawal details are encrypted at rest -----------------------------
$has_crypto = function_exists( 'wpss_encrypt_secret' ) && function_exists( 'wpss_decrypt_secret' );
$check( 'secret helpers exist', $has_crypto );

$plain = wp_json_encode( array( 'bank_name' => 'Test Bank', 'account_number' => '12345678' ) );

if ( $has_crypto ) {
	$enc = wpss_encrypt_secret( $plain );
	$check( 'encrypted value carries the enc: prefix', 0 === strpos( $enc, 'enc:' ) );
	$check( 'encrypted value is not readable JSON', null === json_decode( $enc, true ) && false === strpos( $enc, 'Test Bank' ) );
	$check( 'decrypt round-trips', wpss_decrypt_secret( $enc ) === $plain );
	$check( 'a fresh IV per value', wpss_encrypt_secret( $plain ) !== $enc );
	$check( 'legacy plaintext still reads as itself', wpss_decrypt_secret( $plain ) === $plain );
}

$wpdb->insert(
	$wpdb->prefix . 'wpss_withdrawals',
	array(
		'vendor_id'  => $vendor,
		'amount'     => 10,
		'method'     => 'bank_transfer',
		'details'    => $plain,
		'status'     => 'pending',
		'created_at' => current_time( 'mysql' ),
	)
);
$withdrawal_id = (int) $wpdb->insert_id;

if ( function_exists( 'wpss_encrypt_legacy_withdrawal_details' ) ) {
	wpss_encrypt_legacy_withdrawal_details();
}

$plaintext_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_withdrawals WHERE details IS NOT NULL AND details <> '' AND details NOT LIKE 'enc:%'" );
$check( 'upgrade leaves no plaintext details rows', 0 === $plaintext_rows );

$listed = ( new EarningsService() )->get_withdrawals( $vendor, array( 'limit' => 5 ) );
$check( 'vendor payout screen still reads the details', 'Test Bank' === ( $listed[0]['details']['bank_name'] ?? '' ) );

// --- /media refuses order contexts ----------------------------------------
wp_set_current_user( 1 );
$request = new WP_REST_Request( 'POST', '/wpss/v1/media' );
$request->set_param( 'context', 'order' );
$response = rest_do_request( $request );
$check( 'POST /media with context=order is a 400 naming the order routes', 400 === $response->get_status() && 'wpss_order_upload_context' === ( $response->as_error() ? $response->as_error()->get_error_code() : '' ) );

// --- cleanup ----------------------------------------------------------------
update_option( 'wpss_orders', $saved_settings );
$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $withdrawal_id ) );
if ( $dispute_id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $dispute_id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'id' => $dispute_id ) );
}
if ( $conversation ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => (int) $conversation->id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => (int) $conversation->id ) );
}
$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $order_id ) );
foreach ( array( $buyer, $vendor ) as $uid ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $uid ) );
}
if ( function_exists( 'wpss_rmdir_recursive' ) ) {
	wpss_rmdir_recursive( wpss_get_order_files_dir() . $order_id . '/' );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
