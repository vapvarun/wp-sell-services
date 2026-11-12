<?php
/**
 * Contacting a vendor notifies them once (Basecamp 10504407114).
 *
 * Run: wp eval-file tests/test-contact-vendor-notifies-once.php
 *
 * The form wrote its own bell entry and branded email, then the message it
 * creates fired the message hook, which added a second bell entry and a second
 * email.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$vendor_id = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->prefix}wpss_vendor_profiles WHERE status = 'active' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( ! $vendor_id ) {
	echo "SKIP  no active vendor\n";
	return;
}

// EmailService mutes the same mail to the same person for 5 minutes; start clean.
$vendor_email = get_userdata( $vendor_id )->user_email;
foreach ( array( 'vendor_contact', 'new_message' ) as $type ) {
	delete_transient( 'wpss_email_cooldown_' . md5( $vendor_email . '|' . $type ) );
}

$buyer = (int) wp_insert_user( array( 'user_login' => 'qa_cv_' . wp_generate_password( 5, false, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-cv-' . wp_generate_password( 5, false, false ) . '@example.test', 'role' => 'subscriber' ) );
wp_set_current_user( $buyer );

$mails = array();
add_filter( 'pre_wp_mail', static function ( $short, $atts ) use ( &$mails ) { $mails[] = $atts; return true; }, 10, 2 );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'ajax-done' ); } );
add_filter( 'wp_die_handler', static fn() => static function () { throw new RuntimeException( 'ajax-done' ); } );

$_POST['nonce']     = wp_create_nonce( 'wpss_service_nonce' );
$_POST['vendor_id'] = $vendor_id;
$_POST['message']   = 'Hello, is this still available?';
$_REQUEST           = $_POST;

ob_start();
try {
	( new \WPSellServices\Frontend\AjaxHandlers() )->contact_vendor();
} catch ( RuntimeException $e ) {
	unset( $e );
}
$out = (string) ob_get_clean();

$notes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND created_at >= %s", $vendor_id, gmdate( 'Y-m-d H:i:s', time() - 60 ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$to_vendor = array_filter( $mails, static fn( $m ) => in_array( get_userdata( $vendor_id )->user_email, (array) $m['to'], true ) );

$check( 'the contact call succeeded', false !== strpos( $out, '"success":true' ), substr( $out, 0, 200 ) );
$check( 'the vendor has one bell entry', 1 === $notes, (string) $notes );
$check( 'and receives one email', 1 === count( $to_vendor ), (string) count( $to_vendor ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND created_at >= %s", $vendor_id, gmdate( 'Y-m-d H:i:s', time() - 120 ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
