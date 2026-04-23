<?php
/**
 * Withdrawals Management Page
 *
 * Admin page for managing vendor withdrawal requests.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

use WPSellServices\Services\EarningsService;

defined( 'ABSPATH' ) || exit;

/**
 * Withdrawals Page Class.
 *
 * @since 1.0.0
 */
class WithdrawalsPage {

	/**
	 * Earnings service instance.
	 *
	 * @var EarningsService
	 */
	private EarningsService $earnings_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->earnings_service = new EarningsService();
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
		add_action( 'wp_ajax_wpss_process_withdrawal', array( $this, 'ajax_process_withdrawal' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'Withdrawals', 'wp-sell-services' ),
			__( 'Withdrawals', 'wp-sell-services' ),
			'manage_options',
			'wpss-withdrawals',
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
				'content' => '<p>' . esc_html__( 'Withdrawals are vendor payout requests submitted from their dashboard. Each request carries an amount, a payout method (PayPal, bank transfer, or custom), and a status — pending, approved, completed, or rejected. Use the filter bar to focus on one status at a time and reconcile against your external payout system.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-actions',
				'title'   => __( 'Available actions', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'From each row you can approve, reject, or mark a withdrawal as completed after the external transfer clears. Configure the minimum withdrawal threshold, supported payout methods, and hold period in Settings > Payouts. Rejected and completed rows stay in the history for audit.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'wp-sell-services' ) . '</strong></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/" target="_blank" rel="noopener">' . esc_html__( 'Plugin docs', 'wp-sell-services' ) . '</a></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/withdrawals-wpss" target="_blank" rel="noopener">' . esc_html__( 'Withdrawals guide', 'wp-sell-services' ) . '</a></p>'
		);
	}

	/**
	 * Enqueue page scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'wp-sell-services_page_wpss-withdrawals' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );

		// Register wpss-admin if not already registered (e.g. Admin::enqueue_scripts did not run).
		if ( ! wp_script_is( 'wpss-admin', 'registered' ) ) {
			wp_register_script(
				'wpss-admin',
				\WPSS_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
				\WPSS_VERSION,
				true
			);
		}

		wp_enqueue_script( 'wpss-admin' );
	}

	/**
	 * Get withdrawals with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function get_withdrawals( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'status'   => '',
		);

		$args   = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Build query.
		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'w.status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		// Count total.
		$count_query = "SELECT COUNT(*) FROM {$table} w WHERE {$where_clause}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $values
			? (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) )
			: (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get withdrawals.
		$query_values   = $values;
		$query_values[] = $args['per_page'];
		$query_values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$withdrawals = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT w.*, u.display_name as vendor_name, u.user_email as vendor_email
					FROM {$table} w
					LEFT JOIN {$wpdb->users} u ON w.vendor_id = u.ID
					WHERE {$where_clause}
					ORDER BY w.created_at DESC
					LIMIT %d OFFSET %d",
					$query_values
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$withdrawals = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT w.*, u.display_name as vendor_name, u.user_email as vendor_email
					FROM {$table} w
					LEFT JOIN {$wpdb->users} u ON w.vendor_id = u.ID
					ORDER BY w.created_at DESC
					LIMIT %d OFFSET %d",
					$args['per_page'],
					$offset
				)
			);
		}

		return array(
			'withdrawals' => $withdrawals,
			'total'       => $total,
			'pages'       => (int) ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get withdrawal statistics.
	 *
	 * @return array
	 */
	private function get_stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
				SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
				SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount
			FROM {$table}"
		);

		return array(
			'total'            => (int) ( $stats->total ?? 0 ),
			'pending'          => (int) ( $stats->pending ?? 0 ),
			'approved'         => (int) ( $stats->approved ?? 0 ),
			'completed'        => (int) ( $stats->completed ?? 0 ),
			'rejected'         => (int) ( $stats->rejected ?? 0 ),
			'pending_amount'   => (float) ( $stats->pending_amount ?? 0 ),
			'completed_amount' => (float) ( $stats->completed_amount ?? 0 ),
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$result      = $this->get_withdrawals(
			array(
				'page'   => $current_page,
				'status' => $status,
			)
		);
		$withdrawals = $result['withdrawals'];
		$total       = $result['total'];
		$total_pages = $result['pages'];
		$stats       = $this->get_stats();
		$statuses    = EarningsService::get_withdrawal_statuses();
		$methods     = EarningsService::get_withdrawal_methods();
		?>
		<div class="wrap wpss-listing-page wpss-withdrawals-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawals', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Stats Cards -->
			<div class="wpss-listing-stats wpss-withdrawal-stats">
				<div class="wpss-stat-card wpss-stat-pending">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
					<span class="wpss-stat-amount"><?php echo esc_html( wpss_format_price( $stats['pending_amount'] ) ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-approved">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['approved'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Approved', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-completed">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['completed'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
					<span class="wpss-stat-amount"><?php echo esc_html( wpss_format_price( $stats['completed_amount'] ) ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-rejected">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $stats['rejected'] ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<!-- Filter + content unified card -->
			<div class="wpss-list-card">
				<div class="wpss-list-card__filters wpss-withdrawals-filters">
					<ul class="subsubsub">
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals' ) ); ?>"
								class="<?php echo $status === '' ? 'current' : ''; ?>">
								<?php esc_html_e( 'All', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['total'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals&status=pending' ) ); ?>"
								class="<?php echo $status === 'pending' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Pending', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['pending'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals&status=approved' ) ); ?>"
								class="<?php echo $status === 'approved' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Approved', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['approved'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals&status=completed' ) ); ?>"
								class="<?php echo $status === 'completed' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Completed', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['completed'] ); ?>)</span>
							</a> |
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals&status=rejected' ) ); ?>"
								class="<?php echo $status === 'rejected' ? 'current' : ''; ?>">
								<?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?>
								<span class="count">(<?php echo esc_html( $stats['rejected'] ); ?>)</span>
							</a>
						</li>
					</ul>
				</div>

				<div class="wpss-list-card__body">
			<?php if ( empty( $withdrawals ) ) : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<?php echo \WPSellServices\Services\Icon::render( 'banknote' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No withdrawals yet', 'wp-sell-services' ); ?></h2>
					<p class="wpss-empty-state__body"><?php esc_html_e( 'When vendors request a payout, their withdrawal requests appear here for approval.', 'wp-sell-services' ); ?></p>
					<p class="wpss-empty-state__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings#payouts' ) ); ?>" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Payout settings', 'wp-sell-services' ); ?></a>
						<a href="https://wbcomdesigns.com/docs/wp-sell-services/withdrawals-wpss" class="wpss-empty-state__learn" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'wp-sell-services' ); ?></a>
					</p>
				</div>
			<?php else : ?>
			<!-- Withdrawals Table -->
			<table class="wp-list-table widefat fixed striped wpss-withdrawals-table">
				<thead>
					<tr>
						<th scope="col" class="column-id"><?php esc_html_e( 'ID', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-vendor"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-amount"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-method"><?php esc_html_e( 'Method', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-date"><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $withdrawals as $withdrawal ) : ?>
						<?php $this->render_withdrawal_row( $withdrawal, $statuses, $methods ); ?>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col" class="column-id"><?php esc_html_e( 'ID', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-vendor"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-amount"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-method"><?php esc_html_e( 'Method', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th scope="col" class="column-date"><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
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
			<?php endif; // withdrawals empty check. ?>
				</div><!-- .wpss-list-card__body -->
			</div><!-- .wpss-list-card -->
		</div>

		<!-- Process Withdrawal Modal -->
		<div id="wpss-withdrawal-modal" class="wpss-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpss-modal-title">
			<div class="wpss-modal-content wpss-modal-small">
				<span class="wpss-modal-close" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</span>
				<h2 id="wpss-modal-title"><?php esc_html_e( 'Process Withdrawal', 'wp-sell-services' ); ?></h2>
				<form id="wpss-process-withdrawal-form">
					<input type="hidden" name="withdrawal_id" id="wpss-withdrawal-id">
					<input type="hidden" name="action_type" id="wpss-action-type">

					<p id="wpss-modal-description"></p>

					<div class="wpss-form-field">
						<label for="wpss-admin-note"><?php esc_html_e( 'Admin Note (Optional)', 'wp-sell-services' ); ?></label>
						<textarea name="admin_note" id="wpss-admin-note" rows="3" class="large-text"></textarea>
					</div>

					<div class="wpss-modal-actions">
						<button type="button" class="button wpss-modal-cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
						<button type="submit" class="button button-primary" id="wpss-modal-submit">
							<?php esc_html_e( 'Confirm', 'wp-sell-services' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<style>
			/* Stat-card, stat-number, stat-label, stat-amount, filter-row,
			   and status colors now live in assets/css/admin.css via the
			   shared `.wpss-listing-stats` rules. Keep only withdrawal-page
			   specific utilities below. */

			.wpss-withdrawals-table .column-id { width: 6%; }
			.wpss-withdrawals-table .column-vendor { width: 20%; }
			.wpss-withdrawals-table .column-amount { width: 12%; text-align: right; }
			.wpss-withdrawals-table .column-method { width: 12%; }
			.wpss-withdrawals-table .column-status { width: 12%; }
			.wpss-withdrawals-table .column-date { width: 15%; }
			.wpss-withdrawals-table .column-actions { width: 18%; }

			.wpss-vendor-info {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.wpss-vendor-avatar {
				width: 32px;
				height: 32px;
				border-radius: 50%;
			}
			.wpss-vendor-name {
				font-weight: 500;
			}
			.wpss-vendor-email {
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
			.wpss-status-pending { background: #fff3cd; color: #856404; }
			.wpss-status-approved { background: #d1e7f3; color: #0a4b78; }
			.wpss-status-completed { background: #d4edda; color: #155724; }
			.wpss-status-rejected { background: #f8d7da; color: #721c24; }

			.wpss-withdrawal-actions {
				display: flex;
				gap: 5px;
				flex-wrap: wrap;
			}
			.wpss-withdrawal-actions .button {
				padding: 2px 8px;
				font-size: 12px;
			}

			.wpss-withdrawal-details {
				margin-top: 5px;
				font-size: 12px;
				color: #646970;
			}
			.wpss-withdrawal-details code {
				font-size: 11px;
				background: #f0f0f1;
				padding: 1px 4px;
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
				margin: 10% auto;
				padding: 20px;
				border-radius: 4px;
				width: 90%;
				max-width: 500px;
				position: relative;
			}
			.wpss-modal-small {
				max-width: 400px;
			}
			.wpss-modal-close {
				position: absolute;
				right: 15px;
				top: 10px;
				font-size: 28px;
				font-weight: bold;
				cursor: pointer;
				color: #646970;
			}
			.wpss-modal-close:hover { color: #1d2327; }
			.wpss-modal h2 {
				margin-top: 0;
				padding-right: 30px;
			}
			.wpss-form-field {
				margin-bottom: 15px;
			}
			.wpss-form-field label {
				display: block;
				margin-bottom: 5px;
				font-weight: 500;
			}
			.wpss-modal-actions {
				display: flex;
				justify-content: flex-end;
				gap: 10px;
				margin-top: 20px;
			}

		</style>

		<script>
		function wpssAdminNotice(msg, type) {
			type = type || 'error';
			var cls = type === 'success' ? 'notice-success' : 'notice-error';
			var $notice = jQuery('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
			jQuery('.wrap h1, .wrap h2').first().after($notice);
			$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
			setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
		}
		jQuery(function($) {
			var wpssWithdrawals = 
			<?php
			echo wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpss_withdrawals_admin' ),
					'i18n'    => array(
						'confirmApprove'  => __( 'Are you sure you want to approve this withdrawal?', 'wp-sell-services' ),
						'confirmReject'   => __( 'Are you sure you want to reject this withdrawal?', 'wp-sell-services' ),
						'confirmComplete' => __( 'Are you sure you want to mark this withdrawal as completed?', 'wp-sell-services' ),
						'loading'         => __( 'Processing...', 'wp-sell-services' ),
						'error'           => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					),
				)
			);
			?>
			;

			var $modal = $('#wpss-withdrawal-modal');
			var $form = $('#wpss-process-withdrawal-form');

			// Open modal for processing
			$('.wpss-process-withdrawal').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var withdrawalId = $btn.data('withdrawal-id');
				var action = $btn.data('action');
				var amount = $btn.data('amount');
				var vendor = $btn.data('vendor');

				$('#wpss-withdrawal-id').val(withdrawalId);
				$('#wpss-action-type').val(action);
				$('#wpss-admin-note').val('');

				var actionLabels = {
					'approve': '<?php echo esc_js( __( 'Approve Withdrawal', 'wp-sell-services' ) ); ?>',
					'complete': '<?php echo esc_js( __( 'Mark as Completed', 'wp-sell-services' ) ); ?>',
					'reject': '<?php echo esc_js( __( 'Reject Withdrawal', 'wp-sell-services' ) ); ?>'
				};

				var descriptions = {
					'approve': '<?php echo esc_js( __( 'Approve this withdrawal request for', 'wp-sell-services' ) ); ?> ' + amount + ' <?php echo esc_js( __( 'from', 'wp-sell-services' ) ); ?> ' + vendor + '?',
					'complete': '<?php echo esc_js( __( 'Mark this withdrawal as completed. This means payment has been sent to', 'wp-sell-services' ) ); ?> ' + vendor + '.',
					'reject': '<?php echo esc_js( __( 'Reject this withdrawal request from', 'wp-sell-services' ) ); ?> ' + vendor + '? <?php echo esc_js( __( 'The funds will be returned to their available balance.', 'wp-sell-services' ) ); ?>'
				};

				$('#wpss-modal-title').text(actionLabels[action] || '<?php echo esc_js( __( 'Process Withdrawal', 'wp-sell-services' ) ); ?>');
				$('#wpss-modal-description').text(descriptions[action] || '');

				if (action === 'reject') {
					$('#wpss-modal-submit').removeClass('button-primary').addClass('button-link-delete');
				} else {
					$('#wpss-modal-submit').addClass('button-primary').removeClass('button-link-delete');
				}

				$modal.show();
			});

			// Close modal
			$('.wpss-modal-close, .wpss-modal-cancel').on('click', function() {
				$modal.hide();
			});

			$modal.on('click', function(e) {
				if (e.target === this) {
					$modal.hide();
				}
			});

			// Submit form
			$form.on('submit', function(e) {
				e.preventDefault();

				var $submit = $('#wpss-modal-submit');
				var originalText = $submit.text();

				$submit.prop('disabled', true).text(wpssWithdrawals.i18n.loading);

				$.ajax({
					url: wpssWithdrawals.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_process_withdrawal',
						nonce: wpssWithdrawals.nonce,
						withdrawal_id: $('#wpss-withdrawal-id').val(),
						action_type: $('#wpss-action-type').val(),
						admin_note: $('#wpss-admin-note').val()
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							wpssAdminNotice(response.data.message || wpssWithdrawals.i18n.error, 'error');
							$submit.prop('disabled', false).text(originalText);
						}
					},
					error: function() {
						wpssAdminNotice(wpssWithdrawals.i18n.error, 'error');
						$submit.prop('disabled', false).text(originalText);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render withdrawal table row.
	 *
	 * @param object $withdrawal Withdrawal data.
	 * @param array  $statuses   Status labels.
	 * @param array  $methods    Method labels.
	 * @return void
	 */
	private function render_withdrawal_row( object $withdrawal, array $statuses, array $methods ): void {
		$avatar  = get_avatar_url( $withdrawal->vendor_id, array( 'size' => 64 ) );
		$details = json_decode( $withdrawal->details ?? '{}', true ) ?: array();
		$status  = $withdrawal->status ?? 'pending';
		?>
		<tr data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>">
			<td class="column-id">
				<strong>#<?php echo esc_html( $withdrawal->id ); ?></strong>
			</td>
			<td class="column-vendor">
				<div class="wpss-vendor-info">
					<img src="<?php echo esc_url( $avatar ); ?>" alt="" class="wpss-vendor-avatar">
					<div>
						<div class="wpss-vendor-name">
							<a href="<?php echo esc_url( get_edit_user_link( $withdrawal->vendor_id ) ); ?>">
								<?php echo esc_html( $withdrawal->vendor_name ?? __( 'Unknown', 'wp-sell-services' ) ); ?>
							</a>
						</div>
						<div class="wpss-vendor-email">
							<?php echo esc_html( $withdrawal->vendor_email ?? '' ); ?>
						</div>
					</div>
				</div>
			</td>
			<td class="column-amount" data-colname="<?php esc_attr_e( 'Amount', 'wp-sell-services' ); ?>">
				<strong><?php echo esc_html( wpss_format_price( (float) $withdrawal->amount ) ); ?></strong>
			</td>
			<td class="column-method" data-colname="<?php esc_attr_e( 'Method', 'wp-sell-services' ); ?>">
				<?php echo esc_html( $methods[ $withdrawal->method ] ?? ucfirst( $withdrawal->method ) ); ?>
				<?php if ( ! empty( $details ) ) : ?>
					<div class="wpss-withdrawal-details">
						<?php
						// Show relevant details based on method.
						if ( $withdrawal->method === 'paypal' && ! empty( $details['email'] ) ) {
							echo '<code>' . esc_html( $details['email'] ) . '</code>';
						} elseif ( $withdrawal->method === 'bank_transfer' ) {
							if ( ! empty( $details['bank_name'] ) ) {
								echo esc_html( $details['bank_name'] );
							}
							if ( ! empty( $details['account_number'] ) ) {
								echo ' <code>***' . esc_html( substr( $details['account_number'], -4 ) ) . '</code>';
							}
						}
						?>
					</div>
				<?php endif; ?>
			</td>
			<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
				<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( $statuses[ $status ] ?? ucfirst( $status ) ); ?>
				</span>
			</td>
			<td class="column-date" data-colname="<?php esc_attr_e( 'Date', 'wp-sell-services' ); ?>">
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $withdrawal->created_at ) ) ); ?>
				<?php if ( ! empty( $withdrawal->processed_at ) ) : ?>
					<div class="wpss-withdrawal-details">
						<?php
						printf(
							/* translators: %s: date */
							esc_html__( 'Processed: %s', 'wp-sell-services' ),
							esc_html( date_i18n( get_option( 'date_format' ), strtotime( $withdrawal->processed_at ) ) )
						);
						?>
					</div>
				<?php endif; ?>
			</td>
			<td class="column-actions">
				<div class="wpss-withdrawal-actions">
					<?php if ( $status === 'pending' ) : ?>
						<button type="button" class="button button-primary wpss-process-withdrawal"
								data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>"
								data-action="approve"
								data-amount="<?php echo esc_attr( wpss_format_price( (float) $withdrawal->amount ) ); ?>"
								data-vendor="<?php echo esc_attr( $withdrawal->vendor_name ); ?>">
							<?php esc_html_e( 'Approve', 'wp-sell-services' ); ?>
						</button>
						<button type="button" class="button wpss-process-withdrawal"
								data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>"
								data-action="reject"
								data-amount="<?php echo esc_attr( wpss_format_price( (float) $withdrawal->amount ) ); ?>"
								data-vendor="<?php echo esc_attr( $withdrawal->vendor_name ); ?>">
							<?php esc_html_e( 'Reject', 'wp-sell-services' ); ?>
						</button>
					<?php elseif ( $status === 'approved' ) : ?>
						<button type="button" class="button button-primary wpss-process-withdrawal"
								data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>"
								data-action="complete"
								data-amount="<?php echo esc_attr( wpss_format_price( (float) $withdrawal->amount ) ); ?>"
								data-vendor="<?php echo esc_attr( $withdrawal->vendor_name ); ?>">
							<?php esc_html_e( 'Mark Completed', 'wp-sell-services' ); ?>
						</button>
						<button type="button" class="button wpss-process-withdrawal"
								data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>"
								data-action="reject"
								data-amount="<?php echo esc_attr( wpss_format_price( (float) $withdrawal->amount ) ); ?>"
								data-vendor="<?php echo esc_attr( $withdrawal->vendor_name ); ?>">
							<?php esc_html_e( 'Reject', 'wp-sell-services' ); ?>
						</button>
					<?php else : ?>
						<span class="wpss-withdrawal-details">&mdash;</span>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * AJAX handler for processing withdrawal.
	 *
	 * @return void
	 */
	public function ajax_process_withdrawal(): void {
		check_ajax_referer( 'wpss_withdrawals_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$withdrawal_id = absint( $_POST['withdrawal_id'] ?? 0 );
		$action_type   = sanitize_key( $_POST['action_type'] ?? '' );
		$admin_note    = sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ?? '' ) );

		if ( ! $withdrawal_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid withdrawal ID.', 'wp-sell-services' ) ) );
		}

		// Map action to status.
		$status_map = array(
			'approve'  => EarningsService::WITHDRAWAL_APPROVED,
			'complete' => EarningsService::WITHDRAWAL_COMPLETED,
			'reject'   => EarningsService::WITHDRAWAL_REJECTED,
		);

		if ( ! isset( $status_map[ $action_type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'wp-sell-services' ) ) );
		}

		$result = $this->earnings_service->process_withdrawal(
			$withdrawal_id,
			$status_map[ $action_type ],
			$admin_note
		);

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}
}
