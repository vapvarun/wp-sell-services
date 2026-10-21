<?php
/**
 * Guard: no two translatable strings differ only by case, a trailing period or an ellipsis style.
 *
 * Run: wp eval-file tests/test-string-variants.php
 *
 * @package WPSellServices
 */

$fails = 0;
$roots = array( 'wp-sell-services' => WPSS_PLUGIN_DIR );
if ( is_dir( dirname( WPSS_PLUGIN_DIR ) . '/wp-sell-services-pro/src' ) ) {
	$roots['wp-sell-services-pro'] = dirname( WPSS_PLUGIN_DIR ) . '/wp-sell-services-pro/';
}

foreach ( $roots as $domain => $dir ) {
	$seen = array();
	$rx   = "/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'" . preg_quote( $domain, '/' ) . "'/";
	foreach ( array( 'src', 'templates' ) as $sub ) {
		if ( ! is_dir( $dir . $sub ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir . $sub, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( 'php' !== $f->getExtension() || ! preg_match_all( $rx, (string) file_get_contents( $f->getPathname() ), $m ) ) {
				continue;
			}
			foreach ( $m[1] as $s ) {
				$k = strtolower( str_replace( '...', '…', trim( $s ) ) );
				$k = preg_match( '/…$/u', $k ) ? $k : preg_replace( '/\.$/', '', $k );
				$seen[ preg_replace( '/\s+/', ' ', $k ) ][ $s ] = true;
			}
		}
	}
	foreach ( $seen as $variants ) {
		// Fragments that start lowercase are mid-sentence pieces, reviewed by hand.
		if ( count( $variants ) > 1 && ! array_filter( array_keys( $variants ), static fn( $v ) => ctype_lower( $v[0] ?? 'A' ) ) ) {
			++$fails;
			echo "  FAIL  [$domain] variants: " . implode( ' | ', array_keys( $variants ) ) . "\n";
		}
	}
}
echo $fails ? "  FAIL  $fails string variant set(s)\n" : "  PASS  no case, period or ellipsis string variants\n";
