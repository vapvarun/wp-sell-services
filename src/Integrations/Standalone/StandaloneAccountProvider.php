<?php
/**
 * Standalone Account Provider
 *
 * @package WPSellServices\Integrations\Standalone
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Standalone;

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
		$url = wpss_get_page_url( 'dashboard' );
		if ( $endpoint ) {
			$url = add_query_arg( 'section', $endpoint, $url );
		}
		return $url;
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
		$this->render_orders_page();
	}

	/**
	 * Render vendor services endpoint content.
	 *
	 * @return void
	 */
	public function render_services_endpoint(): void {
		$this->render_vendor_services();
	}

	/**
	 * Render notifications endpoint content.
	 *
	 * @return void
	 */
	public function render_notifications_endpoint(): void {
		$this->render_notifications_page();
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
		return add_query_arg( 'section', 'orders', wpss_get_page_url( 'dashboard' ) );
	}

	/**
	 * Get vendor dashboard URL.
	 *
	 * @return string
	 */
	public function get_vendor_dashboard_url(): string {
		return add_query_arg( 'section', 'overview', wpss_get_page_url( 'dashboard' ) );
	}

	/**
	 * Get vendor services URL.
	 *
	 * @return string
	 */
	public function get_vendor_services_url(): string {
		return add_query_arg( 'section', 'services', wpss_get_page_url( 'dashboard' ) );
	}

	/**
	 * Get notifications URL.
	 *
	 * @return string
	 */
	public function get_notifications_url(): string {
		return add_query_arg( 'section', 'notifications', wpss_get_page_url( 'dashboard' ) );
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
				'icon'  => 'dashicons-dashboard',
			),
			'orders'        => array(
				'label' => __( 'Service Orders', 'wp-sell-services' ),
				'url'   => $this->get_orders_url(),
				'icon'  => 'dashicons-list-view',
			),
			'notifications' => array(
				'label' => __( 'Notifications', 'wp-sell-services' ),
				'url'   => $this->get_notifications_url(),
				'icon'  => 'dashicons-bell',
			),
		);

		// Add vendor items if user is a vendor.
		if ( wpss_is_vendor() ) {
			$items['vendor-dashboard'] = array(
				'label' => __( 'Vendor Dashboard', 'wp-sell-services' ),
				'url'   => $this->get_vendor_dashboard_url(),
				'icon'  => 'dashicons-store',
			);
			$items['vendor-services']  = array(
				'label' => __( 'My Services', 'wp-sell-services' ),
				'url'   => $this->get_vendor_services_url(),
				'icon'  => 'dashicons-admin-tools',
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

		$page = get_query_var( 'wpss_account_page' ) ?: 'dashboard';

		ob_start();
		$this->render_account_page( $page );
		return ob_get_clean();
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
				background: #fff;
				border: 1px solid #e5e5e5;
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

	/**
	 * Render account page.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	private function render_account_page( string $page ): void {
		$menu_items   = $this->get_menu_items();
		$current_user = wp_get_current_user();
		?>
		<div class="wpss-account-wrapper">
			<nav class="wpss-account-nav">
				<div class="wpss-account-user">
					<?php echo get_avatar( $current_user->ID, 60 ); ?>
					<span class="wpss-user-name"><?php echo esc_html( $current_user->display_name ); ?></span>
				</div>

				<ul class="wpss-account-menu">
					<?php foreach ( $menu_items as $key => $item ) : ?>
						<li class="<?php echo esc_attr( $page === $key ? 'active' : '' ); ?>">
							<a href="<?php echo esc_url( $item['url'] ); ?>">
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
								<?php echo esc_html( $item['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
					<li>
						<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
							<span class="dashicons dashicons-exit"></span>
							<?php esc_html_e( 'Logout', 'wp-sell-services' ); ?>
						</a>
					</li>
				</ul>
			</nav>

			<main class="wpss-account-content">
				<?php $this->render_page_content( $page ); ?>
			</main>
		</div>

		<style>
			.wpss-account-wrapper {
				display: grid;
				grid-template-columns: 250px 1fr;
				gap: 30px;
				max-width: 1200px;
				margin: 0 auto;
			}
			.wpss-account-nav {
				background: #fff;
				border: 1px solid #e5e5e5;
				border-radius: 8px;
				padding: 20px;
			}
			.wpss-account-user {
				text-align: center;
				padding-bottom: 20px;
				border-bottom: 1px solid #eee;
				margin-bottom: 20px;
			}
			.wpss-account-user img {
				border-radius: 50%;
				margin-bottom: 10px;
			}
			.wpss-user-name {
				display: block;
				font-weight: 600;
			}
			.wpss-account-menu {
				list-style: none;
				margin: 0;
				padding: 0;
			}
			.wpss-account-menu li {
				margin-bottom: 5px;
			}
			.wpss-account-menu a {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 10px 15px;
				border-radius: 4px;
				text-decoration: none;
				color: #333;
			}
			.wpss-account-menu a:hover,
			.wpss-account-menu li.active a {
				background: #f0f0f0;
			}
			.wpss-account-content {
				background: #fff;
				border: 1px solid #e5e5e5;
				border-radius: 8px;
				padding: 30px;
			}
			@media (max-width: 768px) {
				.wpss-account-wrapper {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}

	/**
	 * Render page content based on current page.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	private function render_page_content( string $page ): void {
		switch ( $page ) {
			case 'orders':
				$this->render_orders_page();
				break;
			case 'notifications':
				$this->render_notifications_page();
				break;
			case 'vendor-dashboard':
				$this->render_vendor_dashboard();
				break;
			case 'vendor-services':
				$this->render_vendor_services();
				break;
			default:
				$this->render_dashboard();
				break;
		}
	}

	/**
	 * Render main dashboard.
	 *
	 * @return void
	 */
	private function render_dashboard(): void {
		$user_id      = get_current_user_id();
		$order_count  = wpss_get_user_order_count( $user_id );
		$active_count = wpss_get_user_active_order_count( $user_id );
		?>
		<h2><?php esc_html_e( 'Dashboard', 'wp-sell-services' ); ?></h2>

		<div class="wpss-dashboard-stats">
			<div class="wpss-stat-card">
				<span class="wpss-stat-number"><?php echo esc_html( $order_count ); ?></span>
				<span class="wpss-stat-label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
			</div>
			<div class="wpss-stat-card">
				<span class="wpss-stat-number"><?php echo esc_html( $active_count ); ?></span>
				<span class="wpss-stat-label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></span>
			</div>
		</div>

		<h3><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h3>
		<?php $this->render_orders_table( 5 ); ?>

		<style>
			.wpss-dashboard-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
				gap: 20px;
				margin: 20px 0;
			}
			.wpss-stat-card {
				background: #f8f9fa;
				padding: 20px;
				border-radius: 8px;
				text-align: center;
			}
			.wpss-stat-number {
				display: block;
				font-size: 32px;
				font-weight: 700;
				color: #1e3a5f;
			}
			.wpss-stat-label {
				color: #666;
				font-size: 14px;
			}
		</style>
		<?php
	}

	/**
	 * Render orders page.
	 *
	 * @return void
	 */
	private function render_orders_page(): void {
		?>
		<h2><?php esc_html_e( 'Service Orders', 'wp-sell-services' ); ?></h2>
		<?php $this->render_orders_table(); ?>
		<?php
	}

	/**
	 * Render orders table.
	 *
	 * @param int $limit Number of orders to show.
	 * @return void
	 */
	private function render_orders_table( int $limit = 20 ): void {
		$orders = wpss_get_user_orders( get_current_user_id(), $limit );

		if ( empty( $orders ) ) {
			echo '<p>' . esc_html__( 'No orders found.', 'wp-sell-services' ) . '</p>';
			return;
		}
		?>
		<table class="wpss-orders-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td>#<?php echo esc_html( $order->order_number ); ?></td>
						<?php $service_post = get_post( $order->service_id ); ?>
						<td><?php echo esc_html( $service_post ? $service_post->post_title : 'N/A' ); ?></td>
						<td>
							<span class="wpss-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
								<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( wpss_format_price( $order->total, $order->currency ) ); ?></td>
						<td><?php echo esc_html( wp_date( 'M j, Y', strtotime( $order->created_at ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<style>
			.wpss-orders-table {
				width: 100%;
				border-collapse: collapse;
			}
			.wpss-orders-table th,
			.wpss-orders-table td {
				padding: 12px;
				text-align: left;
				border-bottom: 1px solid #eee;
			}
			.wpss-orders-table th {
				font-weight: 600;
				background: #f8f9fa;
			}
			.wpss-status {
				display: inline-block;
				padding: 4px 8px;
				border-radius: 4px;
				font-size: 12px;
				font-weight: 500;
			}
			.wpss-status-pending_payment { background: #fff3cd; color: #856404; }
			.wpss-status-pending_requirements { background: #cce5ff; color: #004085; }
			.wpss-status-in_progress { background: #d4edda; color: #155724; }
			.wpss-status-delivered { background: #d1ecf1; color: #0c5460; }
			.wpss-status-completed { background: #c3e6cb; color: #155724; }
			.wpss-status-cancelled { background: #f8d7da; color: #721c24; }
		</style>
		<?php
	}

	/**
	 * Render notifications page.
	 *
	 * @return void
	 */
	private function render_notifications_page(): void {
		$notifications = wpss_get_user_notifications( get_current_user_id() );
		?>
		<h2><?php esc_html_e( 'Notifications', 'wp-sell-services' ); ?></h2>

		<?php if ( empty( $notifications ) ) : ?>
			<p><?php esc_html_e( 'No notifications.', 'wp-sell-services' ); ?></p>
		<?php else : ?>
			<div class="wpss-notifications-list">
				<?php foreach ( $notifications as $notification ) : ?>
					<div class="wpss-notification <?php echo esc_attr( $notification->is_read ? '' : 'unread' ); ?>">
						<div class="wpss-notification-content">
							<strong><?php echo esc_html( $notification->title ); ?></strong>
							<p><?php echo esc_html( $notification->message ); ?></p>
							<span class="wpss-notification-time">
								<?php echo esc_html( human_time_diff( strtotime( $notification->created_at ) ) ); ?>
								<?php esc_html_e( 'ago', 'wp-sell-services' ); ?>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<style>
			.wpss-notification {
				padding: 15px;
				border: 1px solid #eee;
				border-radius: 4px;
				margin-bottom: 10px;
			}
			.wpss-notification.unread {
				background: #f8f9ff;
				border-color: #cce5ff;
			}
			.wpss-notification-time {
				font-size: 12px;
				color: #999;
			}
		</style>
		<?php
	}

	/**
	 * Render vendor dashboard.
	 *
	 * @return void
	 */
	private function render_vendor_dashboard(): void {
		if ( ! wpss_is_vendor() ) {
			echo '<p>' . esc_html__( 'You are not registered as a vendor.', 'wp-sell-services' ) . '</p>';
			return;
		}

		$vendor = wpss_get_vendor( get_current_user_id() );
		$stats  = $vendor ? $vendor->get_stats() : array();
		?>
		<h2><?php esc_html_e( 'Vendor Dashboard', 'wp-sell-services' ); ?></h2>

		<div class="wpss-dashboard-stats">
			<div class="wpss-stat-card">
				<span class="wpss-stat-number"><?php echo esc_html( $stats['total_orders'] ?? 0 ); ?></span>
				<span class="wpss-stat-label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
			</div>
			<div class="wpss-stat-card">
				<span class="wpss-stat-number"><?php echo esc_html( $stats['active_orders'] ?? 0 ); ?></span>
				<span class="wpss-stat-label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></span>
			</div>
			<div class="wpss-stat-card">
				<span class="wpss-stat-number"><?php echo esc_html( wpss_format_price( $stats['total_earnings'] ?? 0 ) ); ?></span>
				<span class="wpss-stat-label"><?php esc_html_e( 'Total Earnings', 'wp-sell-services' ); ?></span>
			</div>
			<div class="wpss-stat-card">
				<span class="wpss-stat-number"><?php echo esc_html( number_format( $stats['average_rating'] ?? 0, 1 ) ); ?></span>
				<span class="wpss-stat-label"><?php esc_html_e( 'Average Rating', 'wp-sell-services' ); ?></span>
			</div>
		</div>

		<h3><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h3>
		<?php
		$orders = wpss_get_vendor_orders( get_current_user_id(), 5 );
		if ( ! empty( $orders ) ) {
			$this->render_vendor_orders_table( $orders );
		} else {
			echo '<p>' . esc_html__( 'No orders yet.', 'wp-sell-services' ) . '</p>';
		}
	}

	/**
	 * Render vendor orders table.
	 *
	 * @param array $orders Orders array.
	 * @return void
	 */
	private function render_vendor_orders_table( array $orders ): void {
		?>
		<table class="wpss-orders-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td>#<?php echo esc_html( $order->order_number ); ?></td>
						<?php
					$buyer_user = get_userdata( $order->customer_id );
					$service_post = get_post( $order->service_id );
					?>
						<td><?php echo esc_html( $buyer_user ? $buyer_user->display_name : 'N/A' ); ?></td>
						<td><?php echo esc_html( $service_post ? $service_post->post_title : 'N/A' ); ?></td>
						<td>
							<span class="wpss-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
								<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $order->delivery_deadline ) : ?>
								<?php echo esc_html( wp_date( 'M j, Y', strtotime( $order->delivery_deadline ) ) ); ?>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render vendor services page.
	 *
	 * @return void
	 */
	private function render_vendor_services(): void {
		if ( ! wpss_is_vendor() ) {
			echo '<p>' . esc_html__( 'You are not registered as a vendor.', 'wp-sell-services' ) . '</p>';
			return;
		}

		$services = wpss_get_vendor_services( get_current_user_id() );
		?>
		<h2><?php esc_html_e( 'My Services', 'wp-sell-services' ); ?></h2>

		<p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add New Service', 'wp-sell-services' ); ?>
			</a>
		</p>

		<?php if ( empty( $services ) ) : ?>
			<p><?php esc_html_e( 'You have not created any services yet.', 'wp-sell-services' ); ?></p>
		<?php else : ?>
			<div class="wpss-services-grid">
				<?php foreach ( $services as $service ) : ?>
					<div class="wpss-service-card">
						<?php if ( $service->thumbnail_id ) : ?>
							<img src="<?php echo esc_url( $service->get_thumbnail_url( 'medium' ) ); ?>" alt="">
						<?php endif; ?>
						<div class="wpss-service-card-content">
							<h3><?php echo esc_html( $service->title ); ?></h3>
							<p class="wpss-service-price">
								<?php esc_html_e( 'From', 'wp-sell-services' ); ?>
								<?php echo esc_html( wpss_format_price( $service->get_starting_price() ) ); ?>
							</p>
							<div class="wpss-service-actions">
								<a href="<?php echo esc_url( get_edit_post_link( $service->id ) ); ?>" class="button">
									<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
								</a>
								<a href="<?php echo esc_url( get_permalink( $service->id ) ); ?>" class="button" target="_blank">
									<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
								</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<style>
			.wpss-services-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
				gap: 20px;
				margin-top: 20px;
			}
			.wpss-service-card {
				border: 1px solid #eee;
				border-radius: 8px;
				overflow: hidden;
			}
			.wpss-service-card img {
				width: 100%;
				height: 150px;
				object-fit: cover;
			}
			.wpss-service-card-content {
				padding: 15px;
			}
			.wpss-service-card h3 {
				margin: 0 0 10px;
				font-size: 16px;
			}
			.wpss-service-price {
				color: #1e3a5f;
				font-weight: 600;
			}
			.wpss-service-actions {
				margin-top: 15px;
				display: flex;
				gap: 10px;
			}
		</style>
		<?php
	}
}
