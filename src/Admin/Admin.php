<?php
/**
 * Admin Class
 *
 * @package WPSellServices\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

use WPSellServices\Admin\Metaboxes\ServiceMetabox;
use WPSellServices\Admin\Metaboxes\BuyerRequestMetabox;
use WPSellServices\Admin\Metaboxes\OrderMetabox;
use WPSellServices\Admin\Pages\ManualOrderPage;
use WPSellServices\Admin\Pages\VendorsPage;
use WPSellServices\Admin\Pages\ServiceModerationPage;
use WPSellServices\Admin\Pages\WithdrawalsPage;
use WPSellServices\Admin\Tables\OrdersListTable;
use WPSellServices\Admin\Tables\DisputesListTable;

/**
 * Handles all admin-side functionality.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Manual order page instance.
	 *
	 * @var ManualOrderPage
	 */
	private ManualOrderPage $manual_order_page;

	/**
	 * Vendors page instance.
	 *
	 * @var VendorsPage
	 */
	private VendorsPage $vendors_page;

	/**
	 * Service moderation page instance.
	 *
	 * @var ServiceModerationPage
	 */
	private ServiceModerationPage $moderation_page;

	/**
	 * Withdrawals page instance.
	 *
	 * @var WithdrawalsPage
	 */
	private WithdrawalsPage $withdrawals_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings          = new Settings();
		$this->manual_order_page = new ManualOrderPage();
		$this->vendors_page      = new VendorsPage();
		$this->moderation_page   = new ServiceModerationPage();
		$this->withdrawals_page  = new WithdrawalsPage();
		$this->init_metaboxes();
		$this->init_pages();
		$this->init_ajax_handlers();
		$this->init_menu_highlights();
	}

	/**
	 * Initialize menu highlight filters.
	 *
	 * @return void
	 */
	private function init_menu_highlights(): void {
		add_filter( 'parent_file', array( $this, 'set_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'set_submenu_file' ) );
		add_action( 'admin_menu', array( $this, 'reorder_admin_submenu' ), 999 );
	}

	/**
	 * Reorder admin submenu items.
	 *
	 * @return void
	 */
	public function reorder_admin_submenu(): void {
		global $submenu;

		if ( ! isset( $submenu['wp-sell-services'] ) ) {
			return;
		}

		$menu_slug = 'wp-sell-services';
		$ordered   = array();
		$rest      = array();

		// Define the desired order of menu slugs.
		$order = array(
			'wp-sell-services',                                              // Dashboard.
			'edit.php?post_type=wpss_service',                               // All Services.
			'post-new.php?post_type=wpss_service',                           // Add New Service.
			'wpss-moderation',                                               // Service Moderation.
			'edit-tags.php?taxonomy=wpss_service_category&post_type=wpss_service', // Categories.
			'edit-tags.php?taxonomy=wpss_service_tag&post_type=wpss_service',      // Tags.
			'edit.php?post_type=wpss_request',                               // All Requests.
			'post-new.php?post_type=wpss_request',                           // Add New Request.
			'wpss-orders',                                                   // Orders.
			'wpss-vendors',                                                  // Vendors.
			'wpss-withdrawals',                                              // Withdrawals.
			'wpss-disputes',                                                 // Disputes.
			'wpss-settings',                                                 // Settings.
		);

		// Build a map of slug => menu item.
		$menu_map = array();
		foreach ( $submenu[ $menu_slug ] as $item ) {
			$menu_map[ $item[2] ] = $item;
		}

		// Add items in the desired order.
		foreach ( $order as $slug ) {
			if ( isset( $menu_map[ $slug ] ) ) {
				$ordered[] = $menu_map[ $slug ];
				unset( $menu_map[ $slug ] );
			}
		}

		// Add any remaining items not in the order array.
		foreach ( $menu_map as $item ) {
			$ordered[] = $item;
		}

		$submenu[ $menu_slug ] = $ordered;
	}

	/**
	 * Set the parent menu for CPT pages.
	 *
	 * @param string $parent_file The parent file.
	 * @return string
	 */
	public function set_parent_menu( string $parent_file ): string {
		global $current_screen;

		if ( ! $current_screen ) {
			return $parent_file;
		}

		// Set parent menu for Service and Buyer Request CPTs.
		if ( in_array( $current_screen->post_type, array( 'wpss_service', 'wpss_request' ), true ) ) {
			return 'wp-sell-services';
		}

		// Set parent menu for Service taxonomy.
		if ( 'wpss_service_category' === $current_screen->taxonomy || 'wpss_service_tag' === $current_screen->taxonomy ) {
			return 'wp-sell-services';
		}

		return $parent_file;
	}

	/**
	 * Set the submenu file for CPT pages.
	 *
	 * @param string|null $submenu_file The submenu file.
	 * @return string|null
	 */
	public function set_submenu_file( ?string $submenu_file ): ?string {
		global $current_screen;

		if ( ! $current_screen ) {
			return $submenu_file;
		}

		// Highlight correct submenu for Service CPT.
		if ( 'wpss_service' === $current_screen->post_type ) {
			if ( 'edit' === $current_screen->base ) {
				return 'edit.php?post_type=wpss_service';
			}
			if ( 'post' === $current_screen->base ) {
				return 'edit.php?post_type=wpss_service';
			}
		}

		// Highlight correct submenu for Buyer Request CPT.
		if ( 'wpss_request' === $current_screen->post_type ) {
			return 'edit.php?post_type=wpss_request';
		}

		// Highlight correct submenu for Service Category taxonomy.
		if ( 'wpss_service_category' === $current_screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=wpss_service_category&post_type=wpss_service';
		}

		// Highlight correct submenu for Service Tag taxonomy.
		if ( 'wpss_service_tag' === $current_screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=wpss_service_tag&post_type=wpss_service';
		}

		return $submenu_file;
	}

	/**
	 * Initialize metaboxes.
	 *
	 * @return void
	 */
	private function init_metaboxes(): void {
		$service_metabox = new ServiceMetabox();
		$service_metabox->init();

		$request_metabox = new BuyerRequestMetabox();
		$request_metabox->init();

		$order_metabox = new OrderMetabox();
		$order_metabox->init();
	}

	/**
	 * Initialize admin pages.
	 *
	 * @return void
	 */
	private function init_pages(): void {
		$this->manual_order_page->init();
		$this->vendors_page->init();
		$this->moderation_page->init();
		$this->withdrawals_page->init();
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	private function init_ajax_handlers(): void {
		add_action( 'wp_ajax_wpss_get_service_packages', array( $this, 'ajax_get_service_packages' ) );
	}

	/**
	 * AJAX handler to get service packages.
	 *
	 * @return void
	 */
	public function ajax_get_service_packages(): void {
		check_ajax_referer( 'wpss_create_manual_order', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$service_id = absint( $_POST['service_id'] ?? 0 );

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service ID.', 'wp-sell-services' ) ) );
		}

		$packages = wpss_get_service_packages( $service_id );

		$formatted_packages = array();
		foreach ( $packages as $package ) {
			$formatted_packages[] = array(
				'id'              => $package['id'] ?? 0,
				'name'            => $package['name'] ?? __( 'Standard', 'wp-sell-services' ),
				'price'           => (float) ( $package['price'] ?? 0 ),
				'formatted_price' => wpss_format_price( (float) ( $package['price'] ?? 0 ) ),
				'delivery_days'   => (int) ( $package['delivery_days'] ?? 7 ),
				'revisions'       => (int) ( $package['revisions'] ?? 0 ),
			);
		}

		wp_send_json_success( array( 'packages' => $formatted_packages ) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		global $post_type;

		// Load on plugin pages or CPT edit screens.
		$load_assets = $this->is_plugin_page( $hook )
			|| ( $post_type && in_array( $post_type, array( 'wpss_service', 'wpss_request' ), true ) );

		if ( ! $load_assets ) {
			return;
		}

		wp_enqueue_style(
			'wpss-admin',
			\WPSS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			\WPSS_VERSION
		);

		// Color picker for category taxonomy.
		if ( strpos( $hook, 'wpss_service_category' ) !== false ) {
			wp_enqueue_style( 'wp-color-picker' );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		global $post_type;

		// Load on plugin pages or CPT edit screens.
		$load_assets = $this->is_plugin_page( $hook )
			|| ( $post_type && in_array( $post_type, array( 'wpss_service', 'wpss_request' ), true ) );

		if ( ! $load_assets ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'wpss-admin',
			\WPSS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
			\WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-admin',
			'wpssAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_admin_nonce' ),
				'i18n'    => array(
					'selectImage'  => __( 'Select Image', 'wp-sell-services' ),
					'selectImages' => __( 'Select Images', 'wp-sell-services' ),
					'useImage'     => __( 'Use Image', 'wp-sell-services' ),
					'confirm'      => __( 'Are you sure?', 'wp-sell-services' ),
				),
			)
		);

		// Color picker for category taxonomy.
		if ( strpos( $hook, 'wpss_service_category' ) !== false ) {
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'WP Sell Services', 'wp-sell-services' ),
			__( 'Sell Services', 'wp-sell-services' ),
			'manage_options',
			'wp-sell-services',
			array( $this, 'render_dashboard_page' ),
			'dashicons-store',
			30
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Dashboard', 'wp-sell-services' ),
			__( 'Dashboard', 'wp-sell-services' ),
			'manage_options',
			'wp-sell-services',
			array( $this, 'render_dashboard_page' )
		);

		// Note: Services CPT menus (All Services, Add New) are automatically added
		// since show_in_menu is set to 'wp-sell-services' in ServicePostType.

		// Add taxonomy submenu (not auto-added when using show_in_menu).
		add_submenu_page(
			'wp-sell-services',
			__( 'Service Categories', 'wp-sell-services' ),
			__( 'Categories', 'wp-sell-services' ),
			'manage_categories',
			'edit-tags.php?taxonomy=wpss_service_category&post_type=wpss_service'
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Service Tags', 'wp-sell-services' ),
			__( 'Tags', 'wp-sell-services' ),
			'manage_categories',
			'edit-tags.php?taxonomy=wpss_service_tag&post_type=wpss_service'
		);

		// Note: Buyer Requests CPT menus are automatically added
		// since show_in_menu is set to 'wp-sell-services' in BuyerRequestPostType.

		add_submenu_page(
			'wp-sell-services',
			__( 'Orders', 'wp-sell-services' ),
			__( 'Orders', 'wp-sell-services' ),
			'wpss_manage_orders',
			'wpss-orders',
			array( $this, 'render_orders_page' )
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Disputes', 'wp-sell-services' ),
			__( 'Disputes', 'wp-sell-services' ),
			'wpss_manage_disputes',
			'wpss-disputes',
			array( $this, 'render_disputes_page' )
		);

		add_submenu_page(
			'wp-sell-services',
			__( 'Settings', 'wp-sell-services' ),
			__( 'Settings', 'wp-sell-services' ),
			'wpss_manage_settings',
			'wpss-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$this->settings->init();
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return bool
	 */
	private function is_plugin_page( string $hook ): bool {
		$plugin_pages = array(
			'toplevel_page_wp-sell-services',
			'sell-services_page_wpss-orders',
			'sell-services_page_wpss-vendors',
			'sell-services_page_wpss-withdrawals',
			'sell-services_page_wpss-moderation',
			'sell-services_page_wpss-disputes',
			'sell-services_page_wpss-settings',
		);

		return in_array( $hook, $plugin_pages, true );
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		global $wpdb;

		// Get stats.
		$orders_table   = $wpdb->prefix . 'wpss_orders';
		$services_count = wp_count_posts( 'wpss_service' );
		$requests_count = wp_count_posts( 'wpss_request' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status IN ('pending_payment', 'pending_requirements') THEN 1 ELSE 0 END) as pending
			FROM {$orders_table}"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revenue = $wpdb->get_var(
			"SELECT SUM(total) FROM {$orders_table} WHERE status = 'completed'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent_orders = $wpdb->get_results(
			"SELECT * FROM {$orders_table} ORDER BY created_at DESC LIMIT 5"
		);
		?>
		<div class="wrap wpss-dashboard-wrap">
			<h1><?php esc_html_e( 'WP Sell Services Dashboard', 'wp-sell-services' ); ?></h1>

			<div class="wpss-dashboard-grid">
				<!-- Stats Cards -->
				<div class="wpss-stats-row">
					<div class="wpss-stat-card">
						<span class="wpss-stat-icon dashicons dashicons-cart"></span>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( $order_stats->total ?? 0 ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
						</div>
					</div>

					<div class="wpss-stat-card">
						<span class="wpss-stat-icon dashicons dashicons-clock" style="color: #dba617;"></span>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( $order_stats->in_progress ?? 0 ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'In Progress', 'wp-sell-services' ); ?></span>
						</div>
					</div>

					<div class="wpss-stat-card">
						<span class="wpss-stat-icon dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( $order_stats->completed ?? 0 ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
						</div>
					</div>

					<div class="wpss-stat-card">
						<span class="wpss-stat-icon dashicons dashicons-money-alt" style="color: #1dbf73;"></span>
						<div class="wpss-stat-info">
							<span class="wpss-stat-number"><?php echo esc_html( wpss_format_price( (float) ( $revenue ?? 0 ) ) ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Total Revenue', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>

				<div class="wpss-dashboard-columns">
					<!-- Quick Actions -->
					<div class="wpss-dashboard-box">
						<h2><?php esc_html_e( 'Quick Actions', 'wp-sell-services' ); ?></h2>
						<div class="wpss-quick-actions">
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="wpss-action-btn">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php esc_html_e( 'Add Service', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders' ) ); ?>" class="wpss-action-btn">
								<span class="dashicons dashicons-list-view"></span>
								<?php esc_html_e( 'View Orders', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service' ) ); ?>" class="wpss-action-btn">
								<span class="dashicons dashicons-admin-tools"></span>
								<?php esc_html_e( 'Manage Services', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings' ) ); ?>" class="wpss-action-btn">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Settings', 'wp-sell-services' ); ?>
							</a>
						</div>
					</div>

					<!-- Content Stats -->
					<div class="wpss-dashboard-box">
						<h2><?php esc_html_e( 'Content Overview', 'wp-sell-services' ); ?></h2>
						<ul class="wpss-content-stats">
							<li>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service' ) ); ?>">
									<span class="count"><?php echo esc_html( $services_count->publish ?? 0 ); ?></span>
									<?php esc_html_e( 'Published Services', 'wp-sell-services' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service&post_status=draft' ) ); ?>">
									<span class="count"><?php echo esc_html( $services_count->draft ?? 0 ); ?></span>
									<?php esc_html_e( 'Draft Services', 'wp-sell-services' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_request' ) ); ?>">
									<span class="count"><?php echo esc_html( $requests_count->publish ?? 0 ); ?></span>
									<?php esc_html_e( 'Buyer Requests', 'wp-sell-services' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&status=pending_payment' ) ); ?>">
									<span class="count"><?php echo esc_html( $order_stats->pending ?? 0 ); ?></span>
									<?php esc_html_e( 'Pending Orders', 'wp-sell-services' ); ?>
								</a>
							</li>
						</ul>
					</div>
				</div>

				<!-- Recent Orders -->
				<div class="wpss-dashboard-box wpss-recent-orders">
					<h2><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h2>
					<?php if ( ! empty( $recent_orders ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_orders as $order ) : ?>
									<?php $service = get_post( $order->service_id ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $order->id ) ); ?>">
												#<?php echo esc_html( $order->order_number ); ?>
											</a>
										</td>
										<td><?php echo esc_html( $service ? $service->post_title : __( 'Deleted', 'wp-sell-services' ) ); ?></td>
										<td><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></td>
										<td>
											<span class="wpss-status-badge wpss-status-<?php echo esc_attr( str_replace( '_', '-', $order->status ) ); ?>">
												<?php echo esc_html( ucwords( str_replace( '_', ' ', $order->status ) ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( wp_date( 'M j, Y', strtotime( $order->created_at ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="wpss-no-data"><?php esc_html_e( 'No orders yet.', 'wp-sell-services' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render orders page.
	 *
	 * @return void
	 */
	public function render_orders_page(): void {
		$list_table = new OrdersListTable();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-create-order' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Create Test Order', 'wp-sell-services' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $list_table->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="wpss-orders">
				<?php
				$list_table->search_box( __( 'Search Orders', 'wp-sell-services' ), 'order' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render disputes page.
	 *
	 * @return void
	 */
	public function render_disputes_page(): void {
		// Check if viewing a specific dispute.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dispute_id = isset( $_GET['dispute_id'] ) ? absint( $_GET['dispute_id'] ) : 0;

		if ( 'view' === $action && $dispute_id ) {
			$this->render_dispute_detail( $dispute_id );
			return;
		}

		$list_table = new DisputesListTable();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Disputes', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<?php $list_table->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="wpss-disputes">
				<?php
				$list_table->search_box( __( 'Search Disputes', 'wp-sell-services' ), 'dispute' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render dispute detail view.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return void
	 */
	private function render_dispute_detail( int $dispute_id ): void {
		global $wpdb;
		$disputes_table = $wpdb->prefix . 'wpss_disputes';
		$messages_table = $wpdb->prefix . 'wpss_dispute_messages';
		$orders_table   = $wpdb->prefix . 'wpss_orders';

		// Get dispute.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dispute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$disputes_table} WHERE id = %d",
				$dispute_id
			)
		);

		if ( ! $dispute ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Dispute not found.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		// Get related order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} WHERE id = %d",
				$dispute->order_id
			)
		);

		// Get dispute messages.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$messages_table} WHERE dispute_id = %d ORDER BY created_at ASC",
				$dispute_id
			)
		);

		$opened_by = get_userdata( $dispute->opened_by );
		$vendor    = $order ? get_userdata( $order->vendor_id ) : null;
		$customer  = $order ? get_userdata( $order->customer_id ) : null;

		$statuses = array(
			'open'           => __( 'Open', 'wp-sell-services' ),
			'pending_review' => __( 'Pending Review', 'wp-sell-services' ),
			'resolved'       => __( 'Resolved', 'wp-sell-services' ),
			'escalated'      => __( 'Escalated', 'wp-sell-services' ),
			'closed'         => __( 'Closed', 'wp-sell-services' ),
		);

		$resolutions = array(
			'full_refund'      => __( 'Full Refund to Buyer', 'wp-sell-services' ),
			'partial_refund'   => __( 'Partial Refund', 'wp-sell-services' ),
			'favor_vendor'     => __( 'Release Payment to Vendor', 'wp-sell-services' ),
			'favor_buyer'      => __( 'Full Refund to Buyer', 'wp-sell-services' ),
			'mutual_agreement' => __( 'Mutual Agreement', 'wp-sell-services' ),
		);

		$reasons = array(
			'quality'       => __( 'Quality Issues', 'wp-sell-services' ),
			'delivery'      => __( 'Late Delivery', 'wp-sell-services' ),
			'communication' => __( 'Communication Issues', 'wp-sell-services' ),
			'not_delivered' => __( 'Not Delivered', 'wp-sell-services' ),
			'other'         => __( 'Other', 'wp-sell-services' ),
		);
		?>
		<div class="wrap wpss-dispute-detail">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %d: dispute ID */
					esc_html__( 'Dispute #%d', 'wp-sell-services' ),
					$dispute_id
				);
				?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-disputes' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Back to Disputes', 'wp-sell-services' ); ?>
			</a>
			<hr class="wp-header-end">

			<div class="wpss-dispute-layout" style="display: flex; gap: 20px; margin-top: 20px;">
				<div class="wpss-dispute-main" style="flex: 2;">
					<!-- Dispute Info -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Dispute Details', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<td>
										<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $dispute->status ); ?>">
											<?php echo esc_html( $statuses[ $dispute->status ] ?? $dispute->status ); ?>
										</span>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></th>
									<td><?php echo esc_html( $reasons[ $dispute->reason ] ?? $dispute->reason ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Opened By', 'wp-sell-services' ); ?></th>
									<td>
										<?php if ( $opened_by ) : ?>
											<a href="<?php echo esc_url( get_edit_user_link( $opened_by->ID ) ); ?>">
												<?php echo esc_html( $opened_by->display_name ); ?>
											</a>
										<?php else : ?>
											<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
									<td>
										<?php if ( $order ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $order->id ) ); ?>">
												#<?php echo esc_html( $order->order_number ); ?>
											</a>
										<?php else : ?>
											<em><?php esc_html_e( 'Deleted', 'wp-sell-services' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Date Opened', 'wp-sell-services' ); ?></th>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dispute->created_at ) ) ); ?></td>
								</tr>
								<?php if ( ! empty( $dispute->description ) ) : ?>
									<tr>
										<th><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></th>
										<td><?php echo wp_kses_post( wpautop( $dispute->description ) ); ?></td>
									</tr>
								<?php endif; ?>
							</table>
						</div>
					</div>

					<!-- Messages -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Messages', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<?php if ( ! empty( $messages ) ) : ?>
								<div class="wpss-dispute-messages" style="max-height: 400px; overflow-y: auto;">
									<?php foreach ( $messages as $message ) : ?>
										<?php $msg_user = get_userdata( $message->user_id ); ?>
										<div class="wpss-message" style="padding: 10px; margin-bottom: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">
											<div style="margin-bottom: 5px;">
												<strong><?php echo esc_html( $msg_user ? $msg_user->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?></strong>
												<span style="color: #666; margin-left: 10px;">
													<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
												</span>
											</div>
											<div><?php echo wp_kses_post( wpautop( $message->message ) ); ?></div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p><?php esc_html_e( 'No messages yet.', 'wp-sell-services' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="wpss-dispute-sidebar" style="flex: 1;">
					<!-- Parties -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Parties Involved', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<p>
								<strong><?php esc_html_e( 'Buyer:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $customer ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $customer->ID ) ); ?>">
										<?php echo esc_html( $customer->display_name ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Vendor:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $vendor ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">
										<?php echo esc_html( $vendor->display_name ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
							<?php if ( $order ) : ?>
								<p>
									<strong><?php esc_html_e( 'Order Value:', 'wp-sell-services' ); ?></strong><br>
									<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Resolution Actions -->
					<?php if ( ! in_array( $dispute->status, array( 'resolved', 'closed' ), true ) ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Resolution', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'wpss_resolve_dispute', 'wpss_dispute_nonce' ); ?>
									<input type="hidden" name="action" value="wpss_resolve_dispute">
									<input type="hidden" name="dispute_id" value="<?php echo esc_attr( $dispute_id ); ?>">

									<p>
										<label for="dispute_status"><strong><?php esc_html_e( 'Update Status:', 'wp-sell-services' ); ?></strong></label><br>
										<select name="dispute_status" id="dispute_status" style="width: 100%;">
											<?php foreach ( $statuses as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $dispute->status, $value ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</p>

									<p>
										<label for="resolution"><strong><?php esc_html_e( 'Resolution:', 'wp-sell-services' ); ?></strong></label><br>
										<select name="resolution" id="resolution" style="width: 100%;">
											<option value=""><?php esc_html_e( '— Select Resolution —', 'wp-sell-services' ); ?></option>
											<?php foreach ( $resolutions as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $dispute->resolution ?? '', $value ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</p>

									<p>
										<label for="admin_notes"><strong><?php esc_html_e( 'Admin Notes:', 'wp-sell-services' ); ?></strong></label><br>
										<textarea name="admin_notes" id="admin_notes" rows="4" style="width: 100%;"><?php echo esc_textarea( $dispute->admin_notes ?? '' ); ?></textarea>
									</p>

									<?php submit_button( __( 'Update Dispute', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
								</form>
							</div>
						</div>
					<?php else : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Resolution', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<p>
									<strong><?php esc_html_e( 'Resolution:', 'wp-sell-services' ); ?></strong><br>
									<?php echo esc_html( $resolutions[ $dispute->resolution ?? '' ] ?? __( 'N/A', 'wp-sell-services' ) ); ?>
								</p>
								<?php if ( ! empty( $dispute->admin_notes ) ) : ?>
									<p>
										<strong><?php esc_html_e( 'Admin Notes:', 'wp-sell-services' ); ?></strong><br>
										<?php echo wp_kses_post( wpautop( $dispute->admin_notes ) ); ?>
									</p>
								<?php endif; ?>
								<?php if ( ! empty( $dispute->resolved_at ) ) : ?>
									<p>
										<strong><?php esc_html_e( 'Resolved At:', 'wp-sell-services' ); ?></strong><br>
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dispute->resolved_at ) ) ); ?>
									</p>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$this->settings->render();
	}

	/**
	 * Get settings instance.
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}
}
