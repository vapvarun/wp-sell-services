<?php
/**
 * Review Moderation Page
 *
 * Admin queue for moderating customer reviews (flow #6). Reviews are stored in
 * the `{prefix}wpss_reviews` custom table with a `status` column
 * (`pending` / `approved` / `rejected`); only approved reviews surface on the
 * frontend (see {@see \WPSellServices\Services\ReviewService}). When the
 * `wpss_moderate_reviews` option is on, newly created reviews land as
 * `pending` and wait here for an admin decision.
 *
 * This page follows the same listing-page pattern as
 * {@see \WPSellServices\Admin\Pages\ServiceModerationPage}: a stats strip, a
 * filter card with status tabs, and a WP list table. It consumes the typed
 * review row shape produced by the schema in
 * {@see \WPSellServices\Database\SchemaManager} (the `wpss_reviews` table) and
 * the {@see \WPSellServices\Models\Review} status constants.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

use WPSellServices\Models\Review;
use WPSellServices\Services\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * Review Moderation Page Class.
 *
 * @since 1.2.0
 */
class ReviewModerationPage {

	/**
	 * Page hook suffix, captured at registration for screen-scoped enqueues.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Reviews per page in the queue.
	 */
	private const PER_PAGE = 20;

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 16 );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );

		// AJAX moderation actions (admin one-click utilities; nonce + capability guarded).
		add_action( 'wp_ajax_wpss_approve_review', array( $this, 'ajax_approve_review' ) );
		add_action( 'wp_ajax_wpss_reject_review', array( $this, 'ajax_reject_review' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$pending_count = $this->get_status_counts()[ Review::STATUS_PENDING ] ?? 0;
		$menu_title    = __( 'Review Moderation', 'wp-sell-services' );

		if ( $pending_count > 0 ) {
			$menu_title .= sprintf( ' <span class="awaiting-mod">%d</span>', (int) $pending_count );
		}

		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'Review Moderation', 'wp-sell-services' ),
			$menu_title,
			'manage_options',
			'wpss-review-moderation',
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			$this->hook = $hook;
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
				'id'      => 'wpss-review-overview',
				'title'   => __( 'Overview', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'When review moderation is enabled, customer reviews are held as pending until an admin approves them. Only approved reviews are shown on service and vendor pages and counted toward ratings. Use the status tabs to browse pending, approved, and rejected reviews.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-review-actions',
				'title'   => __( 'Available actions', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Approve a review to publish it and include it in the service and vendor rating averages. Reject a review to keep it hidden. Review moderation is toggled from Sell Services > Settings; with it off, reviews are auto-approved on submission.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'wp-sell-services' ) . '</strong></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/" target="_blank" rel="noopener">' . esc_html__( 'Plugin docs', 'wp-sell-services' ) . '</a></p>'
		);
	}

	/**
	 * Enqueue page assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );
		wp_enqueue_script( 'wpss-admin' );

		// Render Lucide glyphs already on the page (shared admin icon loader).
		if ( wp_script_is( 'wpss-admin-icons', 'registered' ) ) {
			wp_enqueue_script( 'wpss-admin-icons' );
		}

		// Per-page behaviour without a raw inline <script> tag: config + a small
		// delegated handler are attached to the shared admin bundle via
		// wp_add_inline_script(), so both still flow through the enqueue
		// pipeline (sanctioned escape hatch per the design-system F2 rule).
		wp_add_inline_script(
			'wpss-admin',
			'window.wpssReviewModeration = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpss_review_moderation' ),
					'i18n'    => array(
						'error' => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					),
				)
			) . ';',
			'before'
		);

		wp_add_inline_script( 'wpss-admin', $this->get_inline_handler(), 'after' );
	}

	/**
	 * Delegated approve/reject handler, delivered through the enqueue pipeline.
	 *
	 * No native alert/confirm/prompt is used (design-system rule): moderation
	 * is a one-click, reversible action with toast feedback. On success the row
	 * is reloaded so the status tab counts stay accurate.
	 *
	 * @return string
	 */
	private function get_inline_handler(): string {
		return <<<'JS'
( function () {
	'use strict';
	var cfg = window.wpssReviewModeration || {};
	function notify( msg, type ) {
		if ( typeof window.wpssToast === 'function' ) {
			window.wpssToast( msg, type );
		}
	}
	function moderate( action, reviewId, button ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'review_id', reviewId );
		body.append( 'nonce', cfg.nonce || '' );
		button.disabled = true;
		window.fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( res ) {
			if ( res && res.success ) {
				notify( ( res.data && res.data.message ) || '', 'success' );
				window.location.reload();
			} else {
				notify( ( res && res.data && res.data.message ) || cfg.i18n.error, 'error' );
				button.disabled = false;
			}
		} ).catch( function () {
			notify( cfg.i18n.error, 'error' );
			button.disabled = false;
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		var approve = e.target.closest( '.wpss-approve-review' );
		var reject = e.target.closest( '.wpss-reject-review' );
		if ( approve ) {
			e.preventDefault();
			moderate( 'wpss_approve_review', approve.getAttribute( 'data-review' ), approve );
		} else if ( reject ) {
			e.preventDefault();
			moderate( 'wpss_reject_review', reject.getAttribute( 'data-review' ), reject );
		}
	} );
} )();
JS;
	}

	/**
	 * Render the review moderation queue.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-sell-services' ) );
		}

		$is_enabled    = ! empty( get_option( 'wpss_vendor', array() )['moderate_reviews'] ?? '' );
		$status_filter = $this->read_status_filter();
		$current_page  = $this->read_paged();
		$search        = $this->read_search();

		$counts = $this->get_status_counts();
		$result = $this->query_reviews( $status_filter, $current_page, $search );
		$rows   = $result['rows'];
		$total  = $result['total'];

		$total_pages = $total > 0 ? (int) ceil( $total / self::PER_PAGE ) : 0;
		?>
		<div class="wrap wpss-listing-page wpss-review-moderation-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Review Moderation', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( ! $is_enabled ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php
						printf(
							/* translators: %s: settings page URL. */
							wp_kses_post( __( 'Review moderation is currently <strong>disabled</strong>, so new reviews are auto-approved. You can still review existing entries below, or <a href="%s">enable moderation in Settings</a>.', 'wp-sell-services' ) ),
							esc_url( admin_url( 'admin.php?page=wpss-settings' ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="wpss-listing-stats wpss-review-moderation-stats">
				<div class="wpss-stat-card">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( (int) array_sum( $counts ) ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Reviews', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-pending">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( (int) ( $counts[ Review::STATUS_PENDING ] ?? 0 ) ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-approved">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( (int) ( $counts[ Review::STATUS_APPROVED ] ?? 0 ) ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Approved', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-rejected">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( (int) ( $counts[ Review::STATUS_REJECTED ] ?? 0 ) ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-list-card">
				<div class="wpss-list-card__filters">
					<ul class="subsubsub">
						<?php
						$tabs = array(
							'all'                   => __( 'All', 'wp-sell-services' ),
							Review::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
							Review::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
							Review::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
						);
						$last = array_key_last( $tabs );
						foreach ( $tabs as $key => $label ) :
							$count = 'all' === $key ? (int) array_sum( $counts ) : (int) ( $counts[ $key ] ?? 0 );
							?>
							<li>
								<a
									href="
									<?php
									echo esc_url(
										add_query_arg(
											array_filter(
												array(
													'status' => $key,
													's' => $search,
												),
												static fn( $v ) => '' !== $v
											),
											admin_url( 'admin.php?page=wpss-review-moderation' )
										)
									);
									?>
									"
									class="<?php echo $status_filter === $key ? 'current' : ''; ?>"
								>
									<?php echo esc_html( $label ); ?>
									<span class="count">(<?php echo esc_html( number_format_i18n( $count ) ); ?>)</span>
								</a><?php echo $key !== $last ? ' |' : ''; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<form class="wpss-review-search search-form" method="get">
						<input type="hidden" name="page" value="wpss-review-moderation">
						<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
						<label class="screen-reader-text" for="wpss-review-search-input"><?php esc_html_e( 'Search reviews by reviewer name', 'wp-sell-services' ); ?></label>
						<input
							type="search"
							id="wpss-review-search-input"
							name="s"
							value="<?php echo esc_attr( $search ); ?>"
							placeholder="<?php esc_attr_e( 'Search by reviewer or text&hellip;', 'wp-sell-services' ); ?>"
						>
						<button type="submit" class="button"><?php esc_html_e( 'Search', 'wp-sell-services' ); ?></button>
						<?php if ( '' !== $search ) : ?>
							<a class="button-link" href="<?php echo esc_url( add_query_arg( array( 'status' => $status_filter ), admin_url( 'admin.php?page=wpss-review-moderation' ) ) ); ?>"><?php esc_html_e( 'Clear', 'wp-sell-services' ); ?></a>
						<?php endif; ?>
					</form>
				</div>

				<div class="wpss-list-card__body">
					<?php if ( empty( $rows ) ) : ?>
						<div class="wpss-empty-state">
							<div class="wpss-empty-state__icon">
								<?php echo Icon::render( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns escaped markup. ?>
							</div>
							<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No reviews in this queue', 'wp-sell-services' ); ?></h2>
							<p class="wpss-empty-state__body"><?php esc_html_e( 'Customer reviews appear here after buyers rate completed orders. With moderation enabled, new reviews wait for your approval before going live.', 'wp-sell-services' ); ?></p>
						</div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped wpss-review-table">
							<thead>
								<tr>
									<th scope="col" class="column-rating"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-review"><?php esc_html_e( 'Review', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-reviewer"><?php esc_html_e( 'Reviewer', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-service"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-date"><?php esc_html_e( 'Submitted', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<?php $this->render_review_row( $row ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>

						<?php if ( $total_pages > 1 ) : ?>
							<div class="tablenav bottom">
								<div class="tablenav-pages">
									<span class="displaying-num">
										<?php
										printf(
											/* translators: %s: number of reviews. */
											esc_html( _n( '%s review', '%s reviews', $total, 'wp-sell-services' ) ),
											esc_html( number_format_i18n( $total ) )
										);
										?>
									</span>
									<span class="pagination-links">
										<?php
										echo wp_kses_post(
											(string) paginate_links(
												array(
													'base' => add_query_arg( 'paged', '%#%' ),
													'format' => '',
													'prev_text' => '&laquo;',
													'next_text' => '&raquo;',
													'total' => $total_pages,
													'current' => $current_page,
												)
											)
										);
										?>
									</span>
								</div>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div><!-- .wpss-list-card__body -->
			</div><!-- .wpss-list-card -->
		</div>
		<?php
	}

	/**
	 * Render a single review row.
	 *
	 * @param object $row Raw review DB row.
	 * @return void
	 */
	private function render_review_row( object $row ): void {
		$id          = (int) ( $row->id ?? 0 );
		$rating      = (int) ( $row->rating ?? 0 );
		$content     = (string) ( $row->review ?? '' );
		$status      = isset( $row->status ) ? (string) $row->status : Review::STATUS_PENDING;
		$reviewer_id = (int) ( $row->reviewer_id ?? $row->customer_id ?? 0 );
		$service_id  = (int) ( $row->service_id ?? 0 );
		$created_at  = isset( $row->created_at ) ? (string) $row->created_at : '';

		$reviewer      = $reviewer_id > 0 ? get_userdata( $reviewer_id ) : false;
		$reviewer_name = Review::resolve_reviewer_name( $reviewer_id, $row->reviewer_name ?? null );

		$service       = $service_id > 0 ? get_post( $service_id ) : null;
		$service_title = $service ? $service->post_title : '';

		$when = '';
		if ( '' !== $created_at ) {
			$timestamp = strtotime( $created_at );
			if ( false !== $timestamp ) {
				$when = (string) wp_date( get_option( 'date_format' ), $timestamp );
			}
		}

		$status_labels = Review::get_statuses();
		$status_label  = $status_labels[ $status ] ?? ucfirst( $status );
		?>
		<tr>
			<td class="column-rating">
				<span class="wpss-review-rating" aria-label="
				<?php
				echo esc_attr(
					sprintf(
						/* translators: %d: rating out of 5. */
						__( '%d out of 5 stars', 'wp-sell-services' ),
						$rating
					)
				);
				?>
				">
					<?php echo esc_html( str_repeat( "\u{2605}", max( 0, min( 5, $rating ) ) ) . str_repeat( "\u{2606}", max( 0, 5 - $rating ) ) ); ?>
				</span>
			</td>
			<td class="column-review">
				<?php if ( '' !== trim( $content ) ) : ?>
					<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $content ), 30 ) ); ?>
				<?php else : ?>
					<em><?php esc_html_e( 'No written review.', 'wp-sell-services' ); ?></em>
				<?php endif; ?>
			</td>
			<td class="column-reviewer">
				<?php if ( $reviewer ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_user_link( $reviewer_id ) ); ?>"><?php echo esc_html( $reviewer_name ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $reviewer_name ); ?>
				<?php endif; ?>
			</td>
			<td class="column-service">
				<?php if ( $service ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $service_id ) ); ?>"><?php echo esc_html( $service_title ); ?></a>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
			<td class="column-date"><?php echo esc_html( '' !== $when ? $when : $created_at ); ?></td>
			<td class="column-status">
				<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</td>
			<td class="column-actions">
				<?php if ( Review::STATUS_APPROVED !== $status ) : ?>
					<button
						type="button"
						class="button button-small wpss-approve-review"
						data-review="<?php echo esc_attr( (string) $id ); ?>"
					><?php esc_html_e( 'Approve', 'wp-sell-services' ); ?></button>
				<?php endif; ?>
				<?php if ( Review::STATUS_REJECTED !== $status ) : ?>
					<button
						type="button"
						class="button button-small wpss-reject-review"
						data-review="<?php echo esc_attr( (string) $id ); ?>"
					><?php esc_html_e( 'Reject', 'wp-sell-services' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Query reviews for the given status + page.
	 *
	 * Consumes the `wpss_reviews` table row shape directly (parity with
	 * {@see \WPSellServices\Services\ReviewService}). The page class owns its
	 * own moderation query because the queue is admin-only and the service
	 * layer exposes only frontend (approved) read paths.
	 *
	 * @param string $status Status filter (`all` or a Review status).
	 * @param int    $page   1-indexed page number.
	 * @return array{rows: array<int, object>, total: int}
	 */
	private function query_reviews( string $status, int $page, string $search = '' ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'wpss_reviews';
		$offset = ( $page - 1 ) * self::PER_PAGE;

		// Build the WHERE clause dynamically so status + reviewer search compose.
		$where  = array();
		$params = array();

		if ( 'all' !== $status ) {
			$where[]  = 'r.status = %s';
			$params[] = $status;
		}

		// Search matches the migrated guest name, the registered reviewer's
		// display_name, or the review text. The users JOIN is added only when
		// searching so the default queue keeps its single-table query.
		$join = '';
		if ( '' !== $search ) {
			$join     = "LEFT JOIN {$wpdb->users} u ON r.reviewer_id = u.ID";
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( r.reviewer_name LIKE %s OR u.display_name LIKE %s OR r.review LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = ! empty( $where ) ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} r {$join} {$where_sql}";
		$total     = (int) ( empty( $params )
			? $wpdb->get_var( $count_sql )
			: $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) );

		$rows_sql    = "SELECT r.* FROM {$table} r {$join} {$where_sql} ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( self::PER_PAGE, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$rows_params ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Get per-status review counts.
	 *
	 * @return array<string, int>
	 */
	private function get_status_counts(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		$counts = array(
			Review::STATUS_PENDING  => 0,
			Review::STATUS_APPROVED => 0,
			Review::STATUS_REJECTED => 0,
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$key = isset( $row->status ) ? (string) $row->status : '';
				if ( isset( $counts[ $key ] ) ) {
					$counts[ $key ] = (int) $row->total;
				}
			}
		}

		return $counts;
	}

	/**
	 * Read and validate the status filter from the query string.
	 *
	 * @return string
	 */
	private function read_status_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only listing filter; no state change.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : Review::STATUS_PENDING;

		$allowed = array(
			'all',
			Review::STATUS_PENDING,
			Review::STATUS_APPROVED,
			Review::STATUS_REJECTED,
		);

		return in_array( $status, $allowed, true ) ? $status : Review::STATUS_PENDING;
	}

	/**
	 * Read the reviewer search term from the query string.
	 *
	 * @return string
	 */
	private function read_search(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only listing filter; no state change.
		return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	}

	/**
	 * Read the current page number from the query string.
	 *
	 * @return int
	 */
	private function read_paged(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination; no state change.
		return isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	}

	/**
	 * AJAX: approve a review.
	 *
	 * @return void
	 */
	public function ajax_approve_review(): void {
		$this->set_review_status( Review::STATUS_APPROVED );
	}

	/**
	 * AJAX: reject a review.
	 *
	 * @return void
	 */
	public function ajax_reject_review(): void {
		$this->set_review_status( Review::STATUS_REJECTED );
	}

	/**
	 * Shared moderation mutation for both AJAX actions.
	 *
	 * Capability is checked before the nonce so an unprivileged caller never
	 * reaches the state-changing branch.
	 *
	 * @param string $new_status Target review status.
	 * @return void
	 */
	private function set_review_status( string $new_status ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ), 403 );
		}

		check_ajax_referer( 'wpss_review_moderation', 'nonce' );

		$review_id = isset( $_POST['review_id'] ) ? absint( wp_unslash( $_POST['review_id'] ) ) : 0;
		if ( $review_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid review.', 'wp-sell-services' ) ), 400 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array( 'status' => $new_status ),
			array( 'id' => $review_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not update the review.', 'wp-sell-services' ) ), 500 );
		}

		/**
		 * Fires after an admin moderates a review from the queue.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $review_id  Moderated review ID.
		 * @param string $new_status New review status.
		 */
		do_action( 'wpss_review_moderated', $review_id, $new_status );

		wp_send_json_success(
			array(
				'message' => Review::STATUS_APPROVED === $new_status
					? __( 'Review approved.', 'wp-sell-services' )
					: __( 'Review rejected.', 'wp-sell-services' ),
				'status'  => $new_status,
			)
		);
	}
}
