<?php
/**
 * Order files: one site never deletes another's, a served folder is never called private, a moved
 * folder never 404s old files, and a bucket delete never blocks the request (Basecamp 10551464914).
 *
 * The folder above WordPress has no site identity, so deleting order 12 on a staging site ran a
 * recursive rmdir of dir/12/ and took production's files; uninstall removed the whole folder. The
 * "outside" test was only "not under ABSPATH", so WordPress in a subfolder or Bedrock put the folder in
 * the web root with no guard files. Reads built the path from the folder chosen on this request only,
 * so a folder switch 404'd every older file. Bucket deletes ran one HTTP call each inline.
 *
 * Run: wp eval-file tests/test-order-files-folder-safety.php
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

$f       = wpss_test_fixtures();
$oid     = ( $f->order )( array( 'status' => 'completed' ) );
$uploads = wp_upload_dir();
$legacy  = trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/' . $oid . '/';
$current = wpss_get_order_files_dir() . $oid . '/';
$tag     = wp_generate_password( 6, false );
$doc     = $_SERVER['DOCUMENT_ROOT'] ?? null;
$made    = array();
$restore_doc = static function () use ( $doc ): void {
	if ( null === $doc ) {
		unset( $_SERVER['DOCUMENT_ROOT'] );
	} else {
		$_SERVER['DOCUMENT_ROOT'] = $doc;
	}
};

wp_mkdir_p( $legacy );
wp_mkdir_p( $current );
file_put_contents( $legacy . "old-{$tag}.txt", 'old' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
file_put_contents( $current . "new-{$tag}.txt", 'new' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// Another site on the same account, same order id, a file with no record here.
file_put_contents( $current . "other-site-{$tag}.txt", 'theirs' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$att = wp_insert_attachment( array( 'post_title' => "qa-914-{$tag}", 'post_mime_type' => 'text/plain', 'post_status' => 'inherit' ) );

$records = array(
	array( 'id' => "qa-old-{$tag}", 'name' => 'old.txt', 'path' => "old-{$tag}.txt" ),
	array( 'id' => "qa-new-{$tag}", 'name' => 'new.txt', 'path' => "new-{$tag}.txt" ),
	array( 'id' => "qa-remote-{$tag}", 'name' => 'r.txt', 'path' => '', 'remote_path' => "wpss/{$oid}/delivery/r-{$tag}.txt", 'provider' => 'qa-none' ),
	array( 'id' => (string) $att, 'name' => 'legacy.txt', 'url' => 'https://example.test/legacy.txt' ),
);
$wpdb->insert( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $oid, 'vendor_id' => $f->vendor, 'attachments' => wp_json_encode( $records ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$made[] = (int) $wpdb->insert_id;
$job    = array( 'qa-none', "wpss/{$oid}/delivery/r-{$tag}.txt", $oid );

try {
	// 1. A file written to the other folder still downloads.
	$loc = wpss_locate_order_file( $oid, "qa-old-{$tag}" );
	$check( 'A file in the previous folder is found, not 404', is_array( $loc ) && ( $legacy . "old-{$tag}.txt" ) === ( $loc['path'] ?? '' ), is_wp_error( $loc ) ? $loc->get_error_code() : wp_json_encode( $loc ) );

	// 2. A web root covering the folder above WordPress sends files to uploads, which is guarded.
	$_SERVER['DOCUMENT_ROOT'] = dirname( untrailingslashit( ABSPATH ) );
	$dir                      = wpss_get_order_files_dir();
	$check( 'Folder above WordPress inside the web root (WP in a subfolder, Bedrock): not used', trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/' === $dir, $dir );
	$check( '  and the folder used carries the deny guards', file_exists( $dir . '.htaccess' ) && file_exists( $dir . 'web.config' ) );
	$restore_doc();
	$outside = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'wpss-order-files/';
	$check( '  with no web root over it, the folder above WordPress is used and guarded too', $outside !== wpss_get_order_files_dir() || file_exists( $outside . '.htaccess' ) );

	// 3. Deleting the order removes its own files only, queues the bucket delete, drops the legacy attachment.
	wpss_delete_order_files( $oid );
	$check( 'Delete: this order\'s files are gone from both folders', ! file_exists( $legacy . "old-{$tag}.txt" ) && ! file_exists( $current . "new-{$tag}.txt" ) );
	$check( '  another site\'s file in the shared folder survives', file_exists( $current . "other-site-{$tag}.txt" ) );
	$check( '  the bucket delete is queued, not run inline', \WPSellServices\Services\Scheduler::has_pending( 'wpss_delete_remote_order_file', $job ) );
	$check( '  the pre-1.7 media-library copy is deleted', null === get_post( $att ) );

	// 4. Uninstall leaves the shared folder above WordPress alone.
	$uninstall = (string) file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
	$check( 'Uninstall does not remove the folder above WordPress', ! str_contains( $uninstall, 'dirname( untrailingslashit( ABSPATH ) )' ) );
} finally {
	$restore_doc();
	\WPSellServices\Services\Scheduler::unschedule( 'wpss_delete_remote_order_file', $job );
	foreach ( array( "old-{$tag}.txt", "new-{$tag}.txt", "other-site-{$tag}.txt" ) as $name ) {
		foreach ( array( $legacy, $current ) as $d ) {
			file_exists( $d . $name ) && wp_delete_file( $d . $name );
		}
	}
	@rmdir( $legacy ); // phpcs:ignore
	@rmdir( $current ); // phpcs:ignore
	get_post( $att ) && wp_delete_attachment( $att, true );
	foreach ( $made as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
