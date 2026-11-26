<?php
/**
 * Service wizard gallery icons are drawn after choosing or removing the main image
 * (Basecamp 10545984846).
 *
 * Lucide replaces [data-lucide] placeholders once at load. Choosing a main image
 * (its remove button) and removing any image (the upload placeholder comes back)
 * both add icon markup through Alpine, which stayed as empty boxes because neither
 * path re-ran refreshIcons().
 *
 * Run: wp eval-file tests/test-gallery-icons-after-select-and-remove.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

foreach ( array( 'service-wizard.js', 'service-wizard.min.js' ) as $file ) {
	$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/' . $file );

	// The media frame select handler has two branches (main image, extra image); each must redraw.
	preg_match( '/\.on\(\s*["\']select["\'](.*?)isDirty/s', $js, $select );
	$check( "{$file}: choosing the main image redraws icons (both select branches call refreshIcons)", ! empty( $select[1] ) && 2 <= substr_count( $select[1], 'refreshIcons()' ) );

	preg_match( '/removeGalleryItem\(([^)]*)\)\s*\{(.{0,400}?)\}\s*,\s*(?:\/\*|async\s|\w+\()/s', $js, $remove );
	$check( "{$file}: removing a gallery image redraws icons", ! empty( $remove[2] ) && false !== strpos( $remove[2], 'refreshIcons()' ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
