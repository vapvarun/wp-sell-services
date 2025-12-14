<?php
/**
 * Template: Vendor Profile
 *
 * Displays a vendor's public profile page.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/vendor/profile.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $vendor_id Vendor user ID.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $vendor_id ) ) {
	$vendor_id = get_query_var( 'wpss_vendor' );
}

if ( ! $vendor_id ) {
	return;
}

$vendor = get_userdata( $vendor_id );

if ( ! $vendor || ! get_user_meta( $vendor_id, '_wpss_is_vendor', true ) ) {
	echo '<div class="wpss-notice wpss-notice-error">' . esc_html__( 'Vendor not found.', 'wp-sell-services' ) . '</div>';
	return;
}

// Get vendor data.
$tagline       = get_user_meta( $vendor_id, '_wpss_vendor_tagline', true );
$bio           = get_user_meta( $vendor_id, '_wpss_vendor_bio', true );
$skills        = get_user_meta( $vendor_id, '_wpss_vendor_skills', true ) ?: [];
$languages     = get_user_meta( $vendor_id, '_wpss_vendor_languages', true ) ?: [];
$response_time = get_user_meta( $vendor_id, '_wpss_vendor_response_time', true );
$country       = get_user_meta( $vendor_id, '_wpss_vendor_country', true );
$member_since  = get_user_meta( $vendor_id, '_wpss_vendor_since', true ) ?: $vendor->user_registered;
$is_verified   = get_user_meta( $vendor_id, '_wpss_vendor_verified', true );
$social_links  = get_user_meta( $vendor_id, '_wpss_vendor_social', true ) ?: [];

// Stats.
$rating_avg     = (float) get_user_meta( $vendor_id, '_wpss_rating_average', true );
$rating_count   = (int) get_user_meta( $vendor_id, '_wpss_rating_count', true );
$completed_orders = (int) get_user_meta( $vendor_id, '_wpss_completed_orders', true );

// Get services.
$services = get_posts(
	[
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'author'         => $vendor_id,
		'posts_per_page' => 6,
	]
);

// Get reviews.
global $wpdb;
$reviews = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wpss_reviews
		WHERE vendor_id = %d AND status = 'approved'
		ORDER BY created_at DESC
		LIMIT 5",
		$vendor_id
	)
);

get_header();

/**
 * Hook: wpss_before_vendor_profile
 *
 * @param int $vendor_id Vendor user ID.
 */
do_action( 'wpss_before_vendor_profile', $vendor_id );
?>

<div class="wpss-vendor-profile">
	<div class="wpss-container">
		<!-- Profile Header -->
		<div class="wpss-profile-header">
			<div class="wpss-profile-cover">
				<?php
				$cover_image = get_user_meta( $vendor_id, '_wpss_vendor_cover', true );
				if ( $cover_image ) :
					?>
					<img src="<?php echo esc_url( wp_get_attachment_url( $cover_image ) ); ?>" alt="">
				<?php endif; ?>
			</div>

			<div class="wpss-profile-info">
				<div class="wpss-profile-avatar">
					<img src="<?php echo esc_url( get_avatar_url( $vendor_id, [ 'size' => 150 ] ) ); ?>"
						 alt="<?php echo esc_attr( $vendor->display_name ); ?>">
					<?php if ( $is_verified ) : ?>
						<span class="wpss-verified-badge" title="<?php esc_attr_e( 'Verified Vendor', 'wp-sell-services' ); ?>">
							<svg viewBox="0 0 16 16" width="24" height="24">
								<path fill="currentColor" d="M8 0l2.5 2.5H14v3.5L16 8l-2 2v3.5h-3.5L8 16l-2.5-2.5H2v-3.5L0 8l2-2V2.5h3.5L8 0zm-.5 11.5l5-5-1.5-1.5-3.5 3.5-1.5-1.5-1.5 1.5 3 3z"/>
							</svg>
						</span>
					<?php endif; ?>
				</div>

				<div class="wpss-profile-details">
					<h1 class="wpss-profile-name"><?php echo esc_html( $vendor->display_name ); ?></h1>

					<?php if ( $tagline ) : ?>
						<p class="wpss-profile-tagline"><?php echo esc_html( $tagline ); ?></p>
					<?php endif; ?>

					<div class="wpss-profile-meta">
						<?php if ( $rating_count > 0 ) : ?>
							<span class="wpss-meta-item wpss-rating">
								<span class="wpss-star">★</span>
								<?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
								<span class="wpss-rating-count">
									<?php
									printf(
										/* translators: %d: review count */
										esc_html( _n( '(%d review)', '(%d reviews)', $rating_count, 'wp-sell-services' ) ),
										$rating_count
									);
									?>
								</span>
							</span>
						<?php endif; ?>

						<?php if ( $completed_orders > 0 ) : ?>
							<span class="wpss-meta-item wpss-orders">
								<?php
								printf(
									/* translators: %d: number of completed orders */
									esc_html( _n( '%d order completed', '%d orders completed', $completed_orders, 'wp-sell-services' ) ),
									$completed_orders
								);
								?>
							</span>
						<?php endif; ?>

						<?php if ( $country ) : ?>
							<span class="wpss-meta-item wpss-location">
								<span class="wpss-icon-location"></span>
								<?php echo esc_html( $country ); ?>
							</span>
						<?php endif; ?>

						<span class="wpss-meta-item wpss-member-since">
							<?php
							printf(
								/* translators: %s: date */
								esc_html__( 'Member since %s', 'wp-sell-services' ),
								esc_html( wp_date( 'F Y', strtotime( $member_since ) ) )
							);
							?>
						</span>
					</div>
				</div>

				<div class="wpss-profile-actions">
					<a href="#contact" class="wpss-btn wpss-btn-primary">
						<?php esc_html_e( 'Contact Me', 'wp-sell-services' ); ?>
					</a>
				</div>
			</div>
		</div>

		<div class="wpss-profile-layout">
			<main class="wpss-profile-main">
				<!-- About -->
				<?php if ( $bio ) : ?>
					<section class="wpss-profile-section">
						<h2><?php esc_html_e( 'About', 'wp-sell-services' ); ?></h2>
						<div class="wpss-profile-bio">
							<?php echo wp_kses_post( wpautop( $bio ) ); ?>
						</div>
					</section>
				<?php endif; ?>

				<!-- Services -->
				<?php if ( ! empty( $services ) ) : ?>
					<section class="wpss-profile-section">
						<h2><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></h2>
						<div class="wpss-services-grid wpss-services-grid-3">
							<?php foreach ( $services as $service_post ) : ?>
								<?php
								global $post;
								$post = $service_post;
								setup_postdata( $post );
								wpss_get_template_part( 'content', 'service-card' );
								?>
							<?php endforeach; ?>
							<?php wp_reset_postdata(); ?>
						</div>

						<?php
						$total_services = count(
							get_posts(
								[
									'post_type'      => 'wpss_service',
									'post_status'    => 'publish',
									'author'         => $vendor_id,
									'posts_per_page' => -1,
									'fields'         => 'ids',
								]
							)
						);
						?>
						<?php if ( $total_services > 6 ) : ?>
							<p class="wpss-view-all">
								<a href="<?php echo esc_url( add_query_arg( 'vendor', $vendor_id, get_post_type_archive_link( 'wpss_service' ) ) ); ?>">
									<?php
									printf(
										/* translators: %d: number of services */
										esc_html__( 'View all %d services', 'wp-sell-services' ),
										$total_services
									);
									?>
									&rarr;
								</a>
							</p>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<!-- Reviews -->
				<?php if ( ! empty( $reviews ) ) : ?>
					<section class="wpss-profile-section">
						<h2>
							<?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?>
							<?php if ( $rating_count > 0 ) : ?>
								<span class="wpss-section-meta">
									<?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
									<span class="wpss-star">★</span>
									(<?php echo esc_html( $rating_count ); ?>)
								</span>
							<?php endif; ?>
						</h2>

						<div class="wpss-reviews-list">
							<?php foreach ( $reviews as $review ) : ?>
								<?php $reviewer = get_userdata( $review->customer_id ); ?>
								<div class="wpss-review">
									<div class="wpss-review-header">
										<img src="<?php echo esc_url( get_avatar_url( $review->customer_id, [ 'size' => 48 ] ) ); ?>"
											 alt="<?php echo esc_attr( $reviewer ? $reviewer->display_name : '' ); ?>"
											 class="wpss-review-avatar">
										<div class="wpss-review-meta">
											<strong class="wpss-review-author">
												<?php echo esc_html( $reviewer ? $reviewer->display_name : __( 'Anonymous', 'wp-sell-services' ) ); ?>
											</strong>
											<span class="wpss-review-date">
												<?php echo esc_html( wpss_time_ago( $review->created_at ) ); ?>
											</span>
										</div>
										<div class="wpss-review-rating">
											<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
												<span class="wpss-star <?php echo $i <= $review->rating ? 'filled' : ''; ?>">★</span>
											<?php endfor; ?>
										</div>
									</div>
									<div class="wpss-review-content">
										<?php echo wp_kses_post( wpautop( $review->review ) ); ?>
									</div>
									<?php if ( $review->vendor_reply ) : ?>
										<div class="wpss-review-reply">
											<strong><?php esc_html_e( 'Seller Response:', 'wp-sell-services' ); ?></strong>
											<?php echo wp_kses_post( wpautop( $review->vendor_reply ) ); ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( $rating_count > 5 ) : ?>
							<p class="wpss-view-all">
								<a href="#" class="wpss-load-more-reviews" data-vendor="<?php echo esc_attr( $vendor_id ); ?>">
									<?php esc_html_e( 'View all reviews', 'wp-sell-services' ); ?> &rarr;
								</a>
							</p>
						<?php endif; ?>
					</section>
				<?php endif; ?>
			</main>

			<aside class="wpss-profile-sidebar">
				<!-- Quick Stats -->
				<div class="wpss-sidebar-card">
					<ul class="wpss-quick-stats">
						<?php if ( $response_time ) : ?>
							<li>
								<span class="wpss-stat-label"><?php esc_html_e( 'Avg. Response Time', 'wp-sell-services' ); ?></span>
								<span class="wpss-stat-value"><?php echo esc_html( $response_time ); ?></span>
							</li>
						<?php endif; ?>
						<li>
							<span class="wpss-stat-label"><?php esc_html_e( 'Active Services', 'wp-sell-services' ); ?></span>
							<span class="wpss-stat-value"><?php echo esc_html( count( $services ) ); ?></span>
						</li>
						<li>
							<span class="wpss-stat-label"><?php esc_html_e( 'Orders Completed', 'wp-sell-services' ); ?></span>
							<span class="wpss-stat-value"><?php echo esc_html( $completed_orders ); ?></span>
						</li>
					</ul>
				</div>

				<!-- Skills -->
				<?php if ( ! empty( $skills ) ) : ?>
					<div class="wpss-sidebar-card">
						<h4><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?></h4>
						<div class="wpss-tags">
							<?php foreach ( $skills as $skill ) : ?>
								<span class="wpss-tag"><?php echo esc_html( $skill ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Languages -->
				<?php if ( ! empty( $languages ) ) : ?>
					<div class="wpss-sidebar-card">
						<h4><?php esc_html_e( 'Languages', 'wp-sell-services' ); ?></h4>
						<ul class="wpss-languages-list">
							<?php foreach ( $languages as $language ) : ?>
								<li><?php echo esc_html( $language ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Social Links -->
				<?php if ( ! empty( $social_links ) ) : ?>
					<div class="wpss-sidebar-card">
						<h4><?php esc_html_e( 'Connect', 'wp-sell-services' ); ?></h4>
						<div class="wpss-social-links">
							<?php foreach ( $social_links as $platform => $url ) : ?>
								<?php if ( ! empty( $url ) ) : ?>
									<a href="<?php echo esc_url( $url ); ?>"
									   class="wpss-social-link wpss-social-<?php echo esc_attr( $platform ); ?>"
									   target="_blank"
									   rel="noopener noreferrer"
									   title="<?php echo esc_attr( ucfirst( $platform ) ); ?>">
										<span class="wpss-icon-<?php echo esc_attr( $platform ); ?>"></span>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_vendor_profile
 *
 * @param int $vendor_id Vendor user ID.
 */
do_action( 'wpss_after_vendor_profile', $vendor_id );

get_footer();
