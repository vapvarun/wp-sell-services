<?php
/**
 * Dashboard Section: Earnings (vendor only)
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Database\Repositories\OrderRepository;

defined( 'ABSPATH' ) || exit;

$order_repo = new OrderRepository();

// Get earnings data from vendor stats.
$stats          = $order_repo->get_vendor_stats( $user_id );
$total_earnings = (float) ( $stats['total_earnings'] ?? 0 );
$total_orders   = (int) ( $stats['total_orders'] ?? 0 );
$completed      = (int) ( $stats['completed_orders'] ?? 0 );
$active_orders  = (int) ( $stats['active_orders'] ?? 0 );
?>

<div class="wpss-section wpss-section--earnings">
	<div class="wpss-stats-grid wpss-stats-grid--4">
		<div class="wpss-stat-card wpss-stat-card--highlight">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $total_earnings ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Earned', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $completed ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Completed Orders', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $active_orders ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Active Orders', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $total_orders ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<div class="wpss-earnings__info">
		<div class="wpss-notice wpss-notice--info">
			<p><?php esc_html_e( 'Withdrawal functionality coming soon. Your earnings are being tracked and will be available for withdrawal once the feature is enabled.', 'wp-sell-services' ); ?></p>
		</div>
	</div>
</div>
