<?php
/**
 * Audit Log Viewer Page
 *
 * Read-only admin viewer for the forensic audit trail recorded by
 * {@see \WPSellServices\Services\AuditLogService}. The same data is exposed
 * over REST at `GET /wpss/v1/audit-log` (see
 * {@see \WPSellServices\API\AuditLogController}); this page renders it inside
 * wp-admin so site owners can answer "who did what, when, and was it forced?"
 * without leaving WordPress.
 *
 * The page is intentionally read-only: the audit log is a tamper-evident
 * record, so there are no edit/delete affordances here. Retention is managed
 * by the scheduled `AuditLogService::cleanup_expired()` job.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

use WPSellServices\Services\AuditLogService;
use WPSellServices\Services\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * Audit Log Page Class.
 *
 * Follows the shared listing-page pattern (stats strip + filter card +
 * WP list table) used by Withdrawals, Vendors, and Moderation.
 *
 * @since 1.2.0
 */
class AuditLogPage {

	/**
	 * Page hook suffix, captured at registration for screen-scoped enqueues.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Audit log service.
	 *
	 * @var AuditLogService
	 */
	private AuditLogService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new AuditLogService();
	}

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 25 );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Add submenu page.
	 *
	 * Registered as a hidden page (parent slug `null`) so it does not crowd the
	 * marketplace menu; it is reached from Settings and from contextual links.
	 * Admin highlights it under Settings via the parent plugin's submenu filter.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'Audit Log', 'wp-sell-services' ),
			__( 'Audit Log', 'wp-sell-services' ),
			'manage_options',
			'wpss-audit-log',
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
				'id'      => 'wpss-audit-overview',
				'title'   => __( 'Overview', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'The audit log records sensitive marketplace events — order status changes, dispute decisions, withdrawal processing, and any administrator override that bypassed the normal rules. Each entry captures who acted, their role, what changed (from/to), and whether the action was forced. The log is read-only by design so it can be trusted as a forensic record.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'wpss-audit-filters',
				'title'   => __( 'Filtering', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'Use the filter bar to narrow by object type (order, dispute, withdrawal, proposal), event type, actor, date range, or to show only forced events that bypassed natural rules. Filters combine, so you can isolate, for example, every forced order status change in the last week.', 'wp-sell-services' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'wp-sell-services' ) . '</strong></p>' .
			'<p><a href="https://wbcomdesigns.com/docs/wp-sell-services/" target="_blank" rel="noopener">' . esc_html__( 'Plugin docs', 'wp-sell-services' ) . '</a></p>'
		);
	}

	/**
	 * Enqueue page styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );

		// Render Lucide glyphs already on the page (shared admin icon loader).
		if ( wp_script_is( 'wpss-admin-icons', 'registered' ) ) {
			wp_enqueue_script( 'wpss-admin-icons' );
		}
	}

	/**
	 * Render the audit log viewer.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-sell-services' ) );
		}

		// Read-only viewer: the only request input is filter/pagination state.
		// No nonce is required for a read action that performs no mutation;
		// every value is sanitized before it reaches the service.
		$filters      = $this->read_filters();
		$current_page = max( 1, (int) ( $filters['page'] ?? 1 ) );

		$result = $this->service->query(
			array(
				'object_type' => $filters['object_type'],
				'event_type'  => $filters['event_type'],
				'actor_id'    => $filters['actor_id'] > 0 ? $filters['actor_id'] : null,
				'is_forced'   => $filters['is_forced'],
				'from_date'   => '' !== $filters['from_date'] ? $filters['from_date'] : null,
				'to_date'     => '' !== $filters['to_date'] ? $filters['to_date'] : null,
				'page'        => $current_page,
				'per_page'    => 30,
			)
		);

		$rows        = $result['rows'];
		$total       = (int) $result['total'];
		$per_page    = (int) $result['per_page'];
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;
		$forced      = $this->count_forced();
		?>
		<div class="wrap wpss-listing-page wpss-audit-log-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Audit Log', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<p class="description wpss-audit-intro">
				<?php esc_html_e( 'A tamper-evident record of sensitive marketplace events. This view is read-only.', 'wp-sell-services' ); ?>
			</p>

			<div class="wpss-listing-stats wpss-audit-stats">
				<div class="wpss-stat-card wpss-stat-total">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Matching events', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-forced">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $forced ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Forced overrides (all time)', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-list-card">
				<div class="wpss-list-card__filters wpss-audit-filters">
					<form method="get" action="">
						<input type="hidden" name="page" value="wpss-audit-log">

						<label for="wpss-audit-object-type" class="screen-reader-text"><?php esc_html_e( 'Filter by object type', 'wp-sell-services' ); ?></label>
						<input
							type="text"
							id="wpss-audit-object-type"
							name="object_type"
							class="wpss-audit-filter"
							value="<?php echo esc_attr( $filters['object_type'] ); ?>"
							placeholder="<?php esc_attr_e( 'Object type (e.g. order)', 'wp-sell-services' ); ?>"
						>

						<label for="wpss-audit-event-type" class="screen-reader-text"><?php esc_html_e( 'Filter by event type', 'wp-sell-services' ); ?></label>
						<select id="wpss-audit-event-type" name="event_type" class="wpss-audit-filter">
							<option value=""><?php esc_html_e( 'All events', 'wp-sell-services' ); ?></option>
							<?php
							$event_types = AuditLogService::EVENT_TYPES;
							if ( '' !== $filters['event_type'] && ! in_array( $filters['event_type'], $event_types, true ) ) {
								$event_types[] = $filters['event_type'];
							}
							foreach ( $event_types as $type ) :
								?>
								<option value="<?php echo esc_attr( $type ); ?>"<?php selected( $filters['event_type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
							<?php endforeach; ?>
						</select>

						<label for="wpss-audit-actor" class="screen-reader-text"><?php esc_html_e( 'Filter by actor user ID', 'wp-sell-services' ); ?></label>
						<input
							type="number"
							id="wpss-audit-actor"
							name="actor_id"
							class="wpss-audit-filter wpss-audit-filter--num"
							min="0"
							value="<?php echo $filters['actor_id'] > 0 ? esc_attr( (string) $filters['actor_id'] ) : ''; ?>"
							placeholder="<?php esc_attr_e( 'Actor ID', 'wp-sell-services' ); ?>"
						>

						<label for="wpss-audit-from" class="screen-reader-text"><?php esc_html_e( 'From date', 'wp-sell-services' ); ?></label>
						<input
							type="date"
							id="wpss-audit-from"
							name="from_date"
							class="wpss-audit-filter"
							value="<?php echo esc_attr( $filters['from_date'] ); ?>"
						>

						<label for="wpss-audit-to" class="screen-reader-text"><?php esc_html_e( 'To date', 'wp-sell-services' ); ?></label>
						<input
							type="date"
							id="wpss-audit-to"
							name="to_date"
							class="wpss-audit-filter"
							value="<?php echo esc_attr( $filters['to_date'] ); ?>"
						>

						<label class="wpss-audit-forced-toggle">
							<input type="checkbox" name="is_forced" value="1" <?php checked( $filters['is_forced'] ); ?>>
							<?php esc_html_e( 'Forced only', 'wp-sell-services' ); ?>
						</label>

						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'wp-sell-services' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-audit-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'wp-sell-services' ); ?></a>
					</form>
				</div>

				<div class="wpss-list-card__body">
					<?php if ( empty( $rows ) ) : ?>
						<div class="wpss-empty-state">
							<div class="wpss-empty-state__icon">
								<?php echo Icon::render( 'scroll-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns escaped markup. ?>
							</div>
							<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No audit events found', 'wp-sell-services' ); ?></h2>
							<p class="wpss-empty-state__body"><?php esc_html_e( 'No events match the current filters. Sensitive marketplace actions are recorded here as they happen.', 'wp-sell-services' ); ?></p>
						</div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped wpss-audit-table">
							<thead>
								<tr>
									<th scope="col" class="column-date"><?php esc_html_e( 'When', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-actor"><?php esc_html_e( 'Actor', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-event"><?php esc_html_e( 'Event', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-object"><?php esc_html_e( 'Object', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-change"><?php esc_html_e( 'Change', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-forced"><?php esc_html_e( 'Forced', 'wp-sell-services' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<?php $this->render_row( $row ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>

						<?php if ( $total_pages > 1 ) : ?>
							<div class="tablenav bottom">
								<div class="tablenav-pages">
									<span class="displaying-num">
										<?php
										printf(
											/* translators: %s: number of items. */
											esc_html( _n( '%s event', '%s events', $total, 'wp-sell-services' ) ),
											esc_html( number_format_i18n( $total ) )
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
					<?php endif; ?>
				</div><!-- .wpss-list-card__body -->
			</div><!-- .wpss-list-card -->
		</div>
		<?php
	}

	/**
	 * Render a single audit row.
	 *
	 * @param object $row Raw audit DB row.
	 * @return void
	 */
	private function render_row( object $row ): void {
		$actor_id    = (int) ( $row->actor_id ?? 0 );
		$actor_role  = isset( $row->actor_role ) ? (string) $row->actor_role : '';
		$event_type  = isset( $row->event_type ) ? (string) $row->event_type : '';
		$object_type = isset( $row->object_type ) ? (string) $row->object_type : '';
		$object_id   = (int) ( $row->object_id ?? 0 );
		$from_value  = isset( $row->from_value ) ? (string) $row->from_value : '';
		$to_value    = isset( $row->to_value ) ? (string) $row->to_value : '';
		$is_forced   = ! empty( $row->is_forced );
		$created_at  = isset( $row->created_at ) ? (string) $row->created_at : '';

		$actor_name = '';
		if ( $actor_id > 0 ) {
			$user       = get_userdata( $actor_id );
			$actor_name = $user ? $user->display_name : '';
		}

		$when = '';
		if ( '' !== $created_at ) {
			$timestamp = strtotime( $created_at );
			if ( false !== $timestamp ) {
				$when = wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					$timestamp
				);
			}
		}
		?>
		<tr>
			<td class="column-date"><?php echo esc_html( '' !== $when ? $when : $created_at ); ?></td>
			<td class="column-actor">
				<?php if ( '' !== $actor_name ) : ?>
					<span class="wpss-audit-actor-name"><?php echo esc_html( $actor_name ); ?></span>
				<?php elseif ( $actor_id > 0 ) : ?>
					<span class="wpss-audit-actor-name">#<?php echo esc_html( (string) $actor_id ); ?></span>
				<?php else : ?>
					<span class="wpss-audit-actor-name wpss-audit-system"><?php esc_html_e( 'System', 'wp-sell-services' ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $actor_role ) : ?>
					<span class="wpss-audit-actor-role"><?php echo esc_html( translate_user_role( ucfirst( $actor_role ) ) ); ?></span>
				<?php endif; ?>
			</td>
			<td class="column-event"><code class="wpss-audit-event"><?php echo esc_html( $event_type ); ?></code></td>
			<td class="column-object">
				<?php
				if ( '' !== $object_type ) {
					echo esc_html( $object_type );
					if ( $object_id > 0 ) {
						echo ' #' . esc_html( (string) $object_id );
					}
				} else {
					echo '&mdash;';
				}
				?>
			</td>
			<td class="column-change">
				<?php if ( '' !== $from_value || '' !== $to_value ) : ?>
					<span class="wpss-audit-change">
						<span class="wpss-audit-from"><?php echo esc_html( '' !== $from_value ? $from_value : '—' ); ?></span>
						<span class="wpss-audit-arrow" aria-hidden="true">&rarr;</span>
						<span class="wpss-audit-to"><?php echo esc_html( '' !== $to_value ? $to_value : '—' ); ?></span>
					</span>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
			<td class="column-forced">
				<?php if ( $is_forced ) : ?>
					<span class="wpss-status-badge wpss-status-badge--danger"><?php esc_html_e( 'Forced', 'wp-sell-services' ); ?></span>
				<?php else : ?>
					<span class="wpss-audit-natural" aria-label="<?php esc_attr_e( 'Natural event', 'wp-sell-services' ); ?>">&mdash;</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Read and sanitize the filter/pagination query parameters.
	 *
	 * Read-only viewer state, sanitized at the boundary before it reaches the
	 * service layer (which additionally prepares every value for SQL).
	 *
	 * @return array{object_type:string, event_type:string, actor_id:int, is_forced:bool, from_date:string, to_date:string, page:int}
	 */
	private function read_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only listing filters; no state is changed.
		$object_type = isset( $_GET['object_type'] ) ? sanitize_key( wp_unslash( $_GET['object_type'] ) ) : '';
		$event_type  = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';
		$actor_id    = isset( $_GET['actor_id'] ) ? absint( wp_unslash( $_GET['actor_id'] ) ) : 0;
		$is_forced   = isset( $_GET['is_forced'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['is_forced'] ) );
		$from_date   = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
		$to_date     = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';
		$page        = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Constrain free-text dates to ISO YYYY-MM-DD; drop anything malformed.
		if ( '' !== $from_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from_date ) ) {
			$from_date = '';
		}
		if ( '' !== $to_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to_date ) ) {
			$to_date = '';
		}

		return array(
			'object_type' => $object_type,
			'event_type'  => $event_type,
			'actor_id'    => $actor_id,
			'is_forced'   => $is_forced,
			'from_date'   => $from_date,
			'to_date'     => $to_date,
			'page'        => $page,
		);
	}

	/**
	 * Count all-time forced override events, for the stats strip.
	 *
	 * @return int
	 */
	private function count_forced(): int {
		$result = $this->service->query(
			array(
				'is_forced' => true,
				'page'      => 1,
				'per_page'  => 1,
			)
		);

		return (int) $result['total'];
	}
}
