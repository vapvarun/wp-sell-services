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
$tabs = [
	'overview' => __( 'Overview', 'wp-sell-services' ),
	'orders'   => __( 'Orders', 'wp-sell-services' ),
	'messages' => __( 'Messages', 'wp-sell-services' ),
	'settings' => __( 'Settings', 'wp-sell-services' ),
];

// Add vendor-only tabs.
if ( $is_vendor ) {
	$tabs = array_merge(
		array_slice( $tabs, 0, 1 ),
		[
			'services' => __( 'My Services', 'wp-sell-services' ),
			'earnings' => __( 'Earnings', 'wp-sell-services' ),
		],
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
	<div class="wpss-container">
		<div class="wpss-dashboard-layout">
			<aside class="wpss-dashboard-sidebar">
				<div class="wpss-user-info">
					<img src="<?php echo esc_url( get_avatar_url( $user_id, [ 'size' => 80 ] ) ); ?>"
						 alt="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>"
						 class="wpss-user-avatar">
					<div class="wpss-user-details">
						<h3 class="wpss-user-name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></h3>
						<?php if ( $is_vendor ) : ?>
							<span class="wpss-user-role"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></span>
						<?php else : ?>
							<span class="wpss-user-role"><?php esc_html_e( 'Customer', 'wp-sell-services' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<nav class="wpss-dashboard-nav">
					<ul>
						<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
							<li class="<?php echo $tab === $tab_key ? 'active' : ''; ?>">
								<a href="<?php echo esc_url( wpss_get_dashboard_url( $tab_key ) ); ?>">
									<span class="wpss-icon wpss-icon-<?php echo esc_attr( $tab_key ); ?>"></span>
									<?php echo esc_html( $tab_label ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<?php if ( ! $is_vendor ) : ?>
					<div class="wpss-become-vendor">
						<p><?php esc_html_e( 'Want to sell your services?', 'wp-sell-services' ); ?></p>
						<a href="<?php echo esc_url( wpss_get_dashboard_url( 'become-vendor' ) ); ?>" class="wpss-btn wpss-btn-outline">
							<?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</aside>

			<main class="wpss-dashboard-content">
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
			</main>
		</div>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_dashboard
 */
do_action( 'wpss_after_dashboard' );

get_footer();
