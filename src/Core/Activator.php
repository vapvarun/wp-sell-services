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

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Database schema version.
	 *
	 * @var string
	 */
	public const SCHEMA_VERSION = '1.0.0';

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_dependencies();
		self::create_tables();
		self::create_roles();
		self::set_default_options();
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
	 * Create database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Service Packages table.
		$table_packages = $wpdb->prefix . 'wpss_service_packages';
		$sql_packages   = "CREATE TABLE {$table_packages} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			service_id bigint(20) UNSIGNED NOT NULL,
			name varchar(100) NOT NULL,
			description text,
			price decimal(10,2) NOT NULL,
			delivery_days int(11) NOT NULL,
			revisions int(11) DEFAULT 0,
			features longtext,
			sort_order int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_service (service_id)
		) {$charset_collate};";
		dbDelta( $sql_packages );

		// Orders table.
		$table_orders = $wpdb->prefix . 'wpss_orders';
		$sql_orders   = "CREATE TABLE {$table_orders} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number varchar(50) NOT NULL,
			customer_id bigint(20) UNSIGNED NOT NULL,
			vendor_id bigint(20) UNSIGNED NOT NULL,
			service_id bigint(20) UNSIGNED NOT NULL,
			package_id bigint(20) UNSIGNED,
			addons longtext,
			platform varchar(50) DEFAULT 'standalone',
			platform_order_id bigint(20) UNSIGNED,
			platform_item_id bigint(20) UNSIGNED,
			subtotal decimal(10,2) NOT NULL,
			addons_total decimal(10,2) DEFAULT 0,
			total decimal(10,2) NOT NULL,
			currency varchar(10) DEFAULT 'USD',
			status varchar(50) DEFAULT 'pending_payment',
			delivery_deadline datetime,
			original_deadline datetime,
			payment_method varchar(50),
			payment_status varchar(50) DEFAULT 'pending',
			transaction_id varchar(255),
			paid_at datetime,
			revisions_included int(11) DEFAULT 0,
			revisions_used int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			started_at datetime,
			completed_at datetime,
			PRIMARY KEY (id),
			UNIQUE KEY order_number (order_number),
			KEY idx_customer (customer_id),
			KEY idx_vendor (vendor_id),
			KEY idx_status (status),
			KEY idx_platform (platform, platform_order_id)
		) {$charset_collate};";
		dbDelta( $sql_orders );

		// Conversations table.
		$table_conversations = $wpdb->prefix . 'wpss_conversations';
		$sql_conversations   = "CREATE TABLE {$table_conversations} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			sender_id bigint(20) UNSIGNED NOT NULL,
			recipient_id bigint(20) UNSIGNED NOT NULL,
			message longtext NOT NULL,
			message_type enum('text','delivery','revision_request','extension_request','system') DEFAULT 'text',
			attachments longtext,
			is_read tinyint(1) DEFAULT 0,
			read_at datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_sender (sender_id),
			KEY idx_unread (recipient_id, is_read)
		) {$charset_collate};";
		dbDelta( $sql_conversations );

		// Deliveries table.
		$table_deliveries = $wpdb->prefix . 'wpss_deliveries';
		$sql_deliveries   = "CREATE TABLE {$table_deliveries} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			vendor_id bigint(20) UNSIGNED NOT NULL,
			message text,
			attachments longtext,
			version int(11) DEFAULT 1,
			status enum('pending','accepted','rejected','revision_requested') DEFAULT 'pending',
			response_message text,
			responded_at datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id)
		) {$charset_collate};";
		dbDelta( $sql_deliveries );

		// Reviews table.
		$table_reviews = $wpdb->prefix . 'wpss_reviews';
		$sql_reviews   = "CREATE TABLE {$table_reviews} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			reviewer_id bigint(20) UNSIGNED NOT NULL,
			reviewee_id bigint(20) UNSIGNED NOT NULL,
			service_id bigint(20) UNSIGNED NOT NULL,
			rating tinyint(3) UNSIGNED NOT NULL,
			review text,
			review_type enum('customer_to_vendor','vendor_to_customer'),
			communication_rating tinyint(3) UNSIGNED,
			quality_rating tinyint(3) UNSIGNED,
			delivery_rating tinyint(3) UNSIGNED,
			is_public tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_reviewee (reviewee_id),
			KEY idx_service (service_id)
		) {$charset_collate};";
		dbDelta( $sql_reviews );

		// Disputes table.
		$table_disputes = $wpdb->prefix . 'wpss_disputes';
		$sql_disputes   = "CREATE TABLE {$table_disputes} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			initiated_by bigint(20) UNSIGNED NOT NULL,
			reason varchar(100) NOT NULL,
			description text NOT NULL,
			evidence longtext,
			status enum('open','under_review','resolved','escalated','closed') DEFAULT 'open',
			resolution enum('refund_full','refund_partial','complete_order','cancelled','dismissed'),
			resolution_notes text,
			resolved_by bigint(20) UNSIGNED,
			resolved_at datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_status (status)
		) {$charset_collate};";
		dbDelta( $sql_disputes );

		// Notifications table.
		$table_notifications = $wpdb->prefix . 'wpss_notifications';
		$sql_notifications   = "CREATE TABLE {$table_notifications} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			type varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			message text,
			data longtext,
			action_url varchar(255),
			is_read tinyint(1) DEFAULT 0,
			read_at datetime,
			is_email_sent tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_unread (user_id, is_read),
			KEY idx_type (type)
		) {$charset_collate};";
		dbDelta( $sql_notifications );

		// Update schema version.
		update_option( 'wpss_schema_version', self::SCHEMA_VERSION );
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
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules(): void {
		// Set flag to flush rules on next init.
		set_transient( 'wpss_flush_rewrite_rules', true, 60 );
	}
}
