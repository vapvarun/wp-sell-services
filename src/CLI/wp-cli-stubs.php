<?php
/**
 * WP-CLI stub classes for non-CLI contexts.
 *
 * Allows ServiceCommands to be instantiated in browser/AJAX context
 * (e.g., demo content import) without WP-CLI being loaded.
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Stub WP_CLI_Command for non-CLI usage.
	 */
	class WP_CLI_Command {}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Stub WP_CLI for non-CLI usage.
	 */
	class WP_CLI {
		/**
		 * No-op log.
		 *
		 * @param string $message Message.
		 */
		public static function log( $message ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * No-op error (non-fatal in stub).
		 *
		 * @param string $message Message.
		 */
		public static function error( $message ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * No-op success.
		 *
		 * @param string $message Message.
		 */
		public static function success( $message ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * No-op warning.
		 *
		 * @param string $message Message.
		 */
		public static function warning( $message ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * No-op confirm.
		 *
		 * @param string $message Message.
		 * @param array  $assoc_args Args.
		 */
		public static function confirm( $message, $assoc_args = array() ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}
}

// phpcs:enable
