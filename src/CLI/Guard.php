<?php
/**
 * The one gate in front of every WP-CLI command that writes or deletes rows.
 *
 * @package WPSellServices\CLI
 * @since   1.7.1
 */

declare(strict_types=1);

namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;

/**
 * Production refusal plus a row-count confirmation, shared by every writing command.
 *
 * Before this existed each command decided for itself: `demo delete` prompted
 * with the wrong count, `scale seed` and `test:flow` wrote straight into live
 * tables with no prompt at all. Every command now routes through here, so the
 * flags mean the same thing everywhere: `--force` overrides the production
 * refusal, `--yes` skips the confirmation.
 */
final class Guard {

	/**
	 * Refuse on a production site unless --force, then confirm the count unless --yes.
	 *
	 * @param string               $what       What the count is, in the plural: "demo services".
	 * @param int                  $count      How many will be written or deleted.
	 * @param array<string, mixed> $assoc_args Command flags; reads `force` and `yes`.
	 * @return void
	 */
	public static function writes( string $what, int $count, array $assoc_args ): void {
		if ( 'production' === wp_get_environment_type() && empty( $assoc_args['force'] ) ) {
			WP_CLI::error( "Refusing on a production site: {$count} {$what} would be written or deleted. Pass --force to override." );
		}

		WP_CLI::confirm( "{$count} {$what} will be written or deleted. Continue?", $assoc_args );
	}
}
