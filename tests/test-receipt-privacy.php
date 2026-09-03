<?php
/**
 * A buyer's proof of payment is private, like a delivery.
 *
 * Receipts went to the media library, so the bank transfer slip a buyer
 * uploaded was fetchable by anyone holding the wp-content/uploads URL - no
 * login, no relation to the order (Basecamp 10267994010). They now go through
 * the one order-file seam and are served only to the order's buyer, its vendor
 * and administrators.
 *
 * The upload itself cannot be driven from WP-CLI: wpss_store_order_file()
 * refuses anything is_uploaded_file() rejects, and that is always false outside
 * a real multipart request - correctly so, and not something to weaken for a
 * test. So the submit path is asserted by WHERE IT ROUTES (the private store's
 * refusal, not the media library's) plus "it created no attachment", and the
 * stored shape is seeded the way the fixed submit() writes it.
 *
 * Run: wp eval-file tests/test-receipt-privacy.php
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\PaymentReceiptRepository;
use WPSellServices\Database\SchemaManager;
use WPSellServices\Services\PaymentReceiptService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

// Pre-fix builds have no such helper. Stubbed rather than guarded at every call
// so the run reports its failures and still cleans up after itself.
if ( ! function_exists( 'wpss_get_receipt_file' ) ) {
	/**
	 * Stand-in for the missing helper.
	 *
	 * @param object $receipt Receipt row.
	 * @return array{url:string,is_image:bool,name:string}
	 */
	function wpss_get_receipt_file( object $receipt ): array { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		unset( $receipt );

		return array(
			'url'      => '',
			'is_image' => false,
			'name'     => '',
		);
	}
}

$receipts_table = $wpdb->prefix . 'wpss_payment_receipts';
$uploads        = wp_upload_dir();

// A 1x1 PNG, the smallest honest "photo of a bank slip".
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

// --- the schema this release ships ----------------------------------------
( new SchemaManager() )->sync();

$has_column = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
		DB_NAME,
		$receipts_table,
		'attachments'
	)
);
$check( 'receipts table holds a file record, not just an attachment id', 1 === $has_column );

// --- a throwaway offline order awaiting payment ----------------------------
$saved_receipt_settings = get_option( 'wpss_offline_receipt_settings', array() );
update_option( 'wpss_offline_receipt_settings', array( 'enabled' => 1 ) );

$buyer  = wp_insert_user(
	array(
		'user_login' => 'wpss-receipt-buyer-' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wpss-receipt-buyer-' . wp_rand() . '@example.com',
		'role'       => 'subscriber',
	)
);
$vendor = wp_insert_user(
	array(
		'user_login' => 'wpss-receipt-vendor-' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wpss-receipt-vendor-' . wp_rand() . '@example.com',
		'role'       => 'subscriber',
	)
);
$buyer  = (int) $buyer;
$vendor = (int) $vendor;

$wpdb->insert(
	$wpdb->prefix . 'wpss_orders',
	array(
		'order_number'   => 'WPSS-RECEIPT-CONTRACT-' . wp_rand(),
		'customer_id'    => $buyer,
		'vendor_id'      => $vendor,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'payment_method' => 'offline',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'pending_payment',
		'payment_status' => 'pending',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$service = new PaymentReceiptService();
$repo    = new PaymentReceiptRepository();

// --- submit() routes to the private store, never the media library ---------
$tmp = wp_tempnam( 'wpss-receipt' );
file_put_contents( $tmp, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$attachments_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );

wp_set_current_user( $buyer );
$submitted = $service->submit(
	$order_id,
	$buyer,
	array(
		'name'     => array( 'bank-slip.png' ),
		'type'     => array( 'image/png' ),
		'tmp_name' => array( $tmp ),
		'error'    => array( 0 ),
		'size'     => array( strlen( $png ) ),
	),
	'ref 12345'
);

$submit_error = is_wp_error( $submitted ) ? $submitted->get_error_message() : '';

$check(
	'submit() hands the file to the order store, not the media library',
	is_wp_error( $submitted ) && false !== strpos( $submit_error, 'upload failed' )
);
$check(
	'a refused receipt leaves nothing in the media library',
	$attachments_before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" )
);
$check( 'nothing was recorded for a refused upload', array() === $repo->get_for_order( $order_id ) );

wp_delete_file( $tmp );

// --- the stored shape, as the fixed submit() writes it ---------------------
$dir = wpss_get_order_files_dir() . $order_id . '/';
wp_mkdir_p( $dir );
file_put_contents( $dir . 'bank-slip.png', $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$record = array(
	'id'          => wp_generate_uuid4(),
	'name'        => 'bank-slip.png',
	'type'        => 'image/png',
	'size'        => strlen( $png ),
	'path'        => 'bank-slip.png',
	'order_id'    => $order_id,
	'kind'        => 'receipt',
	'user_id'     => $buyer,
	'remote_path' => null,
	'provider'    => null,
);

$receipt_id = (int) $repo->insert(
	array(
		'order_id'      => $order_id,
		'uploaded_by'   => $buyer,
		'attachment_id' => 0,
		'attachments'   => (string) wp_json_encode( array( $record ) ),
		'note'          => 'ref 12345',
		'status'        => 'submitted',
		'created_at'    => current_time( 'mysql' ),
	)
);
$check( 'a receipt row holding a file record can be written', $receipt_id > 0 );

$stored = (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$receipts_table} WHERE id = %d", $receipt_id ) );
$check( 'no public uploads URL is stored on the receipt row', '' !== $stored && false === strpos( $stored, '/uploads/' ) && false === strpos( $stored, '"url"' ) );
$check( 'record kind is receipt', false !== strpos( $stored, '"kind":"receipt"' ) );

$found = wpss_find_order_file( $order_id, $record['id'] );
$check( 'the order read gate can find a receipt', null !== $found && 'receipt' === ( $found['kind'] ?? '' ) );

// --- who may read it -------------------------------------------------------
$check( 'the buyer may read it', wpss_can_read_order_files( $order_id, $buyer ) );
$check( 'the vendor may read it', wpss_can_read_order_files( $order_id, $vendor ) );
$check( 'an administrator may read it', wpss_can_read_order_files( $order_id, 1 ) );

$stranger = wp_insert_user(
	array(
		'user_login' => 'wpss-receipt-stranger-' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wpss-receipt-stranger-' . wp_rand() . '@example.com',
		'role'       => 'subscriber',
	)
);
$stranger = (int) $stranger;
$check( 'another logged-in member may not', ! wpss_can_read_order_files( $order_id, $stranger ) );

// 0 means "whoever is asking", so nobody has to be logged in for this to be
// the real question.
wp_set_current_user( 0 );
$check( 'a logged-out visitor may not', ! wpss_can_read_order_files( $order_id ) );

$located = wpss_locate_order_file( $order_id, $record['id'] );
$check( 'an entitled reader is served the bytes from the private store', ! is_wp_error( $located ) && ! empty( $located['path'] ) && is_readable( $located['path'] ) );
$check( 'the private store is not under wp-content/uploads', ! is_wp_error( $located ) && false === strpos( (string) ( $located['path'] ?? '' ), $uploads['basedir'] . '/20' ) );

// --- both URL emitters hand out the gated link -----------------------------
wp_set_current_user( 1 );
$row  = $receipt_id > 0 ? $repo->find( $receipt_id ) : null;
$file = $row ? wpss_get_receipt_file( $row ) : array( 'url' => '' );

$check( 'the receipt link goes through the gated endpoint', false !== strpos( $file['url'], 'action=wpss_order_file' ) );
$check( 'the receipt link leaks no uploads path', '' !== $file['url'] && false === strpos( $file['url'], '/uploads/' ) );

$admin_box = new ReflectionMethod( \WPSellServices\Integrations\Gateways\OfflineGateway::class, 'render_admin_receipt_review' );
$admin_box->setAccessible( true );
ob_start();
$admin_box->invoke( new \WPSellServices\Integrations\Gateways\OfflineGateway(), $order_id );
$admin_html = (string) ob_get_clean();

$check( 'the admin review box links through the gated endpoint', false !== strpos( $admin_html, 'action=wpss_order_file' ) );
$check( 'the admin review box leaks no uploads path', false === strpos( $admin_html, '/uploads/' ) );

wp_set_current_user( $buyer );
$request  = new WP_REST_Request( 'GET', '/wpss/v1/orders/' . $order_id . '/receipts' );
$request->set_param( 'id', $order_id );
$response = rest_do_request( $request );
$rest     = (array) $response->get_data();
$rest_url = (string) ( $rest[0]['file_url'] ?? '' );

$check( 'GET /orders/{id}/receipts answers the buyer', 200 === $response->get_status() && '' !== $rest_url );
$check( 'REST returns the gated URL', false !== strpos( $rest_url, 'action=wpss_order_file' ) && false === strpos( $rest_url, '/uploads/' ) );

// A logged-out fetch of that link is refused: admin-post.php authenticates from
// the session cookie, and this request carries none.
$anon = wp_remote_get( html_entity_decode( $rest_url ), array( 'timeout' => 10, 'sslverify' => false ) );
$check(
	'a logged-out fetch of the receipt is refused',
	! is_wp_error( $anon ) && 200 !== (int) wp_remote_retrieve_response_code( $anon ) && false === strpos( (string) wp_remote_retrieve_body( $anon ), $png )
);

// --- a pre-1.7.1 receipt is moved out of the media library -----------------
$legacy_name = 'legacy-slip-' . wp_rand() . '.png';
$legacy_file = trailingslashit( $uploads['path'] ) . $legacy_name;
file_put_contents( $legacy_file, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$legacy_attachment = (int) wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'legacy slip',
		'post_status'    => 'private',
	),
	$legacy_file
);

$legacy_url = (string) wp_get_attachment_url( $legacy_attachment );

$wpdb->insert(
	$receipts_table,
	array(
		'order_id'      => $order_id,
		'uploaded_by'   => $buyer,
		'attachment_id' => $legacy_attachment,
		'note'          => 'legacy',
		'status'        => 'submitted',
		'created_at'    => current_time( 'mysql' ),
	)
);
$legacy_receipt_id = (int) $wpdb->insert_id;

$leak = wp_remote_get( $legacy_url, array( 'timeout' => 10, 'sslverify' => false ) );
$check(
	'the legacy receipt starts out publicly readable (the reported bug)',
	! is_wp_error( $leak ) && 200 === (int) wp_remote_retrieve_response_code( $leak )
);

( new SchemaManager() )->sync();

$legacy_stored = (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$receipts_table} WHERE id = %d", $legacy_receipt_id ) );
$check( 'the upgrade gives the legacy row a private file record', '' !== $legacy_stored && false !== strpos( $legacy_stored, '"kind":"receipt"' ) );
$check( 'the upgrade drops the public URL from the row', '' !== $legacy_stored && false === strpos( $legacy_stored, '"url"' ) && false === strpos( $legacy_stored, '/uploads/' ) );

$after = wp_remote_get( $legacy_url, array( 'timeout' => 10, 'sslverify' => false ) );
$check( 'the moved file is no longer served from uploads', ! is_wp_error( $after ) && 200 !== (int) wp_remote_retrieve_response_code( $after ) );
$check( 'the media-library row went with it', null === get_post( $legacy_attachment ) );

wp_set_current_user( 1 );
$legacy_row  = $repo->find( $legacy_receipt_id );
$legacy_link = $legacy_row ? wpss_get_receipt_file( $legacy_row ) : array( 'url' => '' );
$check( 'the migrated receipt still opens, through the gate', false !== strpos( $legacy_link['url'], 'action=wpss_order_file' ) );

// --- cleanup ---------------------------------------------------------------
wp_set_current_user( 0 );
update_option( 'wpss_offline_receipt_settings', $saved_receipt_settings );

$wpdb->delete( $receipts_table, array( 'order_id' => $order_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $order_id ) );

if ( get_post( $legacy_attachment ) ) {
	wp_delete_attachment( $legacy_attachment, true );
}
if ( file_exists( $legacy_file ) ) {
	wp_delete_file( $legacy_file );
}

wpss_rmdir_recursive( wpss_get_order_files_dir() . $order_id . '/' );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $buyer, $vendor, $stranger ) as $uid ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $uid ) );
	if ( $uid > 0 ) {
		wp_delete_user( $uid );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
