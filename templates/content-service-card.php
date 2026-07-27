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

// Favorite state — resolved server-side so the toggle renders in the correct
// state on first paint (no flash). The toggle reads/writes the canonical
// the canonical favorites store via the REST favorites controller (frontend.js).
$wpss_card_is_logged_in = is_user_logged_in();
$wpss_card_favorited    = false;
if ( $wpss_card_is_logged_in ) {
	$wpss_card_favs = \WPSellServices\Services\FavoritesService::get_ids( get_current_user_id() );
	$wpss_card_favorited = is_array( $wpss_card_favs ) && in_array( $service_id, array_map( 'intval', $wpss_card_favs ), true );
}
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

			<?php
			$wpss_card_fav_label = $wpss_card_favorited
				? __( 'Remove from favorites', 'wp-sell-services' )
				: __( 'Add to favorites', 'wp-sell-services' );
			?>
			<button
				type="button"
				class="wpss-fav-toggle wpss-service-card__fav<?php echo $wpss_card_favorited ? ' is-favorited' : ''; ?>"
				data-service-id="<?php echo esc_attr( (string) $service_id ); ?>"
				data-logged-in="<?php echo $wpss_card_is_logged_in ? '1' : '0'; ?>"
				aria-pressed="<?php echo $wpss_card_favorited ? 'true' : 'false'; ?>"
				aria-label="<?php echo esc_attr( $wpss_card_fav_label ); ?>"
				title="<?php echo esc_attr( $wpss_card_fav_label ); ?>"
			>
				<i data-lucide="heart" class="wpss-icon wpss-icon--sm wpss-fav-toggle__icon" aria-hidden="true"></i>
				<span class="screen-reader-text wpss-fav-toggle__label"><?php echo esc_html( $wpss_card_fav_label ); ?></span>
			</button>

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
				<?php
				// Verified state derives from the canonical wpss_vendor_profiles
				// table (verification_tier) — the _wpss_vendor_verified user-meta
				// key was never written.
				$card_vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $vendor_id );
				?>
				<?php if ( $card_vendor_profile && $card_vendor_profile->is_verified ) : ?>
					<span class="wpss-service-card__verified" title="<?php esc_attr_e( 'Verified Vendor', 'wp-sell-services' ); ?>">
						<i data-lucide="badge-check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
					</span>
				<?php endif; ?>
				<?php
				if ( $card_vendor_profile ) :
					$card_tier = $card_vendor_profile->tier;

					if ( \WPSellServices\Models\VendorProfile::TIER_NEW !== $card_tier ) {
						// Earned-tier badge — Rising / Top Rated / Pro.
						$card_tier_label  = $card_vendor_profile->get_tier_label();
						$card_known_tiers = array( 'rising', 'top_rated', 'pro' );
						if ( in_array( $card_tier, $card_known_tiers, true ) ) {
							// Colour comes from the .wpss-seller-badge--{tier} modifier in
							// frontend.css (token-driven), not an inline style attribute.
							?>
							<span class="wpss-seller-badge wpss-seller-badge--<?php echo esc_attr( $card_tier ); ?>">
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
						<span class="wpss-seller-badge wpss-seller-badge--new">
							<?php esc_html_e( 'New seller', 'wp-sell-services' ); ?>
						</span>
						<?php
					}

					// Vacation badge — only when on vacation AND a return date is set.
					// Vacationing vendors are excluded from the archive query by
					// default, so this only surfaces if a site opts to include them
					// via filter. Reuses the already-loaded profile (no extra query).
					if ( $card_vendor_profile->is_on_vacation() && ! empty( $card_vendor_profile->vacation_return_date ) ) {
						$card_vacation_ts = strtotime( (string) $card_vendor_profile->vacation_return_date );
						if ( $card_vacation_ts ) {
							?>
							<span class="wpss-seller-badge wpss-seller-badge--vacation">
								<?php
								printf(
									/* translators: %s: formatted return date */
									esc_html__( 'Back on %s', 'wp-sell-services' ),
									esc_html( date_i18n( get_option( 'date_format' ), $card_vacation_ts ) )
								);
								?>
							</span>
							<?php
						}
					}
				endif;
				?>
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
