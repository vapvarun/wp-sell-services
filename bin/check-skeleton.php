<?php
/**
 * Skeleton gate: the file layout in docs/standards/skeleton.md.
 *
 *  1  every src/functions/*.php is loaded by src/functions.php, and every loaded file exists
 *  2  no dumping-ground file name (misc, utils, helpers, common) in src/ or assets/
 *  3  a global function is declared only in src/functions/ and is prefixed wpss_
 *  4  a function file holds only functions (no classes)
 *
 * Exit 1 on a violation. Run: php bin/check-skeleton.php
 */

$root  = dirname( __DIR__ );
$fails = array();

$loader = (string) file_get_contents( "$root/src/functions.php" );
preg_match_all( "/^\t'([a-z_-]+)',$/m", $loader, $m );
$loaded = $m[1];
$files  = array_map( fn( $f ) => basename( $f, '.php' ), glob( "$root/src/functions/*.php" ) );

foreach ( array_diff( $files, $loaded ) as $f ) {
	$fails[] = "src/functions/$f.php is not loaded by src/functions.php";
}
foreach ( array_diff( $loaded, $files ) as $f ) {
	$fails[] = "src/functions.php loads $f but src/functions/$f.php does not exist";
}

$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/src", FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$rel = substr( $file->getPathname(), strlen( $root ) + 1 );
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	if ( preg_match( '/^(misc|utils?|helpers?|common)\.php$/i', $file->getFilename() ) ) {
		$fails[] = "$rel is a dumping-ground name; use a domain file";
	}
	$code = (string) file_get_contents( $file->getPathname() );
	if ( str_starts_with( $rel, 'src/functions/' ) ) {
		if ( preg_match( '/^(abstract\s+|final\s+)?(class|interface|trait|enum)\s/m', $code ) ) {
			$fails[] = "$rel declares a class; function files hold functions only";
		}
	}
	if ( str_ends_with( $rel, 'wp-cli-stubs.php' ) ) {
		continue;
	}
	// Top-level `function name(` (no indentation) is a global function.
	if ( preg_match_all( '/^function\s+(\w+)\s*\(/m', $code, $fm ) ) {
		foreach ( $fm[1] as $name ) {
			if ( ! str_starts_with( $rel, 'src/functions' ) ) {
				$fails[] = "$rel declares global function $name outside src/functions/";
			} elseif ( 'wpss' !== $name && ! str_starts_with( $name, 'wpss_' ) ) {
				$fails[] = "$rel declares $name without the wpss_ prefix";
			}
		}
	}
}

foreach ( array( 'assets/css', 'assets/js' ) as $dir ) {
	foreach ( glob( "$root/$dir/*" ) as $f ) {
		if ( preg_match( '/^(misc|utils?|helpers?|common)\./i', basename( $f ) ) ) {
			$fails[] = "$dir/" . basename( $f ) . ' is a dumping-ground name';
		}
	}
}

if ( $fails ) {
	echo "FAIL skeleton gate:\n  " . implode( "\n  ", $fails ) . "\n";
	exit( 1 );
}
echo "OK  skeleton gate: " . count( $files ) . " function files, all loaded, domain-named, wpss_ prefixed\n";
