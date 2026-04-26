<?php
defined( 'ABSPATH' ) || exit;
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
 // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

		/**
		 * Log a message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function log( string $message ): void {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Display an error and optionally exit.
		 *
		 * @param string $message Message.
		 * @param bool   $exit    Whether to exit (default true).
		 * @return void
		 */
		public static function error( string $message, bool $exit = true ): void {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Display a success message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( string $message ): void {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Display a warning.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function warning( string $message ): void {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Ask for confirmation.
		 *
		 * @param string $message    Message.
		 * @param array  $assoc_args Args.
		 * @return void
		 */
		public static function confirm( string $message, array $assoc_args = array() ): void {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Register a command.
		 *
		 * @param string       $name    Command name.
		 * @param string|array $handler Handler class or callable.
		 * @param array        $args    Command args.
		 * @return bool
		 */
		public static function add_command( string $name, $handler, array $args = array() ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			return true;
		}

		/**
		 * Colorize a string.
		 *
		 * @param string $string String with color tokens.
		 * @return string
		 */
		public static function colorize( string $string ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			return $string;
		}

		/**
		 * Display a line (alias for log).
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function line( string $message = '' ): void {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Run a WP-CLI command.
		 *
		 * @param string $command Command string.
		 * @param array  $options Options.
		 * @return mixed
		 */
		public static function runcommand( string $command, array $options = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			return null;
		}
	}
}

// phpcs:enable
