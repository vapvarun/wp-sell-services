<?php
/**
 * Gallery Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

/**
 * Manages service gallery images and videos.
 *
 * @since 1.0.0
 */
class GalleryService {

	/**
	 * Meta key for gallery data.
	 */
	private const META_KEY = '_wpss_gallery';

	/**
	 * Allowed image types.
	 *
	 * @var array
	 */
	private array $allowed_image_types = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];

	/**
	 * Allowed video types.
	 *
	 * @var array
	 */
	private array $allowed_video_types = [ 'mp4', 'webm', 'mov' ];

	/**
	 * Get gallery items for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array Gallery items.
	 */
	public function get_gallery( int $service_id ): array {
		$gallery = get_post_meta( $service_id, self::META_KEY, true );
		return is_array( $gallery ) ? $gallery : [];
	}

	/**
	 * Save gallery items for a service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $items      Gallery items.
	 * @return bool
	 */
	public function save_gallery( int $service_id, array $items ): bool {
		$sanitized = [];

		foreach ( $items as $item ) {
			$sanitized_item = $this->sanitize_item( $item );
			if ( $sanitized_item ) {
				$sanitized[] = $sanitized_item;
			}
		}

		return (bool) update_post_meta( $service_id, self::META_KEY, $sanitized );
	}

	/**
	 * Add item to gallery.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $item       Gallery item data.
	 * @return bool
	 */
	public function add_item( int $service_id, array $item ): bool {
		$gallery = $this->get_gallery( $service_id );
		$sanitized = $this->sanitize_item( $item );

		if ( ! $sanitized ) {
			return false;
		}

		$gallery[] = $sanitized;

		return $this->save_gallery( $service_id, $gallery );
	}

	/**
	 * Remove item from gallery.
	 *
	 * @param int $service_id Service ID.
	 * @param int $index      Item index.
	 * @return bool
	 */
	public function remove_item( int $service_id, int $index ): bool {
		$gallery = $this->get_gallery( $service_id );

		if ( ! isset( $gallery[ $index ] ) ) {
			return false;
		}

		array_splice( $gallery, $index, 1 );

		return $this->save_gallery( $service_id, $gallery );
	}

	/**
	 * Reorder gallery items.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $order      New order (array of indices).
	 * @return bool
	 */
	public function reorder( int $service_id, array $order ): bool {
		$gallery = $this->get_gallery( $service_id );
		$reordered = [];

		foreach ( $order as $index ) {
			if ( isset( $gallery[ $index ] ) ) {
				$reordered[] = $gallery[ $index ];
			}
		}

		return $this->save_gallery( $service_id, $reordered );
	}

	/**
	 * Get gallery images only.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_images( int $service_id ): array {
		$gallery = $this->get_gallery( $service_id );

		return array_filter(
			$gallery,
			fn( $item ) => 'image' === ( $item['type'] ?? '' )
		);
	}

	/**
	 * Get gallery videos only.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_videos( int $service_id ): array {
		$gallery = $this->get_gallery( $service_id );

		return array_filter(
			$gallery,
			fn( $item ) => 'video' === ( $item['type'] ?? '' )
		);
	}

	/**
	 * Get first gallery image URL.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $size       Image size.
	 * @return string|null
	 */
	public function get_primary_image( int $service_id, string $size = 'large' ): ?string {
		$images = $this->get_images( $service_id );

		if ( empty( $images ) ) {
			return null;
		}

		$first = reset( $images );
		$attachment_id = $first['attachment_id'] ?? 0;

		if ( ! $attachment_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $attachment_id, $size );

		return $image[0] ?? null;
	}

	/**
	 * Sanitize a gallery item.
	 *
	 * @param array $item Raw item data.
	 * @return array|null Sanitized item or null if invalid.
	 */
	private function sanitize_item( array $item ): ?array {
		$type = $item['type'] ?? '';

		if ( ! in_array( $type, [ 'image', 'video', 'embed' ], true ) ) {
			return null;
		}

		$sanitized = [
			'type' => $type,
		];

		switch ( $type ) {
			case 'image':
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
					return null;
				}
				$sanitized['attachment_id'] = $attachment_id;
				$sanitized['alt'] = sanitize_text_field( $item['alt'] ?? '' );
				break;

			case 'video':
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				if ( ! $attachment_id ) {
					return null;
				}

				$file = get_attached_file( $attachment_id ) ?: '';
				$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

				if ( ! in_array( $ext, $this->allowed_video_types, true ) ) {
					return null;
				}

				$sanitized['attachment_id'] = $attachment_id;
				$sanitized['poster_id'] = absint( $item['poster_id'] ?? 0 );
				break;

			case 'embed':
				$url = esc_url_raw( $item['url'] ?? '' );
				if ( ! $url || ! $this->is_valid_embed_url( $url ) ) {
					return null;
				}
				$sanitized['url'] = $url;
				$sanitized['title'] = sanitize_text_field( $item['title'] ?? '' );
				break;
		}

		return $sanitized;
	}

	/**
	 * Check if URL is a valid embed URL.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	private function is_valid_embed_url( string $url ): bool {
		$allowed_hosts = [
			'youtube.com',
			'www.youtube.com',
			'youtu.be',
			'vimeo.com',
			'www.vimeo.com',
		];

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return in_array( $host, $allowed_hosts, true );
	}

	/**
	 * Render gallery HTML.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $args       Display arguments.
	 * @return string HTML output.
	 */
	public function render( int $service_id, array $args = [] ): string {
		$defaults = [
			'size'       => 'large',
			'thumb_size' => 'thumbnail',
			'class'      => 'wpss-gallery',
			'lightbox'   => true,
		];

		$args = wp_parse_args( $args, $defaults );
		$gallery = $this->get_gallery( $service_id );

		if ( empty( $gallery ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $args['class'] ); ?>" data-lightbox="<?php echo esc_attr( $args['lightbox'] ? 'true' : 'false' ); ?>">
			<div class="wpss-gallery-main">
				<?php echo $this->render_item( $gallery[0], $args['size'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php if ( count( $gallery ) > 1 ) : ?>
				<div class="wpss-gallery-thumbnails">
					<?php foreach ( $gallery as $index => $item ) : ?>
						<div class="wpss-gallery-thumb <?php echo 0 === $index ? 'active' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>">
							<?php echo $this->render_thumbnail( $item, $args['thumb_size'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single gallery item.
	 *
	 * @param array  $item Item data.
	 * @param string $size Image size.
	 * @return string HTML output.
	 */
	private function render_item( array $item, string $size ): string {
		$type = $item['type'] ?? '';

		switch ( $type ) {
			case 'image':
				return wp_get_attachment_image(
					$item['attachment_id'],
					$size,
					false,
					[
						'class' => 'wpss-gallery-image',
						'alt'   => $item['alt'] ?? '',
					]
				);

			case 'video':
				$url = wp_get_attachment_url( $item['attachment_id'] );
				$poster = ! empty( $item['poster_id'] ) ? wp_get_attachment_url( $item['poster_id'] ) : '';

				return sprintf(
					'<video class="wpss-gallery-video" controls %s><source src="%s" type="video/mp4"></video>',
					$poster ? 'poster="' . esc_url( $poster ) . '"' : '',
					esc_url( $url )
				);

			case 'embed':
				$embed = wp_oembed_get( $item['url'] );
				return $embed ?: sprintf(
					'<iframe class="wpss-gallery-embed" src="%s" allowfullscreen></iframe>',
					esc_url( $item['url'] )
				);

			default:
				return '';
		}
	}

	/**
	 * Render thumbnail for gallery item.
	 *
	 * @param array  $item Item data.
	 * @param string $size Thumbnail size.
	 * @return string HTML output.
	 */
	private function render_thumbnail( array $item, string $size ): string {
		$type = $item['type'] ?? '';

		switch ( $type ) {
			case 'image':
				return wp_get_attachment_image( $item['attachment_id'], $size );

			case 'video':
				if ( ! empty( $item['poster_id'] ) ) {
					return wp_get_attachment_image( $item['poster_id'], $size );
				}
				return '<span class="wpss-video-thumb dashicons dashicons-video-alt3"></span>';

			case 'embed':
				return '<span class="wpss-embed-thumb dashicons dashicons-format-video"></span>';

			default:
				return '';
		}
	}

	/**
	 * Render admin gallery manager.
	 *
	 * @param int $service_id Service ID.
	 * @return string HTML output.
	 */
	public function render_admin( int $service_id ): string {
		$gallery = $this->get_gallery( $service_id );

		ob_start();
		?>
		<div class="wpss-gallery-admin" data-service-id="<?php echo esc_attr( $service_id ); ?>">
			<div class="wpss-gallery-items" id="wpss-gallery-items">
				<?php foreach ( $gallery as $index => $item ) : ?>
					<?php echo $this->render_admin_item( $item, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>

			<div class="wpss-gallery-actions">
				<button type="button" class="button wpss-add-image" data-type="image">
					<span class="dashicons dashicons-format-image"></span>
					<?php esc_html_e( 'Add Image', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="button wpss-add-video" data-type="video">
					<span class="dashicons dashicons-video-alt3"></span>
					<?php esc_html_e( 'Add Video', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="button wpss-add-embed" data-type="embed">
					<span class="dashicons dashicons-admin-site"></span>
					<?php esc_html_e( 'Add YouTube/Vimeo', 'wp-sell-services' ); ?>
				</button>
			</div>

			<input type="hidden" name="wpss_gallery" id="wpss-gallery-data" value="<?php echo esc_attr( wp_json_encode( $gallery ) ); ?>">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render admin gallery item.
	 *
	 * @param array $item  Item data.
	 * @param int   $index Item index.
	 * @return string HTML output.
	 */
	private function render_admin_item( array $item, int $index ): string {
		$type = $item['type'] ?? '';

		ob_start();
		?>
		<div class="wpss-gallery-item" data-index="<?php echo esc_attr( $index ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
			<div class="wpss-gallery-item-preview">
				<?php echo $this->render_thumbnail( $item, 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="wpss-gallery-item-actions">
				<button type="button" class="wpss-gallery-move" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>">
					<span class="dashicons dashicons-move"></span>
				</button>
				<button type="button" class="wpss-gallery-remove" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<span class="wpss-gallery-type-badge"><?php echo esc_html( ucfirst( $type ) ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}
}
