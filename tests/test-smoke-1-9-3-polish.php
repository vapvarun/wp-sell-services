<?php
/**
 * Guard: the 1.9.3 release-smoke polish findings stay fixed.
 *
 * A reactivated vendor is not told their application was approved; approved and
 * paid withdrawals carry different notice titles; a suspended vendor is not
 * offered Start selling; a logged-out pay link asks for a sign-in; a proposal
 * order's way back is the order. Run: wp eval-file tests/test-smoke-1-9-3-polish.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;
use WPSellServices\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/exit-on-fail.php';

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$notes   = $wpdb->prefix . 'wpss_notifications';
$vendor  = (int) wpss_test_fixture_user( 'vendor' );
if ( ! $vendor ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}

$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(id),0) FROM {$notes} WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$start = $since;
$after = static function () use ( $wpdb, $notes, $vendor, &$since ): array {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows  = (array) $wpdb->get_col( $wpdb->prepare( "SELECT title FROM {$notes} WHERE user_id = %d AND id > %d ORDER BY id ASC", $vendor, $since ) );
	$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(id),0) FROM {$notes} WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $rows;
};

// 1. Reactivation is not a new approval.
$service = new NotificationService();
$service->notify_vendor_approved( $vendor, true );
$check( 'reactivated vendor: the notice says reactivated', in_array( 'Vendor Account Reactivated', $after(), true ) );
$service->notify_vendor_approved( $vendor );
$check( 'new approval: the notice still says application approved', in_array( 'Vendor Application Approved', $after(), true ) );

// 2. Approved and paid are different news.
$earnings = new EarningsService();
$notify   = new ReflectionMethod( $earnings, 'notify_withdrawal_processed' );
$notify->setAccessible( true );
$row = (object) array( 'vendor_id' => $vendor, 'amount' => 10 );
$notify->invoke( $earnings, 999999, EarningsService::WITHDRAWAL_APPROVED, $row );
$approved = $after();
$notify->invoke( $earnings, 999999, EarningsService::WITHDRAWAL_COMPLETED, $row );
$paid = $after();
$check( 'withdrawal approved and paid carry different titles', $approved && $paid && $approved[0] !== $paid[0], wp_json_encode( array( $approved, $paid ) ) );

// 3. The doors are wired.
$root = dirname( __DIR__ );
$src  = static fn( string $f ): string => (string) file_get_contents( "$root/$f" );
$check( 'request page does not offer Become a Vendor to a suspended vendor', str_contains( $src( 'templates/single-request.php' ), "'suspended' !== wpss_get_vendor_status" ) );
$check( 'the become-vendor section does not offer Start selling to a suspended vendor', str_contains( $src( 'src/Frontend/UnifiedDashboard.php' ), "'suspended' !== wpss_get_vendor_status( \$user_id )" ) );
$check( 'a logged-out pay link asks for a sign-in', str_contains( $src( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' ), 'Please log in to pay for this order.' ) );
$check( 'a proposal order goes back to the order, not the home page', str_contains( $src( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' ), "'Back to order'" ) );
$check( 'the withdrawal form is replaced by the in-progress notice while one is open', str_contains( $src( 'templates/dashboard/sections/earnings.php' ), "(float) \$earnings['pending_withdrawal'] <= 0" ) );

// Remove only what this run wrote.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$notes} WHERE user_id = %d AND id > %d", $vendor, $start ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
