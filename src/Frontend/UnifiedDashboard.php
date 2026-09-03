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
use WPSellServices\Assets\ScriptRegistry;

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
	 * Sections only an active vendor may open.
	 *
	 * One list. It used to be copied into three methods, which is how a new
	 * selling section could be gated in one place and left open in another.
	 *
	 * @var string[]
	 */
	private const VENDOR_SECTIONS = array( 'services', 'sales', 'proposals', 'reviews', 'earnings', 'wallet', 'analytics', 'portfolio', 'create' );

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

		// Shared UI primitives: wpssConfirm (Promise modal) + wpssToast fallback.
		// Must be enqueued before any dashboard script (free or pro) that calls
		// wpssConfirm() / wpssToast().
		ScriptRegistry::enqueue_ui();

		wp_enqueue_style(
			'wpss-unified-dashboard',
			WPSS_PLUGIN_URL . 'assets/css/unified-dashboard.css',
			array(),
			WPSS_VERSION
		);
		wp_style_add_data( 'wpss-unified-dashboard', 'rtl', 'replace' );

		// The Messages section renders through wpss_render_message_row(), the
		// same renderer the order conversation uses, so it needs the same
		// stylesheet. That sheet used to be enqueued only from
		// templates/order/conversation.php, which is why the dashboard thread
		// could not simply reuse the renderer (Basecamp #10159632931).
		wp_enqueue_style(
			'wpss-messaging',
			WPSS_PLUGIN_URL . 'assets/css/messaging.css',
			array( 'wpss-design-system' ),
			WPSS_VERSION
		);
		wp_style_add_data( 'wpss-messaging', 'rtl', 'replace' );

		// wpss-ui provides window.wpssToast. The dashboard reports a saved
		// profile through it, so declare the dependency rather than relying on
		// some other surface having registered the handle first.
		ScriptRegistry::register_ui();

		ScriptRegistry::enqueue(
			'wpss-unified-dashboard',
			'assets/js/unified-dashboard.js',
			array( 'jquery', ScriptRegistry::HANDLE_UI )
		);

		wp_localize_script(
			'wpss-unified-dashboard',
			'wpssUnifiedDashboard',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'wpss_dashboard_nonce' ),
				'serviceNonce'          => wp_create_nonce( 'wpss_service_nonce' ),
				'restUrl'               => esc_url_raw( rest_url( 'wpss/v1/' ) ),
				'restNonce'             => wp_create_nonce( 'wp_rest' ),
				'currencyDecimals'      => wpss_get_currency_decimals(),
				'zeroDecimalCurrencies' => wpss_get_zero_decimal_currencies(),
				'i18n'                  => array(
					'becomeVendorConfirm'    => __( 'Start selling services on this marketplace?', 'wp-sell-services' ),

					// Wallet transactions table. These are rendered entirely in JS,
					// so without them here the table headers and its empty/error
					// states stay English in every locale - the JS `|| 'Date'`
					// fallbacks were doing the work.
					'walletColDate'          => __( 'Date', 'wp-sell-services' ),
					'walletColType'          => __( 'Type', 'wp-sell-services' ),
					'walletColDescription'   => __( 'Description', 'wp-sell-services' ),
					'walletColAmount'        => __( 'Amount', 'wp-sell-services' ),
					'walletEmpty'            => __( 'No wallet transactions yet.', 'wp-sell-services' ),
					'walletLoadFailed'       => __( 'Could not load transactions. Please try again.', 'wp-sell-services' ),
					'walletTypeUnknown'      => __( 'Other', 'wp-sell-services' ),
					'processing'             => __( 'Processing...', 'wp-sell-services' ),
					'confirmDelete'          => __( 'Are you sure you want to delete this service? This action cannot be undone.', 'wp-sell-services' ),
					'pause'                  => __( 'Pause', 'wp-sell-services' ),
					// The same button toggles between these two labels, but only
					// 'pause' was ever sent -- so it read translated when paused and
					// English when published. Both are sent now.
					'publish'                => __( 'Publish', 'wp-sell-services' ),
					'activate'               => __( 'Activate', 'wp-sell-services' ),
					// Shown after a successful profile save.
					'profileSaved'           => __( 'Profile updated successfully.', 'wp-sell-services' ),
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
					/* translators: %d: number of saved services */
					'favoriteCountSingular'  => __( '%d saved service', 'wp-sell-services' ),
					/* translators: %d: number of saved services. */
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
					'reviewReplySent'        => __( 'Your reply has been posted.', 'wp-sell-services' ),
					'reviewReplyFailed'      => __( 'Could not post your reply. Please try again.', 'wp-sell-services' ),
					'sellerResponse'         => __( 'Seller Response:', 'wp-sell-services' ),
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

		// [wpss_account] renders this dashboard too - it is a thin wrapper that
		// maps the legacy account pages onto our sections - so a page using it
		// needs these assets just as much. Matching only [wpss_dashboard] left
		// the wrapper rendering correct markup with no stylesheet: nav and stats
		// came out as bare bullet lists. Caught in the browser; no PHP-level
		// check would have shown it.
		$shortcodes = array( 'wpss_dashboard', 'wpss_account' );

		/**
		 * Filters the shortcodes that make a page load the dashboard assets.
		 *
		 * @since 1.6.0
		 *
		 * @param string[] $shortcodes Shortcode tags.
		 */
		$shortcodes = (array) apply_filters( 'wpss_dashboard_asset_shortcodes', $shortcodes );

		foreach ( $shortcodes as $tag ) {
			if ( has_shortcode( $post->post_content, (string) $tag ) ) {
				return true;
			}
		}

		return false;
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

		$this->current_section = $this->resolve_current_section();
		$this->sections        = $this->get_sections();

		// A hidden/denied section reached by direct URL falls back to the default
		// landing section — hiding a menu item must also block its address, not
		// just remove the link.
		if ( ! $this->can_access_section( $this->current_section ) ) {
			$this->current_section = $this->default_section();
		}

		ob_start();
		$this->render_shell();
		return ob_get_clean();
	}

	/**
	 * Resolve the current dashboard section from request state.
	 *
	 * Prefers the pretty-permalink endpoint query var (`wpss_section`, populated
	 * by the /{dashboard}/{section}/ rewrite). Falls back to the legacy
	 * `?section=` query arg so plain-permalink sites and old links keep working.
	 * Defaults to the role-aware landing section (see default_section()).
	 *
	 * The requested slug is resolved through wpss_normalize_dashboard_section(),
	 * which maps label-derived guesses (`my-orders` -> `orders`) onto the real
	 * slug and answers with an empty string for anything this product does not
	 * have. An unrecognised slug therefore falls through to the default landing
	 * section instead of being handed on to the renderer, which used to accept
	 * ANY sanitize_key-clean string and then render a dead "Section Not
	 * Available" card. Plugin::redirect_dashboard_section_url() normally
	 * redirects those URLs before we get here; this is the fallback for the
	 * contexts template_redirect never runs in (AJAX, the shortcode embedded on
	 * a page that is not the mapped dashboard).
	 *
	 * @since 1.2.0
	 *
	 * @return string Sanitized section slug.
	 */
	private function resolve_current_section(): string {
		$section = (string) get_query_var( 'wpss_section', '' );

		if ( '' === $section ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Section routing, no data processing.
			$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		}

		$section = wpss_normalize_dashboard_section( $section );

		if ( '' !== $section ) {
			return $section;
		}

		// A URL that names an order belongs to THAT order's side of the trade,
		// not to whatever the viewer's role defaults to. Without this, a member
		// who both buys and sells — which is every vendor who also orders, and
		// the state a buyer lands in the moment they register as a vendor — was
		// sent to "Sales Orders" straight after PAYING for something: the page
		// title, and the highlighted nav item, both claimed a purchase was a
		// sale. Deciding from the order keeps buying and selling honest.
		$section_for_order = $this->section_for_order( $this->resolve_requested_order_id() );

		return '' !== $section_for_order ? $section_for_order : $this->default_section();
	}

	/**
	 * Read the order ID a dashboard URL is pointing at, if any.
	 *
	 * @since 1.2.4
	 *
	 * @return int Order ID, or 0 when the URL names no order.
	 */
	private function resolve_requested_order_id(): int {
		return function_exists( 'wpss_resolve_request_order_id' )
			? wpss_resolve_request_order_id()
			: 0;
	}

	/**
	 * Which dashboard section an order belongs to for the current viewer.
	 *
	 * @since 1.2.4
	 *
	 * @param int $order_id Order ID (0 for none).
	 * @return string `orders` when the viewer bought it, `sales` when they sold
	 *                it, empty string when neither (or no order).
	 */
	private function section_for_order( int $order_id ): string {
		if ( ! $order_id ) {
			return '';
		}

		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		$user_id = get_current_user_id();

		if ( (int) ( $order->customer_id ?? 0 ) === $user_id ) {
			return 'orders';
		}

		if ( (int) ( $order->vendor_id ?? 0 ) === $user_id ) {
			return 'sales';
		}

		return '';
	}

	/**
	 * Resolve the role-aware default landing section.
	 *
	 * Active vendors land on their selling overview (`sales`) — a seller's
	 * operational home — while buyers land on `orders`. Explicit section
	 * requests always win (see resolve_current_section()).
	 *
	 * @since 1.2.0
	 *
	 * @return string Section slug.
	 */
	private function default_section(): string {
		$user_id = get_current_user_id();
		$default = 'orders';

		if ( $this->vendor_service->is_vendor( $user_id )
			&& 'active' === $this->vendor_service->get_vendor_status( $user_id ) ) {
			$default = 'sales';
		}

		/**
		 * Filter the dashboard's default landing section.
		 *
		 * @since 1.2.0
		 *
		 * @param string $default Section slug (`sales` for active vendors, `orders` otherwise).
		 * @param int    $user_id Current user ID.
		 */
		return (string) apply_filters( 'wpss_dashboard_default_section', $default, $user_id );
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
		$vendor_only_sections = self::VENDOR_SECTIONS;
		$user_id              = get_current_user_id();

		// A vendor-only section requires an active (approved) vendor — pending
		// vendors and non-vendors are refused outright.
		if ( in_array( $section, $vendor_only_sections, true )
			&& ! ( $this->vendor_service->is_vendor( $user_id )
				&& 'active' === $this->vendor_service->get_vendor_status( $user_id ) ) ) {
			return false;
		}

		/**
		 * Filter whether user can access a dashboard section.
		 *
		 * Runs for EVERY section — including vendor-only ones — so role-based menu
		 * visibility can hide a selling section too. The filter only ever tightens
		 * access; a section already refused above never reaches here.
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
					'proposals' => array(
						'icon'  => 'send',
						'label' => __( 'Proposals', 'wp-sell-services' ),
					),
					'reviews'   => array(
						'icon'  => 'star',
						'label' => __( 'Reviews', 'wp-sell-services' ),
					),
					'earnings'  => array(
						'icon'  => 'wallet',
						'label' => __( 'Earnings & Payouts', 'wp-sell-services' ),
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
				'messages'      => array(
					'icon'  => 'chat',
					'label' => __( 'Messages', 'wp-sell-services' ),
				),
				'notifications' => array(
					'icon'  => 'bell',
					'label' => __( 'Notifications', 'wp-sell-services' ),
				),
				'disputes'      => array(
					'icon'  => 'shield',
					'label' => __( 'Disputes', 'wp-sell-services' ),
				),
				'profile'       => array(
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

		/*
		 * The heading is an H1, not an H2, and it has to be here.
		 *
		 * The dashboard is a plugin-shell surface, so ShellHeader suppresses the
		 * theme's own <h1> in favour of the plugin's. The signed-in dashboard
		 * renders one; this prompt did not, so once suppression started working a
		 * logged-out visitor got a page with NO H1 at all — worse than the
		 * duplicate that was reported (Basecamp 10208511245).
		 *
		 * Rendered through ShellHeader::render() rather than a hand-written <h1>,
		 * so it is the same component, class names and styling as every other
		 * plugin heading.
		 */
		return \WPSellServices\Frontend\ShellHeader::render(
			array(
				'title' => __( 'Access Your Dashboard', 'wp-sell-services' ),
				'echo'  => false,
			)
		) . sprintf(
			'<div class="wpss-dashboard-login">
				<div class="wpss-dashboard-login__icon">
					<i data-lucide="user" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
				</div>
				<p>%s</p>
				<a href="%s" class="wpss-btn wpss-btn--primary">%s</a>
			</div>',
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
		<div class="wpss-app-shell">
			<div class="wpss-app-shell__container">
				<div class="wpss-dashboard">
					<aside class="wpss-dashboard__sidebar">
				<?php
				// Under 480px the sidebar collapses to this bar (see the CSS), so
				// the section content is the first thing on screen; the toggle
				// reveals the nav below it. Hidden on wider viewports.
				?>
				<div class="wpss-dashboard__nav-bar">
					<span class="wpss-dashboard__nav-bar-title"><?php echo esc_html( $section_data['title'] ); ?></span>
					<button type="button" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-dashboard__nav-toggle" aria-expanded="false" aria-controls="wpss-dashboard-nav">
						<?php $this->render_icon( 'menu' ); ?>
						<span><?php esc_html_e( 'Menu', 'wp-sell-services' ); ?></span>
					</button>
				</div>
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

				<nav id="wpss-dashboard-nav" class="wpss-dashboard__nav">
					<?php
					foreach ( $this->sections as $group_key => $group ) :
						// Drop items the current user's role cannot access (vendor-only
						// gate + role-based menu visibility, via can_access_section) so
						// the nav never shows a link that would be denied. A group whose
						// items are all hidden is skipped entirely — no empty header.
						$visible_items = array_filter(
							$group['items'],
							function ( $item_key ) {
								return $this->can_access_section( (string) $item_key );
							},
							ARRAY_FILTER_USE_KEY
						);

						if ( empty( $visible_items ) ) {
							continue;
						}
						?>
						<div class="wpss-dashboard__nav-group">
							<span class="wpss-dashboard__nav-label"><?php echo esc_html( $group['label'] ); ?></span>
							<ul class="wpss-dashboard__nav-list">
								<?php foreach ( $visible_items as $item_key => $item ) : ?>
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

					<?php
					// Log Out — members sign in through the dashboard, so give them
					// a way out here too (Basecamp #10092963015). Pinned in its own
					// group at the bottom of the nav.
					?>
					<div class="wpss-dashboard__nav-group wpss-dashboard__nav-group--account">
						<ul class="wpss-dashboard__nav-list">
							<li>
								<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"
									class="wpss-dashboard__nav-item wpss-dashboard__nav-item--logout">
									<?php $this->render_icon( 'log-out' ); ?>
									<span><?php esc_html_e( 'Log Out', 'wp-sell-services' ); ?></span>
								</a>
							</li>
						</ul>
					</div>
				</nav>

				<?php if ( $is_pending ) : ?>
					<div class="wpss-dashboard__pending-notice">
						<p><?php esc_html_e( 'Your vendor application is pending admin approval. You will be notified once your application is reviewed.', 'wp-sell-services' ); ?></p>
					</div>
					<?php
				elseif ( ! $is_vendor && ! $is_pending ) :
					$sb_registration_mode = wpss_get_option( 'vendor', 'vendor_registration' );
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
				<?php
				// Contextual prompts live in their own sections (the payout prompt
				// in Earnings & Payouts, profile completeness in the Profile
				// section) - they are NOT rendered globally here, which previously
				// leaked the same banner onto every dashboard tab.
				?>
				<header class="wpss-dashboard__header">
					<?php
					/**
					 * Fires at the start of the unified dashboard header.
					 *
					 * Pro's WhiteLabel DashboardBrandingService listens here (priority 5)
					 * to render a branded logo or brand name before the section title.
					 * Free shipped without firing this action, leaving that listener
					 * dead; added in 1.2.0 to complete the contract.
					 *
					 * @since 1.2.0
					 */
					do_action( 'wpss_dashboard_header' );
					?>
					<h1 class="wpss-dashboard__title wpss-page-header__title">
						<?php
						$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL parameter for display.
						// Service edit reuses the `create` section with ?id=<service_id>, so
						// an editing context is `create` section + a present id. The guard was
						// inverted (`!== 'create'`), so editing a service always fell through to
						// the "Create Service" title.
						if ( $id && 'create' === $this->current_section ) {
							esc_html_e( 'Update Service', 'wp-sell-services' );
						} else {
							echo esc_html( $section_data['title'] );
						}
						?>
					</h1>
					<?php
					// F4 (baseline-2026-04-25.md): show the top "Create Service" button
					// only when the vendor already has at least one service. On the empty
					// state, the in-card "Create Your First Service" CTA stands alone so
					// vendors aren't presented with two competing entry points.
					if ( 'services' === $this->current_section && $this->vendor_has_any_services( $user_id ) ) :
						?>
						<a href="<?php echo esc_url( $this->get_section_url( 'create' ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Create Service', 'wp-sell-services' ); ?>
						</a>
					<?php elseif ( 'requests' === $this->current_section ) : ?>
						<a href="<?php echo esc_url( $this->get_section_url( 'create-request' ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Post Request', 'wp-sell-services' ); ?>
						</a>
					<?php endif; ?>
					<button type="button" class="wpss-dashboard__tour-replay" onclick="if(window.wpssTour&&window.wpssTour.start){window.wpssTour.start();}return false;">
						<i data-lucide="help-circle" class="wpss-icon" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Replay tour', 'wp-sell-services' ); ?></span>
					</button>
				</header>

				<div class="wpss-dashboard__body">
					<?php $this->render_section( $this->current_section ); ?>
				</div>

				<?php
				/**
				 * Filters whether the "Powered by WP Sell Services" footer credit
				 * is rendered on the frontend dashboard.
				 *
				 * DEFAULT FALSE since 1.4.0. This is a self-hosted plugin, not a
				 * hosted service: the dashboard belongs to the site owner and their
				 * members, and we do not put our name and an outbound link on it
				 * uninvited. It previously defaulted to true and could only be
				 * taken off with Pro's white-label toggle, which made an owner pay
				 * to remove our branding from their own site — a SaaS pattern that
				 * does not belong in a WordPress plugin.
				 *
				 * Owners who want to credit the plugin can opt in:
				 *
				 *     add_filter( 'wpss_show_powered_by', '__return_true' );
				 *
				 * Pro's white-label toggle still filters this hook; with the default
				 * off it simply has nothing left to remove.
				 *
				 * @since 1.2.0
				 * @since 1.4.0 Default changed from true to false.
				 *
				 * @param bool $show_powered_by Whether to render the credit. Default false.
				 */
				if ( apply_filters( 'wpss_show_powered_by', false ) ) :
					?>
					<footer class="wpss-dashboard__footer">
						<p class="wpss-powered-by">
							<?php
							printf(
								/* translators: %s: linked plugin name "WP Sell Services". */
								esc_html__( 'Powered by %s', 'wp-sell-services' ),
								'<a href="https://wbcomdesigns.com/downloads/wp-sell-services/" target="_blank" rel="noopener nofollow">' . esc_html__( 'WP Sell Services', 'wp-sell-services' ) . '</a>'
							);
							?>
						</p>
					</footer>
				<?php endif; ?>
			</main>
				</div>
			</div>
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
			'proposals'      => __( 'Proposals', 'wp-sell-services' ),
			'reviews'        => __( 'Reviews', 'wp-sell-services' ),
			'earnings'       => __( 'Earnings & Payouts', 'wp-sell-services' ),
			'wallet'         => __( 'Earnings & Payouts', 'wp-sell-services' ),
			'analytics'      => __( 'Analytics', 'wp-sell-services' ),
			'portfolio'      => __( 'Portfolio', 'wp-sell-services' ),
			'messages'       => __( 'Messages', 'wp-sell-services' ),
			// Disputes and Notifications are in the dashboard nav but were
			// missing from this map, so both fell through to the generic
			// "Dashboard" default: the page header read "Dashboard" while the
			// section repeated its own name below it — two headings, and the
			// top one wrong. Every nav destination needs an entry here.
			'disputes'       => __( 'Disputes', 'wp-sell-services' ),
			'notifications'  => __( 'Notifications', 'wp-sell-services' ),
			'profile'        => __( 'Profile', 'wp-sell-services' ),
			'create'         => __( 'Create Service', 'wp-sell-services' ),
			'create-request' => __( 'Post a Request', 'wp-sell-services' ),
			'edit-request'   => __( 'Edit Request', 'wp-sell-services' ),
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

		// Centralized builder emits the pretty endpoint (/dashboard/{section}/)
		// when permalinks are pretty and falls back to ?section= otherwise.
		return wpss_append_dashboard_section( $base_url, $section );
	}

	/**
	 * Render a section.
	 *
	 * @param string $section Section slug.
	 * @return void
	 */
	private function render_section( string $section ): void {
		// `wallet` and `earnings` are one screen ("Wallet & Earnings"):
		// earnings.php renders both the earnings summary and the wallet ledger
		// (#wpss-wallet-transactions). The wallet slug is kept as a friendly
		// URL/nav entry but resolves to the single earnings template - no
		// duplicate template, one source of truth.
		$template_section = ( 'wallet' === $section ) ? 'earnings' : $section;
		$template_path    = WPSS_PLUGIN_DIR . "templates/dashboard/sections/{$template_section}.php";

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
		$vendor_only_sections = self::VENDOR_SECTIONS;
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
		$fb_registration_mode = wpss_get_option( 'vendor', 'vendor_registration' );
		$registration_is_open = 'closed' !== $fb_registration_mode;

		// Vendor-only sections: show a CTA to become a vendor.
		$vendor_only_sections = self::VENDOR_SECTIONS;

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
			// A KNOWN section whose template this install cannot render. Since
			// the router now redirects unrecognised slugs instead of routing
			// them here (see wpss_normalize_dashboard_section()), the only way
			// to land in this branch is a real address whose template ships
			// somewhere else — in practice, a Pro-only section such as
			// Analytics viewed on a Free-only site. Say that, rather than the
			// old flat "This section is not available", which read as a broken
			// link and sent testers to file bugs against working URLs.
			$pro_active = defined( 'WPSS_PRO_VERSION' );
			?>
			<div class="wpss-dashboard__empty">
				<div class="wpss-dashboard__empty-icon">
					<?php $this->render_icon( 'folder' ); ?>
				</div>
				<h3><?php esc_html_e( 'Section Not Available', 'wp-sell-services' ); ?></h3>
				<p>
					<?php
					if ( $pro_active ) {
						esc_html_e( 'This section is not available on this site.', 'wp-sell-services' );
					} else {
						esc_html_e( 'This section is part of WP Sell Services Pro and is not available on this site.', 'wp-sell-services' );
					}
					?>
				</p>
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
	 * @since 1.0.0
	 *
	 * @param int  $user_id   Current user ID.
	 * @param bool $is_active Whether user is an active vendor.
	 * @return void
	 */
	/**
	 * Whether the vendor has any services in any status (publish/draft/pending).
	 *
	 * Used to decide whether to show the top "+ Create Service" button (vendors
	 * with services) or rely on the in-card empty-state CTA (vendors with none).
	 * Cached per request to avoid repeating the count query in the template.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return bool True if at least one service exists, false otherwise.
	 */
	private function vendor_has_any_services( int $user_id ): bool {
		static $cache = array();
		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}

		$count = (int) ( new \WP_Query(
			array(
				'post_type'      => 'wpss_service',
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		) )->found_posts;

		$cache[ $user_id ] = $count > 0;
		return $cache[ $user_id ];
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
		$ajax_registration_mode = wpss_get_option( 'vendor', 'vendor_registration' );
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
			$registration_mode = wpss_get_option( 'vendor', 'vendor_registration' );

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
