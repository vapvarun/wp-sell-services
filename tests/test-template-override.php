<?php
/**
 * Every in-place template include resolves through wpss_locate_template(), so a theme copy under
 * wp-sell-services/ wins (Basecamp 10415811101).
 *
 * Run: wp eval-file tests/test-template-override.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// 1. No file includes a plugin template by building the plugin path itself.
$bad = array();
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ), FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$path = $file->getPathname();
	if ( 'php' !== $file->getExtension() || preg_match( '#/(vendor|node_modules|tests|bin|dist)/#', $path ) ) {
		continue;
	}
	if ( preg_match( "/(include|require)(_once)?\s+WPSS_PLUGIN_DIR\s*\.\s*'templates\//", (string) file_get_contents( $path ) ) ) {
		$bad[] = str_replace( dirname( __DIR__ ) . '/', '', $path );
	}
}
$check( 'no direct WPSS_PLUGIN_DIR template include' . ( $bad ? ': ' . implode( ', ', $bad ) : '' ), ! $bad );

// 2. A theme copy wins, the plugin file is the fallback.
$theme = get_stylesheet_directory() . '/wp-sell-services/order/tip-view.php';
$check( 'fallback is the plugin file', wpss_locate_template( 'order/tip-view.php' ) === WPSS_PLUGIN_DIR . 'templates/order/tip-view.php' );
wp_mkdir_p( dirname( $theme ) );
file_put_contents( $theme, '<?php // override' );
$check( 'theme copy wins', wpss_locate_template( 'order/tip-view.php' ) === $theme );
unlink( $theme );
@rmdir( dirname( $theme ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
@rmdir( dirname( $theme, 2 ) . '/wp-sell-services' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

exit( $fails ? 1 : 0 );
