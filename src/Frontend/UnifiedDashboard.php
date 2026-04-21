<?php
/**
 * Unified Dashboard
 *
 * Single dashboard for both buyers and vendors with context-aware navigation.
 *
 * @package WPSellServices\Frontend
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Services\VendorService;

defined( 'ABSPATH' ) || exit;

/**
 * UnifiedDashboard class.
 *
 * Replaces separate vendor and buyer dashboards with a single unified interface.
 *
 * @since 1.1.0
 */
class UnifiedDashboard {

	/**
	 * Vendor service instance.
	 *
	 * @var VendorService
	 */
	private VendorService $vendor_service;

	/**
	 * Current section.
	 *
	 * @var string
	 */
	private string $current_section = 'orders';

	/**
	 * Available sections.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $sections = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_service = new VendorService();
	}

	/**
	 * Initialize the dashboard.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'wpss_dashboard', array( $this, 'render' ) );
		add_action( 'wp_ajax_wpss_become_vendor', array( $this, 'ajax_become_vendor' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue dashboard assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Media library for profile avatar/portfolio uploads.
		if ( is_user_logged_in() ) {
			// Grant upload_files capability temporarily for non-vendor users on the dashboard
			// so customers can upload profile images via the WP Media Library.
			// Uses a filter instead of $user->add_cap() to avoid persisting to the database.
			$user = wp_get_current_user();
			if ( $user->exists() && ! $user->has_cap( 'upload_files' ) ) {
				add_filter(
					'user_has_cap',
					static function ( array $allcaps ) use ( $user ): array {
						$allcaps['upload_files'] = true;
						return $allcaps;
					}
				);
			}
			wp_enqueue_media();
		}

		// Enqueue frontend assets to ensure wpssData is available for WPSS functions
		wpss_enqueue_frontend_assets();

		wp_enqueue_style(
			'wpss-unified-dashboard',
			WPSS_PLUGIN_URL . 'assets/css/unified-dashboard.css',
			array(),
			WPSS_VERSION
		);
		wp_style_add_data( 'wpss-unified-dashboard', 'rtl', 'replace' );

		wp_enqueue_script(
			'wpss-unified-dashboard',
			WPSS_PLUGIN_URL . 'assets/js/unified-dashboard.js',
			array( 'jquery' ),
			WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-unified-dashboard',
			'wpssUnifiedDashboard',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wpss_dashboard_nonce' ),
				'serviceNonce' => wp_create_nonce( 'wpss_service_nonce' ),
				'restUrl'      => esc_url_raw( rest_url( 'wpss/v1/' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'         => array(
					'becomeVendorConfirm'    => __( 'Start selling services on this marketplace?', 'wp-sell-services' ),
					'processing'             => __( 'Processing...', 'wp-sell-services' ),
					'confirmDelete'          => __( 'Are you sure you want to delete this service? This action cannot be undone.', 'wp-sell-services' ),
					'pause'                  => __( 'Pause', 'wp-sell-services' ),
					'activate'               => __( 'Activate', 'wp-sell-services' ),
					'closeRequestConfirm'    => __( 'Close this request? It will no longer be visible to sellers.', 'wp-sell-services' ),
					'reopenRequestConfirm'   => __( 'Reopen this request? It will be visible to sellers again.', 'wp-sell-services' ),
					'deleteRequestConfirm'   => __( 'Delete this request permanently? This cannot be undone.', 'wp-sell-services' ),
					'deletePortfolioConfirm' => __( 'Are you sure you want to delete this portfolio item?', 'wp-sell-services' ),
					'deleteConfirmBtn'       => __( 'Delete', 'wp-sell-services' ),
					'errorOccurred'          => __( 'An error occurred.', 'wp-sell-services' ),
					'errorTryAgain'          => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'published'              => __( 'Published', 'wp-sell-services' ),
					'draft'                  => __( 'Draft', 'wp-sell-services' ),
					'requestClosed'          => __( 'Request closed.', 'wp-sell-services' ),
					'requestCloseFailed'     => __( 'Failed to close request.', 'wp-sell-services' ),
					'requestReopened'        => __( 'Request reopened.', 'wp-sell-services' ),
					'requestReopenFailed'    => __( 'Failed to reopen request.', 'wp-sell-services' ),
					'requestDeleted'         => __( 'Request deleted.', 'wp-sell-services' ),
					'requestDeleteFailed'    => __( 'Failed to delete request.', 'wp-sell-services' ),
					'deleteFailed'           => __( 'Delete failed.', 'wp-sell-services' ),
					'saveFailed'             => __( 'Save failed.', 'wp-sell-services' ),
					'failed'                 => __( 'Failed.', 'wp-sell-services' ),
					'favoriteCountSingular'  => __( '%d saved service', 'wp-sell-services' ),
					'favoriteCountPlural'    => __( '%d saved services', 'wp-sell-services' ),
					'favoriteRemoveFailed'   => __( 'Could not remove favorite. Please try again.', 'wp-sell-services' ),
					'chooseProfilePhoto'     => __( 'Choose Profile Photo', 'wp-sell-services' ),
					'useAsProfilePhoto'      => __( 'Use as Profile Photo', 'wp-sell-services' ),
					'selectCoverImage'       => __( 'Select Cover Image', 'wp-sell-services' ),
					'setCoverImage'          => __( 'Set Cover Image', 'wp-sell-services' ),
					'addPortfolioItem'       => __( 'Add Portfolio Item', 'wp-sell-services' ),
					'editPortfolioItem'      => __( 'Edit Portfolio Item', 'wp-sell-services' ),
					'selectPortfolioImages'  => __( 'Select Portfolio Images', 'wp-sell-services' ),
					'addToPortfolio'         => __( 'Add to Portfolio', 'wp-sell-services' ),
					'remove'                 => __( 'Remove', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Check if current page is dashboard.
	 *
	 * @return bool
	 */
	private function is_dashboard_page(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'wpss_dashboard' );
	}

	/**
	 * Render the dashboard.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Dashboard HTML.
	 */
	public function render( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Section routing, no data processing.
		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'orders';

		// Validate section access — vendor-only sections show a fallback message
		// instead of silently redirecting to orders (which confused users).

		$this->current_section = $section;
		$this->sections        = $this->get_sections();

		ob_start();
		$this->render_shell();
		return ob_get_clean();
	}

	/**
	 * Check if user can access a section.
	 *
	 * Vendor-only sections require the user to be an active (approved) vendor.
	 * Pending vendors are not granted access to selling sections.
	 *
	 * @param string $section Section slug.
	 * @return bool True if accessible.
	 */
	private function can_access_section( string $section ): bool {
		$vendor_only_sections = array( 'services', 'sales', 'earnings', 'wallet', 'analytics', 'portfolio', 'create' );
		$user_id              = get_current_user_id();

		if ( in_array( $section, $vendor_only_sections, true ) ) {
			// Must be an active vendor (not just registered/pending).
			return $this->vendor_service->is_vendor( $user_id )
				&& 'active' === $this->vendor_service->get_vendor_status( $user_id );
		}

		/**
		 * Filter whether user can access a dashboard section.
		 *
		 * @since 1.1.0
		 * @param bool   $can_access Whether user can access section.
		 * @param string $section    Section slug.
		 * @param int    $user_id    Current user ID.
		 */
		return apply_filters( 'wpss_can_access_dashboard_section', true, $section, $user_id );
	}

	/**
	 * Get all available sections.
	 *
	 * The Selling section is only shown for active (approved) vendors.
	 * Pending vendors see only Buying and Account sections.
	 *
	 * @return array<string, array<string, mixed>> Sections configuration.
	 */
	private function get_sections(): array {
		$user_id       = get_current_user_id();
		$is_vendor     = $this->vendor_service->is_vendor( $user_id );
		$vendor_status = $this->vendor_service->get_vendor_status( $user_id );
		$is_active     = $is_vendor && 'active' === $vendor_status;

		$sections = array(
			'buying' => array(
				'label' => __( 'Buying', 'wp-sell-services' ),
				'items' => array(
					'orders'    => array(
						'icon'  => 'shopping-bag',
						'label' => __( 'My Orders', 'wp-sell-services' ),
					),
					'favorites' => array(
						'icon'  => 'heart',
						'label' => __( 'Favorites', 'wp-sell-services' ),
					),
					'requests'  => array(
						'icon'  => 'megaphone',
						'label' => __( 'Buyer Requests', 'wp-sell-services' ),
					),
				),
			),
		);

		if ( $is_active ) {
			$sections['selling'] = array(
				'label' => __( 'Selling', 'wp-sell-services' ),
				'items' => array(
					'services'  => array(
						'icon'  => 'briefcase',
						'label' => __( 'My Services', 'wp-sell-services' ),
					),
					'sales'     => array(
						'icon'  => 'receipt',
						'label' => __( 'Sales Orders', 'wp-sell-services' ),
					),
					'earnings'  => array(
						'icon'  => 'wallet',
						'label' => __( 'Earnings', 'wp-sell-services' ),
					),
					'portfolio' => array(
						'icon'  => 'folder',
						'label' => __( 'Portfolio', 'wp-sell-services' ),
					),
				),
			);
		}

		$sections['account'] = array(
			'label' => __( 'Account', 'wp-sell-services' ),
			'items' => array(
				'messages' => array(
					'icon'  => 'chat',
					'label' => __( 'Messages', 'wp-sell-services' ),
				),
				'profile'  => array(
					'icon'  => 'user',
					'label' => __( 'Profile', 'wp-sell-services' ),
				),
			),
		);

		/**
		 * Filter dashboard sections.
		 *
		 * @since 1.1.0
		 * @param array $sections  Sections configuration.
		 * @param int   $user_id   Current user ID.
		 * @param bool  $is_vendor Whether user is a vendor (active).
		 */
		return apply_filters( 'wpss_dashboard_sections', $sections, $user_id, $is_active );
	}

	/**
	 * Render login prompt.
	 *
	 * @return string Login prompt HTML.
	 */
	private function render_login_prompt(): string {
		$login_url = wp_login_url( get_permalink() ?: home_url() );

		return sprintf(
			'<div class="wpss-dashboard-login">
				<div class="wpss-dashboard-login__icon">
					<i data-lucide="user" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
				</div>
				<h2>%s</h2>
				<p>%s</p>
				<a href="%s" class="wpss-btn wpss-btn--primary">%s</a>
			</div>',
			esc_html__( 'Access Your Dashboard', 'wp-sell-services' ),
			esc_html__( 'Please log in to view your orders, messages, and manage your services.', 'wp-sell-services' ),
			esc_url( $login_url ),
			esc_html__( 'Log In', 'wp-sell-services' )
		);
	}

	/**
	 * Render the dashboard shell.
	 *
	 * @return void
	 */
	private function render_shell(): void {
		$user_id       = get_current_user_id();
		$user          = get_userdata( $user_id );
		$is_vendor     = $this->vendor_service->is_vendor( $user_id );
		$vendor_status = $this->vendor_service->get_vendor_status( $user_id );
		$is_active     = $is_vendor && 'active' === $vendor_status;
		$is_pending    = 'pending' === $vendor_status;
		$section_data  = $this->get_section_data( $this->current_section );
		?>
		<div class="wpss-dashboard">
			<aside class="wpss-dashboard__sidebar">
				<div class="wpss-dashboard__user">
					<?php echo get_avatar( $user_id, 48, '', '', array( 'class' => 'wpss-dashboard__avatar' ) ); ?>
					<div class="wpss-dashboard__user-info">
						<span class="wpss-dashboard__user-name"><?php echo esc_html( $user->display_name ); ?></span>
						<?php if ( $is_active ) : ?>
							<span class="wpss-dashboard__user-badge"><?php esc_html_e( 'Seller', 'wp-sell-services' ); ?></span>
						<?php elseif ( $is_pending ) : ?>
							<span class="wpss-dashboard__user-badge wpss-dashboard__user-badge--pending"><?php esc_html_e( 'Pending Approval', 'wp-sell-services' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<nav class="wpss-dashboard__nav">
					<?php foreach ( $this->sections as $group_key => $group ) : ?>
						<div class="wpss-dashboard__nav-group">
							<span class="wpss-dashboard__nav-label"><?php echo esc_html( $group['label'] ); ?></span>
							<ul class="wpss-dashboard__nav-list">
								<?php foreach ( $group['items'] as $item_key => $item ) : ?>
									<li>
										<a href="<?php echo esc_url( $this->get_section_url( $item_key ) ); ?>"
											class="wpss-dashboard__nav-item <?php echo $this->current_section === $item_key ? 'wpss-dashboard__nav-item--active' : ''; ?>">
											<?php $this->render_icon( $item['icon'] ); ?>
											<span><?php echo esc_html( $item['label'] ); ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</nav>

				<?php if ( $is_pending ) : ?>
					<div class="wpss-dashboard__pending-notice">
						<p><?php esc_html_e( 'Your vendor application is pending admin approval. You will be notified once your application is reviewed.', 'wp-sell-services' ); ?></p>
					</div>
					<?php
				elseif ( ! $is_vendor && ! $is_pending ) :
					$sb_vendor_settings   = get_option( 'wpss_vendor', array() );
					$sb_registration_mode = $sb_vendor_settings['vendor_registration'] ?? 'open';
					if ( 'closed' !== $sb_registration_mode ) :
						?>
					<div class="wpss-dashboard__become-vendor">
						<p><?php esc_html_e( 'Start selling your services', 'wp-sell-services' ); ?></p>
						<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--full" data-action="become-vendor">
							<?php esc_html_e( 'Start Selling', 'wp-sell-services' ); ?>
						</button>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			</aside>

			<main class="wpss-dashboard__content">
				<?php $this->maybe_render_payout_banner( $user_id, $is_active ); ?>
				<header class="wpss-dashboard__header">
					<h1 class="wpss-dashboard__title">
						<?php
						$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL parameter for display.
						if ( $id && 'create' !== $this->current_section ) {
							esc_html_e( 'Update Service', 'wp-sell-services' );
						} else {
							echo esc_html( $section_data['title'] );
						}
						?>
					</h1>
					<?php if ( $this->current_section === 'services' ) : ?>
						<a href="<?php echo esc_url( $this->get_section_url( 'create' ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Create Service', 'wp-sell-services' ); ?>
						</a>
					<?php elseif ( $this->current_section === 'requests' ) : ?>
						<a href="<?php echo esc_url( $this->get_section_url( 'create-request' ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Post Request', 'wp-sell-services' ); ?>
						</a>
					<?php endif; ?>
				</header>

				<div class="wpss-dashboard__body">
					<?php $this->render_section( $this->current_section ); ?>
				</div>
			</main>
		</div>
		<?php
	}

	/**
	 * Get section data.
	 *
	 * @param string $section Section slug.
	 * @return array<string, mixed> Section data.
	 */
	private function get_section_data( string $section ): array {
		$titles = array(
			'orders'         => __( 'My Orders', 'wp-sell-services' ),
			'favorites'      => __( 'Favorites', 'wp-sell-services' ),
			'requests'       => __( 'Buyer Requests', 'wp-sell-services' ),
			'services'       => __( 'My Services', 'wp-sell-services' ),
			'sales'          => __( 'Sales Orders', 'wp-sell-services' ),
			'earnings'       => __( 'Earnings', 'wp-sell-services' ),
			'wallet'         => __( 'Wallet & Earnings', 'wp-sell-services' ),
			'analytics'      => __( 'Analytics', 'wp-sell-services' ),
			'portfolio'      => __( 'Portfolio', 'wp-sell-services' ),
			'messages'       => __( 'Messages', 'wp-sell-services' ),
			'profile'        => __( 'Profile', 'wp-sell-services' ),
			'create'         => __( 'Create Service', 'wp-sell-services' ),
			'create-request' => __( 'Post a Request', 'wp-sell-services' ),
		);

		/**
		 * Filter dashboard section titles.
		 *
		 * @since 1.1.0
		 * @param array $titles Section titles keyed by slug.
		 */
		$titles = apply_filters( 'wpss_dashboard_section_titles', $titles );

		return array(
			'title' => $titles[ $section ] ?? __( 'Dashboard', 'wp-sell-services' ),
		);
	}

	/**
	 * Get section URL.
	 *
	 * @param string $section Section slug.
	 * @return string Section URL.
	 */
	private function get_section_url( string $section ): string {
		// Try to get the dashboard page URL from settings first (works in AJAX context).
		$base_url = wpss_get_page_url( 'dashboard' );

		// Fallback to get_permalink() for non-AJAX, then home_url() as last resort.
		if ( ! $base_url ) {
			$base_url = get_permalink() ?: home_url();
		}

		if ( 'orders' === $section ) {
			return $base_url;
		}

		return add_query_arg( 'section', $section, $base_url );
	}

	/**
	 * Render a section.
	 *
	 * @param string $section Section slug.
	 * @return void
	 */
	private function render_section( string $section ): void {
		$template_path = WPSS_PLUGIN_DIR . "templates/dashboard/sections/{$section}.php";

		/**
		 * Filter the template path for a dashboard section.
		 *
		 * Allows pro or third-party plugins to provide custom templates for sections.
		 *
		 * @since 1.1.0
		 * @param string $template_path Full path to section template.
		 * @param string $section       Section slug.
		 */
		$template_path = apply_filters( 'wpss_dashboard_section_template', $template_path, $section );

		$user_id        = get_current_user_id();
		$vendor_service = $this->vendor_service;
		$is_vendor      = $vendor_service->is_vendor( $user_id );

		// Check access: vendor-only sections require vendor status.
		$vendor_only_sections = array( 'services', 'sales', 'earnings', 'wallet', 'analytics', 'portfolio', 'create' );
		if ( ! $is_vendor && in_array( $section, $vendor_only_sections, true ) ) {
			$this->render_section_fallback( $section );
			return;
		}

		if ( file_exists( $template_path ) ) {
			/**
			 * Fires before the dashboard section content is rendered.
			 *
			 * Allows Pro or third-party plugins to inject banners or notices
			 * above the section content (e.g. subscription-required prompts).
			 *
			 * @since 1.2.0
			 *
			 * @param string $section Current section slug.
			 * @param int    $user_id Current user ID.
			 */
			do_action( 'wpss_dashboard_section_before_content', $section, $user_id );

			include $template_path;
		} else {
			$this->render_section_fallback( $section );
		}
	}

	/**
	 * Render fallback content for missing section templates.
	 *
	 * @param string $section Section slug.
	 * @return void
	 */
	private function render_section_fallback( string $section ): void {
		$user_id   = get_current_user_id();
		$is_vendor = $this->vendor_service->is_vendor( $user_id );

		// Check if vendor registration is open.
		$fb_vendor_settings   = get_option( 'wpss_vendor', array() );
		$fb_registration_mode = $fb_vendor_settings['vendor_registration'] ?? 'open';
		$registration_is_open = 'closed' !== $fb_registration_mode;

		// Vendor-only sections: show a CTA to become a vendor.
		$vendor_only_sections = array( 'services', 'sales', 'earnings', 'wallet', 'analytics', 'portfolio', 'create' );

		if ( 'become-vendor' === $section && ! $is_vendor && $registration_is_open ) {
			// The become-vendor section should show the vendor onboarding prompt, not an error.
			?>
			<div class="wpss-dashboard__empty">
				<div class="wpss-dashboard__empty-icon">
					<?php $this->render_icon( 'briefcase' ); ?>
				</div>
				<h3><?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?></h3>
				<p><?php esc_html_e( 'Start selling your services on this marketplace. Click the button below to register as a vendor and begin offering your skills.', 'wp-sell-services' ); ?></p>
				<button type="button" class="wpss-btn wpss-btn--primary" data-action="become-vendor">
					<?php esc_html_e( 'Start Selling', 'wp-sell-services' ); ?>
				</button>
			</div>
			<?php
		} elseif ( ! $is_vendor && in_array( $section, $vendor_only_sections, true ) ) {
			// Non-vendor trying to access vendor-only sections.
			?>
			<div class="wpss-dashboard__empty">
				<div class="wpss-dashboard__empty-icon">
					<?php $this->render_icon( 'briefcase' ); ?>
				</div>
				<h3><?php esc_html_e( 'Vendor Access Required', 'wp-sell-services' ); ?></h3>
				<p><?php esc_html_e( 'This section is available to vendors. Become a vendor to access this feature and start selling your services.', 'wp-sell-services' ); ?></p>
				<?php if ( $registration_is_open ) : ?>
				<button type="button" class="wpss-btn wpss-btn--primary" data-action="become-vendor">
					<?php esc_html_e( 'Start Selling', 'wp-sell-services' ); ?>
				</button>
				<?php endif; ?>
			</div>
			<?php
		} else {
			// Genuinely missing sections.
			?>
			<div class="wpss-dashboard__empty">
				<div class="wpss-dashboard__empty-icon">
					<?php $this->render_icon( 'folder' ); ?>
				</div>
				<h3><?php esc_html_e( 'Section Not Available', 'wp-sell-services' ); ?></h3>
				<p><?php esc_html_e( 'This section is not available.', 'wp-sell-services' ); ?></p>
				<a href="<?php echo esc_url( wpss_get_dashboard_url() ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Back to Dashboard', 'wp-sell-services' ); ?>
				</a>
			</div>
			<?php
		}
	}

	/**
	 * Show a payout setup banner if vendor has earnings but no payout method.
	 *
	 * @since 1.5.0
	 *
	 * @param int  $user_id   Current user ID.
	 * @param bool $is_active Whether user is an active vendor.
	 * @return void
	 */
	private function maybe_render_payout_banner( int $user_id, bool $is_active ): void {
		if ( ! $is_active ) {
			return;
		}

		$payout_method = get_user_meta( $user_id, 'wpss_payout_method', true );

		// Already configured - no banner needed.
		if ( ! empty( $payout_method ) ) {
			return;
		}

		// Check if vendor has any earnings.
		$earnings_service = new \WPSellServices\Services\EarningsService();
		$summary          = $earnings_service->get_summary( $user_id );
		$has_earnings     = ( $summary['available_balance'] ?? 0 ) > 0 || ( $summary['pending_clearance'] ?? 0 ) > 0;

		if ( ! $has_earnings ) {
			return;
		}

		$earnings_url = $this->get_section_url( 'earnings' );
		?>
		<div class="wpss-dashboard__payout-banner">
			<span class="wpss-payout-banner__icon">&#128176;</span>
			<div class="wpss-payout-banner__content">
				<strong class="wpss-payout-banner__title">
					<?php esc_html_e( 'You have earnings ready for withdrawal!', 'wp-sell-services' ); ?>
				</strong>
				<span class="wpss-payout-banner__text">
					<?php esc_html_e( 'Set up your payout method to start receiving payments.', 'wp-sell-services' ); ?>
				</span>
			</div>
			<a href="<?php echo esc_url( $earnings_url ); ?>" class="wpss-btn wpss-btn--primary wpss-payout-banner__btn">
				<?php esc_html_e( 'Set Up Payouts', 'wp-sell-services' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render a dashboard nav / empty-state icon using Lucide.
	 *
	 * Packet H (1.1.0): replaces previously-inlined Lucide SVGs with
	 * <i data-lucide="…" class="wpss-icon"> markers. The lucide vendor
	 * library (enqueued by `wpss_enqueue_frontend_assets()`) replaces the
	 * <i> with the correct SVG on DOMContentLoaded, and re-renders after
	 * any `wpss:icons:refresh` CustomEvent.
	 *
	 * Legacy short names (`chat`, `receipt`, `awards`, `chart-bar`) are
	 * aliased to their Lucide names so existing callers keep working.
	 *
	 * @param string $icon Internal icon name (may be a legacy alias).
	 * @return void
	 */
	private function render_icon( string $icon ): void {
		$aliases = array(
			'chat'      => 'message-square',
			'receipt'   => 'banknote',
			'awards'    => 'award',
			'chart-bar' => 'chart-column',
		);

		$lucide = $aliases[ $icon ] ?? $icon;
		// Only whitelisted characters are allowed in a lucide name to keep
		// the attribute safe — Lucide names are lowercase alphanumeric +
		// hyphen only.
		$lucide = preg_replace( '/[^a-z0-9-]/', '', $lucide );
		if ( '' === $lucide ) {
			return;
		}

		printf(
			'<i data-lucide="%s" class="wpss-icon" aria-hidden="true"></i>',
			esc_attr( $lucide )
		);
	}

	/**
	 * Handle AJAX become vendor request.
	 *
	 * @return void
	 */
	public function ajax_become_vendor(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in first.', 'wp-sell-services' ) ) );
		}

		// Reject if vendor registration is closed.
		$ajax_vendor_settings   = get_option( 'wpss_vendor', array() );
		$ajax_registration_mode = $ajax_vendor_settings['vendor_registration'] ?? 'open';
		if ( 'closed' === $ajax_registration_mode ) {
			wp_send_json_error( array( 'message' => __( 'Vendor registration is currently closed.', 'wp-sell-services' ) ) );
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are already a seller.', 'wp-sell-services' ) ) );
		}

		// Check for existing pending application.
		if ( $this->vendor_service->has_pending_application( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Your vendor application is already pending approval.', 'wp-sell-services' ) ) );
		}

		$result = $this->vendor_service->register( $user_id );

		if ( $result ) {
			// Check if approval is required (vendor will be in pending state).
			$vendor_settings   = get_option( 'wpss_vendor', array() );
			$registration_mode = $vendor_settings['vendor_registration'] ?? 'open';

			if ( 'approval' === $registration_mode ) {
				wp_send_json_success(
					array(
						'message'          => __( 'Your vendor application has been submitted! It is pending admin approval.', 'wp-sell-services' ),
						'pending_approval' => true,
						'redirect'         => $this->get_section_url( 'orders' ),
					)
				);
			} else {
				/**
				 * Filter the redirect URL after a vendor successfully registers.
				 *
				 * Allows Pro or third-party plugins to redirect newly registered vendors
				 * to a different page (e.g. subscription plan selection).
				 *
				 * @since 1.2.0
				 *
				 * @param string $redirect_url Default redirect URL (services section).
				 * @param int    $user_id      The newly registered vendor's user ID.
				 */
				$redirect_url = apply_filters(
					'wpss_after_become_vendor_redirect',
					$this->get_section_url( 'services' ),
					$user_id
				);

				wp_send_json_success(
					array(
						'message'  => __( 'Welcome! You can now create and sell services.', 'wp-sell-services' ),
						'redirect' => $redirect_url,
					)
				);
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Unable to complete registration. Please try again.', 'wp-sell-services' ) ) );
		}
	}
}
