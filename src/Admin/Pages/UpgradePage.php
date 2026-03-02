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
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'wp-sell-services',
			__( 'Upgrade to Pro', 'wp-sell-services' ),
			__( 'Upgrade to Pro', 'wp-sell-services' ),
			'manage_options',
			'wpss-upgrade',
			array( $this, 'render' )
		);
	}

	/**
	 * Get feature comparison data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_features(): array {
		return array(
			__( 'Marketplace & Services', 'wp-sell-services' )   => array(
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
			__( 'Orders & Workflow', 'wp-sell-services' )        => array(
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
			__( 'E-Commerce Integration', 'wp-sell-services' )   => array(
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
				array(
					'feature' => __( 'SureCart', 'wp-sell-services' ),
					'free'    => false,
					'pro'     => true,
				),
				array(
					'feature' => __( 'Standalone (no e-commerce plugin needed)', 'wp-sell-services' ),
					'free'    => true,
					'pro'     => true,
				),
			),
			__( 'Payment Gateways', 'wp-sell-services' )        => array(
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
			__( 'Storage & Media', 'wp-sell-services' )         => array(
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
			),
			__( 'Support & Updates', 'wp-sell-services' )       => array(
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
			echo '<span class="wpss-feature-yes dashicons dashicons-yes-alt"></span>';
		} elseif ( false === $value ) {
			echo '<span class="wpss-feature-no dashicons dashicons-minus"></span>';
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
		$features   = $this->get_features();
		$upgrade_url = 'https://wbcomdesigns.com/downloads/wp-sell-services-pro/';
		?>
		<div class="wrap wpss-upgrade-wrap">
			<div class="wpss-upgrade-header">
				<h1><?php esc_html_e( 'Upgrade to WP Sell Services Pro', 'wp-sell-services' ); ?></h1>
				<p class="wpss-upgrade-tagline">
					<?php esc_html_e( 'Unlock additional e-commerce platforms, direct payment gateways, cloud storage, advanced analytics, and priority support.', 'wp-sell-services' ); ?>
				</p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero wpss-upgrade-cta" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get Pro Now', 'wp-sell-services' ); ?>
				</a>
			</div>

			<?php foreach ( $features as $section_label => $section_features ) : ?>
				<div class="wpss-comparison-section">
					<h2><?php echo esc_html( $section_label ); ?></h2>
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
			<?php endforeach; ?>

			<div class="wpss-upgrade-footer">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero wpss-upgrade-cta" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get Pro Now', 'wp-sell-services' ); ?>
				</a>
				<p class="wpss-upgrade-guarantee">
					<?php esc_html_e( '14-day money-back guarantee. Cancel anytime.', 'wp-sell-services' ); ?>
				</p>
			</div>
		</div>

		<style>
			.wpss-upgrade-wrap {
				max-width: 900px;
			}
			.wpss-upgrade-header {
				text-align: center;
				padding: 40px 20px 30px;
			}
			.wpss-upgrade-header h1 {
				font-size: 28px;
				margin-bottom: 10px;
			}
			.wpss-upgrade-tagline {
				font-size: 15px;
				color: #646970;
				max-width: 600px;
				margin: 0 auto 20px;
			}
			.wpss-upgrade-cta {
				font-size: 16px !important;
				padding: 8px 32px !important;
				height: auto !important;
				background: #1dbf73 !important;
				border-color: #1dbf73 !important;
			}
			.wpss-upgrade-cta:hover,
			.wpss-upgrade-cta:focus {
				background: #19a463 !important;
				border-color: #19a463 !important;
			}
			.wpss-comparison-section {
				margin-bottom: 30px;
			}
			.wpss-comparison-section h2 {
				font-size: 16px;
				margin: 0 0 8px;
				padding: 0;
			}
			.wpss-comparison-table {
				border-collapse: collapse;
			}
			.wpss-comparison-table th,
			.wpss-comparison-table td {
				padding: 10px 14px;
			}
			.wpss-comparison-table thead th {
				font-weight: 600;
				background: #f0f0f1;
			}
			.wpss-feature-col {
				width: 60%;
			}
			.wpss-plan-col {
				width: 20%;
				text-align: center;
			}
			.wpss-plan-pro {
				background: #f0faf5;
			}
			.wpss-feature-yes {
				color: #00a32a;
				font-size: 20px;
			}
			.wpss-feature-no {
				color: #cc1818;
				font-size: 20px;
			}
			.wpss-feature-text {
				font-size: 13px;
				color: #996800;
				font-weight: 500;
			}
			.wpss-upgrade-footer {
				text-align: center;
				padding: 30px 20px 10px;
			}
			.wpss-upgrade-guarantee {
				margin-top: 10px;
				color: #646970;
				font-size: 13px;
			}
		</style>
		<?php
	}
}
