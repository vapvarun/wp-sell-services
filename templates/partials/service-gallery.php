<?php
/**
 * Template Partial: Service Gallery
 *
 * Displays the service image gallery.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var WPSellServices\Models\Service $service Service object.
 */

defined( 'ABSPATH' ) || exit;

$service_id = get_the_ID();
$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true ) ?: [];

// ServiceWizard saves as ['images' => [...], 'video' => '...'] — extract the image IDs.
if ( isset( $gallery_raw['images'] ) && is_array( $gallery_raw['images'] ) ) {
	$gallery_ids = array_map( 'absint', $gallery_raw['images'] );
	$video_url   = $gallery_raw['video'] ?? '';
	if ( $video_url ) {
		update_post_meta( $service_id, '_wpss_video_url', esc_url_raw( $video_url ) );
	}
} else {
	$gallery_ids = is_array( $gallery_raw ) ? array_map( 'absint', $gallery_raw ) : [];
}

$has_thumbnail = has_post_thumbnail( $service_id );

// Add featured image to gallery if exists.
if ( $has_thumbnail ) {
	array_unshift( $gallery_ids, get_post_thumbnail_id( $service_id ) );
}

if ( empty( $gallery_ids ) ) {
	return;
}

$gallery_ids = array_unique( array_filter( $gallery_ids ) );

// Exit if no valid images after filtering.
if ( empty( $gallery_ids ) ) {
	return;
}
?>

<div class="wpss-service-gallery">
	<div class="wpss-gallery-main">
		<?php
		$first_image = reset( $gallery_ids ); // Use reset() instead of [0] to handle non-sequential keys.
		$is_video = get_post_meta( $service_id, '_wpss_video_url', true );
		?>
		<div class="wpss-gallery-active">
			<?php if ( $is_video ) : ?>
				<div class="wpss-gallery-video">
					<?php
					$video_url = get_post_meta( $service_id, '_wpss_video_url', true );
					// Extract video embed.
					echo wp_oembed_get( esc_url( $video_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			<?php else : ?>
				<img src="<?php echo esc_url( wp_get_attachment_image_url( $first_image, 'large' ) ); ?>"
					 alt="<?php echo esc_attr( get_the_title() ); ?>"
					 class="wpss-gallery-image">
			<?php endif; ?>
		</div>
	</div>

	<?php if ( count( $gallery_ids ) > 1 ) : ?>
		<div class="wpss-gallery-thumbs">
			<?php foreach ( $gallery_ids as $index => $image_id ) : ?>
				<button type="button"
						class="wpss-gallery-thumb <?php echo 0 === $index ? 'active' : ''; ?>"
						data-index="<?php echo esc_attr( $index ); ?>"
						data-src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'large' ) ); ?>">
					<img src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'thumbnail' ) ); ?>"
						 alt="<?php echo esc_attr( get_the_title() . ' - ' . ( $index + 1 ) ); ?>">
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
