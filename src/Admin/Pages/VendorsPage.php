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
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_repo = new VendorProfileRepository();
		$this->order_repo  = new OrderRepository();
	}

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ], 20 );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20 );
		add_action( 'wp_ajax_wpss_update_vendor_status', [ $this, 'ajax_update_vendor_status' ] );
		add_action( 'wp_ajax_wpss_get_vendor_details', [ $this, 'ajax_get_vendor_details' ] );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'wp-sell-services',
			__( 'Vendors', 'wp-sell-services' ),
			__( 'Vendors', 'wp-sell-services' ),
			'manage_options',
			'wpss-vendors',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue page scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'wp-sell-services_page_wpss-vendors' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );
		wp_enqueue_script( 'wpss-admin' );

		wp_add_inline_script(
			'wpss-admin',
			'window.wpssVendors = ' . wp_json_encode(
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpss_vendors_admin' ),
					'i18n'    => [
						'confirmStatusChange' => __( 'Are you sure you want to change this vendor\'s status?', 'wp-sell-services' ),
						'loading'             => __( 'Loading...', 'wp-sell-services' ),
						'error'               => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					],
				]
			) . ';'
		);
	}

	/**
	 * Get vendors with stats.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function get_vendors( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'per_page' => 20,
			'page'     => 1,
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		];

		$args   = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Build query.
		$where = [ '1=1' ];
		$values = [];

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
		$count_query = "
			SELECT COUNT(DISTINCT vp.user_id)
			FROM {$wpdb->prefix}wpss_vendor_profiles vp
			LEFT JOIN {$wpdb->users} u ON vp.user_id = u.ID
			WHERE {$where_clause}
		";

		$total = $values
			? (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) )
			: (int) $wpdb->get_var( $count_query );

		// Get vendors with stats.
		$orderby_map = [
			'created_at'   => 'vp.created_at',
			'display_name' => 'u.display_name',
			'rating'       => 'vp.rating',
			'total_orders' => 'vp.total_orders',
			'total_earned' => 'vp.total_earned',
		];

		$orderby = $orderby_map[ $args['orderby'] ] ?? 'vp.created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$query = $wpdb->prepare(
			"SELECT
				vp.*,
				u.display_name,
				u.user_email,
				u.user_registered,
				(SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_author = vp.user_id AND p.post_type = 'wpss_service' AND p.post_status = 'publish') as services_count
			FROM {$wpdb->prefix}wpss_vendor_profiles vp
			LEFT JOIN {$wpdb->users} u ON vp.user_id = u.ID
			WHERE {$where_clause}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d",
			array_merge( $values, [ $args['per_page'], $offset ] )
		);

		$vendors = $wpdb->get_results( $query );

		return [
			'vendors' => $vendors,
			'total'   => $total,
			'pages'   => ceil( $total / $args['per_page'] ),
		];
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
				AVG(rating) as avg_rating,
				SUM(total_earned) as total_earnings
			FROM {$wpdb->prefix}wpss_vendor_profiles"
		);

		return [
			'total'     => (int) ( $stats->total_vendors ?? 0 ),
			'active'    => (int) ( $stats->active_vendors ?? 0 ),
			'pending'   => (int) ( $stats->pending_vendors ?? 0 ),
			'suspended' => (int) ( $stats->suspended_vendors ?? 0 ),
			'avg_rating' => round( (float) ( $stats->avg_rating ?? 0 ), 2 ),
			'total_earnings' => (float) ( $stats->total_earnings ?? 0 ),
		];
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$status       = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order        = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';

		$result = $this->get_vendors(
			[
				'page'    => $current_page,
				'status'  => $status,
				'search'  => $search,
				'orderby' => $orderby,
				'order'   => $order,
			]
		);

		$vendors    = $result['vendors'];
		$total      = $result['total'];
		$total_pages = $result['pages'];
		$stats      = $this->get_vendor_stats();
		?>
		<div class="wrap wpss-vendors-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Vendors', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Stats Cards -->
			<div class="wpss-vendor-stats">
				<div class="wpss-stat-card">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Vendors', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-active">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['active'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Active', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-pending">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-suspended">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['suspended'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Suspended', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card">
					<span class="wpss-stat-number"><?php echo esc_html( wpss_format_price( $stats['total_earnings'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Earnings', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<!-- Filters -->
			<div class="wpss-vendors-filters">
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

			<!-- Vendors Table -->
			<table class="wp-list-table widefat fixed striped wpss-vendors-table">
				<thead>
					<tr>
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
							<?php $this->sortable_column_header( 'total_earned', __( 'Earnings', 'wp-sell-services' ), $orderby, $order ); ?>
						</th>
						<th scope="col" class="column-status">
							<?php esc_html_e( 'Status', 'wp-sell-services' ); ?>
						</th>
						<th scope="col" class="column-joined">
							<?php $this->sortable_column_header( 'created_at', __( 'Joined', 'wp-sell-services' ), $orderby, $order ); ?>
						</th>
						<th scope="col" class="column-actions">
							<?php esc_html_e( 'Actions', 'wp-sell-services' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $vendors ) ) : ?>
						<tr>
							<td colspan="8" class="wpss-no-items">
								<?php esc_html_e( 'No vendors found.', 'wp-sell-services' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $vendors as $vendor ) : ?>
							<?php $this->render_vendor_row( $vendor ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col" class="column-vendor"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-services"><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-orders"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-rating"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-earnings"><?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-joined"><?php esc_html_e( 'Joined', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
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
								number_format_i18n( $total )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$pagination_args = [
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $current_page,
							];
							echo wp_kses_post( paginate_links( $pagination_args ) );
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Vendor Details Modal -->
		<div id="wpss-vendor-modal" class="wpss-modal" style="display: none;">
			<div class="wpss-modal-content">
				<span class="wpss-modal-close">&times;</span>
				<div id="wpss-vendor-modal-body">
					<div class="wpss-modal-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading vendor details...', 'wp-sell-services' ); ?>
					</div>
				</div>
			</div>
		</div>

		<style>
			.wpss-vendor-stats {
				display: grid;
				grid-template-columns: repeat(5, 1fr);
				gap: 15px;
				margin: 20px 0;
			}
			.wpss-stat-card {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				text-align: center;
			}
			.wpss-stat-number {
				display: block;
				font-size: 28px;
				font-weight: 600;
				color: #1d2327;
			}
			.wpss-stat-label {
				display: block;
				font-size: 13px;
				color: #646970;
				margin-top: 5px;
			}
			.wpss-stat-active .wpss-stat-number { color: #00a32a; }
			.wpss-stat-pending .wpss-stat-number { color: #dba617; }
			.wpss-stat-suspended .wpss-stat-number { color: #d63638; }

			.wpss-vendors-filters {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin: 15px 0;
			}
			.wpss-vendors-filters .subsubsub {
				margin: 0;
			}
			.wpss-vendors-filters .search-box {
				display: flex;
				gap: 5px;
			}

			.wpss-vendors-table .column-vendor { width: 20%; }
			.wpss-vendors-table .column-services { width: 8%; text-align: center; }
			.wpss-vendors-table .column-orders { width: 8%; text-align: center; }
			.wpss-vendors-table .column-rating { width: 10%; text-align: center; }
			.wpss-vendors-table .column-earnings { width: 12%; text-align: right; }
			.wpss-vendors-table .column-status { width: 10%; }
			.wpss-vendors-table .column-joined { width: 12%; }
			.wpss-vendors-table .column-actions { width: 15%; }

			.wpss-vendor-info {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.wpss-vendor-avatar {
				width: 40px;
				height: 40px;
				border-radius: 50%;
			}
			.wpss-vendor-name {
				font-weight: 500;
			}
			.wpss-vendor-email {
				font-size: 12px;
				color: #646970;
			}

			.wpss-rating-stars {
				color: #ffb900;
			}
			.wpss-rating-count {
				font-size: 12px;
				color: #646970;
			}

			.wpss-status-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
			}
			.wpss-status-active { background: #d4edda; color: #155724; }
			.wpss-status-pending { background: #fff3cd; color: #856404; }
			.wpss-status-suspended { background: #f8d7da; color: #721c24; }

			.wpss-vendor-actions {
				display: flex;
				gap: 5px;
				flex-wrap: wrap;
			}
			.wpss-vendor-actions .button {
				padding: 2px 8px;
				font-size: 12px;
			}

			.wpss-no-items {
				text-align: center;
				padding: 40px 20px;
				color: #646970;
			}

			/* Modal */
			.wpss-modal {
				position: fixed;
				z-index: 100000;
				left: 0;
				top: 0;
				width: 100%;
				height: 100%;
				background-color: rgba(0, 0, 0, 0.6);
			}
			.wpss-modal-content {
				background-color: #fff;
				margin: 5% auto;
				padding: 0;
				border-radius: 4px;
				width: 80%;
				max-width: 800px;
				max-height: 80vh;
				overflow-y: auto;
				position: relative;
			}
			.wpss-modal-close {
				position: absolute;
				right: 15px;
				top: 10px;
				font-size: 28px;
				font-weight: bold;
				cursor: pointer;
				color: #646970;
				z-index: 1;
			}
			.wpss-modal-close:hover { color: #1d2327; }
			.wpss-modal-loading {
				padding: 60px;
				text-align: center;
			}
			.wpss-modal-loading .spinner {
				float: none;
				margin: 0 10px 0 0;
			}

			#wpss-vendor-modal-body .wpss-vendor-details {
				padding: 20px;
			}
			.wpss-vendor-header {
				display: flex;
				align-items: center;
				gap: 20px;
				padding-bottom: 20px;
				border-bottom: 1px solid #dcdcde;
				margin-bottom: 20px;
			}
			.wpss-vendor-header img {
				width: 80px;
				height: 80px;
				border-radius: 50%;
			}
			.wpss-vendor-header h2 {
				margin: 0 0 5px;
			}
			.wpss-vendor-stats-grid {
				display: grid;
				grid-template-columns: repeat(4, 1fr);
				gap: 15px;
				margin-bottom: 20px;
			}
			.wpss-vendor-stat {
				background: #f6f7f7;
				padding: 15px;
				border-radius: 4px;
				text-align: center;
			}
			.wpss-vendor-stat strong {
				display: block;
				font-size: 20px;
				margin-bottom: 5px;
			}

			@media (max-width: 1200px) {
				.wpss-vendor-stats { grid-template-columns: repeat(3, 1fr); }
			}
			@media (max-width: 782px) {
				.wpss-vendor-stats { grid-template-columns: repeat(2, 1fr); }
				.wpss-vendors-filters {
					flex-direction: column;
					align-items: flex-start;
					gap: 10px;
				}
			}
		</style>

		<script>
		jQuery(function($) {
			var $modal = $('#wpss-vendor-modal');
			var $modalBody = $('#wpss-vendor-modal-body');

			// View vendor details
			$('.wpss-view-vendor').on('click', function(e) {
				e.preventDefault();
				var vendorId = $(this).data('vendor-id');

				$modalBody.html('<div class="wpss-modal-loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading vendor details...', 'wp-sell-services' ); ?></div>');
				$modal.show();

				$.ajax({
					url: wpssVendors.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_get_vendor_details',
						nonce: wpssVendors.nonce,
						vendor_id: vendorId
					},
					success: function(response) {
						if (response.success) {
							$modalBody.html(response.data.html);
						} else {
							$modalBody.html('<div class="notice notice-error"><p>' + (response.data.message || wpssVendors.i18n.error) + '</p></div>');
						}
					},
					error: function() {
						$modalBody.html('<div class="notice notice-error"><p>' + wpssVendors.i18n.error + '</p></div>');
					}
				});
			});

			// Close modal
			$('.wpss-modal-close, .wpss-modal').on('click', function(e) {
				if (e.target === this) {
					$modal.hide();
				}
			});

			// Update vendor status
			$('.wpss-change-status').on('click', function(e) {
				e.preventDefault();

				if (!confirm(wpssVendors.i18n.confirmStatusChange)) {
					return;
				}

				var $btn = $(this);
				var vendorId = $btn.data('vendor-id');
				var newStatus = $btn.data('status');
				var $row = $btn.closest('tr');

				$btn.prop('disabled', true);

				$.ajax({
					url: wpssVendors.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_update_vendor_status',
						nonce: wpssVendors.nonce,
						vendor_id: vendorId,
						status: newStatus
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || wpssVendors.i18n.error);
							$btn.prop('disabled', false);
						}
					},
					error: function() {
						alert(wpssVendors.i18n.error);
						$btn.prop('disabled', false);
					}
				});
			});
		});
		</script>
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
			[
				'orderby' => $column,
				'order'   => $new_order,
			]
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
		$avatar  = get_avatar_url( $vendor->user_id, [ 'size' => 80 ] );
		$rating  = (float) ( $vendor->rating ?? 0 );
		$reviews = (int) ( $vendor->total_reviews ?? 0 );
		$status  = $vendor->status ?? 'active';
		?>
		<tr data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>">
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
					</div>
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
			<td class="column-earnings" data-colname="<?php esc_attr_e( 'Earnings', 'wp-sell-services' ); ?>">
				<?php echo esc_html( wpss_format_price( (float) ( $vendor->total_earned ?? 0 ) ) ); ?>
			</td>
			<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
				<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( ucfirst( $status ) ); ?>
				</span>
			</td>
			<td class="column-joined" data-colname="<?php esc_attr_e( 'Joined', 'wp-sell-services' ); ?>">
				<?php
				$joined = $vendor->created_at ?? $user->user_registered ?? '';
				if ( $joined ) {
					echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $joined ) ) );
				}
				?>
			</td>
			<td class="column-actions">
				<div class="wpss-vendor-actions">
					<button type="button" class="button wpss-view-vendor" data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>">
						<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
					</button>
					<a href="<?php echo esc_url( get_edit_user_link( $vendor->user_id ) ); ?>" class="button">
						<?php esc_html_e( 'Edit User', 'wp-sell-services' ); ?>
					</a>
					<?php if ( $status === 'active' ) : ?>
						<button type="button" class="button wpss-change-status"
								data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
								data-status="suspended">
							<?php esc_html_e( 'Suspend', 'wp-sell-services' ); ?>
						</button>
					<?php elseif ( $status === 'suspended' ) : ?>
						<button type="button" class="button wpss-change-status"
								data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
								data-status="active">
							<?php esc_html_e( 'Activate', 'wp-sell-services' ); ?>
						</button>
					<?php elseif ( $status === 'pending' ) : ?>
						<button type="button" class="button button-primary wpss-change-status"
								data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
								data-status="active">
							<?php esc_html_e( 'Approve', 'wp-sell-services' ); ?>
						</button>
						<button type="button" class="button wpss-change-status"
								data-vendor-id="<?php echo esc_attr( $vendor->user_id ); ?>"
								data-status="rejected">
							<?php esc_html_e( 'Reject', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * AJAX handler for updating vendor status.
	 *
	 * @return void
	 */
	public function ajax_update_vendor_status(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-sell-services' ) ] );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );
		$status    = sanitize_key( $_POST['status'] ?? '' );

		if ( ! $vendor_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ] );
		}

		$valid_statuses = [ 'active', 'pending', 'suspended', 'rejected' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid status.', 'wp-sell-services' ) ] );
		}

		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'wpss_vendor_profiles',
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'user_id' => $vendor_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update vendor status.', 'wp-sell-services' ) ] );
		}

		/**
		 * Fires when vendor status is updated.
		 *
		 * @param int    $vendor_id Vendor user ID.
		 * @param string $status    New status.
		 */
		do_action( 'wpss_vendor_status_updated', $vendor_id, $status );

		wp_send_json_success( [ 'message' => __( 'Vendor status updated successfully.', 'wp-sell-services' ) ] );
	}

	/**
	 * AJAX handler for getting vendor details.
	 *
	 * @return void
	 */
	public function ajax_get_vendor_details(): void {
		check_ajax_referer( 'wpss_vendors_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-sell-services' ) ] );
		}

		$vendor_id = absint( $_POST['vendor_id'] ?? 0 );

		if ( ! $vendor_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid vendor ID.', 'wp-sell-services' ) ] );
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
			wp_send_json_error( [ 'message' => __( 'Vendor not found.', 'wp-sell-services' ) ] );
		}

		// Get services.
		$services = get_posts(
			[
				'post_type'      => 'wpss_service',
				'post_status'    => [ 'publish', 'draft', 'pending' ],
				'author'         => $vendor_id,
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
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
					<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $vendor->status ); ?>">
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
					<strong><?php echo esc_html( $vendor->rating ? number_format( (float) $vendor->rating, 1 ) . ' ★' : '-' ); ?></strong>
					<?php esc_html_e( 'Rating', 'wp-sell-services' ); ?>
				</div>
				<div class="wpss-vendor-stat">
					<strong><?php echo esc_html( wpss_format_price( (float) ( $vendor->total_earned ?? 0 ) ) ); ?></strong>
					<?php esc_html_e( 'Earnings', 'wp-sell-services' ); ?>
				</div>
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
									<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $order->status ); ?>">
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

		wp_send_json_success( [ 'html' => $html ] );
	}
}
