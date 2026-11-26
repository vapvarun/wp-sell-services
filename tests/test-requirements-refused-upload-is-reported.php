<?php
/**
 * A requirements file the site's upload rule refuses is reported to the buyer,
 * not dropped while the form says "submitted" (Basecamp 10380882200, item 5).
 *
 * RequirementsService::process_uploads() used to `continue` past a file that
 * wpss_check_upload() refused, so the brief was saved and the order started
 * with the file silently missing. The refusal now comes back through submit(),
 * which the AJAX form (data.message) and REST (message) both show.
 *
 * Run: wp eval-file tests/test-requirements-refused-upload-is-reported.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\RequirementsService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
add_filter( 'pre_wp_mail', '__return_true' );

$buyer  = wp_insert_user( array( 'user_login' => 'qa_rft_req_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'qa_rft_req_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'qa_rft_req_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'qa_rft_req_vendor@example.test' ) );
$wpdb->insert(
	$orders,
	array(
		'order_number'   => 'QA-RFT-REQ-' . wp_generate_password( 6, false ),
		'customer_id'    => $buyer,
		'vendor_id'      => $vendor,
		'service_id'     => 0,
		'platform'       => 'request',
		'status'         => 'pending_requirements',
		'payment_status' => 'paid',
		'subtotal'       => 40,
		'total'          => 40,
		'currency'       => wpss_get_currency(),
		'paid_at'        => current_time( 'mysql' ),
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

// A PHP script renamed nothing: its real bytes cannot be verified as an allowed type.
$tmp = wp_tempnam( 'qa-rft-req' );
file_put_contents( $tmp, "<?php echo 'x';" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

try {
	wp_set_current_user( $buyer );
	$result = ( new RequirementsService() )->submit(
		$order_id,
		array( 'description' => 'QA-RFT brief' ),
		array(
			'requirements_files' => array(
				'name'     => array( 'brief.php' ),
				'type'     => array( 'application/pdf' ),
				'tmp_name' => array( $tmp ),
				'error'    => array( UPLOAD_ERR_OK ),
				'size'     => array( filesize( $tmp ) ),
			),
		)
	);
	$status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$check( 'submit() refuses when a file is refused', empty( $result['success'] ) );
	$check( 'the message names the file and the reason', false !== strpos( (string) ( $result['message'] ?? '' ), 'brief.php' ) && false !== strpos( (string) ( $result['message'] ?? '' ), 'could not be verified' ) );
	$check( 'the order is not started without the file', 'pending_requirements' === $status );
	$check( 'nothing is saved for the order', ! ( new RequirementsService() )->has_requirements( $order_id ) );
} finally {
	wp_set_current_user( 1 );
	wp_delete_file( $tmp );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $order_id ) );
	$wpdb->delete( $orders, array( 'id' => $order_id ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
