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
	 * Admin page hook suffix returned by add_submenu_page().
	 *
	 * Stored so enqueue_scripts() matches the REAL hook — the previous
	 * hardcoded 'wp-sell-services_page_wpss-withdrawals' never matched the
	 * actual 'sell-services_page_wpss-withdrawals' suffix WordPress derives
	 * from the parent menu title, so the page-specific enqueue was dead code.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

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
		add_action( 'wp_ajax_wpss_bulk_process_withdrawals', array( $this, 'ajax_bulk_process_withdrawals' ) );
		add_action( 'admin_post_wpss_export_withdrawals', array( $this, 'export_csv' ) );
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
			$this->hook_suffix = $hook;
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
		if ( '' === $this->hook_suffix || $this->hook_suffix !== $hook ) {
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
			wp_set_script_translations( 'wpss-admin', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );
		}

		wp_enqueue_script( 'wpss-admin' );

		// Page behaviour: modal actions, bulk apply (wpssConfirm), toasts.
		wp_enqueue_script(
			'wpss-admin-withdrawals',
			\WPSS_PLUGIN_URL . 'assets/js/admin-withdrawals.js',
			array( 'jquery', 'wpss-ui' ),
			\WPSS_VERSION,
			true
		);
		wp_set_script_translations( 'wpss-admin-withdrawals', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'wpss-admin-withdrawals',
			'wpssWithdrawals',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wpss_withdrawals_admin' ),
				'bulkNonce'        => wp_create_nonce( 'wpss_withdrawals_bulk' ),
				// Currency parts for the settlement total shown in the bulk
				// mark-paid confirmation. Same pair Admin::enqueue_scripts
				// localizes; wpss_format_price() always prefixes the symbol.
				'currencySymbol'   => wpss_get_currency_symbol(),
				'currencyDecimals' => wpss_get_currency_decimals(),
				'i18n'             => array(
					'loading'      => __( 'Processing…', 'wp-sell-services' ),
					'error'        => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'selectFirst'  => __( 'Select at least one withdrawal first.', 'wp-sell-services' ),
					/* translators: %action%: bulk action label, %count%: number of selected withdrawals. Placeholders are replaced in JS. */
					'bulkConfirm'  => __( '%action% %count% withdrawal(s)? This applies to every selected row.', 'wp-sell-services' ),
					// Second confirmation, shown ONLY for bulk mark-as-paid. The
					// plugin records the payout; it never sends the money on the
					// manual rail, and saying so is the whole point of this step.
					'settleTitle'  => __( 'Confirm you have already paid these vendors', 'wp-sell-services' ),
					/* translators: %total%: formatted settlement total, %vendors%: number of distinct vendors, %count%: number of withdrawals, %methods%: payout-method breakdown such as "PayPal x3, Bank Transfer x2". Placeholders are replaced in JS. */
					'settleBody'   => __( 'This settles %total% across %vendors% vendor(s) in %count% withdrawal(s) — %methods%. It marks the outstanding amount paid and debits each vendor wallet, but it does NOT send any money. Paying each vendor by their own method is your manual step, outside this site.', 'wp-sell-services' ),
					'settleAction' => __( 'Yes, mark as paid', 'wp-sell-services' ),
					'bulkLabels'   => array(
						'approve'  => __( 'Approve', 'wp-sell-services' ),
						'complete' => __( 'Mark as paid', 'wp-sell-services' ),
						'reject'   => __( 'Reject', 'wp-sell-services' ),
					),
					'titles'       => array(
						'approve'  => __( 'Approve Withdrawal', 'wp-sell-services' ),
						'complete' => __( 'Mark as Paid', 'wp-sell-services' ),
						'reject'   => __( 'Reject Withdrawal', 'wp-sell-services' ),
						'fallback' => __( 'Process Withdrawal', 'wp-sell-services' ),
					),
					'descriptions' => array(
						/* translators: %amount% / %vendor% placeholders are replaced in JS. */
						'approve'  => __( 'Approve this withdrawal request for %amount% from %vendor%?', 'wp-sell-services' ),
						/* translators: %vendor% placeholder is replaced in JS. */
						'complete' => __( 'Mark this withdrawal as paid. Confirm only after the %amount% payment has actually been sent to %vendor% — this debits their wallet balance.', 'wp-sell-services' ),
						/* translators: %vendor% placeholder is replaced in JS. */
						'reject'   => __( 'Reject this withdrawal request from %vendor%? The funds will be returned to their available balance.', 'wp-sell-services' ),
					),
				),
			)
		);
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
			'method'   => '',
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

		if ( $args['method'] ) {
			$where[]  = 'w.method = %s';
			$values[] = $args['method'];
		}

		$where_clause = implode( ' AND ', $where );

		// Count total.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_clause is built from hardcoded fragments with %s/%d placeholders only; user values pass through prepare() below.
		$count_query = "SELECT COUNT(*) FROM {$table} w WHERE {$where_clause}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_query has hardcoded fragments only.
		$total = $values
			? (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) )
			: (int) $wpdb->get_var( $count_query );
		// phpcs:enable

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$method = isset( $_GET['method'] ) ? sanitize_key( $_GET['method'] ) : '';

		$statuses = EarningsService::get_withdrawal_statuses();
		$methods  = EarningsService::get_withdrawal_methods();

		// Only filter on known values — an unknown slug silently matches nothing.
		if ( $method && ! isset( $methods[ $method ] ) ) {
			$method = '';
		}

		$result      = $this->get_withdrawals(
			array(
				'page'   => $current_page,
				'status' => $status,
				'method' => $method,
			)
		);
		$withdrawals = $result['withdrawals'];
		$total       = $result['total'];
		$total_pages = $result['pages'];
		$stats       = $this->get_stats();

		// Status filter links keep the method filter, and vice versa.
		$base_url = admin_url( 'admin.php?page=wpss-withdrawals' );
		if ( $method ) {
			$base_url = add_query_arg( 'method', $method, $base_url );
		}
		?>
		<div class="wrap wpss-listing-page wpss-withdrawals-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawals', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">
			<?php
			// Surface the last bulk-action report (incl. per-row failures) that
			// the post-action reload would otherwise have thrown away.
			$bulk_report_key = 'wpss_bulk_withdrawal_report_' . get_current_user_id();
			$bulk_report     = get_transient( $bulk_report_key );
			if ( $bulk_report ) {
				delete_transient( $bulk_report_key );
				printf(
					'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
					esc_html( (string) $bulk_report )
				);
			}
			?>

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
						<?php
						$status_links = array(
							''          => array( __( 'All', 'wp-sell-services' ), $stats['total'] ),
							'pending'   => array( __( 'Pending', 'wp-sell-services' ), $stats['pending'] ),
							'approved'  => array( __( 'Approved', 'wp-sell-services' ), $stats['approved'] ),
							'completed' => array( __( 'Completed', 'wp-sell-services' ), $stats['completed'] ),
							'rejected'  => array( __( 'Rejected', 'wp-sell-services' ), $stats['rejected'] ),
						);
						$last_key     = array_key_last( $status_links );
						foreach ( $status_links as $status_key => $link ) :
							$link_url = $status_key ? add_query_arg( 'status', $status_key, $base_url ) : $base_url;
							?>
						<li>
							<a href="<?php echo esc_url( $link_url ); ?>"
								class="<?php echo $status === $status_key ? 'current' : ''; ?>">
								<?php echo esc_html( $link[0] ); ?>
								<span class="count">(<?php echo esc_html( number_format_i18n( $link[1] ) ); ?>)</span>
							</a><?php echo $status_key === $last_key ? '' : ' |'; ?>
						</li>
						<?php endforeach; ?>
					</ul>

					<form method="get" class="wpss-withdrawals-method-filter">
						<input type="hidden" name="page" value="wpss-withdrawals">
						<?php if ( $status ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
						<?php endif; ?>
						<label for="wpss-filter-method" class="screen-reader-text"><?php esc_html_e( 'Filter by payout method', 'wp-sell-services' ); ?></label>
						<select name="method" id="wpss-filter-method">
							<option value=""><?php esc_html_e( 'All methods', 'wp-sell-services' ); ?></option>
							<?php foreach ( $methods as $method_key => $method_label ) : ?>
								<option value="<?php echo esc_attr( $method_key ); ?>" <?php selected( $method, $method_key ); ?>>
									<?php echo esc_html( $method_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-sell-services' ); ?></button>
					</form>

					<?php
					// Export the CURRENT filter. Export never changes a row's
					// status (MONEY-FLOW.md rule 2.4): pay offline first, then
					// come back and Mark paid as a separate deliberate act.
					$export_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'wpss_export_withdrawals',
								'status' => $status,
								'method' => $method,
							),
							admin_url( 'admin-post.php' )
						),
						'wpss_export_withdrawals'
					);
					?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button wpss-withdrawals-export"><?php esc_html_e( 'Export CSV', 'wp-sell-services' ); ?></a>
				</div>

				<div class="wpss-list-card__body">
			<?php if ( empty( $withdrawals ) ) : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<?php echo \WPSellServices\Services\Icon::render( 'banknote' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php if ( $status || $method ) : ?>
						<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No withdrawals match this filter', 'wp-sell-services' ); ?></h2>
						<p class="wpss-empty-state__body"><?php esc_html_e( 'Try a different status or payout method, or clear the filter to see every withdrawal.', 'wp-sell-services' ); ?></p>
						<p class="wpss-empty-state__actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-withdrawals' ) ); ?>" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Clear filter', 'wp-sell-services' ); ?></a>
						</p>
					<?php else : ?>
						<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No withdrawals yet', 'wp-sell-services' ); ?></h2>
						<p class="wpss-empty-state__body"><?php esc_html_e( 'When vendors request a payout, their withdrawal requests appear here for approval.', 'wp-sell-services' ); ?></p>
						<p class="wpss-empty-state__actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings#payouts' ) ); ?>" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Payout settings', 'wp-sell-services' ); ?></a>
							<a href="https://wbcomdesigns.com/docs/wp-sell-services/withdrawals-wpss" class="wpss-empty-state__learn" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'wp-sell-services' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
			<!-- Bulk actions row (mirrors the Service Moderation pattern). -->
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp-sell-services' ); ?></label>
					<select name="bulk_action" id="bulk-action-selector-top" class="wpss-withdrawals-bulk-select">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-sell-services' ); ?></option>
						<option value="approve"><?php esc_html_e( 'Approve', 'wp-sell-services' ); ?></option>
						<option value="complete"><?php esc_html_e( 'Mark as paid', 'wp-sell-services' ); ?></option>
						<option value="reject"><?php esc_html_e( 'Reject', 'wp-sell-services' ); ?></option>
					</select>
					<button type="button" class="button wpss-withdrawals-bulk-apply"><?php esc_html_e( 'Apply', 'wp-sell-services' ); ?></button>
				</div>
			</div>

			<!-- Withdrawals Table -->
			<table class="wp-list-table widefat fixed striped wpss-withdrawals-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-1" aria-label="<?php esc_attr_e( 'Select all withdrawals', 'wp-sell-services' ); ?>">
						</td>
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
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-2" aria-label="<?php esc_attr_e( 'Select all withdrawals', 'wp-sell-services' ); ?>">
						</td>
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
			<th scope="row" class="check-column">
				<label class="screen-reader-text" for="wpss-cb-withdrawal-<?php echo esc_attr( $withdrawal->id ); ?>"><?php esc_html_e( 'Select withdrawal', 'wp-sell-services' ); ?></label>
				<?php
				// data-amount / data-vendor-id feed the bulk mark-paid settlement
				// confirmation, which states the exact total and how many vendors
				// it settles before the admin commits.
				?>
				<input type="checkbox" id="wpss-cb-withdrawal-<?php echo esc_attr( $withdrawal->id ); ?>" name="withdrawal_ids[]" value="<?php echo esc_attr( $withdrawal->id ); ?>"
					data-amount="<?php echo esc_attr( (string) (float) $withdrawal->amount ); ?>"
					data-vendor-id="<?php echo esc_attr( (string) (int) $withdrawal->vendor_id ); ?>"
					data-method="<?php echo esc_attr( $methods[ $withdrawal->method ] ?? ucfirst( (string) $withdrawal->method ) ); ?>"
					<?php disabled( ! in_array( $status, array( 'pending', 'approved' ), true ) ); ?>>
			</th>
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
				<?php if ( ! empty( $withdrawal->is_auto ) ) : ?>
					<?php
					// is_auto was stored, and changed behaviour - a vendor cannot
					// cancel an automatic withdrawal - but was shown nowhere, so
					// an admin asked "why can't I cancel this?" had no way to
					// tell which ones were automatic. The docs had claimed this
					// badge existed for some time (Basecamp 10235851403).
					?>
					<span class="wpss-badge wpss-badge--default" title="<?php esc_attr_e( 'Raised automatically because the vendor passed the payout threshold. The vendor cannot cancel it.', 'wp-sell-services' ); ?>">
						<?php esc_html_e( 'Auto', 'wp-sell-services' ); ?>
					</span>
				<?php endif; ?>
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
				<span class="<?php echo esc_attr( wpss_status_class( $status ) ); ?>">
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
					<?php if ( in_array( $status, array( 'pending', 'approved' ), true ) ) : ?>
						<?php // Mark paid is THE terminal step of the manual rail — offered on ?>
						<?php // pending too, so the batch flow (export → pay offline → mark paid) ?>
						<?php // does not force a pointless approve click per row. ?>
						<button type="button" class="button button-primary wpss-process-withdrawal"
								data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>"
								data-action="complete"
								data-amount="<?php echo esc_attr( wpss_format_price( (float) $withdrawal->amount ) ); ?>"
								data-vendor="<?php echo esc_attr( $withdrawal->vendor_name ); ?>">
							<?php esc_html_e( 'Mark paid', 'wp-sell-services' ); ?>
						</button>
						<?php if ( 'pending' === $status ) : ?>
							<button type="button" class="button wpss-process-withdrawal"
									data-withdrawal-id="<?php echo esc_attr( $withdrawal->id ); ?>"
									data-action="approve"
									data-amount="<?php echo esc_attr( wpss_format_price( (float) $withdrawal->amount ) ); ?>"
									data-vendor="<?php echo esc_attr( $withdrawal->vendor_name ); ?>">
								<?php esc_html_e( 'Approve', 'wp-sell-services' ); ?>
							</button>
						<?php endif; ?>
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

	/**
	 * AJAX handler for bulk-processing withdrawals.
	 *
	 * Loops the selected IDs and routes each through the same
	 * `EarningsService::process_withdrawal()` path the per-row Approve /
	 * Reject / Mark-as-completed buttons use, so per-row side-effects
	 * (wallet ledger, audit log, vendor notification, idempotency) stay
	 * identical. Reports the per-id success/failure counts back to the UI.
	 *
	 * @return void
	 */
	public function ajax_bulk_process_withdrawals(): void {
		check_ajax_referer( 'wpss_withdrawals_bulk', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ), 403 );
		}

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IDs cast to int below.
		$ids_raw = isset( $_POST['withdrawal_ids'] ) ? (array) $_POST['withdrawal_ids'] : array();
		$ids     = array_values( array_filter( array_map( 'absint', $ids_raw ) ) );

		$status_map = array(
			'approve'  => EarningsService::WITHDRAWAL_APPROVED,
			'complete' => EarningsService::WITHDRAWAL_COMPLETED,
			'reject'   => EarningsService::WITHDRAWAL_REJECTED,
		);

		if ( ! isset( $status_map[ $bulk_action ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bulk action.', 'wp-sell-services' ) ) );
		}

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No withdrawals selected.', 'wp-sell-services' ) ) );
		}

		$success = 0;
		$failed  = array();
		foreach ( $ids as $withdrawal_id ) {
			$result = $this->earnings_service->process_withdrawal(
				$withdrawal_id,
				$status_map[ $bulk_action ],
				''
			);
			if ( ! empty( $result['success'] ) ) {
				++$success;
			} else {
				$failed[] = sprintf( '#%d (%s)', $withdrawal_id, (string) ( $result['message'] ?? __( 'failed', 'wp-sell-services' ) ) );
			}
		}

		$message = sprintf(
			/* translators: 1: number of successful withdrawals, 2: total selected */
			_n(
				'Processed %1$d of %2$d withdrawal.',
				'Processed %1$d of %2$d withdrawals.',
				count( $ids ),
				'wp-sell-services'
			),
			$success,
			count( $ids )
		);
		if ( ! empty( $failed ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: comma-separated list of failed IDs and reasons */
				__( 'Failed: %s', 'wp-sell-services' ),
				implode( ', ', $failed )
			);
		}

		// Persist the per-row report so it survives the JS success reload. The
		// success callback reloads the page, which discarded this message and
		// hid partial failures (e.g. "Failed: #12 (already paid)") from the admin.
		set_transient( 'wpss_bulk_withdrawal_report_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Stream the current withdrawal filter as CSV (admin-post handler).
	 *
	 * The manual payout rail: export the batch, pay by bank / PayPal bulk
	 * upload OUTSIDE the system, then come back and Mark paid. Columns cover
	 * what a bank transfer or a PayPal bulk-payments upload needs (recipient,
	 * amount, currency, account detail).
	 *
	 * Export NEVER mutates status (MONEY-FLOW.md rule 2.4) — an export that
	 * auto-marked rows paid would lie the moment a transfer failed. This
	 * method runs SELECTs only.
	 *
	 * Batched at 500 rows per query so a 100k-row table streams without
	 * exhausting memory; no LIMIT on the export itself — the admin asked for
	 * the batch, a silently truncated batch means unpaid vendors.
	 *
	 * @return void
	 */
	public function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-sell-services' ), 403 );
		}

		check_admin_referer( 'wpss_export_withdrawals' );

		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$method = isset( $_GET['method'] ) ? sanitize_key( $_GET['method'] ) : '';

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$where  = array( '1=1' );
		$values = array();

		if ( $status ) {
			$where[]  = 'w.status = %s';
			$values[] = $status;
		}

		if ( $method ) {
			$where[]  = 'w.method = %s';
			$values[] = $method;
		}

		$where_clause = implode( ' AND ', $where );
		$currency     = wpss_get_currency();

		$filename = 'wpss-payouts-' . ( $status ? $status . '-' : '' ) . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to the response body.
		$out = fopen( 'php://output', 'w' );

		fputcsv(
			$out,
			array(
				'withdrawal_id',
				'vendor_id',
				'vendor_name',
				'vendor_email',
				'amount',
				'currency',
				'method',
				'paypal_email',
				'bank_name',
				'account_number',
				'status',
				'requested_at',
				'processed_at',
			)
		);

		$batch_size = 500;
		$last_id    = 0;

		do {
			// Keyset pagination on id — stable while rows are appended, and no
			// O(N) OFFSET walk on large tables.
			$query_values   = $values;
			$query_values[] = $last_id;
			$query_values[] = $batch_size;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT w.*, u.display_name AS vendor_name, u.user_email AS vendor_email
					FROM {$table} w
					LEFT JOIN {$wpdb->users} u ON w.vendor_id = u.ID
					WHERE {$where_clause} AND w.id > %d
					ORDER BY w.id ASC
					LIMIT %d",
					$query_values
				)
			);

			$row_count = count( $rows );

			foreach ( $rows as $row ) {
				$last_id = (int) $row->id;
				$details = json_decode( (string) ( $row->details ?? '' ), true ) ?: array();

				fputcsv(
					$out,
					array(
						(int) $row->id,
						(int) $row->vendor_id,
						(string) ( $row->vendor_name ?? '' ),
						(string) ( $row->vendor_email ?? '' ),
						number_format( (float) $row->amount, 2, '.', '' ),
						$currency,
						(string) $row->method,
						(string) ( $details['email'] ?? '' ),
						(string) ( $details['bank_name'] ?? '' ),
						(string) ( $details['account_number'] ?? '' ),
						(string) $row->status,
						(string) $row->created_at,
						(string) ( $row->processed_at ?? '' ),
					)
				);
			}
		} while ( $row_count === $batch_size );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out );
		exit;
	}
}
