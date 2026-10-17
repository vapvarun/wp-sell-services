<?php
/**
 * Settings cards open with a real heading, sub-sections sit one level below it, and the
 * admin helper/label greys pass WCAG AA on wp-admin's grey (Basecamp 10412306674,
 * 10412156185, 10412156063, 10412155855, 10412155759, 10384448877).
 *
 * Run: wp eval-file tests/test-settings-heading-outline.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$roots = array( WPSS_PLUGIN_DIR . 'src' );
if ( defined( 'WPSS_PRO_PLUGIN_DIR' ) ) {
	$roots[] = WPSS_PRO_PLUGIN_DIR . 'src';
}

$paragraph_titles = array();
$h4_in_settings   = array();
foreach ( $roots as $root ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		$code = (string) file_get_contents( $file->getPathname() );
		if ( preg_match( '/<p class="wpss-(card__title|settings-subhead__title)"/', $code ) ) {
			$paragraph_titles[] = basename( $file->getPathname() );
		}
		if ( false !== strpos( $file->getFilename(), 'SettingsRenderer' ) && false !== strpos( $code, '<h4' ) ) {
			$h4_in_settings[] = basename( $file->getPathname() );
		}
	}
}
$check( 'no settings card title is a <p> (' . implode( ',', $paragraph_titles ) . ')', ! $paragraph_titles );
$check( 'no settings renderer skips from h2 to h4 (' . implode( ',', $h4_in_settings ) . ')', ! $h4_in_settings );

// Contrast of the two admin greys against #f0f0f1 (wp-admin background).
$lum = static function ( string $hex ): float {
	$c = array_map( static fn( $p ) => ( ( $v = hexdec( $p ) / 255 ) <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ), str_split( ltrim( $hex, '#' ), 2 ) );
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};
$ratio = static function ( string $fg, string $bg ) use ( $lum ): float {
	$a = $lum( $fg );
	$b = $lum( $bg );
	return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
};
$css = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/admin.css' );
preg_match( '/--wpss-admin-text-light:\s*(#[0-9a-f]{6})/i', $css, $light );
preg_match( '/--wpss-admin-text-tertiary:\s*(#[0-9a-f]{6})/i', $css, $tert );
$check( 'helper text grey is a fixed colour', ! empty( $light[1] ) );
$check( 'helper text grey is at least 4.5:1 on #f0f0f1', ! empty( $light[1] ) && $ratio( $light[1], '#f0f0f1' ) >= 4.5 );
$check( 'sidebar label grey is at least 4.5:1 on #f9fafb', ! empty( $tert[1] ) && $ratio( $tert[1], '#f9fafb' ) >= 4.5 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
