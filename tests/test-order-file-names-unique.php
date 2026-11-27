<?php
/**
 * Two files with the same name on one order never share a disk path or bucket key (Basecamp 10551464875).
 *
 * The stored name was sanitize_file_name(original), made unique against the local folder only, and the
 * bucket key wpss/{order}/{kind}/{name}. With cloud storage the local copy is deleted after upload, so a
 * second final.zip got the same key and the first record served the second file. Names were guessable.
 * Stored names now lead with the record's uuid.
 *
 * move_uploaded_file() refuses CLI files, so the store runs inside a real HTTP upload: a token-gated
 * mu-plugin, written for this run and removed after.
 *
 * Run: wp eval-file tests/test-order-file-names-unique.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f     = wpss_test_fixtures();
$oid   = ( $f->order )( array( 'status' => 'in_progress' ) );
$token = wp_generate_password( 24, false );
$mu    = WPMU_PLUGIN_DIR . '/qa-875-store-' . wp_generate_password( 6, false ) . '.php';
$base  = getenv( 'WPSS_QA_BASE_URL' ) ? rtrim( (string) getenv( 'WPSS_QA_BASE_URL' ), '/' ) : untrailingslashit( home_url() );
$keys  = array();
$paths = array();

wp_mkdir_p( WPMU_PLUGIN_DIR );
// A stand-in bucket records every key it is handed, keeps nothing, so a clash is visible as a repeated key.
file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$mu,
	'<?php
add_action( "admin_post_nopriv_qa_875_store", static function () {
	if ( ! hash_equals( ' . var_export( $token, true ) . ', (string) ( $_POST["t"] ?? "" ) ) ) { wp_die( "no", 403 ); }
	add_filter( "wpss_allowed_file_types", static fn( $t ) => array_merge( (array) $t, array( "txt" ) ) );
	add_filter( "upload_mimes", static fn( $m ) => $m + array( "txt" => "text/plain" ) );
	$cloud = ! empty( $_POST["cloud"] );
	if ( $cloud ) {
		$stub = new class() implements \WPSellServices\Integrations\Contracts\StorageProviderInterface {
			public function get_id(): string { return "qa875"; }
			public function get_name(): string { return "QA"; }
			public function is_configured(): bool { return true; }
			public function upload( string $local_path, string $remote_path, array $options = [] ): array|\\WP_Error { return array( "key" => $remote_path ); }
			public function download( string $remote_path, string $local_path ): array { return array(); }
			public function delete( string $remote_path ): bool { return true; }
			public function exists( string $remote_path ): bool { return true; }
			public function get_signed_url( string $remote_path, int $expires = 3600 ): ?string { return "https://example.test/" . $remote_path; }
			public function get_url( string $remote_path ): string { return ""; }
			public function list_files( string $prefix = "" ): array { return array(); }
			public function get_metadata( string $remote_path ): ?array { return null; }
		};
		add_filter( "wpss_storage_providers", static fn( $p ) => array_merge( (array) $p, array( "qa875" => $stub ) ) );
		add_filter( "pre_option_wpss_active_storage_provider", static fn() => "qa875" );
	}
	$rec = wpss_store_order_file( $_FILES["f"], (int) $_POST["o"], "delivery" );
	wp_send_json( $rec );
} );
'
);

$send = static function ( string $content, bool $cloud ) use ( $base, $token, $oid ) {
	$boundary = wp_generate_password( 16, false );
	$body     = '';
	foreach ( array( 'action' => 'qa_875_store', 't' => $token, 'o' => (string) $oid, 'cloud' => $cloud ? '1' : '' ) as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"f\"; filename=\"final.txt\"\r\nContent-Type: text/plain\r\n\r\n{$content}\r\n--{$boundary}--\r\n";
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
	// 1. Cloud: two deliveries both called final.txt.
	$a = $send( 'first version', true );
	$b = $send( 'second version', true );
	$check( 'Both cloud uploads stored', is_array( $a ) && is_array( $b ) && ! empty( $a['remote_path'] ) && ! empty( $b['remote_path'] ), wp_json_encode( array( $a, $b ) ) );
	$check( '  two different bucket keys, so the first is not overwritten', is_array( $a ) && is_array( $b ) && $a['remote_path'] !== $b['remote_path'], ( $a['remote_path'] ?? '' ) . ' / ' . ( $b['remote_path'] ?? '' ) );
	$check( '  each key carries its record id (not guessable from the name)', is_array( $a ) && str_contains( (string) ( $a['remote_path'] ?? '' ), (string) ( $a['id'] ?? 'x' ) ) );
	$check( '  the display name stays final.txt', is_array( $a ) && 'final.txt' === ( $a['name'] ?? '' ) );

	// 2. Local: two of the same name, both readable with their own bytes.
	$c = $send( 'local one', false );
	$d = $send( 'local two', false );
	$pc = is_array( $c ) ? wpss_find_local_order_file( $oid, (string) $c['path'] ) : '';
	$pd = is_array( $d ) ? wpss_find_local_order_file( $oid, (string) $d['path'] ) : '';
	$paths = array_filter( array( $pc, $pd ) );
	$check( 'Local: two paths, each file keeps its own content', '' !== $pc && '' !== $pd && $pc !== $pd && 'local one' === file_get_contents( $pc ) && 'local two' === file_get_contents( $pd ), $pc . ' / ' . $pd ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$check( '  the stored name leads with the record id', is_array( $c ) && str_starts_with( (string) ( $c['path'] ?? '' ), (string) ( $c['id'] ?? 'x' ) . '-' ), (string) ( $c['path'] ?? '' ) );
} finally {
	wp_delete_file( $mu );
	foreach ( $paths as $p ) {
		wp_delete_file( $p );
	}
	foreach ( wpss_order_files_dirs() as $dir ) {
		@rmdir( $dir . $oid . '/' ); // phpcs:ignore -- only removes it when empty.
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
