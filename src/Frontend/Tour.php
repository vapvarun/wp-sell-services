<?php
/**
 * Guided Tour — asset + REST plumbing.
 *
 * @package WPSellServices\Frontend
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Shepherd.js-powered onboarding tour scaffolding.
 *
 * This class provides the plumbing that drives the in-admin + in-dashboard
 * walkthrough: vendor assets (Shepherd + Lucide), the controller script
 * (`assets/js/wpss-tour.js`), a localized `wpssTour` config object, and a
 * REST endpoint for persisting completion. Step authoring lives in
 * {@see self::get_admin_tour_steps()} and is intentionally stubbed here —
 * content gets layered on top by the Packet that ships user-visible tours.
 *
 * @since 1.1.0
 */
final class Tour {

	/**
	 * REST namespace used for the tour endpoint.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'wpss/v1';

	/**
	 * REST route (relative to namespace) used to persist completion.
	 *
	 * @var string
	 */
	private const REST_ROUTE_COMPLETE = '/tour/complete';

	/**
	 * User meta key that stores the "tour completed" flag.
	 *
	 * @var string
	 */
	public const USER_META_COMPLETED = 'wpss_tour_completed';

	/**
	 * Script handle for the Shepherd vendor library.
	 *
	 * @var string
	 */
	public const HANDLE_SHEPHERD = 'wpss-shepherd';

	/**
	 * Script handle for the Lucide vendor library.
	 *
	 * @var string
	 */
	public const HANDLE_LUCIDE = 'wpss-lucide';

	/**
	 * Script handle for the tour controller.
	 *
	 * @var string
	 */
	public const HANDLE_TOUR = 'wpss-tour';

	/**
	 * Style handle for the Shepherd vendor CSS.
	 *
	 * @var string
	 */
	public const STYLE_SHEPHERD = 'wpss-shepherd';

	/**
	 * Style handle for the tour theme overrides.
	 *
	 * @var string
	 */
	public const STYLE_TOUR = 'wpss-tour';

	/**
	 * Wire up hooks.
	 *
	 * Expected to be called once from {@see \WPSellServices\Core\Plugin::init()}.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register and (where appropriate) enqueue tour assets.
	 *
	 * Runs in both admin + frontend contexts. Enqueues only when the
	 * current request is one the tour is meant for:
	 *
	 * - Admin: any screen whose `id` begins with `wpss` OR the top-level
	 *          `toplevel_page_wpss-dashboard` screen.
	 * - Front: any singular post/page whose content contains the
	 *          `[wpss_dashboard]` shortcode.
	 *
	 * Registration itself is idempotent — enqueueing the controller script
	 * triggers Shepherd + Lucide automatically via dependencies.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_scripts(): void {
		// Vendor: Shepherd.js.
		wp_register_style(
			self::STYLE_SHEPHERD,
			\WPSS_PLUGIN_URL . 'assets/vendor/shepherd/shepherd.css',
			array(),
			'11.2.0'
		);

		wp_register_script(
			self::HANDLE_SHEPHERD,
			\WPSS_PLUGIN_URL . 'assets/vendor/shepherd/shepherd.min.js',
			array(),
			'11.2.0',
			true
		);

		// Vendor: Lucide icon renderer.
		wp_register_script(
			self::HANDLE_LUCIDE,
			\WPSS_PLUGIN_URL . 'assets/vendor/lucide/lucide.min.js',
			array(),
			'0.460.0',
			true
		);

		// Plugin: theme overrides + controller.
		wp_register_style(
			self::STYLE_TOUR,
			\WPSS_PLUGIN_URL . 'assets/css/wpss-tour.css',
			array( self::STYLE_SHEPHERD ),
			\WPSS_VERSION
		);

		wp_register_script(
			self::HANDLE_TOUR,
			\WPSS_PLUGIN_URL . 'assets/js/wpss-tour.js',
			array( self::HANDLE_SHEPHERD, self::HANDLE_LUCIDE ),
			\WPSS_VERSION,
			true
		);

		if ( ! $this->should_enqueue() ) {
			return;
		}

		wp_enqueue_style( self::STYLE_SHEPHERD );
		wp_enqueue_style( self::STYLE_TOUR );
		wp_enqueue_script( self::HANDLE_SHEPHERD );
		wp_enqueue_script( self::HANDLE_LUCIDE );
		wp_enqueue_script( self::HANDLE_TOUR );

		wp_localize_script(
			self::HANDLE_TOUR,
			'wpssTour',
			$this->localize_tour_data()
		);
	}

	/**
	 * Decide whether the current request should load the tour assets.
	 *
	 * Kept in a separate method so step authors can filter it later via
	 * `wpss_tour_should_enqueue` without patching `register_scripts()`.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	private function should_enqueue(): bool {
		$should = false;

		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen instanceof \WP_Screen ) {
				$id = (string) $screen->id;
				// Top-level dashboard is `toplevel_page_wp-sell-services`;
				// subpages follow the pattern `sell-services_page_wpss-*`.
				if ( 0 === strpos( $id, 'toplevel_page_wp-sell-services' )
					|| false !== strpos( $id, '_page_wpss-' ) ) {
					$should = true;
				}
			}
		} elseif ( ! is_admin() ) {
			global $post;
			if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'wpss_dashboard' ) ) {
				$should = true;
			}
		}

		/**
		 * Filter whether WPSS tour assets load on the current request.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $should True to load the tour, false to skip.
		 */
		return (bool) apply_filters( 'wpss_tour_should_enqueue', $should );
	}

	/**
	 * Build the `wpssTour` localized object handed to the controller.
	 *
	 * Returned payload shape:
	 *
	 *     array(
	 *         'steps'       => array<int, array>  Shepherd step configs (empty here).
	 *         'completed'   => bool               Has this user dismissed / finished the tour?
	 *         'completeUrl' => string             Absolute URL to the REST completion endpoint.
	 *         'nonce'       => string             wp_rest nonce for the POST.
	 *     )
	 *
	 * @since 1.1.0
	 * @return array<string,mixed>
	 */
	public function localize_tour_data(): array {
		$user_id   = get_current_user_id();
		$completed = false;

		if ( $user_id > 0 ) {
			$completed = (bool) get_user_meta( $user_id, self::USER_META_COMPLETED, true );
		}

		// Screen-gate which step set to ship. The admin walkthrough is only
		// useful on the WPSS dashboard or on the setup wizard success screen
		// (so admins who finish the wizard can kick the tour off). Frontend
		// steps are reserved for the `[wpss_dashboard]` shortcode.
		$steps = array();

		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen instanceof \WP_Screen ) {
				$id = (string) $screen->id;
				if ( 0 === strpos( $id, 'toplevel_page_wp-sell-services' )
					|| 'sell-services_page_wpss-setup-wizard' === $id ) {
					$steps = $this->get_admin_tour_steps();
				}
			}
		} elseif ( ! is_admin() ) {
			$steps = $this->get_frontend_tour_steps();
		}

		/**
		 * Filter the steps array handed to Shepherd.
		 *
		 * Step authors should hook here rather than editing `Tour::get_admin_tour_steps()`
		 * directly — keeps the scaffold and content decoupled.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, array> $steps Ordered Shepherd step configs.
		 */
		$steps = (array) apply_filters( 'wpss_tour_steps', $steps );

		// Completion state is surfaced but not used to server-side gate —
		// the JS controller bails on its own when `completed` is true, while
		// keeping `window.wpssTour.start()` available for manual re-triggers.
		return array(
			'steps'       => $steps,
			'completed'   => $completed,
			'completeUrl' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE_COMPLETE ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
		);
	}

	/**
	 * Register the tour REST route.
	 *
	 * `POST /wpss/v1/tour/complete` — flips the per-user completion flag
	 * so the controller knows not to auto-open the tour next time.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_COMPLETE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_complete_request' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * REST handler — persist the completion flag for the current user.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request Inbound REST request (unused — no body).
	 * @return \WP_REST_Response
	 */
	public function handle_complete_request( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'not_logged_in',
				),
				401
			);
		}

		update_user_meta( $user_id, self::USER_META_COMPLETED, 1 );

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'completed' => true,
			),
			200
		);
	}

	/**
	 * Admin / dashboard tour steps.
	 *
	 * Eight-step walkthrough covering the sidebar menu item, the dashboard
	 * stat cards + quick actions, each of the four sub-pages (Services,
	 * Vendors, Orders, Settings), and the setup wizard re-entry point.
	 *
	 * Step IDs are stable — external code (and the JS controller) can rely
	 * on them for analytics or for jumping to a specific step.
	 *
	 * @since 1.1.0
	 * @return array<int, array<string,mixed>>
	 */
	public function get_admin_tour_steps(): array {
		$back_btn = array(
			'text'    => __( 'Back', 'wp-sell-services' ),
			'action'  => 'back',
			'classes' => 'shepherd-button-secondary',
		);
		$next_btn = array(
			'text'    => __( 'Next', 'wp-sell-services' ),
			'action'  => 'next',
			'classes' => 'shepherd-button-primary',
		);

		return array(
			array(
				'id'       => 'welcome',
				'title'    => __( 'Welcome to WP Sell Services', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="rocket"></i> Let\'s take a quick tour of your marketplace so you know where everything lives.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '#adminmenu a[href="admin.php?page=wp-sell-services"]',
					'on'      => 'right',
				),
				'buttons'  => array(
					array(
						'text'    => __( 'Skip', 'wp-sell-services' ),
						'action'  => 'cancel',
						'classes' => 'shepherd-button-secondary',
					),
					array(
						'text'    => __( 'Start tour', 'wp-sell-services' ),
						'action'  => 'next',
						'classes' => 'shepherd-button-primary',
					),
				),
			),
			array(
				'id'       => 'dashboard-cards',
				'title'    => __( 'Marketplace at a glance', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="bar-chart-3"></i> These cards surface live totals for vendors, services, and orders so you can spot momentum (or trouble) at a glance.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-stats-row, .wpss-dashboard-grid',
					'on'      => 'bottom',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'quick-actions',
				'title'    => __( 'Quick actions', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="zap"></i> Jump straight to the four things most admins do first — add a service, review vendors, check orders, or fine-tune settings.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-quick-actions',
					'on'      => 'top',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'services-menu',
				'title'    => __( 'Services', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="package"></i> This is where vendor services live — browse, moderate, or edit any listing on your marketplace.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '#adminmenu a[href="edit.php?post_type=wpss_service"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'vendors-menu',
				'title'    => __( 'Vendors', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="users"></i> All vendor accounts appear here. The <code>/become-a-vendor/</code> page handles self-registration for new sellers.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '#adminmenu a[href="admin.php?page=wpss-vendors"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'orders-menu',
				'title'    => __( 'Orders', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="shopping-cart"></i> Orders flow through 11 statuses from <code>pending_payment</code> to <code>completed</code> — every transition is logged here.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '#adminmenu a[href="admin.php?page=wpss-orders"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'settings-menu',
				'title'    => __( 'Settings', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="settings"></i> Configure commission, payouts, tax, and notifications here. Most marketplaces only need a few tabs to get going.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '#adminmenu a[href="admin.php?page=wpss-settings"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'      => 'finish',
				'title'   => __( 'You\'re all set', 'wp-sell-services' ),
				'text'    => __( '<i data-lucide="check-circle-2"></i> That\'s the whole marketplace. Use the "Replay guide" button on the dashboard any time you want to run through this again. Happy selling!', 'wp-sell-services' ),
				'buttons' => array(
					$back_btn,
					array(
						'text'    => __( 'Finish', 'wp-sell-services' ),
						'action'  => 'complete',
						'classes' => 'shepherd-button-primary',
					),
				),
			),
		);
	}

	/**
	 * Frontend tour steps for the `[wpss_dashboard]` shortcode.
	 *
	 * Role-aware: an active seller gets the selling + earnings steps, a buyer
	 * with no vendor role gets the "start selling" CTA highlight instead.
	 * Selectors match the DOM rendered by {@see UnifiedDashboard::render_shell()}.
	 *
	 * @since 1.1.0
	 * @return array<int, array<string,mixed>>
	 */
	public function get_frontend_tour_steps(): array {
		$user_id = get_current_user_id();

		// Logged-out visitors see the login prompt instead of the dashboard
		// shell, so there's nothing meaningful to walk.
		if ( $user_id <= 0 ) {
			return array();
		}

		$vendor_service = new \WPSellServices\Services\VendorService();
		$is_vendor      = $vendor_service->is_vendor( $user_id );
		$is_active      = $is_vendor && 'active' === $vendor_service->get_vendor_status( $user_id );

		$back_btn = array(
			'text'    => __( 'Back', 'wp-sell-services' ),
			'action'  => 'back',
			'classes' => 'shepherd-button-secondary',
		);
		$next_btn = array(
			'text'    => __( 'Next', 'wp-sell-services' ),
			'action'  => 'next',
			'classes' => 'shepherd-button-primary',
		);

		$steps = array(
			array(
				'id'      => 'welcome',
				'title'   => __( 'Welcome to your dashboard', 'wp-sell-services' ),
				'text'    => __( '<i data-lucide="sparkles"></i> A quick tour so you know where your orders, messages, and (if you sell) earnings live.', 'wp-sell-services' ),
				'buttons' => array(
					array(
						'text'    => __( 'Skip', 'wp-sell-services' ),
						'action'  => 'cancel',
						'classes' => 'shepherd-button-secondary',
					),
					array(
						'text'    => __( 'Start tour', 'wp-sell-services' ),
						'action'  => 'next',
						'classes' => 'shepherd-button-primary',
					),
				),
			),
			array(
				'id'       => 'sidebar',
				'title'    => __( 'Your dashboard', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="layout-dashboard"></i> Everything you do on this marketplace — buying, selling, conversations — routes through this sidebar. The current section is highlighted.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__sidebar',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'orders',
				'title'    => __( 'My Orders', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="shopping-bag"></i> Every service you buy lives here — current orders in progress, completed history, and any open disputes. Click an order to open the conversation, requirements, and deliveries.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__nav-item[href$="/dashboard/"], .wpss-dashboard__nav-group:first-child .wpss-dashboard__nav-item:first-child',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
			array(
				'id'       => 'requests',
				'title'    => __( 'Buyer Requests', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="megaphone"></i> Need something custom? Post a request with your budget and deadline — vendors reply with proposals you can compare side by side.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__nav-item[href*="section=requests"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			),
		);

		if ( $is_active ) {
			// Active vendor — show selling-side tools.
			$steps[] = array(
				'id'       => 'services',
				'title'    => __( 'My Services', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="briefcase"></i> Your service listings. Create new ones, edit pricing tiers, toggle availability, or pause a service with vacation mode.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__nav-item[href*="section=services"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			);
			$steps[] = array(
				'id'       => 'sales',
				'title'    => __( 'Sales Orders', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="receipt"></i> Orders buyers have placed with you — deliver, request revisions, propose milestones or extensions, and track every status from pending requirements to completion.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__nav-item[href*="section=sales"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			);
			$steps[] = array(
				'id'       => 'earnings',
				'title'    => __( 'Earnings & Wallet', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="wallet"></i> Your NET earnings, wallet balance, and withdrawal controls. The ledger surfaces every transaction — earning, tip, milestone, extension, withdrawal — with a CSV export for your bookkeeping.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__nav-item[href*="section=earnings"]',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			);
		} elseif ( ! $is_vendor ) {
			// Buyer who hasn't started selling yet — highlight the CTA.
			$steps[] = array(
				'id'       => 'become-vendor',
				'title'    => __( 'Want to sell too?', 'wp-sell-services' ),
				'text'     => __( '<i data-lucide="rocket"></i> Flip this switch to open a seller profile. You can list services, receive orders, and build up earnings without leaving this dashboard.', 'wp-sell-services' ),
				'attachTo' => array(
					'element' => '.wpss-dashboard__become-vendor',
					'on'      => 'right',
				),
				'buttons'  => array( $back_btn, $next_btn ),
			);
		}

		$steps[] = array(
			'id'       => 'messages',
			'title'    => __( 'Messages', 'wp-sell-services' ),
			'text'     => __( '<i data-lucide="message-square"></i> All your order conversations in one inbox. Attachments, delivery files, and dispute threads all surface here.', 'wp-sell-services' ),
			'attachTo' => array(
				'element' => '.wpss-dashboard__nav-item[href*="section=messages"]',
				'on'      => 'right',
			),
			'buttons'  => array( $back_btn, $next_btn ),
		);

		$steps[] = array(
			'id'      => 'finish',
			'title'   => __( 'You\'re set', 'wp-sell-services' ),
			'text'    => $is_active
				? __( '<i data-lucide="check-circle-2"></i> That\'s the full dashboard. Your Profile link is in the bottom group — keep it up to date so buyers trust your services.', 'wp-sell-services' )
				: __( '<i data-lucide="check-circle-2"></i> That\'s the whole dashboard. Browse services, post a request, or open a conversation whenever you need.', 'wp-sell-services' ),
			'buttons' => array(
				$back_btn,
				array(
					'text'    => __( 'Finish', 'wp-sell-services' ),
					'action'  => 'complete',
					'classes' => 'shepherd-button-primary',
				),
			),
		);

		return $steps;
	}
}
