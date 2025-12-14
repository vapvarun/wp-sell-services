<?php
/**
 * Vendor Dashboard - My Account Template
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var \WPSellServices\Models\VendorProfile|null $vendor  Vendor profile.
 * @var object                                     $stats   Vendor statistics.
 * @var int                                        $user_id Current user ID.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wpss-vendor-dashboard">
	<h2><?php esc_html_e( 'Vendor Dashboard', 'wp-sell-services' ); ?></h2>

	<?php if ( $vendor ) : ?>
		<div class="wpss-dashboard-header">
			<div class="wpss-vendor-info">
				<img src="<?php echo esc_url( $vendor->get_avatar_url() ); ?>" alt="" class="wpss-vendor-avatar">
				<div class="wpss-vendor-details">
					<h3><?php echo esc_html( $vendor->display_name ); ?></h3>
					<span class="wpss-vendor-tier wpss-tier-<?php echo esc_attr( $vendor->tier ); ?>">
						<?php echo esc_html( $vendor->get_tier_label() ); ?>
					</span>
					<?php if ( $vendor->is_verified ) : ?>
						<span class="wpss-verified-badge" title="<?php esc_attr_e( 'Verified Seller', 'wp-sell-services' ); ?>">✓</span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="wpss-dashboard-stats">
			<div class="wpss-stat-card">
				<div class="wpss-stat-value"><?php echo esc_html( number_format( (float) ( $stats->total_earnings ?? 0 ), 2 ) ); ?></div>
				<div class="wpss-stat-label"><?php esc_html_e( 'Total Earnings', 'wp-sell-services' ); ?></div>
			</div>
			<div class="wpss-stat-card">
				<div class="wpss-stat-value"><?php echo esc_html( $stats->completed_orders ?? 0 ); ?></div>
				<div class="wpss-stat-label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></div>
			</div>
			<div class="wpss-stat-card">
				<div class="wpss-stat-value"><?php echo esc_html( $stats->active_orders ?? 0 ); ?></div>
				<div class="wpss-stat-label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></div>
			</div>
			<div class="wpss-stat-card">
				<div class="wpss-stat-value"><?php echo esc_html( number_format( $vendor->rating, 1 ) ); ?> ★</div>
				<div class="wpss-stat-label"><?php esc_html_e( 'Average Rating', 'wp-sell-services' ); ?></div>
			</div>
		</div>

		<div class="wpss-dashboard-metrics">
			<h3><?php esc_html_e( 'Performance Metrics', 'wp-sell-services' ); ?></h3>
			<div class="wpss-metrics-grid">
				<div class="wpss-metric">
					<span class="wpss-metric-label"><?php esc_html_e( 'Response Rate', 'wp-sell-services' ); ?></span>
					<span class="wpss-metric-value"><?php echo esc_html( number_format( $vendor->response_rate, 0 ) ); ?>%</span>
				</div>
				<div class="wpss-metric">
					<span class="wpss-metric-label"><?php esc_html_e( 'Response Time', 'wp-sell-services' ); ?></span>
					<span class="wpss-metric-value"><?php echo esc_html( $vendor->get_response_time_label() ); ?></span>
				</div>
				<div class="wpss-metric">
					<span class="wpss-metric-label"><?php esc_html_e( 'On-Time Delivery', 'wp-sell-services' ); ?></span>
					<span class="wpss-metric-value"><?php echo esc_html( number_format( $vendor->delivery_rate, 0 ) ); ?>%</span>
				</div>
				<div class="wpss-metric">
					<span class="wpss-metric-label"><?php esc_html_e( 'Order Completion', 'wp-sell-services' ); ?></span>
					<span class="wpss-metric-value"><?php echo esc_html( number_format( $vendor->completion_rate, 0 ) ); ?>%</span>
				</div>
			</div>
		</div>

		<div class="wpss-dashboard-actions">
			<a href="<?php echo esc_url( home_url( '/my-account/vendor-services/' ) ); ?>" class="button">
				<?php esc_html_e( 'Manage Services', 'wp-sell-services' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create New Service', 'wp-sell-services' ); ?>
			</a>
		</div>

	<?php else : ?>
		<div class="wpss-no-vendor-profile">
			<p><?php esc_html_e( 'You haven\'t set up your vendor profile yet.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/become-a-seller/' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Set Up Profile', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

<style>
.wpss-vendor-dashboard {
	padding: 20px 0;
}

.wpss-dashboard-header {
	display: flex;
	align-items: center;
	margin-bottom: 30px;
	padding: 20px;
	background: #f8f9fa;
	border-radius: 8px;
}

.wpss-vendor-info {
	display: flex;
	align-items: center;
	gap: 15px;
}

.wpss-vendor-avatar {
	width: 80px;
	height: 80px;
	border-radius: 50%;
	object-fit: cover;
}

.wpss-vendor-details h3 {
	margin: 0 0 5px;
}

.wpss-vendor-tier {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 4px;
	font-size: 12px;
	background: #e9ecef;
}

.wpss-tier-top_rated { background: #ffd700; color: #333; }
.wpss-tier-pro { background: #6c5ce7; color: #fff; }

.wpss-verified-badge {
	display: inline-block;
	width: 20px;
	height: 20px;
	line-height: 20px;
	text-align: center;
	background: #00b894;
	color: #fff;
	border-radius: 50%;
	font-size: 12px;
	margin-left: 5px;
}

.wpss-dashboard-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.wpss-stat-card {
	padding: 20px;
	background: #fff;
	border: 1px solid #e5e5e5;
	border-radius: 8px;
	text-align: center;
}

.wpss-stat-value {
	font-size: 28px;
	font-weight: 700;
	color: #2d3436;
}

.wpss-stat-label {
	font-size: 13px;
	color: #636e72;
	margin-top: 5px;
}

.wpss-dashboard-metrics {
	margin-bottom: 30px;
}

.wpss-metrics-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
	margin-top: 15px;
}

.wpss-metric {
	display: flex;
	justify-content: space-between;
	padding: 15px;
	background: #f8f9fa;
	border-radius: 4px;
}

.wpss-metric-label {
	color: #636e72;
}

.wpss-metric-value {
	font-weight: 600;
}

.wpss-dashboard-actions {
	display: flex;
	gap: 10px;
	margin-top: 20px;
}

.wpss-no-vendor-profile {
	text-align: center;
	padding: 40px 20px;
}
</style>
