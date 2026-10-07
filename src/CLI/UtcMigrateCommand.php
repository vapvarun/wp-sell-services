<?php
/**
 * WP-CLI: convert stored datetimes to UTC (Basecamp 10351460106).
 *
 * @package WPSellServices\CLI
 * @since   1.8.0
 */

namespace WPSellServices\CLI;

use WP_CLI;
use WPSellServices\Database\UtcMigration;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the one-time UTC conversion in the foreground, or previews it.
 */
class UtcMigrateCommand {

	/**
	 * Convert pre-1.8.0 datetimes to UTC now, or preview the change.
	 *
	 * The background job does the same work in chunks; this runs it to the end
	 * in one go. Safe to run again: converted rows are not touched twice.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print rows per column and three "old -> new" samples, write nothing.
	 *
	 * [--force]
	 * : Allow the conversion on a site whose environment type is production.
	 *
	 * [--yes]
	 * : Skip the confirmation of how many tables will be converted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss utc-migrate --dry-run
	 *     wp wpss utc-migrate
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$state   = get_option( UtcMigration::OPTION );

		if ( ! is_array( $state ) ) {
			$state = UtcMigration::plan();
		}

		WP_CLI::log( sprintf( 'Site on UTC: %s. Database server offset: %+d s. Status: %s.', $state['site_is_utc'] ? 'yes' : 'no', (int) $state['server_offset'], $state['status'] ) );

		if ( ! $dry_run ) {
			Guard::writes( 'tables of stored datetimes to UTC', count( $state['cutover'] ?? array() ), $assoc_args );
		}

		if ( 'done' === $state['status'] ) {
			WP_CLI::success( 'Nothing to convert.' );
			return;
		}

		$samples = array();
		$guard   = 0;

		if ( $dry_run ) {
			while ( ! UtcMigration::advance( $state, true, $samples ) && ++$guard < 100000 ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
			}

			foreach ( $samples as $key => $value ) {
				if ( '#rows' === substr( $key, -5 ) ) {
					continue;
				}
				WP_CLI::log( sprintf( '%s: %d rows', $key, (int) ( $samples[ $key . '#rows' ] ?? 0 ) ) );
				foreach ( (array) $value as $line ) {
					WP_CLI::log( '    ' . $line );
				}
			}
			WP_CLI::success( 'Dry run: nothing written.' );
			return;
		}

		// The same chunk the background job runs: rows and cursor commit together.
		update_option( UtcMigration::OPTION, $state, false );
		while ( ! UtcMigration::step() && ++$guard < 100000 ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
		}

		$state = get_option( UtcMigration::OPTION );
		\WPSellServices\Services\Scheduler::unschedule_all( UtcMigration::HOOK );
		WP_CLI::success( 'Converted. Status: ' . $state['status'] . '.' );
	}
}
