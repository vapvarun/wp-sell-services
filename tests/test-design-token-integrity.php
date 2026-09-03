<?php
/**
 * Design-token integrity contract.
 *
 * Two ways a themeable colour silently stops being themeable, both of which
 * shipped:
 *
 * 1. A token that is USED but never DEFINED. var() falls back to the literal
 *    second argument, so the page looks right in light mode and can never go
 *    dark. templates/cart/cart.php had 30 of these against a --wpss-color-*
 *    namespace that does not exist anywhere in either plugin; the design system
 *    defines --wpss-text, --wpss-surface, --wpss-border and so on.
 *
 * 2. A surface painted with --wpss-white. That token is a literal kept for
 *    on-colour text that must stay white in dark mode. Surfaces must use
 *    --wpss-surface, which flips. design-system.css says so in a comment; three
 *    inline style blocks in PHP did it anyway, which is how the become-a-vendor
 *    heading ended up light grey on white.
 *
 * Run: wp eval-file tests/test-design-token-integrity.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$fails  = 0;
$passes = 0;
$check  = static function ( string $label, bool $ok ) use ( &$fails, &$passes ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$ok ? $passes++ : $fails++;
};

/**
 * Every file that can carry CSS: real stylesheets plus the PHP that embeds
 * <style> blocks. The PHP ones are the whole point - they are where the token
 * rules got broken, because nobody looks for CSS there.
 */
$roots = array( WPSS_PLUGIN_DIR );
if ( defined( 'WPSS_PRO_PLUGIN_DIR' ) ) {
	$roots[] = WPSS_PRO_PLUGIN_DIR;
}

$files = array();
foreach ( $roots as $root ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$path = $f->getPathname();
		// dist/ is a stale build copy of the whole plugin. Scanning it double-counts
		// every file and reports defects that were already fixed in the source.
		if ( str_contains( $path, '/vendor/' ) || str_contains( $path, '/node_modules/' ) || str_contains( $path, '/dist/' ) ) {
			continue;
		}
		if ( str_contains( $path, '.min.' ) || str_contains( $path, '-rtl.' ) ) {
			continue;
		}
		if ( preg_match( '/\.(css|php)$/', $path ) ) {
			$files[] = $path;
		}
	}
}
$check( 'found stylesheets and PHP to scan', count( $files ) > 50 );

// --- 1. every token used is defined somewhere -------------------------------------
$defined = array();
$used    = array();
foreach ( $files as $path ) {
	$code = (string) file_get_contents( $path );
	if ( preg_match_all( '/(--wpss-[a-z0-9-]+)\s*:/i', $code, $m ) ) {
		foreach ( $m[1] as $t ) {
			$defined[ $t ] = true;
		}
	}
	if ( preg_match_all( '/var\(\s*(--wpss-[a-z0-9-]+)/i', $code, $m ) ) {
		foreach ( $m[1] as $t ) {
			$used[ $t ][] = $path;
		}
	}
}

$orphans = array_diff_key( $used, $defined );

/*
 * Known orphans, tracked on Basecamp 10268613021.
 *
 * Each one falls back to a hardcoded literal, so the component it styles cannot
 * follow the theme. They are the same defect that made the whole cart page
 * un-themeable, just spread thinner. Fixing them means choosing the right
 * replacement token per use and re-verifying the surface, which is 1.8.0 work.
 *
 * This list may SHRINK, never grow. A new orphan fails the contract.
 */
$known_orphans = array(
	'--wpss-admin-font-mono',
	'--wpss-bg-light',
	'--wpss-border-color',
	'--wpss-card-bg',
	'--wpss-muted',
	'--wpss-primary-100',
	'--wpss-primary-700',
	'--wpss-primary-bg',
	'--wpss-primary-color',
	'--wpss-primary-rgb',
	'--wpss-radius-md',
	'--wpss-status-cancelled-bg',
	'--wpss-status-cancelled-text',
	'--wpss-status-completed-text',
	'--wpss-status-delivered-text',
	'--wpss-status-disputed-text',
	'--wpss-status-in-progress-text',
	'--wpss-status-pending-text',
	'--wpss-success-bg',
	'--wpss-surface-hover',
	'--wpss-surface-muted',
	'--wpss-text-primary',
	'--wpss-transition-slow',
	'--wpss-warning-fg',
	'--wpss-x',
);

$new_orphans = array_diff( array_keys( $orphans ), $known_orphans );
$check( 'no NEW undefined --wpss-* token has appeared', empty( $new_orphans ) );
foreach ( $new_orphans as $token ) {
	$rel = str_replace( array( WPSS_PLUGIN_DIR, defined( 'WPSS_PRO_PLUGIN_DIR' ) ? WPSS_PRO_PLUGIN_DIR : '|' ), '', $orphans[ $token ][0] );
	echo '      NEW orphan ' . $token . ' (' . count( $orphans[ $token ] ) . ' use(s), e.g. ' . $rel . ")\n";
}

$fixed = array_diff( $known_orphans, array_keys( $orphans ) );
$check( 'the cart page carries no undefined token', ! isset( $orphans['--wpss-color-text'], $orphans['--wpss-radius-md'] ) );
if ( $fixed ) {
	echo '      ' . count( $fixed ) . " known orphan(s) now resolved - remove them from \$known_orphans:\n";
	foreach ( $fixed as $t ) {
		echo '        ' . $t . "\n";
	}
}

// --- 2. no surface is painted with the literal white ------------------------------
$literal_surfaces = array();
foreach ( $files as $path ) {
	$code = (string) file_get_contents( $path );
	if ( preg_match( '/background[^;:\n]*:[^;\n]*var\(\s*--wpss-white/i', $code ) ) {
		$literal_surfaces[] = str_replace( array( WPSS_PLUGIN_DIR, defined( 'WPSS_PRO_PLUGIN_DIR' ) ? WPSS_PRO_PLUGIN_DIR : '|' ), '', $path );
	}
}
$check( 'no background uses --wpss-white (surfaces must use --wpss-surface)', empty( $literal_surfaces ) );
foreach ( $literal_surfaces as $rel ) {
	echo '      ' . $rel . "\n";
}

// --- 3. one owner for .wpss-btn--outline ------------------------------------------
$owners = array();
foreach ( $files as $path ) {
	if ( ! str_ends_with( $path, '.css' ) ) {
		continue;
	}
	$code = (string) file_get_contents( $path );
	if ( preg_match( '/^\s*(\.wpss-btn)?\.wpss-btn--outline\s*\{/m', $code ) ) {
		$owners[] = basename( $path );
	}
}
$check( '.wpss-btn--outline is defined in exactly one stylesheet', 1 === count( $owners ) );
$check( '  and that stylesheet is design-system.css', array( 'design-system.css' ) === $owners );

echo "\n" . $passes . ' passed, ' . $fails . " failed\n";
