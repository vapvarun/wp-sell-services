<?php
/**
 * Plugin Activator
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Core;

use WPSellServices\Database\SchemaManager;
use WPSellServices\Database\MigrationManager;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_dependencies();
		self::create_tables();
		self::run_migrations();
		self::create_roles();
		self::set_default_options();
		self::create_wc_carrier_product();
		self::schedule_cron_events();
		self::flush_rewrite_rules();
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @return void
	 */
	private static function check_dependencies(): void {
		// Check for WooCommerce (required for free version).
		if ( ! class_exists( 'WooCommerce' ) ) {
			// WooCommerce not required initially, but show notice.
			set_transient( 'wpss_show_wc_notice', true, 30 );
		}
	}

	/**
	 * Create database tables using SchemaManager.
	 *
	 * Creates all 20 plugin tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		$schema = new SchemaManager();
		$schema->install();
	}

	/**
	 * Run database migrations.
	 *
	 * Handles migration from woo-sell-services if needed.
	 *
	 * @return void
	 */
	private static function run_migrations(): void {
		$schema    = new SchemaManager();
		$migration = new MigrationManager( $schema );

		if ( $migration->should_migrate_from_wss() ) {
			$migration->run_migrations();
		}
	}

	/**
	 * Create custom roles and capabilities.
	 *
	 * @return void
	 */
	private static function create_roles(): void {
		// Add vendor capabilities to existing roles.
		$vendor_caps = array(
			'wpss_manage_services'     => true,
			'wpss_manage_orders'       => true,
			'wpss_view_analytics'      => true,
			'wpss_respond_to_requests' => true,
		);

		// Get roles that should have vendor capabilities.
		$vendor_roles = array( 'administrator', 'shop_manager', 'author' );

		foreach ( $vendor_roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $vendor_caps as $cap => $grant ) {
					$role->add_cap( $cap, $grant );
				}
			}
		}

		// Admin-only capabilities.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'wpss_manage_settings', true );
			$admin_role->add_cap( 'wpss_manage_disputes', true );
			$admin_role->add_cap( 'wpss_manage_vendors', true );
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = array(
			'wpss_general_settings'      => array(
				'currency'              => 'USD',
				'date_format'           => 'Y-m-d',
				'auto_complete_days'    => 3,
				'enable_buyer_requests' => true,
				'enable_disputes'       => true,
			),
			'wpss_notification_settings' => array(
				'enable_email_notifications' => true,
				'enable_live_notifications'  => true,
				'notification_sound'         => true,
				'polling_interval'           => 10,
			),
			'wpss_vendor_settings'       => array(
				'auto_approve_vendors'    => false,
				'enable_verification'     => true,
				'verification_tiers'      => array( 'basic', 'verified', 'pro' ),
				'default_commission_rate' => 0,
			),
		);

		foreach ( $defaults as $option_name => $option_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}

		// Set activation timestamp.
		if ( false === get_option( 'wpss_activated_at' ) ) {
			add_option( 'wpss_activated_at', time() );
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_cron_events(): void {
		// Auto-complete orders cron.
		if ( ! wp_next_scheduled( 'wpss_auto_complete_orders' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpss_auto_complete_orders' );
		}

		// Clean up expired buyer requests.
		if ( ! wp_next_scheduled( 'wpss_cleanup_expired_requests' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_cleanup_expired_requests' );
		}

		// Update vendor stats.
		if ( ! wp_next_scheduled( 'wpss_update_vendor_stats' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wpss_update_vendor_stats' );
		}
	}

	/**
	 * Create WooCommerce carrier product for service orders.
	 *
	 * @return void
	 */
	private static function create_wc_carrier_product(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Use WCServiceCarrier to create the product.
		if ( class_exists( '\WPSellServices\Integrations\WooCommerce\WCServiceCarrier' ) ) {
			\WPSellServices\Integrations\WooCommerce\WCServiceCarrier::activate();
		}
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules(): void {
		// Set flag to flush rules on next init.
		set_transient( 'wpss_flush_rewrite_rules', true, 60 );
	}
}
