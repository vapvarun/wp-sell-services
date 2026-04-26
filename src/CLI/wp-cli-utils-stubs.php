<?php
/**
 * WP-CLI Utils namespace stubs for PHPStan.
 *
 * The `WP_CLI\Utils` namespace is owned by wp-cli/wp-cli, not by this plugin.
 * These are minimal compatibility stubs so PHPStan can resolve calls when
 * wp-cli is not installed in the analysis environment. Runtime behaviour is
 * unaffected — the real wp-cli implementations replace these via
 * `function_exists` guards.
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- Stubs for the wp-cli/wp-cli `WP_CLI\Utils` namespace; the prefix is fixed by the upstream library.
namespace WP_CLI\Utils;

if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
	/**
	 * Format items as table/CSV/JSON.
	 *
	 * Stub for `WP_CLI\Utils\format_items()` so PHPStan can resolve the call
	 * when wp-cli is not loaded.
	 *
	 * @param string            $format Format type.
	 * @param array<int, mixed> $items  Items to display.
	 * @param mixed             $fields Fields to include.
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {} // phpcs:ignore
}

if ( ! function_exists( 'WP_CLI\Utils\make_progress_bar' ) ) {
	/**
	 * Create a progress bar.
	 *
	 * Stub for `WP_CLI\Utils\make_progress_bar()` so PHPStan can resolve the
	 * call when wp-cli is not loaded. Returns an anonymous class that mirrors
	 * the public surface (`tick()`, `finish()`) of the real progress bar.
	 *
	 * @param string $message Message.
	 * @param int    $count   Count.
	 * @return object
	 */
	function make_progress_bar( $message, $count ) { // phpcs:ignore
		return new class() {
			/**
			 * Advance the progress bar one step.
			 *
			 * @return void
			 */
			public function tick(): void {}
			/**
			 * Finalize the progress bar.
			 *
			 * @return void
			 */
			public function finish(): void {}
		};
	}
}
