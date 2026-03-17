<?php
/**
 * WP-CLI Validate Command
 *
 * Provides schema validation commands for development.
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Core\SchemaValidator;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Validates WP Sell Services models and schema.
 *
 * ## EXAMPLES
 *
 *     # Run full schema validation
 *     $ wp wpss validate schema
 *
 *     # Run model consistency check
 *     $ wp wpss validate models
 *
 *     # Run all validations
 *     $ wp wpss validate all
 *
 * @since 1.0.0
 */
class ValidateCommand {

	/**
	 * Validate model-to-schema mappings.
	 *
	 * Checks that model properties correctly map to database columns.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss validate schema
	 *     $ wp wpss validate schema --format=json
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associated arguments.
	 */
	public function schema( array $args, array $assoc_args ): void {
		$validator = new SchemaValidator();
		$results   = $validator->validate_all();

		$format = $assoc_args['format'] ?? 'table';

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $results, JSON_PRETTY_PRINT ) );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%BSchema Validation Report%n' ) );
		WP_CLI::line( str_repeat( '=', 50 ) );
		WP_CLI::line( '' );

		$has_errors   = false;
		$has_warnings = false;

		foreach ( $results as $model => $result ) {
			$short_name = substr( $model, strrpos( $model, '\\' ) + 1 );

			if ( empty( $result['errors'] ) && empty( $result['warnings'] ) ) {
				WP_CLI::line( WP_CLI::colorize( "%G✓%n {$short_name}: OK" ) );
				continue;
			}

			if ( ! empty( $result['errors'] ) ) {
				$has_errors = true;
				WP_CLI::line( WP_CLI::colorize( "%R✗%n {$short_name}:" ) );
				foreach ( $result['errors'] as $error ) {
					WP_CLI::line( WP_CLI::colorize( "  %RERROR:%n {$error}" ) );
				}
			}

			if ( ! empty( $result['warnings'] ) ) {
				$has_warnings = true;
				if ( empty( $result['errors'] ) ) {
					WP_CLI::line( WP_CLI::colorize( "%Y⚠%n {$short_name}:" ) );
				}
				foreach ( $result['warnings'] as $warning ) {
					WP_CLI::line( WP_CLI::colorize( "  %YWARNING:%n {$warning}" ) );
				}
			}

			WP_CLI::line( '' );
		}

		WP_CLI::line( '' );

		if ( $has_errors ) {
			WP_CLI::error( 'Schema validation failed with errors.', false );
		} elseif ( $has_warnings ) {
			WP_CLI::warning( 'Schema validation passed with warnings.' );
		} else {
			WP_CLI::success( 'All models validated successfully!' );
		}
	}

	/**
	 * Check model consistency (naming conventions, required methods).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss validate models
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associated arguments.
	 */
	public function models( array $args, array $assoc_args ): void {
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%BModel Consistency Check%n' ) );
		WP_CLI::line( str_repeat( '=', 50 ) );
		WP_CLI::line( '' );

		$models_dir = WPSS_PLUGIN_DIR . 'src/Models';
		$issues     = [];

		if ( ! is_dir( $models_dir ) ) {
			WP_CLI::error( 'Models directory not found.' );
			return;
		}

		$files = glob( $models_dir . '/*.php' );

		foreach ( $files as $file ) {
			$content  = file_get_contents( $file );
			$filename = basename( $file, '.php' );

			// Check for from_row (should be from_db).
			if ( preg_match( '/function\s+from_row\s*\(/', $content ) ) {
				$issues[] = [
					'file'    => $filename,
					'type'    => 'error',
					'message' => 'Uses deprecated from_row() - should be from_db()',
				];
			}

			// Check for missing from_db (except Service which uses from_post).
			if ( 'Service' !== $filename && ! preg_match( '/function\s+from_db\s*\(/', $content ) ) {
				// Only warn if it has database interaction.
				if ( preg_match( '/\$wpdb|get_row|get_results/', $content ) ) {
					$issues[] = [
						'file'    => $filename,
						'type'    => 'warning',
						'message' => 'Missing from_db() method for DB hydration',
					];
				}
			}

			// Check Service has from_post.
			if ( 'Service' === $filename && ! preg_match( '/function\s+from_post\s*\(/', $content ) ) {
				$issues[] = [
					'file'    => $filename,
					'type'    => 'error',
					'message' => 'Service model missing from_post() method',
				];
			}

			WP_CLI::line( WP_CLI::colorize( "%c→%n Checking {$filename}..." ) );
		}

		WP_CLI::line( '' );

		if ( empty( $issues ) ) {
			WP_CLI::success( 'All models follow naming conventions!' );
			return;
		}

		WP_CLI::line( WP_CLI::colorize( '%RIssues Found:%n' ) );
		WP_CLI::line( '' );

		foreach ( $issues as $issue ) {
			$color = 'error' === $issue['type'] ? '%R' : '%Y';
			$icon  = 'error' === $issue['type'] ? '✗' : '⚠';
			WP_CLI::line( WP_CLI::colorize( "{$color}{$icon}%n {$issue['file']}: {$issue['message']}" ) );
		}

		$error_count = count( array_filter( $issues, fn( $i ) => 'error' === $i['type'] ) );

		if ( $error_count > 0 ) {
			WP_CLI::error( "Found {$error_count} error(s).", false );
		} else {
			WP_CLI::warning( 'Found ' . count( $issues ) . ' warning(s).' );
		}
	}

	/**
	 * Run all validation checks.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss validate all
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associated arguments.
	 */
	public function all( array $args, array $assoc_args ): void {
		$this->schema( $args, $assoc_args );
		WP_CLI::line( '' );
		$this->models( $args, $assoc_args );
	}
}
