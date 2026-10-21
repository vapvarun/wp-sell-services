<?php
/**
 * Marking a withdrawal paid tells the vendor it was paid, not approved again (Basecamp 10402904790).
 *
 * Run: wp eval-file tests/test-withdrawal-paid-email.php
 *
 * Approve and mark paid each send one email with its own subject and heading, from its own template, and each
 * leaves its own in-app notification type. The test seeds one withdrawal for a throwaway vendor, captures
 * wp_mail, and removes what it made.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;
use WPSellServices\Services\EmailService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_wd_vendor_' . $suffix, 'user_email' => 'wpss_wd_vendor_' . $suffix . '@example.test' ) );
$mails  = array();
$grab   = static function ( $pre, $atts ) use ( &$mails ) {
	$mails[] = array( 'subject' => (string) ( $atts['subject'] ?? '' ), 'message' => (string) ( $atts['message'] ?? '' ) );

	return true;
};
add_filter( 'pre_wp_mail', $grab, 10, 2 );

$wpdb->insert(
	$wpdb->prefix . 'wpss_withdrawals',
	array(
		'vendor_id'  => $vendor->ID,
		'amount'     => 25.000,
		'method'     => 'paypal',
		'details'    => wp_json_encode( array( 'email' => 'wd@example.test' ) ),
		'status'     => 'pending',
		'created_at' => current_time( 'mysql' ),
	)
);
$id       = (int) $wpdb->insert_id;
$earnings = new EarningsService();

// Paying a withdrawal needs the money in the vendor's ledger.
wpss_insert_ledger_row(
	array(
		'user_id'        => $vendor->ID,
		'type'           => 'earning',
		'amount'         => 100.0,
		'balance_after'  => 100.0,
		'currency'       => wpss_get_currency(),
		'description'    => 'test credit',
		'reference_type' => 'order',
		'reference_id'   => 900000000 + $vendor->ID,
		'status'         => 'completed',
		'created_at'     => current_time( 'mysql', true ),
	)
);

try {
	$earnings->process_withdrawal( $id, EarningsService::WITHDRAWAL_APPROVED );
	$approve_mail = end( $mails ) ?: array( 'subject' => '', 'message' => '' );
	$check( 'approving sends an Approved email', false !== strpos( $approve_mail['subject'], 'Approved' ) );

	$before = count( $mails );
	$paid   = $earnings->mark_paid( $id, 'test' );
	$paid_mail = count( $mails ) > $before ? end( $mails ) : array( 'subject' => '', 'message' => '' );
	$check( 'marking paid succeeds (' . wp_json_encode( $paid ) . ')', ! empty( $paid['success'] ) );
	$check( '  and sends exactly one more email', count( $mails ) === $before + 1 );
	$check( '  whose subject says Paid, not Approved', false !== strpos( $paid_mail['subject'], 'Paid' ) && false === strpos( $paid_mail['subject'], 'Approved' ) );
	$check( '  and whose body is the paid heading', false !== strpos( $paid_mail['message'], 'Withdrawal paid' ) && $paid_mail['message'] !== $approve_mail['message'] );
	$check( 'the paid email has its own template and type', is_file( WPSS_PLUGIN_DIR . 'templates/emails/withdrawal-completed.php' ) && 'withdrawal_completed' === EmailService::TYPE_WITHDRAWAL_COMPLETED );

	$note = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND type = %s", $vendor->ID, 'withdrawal_completed' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( 'the vendor also gets the in-app withdrawal_completed notification', $note >= 1 );

	$again = count( $mails );
	$earnings->mark_paid( $id, 'again' );
	$check( 'marking paid twice does not email twice', count( $mails ) === $again );
} finally {
	remove_filter( 'pre_wp_mail', $grab, 10 );
	$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $vendor->ID ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $vendor->ID ) );
	\WPSellServices\Tests\Factories\UserFactory::cleanup();
}

exit( $fails ? 1 : 0 );
