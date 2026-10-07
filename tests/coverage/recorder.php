<?php
/**
 * Execution recorder for coverage runs. Test tooling only - never shipped
 * (tests/ is in .distignore).
 *
 * Loaded by bin/coverage-run.sh: through `wp --require` for CLI checks, and by
 * a temporary mu-plugin for HTTP requests. It does nothing unless the sentinel
 * file wp-content/wpss-coverage.log exists, so a leftover loader is inert.
 *
 * Appends one JSON line per request naming what actually ran: the REST route
 * pattern dispatched, the AJAX action, shortcodes and blocks rendered, the
 * admin page, the WP-CLI command, the plugin templates included and the wpss_
 * hooks fired. bin/coverage-run.sh folds the log into audit/render-coverage.json,
 * which bin/coverage-map.php counts as evidence.
 *
 * @package WPSellServices
 */

if ( ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) || defined( 'WPSS_COVERAGE_RECORDER' ) ) {
	return;
}
// Loaded twice on CLI runs (wp --require and the mu-plugin loader).
define( 'WPSS_COVERAGE_RECORDER', true );

( static function () {
	$log = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__, 4 ) ) . '/wpss-coverage.log';

	if ( ! file_exists( $log ) ) {
		return;
	}

	$seen = array();
	$add  = static function ( string $kind, string $name ) use ( &$seen ) {
		$seen[ $kind ][ $name ] = true;
	};

	$hook = static function ( $tag, $cb, $prio = 10, $n = 1 ) {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( $tag, $cb, $prio, $n );
		} else {
			// Required before WordPress loads (wp --require): queue the hook.
			$GLOBALS['wp_filter'][ $tag ][ $prio ][] = array(
				'function'      => $cb,
				'accepted_args' => $n,
			);
		}
	};

	$hook(
		'all',
		static function ( $tag ) use ( $add ) {
			if ( is_string( $tag ) && str_starts_with( $tag, 'wpss_' ) ) {
				$add( 'hook', $tag );
			}
		}
	);
	$hook(
		'rest_dispatch_request',
		static function ( $result, $request, $route ) use ( $add ) {
			$add( 'rest', (string) $route );
			return $result;
		},
		10,
		3
	);
	$hook(
		'do_shortcode_tag',
		static function ( $out, $tag ) use ( $add ) {
			$add( 'shortcode', (string) $tag );
			return $out;
		},
		10,
		2
	);
	$hook(
		'render_block',
		static function ( $out, $block ) use ( $add ) {
			if ( ! empty( $block['blockName'] ) ) {
				$add( 'block', (string) $block['blockName'] );
			}
			return $out;
		},
		10,
		2
	);
	$hook(
		'admin_init',
		static function () use ( $add ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- recording only.
			if ( wp_doing_ajax() && ! empty( $_REQUEST['action'] ) ) {
				$add( 'ajax', sanitize_key( wp_unslash( $_REQUEST['action'] ) ) );
			}
			if ( ! empty( $_GET['page'] ) ) {
				$add( 'admin_page', sanitize_key( wp_unslash( $_GET['page'] ) ) );
			}
			// phpcs:enable
		}
	);

	if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
		WP_CLI::add_hook(
			'before_run_command',
			static function ( $args ) use ( $add ) {
				if ( isset( $args[0], $args[1] ) && 'wpss' === $args[0] ) {
					$add( 'cli', 'wpss ' . $args[1] );
				}
			}
		);
	}

	register_shutdown_function(
		static function () use ( &$seen, $log ) {
			$templates = dirname( __DIR__, 2 ) . '/templates/';
			foreach ( get_included_files() as $file ) {
				if ( str_starts_with( $file, $templates ) ) {
					$seen['template'][ substr( $file, strlen( $templates ), -4 ) ] = true;
				}
			}

			$line = array();
			foreach ( $seen as $kind => $names ) {
				$line[ $kind ] = array_keys( $names );
			}

			if ( $line ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $log, json_encode( $line ) . "\n", FILE_APPEND | LOCK_EX );
			}
		}
	);
} )();
