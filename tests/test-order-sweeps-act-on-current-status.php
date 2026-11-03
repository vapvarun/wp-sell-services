<?php
/**
 * An order sweep acts only on the status it selected, never on one that changed since.
 *
 * Each scheduled sweep selected a batch, then moved every row to its target if
 * that move was legal from whatever status the order had by then. A buyer who
 * opened a dispute meanwhile was auto-completed and the vendor paid; a vendor
 * who declined a cancellation meanwhile had the order cancelled and refunded;
 * a buyer who had just sent requirements had the order cancelled. Each sweep
 * now passes the status it selected as update_status()'s expected_from.
 *
 * The race is made deterministic: the sweep's SELECT is confined to the seeded
 * orders, and the order's status is flipped on the very next query, between
 * the SELECT and the update - the window a real batch of 200 rows leaves open.
 *
 * Run: wp eval-file tests/test-order-sweeps-act-on-current-status.php
 *
 * Seeds QA-SWEEP-* orders and deletes them with every row that references them.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders  = $wpdb->prefix . 'wpss_orders';
$stamp   = strtoupper( wp_generate_password( 5, false ) );
$vendor  = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->prefix}wpss_vendor_profiles ORDER BY user_id LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$buyer   = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$made    = array();
$days    = static fn( int $n ) => gmdate( 'Y-m-d H:i:s', time() - $n * DAY_IN_SECONDS );
$ledger0 = (int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$wpdb->prefix}wpss_wallet_transactions" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$seed = static function ( string $suffix, array $row ) use ( $wpdb, $orders, $stamp, $vendor, $buyer, &$made ): int {
	$wpdb->insert(
		$orders,
		$row + array(
			'order_number'   => "QA-SWEEP-{$stamp}-{$suffix}",
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'subtotal'       => 20,
			'total'          => 20,
			'currency'       => 'USD',
			'payment_status' => 'paid',
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/*
 * Run one sweep with its SELECT confined to $ids. When $flip_to is given, the
 * order's status is changed on the first query after that SELECT.
 */
$run = static function ( string $marker, string $id_column, array $ids, callable $sweep, int $flip_id = 0, string $flip_to = '' ) use ( $wpdb, $orders ): bool {
	$armed   = false;
	$flipped = false;
	$busy    = false;
	$filter  = static function ( $sql ) use ( $wpdb, $orders, $marker, $id_column, $ids, $flip_id, $flip_to, &$armed, &$flipped, &$busy ) {
		if ( $busy ) {
			return $sql;
		}
		if ( $armed && ! $flipped && $flip_id ) {
			$busy = true;
			$wpdb->update( $orders, array( 'status' => $flip_to ), array( 'id' => $flip_id ) );
			$busy    = false;
			$flipped = true;
		}
		if ( ! $armed && 0 === stripos( ltrim( $sql ), 'SELECT' ) && false !== strpos( $sql, $marker ) ) {
			$armed = true;
			$sql   = preg_replace( '/(\s+ORDER BY|\s*$)/', ' AND ' . $id_column . ' IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')$1', $sql, 1 );
		}
		return $sql;
	};
	add_filter( 'query', $filter );
	$sweep();
	remove_filter( 'query', $filter );
	return $armed && ( ! $flip_id || $flipped );
};

$manager = new OrderWorkflowManager();
add_filter( 'pre_wp_mail', '__return_true' ); // The sweeps notify; nothing needs to leave the machine.

// 1. Late sweep: control, then a race against a delivery.
$late = $seed( 'late', array( 'status' => ServiceOrder::STATUS_IN_PROGRESS, 'delivery_deadline' => $days( 1 ) ) );
$check( 'late sweep ran on the seeded order', $run( 'delivery_deadline <', 'id', array( $late ), array( $manager, 'check_late_orders' ) ) );
$check( 'an overdue order is marked late (control)', ServiceOrder::STATUS_LATE === $status( $late ), $status( $late ) );

$late2 = $seed( 'late2', array( 'status' => ServiceOrder::STATUS_IN_PROGRESS, 'delivery_deadline' => $days( 1 ) ) );
$check( 'late sweep raced a dispute', $run( 'delivery_deadline <', 'id', array( $late2 ), array( $manager, 'check_late_orders' ), $late2, ServiceOrder::STATUS_DISPUTED ) );
$check( 'an order disputed since the select is not marked late', ServiceOrder::STATUS_DISPUTED === $status( $late2 ), $status( $late2 ) );

// 2. Auto-complete raced by a dispute: no completion, no payout.
$done = $seed( 'done', array( 'status' => ServiceOrder::STATUS_DELIVERED ) );
$wpdb->insert( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $done, 'vendor_id' => $vendor, 'created_at' => $days( 30 ) ) );
$check( 'auto-complete sweep raced a dispute', $run( 'MAX( d.created_at )', 'o.id', array( $done ), array( $manager, 'auto_complete_orders' ), $done, ServiceOrder::STATUS_DISPUTED ) );
$check( 'an order disputed since the select is not auto-completed', ServiceOrder::STATUS_DISPUTED === $status( $done ), $status( $done ) );
$paid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_wallet_transactions WHERE id > %d AND user_id = %d", $ledger0, $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'and the vendor is not credited for it', 0 === $paid, "{$paid} ledger rows" );

// 3. Requirements timeout (cancel branch) raced by the buyer sending them.
$no_start = static function ( $value ) {
	return array( 'requirements_timeout_days' => 7, 'auto_start_on_timeout' => 0 ) + (array) $value;
};
add_filter( 'option_wpss_orders', $no_start );
$req = $seed( 'req', array( 'status' => ServiceOrder::STATUS_PENDING_REQUIREMENTS, 'paid_at' => $days( 30 ) ) );
$check( 'requirements sweep raced a submission', $run( 'COALESCE( paid_at, created_at )', 'id', array( $req ), array( $manager, 'check_requirements_timeout' ), $req, ServiceOrder::STATUS_IN_PROGRESS ) );
$check( 'an order started since the select is not cancelled', ServiceOrder::STATUS_IN_PROGRESS === $status( $req ), $status( $req ) );
remove_filter( 'option_wpss_orders', $no_start );

// 4. Cancellation timeout raced by the vendor declining.
$cancel = $seed(
	'cancel',
	array(
		'status' => ServiceOrder::STATUS_CANCELLATION_REQUESTED,
		'meta'   => wp_json_encode( array( 'cancellation_request' => array( 'requested_at' => $days( 3 ) ) ) ),
	)
);
$check( 'cancellation sweep raced a decline', $run( 'meta, updated_at', 'id', array( $cancel ), array( $manager, 'process_cancellation_timeouts' ), $cancel, ServiceOrder::STATUS_IN_PROGRESS ) );
$check( 'a cancellation the vendor declined since the select is not applied', ServiceOrder::STATUS_IN_PROGRESS === $status( $cancel ), $status( $cancel ) );

// 5. Offline auto-cancel raced by the admin confirming payment.
$hold = static fn( $value ) => array( 'auto_hold_hours' => 24 ) + (array) $value;
add_filter( 'option_wpss_offline_settings', $hold );
add_filter( 'default_option_wpss_offline_settings', $hold );
$offline = $seed(
	'offline',
	array(
		'status'         => ServiceOrder::STATUS_PENDING_PAYMENT,
		'payment_status' => 'pending',
		'payment_method' => 'offline',
		'created_at'     => $days( 30 ),
	)
);
$check( 'offline sweep raced a payment', $run( "payment_method = 'offline'", 'id', array( $offline ), array( $manager, 'process_offline_auto_cancel' ), $offline, ServiceOrder::STATUS_PENDING_REQUIREMENTS ) );
$check( 'an order paid since the select is not cancelled', ServiceOrder::STATUS_PENDING_REQUIREMENTS === $status( $offline ), $status( $offline ) );
remove_filter( 'option_wpss_offline_settings', $hold );
remove_filter( 'default_option_wpss_offline_settings', $hold );

// Clean up every row that points at a seeded order.
$ids = implode( ',', array_map( 'intval', $made ) );
foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = %s AND TABLE_NAME LIKE %s', 'order_id', $wpdb->esc_like( $wpdb->prefix . 'wpss_' ) . '%' ) ) as $table ) {
	$wpdb->query( "DELETE FROM `{$table}` WHERE order_id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
$wpdb->query( "DELETE FROM {$orders} WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_wallet_transactions WHERE id > %d AND user_id = %d", $ledger0, $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->sync_ledger_totals( $vendor );
$check( 'seeded orders removed', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE order_number LIKE %s", "QA-SWEEP-{$stamp}-%" ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
