<?php
/**
 * Template: Service Card
 *
 * Displays a service card in archive/grid views.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/content-service-card.php
 *
 * Available hooks:
 * - wpss_before_service_card - Before card wrapper
 * - wpss_service_card_image_overlay - Inside image area for badges
 * - wpss_service_card_header - After title, before rating
 * - wpss_service_card_meta - After vendor info, for custom metadata
 * - wpss_service_card_footer - Before price display
 * - wpss_after_service_card - After card wrapper
 *
 * Available filters:
 * - wpss_service_card_classes - Modify card CSS classes
 * - wpss_service_card_thumbnail_size - Change thumbnail size (default: medium_large)
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$service_id     = get_the_ID();
$vendor_id      = (int) get_post_field( 'post_author', $service_id );
$vendor         = get_userdata( $vendor_id );
$starting_price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
$rating_avg     = (float) get_post_meta( $service_id, '_wpss_rating_average', true );
$rating_count   = (int) get_post_meta( $service_id, '_wpss_rating_count', true );
$categories     = wp_get_post_terms( $service_id, 'wpss_service_category', array( 'fields' => 'names' ) );

// Filter card classes.
$card_classes = apply_filters( 'wpss_service_card_classes', array( 'wpss-service-card' ), $service_id );
?>

<?php
/**
 * Hook: wpss_before_service_card
 *
 * Fires before the service card wrapper.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_before_service_card', $service_id );
?>

<article <?php post_class( $card_classes ); ?>>
	<a href="<?php the_permalink(); ?>" class="wpss-service-card__link">
		<div class="wpss-service-card__media">
			<?php
			// Get thumbnail size via filter.
			$thumbnail_size = apply_filters( 'wpss_service_card_thumbnail_size', 'medium_large', $service_id );

			// Check for featured image first.
			$has_image     = has_post_thumbnail();
			$gallery_image = null;

			// Fallback to first gallery image if no featured image.
			if ( ! $has_image ) {
				$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true );
				$gallery_ids = wpss_get_gallery_ids( $gallery_raw );
				if ( ! empty( $gallery_ids[0] ) ) {
					$gallery_image = $gallery_ids[0];
				}
			}
			?>
			<?php if ( $has_image ) : ?>
				<?php the_post_thumbnail( $thumbnail_size, array( 'class' => 'wpss-service-card__image' ) ); ?>
			<?php elseif ( $gallery_image ) : ?>
				<?php echo wp_get_attachment_image( $gallery_image, $thumbnail_size, false, array( 'class' => 'wpss-service-card__image' ) ); ?>
			<?php else : ?>
				<div class="wpss-service-card__placeholder">
					<i data-lucide="image" class="wpss-icon wpss-service-card__placeholder-icon" aria-hidden="true"></i>
				</div>
			<?php endif; ?>

			<?php
			/**
			 * Hook: wpss_service_card_image_overlay
			 *
			 * Fires inside the image area, useful for badges, icons, or overlays.
			 *
			 * @since 1.0.0
			 *
			 * @param int $service_id Service post ID.
			 */
			do_action( 'wpss_service_card_image_overlay', $service_id );
			?>

			<?php if ( ! empty( $categories ) ) : ?>
				<span class="wpss-service-card__category"><?php echo esc_html( $categories[0] ); ?></span>
			<?php endif; ?>
		</div>

		<div class="wpss-service-card__body">
			<div class="wpss-service-card__vendor">
				<img src="<?php echo esc_url( get_avatar_url( $vendor_id, array( 'size' => 32 ) ) ); ?>"
					alt="<?php echo esc_attr( $vendor ? $vendor->display_name : '' ); ?>"
					class="wpss-service-card__vendor-avatar">
				<span class="wpss-service-card__vendor-name">
					<?php echo esc_html( $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?>
				</span>
				<?php if ( get_user_meta( $vendor_id, '_wpss_vendor_verified', true ) ) : ?>
					<span class="wpss-service-card__verified" title="<?php esc_attr_e( 'Verified Vendor', 'wp-sell-services' ); ?>">
						<i data-lucide="badge-check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
					</span>
				<?php endif; ?>
				<?php
				$card_vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $vendor_id );
				if ( $card_vendor_profile ) :
					$card_tier = $card_vendor_profile->tier;

					if ( \WPSellServices\Models\VendorProfile::TIER_NEW !== $card_tier ) {
						// Earned-tier badge — Rising / Top Rated / Pro.
						$card_tier_label  = $card_vendor_profile->get_tier_label();
						$card_tier_colors = array(
							'rising'    => 'background:#eff6ff;color:#2563eb;',
							'top_rated' => 'background:#fefce8;color:#ca8a04;',
							'pro'       => 'background:#faf5ff;color:#7c3aed;',
						);
						$card_tier_style  = $card_tier_colors[ $card_tier ] ?? '';
						if ( $card_tier_style ) {
							?>
							<span class="wpss-seller-badge wpss-seller-badge--<?php echo esc_attr( $card_tier ); ?>" style="display:inline-block;font-size:10px;font-weight:600;padding:1px 6px;border-radius:9999px;margin-left:4px;<?php echo esc_attr( $card_tier_style ); ?>">
								<?php echo esc_html( $card_tier_label ); ?>
							</span>
							<?php
						}
					} elseif ( ! $card_vendor_profile->is_profile_complete() ) {
						// F7b (baseline-2026-04-25.md): "New seller" badge for
						// TIER_NEW vendors who haven't filled tagline / bio /
						// country. Soft signal — vendor is still listed; buyer
						// just sees the badge as a "ask more questions" hint.
						?>
						<span class="wpss-seller-badge wpss-seller-badge--new" style="display:inline-block;font-size:10px;font-weight:600;padding:1px 6px;border-radius:9999px;margin-left:4px;background:#f3f4f6;color:#6b7280;">
							<?php esc_html_e( 'New seller', 'wp-sell-services' ); ?>
						</span>
						<?php
					}
				endif; ?>
			</div>

			<?php
			/**
			 * Hook: wpss_service_card_meta
			 *
			 * Fires after vendor info, useful for custom metadata like ratings, badges, etc.
			 *
			 * @since 1.0.0
			 *
			 * @param int $service_id Service post ID.
			 */
			do_action( 'wpss_service_card_meta', $service_id );
			?>

			<h3 class="wpss-service-card__title"><?php the_title(); ?></h3>

			<?php
			/**
			 * Hook: wpss_service_card_header
			 *
			 * Fires after the service title, before rating display.
			 *
			 * @since 1.0.0
			 *
			 * @param int $service_id Service post ID.
			 */
			do_action( 'wpss_service_card_header', $service_id );
			?>

			<div class="wpss-service-card__rating">
				<?php if ( $rating_count > 0 ) : ?>
					<i data-lucide="star" class="wpss-icon wpss-icon--sm wpss-service-card__star" aria-hidden="true"></i>
					<span class="wpss-service-card__rating-value"><?php echo esc_html( number_format( $rating_avg, 1 ) ); ?></span>
					<span class="wpss-service-card__rating-count">
						<?php
						printf(
							/* translators: %d: number of reviews */
							esc_html( _n( '(%d)', '(%d)', $rating_count, 'wp-sell-services' ) ),
							absint( $rating_count )
						);
						?>
					</span>
				<?php else : ?>
					<span class="wpss-service-card__rating-new"><?php esc_html_e( 'New', 'wp-sell-services' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpss-service-card__footer">
			<?php
			/**
			 * Hook: wpss_service_card_footer
			 *
			 * Fires inside the footer area, before the price display.
			 *
			 * @since 1.0.0
			 *
			 * @param int $service_id Service post ID.
			 */
			do_action( 'wpss_service_card_footer', $service_id );
			?>

			<span class="wpss-service-card__price-label"><?php esc_html_e( 'Starting at', 'wp-sell-services' ); ?></span>
			<span class="wpss-service-card__price"><?php echo esc_html( wpss_format_price( $starting_price ) ); ?></span>
		</div>
	</a>

	<?php
	/**
	 * Hook: wpss_after_service_card
	 *
	 * Fires after the service card wrapper closes.
	 *
	 * @since 1.0.0
	 *
	 * @param int $service_id Service post ID.
	 */
	do_action( 'wpss_after_service_card', $service_id );
	?>
</article>
