<?php
/**
 * A refund is recorded only when the money moved.
 *
 * Run: wp eval-file tests/test-refund-outcome-contract.php
 *
 * The status moved first and the gateway was asked second, inside the status
 * hook. When the gateway refused, the order still read "refunded", the buyer's
 * "refunded" email had already gone, and the vendor's earnings were reversed
 * with nothing sent back (Basecamp 10331363649: vendor 508.27 -> 431.77 on a
 * failed Stripe refund).
 *
 * Gateways are mocked through wpss_pre_process_gateway_refund; nothing leaves
 * the machine. Every row this script creates is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders   = $wpdb->prefix . 'wpss_orders';
$ledger   = $wpdb->prefix . 'wpss_wallet_transactions';
$disputes = $wpdb->prefix . 'wpss_disputes';
$buyer    = 999999; // Nobody. Rows are removed at the end.
$vendor   = 999998;
$flag_key = defined( OrderWorkflowManager::class . '::REFUND_FAILED_META' ) ? OrderWorkflowManager::REFUND_FAILED_META : '_wpss_refund_failed';
$service  = new OrderService();
$provider = wpss_get_order_provider();

$seed = static function ( string $status, string $method, float $earnings ) use ( $wpdb, $orders, $ledger, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'    => 'WPSS-REFUND-OUTCOME-' . wp_generate_password( 6, false ),
			'customer_id'     => $buyer,
			'vendor_id'       => $vendor,
			'service_id'      => 0,
			'platform'        => 'standalone',
			'subtotal'        => 100.000,
			'total'           => 100.000,
			'currency'        => 'USD',
			'status'          => $status,
			'payment_status'  => 'paid',
			'payment_method'  => $method,
			'transaction_id'  => 'pi_outcome_' . wp_generate_password( 8, false ),
			'vendor_earnings' => $earnings,
			'platform_fee'    => 100 - $earnings,
			'created_at'      => current_time( 'mysql' ),
		)
	);
	$id = (int) $wpdb->insert_id;

	if ( $earnings > 0 ) {
		wpss_insert_ledger_row(
			array(
				'user_id'        => $vendor,
				'type'           => 'order_earning',
				'amount'         => $earnings,
				'balance_after'  => $earnings,
				'currency'       => 'USD',
				'description'    => 'refund outcome contract credit',
				'reference_type' => 'order',
				'reference_id'   => $id,
				'status'         => 'completed',
				'created_at'     => current_time( 'mysql' ),
			)
		);
	}

	return $id;
};

$row       = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT status, payment_status, refunded_amount FROM {$orders} WHERE id = %d", $id ) );
$reversals = static fn( int $id ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE reference_id = %d AND type = 'order_reversal'", $id ) );
$refund    = static function ( int $id, ?float $amount, string $status ) use ( $service ): array {
	if ( method_exists( $service, 'refund' ) ) {
		return $service->refund( $id, $amount, $status, array( 'origin' => 'admin' ) );
	}
	return array( 'ok' => $service->apply_refund_status( $id, $amount, $status ), 'outcome' => 'unknown' );
};

// The gateway's answer for this run, and how often it was asked.
$gateway_answer = array( 'success' => false, 'message' => 'Your card was declined.' );
$gateway_calls  = 0;
$mock           = static function ( $handled, $order ) use ( &$gateway_answer, &$gateway_calls, $vendor ) {
	if ( (int) $order->vendor_id !== $vendor ) {
		return $handled;
	}
	++$gateway_calls;
	return $gateway_answer;
};
add_filter( 'wpss_pre_process_gateway_refund', $mock, 10, 2 );

$ids = array();

try {
	// --- 1. Gateway refuses: nothing is recorded -----------------------------
	$a     = $seed( 'completed', 'stripe', 76.50 );
	$ids[] = $a;
	$fired = did_action( 'wpss_order_status_refunded' );
	$res   = $refund( $a, null, ServiceOrder::STATUS_REFUNDED );
	$r     = $row( $a );

	$check( 'a declined gateway refund reports ok=false', false === ( $res['ok'] ?? null ) );
	$check( '  with outcome "failed"', 'failed' === ( $res['outcome'] ?? '' ) );
	$check( '  and carries the gateway message', false !== strpos( (string) ( $res['message'] ?? '' ), 'declined' ) );
	$check( '  the order keeps its status', 'completed' === $r->status );
	$check( '  refunded_amount is untouched', null === $r->refunded_amount || 0.0 === (float) $r->refunded_amount );
	$check( '  no "refunded" status hook fired (no buyer email)', did_action( 'wpss_order_status_refunded' ) === $fired );
	$check( '  the vendor is NOT debited', 0 === $reversals( $a ) );
	$flag = (array) $provider->get_item_meta( $a, $flag_key );
	$check( '  the order is flagged "refund failed"', ! empty( $flag ) && 100.0 === (float) ( $flag['amount'] ?? 0 ) );

	// --- 2. Retry succeeds: recorded once, flag cleared ------------------------
	$gateway_answer = array( 'success' => true, 'refund_id' => 're_outcome_ok' );
	$retry          = method_exists( $service, 'retry_refund' ) ? $service->retry_refund( $a ) : array( 'ok' => false );
	$r              = $row( $a );
	$check( 'retry after the gateway recovers reports ok=true, outcome "moved"', true === ( $retry['ok'] ?? null ) && 'moved' === ( $retry['outcome'] ?? '' ) );
	$check( '  the order is refunded', 'refunded' === $r->status );
	$check( '  the vendor is debited exactly once', 1 === $reversals( $a ) );
	$check( '  the flag is cleared', empty( $provider->get_item_meta( $a, $flag_key ) ) );

	$calls = $gateway_calls;
	$again = method_exists( $service, 'retry_refund' ) ? $service->retry_refund( $a ) : array( 'ok' => true );
	$check( 'a further retry is refused', false === ( $again['ok'] ?? null ) );
	$check( '  without calling the gateway', $calls === $gateway_calls );

	// --- 3. Offline: recorded as a manual refund --------------------------------
	$b     = $seed( 'completed', 'offline', 76.50 );
	$ids[] = $b;
	$gateway_answer = array( 'success' => true, 'manual' => true );
	$res            = $refund( $b, null, ServiceOrder::STATUS_REFUNDED );
	$check( 'an offline refund is recorded (ok=true, outcome "manual")', true === ( $res['ok'] ?? null ) && 'manual' === ( $res['outcome'] ?? '' ) );
	$check( '  and the order reaches refunded', 'refunded' === $row( $b )->status );

	// --- 4. Dispute resolved for the buyer while the gateway fails --------------
	$c     = $seed( 'in_progress', 'stripe', 0 );
	$ids[] = $c;
	wp_set_current_user( 0 );
	$disputes_service = new DisputeService();
	$dispute_id       = (int) $disputes_service->open( $c, $buyer, 'not_as_described', 'Refund outcome contract.' );
	$gateway_answer   = array( 'success' => false, 'message' => 'Charge already disputed at the bank.' );
	$resolved         = $disputes_service->resolve( $dispute_id, DisputeService::RESOLUTION_FAVOR_BUYER, 'contract', 1 );
	$check( 'resolving a dispute for the buyer fails when the gateway refund fails', false === $resolved );
	$check( '  and says why (the gateway message)', false !== strpos( (string) $disputes_service->last_error(), 'already disputed' ) );
	$check( '  the dispute stays open', 'resolved' !== (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$disputes} WHERE id = %d", $dispute_id ) ) );
	$check( '  the order stays disputed', 'disputed' === $row( $c )->status );
} finally {
	remove_filter( 'wpss_pre_process_gateway_refund', $mock, 10 );

	foreach ( $ids as $id ) {
		$did = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$disputes} WHERE order_id = %d", $id ) );
		if ( $did ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $did ) );
		}
		$wpdb->delete( $disputes, array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
	foreach ( array( $buyer, $vendor ) as $u ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $u ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
