<?php
/**
 * Digital products: pay and download at once (Basecamp 10551449503).
 *
 * Run: wp eval-file tests/test-digital-products.php
 *
 * A `digital` service sells a file kept in the one private store (folder s{service}, scope `service`).
 * Paying its order (mark_as_paid(), which every rail calls) skips the brief and delivers at once; the
 * order records its kind; only the seller, an admin and a buyer with a standing paid order may download,
 * and each download is one audit row. Uploads run in a real HTTP request (is_uploaded_file refuses CLI
 * files), through a token-gated mu-plugin written for this run and removed after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! function_exists( 'wpss_add_product_file' ) || ! isset( wpss_get_service_types()['digital'] ) ) {
	$check( 'A digital product type with a file exists', false, 'missing' );
	echo "\n1 FAILED\n";
	exit( 1 );
}

$f        = wpss_test_fixtures();
$outsider = (int) wp_insert_user( array( 'user_login' => $f->tag . '_out', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$token    = wp_generate_password( 24, false );
$mu       = WPMU_PLUGIN_DIR . '/qa-503-' . wp_generate_password( 6, false ) . '.php';
$base     = getenv( 'WPSS_QA_BASE_URL' ) ? rtrim( (string) getenv( 'WPSS_QA_BASE_URL' ), '/' ) : untrailingslashit( home_url() );
$sid      = ( $f->service )( 'publish' );
$fixed    = ( $f->service )( 'publish' );
$packages = array(
	array(
		'name'          => 'Personal',
		'price'         => 20,
		'delivery_days' => 1,
		'revisions'     => 2,
	),
);
update_post_meta( $sid, '_wpss_packages', $packages );
update_post_meta( $fixed, '_wpss_packages', $packages );
wpss_set_service_type( $sid, 'digital' );
update_post_meta( $sid, '_wpss_requirements', array( array( 'question' => 'Old question left from a fixed past' ) ) );

wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$mu,
	'<?php
add_action( "admin_post_nopriv_qa_503", static function () {
	if ( ! hash_equals( ' . var_export( $token, true ) . ', (string) ( $_POST["t"] ?? "" ) ) ) { wp_die( "no", 403 ); }
	add_filter( "upload_mimes", static fn( $m ) => $m + array( "txt" => "text/plain" ) );
	add_filter( "wpss_delivery_allowed_file_types", static fn( $t ) => array_merge( (array) $t, array( "txt" ) ) );
	add_filter( "wpss_allowed_file_types", static fn( $t ) => array_merge( (array) $t, array( "txt" ) ) );
	wp_set_current_user( (int) $_POST["u"] );
	$r = new WP_REST_Request( "POST", "/wpss/v1/services/" . (int) $_POST["s"] . "/file" );
	$r->set_file_params( $_FILES );
	$res = rest_do_request( $r );
	wp_send_json( array( "status" => $res->get_status(), "data" => $res->get_data() ) );
} );
'
);

$upload = static function ( int $user, string $bytes ) use ( $base, $token, $sid ) {
	$boundary = wp_generate_password( 16, false );
	$body     = '';
	foreach ( array( 'action' => 'qa_503', 't' => $token, 'u' => (string) $user, 's' => (string) $sid ) as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"guide.txt\"\r\nContent-Type: text/plain\r\n\r\n{$bytes}\r\n--{$boundary}--\r\n";
	$res   = wp_remote_post(
		$base . '/wp-admin/admin-post.php',
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => "multipart/form-data; boundary={$boundary}" ),
			'body'    => $body,
		)
	);
	// The upload ran in another process: drop this one's cached meta for the product.
	wp_cache_delete( $sid, 'post_meta' );
	return is_wp_error( $res ) ? array( 'status' => 0 ) : (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
};

// A browser request as one member, through the web door the order page links.
// Links the plugin builds carry home_url(); a runner that reaches the site at another address
// (a CLI container, WPSS_QA_BASE_URL) fetches them there, same path and query.
$fetch = static function ( string $url, int $user ) use ( $base ) {
	$home = untrailingslashit( home_url() );
	$url  = 0 === strpos( $url, $home ) ? $base . substr( $url, strlen( $home ) ) : $url;
	$exp  = time() + HOUR_IN_SECONDS;
	$cookies = $user ? array(
		AUTH_COOKIE      => wp_generate_auth_cookie( $user, $exp, 'auth' ),
		LOGGED_IN_COOKIE => wp_generate_auth_cookie( $user, $exp, 'logged_in' ),
	) : array();
	$res     = wp_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'redirection' => 0,
			'cookies'     => $cookies,
		)
	);
	return is_wp_error( $res ) ? array( 0, '' ) : array( (int) wp_remote_retrieve_response_code( $res ), (string) wp_remote_retrieve_body( $res ) );
};

$rest = static function ( string $method, string $route, int $user, array $params = array() ) {
	wp_set_current_user( $user );
	$r = new WP_REST_Request( $method, $route );
	$r->set_query_params( $params );
	$res = rest_do_request( $r );
	return array( $res->get_status(), $res->get_data() );
};

$orders = array();
$dir    = '';

try {
	// 1. The type.
	$type = wpss_get_service_types()['digital'];
	$check( 'digital is registered: buyable, a file step, no brief, no add-ons, no delivery time', $type['purchasable'] && in_array( 'file', $type['steps'], true ) && ! in_array( 'requirements', $type['steps'], true ) && ! $type['addons'] && ! $type['delivery'] );
	$check( '  fixed and custom have no file step', ! wpss_service_type_supports( $fixed, 'file' ) );

	// 2. No file yet: not for sale, cannot go live.
	$check( 'With no file, the purchase gate refuses it', str_contains( wpss_service_unavailable_reason( $sid ), 'no file' ), wpss_service_unavailable_reason( $sid ) );
	$check( '  and publishing names the missing file', in_array( 'Please upload the file buyers download.', wpss_get_service_publish_errors( $sid ), true ) );

	// 3. Uploads: owner only, each one a new version, bytes in the private store.
	$denied = $upload( $outsider, 'not yours' );
	$check( 'Another member cannot upload to the product', 403 === (int) ( $denied['status'] ?? 0 ), wp_json_encode( $denied ) );
	$v1 = $upload( (int) $f->vendor, 'version one' );
	$check( 'The seller uploads the file (POST /services/{id}/file, 201)', 201 === (int) ( $v1['status'] ?? 0 ) && 'guide.txt' === ( $v1['data']['name'] ?? '' ) && 1 === (int) ( $v1['data']['version'] ?? 0 ), wp_json_encode( $v1 ) );
	$v2 = $upload( (int) $f->vendor, 'version two' );
	$current = wpss_get_product_file( $sid );
	$check( '  a second upload is version 2 and becomes the current file', 2 === (int) ( $v2['data']['version'] ?? 0 ) && (string) ( $current['id'] ?? '' ) === (string) ( $v2['data']['id'] ?? 'x' ) );
	$path = $current ? wpss_find_local_order_file( wpss_file_folder( $current ), (string) $current['path'] ) : '';
	$dir  = $path ? dirname( $path ) : '';
	$check( '  its bytes are in the private store under s{service}/', '' !== $path && str_contains( $path, '/s' . $sid . '/' ) && 'version two' === trim( (string) file_get_contents( $path ) ), $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$check( '  the client shape carries no storage path', ! isset( $v2['data']['path'] ) && str_contains( (string) $v2['data']['url'], 'scope=service' ) );
	$check( 'With a file, the gate lets it be bought', ! str_contains( wpss_service_unavailable_reason( $sid ), 'no file' ) );

	// 4. Paying delivers at once, whatever the rail.
	$fired = did_action( 'wpss_instant_delivery_created' );
	$oid   = ( $f->order )(
		array(
			'service_id'         => $sid,
			'revisions_included' => 2,
			'status'             => 'pending_payment',
			'payment_status' => 'pending',
			'paid_at'        => null,
		)
	);
	$orders[] = $oid;
	( new StandaloneOrderProvider() )->mark_as_paid( $oid, 'qa_503_txn', 'stripe' );
	$order = wpss_get_order( $oid );
	$check( 'Paid digital order is delivered at once (pending_approval)', 'pending_approval' === $order->status, (string) $order->status );
	$check( '  it skipped the brief although old questions are saved', ! wpss_order_needs_requirements( $order ) );
	$check( '  one delivery row', 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_deliveries WHERE order_id = %d", $oid ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( '  wpss_instant_delivery_created fired', did_action( 'wpss_instant_delivery_created' ) === $fired + 1 );
	$check( '  the order records its kind: digital', 'digital' === wpss_get_order_kind( $order ) );
	$check( '  no revision can be asked, whatever the package stored (2)', 0 === $order->get_remaining_revisions() && ! $order->can_request_revision() );
	$ext = ( new \WPSellServices\Services\ExtensionOrderService() )->create_extension_request( $oid, 10, 2, (int) $f->vendor, 'More pages for the template, as discussed.' );
	$check( '  the vendor cannot quote an extension on it', empty( $ext['success'] ) && str_contains( (string) ( $ext['message'] ?? '' ), 'digital product' ), wp_json_encode( $ext ) );
	$check( '  checkout badges promise no revisions or delivery days', array() === array_filter( wpss_get_checkout_badges( array( 'delivery_days' => 0, 'revisions' => 0 ) ), static fn( $b ) => in_array( $b['icon'], array( 'timer', 'circle-check' ), true ) ) );
	$pending = (object) array( 'id' => 0, 'platform' => 'standalone', 'service_id' => $sid );
	$check( '  waiting for offline payment says the download is ready once confirmed', str_contains( wpss_offline_payment_notice( $pending ), 'download is ready' ) );

	$foid     = ( $f->order )(
		array(
			'service_id'     => $fixed,
			'status'         => 'pending_payment',
			'payment_status' => 'pending',
			'paid_at'        => null,
		)
	);
	$orders[] = $foid;
	( new StandaloneOrderProvider() )->mark_as_paid( $foid, 'qa_503_fixed', 'offline' );
	$forder = wpss_get_order( $foid );
	$check( 'A fixed service order is not delivered by itself and stays kind service', 'pending_approval' !== $forder->status && 'service' === wpss_get_order_kind( $forder ), (string) $forder->status );

	// 5. Who may download.
	$check( 'The buyer may download', wpss_can_read_file( 'service', $sid, (int) $f->buyer ) );
	$check( '  the seller may', wpss_can_read_file( 'service', $sid, (int) $f->vendor ) );
	$check( '  another member may not', ! wpss_can_read_file( 'service', $sid, $outsider ) );
	$check( '  a visitor may not', ! wpss_can_read_file( 'service', $sid, 0 ) );
	$check( '  a buyer of the fixed service gets nothing from it', ! wpss_can_read_file( 'service', $fixed, (int) $f->buyer ) );

	// 6. REST.
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/orders/' . $oid, (int) $f->buyer );
	$check( 'GET /orders/{id}: kind digital with the current file', 200 === $st && 'digital' === ( $data['kind'] ?? '' ) && '' !== (string) ( $data['download']['id'] ?? '' ) && (string) $v2['data']['id'] === (string) $data['download']['id'], (string) $st );
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/downloads', (int) $f->buyer );
	$check( 'GET /downloads lists the purchase for its buyer', 200 === $st && 1 === count( (array) $data ) && $oid === (int) ( $data[0]['order_id'] ?? 0 ) && 'guide.txt' === ( $data[0]['file']['name'] ?? '' ) );
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/downloads', $outsider );
	$check( '  and nothing for anyone else', 200 === $st && array() === (array) $data );
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/downloads', 0 );
	$check( '  a visitor gets 401', 401 === $st, (string) $st );
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/services/' . $sid, (int) $f->buyer );
	$check( 'GET /services/{id}: product_file for the buyer', 200 === $st && 'guide.txt' === ( $data['product_file']['name'] ?? '' ) );
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/services/' . $sid, $outsider );
	$check( '  null for anyone else', 200 === $st && array_key_exists( 'product_file', (array) $data ) && null === $data['product_file'] );
	list( $st, $data ) = $rest( 'GET', '/wpss/v1/orders/' . $sid . '/files/' . $current['id'], $outsider, array( 'scope' => 'service' ) );
	$check( 'The app file route refuses another member (scope=service, 403)', 403 === $st, (string) $st );

	// 7. Over HTTP, as the browser does it; each download is one audit row.
	$link    = wpss_file_link( 'service', $sid, (string) $current['id'] );
	$audit   = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_audit_log WHERE event_type = 'product.downloaded' AND object_id = %d", $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$before  = $audit();
	list( $code, $body ) = $fetch( $link, (int) $f->buyer );
	$check( 'The buyer downloads the latest version over HTTP', 200 === $code && 'version two' === trim( $body ), $code . ' ' . substr( $body, 0, 60 ) );
	$check( '  one audit row per download', $audit() === $before + 1, (string) $audit() );
	list( $code, $body ) = $fetch( $link, $outsider );
	$check( '  another member gets 404 and no bytes', 404 === $code && ! str_contains( $body, 'version two' ), (string) $code );
	$check( '  and leaves no audit row', $audit() === $before + 1 );

	// 8. What the buyer and the vendor see.
	wp_set_current_user( (int) $f->buyer );
	global $post;
	$post = get_post( $sid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	ob_start();
	$service_id = $sid;
	include wpss_locate_template( 'partials/service-packages.php' );
	$box = (string) ob_get_clean();
	wp_reset_postdata();
	$check( 'Buy box says Instant download, shows no delivery days or revisions', str_contains( $box, 'Instant download' ) && ! str_contains( $box, 'Revisions' ) && ! str_contains( $box, '1 day' ) );
	ob_start();
	wpss_get_template_part( 'order/download', '', array( 'wpss_order' => $order ) );
	$panel = (string) ob_get_clean();
	$check( 'The order page shows the Download button to the buyer', str_contains( $panel, 'guide.txt' ) && str_contains( $panel, 'scope=service' ) );
	wp_set_current_user( (int) $f->vendor );
	ob_start();
	wpss_get_template_part( 'order/download', '', array( 'wpss_order' => $order ) );
	$check( '  the vendor gets no download box on their own sale', '' === trim( (string) ob_get_clean() ) );
	wp_set_current_user( (int) $f->buyer );
	$check( 'The buyer dashboard lists Downloads', isset( ( new \WPSellServices\Frontend\UnifiedDashboard() )->get_sections()['buying']['items']['downloads'] ) );
	wp_set_current_user( $outsider );
	$check( '  a member with no digital purchase has no Downloads item', ! isset( ( new \WPSellServices\Frontend\UnifiedDashboard() )->get_sections()['buying']['items']['downloads'] ) );

	// 9. Wizard.
	wp_set_current_user( (int) $f->vendor );
	$wizard = new \WPSellServices\Frontend\ServiceWizard();
	$call   = static function ( string $method, array $args ) use ( $wizard ) {
		$m = new ReflectionMethod( $wizard, $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $wizard, $args );
	};
	// A fresh digital product posts no delivery time: the wizard hides the field (QA bounce, 10551449503).
	$fresh = array(
		'title'        => 'Brand guide template for small studios',
		'category'     => (int) ( get_terms( array( 'taxonomy' => 'wpss_service_category', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' ) )[0] ?? 0 ),
		'description'  => str_repeat( 'A ready-made brand guide template you download and edit. ', 4 ),
		'service_type' => 'digital',
		'packages'     => array( 'basic' => array( 'name' => 'Template', 'price' => 19, 'delivery_days' => '' ) ),
		'gallery'      => array( 'main' => 1 ),
	);
	$delivery_errors = static fn( array $errors ): array => array_values( array_filter( $errors, static fn( $e ) => str_contains( (string) $e, 'delivery time' ) ) );
	$check( 'Wizard publish check: a fresh digital product needs no delivery time', array() === $delivery_errors( $call( 'validate_service_data', array( $fresh ) ) ), wp_json_encode( $call( 'validate_service_data', array( $fresh ) ) ) );
	$check( '  a fixed service with no delivery time is still refused', array() !== $delivery_errors( $call( 'validate_service_data', array( array( 'service_type' => 'fixed' ) + $fresh ) ) ) );
	$check( '  the shared check (REST, admin) agrees', array() === $delivery_errors( wpss_validate_service_publishable( array( 'packages' => $fresh['packages'], 'service_type' => 'digital' ) ) ) && array() !== $delivery_errors( wpss_validate_service_publishable( array( 'packages' => $fresh['packages'] ) ) ) );
	$saved_pkgs = get_post_meta( $sid, '_wpss_packages', true );
	update_post_meta( $sid, '_wpss_packages', array( array( 'name' => 'Template', 'price' => 19 ) ) );
	$check( '  and a saved digital product with no delivery time can go live', array() === $delivery_errors( wpss_get_service_publish_errors( $sid ) ), wp_json_encode( wpss_get_service_publish_errors( $sid ) ) );
	update_post_meta( $sid, '_wpss_packages', $saved_pkgs );
	$view = new \WPSellServices\Frontend\SingleServiceView();
	$meta = static function ( int $id ) use ( $view ): string {
		ob_start();
		$view->render_meta( \WPSellServices\Models\Service::from_post( get_post( $id ) ) );
		return (string) ob_get_clean();
	};
	$orders[] = ( $f->order )(
		array(
			'service_id' => $fixed,
			'status'     => 'in_progress',
		)
	);
	$check( 'The product page shows no "orders in queue"; a fixed service still does', ! str_contains( $meta( $sid ), 'in queue' ) && str_contains( $meta( $fixed ), 'in queue' ) );
	$check( 'Wizard loads the current file', 'guide.txt' === ( $call( 'get_service_data', array( $sid ) )['product_file']['name'] ?? '' ) );
	ob_start();
	$call( 'render_step_file', array() );
	$step = (string) ob_get_clean();
	ob_start();
	$call( 'render_step_review', array( null ) );
	$review = (string) ob_get_clean();
	$check( '  the review step lists the file and says Instant download', str_contains( $review, "steps.includes('file')" ) && str_contains( $review, 'Instant download' ) );
	$check( '  the file step offers the upload', str_contains( $step, 'uploadProductFile($event)' ) && str_contains( $step, 'type="file"' ) );
	$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/service-wizard.min.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$check( '  the served wizard JS uploads and skips the delivery time', str_contains( $js, 'uploadProductFile' ) && str_contains( $js, 'typeHasDelivery' ) );
	$saved = $call( 'sanitize_service_data', array( array( 'service_type' => 'digital' ) ) );
	$saved['packages'] = array(
		'basic' => array(
			'enabled'       => true,
			'name'          => 'Personal',
			'price'         => 20,
			'delivery_time' => '',
			'revisions'     => 3,
		),
	);
	$saved['gallery']  = array( 'images' => array(), 'main' => null, 'video' => '' );
	$call( 'save_service_meta', array( $sid, $saved ) );
	$pkg = wpss_get_service_packages( $sid )[0] ?? array();
	$check( '  saving a digital product stores 1 day and no revisions', 1 === (int) ( $pkg['delivery_days'] ?? 0 ) && 0 === (int) ( $pkg['revisions'] ?? -1 ), wp_json_encode( $pkg ) );

	// 10. A refund ends the download; a filter can add a limit.
	$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => 'refunded', 'payment_status' => 'refunded' ), array( 'id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( 'A refunded order loses its download', ! wpss_can_read_file( 'service', $sid, (int) $f->buyer ) && 0 === wpss_get_buyer_downloads( (int) $f->buyer, 0, 0 ) );
	$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => 'completed', 'payment_status' => 'paid' ), array( 'id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$limit = static fn() => false;
	add_filter( 'wpss_can_download_product', $limit );
	$check( 'wpss_can_download_product can refuse (a limit or expiry)', ! wpss_can_read_file( 'service', $sid, (int) $f->buyer ) );
	remove_filter( 'wpss_can_download_product', $limit );
	$check( '  and a completed order keeps it', wpss_can_read_file( 'service', $sid, (int) $f->buyer ) );

	// 11. A product with buyers cannot be deleted, so their download stays; once it has none, deleting it deletes its files.
	wp_delete_post( $sid, true );
	$check( 'A product with orders is not deleted, and its file stays', 'wpss_service' === get_post_type( $sid ) && file_exists( $path ) );
	foreach ( $orders as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	wp_delete_post( $sid, true );
	$check( 'Deleting a product with no orders removes its stored files', '' !== $path && ! file_exists( $path ) && ! is_dir( $dir ) );
} finally {
	wp_set_current_user( 0 );
	wp_delete_file( $mu );
	foreach ( $orders as $id ) {
		foreach ( array( 'wpss_deliveries', 'wpss_conversations' ) as $t ) {
			$wpdb->delete( $wpdb->prefix . $t, array( 'order_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'service', 'object_id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'order' AND object_id IN (" . implode( ',', array_map( 'intval', $orders ?: array( 0 ) ) ) . ') AND created_at > %s', gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( 'wpss_service' === get_post_type( $sid ) ) {
		wp_delete_post( $sid, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $outsider );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
