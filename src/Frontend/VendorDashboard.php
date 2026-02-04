<?php
/**
 * Vendor Dashboard
 *
 * Handles frontend vendor dashboard functionality.
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Services\VendorService;
use WPSellServices\Services\EarningsService;
use WPSellServices\Services\AnalyticsService;
use WPSellServices\Services\PortfolioService;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\ServiceManager;

/**
 * Manages the frontend vendor dashboard.
 *
 * @since 1.0.0
 */
class VendorDashboard {

	/**
	 * Vendor service.
	 *
	 * @var VendorService
	 */
	private VendorService $vendor_service;

	/**
	 * Earnings service.
	 *
	 * @var EarningsService
	 */
	private EarningsService $earnings_service;

	/**
	 * Analytics service.
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics_service;

	/**
	 * Portfolio service.
	 *
	 * @var PortfolioService
	 */
	private PortfolioService $portfolio_service;

	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private OrderService $order_service;

	/**
	 * Service manager.
	 *
	 * @var ServiceManager
	 */
	private ServiceManager $service_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_service    = new VendorService();
		$this->earnings_service  = new EarningsService();
		$this->analytics_service = new AnalyticsService();
		$this->portfolio_service = new PortfolioService();
		$this->order_service     = new OrderService();
		$this->service_manager   = new ServiceManager();
	}

	/**
	 * Initialize dashboard hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'wpss_vendor_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'wpss_become_vendor', array( $this, 'render_registration_form' ) );
		add_shortcode( 'wpss_vendor_registration', array( $this, 'render_registration_form' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_update_vendor_profile', array( $this, 'ajax_update_profile' ) );
		add_action( 'wp_ajax_wpss_request_withdrawal', array( $this, 'ajax_request_withdrawal' ) );
		add_action( 'wp_ajax_wpss_add_portfolio_item', array( $this, 'ajax_add_portfolio_item' ) );
		add_action( 'wp_ajax_wpss_delete_portfolio_item', array( $this, 'ajax_delete_portfolio_item' ) );
		add_action( 'wp_ajax_wpss_toggle_featured_portfolio', array( $this, 'ajax_toggle_featured_portfolio' ) );
		add_action( 'wp_ajax_wpss_reorder_portfolio', array( $this, 'ajax_reorder_portfolio' ) );
		add_action( 'wp_ajax_wpss_update_service_status', array( $this, 'ajax_update_service_status' ) );
		add_action( 'wp_ajax_wpss_delete_service', array( $this, 'ajax_delete_service' ) );
		add_action( 'wp_ajax_wpss_vendor_registration', array( $this, 'ajax_vendor_registration' ) );
	}

	/**
	 * Render main vendor dashboard.
	 *
	 * @deprecated 1.1.0 Use [wpss_dashboard] shortcode instead.
	 * @param array $atts Shortcode attributes.
	 * @return string Dashboard HTML.
	 */
	public function render_dashboard( array $atts = array() ): string {
		// Redirect to unified dashboard.
		$unified_dashboard = new UnifiedDashboard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just setting default section.
		$_GET['section'] = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'services';
		return $unified_dashboard->render( array() );
	}

	/**
	 * Legacy render dashboard - kept for reference.
	 *
	 * @deprecated 1.1.0
	 * @param array $atts Shortcode attributes.
	 * @return string Dashboard HTML.
	 */
	private function render_dashboard_legacy( array $atts = array() ): string {
		// Enqueue dashboard styles.
		wp_enqueue_style( 'wpss-vendor-dashboard', WPSS_PLUGIN_URL . 'assets/css/vendor-dashboard.css', array( 'wpss-design-system' ), WPSS_VERSION );

		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			return $this->render_not_vendor();
		}

		$atts = shortcode_atts(
			array(
				'tab' => isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			),
			$atts,
			'wpss_vendor_dashboard'
		);

		ob_start();
		?>
		<div class="wpss-dashboard" data-vendor-id="<?php echo esc_attr( $user_id ); ?>">
			<?php $this->render_dashboard_nav( $atts['tab'], $user_id ); ?>

			<div class="wpss-dashboard__content">
				<?php
				switch ( $atts['tab'] ) {
					case 'orders':
						$this->render_orders_tab( $user_id );
						break;
					case 'services':
						$this->render_services_tab( $user_id );
						break;
					case 'earnings':
						$this->render_earnings_tab( $user_id );
						break;
					case 'portfolio':
						$this->render_portfolio_tab( $user_id );
						break;
					case 'analytics':
						$this->render_analytics_tab( $user_id );
						break;
					case 'profile':
						$this->render_profile_tab( $user_id );
						break;
					default:
						$this->render_overview_tab( $user_id );
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render dashboard navigation.
	 *
	 * @param string $active_tab Current active tab.
	 * @param int    $user_id    Vendor user ID.
	 * @return void
	 */
	private function render_dashboard_nav( string $active_tab, int $user_id ): void {
		$user = get_userdata( $user_id );

		$tabs = array(
			'overview'  => array(
				'label' => __( 'Overview', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
			),
			'orders'    => array(
				'label' => __( 'Orders', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>',
			),
			'services'  => array(
				'label' => __( 'Services', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
			),
			'earnings'  => array(
				'label' => __( 'Earnings', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
			),
			'portfolio' => array(
				'label' => __( 'Portfolio', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
			),
			'analytics' => array(
				'label' => __( 'Analytics', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
			),
			'profile'   => array(
				'label' => __( 'Profile', 'wp-sell-services' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			),
		);

		/**
		 * Filter dashboard tabs.
		 *
		 * @param array $tabs Dashboard tabs.
		 */
		$tabs = apply_filters( 'wpss_vendor_dashboard_tabs', $tabs );
		?>
		<aside class="wpss-dashboard__sidebar">
			<div class="wpss-dashboard__user">
				<img src="<?php echo esc_url( get_avatar_url( $user_id, array( 'size' => 96 ) ) ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" class="wpss-dashboard__user-avatar">
				<div class="wpss-dashboard__user-info">
					<h3><?php echo esc_html( $user->display_name ); ?></h3>
					<span class="wpss-dashboard__user-role"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<nav class="wpss-dashboard__nav">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>" class="wpss-dashboard__nav-item <?php echo $active_tab === $slug ? 'wpss-dashboard__nav-item--active' : ''; ?>">
						<span class="wpss-dashboard__nav-icon"><?php echo $tab['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup. ?></span>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</aside>
		<?php
	}

	/**
	 * Render overview tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_overview_tab( int $vendor_id ): void {
		$earnings = $this->earnings_service->get_summary( $vendor_id );
		$stats    = $this->analytics_service->get_vendor_stats( $vendor_id );
		$profile  = $this->vendor_service->get_vendor_profile( $vendor_id );

		// Get recent orders.
		$recent_orders = $this->order_service->get_by_vendor(
			$vendor_id,
			array(
				'limit' => 5,
			)
		);

		// Get pending actions count.
		$pending_requirements = $this->order_service->count_by_status( $vendor_id, 'requirements_pending' );
		$pending_delivery     = $this->order_service->count_by_status( $vendor_id, 'in_progress' );
		$pending_revision     = $this->order_service->count_by_status( $vendor_id, 'revision_requested' );
		?>
		<div class="wpss-dashboard__body">
			<!-- Earnings Summary -->
			<div class="wpss-stats-grid">
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--success">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['available_balance'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Available Balance', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--warning">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['pending_clearance'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending Clearance', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--primary">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['this_month'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'This Month', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--info">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['total_earned'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Earned', 'wp-sell-services' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Pending Actions -->
			<?php if ( $pending_requirements > 0 || $pending_delivery > 0 || $pending_revision > 0 ) : ?>
			<div class="wpss-section">
				<div class="wpss-section__header">
					<h3 class="wpss-section__title"><?php esc_html_e( 'Pending Actions', 'wp-sell-services' ); ?></h3>
				</div>
				<div class="wpss-section__body">
					<ul class="wpss-activity-list">
						<?php if ( $pending_requirements > 0 ) : ?>
							<li class="wpss-activity-item">
								<div class="wpss-activity-item__icon" style="background: var(--wpss-warning-light); color: var(--wpss-warning);">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
								</div>
								<div class="wpss-activity-item__content">
									<p class="wpss-activity-item__title"><strong><?php echo esc_html( $pending_requirements ); ?></strong> <?php esc_html_e( 'orders awaiting requirements', 'wp-sell-services' ); ?></p>
									<a href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'tab'    => 'orders',
												'status' => 'requirements_pending',
											)
										)
									);
									?>
												" class="wpss-btn wpss-btn--sm wpss-btn--ghost"><?php esc_html_e( 'View Orders', 'wp-sell-services' ); ?></a>
								</div>
							</li>
						<?php endif; ?>
						<?php if ( $pending_delivery > 0 ) : ?>
							<li class="wpss-activity-item">
								<div class="wpss-activity-item__icon">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
								</div>
								<div class="wpss-activity-item__content">
									<p class="wpss-activity-item__title"><strong><?php echo esc_html( $pending_delivery ); ?></strong> <?php esc_html_e( 'orders to deliver', 'wp-sell-services' ); ?></p>
									<a href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'tab'    => 'orders',
												'status' => 'in_progress',
											)
										)
									);
									?>
												" class="wpss-btn wpss-btn--sm wpss-btn--ghost"><?php esc_html_e( 'View Orders', 'wp-sell-services' ); ?></a>
								</div>
							</li>
						<?php endif; ?>
						<?php if ( $pending_revision > 0 ) : ?>
							<li class="wpss-activity-item">
								<div class="wpss-activity-item__icon" style="background: var(--wpss-danger-light); color: var(--wpss-danger);">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
								</div>
								<div class="wpss-activity-item__content">
									<p class="wpss-activity-item__title"><strong><?php echo esc_html( $pending_revision ); ?></strong> <?php esc_html_e( 'revision requests', 'wp-sell-services' ); ?></p>
									<a href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'tab'    => 'orders',
												'status' => 'revision_requested',
											)
										)
									);
									?>
												" class="wpss-btn wpss-btn--sm wpss-btn--danger"><?php esc_html_e( 'View Requests', 'wp-sell-services' ); ?></a>
								</div>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
			<?php endif; ?>

			<!-- Performance Stats -->
			<div class="wpss-section">
				<div class="wpss-section__header">
					<h3 class="wpss-section__title"><?php esc_html_e( 'Performance', 'wp-sell-services' ); ?></h3>
				</div>
				<div class="wpss-section__body">
					<div class="wpss-stats-grid">
						<div class="wpss-stat-card">
							<div class="wpss-stat-card__content">
								<span class="wpss-stat-card__value"><?php echo esc_html( $stats['orders_completed'] ?? 0 ); ?></span>
								<span class="wpss-stat-card__label"><?php esc_html_e( 'Orders Completed', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-stat-card">
							<div class="wpss-stat-card__content">
								<span class="wpss-stat-card__value"><?php echo esc_html( number_format( $stats['average_rating'] ?? 0, 1 ) ); ?> <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="color: var(--wpss-warning);"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
								<span class="wpss-stat-card__label"><?php esc_html_e( 'Average Rating', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-stat-card">
							<div class="wpss-stat-card__content">
								<span class="wpss-stat-card__value"><?php echo esc_html( ( $stats['response_rate'] ?? 0 ) . '%' ); ?></span>
								<span class="wpss-stat-card__label"><?php esc_html_e( 'Response Rate', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-stat-card">
							<div class="wpss-stat-card__content">
								<span class="wpss-stat-card__value"><?php echo esc_html( ( $stats['on_time_rate'] ?? 0 ) . '%' ); ?></span>
								<span class="wpss-stat-card__label"><?php esc_html_e( 'On-Time Delivery', 'wp-sell-services' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Recent Orders -->
			<div class="wpss-table-wrapper">
				<div class="wpss-table-header">
					<h3><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h3>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'orders' ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--ghost"><?php esc_html_e( 'View All', 'wp-sell-services' ); ?></a>
				</div>
				<?php if ( ! empty( $recent_orders ) ) : ?>
					<table class="wpss-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_orders as $order ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $this->get_order_url( $order['id'] ) ); ?>">#<?php echo esc_html( $order['id'] ); ?></a></td>
									<td><?php echo esc_html( $order['service_title'] ?? '' ); ?></td>
									<td>
										<div class="wpss-table__cell-user">
											<?php echo get_avatar( $order['customer_id'], 32 ); ?>
											<span><?php echo esc_html( get_userdata( $order['customer_id'] )->display_name ?? '' ); ?></span>
										</div>
									</td>
									<td><?php echo wp_kses_post( wpss_format_price( (float) $order['total'] ) ); ?></td>
									<td><span class="wpss-badge wpss-badge--status-<?php echo esc_attr( str_replace( '_', '-', $order['status'] ) ); ?>"><?php echo esc_html( $this->get_status_label( $order['status'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="wpss-empty-state">
						<div class="wpss-empty-state__icon">
							<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
						</div>
						<h4 class="wpss-empty-state__title"><?php esc_html_e( 'No orders yet', 'wp-sell-services' ); ?></h4>
						<p class="wpss-empty-state__description"><?php esc_html_e( 'Orders will appear here once buyers purchase your services.', 'wp-sell-services' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render orders tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_orders_tab( int $vendor_id ): void {
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'limit'  => 20,
			'offset' => ( $page - 1 ) * 20,
		);

		if ( $status ) {
			$args['status'] = $status;
		}

		$orders      = $this->order_service->get_by_vendor( $vendor_id, $args );
		$total_count = $this->order_service->count_by_vendor( $vendor_id, $status ? array( 'status' => $status ) : array() );
		$total_pages = ceil( $total_count / 20 );

		// Status filters.
		$statuses = array(
			''                     => __( 'All Orders', 'wp-sell-services' ),
			'requirements_pending' => __( 'Requirements Pending', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'             => __( 'Disputed', 'wp-sell-services' ),
		);
		?>
		<div class="wpss-dashboard__body">
			<div class="wpss-dashboard__header">
				<h1 class="wpss-dashboard__title"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h1>
			</div>

			<!-- Status Filters -->
			<div class="wpss-dashboard__actions" style="margin-bottom: 1.5rem; flex-wrap: wrap;">
				<?php foreach ( $statuses as $key => $label ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'tab'    => 'orders',
								'status' => $key,
							)
						)
					);
					?>
					" class="wpss-btn wpss-btn--sm <?php echo $status === $key ? 'wpss-btn--primary' : 'wpss-btn--ghost'; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<!-- Orders Table -->
			<?php if ( ! empty( $orders ) ) : ?>
				<div class="wpss-table-wrapper">
					<table class="wpss-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $orders as $order ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $this->get_order_url( $order['id'] ) ); ?>">#<?php echo esc_html( $order['id'] ); ?></a></td>
									<td><?php echo esc_html( $order['service_title'] ?? '' ); ?></td>
									<td>
										<div class="wpss-table__cell-user">
											<?php echo get_avatar( $order['customer_id'], 32 ); ?>
											<span><?php echo esc_html( get_userdata( $order['customer_id'] )->display_name ?? '' ); ?></span>
										</div>
									</td>
									<td><?php echo wp_kses_post( wpss_format_price( (float) $order['total'] ) ); ?></td>
									<td><?php echo $order['due_date'] ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $order['due_date'] ) ) ) : '—'; ?></td>
									<td><span class="wpss-badge wpss-badge--status-<?php echo esc_attr( str_replace( '_', '-', $order['status'] ) ); ?>"><?php echo esc_html( $this->get_status_label( $order['status'] ) ); ?></span></td>
									<td>
										<div class="wpss-table__cell-actions">
											<a href="<?php echo esc_url( $this->get_order_url( $order['id'] ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--ghost"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="wpss-pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.5rem;">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $page,
									'total'     => $total_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
					</div>
					<h4 class="wpss-empty-state__title"><?php esc_html_e( 'No orders found', 'wp-sell-services' ); ?></h4>
					<p class="wpss-empty-state__description"><?php esc_html_e( 'Try adjusting your filters or check back later.', 'wp-sell-services' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render services tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_services_tab( int $vendor_id ): void {
		$services = $this->service_manager->get_by_vendor( $vendor_id );
		?>
		<div class="wpss-dashboard__body">
			<div class="wpss-dashboard__header">
				<h1 class="wpss-dashboard__title"><?php esc_html_e( 'My Services', 'wp-sell-services' ); ?></h1>
				<div class="wpss-dashboard__actions">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="wpss-btn wpss-btn--primary">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						<?php esc_html_e( 'Add New Service', 'wp-sell-services' ); ?>
					</a>
				</div>
			</div>

			<?php if ( ! empty( $services ) ) : ?>
				<div class="wpss-card-grid wpss-card-grid--3col">
					<?php foreach ( $services as $service ) : ?>
						<div class="wpss-card" data-service-id="<?php echo esc_attr( $service['id'] ); ?>">
							<div class="wpss-card__media">
								<?php if ( $service['thumbnail'] ) : ?>
									<img src="<?php echo esc_url( $service['thumbnail'] ); ?>" alt="<?php echo esc_attr( $service['title'] ); ?>">
								<?php endif; ?>
								<div class="wpss-card__badge">
									<span class="wpss-badge <?php echo 'publish' === $service['status'] ? 'wpss-badge--success' : 'wpss-badge--neutral'; ?>">
										<?php echo esc_html( ucfirst( $service['status'] ) ); ?>
									</span>
								</div>
							</div>
							<div class="wpss-card__body">
								<h4 class="wpss-card__title"><a href="<?php echo esc_url( get_permalink( $service['id'] ) ); ?>"><?php echo esc_html( $service['title'] ); ?></a></h4>
								<p class="wpss-card__meta">
									<span><?php echo esc_html( $service['views'] ?? 0 ); ?> <?php esc_html_e( 'views', 'wp-sell-services' ); ?></span>
									<span> · </span>
									<span><?php echo esc_html( $service['orders'] ?? 0 ); ?> <?php esc_html_e( 'orders', 'wp-sell-services' ); ?></span>
									<?php if ( $service['rating'] ) : ?>
										<span> · </span>
										<span><?php echo esc_html( number_format( $service['rating'], 1 ) ); ?> <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="color: var(--wpss-warning);"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
									<?php endif; ?>
								</p>
							</div>
							<div class="wpss-card__footer">
								<span class="wpss-card__price"><?php esc_html_e( 'From', 'wp-sell-services' ); ?> <?php echo wp_kses_post( wpss_format_price( (float) $service['starting_price'] ?? 0 ) ); ?></span>
								<div class="wpss-card__actions">
									<a href="<?php echo esc_url( get_edit_post_link( $service['id'] ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--ghost" title="<?php esc_attr_e( 'Edit', 'wp-sell-services' ); ?>">
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
									</a>
									<button type="button" class="wpss-btn wpss-btn--sm wpss-btn--ghost wpss-toggle-status" data-service-id="<?php echo esc_attr( $service['id'] ); ?>" data-current-status="<?php echo esc_attr( $service['status'] ); ?>" title="<?php echo 'publish' === $service['status'] ? esc_attr__( 'Pause', 'wp-sell-services' ) : esc_attr__( 'Activate', 'wp-sell-services' ); ?>">
										<?php if ( 'publish' === $service['status'] ) : ?>
											<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
										<?php else : ?>
											<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
										<?php endif; ?>
									</button>
									<button type="button" class="wpss-btn wpss-btn--sm wpss-btn--ghost wpss-btn--danger wpss-delete-service" data-service-id="<?php echo esc_attr( $service['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'wp-sell-services' ); ?>">
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
									</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
					</div>
					<h4 class="wpss-empty-state__title"><?php esc_html_e( 'No services yet', 'wp-sell-services' ); ?></h4>
					<p class="wpss-empty-state__description"><?php esc_html_e( 'Create your first service to start selling.', 'wp-sell-services' ); ?></p>
					<div class="wpss-empty-state__actions">
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="wpss-btn wpss-btn--primary">
							<?php esc_html_e( 'Create Your First Service', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render earnings tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_earnings_tab( int $vendor_id ): void {
		$earnings = $this->earnings_service->get_summary( $vendor_id );
		$history  = $this->earnings_service->get_history( $vendor_id, array( 'limit' => 20 ) );
		$methods  = $this->earnings_service->get_withdrawal_methods();
		?>
		<div class="wpss-dashboard__body">
			<div class="wpss-dashboard__header">
				<h1 class="wpss-dashboard__title"><?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?></h1>
			</div>

			<!-- Earnings Summary -->
			<div class="wpss-stats-grid">
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--success">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['available_balance'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Available for Withdrawal', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--warning">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['pending_clearance'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending Clearance', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--info">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['pending_withdrawal'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending Withdrawal', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--primary">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo wp_kses_post( wpss_format_price( (float) $earnings['withdrawn'] ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Withdrawn', 'wp-sell-services' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Withdrawal Request Form -->
			<?php if ( $earnings['available_balance'] >= EarningsService::get_min_withdrawal_amount() ) : ?>
				<div class="wpss-section">
					<div class="wpss-section__header">
						<h3 class="wpss-section__title"><?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-section__body">
						<form id="wpss-withdrawal-form" class="wpss-form">
							<?php wp_nonce_field( 'wpss_request_withdrawal', 'wpss_withdrawal_nonce' ); ?>
							<div class="wpss-form-group">
								<label class="wpss-form-group__label" for="withdrawal_amount"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></label>
								<input type="number" name="amount" id="withdrawal_amount" class="wpss-form-group__input"
										min="<?php echo esc_attr( EarningsService::get_min_withdrawal_amount() ); ?>"
										max="<?php echo esc_attr( $earnings['available_balance'] ); ?>"
										step="0.01" required>
								<span class="wpss-form-group__hint">
									<?php
									printf(
										/* translators: %s: minimum withdrawal amount */
										esc_html__( 'Minimum: %s', 'wp-sell-services' ),
										wp_kses_post( wpss_format_price( EarningsService::get_min_withdrawal_amount() ) )
									);
									?>
								</span>
							</div>
							<div class="wpss-form-group">
								<label class="wpss-form-group__label" for="withdrawal_method"><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></label>
								<select name="method" id="withdrawal_method" class="wpss-form-group__select" required>
									<option value=""><?php esc_html_e( 'Select method', 'wp-sell-services' ); ?></option>
									<?php foreach ( $methods as $key => $method ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $method['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="wpss-form-group wpss-method-details" style="display: none;">
								<label class="wpss-form-group__label" for="payment_details"><?php esc_html_e( 'Payment Details', 'wp-sell-services' ); ?></label>
								<textarea name="details" id="payment_details" class="wpss-form-group__textarea" rows="3" placeholder="<?php esc_attr_e( 'Enter your payment details (e.g., PayPal email, bank account info)', 'wp-sell-services' ); ?>"></textarea>
							</div>
							<div class="wpss-form-group">
								<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?></button>
							</div>
						</form>
					</div>
				</div>
			<?php else : ?>
				<div class="wpss-notice wpss-notice--info">
					<p>
						<?php
						printf(
							/* translators: %s: minimum withdrawal amount */
							esc_html__( 'You need at least %s in available balance to request a withdrawal.', 'wp-sell-services' ),
							wp_kses_post( wpss_format_price( EarningsService::get_min_withdrawal_amount() ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Transaction History -->
			<div class="wpss-section">
				<div class="wpss-section__header">
					<h3 class="wpss-section__title"><?php esc_html_e( 'Transaction History', 'wp-sell-services' ); ?></h3>
				</div>
				<div class="wpss-section__body">
					<?php if ( ! empty( $history ) ) : ?>
						<div class="wpss-table-wrapper">
							<table class="wpss-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
										<th><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
										<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $history as $transaction ) : ?>
										<tr>
											<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $transaction['created_at'] ) ) ); ?></td>
											<td><?php echo esc_html( $transaction['description'] ); ?></td>
											<td class="<?php echo $transaction['type'] === 'credit' ? 'wpss-table__amount--positive' : 'wpss-table__amount--negative'; ?>">
												<?php echo $transaction['type'] === 'credit' ? '+' : '-'; ?>
												<?php echo wp_kses_post( wpss_format_price( (float) abs( $transaction['amount'] ) ) ); ?>
											</td>
											<td><span class="wpss-badge wpss-badge--status-<?php echo esc_attr( $transaction['status'] ); ?>"><?php echo esc_html( ucfirst( $transaction['status'] ) ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<div class="wpss-empty-state">
							<div class="wpss-empty-state__icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
							</div>
							<p class="wpss-empty-state__text"><?php esc_html_e( 'No transactions yet.', 'wp-sell-services' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render portfolio tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_portfolio_tab( int $vendor_id ): void {
		$portfolio = $this->portfolio_service->get_by_vendor( $vendor_id, array( 'limit' => 100 ) );
		$max_items = (int) get_option( 'wpss_max_portfolio_items', 50 );
		?>
		<div class="wpss-dashboard__body">
			<div class="wpss-dashboard__header">
				<h1 class="wpss-dashboard__title"><?php esc_html_e( 'Portfolio', 'wp-sell-services' ); ?></h1>
				<div class="wpss-dashboard__actions">
					<?php if ( count( $portfolio ) < $max_items ) : ?>
						<button type="button" class="wpss-btn wpss-btn--primary" id="wpss-add-portfolio-item">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
							<?php esc_html_e( 'Add Item', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<p class="wpss-dashboard__meta" style="margin-bottom: 1.5rem; color: var(--wpss-text-muted);">
				<?php
				printf(
					/* translators: %1$d: current items, %2$d: max items */
					esc_html__( '%1$d of %2$d items', 'wp-sell-services' ),
					count( $portfolio ),
					$max_items
				);
				?>
			</p>

			<!-- Portfolio Grid -->
			<?php if ( ! empty( $portfolio ) ) : ?>
				<div class="wpss-card-grid wpss-card-grid--3col" id="wpss-portfolio-sortable">
					<?php foreach ( $portfolio as $item ) : ?>
						<div class="wpss-card wpss-card--portfolio" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
							<div class="wpss-card__media">
								<?php if ( ! empty( $item['media'] ) ) : ?>
									<img src="<?php echo esc_url( $item['media'][0]['medium'] ?? $item['media'][0]['url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
								<?php else : ?>
									<div class="wpss-card__placeholder">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
									</div>
								<?php endif; ?>
								<?php if ( $item['is_featured'] ) : ?>
									<span class="wpss-card__badge wpss-card__badge--featured"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="wpss-card__body">
								<h4 class="wpss-card__title"><?php echo esc_html( $item['title'] ); ?></h4>
								<?php if ( ! empty( $item['tags'] ) ) : ?>
									<div class="wpss-card__tags">
										<?php foreach ( array_slice( $item['tags'], 0, 3 ) as $tag ) : ?>
											<span class="wpss-tag"><?php echo esc_html( $tag ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="wpss-card__footer">
								<div class="wpss-card__actions">
									<button type="button" class="wpss-btn wpss-btn--icon wpss-btn--ghost wpss-toggle-featured" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Toggle Featured', 'wp-sell-services' ); ?>">
										<svg viewBox="0 0 24 24" fill="<?php echo $item['is_featured'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" width="18" height="18"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
									</button>
									<button type="button" class="wpss-btn wpss-btn--icon wpss-btn--ghost wpss-edit-portfolio" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Edit', 'wp-sell-services' ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
									</button>
									<button type="button" class="wpss-btn wpss-btn--icon wpss-btn--ghost wpss-delete-portfolio" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'wp-sell-services' ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
									</button>
									<span class="wpss-btn wpss-btn--icon wpss-btn--ghost wpss-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
									</span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
					</div>
					<h3 class="wpss-empty-state__title"><?php esc_html_e( 'No Portfolio Items', 'wp-sell-services' ); ?></h3>
					<p class="wpss-empty-state__text"><?php esc_html_e( 'Your portfolio is empty. Add items to showcase your work!', 'wp-sell-services' ); ?></p>
					<button type="button" class="wpss-btn wpss-btn--primary" id="wpss-add-first-portfolio">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						<?php esc_html_e( 'Add Your First Item', 'wp-sell-services' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Portfolio Add/Edit Modal -->
			<div id="wpss-portfolio-modal" class="wpss-modal" style="display: none;">
				<div class="wpss-modal__content">
					<div class="wpss-modal__header">
						<h4 class="wpss-modal__title" id="wpss-portfolio-modal-title"><?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?></h4>
						<button type="button" class="wpss-modal__close">&times;</button>
					</div>
					<form id="wpss-portfolio-form" class="wpss-form">
						<?php wp_nonce_field( 'wpss_portfolio_nonce', 'portfolio_nonce' ); ?>
						<input type="hidden" name="item_id" id="portfolio_item_id" value="">

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="portfolio_title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="title" id="portfolio_title" class="wpss-form-group__input" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="portfolio_description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
							<textarea name="description" id="portfolio_description" class="wpss-form-group__textarea" rows="4"></textarea>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label"><?php esc_html_e( 'Images/Videos', 'wp-sell-services' ); ?></label>
							<div class="wpss-media-uploader" id="portfolio_media_uploader">
								<input type="hidden" name="media" id="portfolio_media" value="">
								<div class="wpss-media-uploader__preview" id="portfolio_media_preview"></div>
								<button type="button" class="wpss-btn wpss-btn--outline wpss-upload-media"><?php esc_html_e( 'Add Media', 'wp-sell-services' ); ?></button>
							</div>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="portfolio_external_url"><?php esc_html_e( 'External URL', 'wp-sell-services' ); ?></label>
							<input type="url" name="external_url" id="portfolio_external_url" class="wpss-form-group__input" placeholder="https://example.com/project">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="portfolio_tags"><?php esc_html_e( 'Tags', 'wp-sell-services' ); ?></label>
							<input type="text" name="tags" id="portfolio_tags" class="wpss-form-group__input" placeholder="<?php esc_attr_e( 'Enter tags separated by commas', 'wp-sell-services' ); ?>">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="portfolio_service"><?php esc_html_e( 'Related Service', 'wp-sell-services' ); ?></label>
							<select name="service_id" id="portfolio_service" class="wpss-form-group__select">
								<option value=""><?php esc_html_e( 'None', 'wp-sell-services' ); ?></option>
								<?php
								$vendor_services = $this->service_manager->get_by_vendor( $vendor_id );
								foreach ( $vendor_services as $service ) :
									?>
									<option value="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__checkbox">
								<input type="checkbox" name="is_featured" id="portfolio_featured" value="1">
								<span><?php esc_html_e( 'Feature this item on my profile', 'wp-sell-services' ); ?></span>
							</label>
						</div>

						<div class="wpss-modal__footer">
							<button type="button" class="wpss-btn wpss-btn--ghost wpss-modal-cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
							<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Save', 'wp-sell-services' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render analytics tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_analytics_tab( int $vendor_id ): void {
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stats  = $this->analytics_service->get_vendor_stats( $vendor_id, (int) $period );
		?>
		<div class="wpss-dashboard__body">
			<div class="wpss-dashboard__header">
				<h1 class="wpss-dashboard__title"><?php esc_html_e( 'Analytics', 'wp-sell-services' ); ?></h1>
				<div class="wpss-dashboard__actions">
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'tab'    => 'analytics',
								'period' => '7',
							)
						)
					);
					?>
					" class="wpss-btn wpss-btn--sm <?php echo '7' === $period ? 'wpss-btn--primary' : 'wpss-btn--ghost'; ?>"><?php esc_html_e( '7 Days', 'wp-sell-services' ); ?></a>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'tab'    => 'analytics',
								'period' => '30',
							)
						)
					);
					?>
					" class="wpss-btn wpss-btn--sm <?php echo '30' === $period ? 'wpss-btn--primary' : 'wpss-btn--ghost'; ?>"><?php esc_html_e( '30 Days', 'wp-sell-services' ); ?></a>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'tab'    => 'analytics',
								'period' => '90',
							)
						)
					);
					?>
					" class="wpss-btn wpss-btn--sm <?php echo '90' === $period ? 'wpss-btn--primary' : 'wpss-btn--ghost'; ?>"><?php esc_html_e( '90 Days', 'wp-sell-services' ); ?></a>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'tab'    => 'analytics',
								'period' => '365',
							)
						)
					);
					?>
					" class="wpss-btn wpss-btn--sm <?php echo '365' === $period ? 'wpss-btn--primary' : 'wpss-btn--ghost'; ?>"><?php esc_html_e( 'Year', 'wp-sell-services' ); ?></a>
				</div>
			</div>

			<!-- Overview Stats -->
			<div class="wpss-stats-grid" style="margin-bottom: 2rem;">
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--info">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo esc_html( number_format( $stats['profile_views'] ?? 0 ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Profile Views', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--primary">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo esc_html( number_format( $stats['impressions'] ?? 0 ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Service Impressions', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--warning">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo esc_html( number_format( $stats['clicks'] ?? 0 ) ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Service Clicks', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-card">
					<div class="wpss-stat-card__icon wpss-stat-card__icon--success">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
					</div>
					<div class="wpss-stat-card__content">
						<span class="wpss-stat-card__value"><?php echo esc_html( $stats['orders_received'] ?? 0 ); ?></span>
						<span class="wpss-stat-card__label"><?php esc_html_e( 'Orders Received', 'wp-sell-services' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Conversion Funnel -->
			<div class="wpss-section">
				<div class="wpss-section__header">
					<h3 class="wpss-section__title"><?php esc_html_e( 'Conversion Funnel', 'wp-sell-services' ); ?></h3>
				</div>
				<div class="wpss-section__body">
					<div class="wpss-funnel">
						<div class="wpss-funnel__step">
							<span class="wpss-funnel__value"><?php echo esc_html( number_format( $stats['impressions'] ?? 0 ) ); ?></span>
							<span class="wpss-funnel__label"><?php esc_html_e( 'Impressions', 'wp-sell-services' ); ?></span>
						</div>
						<div class="wpss-funnel__arrow">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><polyline points="9 18 15 12 9 6"/></svg>
						</div>
						<div class="wpss-funnel__step">
							<span class="wpss-funnel__value"><?php echo esc_html( number_format( $stats['clicks'] ?? 0 ) ); ?></span>
							<span class="wpss-funnel__label"><?php esc_html_e( 'Clicks', 'wp-sell-services' ); ?></span>
							<span class="wpss-funnel__rate"><?php echo esc_html( $stats['click_rate'] ?? 0 ); ?>%</span>
						</div>
						<div class="wpss-funnel__arrow">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><polyline points="9 18 15 12 9 6"/></svg>
						</div>
						<div class="wpss-funnel__step">
							<span class="wpss-funnel__value"><?php echo esc_html( $stats['orders_received'] ?? 0 ); ?></span>
							<span class="wpss-funnel__label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
							<span class="wpss-funnel__rate"><?php echo esc_html( $stats['conversion_rate'] ?? 0 ); ?>%</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Top Services -->
			<?php if ( ! empty( $stats['top_services'] ) ) : ?>
				<div class="wpss-section">
					<div class="wpss-section__header">
						<h3 class="wpss-section__title"><?php esc_html_e( 'Top Performing Services', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-section__body">
						<div class="wpss-table-wrapper">
							<table class="wpss-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
										<th><?php esc_html_e( 'Views', 'wp-sell-services' ); ?></th>
										<th><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></th>
										<th><?php esc_html_e( 'Revenue', 'wp-sell-services' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $stats['top_services'] as $service ) : ?>
										<tr>
											<td><a href="<?php echo esc_url( get_permalink( $service['id'] ) ); ?>" class="wpss-table__link"><?php echo esc_html( $service['title'] ); ?></a></td>
											<td><?php echo esc_html( number_format( $service['views'] ) ); ?></td>
											<td><?php echo esc_html( $service['orders'] ); ?></td>
											<td><strong><?php echo wp_kses_post( wpss_format_price( (float) $service['revenue'] ) ); ?></strong></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render profile tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_profile_tab( int $vendor_id ): void {
		$profile = $this->vendor_service->get_vendor_profile( $vendor_id );
		$user    = get_userdata( $vendor_id );
		?>
		<div class="wpss-dashboard__body">
			<div class="wpss-dashboard__header">
				<h1 class="wpss-dashboard__title"><?php esc_html_e( 'Vendor Profile', 'wp-sell-services' ); ?></h1>
			</div>

			<form id="wpss-vendor-profile-form" class="wpss-form">
				<?php wp_nonce_field( 'wpss_update_profile', 'wpss_profile_nonce' ); ?>

				<!-- Basic Info -->
				<div class="wpss-section">
					<div class="wpss-section__header">
						<h3 class="wpss-section__title"><?php esc_html_e( 'Basic Information', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-section__body">
						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="display_name"><?php esc_html_e( 'Display Name', 'wp-sell-services' ); ?></label>
							<input type="text" name="display_name" id="display_name" class="wpss-form-group__input" value="<?php echo esc_attr( $profile['display_name'] ?? $user->display_name ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="tagline"><?php esc_html_e( 'Professional Tagline', 'wp-sell-services' ); ?></label>
							<input type="text" name="tagline" id="tagline" class="wpss-form-group__input" value="<?php echo esc_attr( $profile['tagline'] ?? '' ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g., Professional Web Developer with 10+ years experience', 'wp-sell-services' ); ?>">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="bio"><?php esc_html_e( 'Bio', 'wp-sell-services' ); ?></label>
							<textarea name="bio" id="bio" class="wpss-form-group__textarea" rows="5" placeholder="<?php esc_attr_e( 'Tell buyers about yourself, your experience, and what makes you unique...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $profile['bio'] ?? '' ); ?></textarea>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label"><?php esc_html_e( 'Profile Photo', 'wp-sell-services' ); ?></label>
							<div class="wpss-avatar-uploader">
								<input type="hidden" name="avatar_id" id="vendor_avatar_id" value="<?php echo esc_attr( $profile['avatar_id'] ?? '' ); ?>">
								<div class="wpss-avatar-uploader__preview">
									<?php echo get_avatar( $vendor_id, 150 ); ?>
								</div>
								<button type="button" class="wpss-btn wpss-btn--outline wpss-upload-avatar"><?php esc_html_e( 'Change Photo', 'wp-sell-services' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<!-- Skills & Expertise -->
				<div class="wpss-section">
					<div class="wpss-section__header">
						<h3 class="wpss-section__title"><?php esc_html_e( 'Skills & Expertise', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-section__body">
						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="skills"><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?></label>
							<input type="text" name="skills" id="skills" class="wpss-form-group__input" value="<?php echo esc_attr( implode( ', ', $profile['skills'] ?? array() ) ); ?>" placeholder="<?php esc_attr_e( 'Enter skills separated by commas', 'wp-sell-services' ); ?>">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="languages"><?php esc_html_e( 'Languages', 'wp-sell-services' ); ?></label>
							<input type="text" name="languages" id="languages" class="wpss-form-group__input" value="<?php echo esc_attr( implode( ', ', $profile['languages'] ?? array() ) ); ?>" placeholder="<?php esc_attr_e( 'e.g., English (Native), Spanish (Fluent)', 'wp-sell-services' ); ?>">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="experience_level"><?php esc_html_e( 'Experience Level', 'wp-sell-services' ); ?></label>
							<select name="experience_level" id="experience_level" class="wpss-form-group__select">
								<option value="beginner" <?php selected( $profile['experience_level'] ?? '', 'beginner' ); ?>><?php esc_html_e( 'Beginner (0-2 years)', 'wp-sell-services' ); ?></option>
								<option value="intermediate" <?php selected( $profile['experience_level'] ?? '', 'intermediate' ); ?>><?php esc_html_e( 'Intermediate (2-5 years)', 'wp-sell-services' ); ?></option>
								<option value="expert" <?php selected( $profile['experience_level'] ?? '', 'expert' ); ?>><?php esc_html_e( 'Expert (5+ years)', 'wp-sell-services' ); ?></option>
							</select>
						</div>
					</div>
				</div>

				<!-- Contact & Social -->
				<div class="wpss-section">
					<div class="wpss-section__header">
						<h3 class="wpss-section__title"><?php esc_html_e( 'Contact & Social', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-section__body">
						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="website"><?php esc_html_e( 'Website', 'wp-sell-services' ); ?></label>
							<input type="url" name="website" id="website" class="wpss-form-group__input" value="<?php echo esc_url( $profile['website'] ?? '' ); ?>" placeholder="https://yourwebsite.com">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="location"><?php esc_html_e( 'Location', 'wp-sell-services' ); ?></label>
							<input type="text" name="location" id="location" class="wpss-form-group__input" value="<?php echo esc_attr( $profile['location'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'City, Country', 'wp-sell-services' ); ?>">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="timezone"><?php esc_html_e( 'Timezone', 'wp-sell-services' ); ?></label>
							<select name="timezone" id="timezone" class="wpss-form-group__select">
								<?php
								$timezones        = timezone_identifiers_list();
								$current_timezone = $profile['timezone'] ?? wp_timezone_string();
								foreach ( $timezones as $tz ) :
									?>
									<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $current_timezone, $tz ); ?>><?php echo esc_html( $tz ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<!-- Availability -->
				<div class="wpss-section">
					<div class="wpss-section__header">
						<h3 class="wpss-section__title"><?php esc_html_e( 'Availability', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-section__body">
						<div class="wpss-form-group">
							<label class="wpss-form-group__checkbox">
								<input type="checkbox" name="available_for_work" id="available_for_work" value="1" <?php checked( $profile['available_for_work'] ?? true ); ?>>
								<span><?php esc_html_e( 'I am available to take new orders', 'wp-sell-services' ); ?></span>
							</label>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="response_time"><?php esc_html_e( 'Typical Response Time', 'wp-sell-services' ); ?></label>
							<select name="response_time" id="response_time" class="wpss-form-group__select">
								<option value="1" <?php selected( $profile['response_time'] ?? '', '1' ); ?>><?php esc_html_e( 'Within 1 hour', 'wp-sell-services' ); ?></option>
								<option value="3" <?php selected( $profile['response_time'] ?? '', '3' ); ?>><?php esc_html_e( 'Within 3 hours', 'wp-sell-services' ); ?></option>
								<option value="12" <?php selected( $profile['response_time'] ?? '', '12' ); ?>><?php esc_html_e( 'Within 12 hours', 'wp-sell-services' ); ?></option>
								<option value="24" <?php selected( $profile['response_time'] ?? '', '24' ); ?>><?php esc_html_e( 'Within 24 hours', 'wp-sell-services' ); ?></option>
							</select>
						</div>
					</div>
				</div>

				<div class="wpss-form-group" style="margin-top: 2rem;">
					<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg"><?php esc_html_e( 'Save Profile', 'wp-sell-services' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render vendor registration form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Registration form HTML.
	 */
	public function render_registration_form( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			return '<div class="wpss-notice wpss-notice--info">' . esc_html__( 'You are already a registered vendor.', 'wp-sell-services' ) . ' <a href="' . esc_url( $this->get_dashboard_url() ) . '">' . esc_html__( 'Go to Dashboard', 'wp-sell-services' ) . '</a></div>';
		}

		// Enqueue dashboard styles for registration form.
		wp_enqueue_style( 'wpss-vendor-dashboard', WPSS_PLUGIN_URL . 'assets/css/vendor-dashboard.css', array( 'wpss-design-system' ), WPSS_VERSION );

		ob_start();
		?>
		<div class="wpss-registration">
			<div class="wpss-registration__header">
				<h1 class="wpss-registration__title"><?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?></h1>
				<p class="wpss-registration__intro"><?php esc_html_e( 'Start selling your services today! Complete the form below to become a vendor.', 'wp-sell-services' ); ?></p>
			</div>

			<form id="wpss-vendor-registration-form" class="wpss-form">
				<?php wp_nonce_field( 'wpss_vendor_registration', 'wpss_registration_nonce' ); ?>

				<div class="wpss-section">
					<div class="wpss-section__body">
						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_display_name"><?php esc_html_e( 'Display Name', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="display_name" id="reg_display_name" class="wpss-form-group__input" value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_tagline"><?php esc_html_e( 'Professional Tagline', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="tagline" id="reg_tagline" class="wpss-form-group__input" maxlength="100" placeholder="<?php esc_attr_e( 'e.g., Professional Web Developer', 'wp-sell-services' ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_bio"><?php esc_html_e( 'About You', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<textarea name="bio" id="reg_bio" class="wpss-form-group__textarea" rows="5" placeholder="<?php esc_attr_e( 'Tell us about your experience and expertise...', 'wp-sell-services' ); ?>" required></textarea>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_skills"><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="skills" id="reg_skills" class="wpss-form-group__input" placeholder="<?php esc_attr_e( 'Enter skills separated by commas', 'wp-sell-services' ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__checkbox">
								<input type="checkbox" name="terms_agreed" id="reg_terms" value="1" required>
								<span>
									<?php
									printf(
										/* translators: %s: terms and conditions link */
										esc_html__( 'I agree to the %s', 'wp-sell-services' ),
										'<a href="' . esc_url( get_permalink( get_option( 'wpss_terms_page' ) ) ) . '" target="_blank">' . esc_html__( 'Terms and Conditions', 'wp-sell-services' ) . '</a>'
									);
									?>
								</span>
							</label>
						</div>

						<div class="wpss-form-group" style="margin-top: 1.5rem;">
							<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg"><?php esc_html_e( 'Submit Application', 'wp-sell-services' ); ?></button>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login required message.
	 *
	 * @return string Login message HTML.
	 */
	private function render_login_required(): string {
		return '<div class="wpss-notice wpss-notice--warning">' .
			esc_html__( 'Please log in to access this page.', 'wp-sell-services' ) .
			' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="wpss-notice__link">' . esc_html__( 'Log In', 'wp-sell-services' ) . '</a></div>';
	}

	/**
	 * Render not a vendor message.
	 *
	 * @return string Not vendor message HTML.
	 */
	private function render_not_vendor(): string {
		return '<div class="wpss-notice wpss-notice--info">' .
			esc_html__( 'You are not registered as a vendor.', 'wp-sell-services' ) .
			' <a href="' . esc_url( $this->get_registration_url() ) . '" class="wpss-notice__link">' . esc_html__( 'Become a Vendor', 'wp-sell-services' ) . '</a></div>';
	}

	/**
	 * AJAX: Update vendor profile.
	 *
	 * @return void
	 */
	public function ajax_update_profile(): void {
		check_ajax_referer( 'wpss_update_profile', 'wpss_profile_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$data = array(
			'display_name'       => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'tagline'            => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
			'bio'                => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'skills'             => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['skills'] ?? '' ) ) ) ),
			'languages'          => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['languages'] ?? '' ) ) ) ),
			'experience_level'   => sanitize_text_field( wp_unslash( $_POST['experience_level'] ?? '' ) ),
			'website'            => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ),
			'location'           => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
			'timezone'           => sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) ),
			'available_for_work' => ! empty( $_POST['available_for_work'] ),
			'response_time'      => absint( $_POST['response_time'] ?? 24 ),
			'avatar_id'          => absint( $_POST['avatar_id'] ?? 0 ),
		);

		$result = $this->vendor_service->update_profile( $user_id, $data );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update profile.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * AJAX: Request withdrawal.
	 *
	 * @return void
	 */
	public function ajax_request_withdrawal(): void {
		check_ajax_referer( 'wpss_request_withdrawal', 'wpss_withdrawal_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$amount  = floatval( $_POST['amount'] ?? 0 );
		$method  = sanitize_text_field( wp_unslash( $_POST['method'] ?? '' ) );
		$details = sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) );

		$result = $this->earnings_service->request_withdrawal( $user_id, $amount, $method, array( 'payment_details' => $details ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Add portfolio item.
	 *
	 * @return void
	 */
	public function ajax_add_portfolio_item(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$data = array(
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'media'        => array_map( 'absint', json_decode( stripslashes( $_POST['media'] ?? '[]' ), true ) ?: array() ),
			'external_url' => esc_url_raw( wp_unslash( $_POST['external_url'] ?? '' ) ),
			'tags'         => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ) ) ),
			'service_id'   => absint( $_POST['service_id'] ?? 0 ),
			'is_featured'  => ! empty( $_POST['is_featured'] ),
		);

		$item_id = absint( $_POST['item_id'] ?? 0 );

		if ( $item_id ) {
			$result = $this->portfolio_service->update( $item_id, $data );
		} else {
			$result = $this->portfolio_service->create( $user_id, $data );
		}

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Delete portfolio item.
	 *
	 * @return void
	 */
	public function ajax_delete_portfolio_item(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();
		$item_id = absint( $_POST['item_id'] ?? 0 );

		$result = $this->portfolio_service->delete( $item_id, $user_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Toggle featured portfolio item.
	 *
	 * @return void
	 */
	public function ajax_toggle_featured_portfolio(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();
		$item_id = absint( $_POST['item_id'] ?? 0 );

		$result = $this->portfolio_service->toggle_featured( $item_id, $user_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Reorder portfolio items.
	 *
	 * @return void
	 */
	public function ajax_reorder_portfolio(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$order = array_map( 'absint', $_POST['order'] ?? array() );

		if ( $this->portfolio_service->reorder( $user_id, $order ) ) {
			wp_send_json_success( array( 'message' => __( 'Portfolio reordered successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reorder portfolio.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * AJAX: Update service status.
	 *
	 * @return void
	 */
	public function ajax_update_service_status(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$status     = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$new_status = 'publish' === $status ? 'draft' : 'publish';

		$result = wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => $new_status,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return; // Explicit return for defensive coding.
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Service status updated.', 'wp-sell-services' ),
				'new_status' => $new_status,
			)
		);
	}

	/**
	 * AJAX: Delete a service.
	 *
	 * @return void
	 */
	public function ajax_delete_service(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$service_id = absint( $_POST['service_id'] ?? 0 );

		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Service not found or you do not have permission to delete it.', 'wp-sell-services' ) ) );
			return;
		}

		// Check if service has any active orders.
		$order_service = new OrderService();
		$active_orders = $order_service->get_by_service( $service_id, array( 'status' => array( 'pending', 'in_progress', 'revision' ) ) );

		if ( ! empty( $active_orders ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete service with active orders.', 'wp-sell-services' ) ) );
			return;
		}

		$result = wp_trash_post( $service_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete service.', 'wp-sell-services' ) ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Service deleted successfully.', 'wp-sell-services' ) ) );
	}

	/**
	 * AJAX: Vendor registration.
	 *
	 * @return void
	 */
	public function ajax_vendor_registration(): void {
		check_ajax_referer( 'wpss_vendor_registration', 'wpss_registration_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to register as a vendor.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are already registered as a vendor.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$data = array(
			'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'tagline'      => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
			'bio'          => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'skills'       => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['skills'] ?? '' ) ) ) ),
			'terms_agreed' => ! empty( $_POST['terms_agreed'] ),
		);

		if ( ! $data['terms_agreed'] ) {
			wp_send_json_error( array( 'message' => __( 'You must agree to the terms and conditions.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$result = $this->vendor_service->register_vendor( $user_id, $data );

		if ( $result['success'] ) {
			wp_send_json_success(
				array_merge(
					$result,
					array(
						'redirect' => $this->get_dashboard_url(),
					)
				)
			);
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Get order URL.
	 *
	 * @param int $order_id Order ID.
	 * @return string Order URL.
	 */
	private function get_order_url( int $order_id ): string {
		$page_id = get_option( 'wpss_order_details_page' );
		if ( $page_id ) {
			return add_query_arg( 'order_id', $order_id, get_permalink( $page_id ) );
		}
		return add_query_arg(
			array(
				'tab'      => 'orders',
				'order_id' => $order_id,
			)
		);
	}

	/**
	 * Get dashboard URL.
	 *
	 * @return string Dashboard URL.
	 */
	private function get_dashboard_url(): string {
		$url = wpss_get_page_url( 'dashboard' );

		if ( $url ) {
			return $url;
		}

		// Fallback to WooCommerce My Account page.
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$wc_url = wc_get_page_permalink( 'myaccount' );
			if ( $wc_url ) {
				return $wc_url;
			}
		}

		return home_url();
	}

	/**
	 * Get registration URL.
	 *
	 * @return string Registration URL.
	 */
	private function get_registration_url(): string {
		$url = wpss_get_page_url( 'become_vendor' );
		return $url ? $url : home_url();
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status slug.
	 * @return string Status label.
	 */
	private function get_status_label( string $status ): string {
		$labels = array(
			'pending'              => __( 'Pending', 'wp-sell-services' ),
			'requirements_pending' => __( 'Requirements Pending', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'             => __( 'Disputed', 'wp-sell-services' ),
			'late'                 => __( 'Late', 'wp-sell-services' ),
		);

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}
}
