<?php
/**
 * Template Partial: Vendor Card
 *
 * Displays vendor information card on service pages.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $vendor_id Vendor user ID.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $vendor_id ) ) {
	$vendor_id = (int) get_post_field( 'post_author', get_the_ID() );
}

$vendor = get_userdata( $vendor_id );

if ( ! $vendor ) {
	return;
}

$tagline         = get_user_meta( $vendor_id, '_wpss_vendor_tagline', true );
$rating_avg      = (float) get_user_meta( $vendor_id, '_wpss_rating_average', true );
$rating_count    = (int) get_user_meta( $vendor_id, '_wpss_rating_count', true );
$completed_orders = (int) get_user_meta( $vendor_id, '_wpss_completed_orders', true );
$response_time   = get_user_meta( $vendor_id, '_wpss_vendor_response_time', true );
$country         = get_user_meta( $vendor_id, '_wpss_vendor_country', true );
$member_since    = get_user_meta( $vendor_id, '_wpss_vendor_since', true ) ?: $vendor->user_registered;
$is_verified     = get_user_meta( $vendor_id, '_wpss_vendor_verified', true );
$is_online       = get_user_meta( $vendor_id, '_wpss_last_active', true );

// Check if vendor is online (active in last 5 minutes).
$is_currently_online = $is_online && ( time() - strtotime( $is_online ) ) < 300;
?>

<div class="wpss-vendor-card">
	<div class="wpss-vendor-header">
		<div class="wpss-vendor-avatar-wrapper">
			<img src="<?php echo esc_url( get_avatar_url( $vendor_id, [ 'size' => 80 ] ) ); ?>"
				 alt="<?php echo esc_attr( $vendor->display_name ); ?>"
				 class="wpss-vendor-avatar">
			<?php if ( $is_currently_online ) : ?>
				<span class="wpss-online-indicator" title="<?php esc_attr_e( 'Online', 'wp-sell-services' ); ?>"></span>
			<?php endif; ?>
			<?php if ( $is_verified ) : ?>
				<span class="wpss-verified-badge" title="<?php esc_attr_e( 'Verified Vendor', 'wp-sell-services' ); ?>">
					<svg viewBox="0 0 16 16" width="18" height="18">
						<path fill="currentColor" d="M8 0l2.5 2.5H14v3.5L16 8l-2 2v3.5h-3.5L8 16l-2.5-2.5H2v-3.5L0 8l2-2V2.5h3.5L8 0zm-.5 11.5l5-5-1.5-1.5-3.5 3.5-1.5-1.5-1.5 1.5 3 3z"/>
					</svg>
				</span>
			<?php endif; ?>
		</div>

		<div class="wpss-vendor-info">
			<h4 class="wpss-vendor-name">
				<a href="<?php echo esc_url( wpss_get_vendor_url( $vendor_id ) ); ?>">
					<?php echo esc_html( $vendor->display_name ); ?>
				</a>
			</h4>
			<?php if ( $tagline ) : ?>
				<p class="wpss-vendor-tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<div class="wpss-vendor-stats">
		<?php if ( $rating_count > 0 ) : ?>
			<div class="wpss-stat">
				<span class="wpss-stat-value">
					<span class="wpss-star filled">★</span>
					<?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
					<span class="wpss-stat-count">(<?php echo esc_html( $rating_count ); ?>)</span>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $completed_orders > 0 ) : ?>
			<div class="wpss-stat">
				<span class="wpss-stat-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
				<span class="wpss-stat-value"><?php echo esc_html( $completed_orders ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<ul class="wpss-vendor-details">
		<?php if ( $country ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-location"></span>
				<span class="wpss-detail-label"><?php esc_html_e( 'From', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( $country ); ?></span>
			</li>
		<?php endif; ?>

		<li>
			<span class="wpss-detail-icon wpss-icon-calendar"></span>
			<span class="wpss-detail-label"><?php esc_html_e( 'Member since', 'wp-sell-services' ); ?></span>
			<span class="wpss-detail-value"><?php echo esc_html( wp_date( 'M Y', strtotime( $member_since ) ) ); ?></span>
		</li>

		<?php if ( $response_time ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-clock"></span>
				<span class="wpss-detail-label"><?php esc_html_e( 'Avg. Response', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( $response_time ); ?></span>
			</li>
		<?php endif; ?>

		<?php if ( $is_online ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-activity"></span>
				<span class="wpss-detail-label"><?php esc_html_e( 'Last Delivery', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( wpss_time_ago( $is_online ) ); ?></span>
			</li>
		<?php endif; ?>
	</ul>

	<div class="wpss-vendor-actions">
		<a href="<?php echo esc_url( wpss_get_vendor_url( $vendor_id ) ); ?>"
		   class="wpss-btn wpss-btn-outline wpss-btn-block">
			<?php esc_html_e( 'View Profile', 'wp-sell-services' ); ?>
		</a>
	</div>
</div>
