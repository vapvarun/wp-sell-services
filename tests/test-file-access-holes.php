<?php
/**
 * File access holes and silent file loss (Basecamp 10551464950).
 *
 * Request briefs were public attachments that core REST listed to logged-out visitors; a message could
 * carry another member's media id and show its URL; the order store checked uploads with no context, so
 * a widened delivery/requirements type list was refused; files stayed on disk and in the bucket when
 * the row that named them failed to save; an empty or '..' name resolved to a folder; dead upload and
 * dispute-message code still shipped; the dispute timeline sent storage internals to clients.
 *
 * Uploads must run inside a real HTTP request (move_uploaded_file / is_uploaded_file refuse CLI files),
 * so they go through a token-gated mu-plugin written for this run and removed after.
 *
 * Run: wp eval-file tests/test-file-access-holes.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f      = wpss_test_fixtures();
$oid    = ( $f->order )( array( 'status' => 'in_progress' ) );
$buyer  = (int) wpss_get_order( $oid )->customer_id;
$token  = wp_generate_password( 24, false );
$mu     = WPMU_PLUGIN_DIR . '/qa-950-' . wp_generate_password( 6, false ) . '.php';
$base   = getenv( 'WPSS_QA_BASE_URL' ) ? rtrim( (string) getenv( 'WPSS_QA_BASE_URL' ), '/' ) : untrailingslashit( home_url() );
$atts   = array();
$stored = array();

wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$mu,
	'<?php
add_action( "admin_post_nopriv_qa_950", static function () {
	if ( ! hash_equals( ' . var_export( $token, true ) . ', (string) ( $_POST["t"] ?? "" ) ) ) { wp_die( "no", 403 ); }
	if ( "reset" === $_POST["do"] ) {
		// The web server may still hold an older copy of the code in opcache.
		wp_send_json( function_exists( "opcache_reset" ) && opcache_reset() );
	}
	add_filter( "upload_mimes", static fn( $m ) => $m + array( "txt" => "text/plain" ) );
	if ( "brief" === $_POST["do"] ) {
		add_filter( "wpss_allowed_file_types", static fn( $t ) => array_merge( (array) $t, array( "txt" ) ) );
		wp_set_current_user( (int) $_POST["u"] );
		$r = new WP_REST_Request( "POST", "/wpss/v1/media" );
		$r->set_param( "context", "request" );
		$r->set_file_params( $_FILES );
		wp_send_json( rest_do_request( $r )->get_data() );
	}
	// Only the delivery list is widened: the store must ask with the kind\'s own context.
	add_filter( "wpss_allowed_file_types", static fn( $t ) => array_values( array_diff( (array) $t, array( "txt" ) ) ) );
	add_filter( "wpss_delivery_allowed_file_types", static fn( $t ) => array_merge( (array) $t, array( "txt" ) ) );
	wp_send_json( wpss_store_order_file( $_FILES["file"], (int) $_POST["o"], (string) $_POST["kind"] ) );
} );
'
);

$send = static function ( array $fields ) use ( $base, $token ) {
	$boundary = wp_generate_password( 16, false );
	$body     = '';
	foreach ( array_merge( array( 'action' => 'qa_950', 't' => $token ), $fields ) as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"brief.txt\"\r\nContent-Type: text/plain\r\n\r\nqa 950 bytes\r\n--{$boundary}--\r\n";
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

try {
	$send( array( 'do' => 'reset' ) );

	// 1. A new request brief is private with an unguessable name; logged-out REST cannot list it.
	$brief  = $send( array( 'do' => 'brief', 'u' => (string) $buyer ) );
	$bid    = (int) ( $brief['id'] ?? 0 );
	$atts[] = $bid;
	$check( 'Request brief upload stored', $bid > 0, wp_json_encode( $brief ) );
	$check( '  as a private attachment', $bid > 0 && 'private' === get_post_status( $bid ), (string) get_post_status( $bid ) );
	$check( '  with an unguessable file name', $bid > 0 && 'brief.txt' !== basename( (string) get_attached_file( $bid ) ), basename( (string) get_attached_file( $bid ) ) );
	$check( '  but shown under its original name', $bid > 0 && function_exists( 'wpss_attachment_display_name' ) && 'brief.txt' === wpss_attachment_display_name( $bid ), $bid > 0 && function_exists( 'wpss_attachment_display_name' ) ? wpss_attachment_display_name( $bid ) : '' );
	$legacy = wp_insert_attachment( array( 'post_title' => 'qa-950-legacy', 'post_mime_type' => 'text/plain', 'post_status' => 'private' ), '/tmp/abc123-legacy.txt' );
	$atts[] = $legacy;
	$check( '  and a brief from before 2.0.0 falls back to its file name', function_exists( 'wpss_attachment_display_name' ) && 'abc123-legacy.txt' === wpss_attachment_display_name( (int) $legacy ) );
	wp_set_current_user( 0 );
	$listed = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $bid ) );
	$check( '  and logged-out /wp/v2/media does not serve it', $bid > 0 && 200 !== $listed->get_status(), (string) $listed->get_status() );

	// 2. A brief uploaded before 2.0.0 becomes private on upgrade.
	$old    = wp_insert_attachment( array( 'post_title' => 'qa-950-old', 'post_mime_type' => 'text/plain', 'post_status' => 'inherit', 'post_author' => $buyer ) );
	$atts[] = $old;
	update_post_meta( $old, '_wpss_upload_context', 'request' );
	$schema = new \WPSellServices\Database\SchemaManager();
	if ( method_exists( $schema, 'privatise_request_briefs' ) ) {
		$m = new ReflectionMethod( $schema, 'privatise_request_briefs' );
		$m->setAccessible( true );
		$m->invoke( $schema );
	}
	clean_post_cache( $old );
	$check( 'Existing brief made private by the upgrade pass', 'private' === get_post_status( $old ), (string) get_post_status( $old ) );

	// 3. A message keeps the sender's own media id and drops another member's.
	$mine    = wp_insert_attachment( array( 'post_title' => 'qa-950-mine', 'post_mime_type' => 'text/plain', 'post_status' => 'inherit', 'post_author' => $f->vendor ) );
	$theirs  = wp_insert_attachment( array( 'post_title' => 'qa-950-theirs', 'post_mime_type' => 'text/plain', 'post_status' => 'private', 'post_author' => $buyer ) );
	$atts[]  = $mine;
	$atts[]  = $theirs;
	$svc     = new \WPSellServices\Services\ConversationService();
	$conv    = $svc->get_by_order( $oid ) ?: $svc->create_for_order( $oid );
	$msg     = $conv ? $svc->send_message( (int) $conv->id, (int) $f->vendor, 'qa 950', array( $mine, $theirs ) ) : null;
	$saved   = $msg ? json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_messages WHERE id = %d", $msg->id ) ), true ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( 'Message: another member\'s media id is dropped, the sender\'s own kept', array( $mine ) === array_map( 'intval', (array) $saved ), wp_json_encode( $saved ) );

	// 3b. A client cannot hand the message route a file record of its own making.
	wp_set_current_user( (int) $f->vendor );
	$forge = new WP_REST_Request( 'POST', "/wpss/v1/orders/{$oid}/conversation/messages" );
	$forge->set_body_params( array( 'content' => 'qa 950 forged', 'attachments' => array( array( 'id' => 'f', 'remote_path' => 'wpss/1/delivery/other.pdf', 'provider' => 's3' ) ) ) );
	$fres   = rest_do_request( $forge );
	$fmsg   = (int) ( $fres->get_data()['data']['id'] ?? 0 );
	$frow   = $fmsg ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_messages WHERE id = %d", $fmsg ) ) : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$fmsg && $wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'id' => $fmsg ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_set_current_user( 0 );
	$check( 'Message route: a client-built file record is never stored', ! str_contains( $frow, 'remote_path' ), $fres->get_status() . ' ' . $frow );

	// 4. The store asks with the kind's own context: widened delivery types hold for deliveries only.
	$d        = $send( array( 'do' => 'store', 'o' => (string) $oid, 'kind' => 'delivery' ) );
	$msgfile  = $send( array( 'do' => 'store', 'o' => (string) $oid, 'kind' => 'message' ) );
	$stored[] = $d;
	$stored[] = $msgfile;
	$check( 'Store: a type widened for deliveries is stored as a delivery', is_array( $d ) && ! empty( $d['id'] ), wp_json_encode( $d ) );
	$check( '  and still refused for a message', null === $msgfile || empty( $msgfile['id'] ), wp_json_encode( $msgfile ) );

	// 5. Discarding a record removes its bytes and queues the bucket delete.
	$local = is_array( $d ) ? wpss_find_local_order_file( $oid, (string) $d['path'] ) : '';
	$job   = array( 'qa-950', "wpss/{$oid}/delivery/r.txt", $oid );
	if ( function_exists( 'wpss_discard_order_file' ) ) {
		is_array( $d ) && wpss_discard_order_file( $d, $oid );
		wpss_discard_order_file( array( 'id' => 'x', 'path' => '', 'remote_path' => $job[1], 'provider' => 'qa-950' ), $oid );
	}
	$check( 'Discard: the local file is gone', '' !== $local && ! file_exists( $local ), $local );
	$check( '  and the bucket delete is queued', \WPSellServices\Services\Scheduler::has_pending( 'wpss_delete_remote_order_file', $job ) );
	\WPSellServices\Services\Scheduler::unschedule( 'wpss_delete_remote_order_file', $job );
	$foreign = array( 'qa-950', 'wpss/' . ( $oid + 1 ) . '/delivery/theirs.txt', $oid );
	wpss_discard_order_file( array( 'id' => 'y', 'path' => '', 'remote_path' => $foreign[1], 'provider' => 'qa-950' ), $oid );
	$check( '  but never for a bucket key outside the record\'s own order', ! \WPSellServices\Services\Scheduler::has_pending( 'wpss_delete_remote_order_file', $foreign ) );
	\WPSellServices\Services\Scheduler::unschedule( 'wpss_delete_remote_order_file', $foreign );
	$check( 'Only keys under wpss/{order}/ are signed for an order', function_exists( 'wpss_remote_path_is_order_scoped' ) && wpss_remote_path_is_order_scoped( "wpss/{$oid}/delivery/a.txt", $oid ) && ! wpss_remote_path_is_order_scoped( 'wpss/' . ( $oid + 1 ) . '/delivery/a.txt', $oid ) && ! wpss_remote_path_is_order_scoped( "wpss/{$oid}/../9/a.txt", $oid ) );
	$check( '  every failure return of a delivery, requirements save and dispute evidence discards', 3 === substr_count( (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/DeliveryService.php' ), '$this->discard_files(' ) && 2 === substr_count( (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/RequirementsService.php' ), '$this->discard_uploads(' ) && str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/src/API/DisputesController.php' ), 'wpss_discard_order_file(' ) && str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/src/Frontend/AjaxHandlers.php' ), 'wpss_discard_order_file(' ) );

	// 6. An empty or '..' name never resolves to a folder.
	wp_mkdir_p( wpss_get_order_files_dir() . $oid . '/' );
	$check( 'Locate: an empty name finds nothing', '' === wpss_find_local_order_file( $oid, '' ) );
	$check( '  nor does ".."', '' === wpss_find_local_order_file( $oid, '..' ) );

	// 7. Clients get the gated link, never storage internals.
	$client = ! function_exists( 'wpss_order_file_for_client' ) ? array( 'path' => 1 ) : wpss_order_file_for_client( array( 'id' => 'a', 'order_id' => $oid, 'path' => 'p.txt', 'remote_path' => 'k', 'provider' => 'x' ) );
	$check( 'Client shape drops path, remote_path and provider', ! isset( $client['path'] ) && ! isset( $client['remote_path'] ) && ! isset( $client['provider'] ) && '' !== (string) ( $client['url'] ?? '' ) );

	// 8. Dead code is gone.
	$check( 'Dead: DisputeWorkflowManager::get_messages removed', ! method_exists( \WPSellServices\Services\DisputeWorkflowManager::class, 'get_messages' ) );
	$check( 'Dead: proposals no longer take an attachments input', ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/src/API/ProposalsController.php' ), "get_param( 'attachments' )" ) );
} finally {
	wp_set_current_user( 0 );
	wp_delete_file( $mu );
	foreach ( array_filter( $atts ) as $id ) {
		wp_delete_attachment( (int) $id, true );
	}
	foreach ( array_filter( $stored ) as $rec ) {
		if ( is_array( $rec ) && ! empty( $rec['path'] ) ) {
			$p = wpss_find_local_order_file( $oid, (string) $rec['path'] );
			'' !== $p && is_file( $p ) && wp_delete_file( $p );
		}
	}
	foreach ( wpss_order_files_dirs() as $dir ) {
		@rmdir( $dir . $oid . '/' ); // phpcs:ignore -- only removes it when empty.
	}
	if ( isset( $msg ) && $msg ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'id' => (int) $msg->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
