<?php
/**
 * Plugin pages open and close the document through wpss_get_header()/wpss_get_footer(): get_header()
 * on a block theme logs a core deprecation and prints core's compat chrome instead of the theme's
 * (Basecamp 10415816659).
 *
 * Run: wp eval-file tests/test-page-chrome-block-theme.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root = dirname( __DIR__ );
$bad  = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$path = $file->getPathname();
	if ( 'php' !== $file->getExtension() || preg_match( '#/(vendor|node_modules|tests|bin|dist)/|functions/templates\.php$#', $path ) ) {
		continue;
	}
	if ( preg_match( '/^\s*get_(header|footer)\(\s*\)/m', (string) file_get_contents( $path ) ) ) {
		$bad[] = str_replace( $root . '/', '', $path );
	}
}
$check( 'no template calls get_header()/get_footer() directly' . ( $bad ? ': ' . implode( ', ', $bad ) : '' ), ! $bad );

if ( wp_is_block_theme() ) {
	ob_start();
	wpss_get_header();
	$head = ob_get_clean();
	$check( 'block theme: header prints a <title>', str_contains( $head, '<title>' ) );
	$check( 'block theme: header prints the document shell and the theme header part', str_contains( $head, '<div class="wp-site-blocks">' ) && str_contains( $head, 'wp-block-site-title' ) );
} else {
	echo "SKIP  block-theme output (active theme is classic)\n";
}

exit( $fails ? 1 : 0 );
