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
// Since 2.0.0 one helper answers for rejected and suspended members; rendered behaviour is in test-suspended-vendor-doors.php.
$check( 'the become-vendor section does not offer Start selling to a suspended vendor', str_contains( $src( 'src/Frontend/UnifiedDashboard.php' ), 'wpss_vendor_closed_notice( $user_id )' ) );
$check( 'a logged-out pay link asks for a sign-in', str_contains( $src( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' ), 'Please sign in to pay for this order.' ) );
$check( 'a proposal order goes back to the order, not the home page', str_contains( $src( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' ), "'Back to order'" ) );
$check( 'the withdrawal form is replaced by the in-progress notice while one is open', str_contains( $src( 'templates/dashboard/sections/earnings.php' ), "(float) \$earnings['pending_withdrawal'] <= 0" ) );
// One helper since 1.9.4 (Basecamp 10511340927); its behaviour per order type is in test-pay-page-back-link.php.
$check( 'the proposal-order back link uses the order id, the parent id only for sub-orders', str_contains( $src( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' ), 'in_array( (string) ( $order->platform ?? \'\' ), wpss_get_sub_order_platforms(), true ) ? (int) ( $order->platform_order_id ?? 0 ) : 0' ) );
// Since 1.9.4 (cd9fba2c) the card offers Send Proposal only when $wpss_can_propose, which excludes a suspended vendor.
$check( 'the request card does not invite a suspended vendor to send a proposal', str_contains( $src( 'templates/content-request-card.php' ), "&& 'suspended' !== wpss_get_vendor_status( \$wpss_viewer );" ) && str_contains( $src( 'templates/content-request-card.php' ), "\$wpss_can_propose ? esc_html__( 'Send proposal'" ) );
$check( 'the become-a-vendor page does not offer registration to a suspended vendor', str_contains( $src( 'src/Frontend/Shortcodes.php' ), '$closed_notice = wpss_vendor_closed_notice( $user_id );' ) );
$check( 'the moderation badge and dashboard queue count nothing while moderation is off', str_contains( $src( 'src/Admin/Pages/ServiceModerationPage.php' ), 'ModerationService::is_enabled() ? wpss_count_pending_services() : 0' ) && str_contains( $src( 'src/Admin/Admin.php' ), 'ModerationService::is_enabled() ? wpss_count_pending_services() : 0' ) );
$checkout = $src( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' );
$check( 'an extension or tip pay page goes back to the order, not the service', str_contains( $checkout, '$is_pay_order && ( $service->id <= 0 || $is_sub_order )' ) );
$check( 'the checkout sidebar is sticky only on a viewport tall enough to show it whole', (bool) preg_match( '/@media \(min-height: 1100px\)[^}]*co-aside[^}]*position: sticky/', $checkout ) && ! str_contains( $checkout, 'overscroll-behavior: contain' ) );
$check( 'the order page looks for a not-yet-created conversation without a 404', str_contains( $src( 'src/API/ConversationsController.php' ), "get_param( 'soft' )" ) && str_contains( $src( 'templates/order/conversation.php' ), 'data: { soft: 1 }' ) );
$check( 'upgrade and docs links come from one helper each', 0 === preg_match( '#https://(wpsellservices\.com|store\.wbcomdesigns\.com)#', $src( 'src/Admin/Settings.php' ) . $src( 'src/Frontend/ServiceWizard.php' ) . $src( 'src/Admin/Pages/UpgradePage.php' ) . $src( 'src/Admin/ProTeaser.php' ) ) && function_exists( 'wpss_pro_upgrade_url' ) && function_exists( 'wpss_docs_url' ) );
$check( 'a review of a request order is titled with the project', str_contains( $src( 'templates/dashboard/sections/reviews.php' ), 'wpss_get_order_title' ) );
$check( 'a request shows no delivery time the buyer never entered', ! str_contains( $src( 'templates/single-request.php' ), "'Expected delivery'" ) );
$check( 'a phased contract parent does not read Paid beside a Paid so far of zero', str_contains( $src( 'templates/order/payment.php' ), 'Paid phase by phase' ) );
$check( 'the extension modal submit matches its opener', ! str_contains( $src( 'templates/order/order-view.php' ), 'Send Payment Link' ) );
$check( 'a request order requirements form starts from the stored brief', str_contains( $src( 'templates/order/requirements-form.php' ), "'wpss_request' === \$wpss_brief->post_type" ) );

// Remove only what this run wrote.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$notes} WHERE user_id = %d AND id > %d", $vendor, $start ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
