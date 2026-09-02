<?php
/**
 * Reports admin page.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.5.1
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * The owner's queue for member-filed reports.
 *
 * ONE queue for every target type. A screen per type would be four places to
 * check and three to forget, and the question an owner actually asks is "what
 * needs my attention", not "what services were reported".
 *
 * Acting on the member is available from the row, because that is where the
 * decision is made. Making the owner note the member's name, navigate to a
 * different screen and find them again is how reports go unactioned.
 *
 * @since 1.5.1
 */
class ReportsPage {

	/**
	 * Rows per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 16 );
		add_action( 'admin_post_wpss_resolve_report', array( $this, 'handle_resolve' ) );
		add_action( 'admin_post_wpss_set_account_status', array( $this, 'handle_account_status' ) );
		add_action( 'admin_notices', array( $this, 'render_result_notice' ) );

		// The WordPress user profile is where an owner already goes to look
		// someone up, so account standing is shown and editable there too — not
		// only from the reports queue. Members are WordPress users; the control
		// belongs on the screen WordPress already gives them.
		add_action( 'edit_user_profile', array( $this, 'render_profile_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_field' ) );
	}

	/**
	 * Show the outcome of the last action.
	 *
	 * @return void
	 */
	public function render_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['wpss_result'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = sanitize_key( wp_unslash( $_GET['wpss_result'] ) );

		$messages = array(
			'resolved'           => array( 'success', __( 'Report closed.', 'wp-sell-services' ) ),
			'already'            => array( 'warning', __( 'Someone else already dealt with that report.', 'wp-sell-services' ) ),
			'invalid'            => array( 'error', __( 'That action was not recognised.', 'wp-sell-services' ) ),
			'is_admin'           => array( 'error', __( 'Administrators cannot be suspended from here.', 'wp-sell-services' ) ),
			'standing_active'    => array( 'success', __( 'Member restored. They can sell and buy again.', 'wp-sell-services' ) ),
			'standing_suspended' => array( 'success', __( 'Member suspended. They can still finish orders already paid for.', 'wp-sell-services' ) ),
			'standing_banned'    => array( 'success', __( 'Account closed. They can still finish orders already paid for.', 'wp-sell-services' ) ),
		);

		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $result ];

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Show account standing on the WordPress user profile.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public function render_profile_field( $user ): void {
		if ( ! current_user_can( 'manage_options' ) || user_can( $user->ID, 'manage_options' ) ) {
			return;
		}

		$current = wpss_get_account_status( (int) $user->ID );
		?>
		<h2><?php esc_html_e( 'Marketplace standing', 'wp-sell-services' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wpss_account_status"><?php esc_html_e( 'Account standing', 'wp-sell-services' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'wpss_profile_standing_' . $user->ID, 'wpss_profile_standing_nonce' ); ?>
					<select name="wpss_account_status" id="wpss_account_status">
						<option value="active" <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active', 'wp-sell-services' ); ?></option>
						<option value="suspended" <?php selected( $current, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'wp-sell-services' ); ?></option>
						<option value="banned" <?php selected( $current, 'banned' ); ?>><?php esc_html_e( 'Closed', 'wp-sell-services' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Suspended and closed members cannot list, bid, buy or start conversations, on the website or in the app. They can still finish orders a buyer has already paid for, so nobody loses work they paid for.', 'wp-sell-services' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save account standing from the profile screen.
	 *
	 * @param int $user_id User being saved.
	 * @return void
	 */
	public function save_profile_field( $user_id ): void {
		$user_id = (int) $user_id;

		if ( ! current_user_can( 'manage_options' ) || user_can( $user_id, 'manage_options' ) ) {
			return;
		}

		if (
			! isset( $_POST['wpss_profile_standing_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['wpss_profile_standing_nonce'] ) ),
				'wpss_profile_standing_' . $user_id
			)
		) {
			return;
		}

		$status = isset( $_POST['wpss_account_status'] ) ? sanitize_key( wp_unslash( $_POST['wpss_account_status'] ) ) : 'active';

		if ( ! in_array( $status, array( 'active', 'suspended', 'banned' ), true ) ) {
			return;
		}

		if ( 'active' === $status ) {
			delete_user_meta( $user_id, 'wpss_account_status' );
		} else {
			update_user_meta( $user_id, 'wpss_account_status', $status );
		}

		/** This action is documented in src/Admin/Pages/ReportsPage.php */
		do_action( 'wpss_account_status_changed', $user_id, $status );
	}

	/**
	 * Add the submenu entry.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$open       = $this->count_by_status( 'open' );
		$menu_title = __( 'Reports', 'wp-sell-services' );

		// The bubble is the whole point of putting this in the menu: an owner
		// who has to open a screen to discover there is nothing to do will stop
		// opening it, and then miss the day there is.
		if ( $open > 0 ) {
			$menu_title .= sprintf( ' <span class="awaiting-mod">%d</span>', (int) $open );
		}

		add_submenu_page(
			'wp-sell-services',
			__( 'Reports', 'wp-sell-services' ),
			$menu_title,
			'manage_options',
			'wpss-reports',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Count rows in one status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	private function count_by_status( string $status ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_reports';

		// COUNT(*) on an indexed column, never count( fetch_all() ) — this runs
		// on every admin page load for the menu bubble.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status )
		);
	}

	/**
	 * Render the queue.
	 *
	 * @return void
	 */
	public function render_page(): void {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-sell-services' ) );
		}

		$table = $wpdb->prefix . 'wpss_reports';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open';
		$status = in_array( $status, array( 'open', 'resolved', 'any' ), true ) ? $status : 'open';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$offset = ( $paged - 1 ) * self::PER_PAGE;

		if ( 'any' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					self::PER_PAGE,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$status,
					self::PER_PAGE,
					$offset
				)
			);
		}

		$rows        = $rows ?: array();
		$reasons     = wpss_get_report_reasons();
		$targets     = wpss_get_report_target_types();
		$open_count  = $this->count_by_status( 'open' );
		$done_count  = $this->count_by_status( 'resolved' );
		$total_pages = (int) ceil( $total / self::PER_PAGE );
		?>
		<div class="wrap wpss-listing-page wpss-reports-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Reports', 'wp-sell-services' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'What members have reported to you, newest first. Acting on a member here applies everywhere: on the website and in the app.', 'wp-sell-services' ); ?>
			</p>

			<div class="wpss-listing-stats wpss-reports-stats">
				<div class="wpss-stat-card wpss-stat-pending">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $open_count ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Needs review', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-completed">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $done_count ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Dealt with', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-list-card">
				<div class="wpss-list-card__filters wpss-reports-filters">
					<?php
					$filters = array(
						'open'     => __( 'Needs review', 'wp-sell-services' ),
						'resolved' => __( 'Dealt with', 'wp-sell-services' ),
						'any'      => __( 'All', 'wp-sell-services' ),
					);
					foreach ( $filters as $key => $label ) :
						$url = add_query_arg(
							array(
								'page'   => 'wpss-reports',
								'status' => $key,
							),
							admin_url( 'admin.php' )
						);
						?>
						<a href="<?php echo esc_url( $url ); ?>"
							class="button <?php echo $status === $key ? 'button-primary' : ''; ?>"
							<?php echo $status === $key ? 'aria-current="page"' : ''; ?>>
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="wpss-list-card__body">
				<?php if ( empty( $rows ) ) : ?>
					<div class="wpss-empty-state">
						<div class="wpss-empty-state__content">
							<h2 class="wpss-empty-state__title">
								<?php
								echo 'open' === $status
									? esc_html__( 'Nothing needs your attention', 'wp-sell-services' )
									: esc_html__( 'No reports here', 'wp-sell-services' );
								?>
							</h2>
							<p class="wpss-empty-state__body">
								<?php esc_html_e( 'Reports members file from the website or the app arrive here.', 'wp-sell-services' ); ?>
							</p>
						</div>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped wpss-reports-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Reported', 'wp-sell-services' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What', 'wp-sell-services' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Filed by', 'wp-sell-services' ); ?></th>
								<th scope="col"><?php esc_html_e( 'When', 'wp-sell-services' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$standing  = wpss_get_account_status( (int) $row->reported_user_id );
							$is_open   = 'open' === $row->status;
							$row_label = wpss_get_member_display_name( (int) $row->reported_user_id );
							?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Reported', 'wp-sell-services' ); ?>">
									<strong><?php echo esc_html( $row_label ); ?></strong>
									<?php if ( 'active' !== $standing ) : ?>
										<span class="wpss-badge wpss-badge--warning"><?php echo esc_html( $standing ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Reason', 'wp-sell-services' ); ?>"><?php echo esc_html( $reasons[ $row->reason ] ?? $row->reason ); ?></td>
								<td data-label="<?php esc_attr_e( 'What', 'wp-sell-services' ); ?>">
									<?php echo esc_html( $targets[ $row->target_type ] ?? $row->target_type ); ?>
									<?php if ( (int) $row->target_id ) : ?>
										<code>#<?php echo esc_html( (string) (int) $row->target_id ); ?></code>
									<?php endif; ?>
									<?php if ( '' !== (string) $row->details ) : ?>
										<p class="description"><?php echo esc_html( wp_trim_words( (string) $row->details, 24 ) ); ?></p>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Filed by', 'wp-sell-services' ); ?>"><?php echo esc_html( wpss_get_member_display_name( (int) $row->reporter_id ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'When', 'wp-sell-services' ); ?>">
									<?php
									// created_at is stored in site-local time, so it is
									// converted to GMT before being compared against
									// time(). Comparing a local string to a UTC
									// timestamp reads "3 hours ago" for something
									// filed a minute ago on any site not on UTC.
									echo esc_html(
										sprintf(
											/* translators: %s: human-readable time difference, e.g. "2 hours" */
											__( '%s ago', 'wp-sell-services' ),
											human_time_diff( strtotime( get_gmt_from_date( (string) $row->created_at ) ), time() )
										)
									);
									?>
								</td>
								<td class="wpss-reports-table__actions" data-label="<?php esc_attr_e( 'Actions', 'wp-sell-services' ); ?>">
									<?php if ( $is_open ) : ?>
										<?php $this->action_button( 'wpss_resolve_report', (int) $row->id, 'upheld', __( 'Uphold', 'wp-sell-services' ), 'button-primary' ); ?>
										<?php $this->action_button( 'wpss_resolve_report', (int) $row->id, 'dismissed', __( 'Dismiss', 'wp-sell-services' ), '' ); ?>
									<?php else : ?>
										<span class="wpss-badge"><?php echo esc_html( (string) $row->resolution ); ?></span>
									<?php endif; ?>

									<?php if ( $reported ) : ?>
										<?php if ( 'active' === $standing ) : ?>
											<?php $this->status_button( (int) $row->reported_user_id, 'suspended', __( 'Suspend member', 'wp-sell-services' ) ); ?>
											<?php $this->status_button( (int) $row->reported_user_id, 'banned', __( 'Close account', 'wp-sell-services' ) ); ?>
										<?php else : ?>
											<?php $this->status_button( (int) $row->reported_user_id, 'active', __( 'Restore member', 'wp-sell-services' ) ); ?>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 ) : ?>
						<nav class="tablenav wpss-reports-pagination" aria-label="<?php esc_attr_e( 'Reports pages', 'wp-sell-services' ); ?>">
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									paginate_links(
										array(
											'base'      => add_query_arg( 'paged', '%#%' ),
											'format'    => '',
											'current'   => $paged,
											'total'     => $total_pages,
											'prev_text' => __( '&laquo; Previous', 'wp-sell-services' ),
											'next_text' => __( 'Next &raquo;', 'wp-sell-services' ),
										)
									)
								);
								?>
							</div>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one resolve button as its own nonce-protected form.
	 *
	 * A form rather than a link: these change state, and a GET link is
	 * followable by a prefetcher, an email scanner or a crawler with an
	 * authenticated session.
	 *
	 * @param string $action     admin-post action.
	 * @param int    $report_id  Report ID.
	 * @param string $resolution Resolution key.
	 * @param string $label      Button label.
	 * @param string $extra_class Extra button class.
	 * @return void
	 */
	private function action_button( string $action, int $report_id, string $resolution, string $label, string $extra_class ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpss-inline-form">
			<?php wp_nonce_field( $action . '_' . $report_id ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="report_id" value="<?php echo esc_attr( (string) $report_id ); ?>">
			<input type="hidden" name="resolution" value="<?php echo esc_attr( $resolution ); ?>">
			<button type="submit" class="button <?php echo esc_attr( $extra_class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render one account-standing button.
	 *
	 * @param int    $user_id Member.
	 * @param string $status  Target standing.
	 * @param string $label   Button label.
	 * @return void
	 */
	private function status_button( int $user_id, string $status, string $label ): void {
		$user = get_userdata( $user_id );
		$name = $user ? $user->display_name : (string) $user_id;

		// Suspending or closing an account is not undoable from the member's
		// side and changes what they can do everywhere at once, so it asks
		// first. Restoring is safe and does not.
		$confirmations = array(
			'suspended' => sprintf(
				/* translators: %s: member display name */
				__( 'Suspend %s? They will not be able to list, bid, buy or start conversations. Orders already paid for can still be completed.', 'wp-sell-services' ),
				$name
			),
			'banned'    => sprintf(
				/* translators: %s: member display name */
				__( 'Close %s\'s account? Same restrictions as suspending, and it reads to them as permanent. Orders already paid for can still be completed.', 'wp-sell-services' ),
				$name
			),
		);

		$confirm = $confirmations[ $status ] ?? '';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpss-inline-form"
			<?php if ( '' !== $confirm ) : ?>
				data-wpss-confirm="<?php echo esc_attr( $confirm ); ?>"
			<?php endif; ?>>
			<?php wp_nonce_field( 'wpss_set_account_status_' . $user_id ); ?>
			<input type="hidden" name="action" value="wpss_set_account_status">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>">
			<input type="hidden" name="account_status" value="<?php echo esc_attr( $status ); ?>">
			<button type="submit" class="button <?php echo 'active' === $status ? '' : 'button-link-delete'; ?>">
				<?php echo esc_html( $label ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Close a report.
	 *
	 * @return void
	 */
	public function handle_resolve(): void {
		global $wpdb;

		$report_id = isset( $_POST['report_id'] ) ? absint( wp_unslash( $_POST['report_id'] ) ) : 0;

		check_admin_referer( 'wpss_resolve_report_' . $report_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-sell-services' ) );
		}

		$resolution = isset( $_POST['resolution'] ) ? sanitize_key( wp_unslash( $_POST['resolution'] ) ) : '';

		if ( ! in_array( $resolution, array( 'upheld', 'dismissed' ), true ) ) {
			$this->redirect_back( 'invalid' );
		}

		// Scoped to status = 'open', so two moderators working at once cannot
		// both close the same row with the second silently overwriting the
		// first one's decision.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$wpdb->prefix . 'wpss_reports',
			array(
				'status'      => 'resolved',
				'resolution'  => $resolution,
				'resolved_by' => get_current_user_id(),
				'resolved_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $report_id,
				'status' => 'open',
			),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);

		$this->redirect_back( $updated ? 'resolved' : 'already' );
	}

	/**
	 * Set a member's account standing.
	 *
	 * @return void
	 */
	public function handle_account_status(): void {
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

		check_admin_referer( 'wpss_set_account_status_' . $user_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-sell-services' ) );
		}

		$status = isset( $_POST['account_status'] ) ? sanitize_key( wp_unslash( $_POST['account_status'] ) ) : '';

		if ( ! in_array( $status, array( 'active', 'suspended', 'banned' ), true ) || ! get_userdata( $user_id ) ) {
			$this->redirect_back( 'invalid' );
		}

		// An owner cannot lock themselves or another administrator out of their
		// own marketplace by clicking a row action.
		if ( user_can( $user_id, 'manage_options' ) ) {
			$this->redirect_back( 'is_admin' );
		}

		if ( 'active' === $status ) {
			delete_user_meta( $user_id, 'wpss_account_status' );
		} else {
			update_user_meta( $user_id, 'wpss_account_status', $status );
		}

		/**
		 * Fires when an owner changes a member's account standing.
		 *
		 * @since 1.5.1
		 *
		 * @param int    $user_id Member.
		 * @param string $status  New standing.
		 */
		do_action( 'wpss_account_status_changed', $user_id, $status );

		$this->redirect_back( 'standing_' . $status );
	}

	/**
	 * Return to the queue with a result notice.
	 *
	 * @param string $result Result key.
	 * @return void
	 */
	private function redirect_back( string $result ): void {
		$referer = wp_get_referer();

		wp_safe_redirect(
			add_query_arg(
				'wpss_result',
				$result,
				$referer ? $referer : admin_url( 'admin.php?page=wpss-reports' )
			)
		);
		exit;
	}
}
