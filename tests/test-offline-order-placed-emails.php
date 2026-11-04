<?php
/**
 * Placing a bank-transfer (offline) order emails the buyer the payment instructions and tells the admin and the seller.
 *
 * Run: wp eval-file tests/test-offline-order-placed-emails.php
 *
 * wpss_offline_order_created and wpss_offline_multi_orders_created had no listener, so a buyer who chose bank
 * transfer saw the instructions on screen only and nobody else heard about the order. Both hooks now send.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders = $wpdb->prefix . 'wpss_orders';
$mk     = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_offmail_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-offmail-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor = $mk( 'v' );
$buyer  = $mk( 'b' );
$ids    = array();
register_shutdown_function(
	static function () use ( $wpdb, $orders, &$ids ) {
		foreach ( $ids as $id ) {
			$wpdb->delete( $orders, array( 'id' => $id ) );
		}
	}
);
$make_order = static function () use ( $wpdb, $orders, $vendor, $buyer, &$ids ): int {
	$wpdb->insert( $orders, array( 'order_number' => 'QA-OFFMAIL-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'pending_payment', 'payment_status' => 'pending', 'payment_method' => 'offline', 'subtotal' => 80, 'total' => 80, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ) ) );
	$ids[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = array( 'to' => (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ), 'subject' => (string) $atts['subject'], 'message' => (string) $atts['message'] );
		return true;
	},
	10,
	2
);
$to = static function ( string $email ) use ( &$mails ): array {
	return array_values( array_filter( $mails, static fn( $m ) => str_contains( $m['to'], $email ) ) );
};
$admin_email = (string) apply_filters( 'wpss_admin_notification_email', get_option( 'admin_email' ) );

$one = $make_order();
do_action( 'wpss_offline_order_created', $one, wpss_get_order( $one ) );
$order_no = wpss_get_order( $one )->order_number;
$b        = $to( get_userdata( $buyer )->user_email );
$check( 'buyer gets one email when the order is placed', 1 === count( $b ), (string) count( $b ) );
$check( 'it carries the payment instructions and the order number', isset( $b[0] ) && str_contains( $b[0]['message'], 'Payment instructions' ) && str_contains( $b[0]['message'], $order_no ) );
$check( 'admin is told an order is waiting for payment', count( $to( $admin_email ) ) >= 1 );
$s = $to( get_userdata( $vendor )->user_email );
$check( 'seller is told the order awaits payment, and not asked to start work', 1 === count( $s ) && ! str_contains( $s[0]['message'], 'Submit requirements' ) );

// Putting the same order on the offline rail again (a retried request) does not mail everyone a second time.
$before = count( $mails );
do_action( 'wpss_offline_order_created', $one, wpss_get_order( $one ) );
$check( 'a repeat for the same order sends nothing more', count( $mails ) === $before, (string) ( count( $mails ) - $before ) );

// The cart path places several orders at once: one email per order, not one per hook.
$mails = array();
$two   = array( $make_order(), $make_order() );
do_action( 'wpss_offline_multi_orders_created', $two, $buyer );
$check( 'multi-order checkout: buyer gets one email per order', 2 === count( $to( get_userdata( $buyer )->user_email ) ), (string) count( $to( get_userdata( $buyer )->user_email ) ) );

// An owner who left the instructions blank: the email says how the buyer will hear, like the order page, not an empty section.
$blank = static function ( $value ) {
	$value                 = is_array( $value ) ? $value : array();
	$value['instructions'] = '';
	unset( $value['methods'] );
	return $value;
};
add_filter( 'option_wpss_offline_settings', $blank );
add_filter( 'default_option_wpss_offline_settings', $blank );
$mails = array();
$three = $make_order();
do_action( 'wpss_offline_order_created', $three, wpss_get_order( $three ) );
$b = $to( get_userdata( $buyer )->user_email );
$check( 'blank instructions: the buyer email says the owner will send them', isset( $b[0] ) && str_contains( $b[0]['message'], 'The site owner will send you payment instructions by email.' ) );
remove_filter( 'option_wpss_offline_settings', $blank );
remove_filter( 'default_option_wpss_offline_settings', $blank );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
