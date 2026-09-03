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
$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true );
$gallery_ids = wpss_get_gallery_ids( $gallery_raw );

/*
 * Resolve the video URL from the gallery, and write nothing.
 *
 * This block used to call update_post_meta() on every render, so every
 * anonymous GET of a single-service page wrote a row - cache-hostile, and two
 * simultaneous visitors raced each other over the same value. A template is a
 * read surface.
 *
 * `_wpss_video_url` is deliberately NOT read. That key had exactly one writer -
 * the render-time write removed above - and it derived its value from this same
 * gallery meta, so it can never hold anything the gallery does not. What it CAN
 * hold is a stale value: a vendor who removes the video from their gallery
 * would still have the old row, and reading it first would keep showing a video
 * they deleted. One source of truth is both simpler and more correct.
 */
$video_url = (string) wpss_get_gallery_video_url( $gallery_raw );

$has_thumbnail = has_post_thumbnail( $service_id );

// Add featured image to gallery if exists.
if ( $has_thumbnail ) {
	array_unshift( $gallery_ids, get_post_thumbnail_id( $service_id ) );
}

if ( empty( $gallery_ids ) ) {
	return;
}

/*
 * array_values() matters: array_unique() and array_filter() PRESERVE keys, so a
 * vendor whose featured image also sits in the gallery got keys 0, 2, 3 for a
 * three-image strip. That leaked into the "Title - 4" alt text of a third
 * thumbnail and into data-index, and it would break the first-thumbnail
 * `0 === $index` active state the moment key 0 were the duplicate one.
 */
$gallery_ids = array_values( array_unique( array_filter( $gallery_ids ) ) );

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
			<?php
			/*
			 * The image comes first, always. The video used to take the main
			 * area whenever one existed, so a buyer landed on an autoplaying-
			 * capable embed instead of the work being sold (Basecamp
			 * 10208068212).
			 */
			?>
			<?php
			/*
			 * Raw post_title, never get_the_title().
			 *
			 * ShellHeader::maybe_suppress_theme_title() blanks `the_title` for
			 * the queried object on every plugin-shell surface so the theme
			 * stops printing a duplicate H1, and a single service page IS one.
			 * It cannot tell who is asking, so this alt shipped as alt="" and
			 * the thumbnails below as alt=" - 1".
			 */
			$service_title = (string) get_post_field( 'post_title', $service_id );
			?>
			<img src="<?php echo esc_url( wp_get_attachment_image_url( $first_image, $image_size ) ); ?>"
				alt="<?php echo esc_attr( $service_title ); ?>"
				class="wpss-gallery-image">

			<?php if ( '' !== $video_url ) : ?>
				<div class="wpss-gallery-video" hidden></div>
				<?php
				/*
				 * The embed sits in a <template>, which browsers do not render
				 * or fetch. So YouTube is not contacted until a buyer actually
				 * asks for the video, and once cloned the player node is kept
				 * and toggled rather than rebuilt - switching back to it does
				 * not restart the video or re-request the embed.
				 */
				?>
				<template class="wpss-gallery-video-embed">
					<?php echo wp_oembed_get( esc_url( $video_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_oembed_get() returns provider markup. ?>
				</template>
			<?php endif; ?>
		</div>
	</div>

	<?php
	/*
	 * The video counts as an item. The old test was count( $gallery_ids ) > 1,
	 * so a service with a video and exactly ONE image rendered no strip at all
	 * and its video was unreachable.
	 */
	$wpss_thumb_count = count( $gallery_ids ) + ( '' !== $video_url ? 1 : 0 );
	?>
	<?php if ( $wpss_thumb_count > 1 ) : ?>
		<div class="wpss-gallery-thumbs">
			<?php if ( '' !== $video_url ) : ?>
				<?php
				/*
				 * The video leads the strip. It is the seller's pitch, and it is
				 * what a marketplace shows first - the main area still opens on
				 * the image, so the active thumb is the second one. Owner
				 * decision, 2026-08-18.
				 *
				 * The poster is the PROVIDER's own frame. Reusing the service's
				 * featured image made this button pixel-identical to the first
				 * image thumb next to it, so nothing but the play badge said
				 * which one was the video. Falls back to the featured image only
				 * when the provider offers no poster.
				 */
				$wpss_video_poster = wpss_get_video_thumbnail_url( $video_url );

				if ( '' === $wpss_video_poster ) {
					$wpss_video_poster = has_post_thumbnail( $service_id )
						? get_the_post_thumbnail_url( $service_id, 'thumbnail' )
						: wp_get_attachment_image_url( $first_image, 'thumbnail' );
				}
				?>
				<button type="button"
						class="wpss-gallery-thumb wpss-gallery-thumb--video"
						data-video="1"
						aria-label="<?php esc_attr_e( 'Play the service video', 'wp-sell-services' ); ?>">
					<img src="<?php echo esc_url( (string) $wpss_video_poster ); ?>" alt="" loading="lazy">
					<span class="wpss-gallery-thumb__play" aria-hidden="true">
						<i data-lucide="play"></i>
					</span>
				</button>
			<?php endif; ?>

			<?php foreach ( $gallery_ids as $index => $image_id ) : ?>
				<button type="button"
						class="wpss-gallery-thumb <?php echo 0 === $index ? 'active' : ''; ?>"
						data-index="<?php echo esc_attr( $index ); ?>"
						data-src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, $image_size ) ); ?>">
					<img src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'thumbnail' ) ); ?>"
						alt="<?php echo esc_attr( $service_title . ' - ' . ( $index + 1 ) ); ?>">
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
