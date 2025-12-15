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
		add_shortcode( 'wpss_vendor_dashboard', [ $this, 'render_dashboard' ] );
		add_shortcode( 'wpss_become_vendor', [ $this, 'render_registration_form' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_update_vendor_profile', [ $this, 'ajax_update_profile' ] );
		add_action( 'wp_ajax_wpss_request_withdrawal', [ $this, 'ajax_request_withdrawal' ] );
		add_action( 'wp_ajax_wpss_add_portfolio_item', [ $this, 'ajax_add_portfolio_item' ] );
		add_action( 'wp_ajax_wpss_delete_portfolio_item', [ $this, 'ajax_delete_portfolio_item' ] );
		add_action( 'wp_ajax_wpss_toggle_featured_portfolio', [ $this, 'ajax_toggle_featured_portfolio' ] );
		add_action( 'wp_ajax_wpss_reorder_portfolio', [ $this, 'ajax_reorder_portfolio' ] );
		add_action( 'wp_ajax_wpss_update_service_status', [ $this, 'ajax_update_service_status' ] );
		add_action( 'wp_ajax_wpss_vendor_registration', [ $this, 'ajax_vendor_registration' ] );
	}

	/**
	 * Render main vendor dashboard.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Dashboard HTML.
	 */
	public function render_dashboard( array $atts = [] ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			return $this->render_not_vendor();
		}

		$atts = shortcode_atts(
			[
				'tab' => isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			],
			$atts,
			'wpss_vendor_dashboard'
		);

		ob_start();
		?>
		<div class="wpss-vendor-dashboard" data-vendor-id="<?php echo esc_attr( $user_id ); ?>">
			<?php $this->render_dashboard_nav( $atts['tab'] ); ?>

			<div class="wpss-dashboard-content">
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
	 * @return void
	 */
	private function render_dashboard_nav( string $active_tab ): void {
		$tabs = [
			'overview'  => [
				'label' => __( 'Overview', 'wp-sell-services' ),
				'icon'  => 'dashicons-dashboard',
			],
			'orders'    => [
				'label' => __( 'Orders', 'wp-sell-services' ),
				'icon'  => 'dashicons-clipboard',
			],
			'services'  => [
				'label' => __( 'Services', 'wp-sell-services' ),
				'icon'  => 'dashicons-portfolio',
			],
			'earnings'  => [
				'label' => __( 'Earnings', 'wp-sell-services' ),
				'icon'  => 'dashicons-chart-area',
			],
			'portfolio' => [
				'label' => __( 'Portfolio', 'wp-sell-services' ),
				'icon'  => 'dashicons-images-alt2',
			],
			'analytics' => [
				'label' => __( 'Analytics', 'wp-sell-services' ),
				'icon'  => 'dashicons-chart-line',
			],
			'profile'   => [
				'label' => __( 'Profile', 'wp-sell-services' ),
				'icon'  => 'dashicons-admin-users',
			],
		];

		/**
		 * Filter dashboard tabs.
		 *
		 * @param array $tabs Dashboard tabs.
		 */
		$tabs = apply_filters( 'wpss_vendor_dashboard_tabs', $tabs );
		?>
		<nav class="wpss-dashboard-nav">
			<ul>
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<li class="<?php echo $active_tab === $slug ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>">
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
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
		$recent_orders = $this->order_service->get_by_vendor( $vendor_id, [
			'limit' => 5,
		] );

		// Get pending actions count.
		$pending_requirements = $this->order_service->count_by_status( $vendor_id, 'requirements_pending' );
		$pending_delivery     = $this->order_service->count_by_status( $vendor_id, 'in_progress' );
		$pending_revision     = $this->order_service->count_by_status( $vendor_id, 'revision_requested' );
		?>
		<div class="wpss-dashboard-overview">
			<!-- Earnings Summary -->
			<div class="wpss-overview-section wpss-earnings-summary">
				<h3><?php esc_html_e( 'Earnings Summary', 'wp-sell-services' ); ?></h3>
				<div class="wpss-stats-grid">
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Available Balance', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['available_balance'] ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Pending Clearance', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['pending_clearance'] ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'This Month', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['this_month'] ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Total Earned', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['total_earned'] ) ); ?></span>
					</div>
				</div>
			</div>

			<!-- Pending Actions -->
			<div class="wpss-overview-section wpss-pending-actions">
				<h3><?php esc_html_e( 'Pending Actions', 'wp-sell-services' ); ?></h3>
				<div class="wpss-action-list">
					<?php if ( $pending_requirements > 0 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'orders', 'status' => 'requirements_pending' ] ) ); ?>" class="wpss-action-item wpss-action-warning">
							<span class="wpss-action-count"><?php echo esc_html( $pending_requirements ); ?></span>
							<span class="wpss-action-label"><?php esc_html_e( 'Awaiting Requirements', 'wp-sell-services' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $pending_delivery > 0 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'orders', 'status' => 'in_progress' ] ) ); ?>" class="wpss-action-item">
							<span class="wpss-action-count"><?php echo esc_html( $pending_delivery ); ?></span>
							<span class="wpss-action-label"><?php esc_html_e( 'To Deliver', 'wp-sell-services' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $pending_revision > 0 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'orders', 'status' => 'revision_requested' ] ) ); ?>" class="wpss-action-item wpss-action-urgent">
							<span class="wpss-action-count"><?php echo esc_html( $pending_revision ); ?></span>
							<span class="wpss-action-label"><?php esc_html_e( 'Revision Requests', 'wp-sell-services' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Performance Stats -->
			<div class="wpss-overview-section wpss-performance">
				<h3><?php esc_html_e( 'Performance', 'wp-sell-services' ); ?></h3>
				<div class="wpss-stats-grid">
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Orders Completed', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( $stats['orders_completed'] ?? 0 ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Average Rating', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( number_format( $stats['average_rating'] ?? 0, 1 ) ); ?> ★</span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Response Rate', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( ( $stats['response_rate'] ?? 0 ) . '%' ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'On-Time Delivery', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( ( $stats['on_time_rate'] ?? 0 ) . '%' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Recent Orders -->
			<div class="wpss-overview-section wpss-recent-orders">
				<h3><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h3>
				<?php if ( ! empty( $recent_orders ) ) : ?>
					<table class="wpss-orders-table">
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
									<td><?php echo esc_html( get_userdata( $order['customer_id'] )->display_name ?? '' ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $order['total'] ) ); ?></td>
									<td><span class="wpss-status wpss-status-<?php echo esc_attr( $order['status'] ); ?>"><?php echo esc_html( $this->get_status_label( $order['status'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="wpss-view-all"><a href="<?php echo esc_url( add_query_arg( 'tab', 'orders' ) ); ?>"><?php esc_html_e( 'View All Orders', 'wp-sell-services' ); ?> →</a></p>
				<?php else : ?>
					<p class="wpss-no-data"><?php esc_html_e( 'No orders yet.', 'wp-sell-services' ); ?></p>
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

		$args = [
			'limit'  => 20,
			'offset' => ( $page - 1 ) * 20,
		];

		if ( $status ) {
			$args['status'] = $status;
		}

		$orders      = $this->order_service->get_by_vendor( $vendor_id, $args );
		$total_count = $this->order_service->count_by_vendor( $vendor_id, $status ? [ 'status' => $status ] : [] );
		$total_pages = ceil( $total_count / 20 );

		// Status filters.
		$statuses = [
			''                     => __( 'All Orders', 'wp-sell-services' ),
			'requirements_pending' => __( 'Requirements Pending', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'             => __( 'Disputed', 'wp-sell-services' ),
		];
		?>
		<div class="wpss-orders-tab">
			<h3><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h3>

			<!-- Status Filters -->
			<div class="wpss-status-filters">
				<?php foreach ( $statuses as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'orders', 'status' => $key ] ) ); ?>"
					   class="<?php echo $status === $key ? 'active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<!-- Orders Table -->
			<?php if ( ! empty( $orders ) ) : ?>
				<table class="wpss-orders-table wpss-full-table">
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
								<td><?php echo esc_html( get_userdata( $order['customer_id'] )->display_name ?? '' ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $order['total'] ) ); ?></td>
								<td><?php echo $order['due_date'] ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $order['due_date'] ) ) ) : '—'; ?></td>
								<td><span class="wpss-status wpss-status-<?php echo esc_attr( $order['status'] ); ?>"><?php echo esc_html( $this->get_status_label( $order['status'] ) ); ?></span></td>
								<td>
									<a href="<?php echo esc_url( $this->get_order_url( $order['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="wpss-pagination">
						<?php
						echo wp_kses_post(
							paginate_links( [
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							] )
						);
						?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p class="wpss-no-data"><?php esc_html_e( 'No orders found.', 'wp-sell-services' ); ?></p>
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
		<div class="wpss-services-tab">
			<div class="wpss-tab-header">
				<h3><?php esc_html_e( 'My Services', 'wp-sell-services' ); ?></h3>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Add New Service', 'wp-sell-services' ); ?>
				</a>
			</div>

			<?php if ( ! empty( $services ) ) : ?>
				<div class="wpss-services-grid">
					<?php foreach ( $services as $service ) : ?>
						<div class="wpss-service-card" data-service-id="<?php echo esc_attr( $service['id'] ); ?>">
							<?php if ( $service['thumbnail'] ) : ?>
								<div class="wpss-service-thumbnail">
									<img src="<?php echo esc_url( $service['thumbnail'] ); ?>" alt="<?php echo esc_attr( $service['title'] ); ?>">
								</div>
							<?php endif; ?>
							<div class="wpss-service-info">
								<h4><?php echo esc_html( $service['title'] ); ?></h4>
								<div class="wpss-service-meta">
									<span class="wpss-service-price"><?php esc_html_e( 'From', 'wp-sell-services' ); ?> <?php echo wp_kses_post( wc_price( $service['starting_price'] ?? 0 ) ); ?></span>
									<span class="wpss-service-status wpss-status-<?php echo esc_attr( $service['status'] ); ?>">
										<?php echo esc_html( ucfirst( $service['status'] ) ); ?>
									</span>
								</div>
								<div class="wpss-service-stats">
									<span><?php echo esc_html( $service['views'] ?? 0 ); ?> <?php esc_html_e( 'views', 'wp-sell-services' ); ?></span>
									<span><?php echo esc_html( $service['orders'] ?? 0 ); ?> <?php esc_html_e( 'orders', 'wp-sell-services' ); ?></span>
									<?php if ( $service['rating'] ) : ?>
										<span><?php echo esc_html( number_format( $service['rating'], 1 ) ); ?> ★</span>
									<?php endif; ?>
								</div>
								<div class="wpss-service-actions">
									<a href="<?php echo esc_url( get_edit_post_link( $service['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'wp-sell-services' ); ?></a>
									<a href="<?php echo esc_url( get_permalink( $service['id'] ) ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a>
									<button type="button" class="button button-small wpss-toggle-status" data-service-id="<?php echo esc_attr( $service['id'] ); ?>" data-current-status="<?php echo esc_attr( $service['status'] ); ?>">
										<?php echo 'publish' === $service['status'] ? esc_html__( 'Pause', 'wp-sell-services' ) : esc_html__( 'Activate', 'wp-sell-services' ); ?>
									</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wpss-no-data wpss-no-services">
					<p><?php esc_html_e( 'You haven\'t created any services yet.', 'wp-sell-services' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Create Your First Service', 'wp-sell-services' ); ?>
					</a>
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
		$history  = $this->earnings_service->get_history( $vendor_id, [ 'limit' => 20 ] );
		$methods  = $this->earnings_service->get_withdrawal_methods();
		?>
		<div class="wpss-earnings-tab">
			<h3><?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?></h3>

			<!-- Earnings Summary -->
			<div class="wpss-earnings-overview">
				<div class="wpss-stats-grid">
					<div class="wpss-stat-card wpss-stat-primary">
						<span class="wpss-stat-label"><?php esc_html_e( 'Available for Withdrawal', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['available_balance'] ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Pending Clearance', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['pending_clearance'] ) ); ?></span>
						<span class="wpss-stat-note">
							<?php
							printf(
								/* translators: %d: clearance days */
								esc_html__( 'Clears after %d days', 'wp-sell-services' ),
								(int) get_option( 'wpss_earnings_clearance_days', 14 )
							);
							?>
						</span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Pending Withdrawal', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['pending_withdrawal'] ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Total Withdrawn', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo wp_kses_post( wc_price( $earnings['withdrawn'] ) ); ?></span>
					</div>
				</div>
			</div>

			<!-- Withdrawal Request Form -->
			<?php if ( $earnings['available_balance'] >= (float) get_option( 'wpss_min_withdrawal', 50 ) ) : ?>
				<div class="wpss-withdrawal-form-wrapper">
					<h4><?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?></h4>
					<form id="wpss-withdrawal-form" class="wpss-withdrawal-form">
						<?php wp_nonce_field( 'wpss_request_withdrawal', 'wpss_withdrawal_nonce' ); ?>
						<div class="wpss-form-row">
							<label for="withdrawal_amount"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></label>
							<input type="number" name="amount" id="withdrawal_amount"
								   min="<?php echo esc_attr( get_option( 'wpss_min_withdrawal', 50 ) ); ?>"
								   max="<?php echo esc_attr( $earnings['available_balance'] ); ?>"
								   step="0.01" required>
							<span class="wpss-form-note">
								<?php
								printf(
									/* translators: %s: minimum withdrawal amount */
									esc_html__( 'Minimum: %s', 'wp-sell-services' ),
									wp_kses_post( wc_price( get_option( 'wpss_min_withdrawal', 50 ) ) )
								);
								?>
							</span>
						</div>
						<div class="wpss-form-row">
							<label for="withdrawal_method"><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></label>
							<select name="method" id="withdrawal_method" required>
								<option value=""><?php esc_html_e( 'Select method', 'wp-sell-services' ); ?></option>
								<?php foreach ( $methods as $key => $method ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $method['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wpss-form-row wpss-method-details" style="display: none;">
							<label for="payment_details"><?php esc_html_e( 'Payment Details', 'wp-sell-services' ); ?></label>
							<textarea name="details" id="payment_details" rows="3" placeholder="<?php esc_attr_e( 'Enter your payment details (e.g., PayPal email, bank account info)', 'wp-sell-services' ); ?>"></textarea>
						</div>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Request Withdrawal', 'wp-sell-services' ); ?></button>
					</form>
				</div>
			<?php else : ?>
				<div class="wpss-withdrawal-notice">
					<p>
						<?php
						printf(
							/* translators: %s: minimum withdrawal amount */
							esc_html__( 'You need at least %s in available balance to request a withdrawal.', 'wp-sell-services' ),
							wp_kses_post( wc_price( get_option( 'wpss_min_withdrawal', 50 ) ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Transaction History -->
			<div class="wpss-earnings-history">
				<h4><?php esc_html_e( 'Transaction History', 'wp-sell-services' ); ?></h4>
				<?php if ( ! empty( $history ) ) : ?>
					<table class="wpss-history-table">
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
									<td class="<?php echo $transaction['type'] === 'credit' ? 'wpss-amount-positive' : 'wpss-amount-negative'; ?>">
										<?php echo $transaction['type'] === 'credit' ? '+' : '-'; ?>
										<?php echo wp_kses_post( wc_price( abs( $transaction['amount'] ) ) ); ?>
									</td>
									<td><span class="wpss-status wpss-status-<?php echo esc_attr( $transaction['status'] ); ?>"><?php echo esc_html( ucfirst( $transaction['status'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="wpss-no-data"><?php esc_html_e( 'No transactions yet.', 'wp-sell-services' ); ?></p>
				<?php endif; ?>
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
		$portfolio = $this->portfolio_service->get_by_vendor( $vendor_id, [ 'limit' => 100 ] );
		$max_items = (int) get_option( 'wpss_max_portfolio_items', 50 );
		?>
		<div class="wpss-portfolio-tab">
			<div class="wpss-tab-header">
				<h3><?php esc_html_e( 'Portfolio', 'wp-sell-services' ); ?></h3>
				<?php if ( count( $portfolio ) < $max_items ) : ?>
					<button type="button" class="button button-primary" id="wpss-add-portfolio-item">
						<?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<p class="wpss-portfolio-count">
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
				<div class="wpss-portfolio-grid" id="wpss-portfolio-sortable">
					<?php foreach ( $portfolio as $item ) : ?>
						<div class="wpss-portfolio-item" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
							<div class="wpss-portfolio-media">
								<?php if ( ! empty( $item['media'] ) ) : ?>
									<img src="<?php echo esc_url( $item['media'][0]['medium'] ?? $item['media'][0]['url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
								<?php else : ?>
									<div class="wpss-portfolio-placeholder">
										<span class="dashicons dashicons-format-image"></span>
									</div>
								<?php endif; ?>
								<?php if ( $item['is_featured'] ) : ?>
									<span class="wpss-featured-badge"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="wpss-portfolio-details">
								<h4><?php echo esc_html( $item['title'] ); ?></h4>
								<?php if ( ! empty( $item['tags'] ) ) : ?>
									<div class="wpss-portfolio-tags">
										<?php foreach ( array_slice( $item['tags'], 0, 3 ) as $tag ) : ?>
											<span class="wpss-tag"><?php echo esc_html( $tag ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="wpss-portfolio-actions">
								<button type="button" class="wpss-toggle-featured" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Toggle Featured', 'wp-sell-services' ); ?>">
									<span class="dashicons dashicons-star-<?php echo $item['is_featured'] ? 'filled' : 'empty'; ?>"></span>
								</button>
								<button type="button" class="wpss-edit-portfolio" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Edit', 'wp-sell-services' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button type="button" class="wpss-delete-portfolio" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'wp-sell-services' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
								<span class="wpss-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>">
									<span class="dashicons dashicons-menu"></span>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wpss-no-data wpss-no-portfolio">
					<p><?php esc_html_e( 'Your portfolio is empty. Add items to showcase your work!', 'wp-sell-services' ); ?></p>
					<button type="button" class="button button-primary" id="wpss-add-first-portfolio">
						<?php esc_html_e( 'Add Your First Item', 'wp-sell-services' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Portfolio Add/Edit Modal -->
			<div id="wpss-portfolio-modal" class="wpss-modal" style="display: none;">
				<div class="wpss-modal-content">
					<div class="wpss-modal-header">
						<h4 id="wpss-portfolio-modal-title"><?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?></h4>
						<button type="button" class="wpss-modal-close">&times;</button>
					</div>
					<form id="wpss-portfolio-form">
						<?php wp_nonce_field( 'wpss_portfolio_nonce', 'portfolio_nonce' ); ?>
						<input type="hidden" name="item_id" id="portfolio_item_id" value="">

						<div class="wpss-form-row">
							<label for="portfolio_title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?> <span class="required">*</span></label>
							<input type="text" name="title" id="portfolio_title" required>
						</div>

						<div class="wpss-form-row">
							<label for="portfolio_description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
							<textarea name="description" id="portfolio_description" rows="4"></textarea>
						</div>

						<div class="wpss-form-row">
							<label><?php esc_html_e( 'Images/Videos', 'wp-sell-services' ); ?></label>
							<div class="wpss-media-uploader" id="portfolio_media_uploader">
								<input type="hidden" name="media" id="portfolio_media" value="">
								<div class="wpss-media-preview" id="portfolio_media_preview"></div>
								<button type="button" class="button wpss-upload-media"><?php esc_html_e( 'Add Media', 'wp-sell-services' ); ?></button>
							</div>
						</div>

						<div class="wpss-form-row">
							<label for="portfolio_external_url"><?php esc_html_e( 'External URL', 'wp-sell-services' ); ?></label>
							<input type="url" name="external_url" id="portfolio_external_url" placeholder="https://example.com/project">
						</div>

						<div class="wpss-form-row">
							<label for="portfolio_tags"><?php esc_html_e( 'Tags', 'wp-sell-services' ); ?></label>
							<input type="text" name="tags" id="portfolio_tags" placeholder="<?php esc_attr_e( 'Enter tags separated by commas', 'wp-sell-services' ); ?>">
						</div>

						<div class="wpss-form-row">
							<label for="portfolio_service"><?php esc_html_e( 'Related Service', 'wp-sell-services' ); ?></label>
							<select name="service_id" id="portfolio_service">
								<option value=""><?php esc_html_e( 'None', 'wp-sell-services' ); ?></option>
								<?php
								$vendor_services = $this->service_manager->get_by_vendor( $vendor_id );
								foreach ( $vendor_services as $service ) :
									?>
									<option value="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="wpss-form-row">
							<label>
								<input type="checkbox" name="is_featured" id="portfolio_featured" value="1">
								<?php esc_html_e( 'Feature this item on my profile', 'wp-sell-services' ); ?>
							</label>
						</div>

						<div class="wpss-form-actions">
							<button type="button" class="button wpss-modal-cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'wp-sell-services' ); ?></button>
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
		<div class="wpss-analytics-tab">
			<div class="wpss-tab-header">
				<h3><?php esc_html_e( 'Analytics', 'wp-sell-services' ); ?></h3>
				<div class="wpss-period-selector">
					<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'analytics', 'period' => '7' ] ) ); ?>" class="<?php echo '7' === $period ? 'active' : ''; ?>"><?php esc_html_e( '7 Days', 'wp-sell-services' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'analytics', 'period' => '30' ] ) ); ?>" class="<?php echo '30' === $period ? 'active' : ''; ?>"><?php esc_html_e( '30 Days', 'wp-sell-services' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'analytics', 'period' => '90' ] ) ); ?>" class="<?php echo '90' === $period ? 'active' : ''; ?>"><?php esc_html_e( '90 Days', 'wp-sell-services' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'analytics', 'period' => '365' ] ) ); ?>" class="<?php echo '365' === $period ? 'active' : ''; ?>"><?php esc_html_e( 'Year', 'wp-sell-services' ); ?></a>
				</div>
			</div>

			<!-- Overview Stats -->
			<div class="wpss-analytics-overview">
				<div class="wpss-stats-grid">
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Profile Views', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( number_format( $stats['profile_views'] ?? 0 ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Service Impressions', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( number_format( $stats['impressions'] ?? 0 ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Service Clicks', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( number_format( $stats['clicks'] ?? 0 ) ); ?></span>
					</div>
					<div class="wpss-stat-card">
						<span class="wpss-stat-label"><?php esc_html_e( 'Orders Received', 'wp-sell-services' ); ?></span>
						<span class="wpss-stat-value"><?php echo esc_html( $stats['orders_received'] ?? 0 ); ?></span>
					</div>
				</div>
			</div>

			<!-- Conversion Funnel -->
			<div class="wpss-analytics-section">
				<h4><?php esc_html_e( 'Conversion Funnel', 'wp-sell-services' ); ?></h4>
				<div class="wpss-funnel">
					<div class="wpss-funnel-step">
						<span class="wpss-funnel-value"><?php echo esc_html( number_format( $stats['impressions'] ?? 0 ) ); ?></span>
						<span class="wpss-funnel-label"><?php esc_html_e( 'Impressions', 'wp-sell-services' ); ?></span>
					</div>
					<div class="wpss-funnel-arrow">→</div>
					<div class="wpss-funnel-step">
						<span class="wpss-funnel-value"><?php echo esc_html( number_format( $stats['clicks'] ?? 0 ) ); ?></span>
						<span class="wpss-funnel-label"><?php esc_html_e( 'Clicks', 'wp-sell-services' ); ?></span>
						<span class="wpss-funnel-rate"><?php echo esc_html( $stats['click_rate'] ?? 0 ); ?>%</span>
					</div>
					<div class="wpss-funnel-arrow">→</div>
					<div class="wpss-funnel-step">
						<span class="wpss-funnel-value"><?php echo esc_html( $stats['orders_received'] ?? 0 ); ?></span>
						<span class="wpss-funnel-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
						<span class="wpss-funnel-rate"><?php echo esc_html( $stats['conversion_rate'] ?? 0 ); ?>%</span>
					</div>
				</div>
			</div>

			<!-- Top Services -->
			<?php if ( ! empty( $stats['top_services'] ) ) : ?>
				<div class="wpss-analytics-section">
					<h4><?php esc_html_e( 'Top Performing Services', 'wp-sell-services' ); ?></h4>
					<table class="wpss-analytics-table">
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
									<td><a href="<?php echo esc_url( get_permalink( $service['id'] ) ); ?>"><?php echo esc_html( $service['title'] ); ?></a></td>
									<td><?php echo esc_html( number_format( $service['views'] ) ); ?></td>
									<td><?php echo esc_html( $service['orders'] ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $service['revenue'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
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
		<div class="wpss-profile-tab">
			<h3><?php esc_html_e( 'Vendor Profile', 'wp-sell-services' ); ?></h3>

			<form id="wpss-vendor-profile-form" class="wpss-profile-form">
				<?php wp_nonce_field( 'wpss_update_profile', 'wpss_profile_nonce' ); ?>

				<!-- Basic Info -->
				<div class="wpss-form-section">
					<h4><?php esc_html_e( 'Basic Information', 'wp-sell-services' ); ?></h4>

					<div class="wpss-form-row">
						<label for="display_name"><?php esc_html_e( 'Display Name', 'wp-sell-services' ); ?></label>
						<input type="text" name="display_name" id="display_name" value="<?php echo esc_attr( $profile['display_name'] ?? $user->display_name ); ?>" required>
					</div>

					<div class="wpss-form-row">
						<label for="tagline"><?php esc_html_e( 'Professional Tagline', 'wp-sell-services' ); ?></label>
						<input type="text" name="tagline" id="tagline" value="<?php echo esc_attr( $profile['tagline'] ?? '' ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g., Professional Web Developer with 10+ years experience', 'wp-sell-services' ); ?>">
					</div>

					<div class="wpss-form-row">
						<label for="bio"><?php esc_html_e( 'Bio', 'wp-sell-services' ); ?></label>
						<textarea name="bio" id="bio" rows="5" placeholder="<?php esc_attr_e( 'Tell buyers about yourself, your experience, and what makes you unique...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $profile['bio'] ?? '' ); ?></textarea>
					</div>

					<div class="wpss-form-row">
						<label><?php esc_html_e( 'Profile Photo', 'wp-sell-services' ); ?></label>
						<div class="wpss-avatar-uploader">
							<input type="hidden" name="avatar_id" id="vendor_avatar_id" value="<?php echo esc_attr( $profile['avatar_id'] ?? '' ); ?>">
							<div class="wpss-avatar-preview">
								<?php echo get_avatar( $vendor_id, 150 ); ?>
							</div>
							<button type="button" class="button wpss-upload-avatar"><?php esc_html_e( 'Change Photo', 'wp-sell-services' ); ?></button>
						</div>
					</div>
				</div>

				<!-- Skills & Expertise -->
				<div class="wpss-form-section">
					<h4><?php esc_html_e( 'Skills & Expertise', 'wp-sell-services' ); ?></h4>

					<div class="wpss-form-row">
						<label for="skills"><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?></label>
						<input type="text" name="skills" id="skills" value="<?php echo esc_attr( implode( ', ', $profile['skills'] ?? [] ) ); ?>" placeholder="<?php esc_attr_e( 'Enter skills separated by commas', 'wp-sell-services' ); ?>">
					</div>

					<div class="wpss-form-row">
						<label for="languages"><?php esc_html_e( 'Languages', 'wp-sell-services' ); ?></label>
						<input type="text" name="languages" id="languages" value="<?php echo esc_attr( implode( ', ', $profile['languages'] ?? [] ) ); ?>" placeholder="<?php esc_attr_e( 'e.g., English (Native), Spanish (Fluent)', 'wp-sell-services' ); ?>">
					</div>

					<div class="wpss-form-row">
						<label for="experience_level"><?php esc_html_e( 'Experience Level', 'wp-sell-services' ); ?></label>
						<select name="experience_level" id="experience_level">
							<option value="beginner" <?php selected( $profile['experience_level'] ?? '', 'beginner' ); ?>><?php esc_html_e( 'Beginner (0-2 years)', 'wp-sell-services' ); ?></option>
							<option value="intermediate" <?php selected( $profile['experience_level'] ?? '', 'intermediate' ); ?>><?php esc_html_e( 'Intermediate (2-5 years)', 'wp-sell-services' ); ?></option>
							<option value="expert" <?php selected( $profile['experience_level'] ?? '', 'expert' ); ?>><?php esc_html_e( 'Expert (5+ years)', 'wp-sell-services' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Contact & Social -->
				<div class="wpss-form-section">
					<h4><?php esc_html_e( 'Contact & Social', 'wp-sell-services' ); ?></h4>

					<div class="wpss-form-row">
						<label for="website"><?php esc_html_e( 'Website', 'wp-sell-services' ); ?></label>
						<input type="url" name="website" id="website" value="<?php echo esc_url( $profile['website'] ?? '' ); ?>" placeholder="https://yourwebsite.com">
					</div>

					<div class="wpss-form-row">
						<label for="location"><?php esc_html_e( 'Location', 'wp-sell-services' ); ?></label>
						<input type="text" name="location" id="location" value="<?php echo esc_attr( $profile['location'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'City, Country', 'wp-sell-services' ); ?>">
					</div>

					<div class="wpss-form-row">
						<label for="timezone"><?php esc_html_e( 'Timezone', 'wp-sell-services' ); ?></label>
						<select name="timezone" id="timezone">
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

				<!-- Availability -->
				<div class="wpss-form-section">
					<h4><?php esc_html_e( 'Availability', 'wp-sell-services' ); ?></h4>

					<div class="wpss-form-row">
						<label>
							<input type="checkbox" name="available_for_work" id="available_for_work" value="1" <?php checked( $profile['available_for_work'] ?? true ); ?>>
							<?php esc_html_e( 'I am available to take new orders', 'wp-sell-services' ); ?>
						</label>
					</div>

					<div class="wpss-form-row">
						<label for="response_time"><?php esc_html_e( 'Typical Response Time', 'wp-sell-services' ); ?></label>
						<select name="response_time" id="response_time">
							<option value="1" <?php selected( $profile['response_time'] ?? '', '1' ); ?>><?php esc_html_e( 'Within 1 hour', 'wp-sell-services' ); ?></option>
							<option value="3" <?php selected( $profile['response_time'] ?? '', '3' ); ?>><?php esc_html_e( 'Within 3 hours', 'wp-sell-services' ); ?></option>
							<option value="12" <?php selected( $profile['response_time'] ?? '', '12' ); ?>><?php esc_html_e( 'Within 12 hours', 'wp-sell-services' ); ?></option>
							<option value="24" <?php selected( $profile['response_time'] ?? '', '24' ); ?>><?php esc_html_e( 'Within 24 hours', 'wp-sell-services' ); ?></option>
						</select>
					</div>
				</div>

				<div class="wpss-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Profile', 'wp-sell-services' ); ?></button>
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
	public function render_registration_form( array $atts = [] ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			return '<div class="wpss-notice wpss-notice-info">' . esc_html__( 'You are already a registered vendor.', 'wp-sell-services' ) . ' <a href="' . esc_url( $this->get_dashboard_url() ) . '">' . esc_html__( 'Go to Dashboard', 'wp-sell-services' ) . '</a></div>';
		}

		ob_start();
		?>
		<div class="wpss-vendor-registration">
			<h2><?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?></h2>
			<p class="wpss-registration-intro"><?php esc_html_e( 'Start selling your services today! Complete the form below to become a vendor.', 'wp-sell-services' ); ?></p>

			<form id="wpss-vendor-registration-form" class="wpss-registration-form">
				<?php wp_nonce_field( 'wpss_vendor_registration', 'wpss_registration_nonce' ); ?>

				<div class="wpss-form-row">
					<label for="reg_display_name"><?php esc_html_e( 'Display Name', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<input type="text" name="display_name" id="reg_display_name" value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" required>
				</div>

				<div class="wpss-form-row">
					<label for="reg_tagline"><?php esc_html_e( 'Professional Tagline', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<input type="text" name="tagline" id="reg_tagline" maxlength="100" placeholder="<?php esc_attr_e( 'e.g., Professional Web Developer', 'wp-sell-services' ); ?>" required>
				</div>

				<div class="wpss-form-row">
					<label for="reg_bio"><?php esc_html_e( 'About You', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<textarea name="bio" id="reg_bio" rows="5" placeholder="<?php esc_attr_e( 'Tell us about your experience and expertise...', 'wp-sell-services' ); ?>" required></textarea>
				</div>

				<div class="wpss-form-row">
					<label for="reg_skills"><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<input type="text" name="skills" id="reg_skills" placeholder="<?php esc_attr_e( 'Enter skills separated by commas', 'wp-sell-services' ); ?>" required>
				</div>

				<div class="wpss-form-row">
					<label>
						<input type="checkbox" name="terms_agreed" id="reg_terms" value="1" required>
						<?php
						printf(
							/* translators: %s: terms and conditions link */
							esc_html__( 'I agree to the %s', 'wp-sell-services' ),
							'<a href="' . esc_url( get_permalink( get_option( 'wpss_terms_page' ) ) ) . '" target="_blank">' . esc_html__( 'Terms and Conditions', 'wp-sell-services' ) . '</a>'
						);
						?>
					</label>
				</div>

				<div class="wpss-form-actions">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Submit Application', 'wp-sell-services' ); ?></button>
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
		return '<div class="wpss-notice wpss-notice-warning">' .
			esc_html__( 'Please log in to access this page.', 'wp-sell-services' ) .
			' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log In', 'wp-sell-services' ) . '</a></div>';
	}

	/**
	 * Render not a vendor message.
	 *
	 * @return string Not vendor message HTML.
	 */
	private function render_not_vendor(): string {
		return '<div class="wpss-notice wpss-notice-info">' .
			esc_html__( 'You are not registered as a vendor.', 'wp-sell-services' ) .
			' <a href="' . esc_url( $this->get_registration_url() ) . '">' . esc_html__( 'Become a Vendor', 'wp-sell-services' ) . '</a></div>';
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
			wp_send_json_error( [ 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ] );
		}

		$data = [
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
		];

		$result = $this->vendor_service->update_profile( $user_id, $data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
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
			wp_send_json_error( [ 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ] );
		}

		$amount  = floatval( $_POST['amount'] ?? 0 );
		$method  = sanitize_text_field( wp_unslash( $_POST['method'] ?? '' ) );
		$details = sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) );

		$result = $this->earnings_service->request_withdrawal( $user_id, $amount, $method, [ 'payment_details' => $details ] );

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
			wp_send_json_error( [ 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ] );
		}

		$data = [
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'media'        => array_map( 'absint', json_decode( stripslashes( $_POST['media'] ?? '[]' ), true ) ?: [] ),
			'external_url' => esc_url_raw( wp_unslash( $_POST['external_url'] ?? '' ) ),
			'tags'         => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ) ) ),
			'service_id'   => absint( $_POST['service_id'] ?? 0 ),
			'is_featured'  => ! empty( $_POST['is_featured'] ),
		];

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
			wp_send_json_error( [ 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ] );
		}

		$order = array_map( 'absint', $_POST['order'] ?? [] );

		if ( $this->portfolio_service->reorder( $user_id, $order ) ) {
			wp_send_json_success( [ 'message' => __( 'Portfolio reordered successfully.', 'wp-sell-services' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to reorder portfolio.', 'wp-sell-services' ) ] );
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
			wp_send_json_error( [ 'message' => __( 'Service not found.', 'wp-sell-services' ) ] );
		}

		$new_status = 'publish' === $status ? 'draft' : 'publish';

		$result = wp_update_post( [
			'ID'          => $service_id,
			'post_status' => $new_status,
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'    => __( 'Service status updated.', 'wp-sell-services' ),
			'new_status' => $new_status,
		] );
	}

	/**
	 * AJAX: Vendor registration.
	 *
	 * @return void
	 */
	public function ajax_vendor_registration(): void {
		check_ajax_referer( 'wpss_vendor_registration', 'wpss_registration_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in to register as a vendor.', 'wp-sell-services' ) ] );
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are already registered as a vendor.', 'wp-sell-services' ) ] );
		}

		$data = [
			'display_name'  => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'tagline'       => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
			'bio'           => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'skills'        => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['skills'] ?? '' ) ) ) ),
			'terms_agreed'  => ! empty( $_POST['terms_agreed'] ),
		];

		if ( ! $data['terms_agreed'] ) {
			wp_send_json_error( [ 'message' => __( 'You must agree to the terms and conditions.', 'wp-sell-services' ) ] );
		}

		$result = $this->vendor_service->register_vendor( $user_id, $data );

		if ( $result['success'] ) {
			wp_send_json_success( array_merge( $result, [
				'redirect' => $this->get_dashboard_url(),
			] ) );
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
		return add_query_arg( [ 'tab' => 'orders', 'order_id' => $order_id ] );
	}

	/**
	 * Get dashboard URL.
	 *
	 * @return string Dashboard URL.
	 */
	private function get_dashboard_url(): string {
		$page_id = get_option( 'wpss_vendor_dashboard_page' );
		return $page_id ? get_permalink( $page_id ) : home_url();
	}

	/**
	 * Get registration URL.
	 *
	 * @return string Registration URL.
	 */
	private function get_registration_url(): string {
		$page_id = get_option( 'wpss_become_vendor_page' );
		return $page_id ? get_permalink( $page_id ) : home_url();
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status slug.
	 * @return string Status label.
	 */
	private function get_status_label( string $status ): string {
		$labels = [
			'pending'              => __( 'Pending', 'wp-sell-services' ),
			'requirements_pending' => __( 'Requirements Pending', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'             => __( 'Disputed', 'wp-sell-services' ),
			'late'                 => __( 'Late', 'wp-sell-services' ),
		];

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}
}
