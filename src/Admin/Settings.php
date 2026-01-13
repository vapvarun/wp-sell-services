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
	 * Settings tabs.
	 *
	 * @var array<string, string>
	 */
	private array $tabs = array();

	/**
	 * Tab groups for visual organization.
	 *
	 * @var array<string, array>
	 */
	private array $tab_groups = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Define tabs in logical groups for better UX.
		$this->tabs = array(
			// Setup group.
			'general'       => __( 'General', 'wp-sell-services' ),
			'pages'         => __( 'Pages', 'wp-sell-services' ),
			// Business group.
			'commission'    => __( 'Commission', 'wp-sell-services' ),
			'tax'           => __( 'Tax', 'wp-sell-services' ),
			'payouts'       => __( 'Payouts', 'wp-sell-services' ),
			'vendor'        => __( 'Vendor', 'wp-sell-services' ),
			// Operations group.
			'orders'        => __( 'Orders', 'wp-sell-services' ),
			'notifications' => __( 'Notifications', 'wp-sell-services' ),
			// System group (Pro tabs will be inserted before this).
			'advanced'      => __( 'Advanced', 'wp-sell-services' ),
		);

		// Define tab groups for visual separators.
		$this->tab_groups = array(
			'setup'      => array( 'general', 'pages' ),
			'business'   => array( 'commission', 'tax', 'payouts', 'vendor' ),
			'operations' => array( 'orders', 'notifications' ),
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
		$core_tabs = array(
			'general',
			'pages',
			'commission',
			'tax',
			'payouts',
			'vendor',
			'orders',
			'notifications',
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
		$title = sanitize_text_field( $_POST['title'] ?? '' );

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
			array( $this, 'sanitize_general_settings' )
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

		add_settings_field(
			'tax_on_commission',
			__( 'Tax on Commission', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_tax',
			'wpss_tax_section',
			array(
				'option_name' => 'wpss_tax',
				'field'       => 'tax_on_commission',
				'options'     => array(
					'none'     => __( 'No tax on commission', 'wp-sell-services' ),
					'platform' => __( 'Platform collects tax on full amount', 'wp-sell-services' ),
					'vendor'   => __( 'Vendor handles own tax obligations', 'wp-sell-services' ),
				),
				'default'     => 'none',
				'description' => __( 'How tax is handled relative to platform commission.', 'wp-sell-services' ),
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
				'min'         => 1,
				'max'         => 100,
				'default'     => 20,
				'description' => __( '0 for unlimited.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'require_verification',
			__( 'Require Verification', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'require_verification',
				'label'       => __( 'Require vendors to verify identity before selling', 'wp-sell-services' ),
				'default'     => false,
			)
		);

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

		add_settings_field(
			'revision_limit',
			__( 'Default Revision Limit', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'revision_limit',
				'min'         => 0,
				'max'         => 10,
				'default'     => 2,
				'description' => __( 'Default revisions per order. Can be overridden per service.', 'wp-sell-services' ),
			)
		);

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

		$notification_types = array(
			'new_order'          => __( 'New Order', 'wp-sell-services' ),
			'order_completed'    => __( 'Order Completed', 'wp-sell-services' ),
			'order_cancelled'    => __( 'Order Cancelled', 'wp-sell-services' ),
			'delivery_submitted' => __( 'Delivery Submitted', 'wp-sell-services' ),
			'revision_requested' => __( 'Revision Requested', 'wp-sell-services' ),
			'new_message'        => __( 'New Message', 'wp-sell-services' ),
			'new_review'         => __( 'New Review', 'wp-sell-services' ),
			'dispute_opened'     => __( 'Dispute Opened', 'wp-sell-services' ),
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
			'services_page'  => __( 'Services Page', 'wp-sell-services' ),
			'dashboard'      => __( 'Dashboard', 'wp-sell-services' ),
			'become_vendor'  => __( 'Become a Vendor', 'wp-sell-services' ),
			'create_service' => __( 'Create Service', 'wp-sell-services' ),
		);

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
			// Check if this is a Pro/extension tab (not a core tab).
			$core_tabs = array( 'general', 'commission', 'tax', 'payouts', 'vendor', 'orders', 'notifications', 'pages', 'advanced' );

			if ( ! in_array( $active_tab, $core_tabs, true ) ) {
				/**
				 * Fires when rendering a custom settings tab added by Pro or extensions.
				 *
				 * @since 1.2.0
				 *
				 * @param string $active_tab The active tab slug.
				 */
				do_action( 'wpss_settings_tab_' . $active_tab );
			} else {
				?>
			<form method="post" action="options.php">
				<?php
				switch ( $active_tab ) {
					case 'commission':
						settings_fields( 'wpss_commission' );
						do_settings_sections( 'wpss_commission' );
						break;

					case 'payouts':
						settings_fields( 'wpss_payouts' );
						do_settings_sections( 'wpss_payouts' );
						break;

					case 'tax':
						settings_fields( 'wpss_tax' );
						do_settings_sections( 'wpss_tax' );
						break;

					case 'vendor':
						settings_fields( 'wpss_vendor' );
						do_settings_sections( 'wpss_vendor' );
						break;

					case 'orders':
						settings_fields( 'wpss_orders' );
						do_settings_sections( 'wpss_orders' );
						break;

					case 'notifications':
						settings_fields( 'wpss_notifications' );
						do_settings_sections( 'wpss_notifications' );
						break;

					case 'pages':
						settings_fields( 'wpss_pages' );
						do_settings_sections( 'wpss_pages' );
						break;

					case 'advanced':
						settings_fields( 'wpss_advanced' );
						do_settings_sections( 'wpss_advanced' );
						break;

					default:
						settings_fields( 'wpss_general' );
						do_settings_sections( 'wpss_general' );
						break;
				}

				submit_button();
				?>
			</form>
			<?php } ?>

			<?php if ( 'pages' === $active_tab ) : ?>
			<script>
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
						alert('<?php echo esc_js( __( 'Page title not defined.', 'wp-sell-services' ) ); ?>');
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
								alert(response.data.message || '<?php echo esc_js( __( 'Failed to create page.', 'wp-sell-services' ) ); ?>');
								$btn.removeClass('creating').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
							}
						},
						error: function() {
							alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
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
		$wc_active = class_exists( 'WooCommerce' );
		echo '<p>' . esc_html__( 'Configure e-commerce platform for service checkout.', 'wp-sell-services' ) . '</p>';
		if ( ! $wc_active ) {
			echo '<p class="description" style="color: #d63638;">';
			echo esc_html__( 'WooCommerce is not installed or active.', 'wp-sell-services' );
			echo '</p>';
		}
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
				esc_html__( 'No e-commerce platform detected. Please install WooCommerce or another supported platform.', 'wp-sell-services' )
			);
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Select which e-commerce platform should handle service checkouts. Pro version adds support for EDD, FluentCart, SureCart, and Standalone mode.', 'wp-sell-services' )
		);
	}

	/**
	 * Render page select field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_page_select_field( array $args ): void {
		$options     = get_option( $args['option_name'], array() );
		$value       = $options[ $args['field'] ] ?? '';
		$page_titles = array(
			'services_page' => __( 'Services', 'wp-sell-services' ),
			'dashboard'     => __( 'Dashboard', 'wp-sell-services' ),
			'become_vendor' => __( 'Become a Vendor', 'wp-sell-services' ),
		);

		echo '<div class="wpss-page-select-wrap">';

		wp_dropdown_pages(
			array(
				'name'              => $args['option_name'] . '[' . $args['field'] . ']',
				'id'                => $args['field'],
				'show_option_none'  => __( '— Select —', 'wp-sell-services' ),
				'option_none_value' => '',
				'selected'          => $value,
				'class'             => 'wpss-page-dropdown',
			)
		);

		// Create page button.
		printf(
			'<button type="button" class="button wpss-create-page" data-field="%s" data-title="%s">%s</button>',
			esc_attr( $args['field'] ),
			esc_attr( $page_titles[ $args['field'] ] ?? '' ),
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
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_general_settings( array $input ): array {
		$sanitized = array();

		$sanitized['platform_name']      = sanitize_text_field( $input['platform_name'] ?? '' );
		$sanitized['currency']           = sanitize_text_field( $input['currency'] ?? 'USD' );
		$sanitized['ecommerce_platform'] = sanitize_key( $input['ecommerce_platform'] ?? 'auto' );

		return $sanitized;
	}

	/**
	 * Sanitize commission settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_commission_settings( array $input ): array {
		$sanitized = array();

		$sanitized['commission_rate']     = min( 50, max( 0, (float) ( $input['commission_rate'] ?? 10 ) ) );
		$sanitized['enable_vendor_rates'] = ! empty( $input['enable_vendor_rates'] );

		return $sanitized;
	}

	/**
	 * Sanitize payouts settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_payouts_settings( array $input ): array {
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
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_tax_settings( array $input ): array {
		$sanitized = array();

		$sanitized['enable_tax']        = ! empty( $input['enable_tax'] );
		$sanitized['tax_label']         = sanitize_text_field( $input['tax_label'] ?? __( 'Tax', 'wp-sell-services' ) );
		$sanitized['tax_rate']          = min( 50, max( 0, (float) ( $input['tax_rate'] ?? 0 ) ) );
		$sanitized['tax_included']      = ! empty( $input['tax_included'] );
		$sanitized['tax_on_commission'] = sanitize_key( $input['tax_on_commission'] ?? 'none' );

		// Validate tax_on_commission option.
		$valid_options = array( 'none', 'platform', 'vendor' );
		if ( ! in_array( $sanitized['tax_on_commission'], $valid_options, true ) ) {
			$sanitized['tax_on_commission'] = 'none';
		}

		return $sanitized;
	}

	/**
	 * Sanitize vendor settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_vendor_settings( array $input ): array {
		$sanitized = array();

		$sanitized['vendor_registration']        = sanitize_key( $input['vendor_registration'] ?? 'open' );
		$sanitized['max_services_per_vendor']    = absint( $input['max_services_per_vendor'] ?? 20 );
		$sanitized['require_verification']       = ! empty( $input['require_verification'] );
		$sanitized['require_service_moderation'] = ! empty( $input['require_service_moderation'] );

		return $sanitized;
	}

	/**
	 * Sanitize order settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_order_settings( array $input ): array {
		$sanitized = array();

		$sanitized['auto_complete_days']  = absint( $input['auto_complete_days'] ?? 3 );
		$sanitized['revision_limit']      = absint( $input['revision_limit'] ?? 2 );
		$sanitized['allow_disputes']      = ! empty( $input['allow_disputes'] );
		$sanitized['dispute_window_days'] = absint( $input['dispute_window_days'] ?? 14 );

		return $sanitized;
	}

	/**
	 * Sanitize notification settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_notification_settings( array $input ): array {
		$sanitized = array();

		$notification_keys = array(
			'notify_new_order',
			'notify_order_completed',
			'notify_order_cancelled',
			'notify_delivery_submitted',
			'notify_revision_requested',
			'notify_new_message',
			'notify_new_review',
			'notify_dispute_opened',
		);

		foreach ( $notification_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize pages settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_pages_settings( array $input ): array {
		$sanitized = array();

		$page_keys = array(
			'services_page',
			'dashboard',
			'become_vendor',
			'create_service',
		);

		foreach ( $page_keys as $key ) {
			$sanitized[ $key ] = absint( $input[ $key ] ?? 0 );
		}

		return $sanitized;
	}

	/**
	 * Sanitize advanced settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_advanced_settings( array $input ): array {
		$sanitized = array();

		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
		$sanitized['enable_debug_mode']        = ! empty( $input['enable_debug_mode'] );

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
