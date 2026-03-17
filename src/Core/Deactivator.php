<?php
/**
 * Plugin Deactivator
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

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
			'wpss_check_late_orders',
			'wpss_auto_complete_orders',
			'wpss_send_deadline_reminders',
			'wpss_send_requirements_reminders',
			'wpss_check_requirements_timeout',
			'wpss_recalculate_seller_levels',
			'wpss_process_cancellation_timeouts',
			'wpss_cleanup_expired_requests',
			'wpss_update_vendor_stats',
			'wpss_process_auto_withdrawals',
			'wpss_cron_daily',
		);

		foreach ( $cron_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
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
