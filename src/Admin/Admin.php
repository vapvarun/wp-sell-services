<?php
/**
 * Admin Class
 *
 * @package WPSellServices\Admin
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Admin\Metaboxes\ServiceMetabox;
use WPSellServices\Admin\Metaboxes\BuyerRequestMetabox;
use WPSellServices\Admin\OrderScreen;
use WPSellServices\Admin\Pages\ManualOrderPage;
use WPSellServices\Admin\Pages\VendorsPage;
use WPSellServices\Admin\Pages\ReportsPage;
use WPSellServices\Admin\Pages\ServiceModerationPage;
use WPSellServices\Admin\Pages\ReviewModerationPage;
use WPSellServices\Admin\Pages\WithdrawalsPage;
use WPSellServices\Admin\Pages\NotificationsPage;
use WPSellServices\Admin\Pages\AuditLogPage;
use WPSellServices\Admin\Pages\SetupWizardPage;
use WPSellServices\Admin\Pages\UpgradePage;
use WPSellServices\Admin\Tables\OrdersListTable;
use WPSellServices\Admin\Tables\DisputesListTable;
use WPSellServices\Models\Dispute;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\OrderService;
use WPSellServices\Assets\ScriptRegistry;

/**
 * Handles all admin-side functionality.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Manual order page instance.
	 *
	 * @var ManualOrderPage
	 */
	private ManualOrderPage $manual_order_page;

	/**
	 * Vendors page instance.
	 *
	 * @var VendorsPage
	 */
	private VendorsPage $vendors_page;

	/**
	 * Service moderation page instance.
	 *
	 * @var ServiceModerationPage
	 */
	private ServiceModerationPage $moderation_page;

	/**
	 * Reports queue page.
	 *
	 * @var ReportsPage
	 */
	private ReportsPage $reports_page;

	/**
	 * Review moderation page instance.
	 *
	 * @var ReviewModerationPage
	 */
	private ReviewModerationPage $review_moderation_page;

	/**
	 * Withdrawals page instance.
	 *
	 * @var WithdrawalsPage
	 */
	private WithdrawalsPage $withdrawals_page;

	/**
	 * Notifications viewer page instance.
	 *
	 * @var NotificationsPage
	 */
	private NotificationsPage $notifications_page;

	/**
	 * Audit log viewer page instance.
	 *
	 * @var AuditLogPage
	 */
	private AuditLogPage $audit_log_page;

	/**
	 * Setup wizard page instance.
	 *
	 * @var SetupWizardPage
	 */
	private SetupWizardPage $setup_wizard_page;

	/**
	 * Upgrade page instance (only when Pro is not active).
	 *
	 * @var UpgradePage|null
	 */
	private ?UpgradePage $upgrade_page = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings               = new Settings();
		$this->manual_order_page      = new ManualOrderPage();
		$this->vendors_page           = new VendorsPage();
		$this->moderation_page        = new ServiceModerationPage();
		$this->review_moderation_page = new ReviewModerationPage();
		$this->withdrawals_page       = new WithdrawalsPage();
		$this->notifications_page     = new NotificationsPage();
		$this->audit_log_page         = new AuditLogPage();
		$this->reports_page           = new ReportsPage();
		$this->setup_wizard_page      = new SetupWizardPage();

		if ( ! $this->is_pro_active() ) {
			$this->upgrade_page = new UpgradePage();
		}

		$this->init_metaboxes();
		$this->init_pages();
		$this->init_ajax_handlers();
		$this->init_menu_highlights();
	}

	/**
	 * Initialize menu highlight filters.
	 *
	 * @return void
	 */
	private function init_menu_highlights(): void {
		add_filter( 'parent_file', array( $this, 'set_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'set_submenu_file' ) );
		add_action( 'admin_menu', array( $this, 'reorder_admin_submenu' ), 999 );
		add_action( 'in_admin_header', array( $this, 'hide_third_party_notices' ), 1 );
	}

	/**
	 * Suppress third-party admin notices on plugin screens.
	 *
	 * Theme/other-plugin banners (TGMPA "recommended plugins", update nags,
	 * promo notices) crowd the plugin's admin pages and undermine trust.
	 * Removes every admin_notices / all_admin_notices callback that does not
	 * belong to WP Sell Services (Free or Pro), so the plugin's own notices
	 * still render.
	 *
	 * @return void
	 */
	public function hide_third_party_notices(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! $this->is_plugin_page( $screen->id ) ) {
			return;
		}

		global $wp_filter;

		foreach ( array( 'admin_notices', 'all_admin_notices' ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( ! $this->is_own_notice_callback( $callback['function'] ) ) {
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	}

	/**
	 * Check whether a notice callback belongs to this plugin (Free or Pro).
	 *
	 * @param callable|string|array<int, mixed> $callback Hooked callback.
	 * @return bool
	 */
	private function is_own_notice_callback( $callback ): bool {
		if ( is_string( $callback ) ) {
			return str_starts_with( $callback, 'wpss_' )
				|| str_starts_with( $callback, 'WPSellServices' );
		}

		if ( is_array( $callback ) && isset( $callback[0] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return str_starts_with( $class, 'WPSellServices' );
		}

		// Closures and other callables can't be attributed — treat as third-party.
		return false;
	}

	/**
	 * Reorder admin submenu items.
	 *
	 * @return void
	 */
	public function reorder_admin_submenu(): void {
		global $submenu;

		if ( ! isset( $submenu['wp-sell-services'] ) ) {
			return;
		}

		$menu_slug = 'wp-sell-services';
		$ordered   = array();
		$rest      = array();

		// Desired order, grouped by what the admin is DOING — not by which
		// class happens to register the page. The blocks below are the house
		// convention: Overview → Content → Moderation → Users → Money →
		// Insights → Config. Keeping every screen of one job together is the
		// whole point; the previous order split the three moderation queues
		// nine slots apart and buried Vendors inside the money block.
		$order = array(
			// --- Overview: where you land, and how you get set up. ---
			'wp-sell-services',                                              // Dashboard.
			// Setup Wizard only registers while setup is incomplete (or there
			// are no published services). Listing it here keeps it at the TOP
			// on exactly those fresh installs — omitting it sent the first
			// screen a new owner needs to the very bottom of the menu, under
			// "Upgrade to Pro", via the unlisted-items tail below.
			'wpss-setup-wizard',                                             // Setup Wizard (onboarding, conditional).

			// --- Content: the things being sold and asked for. Both CPTs stay
			// adjacent, with their taxonomies clustered after them rather than
			// wedged between Services and Requests. ---
			'edit.php?post_type=wpss_service',                               // All Services.
			'post-new.php?post_type=wpss_service',                           // Add New Service.
			'edit.php?post_type=wpss_request',                               // Buyer Requests.
			'post-new.php?post_type=wpss_request',                           // Add New Request.
			'edit-tags.php?taxonomy=wpss_service_category&post_type=wpss_service', // Categories.
			'edit-tags.php?taxonomy=wpss_service_tag&post_type=wpss_service',      // Tags.

			// --- Moderation: every queue an admin works in the same sitting.
			// Disputes belongs here, not marooned in the money block. ---
			'wpss-moderation',                                               // Service Moderation.
			'wpss-review-moderation',                                        // Review Moderation.
			'wpss-disputes',                                                 // Disputes.

			// --- Users: who is selling. Precedes the money they generate. ---
			'wpss-vendors',                                                  // Vendors.

			// --- Money: the full journey, in the order it happens. ---
			'wpss-orders',                                                   // Orders.
			'wpss-subscriptions',                                            // Subscriptions (Pro).
			'wpss-withdrawals',                                              // Withdrawals (payouts).

			// --- Insights: reporting and trails, read not acted on. ---
			'wpss-analytics',                                                // Analytics (Pro).
			'wpss-audit-log',                                                // Audit Log (forensic trail).
			'wpss-notifications',                                            // My Notifications (personal inbox).

			// --- Config: last, so day-to-day work is never scrolled past it. ---
			'wpss-settings',                                                 // Settings.
			'wpss-license',                                                  // License (Pro).
			'wpss-upgrade',                                                  // Upgrade to Pro (free only).
		);

		// Build a map of slug => menu item.
		$menu_map = array();
		foreach ( $submenu[ $menu_slug ] as $item ) {
			$menu_map[ $item[2] ] = $item;
		}

		// Add items in the desired order.
		foreach ( $order as $slug ) {
			if ( isset( $menu_map[ $slug ] ) ) {
				$ordered[] = $menu_map[ $slug ];
				unset( $menu_map[ $slug ] );
			}
		}

		// Add any remaining items not in the order array.
		foreach ( $menu_map as $item ) {
			$ordered[] = $item;
		}

		$submenu[ $menu_slug ] = $ordered;
	}

	/**
	 * Set the parent menu for CPT pages.
	 *
	 * @param string $parent_file The parent file.
	 * @return string
	 */
	public function set_parent_menu( string $parent_file ): string {
		global $current_screen;

		if ( ! $current_screen ) {
			return $parent_file;
		}

		// Set parent menu for Service and Buyer Request CPTs.
		if ( in_array( $current_screen->post_type, array( 'wpss_service', 'wpss_request' ), true ) ) {
			return 'wp-sell-services';
		}

		// Set parent menu for Service taxonomy.
		if ( 'wpss_service_category' === $current_screen->taxonomy || 'wpss_service_tag' === $current_screen->taxonomy ) {
			return 'wp-sell-services';
		}

		return $parent_file;
	}

	/**
	 * Set the submenu file for CPT pages.
	 *
	 * @param string|null $submenu_file The submenu file.
	 * @return string|null
	 */
	public function set_submenu_file( ?string $submenu_file ): ?string {
		global $current_screen;

		if ( ! $current_screen ) {
			return $submenu_file;
		}

		// Highlight correct submenu for Service CPT.
		if ( 'wpss_service' === $current_screen->post_type ) {
			if ( 'edit' === $current_screen->base ) {
				return 'edit.php?post_type=wpss_service';
			}
			if ( 'post' === $current_screen->base ) {
				return 'edit.php?post_type=wpss_service';
			}
		}

		// Highlight correct submenu for Buyer Request CPT.
		if ( 'wpss_request' === $current_screen->post_type ) {
			return 'edit.php?post_type=wpss_request';
		}

		// Highlight correct submenu for Service Category taxonomy.
		if ( 'wpss_service_category' === $current_screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=wpss_service_category&post_type=wpss_service';
		}

		// Highlight correct submenu for Service Tag taxonomy.
		if ( 'wpss_service_tag' === $current_screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=wpss_service_tag&post_type=wpss_service';
		}

		return $submenu_file;
	}

	/**
	 * Initialize metaboxes.
	 *
	 * @return void
	 */
	private function init_metaboxes(): void {
		$service_metabox = new ServiceMetabox();
		$service_metabox->init();

		$request_metabox = new BuyerRequestMetabox();
		$request_metabox->init();

		$order_screen = new OrderScreen();
		$order_screen->init();
	}

	/**
	 * Initialize admin pages.
	 *
	 * @return void
	 */
	private function init_pages(): void {
		$this->manual_order_page->init();
		$this->vendors_page->init();
		$this->moderation_page->init();
		$this->review_moderation_page->init();
		$this->withdrawals_page->init();
		$this->notifications_page->init();
		$this->audit_log_page->init();
		$this->reports_page->init();
		// review_moderation_page and notifications_page were each init()ed a
		// second time here. Harmless — WordPress keys callbacks by identity, so
		// the repeat replaced rather than duplicated the registration — but it
		// read as though those two pages needed something the others did not.
		$this->setup_wizard_page->init();

		if ( $this->upgrade_page ) {
			$this->upgrade_page->init();
		}

		// Page setup admin notice.
		add_action( 'admin_notices', array( $this, 'check_page_setup_notice' ) );
		add_action( 'wp_ajax_wpss_dismiss_pages_notice', array( $this, 'ajax_dismiss_pages_notice' ) );

		// Demo payments notice. Deliberately NOT dismissible - see the method.
		add_action( 'admin_notices', array( $this, 'demo_payments_notice' ) );
		add_action( 'admin_notices', array( $this, 'order_files_public_notice' ) );
		add_action( 'admin_notices', array( $this, 'missing_terms_notice' ) );
		add_action( 'wp_ajax_wpss_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'admin_post_wpss_disable_demo_payments', array( $this, 'disable_demo_payments' ) );
	}

	/**
	 * Warn when order files are reachable straight from the browser.
	 *
	 * Order files are written outside the document root wherever the parent of
	 * ABSPATH is writable, which is most hosts. Where it is not - shared hosting,
	 * typically - they fall back to a guarded directory inside uploads, and the
	 * guard is a .htaccess that Apache reads and nginx ignores entirely.
	 *
	 * That is not hypothetical: the first host this was tested against served a
	 * file from that directory at HTTP 200 with its contents, deny file present.
	 * The paths are guessable (order id, then filename), so on such a host a
	 * buyer's brief is public and nothing says so.
	 *
	 * wpss_order_files_are_public() answers by writing a canary and asking the
	 * web server for it - the only way to know, since whether a deny rule is
	 * honoured depends on configuration PHP cannot read. It caches for a week,
	 * so this costs one HTTP round trip per week per site.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	public function order_files_public_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wpss_order_files_are_public' ) ) {
			return;
		}

		// Only on our own screens: this is an owner's problem to fix, not
		// something to shout about while they are writing a post.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || false === strpos( (string) $screen->id, 'wpss' ) ) {
			return;
		}

		if ( true !== wpss_order_files_are_public() ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p>%s</p></div>',
			esc_html__( 'Order files can be downloaded by anyone.', 'wp-sell-services' ),
			esc_html__( 'This server could not store them outside the web root, and it is ignoring the deny rule we wrote alongside them. Requirement briefs and delivered work are readable by anyone who guesses the address.', 'wp-sell-services' ),
			esc_html__( 'Ask your host to block direct access to the wpss-order-files directory, or to make the folder above WordPress writable so the files can be moved out of the web root entirely.', 'wp-sell-services' )
		);
	}

	/**
	 * Tell the owner their marketplace is taking money with no Terms page.
	 *
	 * Gated on wpss_has_live_gateway() rather than on the plugin being active,
	 * because that is the point at which this stops being a setup step and
	 * starts being a live risk: buyers are paying and there is nothing telling
	 * them what they agreed to. A site still being built is left alone.
	 *
	 * Dismissible, unlike demo_payments_notice(): an owner may have decided
	 * against publishing terms, and nagging them forever teaches people to
	 * ignore our notices - which costs us the one that matters. The dismissal
	 * records WHICH gateways were live at the time, so turning on another one
	 * later asks the question again against the new configuration.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function missing_terms_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wpss_has_live_gateway' ) ) {
			return;
		}

		// Our own screens only. An owner writing a post does not need to be
		// interrupted about a settings page.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || false === strpos( (string) $screen->id, 'wpss' ) ) {
			return;
		}

		$terms_id = (int) get_option( 'wpss_terms_page' );

		if ( $terms_id && 'publish' === get_post_status( $terms_id ) ) {
			return;
		}

		if ( ! wpss_has_live_gateway() ) {
			return;
		}

		$signature = $this->live_gateway_signature();

		if ( get_user_meta( get_current_user_id(), '_wpss_terms_notice_dismissed', true ) === $signature ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible wpss-dismissible-notice" data-notice="terms" data-signature="%s" data-nonce="%s"><p><strong>%s</strong> %s</p><p><a class="button" href="%s">%s</a></p></div>',
			esc_attr( $signature ),
			esc_attr( wp_create_nonce( 'wpss_dismiss_notice' ) ),
			esc_html__( 'Buyers are paying on this site, but no Terms page is mapped.', 'wp-sell-services' ),
			esc_html__( 'Checkout has nothing to link to, so buyers agree to nothing in writing - which is the gap owners usually find out about during a dispute. Map a page you have written; we never publish one for you.', 'wp-sell-services' ),
			esc_url( wpss_get_settings_url( 'pages' ) ),
			esc_html__( 'Map a Terms page', 'wp-sell-services' )
		);
	}

	/**
	 * Identify the currently live gateway set.
	 *
	 * Used so a dismissal applies to the configuration it was made against.
	 * Enabling a second gateway is a new decision about taking money, and the
	 * Terms question deserves asking again.
	 *
	 * @since 1.7.0
	 * @return string Short stable hash of the live gateway ids.
	 */
	private function live_gateway_signature(): string {
		$ids = array();

		foreach ( wpss()->get_payment_gateways() as $id => $gateway ) {
			if ( 'test' === $id ) {
				continue;
			}
			if ( $gateway instanceof \WPSellServices\Integrations\Contracts\PaymentGatewayInterface && $gateway->is_enabled() ) {
				$ids[] = (string) $id;
			}
		}

		sort( $ids );

		return substr( md5( implode( ',', $ids ) ), 0, 12 );
	}

	/**
	 * Dismiss one of the plugin's dismissible admin notices.
	 *
	 * Generic on purpose. check_page_setup_notice() shipped with its own
	 * action and its own handler; a second notice doing the same would have
	 * made a second pair, and a third a third. The notice key is whitelisted,
	 * so this cannot be used to write arbitrary user meta.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function ajax_dismiss_notice(): void {
		check_ajax_referer( 'wpss_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$allowed = array( 'terms' => '_wpss_terms_notice_dismissed' );
		$notice  = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';

		if ( ! isset( $allowed[ $notice ] ) ) {
			wp_send_json_error();
		}

		// The signature is stored rather than a bare true, so the notice
		// returns if the gateway configuration changes underneath it.
		$signature = isset( $_POST['signature'] ) ? sanitize_key( wp_unslash( $_POST['signature'] ) ) : '1';

		update_user_meta( get_current_user_id(), $allowed[ $notice ], $signature );
		wp_send_json_success();
	}

	/**
	 * Warn, on every admin screen, while payments are simulated.
	 *
	 * A fresh install registers the Test gateway so the marketplace works end
	 * to end before any gateway credentials exist. That is the right default -
	 * an owner can take a service from listing to paid order on day one - but
	 * it must never be quiet, because the failure mode is a real store selling
	 * to real buyers and settling nothing.
	 *
	 * No dismiss control, on purpose. The notice goes away by fixing its cause:
	 * configure a gateway, and wpss_demo_payments_enabled() is false on the
	 * next request.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function demo_payments_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wpss_demo_payments_enabled' ) ) {
			return;
		}

		if ( ! wpss_demo_payments_enabled() ) {
			return;
		}

		// The opt-out link is what makes wpss_demo_payments reachable. Without
		// it the option is read but never written, so an owner running a live
		// standalone store before configuring a gateway had no way to stop a
		// simulated checkout appearing to their buyers.
		$turn_off = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpss_disable_demo_payments' ),
			'wpss_disable_demo_payments'
		);

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p><a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a></p></div>',
			esc_html__( 'Demo payments are on.', 'wp-sell-services' ),
			esc_html__( 'Checkout is simulated so you can test the whole buying flow - no money moves and no card is charged. Configure a payment gateway before taking real orders; this notice clears itself once one is ready.', 'wp-sell-services' ),
			esc_url( admin_url( 'admin.php?page=wpss-settings#payments' ) ),
			esc_html__( 'Set up payments', 'wp-sell-services' ),
			esc_url( $turn_off ),
			esc_html__( 'Turn demo payments off', 'wp-sell-services' )
		);
	}

	/**
	 * Turn the simulated checkout off for good.
	 *
	 * An explicit opt-out, so a store that is live but has not configured a
	 * gateway yet shows buyers nothing rather than a demo checkout. Reversed by
	 * configuring a real gateway, which is what the notice asks for anyway.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function disable_demo_payments(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change payment settings.', 'wp-sell-services' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'wpss_disable_demo_payments' );

		update_option( 'wpss_demo_payments', 'no' );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Show an admin notice when required pages are unmapped.
	 *
	 * Lists missing pages and links to the setup wizard or settings page.
	 * Dismissible via user meta so it does not persist after dismissal.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_page_setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show if dismissed.
		if ( get_user_meta( get_current_user_id(), '_wpss_pages_notice_dismissed', true ) ) {
			return;
		}

		$required_pages = wpss_get_required_pages();

		$pages   = get_option( 'wpss_pages', array() );
		$missing = array();

		foreach ( $required_pages as $key => $label ) {
			$page_id = (int) ( $pages[ $key ] ?? 0 );
			if ( ! $page_id || ! get_post( $page_id ) || 'publish' !== get_post_status( $page_id ) ) {
				$missing[] = $label;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		$settings_url = wpss_get_settings_url( 'pages' );
		$wizard_url   = admin_url( 'admin.php?page=wpss-setup-wizard' );

		printf(
			'<div class="notice notice-warning is-dismissible wpss-pages-notice" data-nonce="%s"><p><strong>%s</strong> %s: <em>%s</em>. <a href="%s">%s</a> %s <a href="%s">%s</a>.</p></div>',
			esc_attr( wp_create_nonce( 'wpss_dismiss_pages_notice' ) ),
			esc_html__( 'WP Sell Services:', 'wp-sell-services' ),
			esc_html__( 'The following pages need to be set up', 'wp-sell-services' ),
			esc_html( implode( ', ', $missing ) ),
			esc_url( $wizard_url ),
			esc_html__( 'Run Setup Wizard', 'wp-sell-services' ),
			esc_html__( 'or', 'wp-sell-services' ),
			esc_url( $settings_url ),
			esc_html__( 'configure pages manually', 'wp-sell-services' )
		);

		// Inline script to handle dismiss via AJAX.
		?>
		<?php
	}

	/**
	 * AJAX handler to dismiss the pages setup notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_dismiss_pages_notice(): void {
		check_ajax_referer( 'wpss_dismiss_pages_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), '_wpss_pages_notice_dismissed', true );
		wp_send_json_success();
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	private function init_ajax_handlers(): void {
		add_action( 'wp_ajax_wpss_get_service_packages', array( $this, 'ajax_get_service_packages' ) );
		add_action( 'wp_ajax_wpss_import_demo_content', array( $this, 'ajax_import_demo_content' ) );
		add_action( 'wp_ajax_wpss_delete_demo_content', array( $this, 'ajax_delete_demo_content' ) );
		add_action( 'admin_post_wpss_update_order', array( $this, 'handle_update_order' ) );
		add_action( 'admin_post_wpss_resolve_dispute', array( $this, 'handle_resolve_dispute' ) );
	}

	/**
	 * AJAX handler to get service packages.
	 *
	 * @return void
	 */
	public function ajax_get_service_packages(): void {
		check_ajax_referer( 'wpss_create_manual_order', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$service_id = absint( $_POST['service_id'] ?? 0 );

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service ID.', 'wp-sell-services' ) ) );
		}

		$packages = wpss_get_service_packages( $service_id );

		$formatted_packages = array();
		foreach ( $packages as $index => $package ) {
			$formatted_packages[] = array(
				'id'              => $index,
				'name'            => $package['name'] ?? __( 'Standard', 'wp-sell-services' ),
				'price'           => (float) ( $package['price'] ?? 0 ),
				'formatted_price' => wpss_format_price( (float) ( $package['price'] ?? 0 ) ),
				'delivery_days'   => (int) ( $package['delivery_days'] ?? 7 ),
				'revisions'       => (int) ( $package['revisions'] ?? 0 ),
			);
		}

		wp_send_json_success( array( 'packages' => $formatted_packages ) );
	}

	/**
	 * Handle order status update from admin-post.php.
	 *
	 * @return void
	 */
	public function handle_update_order(): void {
		// Verify nonce.
		if ( ! isset( $_POST['wpss_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_order_nonce'] ) ), 'wpss_update_order' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// Check capabilities — admins can update any order, vendors can update their own.
		$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$has_access = current_user_can( 'manage_options' );

		if ( ! $has_access && current_user_can( 'wpss_vendor_orders' ) && $order_id ) {
			// Vendors can only update orders they are the vendor on.
			$check_order = wpss_get_order( $order_id );
			if ( $check_order && (int) $check_order->vendor_id === get_current_user_id() ) {
				$has_access = true;
			}
		}

		if ( ! $has_access ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}
		$status = isset( $_POST['order_status'] ) ? sanitize_key( $_POST['order_status'] ) : '';

		if ( ! $order_id || ! $status ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// Valid statuses.
		$valid_statuses = array(
			'pending_payment',
			'pending_requirements',
			'in_progress',
			'delivered',
			'revision_requested',
			'completed',
			'cancelled',
			'disputed',
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_die( esc_html__( 'Invalid status.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// Use OrderService for transition validation, timestamps, logging, and hooks.
		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order ) {
			$update_status = 'error';
		} elseif ( $order->status === $status ) {
			$update_status = 'unchanged';
		} else {
			$result        = $order_service->update_status( $order_id, $status );
			$update_status = $result ? '1' : 'error';
		}

		$redirect_url = add_query_arg(
			array(
				'page'     => 'wpss-orders',
				'action'   => 'view',
				'order_id' => $order_id,
				'updated'  => $update_status,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle dispute resolution form submission.
	 *
	 * @return void
	 */
	public function handle_resolve_dispute(): void {
		if ( ! isset( $_POST['wpss_dispute_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_dispute_nonce'] ) ), 'wpss_resolve_dispute' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		$dispute_id = isset( $_POST['dispute_id'] ) ? absint( $_POST['dispute_id'] ) : 0;
		$status     = isset( $_POST['dispute_status'] ) ? sanitize_key( $_POST['dispute_status'] ) : '';
		$resolution = isset( $_POST['resolution'] ) ? sanitize_key( $_POST['resolution'] ) : '';
		$notes      = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';

		if ( ! $dispute_id || ! $status ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		$dispute_service = new DisputeService();

		if ( 'resolved' === $status ) {
			if ( ! $resolution ) {
				// If resolving, require a resolution type.
				wp_die( esc_html__( 'Please select a resolution type when resolving a dispute.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
			}

			// Cast IS the sanitisation for a money field - there is no
			// sanitize_* that returns a float. Nonce verified above. Same
			// pattern as OrderScreen's refund box.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$refund_amount = isset( $_POST['refund_amount'] ) ? (float) wp_unslash( $_POST['refund_amount'] ) : 0.0;

			// A Partial Refund with no amount used to reach resolve() as 0.0,
			// which apply_refund_status() read as "refund everything" - so the
			// buyer got 100% back on an order recorded as partially refunded
			// (Basecamp 10240143362). Validated here so the admin gets a
			// sentence rather than a silently refused transition.
			if ( DisputeService::RESOLUTION_PARTIAL_REFUND === $resolution ) {
				$dispute = $dispute_service->get( $dispute_id );
				$order   = $dispute ? wpss_get_order( (int) $dispute->order_id ) : null;
				$total   = $order ? (float) $order->total : 0.0;

				if ( $refund_amount <= 0 ) {
					wp_die( esc_html__( 'Enter the amount to refund. A partial refund needs a number greater than zero.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
				}

				if ( $total > 0 && $refund_amount >= $total ) {
					wp_die(
						esc_html(
							sprintf(
								/* translators: %s: formatted order total. */
								__( 'A partial refund must be less than the %s order total. Choose Full Refund to return all of it.', 'wp-sell-services' ),
								wpss_format_price( $total, $order->currency ?? '' )
							)
						),
						'',
						array( 'back_link' => true )
					);
				}
			}

			$result = $dispute_service->resolve( $dispute_id, $resolution, $notes, get_current_user_id(), $refund_amount );
		} else {
			$result = $dispute_service->update_status( $dispute_id, $status, $notes );

			// Also save resolution and notes if provided with a non-resolved status.
			if ( $result && ( $resolution || $notes ) ) {
				global $wpdb;
				$update_data = array( 'updated_at' => current_time( 'mysql' ) );

				if ( $resolution ) {
					$update_data['resolution'] = sanitize_key( $resolution );
				}
				if ( $notes ) {
					$update_data['resolution_notes'] = sanitize_textarea_field( $notes );
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'wpss_disputes',
					$update_data,
					array( 'id' => $dispute_id )
				);
			}
		}

		$updated = ( false !== $result && ! is_wp_error( $result ) ) ? '1' : '0';

		$redirect_url = add_query_arg(
			array(
				'page'       => 'wpss-disputes',
				'action'     => 'view',
				'dispute_id' => $dispute_id,
				'updated'    => $updated,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		global $post_type;

		$current_screen = get_current_screen();
		$is_taxonomy    = $current_screen && in_array( $current_screen->taxonomy, array( 'wpss_service_category', 'wpss_service_tag' ), true );

		// Load on plugin pages, CPT edit screens, or taxonomy screens.
		$load_assets = $this->is_plugin_page( $hook )
			|| ( $post_type && in_array( $post_type, array( 'wpss_service', 'wpss_request' ), true ) )
			|| $is_taxonomy;

		if ( ! $load_assets ) {
			return;
		}

		// Design-system tokens. Until 1.6.1 these were registered ONLY on the
		// frontend, so in admin --wpss-primary and the --wpss-vendor-green alias
		// were undefined and every `var( --wpss-vendor-green, #1dbf73 )` fell
		// through to its hardcoded fallback -- the whole admin kept rendering the
		// retired green accent the 1.6.0 work was meant to end.
		wpss_register_design_system( true );

		wp_enqueue_style(
			'wpss-admin',
			\WPSS_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wpss-design-system' ),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-admin', 'rtl', 'replace' );

		// Settings page CSS (loaded only on settings page).
		if ( $this->is_settings_page( $hook ) ) {
			wp_enqueue_style(
				'wpss-admin-settings',
				\WPSS_PLUGIN_URL . 'assets/css/admin-settings.css',
				array( 'wpss-admin' ),
				\WPSS_VERSION
			);
			wp_style_add_data( 'wpss-admin-settings', 'rtl', 'replace' );
		}

		// Color picker for category taxonomy.
		if ( $is_taxonomy ) {
			wp_enqueue_style( 'wp-color-picker' );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		global $post_type;

		$current_screen = get_current_screen();
		$is_taxonomy    = $current_screen && in_array( $current_screen->taxonomy, array( 'wpss_service_category', 'wpss_service_tag' ), true );

		// Load on plugin pages, CPT edit screens, or taxonomy screens.
		$load_assets = $this->is_plugin_page( $hook )
			|| ( $post_type && in_array( $post_type, array( 'wpss_service', 'wpss_request' ), true ) )
			|| $is_taxonomy;

		if ( ! $load_assets ) {
			return;
		}

		wp_enqueue_media();

		// Shared UI primitives: wpssConfirm (Promise modal) + wpssToast fallback.
		// Enqueued globally on all WPSS admin surfaces so Pro's admin.js can rely
		// on window.wpssConfirm being present (loaded in footer before pro script).
		ScriptRegistry::enqueue_ui();

		// Settings saved via the options.php round-trip reload with
		// settings-updated=true but custom admin pages never render core's
		// notice — surface the confirmation as a design-system toast.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag from core options.php redirect.
		if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) ) ) {
			wp_add_inline_script(
				'wpss-ui',
				sprintf(
					'(function(){var fire=function(){if(window.wpssToast){wpssToast(%s,"success");}else{setTimeout(fire,200);}};if("loading"===document.readyState){document.addEventListener("DOMContentLoaded",fire);}else{fire();}})();',
					wp_json_encode( __( 'Settings saved.', 'wp-sell-services' ) )
				)
			);
		}

		wp_enqueue_script(
			'wpss-admin',
			\WPSS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
			\WPSS_VERSION,
			true
		);
		wp_set_script_translations( 'wpss-admin', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'wpss-admin',
			'wpssAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wpss_admin_nonce' ),
				'currencyFormat'   => wpss_get_currency_symbol() . '%s',
				'currencyDecimals' => wpss_get_currency_decimals(),
				'i18n'             => array(
					'selectImage'  => __( 'Select Image', 'wp-sell-services' ),
					'selectImages' => __( 'Select Images', 'wp-sell-services' ),
					'useImage'     => __( 'Use Image', 'wp-sell-services' ),
					'confirm'      => __( 'Are you sure?', 'wp-sell-services' ),
				),
			)
		);

		// Lucide icons — all plugin admin pages (Packet H house-style).
		wp_enqueue_script(
			'lucide',
			\WPSS_PLUGIN_URL . 'assets/js/vendor/lucide.min.js',
			array(),
			'0.460.0',
			true
		);

		// Unified icon bootstrap: listens for wpss:icons:refresh so dynamically
		// injected modal / AJAX markup re-hydrates Lucide icons.
		wp_enqueue_script(
			'wpss-icons',
			\WPSS_PLUGIN_URL . 'assets/js/wpss-icons.js',
			array( 'lucide' ),
			\WPSS_VERSION,
			true
		);

		// Legacy admin-icons.js kept for BC (single createIcons call) but
		// wpss-icons.js is now the canonical listener.
		wp_enqueue_script(
			'wpss-admin-icons',
			\WPSS_PLUGIN_URL . 'assets/js/admin-icons.js',
			array( 'lucide', 'wpss-icons' ),
			\WPSS_VERSION,
			true
		);
		wp_set_script_translations( 'wpss-admin-icons', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

		// Reports queue: confirm before suspending or closing an account.
		if ( 'sell-services_page_wpss-reports' === $hook ) {
			wp_enqueue_script(
				'wpss-admin-reports',
				\WPSS_PLUGIN_URL . 'assets/js/admin-reports.js',
				array( 'wpss-ui' ),
				\WPSS_VERSION,
				true
			);
			wp_set_script_translations( 'wpss-admin-reports', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );
		}

		// Settings page scripts.
		if ( $this->is_settings_page( $hook ) ) {
			wp_enqueue_script(
				'wpss-admin-settings-nav',
				\WPSS_PLUGIN_URL . 'assets/js/admin-settings-nav.js',
				array( 'wpss-ui', 'wp-i18n' ),
				\WPSS_VERSION,
				true
			);
			wp_set_script_translations( 'wpss-admin-settings-nav', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

			// Pages tab: create page AJAX.
			wp_enqueue_script(
				'wpss-admin-settings-pages',
				\WPSS_PLUGIN_URL . 'assets/js/admin-settings-pages.js',
				array( 'jquery', 'wpss-ui' ),
				\WPSS_VERSION,
				true
			);
			wp_set_script_translations( 'wpss-admin-settings-pages', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

			wp_localize_script(
				'wpss-admin-settings-pages',
				'wpssSettingsPages',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'homeUrl'        => home_url( '/' ),
					'nonce'          => wp_create_nonce( 'wpss_settings_nonce' ),
					'noTitle'        => __( 'Page title not defined.', 'wp-sell-services' ),
					'confirmCreate'  => __( 'Create a new page titled', 'wp-sell-services' ),
					'creating'       => __( 'Creating...', 'wp-sell-services' ),
					'existingLinked' => __( 'Existing Page Linked!', 'wp-sell-services' ),
					'pageCreated'    => __( 'Page Created!', 'wp-sell-services' ),
					'createPage'     => __( 'Create Page', 'wp-sell-services' ),
					'createFailed'   => __( 'Failed to create page.', 'wp-sell-services' ),
					'ajaxError'      => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
				)
			);

			// Emails tab: test email AJAX.
			wp_enqueue_script(
				'wpss-admin-settings-emails',
				\WPSS_PLUGIN_URL . 'assets/js/admin-settings-emails.js',
				array( 'jquery' ),
				\WPSS_VERSION,
				true
			);
			wp_set_script_translations( 'wpss-admin-settings-emails', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

			wp_localize_script(
				'wpss-admin-settings-emails',
				'wpssSettingsEmails',
				array(
					'sending'   => __( 'Sending...', 'wp-sell-services' ),
					'sendTest'  => __( 'Send Test Email', 'wp-sell-services' ),
					'ajaxError' => __( 'Request failed. Please try again.', 'wp-sell-services' ),
				)
			);

			// Advanced tab: demo content AJAX.
			wp_enqueue_script(
				'wpss-admin-settings-demo',
				\WPSS_PLUGIN_URL . 'assets/js/admin-settings-demo.js',
				array( 'jquery' ),
				\WPSS_VERSION,
				true
			);
			wp_set_script_translations( 'wpss-admin-settings-demo', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

			wp_localize_script(
				'wpss-admin-settings-demo',
				'wpssSettingsDemo',
				array(
					'confirmImport' => __( 'Import demo content? This will create sample services, vendors, and categories.', 'wp-sell-services' ),
					'importing'     => __( 'Importing...', 'wp-sell-services' ),
					'pleaseWait'    => __( 'Please wait, this may take a moment...', 'wp-sell-services' ),
					'importSuccess' => __( 'Demo content imported successfully!', 'wp-sell-services' ),
					'importFailed'  => __( 'Import failed.', 'wp-sell-services' ),
					'importBtn'     => __( 'Import Demo Content', 'wp-sell-services' ),
					'confirmDelete' => __( 'Delete all demo content? This will permanently remove demo services, vendors, and empty categories.', 'wp-sell-services' ),
					'deleting'      => __( 'Deleting...', 'wp-sell-services' ),
					'removing'      => __( 'Removing demo content...', 'wp-sell-services' ),
					'deleteSuccess' => __( 'Demo content deleted successfully!', 'wp-sell-services' ),
					'deleteFailed'  => __( 'Deletion failed.', 'wp-sell-services' ),
					'deleteBtn'     => __( 'Delete Demo Content', 'wp-sell-services' ),
					'ajaxError'     => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
				)
			);
		}

		// Color picker for category taxonomy.
		if ( $is_taxonomy ) {
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		/**
		 * Filter the admin menu label for white-labelling.
		 *
		 * @since 1.1.0
		 *
		 * @param string $label The menu label.
		 */
		$menu_label = apply_filters( 'wpss_admin_menu_label', __( 'Sell Services', 'wp-sell-services' ) );

		// Packet H: use a data-URL Lucide `store` glyph (instead of a legacy
		// dashicon class) so the admin-menu entry carries the house-style
		// icon consistently with the rest of the plugin.
		// Not obfuscation: WordPress's `menu_icon` API accepts a dashicon
		// class or an SVG data URL, and a data URL must be base64. The SVG
		// source is inline and readable directly above/below.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required encoding for an SVG data URL.
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/></svg>'
		);

		add_menu_page(
			__( 'WP Sell Services', 'wp-sell-services' ),
			$menu_label,
			'edit_posts',
			'wp-sell-services',
			array( $this, 'render_dashboard_page' ),
			$menu_icon,
			30
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Dashboard', 'wp-sell-services' ),
			__( 'Dashboard', 'wp-sell-services' ),
			'manage_options',
			'wp-sell-services',
			array( $this, 'render_dashboard_page' )
		);

		// Note: Services CPT menus (All Services, Add New) are automatically added
		// since show_in_menu is set to 'wp-sell-services' in ServicePostType.

		// Add taxonomy submenu (not auto-added when using show_in_menu).
		add_submenu_page(
			'wp-sell-services',
			__( 'Service Categories', 'wp-sell-services' ),
			__( 'Categories', 'wp-sell-services' ),
			'manage_categories',
			'edit-tags.php?taxonomy=wpss_service_category&post_type=wpss_service'
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Service Tags', 'wp-sell-services' ),
			__( 'Tags', 'wp-sell-services' ),
			'manage_categories',
			'edit-tags.php?taxonomy=wpss_service_tag&post_type=wpss_service'
		);

		// Note: Buyer Requests CPT menus are automatically added
		// since show_in_menu is set to 'wp-sell-services' in BuyerRequestPostType.

		$orders_hook = add_submenu_page(
			'wp-sell-services',
			__( 'Orders', 'wp-sell-services' ),
			__( 'Orders', 'wp-sell-services' ),
			'wpss_manage_orders',
			'wpss-orders',
			array( $this, 'render_orders_page' )
		);

		if ( $orders_hook ) {
			add_action( 'load-' . $orders_hook, array( $this, 'add_orders_help_tabs' ) );
		}

		$disputes_hook = add_submenu_page(
			'wp-sell-services',
			__( 'Disputes', 'wp-sell-services' ),
			__( 'Disputes', 'wp-sell-services' ),
			'wpss_manage_disputes',
			'wpss-disputes',
			array( $this, 'render_disputes_page' )
		);

		if ( $disputes_hook ) {
			add_action( 'load-' . $disputes_hook, array( $this, 'add_disputes_help_tabs' ) );
		}

		add_submenu_page(
			'wp-sell-services',
			__( 'Settings', 'wp-sell-services' ),
			__( 'Settings', 'wp-sell-services' ),
			'wpss_manage_settings',
			'wpss-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$this->settings->init();
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return bool
	 */
	private function is_plugin_page( string $hook ): bool {
		$plugin_pages = array(
			'toplevel_page_wp-sell-services',
			'sell-services_page_wpss-orders',
			'sell-services_page_wpss-vendors',
			'sell-services_page_wpss-withdrawals',
			'sell-services_page_wpss-moderation',
			'sell-services_page_wpss-review-moderation',
			'sell-services_page_wpss-disputes',
			'sell-services_page_wpss-settings',
			'sell-services_page_wpss-notifications',
			'sell-services_page_wpss-audit-log',
			// A new admin page that is not listed here still renders — it just
			// renders with no plugin CSS at all, which looks like a styling bug
			// rather than a missing registration. Add the hook suffix in the
			// same commit that adds the page.
			'sell-services_page_wpss-reports',
			'admin_page_wpss-create-order',
			'admin_page_wpss-setup-wizard',
			'sell-services_page_wpss-upgrade',
		);

		if ( in_array( $hook, $plugin_pages, true ) ) {
			return true;
		}

		// Support white-labeled menu slugs (e.g., my-marketplace_page_wpss-settings).
		return str_contains( $hook, 'wpss' );
	}

	/**
	 * Check if the current page is a settings page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return bool
	 */
	private function is_settings_page( string $hook ): bool {
		return str_contains( $hook, 'wpss-settings' );
	}

	/**
	 * Check if the Pro plugin is active.
	 *
	 * @return bool
	 */
	private function is_pro_active(): bool {
		return defined( 'WPSS_PRO_VERSION' );
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-sell-services' ) );
		}

		global $wpdb;

		// Get stats.
		$orders_table   = $wpdb->prefix . 'wpss_orders';
		$services_count = wp_count_posts( 'wpss_service' );
		$requests_count = wp_count_posts( 'wpss_request' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status IN ('pending_payment', 'pending_requirements') THEN 1 ELSE 0 END) as pending
			FROM {$orders_table}"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revenue = $wpdb->get_var(
			"SELECT SUM(total) FROM {$orders_table} WHERE status = 'completed'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent_orders = $wpdb->get_results(
			"SELECT * FROM {$orders_table} ORDER BY created_at DESC LIMIT 5"
		);

		// Daily-cadence action items: things that need admin attention today.
		// Each tile links to the page where the work happens, so the
		// dashboard answers "what's on my plate?" before "how big is the
		// marketplace?" (see plans/1.1.0-ADMIN-OVERWHELM-AUDIT.md finding #4).
		$disputes_table    = $wpdb->prefix . 'wpss_disputes';
		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';
		$vendor_profiles   = $wpdb->prefix . 'wpss_vendor_profiles';
		$vendor_settings   = get_option( 'wpss_vendor', array() );
		$is_approval_mode  = isset( $vendor_settings['vendor_registration'] ) && 'approval' === $vendor_settings['vendor_registration'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$open_disputes = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$disputes_table} WHERE status IN ('open', 'in_review', 'evidence_pending')"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_withdrawals = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$withdrawals_table} WHERE status = 'pending'"
		);
		$pending_vendors     = 0;
		if ( $is_approval_mode ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$pending_vendors = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$vendor_profiles} WHERE status = 'pending'"
			);
		}
		?>
		<div class="wrap wpss-dashboard-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'WP Sell Services Dashboard', 'wp-sell-services' ); ?></h1>
			<button type="button" class="page-title-action wpss-tour-replay">
				<?php esc_html_e( 'Replay guide', 'wp-sell-services' ); ?>
			</button>
			<hr class="wp-header-end">

			<div class="wpss-dashboard-grid">
				<!-- Daily action items — counts >0 are highlighted, =0 dim out
					so admin can confirm "nothing on my plate today" at a glance. -->
				<h2 class="wpss-stats-heading"><?php esc_html_e( 'Action items', 'wp-sell-services' ); ?></h2>
				<div class="wpss-stats-row wpss-stats-row--action">
					<a class="wpss-stat-card wpss-stat-card--action <?php echo $open_disputes > 0 ? 'is-active' : 'is-empty'; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-disputes' ) ); ?>">
						<i data-lucide="shield-alert" class="wpss-icon wpss-stat-icon" style="color: <?php echo $open_disputes > 0 ? '#d63638' : '#a7aaad'; ?>;" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( (string) $open_disputes ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Open disputes', 'wp-sell-services' ); ?></span>
						</div>
					</a>
					<a class="wpss-stat-card wpss-stat-card--action <?php echo $pending_withdrawals > 0 ? 'is-active' : 'is-empty'; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals&status=pending' ) ); ?>">
						<i data-lucide="banknote" class="wpss-icon wpss-stat-icon" style="color: <?php echo $pending_withdrawals > 0 ? '#dba617' : '#a7aaad'; ?>;" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( (string) $pending_withdrawals ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Pending withdrawals', 'wp-sell-services' ); ?></span>
						</div>
					</a>
					<?php if ( $is_approval_mode ) : ?>
					<a class="wpss-stat-card wpss-stat-card--action <?php echo $pending_vendors > 0 ? 'is-active' : 'is-empty'; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors&status=pending' ) ); ?>">
						<i data-lucide="user-check" class="wpss-icon wpss-stat-icon" style="color: <?php echo $pending_vendors > 0 ? '#2271b1' : '#a7aaad'; ?>;" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( (string) $pending_vendors ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Pending vendor approvals', 'wp-sell-services' ); ?></span>
						</div>
					</a>
					<?php endif; ?>
					<a class="wpss-stat-card wpss-stat-card--action <?php echo ( $order_stats->pending ?? 0 ) > 0 ? 'is-active' : 'is-empty'; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&status=pending_payment' ) ); ?>">
						<i data-lucide="clock" class="wpss-icon wpss-stat-icon" style="color: <?php echo ( $order_stats->pending ?? 0 ) > 0 ? '#dba617' : '#a7aaad'; ?>;" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( (string) ( $order_stats->pending ?? 0 ) ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Pending orders', 'wp-sell-services' ); ?></span>
						</div>
					</a>
				</div>

				<!-- Marketplace health (rolling totals — read-only at-a-glance). -->
				<h2 class="wpss-stats-heading"><?php esc_html_e( 'Marketplace health', 'wp-sell-services' ); ?></h2>
				<div class="wpss-stats-row">
					<div class="wpss-stat-card">
						<i data-lucide="shopping-cart" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( $order_stats->total ?? 0 ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
						</div>
					</div>

					<div class="wpss-stat-card">
						<i data-lucide="clock" class="wpss-icon wpss-stat-icon wpss-stat-icon--pending" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( $order_stats->in_progress ?? 0 ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'In Progress', 'wp-sell-services' ); ?></span>
						</div>
					</div>

					<div class="wpss-stat-card">
						<i data-lucide="check-circle-2" class="wpss-icon wpss-stat-icon wpss-stat-icon--success" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( $order_stats->completed ?? 0 ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
						</div>
					</div>

					<div class="wpss-stat-card">
						<i data-lucide="banknote" class="wpss-icon wpss-stat-icon wpss-stat-icon--revenue" aria-hidden="true"></i>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( wpss_format_price( (float) ( $revenue ?? 0 ) ) ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Total Revenue', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>

				<div class="wpss-dashboard-columns">
					<!-- Quick Actions -->
					<div class="wpss-dashboard-box">
						<h2><?php esc_html_e( 'Quick Actions', 'wp-sell-services' ); ?></h2>
						<div class="wpss-quick-actions">
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="wpss-action-btn">
								<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
								<?php esc_html_e( 'Add Service', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders' ) ); ?>" class="wpss-action-btn">
								<i data-lucide="list" class="wpss-icon" aria-hidden="true"></i>
								<?php esc_html_e( 'View Orders', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service' ) ); ?>" class="wpss-action-btn">
								<i data-lucide="wrench" class="wpss-icon" aria-hidden="true"></i>
								<?php esc_html_e( 'Manage Services', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings' ) ); ?>" class="wpss-action-btn">
								<i data-lucide="settings" class="wpss-icon" aria-hidden="true"></i>
								<?php esc_html_e( 'Settings', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-audit-log' ) ); ?>" class="wpss-action-btn">
								<i data-lucide="scroll-text" class="wpss-icon" aria-hidden="true"></i>
								<?php esc_html_e( 'Audit Log', 'wp-sell-services' ); ?>
							</a>
						</div>
					</div>

					<!-- Content Stats -->
					<div class="wpss-dashboard-box">
						<h2><?php esc_html_e( 'Content Overview', 'wp-sell-services' ); ?></h2>
						<ul class="wpss-content-stats">
							<li>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service' ) ); ?>">
									<span class="count"><?php echo esc_html( $services_count->publish ?? 0 ); ?></span>
									<?php esc_html_e( 'Published Services', 'wp-sell-services' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service&post_status=draft' ) ); ?>">
									<span class="count"><?php echo esc_html( $services_count->draft ?? 0 ); ?></span>
									<?php esc_html_e( 'Draft Services', 'wp-sell-services' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_request' ) ); ?>">
									<span class="count"><?php echo esc_html( $requests_count->publish ?? 0 ); ?></span>
									<?php esc_html_e( 'Buyer Requests', 'wp-sell-services' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&status=pending_payment' ) ); ?>">
									<span class="count"><?php echo esc_html( $order_stats->pending ?? 0 ); ?></span>
									<?php esc_html_e( 'Pending Orders', 'wp-sell-services' ); ?>
								</a>
							</li>
						</ul>
					</div>
				</div>

				<!-- Recent Orders -->
				<div class="wpss-dashboard-box wpss-recent-orders">
					<h2><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h2>
					<?php if ( ! empty( $recent_orders ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_orders as $order ) : ?>
									<?php $wpss_subject = wpss_get_order_subject( $order, 'admin' ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $order->id ) ); ?>">
												#<?php echo esc_html( $order->order_number ); ?>
											</a>
										</td>
										<td><?php echo esc_html( $wpss_subject['label'] ); ?></td>
										<td><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></td>
										<td>
											<span class="<?php echo esc_attr( wpss_status_class( $order->status ) ); ?>">
												<?php echo esc_html( ucwords( str_replace( '_', ' ', $order->status ) ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( wp_date( 'M j, Y', strtotime( $order->created_at ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="wpss-no-data"><?php esc_html_e( 'No orders yet.', 'wp-sell-services' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render orders page.
	 *
	 * @return void
	 */
	public function render_orders_page(): void {
		// Check if viewing a specific order.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( 'view' === $action && $order_id ) {
			$this->render_order_detail( $order_id );
			return;
		}

		// Handle bulk actions.
		$this->process_order_bulk_actions( $action );

		$list_table = new OrdersListTable();
		$list_table->prepare_items();
		$has_items = ! empty( $list_table->items );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h1>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-create-order' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Create Order', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="wpss-orders">
				<div class="wpss-list-card">
					<div class="wpss-list-card__filters">
						<?php
						$list_table->views();
						$list_table->search_box( __( 'Search Orders', 'wp-sell-services' ), 'order' );
						?>
					</div>
					<div class="wpss-list-card__body">
						<?php
						if ( $has_items ) {
							$list_table->display();
						} else {
							$list_table->no_items();
						}
						?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Process order bulk actions.
	 *
	 * @param string $action The bulk action.
	 * @return void
	 */
	private function process_order_bulk_actions( string $action ): void {
		$bulk_actions = array( 'mark_completed', 'mark_cancelled' );

		if ( ! in_array( $action, $bulk_actions, true ) ) {
			return;
		}

		check_admin_referer( 'bulk-orders' );

		// Bulk status changes act on arbitrary order IDs (not ownership-scoped),
		// so require admin-level access in addition to the nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$order_ids = isset( $_GET['order_ids'] ) ? array_map( 'absint', (array) $_GET['order_ids'] ) : array();

		if ( empty( $order_ids ) ) {
			return;
		}

		$status_map = array(
			'mark_completed' => 'completed',
			'mark_cancelled' => 'cancelled',
		);

		$new_status    = $status_map[ $action ];
		$updated       = 0;
		$order_service = new OrderService();

		foreach ( $order_ids as $id ) {
			$result = $order_service->update_status( (int) $id, $new_status );

			if ( $result ) {
				++$updated;
			}
		}

		if ( $updated > 0 ) {
			add_action(
				'admin_notices',
				function () use ( $updated ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						/* translators: %d: number of orders updated */
						esc_html( sprintf( _n( '%d order updated.', '%d orders updated.', $updated, 'wp-sell-services' ), $updated ) )
					);
				}
			);
		}
	}

	/**
	 * Process dispute bulk actions.
	 *
	 * @param string $action The bulk action.
	 * @return void
	 */
	private function process_disputes_bulk_actions( string $action ): void {
		$bulk_actions = array( 'mark_pending_review', 'mark_escalated', 'mark_closed' );

		if ( ! in_array( $action, $bulk_actions, true ) ) {
			return;
		}

		check_admin_referer( 'bulk-disputes' );

		// Bulk status changes act on arbitrary dispute IDs (not ownership-scoped),
		// so require admin-level access in addition to the nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$dispute_ids = isset( $_GET['dispute_ids'] ) ? array_map( 'absint', (array) $_GET['dispute_ids'] ) : array();

		if ( empty( $dispute_ids ) ) {
			return;
		}

		$status_map = array(
			'mark_pending_review' => DisputeService::STATUS_PENDING,
			'mark_escalated'      => DisputeService::STATUS_ESCALATED,
			'mark_closed'         => DisputeService::STATUS_CLOSED,
		);

		$new_status      = $status_map[ $action ];
		$dispute_service = new DisputeService();
		$updated         = 0;

		foreach ( $dispute_ids as $dispute_id ) {
			if ( $dispute_service->update_status( $dispute_id, $new_status ) ) {
				++$updated;
			}
		}

		if ( $updated > 0 ) {
			add_action(
				'admin_notices',
				function () use ( $updated ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						/* translators: %d: number of disputes updated */
						esc_html( sprintf( _n( '%d dispute updated.', '%d disputes updated.', $updated, 'wp-sell-services' ), $updated ) )
					);
				}
			);
		}
	}

	/**
	 * Render order detail view.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function render_order_detail( int $order_id ): void {
		global $wpdb;
		$conversations_table = $wpdb->prefix . 'wpss_conversations';
		$deliveries_table    = $wpdb->prefix . 'wpss_deliveries';

		// Hydrate the MODEL, never a raw row.
		//
		// This screen renders model methods (get_package_name(), get_requirements())
		// and treats timestamps as objects. A raw $wpdb->get_row() is a plain
		// stdClass, so `$order->get_package_name()` was a hard fatal that killed
		// the screen for EVERY order, on every rail - the card renders "There has
		// been a critical error" and everything below it is lost. $wpdb also
		// returns every column as a string, which breaks the typed helpers this
		// screen passes values into. ServiceOrder::find() does the coercion once,
		// in one place.
		$order = \WPSellServices\Models\ServiceOrder::find( $order_id );

		if ( ! $order ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		// Vendor access check — vendors can only view their own orders.
		if ( ! current_user_can( 'manage_options' ) && (int) $order->vendor_id !== get_current_user_id() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this order.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		// What the order is FOR. Resolved through the shared helper rather than
		// get_post( $order->service_id ): request orders and sub-orders carry
		// service_id = 0 by design, and this screen used to render them all as
		// an italic "Deleted" (Basecamp 10208199238).
		$subject = wpss_get_order_subject( $order, 'admin' );
		$vendor  = get_userdata( $order->vendor_id );
		$buyer   = get_userdata( $order->customer_id );

		// Get messages via the messages table joined with conversations.
		$messages_table = $wpdb->prefix . 'wpss_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.* FROM {$messages_table} m
				 INNER JOIN {$conversations_table} c ON m.conversation_id = c.id
				 WHERE c.order_id = %d
				 ORDER BY m.created_at ASC",
				$order_id
			)
		);

		// Get deliveries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deliveries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$deliveries_table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		$statuses = array(
			'pending_payment'      => __( 'Pending Payment', 'wp-sell-services' ),
			'pending_requirements' => __( 'Waiting for Requirements', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'             => __( 'Disputed', 'wp-sell-services' ),
		);
		?>
		<div class="wrap wpss-order-detail">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order #%s', 'wp-sell-services' ),
					esc_html( $order->order_number )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Back to Orders', 'wp-sell-services' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php
			/*
			 * An order's dispute, surfaced ON the order.
			 *
			 * A dispute does not force the order's status: an order can be
			 * Completed and still carry an open dispute, which is exactly the
			 * case support most needs to see. This screen showed status,
			 * messages, deliveries and the refund controls with no mention of
			 * the dispute at all, so an admin could read the whole order,
			 * process a refund, and never learn a conflict was live on it
			 * (Basecamp 10208211608).
			 *
			 * Resolved disputes are shown too, quieter: "how did that end?" is
			 * asked from the order, and the answer drives whether a refund is
			 * appropriate. Same reasoning as the buyer-facing order view, which
			 * uses the same reader.
			 */
			$wpss_order_dispute = ( new \WPSellServices\Services\DisputeService() )->get_by_order( $order_id );

			if ( $wpss_order_dispute ) :
				$wpss_dispute_statuses = \WPSellServices\Models\Dispute::get_statuses();
				$wpss_dispute_reasons  = wpss_get_dispute_reasons();
				$wpss_dispute_live     = in_array(
					$wpss_order_dispute->status,
					array(
						\WPSellServices\Models\Dispute::STATUS_OPEN,
						\WPSellServices\Models\Dispute::STATUS_PENDING,
						\WPSellServices\Models\Dispute::STATUS_ESCALATED,
					),
					true
				);
				$wpss_dispute_url      = admin_url(
					'admin.php?page=wpss-disputes&action=view&dispute_id=' . (int) $wpss_order_dispute->id
				);
				?>
				<div class="notice <?php echo $wpss_dispute_live ? 'notice-error' : 'notice-info'; ?> wpss-order-dispute-notice" style="margin-top: 20px;">
					<p>
						<strong>
							<?php
							if ( $wpss_dispute_live ) {
								printf(
									/* translators: %s: dispute status label, e.g. Open or Escalated */
									esc_html__( 'Active dispute on this order (%s)', 'wp-sell-services' ),
									esc_html( $wpss_dispute_statuses[ $wpss_order_dispute->status ] ?? $wpss_order_dispute->status )
								);
							} else {
								printf(
									/* translators: %s: dispute status label, e.g. Resolved or Closed */
									esc_html__( 'Dispute history on this order (%s)', 'wp-sell-services' ),
									esc_html( $wpss_dispute_statuses[ $wpss_order_dispute->status ] ?? $wpss_order_dispute->status )
								);
							}
							?>
						</strong>
						&mdash;
						<?php echo esc_html( $wpss_dispute_reasons[ $wpss_order_dispute->reason ] ?? $wpss_order_dispute->reason ); ?>
						<?php if ( ! empty( $wpss_order_dispute->dispute_number ) ) : ?>
							(<?php echo esc_html( $wpss_order_dispute->dispute_number ); ?>)
						<?php endif; ?>
						<a href="<?php echo esc_url( $wpss_dispute_url ); ?>" class="button button-small" style="margin-inline-start: 8px;">
							<?php esc_html_e( 'Open dispute', 'wp-sell-services' ); ?>
						</a>
					</p>
					<?php if ( $wpss_dispute_live && 'completed' === $order->status ) : ?>
						<p>
							<?php esc_html_e( 'This order is marked completed while the dispute is still unresolved. Resolve the dispute before treating the order as settled.', 'wp-sell-services' ); ?>
						</p>
					<?php endif; ?>
				</div>
				<?php
			endif;
			?>

			<div class="wpss-order-layout" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
				<div class="wpss-order-main" style="flex: 2;">
					<!-- Order Info -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<td>
										<span class="<?php echo esc_attr( wpss_status_class( $order->status ) ); ?>">
											<?php echo esc_html( $statuses[ $order->status ] ?? ucwords( str_replace( '_', ' ', $order->status ) ) ); ?>
										</span>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
									<td>
										<?php if ( '' !== $subject['url'] ) : ?>
											<a href="<?php echo esc_url( $subject['url'] ); ?>">
												<?php echo esc_html( $subject['label'] ); ?>
											</a>
										<?php else : ?>
											<em><?php echo esc_html( $subject['label'] ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
								<?php $wpss_package_name = $order->get_package_name(); ?>
								<?php if ( '' !== $wpss_package_name ) : ?>
									<tr>
										<th><?php esc_html_e( 'Package', 'wp-sell-services' ); ?></th>
										<td><?php echo esc_html( $wpss_package_name ); ?></td>
									</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
									<td><strong><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></strong></td>
								</tr>
								<?php if ( $order->delivery_deadline ) : ?>
									<tr>
										<th><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></th>
										<td>
										<?php
										// ServiceOrder::from_db() guarantees a DateTimeImmutable here,
										// so the old string/object dual handling is now dead.
										echo esc_html( wp_date( get_option( 'date_format' ), $order->delivery_deadline->getTimestamp() ) );
										?>
										</td>
									</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Created', 'wp-sell-services' ); ?></th>
									<td>
									<?php
									if ( $order->created_at ) {
										// Always a DateTimeImmutable via the model hydrator.
										echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->created_at->getTimestamp() ) );
									}
									?>
									</td>
								</tr>
								<?php
								// `platform_order_id` does NOT always hold a WooCommerce
								// order id: on a sub-order (tip / milestone / extension)
								// the same column holds the PARENT WPSS order id, and
								// `platform` holds the sub-order type rather than a rail.
								// Gating this row on the column alone therefore printed
								// "WooCommerce Order #74" for a milestone whose 74 is a
								// WPSS order — a link straight into a WooCommerce order
								// edit screen for an order that does not exist. Only a
								// row whose platform really IS woocommerce has a WC order
								// behind that number.
								if ( ! empty( $order->platform_order_id ) && 'woocommerce' === ( $order->platform ?? '' ) ) :
									?>
									<tr>
										<th><?php esc_html_e( 'WooCommerce Order', 'wp-sell-services' ); ?></th>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->platform_order_id ) ); ?>">
												#<?php echo esc_html( (string) $order->platform_order_id ); ?>
											</a>
										</td>
									</tr>
									<?php
								endif;
								?>
							</table>
						</div>
					</div>

					<!-- Requirements -->
					<?php
					// Requirements live in the wpss_order_requirements table, NOT on
					// the orders row. This block previously read `$order->requirements`,
					// a column that does not exist, so the panel never rendered for
					// any order. get_requirements() is the canonical accessor and is
					// what the buyer-facing surfaces already use.
					$wpss_requirements = $order->get_requirements();
					?>
					<?php if ( ! empty( $wpss_requirements ) ) : ?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Requirements', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<?php
								echo '<dl>';
								foreach ( $wpss_requirements as $wpss_req_key => $wpss_req_value ) {
									// Shared with the buyer/seller order view, which is why
									// the owner no longer reads a raw 'description' key.
									echo '<dt><strong>' . esc_html( wpss_requirement_field_label( (string) $wpss_req_key ) ) . '</strong></dt>';
									echo '<dd>' . esc_html( is_array( $wpss_req_value ) ? implode( ', ', $wpss_req_value ) : (string) $wpss_req_value ) . '</dd>';
								}
								echo '</dl>';
								?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Messages -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Messages', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<?php if ( ! empty( $messages ) ) : ?>
								<div class="wpss-order-messages" style="max-height: 400px; overflow-y: auto;">
									<?php foreach ( $messages as $message ) : ?>
										<?php $msg_user = isset( $message->sender_id ) ? get_userdata( $message->sender_id ) : null; ?>
										<div class="wpss-message" style="padding: 10px; margin-bottom: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">
											<div style="margin-bottom: 5px;">
												<strong><?php echo esc_html( $msg_user ? $msg_user->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?></strong>
												<span style="color: #666; margin-left: 10px;">
													<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
												</span>
											</div>
											<div><?php echo wp_kses_post( wpautop( $message->content ?? '' ) ); ?></div>
											<?php
											$msg_attachments = $message->attachments ? json_decode( $message->attachments, true ) : array();
											if ( ! empty( $msg_attachments ) && is_array( $msg_attachments ) ) :
												?>
												<div style="margin-top: 10px; color: #666;">
													<i data-lucide="paperclip" class="wpss-icon" aria-hidden="true"></i>
													<?php esc_html_e( 'Has attachments', 'wp-sell-services' ); ?>
												</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p><?php esc_html_e( 'No messages yet.', 'wp-sell-services' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Deliveries -->
					<?php if ( ! empty( $deliveries ) ) : ?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Deliveries', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<?php foreach ( $deliveries as $delivery ) : ?>
									<div class="wpss-delivery" style="padding: 10px; margin-bottom: 10px; background: #f0f9f0; border-left: 3px solid #00a32a;">
										<div style="margin-bottom: 5px;">
											<strong>
												<?php
												printf(
													/* translators: %d: delivery number */
													esc_html__( 'Delivery #%d', 'wp-sell-services' ),
													absint( $delivery->id )
												);
												?>
											</strong>
											<span style="color: #666; margin-left: 10px;">
												<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $delivery->created_at ) ) ); ?>
											</span>
										</div>
										<?php if ( ! empty( $delivery->message ) ) : ?>
											<div><?php echo wp_kses_post( wpautop( $delivery->message ) ); ?></div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="wpss-order-sidebar" style="flex: 1;">
					<!-- Parties -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Parties', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<p>
								<strong><?php esc_html_e( 'Buyer:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $buyer ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $buyer->ID ) ); ?>">
										<?php echo esc_html( $buyer->display_name ); ?>
									</a>
									<br><small><?php echo esc_html( $buyer->user_email ); ?></small>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Vendor:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $vendor ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">
										<?php echo esc_html( $vendor->display_name ); ?>
									</a>
									<br><small><?php echo esc_html( $vendor->user_email ); ?></small>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
						</div>
					</div>

					<!-- Update Status -->
					<?php if ( ! in_array( $order->status, array( 'completed', 'cancelled' ), true ) ) : ?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Update Order', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'wpss_update_order', 'wpss_order_nonce' ); ?>
									<input type="hidden" name="action" value="wpss_update_order">
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

									<p>
										<label for="order_status"><strong><?php esc_html_e( 'Status:', 'wp-sell-services' ); ?></strong></label><br>
										<select name="order_status" id="order_status" style="width: 100%;">
											<?php foreach ( $statuses as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $order->status, $value ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</p>

									<?php submit_button( __( 'Update Status', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
								</form>
							</div>
						</div>
					<?php endif; ?>

					<!-- Admin Actions -->
					<?php if ( current_user_can( 'manage_options' ) && wpss_order_is_refundable( $order ) ) : ?>
						<?php
						$wpss_already_refunded = wpss_get_order_refunded_amount( $order );
						$wpss_refundable_left  = max( 0, (float) $order->total - $wpss_already_refunded );
						?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Admin Actions', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<p>
									<label for="wpss-refund-amount-<?php echo esc_attr( $order_id ); ?>">
										<strong><?php esc_html_e( 'Refund amount', 'wp-sell-services' ); ?></strong>
									</label><br>
									<input type="number" id="wpss-refund-amount-<?php echo esc_attr( $order_id ); ?>"
										class="wpss-refund-amount" min="0" step="0.01"
										max="<?php echo esc_attr( (string) $wpss_refundable_left ); ?>"
										placeholder="<?php echo esc_attr( (string) $wpss_refundable_left ); ?>"
										style="width: 140px;">
								</p>
								<button type="button" class="button button-link-delete wpss-process-refund"
									data-order="<?php echo esc_attr( (string) $order_id ); ?>"
									data-order-total="<?php echo esc_attr( (string) $wpss_refundable_left ); ?>">
									<?php esc_html_e( 'Process Refund', 'wp-sell-services' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Leave the amount blank for a full refund. A smaller amount issues a partial refund and claws back the vendor\'s proportional share. The gateway payment is refunded where supported.', 'wp-sell-services' ); ?>
								</p>
							</div>
						</div>
					<?php endif; ?>

					<?php
					// Gateway-specific admin actions.
					//
					// `wpss_admin_order_actions` used to fire ONLY from
					// OrderMetabox::render_actions_metabox(), and that metabox is
					// registered against post type `wpss_orders` - which is not a
					// registered post type, so the screen does not exist and the
					// hook never ran. OfflineGateway is its only listener, and it
					// renders the "Mark as Paid" control for offline orders
					// awaiting payment. The net effect was that an offline order
					// could NOT be marked paid from the admin at all: the button
					// existed, on a screen no one could reach.
					//
					// Firing it here puts it on the screen admins actually use.
					// Buffered so the postbox wrapper only appears when a gateway
					// has something to contribute - an order with no listener
					// output must not render an empty box.
					ob_start();

					/**
					 * Fires in the admin order actions area for gateway-specific actions.
					 *
					 * @since 1.0.0
					 *
					 * @param \WPSellServices\Models\ServiceOrder $order  The order.
					 * @param string                             $status Current order status.
					 */
					do_action( 'wpss_admin_order_actions', $order, $order->status );

					$wpss_gateway_actions = trim( (string) ob_get_clean() );

					if ( '' !== $wpss_gateway_actions ) :
						?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Payment Actions', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<?php echo $wpss_gateway_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Listener output; each gateway escapes its own markup. ?>
							</div>
						</div>
						<?php
					endif;
					?>

					<?php
					// Billing address as recorded when the order was paid — the
					// SAME partial the buyer's order view renders, so the admin
					// and the customer never see different invoice detail.
					// Silent on pre-1.5.0 orders, which carry no snapshot.
					?>
					<div class="postbox wpss-billing-postbox">
						<div class="inside">
							<?php wpss_get_template_part( 'partials/billing', 'summary', array( 'wpss_order' => $order ) ); ?>
						</div>
					</div>

					<!-- Financial Summary -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Financial Summary', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<table style="width: 100%;">
								<tr>
									<td><?php esc_html_e( 'Order Total:', 'wp-sell-services' ); ?></td>
									<td style="text-align: right;"><strong><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></strong></td>
								</tr>
								<?php if ( isset( $order->vendor_earnings ) && $order->vendor_earnings > 0 ) : ?>
									<tr>
										<td><?php esc_html_e( 'Vendor Earning:', 'wp-sell-services' ); ?></td>
										<td style="text-align: right;"><?php echo esc_html( wpss_format_price( (float) $order->vendor_earnings, $order->currency ) ); ?></td>
									</tr>
								<?php endif; ?>
								<?php if ( isset( $order->platform_fee ) && $order->platform_fee > 0 ) : ?>
									<tr>
										<td><?php esc_html_e( 'Commission:', 'wp-sell-services' ); ?></td>
										<td style="text-align: right;"><?php echo esc_html( wpss_format_price( (float) $order->platform_fee, $order->currency ) ); ?></td>
									</tr>
								<?php endif; ?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render disputes page.
	 *
	 * @return void
	 */
	public function render_disputes_page(): void {
		// Check if viewing a specific dispute.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dispute_id = isset( $_GET['dispute_id'] ) ? absint( $_GET['dispute_id'] ) : 0;

		if ( 'view' === $action && $dispute_id ) {
			$this->render_dispute_detail( $dispute_id );
			return;
		}

		// Handle bulk actions.
		$this->process_disputes_bulk_actions( $action );

		$list_table = new DisputesListTable();
		$list_table->prepare_items();
		$has_items = ! empty( $list_table->items );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Disputes', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="wpss-disputes">
				<div class="wpss-list-card">
					<div class="wpss-list-card__filters">
						<?php
						$list_table->views();
						$list_table->search_box( __( 'Search Disputes', 'wp-sell-services' ), 'dispute' );
						?>
					</div>
					<div class="wpss-list-card__body">
						<?php
						if ( $has_items ) {
							$list_table->display();
						} else {
							$list_table->no_items();
						}
						?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Register Orders screen help tabs.
	 *
	 * @return void
	 */
	public function add_orders_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-overview',
				'title'   => __( 'Overview', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Orders represent every service purchase on your marketplace. Each order carries an 11-step lifecycle: pending payment, pending requirements, in progress, delivered, revision requested, completed, cancelled, disputed, and more. Use the filters above the table to focus on a single status, and click any order to open its detail view with messages, requirements, and delivery history.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-actions',
				'title'   => __( 'Available actions', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Bulk-select orders to mark them completed or cancelled. Click an order to manually update its status, issue a refund note, or open a dispute on the buyer or vendor behalf. Administrators can also create an order manually from Sell Services > Create Order when a buyer pays offline.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'wp-sell-services' ) . '</strong></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/" target="_blank" rel="noopener">' . esc_html__( 'Plugin docs', 'wp-sell-services' ) . '</a></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/order-workflow-wpss" target="_blank" rel="noopener">' . esc_html__( 'Order workflow guide', 'wp-sell-services' ) . '</a></p>'
		);
	}

	/**
	 * Register Disputes screen help tabs.
	 *
	 * @return void
	 */
	public function add_disputes_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-overview',
				'title'   => __( 'Overview', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'A dispute opens when a buyer and vendor cannot agree on an order outcome. This screen surfaces every dispute case, its status, the order it relates to, and the most recent activity. Disputes are escalated from order detail pages when either party opens one.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-actions',
				'title'   => __( 'Available actions', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Click a dispute to review the full conversation, both parties evidence, and post an admin mediation note. From the detail screen you can resolve in favour of the buyer (refund) or the vendor (release), escalate, or close without action. Bulk actions can mark multiple disputes as pending review, escalated, or closed.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'wp-sell-services' ) . '</strong></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/" target="_blank" rel="noopener">' . esc_html__( 'Plugin docs', 'wp-sell-services' ) . '</a></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/dispute-admin-mediation-wpss" target="_blank" rel="noopener">' . esc_html__( 'Dispute mediation guide', 'wp-sell-services' ) . '</a></p>'
		);
	}

	/**
	 * Resolution type + refund amount, for the dispute resolution forms.
	 *
	 * ONE renderer for both. The detail screen carries two of these forms and
	 * they were byte-near copies, which is how the refund-amount field could be
	 * added to one and missed on the other.
	 *
	 * The amount field is the fix for Basecamp 10240143362: there was nowhere to
	 * type a partial refund, so Admin passed 0.0 and every Partial Refund
	 * returned the buyer the entire order total. Shown only for Partial Refund,
	 * because that is the one resolution where the number is not implied.
	 *
	 * @since 1.7.0
	 *
	 * @param object               $dispute     Dispute row.
	 * @param object|null          $order       Order the dispute is on, when resolvable.
	 * @param array<string,string> $resolutions Resolution type => label.
	 * @param string               $id          Unique id prefix for this form's fields.
	 * @return void
	 */
	private function render_dispute_resolution_fields( object $dispute, ?object $order, array $resolutions, string $id ): void {
		$selected = (string) ( $dispute->resolution ?? '' );
		$total    = $order ? (float) $order->total : 0.0;
		$decimals = function_exists( 'wpss_get_currency_decimals' ) ? wpss_get_currency_decimals( $order->currency ?? '' ) : 2;
		$step     = $decimals > 0 ? '0.' . str_repeat( '0', $decimals - 1 ) . '1' : '1';
		$existing = isset( $dispute->refund_amount ) ? (float) $dispute->refund_amount : 0.0;
		$partial  = DisputeService::RESOLUTION_PARTIAL_REFUND;
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><strong><?php esc_html_e( 'Resolution:', 'wp-sell-services' ); ?></strong></label><br>
			<select name="resolution" id="<?php echo esc_attr( $id ); ?>" class="wpss-dispute-resolution" style="width: 100%;">
				<option value=""><?php esc_html_e( '— Select Resolution —', 'wp-sell-services' ); ?></option>
				<?php foreach ( $resolutions as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p class="wpss-dispute-refund-amount" data-wpss-partial="<?php echo esc_attr( $partial ); ?>"
			style="<?php echo $partial === $selected ? '' : 'display:none;'; ?>">
			<label for="<?php echo esc_attr( $id ); ?>_amount">
				<strong><?php esc_html_e( 'Refund Amount:', 'wp-sell-services' ); ?></strong>
			</label><br>
			<input
				type="number"
				name="refund_amount"
				id="<?php echo esc_attr( $id ); ?>_amount"
				value="<?php echo $existing > 0 ? esc_attr( (string) $existing ) : ''; ?>"
				min="<?php echo esc_attr( $step ); ?>"
				max="<?php echo esc_attr( (string) $total ); ?>"
				step="<?php echo esc_attr( $step ); ?>"
				style="width: 100%;"
			>
			<span class="description">
				<?php
				if ( $order ) {
					printf(
						/* translators: %s: formatted order total. */
						esc_html__( 'How much of the %s order total goes back to the buyer. The vendor keeps the rest.', 'wp-sell-services' ),
						esc_html( wpss_format_price( $total, $order->currency ?? '' ) )
					);
				} else {
					esc_html_e( 'How much goes back to the buyer. The vendor keeps the rest.', 'wp-sell-services' );
				}
				?>
			</span>
		</p>

		<script>
			( function () {
				var select = document.getElementById( <?php echo wp_json_encode( $id ); ?> );

				if ( ! select ) {
					return;
				}

				var row = select.closest( 'form' ).querySelector( '.wpss-dispute-refund-amount' );

				if ( ! row ) {
					return;
				}

				select.addEventListener( 'change', function () {
					row.style.display = select.value === row.dataset.wpssPartial ? '' : 'none';
				} );
			}() );
		</script>
		<?php
	}

	/**
	 * Render dispute detail view.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return void
	 */
	private function render_dispute_detail( int $dispute_id ): void {
		global $wpdb;
		$disputes_table = $wpdb->prefix . 'wpss_disputes';
		$messages_table = $wpdb->prefix . 'wpss_dispute_messages';
		$orders_table   = $wpdb->prefix . 'wpss_orders';

		// Show update feedback notice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice_class = '1' === $_GET['updated'] ? 'notice-success' : 'notice-error';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice_msg = '1' === $_GET['updated']
				? __( 'Dispute updated successfully.', 'wp-sell-services' )
				: __( 'Failed to update dispute.', 'wp-sell-services' );

			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice_class ),
				esc_html( $notice_msg )
			);
		}

		// Get dispute.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dispute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$disputes_table} WHERE id = %d",
				$dispute_id
			)
		);

		if ( ! $dispute ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Dispute not found.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		// Get related order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} WHERE id = %d",
				$dispute->order_id
			)
		);

		// Get dispute messages.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$messages_table} WHERE dispute_id = %d ORDER BY created_at ASC",
				$dispute_id
			)
		);

		$initiated_by = get_userdata( $dispute->initiated_by );
		$vendor       = $order ? get_userdata( $order->vendor_id ) : null;
		$customer     = $order ? get_userdata( $order->customer_id ) : null;

		$statuses = array(
			'open'           => __( 'Open', 'wp-sell-services' ),
			'pending_review' => __( 'Pending Review', 'wp-sell-services' ),
			'resolved'       => __( 'Resolved', 'wp-sell-services' ),
			'escalated'      => __( 'Escalated', 'wp-sell-services' ),
			'closed'         => __( 'Closed', 'wp-sell-services' ),
		);

		$resolutions = DisputeService::get_resolution_types();

		$reasons = wpss_get_dispute_reasons();
		?>
		<div class="wrap wpss-dispute-detail">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %d: dispute ID */
					esc_html__( 'Dispute #%d', 'wp-sell-services' ),
					absint( $dispute_id )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-disputes' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Back to Disputes', 'wp-sell-services' ); ?>
			</a>
			<hr class="wp-header-end">

			<div class="wpss-dispute-layout" style="display: flex; gap: 20px; margin-top: 20px;">
				<div class="wpss-dispute-main" style="flex: 2;">
					<!-- Dispute Info -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Dispute Details', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<td>
										<span class="<?php echo esc_attr( wpss_status_class( $dispute->status ) ); ?>">
											<?php echo esc_html( $statuses[ $dispute->status ] ?? $dispute->status ); ?>
										</span>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></th>
									<td><?php echo esc_html( $reasons[ $dispute->reason ] ?? $dispute->reason ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Opened By', 'wp-sell-services' ); ?></th>
									<td>
										<?php if ( $initiated_by ) : ?>
											<a href="<?php echo esc_url( get_edit_user_link( $initiated_by->ID ) ); ?>">
												<?php echo esc_html( $initiated_by->display_name ); ?>
											</a>
										<?php else : ?>
											<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
									<td>
										<?php if ( $order ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $order->id ) ); ?>">
												#<?php echo esc_html( $order->order_number ); ?>
											</a>
										<?php else : ?>
											<em><?php esc_html_e( 'Deleted', 'wp-sell-services' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Date Opened', 'wp-sell-services' ); ?></th>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dispute->created_at ) ) ); ?></td>
								</tr>
								<?php if ( ! empty( $dispute->description ) ) : ?>
									<tr>
										<th><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></th>
										<td><?php echo wp_kses_post( wpautop( $dispute->description ) ); ?></td>
									</tr>
								<?php endif; ?>
							</table>
						</div>
					</div>

					<!-- Messages -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Messages', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<?php if ( ! empty( $messages ) ) : ?>
								<div class="wpss-dispute-messages" style="max-height: 400px; overflow-y: auto;">
									<?php foreach ( $messages as $message ) : ?>
										<?php $msg_user = get_userdata( $message->sender_id ); ?>
										<div class="wpss-message" style="padding: 10px; margin-bottom: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">
											<div style="margin-bottom: 5px;">
												<strong><?php echo esc_html( $msg_user ? $msg_user->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?></strong>
												<span style="color: #666; margin-left: 10px;">
													<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
												</span>
											</div>
											<div><?php echo wp_kses_post( wpautop( $message->message ) ); ?></div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p><?php esc_html_e( 'No messages yet.', 'wp-sell-services' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="wpss-dispute-sidebar" style="flex: 1;">
					<!-- Parties -->
					<div class="postbox">
						<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Parties Involved', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<p>
								<strong><?php esc_html_e( 'Buyer:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $customer ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $customer->ID ) ); ?>">
										<?php echo esc_html( $customer->display_name ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Vendor:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $vendor ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">
										<?php echo esc_html( $vendor->display_name ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
							<?php if ( $order ) : ?>
								<p>
									<strong><?php esc_html_e( 'Order Value:', 'wp-sell-services' ); ?></strong><br>
									<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Resolution Actions -->
					<?php if ( ! in_array( $dispute->status, array( 'resolved', 'closed' ), true ) ) : ?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Resolution', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'wpss_resolve_dispute', 'wpss_dispute_nonce' ); ?>
									<input type="hidden" name="action" value="wpss_resolve_dispute">
									<input type="hidden" name="dispute_id" value="<?php echo esc_attr( (string) $dispute_id ); ?>">

									<p>
										<label for="dispute_status"><strong><?php esc_html_e( 'Update Status:', 'wp-sell-services' ); ?></strong></label><br>
										<select name="dispute_status" id="dispute_status" style="width: 100%;">
											<?php foreach ( $statuses as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $dispute->status, $value ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</p>

									<div id="wpss-resolution-fields" style="<?php echo 'resolved' === $dispute->status ? '' : 'display:none;'; ?>">
										<?php $this->render_dispute_resolution_fields( $dispute, $order, $resolutions, 'resolution' ); ?>
									</div>

									<p>
										<label for="admin_notes"><strong><?php esc_html_e( 'Admin Notes:', 'wp-sell-services' ); ?></strong></label><br>
										<textarea name="admin_notes" id="admin_notes" rows="4" style="width: 100%;"><?php echo esc_textarea( $dispute->resolution_notes ?? '' ); ?></textarea>
									</p>

									<?php submit_button( __( 'Update Dispute', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
								</form>
							</div>
						</div>
					<?php else : ?>
						<div class="postbox">
							<h2 class="hndle" style="padding: 0 12px;"><?php esc_html_e( 'Resolution', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<p>
									<strong><?php esc_html_e( 'Resolution:', 'wp-sell-services' ); ?></strong><br>
									<?php echo esc_html( $resolutions[ $dispute->resolution ?? '' ] ?? __( 'N/A', 'wp-sell-services' ) ); ?>
								</p>
								<?php if ( ! empty( $dispute->resolution_notes ) ) : ?>
									<p>
										<strong><?php esc_html_e( 'Admin Notes:', 'wp-sell-services' ); ?></strong><br>
										<?php echo wp_kses_post( wpautop( $dispute->resolution_notes ) ); ?>
									</p>
								<?php endif; ?>
								<?php if ( ! empty( $dispute->resolved_at ) ) : ?>
									<p>
										<strong><?php esc_html_e( 'Resolved At:', 'wp-sell-services' ); ?></strong><br>
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dispute->resolved_at ) ) ); ?>
									</p>
								<?php endif; ?>
								<!-- Allow admin to update resolution on already-resolved disputes -->
								<hr style="margin: 15px 0;">
								<details>
									<summary style="cursor: pointer; font-weight: 600;"><?php esc_html_e( 'Update Resolution', 'wp-sell-services' ); ?></summary>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 10px;">
										<?php wp_nonce_field( 'wpss_resolve_dispute', 'wpss_dispute_nonce' ); ?>
										<input type="hidden" name="action" value="wpss_resolve_dispute">
										<input type="hidden" name="dispute_id" value="<?php echo esc_attr( (string) $dispute_id ); ?>">
										<input type="hidden" name="dispute_status" value="resolved">

										<?php $this->render_dispute_resolution_fields( $dispute, $order, $resolutions, 'resolution_update' ); ?>

										<p>
											<label for="admin_notes_update"><strong><?php esc_html_e( 'Admin Notes:', 'wp-sell-services' ); ?></strong></label><br>
											<textarea name="admin_notes" id="admin_notes_update" rows="4" style="width: 100%;"><?php echo esc_textarea( $dispute->resolution_notes ?? '' ); ?></textarea>
										</p>

										<?php submit_button( __( 'Update Resolution', 'wp-sell-services' ), 'secondary', 'submit', false ); ?>
									</form>
								</details>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$this->settings->render();
	}

	/**
	 * Get settings instance.
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}

	/**
	 * AJAX handler to import demo content.
	 *
	 * Creates demo services, categories, and vendor profiles.
	 *
	 * @return void
	 */
	public function ajax_import_demo_content(): void {
		check_ajax_referer( 'wpss_demo_content', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$cli_file = WPSS_PLUGIN_DIR . 'src/CLI/ServiceCommands.php';
		if ( ! file_exists( $cli_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Demo content module not found.', 'wp-sell-services' ) ) );
		}

		// Provide stub WP-CLI classes if not loaded (non-CLI context).
		if ( ! class_exists( 'WP_CLI_Command' ) ) {
			require_once WPSS_PLUGIN_DIR . 'src/CLI/wp-cli-stubs.php';
		}

		// Use the CLI service templates directly.
		require_once $cli_file;
		$commands = new \WPSellServices\CLI\ServiceCommands();

		/*
		 * Reflection reaches the private templates and the create_service
		 * method. No setAccessible( true ) calls: they have done nothing since
		 * PHP 8.1, which is this plugin's minimum, and PHP 8.5 deprecates them.
		 */
		$ref_class     = new \ReflectionClass( $commands );
		$ref_templates = $ref_class->getProperty( 'service_templates' );
		$templates     = $ref_templates->getValue( $commands );

		$ref_create    = $ref_class->getMethod( 'create_service' );
		$ref_variation = $ref_class->getMethod( 'apply_variation' );

		// Create categories first.
		$categories = array_unique( array_column( $templates, 'category' ) );
		foreach ( $categories as $cat_name ) {
			if ( ! term_exists( $cat_name, 'wpss_service_category' ) ) {
				wp_insert_term( $cat_name, 'wpss_service_category' );
			}
		}

		// Create 20 services (cycling through templates).
		$count          = 20;
		$created        = 0;
		$featured       = 0;
		$template_count = count( $templates );

		for ( $i = 0; $i < $count; $i++ ) {
			$template  = $templates[ $i % $template_count ];
			$variation = (int) floor( $i / $template_count );

			$service_data = $ref_variation->invoke( $commands, $template, $variation );

			// Mark some as featured.
			if ( $featured < 5 && ( $i % 4 === 0 || ! empty( $template['featured'] ) ) ) {
				$service_data['featured'] = true;
				++$featured;
			}

			$result = $ref_create->invoke( $commands, $service_data );
			if ( ! is_wp_error( $result ) ) {
				// Mark as demo content for easy cleanup.
				update_post_meta( $result, '_wpss_demo_content', 1 );
				++$created;
			}
		}

		// Create demo vendor profiles.
		$vendors_created = $this->create_demo_vendors();

		update_option( 'wpss_demo_content_imported', true );

		wp_send_json_success(
			array(
				'message'    => sprintf(
					/* translators: 1: services count, 2: categories count, 3: vendors count */
					__( 'Imported %1$d services, %2$d categories, and %3$d vendor profiles.', 'wp-sell-services' ),
					$created,
					count( $categories ),
					$vendors_created
				),
				'services'   => $created,
				'categories' => count( $categories ),
				'vendors'    => $vendors_created,
			)
		);
	}

	/**
	 * Create demo vendor profiles.
	 *
	 * @return int Number of vendors created.
	 */
	private function create_demo_vendors(): int {
		$vendors = array(
			array(
				'login'   => 'sarah_designer',
				'email'   => 'sarah@demo.test',
				'name'    => 'Sarah Chen',
				'tagline' => 'Top Rated Logo & Brand Designer',
				'bio'     => 'Award-winning designer with 8+ years creating memorable brand identities. Specializing in minimalist logos and complete branding packages.',
				'country' => 'US',
			),
			array(
				'login'   => 'mike_developer',
				'email'   => 'mike@demo.test',
				'name'    => 'Mike Rodriguez',
				'tagline' => 'Full-Stack WordPress Developer',
				'bio'     => 'WordPress developer building custom themes, plugins, and e-commerce solutions. Clean code, fast delivery.',
				'country' => 'CA',
			),
			array(
				'login'   => 'emma_writer',
				'email'   => 'emma@demo.test',
				'name'    => 'Emma Williams',
				'tagline' => 'SEO Content Writer & Strategist',
				'bio'     => 'Published writer creating SEO-optimized content that ranks. Specializing in tech, SaaS, and marketing niches.',
				'country' => 'GB',
			),
			array(
				'login'   => 'alex_marketer',
				'email'   => 'alex@demo.test',
				'name'    => 'Alex Kim',
				'tagline' => 'Digital Marketing Specialist',
				'bio'     => 'Google Ads certified specialist helping businesses grow through data-driven campaigns and SEO strategies.',
				'country' => 'AU',
			),
		);

		$created = 0;

		foreach ( $vendors as $vendor_data ) {
			// Skip if user exists.
			if ( username_exists( $vendor_data['login'] ) ) {
				continue;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $vendor_data['login'],
					'user_email'   => $vendor_data['email'],
					'user_pass'    => wp_generate_password(),
					'display_name' => $vendor_data['name'],
					'role'         => 'wpss_vendor',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			// Mark as demo user.
			update_user_meta( $user_id, '_wpss_demo_content', 1 );
			update_user_meta( $user_id, '_wpss_is_vendor', true );

			// Create vendor profile in DB.
			global $wpdb;
			$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$profiles_table,
				array(
					'user_id'      => $user_id,
					'display_name' => $vendor_data['name'],
					'tagline'      => $vendor_data['tagline'],
					'bio'          => $vendor_data['bio'],
					'status'       => 'active',
					'country'      => $vendor_data['country'],
					'is_available' => 1,
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			// Assign some services to this vendor.
			$services = get_posts(
				array(
					'post_type'      => 'wpss_service',
					'posts_per_page' => 3,
					'orderby'        => 'rand',
					'meta_key'       => '_wpss_demo_content',
					'meta_value'     => '1',
					'author'         => 0, // Only unassigned.
					'fields'         => 'ids',
				)
			);

			foreach ( $services as $service_id ) {
				wp_update_post(
					array(
						'ID'          => $service_id,
						'post_author' => $user_id,
					)
				);
			}

			++$created;
		}

		return $created;
	}

	/**
	 * AJAX handler to delete demo content.
	 *
	 * @return void
	 */
	public function ajax_delete_demo_content(): void {
		check_ajax_referer( 'wpss_demo_content', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// Delete demo services.
		$demo_services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_wpss_demo_content',
				'meta_value'     => '1',
				'fields'         => 'ids',
			)
		);

		$services_deleted = 0;
		foreach ( $demo_services as $post_id ) {
			if ( wp_delete_post( $post_id, true ) ) {
				++$services_deleted;
			}
		}

		// Delete demo vendor users.
		$demo_users = get_users(
			array(
				'meta_key'   => '_wpss_demo_content',
				'meta_value' => '1',
				'fields'     => 'ids',
			)
		);

		global $wpdb;
		$profiles_table  = $wpdb->prefix . 'wpss_vendor_profiles';
		$vendors_deleted = 0;

		foreach ( $demo_users as $user_id ) {
			// Remove vendor profile.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $profiles_table, array( 'user_id' => $user_id ), array( '%d' ) );

			if ( wp_delete_user( $user_id ) ) {
				++$vendors_deleted;
			}
		}

		// Clean up empty demo categories.
		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		$cats_deleted = 0;
		if ( is_array( $categories ) ) {
			foreach ( $categories as $term_id ) {
				$term = get_term( $term_id, 'wpss_service_category' );
				if ( $term && 0 === $term->count ) {
					wp_delete_term( $term_id, 'wpss_service_category' );
					++$cats_deleted;
				}
			}
		}

		delete_option( 'wpss_demo_content_imported' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: services count, 2: vendors count */
					__( 'Deleted %1$d demo services and %2$d demo vendors.', 'wp-sell-services' ),
					$services_deleted,
					$vendors_deleted
				),
			)
		);
	}
}
