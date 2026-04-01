<?php
/**
 * WP-CLI Utils namespace stubs for PHPStan.
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

namespace WP_CLI\Utils;

if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
	/**
	 * Format items as table/CSV/JSON.
	 *
	 * @param string $format Format type.
	 * @param array  $items  Items to display.
	 * @param mixed  $fields Fields to include.
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {} // phpcs:ignore
}

if ( ! function_exists( 'WP_CLI\Utils\make_progress_bar' ) ) {
	/**
	 * Create a progress bar.
	 *
	 * @param string $message Message.
	 * @param int    $count   Count.
	 * @return object
	 */
	function make_progress_bar( $message, $count ) { // phpcs:ignore
		return new class {
			/** @return void */
			public function tick(): void {}
			/** @return void */
			public function finish(): void {}
		};
	}
}
