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
			$has_image       = has_post_thumbnail();
			$gallery_image   = null;

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
					<svg class="wpss-service-card__placeholder-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
						<circle cx="8.5" cy="8.5" r="1.5"/>
						<polyline points="21 15 16 10 5 21"/>
					</svg>
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
						<svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
							<path d="M8 0l2.5 2.5H14v3.5L16 8l-2 2v3.5h-3.5L8 16l-2.5-2.5H2v-3.5L0 8l2-2V2.5h3.5L8 0zm-.5 11.5l5-5-1.5-1.5-3.5 3.5-1.5-1.5-1.5 1.5 3 3z"/>
						</svg>
					</span>
				<?php endif; ?>
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
					<svg class="wpss-service-card__star" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
						<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
					</svg>
					<span class="wpss-service-card__rating-value"><?php echo esc_html( number_format( $rating_avg, 1 ) ); ?></span>
					<span class="wpss-service-card__rating-count">
						<?php
						printf(
							/* translators: %d: number of reviews */
							esc_html( _n( '(%d)', '(%d)', $rating_count, 'wp-sell-services' ) ),
							$rating_count
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
