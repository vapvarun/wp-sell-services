<?php
/**
 * A service with no requirement questions starts at payment instead of asking the buyer for a brief
 * (Basecamp 10399066241).
 *
 * Run: wp eval-file tests/test-no-requirements-starts-order.php
 *
 * Every paid order of every rail goes through StandaloneOrderProvider::mark_as_paid(), so this test pays one
 * order per case there. It seeds a throwaway vendor, buyer and two services, captures wp_mail, and removes them.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;
use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_nr_vendor_' . $suffix, 'user_email' => 'wpss_nr_vendor_' . $suffix . '@example.test' ) );
$buyer  = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_nr_buyer_' . $suffix, 'user_email' => 'wpss_nr_buyer_' . $suffix . '@example.test' ) );
$mails  = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = array( 'to' => (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ), 'message' => (string) ( $atts['message'] ?? '' ) );

		return true;
	},
	10,
	2
);

$plain = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA no questions ' . $suffix, 'post_author' => $vendor->ID ) );
$asks  = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA with questions ' . $suffix, 'post_author' => $vendor->ID ) );
wpss_save_service_requirements( $asks, array( array( 'question' => 'What is the brand name?', 'type' => 'text', 'required' => true ) ) );

$order_ids = array();
$make      = static function ( int $service_id, string $platform ) use ( $wpdb, $vendor, $buyer, &$order_ids ): int {
	$wpdb->insert(
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number'   => 'NR-' . wp_generate_password( 8, false, false ),
			'customer_id'    => $buyer->ID,
			'vendor_id'      => $vendor->ID,
			'service_id'     => $service_id,
			'package_id'     => 0,
			'subtotal'       => 50,
			'total'          => 50,
			'currency'       => wpss_get_currency(),
			'status'         => ServiceOrder::STATUS_PENDING_PAYMENT,
			'payment_status' => 'pending',
			'platform'       => $platform,
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);
	$order_ids[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};
$status = static fn( int $id ): string => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $id ) );

$provider = new StandaloneOrderProvider();
$plain_id = $make( $plain, 'standalone' );
$asks_id  = $make( $asks, 'standalone' );
$req_id   = $make( $plain, 'request' );

$mails = array();
$provider->mark_as_paid( $plain_id, 'txn_nr_' . $suffix, 'test' );
$plain_mails = $mails;
$provider->mark_as_paid( $asks_id, 'txn_nr2_' . $suffix, 'test' );
$provider->mark_as_paid( $req_id, 'txn_nr3_' . $suffix, 'test' );

$check( 'a service with no questions is In Progress once paid', ServiceOrder::STATUS_IN_PROGRESS === $status( $plain_id ) );
$check( 'its started_at is set', '' !== (string) $wpdb->get_var( $wpdb->prepare( "SELECT started_at FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $plain_id ) ) );
$check( 'a service with questions still waits for the buyer', ServiceOrder::STATUS_PENDING_REQUIREMENTS === $status( $asks_id ) );
$check( 'a buyer-request order keeps its own flow', ServiceOrder::STATUS_PENDING_REQUIREMENTS === $status( $req_id ) );

$buyer_mail = '';
foreach ( $plain_mails as $mail ) {
	if ( false !== strpos( $mail['to'], $buyer->user_email ) ) {
		$buyer_mail .= $mail['message'];
	}
}
$check( 'the buyer hears the order started, not a request for requirements', '' !== $buyer_mail && false === stripos( $buyer_mail, 'Submit Requirements' ) && false !== stripos( $buyer_mail, 'started working' ) );

$check( 'the helper agrees', ! wpss_order_needs_requirements( (object) array( 'service_id' => $plain, 'platform' => 'standalone' ) ) && wpss_order_needs_requirements( (object) array( 'service_id' => $asks, 'platform' => 'standalone' ) ) );

foreach ( $order_ids as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
}
wp_delete_post( $plain, true );
wp_delete_post( $asks, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor->ID );
wp_delete_user( $buyer->ID );

exit( $fails ? 1 : 0 );
