<?php
/**
 * Public profile media accepts images (and portfolio video), nothing else.
 *
 * Run: wp eval-file tests/test-public-upload-context-contract.php
 *
 * POST /wpss/v1/media stores files publicly as avatar / profile / service /
 * portfolio media, but checked them against the owner's list for PRIVATE order
 * files - pdf, zip, psd and the rest - without passing its context. A
 * subscriber uploaded a PDF, and with the full default list a ZIP, as an
 * "avatar" and got a public URL (Basecamp 10336370704). The route was also
 * charged the general 60/minute budget instead of the upload one.
 *
 * Temporary files are written to the system temp dir and removed at the end.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$dir   = get_temp_dir();
$made  = array();
$file  = static function ( string $name, string $bytes ) use ( $dir, &$made ): array {
	$path = $dir . 'wpss-upload-ctx-' . wp_generate_password( 6, false ) . '-' . $name;
	file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$made[] = $path;
	return array(
		'name'     => $name,
		'tmp_name' => $path,
		'size'     => strlen( $bytes ),
		'error'    => 0,
	);
};

// Real bytes: WordPress checks content, not the name.
$png = $file( 'pixel.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
$pdf = $file( 'resume.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );

$zip_path = $dir . 'wpss-upload-ctx-' . wp_generate_password( 6, false ) . '-bundle.zip';
$zipper   = new ZipArchive();
$zipper->open( $zip_path, ZipArchive::CREATE );
$zipper->addFromString( 'a.txt', 'a' );
$zipper->close();
$made[] = $zip_path;
$zip    = array(
	'name'     => 'bundle.zip',
	'tmp_name' => $zip_path,
	'size'     => filesize( $zip_path ),
	'error'    => 0,
);

// Widest owner list, so the refusals below come from the context and nothing else.
$widest = static fn() => array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'mp4' );
add_filter( 'wpss_allowed_file_types', $widest, 1 );

try {
	foreach ( array( 'avatar', 'profile', 'service' ) as $context ) {
		$check( "{$context}: an image is accepted", null === wpss_check_upload( $png, $context ) );
		$check( "{$context}: a PDF is refused", null !== wpss_check_upload( $pdf, $context ) );
		$check( "{$context}: a ZIP is refused", null !== wpss_check_upload( $zip, $context ) );
	}
	$check( 'portfolio: an image is accepted', null === wpss_check_upload( $png, 'portfolio' ) );
	$check( 'portfolio: a ZIP is refused', null !== wpss_check_upload( $zip, 'portfolio' ) );
	$check( 'private order files still follow the owner list (a PDF delivery is fine)', null === wpss_check_upload( $pdf, 'delivery' ) );

	$media  = new \WPSellServices\API\MediaController();
	$bucket = new ReflectionMethod( $media, 'get_rate_limit_action' );
	$bucket->setAccessible( true );
	$check( 'POST /media is charged to the file_upload limit', 'file_upload' === $bucket->invoke( $media, new WP_REST_Request( 'POST', '/wpss/v1/media' ) ) );

	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/API/MediaController.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$check( 'the media route passes its context to the upload check', false !== strpos( $src, 'wpss_check_upload( (array) $files[\'file\'], $context )' ) );
} finally {
	remove_filter( 'wpss_allowed_file_types', $widest, 1 );
	foreach ( $made as $path ) {
		wp_delete_file( $path );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
