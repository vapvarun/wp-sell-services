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
 * The list is the members' own notification center
 * (templates/partials/notifications-list.php), so the admin marks read, marks
 * all read and opens each notification's order or dispute exactly as members
 * do. It was a read-only table with 1,183 unread and no way to clear them
 * (Basecamp 10337159668).
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;


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
		?>
		<div class="wrap wpss-listing-page wpss-notifications-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'My Notifications', 'wp-sell-services' ); ?></h1>
			<hr class="wp-header-end">

			<p class="description wpss-notifications-intro">
				<?php esc_html_e( 'Your own notifications - the same list members see on their dashboard. Only you see these.', 'wp-sell-services' ); ?>
			</p>

			<div class="wpss-list-card">
				<div class="wpss-list-card__body">
					<?php
					wpss_get_template_part(
						'partials/notifications-list',
						'',
						array(
							'user_id'           => get_current_user_id(),
							'wpss_show_heading' => false,
						)
					);
					?>
				</div>
			</div>
		</div>
		<?php
	}


}
