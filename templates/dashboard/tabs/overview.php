<?php
/**
 * Template: Dashboard Overview Tab
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$user_id   = get_current_user_id();
$is_vendor = wpss_is_vendor( $user_id );

global $wpdb;
$orders_table = $wpdb->prefix . 'wpss_orders';

// Get customer stats.
$customer_stats = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT
			COUNT(*) as total_orders,
			SUM(CASE WHEN status IN ('pending', 'accepted', 'in_progress', 'delivered') THEN 1 ELSE 0 END) as active_orders,
			SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders
		FROM {$orders_table}
		WHERE customer_id = %d",
		$user_id
	)
);

// Get vendor stats if applicable.
$vendor_stats = null;
if ( $is_vendor ) {
	$vendor_stats = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				COUNT(*) as total_orders,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
				SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_orders,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
				SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as total_earnings
			FROM {$orders_table}
			WHERE vendor_id = %d",
			$user_id
		)
	);

	$services_count = count(
		get_posts(
			[
				'post_type'      => 'wpss_service',
				'author'         => $user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'any',
			]
		)
	);
}

// Recent orders.
$recent_orders = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$orders_table}
		WHERE customer_id = %d OR vendor_id = %d
		ORDER BY created_at DESC
		LIMIT 5",
		$user_id,
		$user_id
	)
);
?>

<div class="wpss-dashboard-overview">
	<h2><?php esc_html_e( 'Dashboard Overview', 'wp-sell-services' ); ?></h2>

	<?php if ( $is_vendor ) : ?>
		<div class="wpss-stats-grid wpss-vendor-stats">
			<div class="wpss-stat-card">
				<span class="wpss-stat-icon wpss-icon-services"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( $services_count ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Active Services', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-stat-card">
				<span class="wpss-stat-icon wpss-icon-pending"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( $vendor_stats->pending_orders ?? 0 ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Pending Orders', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-stat-card">
				<span class="wpss-stat-icon wpss-icon-progress"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( $vendor_stats->active_orders ?? 0 ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'In Progress', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-stat-card wpss-stat-highlight">
				<span class="wpss-stat-icon wpss-icon-earnings"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( wpss_format_price( (float) ( $vendor_stats->total_earnings ?? 0 ) ) ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Earnings', 'wp-sell-services' ); ?></span>
				</div>
			</div>
		</div>
	<?php else : ?>
		<div class="wpss-stats-grid wpss-customer-stats">
			<div class="wpss-stat-card">
				<span class="wpss-stat-icon wpss-icon-orders"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( $customer_stats->total_orders ?? 0 ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-stat-card">
				<span class="wpss-stat-icon wpss-icon-active"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( $customer_stats->active_orders ?? 0 ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></span>
				</div>
			</div>

			<div class="wpss-stat-card">
				<span class="wpss-stat-icon wpss-icon-completed"></span>
				<div class="wpss-stat-content">
					<span class="wpss-stat-value"><?php echo esc_html( $customer_stats->completed_orders ?? 0 ); ?></span>
					<span class="wpss-stat-label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $is_vendor && $vendor_stats && $vendor_stats->pending_orders > 0 ) : ?>
		<div class="wpss-alert wpss-alert-info">
			<span class="wpss-alert-icon"></span>
			<p>
				<?php
				printf(
					/* translators: %d: number of pending orders */
					esc_html( _n(
						'You have %d pending order awaiting your response.',
						'You have %d pending orders awaiting your response.',
						$vendor_stats->pending_orders,
						'wp-sell-services'
					) ),
					$vendor_stats->pending_orders
				);
				?>
				<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) . '&status=pending' ); ?>">
					<?php esc_html_e( 'View Orders', 'wp-sell-services' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="wpss-recent-orders">
		<h3><?php esc_html_e( 'Recent Orders', 'wp-sell-services' ); ?></h3>

		<?php if ( ! empty( $recent_orders ) ) : ?>
			<table class="wpss-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
						<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_orders as $order ) : ?>
						<?php
						$service   = get_post( $order->service_id );
						$is_vendor_order = (int) $order->vendor_id === $user_id;
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $order->order_number ); ?></strong>
								<?php if ( $is_vendor_order ) : ?>
									<span class="wpss-badge wpss-badge-vendor"><?php esc_html_e( 'Selling', 'wp-sell-services' ); ?></span>
								<?php else : ?>
									<span class="wpss-badge wpss-badge-customer"><?php esc_html_e( 'Buying', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $service ? $service->post_title : __( 'N/A', 'wp-sell-services' ) ); ?></td>
							<td>
								<span class="wpss-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
									<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></td>
							<td><?php echo esc_html( wpss_time_ago( $order->created_at ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) . '&order=' . $order->id ); ?>"
								   class="wpss-btn wpss-btn-sm">
									<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="wpss-view-all">
				<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) ); ?>">
					<?php esc_html_e( 'View All Orders', 'wp-sell-services' ); ?> &rarr;
				</a>
			</p>
		<?php else : ?>
			<p class="wpss-empty-state">
				<?php esc_html_e( 'No orders yet.', 'wp-sell-services' ); ?>
				<?php if ( ! $is_vendor ) : ?>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_service' ) ); ?>">
						<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</div>
