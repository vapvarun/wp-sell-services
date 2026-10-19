<?php
/**
 * No settings card title is typed in capitals, in Free or Pro (Basecamp 10416252656).
 *
 * Free's cards are 18px sentence/title case; a Pro string typed as "COMMISSION RULES" reads as a
 * shouting label beside them, and no stylesheet can fix a msgid. Pro is scanned when checked out.
 *
 * Run: wp eval-file tests/test-settings-card-title-case.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$bad   = array();
$roots = array_filter( array( WPSS_PLUGIN_DIR . 'src', dirname( rtrim( WPSS_PLUGIN_DIR, '/' ) ) . '/wp-sell-services-pro/src' ), 'is_dir' );

foreach ( $roots as $root ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		$src = (string) file_get_contents( $file->getPathname() );
		if ( preg_match_all( '/wpss-card__title[^>]*>\s*(?:<\?php\s+)?(?:esc_html_e|echo esc_html__?)\(\s*\'([^\']+)\'/', $src, $m ) ) {
			foreach ( $m[1] as $title ) {
				if ( preg_match( '/[A-Z]{3}/', $title ) && $title === strtoupper( $title ) ) {
					$bad[] = basename( $file->getPathname() ) . ": {$title}";
				}
			}
		}
	}
}

echo ( $bad ? 'FAIL  ' : 'PASS  ' ) . 'no settings card title is typed in capitals' . ( $bad ? ' (' . implode( '; ', $bad ) . ')' : '' ) . "\n";
exit( $bad ? 1 : 0 );
