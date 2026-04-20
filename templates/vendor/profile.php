<?php
/**
 * Template: Vendor Profile
 *
 * Displays a vendor's public profile page.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/vendor/profile.php
 *
 * Available Hooks:
 *
 * - wpss_before_vendor_profile (action)
 *   Fires before the vendor profile wrapper.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_vendor_profile_header (action)
 *   Cover image and avatar area.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_vendor_profile_bio (action)
 *   About/description area.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_vendor_profile_stats (action)
 *   Statistics display area.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_vendor_profile_services (action)
 *   Services grid area.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_vendor_profile_reviews (action)
 *   Reviews section area.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_vendor_profile_sidebar (action)
 *   Profile sidebar area - stats, skills, languages.
 *   @param int $vendor_id Vendor user ID.
 *
 * - wpss_after_vendor_profile (action)
 *   Fires after the vendor profile wrapper.
 *   @param int $vendor_id Vendor user ID.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $vendor_id Vendor user ID.
 */

defined( 'ABSPATH' ) || exit;

use WPSellServices\Services\VendorService;
use WPSellServices\Models\VendorProfile;

// Get vendor ID from global (set by TemplateLoader) or query var.
if ( empty( $vendor_id ) ) {
	global $wpss_vendor_id;
	$vendor_id = $wpss_vendor_id ?: get_query_var( 'wpss_vendor' );
}

if ( ! $vendor_id ) {
	return;
}

$vendor = get_userdata( $vendor_id );

// Use VendorService to check vendor status and get profile from database.
$vendor_service = new VendorService();

if ( ! $vendor || ! $vendor_service->is_vendor( $vendor_id ) ) {
	echo '<div class="wpss-notice wpss-notice-error">' . esc_html__( 'Vendor not found.', 'wp-sell-services' ) . '</div>';
	return;
}

// Get vendor profile from database (not user meta).
$profile = $vendor_service->get_profile( $vendor_id );

// Get vendor data from profile object (database) with fallbacks.
$tagline       = $profile->tagline ?? '';
$bio           = $profile->bio ?? '';
$skills        = ! empty( $profile->skills ) ? json_decode( $profile->skills, true ) : [];
$languages     = ! empty( $profile->languages ) ? json_decode( $profile->languages, true ) : [];
$response_time = $vendor_service->get_response_time( $vendor_id );
$country       = $profile->country ?? '';
$member_since  = get_user_meta( $vendor_id, '_wpss_vendor_since', true ) ?: $vendor->user_registered;
$is_verified   = ( $profile->verification_tier ?? '' ) === VendorProfile::TIER_PRO;
$social_links  = ! empty( $profile->social_links ) ? json_decode( $profile->social_links, true ) : [];

// Stats from profile (cached in database).
$rating_avg       = (float) ( $profile->rating_avg ?? 0 );
$rating_count     = (int) ( $profile->rating_count ?? 0 );
$completed_orders = (int) ( $profile->completed_orders ?? 0 );

// Get services (limited for display grid).
$services = get_posts(
	[
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'author'         => $vendor_id,
		'posts_per_page' => 6,
	]
);

// Total published service count (for sidebar stats and "View all" link).
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
			<?php
			/**
			 * Hook: wpss_vendor_profile_header
			 *
			 * Fires in the vendor profile header area (cover/avatar).
			 * Use this to add custom header content or badges.
			 *
			 * @since 1.0.0
			 *
			 * @param int $vendor_id Vendor user ID.
			 */
			do_action( 'wpss_vendor_profile_header', $vendor_id );
			?>

			<div class="wpss-profile-cover">
				<?php
				// Get cover image from profile database record.
				$cover_image = $profile->cover_image_id ?? 0;
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
					<h1 class="wpss-profile-name">
						<?php echo esc_html( $vendor->display_name ); ?>
						<?php
						$vendor_profile_obj = VendorProfile::get_by_user_id( $vendor_id );
						if ( $vendor_profile_obj ) :
							$tier       = $vendor_profile_obj->tier;
							$tier_label = $vendor_profile_obj->get_tier_label();
							$tier_colors = array(
								'new'       => 'background:#f1f5f9;color:#64748b;',
								'rising'    => 'background:#eff6ff;color:#2563eb;',
								'top_rated' => 'background:#fefce8;color:#ca8a04;',
								'pro'       => 'background:#faf5ff;color:#7c3aed;',
							);
							$tier_style = $tier_colors[ $tier ] ?? $tier_colors['new'];
							?>
							<span class="wpss-seller-badge wpss-seller-badge--<?php echo esc_attr( $tier ); ?>" style="display:inline-block;font-size:13px;font-weight:600;padding:3px 10px;border-radius:9999px;vertical-align:middle;margin-left:8px;<?php echo esc_attr( $tier_style ); ?>">
								<?php if ( 'pro' === $tier ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="vertical-align:-2px;margin-right:3px;"><path d="M8 0l2.5 2.5H14v3.5L16 8l-2 2v3.5h-3.5L8 16l-2.5-2.5H2v-3.5L0 8l2-2V2.5h3.5L8 0zm-.5 11.5l5-5-1.5-1.5-3.5 3.5-1.5-1.5-1.5 1.5 3 3z"/></svg>
								<?php endif; ?>
								<?php echo esc_html( $tier_label ); ?>
							</span>
						<?php endif; ?>
					</h1>

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
										absint( $rating_count )
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
									absint( $completed_orders )
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
					<?php if ( is_user_logged_in() && get_current_user_id() !== $vendor_id ) : ?>
						<a href="#" class="wpss-btn wpss-btn-primary wpss-contact-btn"
							data-vendor="<?php echo esc_attr( $vendor_id ); ?>">
							<?php esc_html_e( 'Contact Me', 'wp-sell-services' ); ?>
						</a>
					<?php elseif ( ! is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wp_login_url( wpss_get_vendor_url( $vendor_id ) ) ); ?>" class="wpss-btn wpss-btn-primary">
							<?php esc_html_e( 'Contact Me', 'wp-sell-services' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="wpss-profile-layout">
			<main class="wpss-profile-main">
				<?php
				$intro_video_embed = isset( $profile->intro_video_url ) && '' !== $profile->intro_video_url
					? wpss_render_video_embed( $profile->intro_video_url, sprintf( /* translators: %s: vendor display name */ __( 'Intro video from %s', 'wp-sell-services' ), $profile->display_name ) )
					: '';
				?>
				<?php if ( '' !== $intro_video_embed ) : ?>
					<section class="wpss-profile-section wpss-profile-section--video">
						<h2><?php esc_html_e( 'Introduction', 'wp-sell-services' ); ?></h2>
						<?php echo $intro_video_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns an HTML fragment with escaped attributes. ?>
					</section>
				<?php endif; ?>

				<!-- About -->
				<?php if ( $bio ) : ?>
					<section class="wpss-profile-section">
						<h2><?php esc_html_e( 'About', 'wp-sell-services' ); ?></h2>
						<div class="wpss-profile-bio">
							<?php echo wp_kses_post( wpautop( $bio ) ); ?>
						</div>

						<?php
						/**
						 * Hook: wpss_vendor_profile_bio
						 *
						 * Fires after the vendor bio/description area.
						 * Use this to add additional profile information.
						 *
						 * @since 1.0.0
						 *
						 * @param int $vendor_id Vendor user ID.
						 */
						do_action( 'wpss_vendor_profile_bio', $vendor_id );
						?>
					</section>
				<?php endif; ?>

				<!-- Services -->
				<?php if ( ! empty( $services ) ) : ?>
					<section class="wpss-profile-section">
						<h2><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></h2>

						<?php
						/**
						 * Hook: wpss_vendor_profile_services
						 *
						 * Fires before the services grid display.
						 * Use this to add custom service filters or sorting.
						 *
						 * @since 1.0.0
						 *
						 * @param int $vendor_id Vendor user ID.
						 */
						do_action( 'wpss_vendor_profile_services', $vendor_id );
						?>

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

						<?php if ( $total_services > 6 ) : ?>
							<p class="wpss-view-all">
								<a href="<?php echo esc_url( add_query_arg( 'vendor', $vendor_id, get_post_type_archive_link( 'wpss_service' ) ) ); ?>">
									<?php
									printf(
										/* translators: %d: number of services */
										esc_html__( 'View all %d services', 'wp-sell-services' ),
										absint( $total_services )
									);
									?>
									&rarr;
								</a>
							</p>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php
				// Portfolio section.
				$portfolio_service = new \WPSellServices\Services\PortfolioService();
				$portfolio_items   = $portfolio_service->get_featured( $vendor_id, 6 );

				if ( ! empty( $portfolio_items ) ) :
				?>
				<div class="wpss-profile-section wpss-profile-portfolio">
					<h2 class="wpss-profile-section__title">
						<?php esc_html_e( 'Portfolio', 'wp-sell-services' ); ?>
					</h2>
					<?php
					wpss_get_template(
						'partials/vendor-portfolio.php',
						array(
							'portfolio_items' => $portfolio_items,
							'vendor_id'       => $vendor_id,
						)
					);
					?>
				</div>
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

						<?php
						/**
						 * Hook: wpss_vendor_profile_reviews
						 *
						 * Fires before the reviews list display.
						 * Use this to add review filters or sorting options.
						 *
						 * @since 1.0.0
						 *
						 * @param int $vendor_id Vendor user ID.
						 */
						do_action( 'wpss_vendor_profile_reviews', $vendor_id );
						?>

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
				<?php
				/**
				 * Hook: wpss_vendor_profile_stats
				 *
				 * Fires at the start of the vendor stats area.
				 * Use this to add custom statistics or badges.
				 *
				 * @since 1.0.0
				 *
				 * @param int $vendor_id Vendor user ID.
				 */
				do_action( 'wpss_vendor_profile_stats', $vendor_id );
				?>

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
							<span class="wpss-stat-value"><?php echo esc_html( $total_services ); ?></span>
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

				<?php
				/**
				 * Filter additional vendor profile fields.
				 *
				 * Pro uses this to add PayPal payout email and other vendor fields.
				 *
				 * @since 1.1.0
				 *
				 * @param array $fields    Array of field HTML strings to render.
				 * @param int   $vendor_id Vendor user ID.
				 */
				$extra_profile_fields = apply_filters( 'wpss_vendor_profile_fields', array(), $vendor_id );

				if ( ! empty( $extra_profile_fields ) ) :
					ob_start();

					foreach ( $extra_profile_fields as $field ) {
						if ( is_array( $field ) ) {
							// Structured field definition (e.g. from Pro plugin).
							$label = trim( (string) ( $field['label'] ?? '' ) );
							$value = trim( (string) ( $field['value'] ?? '' ) );

							if ( '' !== $label && '' !== $value ) {
								printf(
									'<div class="wpss-profile-field"><dt>%s</dt><dd>%s</dd></div>',
									esc_html( $label ),
									esc_html( $value )
								);
							}
						} else {
							$sanitized_field = trim( wp_kses_post( (string) $field ) );

							if ( '' !== wp_strip_all_tags( $sanitized_field ) ) {
								echo $sanitized_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
						}
					}

					$rendered_extra_profile_fields = trim( ob_get_clean() );

					if ( '' !== $rendered_extra_profile_fields ) :
						?>
						<div class="wpss-sidebar-card">
							<?php echo $rendered_extra_profile_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<?php
					endif;
				endif;
				?>

				<?php
				/**
				 * Hook: wpss_vendor_profile_sidebar
				 *
				 * Fires at the end of the vendor profile sidebar.
				 * Use this to add custom sidebar widgets or cards.
				 *
				 * @since 1.0.0
				 *
				 * @param int $vendor_id Vendor user ID.
				 */
				do_action( 'wpss_vendor_profile_sidebar', $vendor_id );
				?>
			</aside>
		</div>
	</div>
</div>

<?php
// Render contact modal for logged-in users who are not the vendor.
if ( is_user_logged_in() && get_current_user_id() !== $vendor_id ) :
	$response_time_display = $vendor_service->get_response_time( $vendor_id );
	?>
	<div id="wpss-contact-modal" class="wpss-modal" hidden role="dialog" aria-modal="true" aria-labelledby="wpss-contact-modal-title">
		<div class="wpss-modal-backdrop wpss-modal-overlay"></div>
		<div class="wpss-modal-dialog wpss-modal-content">
			<button type="button" class="wpss-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				&times;
			</button>

			<div class="wpss-modal-header">
				<h3 id="wpss-contact-modal-title"><?php esc_html_e( 'Contact Seller', 'wp-sell-services' ); ?></h3>
			</div>

			<div class="wpss-modal-body">
				<div class="wpss-contact-vendor-info">
					<img src="<?php echo esc_url( get_avatar_url( $vendor_id, array( 'size' => 50 ) ) ); ?>"
						alt="<?php echo esc_attr( $vendor->display_name ); ?>"
						class="wpss-vendor-avatar">
					<div class="wpss-vendor-details">
						<strong><?php echo esc_html( $vendor->display_name ); ?></strong>
						<?php if ( $response_time_display ) : ?>
							<span class="wpss-response-time">
								<?php
								printf(
									/* translators: %s: response time */
									esc_html__( 'Usually responds in %s', 'wp-sell-services' ),
									esc_html( $response_time_display )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<form id="wpss-contact-form" class="wpss-contact-form">
					<input type="hidden" name="vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>">
					<input type="hidden" name="service_id" value="0">

					<div class="wpss-form-field">
						<label for="wpss-contact-message"><?php esc_html_e( 'Your Message', 'wp-sell-services' ); ?></label>
						<textarea id="wpss-contact-message"
									name="message"
									rows="5"
									placeholder="<?php esc_attr_e( 'Tell the seller what you need...', 'wp-sell-services' ); ?>"
									required></textarea>
						<p class="wpss-field-hint">
							<?php esc_html_e( 'Be specific about your requirements for better assistance.', 'wp-sell-services' ); ?>
						</p>
					</div>

					<div class="wpss-form-field">
						<label for="wpss-contact-attachment"><?php esc_html_e( 'Attach Files (optional)', 'wp-sell-services' ); ?></label>
						<input type="file"
								id="wpss-contact-attachment"
								name="attachments[]"
								multiple
								accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.zip">
						<p class="wpss-field-hint">
							<?php esc_html_e( 'Max 5 files. Allowed: images, PDF, Word, ZIP.', 'wp-sell-services' ); ?>
						</p>
					</div>

					<div class="wpss-form-actions">
						<button type="submit" class="wpss-btn wpss-btn-primary">
							<?php esc_html_e( 'Send Message', 'wp-sell-services' ); ?>
						</button>
						<button type="button" class="wpss-btn wpss-btn-secondary wpss-modal-close">							
							&times;
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
endif;

/**
 * Hook: wpss_after_vendor_profile
 *
 * @param int $vendor_id Vendor user ID.
 */
do_action( 'wpss_after_vendor_profile', $vendor_id );

get_footer();
