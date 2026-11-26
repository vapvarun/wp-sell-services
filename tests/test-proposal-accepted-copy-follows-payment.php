<?php
/**
 * The vendor's "proposal accepted" notice does not say "start work" while the
 * order still waits for the buyer's payment (Basecamp 10379875093, item 4).
 *
 * Run: wp eval-file tests/test-proposal-accepted-copy-follows-payment.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\NotificationService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$notes  = $wpdb->prefix . 'wpss_notifications';
add_filter( 'pre_wp_mail', '__return_true' );

$vendor = wp_insert_user( array( 'user_login' => 'qa_rft_pa_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'qa_rft_pa_vendor@example.test' ) );
$made   = array();
$order  = static function ( string $status ) use ( $wpdb, $orders, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'QA-RFT-PA-' . wp_generate_password( 6, false ),
			'customer_id'    => 1,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'request',
			'status'         => $status,
			'payment_status' => 'pending_payment' === $status ? 'pending' : 'paid',
			'subtotal'       => 40,
			'total'          => 40,
			'currency'       => wpss_get_currency(),
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$text = static function ( $id ) use ( $wpdb, $notes ): string {
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$notes} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

try {
	$svc    = new NotificationService();
	$unpaid = $text( $svc->send( $vendor, 'proposal_accepted', array( 'order_id' => $order( 'pending_payment' ), 'request_title' => 'QA-RFT logo' ) ) );
	$check( 'unpaid hire: says it waits for the buyer\'s payment', false !== stripos( $unpaid, 'waiting for the buyer' ), $unpaid );
	$check( 'unpaid hire: does not say the order is open', false === stripos( $unpaid, 'order is now open' ), $unpaid );

	$paid = $text( $svc->send( $vendor, 'proposal_accepted', array( 'order_id' => $order( 'pending_requirements' ), 'request_title' => 'QA-RFT logo' ) ) );
	$check( 'paid hire: keeps the start-work copy', false !== stripos( $paid, 'start work' ) && false === stripos( $paid, 'waiting for the buyer' ), $paid );
} finally {
	foreach ( $made as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	$wpdb->delete( $notes, array( 'user_id' => $vendor ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
