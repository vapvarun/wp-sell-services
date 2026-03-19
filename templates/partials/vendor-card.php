<?php
/**
 * Template Partial: Vendor Card
 *
 * Displays vendor information card on service pages.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int    $vendor_id         Vendor user ID.
 * @var object $vendor            Vendor WP_User object.
 * @var float  $rating_avg        Vendor average rating.
 * @var int    $rating_count      Vendor rating count.
 * @var int    $completed_orders  Vendor completed orders count.
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

/**
 * Fires before the vendor card.
 *
 * @since 1.0.0
 *
 * @param int $vendor_id Vendor user ID.
 */
do_action( 'wpss_before_vendor_card', $vendor_id );
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
				<?php
				$vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $vendor_id );
				if ( $vendor_profile ) :
					$tier       = $vendor_profile->tier;
					$tier_label = $vendor_profile->get_tier_label();
					$tier_colors = array(
						'new'       => 'background:#f1f5f9;color:#64748b;',
						'rising'    => 'background:#eff6ff;color:#2563eb;',
						'top_rated' => 'background:#fefce8;color:#ca8a04;',
						'pro'       => 'background:#faf5ff;color:#7c3aed;',
					);
					$tier_style = $tier_colors[ $tier ] ?? $tier_colors['new'];
					?>
					<span class="wpss-seller-badge wpss-seller-badge--<?php echo esc_attr( $tier ); ?>" style="display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px;vertical-align:middle;margin-left:6px;<?php echo esc_attr( $tier_style ); ?>">
						<?php if ( 'pro' === $tier ) : ?>
							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" fill="currentColor" style="vertical-align:-1px;margin-right:2px;"><path d="M8 0l2.5 2.5H14v3.5L16 8l-2 2v3.5h-3.5L8 16l-2.5-2.5H2v-3.5L0 8l2-2V2.5h3.5L8 0zm-.5 11.5l5-5-1.5-1.5-3.5 3.5-1.5-1.5-1.5 1.5 3 3z"/></svg>
						<?php endif; ?>
						<?php echo esc_html( $tier_label ); ?>
					</span>
				<?php endif; ?>
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

		<?php
		/**
		 * Fires inside the vendor card meta area for custom badges/icons.
		 *
		 * @since 1.0.0
		 *
		 * @param int $vendor_id Vendor user ID.
		 */
		do_action( 'wpss_vendor_card_meta', $vendor_id );
		?>
	</div>

	<ul class="wpss-vendor-details">
		<?php if ( $country ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-location">
					<svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20.8995L16.9497 15.9497C19.6834 13.2161 19.6834 8.78392 16.9497 6.05025C14.2161 3.31658 9.78392 3.31658 7.05025 6.05025C4.31658 8.78392 4.31658 13.2161 7.05025 15.9497L12 20.8995ZM12 23.7279L5.63604 17.364C2.12132 13.8492 2.12132 8.15076 5.63604 4.63604C9.15076 1.12132 14.8492 1.12132 18.364 4.63604C21.8787 8.15076 21.8787 13.8492 18.364 17.364L12 23.7279ZM12 13C13.1046 13 14 12.1046 14 11C14 9.89543 13.1046 9 12 9C10.8954 9 10 9.89543 10 11C10 12.1046 10.8954 13 12 13ZM12 15C9.79086 15 8 13.2091 8 11C8 8.79086 9.79086 7 12 7C14.2091 7 16 8.79086 16 11C16 13.2091 14.2091 15 12 15Z"></path></svg>
				</span>
				<span class="wpss-detail-label"><?php esc_html_e( 'From', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( $country ); ?></span>
			</li>
		<?php endif; ?>

		<li>
			<span class="wpss-detail-icon wpss-icon-calendar">
				<svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 1V3H15V1H17V3H21C21.5523 3 22 3.44772 22 4V20C22 20.5523 21.5523 21 21 21H3C2.44772 21 2 20.5523 2 20V4C2 3.44772 2.44772 3 3 3H7V1H9ZM20 11H4V19H20V11ZM7 5H4V9H20V5H17V7H15V5H9V7H7V5Z"></path></svg>
			</span>
			<span class="wpss-detail-label"><?php esc_html_e( 'Member since', 'wp-sell-services' ); ?></span>
			<span class="wpss-detail-value"><?php echo esc_html( wp_date( 'M Y', strtotime( $member_since ) ) ); ?></span>
		</li>

		<?php if ( $response_time ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-clock">
					<svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM13 12H17V14H11V7H13V12Z"></path></svg>
				</span>
				<span class="wpss-detail-label"><?php esc_html_e( 'Avg. Response', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( $response_time ); ?></span>
			</li>
		<?php endif; ?>

		<?php if ( $is_online ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-activity">
					<svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM13 12H17V14H11V7H13V12Z"></path></svg>
				</span>
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

<?php
/**
 * Fires after the vendor card.
 *
 * @since 1.0.0
 *
 * @param int $vendor_id Vendor user ID.
 */
do_action( 'wpss_after_vendor_card', $vendor_id );
?>
