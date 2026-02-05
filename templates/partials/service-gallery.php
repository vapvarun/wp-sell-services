<?php
/**
 * Template Partial: Service Gallery
 *
 * Displays the service image gallery.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var WPSellServices\Models\Service $service     Service object.
 * @var int                            $service_id  Service post ID.
 * @var array                          $gallery_ids Array of gallery image attachment IDs.
 */

defined( 'ABSPATH' ) || exit;

$service_id  = get_the_ID();
$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true ) ?: [];
$gallery_ids = [];

// Handle multiple gallery formats for compatibility.
if ( isset( $gallery_raw['images'] ) && is_array( $gallery_raw['images'] ) ) {
	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	$gallery_ids = array_map( 'absint', $gallery_raw['images'] );
	$video_url   = $gallery_raw['video'] ?? '';
	if ( $video_url ) {
		update_post_meta( $service_id, '_wpss_video_url', esc_url_raw( $video_url ) );
	}
} elseif ( ! empty( $gallery_raw ) && is_array( $gallery_raw ) && isset( $gallery_raw[0]['type'] ) ) {
	// GalleryService format: [['type' => 'image', 'attachment_id' => 123], ...].
	foreach ( $gallery_raw as $item ) {
		if ( 'image' === ( $item['type'] ?? '' ) && ! empty( $item['attachment_id'] ) ) {
			$gallery_ids[] = absint( $item['attachment_id'] );
		} elseif ( 'video' === ( $item['type'] ?? '' ) && ! empty( $item['url'] ) ) {
			update_post_meta( $service_id, '_wpss_video_url', esc_url_raw( $item['url'] ) );
		}
	}
} elseif ( is_array( $gallery_raw ) ) {
	// Legacy flat array of IDs: [123, 456, ...].
	$gallery_ids = array_map( 'absint', $gallery_raw );
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

/**
 * Fires before the service gallery.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_before_service_gallery', $service_id );
?>

<div class="wpss-service-gallery">
	<div class="wpss-gallery-main">
		<?php
		$first_image = reset( $gallery_ids ); // Use reset() instead of [0] to handle non-sequential keys.
		$is_video = get_post_meta( $service_id, '_wpss_video_url', true );

		/**
		 * Filters the gallery image size.
		 *
		 * @since 1.0.0
		 *
		 * @param string $size       Image size (default: 'large').
		 * @param int    $service_id Service post ID.
		 */
		$image_size = apply_filters( 'wpss_gallery_image_size', 'large', $service_id );
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
				<img src="<?php echo esc_url( wp_get_attachment_image_url( $first_image, $image_size ) ); ?>"
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
						data-src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, $image_size ) ); ?>">
					<img src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'thumbnail' ) ); ?>"
						 alt="<?php echo esc_attr( get_the_title() . ' - ' . ( $index + 1 ) ); ?>">
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the service gallery.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_after_service_gallery', $service_id );
?>
