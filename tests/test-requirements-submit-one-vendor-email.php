<?php
/**
 * Submitting requirements emails the vendor once (Basecamp 10475484314).
 *
 * Run: wp eval-file tests/test-requirements-submit-one-vendor-email.php
 *
 * RequirementsService::submit() saves the answers (the vendor's "Requirements
 * Submitted" mail) and then starts the order (the "Now In Progress" mail to both
 * sides), so the vendor got two mails for one event. The buyer still gets In
 * Progress, and every other way into In Progress (skip, timeout, resume) still
 * mails the vendor.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\RequirementsService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_rq_vendor_' . $suffix, 'user_email' => 'wpss_rq_vendor_' . $suffix . '@example.test' ) );
$buyer  = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_rq_buyer_' . $suffix, 'user_email' => 'wpss_rq_buyer_' . $suffix . '@example.test' ) );
$mails  = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = array( 'to' => (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ), 'subject' => (string) $atts['subject'] );

		return true;
	},
	10,
	2
);
$subjects = static function ( string $addr ) use ( &$mails ): array {
	return array_values( array_column( array_filter( $mails, static fn( $m ) => str_contains( $m['to'], $addr ) ), 'subject' ) );
};

$service = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA requirements ' . $suffix, 'post_author' => $vendor->ID ) );
wpss_save_service_requirements( $service, array( array( 'question' => 'Brand name?', 'type' => 'text', 'required' => true ) ) );

$order_ids = array();
$make      = static function () use ( $wpdb, $vendor, $buyer, $service, &$order_ids ): int {
	$wpdb->insert(
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number'   => 'RQ-' . wp_generate_password( 8, false, false ),
			'customer_id'    => $buyer->ID,
			'vendor_id'      => $vendor->ID,
			'service_id'     => $service,
			'package_id'     => 0,
			'subtotal'       => 50,
			'total'          => 50,
			'currency'       => wpss_get_currency(),
			'status'         => ServiceOrder::STATUS_PENDING_REQUIREMENTS,
			'payment_status' => 'paid',
			'platform'       => 'standalone',
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);
	$order_ids[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$requirements = new RequirementsService();
$fields       = $requirements->get_service_fields( $service );
$answers      = array();
foreach ( $fields as $field ) {
	$answers[ $field['id'] ] = 'Acme';
}

$mails  = array();
$result = $requirements->submit( $make(), $answers );
$check( 'requirements are accepted', ! empty( $result['success'] ), wp_json_encode( $result ) );
$vendor_got = $subjects( $vendor->user_email );
$check( 'the vendor gets ONE mail', 1 === count( $vendor_got ), wp_json_encode( $vendor_got ) );
$check( 'and it is "Requirements Submitted"', 1 === count( $vendor_got ) && false !== stripos( $vendor_got[0], 'Requirements Submitted' ) );
$check( 'the buyer still gets "Now In Progress"', (bool) array_filter( $subjects( $buyer->user_email ), static fn( $s ) => false !== stripos( $s, 'In Progress' ) ), wp_json_encode( $subjects( $buyer->user_email ) ) );

// Skipping requirements (REST and AJAX both call start_work) still tells the vendor to start.
$mails = array();
( new OrderService() )->start_work( $make() );
$check( 'a skipped order still mails the vendor "Now In Progress"', (bool) array_filter( $subjects( $vendor->user_email ), static fn( $s ) => false !== stripos( $s, 'In Progress' ) ), wp_json_encode( $subjects( $vendor->user_email ) ) );

foreach ( $order_ids as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $id ) );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE user_id IN (%d, %d)", $buyer->ID, $vendor->ID ) );
wp_delete_post( $service, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor->ID );
wp_delete_user( $buyer->ID );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
