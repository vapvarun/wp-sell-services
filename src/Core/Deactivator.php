<?php
/**
 * Plugin Deactivator
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Core;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::clear_cron_events();
		self::clear_transients();
		self::flush_rewrite_rules();
	}

	/**
	 * Clear scheduled cron events.
	 *
	 * @return void
	 */
	private static function clear_cron_events(): void {
		$cron_hooks = array(
			'wpss_auto_complete_orders',
			'wpss_cleanup_expired_requests',
			'wpss_update_vendor_stats',
			'wpss_process_auto_withdrawals',
		);

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Clear plugin transients.
	 *
	 * @return void
	 */
	private static function clear_transients(): void {
		delete_transient( 'wpss_show_wc_notice' );
		delete_transient( 'wpss_flush_rewrite_rules' );
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules(): void {
		flush_rewrite_rules();
	}
}
