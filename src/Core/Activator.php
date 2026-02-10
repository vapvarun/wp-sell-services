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
		// Vendor capabilities.
		$vendor_caps = array(
			'wpss_vendor'              => true,
			'wpss_manage_services'     => true,
			'wpss_manage_orders'       => true,
			'wpss_view_analytics'      => true,
			'wpss_respond_to_requests' => true,
			'read'                     => true, // Basic WordPress capability.
			'upload_files'             => true,
			'edit_posts'               => true,
		);

		// Create the vendor role if it doesn't exist.
		if ( ! get_role( 'wpss_vendor' ) ) {
			add_role(
				'wpss_vendor',
				__( 'Vendor', 'wp-sell-services' ),
				$vendor_caps
			);
		} else {
			// Role exists, ensure it has all capabilities.
			$vendor_role = get_role( 'wpss_vendor' );
			foreach ( $vendor_caps as $cap => $grant ) {
				$vendor_role->add_cap( $cap, $grant );
			}
		}

		// Add vendor capabilities to existing roles that should have them.
		$roles_with_vendor_caps = array( 'administrator', 'shop_manager', 'author' );

		foreach ( $roles_with_vendor_caps as $role_name ) {
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
	 * Option names must match those registered in Settings.php.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = array(
			// General settings - matches Settings.php wpss_general.
			'wpss_general'       => array(
				'platform_name'      => get_bloginfo( 'name' ),
				'currency'           => 'USD',
				'ecommerce_platform' => 'auto',
			),
			// Commission settings - matches Settings.php wpss_commission.
			'wpss_commission'    => array(
				'commission_rate'     => 10,
				'enable_vendor_rates' => true,
			),
			// Payouts settings - matches Settings.php wpss_payouts.
			'wpss_payouts'       => array(
				'min_withdrawal'            => 50,
				'clearance_days'            => 14,
				'auto_withdrawal_enabled'   => false,
				'auto_withdrawal_threshold' => 500,
				'auto_withdrawal_schedule'  => 'monthly',
			),
			// Tax settings - matches Settings.php wpss_tax.
			'wpss_tax'           => array(
				'enable_tax'        => false,
				'tax_label'         => 'Tax',
				'tax_rate'          => 0,
				'tax_included'      => false,
				'tax_on_commission' => 'none',
			),
			// Vendor settings - matches Settings.php wpss_vendor.
			'wpss_vendor'        => array(
				'vendor_registration'        => 'open',
				'max_services_per_vendor'    => 20,
				'require_verification'       => false,
				'require_service_moderation' => false,
			),
			// Order settings - matches Settings.php wpss_orders.
			'wpss_orders'        => array(
				'auto_complete_days'  => 3,
				'revision_limit'      => 2,
				'allow_disputes'      => true,
				'dispute_window_days' => 14,
			),
			// Notification settings - matches Settings.php wpss_notifications.
			'wpss_notifications' => array(
				'notify_new_order'          => true,
				'notify_order_completed'    => true,
				'notify_order_cancelled'    => true,
				'notify_delivery_submitted' => true,
				'notify_revision_requested' => true,
				'notify_new_message'        => true,
				'notify_new_review'         => true,
				'notify_dispute_opened'     => true,
			),
			// Advanced settings - matches Settings.php wpss_advanced.
			'wpss_advanced'      => array(
				'delete_data_on_uninstall' => false,
				'enable_debug_mode'        => false,
			),
		);

		foreach ( $defaults as $option_name => $option_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}

		// Clean up old incorrectly-named options from previous versions.
		$old_options = array( 'wpss_general_settings', 'wpss_vendor_settings', 'wpss_notification_settings' );
		foreach ( $old_options as $old_option ) {
			delete_option( $old_option );
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

		// Process auto-withdrawals (uses dynamic scheduling based on settings).
		\WPSellServices\Services\EarningsService::schedule_auto_withdrawal_cron();
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
