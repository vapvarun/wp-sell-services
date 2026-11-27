<?php
/**
 * One store, one gate, one serve for every customer file (Basecamp 10551464986).
 *
 * A buyer-request brief and a pre-sale contact file used to sit in wp-content/uploads, opened by
 * anyone holding the URL. Both now live in the private store (`r{attachment}/`, `c{conversation}/`),
 * read through wpss_can_read_file() and served by the one admin-post / REST door with a `scope`.
 * Older briefs and contact files move into the store the first time someone allowed opens them; a
 * brief copied onto an order before or after this change still opens; old order links keep working.
 * Also: message and dispute rows are rewritten on migration (H9), a stored URL naming another host is
 * never followed (H10), the dead wpss_upload_file handler is gone (H11), and the download name is
 * sent RFC 6266 style with an opt-in X-Sendfile / X-Accel-Redirect.
 *
 * Uploads must run inside a real HTTP request (is_uploaded_file refuses CLI files), so they go
 * through a token-gated mu-plugin written for this run and removed after.
 *
 * Run: wp eval-file tests/test-file-scopes.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Services\ConversationService;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! function_exists( 'wpss_can_read_file' ) ) {
	$check( 'One read gate for every scope (wpss_can_read_file)', false, 'missing' );
	echo "\n1 FAILED\n";
	exit( 1 );
}

$f        = wpss_test_fixtures();
$oid      = ( $f->order )( array( 'status' => 'in_progress' ) );
$outsider = (int) wp_insert_user( array( 'user_login' => 'qa986o' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$admin    = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$token    = wp_generate_password( 24, false );
$mu       = WPMU_PLUGIN_DIR . '/qa-986-' . wp_generate_password( 6, false ) . '.php';
$base     = getenv( 'WPSS_QA_BASE_URL' ) ? rtrim( (string) getenv( 'WPSS_QA_BASE_URL' ), '/' ) : untrailingslashit( home_url() );
$atts     = array();
$posts    = array();
$files    = array();
$conv_ids = array();

wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$mu,
	'<?php
add_action( "admin_post_nopriv_qa_986", static function () {
	if ( ! hash_equals( ' . var_export( $token, true ) . ', (string) ( $_POST["t"] ?? "" ) ) ) { wp_die( "no", 403 ); }
	if ( "reset" === $_POST["do"] ) {
		wp_send_json( function_exists( "opcache_reset" ) && opcache_reset() );
	}
	add_filter( "upload_mimes", static fn( $m ) => $m + array( "txt" => "text/plain" ) );
	add_filter( "wpss_allowed_file_types", static fn( $t ) => array_merge( (array) $t, array( "txt" ) ) );
	wp_set_current_user( (int) $_POST["u"] );
	if ( "brief" === $_POST["do"] ) {
		$r = new WP_REST_Request( "POST", "/wpss/v1/media" );
		$r->set_param( "context", "request" );
		$r->set_file_params( $_FILES );
		wp_send_json( rest_do_request( $r )->get_data() );
	}
	$grouped = array();
	foreach ( $_FILES["file"] as $k => $v ) { $grouped[ $k ] = array( $v ); }
	wp_send_json( wpss_handle_message_attachments( $grouped, (int) $_POST["o"], "contact" ) );
} );
'
);

$send = static function ( array $fields ) use ( $base, $token ) {
	$boundary = wp_generate_password( 16, false );
	$body     = '';
	foreach ( array_merge( array( 'action' => 'qa_986', 't' => $token ), $fields ) as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"brief.txt\"\r\nContent-Type: text/plain\r\n\r\nqa 986 bytes\r\n--{$boundary}--\r\n";
	$res = wp_remote_post(
		$base . '/wp-admin/admin-post.php',
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => "multipart/form-data; boundary={$boundary}" ),
			'body'    => $body,
		)
	);
	return is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
};

$as = static function ( int $user ) {
	wp_set_current_user( $user );
};

try {
	$send( array( 'do' => 'reset' ) );

	// 1. A new brief: the attachment stays as identity, the bytes go to the private store.
	$brief = $send( array( 'do' => 'brief', 'u' => (string) $f->buyer ) );
	$bid   = (int) ( $brief['id'] ?? 0 );
	$atts[] = $bid;
	$rec   = $bid ? get_post_meta( $bid, '_wpss_file', true ) : null;
	$path  = is_array( $rec ) ? wpss_find_local_order_file( wpss_file_folder( $rec ), (string) $rec['path'] ) : '';
	$check( 'Brief upload: an attachment id comes back', $bid > 0, wp_json_encode( $brief ) );
	$check( '  with no public URL', '' === (string) ( $brief['url'] ?? 'x' ), (string) ( $brief['url'] ?? '' ) );
	$check( '  nothing in wp-content/uploads', $bid > 0 && '' === (string) get_attached_file( $bid ) );
	$check( '  its bytes are in the store under r{attachment}/', '' !== $path && str_contains( $path, '/r' . $bid . '/' ), $path );
	$check( '  and it keeps its original name', $bid > 0 && 'brief.txt' === wpss_attachment_display_name( $bid ) );

	// 2. On a request: the gated link, and the request gate decides.
	$req     = (int) wp_insert_post( array( 'post_type' => BuyerRequestPostType::POST_TYPE, 'post_title' => 'qa-986 request', 'post_status' => 'publish', 'post_author' => $f->buyer ) );
	$posts[] = $req;
	update_post_meta( $req, '_wpss_attachments', array( $bid ) );
	$as( (int) $f->vendor );
	$links = wpss_request_files_for_client( array( $bid ), $req );
	$url   = (string) ( $links[0]['url'] ?? '' );
	$check( 'The request page links the gated door', str_contains( $url, 'action=wpss_order_file' ) && str_contains( $url, 'scope=request' ) && str_contains( $url, 'order=' . $req ), $url );
	$check( '  a signed-in vendor may open it (default policy)', wpss_can_read_file( 'request', $req ) );
	$as( 0 );
	$check( '  a visitor may not', ! wpss_can_read_file( 'request', $req ) );
	$closed = static fn() => false;
	add_filter( 'wpss_can_view_request_attachments', $closed );
	$as( $outsider );
	$check( '  with the request policy closed, another member may not', ! wpss_can_read_file( 'request', $req ) );
	$as( (int) $f->buyer );
	$check( '  but the request author still may', wpss_can_read_file( 'request', $req ) );
	remove_filter( 'wpss_can_view_request_attachments', $closed );
	wp_update_post( array( 'ID' => $req, 'post_status' => 'draft' ) );
	$as( (int) $f->vendor );
	$check( '  a draft request\'s files are refused to other members', ! wpss_can_read_file( 'request', $req ) );
	$as( (int) $f->buyer );
	$check( '  but its author still opens them', wpss_can_read_file( 'request', $req ) );
	wp_update_post( array( 'ID' => $req, 'post_status' => 'publish' ) );
	$check( '  and a file not listed on that request is not found through it', null === wpss_find_file( 'request', $req, (string) ( $bid + 999999 ) ) );
	$located = wpss_locate_order_file( $req, (string) $bid, 'request' );
	$check( '  the serve door finds the private bytes', is_array( $located ) && ( $located['path'] ?? '' ) === $path, is_wp_error( $located ) ? $located->get_error_code() : wp_json_encode( $located ) );

	// REST: the same door with ?scope=request.
	$as( 0 );
	$r = new WP_REST_Request( 'GET', "/wpss/v1/orders/{$req}/files/{$bid}" );
	$r->set_param( 'scope', 'request' );
	$check( 'REST ?scope=request: a visitor gets 401', 401 === rest_do_request( $r )->get_status() );
	$as( (int) $f->vendor );
	$res = rest_do_request( $r );
	$check( '  a signed-in vendor gets the file', 200 === $res->get_status() && 'brief.txt' === ( $res->get_data()['name'] ?? '' ), (string) $res->get_status() );

	// 3. Converted to an order: the order reads its own copy; the request keeps its copy.
	$copied   = wpss_normalize_requirement_attachments( array( $bid ), (int) $f->buyer );
	$check( 'Conversion: the brief becomes an order record with no public url', 1 === count( $copied ) && '' === $copied[0]['url'] && 'brief.txt' === $copied[0]['name'] );
	$wpdb->insert( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $oid, 'field_data' => '[]', 'attachments' => wp_json_encode( $copied ), 'submitted_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$req_row = (int) $wpdb->insert_id;
	$as( (int) $f->buyer );
	$check( '  the order page shows a link for it', '' !== wpss_get_order_file_url( $copied[0] + array( 'order_id' => $oid ) ) );
	$on_order = wpss_locate_order_file( $oid, (string) $bid );
	$files[]  = is_array( $on_order ) ? ( $on_order['path'] ?? '' ) : '';
	$check( '  opening it copies the brief into the order folder', is_array( $on_order ) && str_contains( (string) ( $on_order['path'] ?? '' ), '/' . $oid . '/' ), is_wp_error( $on_order ) ? $on_order->get_error_code() : wp_json_encode( $on_order ) );
	$check( '  and the request still has its own copy', is_file( $path ) && 'attachment' === get_post_type( $bid ) );

	// 4. A brief from before 2.0.0 (public, in uploads) moves on first open and keeps its attachment.
	$up      = wp_upload_dir();
	$old_abs = trailingslashit( $up['path'] ) . 'qa-986-old-' . wp_generate_password( 6, false ) . '.txt';
	file_put_contents( $old_abs, 'old brief' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$old     = (int) wp_insert_attachment( array( 'post_title' => 'qa-986-old', 'post_mime_type' => 'text/plain', 'post_status' => 'private', 'post_author' => $f->buyer ), $old_abs );
	$atts[]  = $old;
	update_post_meta( $old, '_wpss_upload_context', 'request' );
	update_post_meta( $req, '_wpss_attachments', array( $bid, $old ) );
	$moved   = wpss_locate_order_file( $req, (string) $old, 'request' );
	$check( 'An old public brief moves into r{attachment}/ on first open', is_array( $moved ) && str_contains( (string) ( $moved['path'] ?? '' ), '/r' . $old . '/' ), is_wp_error( $moved ) ? $moved->get_error_code() : wp_json_encode( $moved ) );
	$check( '  its public copy is gone', ! file_exists( $old_abs ) );
	$check( '  the attachment (id, owner, lock) stays', 'attachment' === get_post_type( $old ) && is_array( get_post_meta( $old, '_wpss_file', true ) ) );

	// 5. Pre-sale contact: the conversation's folder, its participants only.
	$svc        = new ConversationService();
	$conv       = $svc->create_direct( (int) $f->buyer, (int) $f->vendor, 'qa-986 inquiry', 0 );
	$cid        = $conv ? (int) $conv->id : 0;
	$conv_ids[] = $cid;
	$up_c       = $cid ? $send( array( 'do' => 'contact', 'u' => (string) $f->buyer, 'o' => (string) $cid ) ) : null;
	$crec       = $up_c['attachments'][0] ?? null;
	$cpath      = is_array( $crec ) ? wpss_find_local_order_file( wpss_file_folder( $crec ), (string) ( $crec['path'] ?? '' ) ) : '';
	$files[]    = $cpath;
	$check( 'Contact file stored under c{conversation}/', '' !== $cpath && str_contains( $cpath, '/c' . $cid . '/' ), wp_json_encode( $up_c ) );
	$check( '  the record names the conversation, not an order', is_array( $crec ) && $cid === (int) ( $crec['conversation_id'] ?? 0 ) && 0 === (int) ( $crec['order_id'] ?? -1 ) );
	$msg = $cid && is_array( $crec ) ? $svc->send_message( $cid, (int) $f->buyer, 'qa 986', array( $crec ) ) : null;
	$as( (int) $f->vendor );
	$curl = is_array( $crec ) ? wpss_get_order_file_url( $crec ) : '';
	$check( '  the vendor gets the gated conversation link', str_contains( $curl, 'scope=conversation' ) && str_contains( $curl, 'order=' . $cid ), $curl );
	$check( '  participants may open it', wpss_can_read_file( 'conversation', $cid ) && is_array( wpss_locate_order_file( $cid, (string) ( $crec['id'] ?? '' ), 'conversation' ) ) );
	$as( $outsider );
	$check( '  another member may not', ! wpss_can_read_file( 'conversation', $cid ) );
	$as( $admin );
	$check( '  an admin may', wpss_can_read_file( 'conversation', $cid ) );
	$as( (int) $f->vendor );
	$client = wpss_order_file_for_client( (array) $crec );
	$check( '  what the email and the bell get carries no storage path', ! isset( $client['path'] ) && ! isset( $client['remote_path'] ) && str_contains( (string) $client['url'], 'scope=conversation' ) );

	// 6. A contact file from before 2.0.0 ({id,url} in the message) is linked through the gate, not the URL.
	$legacy_abs = trailingslashit( $up['path'] ) . 'qa-986-contact-' . wp_generate_password( 6, false ) . '.txt';
	file_put_contents( $legacy_abs, 'old contact' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$lid    = (int) wp_insert_attachment( array( 'post_title' => 'qa-986-contact', 'post_mime_type' => 'text/plain', 'post_status' => 'private', 'post_author' => $f->buyer ), $legacy_abs );
	$atts[] = $lid;
	$lrec   = array( 'id' => $lid, 'url' => wp_get_attachment_url( $lid ), 'name' => 'old.txt', 'type' => 'text/plain' );
	$wpdb->insert( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $cid, 'sender_id' => $f->buyer, 'content' => 'old', 'attachments' => wp_json_encode( array( $lrec ) ), 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$lmsg   = (int) $wpdb->insert_id;
	$lurl   = wpss_get_order_file_url( $lrec + array( 'conversation_id' => $cid ) );
	$check( 'Old contact file: the message links the gated door, not the public URL', str_contains( $lurl, 'scope=conversation' ), $lurl );
	$lmoved = wpss_locate_order_file( $cid, (string) $lid, 'conversation' );
	$files[] = is_array( $lmoved ) ? ( $lmoved['path'] ?? '' ) : '';
	$check( '  opening it moves it behind the gate and drops the public copy', is_array( $lmoved ) && isset( $lmoved['path'] ) && ! file_exists( $legacy_abs ), wp_json_encode( $lmoved ) );

	// 7. H10: a stored URL naming another host is never followed.
	$h10 = array( 'id' => 'qa986ext', 'name' => 'x.pdf', 'url' => 'https://evil.example/x.pdf' );
	$wpdb->update( $wpdb->prefix . 'wpss_order_requirements', array( 'attachments' => wp_json_encode( array_merge( $copied, array( $h10 ) ) ) ), array( 'id' => $req_row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ext = wpss_locate_order_file( $oid, 'qa986ext' );
	$check( 'H10: a record pointing at another host is not redirected to', ! ( is_array( $ext ) && isset( $ext['url'] ) ), wp_json_encode( $ext ) );

	// 8. H9: a message row on an order conversation is rewritten by the order migration.
	$ocv        = $svc->get_by_order( $oid ) ?: $svc->create_for_order( $oid );
	$h9         = array( 'id' => 'qa986h9', 'name' => 'h9.txt', 'url' => 'x' );
	$wpdb->insert( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $ocv->id, 'sender_id' => $f->buyer, 'content' => 'h9', 'attachments' => wp_json_encode( array( $h9 ) ), 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$h9msg      = (int) $wpdb->insert_id;
	$conv_ids[] = 0;
	$done       = wpss_rewrite_order_file_record( $oid, 'qa986h9', array( 'id' => 'qa986h9', 'path' => 'p.txt', 'order_id' => $oid ) );
	$check( 'H9: an order message row is rewritten on migration', $done && str_contains( (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_messages WHERE id = %d", $h9msg ) ), '"path":"p.txt"' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// 9. H6: the download headers.
	$hdr = wpss_file_download_headers( array( 'name' => 'my brief é.pdf', 'type' => 'application/pdf' ), $path );
	$check( 'Download name sent RFC 6266 style', str_contains( $hdr['Content-Disposition'], 'filename="my brief _.pdf"' ) && str_contains( $hdr['Content-Disposition'], "filename*=UTF-8''my%20brief%20%C3%A9.pdf" ), $hdr['Content-Disposition'] );
	$check( '  with nosniff', 'nosniff' === ( $hdr['X-Content-Type-Options'] ?? '' ) );
	$check( '  and no X-Sendfile unless the site asks', ! isset( $hdr['X-Sendfile'] ) && ! isset( $hdr['X-Accel-Redirect'] ) );
	$xs = static fn() => 'xsendfile';
	add_filter( 'wpss_file_download_method', $xs );
	$check( '  X-Sendfile when the site turns it on', $path === ( wpss_file_download_headers( array( 'name' => 'a' ), $path )['X-Sendfile'] ?? '' ) );
	remove_filter( 'wpss_file_download_method', $xs );

	// 10. H11 and old links.
	$check( 'H11: the wpss_upload_file AJAX handler is gone', ! has_action( 'wp_ajax_wpss_upload_file' ) && ! method_exists( \WPSellServices\Frontend\AjaxHandlers::class, 'upload_file' ) );
	$check( 'Order links keep the shape already sent in email (no scope)', ! str_contains( wpss_file_link( 'order', $oid, 'abc' ), 'scope=' ) );

	// 11. Deleting the brief's attachment removes its private bytes.
	wp_delete_attachment( $bid, true );
	$check( 'Deleting a brief removes its private bytes', ! file_exists( $path ) );
} finally {
	wp_set_current_user( 0 );
	wp_delete_file( $mu );
	foreach ( array_filter( $atts ) as $id ) {
		wp_delete_attachment( (int) $id, true );
	}
	foreach ( array_filter( $files ) as $p ) {
		is_file( $p ) && wp_delete_file( $p );
	}
	foreach ( array_filter( $posts ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	isset( $req_row ) && $wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'id' => $req_row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( array_filter( array( $lmsg ?? 0, $h9msg ?? 0, isset( $msg ) && $msg ? (int) $msg->id : 0 ) ) as $mid ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'id' => $mid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_filter( $conv_ids ) as $cid ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $cid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => $cid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( wpss_order_files_dirs() as $dir ) {
		foreach ( array( (string) $oid, 'c' . ( $cid ?? 0 ), 'r' . ( $bid ?? 0 ), 'r' . ( $old ?? 0 ) ) as $sub ) {
			@rmdir( $dir . $sub . '/' ); // phpcs:ignore -- only removes it when empty.
		}
	}
	$outsider && wp_delete_user( $outsider );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
