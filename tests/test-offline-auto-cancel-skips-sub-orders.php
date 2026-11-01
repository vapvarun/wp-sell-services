<?php
/**
 * The offline auto-cancel cron cancels whole orders only, never a phase, tip or extension.
 *
 * Run: wp eval-file tests/test-offline-auto-cancel-skips-sub-orders.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table   = $wpdb->prefix . 'wpss_orders';
$buyer   = (int) get_user_by( 'login', 'wpss_buyer_ava' )->ID;
$old     = gmdate( 'Y-m-d H:i:s', time() - 100 * HOUR_IN_SECONDS );
$ids     = array();
$insert  = static function ( string $platform, int $parent = 0 ) use ( $wpdb, $table, $buyer, $old, &$ids ): int {
	$wpdb->insert(
		$table,
		array(
			'order_number'      => 'TEST-' . strtoupper( wp_generate_password( 6, false ) ),
			'customer_id'       => $buyer,
			'vendor_id'         => 7834,
			'service_id'        => 11461,
			'platform'          => $platform,
			'platform_order_id' => $parent,
			'status'            => 'pending_payment',
			'payment_status'    => 'pending',
			'payment_method'    => 'offline',
			'subtotal'          => 50,
			'total'             => 50,
			'currency'          => 'USD',
			'created_at'        => $old,
		)
	);
	$ids[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$whole = $insert( 'standalone' );
$req   = $insert( 'request' );
// The running contract the sub-orders belong to. (Cancelling a parent cascades to its phases, which is a different path.)
$parent = $insert( 'standalone' );
$wpdb->update( $table, array( 'status' => 'in_progress', 'payment_status' => 'paid' ), array( 'id' => $parent ) );
$phase = $insert( 'milestone', $parent );
$tip   = $insert( 'tip', $parent );
$ext   = $insert( 'extension', $parent );

$settings = get_option( 'wpss_offline_settings', array() );
update_option( 'wpss_offline_settings', array_merge( (array) $settings, array( 'auto_hold_hours' => 72 ) ) );
add_filter( 'pre_wp_mail', '__return_true' );

( new \WPSellServices\Services\OrderWorkflowManager() )->process_offline_auto_cancel();

$status = static fn( int $id ): string => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id ) );

$check( 'a whole unpaid order is auto-cancelled', 'cancelled' === $status( $whole ) );
$check( 'a custom-request order is a whole order, so it is cancelled too', 'cancelled' === $status( $req ) );
$check( 'an unpaid phase is left pending', 'pending_payment' === $status( $phase ) );
$check( 'an unpaid tip is left pending', 'pending_payment' === $status( $tip ) );
$check( 'an unpaid extension is left pending', 'pending_payment' === $status( $ext ) );

update_option( 'wpss_offline_settings', $settings );
remove_filter( 'pre_wp_mail', '__return_true' );
$wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
