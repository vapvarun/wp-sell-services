<?php
/**
 * The requirements clock starts when the order is paid, not when it was placed.
 *
 * Run: wp eval-file tests/test-requirements-clock-from-payment.php
 *
 * Basecamp 10336731914: the requirements timeout and reminders counted from
 * created_at, so an offline order placed weeks ago and paid an hour ago was
 * auto-started - and sent every reminder - on the next hourly run.
 *
 * Throwaway buyer, vendor and orders; every row is removed at the end.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';

$settings = static fn( $value ) => array_merge(
	is_array( $value ) ? $value : array(),
	array(
		'requirements_timeout_days' => 7,
		'auto_start_on_timeout'     => true,
	)
);
add_filter( 'option_wpss_orders', $settings );
add_filter( 'pre_wp_mail', '__return_true' );

$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_rq_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_rq_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_rq_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_rq_vendor@example.test' ) );
$made   = array();

$ago   = static fn( int $hours ) => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $hours * HOUR_IN_SECONDS );
$order = static function ( string $created, ?string $paid ) use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'DEVF1-RQ-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'status'         => 'pending_requirements',
			'payment_status' => 'paid',
			'payment_method' => 'offline',
			'subtotal'       => 40,
			'total'          => 40,
			'currency'       => wpss_get_currency(),
			'paid_at'        => $paid,
			'created_at'     => $created,
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

try {
	$late_paid = $order( $ago( 30 * 24 ), $ago( 1 ) );     // Placed 30 days ago, paid an hour ago.
	$old_paid  = $order( $ago( 30 * 24 ), $ago( 8 * 24 ) ); // Paid 8 days ago: past the 7-day timeout.
	$no_paid   = $order( $ago( 8 * 24 ), null );             // paid_at missing: falls back to created_at.

	$manager = new \WPSellServices\Services\OrderWorkflowManager();
	$manager->send_requirements_reminders();
	$check( 'reminders: none for the order paid an hour ago', 0 === (int) get_option( 'wpss_requirements_reminder_' . $late_paid, 0 ) );
	$check( 'reminders: the order paid 8 days ago got one', (int) get_option( 'wpss_requirements_reminder_' . $old_paid, 0 ) > 0 );

	$manager->check_requirements_timeout();
	$check( sprintf( 'timeout: paid an hour ago stays pending_requirements (%s)', $status( $late_paid ) ), 'pending_requirements' === $status( $late_paid ) );
	$check( sprintf( 'timeout: paid 8 days ago is auto-started (%s)', $status( $old_paid ) ), 'in_progress' === $status( $old_paid ) );
	$check( sprintf( 'timeout: no paid_at, created 8 days ago, auto-started (%s)', $status( $no_paid ) ), 'in_progress' === $status( $no_paid ) );
} finally {
	foreach ( $made as $id ) {
		delete_option( 'wpss_requirements_reminder_' . $id );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE JSON_EXTRACT(data, '$.order_id') = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	remove_filter( 'option_wpss_orders', $settings );
	remove_filter( 'pre_wp_mail', '__return_true' );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
