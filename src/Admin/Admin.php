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
use WPSellServices\Admin\Pages\UpgradePage;
use WPSellServices\Admin\Tables\OrdersListTable;
use WPSellServices\Admin\Tables\DisputesListTable;
use WPSellServices\Models\Dispute;
use WPSellServices\Services\DisputeService;

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
	 * Upgrade page instance (only when Pro is not active).
	 *
	 * @var UpgradePage|null
	 */
	private ?UpgradePage $upgrade_page = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings          = new Settings();
		$this->manual_order_page = new ManualOrderPage();
		$this->vendors_page      = new VendorsPage();
		$this->moderation_page   = new ServiceModerationPage();
		$this->withdrawals_page  = new WithdrawalsPage();

		if ( ! $this->is_pro_active() ) {
			$this->upgrade_page = new UpgradePage();
		}

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
			'edit.php?post_type=wpss_request',                               // Buyer Requests.
			'post-new.php?post_type=wpss_request',                           // Add New Request.
			'wpss-orders',                                                   // Orders.
			'wpss-subscriptions',                                            // Subscriptions (Pro).
			'wpss-vendors',                                                  // Vendors.
			'wpss-withdrawals',                                              // Withdrawals.
			'wpss-disputes',                                                 // Disputes.
			'wpss-analytics',                                                // Analytics (Pro).
			'wpss-settings',                                                 // Settings.
			'wpss-license',                                                  // License (Pro).
			'wpss-upgrade',                                                  // Upgrade to Pro (free only).
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

		if ( $this->upgrade_page ) {
			$this->upgrade_page->init();
		}
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	private function init_ajax_handlers(): void {
		add_action( 'wp_ajax_wpss_get_service_packages', array( $this, 'ajax_get_service_packages' ) );
		add_action( 'wp_ajax_wpss_import_demo_content', array( $this, 'ajax_import_demo_content' ) );
		add_action( 'wp_ajax_wpss_delete_demo_content', array( $this, 'ajax_delete_demo_content' ) );
		add_action( 'admin_post_wpss_update_order', array( $this, 'handle_update_order' ) );
		add_action( 'admin_post_wpss_resolve_dispute', array( $this, 'handle_resolve_dispute' ) );
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
		foreach ( $packages as $index => $package ) {
			$formatted_packages[] = array(
				'id'              => $index,
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
	 * Handle order status update from admin-post.php.
	 *
	 * @return void
	 */
	public function handle_update_order(): void {
		// Verify nonce.
		if ( ! isset( $_POST['wpss_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_order_nonce'] ) ), 'wpss_update_order' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['order_status'] ) ? sanitize_key( $_POST['order_status'] ) : '';

		if ( ! $order_id || ! $status ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// Valid statuses.
		$valid_statuses = array(
			'pending_payment',
			'pending_requirements',
			'in_progress',
			'delivered',
			'revision_requested',
			'completed',
			'cancelled',
			'disputed',
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_die( esc_html__( 'Invalid status.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		// Update the order.
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		// Get current status before update.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$orders_table} WHERE id = %d",
				$order_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$orders_table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wpss_log( "Failed to update order {$order_id} status: " . $wpdb->last_error, 'error' );
		}

		// Fire status change hook for notifications (only when rows actually changed).
		if ( $updated && $old_status !== $status ) {
			/**
			 * Fires when order status changes via admin.
			 *
			 * @param int    $order_id   Order ID.
			 * @param string $status     New status.
			 * @param string $old_status Previous status.
			 */
			do_action( 'wpss_order_status_changed', $order_id, $status, $old_status );
		}

		// Redirect back to the order. Distinguish DB error (false) from no-change (0).
		if ( false === $updated ) {
			$update_status = 'error';
		} elseif ( 0 === $updated ) {
			$update_status = 'unchanged';
		} else {
			$update_status = '1';
		}

		$redirect_url = add_query_arg(
			array(
				'page'     => 'wpss-orders',
				'action'   => 'view',
				'order_id' => $order_id,
				'updated'  => $update_status,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle dispute resolution form submission.
	 *
	 * @return void
	 */
	public function handle_resolve_dispute(): void {
		if ( ! isset( $_POST['wpss_dispute_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_dispute_nonce'] ) ), 'wpss_resolve_dispute' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		$dispute_id = isset( $_POST['dispute_id'] ) ? absint( $_POST['dispute_id'] ) : 0;
		$status     = isset( $_POST['dispute_status'] ) ? sanitize_key( $_POST['dispute_status'] ) : '';
		$resolution = isset( $_POST['resolution'] ) ? sanitize_key( $_POST['resolution'] ) : '';
		$notes      = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';

		if ( ! $dispute_id || ! $status ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-sell-services' ), '', array( 'back_link' => true ) );
		}

		$dispute_service = new DisputeService();

		if ( 'resolved' === $status && $resolution ) {
			$result = $dispute_service->resolve( $dispute_id, $resolution, $notes, get_current_user_id() );
		} else {
			$result = $dispute_service->update_status( $dispute_id, $status, $notes );
		}

		$updated = ( false !== $result && ! is_wp_error( $result ) ) ? '1' : '0';

		$redirect_url = add_query_arg(
			array(
				'page'       => 'wpss-disputes',
				'action'     => 'view',
				'dispute_id' => $dispute_id,
				'updated'    => $updated,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		global $post_type;

		$current_screen = get_current_screen();
		$is_taxonomy    = $current_screen && in_array( $current_screen->taxonomy, array( 'wpss_service_category', 'wpss_service_tag' ), true );

		// Load on plugin pages, CPT edit screens, or taxonomy screens.
		$load_assets = $this->is_plugin_page( $hook )
			|| ( $post_type && in_array( $post_type, array( 'wpss_service', 'wpss_request' ), true ) )
			|| $is_taxonomy;

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
		if ( $is_taxonomy ) {
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

		$current_screen = get_current_screen();
		$is_taxonomy    = $current_screen && in_array( $current_screen->taxonomy, array( 'wpss_service_category', 'wpss_service_tag' ), true );

		// Load on plugin pages, CPT edit screens, or taxonomy screens.
		$load_assets = $this->is_plugin_page( $hook )
			|| ( $post_type && in_array( $post_type, array( 'wpss_service', 'wpss_request' ), true ) )
			|| $is_taxonomy;

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
		if ( $is_taxonomy ) {
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		/**
		 * Filter the admin menu label for white-labelling.
		 *
		 * @since 1.1.0
		 *
		 * @param string $label The menu label.
		 */
		$menu_label = apply_filters( 'wpss_admin_menu_label', __( 'Sell Services', 'wp-sell-services' ) );

		add_menu_page(
			__( 'WP Sell Services', 'wp-sell-services' ),
			$menu_label,
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
			'admin_page_wpss-create-order',
			'sell-services_page_wpss-upgrade',
		);

		return in_array( $hook, $plugin_pages, true );
	}

	/**
	 * Check if the Pro plugin is active.
	 *
	 * @return bool
	 */
	private function is_pro_active(): bool {
		return defined( 'WPSS_PRO_VERSION' );
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
		// Check if viewing a specific order.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( 'view' === $action && $order_id ) {
			$this->render_order_detail( $order_id );
			return;
		}

		// Handle bulk actions.
		$this->process_order_bulk_actions( $action );

		$list_table = new OrdersListTable();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-create-order' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Create Order', 'wp-sell-services' ); ?>
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
	 * Process order bulk actions.
	 *
	 * @param string $action The bulk action.
	 * @return void
	 */
	private function process_order_bulk_actions( string $action ): void {
		$bulk_actions = array( 'mark_completed', 'mark_cancelled' );

		if ( ! in_array( $action, $bulk_actions, true ) ) {
			return;
		}

		check_admin_referer( 'bulk-orders' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$order_ids = isset( $_GET['order_ids'] ) ? array_map( 'absint', (array) $_GET['order_ids'] ) : array();

		if ( empty( $order_ids ) ) {
			return;
		}

		$status_map = array(
			'mark_completed' => 'completed',
			'mark_cancelled' => 'cancelled',
		);

		$new_status = $status_map[ $action ];
		$updated    = 0;

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		foreach ( $order_ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$old_status = $wpdb->get_var(
				$wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id )
			);

			if ( $old_status === $new_status ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				array( 'status' => $new_status ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( $result ) {
				++$updated;

				/**
				 * Fires after an order status is changed via bulk action.
				 *
				 * @since 1.0.0
				 *
				 * @param int    $id         Order ID.
				 * @param string $new_status New status.
				 * @param string $old_status Previous status.
				 */
				do_action( 'wpss_order_status_changed', $id, $new_status, $old_status );
			}
		}

		if ( $updated > 0 ) {
			add_action(
				'admin_notices',
				function () use ( $updated ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						/* translators: %d: number of orders updated */
						esc_html( sprintf( _n( '%d order updated.', '%d orders updated.', $updated, 'wp-sell-services' ), $updated ) )
					);
				}
			);
		}
	}

	/**
	 * Render order detail view.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function render_order_detail( int $order_id ): void {
		global $wpdb;
		$orders_table        = $wpdb->prefix . 'wpss_orders';
		$conversations_table = $wpdb->prefix . 'wpss_conversations';
		$deliveries_table    = $wpdb->prefix . 'wpss_deliveries';

		// Get order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		// Vendor access check — vendors can only view their own orders.
		if ( ! current_user_can( 'manage_options' ) && (int) $order->vendor_id !== get_current_user_id() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this order.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		// Get service.
		$service = get_post( $order->service_id );
		$vendor  = get_userdata( $order->vendor_id );
		$buyer   = get_userdata( $order->customer_id );

		// Get messages via the messages table joined with conversations.
		$messages_table = $wpdb->prefix . 'wpss_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.* FROM {$messages_table} m
				 INNER JOIN {$conversations_table} c ON m.conversation_id = c.id
				 WHERE c.order_id = %d
				 ORDER BY m.created_at ASC",
				$order_id
			)
		);

		// Get deliveries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deliveries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$deliveries_table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		$statuses = array(
			'pending_payment'      => __( 'Pending Payment', 'wp-sell-services' ),
			'pending_requirements' => __( 'Waiting for Requirements', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'             => __( 'Disputed', 'wp-sell-services' ),
		);
		?>
		<div class="wrap wpss-order-detail">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order #%s', 'wp-sell-services' ),
					esc_html( $order->order_number )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Back to Orders', 'wp-sell-services' ); ?>
			</a>
			<hr class="wp-header-end">

			<div class="wpss-order-layout" style="display: flex; gap: 20px; margin-top: 20px;">
				<div class="wpss-order-main" style="flex: 2;">
					<!-- Order Info -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<td>
										<span class="wpss-status-badge wpss-status-<?php echo esc_attr( str_replace( '_', '-', $order->status ) ); ?>">
											<?php echo esc_html( $statuses[ $order->status ] ?? ucwords( str_replace( '_', ' ', $order->status ) ) ); ?>
										</span>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
									<td>
										<?php if ( $service ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $service->ID ) ); ?>">
												<?php echo esc_html( $service->post_title ); ?>
											</a>
										<?php else : ?>
											<em><?php esc_html_e( 'Deleted', 'wp-sell-services' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
								<?php if ( ! empty( $order->package_name ) ) : ?>
									<tr>
										<th><?php esc_html_e( 'Package', 'wp-sell-services' ); ?></th>
										<td><?php echo esc_html( $order->package_name ); ?></td>
									</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
									<td><strong><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></strong></td>
								</tr>
								<?php if ( $order->delivery_deadline ) : ?>
									<tr>
										<th><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></th>
										<td><?php
										$deadline_timestamp = $order->delivery_deadline instanceof \DateTimeInterface
											? $order->delivery_deadline->getTimestamp()
											: strtotime( $order->delivery_deadline );
										echo esc_html( wp_date( get_option( 'date_format' ), $deadline_timestamp ) );
										?></td>
									</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Created', 'wp-sell-services' ); ?></th>
									<td><?php
									if ( $order->created_at ) {
										$created_timestamp = $order->created_at instanceof \DateTimeInterface
											? $order->created_at->getTimestamp()
											: strtotime( $order->created_at );
										echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_timestamp ) );
									}
									?></td>
								</tr>
								<?php if ( ! empty( $order->platform_order_id ) ) : ?>
									<tr>
										<th><?php esc_html_e( 'WooCommerce Order', 'wp-sell-services' ); ?></th>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->platform_order_id ) ); ?>">
												#<?php echo esc_html( $order->platform_order_id ); ?>
											</a>
										</td>
									</tr>
								<?php endif; ?>
							</table>
						</div>
					</div>

					<!-- Requirements -->
					<?php if ( ! empty( $order->requirements ) ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Requirements', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<?php
								$requirements = maybe_unserialize( $order->requirements );
								if ( is_array( $requirements ) ) {
									echo '<dl>';
									foreach ( $requirements as $key => $value ) {
										echo '<dt><strong>' . esc_html( $key ) . '</strong></dt>';
										echo '<dd>' . esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ) . '</dd>';
									}
									echo '</dl>';
								} else {
									echo wp_kses_post( wpautop( $requirements ) );
								}
								?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Messages -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Messages', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<?php if ( ! empty( $messages ) ) : ?>
								<div class="wpss-order-messages" style="max-height: 400px; overflow-y: auto;">
									<?php foreach ( $messages as $message ) : ?>
										<?php $msg_user = isset( $message->sender_id ) ? get_userdata( $message->sender_id ) : null; ?>
										<div class="wpss-message" style="padding: 10px; margin-bottom: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">
											<div style="margin-bottom: 5px;">
												<strong><?php echo esc_html( $msg_user ? $msg_user->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?></strong>
												<span style="color: #666; margin-left: 10px;">
													<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
												</span>
											</div>
											<div><?php echo wp_kses_post( wpautop( $message->content ?? '' ) ); ?></div>
											<?php if ( ! empty( $message->attachments ) ) : ?>
												<div style="margin-top: 10px; color: #666;">
													<span class="dashicons dashicons-paperclip"></span>
													<?php esc_html_e( 'Has attachments', 'wp-sell-services' ); ?>
												</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p><?php esc_html_e( 'No messages yet.', 'wp-sell-services' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Deliveries -->
					<?php if ( ! empty( $deliveries ) ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Deliveries', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<?php foreach ( $deliveries as $delivery ) : ?>
									<div class="wpss-delivery" style="padding: 10px; margin-bottom: 10px; background: #f0f9f0; border-left: 3px solid #00a32a;">
										<div style="margin-bottom: 5px;">
											<strong>
												<?php
												printf(
													/* translators: %d: delivery number */
													esc_html__( 'Delivery #%d', 'wp-sell-services' ),
													$delivery->id
												);
												?>
											</strong>
											<span style="color: #666; margin-left: 10px;">
												<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $delivery->created_at ) ) ); ?>
											</span>
										</div>
										<?php if ( ! empty( $delivery->message ) ) : ?>
											<div><?php echo wp_kses_post( wpautop( $delivery->message ) ); ?></div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="wpss-order-sidebar" style="flex: 1;">
					<!-- Parties -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Parties', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<p>
								<strong><?php esc_html_e( 'Buyer:', 'wp-sell-services' ); ?></strong><br>
								<?php if ( $buyer ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $buyer->ID ) ); ?>">
										<?php echo esc_html( $buyer->display_name ); ?>
									</a>
									<br><small><?php echo esc_html( $buyer->user_email ); ?></small>
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
									<br><small><?php echo esc_html( $vendor->user_email ); ?></small>
								<?php else : ?>
									<em><?php esc_html_e( 'Unknown', 'wp-sell-services' ); ?></em>
								<?php endif; ?>
							</p>
						</div>
					</div>

					<!-- Update Status -->
					<?php if ( ! in_array( $order->status, array( 'completed', 'cancelled' ), true ) ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Update Order', 'wp-sell-services' ); ?></h2>
							<div class="inside">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'wpss_update_order', 'wpss_order_nonce' ); ?>
									<input type="hidden" name="action" value="wpss_update_order">
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

									<p>
										<label for="order_status"><strong><?php esc_html_e( 'Status:', 'wp-sell-services' ); ?></strong></label><br>
										<select name="order_status" id="order_status" style="width: 100%;">
											<?php foreach ( $statuses as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $order->status, $value ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</p>

									<?php submit_button( __( 'Update Status', 'wp-sell-services' ), 'primary', 'submit', false ); ?>
								</form>
							</div>
						</div>
					<?php endif; ?>

					<!-- Financial Summary -->
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Financial Summary', 'wp-sell-services' ); ?></h2>
						<div class="inside">
							<table style="width: 100%;">
								<tr>
									<td><?php esc_html_e( 'Order Total:', 'wp-sell-services' ); ?></td>
									<td style="text-align: right;"><strong><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></strong></td>
								</tr>
								<?php if ( isset( $order->vendor_earnings ) && $order->vendor_earnings > 0 ) : ?>
									<tr>
										<td><?php esc_html_e( 'Vendor Earning:', 'wp-sell-services' ); ?></td>
										<td style="text-align: right;"><?php echo esc_html( wpss_format_price( (float) $order->vendor_earnings, $order->currency ) ); ?></td>
									</tr>
								<?php endif; ?>
								<?php if ( isset( $order->platform_fee ) && $order->platform_fee > 0 ) : ?>
									<tr>
										<td><?php esc_html_e( 'Commission:', 'wp-sell-services' ); ?></td>
										<td style="text-align: right;"><?php echo esc_html( wpss_format_price( (float) $order->platform_fee, $order->currency ) ); ?></td>
									</tr>
								<?php endif; ?>
							</table>
						</div>
					</div>
				</div>
			</div>
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

		$initiated_by = get_userdata( $dispute->initiated_by );
		$vendor    = $order ? get_userdata( $order->vendor_id ) : null;
		$customer  = $order ? get_userdata( $order->customer_id ) : null;

		$statuses = array(
			'open'           => __( 'Open', 'wp-sell-services' ),
			'pending_review' => __( 'Pending Review', 'wp-sell-services' ),
			'resolved'       => __( 'Resolved', 'wp-sell-services' ),
			'escalated'      => __( 'Escalated', 'wp-sell-services' ),
			'closed'         => __( 'Closed', 'wp-sell-services' ),
		);

		$resolutions = DisputeService::get_resolution_types();

		$reasons = Dispute::get_reasons();
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
										<?php if ( $initiated_by ) : ?>
											<a href="<?php echo esc_url( get_edit_user_link( $initiated_by->ID ) ); ?>">
												<?php echo esc_html( $initiated_by->display_name ); ?>
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
										<?php $msg_user = get_userdata( $message->sender_id ); ?>
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

	/**
	 * AJAX handler to import demo content.
	 *
	 * Creates demo services, categories, and vendor profiles.
	 *
	 * @return void
	 */
	public function ajax_import_demo_content(): void {
		check_ajax_referer( 'wpss_demo_content', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$cli_file = WPSS_PLUGIN_DIR . 'src/CLI/ServiceCommands.php';
		if ( ! file_exists( $cli_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Demo content module not found.', 'wp-sell-services' ) ) );
		}

		// Use the CLI service templates directly.
		require_once $cli_file;
		$commands = new \WPSellServices\CLI\ServiceCommands();

		// Use reflection to access the private templates and create_service method.
		$ref_class  = new \ReflectionClass( $commands );
		$ref_templates = $ref_class->getProperty( 'service_templates' );
		$ref_templates->setAccessible( true );
		$templates = $ref_templates->getValue( $commands );

		$ref_create = $ref_class->getMethod( 'create_service' );
		$ref_create->setAccessible( true );

		$ref_variation = $ref_class->getMethod( 'apply_variation' );
		$ref_variation->setAccessible( true );

		// Create categories first.
		$categories = array_unique( array_column( $templates, 'category' ) );
		foreach ( $categories as $cat_name ) {
			if ( ! term_exists( $cat_name, 'wpss_service_category' ) ) {
				wp_insert_term( $cat_name, 'wpss_service_category' );
			}
		}

		// Create 20 services (cycling through templates).
		$count   = 20;
		$created = 0;
		$featured = 0;
		$template_count = count( $templates );

		for ( $i = 0; $i < $count; $i++ ) {
			$template  = $templates[ $i % $template_count ];
			$variation = (int) floor( $i / $template_count );

			$service_data = $ref_variation->invoke( $commands, $template, $variation );

			// Mark some as featured.
			if ( $featured < 5 && ( $i % 4 === 0 || ! empty( $template['featured'] ) ) ) {
				$service_data['featured'] = true;
				++$featured;
			}

			$result = $ref_create->invoke( $commands, $service_data );
			if ( ! is_wp_error( $result ) ) {
				// Mark as demo content for easy cleanup.
				update_post_meta( $result, '_wpss_demo_content', 1 );
				++$created;
			}
		}

		// Create demo vendor profiles.
		$vendors_created = $this->create_demo_vendors();

		update_option( 'wpss_demo_content_imported', true );

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: 1: services count, 2: categories count, 3: vendors count */
					__( 'Imported %1$d services, %2$d categories, and %3$d vendor profiles.', 'wp-sell-services' ),
					$created,
					count( $categories ),
					$vendors_created
				),
				'services' => $created,
				'categories' => count( $categories ),
				'vendors'  => $vendors_created,
			)
		);
	}

	/**
	 * Create demo vendor profiles.
	 *
	 * @return int Number of vendors created.
	 */
	private function create_demo_vendors(): int {
		$vendors = array(
			array(
				'login'    => 'sarah_designer',
				'email'    => 'sarah@demo.test',
				'name'     => 'Sarah Chen',
				'tagline'  => 'Top Rated Logo & Brand Designer',
				'bio'      => 'Award-winning designer with 8+ years creating memorable brand identities. Specializing in minimalist logos and complete branding packages.',
				'country'  => 'US',
			),
			array(
				'login'    => 'mike_developer',
				'email'    => 'mike@demo.test',
				'name'     => 'Mike Rodriguez',
				'tagline'  => 'Full-Stack WordPress Developer',
				'bio'      => 'WordPress developer building custom themes, plugins, and e-commerce solutions. Clean code, fast delivery.',
				'country'  => 'CA',
			),
			array(
				'login'    => 'emma_writer',
				'email'    => 'emma@demo.test',
				'name'     => 'Emma Williams',
				'tagline'  => 'SEO Content Writer & Strategist',
				'bio'      => 'Published writer creating SEO-optimized content that ranks. Specializing in tech, SaaS, and marketing niches.',
				'country'  => 'GB',
			),
			array(
				'login'    => 'alex_marketer',
				'email'    => 'alex@demo.test',
				'name'     => 'Alex Kim',
				'tagline'  => 'Digital Marketing Specialist',
				'bio'      => 'Google Ads certified specialist helping businesses grow through data-driven campaigns and SEO strategies.',
				'country'  => 'AU',
			),
		);

		$created = 0;

		foreach ( $vendors as $vendor_data ) {
			// Skip if user exists.
			if ( username_exists( $vendor_data['login'] ) ) {
				continue;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $vendor_data['login'],
					'user_email'   => $vendor_data['email'],
					'user_pass'    => wp_generate_password(),
					'display_name' => $vendor_data['name'],
					'role'         => 'wpss_vendor',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			// Mark as demo user.
			update_user_meta( $user_id, '_wpss_demo_content', 1 );
			update_user_meta( $user_id, '_wpss_is_vendor', true );

			// Create vendor profile in DB.
			global $wpdb;
			$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$profiles_table,
				array(
					'user_id'      => $user_id,
					'display_name' => $vendor_data['name'],
					'tagline'      => $vendor_data['tagline'],
					'bio'          => $vendor_data['bio'],
					'status'       => 'active',
					'country'      => $vendor_data['country'],
					'is_available' => 1,
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			// Assign some services to this vendor.
			$services = get_posts(
				array(
					'post_type'      => 'wpss_service',
					'posts_per_page' => 3,
					'orderby'        => 'rand',
					'meta_key'       => '_wpss_demo_content',
					'meta_value'     => '1',
					'author'         => 0, // Only unassigned.
					'fields'         => 'ids',
				)
			);

			foreach ( $services as $service_id ) {
				wp_update_post(
					array(
						'ID'          => $service_id,
						'post_author' => $user_id,
					)
				);
			}

			++$created;
		}

		return $created;
	}

	/**
	 * AJAX handler to delete demo content.
	 *
	 * @return void
	 */
	public function ajax_delete_demo_content(): void {
		check_ajax_referer( 'wpss_demo_content', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// Delete demo services.
		$demo_services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_wpss_demo_content',
				'meta_value'     => '1',
				'fields'         => 'ids',
			)
		);

		$services_deleted = 0;
		foreach ( $demo_services as $post_id ) {
			if ( wp_delete_post( $post_id, true ) ) {
				++$services_deleted;
			}
		}

		// Delete demo vendor users.
		$demo_users = get_users(
			array(
				'meta_key'   => '_wpss_demo_content',
				'meta_value' => '1',
				'fields'     => 'ids',
			)
		);

		global $wpdb;
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
		$vendors_deleted = 0;

		foreach ( $demo_users as $user_id ) {
			// Remove vendor profile.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $profiles_table, array( 'user_id' => $user_id ), array( '%d' ) );

			if ( wp_delete_user( $user_id ) ) {
				++$vendors_deleted;
			}
		}

		// Clean up empty demo categories.
		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		$cats_deleted = 0;
		if ( is_array( $categories ) ) {
			foreach ( $categories as $term_id ) {
				$term = get_term( $term_id, 'wpss_service_category' );
				if ( $term && 0 === $term->count ) {
					wp_delete_term( $term_id, 'wpss_service_category' );
					++$cats_deleted;
				}
			}
		}

		delete_option( 'wpss_demo_content_imported' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: services count, 2: vendors count */
					__( 'Deleted %1$d demo services and %2$d demo vendors.', 'wp-sell-services' ),
					$services_deleted,
					$vendors_deleted
				),
			)
		);
	}
}
