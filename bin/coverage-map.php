<?php
/**
 * Coverage map: every artefact the plugin's code registers, against the checks
 * that exercise it.
 *
 * Run (WP_ADMIN so the admin menus register):
 *   wp --exec="define('WP_ADMIN', true);" eval-file bin/coverage-map.php           report
 *   wp --exec="define('WP_ADMIN', true);" eval-file bin/coverage-map.php write     also write audit/coverage.json
 *   wp --exec="define('WP_ADMIN', true);" eval-file bin/coverage-map.php check     gate: exit 1 on drift
 *
 * The 1.8.0 build shipped a fatal because nothing walked or tested the order
 * page with a buyer file. Counting tests says nothing about which surfaces
 * they reach, so this lists the surfaces from the code - REST routes, AJAX
 * actions, shortcodes, blocks, admin pages, CLI commands, templates and the
 * hooks the plugin fires - and finds each one in the evidence: tests/,
 * audit/journeys/, the smoke runbook, and the render log the render-matrix
 * crawl writes (audit/render-coverage.json).
 *
 * "Covered" means a check names the artefact. A name is not proof that the
 * check asserts anything useful, but an artefact NO check names is certainly
 * unverified, and that is the list this drives to zero.
 *
 * The gate ratchets: audit/coverage-baseline.json lists what is uncovered
 * today. A new artefact must come with a check, and the baseline must shrink
 * when one is covered - it can never silently grow.
 *
 * Only artefacts whose code lives in this plugin count; with Pro active its
 * routes and pages are registered too, and are left to Pro's own map.
 *
 * @package WPSellServices
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions

$wpss_cov_dir  = dirname( __DIR__ ) . '/';
$wpss_cov_args = isset( $args ) ? (array) $args : array();
// Positional words: wp-cli keeps --flags for itself and never passes them on.
$wpss_cov_mode = in_array( 'check', $wpss_cov_args, true ) ? 'check' : ( in_array( 'write', $wpss_cov_args, true ) ? 'write' : 'report' );

/**
 * File a callable is defined in, or '' when it cannot be resolved.
 *
 * @param mixed $cb Callable.
 * @return string
 */
$wpss_cov_file = static function ( $cb ): string {
	try {
		if ( is_array( $cb ) && 2 === count( $cb ) ) {
			return (string) ( new ReflectionMethod( is_object( $cb[0] ) ? get_class( $cb[0] ) : $cb[0], $cb[1] ) )->getFileName();
		}
		if ( is_string( $cb ) && str_contains( $cb, '::' ) ) {
			return (string) ( new ReflectionMethod( $cb ) )->getFileName();
		}
		if ( $cb instanceof Closure || ( is_string( $cb ) && function_exists( $cb ) ) ) {
			return (string) ( new ReflectionFunction( $cb ) )->getFileName();
		}
		if ( is_object( $cb ) ) {
			return (string) ( new ReflectionClass( $cb ) )->getFileName();
		}
	} catch ( ReflectionException $e ) {
		return '';
	}

	return '';
};

$wpss_cov_ours = static fn( string $file ): bool => '' !== $file && str_starts_with( $file, $wpss_cov_dir );

/**
 * Every PHP file under a directory, recursively.
 *
 * @param string $dir Directory.
 * @return string[]
 */
$wpss_cov_php = static function ( string $dir ): array {
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
		if ( 'php' === $f->getExtension() ) {
			$out[] = $f->getPathname();
		}
	}
	sort( $out );
	return $out;
};

$wpss_cov_src = implode( "\n", array_map( 'file_get_contents', $wpss_cov_php( $wpss_cov_dir . 'src' ) ) );

// ---------------------------------------------------------------------------
// Artefacts, from the code.
// ---------------------------------------------------------------------------

$artefacts = array();

// REST routes this plugin's callbacks serve.
foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
	if ( ! str_starts_with( $route, '/wpss/v1' ) ) {
		continue;
	}
	foreach ( $handlers as $h ) {
		if ( $wpss_cov_ours( $wpss_cov_file( $h['callback'] ?? null ) ) ) {
			$artefacts['rest'][] = $route;
			break;
		}
	}
}

// AJAX actions: literal registrations in src/.
preg_match_all( "/['\"]wp_ajax_(?:nopriv_)?([a-z0-9_]+)['\"]/", $wpss_cov_src, $m );
$artefacts['ajax'] = $m[1];

// Shortcodes and blocks whose callbacks live here.
global $shortcode_tags;
foreach ( $shortcode_tags as $tag => $cb ) {
	if ( $wpss_cov_ours( $wpss_cov_file( $cb ) ) ) {
		$artefacts['shortcode'][] = $tag;
	}
}
foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
	if ( str_starts_with( $name, 'wpss/' ) && ( ! $type->render_callback || $wpss_cov_ours( $wpss_cov_file( $type->render_callback ) ) ) ) {
		$artefacts['block'][] = $name;
	}
}

// Admin pages: the menu as an administrator sees it, owned by the page callback.
if ( is_admin() ) {
	wp_set_current_user( 1 );
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	do_action( 'admin_menu' );
	global $submenu, $menu, $wp_filter;
	$wpss_cov_pages = array();
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && str_starts_with( (string) $item[2], 'wpss' ) ) {
			$wpss_cov_pages[ $item[2] ] = '';
		}
	}
	foreach ( (array) $submenu as $parent => $items ) {
		foreach ( $items as $item ) {
			if ( str_starts_with( (string) $item[2], 'wpss' ) ) {
				$wpss_cov_pages[ $item[2] ] = $parent;
			}
		}
	}
	foreach ( $wpss_cov_pages as $slug => $parent ) {
		$hook = get_plugin_page_hookname( $slug, (string) $parent );
		foreach ( isset( $wp_filter[ $hook ] ) ? $wp_filter[ $hook ]->callbacks : array() as $cbs ) {
			foreach ( $cbs as $cb ) {
				if ( $wpss_cov_ours( $wpss_cov_file( $cb['function'] ) ) ) {
					$artefacts['admin_page'][] = $slug;
					break 2;
				}
			}
		}
	}
} else {
	fwrite( STDERR, "Admin pages skipped: run with --exec=\"define('WP_ADMIN', true);\"\n" );
}

// CLI commands, templates, and the hooks this plugin fires.
preg_match_all( "/WP_CLI::add_command\(\s*'([^']+)'/", $wpss_cov_src, $m );
$artefacts['cli'] = $m[1];

foreach ( $wpss_cov_php( $wpss_cov_dir . 'templates' ) as $f ) {
	$artefacts['template'][] = substr( $f, strlen( $wpss_cov_dir . 'templates/' ), -4 );
}

preg_match_all( "/(?:do_action|apply_filters)(?:_ref_array|_deprecated)?\(\s*'(wpss_[a-z0-9_]+)'/", $wpss_cov_src, $m );
$artefacts['hook'] = $m[1];

foreach ( $artefacts as $kind => $list ) {
	$list = array_values( array_unique( $list ) );
	sort( $list );
	$artefacts[ $kind ] = $list;
}
ksort( $artefacts );

// ---------------------------------------------------------------------------
// Evidence.
// ---------------------------------------------------------------------------

$wpss_cov_evidence = array();
foreach ( array_merge( $wpss_cov_php( $wpss_cov_dir . 'tests' ), glob( $wpss_cov_dir . 'audit/journeys/*.md' ), array( $wpss_cov_dir . 'docs/qa/AGENT_SMOKE_RUNBOOK.md' ) ) as $f ) {
	$text = (string) file_get_contents( $f );
	// A PHP concatenation or a {placeholder} inside a path stands for one segment:
	// '/wpss/v1/orders/' . $id . '/requirements' and /orders/{id}/requirements
	// both read /orders/{x}/requirements.
	$text = preg_replace( "/'\s*\.\s*[^.;\n]+?\s*\.\s*'/", '{x}', $text );
	$text = preg_replace( "/'\s*\.\s*\\$[\w\->\[\]'\"()]+/", "{x}'", $text );
	$text = preg_replace( '/\{[a-z_]+\}|%[ds]/', '{x}', $text );
	$wpss_cov_evidence[ substr( $f, strlen( $wpss_cov_dir ) ) ] = $text;
}

// The render-matrix crawl records which templates and pages it rendered.
$wpss_cov_render = is_readable( $wpss_cov_dir . 'audit/render-coverage.json' )
	? (array) json_decode( (string) file_get_contents( $wpss_cov_dir . 'audit/render-coverage.json' ), true )
	: array();

/**
 * Regex that finds one artefact in an evidence file.
 *
 * @param string $kind Kind.
 * @param string $name Artefact.
 * @return string
 */
$wpss_cov_pattern = static function ( string $kind, string $name ): string {
	if ( 'rest' === $kind ) {
		$path = preg_replace( '/\(\?P<[^>]+>(?:[^()]|\([^()]*\))*\)/', '{x}', substr( $name, strlen( '/wpss/v1' ) ) );
		return '#' . preg_quote( '' === $path ? '/wpss/v1' : $path, '#' ) . '(?![\w/{])#';
	}
	if ( 'cli' === $kind ) {
		return '/\b' . str_replace( '\ ', '\s+', preg_quote( $name, '/' ) ) . '\b/';
	}

	return '/(?<![\w-])' . preg_quote( $name, '/' ) . '(?![\w-])/';
};

$map = array();
foreach ( $artefacts as $kind => $list ) {
	foreach ( $list as $name ) {
		$found = array();
		$re    = $wpss_cov_pattern( $kind, $name );
		foreach ( $wpss_cov_evidence as $file => $text ) {
			if ( preg_match( $re, $text ) ) {
				$found[] = $file;
			}
		}
		if ( in_array( $name, (array) ( $wpss_cov_render[ $kind ] ?? array() ), true ) ) {
			$found[] = 'audit/render-coverage.json';
		}
		$map[ $kind ][ $name ] = $found;
	}
}

// ---------------------------------------------------------------------------
// Report, write, gate.
// ---------------------------------------------------------------------------

$uncovered = array();
$total     = 0;
$hit       = 0;
foreach ( $map as $kind => $items ) {
	$miss = array_keys( array_filter( $items, static fn( $f ) => ! $f ) );
	$n    = count( $items );
	$total += $n;
	$hit   += $n - count( $miss );
	printf( "%-11s %4d / %4d covered  (%5.1f%%)\n", $kind, $n - count( $miss ), $n, $n ? 100 * ( $n - count( $miss ) ) / $n : 100 );
	foreach ( $miss as $name ) {
		$uncovered[] = $kind . ':' . $name;
	}
}
printf( "%-11s %4d / %4d covered  (%5.1f%%)\n", 'TOTAL', $hit, $total, $total ? 100 * $hit / $total : 100 );

if ( 'write' === $wpss_cov_mode ) {
	file_put_contents( $wpss_cov_dir . 'audit/coverage.json', wp_json_encode( array( 'artefacts' => $map ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	file_put_contents( $wpss_cov_dir . 'audit/coverage-baseline.json', wp_json_encode( array( '_doc' => 'Artefacts no check names yet. bin/coverage-map.php check fails when this list grows or goes stale. Shrink it by adding checks; never add to it by hand.', 'uncovered' => $uncovered ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	echo "Wrote audit/coverage.json and audit/coverage-baseline.json\n";
}

if ( 'check' === $wpss_cov_mode ) {
	$baseline = (array) ( json_decode( (string) @file_get_contents( $wpss_cov_dir . 'audit/coverage-baseline.json' ), true )['uncovered'] ?? array() );
	$new      = array_diff( $uncovered, $baseline );
	$stale    = array_diff( $baseline, $uncovered );

	foreach ( $new as $id ) {
		echo "FAIL  uncovered and not in the baseline: {$id} (add a check that names it)\n";
	}
	foreach ( $stale as $id ) {
		echo "FAIL  baseline lists {$id}, which is now covered or gone (run it with write to shrink the baseline)\n";
	}

	if ( $new || $stale ) {
		exit( 1 );
	}
	echo 'PASS  coverage matches the baseline (' . count( $uncovered ) . " uncovered, ratcheting down)\n";
}
