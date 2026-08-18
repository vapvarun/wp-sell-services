<?php
/**
 * Upgrade to Pro Page
 *
 * Shows Free vs Pro feature comparison when Pro is not active.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Upgrade Page Class.
 *
 * @since 1.0.0
 */
class UpgradePage {

	/**
	 * Admin page hook suffix returned by add_submenu_page().
	 *
	 * Stored rather than hardcoded: the real suffix is derived from the PARENT
	 * menu title, so a hand-written guess silently never matches and the
	 * enqueue becomes dead code — exactly what had happened on the Withdrawals
	 * screen before 1.5.1.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'Upgrade to Pro', 'wp-sell-services' ),
			__( 'Upgrade to Pro', 'wp-sell-services' ),
			'manage_options',
			'wpss-upgrade',
			array( $this, 'render' )
		);

		if ( $hook ) {
			$this->hook_suffix = $hook;
		}
	}

	/**
	 * Enqueue the onboarding stylesheet on this screen only.
	 *
	 * @since 1.3.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		if ( '' === $this->hook_suffix || $this->hook_suffix !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wpss-admin-upgrade',
			\WPSS_PLUGIN_URL . 'assets/css/admin-upgrade.css',
			array( 'wpss-admin' ),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-admin-upgrade', 'rtl', 'replace' );
	}

	/**
	 * Get feature comparison data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_features(): array {
		return array(
			__( 'Marketplace & Services', 'wp-sell-services' ) => array(
				array(
					'feature' => __( 'Service listings with packages', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Service categories & tags', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Buyer requests & proposals', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Service extras & add-ons', 'wp-sell-services' ),
					'free'    => __( 'Limited', 'wp-sell-services' ),
					'pro'     => __( 'Unlimited', 'wp-sell-services' ),
				),
				array(
					'feature' => __( 'Service requirements', 'wp-sell-services' ),
					'free'    => __( 'Limited', 'wp-sell-services' ),
					'pro'     => __( 'Unlimited', 'wp-sell-services' ),
				),
				array(
					'feature' => __( 'Service FAQs', 'wp-sell-services' ),
					'free'    => __( 'Limited', 'wp-sell-services' ),
					'pro'     => __( 'Unlimited', 'wp-sell-services' ),
				),
			),
			__( 'Orders & Workflow', 'wp-sell-services' ) => array(
				array(
					'feature' => __( 'Order management', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Messaging & conversations', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Deliveries & revisions', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Disputes & resolution', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Milestone-based orders', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Order tipping', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
			),
			__( 'E-Commerce Integration', 'wp-sell-services' ) => array(
				array(
					'feature' => __( 'WooCommerce', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Easy Digital Downloads', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'FluentCart', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				// SureCart row removed in 1.6.1 with the integration itself. An
				// upgrade page that promises a rail Pro no longer has is a
				// straightforward misrepresentation to someone deciding whether
				// to buy.
				array(
					'feature' => __( 'Standalone (no e-commerce plugin needed)', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
			),
			__( 'Payment Gateways', 'wp-sell-services' )  => array(
				array(
					'feature' => __( 'WooCommerce payment gateways', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Direct Stripe integration', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Direct PayPal integration', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Razorpay integration', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
			),
			__( 'Storage & Media', 'wp-sell-services' )   => array(
				array(
					'feature' => __( 'Local file storage', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Amazon S3 cloud storage', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Google Cloud Storage', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'DigitalOcean Spaces', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
			),
			__( 'Vendor Payouts & Commissions', 'wp-sell-services' ) => array(
				array(
					'feature' => __( 'Manual payouts (mark as paid)', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Stripe Connect direct vendor payouts', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'PayPal mass payouts', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Tiered commission rules (category, volume, seller level)', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Vendor subscription plans', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
			),
			__( 'Analytics & Vendor Management', 'wp-sell-services' ) => array(
				array(
					'feature' => __( 'Basic vendor dashboard', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Vendor earnings & withdrawals', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Advanced analytics dashboard', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Revenue reports & charts', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Wallet integrations', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'White-label branding (admin, emails, dashboard)', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
			),
			__( 'Support & Updates', 'wp-sell-services' ) => array(
				array(
					'feature' => __( 'Community support', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Priority email support', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Automatic updates', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
			),
		);
	}

	/**
	 * Render a feature cell value.
	 *
	 * @param bool|string $value The feature value.
	 * @return void
	 */
	private function render_feature_value( $value ): void {
		if ( true === $value ) {
			echo '<i data-lucide="check-circle-2" class="wpss-icon wpss-feature-yes" aria-hidden="true"></i>';
		} elseif ( false === $value ) {
			echo '<i data-lucide="minus" class="wpss-icon wpss-feature-no" aria-hidden="true"></i>';
		} else {
			echo '<span class="wpss-feature-text">' . esc_html( (string) $value ) . '</span>';
		}
	}

	/**
	 * Render the upgrade page.
	 *
	 * @return void
	 */
	public function render(): void {
		$features = $this->get_features();

		/**
		 * Filters the "Get Pro" purchase URL shown on the upgrade screen.
		 *
		 * @since 1.2.2
		 *
		 * @param string $url Product site URL.
		 */
		$upgrade_url = apply_filters( 'wpss_pro_upgrade_url', 'https://wpsellservices.com/' );

		/**
		 * Filters the documentation URL shown on the upgrade screen.
		 *
		 * @since 1.2.2
		 *
		 * @param string $url Documentation URL.
		 */
		$docs_url = apply_filters( 'wpss_docs_url', 'https://wpsellservices.com/docs/' );
		?>
		<div class="wrap wpss-admin wpss-upgrade-wrap">
			<div class="wpss-page-header">
				<div class="wpss-page-header__left">
					<h1 class="wpss-page-header__title"><?php esc_html_e( 'Upgrade to WP Sell Services Pro', 'wp-sell-services' ); ?></h1>
					<p class="wpss-page-header__desc">
						<?php esc_html_e( 'Unlock WooCommerce checkout, Stripe Connect vendor payouts, tiered commissions, cloud storage, advanced analytics, and more.', 'wp-sell-services' ); ?>
					</p>
				</div>
			</div>

			<div class="wpss-card wpss-upgrade-hero">
				<div class="wpss-card__body">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero wpss-upgrade-cta" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Get Pro Now — Starting at $69/year', 'wp-sell-services' ); ?>
					</a>
					<a href="<?php echo esc_url( $docs_url ); ?>" class="wpss-upgrade-docs" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Read the Documentation', 'wp-sell-services' ); ?>
					</a>
					<p class="wpss-upgrade-guarantee">
						<?php esc_html_e( '30-day money-back guarantee. No questions asked.', 'wp-sell-services' ); ?>
					</p>
				</div>
			</div>

			<?php foreach ( $features as $section_label => $section_features ) : ?>
				<div class="wpss-card wpss-comparison-section">
					<div class="wpss-card__head">
						<p class="wpss-card__title"><?php echo esc_html( $section_label ); ?></p>
					</div>
					<div class="wpss-card__body">
						<table class="wpss-comparison-table widefat">
							<thead>
								<tr>
									<th class="wpss-feature-col"><?php esc_html_e( 'Feature', 'wp-sell-services' ); ?></th>
									<th class="wpss-plan-col"><?php esc_html_e( 'Free', 'wp-sell-services' ); ?></th>
									<th class="wpss-plan-col wpss-plan-pro"><?php esc_html_e( 'Pro', 'wp-sell-services' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $section_features as $feature ) : ?>
									<tr>
										<td class="wpss-feature-col"><?php echo esc_html( $feature['feature'] ); ?></td>
										<td class="wpss-plan-col"><?php $this->render_feature_value( $feature['free'] ); ?></td>
										<td class="wpss-plan-col wpss-plan-pro"><?php $this->render_feature_value( $feature['pro'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="wpss-card wpss-upgrade-footer">
				<div class="wpss-card__body">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero wpss-upgrade-cta" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Get Pro Now — Starting at $69/year', 'wp-sell-services' ); ?>
					</a>
					<p class="wpss-upgrade-guarantee">
						<?php esc_html_e( '30-day money-back guarantee. No questions asked.', 'wp-sell-services' ); ?>
					</p>
				</div>
			</div>
		</div>

		<?php
	}
}
