<?php
/**
 * Fails on a new raw date formatter outside src/functions/time.php.
 *
 * wp_date(), date_i18n(), mysql2date() and get_date_from_gmt() each pick their
 * own timezone, so one moment prints differently on two screens. Show a time
 * with wpss_date_label(); see docs/standards/time.md.
 *
 * Existing calls are counted per file in bin/time-formatter-baseline.json and the
 * count may only go DOWN. Convert a call, then run with --update to lower it.
 * A call that is correct on purpose (a post_date, a one-time migration) carries
 * a `time-ok:` comment on its line.
 *
 * Run: php bin/check-time-formatter.php [--update] [dir ...]   (default: src templates)
 *
 * @package WPSellServices
 */

$root     = dirname( __DIR__ );
$baseline = $root . '/bin/time-formatter-baseline.json';
$update   = in_array( '--update', $argv, true );
$dirs     = array_values( array_filter( array_slice( $argv, 1 ), static fn( $a ) => '--update' !== $a ) ) ?: array( 'src', 'templates' );
$pattern  = '/(?<![\w>:$])(wp_date|date_i18n|mysql2date|get_date_from_gmt)\s*\(/';

$counts = array();
foreach ( $dirs as $dir ) {
	$path = str_starts_with( $dir, '/' ) ? $dir : $root . '/' . $dir;
	if ( ! is_dir( $path ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$rel = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
		if ( 'php' !== $file->getExtension() || 'src/functions/time.php' === $rel || str_contains( $rel, 'vendor/' ) ) {
			continue;
		}
		foreach ( file( $file->getPathname() ) as $line ) {
			$trim = ltrim( $line );
			if ( '' === $trim || in_array( $trim[0], array( '*', '/', '#' ), true ) || str_contains( $line, 'time-ok:' ) ) {
				continue;
			}
			if ( preg_match( $pattern, $line ) ) {
				$counts[ $rel ] = ( $counts[ $rel ] ?? 0 ) + 1;
			}
		}
	}
}
ksort( $counts );

if ( $update ) {
	file_put_contents( $baseline, json_encode( $counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	echo 'Baseline written: ' . array_sum( $counts ) . ' calls in ' . count( $counts ) . " files\n";
	exit( 0 );
}

$allowed = is_file( $baseline ) ? json_decode( (string) file_get_contents( $baseline ), true ) : array();
$bad     = array();
foreach ( $counts as $rel => $n ) {
	if ( $n > ( $allowed[ $rel ] ?? 0 ) ) {
		$bad[] = sprintf( '%s: %d raw formatter call(s), baseline %d', $rel, $n, $allowed[ $rel ] ?? 0 );
	}
}

if ( $bad ) {
	echo "FAIL  raw date formatter outside wpss_date_label():\n  " . implode( "\n  ", $bad ) . "\nUse wpss_date_label() (docs/standards/time.md), or add a `time-ok:` comment if the call is correct on purpose.\n";
	exit( 1 );
}
echo 'OK  time formatter gate: ' . array_sum( $counts ) . ' baseline call(s) left to convert, none new' . "\n";
