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

require_once __DIR__ . '/exit-on-fail.php';

$fails  = 0;
$passes = 0;
$check  = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails, &$passes ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
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
	// Comments stripped: a token named in prose is neither a use nor a definition.
	$code = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $path ) );
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
 * No orphan is tolerated. This list used to hold 25 names (Basecamp 10268606549); most were
 * mentions inside comments, which the scan counted as uses, and the one real orphan is fixed.
 * Do not add to it: define the token in design-system.css or use one that exists.
 */
$known_orphans = array();

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
// --- 4. one owner for the .wpss-btn base rule -------------------------------------
// Two sheets declaring the bare rule meant load order picked the winner (one said `border: none`).
// admin.css is the wp-admin button, which never loads beside design-system.css and has its own tokens.
$btn_owners = array();
foreach ( $files as $path ) {
	if ( ! str_ends_with( $path, '.css' ) ) {
		continue;
	}
	$code = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $path ) );
	if ( preg_match( '/^\.wpss-btn\s*\{[^}]*\b(display|padding|border)\s*:/m', $code ) ) {
		$btn_owners[] = basename( $path );
	}
}
sort( $btn_owners );
$check( '.wpss-btn base rule is declared only in design-system.css and the admin sheet', array( 'admin.css', 'design-system.css' ) === $btn_owners );

// --- 5. public pages draw buttons with .wpss-btn, and an outline reads the same on each ---
// Become a Vendor carried its own .wpss-vr__btn (10px radius, 15px type) beside the shared button, and
// three page sheets gave the outline a darker rest colour than design-system.css, while the archive's
// link reset outranked them all and painted Clear All in the theme link colour.
$private_buttons = array();
$outline_drift   = array();
foreach ( $files as $path ) {
	$rel  = str_replace( array( WPSS_PLUGIN_DIR, defined( 'WPSS_PRO_PLUGIN_DIR' ) ? WPSS_PRO_PLUGIN_DIR : WPSS_PLUGIN_DIR ), '', $path );
	$code = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $path ) );
	// wp-admin screens and the dashboard banners (unified-dashboard.css) are not public pages.
	$public = ! str_starts_with( basename( $path ), 'admin' ) && ! str_contains( $rel, '/Admin/' ) && 'assets/css/unified-dashboard.css' !== $rel;
	if ( $public && preg_match_all( '/\.wpss-[a-z0-9-]+__btn(?:--[a-z]+)?\s*\{/', $code, $m ) ) {
		$private_buttons[] = $rel . ': ' . implode( ', ', array_unique( $m[0] ) );
	}
	if ( str_ends_with( $path, '.css' ) && preg_match_all( '/\.wpss-btn--outline(?::visited)?\s*\{[^}]*?[^-]color:\s*([^;]+);/', $code, $m ) ) {
		foreach ( $m[1] as $value ) {
			if ( ! str_starts_with( trim( str_replace( '!important', '', $value ) ), 'var(--wpss-text-secondary' ) ) {
				$outline_drift[] = $rel . ': ' . trim( $value );
			}
		}
	}
	if ( str_ends_with( $path, '.css' ) && preg_match( '/a:not\((?![^{]*\.wpss-btn)[^{]*\)\s*\{[^}]*color:/', $code ) && str_contains( $code, '.wpss-btn--outline' ) ) {
		$outline_drift[] = $rel . ': a plain-link colour rule that does not exclude .wpss-btn';
	}
}
$check( 'no public page defines a button component of its own', empty( $private_buttons ), implode( ' | ', $private_buttons ) );

// --- 6. a class that rides on .wpss-btn adds layout, never a second look ---------------
// The signup submit, the requirements submit, Write a Review and the wizard buttons each carried a
// .wpss-btn variant and then re-declared radius, border, padding and fill, so one button had four looks.
// Icon-only buttons keep their own shape on purpose.
$icon_buttons = array( 'wpss-sidebar-close', 'wpss-fav-toggle', 'wpss-fav-toggle--inline' );
$companions   = array();
foreach ( $files as $path ) {
	if ( ! str_ends_with( $path, '.php' ) ) {
		continue;
	}
	if ( preg_match_all( '/class="([^"]*\bwpss-btn\b[^"]*)"/', (string) file_get_contents( $path ), $m ) ) {
		foreach ( $m[1] as $list ) {
			foreach ( preg_split( '/\s+/', $list ) as $class ) {
				if ( str_starts_with( $class, 'wpss-' ) && ! str_starts_with( $class, 'wpss-btn' ) && ! in_array( $class, $icon_buttons, true ) ) {
					$companions[ $class ] = true;
				}
			}
		}
	}
}
$restyled = array();
foreach ( $files as $path ) {
	$rel = str_replace( array( WPSS_PLUGIN_DIR, defined( 'WPSS_PRO_PLUGIN_DIR' ) ? WPSS_PRO_PLUGIN_DIR : WPSS_PLUGIN_DIR ), '', $path );
	if ( str_ends_with( $rel, 'design-system.css' ) || str_starts_with( basename( $path ), 'admin' ) || str_contains( $rel, '/Admin/' ) || 'assets/css/unified-dashboard.css' === $rel ) {
		continue;
	}
	$code = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $path ) );
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $code, $rules, PREG_SET_ORDER ) ) {
		continue;
	}
	foreach ( $rules as $rule ) {
		if ( ! preg_match( '/(?<![\w-])(border-radius|background|background-color|border|font-size|padding)\s*:/', $rule[2] ) ) {
			continue;
		}
		foreach ( explode( ',', $rule[1] ) as $selector ) {
			if ( preg_match( '/\.(wpss-[\w-]+)(?::hover|:focus|:visited)?\s*$/', trim( $selector ), $sm ) && isset( $companions[ $sm[1] ] ) ) {
				$restyled[] = $rel . ': .' . $sm[1];
			}
		}
	}
}
$restyled = array_values( array_unique( $restyled ) );
$check( 'a class on a .wpss-btn never re-declares its fill, border, radius, padding or size', empty( $restyled ), implode( ' | ', $restyled ) );
// --- 7. a scoped rule never resizes or reshapes the shared button --------------------
// .wpss-wizard .wpss-btn set its own padding and size and `border: none !important`, and
// .wpss-request-wizard .wpss-btn--lg made "large" a second size. Page sheets may restate colours
// to beat a theme's link styles; size and shape belong to design-system.css alone.
$reshaped = array();
foreach ( $files as $path ) {
	$rel = str_replace( array( WPSS_PLUGIN_DIR, defined( 'WPSS_PRO_PLUGIN_DIR' ) ? WPSS_PRO_PLUGIN_DIR : WPSS_PLUGIN_DIR ), '', $path );
	if ( str_ends_with( $rel, 'design-system.css' ) || str_starts_with( basename( $path ), 'admin' ) || str_contains( $rel, '/Admin/' ) ) {
		continue;
	}
	$code = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $path ) );
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $code, $rules, PREG_SET_ORDER ) ) {
		continue;
	}
	foreach ( $rules as $rule ) {
		if ( ! preg_match( '/(?<![\w-])(padding|font-size|border-radius)\s*:|(?<![\w-])border(-width)?\s*:\s*(none|0)/', $rule[2] ) ) {
			continue;
		}
		foreach ( explode( ',', $rule[1] ) as $selector ) {
			// The icon-only button keeps its own square shape.
			if ( preg_match( '/\s\.wpss-btn(--(?!icon)[a-z]+)?(?::[a-z():-]+)?\s*$/', trim( $selector ) ) ) {
				$reshaped[] = $rel . ': ' . trim( $selector );
			}
		}
	}
}
$check( 'no sheet resizes or reshapes .wpss-btn inside one section', empty( $reshaped ), implode( ' | ', array_unique( $reshaped ) ) );

$check( 'an outline button at rest is --wpss-text-secondary on every page sheet', empty( $outline_drift ), implode( ' | ', $outline_drift ) );

$check( '.wpss-btn--outline is defined in exactly one stylesheet', 1 === count( $owners ) );
$check( '  and that stylesheet is design-system.css', array( 'design-system.css' ) === $owners );

echo "\n" . $passes . ' passed, ' . $fails . " failed\n";
