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
			'general'    => __( 'General', 'wp-sell-services' ),
			'pages'      => __( 'Pages', 'wp-sell-services' ),
			// Money. The old single "Payments" tab carried commission, tax,
			// payouts AND every gateway credential set — eight independent
			// forms with eight save buttons, where editing across two cards
			// silently lost the unsaved one. Split into the three questions an
			// owner actually asks: how does money come IN, what do we KEEP, and
			// how does it go OUT.
			'payments'   => __( 'Payment Gateways', 'wp-sell-services' ),
			'commission' => __( 'Commission &amp; Tax', 'wp-sell-services' ),
			'payouts'    => __( 'Payouts', 'wp-sell-services' ),
			// Marketplace.
			'vendor'     => __( 'Vendor Settings', 'wp-sell-services' ),
			'orders'     => __( 'Orders &amp; Disputes', 'wp-sell-services' ),
			'emails'     => __( 'Emails', 'wp-sell-services' ),
			// System (Pro tabs inserted before this via filter).
			'advanced'   => __( 'Advanced', 'wp-sell-services' ),
		);

		$this->tab_groups = array(
			'setup'      => array( 'general', 'pages' ),
			'money'      => array( 'payments', 'commission', 'payouts' ),
			'operations' => array( 'vendor', 'orders', 'emails' ),
			'pro'        => array(), // Pro tabs added via filter.
			'system'     => array( 'advanced' ),
		);
	}

	/**
	 * The tabs FREE ships — the single authority for "is this a Pro tab?".
	 *
	 * Anything not in this list is treated as added by Pro or an extension: it
	 * lands in the EXTENSIONS group, gets a Pro badge, and renders through the
	 * `wpss_settings_tab_{slug}` action instead of a core method.
	 *
	 * This list previously existed as three separate copies (grouping, panel
	 * dispatch, sidebar). Splitting the Payments tab updated two of them and
	 * missed the sidebar, which promptly badged two free tabs as "Pro" — so it
	 * is defined once here and read everywhere.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, string> Core tab slugs.
	 */
	private function get_core_tabs(): array {
		return array(
			'general',
			'pages',
			'payments',
			'commission',
			'payouts',
			'vendor',
			'orders',
			'emails',
			'advanced',
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

		$core_tabs = $this->get_core_tabs();

		$grouped = array(
			'setup'      => array(),
			'money'      => array(),
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
	 * Lucide icon map for sidebar nav items.
	 *
	 * @return array<string, string> Tab slug => Lucide icon name.
	 */
	private function get_icon_map(): array {
		return array(
			'general'    => 'settings',
			'pages'      => 'layout-template',
			'payments'   => 'credit-card',
			'commission' => 'percent',
			'payouts'    => 'banknote',
			'vendor'     => 'store',
			'orders'     => 'shopping-cart',
			'emails'     => 'mail',
			'advanced'   => 'wrench',
		);
	}

	/**
	 * Group labels for sidebar nav.
	 *
	 * @return array<string, string> Group slug => Display label.
	 */
	private function get_group_labels(): array {
		return array(
			'setup'      => __( 'SETUP', 'wp-sell-services' ),
			'money'      => __( 'MONEY', 'wp-sell-services' ),
			'operations' => __( 'MARKETPLACE', 'wp-sell-services' ),
			'pro'        => __( 'EXTENSIONS', 'wp-sell-services' ),
			'system'     => __( 'SYSTEM', 'wp-sell-services' ),
		);
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Expose the safe masked-secret renderer as a shared extension point so
		// gateway settings renderers (Stripe, PayPal, Pro gateways) emit secret
		// fields that never echo the stored value. Usage:
		// do_action( 'wpss_render_secret_field', $args );
		add_action( 'wpss_render_secret_field', array( $this, 'render_secret_field' ) );
	}

	/**
	 * Enqueue the masked-secret progressive-enhancement script.
	 *
	 * Loaded only on the plugin settings page. The script is pure progressive
	 * enhancement: the masked-secret fields rendered by render_secret_field()
	 * work without it (the stored secret is never echoed regardless of JS).
	 *
	 * @since 1.6.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'wpss-settings' ) ) {
			return;
		}

		wp_enqueue_script(
			'wpss-admin-settings',
			\WPSS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array(),
			\WPSS_VERSION,
			true
		);

		wp_set_script_translations( 'wpss-admin-settings', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'wpss-admin-settings',
			'wpssSettingsSecret',
			array(
				'reveal'      => __( 'Reveal', 'wp-sell-services' ),
				'hide'        => __( 'Hide', 'wp-sell-services' ),
				'replace'     => __( 'Replace', 'wp-sell-services' ),
				'cancel'      => __( 'Cancel', 'wp-sell-services' ),
				'enterSecret' => __( 'Enter the new secret value', 'wp-sell-services' ),
			)
		);
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

		// The registry, not the browser, decides what a known page is called and
		// where it lives. This handler used to take the posted title and let
		// WordPress derive a slug from it, so creating the cart page on a site
		// running WooCommerce found `cart` taken and silently produced
		// `/cart-2/`, `/cart-3/` … while the installer's `service-cart` slug was
		// never used. A key the registry does not know (added through the
		// wpss_page_definitions filter) still honours the posted title.
		$definitions = wpss_get_page_definitions();
		$definition  = $definitions[ $field ] ?? null;
		$slug        = '';

		if ( is_array( $definition ) ) {
			$title = $definition['title'];
			$slug  = $definition['slug'];
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
		$new_page = array(
			'post_title'   => $title,
			'post_content' => $page_content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( '' !== $slug ) {
			$new_page['post_name'] = $slug;
		}

		$page_id = wp_insert_post( $new_page );

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
	 * @since 1.0.0
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

		$email_service = new \WPSellServices\Services\EmailService();
		$result        = $email_service->send(
			$admin_email,
			/* translators: %s: site/platform name */
			sprintf( __( '[%s] Test Email', 'wp-sell-services' ), $site_name ),
			\WPSellServices\Services\EmailService::TYPE_TEST_EMAIL,
			array(
				'recipient' => wp_get_current_user(),
				'site_name' => $site_name,
			)
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
		$definitions = wpss_get_page_definitions();

		return $definitions[ $field ]['shortcode'] ?? '';
	}

	/**
	 * Register all settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$this->register_tuning_settings();

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
				'description' => __( 'Used in emails and notifications. Defaults to your site title - if you rename the site later, update this too or your emails will keep the old name.', 'wp-sell-services' ),
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

		// Checkout reassurance badges.
		//
		// These print on a PUBLIC page a buyer reads while paying, so the words
		// belong to the site owner, not to us. The plugin ships factual
		// defaults derived from the package being bought and never asserts a
		// guarantee on the owner's behalf - it previously promised "on-time
		// delivery or your money back", which nothing in the code honours.
		add_settings_section(
			'wpss_checkout_badges_section',
			__( 'Checkout Reassurance', 'wp-sell-services' ),
			array( $this, 'render_checkout_badges_section' ),
			'wpss_general'
		);

		add_settings_field(
			'checkout_badges_enabled',
			__( 'Show reassurance badges', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_general',
			'wpss_checkout_badges_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'checkout_badges_enabled',
				'label'       => __( 'Display a short row of reassurance items on the checkout page.', 'wp-sell-services' ),
				'default'     => true,
			)
		);

		// Only worth showing when a second cart actually exists to be confused
		// with — on a site without WooCommerce there is only one cart and the
		// option would be noise.
		if ( class_exists( 'WooCommerce' ) ) {
			add_settings_field(
				'use_marketplace_cart_link',
				__( 'Site cart link', 'wp-sell-services' ),
				array( $this, 'render_checkbox_field' ),
				'wpss_general',
				'wpss_checkout_badges_section',
				array(
					'option_name' => 'wpss_general',
					'field'       => 'use_marketplace_cart_link',
					'label'       => __( 'Point the theme\'s cart link at the marketplace cart. Turn this on for a marketplace-only site; leave it off if you also sell WooCommerce products, or their cart link will send buyers to the wrong place.', 'wp-sell-services' ),
					'default'     => false,
				)
			);
		}

		add_settings_field(
			'checkout_account_creation',
			__( 'Account at checkout', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_general',
			'wpss_checkout_badges_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'checkout_account_creation',
				'label'       => __( 'Let a logged-out buyer complete checkout. Their account is created from the billing name and email they enter, and they are signed in before the order is placed, so they can submit requirements and message the seller straight away. Off by default: it changes who can transact on your site.', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'checkout_badges',
			__( 'Badge text', 'wp-sell-services' ),
			array( $this, 'render_checkout_badges_field' ),
			'wpss_general',
			'wpss_checkout_badges_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'checkout_badges',
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
				'description' => __( 'Platform commission deducted from each order. Example: 20% on a $100 order = you keep $20, vendor gets $80.', 'wp-sell-services' ),
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
				'description' => __( 'Allow setting custom commission rates per vendor in their admin profile. Per-vendor rates override the default above.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'tip_commission_rate',
			__( 'Tip Commission Rate (%)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_commission',
			'wpss_commission_section',
			array(
				'option_name' => 'wpss_commission',
				'field'       => 'tip_commission_rate',
				'min'         => 0,
				'max'         => 50,
				'step'        => 0.1,
				'default'     => '',
				'description' => __( 'Cut the platform keeps on tips. Leave empty to use the main commission rate (vendors receive the same net cut as on regular orders). Set to 0 to give vendors 100% of every tip.', 'wp-sell-services' ),
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

		// Wallet provider lives as a standalone option (single canonical key
		// read by free Plugin::get_active_wallet_provider() and Pro's
		// WalletManager — Basecamp #9985173976).
		register_setting(
			'wpss_payouts',
			'wpss_wallet_provider',
			array( 'sanitize_callback' => 'sanitize_key' )
		);

		add_settings_field(
			'wpss_wallet_provider',
			__( 'Wallet Provider', 'wp-sell-services' ),
			array( $this, 'render_wallet_provider_field' ),
			'wpss_payouts',
			'wpss_payouts_section'
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
				'description' => __( 'Vendors must earn at least this amount before they can request a withdrawal. Recommended: $50-$100 for most marketplaces.', 'wp-sell-services' ),
			)
		);

		// clearance_days is stored AND enforced by EarningsService::get_summary()
		// — reads from wpss_payouts.clearance_days, used in the in_clearance
		// bucket query. (VS1 from plans/ORDER-FLOW-AUDIT.md.)
		//
		// OWNER DECISION 2026-07-23: default 0 (no hold); the owner opts in.
		// How long to hold a vendor's money is a business-policy call, not a
		// safety rail we impose — many marketplaces pay out the moment an order
		// completes and want the vendor experience to match.
		//
		// Zero is safe HERE specifically because the wallet ledger records a
		// refund-after-payout as a NEGATIVE balance that future earnings pay
		// down automatically (get_summary() deliberately does not clamp at
		// zero), so the platform never silently absorbs the loss and nobody
		// chases money already in a vendor's bank. Owners who would rather never
		// have that conversation set a refund window: 7 / 14 / 30.
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
				'default'     => 0,
				'description' => __( 'Days to hold earnings after an order completes, before a vendor can be paid. 0 pays out as soon as an order completes. A hold is your refund window: if a refund lands after you have already paid the vendor, their balance goes negative and future earnings clear it — a hold avoids that situation entirely. 7 = weekly, 14 = fortnightly, 30 = monthly.', 'wp-sell-services' ),
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
				'label'       => __( 'Automatically create withdrawal requests for high-earning vendors', 'wp-sell-services' ),
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
				'description' => __( 'A withdrawal request is created for any vendor whose available balance is above this amount, on the schedule below. You still approve and pay each request - no money leaves your account on its own. Set to 0 to disable.', 'wp-sell-services' ),
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
				'description' => __( 'How often automatic withdrawals are processed for eligible vendors.', 'wp-sell-services' ),
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
				'description' => __( 'Add tax to service orders at checkout. Only applies to standalone checkout — WooCommerce uses its own tax settings.', 'wp-sell-services' ),
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
				'description' => __( 'Label shown to buyers at checkout and in emails (e.g., VAT, GST, Sales Tax).', 'wp-sell-services' ),
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
				'description' => __( 'When enabled, displayed prices already include tax. When disabled, tax is added on top at checkout.', 'wp-sell-services' ),
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
				'description' => __( 'Maximum services each vendor can publish. Set to 0 for unlimited. Vendors see an error when they reach the limit.', 'wp-sell-services' ),
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
				'description' => __( 'New services require admin approval before becoming visible to buyers. Manage pending services in the Moderation page.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'moderate_reviews',
			__( 'Review Moderation', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'moderate_reviews',
				'label'       => __( 'Hold new reviews for admin approval before they are published', 'wp-sell-services' ),
				'default'     => false,
				'description' => __( 'New reviews land as pending and appear on the Review Moderation page until approved.', 'wp-sell-services' ),
			)
		);

		// Checkout billing fields. Owner picks which of the twelve are collected
		// (Basecamp #10159633185).
		register_setting(
			'wpss_orders',
			'wpss_billing_field_settings',
			array( $this, 'sanitize_billing_field_settings' )
		);

		// Offline payment proof (Basecamp #10194890682).
		register_setting(
			'wpss_orders',
			'wpss_offline_receipt_settings',
			array( $this, 'sanitize_offline_receipt_settings' )
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
				'description' => __( 'Days after vendor submits delivery before the order auto-completes if buyer does not respond. Set to 0 to require buyer action.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'wpss_billing_fields',
			__( 'Checkout Billing Fields', 'wp-sell-services' ),
			array( $this, 'render_billing_fields_field' ),
			'wpss_orders',
			'wpss_orders_section'
		);

		add_settings_field(
			'wpss_offline_receipts',
			__( 'Offline Payment Proof', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_offline_receipt_settings',
				'field'       => 'enabled',
				'label'       => __( 'Let buyers upload proof of an offline payment for an admin to verify', 'wp-sell-services' ),
				'default'     => false,
				'description' => __( 'A buyer paying by bank transfer can attach a receipt to their order. An admin reviews it and either approves it — which marks the order paid — or rejects it with a reason so the buyer can try again. Off by default: a marketplace taking only card payments should not see an upload box it will never use.', 'wp-sell-services' ),
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
				'description' => __( 'Days after order completion during which buyers can open a dispute. After this window, disputes are locked.', 'wp-sell-services' ),
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
				'description' => __( 'Automatically open a dispute if delivery is overdue by this many days past the deadline. Set to 0 to disable.', 'wp-sell-services' ),
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
				'description' => __( 'Days to wait for buyer to submit requirements before the order auto-starts or auto-cancels (see next setting). Set to 0 to disable.', 'wp-sell-services' ),
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

		// Delivery behaviour, as opposed to which types are switched on.
		//
		// This option was read by wpss_should_skip_message_email() and by the
		// message-email delay, and registered nowhere - so neither could be
		// changed from the screen. A setting nothing can write is a setting
		// nobody has.
		register_setting(
			'wpss_notifications',
			'wpss_notification_settings',
			array( $this, 'sanitize_notification_delivery_settings' )
		);

		add_settings_section(
			'wpss_notification_delivery_section',
			__( 'Message Email Delivery', 'wp-sell-services' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Message emails are the most frequent mail the marketplace sends - one per message on every active order. These two settings cut the ones nobody needed.', 'wp-sell-services' ) . '</p>';
			},
			'wpss_notifications'
		);

		add_settings_field(
			'skip_message_email_when_online',
			__( 'Skip when they are already here', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_notifications',
			'wpss_notification_delivery_section',
			array(
				'option_name' => 'wpss_notification_settings',
				'field'       => 'skip_message_email_when_online',
				'label'       => __( 'Do not email a message to someone who is browsing the site right now', 'wp-sell-services' ),
				'default'     => true,
				'description' => __( 'Presence is read from recent activity. On a quiet site where that is sparse, turn this off if members report missing message emails.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'message_email_delay_minutes',
			__( 'Hold message emails for', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_notifications',
			'wpss_notification_delivery_section',
			array(
				'option_name' => 'wpss_notification_settings',
				'field'       => 'message_email_delay_minutes',
				'default'     => 0,
				'min'         => 0,
				'max'         => 120,
				'description' => __( 'Minutes to wait before sending. When the wait is over the email is sent only if the conversation is still unread. Set to 0 to send immediately.', 'wp-sell-services' ),
			)
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
		$notification_types = $this->get_notification_types();

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

		// Every key here must also appear in sanitize_pages_settings()'s
		// $page_keys, or saving this panel drops it from the option.
		//
		// `vendors_page` and `cart` were both readable and unwritable before:
		// the vendors page had no field at all (so `wpss_pages['vendors_page']`
		// could never be set by anyone) and the cart page was seeded by the
		// installer but missing from the save whitelist, so the first save of
		// this panel deleted it with no way to put it back.
		// The panel iterates the page registry, so a key added through the
		// wpss_page_definitions filter gets a mapping field automatically
		// instead of being creatable but unmappable.
		//
		// The field label describes the mapping ("Services Page"); the page
		// title is what the page will actually be called ("Services"). Where
		// the two differ the label is overridden below -- anything else falls
		// back to the registry title.
		$page_definitions = wpss_get_page_definitions();

		$page_labels = array(
			'services_page' => __( 'Services Page', 'wp-sell-services' ),
			'vendors_page'  => __( 'Vendors Directory', 'wp-sell-services' ),
		);

		$pages = array();

		foreach ( $page_definitions as $page_key => $page_definition ) {
			$pages[ $page_key ] = $page_labels[ $page_key ] ?? $page_definition['title'];
		}

		// Hide the "Become a Vendor" mapping when vendor registration is closed.
		$pages_vendor_settings   = get_option( 'wpss_vendor', array() );
		$pages_registration_mode = $pages_vendor_settings['vendor_registration'] ?? 'open';

		if ( 'closed' === $pages_registration_mode ) {
			unset( $pages['become_vendor'] );
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
					'page_title'  => $page_definitions[ $key ]['title'] ?? $label,
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

		// Realtime (WebSocket) settings.
		register_setting(
			'wpss_realtime',
			'wpss_realtime_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_realtime_settings' ),
			)
		);

		add_settings_section(
			'wpss_realtime_section',
			__( 'Real-time Settings', 'wp-sell-services' ),
			array( $this, 'render_realtime_section' ),
			'wpss_realtime'
		);

		add_settings_field(
			'enabled',
			__( 'Enable Real-time Updates', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'enabled',
				'label'       => __( 'Push live messages and notifications to logged-in users over WebSockets', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'app_id',
			__( 'App ID', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'app_id',
				'default'     => '',
				'description' => __( 'The application ID from your Pusher.com app or Soketi configuration.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'key',
			__( 'Key', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'key',
				'default'     => '',
				'description' => __( 'The publishable app key. This is shared with browsers; it cannot publish events on its own.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'secret',
			__( 'Secret', 'wp-sell-services' ),
			array( $this, 'render_secret_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'secret',
				'label'       => __( 'Realtime app secret', 'wp-sell-services' ),
				'description' => __( 'The app secret used to sign events and channel authorizations. Never sent to browsers.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'host',
			__( 'Host', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'host',
				'default'     => '',
				'description' => __( 'Leave empty for Pusher.com, or enter your self-hosted Pusher-compatible (Soketi) server hostname, e.g. wss-server.example.com.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'cluster',
			__( 'Cluster', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'cluster',
				'default'     => 'mt1',
				'description' => __( 'Pusher.com cluster (e.g. mt1, eu, ap2). Ignored when a custom host is set.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'port',
			__( 'Port', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'port',
				'default'     => 443,
				'min'         => 1,
				'max'         => 65535,
				'step'        => 1,
				'description' => __( 'Server port. 443 for Pusher.com and TLS-terminated Soketi servers.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'use_tls',
			__( 'Use TLS', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_realtime',
			'wpss_realtime_section',
			array(
				'option_name' => 'wpss_realtime_settings',
				'field'       => 'use_tls',
				'label'       => __( 'Connect over TLS (wss/https) - recommended', 'wp-sell-services' ),
				'default'     => true,
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * Uses Pattern A (sidebar + hash routing). All tab sections are rendered
	 * at once; JavaScript shows/hides sections based on the URL hash.
	 *
	 * @return void
	 */
	public function render(): void {
		// Guard on the SAME capability the menu registers this page under
		// (Admin::add_admin_menu uses `wpss_manage_settings`). Guarding on
		// `manage_options` instead meant a role granted only
		// `wpss_manage_settings` saw the menu item, clicked it, and got a
		// completely blank page with no explanation. Administrators are
		// unaffected — they hold both.
		if ( ! current_user_can( 'wpss_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage these settings.', 'wp-sell-services' ),
				esc_html__( 'Permission denied', 'wp-sell-services' ),
				array( 'response' => 403 )
			);
		}

		$this->init_tabs();

		$core_tabs = $this->get_core_tabs();
		?>
		<div class="wrap wpss-admin">
			<div class="wpss-page-header">
				<div class="wpss-page-header__left">
					<h1 class="wpss-page-header__title">
						<i data-lucide="settings" class="wpss-icon--sm"></i>
						<?php echo esc_html__( 'WP Sell Services Settings', 'wp-sell-services' ); ?>
					</h1>
					<p class="wpss-page-header__desc">
						<?php echo esc_html__( 'Configure your service marketplace.', 'wp-sell-services' ); ?>
					</p>
				</div>
			</div>

			<div class="wpss-settings-wrap">
				<?php $this->render_sidebar(); ?>

				<div class="wpss-settings-content">
					<?php
					foreach ( $this->tabs as $tab_key => $tab_label ) {
						echo '<div class="wpss-settings-section" id="section-' . esc_attr( $tab_key ) . '">';
						$this->render_tab_content( $tab_key, $core_tabs );
						echo '</div>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the sidebar navigation.
	 *
	 * @return void
	 */
	private function render_sidebar(): void {
		$grouped_tabs = $this->get_grouped_tabs();
		$group_labels = $this->get_group_labels();
		$icon_map     = $this->get_icon_map();
		$core_tabs    = $this->get_core_tabs();
		?>
		<div class="wpss-settings-sidebar">
			<div class="wpss-settings-sidebar__brand">
				<span class="wpss-settings-sidebar__logo">
					<i data-lucide="shopping-bag"></i>
				</span>
				<div>
					<strong><?php echo esc_html( wpss_get_platform_name() ); ?></strong>
					<span><?php esc_html_e( 'SETTINGS', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<?php foreach ( $grouped_tabs as $group_name => $group_tabs ) : ?>
				<?php if ( empty( $group_tabs ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="wpss-settings-nav-group">
					<span class="wpss-settings-nav-group__label">
						<?php echo esc_html( $group_labels[ $group_name ] ?? strtoupper( $group_name ) ); ?>
					</span>
					<?php foreach ( $group_tabs as $tab_key => $tab_label ) : ?>
						<?php
						$icon   = $icon_map[ $tab_key ] ?? 'circle';
						$is_pro = ! in_array( $tab_key, $core_tabs, true );
						?>
						<a class="wpss-settings-nav-item"
							href="#<?php echo esc_attr( $tab_key ); ?>"
							data-section="<?php echo esc_attr( $tab_key ); ?>">
							<i data-lucide="<?php echo esc_attr( $icon ); ?>"></i>
							<?php echo esc_html( $tab_label ); ?>
							<?php if ( $is_pro ) : ?>
								<span class="wpss-pro-badge"><?php esc_html_e( 'Pro', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

			<div class="wpss-settings-nav-group" style="margin-top:auto;padding-top:12px;border-top:1px solid var(--wpss-admin-border);">
				<a class="wpss-settings-nav-item" href="https://store.wbcomdesigns.com/wp-sell-services/docs/" target="_blank" rel="noopener noreferrer" style="color:var(--wpss-admin-text-light);">
					<i data-lucide="book-open"></i>
					<?php esc_html_e( 'Documentation', 'wp-sell-services' ); ?>
				</a>
				<?php if ( ! defined( 'WPSS_PRO_VERSION' ) ) : ?>
					<a class="wpss-settings-nav-item" href="https://store.wbcomdesigns.com/wp-sell-services-pro/" target="_blank" rel="noopener noreferrer" style="color:#EA580C;font-weight:600;">
						<i data-lucide="zap"></i>
						<?php esc_html_e( 'Get Pro', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the content for a single tab.
	 *
	 * @param string        $tab_key   Tab identifier.
	 * @param array<string> $core_tabs List of core (non-Pro) tab slugs.
	 * @return void
	 */
	private function render_tab_content( string $tab_key, array $core_tabs ): void {
		if ( ! in_array( $tab_key, $core_tabs, true ) ) {
			/**
			 * Fires when rendering a custom settings tab added by Pro or extensions.
			 *
			 * @since 1.2.0
			 *
			 * @param string $tab_key The active tab slug.
			 */
			do_action( 'wpss_settings_tab_' . $tab_key );
			return;
		}

		switch ( $tab_key ) {
			case 'general':
				$this->render_general_tab();
				break;
			case 'pages':
				$this->render_pages_tab();
				break;
			case 'payments':
				$this->render_payments_tab();
				break;
			case 'commission':
				$this->render_commission_tab();
				break;
			case 'payouts':
				$this->render_payouts_tab();
				break;
			case 'vendor':
				$this->render_vendor_tab();
				break;
			case 'orders':
				$this->render_orders_tab();
				break;
			case 'emails':
				$this->render_emails_tab();
				break;
			case 'advanced':
				$this->render_advanced_tab();
				break;
		}
	}

	/**
	 * Render the General tab wrapped in a card.
	 *
	 * @return void
	 */
	private function render_general_tab(): void {
		?>
		<div class="wpss-card">
			<div class="wpss-card__head">
				<p class="wpss-card__title"><?php esc_html_e( 'GENERAL SETTINGS', 'wp-sell-services' ); ?></p>
				<p class="wpss-card__desc"><?php esc_html_e( 'Configure general platform settings.', 'wp-sell-services' ); ?></p>
			</div>
			<div class="wpss-card__body">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wpss_general' );
					do_settings_sections( 'wpss_general' );
					?>
					<div class="wpss-settings-section__footer">
						<?php submit_button( __( 'Save General Settings', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Pages tab wrapped in a card.
	 *
	 * @return void
	 */
	private function render_pages_tab(): void {
		?>
		<div class="wpss-card">
			<div class="wpss-card__head">
				<p class="wpss-card__title"><?php esc_html_e( 'PAGE SETTINGS', 'wp-sell-services' ); ?></p>
				<p class="wpss-card__desc"><?php esc_html_e( 'Assign pages for plugin functionality.', 'wp-sell-services' ); ?></p>
			</div>
			<div class="wpss-card__body">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wpss_pages' );
					do_settings_sections( 'wpss_pages' );
					?>
					<div class="wpss-settings-section__footer">
						<?php submit_button( __( 'Save Page Settings', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Payment Gateways tab — how money comes IN.
	 *
	 * Gateways only. Commission/tax moved to the Commission &amp; Tax tab and
	 * withdrawal config to the Payouts tab: this one panel used to carry all
	 * three plus every gateway, which meant eight independent forms and eight
	 * save buttons on a single screen, where editing across two cards silently
	 * discarded whichever you did not save.
	 *
	 * @return void
	 */
	private function render_payments_tab(): void {
		echo '<div class="wpss-settings-subhead">';
		echo '<p class="wpss-settings-subhead__title">' . esc_html__( 'Payment Gateways', 'wp-sell-services' ) . '</p>';
		echo '<p class="wpss-settings-subhead__desc">' . esc_html__( 'Configure how buyers pay for services. Each gateway can be enabled independently.', 'wp-sell-services' ) . '</p>';
		echo '</div>';

		$this->render_gateway_cards();

		/**
		 * Legacy payments-sections hook.
		 *
		 * Kept firing HERE so extensions written against the old single
		 * "Payments" tab keep rendering after the split. Free + Pro ship
		 * version-locked, so Pro's own renderers move to the precise
		 * wpss_settings_sections_commission / _payouts hooks below; this remains
		 * for third-party code.
		 *
		 * @since 1.1.0
		 */
		do_action( 'wpss_settings_sections_payments', $this );
	}

	/**
	 * Render the Commission &amp; Tax tab — what the platform KEEPS.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_commission_tab(): void {
		$this->render_tab_sections(
			'commission',
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
			)
		);
	}

	/**
	 * Render the Payouts tab — how money goes OUT to vendors.
	 *
	 * Sits next to the Withdrawals screen in the admin's mental model: this is
	 * where the rules are set, that is where the batch is worked. Previously
	 * buried inside "Payments", so an owner looking for payout configuration
	 * had to guess it lived under the tab about taking payments.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function render_payouts_tab(): void {
		$this->render_tab_sections(
			'payouts',
			array(
				array(
					'id'           => 'payouts',
					'title'        => __( 'Payout Settings', 'wp-sell-services' ),
					'description'  => __( 'Configure vendor withdrawal and payout settings.', 'wp-sell-services' ),
					'option_group' => 'wpss_payouts',
					'settings_id'  => 'wpss_payouts',
				),
			)
		);

		printf(
			'<p class="description wpss-settings-crosslink">%s</p>',
			wp_kses_post(
				sprintf(
					/* translators: %s: link to the Withdrawals admin screen. */
					__( 'Work the payout batch itself on the %s screen.', 'wp-sell-services' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wpss-withdrawals' ) ) . '">' . esc_html__( 'Withdrawals', 'wp-sell-services' ) . '</a>'
				)
			)
		);
	}

	/**
	 * Render the consolidated payment-gateway cards.
	 *
	 * Renders Stripe, PayPal, and Offline gateway settings as collapsible
	 * cards. Pro and extensions inject additional gateways via the
	 * wpss_gateway_cards and wpss_settings_sections_gateways hooks, which are
	 * preserved here unchanged so the Pro contract is unaffected by moving the
	 * cards from a standalone tab into the Payments tab.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function render_gateway_cards(): void {
		// When a cart plugin owns payment, say so before showing anything else.
		//
		// These gateways are for the standalone rail. With WooCommerce (or EDD,
		// FluentCart, SureCart) active, that plugin takes the money and none of
		// this is used - but the screen still rendered enabled toggles and key
		// fields, so an owner could configure Stripe here, see it saved, and
		// reasonably believe their store was taking card payments through it.
		// A saved setting that silently does nothing is worse than one that is
		// not offered.
		if ( ! wpss_uses_standalone_payments() ) {
			$adapter = function_exists( 'wpss_get_ecommerce_adapter' ) ? wpss_get_ecommerce_adapter() : null;
			$rail    = $adapter ? $adapter->get_name() : __( 'your store plugin', 'wp-sell-services' );

			printf(
				'<div class="notice notice-info inline wpss-gateway-notice"><p><strong>%s</strong> %s</p><p>%s</p></div>',
				esc_html__( 'Payments are handled by', 'wp-sell-services' ) . ' ' . esc_html( $rail ) . '.',
				esc_html__( 'The gateways below belong to this plugin and are not used while a store plugin is taking payment.', 'wp-sell-services' ),
				esc_html__( 'Configure your payment methods in that plugin instead. Switch Ecommerce Platform to Standalone under General if you want this plugin to take payment directly.', 'wp-sell-services' )
			);

			/**
			 * Fires instead of the gateway cards when a cart rail owns payment.
			 *
			 * @since 1.4.0
			 *
			 * @param string $rail Active ecommerce rail name.
			 */
			do_action( 'wpss_gateway_settings_owned_by_rail', $rail );

			return;
		}

		// Test Gateway section (only in debug mode).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->render_gateway_card(
				'test',
				__( 'Test Gateway', 'wp-sell-services' ),
				__( 'Test payment gateway for development. No real charges.', 'wp-sell-services' ),
				'wpss_test_gateway_settings'
			);
		}

		// Stripe section.
		$this->render_gateway_card(
			'stripe',
			__( 'Stripe', 'wp-sell-services' ),
			__( 'Accept credit card payments via Stripe.', 'wp-sell-services' ),
			'wpss_stripe_settings'
		);

		// PayPal section.
		$this->render_gateway_card(
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
		do_action( 'wpss_gateway_cards', $this );

		/**
		 * Unified gateway sections hook.
		 *
		 * Fires alongside the legacy wpss_gateway_cards hook.
		 * Pro and extensions can use either hook to inject gateway settings.
		 *
		 * @since 1.3.0
		 */
		do_action( 'wpss_settings_sections_gateways' );

		// Offline section.
		$this->render_gateway_card(
			'offline',
			__( 'Offline Payment', 'wp-sell-services' ),
			__( 'Accept bank transfer, cash, and other manual payments.', 'wp-sell-services' ),
			'wpss_offline_settings'
		);
	}

	/**
	 * Render a single gateway as a card section.
	 *
	 * @since 1.1.0
	 *
	 * @param string $gateway_id   Gateway identifier.
	 * @param string $title        Gateway title.
	 * @param string $description  Gateway description.
	 * @param string $option_group Option group for settings_fields().
	 * @return void
	 */
	public function render_gateway_card( string $gateway_id, string $title, string $description, string $option_group ): void {
		$plugin  = \WPSellServices\Core\Plugin::get_instance();
		$gateway = $plugin->get_payment_gateway( $gateway_id );
		$enabled = $gateway && $gateway->is_enabled();

		$badge_class = $enabled ? 'wpss-gateway-badge--enabled' : 'wpss-gateway-badge--disabled';
		$badge_text  = $enabled ? __( 'Enabled', 'wp-sell-services' ) : __( 'Disabled', 'wp-sell-services' );
		$collapsed   = $enabled ? '' : ' is-collapsed';
		?>
		<div class="wpss-card<?php echo esc_attr( $collapsed ); ?>" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
			<div class="wpss-card__head wpss-card__head--with-badge">
				<div>
					<p class="wpss-card__title"><?php echo esc_html( strtoupper( $title ) ); ?></p>
					<p class="wpss-card__desc"><?php echo esc_html( $description ); ?></p>
				</div>
				<span class="wpss-gateway-badge <?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( $badge_text ); ?>
				</span>
				<button type="button" class="wpss-card__toggle" aria-label="<?php esc_attr_e( 'Toggle', 'wp-sell-services' ); ?>">
					<i data-lucide="chevron-down"></i>
				</button>
			</div>
			<div class="wpss-card__body">
				<form method="post" action="options.php">
					<?php
					settings_fields( $option_group );
					/**
					 * Hook to render gateway-specific settings fields.
					 *
					 * @since 1.0.0
					 */
					do_action( "wpss_gateway_settings_{$gateway_id}" );
					?>
					<div class="wpss-settings-section__footer">
						<?php
						submit_button(
							sprintf(
								/* translators: %s: gateway name */
								__( 'Save %s Settings', 'wp-sell-services' ),
								$title
							),
							'primary',
							'submit',
							false
						);
						?>
					</div>
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
					'id'           => 'realtime',
					'title'        => __( 'Real-time (WebSockets)', 'wp-sell-services' ),
					'description'  => __( 'Push live messages and notifications to logged-in users. Works with Pusher.com or any self-hosted Pusher-compatible server such as Soketi.', 'wp-sell-services' ),
					'option_group' => 'wpss_realtime',
					'settings_id'  => 'wpss_realtime',
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
	 * Render the checkout badges section description.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function render_checkout_badges_section(): void {
		echo '<p>' . esc_html__( 'Short reassurance items shown to buyers on the checkout page. These are your words, on your storefront - edit or clear anything you do not want to say.', 'wp-sell-services' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Leave a row empty to hide it. Delivery time and revisions fall back to the real values from the package being bought, so they always match the order.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render the editable checkout badge rows.
	 *
	 * @since 1.4.0
	 *
	 * @param array<string, mixed> $args Field args.
	 * @return void
	 */
	public function render_checkout_badges_field( array $args ): void {
		$option = get_option( 'wpss_general', array() );
		$stored = isset( $option['checkout_badges'] ) && is_array( $option['checkout_badges'] ) ? $option['checkout_badges'] : array();

		// Same source the checkout uses, so the placeholders an owner sees are
		// exactly what buyers get when a row is left blank.
		$defaults = function_exists( 'wpss_get_checkout_badge_defaults' ) ? wpss_get_checkout_badge_defaults() : array();

		echo '<table class="widefat striped wpss-badge-editor"><thead><tr>';
		echo '<th style="width:12rem">' . esc_html__( 'Item', 'wp-sell-services' ) . '</th>';
		echo '<th>' . esc_html__( 'Heading', 'wp-sell-services' ) . '</th>';
		echo '<th>' . esc_html__( 'Sub-text', 'wp-sell-services' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $defaults as $key => $default ) {
			$title = (string) ( $stored[ $key ]['title'] ?? '' );
			$note  = (string) ( $stored[ $key ]['note'] ?? '' );

			printf(
				'<tr><td><strong>%s</strong></td>
				<td><input type="text" class="regular-text" name="wpss_general[checkout_badges][%s][title]" value="%s" placeholder="%s"></td>
				<td><input type="text" class="regular-text" name="wpss_general[checkout_badges][%s][note]" value="%s" placeholder="%s"></td></tr>',
				esc_html( $default['label'] ),
				esc_attr( $key ),
				esc_attr( $title ),
				esc_attr( $default['title'] ),
				esc_attr( $key ),
				esc_attr( $note ),
				esc_attr( $default['note'] )
			);
		}

		echo '</tbody></table>';
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
		echo '<p>' . esc_html__( 'Save yourself checking who has crossed the payout threshold: when enabled, a withdrawal request is raised automatically for every vendor who has. Approving and paying them stays with you, on the Withdrawals screen.', 'wp-sell-services' ) . '</p>';
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
	 * Render the checkout billing-field toggles.
	 *
	 * Twelve fields, most of them required, is a physical-goods checkout. A
	 * marketplace selling logo design has no use for street address, apartment,
	 * city, state or postcode, and each one is a reason to abandon
	 * (Basecamp #10159633185).
	 *
	 * Name and email cannot be switched off - an order has to be attributable to
	 * someone who can be contacted about it - so those render as disabled,
	 * checked boxes rather than being hidden, which would look like an omission.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	public function render_billing_fields_field(): void {
		// The unfiltered definitions, so a field the owner has already switched
		// off still appears here to be switched back on.
		$all      = wpss_get_all_billing_field_definitions();
		$settings = get_option( 'wpss_billing_field_settings', array() );
		$enabled  = isset( $settings['enabled'] ) && is_array( $settings['enabled'] )
			? array_map( 'strval', $settings['enabled'] )
			: array_map( 'strval', array_keys( $all ) );

		$locked = wpss_get_required_billing_fields();
		$preset = wpss_get_digital_billing_field_preset();

		echo '<fieldset class="wpss-billing-fields-toggles">';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Checkout billing fields', 'wp-sell-services' ) . '</legend>';

		foreach ( $all as $key => $definition ) {
			$is_locked  = in_array( $key, $locked, true );
			$is_checked = $is_locked || in_array( (string) $key, $enabled, true );

			printf(
				'<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="wpss_billing_field_settings[enabled][]" value="%1$s"%2$s%3$s> %4$s%5$s</label>',
				esc_attr( (string) $key ),
				checked( $is_checked, true, false ),
				$is_locked ? ' disabled' : '',
				esc_html( (string) ( $definition['label'] ?? $key ) ),
				$is_locked
					? ' <em>' . esc_html__( '(always collected)', 'wp-sell-services' ) . '</em>'
					: ''
			);

			// A disabled checkbox submits nothing, so post the locked keys
			// explicitly - otherwise saving the form would try to drop them and
			// the sanitiser would have to guess.
			if ( $is_locked ) {
				printf(
					'<input type="hidden" name="wpss_billing_field_settings[enabled][]" value="%s">',
					esc_attr( (string) $key )
				);
			}
		}

		echo '</fieldset>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Uncheck what your marketplace does not need. Address fields suit physical goods; a digital service rarely needs more than a name, an email and a country.', 'wp-sell-services' )
		);

		printf(
			'<p class="description">%s <code>%s</code></p>',
			esc_html__( 'Suggested set for digital services:', 'wp-sell-services' ),
			esc_html( implode( ', ', $preset ) )
		);
	}

	/**
	 * Sanitize the offline payment proof settings.
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $input Raw option value.
	 * @return array<string, bool> Clean value.
	 */
	public function sanitize_offline_receipt_settings( $input ): array {
		return array( 'enabled' => ! empty( $input['enabled'] ) );
	}

	/**
	 * Sanitize the billing-field toggles.
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $input Raw option value.
	 * @return array<string, array<int, string>> Clean value.
	 */
	public function sanitize_billing_field_settings( $input ): array {
		$all   = array_keys( wpss_get_all_billing_field_definitions() );
		$given = is_array( $input ) && isset( $input['enabled'] ) && is_array( $input['enabled'] )
			? array_map( 'sanitize_key', $input['enabled'] )
			: array();

		// Only real field keys, and never without the ones an order cannot do
		// without - a hand-crafted POST must not be able to strip the buyer's
		// name and email off checkout.
		$clean = array_values( array_intersect( $all, $given ) );
		$clean = array_values( array_unique( array_merge( $clean, wpss_get_required_billing_fields() ) ) );

		return array( 'enabled' => $clean );
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
	 * Render realtime section description.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function render_realtime_section(): void {
		echo '<p>' . esc_html__( 'Real-time updates power live order messages and notifications without page refreshes. Paste credentials from a Pusher.com app, or point the host at any self-hosted Pusher-compatible server (e.g. Soketi).', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render card sections for a settings tab.
	 *
	 * Each section gets its own card with an optional form. Fires a hook after
	 * all sections so Pro and extensions can inject additional cards.
	 *
	 * @since 1.3.0
	 *
	 * @param string            $tab_id   Tab identifier (e.g. 'payments', 'vendor').
	 * @param array<int, array> $sections Array of section definitions.
	 * @return void
	 */
	public function render_tab_sections( string $tab_id, array $sections ): void {
		foreach ( $sections as $section ) {
			$this->render_single_section( $section );
		}

		/**
		 * Fires after core sections are rendered for a tab.
		 *
		 * Pro and extensions use this to inject additional card sections
		 * into any settings tab.
		 *
		 * @since 1.3.0
		 *
		 * @param string $tab_id The tab being rendered.
		 */
		do_action( "wpss_settings_sections_{$tab_id}" );
	}

	/**
	 * Render a single card section.
	 *
	 * Used by render_tab_sections() and available publicly so Pro renderers
	 * can output sections in the unified card format.
	 *
	 * Section definition keys:
	 *   - id           (string) Section identifier.
	 *   - title        (string) Section heading text (auto-uppercased in card head).
	 *   - description  (string) Optional description paragraph.
	 *   - option_group (string) Option group for settings_fields(). Omit if using callback.
	 *   - settings_id  (string) Settings ID for do_settings_sections(). Omit if using callback.
	 *   - callback     (callable) Optional custom render callback (replaces default form).
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $section Section definition.
	 * @return void
	 */
	public function render_single_section( array $section ): void {
		$title = $section['title'] ?? '';
		?>
		<div class="wpss-card" data-section="<?php echo esc_attr( $section['id'] ?? '' ); ?>">
			<div class="wpss-card__head">
				<p class="wpss-card__title"><?php echo esc_html( strtoupper( $title ) ); ?></p>
				<?php if ( ! empty( $section['description'] ) ) : ?>
					<p class="wpss-card__desc"><?php echo esc_html( $section['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="wpss-card__body">
				<?php if ( ! empty( $section['callback'] ) ) : ?>
					<?php call_user_func( $section['callback'] ); ?>
				<?php elseif ( ! empty( $section['option_group'] ) ) : ?>
					<form method="post" action="options.php">
						<?php
						settings_fields( $section['option_group'] );
						do_settings_sections( $section['settings_id'] ?? $section['option_group'] );
						?>
						<div class="wpss-settings-section__footer">
							<?php
							submit_button(
								sprintf(
									/* translators: %s: section title */
									__( 'Save %s', 'wp-sell-services' ),
									$title ?: __( 'Changes', 'wp-sell-services' )
								),
								'primary',
								'submit',
								false
							);
							?>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>
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
	 * @since 1.0.0
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
					<i data-lucide="check-circle-2" class="wpss-icon" style="vertical-align: middle;" aria-hidden="true"></i>
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
	 * Render a masked webhook-secret / API-secret field.
	 *
	 * Security contract: the stored secret value is NEVER echoed into the page.
	 * When a secret is already saved, the field shows a fixed masked placeholder
	 * and an empty input — submitting an empty value keeps the existing secret
	 * (see sanitize_secret()). Admins type a new value only when rotating the
	 * secret, so the real secret never travels to the browser in markup, browser
	 * history, autofill caches, or page-source views.
	 *
	 * Expected $args keys:
	 *   - option_name (string) Option array name.
	 *   - field       (string) Field key within the option array.
	 *   - label       (string) Optional accessible label (falls back to field).
	 *   - description (string) Optional help text.
	 *   - prefix      (string) Optional non-secret prefix to hint which key is set
	 *                          (e.g. 'whsec_'). Shown for recognition only.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_secret_field( array $args ): void {
		$option_name = (string) ( $args['option_name'] ?? '' );
		$field       = (string) ( $args['field'] ?? '' );
		$options     = get_option( $option_name, array() );
		$options     = is_array( $options ) ? $options : array();
		$has_secret  = ! empty( $options[ $field ] );
		$label       = (string) ( $args['label'] ?? $field );
		$prefix_hint = isset( $args['prefix'] ) ? (string) $args['prefix'] : '';

		$placeholder = $has_secret
			? __( '••••••••••••  (saved — leave blank to keep)', 'wp-sell-services' )
			: __( 'Not set', 'wp-sell-services' );

		printf(
			'<div class="wpss-secret-field" data-has-secret="%1$s">',
			$has_secret ? '1' : '0'
		);

		printf(
			'<input type="password" id="%1$s" name="%2$s[%1$s]" value="" autocomplete="off" spellcheck="false" placeholder="%3$s" aria-label="%4$s" class="regular-text wpss-secret-field__input">',
			esc_attr( $field ),
			esc_attr( $option_name ),
			esc_attr( $placeholder ),
			esc_attr( $label )
		);

		printf(
			'<button type="button" class="button wpss-secret-field__toggle" aria-pressed="false" hidden>%s</button>',
			esc_html__( 'Reveal', 'wp-sell-services' )
		);

		echo '</div>';

		if ( $has_secret && '' !== $prefix_hint ) {
			printf(
				'<p class="description wpss-secret-field__status"><span class="wpss-secret-field__badge">%1$s</span> %2$s</p>',
				esc_html__( 'Configured', 'wp-sell-services' ),
				esc_html(
					sprintf(
						/* translators: %s: non-secret key prefix, e.g. whsec_ */
						__( 'A secret starting with %s is stored. Type a new value to replace it.', 'wp-sell-services' ),
						$prefix_hint
					)
				)
			);
		} elseif ( $has_secret ) {
			printf(
				'<p class="description wpss-secret-field__status"><span class="wpss-secret-field__badge">%1$s</span> %2$s</p>',
				esc_html__( 'Configured', 'wp-sell-services' ),
				esc_html__( 'A secret is stored. Type a new value to replace it.', 'wp-sell-services' )
			);
		}

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( (string) $args['description'] ) );
		}
	}

	/**
	 * Sanitize a masked secret value for a settings field.
	 *
	 * Companion to render_secret_field(). An empty submitted value means
	 * "keep the existing secret" (the input is always rendered blank), so the
	 * admin never has to re-enter the secret to save unrelated fields. A
	 * non-empty value replaces the stored secret.
	 *
	 * @since 1.6.0
	 *
	 * @param string $submitted   Raw submitted value.
	 * @param string $option_name Option array name holding the stored secret.
	 * @param string $field       Field key within the option array.
	 * @return string Sanitized secret to persist.
	 */
	public function sanitize_secret( string $submitted, string $option_name, string $field ): string {
		$submitted = trim( wp_unslash( $submitted ) );

		if ( '' === $submitted ) {
			$options = get_option( $option_name, array() );
			$options = is_array( $options ) ? $options : array();
			return isset( $options[ $field ] ) ? (string) $options[ $field ] : '';
		}

		return sanitize_text_field( $submitted );
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

		$min  = (float) ( $args['min'] ?? 0 );
		$max  = (float) ( $args['max'] ?? 100 );
		$step = $args['step'] ?? 1;

		// A STORED value outside this field's constraints must never make the
		// form unsubmittable.
		//
		// min/max/step are browser constraints on the whole FORM, not just this
		// field: if the stored value violates one, the browser refuses to submit
		// and — when the offending field is scrolled out of view — the admin
		// sees nothing at all. Save simply appears dead, and one bad value
		// silently bricks every other setting on that tab. Reproduced
		// 2026-07-23: auto_withdrawal_threshold = 40 against min = 100 made the
		// entire Payout Settings form unsubmittable, so clearance_days could not
		// be saved either.
		//
		// Such a value arrives from an older release with different bounds, a
		// migration, a filter, WP-CLI or a direct DB edit — and nothing warns.
		// So relax the constraints just enough to let the form through, keeping
		// the TRUE value on screen (clamping the display would show a number
		// that is not what is stored).
		//
		// Nothing is weakened by this: min/max/step are browser hints only, and
		// the sanitizer for each option group is the real authority on what may
		// be stored. Where a bound genuinely matters it must be enforced THERE
		// (as sanitize_payouts_settings does for clearance_days) — never left to
		// an attribute a posted request can ignore anyway.
		if ( is_numeric( $value ) ) {
			$numeric_value = (float) $value;
			$min           = min( $min, $numeric_value );
			$max           = max( $max, $numeric_value );

			// Step is measured from min, so a stored value off the ladder (120
			// against min 100 step 50) blocks submission just as hard as an
			// out-of-range one. Fall back to a free-form step for that render
			// only; valid values keep the intended stepping.
			if ( is_numeric( $step ) && (float) $step > 0 ) {
				$offset = ( $numeric_value - $min ) / (float) $step;
				if ( abs( $offset - round( $offset ) ) > 0.00001 ) {
					$step = 'any';
				}
			}
		}

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			esc_attr( (string) $value ),
			esc_attr( self::format_number_attr( $min ) ),
			esc_attr( self::format_number_attr( $max ) ),
			esc_attr( (string) $step )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Format a bound for a number input's min/max attribute.
	 *
	 * Keeps whole numbers whole — `min="0"`, not `min="0.0"` — while preserving
	 * a genuine decimal bound (a 2.5 % rate stays 2.5).
	 *
	 * @since 1.3.0
	 *
	 * @param float $bound Bound value.
	 * @return string Attribute-ready value.
	 */
	private static function format_number_attr( float $bound ): string {
		if ( abs( $bound - round( $bound ) ) < 0.00001 ) {
			return (string) (int) round( $bound );
		}

		return rtrim( rtrim( number_format( $bound, 4, '.', '' ), '0' ), '.' );
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
	 * Render the wallet provider select.
	 *
	 * Lists every registered wallet provider (Pro's WalletManager when
	 * present, otherwise the free registry) and writes the single canonical
	 * `wpss_wallet_provider` option both sides read.
	 *
	 * @return void
	 */
	public function render_wallet_provider_field(): void {
		$providers = array();

		if ( class_exists( '\\WPSellServicesPro\\Integrations\\Wallets\\WalletManager' ) ) {
			foreach ( \WPSellServicesPro\Integrations\Wallets\WalletManager::get_instance()->get_providers() as $id => $provider ) {
				$providers[ $id ] = method_exists( $provider, 'get_name' ) ? $provider->get_name() : ucfirst( $id );
			}
		}

		if ( empty( $providers ) ) {
			foreach ( \WPSellServices\Core\Plugin::get_instance()->get_wallet_providers() as $id => $provider ) {
				$providers[ $id ] = is_object( $provider ) && method_exists( $provider, 'get_name' ) ? $provider->get_name() : ucfirst( (string) $id );
			}
		}

		if ( empty( $providers ) ) {
			$providers = array( 'internal' => __( 'Internal Wallet', 'wp-sell-services' ) );
		}

		$selected = get_option( 'wpss_wallet_provider', 'internal' );
		?>
		<select id="wpss_wallet_provider" name="wpss_wallet_provider">
			<?php foreach ( $providers as $id => $label ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected, $id ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		// Says what the free plugin already does BEFORE describing what a
		// provider adds. The old wording opened with "wallet integration" and
		// "when their plugin is active", which read as "vendors have no wallet
		// until I install something" - so owners bought Pro for a balance they
		// already had (Basecamp 10235851532).
		?>
		<p class="description">
			<?php esc_html_e( 'Vendor earnings, balances and withdrawals already work with no wallet plugin installed - that is the Internal Wallet below. A provider does not add the wallet; it changes where the balance is held, so it can sit alongside one you already run. Each appears here only while its plugin is active.', 'wp-sell-services' ); ?>
		</p>
		<?php
	}

	/**
	 * Register tuning options that previously had no writer anywhere
	 * (Basecamp #9985175023). Each is a STANDALONE option (not part of a
	 * settings array) so the existing runtime read sites keep their keys.
	 *
	 * @return void
	 */
	private function register_tuning_settings(): void {
		$number = fn( string $option, string $group, string $section, string $label, array $args ) => array(
			register_setting( $group, $option, array( 'sanitize_callback' => $args['float'] ?? false ? 'floatval' : 'absint' ) ),
			add_settings_field(
				$option,
				$label,
				array( $this, 'render_standalone_number_field' ),
				$group,
				$section,
				array_merge( array( 'option' => $option ), $args )
			),
		);

		// General: order amount limits + currency decimals (exposed via GET /settings).
		$number(
			'wpss_decimal_places',
			'wpss_general',
			'wpss_general_section',
			__( 'Price Decimal Places', 'wp-sell-services' ),
			array(
				'default'     => 2,
				'min'         => 0,
				'max'         => 4,
				'description' => __( 'Decimal places used when formatting prices.', 'wp-sell-services' ),
			)
		);
		$number(
			'wpss_min_order_amount',
			'wpss_general',
			'wpss_general_section',
			__( 'Minimum Order Amount', 'wp-sell-services' ),
			array(
				'default'     => 5,
				'min'         => 0,
				'max'         => 100000,
				'float'       => true,
				'step'        => '0.01',
				'description' => __( 'Smallest order total buyers can place.', 'wp-sell-services' ),
			)
		);
		$number(
			'wpss_max_order_amount',
			'wpss_general',
			'wpss_general_section',
			__( 'Maximum Order Amount', 'wp-sell-services' ),
			array(
				'default'     => 10000,
				'min'         => 0,
				'max'         => 10000000,
				'float'       => true,
				'step'        => '0.01',
				'description' => __( 'Largest order total buyers can place.', 'wp-sell-services' ),
			)
		);

		// Orders: extension + buyer-request tuning.
		$number(
			'wpss_max_extension_days',
			'wpss_orders',
			'wpss_orders_section',
			__( 'Max Extension Days', 'wp-sell-services' ),
			array(
				'default'     => 14,
				'min'         => 1,
				'max'         => 90,
				'description' => __( 'Longest deadline extension a vendor can quote on an order.', 'wp-sell-services' ),
			)
		);
		$number(
			'wpss_request_expiry_days',
			'wpss_orders',
			'wpss_orders_section',
			__( 'Buyer Request Expiry (Days)', 'wp-sell-services' ),
			array(
				'default'     => 30,
				'min'         => 1,
				'max'         => 365,
				'description' => __( 'Open buyer requests expire after this many days.', 'wp-sell-services' ),
			)
		);

		// Disputes: SLA timing + escalation contact.
		add_settings_section(
			'wpss_disputes_section',
			__( 'Dispute Settings', 'wp-sell-services' ),
			'__return_empty_string',
			'wpss_orders'
		);
		$number(
			'wpss_dispute_response_days',
			'wpss_orders',
			'wpss_disputes_section',
			__( 'Response Window (Days)', 'wp-sell-services' ),
			array(
				'default'     => 3,
				'min'         => 1,
				'max'         => 30,
				'description' => __( 'Days the other party has to respond to a dispute before it can escalate.', 'wp-sell-services' ),
			)
		);
		$number(
			'wpss_dispute_reminder_days',
			'wpss_orders',
			'wpss_disputes_section',
			__( 'Reminder After (Days)', 'wp-sell-services' ),
			array(
				'default'     => 2,
				'min'         => 1,
				'max'         => 30,
				'description' => __( 'Days of silence before a dispute reminder email is sent.', 'wp-sell-services' ),
			)
		);
		$number(
			'wpss_dispute_auto_escalate_days',
			'wpss_orders',
			'wpss_disputes_section',
			__( 'Auto-Escalate After (Days)', 'wp-sell-services' ),
			array(
				'default'     => 7,
				'min'         => 1,
				'max'         => 60,
				'description' => __( 'Unresolved disputes escalate to admin review after this many days.', 'wp-sell-services' ),
			)
		);

		register_setting( 'wpss_orders', 'wpss_dispute_admin_email', array( 'sanitize_callback' => 'sanitize_email' ) );
		add_settings_field(
			'wpss_dispute_admin_email',
			__( 'Dispute Notifications Email', 'wp-sell-services' ),
			array( $this, 'render_standalone_email_field' ),
			'wpss_orders',
			'wpss_disputes_section',
			array(
				'option'      => 'wpss_dispute_admin_email',
				'description' => __( 'Escalated disputes are sent here. Leave blank to use the site admin email.', 'wp-sell-services' ),
			)
		);

		// Vendor: portfolio caps.
		$number(
			'wpss_max_portfolio_items',
			'wpss_vendor',
			'wpss_vendor_section',
			__( 'Max Portfolio Items', 'wp-sell-services' ),
			array(
				'default'     => 50,
				'min'         => 1,
				'max'         => 500,
				'description' => __( 'Portfolio items each vendor can publish.', 'wp-sell-services' ),
			)
		);
		$number(
			'wpss_max_featured_portfolio',
			'wpss_vendor',
			'wpss_vendor_section',
			__( 'Max Featured Portfolio Items', 'wp-sell-services' ),
			array(
				'default'     => 6,
				'min'         => 1,
				'max'         => 24,
				'description' => __( 'Portfolio items a vendor can mark as featured.', 'wp-sell-services' ),
			)
		);

		// Advanced: audit log retention.
		$number(
			'wpss_audit_log_retention_days',
			'wpss_advanced',
			'wpss_advanced_section',
			__( 'Audit Log Retention (Days)', 'wp-sell-services' ),
			array(
				'default'     => 0,
				'min'         => 0,
				'max'         => 3650,
				'description' => __( 'Audit log entries older than this are pruned. 0 keeps entries forever.', 'wp-sell-services' ),
			)
		);

		// Pages: confirmation + terms mapping.
		foreach ( array(
			'wpss_order_confirmation_page' => array( __( 'Order Confirmation Page', 'wp-sell-services' ), __( 'Custom thank-you page buyers land on after checkout. Leave unset to use the default order view.', 'wp-sell-services' ) ),
			'wpss_terms_page'              => array( __( 'Terms & Conditions Page', 'wp-sell-services' ), __( 'Map your own page - we never publish one. Linked from checkout and exposed to API clients. Privacy Policy comes from Settings > Privacy.', 'wp-sell-services' ) ),
		) as $option => $labels ) {
			register_setting( 'wpss_pages', $option, array( 'sanitize_callback' => 'absint' ) );
			add_settings_field(
				$option,
				$labels[0],
				array( $this, 'render_standalone_page_field' ),
				'wpss_pages',
				'wpss_pages_section',
				array(
					'option'      => $option,
					'description' => $labels[1],
				)
			);
		}
	}

	/**
	 * Render a number input for a standalone option.
	 *
	 * @param array<string, mixed> $args Field arguments (option, default, min, max, step, description).
	 * @return void
	 */
	public function render_standalone_number_field( array $args ): void {
		$option = (string) $args['option'];
		$value  = get_option( $option, $args['default'] ?? 0 );

		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text" min="%3$s" max="%4$s" step="%5$s">',
			esc_attr( $option ),
			esc_attr( (string) $value ),
			esc_attr( (string) ( $args['min'] ?? 0 ) ),
			esc_attr( (string) ( $args['max'] ?? 999999 ) ),
			esc_attr( (string) ( $args['step'] ?? '1' ) )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( (string) $args['description'] ) );
		}
	}

	/**
	 * Render an email input for a standalone option.
	 *
	 * @param array<string, mixed> $args Field arguments (option, description).
	 * @return void
	 */
	public function render_standalone_email_field( array $args ): void {
		$option = (string) $args['option'];

		printf(
			'<input type="email" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s">',
			esc_attr( $option ),
			esc_attr( (string) get_option( $option, '' ) ),
			esc_attr( get_option( 'admin_email' ) )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( (string) $args['description'] ) );
		}
	}

	/**
	 * Build the walker that disambiguates same-titled pages in a dropdown.
	 *
	 * @since 1.5.1
	 *
	 * @return PageDropdownWalker
	 */
	private function page_dropdown_walker(): PageDropdownWalker {
		return new PageDropdownWalker( get_pages( array( 'post_status' => 'publish' ) ) ?: array() );
	}

	/**
	 * Render a page dropdown for a standalone option.
	 *
	 * @param array<string, mixed> $args Field arguments (option, description).
	 * @return void
	 */
	public function render_standalone_page_field( array $args ): void {
		$option = (string) $args['option'];

		$dropdown = wp_dropdown_pages( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_dropdown_pages_wp_dropdown_pages
			array(
				'name'              => esc_attr( $option ),
				'id'                => esc_attr( $option ),
				'selected'          => (int) get_option( $option, 0 ),
				'show_option_none'  => esc_html__( '— Not set —', 'wp-sell-services' ),
				'option_none_value' => '0',
				'echo'              => 0,
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A Walker OBJECT passed as an argument, not output. The sniff sees `$this` inside a function call that can echo.
				'walker'            => $this->page_dropdown_walker(),
			)
		);

		echo wp_kses(
			$dropdown,
			array(
				'select' => array(
					'name'  => true,
					'id'    => true,
					'class' => true,
				),
				'option' => array(
					'value'    => true,
					'selected' => true,
					'class'    => true,
				),
			)
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( (string) $args['description'] ) );
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
			esc_html__( 'Select which e-commerce platform should handle service checkouts. Standalone checkout is included. Pro adds WooCommerce, EDD and FluentCart.', 'wp-sell-services' )
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
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A Walker OBJECT passed as an argument, not output. The sniff sees `$this` inside a function call that can echo.
				'walker'            => $this->page_dropdown_walker(),
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
		$input = is_array( $input ) ? $input : array();

		// Start from what is stored, not from an empty array. Returning only the
		// keys this panel renders silently deletes every other key in
		// wpss_general, so anything added later - a future tab, a migration, an
		// integration - is destroyed the next time an owner saves this screen.
		// Same defect that was fixed on the Pages tab.
		$existing  = get_option( 'wpss_general', array() );
		$existing  = is_array( $existing ) ? $existing : array();
		$sanitized = $existing;

		/*
		 * ABSENT IS NOT EMPTY.
		 *
		 * register_setting() hangs this sanitizer on sanitize_option_wpss_general,
		 * so it runs for EVERY update_option( 'wpss_general', ... ) - not just a
		 * settings-form submit. `wp option patch`, a migration, a Pro feature or a
		 * unit test all pass a PARTIAL array, and defaulting an absent key
		 * overwrites a stored value nobody asked to change.
		 *
		 * For `ecommerce_platform` that is not cosmetic: absent became 'auto',
		 * which resolves to whichever cart plugin is detected first, so a
		 * one-key patch silently moved the site's PAYMENT RAIL. Reproduced by
		 * accident here - `wp option patch insert wpss_general
		 * checkout_account_creation 1` flipped a standalone site to EDD, changing
		 * who takes the money, with nothing in the UI to show it.
		 *
		 * The guard below is the same one the `use_marketplace_cart_link` block
		 * further down already carries; these three keys never got it.
		 */
		if ( array_key_exists( 'platform_name', $input ) ) {
			// Platform name defaults to site name if empty.
			$platform_name              = sanitize_text_field( (string) $input['platform_name'] );
			$sanitized['platform_name'] = '' !== $platform_name ? $platform_name : get_bloginfo( 'name' );
		}

		if ( array_key_exists( 'currency', $input ) ) {
			$sanitized['currency'] = sanitize_text_field( (string) $input['currency'] );
		}

		$previous = (string) ( $existing['ecommerce_platform'] ?? 'auto' );

		if ( array_key_exists( 'ecommerce_platform', $input ) ) {
			$sanitized['ecommerce_platform'] = sanitize_key( (string) $input['ecommerce_platform'] );
		}

		// Changing the rail changes which rewrite rules exist. The standalone
		// adapter owns /wpss-payment/{gateway}/callback - the URL every gateway
		// webhook is delivered to - and registers it only while standalone is
		// the active rail. Without a flush, that rule is missing from the stored
		// rewrite rules after a switch, so every incoming webhook 404s until
		// somebody happens to re-save Permalinks. Nothing surfaces it: the
		// charge succeeds at the gateway and the order silently never goes paid.
		//
		// Deferred through the transient the plugin already uses rather than
		// flushed inline, because this runs during option save - before the new
		// rail's adapter has registered its rules, so an inline flush would
		// persist the OLD rail's rule set again.
		if ( $previous !== (string) ( $sanitized['ecommerce_platform'] ?? 'auto' ) ) {
			set_transient( 'wpss_flush_rewrite_rules', true, MINUTE_IN_SECONDS );
		}

		// Checkout reassurance badges. Owner-authored text for a public page,
		// so it is sanitised as plain text - no markup, no shortcodes.
		if ( array_key_exists( 'checkout_badges_enabled', $input ) ) {
			$sanitized['checkout_badges_enabled'] = ! empty( $input['checkout_badges_enabled'] );
		}

		// The field only renders when WooCommerce is active, so an absent key
		// must not clear a stored preference on a site that has since
		// deactivated Woo — same trap that once wiped wpss_pages['cart'].
		if ( array_key_exists( 'use_marketplace_cart_link', $input ) || class_exists( 'WooCommerce' ) ) {
			$sanitized['use_marketplace_cart_link'] = ! empty( $input['use_marketplace_cart_link'] );
		}

		if ( array_key_exists( 'checkout_account_creation', $input ) ) {
			$sanitized['checkout_account_creation'] = ! empty( $input['checkout_account_creation'] );
		}

		$badges = array();

		if ( isset( $input['checkout_badges'] ) && is_array( $input['checkout_badges'] ) ) {
			foreach ( wpss_get_checkout_badge_defaults() as $key => $unused ) {
				$badges[ $key ] = array(
					'title' => sanitize_text_field( (string) ( $input['checkout_badges'][ $key ]['title'] ?? '' ) ),
					'note'  => sanitize_text_field( (string) ( $input['checkout_badges'][ $key ]['note'] ?? '' ) ),
				);
			}
		}

		$sanitized['checkout_badges'] = $badges;

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

		// Tip commission rate is optional; empty string means "match the main
		// commission rate at runtime" rather than a saved 0 (which means
		// "no platform cut"). Admins can clear the field to revert to the
		// matching-rate behavior.
		$tip_rate_raw = $input['tip_commission_rate'] ?? '';
		if ( '' === $tip_rate_raw ) {
			$sanitized['tip_commission_rate'] = '';
		} else {
			$sanitized['tip_commission_rate'] = min( 50, max( 0, (float) $tip_rate_raw ) );
		}

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

		$sanitized['min_withdrawal'] = absint( $input['min_withdrawal'] ?? 50 );

		// 0 is a legitimate, supported value: pay vendors out the moment an
		// order completes (owner decision 2026-07-23 — see the field definition
		// for why the wallet ledger makes that safe). Capped at 90 to match the
		// field, enforced HERE and not just by the max attribute, which is only
		// a browser hint a posted value can sail straight past.
		$sanitized['clearance_days']            = min( 90, absint( $input['clearance_days'] ?? 0 ) );
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
		$sanitized['moderate_reviews']           = ! empty( $input['moderate_reviews'] );

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
	/**
	 * Sanitize message-email delivery settings.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed>|null $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_notification_delivery_settings( ?array $input ): array {
		$input = $input ?? array();

		return array(
			// Checkbox: absent means unticked, so an explicit false is stored
			// rather than the key going missing - the reader treats a missing
			// key as "on", which is how this defaulted unreachable before.
			'skip_message_email_when_online' => ! empty( $input['skip_message_email_when_online'] ),
			'message_email_delay_minutes'    => min( 120, max( 0, absint( $input['message_email_delay_minutes'] ?? 0 ) ) ),
		);
	}

	public function sanitize_notification_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		// Build keys dynamically from the same filter used to render the UI.
		$notification_types = $this->get_notification_types();

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
		$input = $input ?? array();

		// Derived from the page registry, not listed again here. This list
		// having to be kept in step by hand is what dropped `cart` (seeded by
		// the installer, absent from the whitelist) on the first save of this
		// panel; a registry key can no longer go missing from it.
		$page_keys = array_keys( wpss_get_page_definitions() );

		// Preserve the stored value for any key the submitted form did not
		// contain, instead of zeroing it.
		//
		// `become_vendor` is only REGISTERED as a field while vendor
		// registration is open (see the field loop), so with registration
		// closed the Pages panel posts no `become_vendor` at all — and the old
		// unconditional `absint( $input[$key] ?? 0 )` wrote 0 over a perfectly
		// good page ID. One save of an unrelated panel silently destroyed the
		// mapping, and reopening registration then pointed at nothing. Absent
		// key now means "unchanged", not "clear it".
		$existing = get_option( 'wpss_pages', array() );
		$existing = is_array( $existing ) ? $existing : array();

		// Start from what is already stored rather than from an empty array.
		// The old code returned ONLY the whitelisted keys, so any key the
		// Pages panel does not enumerate was destroyed on every save — `cart`
		// is seeded by the installer and was missing from the whitelist, so
		// saving this tab once silently deleted `wpss_pages['cart']` and left
		// no field able to restore it. Keys this panel does not own now
		// survive it.
		$sanitized = array_map( 'absint', $existing );

		foreach ( $page_keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$sanitized[ $key ] = absint( $input[ $key ] );
				continue;
			}

			$sanitized[ $key ] = absint( $existing[ $key ] ?? 0 );
		}

		// A re-mapped (or cleared) vendors page invalidates the discovery
		// cache behind wpss_get_vendors_page_id().
		delete_transient( 'wpss_vendors_page_lookup' );

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
	 * Sanitize realtime (WebSocket) settings.
	 *
	 * The secret follows the masked-field contract ({@see sanitize_secret()}):
	 * an empty submission keeps the stored secret, so admins never re-type it
	 * to save other fields and the secret never travels back to the browser.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|null $input Raw input (null when all checkboxes unchecked).
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_realtime_settings( ?array $input ): array {
		$input     = $input ?? array();
		$sanitized = array();

		$sanitized['enabled'] = ! empty( $input['enabled'] );
		$sanitized['app_id']  = sanitize_text_field( $input['app_id'] ?? '' );
		$sanitized['key']     = sanitize_text_field( $input['key'] ?? '' );
		$sanitized['secret']  = $this->sanitize_secret( (string) ( $input['secret'] ?? '' ), 'wpss_realtime_settings', 'secret' );

		// Host: bare hostname only — strip scheme and trailing slash so both
		// "soketi.example.com" and "https://soketi.example.com/" work.
		$host              = sanitize_text_field( $input['host'] ?? '' );
		$sanitized['host'] = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', untrailingslashit( $host ) );

		$cluster              = sanitize_text_field( $input['cluster'] ?? 'mt1' );
		$sanitized['cluster'] = '' !== $cluster ? $cluster : 'mt1';

		$port              = absint( $input['port'] ?? 443 );
		$sanitized['port'] = ( $port >= 1 && $port <= 65535 ) ? $port : 443;

		$sanitized['use_tls'] = ! empty( $input['use_tls'] );

		return $sanitized;
	}

	/**
	 * Get available currencies.
	 *
	 * @return array<string, string> Currency codes and labels.
	 */
	private function get_currencies(): array {
		// Derive from the single canonical currency list so the Settings
		// dropdown offers every supported currency (with its symbol) out of
		// the box - a site owner never needs a code snippet to make a
		// currency selectable.
		$currencies = array();
		foreach ( wpss_get_currencies() as $code => $name ) {
			$currencies[ $code ] = sprintf( '%1$s (%2$s)', $name, wpss_get_currency_symbol( $code ) );
		}

		/**
		 * Filter the currencies available in the Settings currency dropdown.
		 *
		 * The default list already covers every supported currency; this is
		 * an optional developer extension point, not a requirement for a
		 * currency to appear.
		 *
		 * @since 1.2.1
		 *
		 * @param array<string, string> $currencies Currency code => label map.
		 */
		return apply_filters( 'wpss_settings_currencies', $currencies );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $group Setting group.
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	public static function get( string $group, string $key, mixed $default = null ): mixed { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Public API; renaming is a named-argument BC break.
		$options = get_option( 'wpss_' . $group, array() );
		return $options[ $key ] ?? $default;
	}

	/**
	 * The switchable notification types.
	 *
	 * One list. This literal was written twice - once to render the checkboxes,
	 * once to sanitize them - and the copies were free to drift. EmailService
	 * gates three moderation emails on `notify_moderation`, a key neither copy
	 * carried, so no control could ever write it and those emails could not be
	 * turned off. A key the sanitizer will not persist is a key that can be
	 * read forever without existing.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, string> notification_key => label.
	 */
	public function get_notification_types(): array {
		/**
		 * Filter the switchable notification types.
		 *
		 * @since 1.1.0
		 *
		 * @param array $types Associative array of notification_key => label.
		 */
		return apply_filters(
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
				'tip_received'           => __( 'Tip Received', 'wp-sell-services' ),
				'milestone_proposed'     => __( 'Milestone Proposed', 'wp-sell-services' ),
				'milestone_paid'         => __( 'Milestone Paid', 'wp-sell-services' ),
				'milestone_submitted'    => __( 'Milestone Delivered', 'wp-sell-services' ),
				'milestone_approved'     => __( 'Milestone Approved', 'wp-sell-services' ),
				'extension_proposed'     => __( 'Extension Proposed', 'wp-sell-services' ),
				'extension_approved'     => __( 'Extension Approved', 'wp-sell-services' ),
				'extension_declined'     => __( 'Extension Declined', 'wp-sell-services' ),
				'moderation'             => __( 'Service Moderation', 'wp-sell-services' ),
			)
		);
	}
}
