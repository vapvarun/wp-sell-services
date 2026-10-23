<?php
/**
 * The site owner is emailed when a member files a report or opens a dispute:
 * once per filing, to the dispute admin email when set, else the site admin
 * email, and to nobody when the recipient filter returns something invalid.
 *
 * Run: wp eval-file tests/test-admin-told-of-filing.php
 *
 * Captures mail instead of sending it, fires the real `wpss_report_filed` hook,
 * and deletes the audit rows it caused and restores the dispute email option on
 * any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\NotificationService;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$audit        = $wpdb->prefix . 'wpss_audit_log';
$audit_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$audit}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$old_option   = get_option( 'wpss_dispute_admin_email', null );

$restore = static function () use ( $wpdb, $audit, $audit_before, $old_option ) {
	null === $old_option ? delete_option( 'wpss_dispute_admin_email' ) : update_option( 'wpss_dispute_admin_email', $old_option );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE id > %d", $audit_before ) );
};

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

wp_set_current_user( 1 );
delete_option( 'wpss_dispute_admin_email' );

// Report: the real hook, the site admin email.
do_action( 'wpss_report_filed', 999999, 'service', 1, 1 );
$check( 'a filed report sends one email', 1 === count( $mails ), (string) count( $mails ) );
$check( 'to the site admin email', ! empty( $mails ) && get_option( 'admin_email' ) === ( (array) $mails[0]['to'] )[0] );
$check( 'the subject says it is a report', ! empty( $mails ) && false !== strpos( $mails[0]['subject'], 'New member report #999999' ), $mails[0]['subject'] ?? '' );

// Dispute: the dispute admin email wins.
$mails = array();
update_option( 'wpss_dispute_admin_email', 'disputes@example.test' );
( new NotificationService() )->notify_admin_of_filing( 'dispute', 77, 1, '#5' );
$check( 'a dispute sends one email', 1 === count( $mails ), (string) count( $mails ) );
$check( 'to the dispute admin email when set', ! empty( $mails ) && 'disputes@example.test' === ( (array) $mails[0]['to'] )[0] );
$check( 'the subject says it is a dispute', ! empty( $mails ) && false !== strpos( $mails[0]['subject'], 'New dispute opened #77' ), $mails[0]['subject'] ?? '' );

// Negative: an invalid recipient from the filter sends nothing.
$mails = array();
$bad   = static fn() => 'not-an-email';
add_filter( 'wpss_admin_filing_recipient', $bad );
( new NotificationService() )->notify_admin_of_filing( 'report', 78, 1, 'x' );
remove_filter( 'wpss_admin_filing_recipient', $bad );
$check( 'an invalid recipient sends nothing', 0 === count( $mails ), (string) count( $mails ) );
