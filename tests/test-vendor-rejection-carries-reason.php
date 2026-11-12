<?php
/**
 * Rejecting or suspending a vendor carries the admin's reason (Basecamp 10475573933).
 *
 * Run: wp eval-file tests/test-vendor-rejection-carries-reason.php
 *
 * The emails already had a slot for the reason; nothing ever filled it, so a
 * rejected applicant read "unable to approve" with no why. The reason now travels
 * from the admin screen through VendorService::set_status() and its hook to the
 * email, the in-app row and the audit row. It is not stored on the profile.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\VendorService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = (string) $atts['message'];

		return true;
	},
	10,
	2
);

wp_set_current_user( 1 );
$service = new VendorService();
$profile = $wpdb->prefix . 'wpss_vendor_profiles';

foreach ( array( 'rejected' => 'Your portfolio links do not open.', 'suspended' => 'Three late deliveries this month.' ) as $status => $reason ) {
	$user = (int) wp_insert_user( array( 'user_login' => 'qa_vr_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-vr-' . wp_generate_password( 6, false, false ) . '@example.test', 'role' => 'subscriber' ) );
	wpss_test_activate_vendor( $user );
	if ( 'rejected' === $status ) {
		$wpdb->update( $profile, array( 'status' => 'pending' ), array( 'user_id' => $user ) );
	}

	$mails = array();
	$check( "{$status}: set_status() succeeds", $service->set_status( $user, $status, $reason ) );
	$check( "{$status}: the email carries the reason", 1 === count( $mails ) && false !== strpos( $mails[0], esc_html( $reason ) ), (string) count( $mails ) );

	$row = (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user ) );
	$check( "{$status}: the in-app row carries the reason", false !== strpos( $row, $reason ), $row );

	$audit = (string) $wpdb->get_var( $wpdb->prepare( "SELECT context FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'vendor' AND object_id = %d ORDER BY id DESC LIMIT 1", $user ) );
	$check( "{$status}: the audit row keeps it as the note", $reason === ( json_decode( $audit, true )['note'] ?? '' ), $audit );

	$mails = array();
	$service->set_status( $user, $status, $reason );
	$check( "{$status}: repeating the decision sends nothing", ! $mails );

	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $user ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'vendor', 'object_id' => $user ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user );
}

// The bulk action carries one reason to every vendor in the batch (10504265808).
$bulk_users = array();
for ( $i = 0; $i < 2; $i++ ) {
	$bulk_users[] = $user = (int) wp_insert_user( array( 'user_login' => 'qa_vb_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-vb-' . wp_generate_password( 6, false, false ) . '@example.test', 'role' => 'subscriber' ) );
	wpss_test_activate_vendor( $user );
}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'ajax-done' ); } );
add_filter( 'wp_die_handler', static fn() => static function () { throw new RuntimeException( 'ajax-done' ); } );
$_POST = $_REQUEST = array( 'nonce' => wp_create_nonce( 'wpss_vendors_bulk' ), 'bulk_action' => 'suspend', 'vendor_ids' => $bulk_users, 'reason' => 'Bulk review: refund complaints.' );
$mails = array();
ob_start();
try {
	( new \WPSellServices\Admin\Pages\VendorsPage() )->ajax_bulk_update_vendor_status();
} catch ( RuntimeException $e ) {
	unset( $e );
}
ob_end_clean();
$with_reason = array_filter( $mails, static fn( $m ) => false !== strpos( $m, 'Bulk review: refund complaints.' ) );
$check( 'bulk suspend: every vendor in the batch gets the reason in their email', 2 === count( $with_reason ), (string) count( $with_reason ) . ' of ' . count( $mails ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $bulk_users as $user ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $user ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'vendor', 'object_id' => $user ) );
	wp_delete_user( $user );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
