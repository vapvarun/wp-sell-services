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

// Canonical vendor data lives in the wpss_vendor_profiles table (1.2.0
// migration) — the _wpss_vendor_tagline/country/verified and
// _wpss_completed_orders user-meta keys were never written. Rating,
// response time, and member-since meta keys DO have write paths.
$vendor_profile   = wpss_get_vendor( $vendor_id );
$tagline          = $vendor_profile ? $vendor_profile->title : '';
$rating_avg       = (float) get_user_meta( $vendor_id, '_wpss_rating_average', true );
$rating_count     = (int) get_user_meta( $vendor_id, '_wpss_rating_count', true );
$completed_orders = $vendor_profile ? $vendor_profile->orders_completed : 0;
$response_time    = get_user_meta( $vendor_id, '_wpss_vendor_response_time', true );
// Through the shared resolver so a code renders as a name and legacy
// free-text values still display. Same call on every country surface.
$country          = $vendor_profile ? wpss_get_country_name( (string) $vendor_profile->country ) : '';
$member_since     = get_user_meta( $vendor_id, '_wpss_vendor_since', true );
$member_since     = $member_since ? $member_since : $vendor->user_registered;
$is_verified      = $vendor_profile && $vendor_profile->is_verified;
$is_online        = get_user_meta( $vendor_id, '_wpss_last_active', true );
$last_delivery    = wpss_get_vendor_last_delivery( $vendor_id );

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
			<img src="<?php echo esc_url( get_avatar_url( $vendor_id, array( 'size' => 80 ) ) ); ?>"
				alt="<?php echo esc_attr( $vendor->display_name ); ?>"
				class="wpss-vendor-avatar">
			<?php if ( $is_currently_online ) : ?>
				<span class="wpss-online-indicator" title="<?php esc_attr_e( 'Online', 'wp-sell-services' ); ?>"></span>
			<?php endif; ?>
			<?php if ( $is_verified ) : ?>
				<span class="wpss-verified-badge" title="<?php esc_attr_e( 'Verified Vendor', 'wp-sell-services' ); ?>">
					<i data-lucide="badge-check" class="wpss-icon" aria-hidden="true"></i>
				</span>
			<?php endif; ?>
		</div>

		<div class="wpss-vendor-info">
			<h4 class="wpss-vendor-name">
				<a href="<?php echo esc_url( wpss_get_vendor_url( $vendor_id ) ); ?>">
					<?php echo esc_html( $vendor->display_name ); ?>
				</a>
				<?php
				if ( $vendor_profile ) :
					$tier       = $vendor_profile->tier;
					$tier_label = $vendor_profile->get_tier_label();
					// Colour comes from the .wpss-seller-badge--{tier} modifier in
					// frontend.css (token-driven), not an inline style attribute.
					?>
					<span class="wpss-seller-badge wpss-seller-badge--md wpss-seller-badge--<?php echo esc_attr( $tier ); ?>">
						<?php if ( 'pro' === $tier ) : ?>
							<i data-lucide="badge-check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
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
					<i data-lucide="map-pin" class="wpss-icon" aria-hidden="true"></i>
				</span>
				<span class="wpss-detail-label"><?php esc_html_e( 'From', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( $country ); ?></span>
			</li>
		<?php endif; ?>

		<li>
			<span class="wpss-detail-icon wpss-icon-calendar">
				<i data-lucide="calendar" class="wpss-icon" aria-hidden="true"></i>
			</span>
			<span class="wpss-detail-label"><?php esc_html_e( 'Member since', 'wp-sell-services' ); ?></span>
			<span class="wpss-detail-value"><?php echo esc_html( wp_date( 'M Y', strtotime( $member_since ) ) ); ?></span>
		</li>

		<?php if ( $response_time ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-clock">
					<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
				</span>
				<span class="wpss-detail-label"><?php esc_html_e( 'Avg. Response', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( $response_time ); ?></span>
			</li>
		<?php endif; ?>

		<?php if ( $last_delivery ) : ?>
			<li>
				<span class="wpss-detail-icon wpss-icon-activity">
					<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
				</span>
				<span class="wpss-detail-label"><?php esc_html_e( 'Last Delivery', 'wp-sell-services' ); ?></span>
				<span class="wpss-detail-value"><?php echo esc_html( wpss_time_ago( $last_delivery ) ); ?></span>
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
