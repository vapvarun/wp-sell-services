<?php
/**
 * The 27 admin-ajax actions nothing calls any more log a deprecation, and keep working.
 *
 * Run: wp eval-file tests/test-dead-ajax-actions-deprecated.php
 *
 * The REST API replaced these handlers, but they stay registered until the next
 * major so a third-party caller gets one release of warning. Each one now runs
 * AjaxHandlers::note_deprecated_ajax() at priority 1, before its handler, which
 * calls _deprecated_function() (deprecated_function_run always fires; the notice
 * shows only with WP_DEBUG on).
 *
 * The list must stay exact both ways: a live action on it would log noise for
 * real users, so each listed action is checked for callers in the shipped Free
 * and Pro scripts, templates and PHP. accept/reject/withdraw_proposal are built
 * by name in frontend.js and must stay off it.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Frontend\AjaxHandlers;

global $wp_filter;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$listed = defined( AjaxHandlers::class . '::DEPRECATED_ACTIONS' ) ? AjaxHandlers::DEPRECATED_ACTIONS : array();
$check( 'AjaxHandlers lists 27 deprecated actions', 27 === count( array_unique( $listed ) ), (string) count( $listed ) );

// The callback registered at priority 1 on a hook, as "Class::method" names.
$at_priority_1 = static function ( string $hook ) use ( &$wp_filter ): array {
	$names = array();
	foreach ( (array) ( isset( $wp_filter[ $hook ] ) ? $wp_filter[ $hook ]->callbacks[1] ?? array() : array() ) as $cb ) {
		if ( is_array( $cb['function'] ) && is_object( $cb['function'][0] ) ) {
			$names[] = get_class( $cb['function'][0] ) . '::' . $cb['function'][1];
		}
	}
	return $names;
};
$note = AjaxHandlers::class . '::note_deprecated_ajax';

$unlogged = array();
$no_handler = array();
foreach ( $listed as $action ) {
	if ( ! in_array( $note, $at_priority_1( 'wp_ajax_' . $action ), true ) ) {
		$unlogged[] = $action;
	}
	// Its real handler is still there, so callers keep working this release.
	if ( empty( $wp_filter[ 'wp_ajax_' . $action ]->callbacks[10] ) ) {
		$no_handler[] = $action;
	}
}
$check( 'every listed action logs before its handler runs', array() === $unlogged && array() !== $listed, implode( ', ', $unlogged ) );
$check( 'every listed action still has its handler', array() === $no_handler, implode( ', ', $no_handler ) );

// No logged-out door is opened: nopriv is logged only where one already existed.
$check( 'load_services logs on its existing logged-out door', in_array( $note, $at_priority_1( 'wp_ajax_nopriv_wpss_load_services' ), true ) );
$check( 'no nopriv action appears for a logged-in-only action', ! has_action( 'wp_ajax_nopriv_wpss_start_work' ) && ! has_action( 'wp_ajax_nopriv_wpss_get_favorites' ) );

// Live actions are not logged.
foreach ( array( 'wpss_accept_proposal', 'wpss_reject_proposal', 'wpss_withdraw_proposal', 'wpss_update_request', 'wpss_send_message' ) as $live ) {
	$check( "{$live} (live) is not deprecated", ! in_array( $live, $listed, true ) && ! in_array( $note, $at_priority_1( 'wp_ajax_' . $live ), true ) );
}

// Every listed action really has no caller in what ships, Free and Pro.
$plugins = dirname( __DIR__, 2 );
$dirs    = array();
foreach ( array( 'wp-sell-services', 'wp-sell-services-pro' ) as $slug ) {
	foreach ( array( 'assets', 'templates', 'src', 'blocks' ) as $sub ) {
		if ( is_dir( "{$plugins}/{$slug}/{$sub}" ) ) {
			$dirs[] = "{$plugins}/{$slug}/{$sub}";
		}
	}
}
$callers = array();
foreach ( $dirs as $dir ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		$path = $file->getPathname();
		if ( ! preg_match( '/\.(php|js)$/', $path ) || str_ends_with( $path, '.min.js' ) || str_contains( $path, '/vendor/' ) || str_contains( $path, '/node_modules/' ) ) {
			continue;
		}
		foreach ( file( $path ) as $n => $line ) {
			if ( preg_match( '#^\s*(//|\*|/\*|\#)#', $line ) ) {
				continue;
			}
			foreach ( $listed as $action ) {
				if ( preg_match( '/\b' . preg_quote( $action, '/' ) . '\b/', $line ) && ! preg_match( '/wp_ajax_(nopriv_)?' . preg_quote( $action, '/' ) . '\b/', $line ) && ! str_contains( $path, 'src/Frontend/AjaxHandlers.php' ) ) {
					$callers[] = $action . ' <- ' . substr( $path, strlen( $plugins ) + 1 ) . ':' . ( $n + 1 );
				}
			}
		}
	}
}
// The constant itself names them, and it lives in AjaxHandlers, which is skipped above.
$check( 'no shipped Free or Pro script, template or PHP calls a listed action', array() === $callers, implode( '; ', $callers ) );

// A call logs once, names the action, and says when it was deprecated.
$seen = array();
$spy  = static function ( $name, $replacement, $version ) use ( &$seen ) {
	$seen[] = array( $name, $version );
};
add_action( 'deprecated_function_run', $spy, 10, 3 );
add_filter( 'deprecated_function_trigger_error', '__return_false' );
$handlers = new AjaxHandlers();
if ( method_exists( $handlers, 'note_deprecated_ajax' ) ) {
	add_action( 'wp_ajax_wpss_qa_probe', array( $handlers, 'note_deprecated_ajax' ), 1 );
	do_action( 'wp_ajax_wpss_qa_probe' );
	remove_action( 'wp_ajax_wpss_qa_probe', array( $handlers, 'note_deprecated_ajax' ), 1 );
}
remove_filter( 'deprecated_function_trigger_error', '__return_false' );
remove_action( 'deprecated_function_run', $spy, 10 );
$check( 'a call logs one deprecation naming the action, version 1.9.3', array( array( 'admin-ajax action wpss_qa_probe', '1.9.3' ) ) === $seen, wp_json_encode( $seen ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
