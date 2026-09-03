<?php
/**
 * Standalone Account Provider
 *
 * @package WPSellServices\Integrations\Standalone
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Standalone;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\AccountProviderInterface;

/**
 * Account provider for standalone mode.
 *
 * @since 1.0.0
 */
class StandaloneAccountProvider implements AccountProviderInterface {

	/**
	 * Get account page URL.
	 *
	 * @param string $endpoint Optional endpoint to append.
	 * @return string
	 */
	public function get_account_url( string $endpoint = '' ): string {
		return wpss_get_dashboard_url( $endpoint );
	}

	/**
	 * Add menu items to account navigation.
	 *
	 * @param array $items Existing menu items.
	 * @return array
	 */
	public function add_menu_items( array $items ): array {
		$items['wpss-orders'] = array(
			'label' => __( 'Service Orders', 'wp-sell-services' ),
			'url'   => $this->get_orders_url(),
		);

		if ( $this->can_access_vendor_dashboard() ) {
			$items['wpss-vendor-dashboard'] = array(
				'label' => __( 'Vendor Dashboard', 'wp-sell-services' ),
				'url'   => $this->get_vendor_dashboard_url(),
			);
		}

		return $items;
	}

	/**
	 * Render orders endpoint content.
	 *
	 * @return void
	 */
	public function render_orders_endpoint(): void {
		echo $this->render_dashboard_section( 'orders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output, escaped at source.
	}

	/**
	 * Render vendor services endpoint content.
	 *
	 * The provider's own vendor-services screen is gone - the dashboard's
	 * `services` section is the single implementation - so this endpoint renders
	 * that instead of a second copy.
	 *
	 * @since 1.6.0 Delegates to the dashboard rather than its own screen.
	 *
	 * @return void
	 */
	public function render_services_endpoint(): void {
		echo $this->render_dashboard_section( 'services' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output, escaped at source.
	}

	/**
	 * Render one dashboard section through the canonical dashboard shortcode.
	 *
	 * @since 1.6.0
	 *
	 * @param string $section Dashboard section slug.
	 * @return string Rendered markup.
	 */
	private function render_dashboard_section( string $section ): string {
		if ( '' !== $section && '' === (string) get_query_var( 'wpss_section', '' ) ) {
			set_query_var( 'wpss_section', $section );
		}

		return do_shortcode( '[wpss_dashboard]' );
	}

	/**
	 * Render notifications endpoint content.
	 *
	 * @return void
	 */
	public function render_notifications_endpoint(): void {
		echo $this->render_dashboard_section( 'notifications' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output, escaped at source.
	}

	/**
	 * Check if current user can access vendor dashboard.
	 *
	 * @return bool
	 */
	public function can_access_vendor_dashboard(): bool {
		return is_user_logged_in() && wpss_is_vendor();
	}

	/**
	 * Get login URL.
	 *
	 * @param string $redirect Redirect URL after login.
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ): string {
		if ( ! $redirect ) {
			$redirect = $this->get_account_url();
		}
		return wp_login_url( $redirect );
	}

	/**
	 * Get registration URL.
	 *
	 * @return string
	 */
	public function get_register_url(): string {
		return wp_registration_url();
	}

	/**
	 * Get orders page URL.
	 *
	 * @return string
	 */
	public function get_orders_url(): string {
		return wpss_get_dashboard_url( 'orders' );
	}

	/**
	 * Get vendor dashboard URL.
	 *
	 * @return string
	 */
	public function get_vendor_dashboard_url(): string {
		// `overview` was never a dashboard section — the slug had no template
		// and rendered a dead "Section Not Available" card. A seller's
		// operational home is Sales Orders, which is also where the dashboard
		// lands an active vendor by default.
		return wpss_get_dashboard_url( 'sales' );
	}

	/**
	 * Get vendor services URL.
	 *
	 * @return string
	 */
	public function get_vendor_services_url(): string {
		return wpss_get_dashboard_url( 'services' );
	}

	/**
	 * Get notifications URL.
	 *
	 * @return string
	 */
	public function get_notifications_url(): string {
		return wpss_get_dashboard_url( 'notifications' );
	}

	/**
	 * Register account endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		// Endpoints are registered via rewrite rules in StandaloneAdapter.
	}

	/**
	 * Get account menu items.
	 *
	 * @return array
	 */
	public function get_menu_items(): array {
		$items = array(
			'dashboard'     => array(
				'label' => __( 'Dashboard', 'wp-sell-services' ),
				'url'   => $this->get_account_url(),
				'icon'  => 'layout-dashboard',
			),
			'orders'        => array(
				'label' => __( 'Service Orders', 'wp-sell-services' ),
				'url'   => $this->get_orders_url(),
				'icon'  => 'list',
			),
			'notifications' => array(
				'label' => __( 'Notifications', 'wp-sell-services' ),
				'url'   => $this->get_notifications_url(),
				'icon'  => 'bell',
			),
		);

		// Add vendor items if user is a vendor.
		if ( wpss_is_vendor() ) {
			$items['vendor-dashboard'] = array(
				'label' => __( 'Vendor Dashboard', 'wp-sell-services' ),
				'url'   => $this->get_vendor_dashboard_url(),
				'icon'  => 'store',
			);
			$items['vendor-services']  = array(
				'label' => __( 'My Services', 'wp-sell-services' ),
				'url'   => $this->get_vendor_services_url(),
				'icon'  => 'wrench',
			);
		}

		return $items;
	}

	/**
	 * Render account shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_account_shortcode( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_form();
		}

		// [wpss_account] is a WRAPPER around the one dashboard, not a second
		// implementation of it.
		//
		// There used to be two member dashboards: this provider's own
		// orders / notifications / vendor-dashboard / vendor-services screens,
		// and UnifiedDashboard's fifteen sections. The nav in get_menu_items()
		// had already been migrated to point at UnifiedDashboard, so the screens
		// below were reachable only by hand-typing ?wpss_account_page=... - which
		// is precisely why a fatal could sit in render_vendor_dashboard()
		// unnoticed (Basecamp #10185434520).
		//
		// Basecamp asked for the nav to be pointed back at this dispatcher. That
		// would have revived the duplicate rather than removed it, against the
		// plugin's own non-negotiable rule that one flow has one implementation.
		// UnifiedDashboard's sections are a strict SUPERSET of these four, so the
		// legacy screens are deleted and the shortcode maps onto the survivor.
		//
		// The legacy ?wpss_account_page= values are translated so old links,
		// bookmarks and rewrite rules keep working.
		$legacy_page = (string) ( get_query_var( 'wpss_account_page' ) ?: '' );

		$section_map = array(
			'orders'           => 'orders',
			'notifications'    => 'notifications',
			// A seller's operational home is Sales Orders - the same choice
			// get_vendor_dashboard_url() already makes.
			'vendor-dashboard' => 'sales',
			'vendor-services'  => 'services',
			'dashboard'        => '',
		);

		$section = $section_map[ $legacy_page ] ?? '';

		/**
		 * Filters the dashboard section a legacy [wpss_account] page maps to.
		 *
		 * @since 1.6.0
		 *
		 * @param string $section     UnifiedDashboard section slug ('' for default).
		 * @param string $legacy_page The ?wpss_account_page= value.
		 */
		$section = (string) apply_filters( 'wpss_account_page_section', $section, $legacy_page );

		// Steer the dashboard through the query var its own resolver reads first
		// (UnifiedDashboard::resolve_current_section), which is the same var the
		// /{dashboard}/{section}/ rewrite populates. Passing a shortcode
		// attribute would NOT work - render() ignores $atts and resolves the
		// section from request state.
		//
		// Only set when the legacy URL actually named a page; otherwise leave the
		// dashboard to its own role-aware default.
		return $this->render_dashboard_section( $section );
	}

	/**
	 * Render login form for non-logged-in users.
	 *
	 * @return string
	 */
	private function render_login_form(): string {
		ob_start();
		?>
		<div class="wpss-account-login">
			<h2><?php esc_html_e( 'Account Login', 'wp-sell-services' ); ?></h2>
			<p><?php esc_html_e( 'Please log in to access your account.', 'wp-sell-services' ); ?></p>

			<?php wp_login_form( array( 'redirect' => $this->get_account_url() ) ); ?>

			<p class="wpss-register-link">
				<?php esc_html_e( "Don't have an account?", 'wp-sell-services' ); ?>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>">
					<?php esc_html_e( 'Register', 'wp-sell-services' ); ?>
				</a>
			</p>
		</div>

		<style>
			.wpss-account-login {
				max-width: 400px;
				margin: 40px auto;
				padding: 30px;
				/* Surface token, not the literal white - see design-system.css. */
				background: var(--wpss-surface, #fff);
				border: 1px solid var(--wpss-border, #e5e5e5);
				border-radius: 8px;
			}
			.wpss-account-login h2 {
				margin-top: 0;
			}
			.wpss-register-link {
				text-align: center;
				margin-top: 20px;
			}
		</style>
		<?php
		return ob_get_clean();
	}
}
