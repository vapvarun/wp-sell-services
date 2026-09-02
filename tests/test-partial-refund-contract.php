<?php
/**
 * A partial refund refunds the amount the admin entered, and nothing else.
 *
 * Run: wp eval-file tests/test-partial-refund-contract.php
 *
 * Guards Basecamp 10240143362: apply_refund_status() read 0/null as "refund
 * everything", which is right for a FULL refund and returned the buyer the
 * whole order on a PARTIAL one. Money path - the check stays.
 *
 * 1.7.1 (F10 / F23): commission is locked at payment, partial refunds
 * accumulate and each reverses its own vendor share, admin and dispute refunds
 * share one math, a status write is conditioned on the status read, and an
 * order created from a proposal is taxed.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\OrderService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';

// A throwaway paid order to refund against.
$wpdb->insert(
	$table,
	array(
		'order_number'   => 'WPSS-REFUND-CONTRACT',
		'customer_id'    => 1,
		'vendor_id'      => 1,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 200.000,
		'currency'       => 'USD',
		'status'         => 'completed',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

if ( ! $order_id ) {
	echo "could not seed an order; skipping\n";
	return;
}

$service = new OrderService();
$reset   = static function () use ( $wpdb, $table, $order_id ) {
	$wpdb->update(
		$table,
		array( 'status' => 'completed', 'refunded_amount' => 0, 'payment_status' => 'paid' ),
		array( 'id' => $order_id )
	);
};
$refunded = static function () use ( $wpdb, $table, $order_id ) {
	return (float) $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM {$table} WHERE id = %d", $order_id ) );
};

// --- The defect: partial with no amount -----------------------------------
$reset();
$ok = $service->apply_refund_status( $order_id, 0.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'partial refund of 0 is refused', false === $ok );
$check( '  and nothing was refunded', 0.0 === $refunded() );

$reset();
$ok = $service->apply_refund_status( $order_id, null, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'partial refund of null is refused', false === $ok );
$check( '  and nothing was refunded', 0.0 === $refunded() );

// A "partial" for the whole total is a full refund - say so instead.
$reset();
$ok = $service->apply_refund_status( $order_id, 200.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'partial refund for the full total is refused', false === $ok );
$check( '  and nothing was refunded', 0.0 === $refunded() );

// --- The real thing still works -------------------------------------------
$reset();
$service->apply_refund_status( $order_id, 50.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
$check( 'a real partial refunds exactly what was entered', 50.0 === $refunded() );

$reset();
$service->apply_refund_status( $order_id, null, ServiceOrder::STATUS_REFUNDED );
$check( 'a full refund still means the total', 200.0 === $refunded() );

$wpdb->delete( $table, array( 'id' => $order_id ) );

// --- 1.7.1 (F10 / F23): locked commission, cumulative partials, one refund math ---
//
// Throwaway vendor 999998 has no profile row and no real ledger; every row
// written below is removed at the end.
$ledger   = $wpdb->prefix . 'wpss_wallet_transactions';
$vendor   = 999998;
$buyer    = 999999;
$decimals = wpss_get_currency_decimals( 'USD' );
$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );

$seed_paid = static function ( string $status = 'in_progress' ) use ( $wpdb, $table, $vendor, $buyer ): int {
	$wpdb->insert(
		$table,
		array(
			'order_number'    => 'WPSS-F10-' . wp_generate_password( 6, false ),
			'customer_id'     => $buyer,
			'vendor_id'       => $vendor,
			'service_id'      => 0,
			'platform'        => 'standalone',
			'subtotal'        => 100.0,
			'total'           => 100.0,
			'currency'        => 'USD',
			'status'          => $status,
			'payment_status'  => 'paid',
			'commission_rate' => 10.0,
			'platform_fee'    => 10.0,
			'vendor_earnings' => 90.0,
			'created_at'      => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
};
$row       = static fn( int $id ): object => $wpdb->get_row( $wpdb->prepare( "SELECT status, payment_status, refunded_amount, commission_rate, platform_fee, vendor_earnings FROM {$table} WHERE id = %d", $id ) );
$reversals = static fn( int $id ): array => $wpdb->get_results( $wpdb->prepare( "SELECT amount, reference_type FROM {$ledger} WHERE reference_id = %d AND type = 'order_reversal' ORDER BY id", $id ) );
$eq        = static fn( float $a, float $b ): bool => abs( $a - $b ) < 0.0001;

// Rate change after payment leaves the order's fee unchanged at completion.
$saved_commission = get_option( 'wpss_commission', array() );
update_option( 'wpss_commission', array_merge( (array) $saved_commission, array( 'commission_rate' => 25, 'enable_vendor_rates' => 0 ) ) );

$locked = $seed_paid();
$service->update_status( $locked, ServiceOrder::STATUS_COMPLETED );
$r      = $row( $locked );
$credit = (float) $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM {$ledger} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_earning'", $locked ) );
$check( 'completion keeps the rate locked at payment (10%, not the new 25%)', $eq( 10.0, (float) $r->commission_rate ) && $eq( 10.0, (float) $r->platform_fee ) && $eq( 90.0, (float) $r->vendor_earnings ) );
$check( '  and credits the locked vendor earnings', $eq( 90.0, $credit ) );

update_option( 'wpss_commission', $saved_commission );

// Partial 10 then 15: refunded_amount accumulates, each event reverses its share.
$check( 'partial 10 applies', $service->apply_refund_status( $locked, 10.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED ) );
$r = $row( $locked );
$check( '  refunded_amount 10, payment still paid, vendor keeps 81', $eq( 10.0, (float) $r->refunded_amount ) && 'paid' === $r->payment_status && $eq( 81.0, (float) $r->vendor_earnings ) );
$check( '  order is still refundable', wpss_order_is_refundable( wpss_get_order( $locked ) ) );

$check( 'partial 15 applies on top', $service->apply_refund_status( $locked, 15.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED ) );
$r  = $row( $locked );
$rv = $reversals( $locked );
$check( '  refunded_amount 25 (accumulated, not overwritten), payment still paid', $eq( 25.0, (float) $r->refunded_amount ) && 'paid' === $r->payment_status && ServiceOrder::STATUS_PARTIALLY_REFUNDED === $r->status );
$check( '  two reversal rows: -9.00 and -13.50 (15% of what was left)', 2 === count( $rv ) && $eq( -9.0, (float) $rv[0]->amount ) && $eq( -13.5, (float) $rv[1]->amount ) );
$check( '  second reversal has its own reference (order_refund_2 on the order id)', 2 === count( $rv ) && 'order_refund_2' === $rv[1]->reference_type );
$check( '  vendor keeps 67.50', $eq( 67.5, (float) $r->vendor_earnings ) );

$check( 'refunding the rest closes the order', $service->apply_refund_status( $locked, null, ServiceOrder::STATUS_REFUNDED ) );
$r  = $row( $locked );
$rv = $reversals( $locked );
$check( '  refunded_amount equals the total, status refunded, nothing left to refund', $eq( 100.0, (float) $r->refunded_amount ) && ServiceOrder::STATUS_REFUNDED === $r->status && ! wpss_order_is_refundable( wpss_get_order( $locked ) ) );
$check( '  third reversal -67.50; ledger nets to zero for the order', 3 === count( $rv ) && $eq( -67.5, (float) $rv[2]->amount ) && $eq( 0.0, (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$ledger} WHERE reference_id = %d", $locked ) ) ) );
$check( '  a fourth refund is refused', false === $service->apply_refund_status( $locked, 5.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED ) );

// Admin partial and dispute partial produce identical vendor_earnings.
$saved_orders = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_orders, array( 'allow_disputes' => 1 ) ) );

$admin_side   = $seed_paid();
$dispute_side = $seed_paid();
$service->update_status( $admin_side, ServiceOrder::STATUS_COMPLETED );
$service->update_status( $dispute_side, ServiceOrder::STATUS_COMPLETED );
// A completed order cannot be disputed; put the credited order back where a
// dispute can open (the credit stays, which is what the reversal reads).
$wpdb->update( $table, array( 'status' => ServiceOrder::STATUS_IN_PROGRESS ), array( 'id' => $dispute_side ) );

$service->apply_refund_status( $admin_side, 30.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );

$disputes   = new DisputeService();
$dispute_id = (int) $disputes->open( $dispute_side, $buyer, 'other', 'contract' );
$resolved   = $dispute_id && $disputes->resolve( $dispute_id, 'partial_refund', 'n', 1, 30.0 );
$a          = $row( $admin_side );
$d          = $row( $dispute_side );
$check( 'dispute partial refund resolves', (bool) $resolved );
$check( 'admin partial and dispute partial leave identical vendor_earnings (63.00)', $eq( (float) $a->vendor_earnings, (float) $d->vendor_earnings ) && $eq( 63.0, (float) $a->vendor_earnings ) );
$check( '  and identical refunded_amount and reversal rows', $eq( (float) $a->refunded_amount, (float) $d->refunded_amount ) && $eq( (float) $reversals( $admin_side )[0]->amount, (float) $reversals( $dispute_side )[0]->amount ) );

update_option( 'wpss_orders', $saved_orders );

// A status write is conditioned on the status the caller read.
$guarded = $seed_paid();
$check( 'update_status refuses a stale expected status', false === $service->update_status( $guarded, ServiceOrder::STATUS_COMPLETED, '', ServiceOrder::STATUS_DELIVERED ) );
$wpdb->update( $table, array( 'status' => ServiceOrder::STATUS_CANCELLED ), array( 'id' => $guarded ) );
$check( '  and one that changed underneath it', false === $service->update_status( $guarded, ServiceOrder::STATUS_COMPLETED, '', ServiceOrder::STATUS_IN_PROGRESS ) && ServiceOrder::STATUS_CANCELLED === $row( $guarded )->status );

// A proposal-created order carries tax and a locked commission.
$saved_tax = get_option( 'wpss_tax', array() );
update_option( 'wpss_tax', array_merge( (array) $saved_tax, array( 'enable_tax' => 1, 'tax_rate' => 18, 'tax_included' => 0 ) ) );

$requests   = new BuyerRequestService();
$request_id = $requests->create( array( 'title' => 'F23 contract', 'description' => 'tax on proposal orders', 'budget_min' => 50, 'budget_max' => 100 ) );
$request_id = is_int( $request_id ) ? $request_id : 0;
$wpdb->insert(
	$wpdb->prefix . 'wpss_proposals',
	array(
		'request_id'     => $request_id,
		'vendor_id'      => $vendor,
		'cover_letter'   => 'contract',
		'proposed_price' => 95.0,
		'proposed_days'  => 7,
		'status'         => 'pending',
		'created_at'     => current_time( 'mysql' ),
	)
);
$proposal_id = (int) $wpdb->insert_id;
$converted   = $request_id ? $requests->convert_to_order( $request_id, $proposal_id ) : array();
$prop_order  = (int) ( $converted['order_id'] ?? 0 );
$po          = $prop_order ? $wpdb->get_row( $wpdb->prepare( "SELECT subtotal, total, commission_rate, vendor_earnings, meta FROM {$table} WHERE id = %d", $prop_order ) ) : null;
$po_meta     = $po ? (array) json_decode( (string) $po->meta, true ) : array();
$check( 'proposal accept creates the order', $prop_order > 0 );
$check( '  priced with tax: subtotal 95.00, total 112.10, tax_amount 17.10', $po && $eq( 95.0, (float) $po->subtotal ) && $eq( 112.1, (float) $po->total ) && $eq( 17.1, (float) ( $po_meta['tax_amount'] ?? 0 ) ) );
$check( '  commission locked at creation on the pre-tax price', $po && null !== $po->commission_rate && $eq( 95.0 * ( 1 - (float) $po->commission_rate / 100 ), (float) $po->vendor_earnings ) );

update_option( 'wpss_tax', $saved_tax );

// Cleanup.
foreach ( array( $locked, $admin_side, $dispute_side, $guarded, $prop_order ) as $id ) {
	if ( $id ) {
		$wpdb->delete( $table, array( 'id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'order_id' => $id ) );
	}
}
$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $proposal_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $vendor ) );
$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $buyer ) );
if ( $request_id ) {
	wp_delete_post( $request_id, true );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
if ( $fails ) {
	exit( 1 );
}
