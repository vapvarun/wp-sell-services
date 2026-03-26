<?php
/**
 * Plugin Activator
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

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
		self::create_pages();
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
		// No required dependencies for standalone mode.
	}

	/**
	 * Create database tables using SchemaManager.
	 *
	 * Creates all 18 plugin tables.
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
				'Vendor', // Avoid __() here — runs before init, causes textdomain early-loading notice.
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
				'currency'           => self::detect_currency_from_locale(),
				'ecommerce_platform' => 'auto',
			),
			// Commission settings - matches Settings.php wpss_commission.
			'wpss_commission'    => array(
				'commission_rate'     => 10,
				'enable_vendor_rates' => true,
			),
			// Payouts settings - matches Settings.php wpss_payouts.
			'wpss_payouts'       => array(
				'min_withdrawal'            => 25,
				'clearance_days'            => 14,
				'auto_withdrawal_enabled'   => false,
				'auto_withdrawal_threshold' => 500,
				'auto_withdrawal_schedule'  => 'monthly',
			),
			// Tax settings - matches Settings.php wpss_tax.
			'wpss_tax'           => array(
				'enable_tax'   => false,
				'tax_label'    => 'Tax',
				'tax_rate'     => 0,
				'tax_included' => false,
			),
			// Vendor settings - matches Settings.php wpss_vendor.
			'wpss_vendor'        => array(
				'vendor_registration'        => 'open',
				'max_services_per_vendor'    => 20,
				'require_verification'       => false,
				'require_service_moderation' => true,
			),
			// Order settings - matches Settings.php wpss_orders.
			// Revision limits are defined per-package in service packages, not as a global setting.
			'wpss_orders'        => array(
				'auto_complete_days'       => 3,
				'allow_disputes'           => true,
				'dispute_window_days'      => 14,
				'auto_dispute_late_days'   => 3,
				'requirements_timeout_days' => 7,
			),
			// Notification settings - matches Settings.php wpss_notifications.
			// ALL email types enabled by default — site owner can disable individually.
			'wpss_notifications' => array(
				'notify_new_order'              => true,
				'notify_order_completed'        => true,
				'notify_order_cancelled'        => true,
				'notify_cancellation_requested' => true,
				'notify_delivery_submitted'     => true,
				'notify_revision_requested'     => true,
				'notify_new_message'            => true,
				'notify_vendor_contact'         => true,
				'notify_new_review'             => true,
				'notify_dispute_opened'         => true,
				'notify_withdrawal_requested'   => true,
				'notify_withdrawal_approved'    => true,
				'notify_withdrawal_rejected'    => true,
				'notify_proposal_submitted'     => true,
				'notify_proposal_accepted'      => true,
				'notify_moderation'             => true,
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

		// Redirect to setup wizard on first activation.
		if ( false === get_option( 'wpss_setup_wizard_completed' ) ) {
			set_transient( 'wpss_activation_redirect', true, 30 );
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_cron_events(): void {
		// Most cron events are scheduled by OrderWorkflowManager::schedule_cron_events()
		// on every init. We only handle EarningsService here since it has its own
		// dynamic scheduling logic based on admin settings.
		\WPSellServices\Services\EarningsService::schedule_auto_withdrawal_cron();
	}

	/**
	 * Create WooCommerce carrier product for service orders.
	 *
	 * @deprecated Moved to Pro plugin. Kept for backward compatibility.
	 * @return void
	 */
	private static function create_wc_carrier_product(): void {
		// WooCommerce integration moved to Pro plugin.
	}

	/**
	 * Create required pages with shortcodes on activation.
	 *
	 * Creates Services, Dashboard, Become a Vendor, and Service Checkout pages
	 * if they don't already exist. Maps page IDs in the wpss_pages option.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	private static function create_pages(): void {
		$pages = array(
			'services_page' => array(
				'title'     => __( 'Services', 'wp-sell-services' ),
				'shortcode' => '[wpss_services]',
			),
			'dashboard'     => array(
				'title'     => __( 'Dashboard', 'wp-sell-services' ),
				'shortcode' => '[wpss_dashboard]',
			),
			'become_vendor' => array(
				'title'     => __( 'Become a Vendor', 'wp-sell-services' ),
				'shortcode' => '[wpss_vendor_registration]',
			),
			'checkout'      => array(
				'title'     => __( 'Service Checkout', 'wp-sell-services' ),
				'shortcode' => '[wpss_checkout]',
			),
		);

		$saved_pages = get_option( 'wpss_pages', array() );

		foreach ( $pages as $key => $page_data ) {
			// Skip if already mapped to a valid published page.
			if ( ! empty( $saved_pages[ $key ] ) ) {
				$existing = get_post( $saved_pages[ $key ] );
				if ( $existing && 'publish' === $existing->post_status ) {
					continue;
				}
			}

			// Check if a page with this shortcode already exists.
			$existing_page = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					's'              => $page_data['shortcode'],
					'posts_per_page' => 1,
				)
			);

			if ( ! empty( $existing_page ) ) {
				$saved_pages[ $key ] = $existing_page[0]->ID;
				continue;
			}

			// Create the page.
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page_data['title'],
					'post_content' => $page_data['shortcode'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$saved_pages[ $key ] = $page_id;
			}
		}

		update_option( 'wpss_pages', $saved_pages );
	}

	/**
	 * Detect currency from WordPress locale.
	 *
	 * @return string Currency code (ISO 4217).
	 */
	private static function detect_currency_from_locale(): string {
		$locale = get_locale();
		$map    = array(
			'en_GB' => 'GBP',
			'en_AU' => 'AUD',
			'en_CA' => 'CAD',
			'de_DE' => 'EUR',
			'fr_FR' => 'EUR',
			'es_ES' => 'EUR',
			'it_IT' => 'EUR',
			'nl_NL' => 'EUR',
			'pt_PT' => 'EUR',
			'pt_BR' => 'BRL',
			'ja'    => 'JPY',
			'zh_CN' => 'CNY',
			'hi_IN' => 'INR',
			'es_MX' => 'MXN',
		);

		return $map[ $locale ] ?? 'USD';
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
