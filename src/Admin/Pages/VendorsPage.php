<?php
/**
 * Vendors Management Page
 *
 * Admin page for managing vendor profiles and statistics.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Services\CommissionService;
use WPSellServices\Services\SellerLevelService;
use WPSellServices\Services\VendorService;
use WPSellServices\Models\VendorProfile;

defined( 'ABSPATH' ) || exit;

/**
 * Vendors Page Class.
 *
 * @since 1.0.0
 */
class VendorsPage {

	/**
	 * Vendor profile repository.
	 *
	 * @var VendorProfileRepository
	 */
	private VendorProfileRepository $vendor_repo;

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Commission service.
	 *
	 * @var CommissionService
	 */
	private CommissionService $commission_service;

	/**
	 * Vendor service.
	 *
	 * @var VendorService
	 */
	private VendorService $vendor_service;

	/**
	 * Seller level service.
	 *
	 * @var SellerLevelService
	 */
	private SellerLevelService $seller_level_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_repo          = new VendorProfileRepository();
		$this->order_repo           = new OrderRepository();
		$this->commission_service   = new CommissionService();
		$this->vendor_service       = new VendorService();
		$this->seller_level_service = new SellerLevelService();
	}

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
		add_action( 'wp_ajax_wpss_update_vendor_status', array( $this, 'ajax_update_vendor_status' ) );
		add_action( 'wp_ajax_wpss_bulk_update_vendor_status', array( $this, 'ajax_bulk_update_vendor_status' ) );
		add_action( 'wp_ajax_wpss_get_vendor_details', array( $this, 'ajax_get_vendor_details' ) );
		add_action( 'wp_ajax_wpss_update_vendor_commission', array( $this, 'ajax_update_vendor_commission' ) );
		add_action( 'wp_ajax_wpss_vendor_tab_content', array( $this, 'ajax_get_tab_content' ) );
		add_action( 'wp_ajax_wpss_update_vendor_vacation', array( $this, 'ajax_update_vendor_vacation' ) );
		add_action( 'wp_ajax_wpss_update_vendor_availability', array( $this, 'ajax_update_vendor_availability' ) );
		add_action( 'wp_ajax_wpss_update_vendor_level', array( $this, 'ajax_update_vendor_level' ) );
		add_action( 'wp_ajax_wpss_moderate_portfolio_item', array( $this, 'ajax_moderate_portfolio_item' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'Vendors', 'wp-sell-services' ),
			__( 'Vendors', 'wp-sell-services' ),
			'manage_options',
			'wpss-vendors',
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'add_help_tabs' ) );
		}
	}

	/**
	 * Register screen help tabs.
	 *
	 * @return void
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-overview',
				'title'   => __( 'Overview', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Vendors are the site users who can list and sell services on your marketplace. This screen shows every vendor with their active services, lifetime orders, supported contract types (catalog vs. buyer-request milestones), average rating, and total earnings. Use the status filters to focus on active, pending, or suspended vendors.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-actions',
				'title'   => __( 'Available actions', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Click any vendor to open their detail drawer with tabs for services, orders, earnings, reviews, and settings. From there you can approve or suspend the account, adjust the per-vendor commission override, toggle vacation mode, and audit their full order history. New vendors usually arrive via the front-end /become-a-vendor registration page.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'wp-sell-services' ) . '</strong></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/" target="_blank" rel="noopener">' . esc_html__( 'Plugin docs', 'wp-sell-services' ) . '</a></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/vendor-registration-wpss" target="_blank" rel="noopener">' . esc_html__( 'Vendor registration guide', 'wp-sell-services' ) . '</a></p>'
		);
	}

	/**
	 * Enqueue page scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'sell-services_page_wpss-vendors' !== $hook ) {
			return;
		}

		// Enqueue free plugin admin styles with unique handle to avoid conflicts.
		wp_enqueue_style(
			'wpss-free-admin',
			\WPSS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			\WPSS_VERSION
		);

		// Enqueue free plugin admin scripts with unique handle.
		wp_enqueue_script(
			'wpss-free-admin',
			\WPSS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
			\WPSS_VERSION,
			true
		);

		// wpss-ui provides wpssConfirm() for the portfolio-item delete confirm.
		wp_enqueue_script(
			'wpss-admin-vendors',
			\WPSS_PLUGIN_URL . 'assets/js/admin-vendors.js',
			array( 'jquery', 'wpss-free-admin', 'wpss-ui' ),
			\WPSS_VERSION,
			true
		);
		wp_set_script_translations( 'wpss-admin-vendors', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

		$this->localize_vendors_script();
	}

	/**
	 * Localise the vendors admin script.
	 *
	 * Split out so render_vendor_detail() can call it again with the specific
	 * vendor id merged in — the detail drawer's AJAX needs it, and it is the
	 * one genuinely per-render value in an otherwise static config.
	 *
	 * @since 1.5.1
	 *
	 * @param int $vendor_id Vendor being viewed, or 0 on the list screen.
	 * @return void
	 */
	private function localize_vendors_script( int $vendor_id = 0 ): void {
		wp_localize_script(
			'wpss-admin-vendors',
			'wpssVendors',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpss_vendors_admin' ),
				'vendorId' => $vendor_id,
				'i18n'     => array(
					'selectAtLeastOneVendorFirst'        => __( 'Select at least one vendor first.', 'wp-sell-services' ),
					'approve'                            => __( 'Approve', 'wp-sell-services' ),
					'suspend'                            => __( 'Suspend', 'wp-sell-services' ),
					'reactivate'                         => __( 'Reactivate', 'wp-sell-services' ),
					/* translators: 1: bulk action label, 2: number of vendors. */
					'bulkConfirm'                        => __( '%1$s %2$d vendor(s)? This applies to every selected row.', 'wp-sell-services' ),
					'confirmStatusChange'                => __( 'Are you sure you want to change this vendor\'s status?', 'wp-sell-services' ),
					'areYouSureYouWantToChangeThisVendorSStatus' => __( 'Are you sure you want to change this vendor\'s status?', 'wp-sell-services' ),
					'pleaseEnterACommissionRate'         => __( 'Please enter a commission rate.', 'wp-sell-services' ),
					'resetThisVendorsCommission'         => __( 'Reset this vendor\'s commission rate to the global rate?', 'wp-sell-services' ),
					'resetToGlobalCommissionRate'        => __( 'Reset to global commission rate?', 'wp-sell-services' ),
					'anErrorOccurredPleaseTryAgain'      => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'failedToLoadContent'                => __( 'Failed to load content.', 'wp-sell-services' ),
					'errorUpdatingCommissionRate'        => __( 'Error updating commission rate.', 'wp-sell-services' ),
					'errorResettingCommissionRate'       => __( 'Error resetting commission rate.', 'wp-sell-services' ),
					'errorUpdatingVacationMode'          => __( 'Error updating vacation mode.', 'wp-sell-services' ),
					'errorUpdatingAvailability'          => __( 'Error updating availability.', 'wp-sell-services' ),
					'errorUpdatingSellerLevel'           => __( 'Error updating seller level.', 'wp-sell-services' ),
					'errorModeratingPortfolioItem'       => __( 'Error moderating portfolio item.', 'wp-sell-services' ),
					'permanentlyRemoveThisPortfolioItem' => __( 'Permanently remove this portfolio item? This cannot be undone.', 'wp-sell-services' ),
					'loading'                            => __( 'Loading...', 'wp-sell-services' ),
					'loadingVendorDetails'               => __( 'Loading vendor details...', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Get vendors with stats.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function get_vendors( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Build query.
		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'vp.status = %s';
			$values[] = $args['status'];
		}

		if ( $args['search'] ) {
			$where[]  = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
		}

		$where_clause = implode( ' AND ', $where );

		// Count total.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_clause is built from hardcoded fragments with %s placeholders only; user values pass through prepare() below.
		$count_query = "
			SELECT COUNT(DISTINCT vp.user_id)
			FROM {$wpdb->prefix}wpss_vendor_profiles vp
			LEFT JOIN {$wpdb->users} u ON vp.user_id = u.ID
			WHERE {$where_clause}
		";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_query has hardcoded fragments only.
		$total = $values
			? (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) )
			: (int) $wpdb->get_var( $count_query );
		// phpcs:enable

		// Get vendors with stats.
		$orderby_map = array(
			'created_at'      => 'vp.created_at',
			'display_name'    => 'u.display_name',
			'rating'          => 'vp.avg_rating',
			'total_orders'    => 'vp.total_orders',
			'total_earned'    => 'ledger_earned',
			'milestone_count' => 'milestone_count',
		);

		$orderby = $orderby_map[ $args['orderby'] ] ?? 'vp.created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Contract-type breakdown columns.
		//
		// Fixed    = completed non-sub-order rows belonging to this vendor.
		// Milestone = completed request-orders (parents) that have at least
		// one milestone child row; correlated sub-query keeps the
		// read-only audit trail intact and avoids joining the
		// orders table twice.
		$tip_platform       = \WPSellServices\Services\TippingService::ORDER_TYPE;
		$extension_platform = \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE;
		$milestone_platform = \WPSellServices\Services\MilestoneService::ORDER_TYPE;
		$orders_table       = $wpdb->prefix . 'wpss_orders';
		$wallet_table       = $wpdb->prefix . 'wpss_wallet_transactions';

		// Lifetime earnings, read from the wallet ledger (the money authority),
		// NOT the denormalised vp.total_earnings which the list must never be
		// able to contradict the vendor's own dashboard with. "Earned" = the sum
		// of completed CREDIT rows (debit types — withdrawals, payouts, refund
		// reversals — are excluded). One correlated sub-query per row, indexed on
		// wallet_transactions.user_id, exactly like services_count above — no
		// per-row PHP call to get_summary(), which would N+1 at 500 vendors.
		// $debit_types_sql values are sanitize_key()'d in the helper, safe to
		// interpolate into the IN () list.
		$debit_types_sql   = wpss_get_ledger_debit_types_sql();
		$ledger_earned_sql = "(SELECT COALESCE( SUM( wt.amount ), 0 )
			FROM {$wallet_table} wt
			WHERE wt.user_id = vp.user_id
			AND wt.status = 'completed'
			AND wt.type NOT IN ({$debit_types_sql}))";

		$fixed_count_sql = $wpdb->prepare(
			"(SELECT COUNT(*) FROM {$orders_table} o
			WHERE o.vendor_id = vp.user_id
			AND o.status = 'completed'
			AND o.platform NOT IN (%s, %s, %s, %s))",
			$tip_platform,
			$extension_platform,
			$milestone_platform,
			'request'
		);

		$milestone_count_sql = $wpdb->prepare(
			"(SELECT COUNT(*) FROM {$orders_table} o
			WHERE o.vendor_id = vp.user_id
			AND o.status = 'completed'
			AND o.platform = %s
			AND EXISTS (
				SELECT 1 FROM {$orders_table} c
				WHERE c.platform = %s
				AND c.platform_order_id = o.id
			))",
			'request',
			$milestone_platform
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed/milestone SQL fragments are pre-prepared above.
		$query = $wpdb->prepare(
			"SELECT
				vp.*,
				u.display_name,
				u.user_email,
				u.user_registered,
				(SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_author = vp.user_id AND p.post_type = 'wpss_service' AND p.post_status = 'publish') as services_count,
				{$fixed_count_sql} as fixed_count,
				{$milestone_count_sql} as milestone_count,
				{$ledger_earned_sql} as ledger_earned
			FROM {$wpdb->prefix}wpss_vendor_profiles vp
			LEFT JOIN {$wpdb->users} u ON vp.user_id = u.ID
			WHERE {$where_clause}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d",
			array_merge( $values, array( $args['per_page'], $offset ) )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query pre-prepared, table names from $wpdb->prefix are safe.
		$vendors = $wpdb->get_results( $query );

		return array(
			'vendors' => $vendors,
			'total'   => $total,
			'pages'   => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get vendor statistics summary.
	 *
	 * @return array
	 */
	private function get_vendor_stats(): array {
		global $wpdb;

		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_vendors,
				SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vendors,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_vendors,
				SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_vendors,
				AVG(avg_rating) as avg_rating
			FROM {$wpdb->prefix}wpss_vendor_profiles"
		);

		// Marketplace lifetime earnings from the ledger authority (completed
		// credits, debit types excluded) so the summary card and the per-vendor
		// "Earned" column are the same number on the same screen. One aggregate
		// query — no per-vendor loop. Scoped to current vendor_profiles so the
		// total equals the sum of the listed column and never counts ledger rows
		// for user_ids that are no longer vendors (former vendors, buyers who
		// received a credit, orphaned rows).
		$debit_types_sql = wpss_get_ledger_debit_types_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- debit types are sanitize_key()'d; no user input.
		$total_earned = (float) $wpdb->get_var(
			"SELECT COALESCE( SUM( wt.amount ), 0 )
			FROM {$wpdb->prefix}wpss_wallet_transactions wt
			WHERE wt.status = 'completed'
			AND wt.type NOT IN ({$debit_types_sql})
			AND wt.user_id IN ( SELECT user_id FROM {$wpdb->prefix}wpss_vendor_profiles )"
		);

		return array(
			'total'          => (int) ( $stats->total_vendors ?? 0 ),
			'active'         => (int) ( $stats->active_vendors ?? 0 ),
			'pending'        => (int) ( $stats->pending_vendors ?? 0 ),
			'suspended'      => (int) ( $stats->suspended_vendors ?? 0 ),
			'avg_rating'     => round( (float) ( $stats->avg_rating ?? 0 ), 2 ),
			'total_earnings' => $total_earned,
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- admin list table pagination/sorting/filtering via URL params.
		// Route to vendor detail view if action=view and vendor_id is set.
		$action    = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$vendor_id = isset( $_GET['vendor_id'] ) ? absint( $_GET['vendor_id'] ) : 0;

		if ( 'view' === $action && $vendor_id ) {
			$this->render_vendor_detail( $vendor_id );
			return;
		}

		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$status       = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order        = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = $this->get_vendors(
			array(
				'page'    => $current_page,
				'status'  => $status,
				'search'  => $search,
				'orderby' => $orderby,
				'order'   => $order,
			)
		);

		$vendors     = $result['vendors'];
		$total       = $result['total'];
		$total_pages = $result['pages'];
		$stats       = $this->get_vendor_stats();
		?>
		<div class="wrap wpss-listing-page wpss-vendors-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Vendors', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">
			<?php
			// Surface the last bulk-action report (incl. per-row failures) that
			// the post-action reload would otherwise have discarded.
			$bulk_report_key = 'wpss_bulk_vendor_report_' . get_current_user_id();
			$bulk_report     = get_transient( $bulk_report_key );
			if ( $bulk_report ) {
				delete_transient( $bulk_report_key );
				printf(
					'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
					esc_html( (string) $bulk_report )
				);
			}
			?>

			<?php
			// Two cards, not five. Active / Pending / Suspended were duplicated
			// verbatim as counts in the status filter row immediately below
			// ("All (1) | Active (1) | Pending (0) | Suspended (0)"), so the
			// cards restated what the filters already say — and the filters are
			// the version you can click. Only the two totals that appear nowhere
			// else are kept.
			?>
			<div class="wpss-listing-stats wpss-vendor-stats">
				<div class="wpss-stat-card">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Vendors', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card">
					<span class="wpss-stat-number"><?php echo esc_html( wpss_format_price( $stats['total_earnings'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Earned', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<!-- Filter + content unified card -->
			<div class="wpss-list-card">
				<div class="wpss-list-card__filters wpss-vendors-filters">
					<ul class="subsubsub">
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors' ) ); ?>"
								class="<?php echo $status === '' ? 'current' : ''; ?>">
								<?php esc_html_e( 'All', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['total'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors&status=active' ) ); ?>"
								class="<?php echo $status === 'active' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Active', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['active'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors&status=pending' ) ); ?>"
								class="<?php echo $status === 'pending' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Pending', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['pending'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors&status=suspended' ) ); ?>"
								class="<?php echo $status === 'suspended' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Suspended', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['suspended'] ); ?>)</span>
							</a>
						</li>
					</ul>

					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-box">
						<input type="hidden" name="page" value="wpss-vendors">
						<?php if ( $status ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
						<?php endif; ?>
						<label class="screen-reader-text" for="vendor-search-input">
							<?php esc_html_e( 'Search vendors', 'wp-sell-services' ); ?>
						</label>
						<input type="search" id="vendor-search-input" name="s"
								value="<?php echo esc_attr( $search ); ?>"
								placeholder="<?php esc_attr_e( 'Search vendors...', 'wp-sell-services' ); ?>">
						<input type="submit" id="search-submit" class="button"
								value="<?php esc_attr_e( 'Search', 'wp-sell-services' ); ?>">
					</form>
				</div>

				<div class="wpss-list-card__body">
			<?php if ( empty( $vendors ) ) : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<?php echo \WPSellServices\Services\Icon::render( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No vendors yet', 'wp-sell-services' ); ?></h2>
					<p class="wpss-empty-state__body"><?php esc_html_e( 'Invite site users to register as vendors, or let them self-register via the /become-a-vendor page.', 'wp-sell-services' ); ?></p>
					<p class="wpss-empty-state__actions">
						<a href="<?php echo esc_url( home_url( '/become-a-vendor/' ) ); ?>" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Open vendor registration', 'wp-sell-services' ); ?></a>
						<a href="https://wbcomdesigns.com/docs/wp-sell-services/vendor-registration-wpss" class="wpss-empty-state__learn" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'wp-sell-services' ); ?></a>
					</p>
				</div>
			<?php else : ?>
			<!-- Bulk actions row (mirrors the Service Moderation pattern). -->
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<?php wp_nonce_field( 'wpss_vendors_bulk', 'wpss_vendors_bulk_nonce' ); ?>
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp-sell-services' ); ?></label>
					<select name="bulk_action" id="bulk-action-selector-top" class="wpss-vendors-bulk-select">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-sell-services' ); ?></option>
						<option value="approve"><?php esc_html_e( 'Approve (set Active)', 'wp-sell-services' ); ?></option>
						<option value="suspend"><?php esc_html_e( 'Suspend', 'wp-sell-services' ); ?></option>
						<option value="reactivate"><?php esc_html_e( 'Reactivate', 'wp-sell-services' ); ?></option>
					</select>
					<button type="button" class="button wpss-vendors-bulk-apply"><?php esc_html_e( 'Apply', 'wp-sell-services' ); ?></button>
				</div>
			</div>

			<!-- Vendors Table -->
			<table class="wp-list-table widefat fixed striped wpss-vendors-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-1" aria-label="<?php esc_attr_e( 'Select all vendors', 'wp-sell-services' ); ?>">
						</td>
						<?php
						// Six columns, down from nine. Dropped: Contract Types
						// ("0x Fixed · 0x Milestone" — derived, wide, and almost
						// never what an admin scans for; it lives in the detail
						// drawer). Joined folded under the vendor name, still
						// sortable via its header link. Actions folded into
						// WordPress row-actions on the primary column, which
						// also stops three buttons wrapping onto two lines and
						// inflating every row's height.
						?>
						<th scope="col" class="column-vendor">
							<?php $this->sortable_column_header( 'display_name', __( 'Vendor', 'wp-sell-services' ), $orderby, $order ); ?>
						</th>
						<th scope="col" class="column-services">
							<?php esc_html_e( 'Services', 'wp-sell-services' ); ?>
						</th>
						<th scope="col" class="column-orders">
							<?php $this->sortable_column_header( 'total_orders', __( 'Orders', 'wp-sell-services' ), $orderby, $order ); ?>
						</th>
						<th scope="col" class="column-rating">
							<?php $this->sortable_column_header( 'rating', __( 'Rating', 'wp-sell-services' ), $orderby, $order ); ?>
						</th>
						<th scope="col" class="column-earnings">
							<?php $this->sortable_column_header( 'total_earned', __( 'Earned', 'wp-sell-services' ), $orderby, $order ); ?>
						</th>
						<th scope="col" class="column-status">
							<?php esc_html_e( 'Status', 'wp-sell-services' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $vendors as $vendor ) : ?>
						<?php $this->render_vendor_row( $vendor ); ?>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-2" aria-label="<?php esc_attr_e( 'Select all vendors', 'wp-sell-services' ); ?>">
						</td>
						<th scope="col" class="column-vendor"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-services"><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-orders"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-rating"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-earnings"><?php esc_html_e( 'Earned', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s item', '%s items', $total, 'wp-sell-services' ) ),
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number_format_i18n() is a safe formatting function.
								number_format_i18n( $total )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$pagination_args = array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $current_page,
							);
							echo wp_kses_post( paginate_links( $pagination_args ) );
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
			<?php endif; // vendors empty check. ?>
				</div><!-- .wpss-list-card__body -->
			</div><!-- .wpss-list-card -->
		</div>

		<!-- Vendor Details Modal -->
		<div id="wpss-vendor-modal" class="wpss-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpss-vendor-modal-heading">
			<div class="wpss-modal-content">
				<span class="wpss-modal-close" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</span>
				<div id="wpss-vendor-modal-body">
					<div class="wpss-modal-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading vendor details...', 'wp-sell-services' ); ?>
					</div>
				</div>
			</div>
		</div>


		<?php
	}

	/**
	 * Render sortable column header.
	 *
	 * @param string $column  Column name.
	 * @param string $label   Column label.
	 * @param string $current Current orderby.
	 * @param string $order   Current order.
	 * @return void
	 */
	private function sortable_column_header( string $column, string $label, string $current, string $order ): void {
		$is_sorted   = $current === $column;
		$new_order   = $is_sorted && $order === 'ASC' ? 'DESC' : 'ASC';
		$sort_class  = $is_sorted ? 'sorted ' . strtolower( $order ) : 'sortable asc';
		$arrow_class = $is_sorted ? ( $order === 'ASC' ? 'asc' : 'desc' ) : '';

		$url = add_query_arg(
			array(
				'orderby' => $column,
				'order'   => $new_order,
			)
		);

		printf(
			'<a href="%s" class="%s"><span>%s</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>',
			esc_url( $url ),
			esc_attr( $sort_class ),
			esc_html( $label )
		);
	}

	/**
	 * Render vendor table row.
	 *
	 * @param object $vendor Vendor data.
	 * @return void
	 */
	private function render_vendor_row( object $vendor ): void {
		$user    = get_userdata( (int) $vendor->user_id );
		$avatar  = get_avatar_url( $vendor->user_id, array( 'size' => 80 ) );
		$rating  = (float) ( $vendor->avg_rating ?? 0 );
		$reviews = (int) ( $vendor->total_reviews ?? 0 );
		$status  = $vendor->status ?? 'active';
		?>
		<tr data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>">
			<th scope="row" class="check-column">
				<label class="screen-reader-text" for="wpss-cb-vendor-<?php echo esc_attr( $vendor->user_id ); ?>"><?php esc_html_e( 'Select vendor', 'wp-sell-services' ); ?></label>
				<input type="checkbox" id="wpss-cb-vendor-<?php echo esc_attr( $vendor->user_id ); ?>" name="vendor_ids[]" value="<?php echo esc_attr( $vendor->user_id ); ?>">
			</th>
			<td class="column-vendor">
				<div class="wpss-vendor-info">
					<img src="<?php echo esc_url( $avatar ); ?>" alt="" class="wpss-vendor-avatar">
					<div>
						<div class="wpss-vendor-name">
							<?php echo esc_html( $vendor->display_name ?? $user->display_name ?? '' ); ?>
						</div>
						<div class="wpss-vendor-email">
							<?php echo esc_html( $vendor->user_email ?? $user->user_email ?? '' ); ?>
						</div>
						<?php
						$joined = $vendor->created_at ?? $user->user_registered ?? '';
						if ( $joined ) :
							?>
							<div class="wpss-vendor-joined">
								<?php
								printf(
									/* translators: %s: date the vendor joined. */
									esc_html__( 'Joined %s', 'wp-sell-services' ),
									esc_html( date_i18n( get_option( 'date_format' ), strtotime( $joined ) ) )
								);
								?>
							</div>
						<?php endif; ?>
					</div>
				</div>
				<?php
				// WordPress row-actions: secondary controls appear on hover, so
				// the row stays one line tall instead of carrying three
				// permanently-visible buttons that wrapped.
				?>
				<div class="row-actions">
					<span class="view">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors&action=view&vendor_id=' . $vendor->user_id ) ); ?>"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a> |
					</span>
					<span class="edit">
						<a href="<?php echo esc_url( get_edit_user_link( $vendor->user_id ) ); ?>"><?php esc_html_e( 'Edit User', 'wp-sell-services' ); ?></a>
					</span>
					<?php if ( 'active' === $status ) : ?>
						<span class="trash">
							| <button type="button" class="button-link wpss-change-status"
									data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
									data-status="suspended"><?php esc_html_e( 'Suspend', 'wp-sell-services' ); ?></button>
						</span>
					<?php elseif ( 'suspended' === $status ) : ?>
						<span class="untrash">
							| <button type="button" class="button-link wpss-change-status"
									data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
									data-status="active"><?php esc_html_e( 'Activate', 'wp-sell-services' ); ?></button>
						</span>
					<?php elseif ( 'pending' === $status ) : ?>
						<span class="approve">
							| <button type="button" class="button-link wpss-change-status"
									data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
									data-status="active"><?php esc_html_e( 'Approve', 'wp-sell-services' ); ?></button>
						</span>
						<span class="trash">
							| <button type="button" class="button-link wpss-change-status"
									data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
									data-status="rejected"><?php esc_html_e( 'Reject', 'wp-sell-services' ); ?></button>
						</span>
					<?php endif; ?>
				</div>
			</td>
			<td class="column-services" data-colname="<?php esc_attr_e( 'Services', 'wp-sell-services' ); ?>">
				<?php
				$services_url = admin_url( 'edit.php?post_type=wpss_service&author=' . $vendor->user_id );
				printf(
					'<a href="%s">%d</a>',
					esc_url( $services_url ),
					(int) $vendor->services_count
				);
				?>
			</td>
			<td class="column-orders" data-colname="<?php esc_attr_e( 'Orders', 'wp-sell-services' ); ?>">
				<?php echo esc_html( number_format_i18n( (int) ( $vendor->total_orders ?? 0 ) ) ); ?>
			</td>
			<td class="column-rating" data-colname="<?php esc_attr_e( 'Rating', 'wp-sell-services' ); ?>">
				<?php if ( $reviews > 0 ) : ?>
					<span class="wpss-rating-stars">
						<?php echo esc_html( number_format( $rating, 1 ) ); ?> ★
					</span>
					<span class="wpss-rating-count">
						(<?php echo esc_html( number_format_i18n( $reviews ) ); ?>)
					</span>
				<?php else : ?>
					<span class="wpss-rating-count"><?php esc_html_e( 'No reviews', 'wp-sell-services' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="column-earnings" data-colname="<?php esc_attr_e( 'Earned', 'wp-sell-services' ); ?>">
				<?php echo esc_html( wpss_format_price( (float) ( $vendor->ledger_earned ?? 0 ) ) ); ?>
			</td>
			<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
				<span class="<?php echo esc_attr( wpss_status_class( $status ) ); ?>">
					<?php echo esc_html( ucfirst( $status ) ); ?>
				</span>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the vendor detail page.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_vendor_detail( int $vendor_id ): void {
		global $wpdb;

		// Get vendor profile with user data.
		$vendor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT vp.*, u.display_name, u.user_email, u.user_registered
				FROM {$wpdb->prefix}wpss_vendor_profiles vp
				LEFT JOIN {$wpdb->users} u ON vp.user_id = u.ID
				WHERE vp.user_id = %d",
				$vendor_id
			)
		);

		if ( ! $vendor ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Vendor not found.', 'wp-sell-services' ) . '</p></div></div>';
			return;
		}

		$user       = get_userdata( $vendor_id );
		$avatar_url = get_avatar_url( $vendor_id, array( 'size' => 160 ) );
		$status     = $vendor->status ?? 'active';
		$rating     = (float) ( $vendor->avg_rating ?? 0 );
		$reviews    = (int) ( $vendor->total_reviews ?? 0 );

		// Get services count.
		$services_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'wpss_service' AND post_status = 'publish'",
				$vendor_id
			)
		);

		// Calculate average response time (mock for now, would need message tracking).
		$response_time = __( 'N/A', 'wp-sell-services' );

		// Get wallet balance.
		$wallet_balance = wpss_get_ledger_balance( (int) $vendor_id );
		?>
		<div class="wrap wpss-vendor-detail-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Vendor Details', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">
			<!-- Back link and action buttons -->
			<div class="wpss-detail-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-vendors' ) ); ?>" class="wpss-back-link">
					&larr; <?php esc_html_e( 'Back to Vendors', 'wp-sell-services' ); ?>
				</a>
				<div class="wpss-detail-buttons">
					<a href="<?php echo esc_url( get_edit_user_link( $vendor_id ) ); ?>" class="button">
						<?php esc_html_e( 'Edit User', 'wp-sell-services' ); ?>
					</a>
					<?php if ( function_exists( 'wpss_get_vendor_profile_url' ) ) : ?>
						<a href="<?php echo esc_url( wpss_get_vendor_profile_url( $vendor_id ) ); ?>" class="button" target="_blank">
							<?php esc_html_e( 'View Profile', 'wp-sell-services' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Vendor Header -->
			<div class="wpss-detail-header">
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="wpss-detail-avatar">
				<div class="wpss-detail-info">
					<h2 class="wpss-detail-name"><?php echo esc_html( $vendor->display_name ); ?></h2>
					<p class="wpss-detail-email"><?php echo esc_html( $vendor->user_email ); ?></p>
					<?php if ( ! empty( $vendor->tagline ) ) : ?>
						<p class="wpss-detail-tagline"><?php echo esc_html( $vendor->tagline ); ?></p>
					<?php endif; ?>
				</div>
				<div class="wpss-detail-status-area">
					<div class="wpss-detail-status-row">
						<span class="<?php echo esc_attr( wpss_status_class( $status ) ); ?>">
							<?php echo esc_html( ucfirst( $status ) ); ?>
						</span>
						<select id="wpss-vendor-status-select" data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>" data-current="<?php echo esc_attr( $status ); ?>">
							<option value=""><?php esc_html_e( 'Change Status...', 'wp-sell-services' ); ?></option>
							<?php if ( $status !== 'active' ) : ?>
								<option value="active"><?php esc_html_e( 'Activate', 'wp-sell-services' ); ?></option>
							<?php endif; ?>
							<?php if ( $status !== 'suspended' ) : ?>
								<option value="suspended"><?php esc_html_e( 'Suspend', 'wp-sell-services' ); ?></option>
							<?php endif; ?>
							<?php if ( $status === 'pending' ) : ?>
								<option value="rejected"><?php esc_html_e( 'Reject', 'wp-sell-services' ); ?></option>
							<?php endif; ?>
						</select>
					</div>
					<p class="wpss-detail-member-since">
						<?php
						printf(
							/* translators: %s: date */
							esc_html__( 'Member since: %s', 'wp-sell-services' ),
							esc_html( date_i18n( get_option( 'date_format' ), strtotime( $vendor->created_at ?? $user->user_registered ) ) )
						);
						?>
					</p>
				</div>
			</div>

			<!-- Stats Cards Row -->
			<div class="wpss-detail-stats-row">
				<div class="wpss-detail-stat-card">
					<span class="wpss-detail-stat-number"><?php echo esc_html( number_format_i18n( $services_count ) ); ?></span>
					<span class="wpss-detail-stat-label"><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-detail-stat-card">
					<span class="wpss-detail-stat-number"><?php echo esc_html( number_format_i18n( (int) ( $vendor->total_orders ?? 0 ) ) ); ?></span>
					<span class="wpss-detail-stat-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-detail-stat-card">
					<span class="wpss-detail-stat-number"><?php echo esc_html( wpss_format_price( (float) $wallet_balance ) ); ?></span>
					<span class="wpss-detail-stat-label"><?php esc_html_e( 'Balance', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-detail-stat-card">
					<span class="wpss-detail-stat-number">
						<?php if ( $reviews > 0 ) : ?>
							<?php echo esc_html( number_format( $rating, 1 ) ); ?> ★
						<?php else : ?>
							-
						<?php endif; ?>
					</span>
					<span class="wpss-detail-stat-label"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?> (<?php echo esc_html( number_format_i18n( $reviews ) ); ?>)</span>
				</div>
				<div class="wpss-detail-stat-card">
					<span class="wpss-detail-stat-number"><?php echo esc_html( $response_time ); ?></span>
					<span class="wpss-detail-stat-label"><?php esc_html_e( 'Response', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<!-- Tab Navigation -->
			<div class="wpss-detail-tabs">
				<button type="button" class="wpss-detail-tab active" data-tab="overview">
					<?php esc_html_e( 'Overview', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="services">
					<?php esc_html_e( 'Services', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="orders">
					<?php esc_html_e( 'Orders', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="earnings">
					<?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="wallet">
					<?php esc_html_e( 'Wallet', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="reviews">
					<?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="portfolio">
					<?php esc_html_e( 'Portfolio', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-detail-tab" data-tab="settings">
					<?php esc_html_e( 'Settings', 'wp-sell-services' ); ?>
				</button>
			</div>

			<!-- Tab Content -->
			<div class="wpss-detail-tab-content" id="wpss-tab-content">
				<div class="wpss-tab-loading">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading...', 'wp-sell-services' ); ?>
				</div>
			</div>
		</div>

		<?php
		// Re-localise with this vendor's id so the detail-drawer AJAX (in
		// admin-vendors.js) has it. The tab loader, commission override,
		// vacation/availability toggles and portfolio moderation all post it.
		$this->localize_vendors_script( $vendor_id );
	}


	/**
	 * AJAX handler for updating vendor status.
	 *
	 * When a vendor is approved (status changed to 'active'), this also grants the
	 * vendor role, capabilities, and _wpss_is_vendor meta. When suspended or rejected,
	 * these are revoked.
	 *
	 * @return void
	 */
	public function ajax_update_vendor_status(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );
		$status    = sanitize_key( $_POST['status'] ?? '' );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		$valid_statuses = array( 'active', 'pending', 'suspended', 'rejected' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'wp-sell-services' ) ) );
		}

		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'wpss_vendor_profiles',
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'user_id' => $vendor_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update vendor status.', 'wp-sell-services' ) ) );
		}

		// Grant or revoke vendor access based on new status.
		if ( 'active' === $status ) {
			$this->vendor_service->grant_vendor_access( $vendor_id );
		} elseif ( in_array( $status, array( 'suspended', 'rejected' ), true ) ) {
			$this->vendor_service->revoke_vendor_access( $vendor_id );
		}

		/**
		 * Fires when vendor status is updated.
		 *
		 * @param int    $vendor_id Vendor user ID.
		 * @param string $status    New status.
		 */
		do_action( 'wpss_vendor_status_updated', $vendor_id, $status );

		wp_send_json_success( array( 'message' => __( 'Vendor status updated successfully.', 'wp-sell-services' ) ) );
	}

	/**
	 * AJAX handler for bulk vendor status updates.
	 *
	 * Admin actions: `approve` (sets pending → active and grants vendor
	 * access), `suspend` (active → suspended and revokes access),
	 * `reactivate` (suspended → active and re-grants access). Routes each
	 * id through the same DB write + grant/revoke + `wpss_vendor_status_updated`
	 * action used by the per-row handler above so side-effects stay
	 * identical. Reports per-id success/failure counts back.
	 *
	 * @return void
	 */
	public function ajax_bulk_update_vendor_status(): void {
		check_ajax_referer( 'wpss_vendors_bulk', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ), 403 );
		}

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IDs cast to int below.
		$ids_raw = isset( $_POST['vendor_ids'] ) ? (array) $_POST['vendor_ids'] : array();
		$ids     = array_values( array_filter( array_map( 'absint', $ids_raw ) ) );

		$status_map = array(
			'approve'    => 'active',
			'reactivate' => 'active',
			'suspend'    => 'suspended',
		);

		if ( ! isset( $status_map[ $bulk_action ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bulk action.', 'wp-sell-services' ) ) );
		}

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No vendors selected.', 'wp-sell-services' ) ) );
		}

		$status  = $status_map[ $bulk_action ];
		$success = 0;
		$failed  = array();

		global $wpdb;
		foreach ( $ids as $vendor_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor profile mutations bypass the object cache by design; mirrors ajax_update_vendor_status().
			$result = $wpdb->update(
				$wpdb->prefix . 'wpss_vendor_profiles',
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'user_id' => $vendor_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $result ) {
				$failed[] = sprintf( '#%d', $vendor_id );
				continue;
			}
			if ( 'active' === $status ) {
				$this->vendor_service->grant_vendor_access( $vendor_id );
			} elseif ( 'suspended' === $status ) {
				$this->vendor_service->revoke_vendor_access( $vendor_id );
			}
			do_action( 'wpss_vendor_status_updated', $vendor_id, $status );
			++$success;
		}

		$message = sprintf(
			/* translators: 1: number of vendors successfully updated, 2: total selected */
			_n(
				'Updated %1$d of %2$d vendor.',
				'Updated %1$d of %2$d vendors.',
				count( $ids ),
				'wp-sell-services'
			),
			$success,
			count( $ids )
		);
		if ( ! empty( $failed ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: comma-separated list of failed vendor IDs */
				__( 'Failed: %s', 'wp-sell-services' ),
				implode( ', ', $failed )
			);
		}

		// Persist the per-row report so it survives the JS success reload, which
		// otherwise discarded this message and hid partial failures from the admin.
		set_transient( 'wpss_bulk_vendor_report_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX handler for getting vendor details.
	 *
	 * @return void
	 */
	public function ajax_get_vendor_details(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		global $wpdb;

		// Get vendor profile.
		$vendor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT vp.*, u.display_name, u.user_email, u.user_registered
				FROM {$wpdb->prefix}wpss_vendor_profiles vp
				LEFT JOIN {$wpdb->users} u ON vp.user_id = u.ID
				WHERE vp.user_id = %d",
				$vendor_id
			)
		);

		if ( ! $vendor ) {
			wp_send_json_error( array( 'message' => __( 'Vendor not found.', 'wp-sell-services' ) ) );
		}

		// Payout-relevant number: current wallet balance ("what do I owe"),
		// read from the ledger authority rather than vp.total_earnings.
		$wallet_balance = wpss_get_ledger_balance( (int) $vendor_id );

		// Get services.
		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'author'         => $vendor_id,
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		// Get recent orders.
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.*, s.post_title as service_title
				FROM {$wpdb->prefix}wpss_orders o
				LEFT JOIN {$wpdb->posts} s ON o.service_id = s.ID
				WHERE o.vendor_id = %d
				ORDER BY o.created_at DESC
				LIMIT 10",
				$vendor_id
			)
		);

		// Build HTML.
		ob_start();
		?>
		<div class="wpss-vendor-details">
			<div class="wpss-vendor-header">
				<?php echo get_avatar( $vendor_id, 80 ); ?>
				<div>
					<h2><?php echo esc_html( $vendor->display_name ); ?></h2>
					<p><?php echo esc_html( $vendor->user_email ); ?></p>
					<span class="<?php echo esc_attr( wpss_status_class( $vendor->status ) ); ?>">
						<?php echo esc_html( ucfirst( $vendor->status ) ); ?>
					</span>
				</div>
			</div>

			<div class="wpss-vendor-stats-grid">
				<div class="wpss-vendor-stat">
					<strong><?php echo esc_html( number_format_i18n( count( $services ) ) ); ?></strong>
					<?php esc_html_e( 'Services', 'wp-sell-services' ); ?>
				</div>
				<div class="wpss-vendor-stat">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $vendor->total_orders ?? 0 ) ) ); ?></strong>
					<?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?>
				</div>
				<div class="wpss-vendor-stat">
					<strong><?php echo esc_html( $vendor->avg_rating ? number_format( (float) $vendor->avg_rating, 1 ) . ' ★' : '-' ); ?></strong>
					<?php esc_html_e( 'Rating', 'wp-sell-services' ); ?>
				</div>
				<div class="wpss-vendor-stat">
					<strong><?php echo esc_html( wpss_format_price( (float) $wallet_balance ) ); ?></strong>
					<?php esc_html_e( 'Balance', 'wp-sell-services' ); ?>
				</div>
			</div>

			<!-- Commission Rate Section -->
			<?php
			$effective_rate = $this->commission_service->get_effective_vendor_rate( $vendor_id );
			$global_rate    = CommissionService::get_global_commission_rate();
			?>
			<div class="wpss-commission-section" style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Commission Rate', 'wp-sell-services' ); ?></h3>
				<p class="description" style="margin-bottom: 15px;">
					<?php
					printf(
						/* translators: %s: global commission rate */
						esc_html__( 'Global commission rate is %s%%. Set a custom rate below to override for this vendor.', 'wp-sell-services' ),
						esc_html( number_format( $global_rate, 1 ) )
					);
					?>
				</p>
				<div style="display: flex; align-items: center; gap: 10px;">
					<label for="wpss-vendor-commission-rate" class="screen-reader-text">
						<?php esc_html_e( 'Commission Rate', 'wp-sell-services' ); ?>
					</label>
					<input type="number" id="wpss-vendor-commission-rate"
							value="<?php echo esc_attr( $effective_rate['is_custom'] ? number_format( $effective_rate['rate'], 2, '.', '' ) : '' ); ?>"
							placeholder="<?php echo esc_attr( number_format( $global_rate, 1 ) ); ?>"
							min="0" max="100" step="0.01"
							style="width: 100px;">
					<span>%</span>
					<button type="button" class="button button-primary" id="wpss-save-commission"
							data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>">
						<?php esc_html_e( 'Save', 'wp-sell-services' ); ?>
					</button>
					<?php if ( $effective_rate['is_custom'] ) : ?>
						<button type="button" class="button" id="wpss-reset-commission"
								data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>">
							<?php esc_html_e( 'Reset to Global', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<p id="wpss-commission-status" style="margin-top: 10px;">
					<?php if ( $effective_rate['is_custom'] ) : ?>
						<span style="color: #2271b1;">
							<?php
							printf(
								/* translators: %s: custom commission rate */
								esc_html__( 'Custom rate: %s%%', 'wp-sell-services' ),
								esc_html( number_format( $effective_rate['rate'], 2 ) )
							);
							?>
						</span>
					<?php else : ?>
						<span style="color: #646970;">
							<?php esc_html_e( 'Using global rate', 'wp-sell-services' ); ?>
						</span>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( $vendor->bio ) : ?>
				<h3><?php esc_html_e( 'Bio', 'wp-sell-services' ); ?></h3>
				<p><?php echo wp_kses_post( $vendor->bio ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $services ) ) : ?>
				<h3><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $services as $service ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $service->ID ) ); ?>">
										<?php echo esc_html( $service->post_title ); ?>
									</a>
								</td>
								<td><?php echo esc_html( ucfirst( $service->post_status ) ); ?></td>
								<td>
									<?php
									$price = get_post_meta( $service->ID, '_wpss_starting_price', true );
									echo $price ? esc_html( wpss_format_price( (float) $price ) ) : '-';
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $orders ) ) : ?>
				<h3><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h3>
				<table class="widefat striped">
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
						<?php foreach ( $orders as $order ) : ?>
							<tr>
								<td><?php echo esc_html( $order->order_number ); ?></td>
								<td><?php echo esc_html( $order->service_title ); ?></td>
								<td><?php echo esc_html( wpss_format_price( (float) $order->total ) ); ?></td>
								<td>
									<span class="<?php echo esc_attr( wpss_status_class( $order->status ) ); ?>">
										<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top: 20px;">
				<a href="<?php echo esc_url( get_edit_user_link( $vendor_id ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Edit User Profile', 'wp-sell-services' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpss_service&author=' . $vendor_id ) ); ?>" class="button">
					<?php esc_html_e( 'View All Services', 'wp-sell-services' ); ?>
				</a>
			</p>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX handler for updating vendor commission rate.
	 *
	 * @return void
	 */
	public function ajax_update_vendor_commission(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		// Check if reset to global was requested.
		$reset = isset( $_POST['reset'] ) && 'true' === $_POST['reset'];

		if ( $reset ) {
			// Reset to global rate.
			$result = $this->commission_service->set_vendor_commission_rate( $vendor_id, null );

			if ( ! $result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to reset commission rate.', 'wp-sell-services' ) ) );
			}

			$global_rate = CommissionService::get_global_commission_rate();

			wp_send_json_success(
				array(
					'message'   => __( 'Commission rate reset to global.', 'wp-sell-services' ),
					'rate'      => $global_rate,
					'is_custom' => false,
				)
			);
		}

		// Set custom rate.
		$rate_input = isset( $_POST['rate'] ) ? sanitize_text_field( wp_unslash( $_POST['rate'] ) ) : '';

		if ( '' === $rate_input ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a commission rate.', 'wp-sell-services' ) ) );
		}

		$rate = (float) $rate_input;

		if ( $rate < 0 || $rate > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Commission rate must be between 0 and 100.', 'wp-sell-services' ) ) );
		}

		$result = $this->commission_service->set_vendor_commission_rate( $vendor_id, $rate );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update commission rate.', 'wp-sell-services' ) ) );
		}

		/**
		 * Fires when vendor commission rate is updated.
		 *
		 * @param int   $vendor_id Vendor user ID.
		 * @param float $rate      New commission rate.
		 */
		do_action( 'wpss_vendor_commission_updated', $vendor_id, $rate );

		wp_send_json_success(
			array(
				'message'   => __( 'Commission rate updated successfully.', 'wp-sell-services' ),
				'rate'      => $rate,
				'is_custom' => true,
			)
		);
	}

	/**
	 * AJAX handler for getting vendor tab content.
	 *
	 * @return void
	 */
	public function ajax_get_tab_content(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );
		$tab       = sanitize_key( $_POST['tab'] ?? 'overview' );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		ob_start();

		switch ( $tab ) {
			case 'overview':
				$this->render_tab_overview( $vendor_id );
				break;
			case 'services':
				$this->render_tab_services( $vendor_id );
				break;
			case 'orders':
				$this->render_tab_orders( $vendor_id );
				break;
			case 'earnings':
				$this->render_tab_earnings( $vendor_id );
				break;
			case 'wallet':
				$this->render_tab_wallet( $vendor_id );
				break;
			case 'reviews':
				$this->render_tab_reviews( $vendor_id );
				break;
			case 'portfolio':
				$this->render_tab_portfolio( $vendor_id );
				break;
			case 'settings':
				$this->render_tab_settings( $vendor_id );
				break;
			default:
				echo '<p>' . esc_html__( 'Invalid tab.', 'wp-sell-services' ) . '</p>';
		}

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Render Overview tab content.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_overview( int $vendor_id ): void {
		$profile = $this->vendor_repo->get_by_user( $vendor_id );
		$user    = get_userdata( $vendor_id );

		if ( ! $profile ) {
			echo '<p>' . esc_html__( 'Vendor profile not found.', 'wp-sell-services' ) . '</p>';
			return;
		}
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Profile Information', 'wp-sell-services' ); ?></h3>
			<div class="wpss-info-grid">
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Bio', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo $profile->bio ? wp_kses_post( $profile->bio ) : '<em>' . esc_html__( 'Not provided', 'wp-sell-services' ) . '</em>'; ?></span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Tagline', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo $profile->tagline ? esc_html( $profile->tagline ) : '<em>' . esc_html__( 'Not provided', 'wp-sell-services' ) . '</em>'; ?></span>
				</div>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Location & Contact', 'wp-sell-services' ); ?></h3>
			<div class="wpss-info-grid">
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Country', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo $profile->country ? esc_html( wpss_get_country_name( (string) $profile->country ) ) : '-'; ?></span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'City', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo $profile->city ? esc_html( $profile->city ) : '-'; ?></span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Timezone', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo $profile->timezone ? esc_html( $profile->timezone ) : '-'; ?></span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Website', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value">
						<?php if ( $profile->website ) : ?>
							<a href="<?php echo esc_url( $profile->website ); ?>" target="_blank"><?php echo esc_html( $profile->website ); ?></a>
						<?php else : ?>
							-
						<?php endif; ?>
					</span>
				</div>
			</div>
		</div>

		<?php
		$social_links = $profile->social_links ? json_decode( $profile->social_links, true ) : array();
		if ( ! empty( $social_links ) ) :
			?>
			<div class="wpss-tab-section">
				<h3><?php esc_html_e( 'Social Links', 'wp-sell-services' ); ?></h3>
				<div class="wpss-social-links">
					<?php foreach ( $social_links as $platform => $url ) : ?>
						<?php if ( $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" class="wpss-social-link" target="_blank">
								<?php echo esc_html( ucfirst( $platform ) ); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Verification & Status', 'wp-sell-services' ); ?></h3>
			<div class="wpss-info-grid">
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Verification Tier', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo esc_html( \WPSellServices\Models\VendorProfile::get_tiers()[ $profile->verification_tier ?? 'new' ] ?? ucfirst( $profile->verification_tier ?? 'new' ) ); ?></span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Verified At', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value">
						<?php echo $profile->verified_at ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $profile->verified_at ) ) ) : '-'; ?>
					</span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Availability', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value">
						<?php if ( ! empty( $profile->is_available ) ) : ?>
							<span style="color: #00a32a;">● <?php esc_html_e( 'Available', 'wp-sell-services' ); ?></span>
						<?php else : ?>
							<span style="color: #646970;">○ <?php esc_html_e( 'Not Available', 'wp-sell-services' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Vacation Mode', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value">
						<?php if ( ! empty( $profile->vacation_mode ) ) : ?>
							<span style="color: #dba617;">● <?php esc_html_e( 'On Vacation', 'wp-sell-services' ); ?></span>
							<?php if ( $profile->vacation_message ) : ?>
								<br><small><?php echo esc_html( $profile->vacation_message ); ?></small>
							<?php endif; ?>
						<?php else : ?>
							<span style="color: #646970;">○ <?php esc_html_e( 'Not on Vacation', 'wp-sell-services' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Performance Metrics', 'wp-sell-services' ); ?></h3>
			<div class="wpss-info-grid">
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'On-Time Delivery Rate', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo esc_html( number_format( (float) ( $profile->on_time_delivery_rate ?? 0 ), 1 ) ); ?>%</span>
				</div>
				<div class="wpss-info-item">
					<span class="wpss-info-label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></span>
					<span class="wpss-info-value"><?php echo esc_html( number_format_i18n( (int) ( $profile->completed_orders ?? 0 ) ) ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Services tab content.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_services( int $vendor_id ): void {
		$page     = isset( $_POST['services_page'] ) ? absint( $_POST['services_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_get_tab_content().
		$per_page = 20;

		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'author'         => $vendor_id,
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$total_services = wp_count_posts( 'wpss_service' );
		// Count only this vendor's services.
		global $wpdb;
		$total       = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'wpss_service' AND post_status IN ('publish', 'draft', 'pending', 'private')",
				$vendor_id
			)
		);
		$total_pages = ceil( $total / $per_page );

		if ( empty( $services ) ) {
			echo '<p>' . esc_html__( 'No services found.', 'wp-sell-services' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Created', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $services as $service ) : ?>
					<?php
					$price        = get_post_meta( $service->ID, '_wpss_starting_price', true );
					$order_count  = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders WHERE service_id = %d",
							$service->ID
						)
					);
					$status_class = 'publish' === $service->post_status ? 'active' : ( 'draft' === $service->post_status ? 'pending' : $service->post_status );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $service->post_title ); ?></strong>
						</td>
						<td>
							<span class="<?php echo esc_attr( wpss_status_class( $status_class ) ); ?>">
								<?php echo esc_html( ucfirst( $service->post_status ) ); ?>
							</span>
						</td>
						<td><?php echo $price ? esc_html( wpss_format_price( (float) $price ) ) : '-'; ?></td>
						<td><?php echo esc_html( number_format_i18n( $order_count ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $service->post_date ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $service->ID ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>" class="button button-small" target="_blank">
								<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: number of items */
							esc_html( _n( '%s service', '%s services', $total, 'wp-sell-services' ) ),
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number_format_i18n() is a safe formatting function.
							number_format_i18n( $total )
						);
						?>
					</span>
					<span class="pagination-links">
						<?php if ( $page > 1 ) : ?>
							<a href="#" class="wpss-services-page" data-page="<?php echo esc_attr( $page - 1 ); ?>">&laquo;</a>
						<?php endif; ?>
						<span class="paging-input">
							<?php echo esc_html( $page ); ?> / <?php echo esc_html( $total_pages ); ?>
						</span>
						<?php if ( $page < $total_pages ) : ?>
							<a href="#" class="wpss-services-page" data-page="<?php echo esc_attr( $page + 1 ); ?>">&raquo;</a>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Orders tab content.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_orders( int $vendor_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_get_tab_content().
		$page          = isset( $_POST['orders_page'] ) ? absint( $_POST['orders_page'] ) : 1;
		$per_page      = 20;
		$status_filter = isset( $_POST['order_status'] ) ? sanitize_key( $_POST['order_status'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$offset = ( $page - 1 ) * $per_page;

		$where  = 'WHERE o.vendor_id = %d';
		$params = array( $vendor_id );

		if ( $status_filter ) {
			$where   .= ' AND o.status = %s';
			$params[] = $status_filter;
		}

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders o {$where}",
				...$params
			)
		);

		$params[] = $per_page;
		$params[] = $offset;

		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.*, s.post_title as service_title, u.display_name as customer_name
				FROM {$wpdb->prefix}wpss_orders o
				LEFT JOIN {$wpdb->posts} s ON o.service_id = s.ID
				LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
				{$where}
				ORDER BY o.created_at DESC
				LIMIT %d OFFSET %d",
				...$params
			)
		);

		$total_pages = ceil( $total / $per_page );

		// Get available statuses for filter.
		$statuses = array(
			''                     => __( 'All Statuses', 'wp-sell-services' ),
			'pending_payment'      => __( 'Pending Payment', 'wp-sell-services' ),
			'pending_requirements' => __( 'Pending Requirements', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress', 'wp-sell-services' ),
			'pending_approval'     => __( 'Pending Approval', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
			'cancelled'            => __( 'Cancelled', 'wp-sell-services' ),
			'refunded'             => __( 'Refunded', 'wp-sell-services' ),
		);
		?>
		<div class="wpss-orders-filter" style="margin-bottom: 15px;">
			<select id="wpss-order-status-filter">
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_filter, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ( empty( $orders ) ) : ?>
			<p><?php esc_html_e( 'No orders found.', 'wp-sell-services' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders as $order ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&id=' . $order->id ) ); ?>">
									<?php echo esc_html( $order->order_number ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $order->customer_name ?? __( 'Guest', 'wp-sell-services' ) ); ?></td>
							<td><?php echo esc_html( $order->service_title ); ?></td>
							<td><?php echo esc_html( wpss_format_price( (float) $order->total ) ); ?></td>
							<td>
								<span class="<?php echo esc_attr( wpss_status_class( $order->status ) ); ?>">
									<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s order', '%s orders', $total, 'wp-sell-services' ) ),
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number_format_i18n() is a safe formatting function.
								number_format_i18n( $total )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php if ( $page > 1 ) : ?>
								<a href="#" class="wpss-orders-page" data-page="<?php echo esc_attr( $page - 1 ); ?>">&laquo;</a>
							<?php endif; ?>
							<span class="paging-input">
								<?php echo esc_html( $page ); ?> / <?php echo esc_html( $total_pages ); ?>
							</span>
							<?php if ( $page < $total_pages ) : ?>
								<a href="#" class="wpss-orders-page" data-page="<?php echo esc_attr( $page + 1 ); ?>">&raquo;</a>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Earnings tab content.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_earnings( int $vendor_id ): void {
		global $wpdb;

		// Get commission summary.
		$commission_summary = $this->commission_service->get_vendor_summary( $vendor_id );
		$effective_rate     = $this->commission_service->get_effective_vendor_rate( $vendor_id );
		$global_rate        = CommissionService::get_global_commission_rate();

		// Get wallet balance.
		$wallet_balance = wpss_get_ledger_balance( (int) $vendor_id );

		// Get withdrawal history.
		$withdrawals_page = isset( $_POST['withdrawals_page'] ) ? absint( $_POST['withdrawals_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_get_tab_content().
		$per_page         = 10;
		$offset           = ( $withdrawals_page - 1 ) * $per_page;

		$withdrawals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_withdrawals
				WHERE vendor_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				$per_page,
				$offset
			)
		);

		$total_withdrawals = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_withdrawals WHERE vendor_id = %d",
				$vendor_id
			)
		);

		$withdrawal_pages = ceil( $total_withdrawals / $per_page );
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Earnings Summary', 'wp-sell-services' ); ?></h3>
			<div class="wpss-earnings-summary">
				<div class="wpss-earnings-card">
					<strong><?php echo esc_html( wpss_format_price( $commission_summary['total_revenue'] ) ); ?></strong>
					<?php esc_html_e( 'Total Revenue', 'wp-sell-services' ); ?>
				</div>
				<div class="wpss-earnings-card">
					<strong><?php echo esc_html( wpss_format_price( $commission_summary['net_earnings'] ) ); ?></strong>
					<?php esc_html_e( 'Net Earnings', 'wp-sell-services' ); ?>
				</div>
				<div class="wpss-earnings-card">
					<strong><?php echo esc_html( wpss_format_price( $commission_summary['total_commission'] ) ); ?></strong>
					<?php esc_html_e( 'Platform Fees', 'wp-sell-services' ); ?>
				</div>
				<?php
				// A negative wallet balance means a refund reclaimed earnings
				// this vendor had already been paid, so they owe the platform.
				// Flagged rather than shown as a plain figure, because an admin
				// scanning this screen needs to spot a debt without doing the
				// arithmetic themselves.
				$wpss_balance_owed = $wallet_balance < 0;
				?>
				<div class="wpss-earnings-card<?php echo $wpss_balance_owed ? ' wpss-earnings-card--owed' : ''; ?>">
					<strong><?php echo esc_html( wpss_format_price( $wallet_balance ) ); ?></strong>
					<?php
					echo $wpss_balance_owed
						? esc_html__( 'Wallet Balance (owed to platform)', 'wp-sell-services' )
						: esc_html__( 'Wallet Balance', 'wp-sell-services' );
					?>
				</div>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Commission Configuration', 'wp-sell-services' ); ?></h3>
			<div class="wpss-commission-form">
				<p class="description">
					<?php
					printf(
						/* translators: %s: global commission rate */
						esc_html__( 'Global platform commission: %s%%. Customize the rate for this vendor below.', 'wp-sell-services' ),
						esc_html( number_format( $global_rate, 1 ) )
					);
					?>
				</p>
				<div class="form-row">
					<input type="number" id="wpss-commission-rate-detail"
							value="<?php echo esc_attr( $effective_rate['is_custom'] ? number_format( $effective_rate['rate'], 2, '.', '' ) : '' ); ?>"
							placeholder="<?php echo esc_attr( number_format( $global_rate, 1 ) ); ?>"
							min="0" max="100" step="0.01">
					<span>%</span>
					<button type="button" class="button button-primary" id="wpss-save-commission-detail">
						<?php esc_html_e( 'Save', 'wp-sell-services' ); ?>
					</button>
					<?php if ( $effective_rate['is_custom'] ) : ?>
						<button type="button" class="button" id="wpss-reset-commission-detail">
							<?php esc_html_e( 'Reset to Global', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<p class="wpss-commission-status" id="wpss-commission-detail-status">
					<?php if ( $effective_rate['is_custom'] ) : ?>
						<span style="color: #2271b1;">
							<?php
							printf(
								/* translators: %s: custom commission rate */
								esc_html__( 'Using custom rate: %s%%', 'wp-sell-services' ),
								esc_html( number_format( $effective_rate['rate'], 2 ) )
							);
							?>
						</span>
					<?php else : ?>
						<span style="color: #646970;">
							<?php esc_html_e( 'Using global rate', 'wp-sell-services' ); ?>
						</span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Withdrawal History', 'wp-sell-services' ); ?></h3>
			<?php if ( empty( $withdrawals ) ) : ?>
				<p><?php esc_html_e( 'No withdrawal requests yet.', 'wp-sell-services' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Method', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Requested', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Processed', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $withdrawals as $withdrawal ) : ?>
							<tr>
								<td>#<?php echo esc_html( $withdrawal->id ); ?></td>
								<td><?php echo esc_html( wpss_format_price( (float) $withdrawal->amount ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $withdrawal->method ?? 'bank' ) ); ?></td>
								<td>
									<span class="<?php echo esc_attr( wpss_status_class( $withdrawal->status ) ); ?>">
										<?php echo esc_html( ucfirst( $withdrawal->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $withdrawal->created_at ) ) ); ?></td>
								<td>
									<?php echo $withdrawal->processed_at ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $withdrawal->processed_at ) ) ) : '-'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $withdrawal_pages > 1 ) : ?>
					<div class="tablenav wpss-withdrawals-pagination">
						<div class="tablenav-pages">
							<?php if ( $withdrawals_page > 1 ) : ?>
								<a href="#" data-page="<?php echo esc_attr( $withdrawals_page - 1 ); ?>">&laquo;</a>
							<?php endif; ?>
							<span class="paging-input">
								<?php echo esc_html( $withdrawals_page ); ?> / <?php echo esc_html( $withdrawal_pages ); ?>
							</span>
							<?php if ( $withdrawals_page < $withdrawal_pages ) : ?>
								<a href="#" data-page="<?php echo esc_attr( $withdrawals_page + 1 ); ?>">&raquo;</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Wallet tab content (read-only).
	 *
	 * Surfaces the vendor's `wpss_wallet_transactions` ledger inside the admin
	 * detail drawer. Read-only: admins audit balance movements here; mutations
	 * stay in their owning flows (orders, tips, withdrawals).
	 *
	 * @since 1.2.0
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_wallet( int $vendor_id ): void {
		global $wpdb;

		$limit = 50;

		// Current balance = balance_after on the latest transaction.
		$wallet_balance = wpss_get_ledger_balance( (int) $vendor_id );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_wallet_transactions WHERE user_id = %d",
				$vendor_id
			)
		);

		// Most recent transactions (read-only audit view).
		$transactions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, type, amount, balance_after, currency, description, reference_type, reference_id, status, created_at
				FROM {$wpdb->prefix}wpss_wallet_transactions
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT %d",
				$vendor_id,
				$limit
			)
		);
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Wallet Balance', 'wp-sell-services' ); ?></h3>
			<div class="wpss-earnings-summary">
				<div class="wpss-earnings-card<?php echo $wallet_balance < 0 ? ' wpss-earnings-card--owed' : ''; ?>">
					<strong><?php echo esc_html( wpss_format_price( $wallet_balance ) ); ?></strong>
					<?php
					echo $wallet_balance < 0
						? esc_html__( 'Current Balance (owed to platform)', 'wp-sell-services' )
						: esc_html__( 'Current Balance', 'wp-sell-services' );
					?>
				</div>
				<div class="wpss-earnings-card">
					<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
					<?php esc_html_e( 'Total Transactions', 'wp-sell-services' ); ?>
				</div>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Transaction History', 'wp-sell-services' ); ?></h3>
			<?php if ( $total > $limit ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of transactions shown */
						esc_html__( 'Showing the %d most recent transactions.', 'wp-sell-services' ),
						(int) $limit
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( empty( $transactions ) ) : ?>
				<p><?php esc_html_e( 'No wallet transactions yet.', 'wp-sell-services' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Type', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Balance', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $transactions as $txn ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $txn->created_at ) ) ); ?></td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $txn->type ) ) ); ?></td>
								<td><?php echo esc_html( (string) ( $txn->description ?? '' ) ); ?></td>
								<td><?php echo esc_html( wpss_format_price( (float) $txn->amount ) ); ?></td>
								<td><?php echo esc_html( wpss_format_price( (float) $txn->balance_after ) ); ?></td>
								<td>
									<span class="<?php echo esc_attr( wpss_status_class( $txn->status ) ); ?>">
										<?php echo esc_html( ucfirst( (string) $txn->status ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Reviews tab content.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_reviews( int $vendor_id ): void {
		global $wpdb;

		$page     = isset( $_POST['reviews_page'] ) ? absint( $_POST['reviews_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_get_tab_content().
		$per_page = 10;
		$offset   = ( $page - 1 ) * $per_page;

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, o.order_number, s.post_title as service_title, COALESCE(NULLIF(u.display_name, ''), r.reviewer_name) as reviewer_display_name
				FROM {$wpdb->prefix}wpss_reviews r
				LEFT JOIN {$wpdb->prefix}wpss_orders o ON r.order_id = o.id
				LEFT JOIN {$wpdb->posts} s ON o.service_id = s.ID
				LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
				WHERE r.vendor_id = %d AND r.review_type = 'customer_to_vendor'
				ORDER BY r.created_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				$per_page,
				$offset
			)
		);

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_reviews WHERE vendor_id = %d AND review_type = 'customer_to_vendor'",
				$vendor_id
			)
		);

		$total_pages = ceil( $total / $per_page );

		// Calculate rating distribution.
		$distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rating, COUNT(*) as count
				FROM {$wpdb->prefix}wpss_reviews
				WHERE vendor_id = %d AND review_type = 'customer_to_vendor' AND status = 'approved'
				GROUP BY rating
				ORDER BY rating DESC",
				$vendor_id
			),
			ARRAY_A
		);

		$dist_counts = array_fill( 1, 5, 0 );
		foreach ( $distribution as $row ) {
			$dist_counts[ (int) $row['rating'] ] = (int) $row['count'];
		}
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Rating Distribution', 'wp-sell-services' ); ?></h3>
			<div class="wpss-rating-distribution" style="max-width: 400px;">
				<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
					<?php
					$count   = $dist_counts[ $i ];
					$percent = $total > 0 ? ( $count / $total ) * 100 : 0;
					?>
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
						<span style="width: 60px;"><?php echo esc_html( $i ); ?> ★</span>
						<div style="flex: 1; height: 20px; background: #dcdcde; border-radius: 3px; overflow: hidden;">
							<div style="width: <?php echo esc_attr( $percent ); ?>%; height: 100%; background: #ffb900;"></div>
						</div>
						<span style="width: 40px; text-align: right;"><?php echo esc_html( $count ); ?></span>
					</div>
				<?php endfor; ?>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Recent Reviews', 'wp-sell-services' ); ?></h3>
			<?php if ( empty( $reviews ) ) : ?>
				<p><?php esc_html_e( 'No reviews yet.', 'wp-sell-services' ); ?></p>
			<?php else : ?>
				<div class="wpss-reviews-list">
					<?php foreach ( $reviews as $review ) : ?>
						<div class="wpss-review-item">
							<div class="wpss-review-header">
								<div>
									<span class="wpss-review-rating">
										<?php echo esc_html( str_repeat( '★', (int) $review->rating ) ); ?>
										<?php echo esc_html( str_repeat( '☆', 5 - (int) $review->rating ) ); ?>
									</span>
									<strong><?php echo esc_html( $review->reviewer_display_name ?? __( 'Anonymous', 'wp-sell-services' ) ); ?></strong>
								</div>
								<span class="wpss-review-meta">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review->created_at ) ) ); ?>
									<?php if ( $review->service_title ) : ?>
										• <?php echo esc_html( $review->service_title ); ?>
									<?php endif; ?>
								</span>
							</div>
							<p class="wpss-review-content"><?php echo wp_kses_post( $review->comment ); ?></p>
							<?php if ( 'approved' !== $review->status ) : ?>
								<span class="<?php echo esc_attr( wpss_status_class( $review->status ) ); ?>">
									<?php echo esc_html( ucfirst( $review->status ) ); ?>
								</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									/* translators: %s: number of items */
									esc_html( _n( '%s review', '%s reviews', $total, 'wp-sell-services' ) ),
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number_format_i18n() is a safe formatting function.
									number_format_i18n( $total )
								);
								?>
							</span>
							<span class="pagination-links">
								<?php if ( $page > 1 ) : ?>
									<a href="#" class="wpss-reviews-page" data-page="<?php echo esc_attr( $page - 1 ); ?>">&laquo;</a>
								<?php endif; ?>
								<span class="paging-input">
									<?php echo esc_html( $page ); ?> / <?php echo esc_html( $total_pages ); ?>
								</span>
								<?php if ( $page < $total_pages ) : ?>
									<a href="#" class="wpss-reviews-page" data-page="<?php echo esc_attr( $page + 1 ); ?>">&raquo;</a>
								<?php endif; ?>
							</span>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Portfolio tab content (admin moderation).
	 *
	 * Lists the vendor's portfolio_items so an admin can moderate the public
	 * showcase from inside the drawer: feature/unfeature an item, or remove an
	 * inappropriate one. Mirrors the operations exposed by PortfolioController
	 * (toggle_featured / delete_item) but gated to manage_options.
	 *
	 * @since 1.2.0
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_portfolio( int $vendor_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- vendor profile portfolio audit read; vendor_id bound via %d.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, service_id, title, description, external_url, is_featured, sort_order, created_at
				FROM {$wpdb->prefix}wpss_portfolio_items
				WHERE vendor_id = %d
				ORDER BY is_featured DESC, sort_order ASC, created_at DESC",
				$vendor_id
			)
		);
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Portfolio Moderation', 'wp-sell-services' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Review the items this vendor shows on their public profile. Feature an item to pin it to the top, or remove items that breach your marketplace policy.', 'wp-sell-services' ); ?>
			</p>
			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'This vendor has no portfolio items.', 'wp-sell-services' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Linked Service', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Added', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$is_featured   = ! empty( $item->is_featured );
							$service_title = $item->service_id ? get_the_title( (int) $item->service_id ) : '';
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $item->title ); ?></strong>
									<?php if ( $item->external_url ) : ?>
										<br>
										<a href="<?php echo esc_url( $item->external_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'View link', 'wp-sell-services' ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $service_title ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $item->service_id ) ); ?>">
											<?php echo esc_html( $service_title ); ?>
										</a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $is_featured ) : ?>
										<span class="wpss-status-badge wpss-status-active"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></span>
									<?php else : ?>
										<span class="wpss-status-line__muted">&mdash;</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) $item->created_at ) ) ); ?></td>
								<td>
									<button type="button" class="button button-small wpss-portfolio-action"
											data-item-id="<?php echo esc_attr( (string) $item->id ); ?>"
											data-mod-action="<?php echo $is_featured ? 'unfeature' : 'feature'; ?>">
										<?php echo $is_featured ? esc_html__( 'Unfeature', 'wp-sell-services' ) : esc_html__( 'Feature', 'wp-sell-services' ); ?>
									</button>
									<button type="button" class="button button-small wpss-portfolio-action"
											data-item-id="<?php echo esc_attr( (string) $item->id ); ?>"
											data-mod-action="delete">
										<?php esc_html_e( 'Remove', 'wp-sell-services' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Settings tab content.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function render_tab_settings( int $vendor_id ): void {
		$profile        = $this->vendor_repo->get_by_user( $vendor_id );
		$effective_rate = $this->commission_service->get_effective_vendor_rate( $vendor_id );
		$global_rate    = CommissionService::get_global_commission_rate();

		if ( ! $profile ) {
			echo '<p>' . esc_html__( 'Vendor profile not found.', 'wp-sell-services' ) . '</p>';
			return;
		}
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Commission Rate', 'wp-sell-services' ); ?></h3>
			<div class="wpss-settings-section">
				<p class="description">
					<?php
					printf(
						/* translators: %s: global commission rate */
						esc_html__( 'Global platform commission: %s%%. Set a custom rate for this vendor.', 'wp-sell-services' ),
						esc_html( number_format( $global_rate, 1 ) )
					);
					?>
				</p>
				<div class="form-row" style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
					<input type="number" id="wpss-commission-rate-detail"
							value="<?php echo esc_attr( $effective_rate['is_custom'] ? number_format( $effective_rate['rate'], 2, '.', '' ) : '' ); ?>"
							placeholder="<?php echo esc_attr( number_format( $global_rate, 1 ) ); ?>"
							min="0" max="100" step="0.01"
							style="width: 100px;">
					<span>%</span>
					<button type="button" class="button button-primary" id="wpss-save-commission-detail">
						<?php esc_html_e( 'Save', 'wp-sell-services' ); ?>
					</button>
					<?php if ( $effective_rate['is_custom'] ) : ?>
						<button type="button" class="button" id="wpss-reset-commission-detail">
							<?php esc_html_e( 'Reset to Global', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<p id="wpss-commission-detail-status" style="margin-top: 10px;">
					<?php if ( $effective_rate['is_custom'] ) : ?>
						<span style="color: #2271b1;">
							<?php
							printf(
								/* translators: %s: custom commission rate */
								esc_html__( 'Using custom rate: %s%%', 'wp-sell-services' ),
								esc_html( number_format( $effective_rate['rate'], 2 ) )
							);
							?>
						</span>
					<?php else : ?>
						<span style="color: #646970;">
							<?php esc_html_e( 'Using global rate', 'wp-sell-services' ); ?>
						</span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Availability', 'wp-sell-services' ); ?></h3>
			<div class="wpss-settings-section">
				<div class="wpss-toggle-row">
					<label for="wpss-availability-toggle">
						<input type="checkbox" id="wpss-availability-toggle" <?php checked( ! empty( $profile->is_available ) ); ?>>
						<?php esc_html_e( 'Vendor is available for new orders', 'wp-sell-services' ); ?>
					</label>
				</div>
				<p id="wpss-availability-status"></p>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Vacation Mode (Admin Override)', 'wp-sell-services' ); ?></h3>
			<div class="wpss-settings-section">
				<p class="description">
					<?php esc_html_e( 'Toggle vacation mode on the vendor\'s behalf. While on vacation the vendor cannot accept new orders and their services are hidden from search.', 'wp-sell-services' ); ?>
				</p>
				<div class="wpss-toggle-row">
					<label for="wpss-vacation-mode-toggle">
						<input type="checkbox" id="wpss-vacation-mode-toggle" <?php checked( ! empty( $profile->vacation_mode ) ); ?>>
						<?php esc_html_e( 'Enable vacation mode', 'wp-sell-services' ); ?>
					</label>
				</div>
				<div class="wpss-vacation-message">
					<label for="wpss-vacation-message">
						<?php esc_html_e( 'Vacation Message (shown to customers)', 'wp-sell-services' ); ?>
					</label>
					<textarea id="wpss-vacation-message" rows="3"><?php echo esc_textarea( $profile->vacation_message ?? '' ); ?></textarea>
				</div>
				<div class="wpss-vacation-return-date">
					<label for="wpss-vacation-return-date">
						<?php esc_html_e( 'Back on (optional)', 'wp-sell-services' ); ?>
					</label>
					<input type="date" id="wpss-vacation-return-date" value="<?php echo esc_attr( $profile->vacation_return_date ?? '' ); ?>">
				</div>
				<p id="wpss-vacation-status"></p>
			</div>
		</div>

		<?php
		$current_level = $profile->verification_tier ?? VendorProfile::TIER_NEW;
		$level_labels  = VendorProfile::get_tiers();
		$auto_level    = $this->seller_level_service->calculate_level( $vendor_id );
		?>
		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Seller Level (Admin Override)', 'wp-sell-services' ); ?></h3>
			<div class="wpss-settings-section">
				<p class="description">
					<?php
					printf(
						/* translators: %s: auto-calculated seller level label */
						esc_html__( 'Levels normally update automatically from vendor performance (the calculated level is %s). Use the override below to grant or revoke a level manually, including the admin-only Pro Seller tier.', 'wp-sell-services' ),
						'<strong>' . esc_html( $level_labels[ $auto_level ] ?? ucfirst( $auto_level ) ) . '</strong>'
					);
					?>
				</p>
				<div class="form-row wpss-form-row--inline">
					<label for="wpss-level-select-detail" class="screen-reader-text">
						<?php esc_html_e( 'Seller Level', 'wp-sell-services' ); ?>
					</label>
					<select id="wpss-level-select-detail" data-vendor-id="<?php echo esc_attr( (string) $vendor_id ); ?>">
						<?php foreach ( $level_labels as $level_key => $level_label ) : ?>
							<option value="<?php echo esc_attr( $level_key ); ?>" <?php selected( $current_level, $level_key ); ?>>
								<?php echo esc_html( $level_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button button-primary" id="wpss-save-level-detail">
						<?php esc_html_e( 'Save Level', 'wp-sell-services' ); ?>
					</button>
				</div>
				<p id="wpss-level-status" class="wpss-status-line">
					<span class="wpss-status-line__muted">
						<?php
						printf(
							/* translators: %s: current seller level label */
							esc_html__( 'Current level: %s', 'wp-sell-services' ),
							esc_html( $level_labels[ $current_level ] ?? ucfirst( $current_level ) )
						);
						?>
					</span>
				</p>
			</div>
		</div>

		<div class="wpss-tab-section">
			<h3><?php esc_html_e( 'Verification', 'wp-sell-services' ); ?></h3>
			<div class="wpss-settings-section">
				<p>
					<strong><?php esc_html_e( 'Current Tier:', 'wp-sell-services' ); ?></strong>
					<?php echo esc_html( \WPSellServices\Models\VendorProfile::get_tiers()[ $profile->verification_tier ?? 'new' ] ?? ucfirst( $profile->verification_tier ?? 'new' ) ); ?>
				</p>
				<?php if ( $profile->verified_at ) : ?>
					<p>
						<strong><?php esc_html_e( 'Verified:', 'wp-sell-services' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $profile->verified_at ) ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for updating vendor vacation mode.
	 *
	 * @return void
	 */
	public function ajax_update_vendor_vacation(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$vendor_id   = absint( $_POST['vendor_id'] ?? 0 );
		$enabled     = ! empty( $_POST['enabled'] );
		$message     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$return_date = isset( $_POST['return_date'] ) ? wpss_sanitize_date( sanitize_text_field( wp_unslash( $_POST['return_date'] ) ) ) : null;

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		$result = $this->vendor_repo->set_vacation_mode( $vendor_id, $enabled, $message, $return_date );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update vacation mode.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => $enabled
					? __( 'Vacation mode enabled.', 'wp-sell-services' )
					: __( 'Vacation mode disabled.', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * AJAX handler for updating vendor availability.
	 *
	 * @return void
	 */
	public function ajax_update_vendor_availability(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );
		$available = ! empty( $_POST['available'] );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		$result = $this->vendor_repo->set_availability( $vendor_id, $available );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update availability.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => $available
					? __( 'Vendor is now available.', 'wp-sell-services' )
					: __( 'Vendor is now unavailable.', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * AJAX handler for the seller-level admin override.
	 *
	 * Manually sets the vendor's verification_tier (seller level), bypassing the
	 * auto-calculation. Delegates the write + `wpss_vendor_level_updated` action
	 * to SellerLevelService so the override behaves identically to the cron-driven
	 * recalculation. The admin-only Pro Seller tier can only be granted here.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_update_vendor_level(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ), 403 );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );
		$level     = sanitize_key( $_POST['level'] ?? '' );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ) );
		}

		$allowed_levels = array_keys( VendorProfile::get_tiers() );
		if ( ! in_array( $level, $allowed_levels, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid seller level.', 'wp-sell-services' ) ) );
		}

		$result = $this->seller_level_service->update_vendor_level( $vendor_id, $level );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update seller level.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: seller level label */
					__( 'Seller level set to %s.', 'wp-sell-services' ),
					SellerLevelService::get_level_label( $level )
				),
				'level'   => $level,
			)
		);
	}

	/**
	 * AJAX handler for portfolio moderation actions.
	 *
	 * Lets an admin feature/unfeature or remove a single portfolio item that
	 * belongs to the vendor. The vendor_id is verified against the item row so an
	 * admin can never accidentally moderate another vendor's item via a stale id.
	 * On success the refreshed portfolio table HTML is returned so the drawer can
	 * re-render without a full reload.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_moderate_portfolio_item(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ), 403 );
		}

		$vendor_id  = absint( $_POST['vendor_id'] ?? 0 );
		$item_id    = absint( $_POST['item_id'] ?? 0 );
		$mod_action = sanitize_key( $_POST['mod_action'] ?? '' );

		if ( ! $vendor_id || ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		if ( ! in_array( $mod_action, array( 'feature', 'unfeature', 'delete' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid moderation action.', 'wp-sell-services' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_portfolio_items';

		// Confirm the item belongs to this vendor before touching it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ownership guard; id bound via %d.
		$owner_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT vendor_id FROM {$wpdb->prefix}wpss_portfolio_items WHERE id = %d",
				$item_id
			)
		);

		if ( ! $owner_id || $owner_id !== $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Portfolio item not found for this vendor.', 'wp-sell-services' ) ) );
		}

		if ( 'delete' === $mod_action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->delete( $table, array( 'id' => $item_id ), array( '%d' ) );
		} else {
			$is_featured = 'feature' === $mod_action ? 1 : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				array( 'is_featured' => $is_featured ),
				array( 'id' => $item_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to moderate the portfolio item.', 'wp-sell-services' ) ) );
		}

		/**
		 * Fires after an admin moderates a vendor portfolio item from the drawer.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $item_id    Portfolio item ID.
		 * @param int    $vendor_id  Vendor user ID.
		 * @param string $mod_action Moderation action (feature|unfeature|delete).
		 */
		do_action( 'wpss_portfolio_item_moderated', $item_id, $vendor_id, $mod_action );

		$messages = array(
			'feature'   => __( 'Portfolio item featured.', 'wp-sell-services' ),
			'unfeature' => __( 'Portfolio item unfeatured.', 'wp-sell-services' ),
			'delete'    => __( 'Portfolio item removed.', 'wp-sell-services' ),
		);

		ob_start();
		$this->render_tab_portfolio( $vendor_id );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'message' => $messages[ $mod_action ],
				'html'    => $html,
			)
		);
	}
}
