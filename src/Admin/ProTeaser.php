<?php
/**
 * Pro Upgrade Teasers
 *
 * Renders tasteful Pro upgrade CTAs throughout the free plugin
 * when the Pro plugin is not active.
 *
 * @package WPSellServices\Admin
 * @since   1.3.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * ProTeaser class.
 *
 * @since 1.3.0
 */
class ProTeaser {

	/**
	 * Initialize teasers.
	 *
	 * Bails immediately if Pro is active.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( defined( 'WPSS_PRO_VERSION' ) ) {
			return;
		}

		// Admin: Locked Analytics tab in settings.
		add_filter( 'wpss_settings_tabs', array( $this, 'add_analytics_tab' ) );
		add_action( 'wpss_settings_tab_analytics', array( $this, 'render_analytics_tab' ) );

		// Admin: Vendor settings accordion teaser.
		add_action( 'wpss_settings_sections_vendor', array( $this, 'render_vendor_settings_teaser' ) );

		// Frontend: Service creation section teaser.
		add_action( 'wpss_dashboard_section_after', array( $this, 'render_section_teasers' ), 10, 2 );

		// The teaser renders on BOTH surfaces, so ONE stylesheet is registered
		// for both rather than its rules being copied into an admin sheet and a
		// frontend sheet — which is the duplicate-class problem this cleanup
		// exists to remove.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue the teaser stylesheet.
	 *
	 * Replaces two inline <style> blocks — one of which needed a static flag
	 * purely to stop it printing three times on a page with three teasers.
	 *
	 * @since 1.5.1
	 * @return void
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style(
			'wpss-pro-teaser',
			\WPSS_PLUGIN_URL . 'assets/css/pro-teaser.css',
			array(),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-pro-teaser', 'rtl', 'replace' );
	}

	/**
	 * Add locked Analytics tab to settings.
	 *
	 * Inserts before the Advanced tab so it appears in the Pro group.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_analytics_tab( array $tabs ): array {
		$tabs['analytics'] = __( 'Analytics', 'wp-sell-services' );

		return $tabs;
	}

	/**
	 * Render the locked Analytics tab content.
	 *
	 * Shows a blurred preview with an upgrade overlay.
	 *
	 * @return void
	 */
	public function render_analytics_tab(): void {
		$upgrade_url = admin_url( 'admin.php?page=wpss-upgrade' );
		?>
		<div class="wpss-pro-locked">
			<div class="wpss-pro-locked__preview" aria-hidden="true">
				<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
					<div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
						<div style="background:#e5e5e5;height:12px;width:40%;border-radius:4px;margin-bottom:12px;"></div>
						<div style="background:#ddd;height:24px;width:60%;border-radius:4px;"></div>
					</div>
					<div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
						<div style="background:#e5e5e5;height:12px;width:50%;border-radius:4px;margin-bottom:12px;"></div>
						<div style="background:#ddd;height:24px;width:45%;border-radius:4px;"></div>
					</div>
					<div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
						<div style="background:#e5e5e5;height:12px;width:35%;border-radius:4px;margin-bottom:12px;"></div>
						<div style="background:#ddd;height:24px;width:55%;border-radius:4px;"></div>
					</div>
					<div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
						<div style="background:#e5e5e5;height:12px;width:45%;border-radius:4px;margin-bottom:12px;"></div>
						<div style="background:#ddd;height:24px;width:50%;border-radius:4px;"></div>
					</div>
				</div>
				<div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;height:200px;">
					<div style="background:#e5e5e5;height:12px;width:20%;border-radius:4px;margin-bottom:16px;"></div>
					<div style="background:#f0f0f0;height:140px;border-radius:4px;"></div>
				</div>
			</div>
			<div class="wpss-pro-locked__overlay">
				<span class="wpss-pro-teaser__badge"><?php esc_html_e( 'Pro', 'wp-sell-services' ); ?></span>
				<h2 style="margin:12px 0 8px;font-size:20px;">
					<?php esc_html_e( 'Detailed Analytics Dashboard', 'wp-sell-services' ); ?>
				</h2>
				<p style="color:#646970;font-size:14px;margin:0 0 16px;max-width:400px;">
					<?php esc_html_e( 'Revenue charts, vendor performance metrics, order trends, and CSV exports.', 'wp-sell-services' ); ?>
				</p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="wpss-pro-teaser__cta">
					<?php esc_html_e( 'Upgrade to Pro', 'wp-sell-services' ); ?>
				</a>
			</div>
		</div>

		<?php
	}

	/**
	 * Render vendor settings teaser card section.
	 *
	 * Appears after core vendor settings sections.
	 *
	 * @return void
	 */
	public function render_vendor_settings_teaser(): void {
		$upgrade_url = admin_url( 'admin.php?page=wpss-upgrade' );
		?>
		<div class="wpss-card" data-section="pro-vendor-features">
			<div class="wpss-card__head">
				<p class="wpss-card__title">
					<?php esc_html_e( 'ADVANCED VENDOR FEATURES', 'wp-sell-services' ); ?>
					<span class="wpss-pro-badge"><?php esc_html_e( 'Pro', 'wp-sell-services' ); ?></span>
				</p>
			</div>
			<div class="wpss-card__body">
				<div class="wpss-pro-teaser" style="border-left-width:0;margin:0;">
					<p class="wpss-pro-teaser__text" style="margin-bottom:16px;">
						<?php esc_html_e( 'Unlock powerful vendor management tools with Pro:', 'wp-sell-services' ); ?>
					</p>
					<ul style="margin:0 0 16px 16px;color:#646970;font-size:13px;line-height:2;">
						<li><?php esc_html_e( 'Tiered commission rates based on revenue or order volume', 'wp-sell-services' ); ?></li>
						<li><?php esc_html_e( 'Vendor subscription plans with recurring billing', 'wp-sell-services' ); ?></li>
						<li><?php esc_html_e( 'White-label branding for your marketplace', 'wp-sell-services' ); ?></li>
						<li><?php esc_html_e( 'Stripe Connect for automatic vendor payouts', 'wp-sell-services' ); ?></li>
					</ul>
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="wpss-pro-teaser__cta">
						<?php esc_html_e( 'Upgrade to Pro', 'wp-sell-services' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render earnings summary teaser.
	 *
	 * Appears after the earnings summary stats on the vendor dashboard.
	 *
	 * @param int $user_id Current user ID.
	 * @return void
	 */
	public function render_earnings_teaser( int $user_id ): void {
		$upgrade_url = admin_url( 'admin.php?page=wpss-upgrade' );
		?>
		<div class="wpss-pro-teaser" style="margin-top:1.5rem;">
			<span class="wpss-pro-teaser__badge"><?php esc_html_e( 'Pro', 'wp-sell-services' ); ?></span>
			<p class="wpss-pro-teaser__title">
				<?php esc_html_e( 'Want more insights into your earnings?', 'wp-sell-services' ); ?>
			</p>
			<p class="wpss-pro-teaser__text">
				<?php esc_html_e( 'Unlock revenue charts, auto-payouts, wallet integrations, and detailed analytics with Pro.', 'wp-sell-services' ); ?>
			</p>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="wpss-pro-teaser__cta">
				<?php esc_html_e( 'Learn More', 'wp-sell-services' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render section-specific teasers.
	 *
	 * Hooks into wpss_dashboard_section_after and renders
	 * a teaser only for the 'create' (service wizard) section.
	 *
	 * @param string $section Section identifier.
	 * @param int    $user_id Current user ID.
	 * @return void
	 */
	public function render_section_teasers( string $section, int $user_id ): void {
		if ( 'create' !== $section ) {
			return;
		}

		$upgrade_url = admin_url( 'admin.php?page=wpss-upgrade' );
		?>
		<div class="wpss-pro-teaser" style="margin-top:1.5rem;">
			<span class="wpss-pro-teaser__badge"><?php esc_html_e( 'Pro', 'wp-sell-services' ); ?></span>
			<p class="wpss-pro-teaser__text" style="margin-top:8px;">
				<?php esc_html_e( 'Enable recurring billing for subscription-based services with Pro.', 'wp-sell-services' ); ?>
			</p>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="wpss-pro-teaser__cta">
				<?php esc_html_e( 'Learn More', 'wp-sell-services' ); ?>
			</a>
		</div>
		<?php
	}
}
