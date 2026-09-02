<?php
/**
 * Every rail-side refund goes through one seam, and manual refunds stay honest.
 *
 * Run: wp eval-file tests/test-gateway-refund-seam.php
 *
 * Guards F4 / F5 / F22 (1.7.1): `wpss_gateway_refund_received` is the ONE
 * listener for refunds that happened at Stripe / PayPal / Razorpay. It must
 * hit only the order the gateway named, split an unnamed cart refund by order
 * total, ignore a replayed refund id, and never call the gateway again. An
 * offline / test gateway cannot move money, so its refund must be flagged as
 * pending manual payment and never logged as successful.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Old code has neither; read them through wrappers so the script reports
// FAIL instead of dying before cleanup.
$last_result = static fn( int $id ): ?array => method_exists( OrderWorkflowManager::class, 'get_last_refund_result' ) ? OrderWorkflowManager::get_last_refund_result( $id ) : array( 'missing' => true );
$pending_key = defined( OrderWorkflowManager::class . '::REFUND_PENDING_META' ) ? OrderWorkflowManager::REFUND_PENDING_META : '_wpss_refund_pending';

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';
$txn   = 'pi_seam_contract_' . wp_generate_password( 8, false );

$seed = static function ( float $total, string $method, string $transaction_id ) use ( $wpdb, $table ): int {
	$wpdb->insert(
		$table,
		array(
			'order_number'   => 'WPSS-SEAM-' . wp_generate_password( 6, false ),
			'customer_id'    => 1,
			'vendor_id'      => 1,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'total'          => $total,
			'subtotal'       => $total,
			'currency'       => 'USD',
			'status'         => 'completed',
			'payment_status' => 'paid',
			'payment_method' => $method,
			'transaction_id' => $transaction_id,
			'created_at'     => current_time( 'mysql' ),
		)
	);

	return (int) $wpdb->insert_id;
};

$row = static function ( int $id ) use ( $wpdb, $table ): ?object {
	return $wpdb->get_row( $wpdb->prepare( "SELECT status, payment_status, refunded_amount FROM {$table} WHERE id = %d", $id ) );
};

$a = $seed( 100.0, 'stripe', $txn );
$b = $seed( 50.0, 'stripe', $txn );
$c = $seed( 80.0, 'offline', 'OFFLINE-REF-' . $a );

if ( ! $a || ! $b || ! $c ) {
	echo "could not seed orders; skipping\n";
	return;
}

// Capture wpss_log() output for the "never logs success" assertion.
$log_file = get_temp_dir() . 'wpss-seam-' . $a . '.log';
$advanced = get_option( 'wpss_advanced', array() );
update_option( 'wpss_advanced', array_merge( (array) $advanced, array( 'enable_debug_mode' => 1 ) ) );
$old_error_log = ini_get( 'error_log' );
ini_set( 'error_log', $log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky

try {
	// --- Seam 1 is wired -------------------------------------------------------
	$check( 'wpss_gateway_refund_received has a listener', false !== has_action( 'wpss_gateway_refund_received' ) );

	// --- A named refund touches only that order --------------------------------
	do_action( 'wpss_gateway_refund_received', 'paypal', $txn, 30.0, array( 'order_id' => $a, 'refund_id' => 'r1', 'currency' => 'USD' ) );
	$ra = $row( $a );
	$rb = $row( $b );
	$check( 'named order is partially refunded for 30', 'partially_refunded' === $ra->status && 30.0 === (float) $ra->refunded_amount );
	$check( '  and the sibling order sharing the transaction is untouched', 'completed' === $rb->status && (float) $rb->refunded_amount <= 0 );
	$check( '  and no gateway call was attempted (settled at rail)', null === $last_result( $a ) );

	// --- A replayed refund id is ignored ----------------------------------------
	do_action( 'wpss_gateway_refund_received', 'paypal', $txn, 30.0, array( 'order_id' => $a, 'refund_id' => 'r1', 'currency' => 'USD' ) );
	$check( 'replaying the same refund id does not add a second 30', 30.0 === (float) $row( $a )->refunded_amount );

	// --- An unnamed cumulative refund is split by order total -------------------
	do_action( 'wpss_gateway_refund_received', 'stripe', $txn, 150.0, array( 'cumulative' => true, 'refund_id' => 're_1', 'currency' => 'USD' ) );
	$ra = $row( $a );
	$rb = $row( $b );
	$check( 'full charge refund lands 100 on the 100 order', 'refunded' === $ra->status && ( null === $ra->refunded_amount || 100.0 === (float) $ra->refunded_amount ) );
	$check( '  and 50 on the 50 order', 'refunded' === $rb->status && ( null === $rb->refunded_amount || 50.0 === (float) $rb->refunded_amount ) );

	// --- Offline refund is pending manual payment, never "successful" ------------
	file_put_contents( $log_file, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$moved   = ( new OrderService() )->apply_refund_status( $c, 40.0, ServiceOrder::STATUS_PARTIALLY_REFUNDED );
	$pending = (float) wpss_get_order_provider()->get_item_meta( $c, $pending_key );
	$outcome = $last_result( $c );
	$log     = (string) file_get_contents( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$check( 'offline partial refund moves the order', true === $moved );
	$check( '  and flags 40 as pending manual refund', 40.0 === $pending );
	$check( '  and reports manual=true to the caller', is_array( $outcome ) && ! empty( $outcome['manual'] ) && ! empty( $outcome['success'] ) );
	$check( '  and the payment stays paid (nothing was sent back)', 'paid' === $row( $c )->payment_status );
	$check( '  and never logs "Auto-refund successful"', false === strpos( $log, 'Auto-refund successful' ) );
	$check( '  and logs that a manual refund is required', false !== strpos( $log, 'Manual refund required' ) );
	$check( '  and the admin notice lists it', function_exists( 'wpss_get_pending_manual_refunds' ) && isset( wpss_get_pending_manual_refunds()[ $c ] ) );

	// --- Every gateway webhook fires the seam (grep-level) -----------------------
	$fires = static function ( string $file ): bool {
		return is_readable( $file ) && false !== strpos( (string) file_get_contents( $file ), "'wpss_gateway_refund_received'" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	};
	$free = defined( 'WPSS_PLUGIN_DIR' ) ? WPSS_PLUGIN_DIR : dirname( __DIR__ ) . '/';
	$pro  = dirname( rtrim( $free, '/' ) ) . '/wp-sell-services-pro/';
	$check( 'Stripe charge.refunded fires the seam', $fires( $free . 'src/Integrations/Stripe/StripeGateway.php' ) );
	$check( 'PayPal PAYMENT.CAPTURE.REFUNDED fires the seam', $fires( $free . 'src/Integrations/PayPal/PayPalGateway.php' ) );
	if ( is_dir( $pro ) ) {
		$check( 'Razorpay refund.created fires the seam (Pro)', $fires( $pro . 'src/Integrations/Razorpay/RazorpayGateway.php' ) );
	}
	$check( 'Stripe no longer applies the refund inline', is_readable( $free . 'src/Integrations/Stripe/StripeGateway.php' ) && false === strpos( (string) file_get_contents( $free . 'src/Integrations/Stripe/StripeGateway.php' ), '->apply_refund_status(' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
} finally {
	// --- Cleanup -----------------------------------------------------------------
	ini_set( 'error_log', (string) $old_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	update_option( 'wpss_advanced', $advanced );
	if ( file_exists( $log_file ) ) {
		wp_delete_file( $log_file );
	}

	foreach ( array( $a, $b, $c ) as $id ) {
		$wpdb->delete( $table, array( 'id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_type' => 'order', 'reference_id' => $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE data LIKE %s", '%"order_id":' . $id . '%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_conversations WHERE order_id = %d", $id ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
