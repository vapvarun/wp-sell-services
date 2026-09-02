<?php
/**
 * A dispute has one state machine, closing gives the order back, and money
 * moves once.
 *
 * Run: wp eval-file tests/test-dispute-state-machine.php
 *
 * Guards Basecamp 10264284617: admin Close left the order at `disputed`
 * forever, resolve() wrote `resolved` before the money code ran and ignored
 * its refusal, a resolved dispute could be resolved again with a different
 * outcome, and a dispute could be opened on an unpaid order.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders   = $wpdb->prefix . 'wpss_orders';
$disputes = $wpdb->prefix . 'wpss_disputes';
$ledger   = $wpdb->prefix . 'wpss_wallet_transactions';
$buyer    = 999999; // Nobody. Rows are removed at the end.
$vendor   = 999998;

$seed = static function ( string $status ) use ( $wpdb, $orders, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'WPSS-DISPUTE-CONTRACT-' . wp_rand(),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'total'          => 100.000,
			'currency'       => 'USD',
			'status'         => $status,
			'payment_status' => 'pending_payment' === $status ? 'pending' : 'paid',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
};
$order_status  = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) );
$dispute_status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$disputes} WHERE id = %d", $id ) );
$ledger_rows   = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE user_id = %d", $vendor ) );

$service  = new DisputeService();
$workflow = new DisputeWorkflowManager();

// Disputes must be allowed for can_open_dispute() to say anything.
$saved_settings = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_settings, array( 'allow_disputes' => 1 ) ) );

// --- open --------------------------------------------------------------------
$paid       = $seed( 'in_progress' );
$dispute_id = (int) $service->open( $paid, $buyer, 'other', 'contract' );
$check( 'open on a paid in_progress order works', $dispute_id > 0 );
$check( '  and the order is disputed', 'disputed' === $order_status( $paid ) );

$unpaid = $seed( 'pending_payment' );
$check( 'button hides on a pending_payment order', ! $service->can_open_dispute( wpss_get_order( $unpaid ) ) );
$refused = $service->open( $unpaid, $buyer, 'other', 'contract' );
$check( 'open on a pending_payment order is refused', false === $refused );
$check( '  with a reason', '' !== $service->last_error() );
$check( '  and no row was inserted', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$disputes} WHERE order_id = %d", $unpaid ) ) );
$check( '  and the order did not move', 'pending_payment' === $order_status( $unpaid ) );

// --- close restores the order -------------------------------------------------
wp_set_current_user( 0 );
$closed = $workflow->cancel( $dispute_id, $buyer, 'withdrawn' );
$check( 'opener can close without admin rights', ! empty( $closed['success'] ) );
$check( '  dispute is closed', 'closed' === $dispute_status( $dispute_id ) );
$check( '  order is back to in_progress', 'in_progress' === $order_status( $paid ) );
$check( '  second dispute after close is refused (one per order)', false === $service->open( $paid, $buyer, 'other', 'again' ) );
$check( '  and the button agrees', ! $service->can_open_dispute( wpss_get_order( $paid ) ) );
$check( '  closed dispute cannot be escalated', empty( $workflow->escalate( $dispute_id, 'x', $buyer )['success'] ) );
$check( '  closed dispute cannot be resolved', false === $service->resolve( $dispute_id, 'favor_vendor', 'n', 1 ) );

// --- resolve moves money once --------------------------------------------------
wp_set_current_user( 1 );
$paid2      = $seed( 'in_progress' );
$dispute2   = (int) $service->open( $paid2, $buyer, 'other', 'contract' );
$before     = $ledger_rows();
$check( 'first resolve (favor_vendor) works', $service->resolve( $dispute2, 'favor_vendor', 'n', 1 ) );
$check( '  order completed', 'completed' === $order_status( $paid2 ) );
$after_first = $ledger_rows();
$check( 'second resolve (full_refund) is refused', false === $service->resolve( $dispute2, 'full_refund', 'n', 1 ) );
$check( '  order still completed', 'completed' === $order_status( $paid2 ) );
$check( '  ledger unchanged', $after_first === $ledger_rows() );
$check( '  resolved dispute cannot be escalated', empty( $workflow->escalate( $dispute2, 'x', 1 )['success'] ) );
$check( '  resolved dispute cannot be closed', empty( $workflow->cancel( $dispute2, 1 )['success'] ) );
$check( '  transition() refuses resolved -> pending_review', false === $service->transition( $dispute2, 'pending_review' ) );

// --- refused money op leaves the dispute unresolved ---------------------------
$paid3    = $seed( 'in_progress' );
$dispute3 = (int) $service->open( $paid3, $buyer, 'other', 'contract' );
$block    = static fn() => false;
add_filter( 'wpss_pre_order_status_change', $block );
$check( 'resolve with a refused order move returns false', false === $service->resolve( $dispute3, 'full_refund', 'n', 1 ) );
remove_filter( 'wpss_pre_order_status_change', $block );
$check( '  dispute is still open', 'open' === $dispute_status( $dispute3 ) );
$check( '  order is still disputed', 'disputed' === $order_status( $paid3 ) );
$check( '  refunded_amount untouched', 0.0 === (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(refunded_amount,0) FROM {$orders} WHERE id = %d", $paid3 ) ) );
$check( 'partial refund of 0 is refused by resolve()', false === $service->resolve( $dispute3, 'partial_refund', 'n', 1, 0.0 ) );
$check( 'partial refund of the total is refused by resolve()', false === $service->resolve( $dispute3, 'partial_refund', 'n', 1, 100.0 ) );
$check( '  dispute still open', 'open' === $dispute_status( $dispute3 ) );

// --- cleanup ------------------------------------------------------------------
update_option( 'wpss_orders', $saved_settings );
$order_ids = array( $paid, $unpaid, $paid2, $paid3 );
foreach ( $order_ids as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$disputes} WHERE order_id = %d", $id ) ) ) );
	$wpdb->delete( $disputes, array( 'order_id' => $id ) );
	$wpdb->delete( $orders, array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
}
$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
foreach ( array( $buyer, $vendor ) as $u ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $u ) );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_messages WHERE conversation_id NOT IN (SELECT id FROM {$wpdb->prefix}wpss_conversations)" ) ); // phpcs:ignore

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
