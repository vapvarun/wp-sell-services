<?php
/**
 * Notifications Admin Viewer Page
 *
 * Read-only admin viewer for in-app notifications (flow #14). It renders the
 * SAME data the mobile/web clients read over REST at `GET /wpss/v1/notifications`
 * (see {@see \WPSellServices\API\NotificationsController}) inside wp-admin so an
 * administrator can see their own notification stream without leaving WordPress.
 *
 * INTENTIONAL USER-PRIVATE EXCEPTION
 * ----------------------------------
 * Notifications are per-recipient and private by design: the `wpss_notifications`
 * table is keyed on `user_id`, the service only exposes
 * {@see \WPSellServices\Services\NotificationService::get_user_notifications()}
 * (scoped to one user), and the REST endpoint returns ONLY the current user's
 * rows. There is deliberately NO "view every user's notifications" surface — that
 * would expose private buyer/seller correspondence to administrators. This page
 * therefore shows the current admin's own notifications only, mirroring the REST
 * contract. Cross-user inspection is out of scope on purpose; an administrator
 * who needs the underlying events should use the forensic Audit Log instead,
 * which records sensitive marketplace actions cross-user by design.
 *
 * The view is read-only here: there are no mark-read/delete affordances, because
 * those mutations belong to the owning user's own client (the dashboard and the
 * REST API), not to an admin screen.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

use WPSellServices\Services\Icon;
use WPSellServices\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications Page Class.
 *
 * @since 1.2.0
 */
class NotificationsPage {

	/**
	 * Page hook suffix, captured at registration for screen-scoped enqueues.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Notification service.
	 *
	 * @var NotificationService
	 */
	private NotificationService $service;

	/**
	 * Notifications per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new NotificationService();
	}

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 27 );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$hook = add_submenu_page(
			'wp-sell-services',
			__( 'My Notifications', 'wp-sell-services' ),
			__( 'My Notifications', 'wp-sell-services' ),
			'manage_options',
			'wpss-notifications',
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
				'id'      => 'wpss-notifications-overview',
				'title'   => __( 'Overview', 'wp-sell-services' ),
				'content' => '<p>' . esc_html__( 'This screen shows your own in-app notifications - the same stream the marketplace dashboard and the mobile app read for you. Notifications are private to each recipient, so this view never shows other users\' notifications. To audit sensitive cross-user marketplace actions, use the Audit Log instead.', 'wp-sell-services' ) . '</p>',
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

		if ( wp_script_is( 'wpss-admin-icons', 'registered' ) ) {
			wp_enqueue_script( 'wpss-admin-icons' );
		}
	}

	/**
	 * Render the notifications viewer.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-sell-services' ) );
		}

		$user_id      = get_current_user_id();
		$current_page = $this->read_paged();
		$offset       = ( $current_page - 1 ) * self::PER_PAGE;

		$total = $this->count_notifications( $user_id );
		$rows  = $this->service->get_user_notifications(
			$user_id,
			array(
				'limit'  => self::PER_PAGE,
				'offset' => $offset,
			)
		);

		$unread      = $this->service->get_unread_count( $user_id );
		$total_pages = $total > 0 ? (int) ceil( $total / self::PER_PAGE ) : 0;
		?>
		<div class="wrap wpss-listing-page wpss-notifications-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'My Notifications', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<p class="description wpss-notifications-intro">
				<?php esc_html_e( 'Your own in-app notifications, mirroring the marketplace dashboard and mobile app. Notifications are private to each recipient, so this view shows only your stream. This is a read-only view.', 'wp-sell-services' ); ?>
			</p>

			<div class="wpss-listing-stats wpss-notifications-stats">
				<div class="wpss-stat-card wpss-stat-total">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Your notifications', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-stat-card wpss-stat-unread">
					<span class="wpss-stat-number"><?php echo esc_html( number_format_i18n( $unread ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Unread', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-list-card">
				<div class="wpss-list-card__body">
					<?php if ( empty( $rows ) ) : ?>
						<div class="wpss-empty-state">
							<div class="wpss-empty-state__icon">
								<?php echo Icon::render( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns escaped markup. ?>
							</div>
							<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No notifications yet', 'wp-sell-services' ); ?></h2>
							<p class="wpss-empty-state__body"><?php esc_html_e( 'Order updates, messages, reviews, and other marketplace events addressed to you will appear here.', 'wp-sell-services' ); ?></p>
						</div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped wpss-notifications-table">
							<thead>
								<tr>
									<th scope="col" class="column-state"><?php esc_html_e( 'State', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-title"><?php esc_html_e( 'Notification', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-type"><?php esc_html_e( 'Type', 'wp-sell-services' ); ?></th>
									<th scope="col" class="column-date"><?php esc_html_e( 'Received', 'wp-sell-services' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $rows as $row ) :
									if ( ! is_object( $row ) ) {
										continue;
									}
									$this->render_notification_row( $row );
								endforeach;
								?>
							</tbody>
						</table>

						<?php if ( $total_pages > 1 ) : ?>
							<div class="tablenav bottom">
								<div class="tablenav-pages">
									<span class="displaying-num">
										<?php
										printf(
											/* translators: %s: number of notifications. */
											esc_html( _n( '%s notification', '%s notifications', $total, 'wp-sell-services' ) ),
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
	 * Render a single notification row.
	 *
	 * @param object $row Raw notification DB row.
	 * @return void
	 */
	private function render_notification_row( object $row ): void {
		$title      = isset( $row->title ) ? (string) $row->title : '';
		$message    = isset( $row->message ) ? (string) $row->message : '';
		$type       = isset( $row->type ) ? (string) $row->type : '';
		$is_read    = ! empty( $row->is_read );
		$created_at = isset( $row->created_at ) ? (string) $row->created_at : '';

		$when = '';
		if ( '' !== $created_at ) {
			$timestamp = strtotime( $created_at );
			if ( false !== $timestamp ) {
				$when = (string) wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					$timestamp
				);
			}
		}

		// Notification "type" is a machine slug (e.g. order_status); present it
		// as a readable label without inventing a translation per slug.
		$type_label = '' !== $type ? ucwords( str_replace( '_', ' ', $type ) ) : '';
		?>
		<tr class="<?php echo $is_read ? '' : 'wpss-notification--unread'; ?>">
			<td class="column-state">
				<?php if ( $is_read ) : ?>
					<span class="wpss-status-badge wpss-status-approved"><?php esc_html_e( 'Read', 'wp-sell-services' ); ?></span>
				<?php else : ?>
					<span class="wpss-status-badge wpss-status-pending"><?php esc_html_e( 'Unread', 'wp-sell-services' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="column-title">
				<strong><?php echo esc_html( $title ); ?></strong>
				<?php if ( '' !== trim( $message ) ) : ?>
					<div class="wpss-notification-message"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $message ), 30 ) ); ?></div>
				<?php endif; ?>
			</td>
			<td class="column-type">
				<?php echo '' !== $type_label ? esc_html( $type_label ) : '&mdash;'; ?>
			</td>
			<td class="column-date"><?php echo esc_html( '' !== $when ? $when : $created_at ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Count the current user's notifications.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function count_notifications( int $user_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_notifications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
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
}
