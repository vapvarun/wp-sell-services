<?php
/**
 * Template: Dashboard
 *
 * Main dashboard template for vendors and customers.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/dashboard/dashboard.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( wpss_get_dashboard_url() ) );
	exit;
}

$user_id   = get_current_user_id();
$is_vendor = wpss_is_vendor( $user_id );
$tab       = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Define available tabs.
$tabs = array(
	'overview' => __( 'Overview', 'wp-sell-services' ),
	'orders'   => __( 'Orders', 'wp-sell-services' ),
	'messages' => __( 'Messages', 'wp-sell-services' ),
	'settings' => __( 'Settings', 'wp-sell-services' ),
);

// Add vendor-only tabs.
if ( $is_vendor ) {
	$tabs = array_merge(
		array_slice( $tabs, 0, 1 ),
		array(
			'services' => __( 'My Services', 'wp-sell-services' ),
			'earnings' => __( 'Earnings', 'wp-sell-services' ),
		),
		array_slice( $tabs, 1 )
	);
}

/**
 * Filter dashboard tabs.
 *
 * @param array $tabs      Dashboard tabs.
 * @param bool  $is_vendor Whether user is a vendor.
 */
$tabs = apply_filters( 'wpss_dashboard_tabs', $tabs, $is_vendor );

get_header();

/**
 * Hook: wpss_before_dashboard
 */
do_action( 'wpss_before_dashboard' );
?>

<div class="wpss-dashboard">
	<aside class="wpss-dashboard__sidebar">
		<div class="wpss-dashboard__user">
			<img src="<?php echo esc_url( get_avatar_url( $user_id, array( 'size' => 96 ) ) ); ?>"
				alt="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>"
				class="wpss-dashboard__user-avatar">
			<div class="wpss-dashboard__user-info">
				<h3><?php echo esc_html( wp_get_current_user()->display_name ); ?></h3>
				<?php if ( $is_vendor ) : ?>
					<span class="wpss-dashboard__user-role"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></span>
				<?php else : ?>
					<span class="wpss-dashboard__user-role"><?php esc_html_e( 'Customer', 'wp-sell-services' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<nav class="wpss-dashboard__nav">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( wpss_get_dashboard_url( $tab_key ) ); ?>" class="wpss-dashboard__nav-item <?php echo $tab === $tab_key ? 'wpss-dashboard__nav-item--active' : ''; ?>">
					<span class="wpss-dashboard__nav-icon">
						<?php
						// SVG icons based on tab.
						switch ( $tab_key ) {
							case 'overview':
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
								break;
							case 'services':
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
								break;
							case 'orders':
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
								break;
							case 'earnings':
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
								break;
							case 'messages':
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
								break;
							case 'settings':
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
								break;
							default:
								echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
						}
						?>
					</span>
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( ! $is_vendor ) : ?>
			<div class="wpss-dashboard__cta">
				<p><?php esc_html_e( 'Want to sell your services?', 'wp-sell-services' ); ?></p>
				<a href="<?php echo esc_url( wpss_get_dashboard_url( 'become-vendor' ) ); ?>" class="wpss-btn wpss-btn--outline">
					<?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</aside>

	<div class="wpss-dashboard__content">
		<?php
		/**
		 * Hook: wpss_dashboard_content_start
		 */
		do_action( 'wpss_dashboard_content_start', $tab );

		// Load tab content.
		$template_file = "dashboard/tabs/{$tab}.php";
		$template_path = locate_template( "wp-sell-services/{$template_file}" );

		if ( ! $template_path ) {
			$template_path = WPSS_PLUGIN_DIR . "templates/{$template_file}";
		}

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// Fallback to action hook for custom tabs.
			do_action( "wpss_dashboard_tab_{$tab}" );
		}

		/**
		 * Hook: wpss_dashboard_content_end
		 */
		do_action( 'wpss_dashboard_content_end', $tab );
		?>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_dashboard
 */
do_action( 'wpss_after_dashboard' );

get_footer();
