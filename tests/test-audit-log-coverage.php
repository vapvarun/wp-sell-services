<?php
/**
 * Every money, moderation and vendor decision leaves one audit row.
 *
 * The audit log held five order events only: withdrawals, dispute outcomes,
 * vendor status changes, commission changes, service and review moderation,
 * payments and ledger rows were untraced (Basecamp 10264291631).
 *
 * Run: wp eval-file tests/test-audit-log-coverage.php
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;
use WPSellServices\Services\CommissionService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\EarningsService;
use WPSellServices\Services\ModerationService;
use WPSellServices\Services\ReviewService;
use WPSellServices\Services\VendorService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$audit  = $wpdb->prefix . 'wpss_audit_log';
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = 999994; // Nobody. Rows are removed at the end.
$vendor = 999995;
$admin  = 1;

wp_set_current_user( $admin );

$start = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$audit}" );

// One row per action, by the actor who acted.
$row = static function ( string $event, string $type, int $id ) use ( $wpdb, $audit, $start ): ?object {
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$audit} WHERE id > %d AND event_type = %s AND object_type = %s AND object_id = %d", $start, $event, $type, $id ) );
	return 1 === count( $rows ) ? $rows[0] : null;
};
$ctx = static fn( ?object $r ) => $r ? (array) json_decode( (string) $r->context, true ) : array();

$seed_order = static function ( string $status, string $payment ) use ( $wpdb, $orders, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'WPSS-AUDIT-CONTRACT-' . wp_rand(),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'total'          => 100.000,
			'currency'       => 'USD',
			'status'         => $status,
			'payment_status' => $payment,
			'created_at'     => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
};

$saved_orders  = get_option( 'wpss_orders', array() );
$saved_payouts = get_option( 'wpss_payouts', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_orders, array( 'allow_disputes' => 1 ) ) );
update_option( 'wpss_payouts', array_merge( (array) $saved_payouts, array( 'clearance_days' => 0 ) ) );

$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor, 'status' => 'pending' ) );

// --- ledger + withdrawal -----------------------------------------------------
$amount = max( 30.0, EarningsService::get_min_withdrawal_amount() );
wpss_insert_ledger_row(
	array(
		'user_id'        => $vendor,
		'type'           => 'order_earning',
		'amount'         => $amount * 2,
		'description'    => 'audit contract',
		'reference_type' => 'order',
		'reference_id'   => 999999 + wp_rand( 1, 999 ),
	)
);
$credit_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$wpdb->prefix}wpss_wallet_transactions WHERE user_id = %d", $vendor ) );
$r         = $row( 'ledger.insert', 'ledger', $credit_id );
$check( 'ledger.insert row for the credit', null !== $r && $admin === (int) $r->actor_id && 'order_earning' === ( $ctx( $r )['type'] ?? '' ) );

$earnings = new EarningsService();
// The vendor is nobody, so WordPress resolves the actor to 0; the row must carry
// whatever get_current_user_id() said at request time, not the admin.
wp_set_current_user( $vendor );
$vendor_actor = get_current_user_id();
$req          = $earnings->request_withdrawal( $vendor, $amount, 'paypal', array( 'email' => 'audit@example.com' ) );
$wid          = (int) ( $req['withdrawal_id'] ?? 0 );
wp_set_current_user( $admin );
$r = $wid ? $row( 'withdrawal.requested', 'withdrawal', $wid ) : null;
$check( 'withdrawal.requested by the requesting user', null !== $r && $vendor_actor === (int) $r->actor_id && $admin !== (int) $r->actor_id && $amount === (float) ( $ctx( $r )['amount'] ?? 0 ) );

$earnings->process_withdrawal( $wid, EarningsService::WITHDRAWAL_APPROVED, 'ok' );
$r = $row( 'withdrawal.approved', 'withdrawal', $wid );
$check( 'withdrawal.approved by the admin, pending -> approved', null !== $r && $admin === (int) $r->actor_id && 'pending' === $r->from_value && 'approved' === $r->to_value );

$earnings->mark_paid( $wid, 'sent' );
$r = $row( 'withdrawal.paid', 'withdrawal', $wid );
$check( 'withdrawal.paid, approved -> completed', null !== $r && 'approved' === $r->from_value && 'completed' === $r->to_value );
$debit_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$wpdb->prefix}wpss_wallet_transactions WHERE user_id = %d AND type = 'withdrawal'", $vendor ) );
$check( '  and the payout debit has its ledger.insert row', $debit_id > 0 && null !== $row( 'ledger.insert', 'ledger', $debit_id ) );

// --- dispute -----------------------------------------------------------------
$disputes   = new DisputeService();
$order_id   = $seed_order( 'in_progress', 'paid' );
$dispute_id = (int) $disputes->open( $order_id, $buyer, 'other', 'contract' );
$disputes->resolve( $dispute_id, DisputeService::RESOLUTION_PARTIAL_REFUND, 'half', $admin, 40.0 );
$r = $dispute_id ? $row( 'dispute.transition', 'dispute', $dispute_id ) : null;
$check( 'dispute.transition open -> resolved with resolution and refund_amount', null !== $r && 'open' === $r->from_value && 'resolved' === $r->to_value && 'partial_refund' === ( $ctx( $r )['resolution'] ?? '' ) && 40.0 === (float) ( $ctx( $r )['refund_amount'] ?? 0 ) );

// --- vendor status -----------------------------------------------------------
$vendors = new VendorService();
$check( 'vendor.approved pending -> active', method_exists( $vendors, 'set_status' ) && $vendors->set_status( $vendor, 'active' ) && ( $r = $row( 'vendor.approved', 'vendor', $vendor ) ) && 'pending' === $r->from_value && $admin === (int) $r->actor_id );
$check( 'vendor.suspended active -> suspended', method_exists( $vendors, 'set_status' ) && $vendors->set_status( $vendor, 'suspended' ) && ( $r = $row( 'vendor.suspended', 'vendor', $vendor ) ) && 'active' === $r->from_value );

// --- commission --------------------------------------------------------------
$saved_commission = get_option( 'wpss_commission', array() );
$old_rate         = (float) ( $saved_commission['commission_rate'] ?? CommissionService::get_global_commission_rate() );
update_option( 'wpss_commission', array_merge( (array) $saved_commission, array( 'commission_rate' => $old_rate + 1 ) ) );
$r = $row( 'commission.rate_changed', 'settings', 0 );
$check( 'commission.rate_changed (global) old -> new', null !== $r && (string) $old_rate === $r->from_value && (string) ( $old_rate + 1 ) === $r->to_value );
update_option( 'wpss_commission', $saved_commission );

$commissions = new CommissionService();
$commissions->set_vendor_commission_rate( $vendor, 12.5 );
$r = $row( 'commission.rate_changed', 'vendor', $vendor );
$check( 'commission.rate_changed (vendor) global -> 12.5', null !== $r && 'global' === $r->from_value && '12.5' === $r->to_value );

// --- service moderation ------------------------------------------------------
$service_id = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'pending', 'post_title' => 'Audit contract', 'post_author' => $vendor ) );
( new ModerationService() )->approve( $service_id, 'fine' );
$r = $row( 'service.approved', 'service', $service_id );
$check( 'service.approved with notes', null !== $r && $admin === (int) $r->actor_id && 'fine' === ( $ctx( $r )['notes'] ?? '' ) );

// --- review moderation -------------------------------------------------------
$wpdb->insert( $wpdb->prefix . 'wpss_reviews', array( 'order_id' => $order_id, 'reviewer_id' => $buyer, 'reviewee_id' => $vendor, 'service_id' => $service_id, 'customer_id' => $buyer, 'vendor_id' => $vendor, 'rating' => 5, 'status' => 'pending' ) );
$review_id = (int) $wpdb->insert_id;
$reviews   = new ReviewService();
$check( 'review.approved pending -> approved', method_exists( $reviews, 'moderate' ) && $reviews->moderate( $review_id, 'approved' ) && ( $r = $row( 'review.approved', 'review', $review_id ) ) && 'pending' === $r->from_value );

// --- order paid --------------------------------------------------------------
$paid_id = $seed_order( 'pending_payment', 'pending' );
( new StandaloneOrderProvider() )->mark_as_paid( $paid_id, 'txn_audit_contract', 'offline' );
$r = $row( 'order.paid', 'order', $paid_id );
$check( 'order.paid with gateway and transaction', null !== $r && 'offline' === ( $ctx( $r )['gateway'] ?? '' ) && 'txn_audit_contract' === ( $ctx( $r )['transaction_id'] ?? '' ) );

// --- retention default and filter list ------------------------------------------
$check( 'retention defaults to one year when never saved', defined( 'WPSellServices\Services\AuditLogService::RETENTION_DEFAULT' ) && 365 === \WPSellServices\Services\AuditLogService::RETENTION_DEFAULT );
$check( 'every event written here is in the filter list', empty( array_diff( array( 'withdrawal.requested', 'withdrawal.approved', 'withdrawal.paid', 'dispute.transition', 'vendor.approved', 'vendor.suspended', 'commission.rate_changed', 'service.approved', 'review.approved', 'order.paid', 'ledger.insert' ), (array) ( defined( 'WPSellServices\Services\AuditLogService::EVENT_TYPES' ) ? \WPSellServices\Services\AuditLogService::EVENT_TYPES : array() ) ) ) );

// --- cleanup ----------------------------------------------------------------
update_option( 'wpss_orders', $saved_orders );
update_option( 'wpss_payouts', $saved_payouts );
wp_delete_post( $service_id, true );
$wpdb->delete( $wpdb->prefix . 'wpss_reviews', array( 'id' => $review_id ) );
if ( $wid ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $wid ) );
}
if ( $dispute_id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $dispute_id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'id' => $dispute_id ) );
}
foreach ( array( $order_id, $paid_id ) as $oid ) {
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpss_conversations WHERE order_id = %d", $oid ) ) as $cid ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => (int) $cid ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => (int) $cid ) );
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $oid ) );
	$wpdb->delete( $orders, array( 'id' => $oid ) );
}
$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $vendor ) );
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) );
foreach ( array( $buyer, $vendor ) as $uid ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $uid ) );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE id > %d", $start ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
