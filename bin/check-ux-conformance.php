<?php
/**
 * Fails when a UX Foundation rule gets WORSE: counts each violation per file and lets the
 * count only go down (bin/ux-conformance-baseline.json), like bin/check-time-formatter.php.
 *
 * Rules counted (ux-foundation rulebook numbers):
 *   10  inline <style> in PHP output
 *   12  inline onclick= in PHP output
 *    5  Dashicons in new markup
 *    8  alert( / confirm( / prompt( in plugin JS
 *   10  an inline <script> block in PHP output (enqueue a file or wp_add_inline_script)
 *    9  a shared primitive (.wpss-btn, .wpss-stat-card, .wpss-stats-grid and their modifiers) declared outside design-system.css
 *   14  `outline: none|0` in source CSS (each needs a :focus-visible replacement)
 *
 * Run: php bin/check-ux-conformance.php [--update]
 * A line that is correct on purpose carries `ux-ok:` in a comment.
 *
 * @package WPSellServices
 */

$root     = dirname( __DIR__ );
$baseline = $root . '/bin/ux-conformance-baseline.json';
$update   = in_array( '--update', $argv, true );

$rules = array(
	'inline-style'   => array( array( 'src', 'templates' ), 'php', '/<style[\s>]/i' ),
	'inline-onclick' => array( array( 'src', 'templates' ), 'php', '/\sonclick\s*=/i' ),
	'inline-script'  => array( array( 'src', 'templates' ), 'php', '/<script\b(?![^>]*\bsrc=)(?![^>]*type=["\']application\/(ld\+)?json)/i' ),
	'dashicons'      => array( array( 'src', 'templates' ), 'php', '/dashicons-/' ),
	'js-dialogs'     => array( array( 'assets/js' ), 'js', '/(?<![\w.])(alert|confirm|prompt)\s*\(/' ),
	'outline-none'   => array( array( 'assets/css' ), 'css', '/outline\s*:\s*(none|0)\b/' ),
	// The one modal is .wpss-modal + __backdrop/__dialog/__header/__title/__close/__body/__footer (design-system.css).
	'legacy-modal-php' => array( array( 'src', 'templates' ), 'php', '/wpss-modal(-(overlay|backdrop|dialog|content|header|body|footer|title|close|close-btn|dismiss|cancel|large|lg|error)|__(overlay|content|cancel))(?![\w-])/' ),
	'legacy-modal-js'  => array( array( 'assets/js' ), 'js', '/wpss-modal(-(overlay|backdrop|dialog|content|header|body|footer|title|close|close-btn|dismiss|cancel|large|lg|error)|__(overlay|content|cancel))(?![\w-])/' ),
	'legacy-modal-css' => array( array( 'assets/css' ), 'css', '/wpss-modal(-(overlay|backdrop|dialog|content|header|body|footer|title|close|close-btn|dismiss|cancel|large|lg|error)|__(overlay|content|cancel))(?![\w-])/' ),
	// Selector line of a rule that opens with the owned base; admin.css is the wp-admin chrome surface.
	'duplicate-primitive' => array( array( 'assets/css' ), 'css', '/^((a\.)?\.wpss-btn(--(primary|secondary|outline|success|danger|sm|lg|block|icon|ghost|disabled|full|danger-outline))?|\.wpss-(stat-card|stats-grid)(__[a-z]+|--[a-z0-9]+)*)\s*[,{]/' ),
);

$counts = array();
foreach ( $rules as $name => list( $dirs, $ext, $re ) ) {
	$counts[ $name ] = array();
	foreach ( $dirs as $dir ) {
		$path = $root . '/' . $dir;
		if ( ! is_dir( $path ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$rel = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
			// Minified and RTL copies are generated from the source file.
			if ( $file->getExtension() !== $ext || str_contains( $rel, '.min.' ) || str_contains( $rel, '-rtl.' ) || ( in_array( $name, array( 'duplicate-primitive', 'legacy-modal-css' ), true ) && in_array( basename( $rel ), array( 'design-system.css', 'admin.css' ), true ) ) || str_contains( $rel, 'vendor/' ) || str_contains( $rel, 'assets/js/vendor' ) ) {
				continue;
			}
			foreach ( file( $file->getPathname() ) as $line ) {
				$trim = ltrim( $line );
				if ( '' === $trim || in_array( $trim[0], array( '*', '/', '#' ), true ) || str_contains( $line, 'ux-ok:' ) ) {
					continue;
				}
				if ( preg_match( $re, $line ) ) {
					$counts[ $name ][ $rel ] = ( $counts[ $name ][ $rel ] ?? 0 ) + 1;
				}
			}
		}
	}
	ksort( $counts[ $name ] );
}

if ( $update ) {
	file_put_contents( $baseline, json_encode( $counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	echo 'Baseline written: ' . implode( ', ', array_map( static fn( $k ) => $k . ' ' . array_sum( $counts[ $k ] ), array_keys( $counts ) ) ) . "\n";
	exit( 0 );
}

$allowed = is_file( $baseline ) ? json_decode( (string) file_get_contents( $baseline ), true ) : array();
$bad     = array();
foreach ( $counts as $name => $files ) {
	foreach ( $files as $rel => $n ) {
		if ( $n > ( $allowed[ $name ][ $rel ] ?? 0 ) ) {
			$bad[] = sprintf( '%s: %s x%d, baseline %d', $rel, $name, $n, $allowed[ $name ][ $rel ] ?? 0 );
		}
	}
}

if ( $bad ) {
	echo "FAIL  UX conformance gate: a rule got worse\n  " . implode( "\n  ", $bad ) . "\n";
	exit( 1 );
}

echo 'OK  UX conformance gate: ' . implode( ', ', array_map( static fn( $k ) => $k . ' ' . array_sum( $counts[ $k ] ), array_keys( $counts ) ) ) . " baselined, none new\n";
