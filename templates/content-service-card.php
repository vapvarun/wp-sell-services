<?php
/**
 * Template: Service Card
 *
 * Displays a service card in archive/grid views.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/content-service-card.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$service_id    = get_the_ID();
$vendor_id     = (int) get_post_field( 'post_author', $service_id );
$vendor        = get_userdata( $vendor_id );
$starting_price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
$rating_avg    = (float) get_post_meta( $service_id, '_wpss_rating_average', true );
$rating_count  = (int) get_post_meta( $service_id, '_wpss_rating_count', true );
$categories    = wp_get_post_terms( $service_id, 'wpss_service_category', [ 'fields' => 'names' ] );
?>

<article <?php post_class( 'wpss-service-card' ); ?>>
	<a href="<?php the_permalink(); ?>" class="wpss-card-link">
		<div class="wpss-card-image">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'medium_large' ); ?>
			<?php else : ?>
				<div class="wpss-card-placeholder">
					<span class="wpss-icon wpss-icon-service"></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $categories ) ) : ?>
				<span class="wpss-card-category"><?php echo esc_html( $categories[0] ); ?></span>
			<?php endif; ?>
		</div>

		<div class="wpss-card-content">
			<div class="wpss-card-vendor">
				<img src="<?php echo esc_url( get_avatar_url( $vendor_id, [ 'size' => 32 ] ) ); ?>"
					 alt="<?php echo esc_attr( $vendor ? $vendor->display_name : '' ); ?>"
					 class="wpss-vendor-avatar">
				<span class="wpss-vendor-name">
					<?php echo esc_html( $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?>
				</span>
				<?php if ( get_user_meta( $vendor_id, '_wpss_vendor_verified', true ) ) : ?>
					<span class="wpss-verified-badge" title="<?php esc_attr_e( 'Verified Vendor', 'wp-sell-services' ); ?>">
						<svg viewBox="0 0 16 16" width="14" height="14">
							<path fill="currentColor" d="M8 0l2.5 2.5H14v3.5L16 8l-2 2v3.5h-3.5L8 16l-2.5-2.5H2v-3.5L0 8l2-2V2.5h3.5L8 0zm-.5 11.5l5-5-1.5-1.5-3.5 3.5-1.5-1.5-1.5 1.5 3 3z"/>
						</svg>
					</span>
				<?php endif; ?>
			</div>

			<h3 class="wpss-card-title"><?php the_title(); ?></h3>

			<div class="wpss-card-rating">
				<?php if ( $rating_count > 0 ) : ?>
					<span class="wpss-stars" data-rating="<?php echo esc_attr( $rating_avg ); ?>">
						<?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
					</span>
					<span class="wpss-rating-count">
						<?php
						printf(
							/* translators: %d: number of reviews */
							esc_html( _n( '(%d)', '(%d)', $rating_count, 'wp-sell-services' ) ),
							$rating_count
						);
						?>
					</span>
				<?php else : ?>
					<span class="wpss-no-rating"><?php esc_html_e( 'New', 'wp-sell-services' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpss-card-footer">
			<span class="wpss-card-price-label"><?php esc_html_e( 'Starting at', 'wp-sell-services' ); ?></span>
			<span class="wpss-card-price"><?php echo esc_html( wpss_format_price( $starting_price ) ); ?></span>
		</div>
	</a>

	<?php
	/**
	 * Hook: wpss_after_service_card
	 *
	 * @param int $service_id Service post ID.
	 */
	do_action( 'wpss_after_service_card', $service_id );
	?>
</article>
