<?php
/**
 * Settings Class
 *
 * Handles plugin settings registration and rendering.
 *
 * @package WPSellServices\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Settings tabs (lazy-initialized to avoid early __() calls).
	 *
	 * @var array<string, string>|null
	 */
	private ?array $tabs = null;

	/**
	 * Tab groups for visual organization (lazy-initialized).
	 *
	 * @var array<string, array>|null
	 */
	private ?array $tab_groups = null;

	/**
	 * Whether the unified section styles have been rendered.
	 *
	 * @var bool
	 */
	private static bool $sections_styles_rendered = false;

	/**
	 * Whether the unified section script has been rendered.
	 *
	 * @var bool
	 */
	private static bool $sections_script_rendered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Tabs and groups are lazy-initialized in init_tabs() to avoid
		// calling __() before the 'init' action (WP 6.7+ compat).

		// Reschedule auto-withdrawal cron when payouts settings are saved.
		add_action( 'update_option_wpss_payouts', array( $this, 'reschedule_auto_withdrawal_cron' ) );
		add_action( 'add_option_wpss_payouts', array( $this, 'reschedule_auto_withdrawal_cron' ) );
	}

	/**
	 * Reschedule auto-withdrawal cron when payouts settings change.
	 *
	 * Fired after the wpss_payouts option is updated or first added,
	 * so that toggling the setting or changing the schedule takes
	 * effect immediately without requiring plugin reactivation.
	 *
	 * @return void
	 */
	public function reschedule_auto_withdrawal_cron(): void {
		\WPSellServices\Services\EarningsService::schedule_auto_withdrawal_cron();
	}

	/**
	 * Initialize tabs and groups on first access.
	 *
	 * @return void
	 */
	private function init_tabs(): void {
		if ( null !== $this->tabs ) {
			return;
		}

		$this->tabs = array(
			// Setup.
			'general'  => __( 'General', 'wp-sell-services' ),
			'pages'    => __( 'Pages', 'wp-sell-services' ),
			// Business.
			'payments' => __( 'Payments', 'wp-sell-services' ),
			'gateways' => __( 'Gateways', 'wp-sell-services' ),
			'vendor'   => __( 'Vendor', 'wp-sell-services' ),
			// Operations.
			'orders'   => __( 'Orders', 'wp-sell-services' ),
			'emails'   => __( 'Emails', 'wp-sell-services' ),
			// System (Pro tabs inserted before this via filter).
			'advanced' => __( 'Advanced', 'wp-sell-services' ),
		);

		$this->tab_groups = array(
			'setup'      => array( 'general', 'pages' ),
			'business'   => array( 'payments', 'gateways', 'vendor' ),
			'operations' => array( 'orders', 'emails' ),
			'pro'        => array(), // Pro tabs added via filter.
			'system'     => array( 'advanced' ),
		);
	}

	/**
	 * Get tabs organized by groups.
	 *
	 * Maps all registered tabs to their groups for visual separation.
	 * Pro tabs are auto-detected and placed in the 'pro' group.
	 *
	 * @return array<string, array<string, string>> Grouped tabs.
	 */
	private function get_grouped_tabs(): array {
		$this->init_tabs();

		$core_tabs = array(
			'general',
			'pages',
			'payments',
			'gateways',
			'vendor',
			'orders',
			'emails',
			'advanced',
		);

		$grouped = array(
			'setup'      => array(),
			'business'   => array(),
			'operations' => array(),
			'pro'        => array(),
			'system'     => array(),
		);

		// Map tabs to their groups.
		foreach ( $this->tabs as $tab_key => $tab_label ) {
			// Check which group this tab belongs to.
			$placed = false;
			foreach ( $this->tab_groups as $group_name => $group_tabs ) {
				if ( in_array( $tab_key, $group_tabs, true ) ) {
					$grouped[ $group_name ][ $tab_key ] = $tab_label;
					$placed                             = true;
					break;
				}
			}

			// If not in predefined groups and not a core tab, it's a Pro/extension tab.
			if ( ! $placed && ! in_array( $tab_key, $core_tabs, true ) ) {
				$grouped['pro'][ $tab_key ] = $tab_label;
			}
		}

		return $grouped;
	}

	/**
	 * Render tab styles for visual grouping.
	 *
	 * @return void
	 */
	private function render_tab_styles(): void {
		?>
		<style>
			.wpss-nav-tabs {
				display: flex;
				flex-wrap: wrap;
				gap: 0;
				margin-bottom: 20px;
			}
			.wpss-nav-tabs .nav-tab {
				margin-left: 0;
				margin-right: 0;
			}
			.wpss-tab-separator {
				display: inline-block;
				width: 1px;
				height: 28px;
				background: #c3c4c7;
				margin: 0 8px;
				vertical-align: bottom;
			}
			.wpss-nav-tabs .nav-tab:first-child {
				margin-left: 0;
			}
		</style>
		<?php
	}

	/**
	 * Initialize settings.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_tabs();

		/**
		 * Filter the settings tabs.
		 *
		 * @since 1.0.0
		 *
		 * @param array $tabs Settings tabs (slug => label).
		 */
		$this->tabs = apply_filters( 'wpss_settings_tabs', $this->tabs );

		$this->register_settings();
		add_action( 'wp_ajax_wpss_create_page', array( $this, 'ajax_create_page' ) );
		add_action( 'wp_ajax_wpss_send_test_email', array( $this, 'ajax_send_test_email' ) );
	}

	/**
	 * AJAX handler to create a page.
	 *
	 * @return void
	 */
	public function ajax_create_page(): void {
		check_ajax_referer( 'wpss_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$field = sanitize_key( $_POST['field'] ?? '' );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );

		if ( ! $field || ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Missing required data.', 'wp-sell-services' ) ) );
		}

		// Check if a page with this shortcode already exists.
		$page_content     = $this->get_page_content( $field );
		$existing_page_id = $this->find_existing_page( $field, $page_content );

		if ( $existing_page_id ) {
			// Page already exists - update option and return existing page.
			$options           = get_option( 'wpss_pages', array() );
			$options[ $field ] = $existing_page_id;
			update_option( 'wpss_pages', $options );

			wp_send_json_success(
				array(
					'page_id'  => $existing_page_id,
					'title'    => get_the_title( $existing_page_id ),
					'view_url' => get_permalink( $existing_page_id ),
					'edit_url' => get_edit_post_link( $existing_page_id, 'raw' ),
					'existing' => true,
					'message'  => __( 'Existing page found and linked.', 'wp-sell-services' ),
				)
			);
		}

		// Create the page.
		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $page_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( is_wp_error( $page_id ) ) {
			wp_send_json_error( array( 'message' => $page_id->get_error_message() ) );
		}

		// Update the option.
		$options           = get_option( 'wpss_pages', array() );
		$options[ $field ] = $page_id;
		update_option( 'wpss_pages', $options );

		wp_send_json_success(
			array(
				'page_id'  => $page_id,
				'title'    => $title,
				'view_url' => get_permalink( $page_id ),
				'edit_url' => get_edit_post_link( $page_id, 'raw' ),
			)
		);
	}

	/**
	 * AJAX handler to send a test email.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function ajax_send_test_email(): void {
		check_ajax_referer( 'wpss_test_email', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$admin_email = get_option( 'admin_email' );
		$site_name   = wpss_get_platform_name();

		$result = wp_mail(
			$admin_email,
			/* translators: %s: site/platform name */
			sprintf( __( '[%s] Test Email', 'wp-sell-services' ), $site_name ),
			sprintf(
				/* translators: %s: site/platform name */
				__( 'This is a test email from %s. If you received this, email notifications are working correctly.', 'wp-sell-services' ),
				$site_name
			),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		if ( $result ) {
			wp_send_json_success(
				array(
					/* translators: %s: admin email address */
					'message' => sprintf( __( 'Test email sent to %s', 'wp-sell-services' ), $admin_email ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to send test email. Check your SMTP configuration.', 'wp-sell-services' ),
				)
			);
		}
	}

	/**
	 * Find an existing page with the WPSS shortcode.
	 *
	 * @param string $field        Page field key.
	 * @param string $page_content Expected shortcode content.
	 * @return int|null Page ID if found, null otherwise.
	 */
	private function find_existing_page( string $field, string $page_content ): ?int {
		// First check if we already have a valid page ID stored.
		$options = get_option( 'wpss_pages', array() );
		if ( ! empty( $options[ $field ] ) ) {
			$stored_page = get_post( $options[ $field ] );
			if ( $stored_page && 'page' === $stored_page->post_type && 'trash' !== $stored_page->post_status ) {
				return (int) $stored_page->ID;
			}
		}

		// If no shortcode, skip search.
		if ( empty( $page_content ) ) {
			return null;
		}

		// Search for pages containing this shortcode.
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				's'              => $page_content,
			)
		);

		if ( ! empty( $pages ) ) {
			return (int) $pages[0]->ID;
		}

		return null;
	}

	/**
	 * Get default page content for a page type.
	 *
	 * @param string $field Page field key.
	 * @return string Page content.
	 */
	private function get_page_content( string $field ): string {
		$shortcodes = array(
			'services_page' => '[wpss_services]',
			'dashboard'     => '[wpss_dashboard]',
			'become_vendor' => '[wpss_vendor_registration]',
			'checkout'      => '[wpss_checkout]',
		);

		return $shortcodes[ $field ] ?? '';
	}

	/**
	 * Register all settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// General settings.
		register_setting(
			'wpss_general',
			'wpss_general',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_general_settings' ),
			)
		);

		add_settings_section(
			'wpss_general_section',
			__( 'General Settings', 'wp-sell-services' ),
			array( $this, 'render_general_section' ),
			'wpss_general'
		);

		add_settings_field(
			'platform_name',
			__( 'Platform Name', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_general',
			'wpss_general_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'platform_name',
				'description' => __( 'Name displayed to users.', 'wp-sell-services' ),
				'default'     => get_bloginfo( 'name' ),
			)
		);

		add_settings_field(
			'currency',
			__( 'Currency', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_general',
			'wpss_general_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'currency',
				'options'     => $this->get_currencies(),
				'default'     => 'USD',
			)
		);

		// E-commerce integration section.
		add_settings_section(
			'wpss_ecommerce_section',
			__( 'E-Commerce Integration', 'wp-sell-services' ),
			array( $this, 'render_ecommerce_section' ),
			'wpss_general'
		);

		add_settings_field(
			'ecommerce_platform',
			__( 'E-Commerce Platform', 'wp-sell-services' ),
			array( $this, 'render_ecommerce_platform_field' ),
			'wpss_general',
			'wpss_ecommerce_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'ecommerce_platform',
			)
		);

		// Commission settings.
		register_setting(
			'wpss_commission',
			'wpss_commission',
			array( $this, 'sanitize_commission_settings' )
		);

		add_settings_section(
			'wpss_commission_section',
			__( 'Platform Commission', 'wp-sell-services' ),
			array( $this, 'render_commission_section' ),
			'wpss_commission'
		);

		add_settings_field(
			'commission_rate',
			__( 'Commission Rate (%)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_commission',
			'wpss_commission_section',
			array(
				'option_name' => 'wpss_commission',
				'field'       => 'commission_rate',
				'min'         => 0,
				'max'         => 50,
				'step'        => 0.1,
				'default'     => 10,
				'description' => __( 'Default percentage deducted from vendor earnings for all orders.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'enable_vendor_rates',
			__( 'Per-Vendor Rates', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_commission',
			'wpss_commission_section',
			array(
				'option_name' => 'wpss_commission',
				'field'       => 'enable_vendor_rates',
				'label'       => __( 'Allow custom commission rates per vendor (configured in vendor profile)', 'wp-sell-services' ),
				'default'     => true,
			)
		);

		// Payouts settings.
		register_setting(
			'wpss_payouts',
			'wpss_payouts',
			array( $this, 'sanitize_payouts_settings' )
		);

		add_settings_section(
			'wpss_payouts_section',
			__( 'Withdrawal Settings', 'wp-sell-services' ),
			array( $this, 'render_payouts_section' ),
			'wpss_payouts'
		);

		add_settings_field(
			'min_withdrawal',
			__( 'Minimum Withdrawal', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_payouts',
			'wpss_payouts_section',
			array(
				'option_name' => 'wpss_payouts',
				'field'       => 'min_withdrawal',
				'min'         => 0,
				'max'         => 1000,
				'step'        => 1,
				'default'     => 50,
				'description' => __( 'Minimum amount vendors must have before requesting withdrawal.', 'wp-sell-services' ),
			)
		);

		// Note: clearance_days is stored but not yet enforced by EarningsService.
		// Reserved for future implementation — do not remove this setting.
		add_settings_field(
			'clearance_days',
			__( 'Clearance Period (Days)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_payouts',
			'wpss_payouts_section',
			array(
				'option_name' => 'wpss_payouts',
				'field'       => 'clearance_days',
				'min'         => 0,
				'max'         => 90,
				'step'        => 1,
				'default'     => 14,
				'description' => __( 'Days after order completion before earnings become available for withdrawal.', 'wp-sell-services' ),
			)
		);

		add_settings_section(
			'wpss_auto_withdrawal_section',
			__( 'Automatic Withdrawals', 'wp-sell-services' ),
			array( $this, 'render_auto_withdrawal_section' ),
			'wpss_payouts'
		);

		add_settings_field(
			'auto_withdrawal_enabled',
			__( 'Enable Auto-Withdrawal', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_payouts',
			'wpss_auto_withdrawal_section',
			array(
				'option_name' => 'wpss_payouts',
				'field'       => 'auto_withdrawal_enabled',
				'label'       => __( 'Automatically process withdrawals for high-earning vendors', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'auto_withdrawal_threshold',
			__( 'Auto-Withdrawal Threshold', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_payouts',
			'wpss_auto_withdrawal_section',
			array(
				'option_name' => 'wpss_payouts',
				'field'       => 'auto_withdrawal_threshold',
				'min'         => 100,
				'max'         => 10000,
				'step'        => 50,
				'default'     => 500,
				'description' => __( 'Minimum available balance to trigger automatic withdrawal.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'auto_withdrawal_schedule',
			__( 'Auto-Withdrawal Schedule', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_payouts',
			'wpss_auto_withdrawal_section',
			array(
				'option_name' => 'wpss_payouts',
				'field'       => 'auto_withdrawal_schedule',
				'options'     => array(
					'weekly'   => __( 'Weekly (every Monday)', 'wp-sell-services' ),
					'biweekly' => __( 'Bi-weekly (1st and 15th)', 'wp-sell-services' ),
					'monthly'  => __( 'Monthly (1st of month)', 'wp-sell-services' ),
				),
				'default'     => 'monthly',
				'description' => __( 'When automatic withdrawals are processed.', 'wp-sell-services' ),
			)
		);

		// Tax settings.
		register_setting(
			'wpss_tax',
			'wpss_tax',
			array( $this, 'sanitize_tax_settings' )
		);

		add_settings_section(
			'wpss_tax_section',
			__( 'Tax Configuration', 'wp-sell-services' ),
			array( $this, 'render_tax_section' ),
			'wpss_tax'
		);

		add_settings_field(
			'enable_tax',
			__( 'Enable Tax', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_tax',
			'wpss_tax_section',
			array(
				'option_name' => 'wpss_tax',
				'field'       => 'enable_tax',
				'label'       => __( 'Enable tax calculation on service orders', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'tax_label',
			__( 'Tax Label', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_tax',
			'wpss_tax_section',
			array(
				'option_name' => 'wpss_tax',
				'field'       => 'tax_label',
				'default'     => __( 'Tax', 'wp-sell-services' ),
				'description' => __( 'Label displayed to customers (e.g., VAT, GST, Sales Tax).', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'tax_rate',
			__( 'Tax Rate (%)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_tax',
			'wpss_tax_section',
			array(
				'option_name' => 'wpss_tax',
				'field'       => 'tax_rate',
				'min'         => 0,
				'max'         => 50,
				'step'        => 0.01,
				'default'     => 0,
				'description' => __( 'Default tax rate applied to all services.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'tax_included',
			__( 'Prices Include Tax', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_tax',
			'wpss_tax_section',
			array(
				'option_name' => 'wpss_tax',
				'field'       => 'tax_included',
				'label'       => __( 'Service prices already include tax (display tax as part of price)', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		// Vendor settings.
		register_setting(
			'wpss_vendor',
			'wpss_vendor',
			array( $this, 'sanitize_vendor_settings' )
		);

		add_settings_section(
			'wpss_vendor_section',
			__( 'Vendor Settings', 'wp-sell-services' ),
			array( $this, 'render_vendor_section' ),
			'wpss_vendor'
		);

		add_settings_field(
			'vendor_registration',
			__( 'Vendor Registration', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'vendor_registration',
				'options'     => array(
					'open'     => __( 'Open (anyone can register)', 'wp-sell-services' ),
					'approval' => __( 'Requires Approval', 'wp-sell-services' ),
					'closed'   => __( 'Closed (admin only)', 'wp-sell-services' ),
				),
				'default'     => 'open',
			)
		);

		add_settings_field(
			'max_services_per_vendor',
			__( 'Max Services per Vendor', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'max_services_per_vendor',
				'min'         => 0,
				'max'         => 100,
				'default'     => 20,
				'description' => __( '0 for unlimited.', 'wp-sell-services' ),
			)
		);

		// Vendor verification is not yet implemented — setting removed to avoid confusion.

		add_settings_field(
			'require_service_moderation',
			__( 'Service Moderation', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'require_service_moderation',
				'label'       => __( 'Require admin approval before services are published', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		// Order settings.
		register_setting(
			'wpss_orders',
			'wpss_orders',
			array( $this, 'sanitize_order_settings' )
		);

		add_settings_section(
			'wpss_orders_section',
			__( 'Order Settings', 'wp-sell-services' ),
			array( $this, 'render_orders_section' ),
			'wpss_orders'
		);

		add_settings_field(
			'auto_complete_days',
			__( 'Auto-Complete Days', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'auto_complete_days',
				'min'         => 0,
				'max'         => 30,
				'default'     => 3,
				'description' => __( 'Days after delivery to auto-complete if buyer does not respond. 0 to disable.', 'wp-sell-services' ),
			)
		);

		// Revision limits are defined per-package in service packages, not as a global setting.

		add_settings_field(
			'allow_disputes',
			__( 'Allow Disputes', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'allow_disputes',
				'label'       => __( 'Allow buyers to open disputes on orders', 'wp-sell-services' ),
				'default'     => true,
			)
		);

		add_settings_field(
			'dispute_window_days',
			__( 'Dispute Window (Days)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'dispute_window_days',
				'min'         => 1,
				'max'         => 90,
				'default'     => 14,
				'description' => __( 'Days after completion within which disputes can be opened.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'auto_dispute_late_days',
			__( 'Auto-Dispute Late Orders (Days)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'auto_dispute_late_days',
				'min'         => 0,
				'max'         => 30,
				'default'     => 3,
				'description' => __( 'Auto-open a dispute when an order is late for this many days. Set to 0 to disable.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'allow_late_requirements',
			__( 'Late Requirements Submission', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'allow_late_requirements',
				'label'       => __( 'Allow buyers to submit requirements after work has started', 'wp-sell-services' ),
				'default'     => false,
				'description' => __( 'If enabled, buyers can submit requirements even if the order is already in progress without them.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'requirements_timeout_days',
			__( 'Requirements Timeout (Days)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'requirements_timeout_days',
				'min'         => 0,
				'max'         => 30,
				'default'     => 0,
				'description' => __( 'Days to wait for requirements before taking action. 0 to disable.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'auto_start_on_timeout',
			__( 'Auto-Start on Timeout', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'auto_start_on_timeout',
				'label'       => __( 'Auto-start order when requirements timeout is reached', 'wp-sell-services' ),
				'default'     => true,
				'description' => __( 'If enabled, the order starts without requirements. If disabled, the order is cancelled instead.', 'wp-sell-services' ),
			)
		);

		// Notification settings.
		register_setting(
			'wpss_notifications',
			'wpss_notifications',
			array( $this, 'sanitize_notification_settings' )
		);

		add_settings_section(
			'wpss_notifications_section',
			__( 'Email Notifications', 'wp-sell-services' ),
			array( $this, 'render_notifications_section' ),
			'wpss_notifications'
		);

		/**
		 * Filter notification types shown in email settings.
		 *
		 * @since 1.1.0
		 *
		 * @param array $types Associative array of notification_key => label.
		 */
		$notification_types = apply_filters(
			'wpss_notification_types',
			array(
				'new_order'              => __( 'New Order', 'wp-sell-services' ),
				'order_completed'        => __( 'Order Completed', 'wp-sell-services' ),
				'order_cancelled'        => __( 'Order Cancelled', 'wp-sell-services' ),
				'cancellation_requested' => __( 'Cancellation Requested', 'wp-sell-services' ),
				'delivery_submitted'     => __( 'Delivery Submitted', 'wp-sell-services' ),
				'revision_requested'     => __( 'Revision Requested', 'wp-sell-services' ),
				'new_message'            => __( 'New Message', 'wp-sell-services' ),
				'vendor_contact'         => __( 'Vendor Direct Message', 'wp-sell-services' ),
				'new_review'             => __( 'New Review', 'wp-sell-services' ),
				'dispute_opened'         => __( 'Dispute Opened', 'wp-sell-services' ),
				'withdrawal_requested'   => __( 'Withdrawal Requested', 'wp-sell-services' ),
				'withdrawal_approved'    => __( 'Withdrawal Approved', 'wp-sell-services' ),
				'withdrawal_rejected'    => __( 'Withdrawal Rejected', 'wp-sell-services' ),
				'proposal_submitted'     => __( 'Proposal Submitted', 'wp-sell-services' ),
				'proposal_accepted'      => __( 'Proposal Accepted', 'wp-sell-services' ),
			)
		);

		foreach ( $notification_types as $key => $label ) {
			add_settings_field(
				'notify_' . $key,
				$label,
				array( $this, 'render_checkbox_field' ),
				'wpss_notifications',
				'wpss_notifications_section',
				array(
					'option_name' => 'wpss_notifications',
					'field'       => 'notify_' . $key,
					'label'       => sprintf(
						/* translators: %s: notification type */
						__( 'Send email for %s', 'wp-sell-services' ),
						strtolower( $label )
					),
					'default'     => true,
				)
			);
		}

		// Pages settings.
		register_setting(
			'wpss_pages',
			'wpss_pages',
			array( $this, 'sanitize_pages_settings' )
		);

		add_settings_section(
			'wpss_pages_section',
			__( 'Page Settings', 'wp-sell-services' ),
			array( $this, 'render_pages_section' ),
			'wpss_pages'
		);

		$pages = array(
			'services_page' => __( 'Services Page', 'wp-sell-services' ),
			'dashboard'     => __( 'Dashboard', 'wp-sell-services' ),
			'checkout'      => __( 'Service Checkout', 'wp-sell-services' ),
		);

		// Only show "Become a Vendor" page option when vendor registration is not closed.
		$pages_vendor_settings   = get_option( 'wpss_vendor', array() );
		$pages_registration_mode = $pages_vendor_settings['vendor_registration'] ?? 'open';
		if ( 'closed' !== $pages_registration_mode ) {
			// Insert after 'dashboard' to maintain original order.
			$pages = array_slice( $pages, 0, 2, true )
				+ array( 'become_vendor' => __( 'Become a Vendor', 'wp-sell-services' ) )
				+ array_slice( $pages, 2, null, true );
		}

		foreach ( $pages as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_page_select_field' ),
				'wpss_pages',
				'wpss_pages_section',
				array(
					'option_name' => 'wpss_pages',
					'field'       => $key,
					'page_title'  => $label,
				)
			);
		}

		// Advanced settings.
		register_setting(
			'wpss_advanced',
			'wpss_advanced',
			array( $this, 'sanitize_advanced_settings' )
		);

		add_settings_section(
			'wpss_advanced_section',
			__( 'Advanced Settings', 'wp-sell-services' ),
			array( $this, 'render_advanced_section' ),
			'wpss_advanced'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete Data on Uninstall', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'delete_data_on_uninstall',
				'label'       => __( 'Delete all plugin data when uninstalling', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'enable_debug_mode',
			__( 'Debug Mode', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'enable_debug_mode',
				'label'       => __( 'Enable debug logging', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'max_file_size',
			__( 'Max File Upload Size (MB)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'max_file_size',
				'default'     => 10,
				'min'         => 1,
				'max'         => 100,
				'step'        => 1,
				'description' => __( 'Maximum file size in megabytes for uploads.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'allowed_file_types',
			__( 'Allowed File Types', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'allowed_file_types',
				'default'     => 'jpg,jpeg,png,gif,pdf,doc,docx',
				'description' => __( 'Comma-separated list of allowed file extensions.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'currency_position',
			__( 'Currency Symbol Position', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'currency_position',
				'default'     => 'before',
				'options'     => array(
					'before' => __( 'Before amount ($99)', 'wp-sell-services' ),
					'after'  => __( 'After amount (99$)', 'wp-sell-services' ),
				),
				'description' => __( 'Position of the currency symbol relative to the amount.', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->init_tabs();

		if ( ! array_key_exists( $active_tab, $this->tabs ) ) {
			$active_tab = 'general';
		}

		// Build grouped tabs for rendering.
		$grouped_tabs = $this->get_grouped_tabs();

		?>
		<div class="wrap wpss-settings">
			<h1><?php echo esc_html__( 'WP Sell Services Settings', 'wp-sell-services' ); ?></h1>

			<?php $this->render_tab_styles(); ?>

			<nav class="nav-tab-wrapper wpss-nav-tabs">
				<?php
				$group_index = 0;
				foreach ( $grouped_tabs as $group_name => $group_tabs ) :
					if ( empty( $group_tabs ) ) {
						continue;
					}
					++$group_index;
					?>
					<?php if ( $group_index > 1 ) : ?>
						<span class="wpss-tab-separator"></span>
					<?php endif; ?>
					<?php foreach ( $group_tabs as $tab_key => $tab_label ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings&tab=' . $tab_key ) ); ?>"
							class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
							data-group="<?php echo esc_attr( $group_name ); ?>">
							<?php echo esc_html( $tab_label ); ?>
						</a>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</nav>

			<?php
			// Always output unified section styles so Pro/extension tabs have them available.
			$this->render_tab_sections_styles();
			?>

			<?php
			// Check if this is a Pro/extension tab (not a core tab).
			$core_tabs = array( 'general', 'payments', 'gateways', 'vendor', 'orders', 'emails', 'pages', 'advanced' );

			if ( ! in_array( $active_tab, $core_tabs, true ) ) {
				/**
				 * Fires when rendering a custom settings tab added by Pro or extensions.
				 *
				 * @since 1.2.0
				 *
				 * @param string $active_tab The active tab slug.
				 */
				do_action( 'wpss_settings_tab_' . $active_tab );
			} elseif ( 'payments' === $active_tab ) {
				$this->render_payments_tab();
			} elseif ( 'gateways' === $active_tab ) {
				$this->render_gateways_tab();
			} elseif ( 'advanced' === $active_tab ) {
				$this->render_advanced_tab();
			} elseif ( 'vendor' === $active_tab ) {
				$this->render_vendor_tab();
			} elseif ( 'orders' === $active_tab ) {
				$this->render_orders_tab();
			} elseif ( 'emails' === $active_tab ) {
				$this->render_emails_tab();
			} else {
				// Standard tabs with single option group (general, pages).
				?>
				<form method="post" action="options.php">
					<?php
					switch ( $active_tab ) {
						case 'pages':
							settings_fields( 'wpss_pages' );
							do_settings_sections( 'wpss_pages' );
							break;

						default:
							settings_fields( 'wpss_general' );
							do_settings_sections( 'wpss_general' );
							break;
					}

					submit_button();
					?>
				</form>
				<?php
			}
			?>

			<?php if ( 'pages' === $active_tab ) : ?>
			<script>
			function wpssAdminNotice(msg, type) {
				type = type || 'error';
				var cls = type === 'success' ? 'notice-success' : 'notice-error';
				var $notice = jQuery('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
				jQuery('.wrap h1, .wrap h2').first().after($notice);
				$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
				setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
			}
			jQuery(function($) {
				var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				var nonce = '<?php echo esc_js( wp_create_nonce( 'wpss_settings_nonce' ) ); ?>';

				$('.wpss-create-page').on('click', function() {
					var $btn = $(this);
					var field = $btn.data('field');
					var title = $btn.data('title');
					var $wrap = $btn.closest('.wpss-page-select-wrap');
					var $select = $wrap.find('select');
					var $viewBtn = $wrap.find('.wpss-view-page');

					if (!title) {
						wpssAdminNotice('<?php echo esc_js( __( 'Page title not defined.', 'wp-sell-services' ) ); ?>', 'error');
						return;
					}

					if (!confirm('<?php echo esc_js( __( 'Create a new page titled', 'wp-sell-services' ) ); ?> "' + title + '"?')) {
						return;
					}

					$btn.addClass('creating').text('<?php echo esc_js( __( 'Creating...', 'wp-sell-services' ) ); ?>');

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_create_page',
							nonce: nonce,
							field: field,
							title: title
						},
						success: function(response) {
							if (response.success) {
								// Check if existing page was linked vs new created.
								var isExisting = response.data.existing || false;
								var successMsg = isExisting
									? '<?php echo esc_js( __( 'Existing Page Linked!', 'wp-sell-services' ) ); ?>'
									: '<?php echo esc_js( __( 'Page Created!', 'wp-sell-services' ) ); ?>';

								// Add new option if not already present, then select it.
								if ($select.find('option[value="' + response.data.page_id + '"]').length === 0) {
									$select.append('<option value="' + response.data.page_id + '">' + response.data.title + '</option>');
								}
								$select.val(response.data.page_id);

								// Show and update view link
								$viewBtn.attr('href', response.data.view_url).show();

								// Change button to success state with appropriate message.
								$btn.removeClass('creating').text(successMsg).addClass('button-primary');

								setTimeout(function() {
									$btn.removeClass('button-primary').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
								}, 2000);
							} else {
								wpssAdminNotice(response.data.message || '<?php echo esc_js( __( 'Failed to create page.', 'wp-sell-services' ) ); ?>', 'error');
								$btn.removeClass('creating').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
							}
						},
						error: function() {
							wpssAdminNotice('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>', 'error');
							$btn.removeClass('creating').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
						}
					});
				});

				// Update view link when dropdown changes
				$('.wpss-page-dropdown').on('change', function() {
					var $select = $(this);
					var pageId = $select.val();
					var $viewBtn = $select.closest('.wpss-page-select-wrap').find('.wpss-view-page');

					if (pageId) {
						// Construct view URL - we'll just reload to get proper URL
						$viewBtn.attr('href', '<?php echo esc_url( home_url( '/' ) ); ?>?page_id=' + pageId).show();
					} else {
						$viewBtn.hide();
					}
				});
			});
			</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Payments tab with collapsible sections.
	 *
	 * Combines Commission, Tax, and Payouts settings into one tab
	 * with expandable accordion sections.
	 *
	 * @return void
	 */
	private function render_payments_tab(): void {
		$this->render_tab_sections(
			'payments',
			array(
				array(
					'id'           => 'commission',
					'title'        => __( 'Commission Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure the platform commission deducted from vendor earnings.', 'wp-sell-services' ),
					'option_group' => 'wpss_commission',
					'settings_id'  => 'wpss_commission',
				),
				array(
					'id'           => 'tax',
					'title'        => __( 'Tax Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure tax calculation for services.', 'wp-sell-services' ),
					'option_group' => 'wpss_tax',
					'settings_id'  => 'wpss_tax',
				),
				array(
					'id'           => 'payouts',
					'title'        => __( 'Payout Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure vendor withdrawal and payout settings.', 'wp-sell-services' ),
					'option_group' => 'wpss_payouts',
					'settings_id'  => 'wpss_payouts',
				),
			)
		);
	}

	/**
	 * Render the Gateways tab with accordion sections.
	 *
	 * Consolidates Stripe, PayPal, and Offline payment gateway settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function render_gateways_tab(): void {
		?>
		<style>
			.wpss-gateways-accordion {
				margin-top: 20px;
			}
			.wpss-accordion-section {
				border: 1px solid #e5e5e5;
				border-radius: 4px;
				margin-bottom: 10px;
				background: #fff;
			}
			.wpss-accordion-header {
				display: flex;
				align-items: center;
				padding: 15px 20px;
				cursor: pointer;
				background: #f9f9f9;
				border-bottom: 1px solid transparent;
				transition: background-color 0.2s;
			}
			.wpss-accordion-header:hover {
				background: #f0f0f0;
			}
			.wpss-accordion-section.open .wpss-accordion-header {
				border-bottom-color: #e5e5e5;
			}
			.wpss-accordion-icon {
				margin-right: 10px;
				transition: transform 0.2s;
			}
			.wpss-accordion-section.open .wpss-accordion-icon {
				transform: rotate(90deg);
			}
			.wpss-accordion-header h3 {
				margin: 0;
				flex: 1;
				font-size: 14px;
			}
			.wpss-gateway-status {
				font-size: 12px;
				padding: 3px 10px;
				border-radius: 3px;
			}
			.wpss-gateway-enabled {
				background: #d4edda;
				color: #155724;
			}
			.wpss-gateway-disabled {
				background: #f8d7da;
				color: #721c24;
			}
			.wpss-accordion-content {
				display: none;
				padding: 20px;
			}
			.wpss-accordion-section.open .wpss-accordion-content {
				display: block;
			}
			.wpss-accordion-content .form-table {
				margin-top: 10px;
			}
		</style>

		<div class="wpss-settings-section">
			<h2><?php esc_html_e( 'Payment Gateways', 'wp-sell-services' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure payment gateways for your marketplace checkout.', 'wp-sell-services' ); ?>
			</p>

			<div class="wpss-gateways-accordion">
				<?php
				// Test Gateway section (only in debug mode).
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->render_gateway_accordion_section(
						'test',
						__( 'Test Gateway', 'wp-sell-services' ) . ' <span style="background:#fff3cd;color:#856404;padding:2px 6px;border-radius:3px;font-size:11px;margin-left:8px;">' . esc_html__( 'Dev Only', 'wp-sell-services' ) . '</span>',
						__( 'Test payment gateway for development. No real charges.', 'wp-sell-services' ),
						'wpss_test_gateway_settings'
					);
				}

				// Stripe section.
				$this->render_gateway_accordion_section(
					'stripe',
					__( 'Stripe', 'wp-sell-services' ),
					__( 'Accept credit card payments via Stripe.', 'wp-sell-services' ),
					'wpss_stripe_settings'
				);

				// PayPal section.
				$this->render_gateway_accordion_section(
					'paypal',
					__( 'PayPal', 'wp-sell-services' ),
					__( 'Accept payments via PayPal.', 'wp-sell-services' ),
					'wpss_paypal_settings'
				);

				/**
				 * Fires after core gateways, before Offline.
				 *
				 * Pro uses this to add Razorpay and other gateways.
				 *
				 * @since 1.1.0
				 */
				do_action( 'wpss_gateway_accordion_sections', $this );

				/**
				 * Unified gateway sections hook.
				 *
				 * Fires alongside the legacy wpss_gateway_accordion_sections hook.
				 * Pro and extensions can use either hook to inject gateway settings.
				 *
				 * @since 1.3.0
				 */
				do_action( 'wpss_settings_sections_gateways' );

				// Offline section.
				$this->render_gateway_accordion_section(
					'offline',
					__( 'Offline Payment', 'wp-sell-services' ),
					__( 'Accept bank transfer, cash, and other manual payments.', 'wp-sell-services' ),
					'wpss_offline_settings'
				);
				?>
			</div>
		</div>

		<script>
		jQuery(function($) {
			$('.wpss-accordion-header').on('click', function() {
				var $section = $(this).closest('.wpss-accordion-section');
				$section.toggleClass('open');
			});
		});
		</script>
		<?php
	}

	/**
	 * Render a single gateway accordion section.
	 *
	 * @since 1.1.0
	 *
	 * @param string $gateway_id   Gateway identifier.
	 * @param string $title        Gateway title (may contain HTML).
	 * @param string $description  Gateway description.
	 * @param string $option_group Option group for settings_fields().
	 * @return void
	 */
	public function render_gateway_accordion_section( string $gateway_id, string $title, string $description, string $option_group ): void {
		$plugin  = \WPSellServices\Core\Plugin::get_instance();
		$gateway = $plugin->get_payment_gateway( $gateway_id );
		$enabled = $gateway && $gateway->is_enabled();

		$status_class = $enabled ? 'wpss-gateway-enabled' : 'wpss-gateway-disabled';
		$status_text  = $enabled ? __( 'Enabled', 'wp-sell-services' ) : __( 'Disabled', 'wp-sell-services' );
		?>
		<div class="wpss-accordion-section" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
			<div class="wpss-accordion-header">
				<span class="wpss-accordion-icon dashicons dashicons-arrow-right"></span>
				<h3><?php echo wp_kses_post( $title ); ?></h3>
				<span class="wpss-gateway-status <?php echo esc_attr( $status_class ); ?>">
					<?php echo esc_html( $status_text ); ?>
				</span>
			</div>
			<div class="wpss-accordion-content">
				<p class="description"><?php echo esc_html( $description ); ?></p>
				<form method="post" action="options.php">
					<?php
					settings_fields( $option_group );
					/**
					 * Hook to render gateway-specific settings fields.
					 *
					 * @since 1.0.0
					 */
					do_action( "wpss_gateway_settings_{$gateway_id}" );
					submit_button( __( 'Save Changes', 'wp-sell-services' ) );
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Advanced tab with collapsible sections.
	 *
	 * Uses accordion pattern for consistency with Pro sections.
	 *
	 * @return void
	 */
	private function render_advanced_tab(): void {
		$this->render_tab_sections(
			'advanced',
			array(
				array(
					'id'           => 'system',
					'title'        => __( 'System Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure advanced system options.', 'wp-sell-services' ),
					'option_group' => 'wpss_advanced',
					'settings_id'  => 'wpss_advanced',
				),
				array(
					'id'          => 'demo-content',
					'title'       => __( 'Demo Content', 'wp-sell-services' ),
					'description' => __( 'Import sample services, vendors, and categories to preview your marketplace. Demo content can be removed at any time.', 'wp-sell-services' ),
					'collapsed'   => true,
					'callback'    => array( $this, 'render_demo_content_section' ),
				),
				array(
					'id'          => 'setup-wizard',
					'title'       => __( 'Setup Wizard', 'wp-sell-services' ),
					'description' => __( 'Re-run the setup wizard to reconfigure your marketplace settings.', 'wp-sell-services' ),
					'collapsed'   => true,
					'callback'    => array( $this, 'render_setup_wizard_section' ),
				),
			)
		);

		// Backward compatibility: fire legacy hook for third-party extensions.
		do_action( 'wpss_advanced_settings_sections' );

		// Demo content inline JS.
		$this->render_demo_content_script();
	}

	/**
	 * Render general section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure general platform settings.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render e-commerce section description.
	 *
	 * @return void
	 */
	public function render_ecommerce_section(): void {
		echo '<p>' . esc_html__( 'Configure e-commerce platform for service checkout. Standalone checkout is included. Pro adds WooCommerce, EDD, and more.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render commission section description.
	 *
	 * @return void
	 */
	public function render_commission_section(): void {
		echo '<p>' . esc_html__( 'Configure how much commission the platform takes from each order.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render payouts section description.
	 *
	 * @return void
	 */
	public function render_payouts_section(): void {
		echo '<p>' . esc_html__( 'Configure how and when vendors can withdraw their earnings.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render automatic withdrawal section description.
	 *
	 * @return void
	 */
	public function render_auto_withdrawal_section(): void {
		echo '<p>' . esc_html__( 'Configure automatic withdrawals for high-earning vendors. When enabled, the system will automatically create and process withdrawal requests for vendors who meet the threshold.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render tax section description.
	 *
	 * @return void
	 */
	public function render_tax_section(): void {
		echo '<p>' . esc_html__( 'Configure tax settings for service transactions. These settings apply when not using an e-commerce platform that handles its own tax calculations.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render vendor section description.
	 *
	 * @return void
	 */
	public function render_vendor_section(): void {
		echo '<p>' . esc_html__( 'Configure vendor registration and capabilities.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render orders section description.
	 *
	 * @return void
	 */
	public function render_orders_section(): void {
		echo '<p>' . esc_html__( 'Configure order workflow and policies.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render notifications section description.
	 *
	 * @return void
	 */
	public function render_notifications_section(): void {
		echo '<p>' . esc_html__( 'Configure which email notifications are sent.', 'wp-sell-services' ) . '</p>';
		echo '<p class="description">';
		echo esc_html__( 'These toggles are the master switch for each notification type. When a notification is disabled here, no email will be sent regardless of other settings.', 'wp-sell-services' );
		echo '</p>';
	}

	/**
	 * Render pages section description.
	 *
	 * @return void
	 */
	public function render_pages_section(): void {
		echo '<p>' . esc_html__( 'Assign pages for plugin functionality.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 *
	 * @return void
	 */
	public function render_advanced_section(): void {
		echo '<p>' . esc_html__( 'Advanced configuration options.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render accordion sections for a settings tab.
	 *
	 * Provides a unified accordion system for all tabs. Each section gets its
	 * own collapsible panel with an optional form. Fires a hook after all
	 * sections so Pro and extensions can inject additional panels.
	 *
	 * @since 1.3.0
	 *
	 * @param string            $tab_id   Tab identifier (e.g. 'payments', 'vendor').
	 * @param array<int, array> $sections Array of section definitions.
	 * @return void
	 */
	public function render_tab_sections( string $tab_id, array $sections ): void {
		$this->render_tab_sections_styles();

		foreach ( $sections as $section ) {
			$this->render_single_section( $section );
		}

		/**
		 * Fires after core sections are rendered for a tab.
		 *
		 * Pro and extensions use this to inject additional accordion sections
		 * into any settings tab.
		 *
		 * @since 1.3.0
		 *
		 * @param string $tab_id The tab being rendered.
		 */
		do_action( "wpss_settings_sections_{$tab_id}" );

		$this->render_tab_sections_script();
	}

	/**
	 * Render a single accordion section.
	 *
	 * Used by render_tab_sections() and available publicly so Pro renderers
	 * can output sections in the unified format.
	 *
	 * Section definition keys:
	 *   - id           (string) Section identifier for data-section attribute.
	 *   - title        (string) Section heading text.
	 *   - description  (string) Optional description paragraph.
	 *   - option_group (string) Option group for settings_fields(). Omit if using callback.
	 *   - settings_id  (string) Settings ID for do_settings_sections(). Omit if using callback.
	 *   - collapsed    (bool)   Whether section starts collapsed. Default false.
	 *   - callback     (callable) Optional custom render callback (replaces default form).
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $section Section definition.
	 * @return void
	 */
	public function render_single_section( array $section ): void {
		$collapsed     = ! empty( $section['collapsed'] );
		$section_class = 'wpss-settings-section' . ( $collapsed ? ' collapsed' : '' );
		$section_id    = $section['id'] ?? '';
		?>
		<div class="<?php echo esc_attr( $section_class ); ?>" data-section="<?php echo esc_attr( $section_id ); ?>">
			<div class="wpss-settings-section-header">
				<h3><?php echo wp_kses_post( $section['title'] ?? '' ); ?></h3>
				<span class="wpss-settings-section-toggle dashicons dashicons-arrow-down-alt2"></span>
			</div>
			<div class="wpss-settings-section-content">
				<?php if ( ! empty( $section['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $section['description'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $section['callback'] ) ) : ?>
					<?php call_user_func( $section['callback'] ); ?>
				<?php elseif ( ! empty( $section['option_group'] ) ) : ?>
					<form method="post" action="options.php">
						<?php
						settings_fields( $section['option_group'] );
						do_settings_sections( $section['settings_id'] ?? $section['option_group'] );
						submit_button(
							sprintf(
								/* translators: %s: section title */
								__( 'Save %s', 'wp-sell-services' ),
								$section['title'] ?? __( 'Changes', 'wp-sell-services' )
							)
						);
						?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output unified CSS for accordion sections. Rendered once per page load.
	 *
	 * Replaces the three separate CSS blocks previously used by payments,
	 * gateways, and advanced tabs.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function render_tab_sections_styles(): void {
		if ( self::$sections_styles_rendered ) {
			return;
		}
		self::$sections_styles_rendered = true;
		?>
		<style>
			.wpss-settings-section {
				background: #fff;
				border: 1px solid #c3c4c7;
				margin-bottom: 15px;
			}
			.wpss-settings-section-header {
				padding: 12px 15px;
				background: #f6f7f7;
				border-bottom: 1px solid #c3c4c7;
				cursor: pointer;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			.wpss-settings-section-header:hover {
				background: #f0f0f1;
			}
			.wpss-settings-section-header h3 {
				margin: 0;
				font-size: 14px;
				font-weight: 600;
			}
			.wpss-settings-section-toggle {
				font-size: 20px;
				color: #787c82;
				transition: transform 0.2s;
			}
			.wpss-settings-section.collapsed .wpss-settings-section-toggle {
				transform: rotate(-90deg);
			}
			.wpss-settings-section-content {
				padding: 15px;
			}
			.wpss-settings-section.collapsed .wpss-settings-section-content {
				display: none;
			}
			.wpss-settings-section .form-table th {
				width: 200px;
			}
			.wpss-pro-badge {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: #fff;
				font-size: 11px;
				padding: 2px 8px;
				border-radius: 3px;
				font-weight: 500;
			}
		</style>
		<?php
	}

	/**
	 * Output shared accordion toggle JavaScript. Rendered once per page load.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function render_tab_sections_script(): void {
		if ( self::$sections_script_rendered ) {
			return;
		}
		self::$sections_script_rendered = true;
		?>
		<script>
		jQuery(function($) {
			$('.wpss-settings-section-header').on('click', function() {
				$(this).closest('.wpss-settings-section').toggleClass('collapsed');
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the Vendor tab with accordion sections.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function render_vendor_tab(): void {
		$this->render_tab_sections(
			'vendor',
			array(
				array(
					'id'           => 'vendor-settings',
					'title'        => __( 'Vendor Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure vendor registration and capabilities.', 'wp-sell-services' ),
					'option_group' => 'wpss_vendor',
					'settings_id'  => 'wpss_vendor',
				),
			)
		);
	}

	/**
	 * Render the Orders tab with accordion sections.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function render_orders_tab(): void {
		$this->render_tab_sections(
			'orders',
			array(
				array(
					'id'           => 'order-settings',
					'title'        => __( 'Order Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure order workflow and policies.', 'wp-sell-services' ),
					'option_group' => 'wpss_orders',
					'settings_id'  => 'wpss_orders',
				),
			)
		);
	}

	/**
	 * Render the Emails tab with accordion sections.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function render_emails_tab(): void {
		$this->render_tab_sections(
			'emails',
			array(
				array(
					'id'          => 'email-test',
					'title'       => __( 'Email Deliverability', 'wp-sell-services' ),
					'description' => __( 'Verify that your site can send emails. If the test fails, check your SMTP or email sending plugin configuration.', 'wp-sell-services' ),
					'callback'    => array( $this, 'render_test_email_section' ),
				),
				array(
					'id'           => 'email-notifications',
					'title'        => __( 'Email Notifications', 'wp-sell-services' ),
					'description'  => __( 'Configure which email notifications are sent. These toggles are the master switch for each notification type.', 'wp-sell-services' ),
					'option_group' => 'wpss_notifications',
					'settings_id'  => 'wpss_notifications',
				),
			)
		);
	}

	/**
	 * Render the test email section in the Emails tab.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function render_test_email_section(): void {
		$admin_email = get_option( 'admin_email' );
		$nonce       = wp_create_nonce( 'wpss_test_email' );
		?>
		<div class="wpss-test-email-section" style="margin-top: 15px;">
			<p>
				<?php
				printf(
					/* translators: %s: admin email address */
					esc_html__( 'Send a test email to %s to verify email delivery is working.', 'wp-sell-services' ),
					'<strong>' . esc_html( $admin_email ) . '</strong>'
				);
				?>
			</p>
			<button type="button" class="button button-primary wpss-send-test-email" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<?php esc_html_e( 'Send Test Email', 'wp-sell-services' ); ?>
			</button>
			<span class="wpss-test-email-status" style="margin-left: 10px; display: none;"></span>
		</div>
		<script>
		jQuery(function($) {
			$('.wpss-send-test-email').on('click', function() {
				var $btn = $(this);
				var $status = $btn.siblings('.wpss-test-email-status');
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'wp-sell-services' ) ); ?>');
				$status.hide();
				$.post(ajaxurl, {
					action: 'wpss_send_test_email',
					nonce: $btn.data('nonce')
				}, function(response) {
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Test Email', 'wp-sell-services' ) ); ?>');
					$status.show();
					if (response.success) {
						$status.css('color', '#00a32a').text(response.data.message);
					} else {
						$status.css('color', '#d63638').text(response.data.message);
					}
				}).fail(function() {
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Test Email', 'wp-sell-services' ) ); ?>');
					$status.show().css('color', '#d63638').text('<?php echo esc_js( __( 'Request failed. Please try again.', 'wp-sell-services' ) ); ?>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render demo content controls inside the advanced tab accordion.
	 *
	 * Used as a callback in render_advanced_tab()'s section definition.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function render_demo_content_section(): void {
		$demo_imported = get_option( 'wpss_demo_content_imported', false );
		$nonce         = wp_create_nonce( 'wpss_demo_content' );
		?>
		<div class="wpss-demo-content-actions" style="margin-top: 15px;">
			<?php if ( $demo_imported ) : ?>
				<p style="color: #00a32a; margin-bottom: 10px;">
					<span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Demo content is currently installed.', 'wp-sell-services' ); ?>
				</p>
				<button type="button" class="button button-secondary wpss-delete-demo" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Delete Demo Content', 'wp-sell-services' ); ?>
				</button>
			<?php else : ?>
				<p style="margin-bottom: 10px;">
					<?php esc_html_e( 'Creates 20 sample services across 6 categories with 4 demo vendors.', 'wp-sell-services' ); ?>
				</p>
				<button type="button" class="button button-primary wpss-import-demo" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Import Demo Content', 'wp-sell-services' ); ?>
				</button>
			<?php endif; ?>

			<span class="wpss-demo-status" style="margin-left: 10px; display: none;"></span>
		</div>
		<?php
	}

	/**
	 * Render setup wizard re-run section.
	 *
	 * Used as a callback in render_advanced_tab()'s section definition.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function render_setup_wizard_section(): void {
		$completed  = get_option( 'wpss_setup_wizard_completed', false );
		$wizard_url = admin_url( 'admin.php?page=wpss-setup-wizard' );
		?>
		<div style="margin-top: 15px;">
			<?php if ( $completed ) : ?>
				<p style="margin-bottom: 10px;">
					<?php
					printf(
						/* translators: %s: completion date */
						esc_html__( 'Setup wizard was completed on %s.', 'wp-sell-services' ),
						esc_html( wp_date( get_option( 'date_format' ), (int) $completed ) )
					);
					?>
				</p>
			<?php else : ?>
				<p style="margin-bottom: 10px;">
					<?php esc_html_e( 'The setup wizard has not been completed yet.', 'wp-sell-services' ); ?>
				</p>
			<?php endif; ?>
			<a href="<?php echo esc_url( $wizard_url ); ?>" class="button button-secondary">
				<?php echo $completed ? esc_html__( 'Re-Run Setup Wizard', 'wp-sell-services' ) : esc_html__( 'Run Setup Wizard', 'wp-sell-services' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render demo content import/delete JavaScript.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function render_demo_content_script(): void {
		?>
		<script>
		jQuery(function($) {
			// Demo content import.
			$('.wpss-import-demo').on('click', function() {
				var $btn = $(this);
				var $status = $btn.siblings('.wpss-demo-status');

				if (!confirm('<?php echo esc_js( __( 'Import demo content? This will create sample services, vendors, and categories.', 'wp-sell-services' ) ); ?>')) {
					return;
				}

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Importing...', 'wp-sell-services' ) ); ?>');
				$status.show().text('<?php echo esc_js( __( 'Please wait, this may take a moment...', 'wp-sell-services' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpss_import_demo_content',
						nonce: $btn.data('nonce')
					},
					success: function(response) {
						if (response.success) {
							$status.css('color', '#00a32a').text(response.data.message || '<?php echo esc_js( __( 'Demo content imported successfully!', 'wp-sell-services' ) ); ?>');
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$status.css('color', '#d63638').text(response.data.message || '<?php echo esc_js( __( 'Import failed.', 'wp-sell-services' ) ); ?>');
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import Demo Content', 'wp-sell-services' ) ); ?>');
						}
					},
					error: function() {
						$status.css('color', '#d63638').text('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import Demo Content', 'wp-sell-services' ) ); ?>');
					}
				});
			});

			// Demo content delete.
			$('.wpss-delete-demo').on('click', function() {
				var $btn = $(this);
				var $status = $btn.siblings('.wpss-demo-status');

				if (!confirm('<?php echo esc_js( __( 'Delete all demo content? This will permanently remove demo services, vendors, and empty categories.', 'wp-sell-services' ) ); ?>')) {
					return;
				}

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'wp-sell-services' ) ); ?>');
				$status.show().text('<?php echo esc_js( __( 'Removing demo content...', 'wp-sell-services' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpss_delete_demo_content',
						nonce: $btn.data('nonce')
					},
					success: function(response) {
						if (response.success) {
							$status.css('color', '#00a32a').text(response.data.message || '<?php echo esc_js( __( 'Demo content deleted successfully!', 'wp-sell-services' ) ); ?>');
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$status.css('color', '#d63638').text(response.data.message || '<?php echo esc_js( __( 'Deletion failed.', 'wp-sell-services' ) ); ?>');
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Delete Demo Content', 'wp-sell-services' ) ); ?>');
						}
					},
					error: function() {
						$status.css('color', '#d63638').text('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Delete Demo Content', 'wp-sell-services' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? '' );

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			esc_attr( $value )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render number field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? 0 );

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			esc_attr( (string) $value ),
			esc_attr( (string) ( $args['min'] ?? 0 ) ),
			esc_attr( (string) ( $args['max'] ?? 100 ) ),
			esc_attr( (string) ( $args['step'] ?? 1 ) )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render select field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_select_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? '' );

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] )
		);

		foreach ( $args['options'] as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? false );

		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s> %4$s</label>',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			checked( $value, true, false ),
			esc_html( $args['label'] ?? '' )
		);
	}

	/**
	 * Render e-commerce platform selection field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_ecommerce_platform_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? 'auto';

		// Get available adapters from integration manager.
		$adapters          = array();
		$integration_mgr   = wpss()->get_integration_manager();
		$registered        = $integration_mgr ? $integration_mgr->get_adapters() : array();
		$active_adapter    = $integration_mgr ? $integration_mgr->get_active_adapter() : null;
		$active_adapter_id = $active_adapter ? $active_adapter->get_id() : '';

		// Build adapter options with availability status.
		$platform_options = array(
			'auto' => __( 'Auto-detect (recommended)', 'wp-sell-services' ),
		);

		foreach ( $registered as $id => $adapter ) {
			$name                    = $adapter->get_name();
			$is_active               = $adapter->is_active();
			$status                  = $is_active ? __( 'Available', 'wp-sell-services' ) : __( 'Not Installed', 'wp-sell-services' );
			$platform_options[ $id ] = sprintf( '%s (%s)', $name, $status );
		}

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] )
		);

		foreach ( $platform_options as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';

		// Show current active platform.
		if ( $active_adapter ) {
			printf(
				'<p class="description"><strong>%s:</strong> %s</p>',
				esc_html__( 'Currently Active', 'wp-sell-services' ),
				esc_html( $active_adapter->get_name() )
			);
		} else {
			printf(
				'<p class="description" style="color: #d63638;">%s</p>',
				esc_html__( 'No e-commerce platform detected. Please check your configuration.', 'wp-sell-services' )
			);
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Select which e-commerce platform should handle service checkouts. Standalone checkout is included. Pro adds WooCommerce, EDD, FluentCart, and SureCart.', 'wp-sell-services' )
		);
	}

	/**
	 * Render page select field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_page_select_field( array $args ): void {
		$options    = get_option( $args['option_name'], array() );
		$value      = $options[ $args['field'] ] ?? '';
		$page_title = $args['page_title'] ?? '';

		echo '<div class="wpss-page-select-wrap">';

		wp_dropdown_pages(
			array(
				'name'              => esc_attr( $args['option_name'] . '[' . $args['field'] . ']' ),
				'id'                => esc_attr( $args['field'] ),
				'show_option_none'  => esc_html__( '— Select —', 'wp-sell-services' ),
				'option_none_value' => '',
				'selected'          => esc_attr( $value ),
				'class'             => 'wpss-page-dropdown',
			)
		);

		// Create page button.
		printf(
			'<button type="button" class="button wpss-create-page" data-field="%s" data-title="%s">%s</button>',
			esc_attr( $args['field'] ),
			esc_attr( $page_title ),
			esc_html__( 'Create Page', 'wp-sell-services' )
		);

		// View page link (only show if page is selected).
		if ( $value ) {
			printf(
				'<a href="%s" class="button wpss-view-page" target="_blank">%s</a>',
				esc_url( get_permalink( $value ) ),
				esc_html__( 'View', 'wp-sell-services' )
			);
		} else {
			printf(
				'<a href="#" class="button wpss-view-page" target="_blank" style="display:none;">%s</a>',
				esc_html__( 'View', 'wp-sell-services' )
			);
		}

		echo '</div>';
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param mixed $input Raw input (may be null from register_setting).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_general_settings( mixed $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$sanitized = array();

		// Platform name defaults to site name if empty.
		$platform_name              = sanitize_text_field( $input['platform_name'] ?? '' );
		$sanitized['platform_name'] = ! empty( $platform_name ) ? $platform_name : get_bloginfo( 'name' );

		$sanitized['currency']           = sanitize_text_field( $input['currency'] ?? 'USD' );
		$sanitized['ecommerce_platform'] = sanitize_key( $input['ecommerce_platform'] ?? 'auto' );

		return $sanitized;
	}

	/**
	 * Sanitize commission settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_commission_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['commission_rate']     = min( 50, max( 0, (float) ( $input['commission_rate'] ?? 10 ) ) );
		$sanitized['enable_vendor_rates'] = ! empty( $input['enable_vendor_rates'] );

		return $sanitized;
	}

	/**
	 * Sanitize payouts settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_payouts_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['min_withdrawal']            = absint( $input['min_withdrawal'] ?? 50 );
		$sanitized['clearance_days']            = absint( $input['clearance_days'] ?? 14 );
		$sanitized['auto_withdrawal_enabled']   = ! empty( $input['auto_withdrawal_enabled'] );
		$sanitized['auto_withdrawal_threshold'] = absint( $input['auto_withdrawal_threshold'] ?? 500 );
		$sanitized['auto_withdrawal_schedule']  = sanitize_key( $input['auto_withdrawal_schedule'] ?? 'monthly' );

		// Validate schedule.
		$valid_schedules = array( 'weekly', 'biweekly', 'monthly' );
		if ( ! in_array( $sanitized['auto_withdrawal_schedule'], $valid_schedules, true ) ) {
			$sanitized['auto_withdrawal_schedule'] = 'monthly';
		}

		return $sanitized;
	}

	/**
	 * Sanitize tax settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_tax_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['enable_tax']   = ! empty( $input['enable_tax'] );
		$sanitized['tax_label']    = sanitize_text_field( $input['tax_label'] ?? __( 'Tax', 'wp-sell-services' ) );
		$sanitized['tax_rate']     = min( 50, max( 0, (float) ( $input['tax_rate'] ?? 0 ) ) );
		$sanitized['tax_included'] = ! empty( $input['tax_included'] );

		return $sanitized;
	}

	/**
	 * Sanitize vendor settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_vendor_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['vendor_registration']     = sanitize_key( $input['vendor_registration'] ?? 'open' );
		$sanitized['max_services_per_vendor'] = absint( $input['max_services_per_vendor'] ?? 20 );
		// Vendor verification is not yet implemented — setting removed to avoid confusion.
		$sanitized['require_service_moderation'] = ! empty( $input['require_service_moderation'] );

		return $sanitized;
	}

	/**
	 * Sanitize order settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_order_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['auto_complete_days'] = absint( $input['auto_complete_days'] ?? 3 );
		// Revision limits are defined per-package in service packages, not as a global setting.
		$sanitized['allow_disputes']            = ! empty( $input['allow_disputes'] );
		$sanitized['dispute_window_days']       = absint( $input['dispute_window_days'] ?? 14 );
		$sanitized['auto_dispute_late_days']    = absint( $input['auto_dispute_late_days'] ?? 3 );
		$sanitized['allow_late_requirements']   = ! empty( $input['allow_late_requirements'] );
		$sanitized['requirements_timeout_days'] = absint( $input['requirements_timeout_days'] ?? 0 );
		$sanitized['auto_start_on_timeout']     = ! empty( $input['auto_start_on_timeout'] );

		return $sanitized;
	}

	/**
	 * Sanitize notification settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_notification_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		// Build keys dynamically from the same filter used to render the UI.
		$notification_types = apply_filters(
			'wpss_notification_types',
			array(
				'new_order'              => __( 'New Order', 'wp-sell-services' ),
				'order_completed'        => __( 'Order Completed', 'wp-sell-services' ),
				'order_cancelled'        => __( 'Order Cancelled', 'wp-sell-services' ),
				'cancellation_requested' => __( 'Cancellation Requested', 'wp-sell-services' ),
				'delivery_submitted'     => __( 'Delivery Submitted', 'wp-sell-services' ),
				'revision_requested'     => __( 'Revision Requested', 'wp-sell-services' ),
				'new_message'            => __( 'New Message', 'wp-sell-services' ),
				'vendor_contact'         => __( 'Vendor Direct Message', 'wp-sell-services' ),
				'new_review'             => __( 'New Review', 'wp-sell-services' ),
				'dispute_opened'         => __( 'Dispute Opened', 'wp-sell-services' ),
				'withdrawal_requested'   => __( 'Withdrawal Requested', 'wp-sell-services' ),
				'withdrawal_approved'    => __( 'Withdrawal Approved', 'wp-sell-services' ),
				'withdrawal_rejected'    => __( 'Withdrawal Rejected', 'wp-sell-services' ),
				'proposal_submitted'     => __( 'Proposal Submitted', 'wp-sell-services' ),
				'proposal_accepted'      => __( 'Proposal Accepted', 'wp-sell-services' ),
			)
		);

		foreach ( array_keys( $notification_types ) as $type_key ) {
			$key               = 'notify_' . $type_key;
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize pages settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_pages_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$page_keys = array(
			'services_page',
			'dashboard',
			'become_vendor',
			'checkout',
		);

		foreach ( $page_keys as $key ) {
			$sanitized[ $key ] = absint( $input[ $key ] ?? 0 );
		}

		return $sanitized;
	}

	/**
	 * Sanitize advanced settings.
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_advanced_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
		$sanitized['enable_debug_mode']        = ! empty( $input['enable_debug_mode'] );

		$sanitized['max_file_size']      = absint( $input['max_file_size'] ?? 10 );
		$sanitized['allowed_file_types'] = sanitize_text_field( $input['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx' );
		$sanitized['currency_position']  = in_array( $input['currency_position'] ?? 'before', array( 'before', 'after' ), true )
			? $input['currency_position']
			: 'before';

		// Sync to standalone options for backward compatibility with existing code
		// that reads these via get_option('wpss_*').
		update_option( 'wpss_max_file_size', $sanitized['max_file_size'] );
		update_option( 'wpss_allowed_file_types', $sanitized['allowed_file_types'] );
		update_option( 'wpss_currency_position', $sanitized['currency_position'] );

		return $sanitized;
	}

	/**
	 * Get available currencies.
	 *
	 * @return array<string, string> Currency codes and labels.
	 */
	private function get_currencies(): array {
		return array(
			'USD' => __( 'US Dollar ($)', 'wp-sell-services' ),
			'EUR' => __( 'Euro (€)', 'wp-sell-services' ),
			'GBP' => __( 'British Pound (£)', 'wp-sell-services' ),
			'CAD' => __( 'Canadian Dollar (C$)', 'wp-sell-services' ),
			'AUD' => __( 'Australian Dollar (A$)', 'wp-sell-services' ),
			'INR' => __( 'Indian Rupee (₹)', 'wp-sell-services' ),
			'JPY' => __( 'Japanese Yen (¥)', 'wp-sell-services' ),
			'CNY' => __( 'Chinese Yuan (¥)', 'wp-sell-services' ),
			'BRL' => __( 'Brazilian Real (R$)', 'wp-sell-services' ),
			'MXN' => __( 'Mexican Peso ($)', 'wp-sell-services' ),
		);
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $group Setting group.
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	public static function get( string $group, string $key, mixed $default = null ): mixed {
		$options = get_option( 'wpss_' . $group, array() );
		return $options[ $key ] ?? $default;
	}
}
