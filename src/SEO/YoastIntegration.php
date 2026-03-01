<?php
/**
 * Yoast SEO Integration
 *
 * @package WPSellServices\SEO
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

/**
 * Integrates with Yoast SEO plugin.
 *
 * @since 1.0.0
 */
class YoastIntegration {

	/**
	 * Initialize Yoast integration.
	 *
	 * @return void
	 */
	public function init(): void {
		// Title and meta.
		add_filter( 'wpseo_title', array( $this, 'modify_title' ), 10, 1 );
		add_filter( 'wpseo_metadesc', array( $this, 'modify_description' ), 10, 1 );
		add_filter( 'wpseo_canonical', array( $this, 'modify_canonical' ), 10, 1 );

		// Open Graph.
		add_filter( 'wpseo_opengraph_type', array( $this, 'set_og_type' ), 10, 1 );
		add_filter( 'wpseo_opengraph_title', array( $this, 'modify_og_title' ), 10, 1 );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'modify_og_description' ), 10, 1 );
		add_filter( 'wpseo_opengraph_image', array( $this, 'modify_og_image' ), 10, 1 );
		add_filter( 'wpseo_add_opengraph_additional_images', array( $this, 'add_gallery_images' ), 10, 1 );

		// Twitter cards.
		add_filter( 'wpseo_twitter_title', array( $this, 'modify_og_title' ), 10, 1 );
		add_filter( 'wpseo_twitter_description', array( $this, 'modify_og_description' ), 10, 1 );

		// Schema.
		add_filter( 'wpseo_schema_graph_pieces', array( $this, 'add_schema_pieces' ), 10, 2 );
		add_filter( 'wpseo_schema_webpage', array( $this, 'modify_webpage_schema' ), 10, 1 );

		// Breadcrumbs.
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'modify_breadcrumbs' ), 10, 1 );

		// Sitemap.
		add_filter( 'wpseo_sitemap_entry', array( $this, 'modify_sitemap_entry' ), 10, 3 );
		add_filter( 'wpseo_sitemap_exclude_post', array( $this, 'exclude_from_sitemap' ), 10, 2 );

		// Meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_seo_hints_meta_box' ), 20 );

		// Analysis.
		add_filter( 'wpseo_primary_term_taxonomies', array( $this, 'add_primary_taxonomy' ), 10, 2 );

		// Robots.
		add_filter( 'wpseo_robots', array( $this, 'modify_robots' ), 10, 1 );
	}

	/**
	 * Modify page title.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function modify_title( string $title ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $title;
		}

		$service_id = get_the_ID();
		$price      = get_post_meta( $service_id, '_wpss_starting_price', true );
		$rating     = get_post_meta( $service_id, '_wpss_rating_average', true );

		// Add price indicator if not already in title.
		if ( $price && strpos( $title, '$' ) === false && strpos( $title, 'Starting' ) === false ) {
			$title = sprintf(
				/* translators: 1: service title, 2: price */
				__( '%1$s - Starting at %2$s', 'wp-sell-services' ),
				$title,
				wpss_format_currency( (float) $price )
			);
		}

		return $title;
	}

	/**
	 * Modify meta description.
	 *
	 * @param string $description Current description.
	 * @return string
	 */
	public function modify_description( string $description ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $description;
		}

		// If Yoast has custom description, use it.
		if ( $description ) {
			return $description;
		}

		$service_id = get_the_ID();
		$post       = get_post( $service_id );

		if ( ! $post ) {
			return $description;
		}

		// Generate from excerpt or content.
		if ( $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 25, '...' );
	}

	/**
	 * Modify canonical URL.
	 *
	 * @param string $canonical Current canonical.
	 * @return string
	 */
	public function modify_canonical( string $canonical ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $canonical;
		}

		// Remove query parameters.
		return strtok( $canonical, '?' ) ?: $canonical;
	}

	/**
	 * Set Open Graph type for services.
	 *
	 * @param string $type Current type.
	 * @return string
	 */
	public function set_og_type( string $type ): string {
		if ( is_singular( 'wpss_service' ) ) {
			return 'product';
		}

		return $type;
	}

	/**
	 * Modify Open Graph title.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function modify_og_title( string $title ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $title;
		}

		$service_id = get_the_ID();
		$rating     = (float) get_post_meta( $service_id, '_wpss_rating_average', true );

		// Add star rating if available.
		if ( $rating >= 4.5 ) {
			$title .= ' ⭐ ' . round( $rating, 1 );
		}

		return $title;
	}

	/**
	 * Modify Open Graph description.
	 *
	 * @param string $description Current description.
	 * @return string
	 */
	public function modify_og_description( string $description ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $description;
		}

		$service_id    = get_the_ID();
		$price         = get_post_meta( $service_id, '_wpss_starting_price', true );
		$delivery_days = get_post_meta( $service_id, '_wpss_fastest_delivery', true );

		$additions = array();

		if ( $price ) {
			$additions[] = sprintf(
				/* translators: %s: price */
				__( 'From %s', 'wp-sell-services' ),
				wpss_format_currency( (float) $price )
			);
		}

		if ( $delivery_days ) {
			$additions[] = sprintf(
				/* translators: %d: days */
				_n( '%d day delivery', '%d days delivery', (int) $delivery_days, 'wp-sell-services' ),
				(int) $delivery_days
			);
		}

		if ( ! empty( $additions ) ) {
			$description = implode( ' | ', $additions ) . '. ' . $description;
		}

		return $description;
	}

	/**
	 * Modify Open Graph image.
	 *
	 * @param string $image Current image URL.
	 * @return string
	 */
	public function modify_og_image( string $image ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $image;
		}

		// If no image set, try featured image.
		if ( empty( $image ) ) {
			$service_id = get_the_ID();
			$thumbnail  = get_the_post_thumbnail_url( $service_id, 'large' );
			if ( $thumbnail ) {
				return $thumbnail;
			}
		}

		return $image;
	}

	/**
	 * Add gallery images to Open Graph.
	 *
	 * @param object $image_container Yoast image container.
	 * @return object
	 */
	public function add_gallery_images( $image_container ) {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $image_container;
		}

		$service_id  = get_the_ID();
		$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true );
		$gallery_ids = wpss_get_gallery_ids( $gallery_raw );

		if ( empty( $gallery_ids ) ) {
			return $image_container;
		}

		foreach ( $gallery_ids as $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'large' );
			if ( $image_url ) {
				$image_container->add_image_by_url( $image_url );
			}
		}

		return $image_container;
	}

	/**
	 * Add schema graph pieces.
	 *
	 * @param array  $pieces  Schema pieces.
	 * @param object $context Yoast context.
	 * @return array
	 */
	public function add_schema_pieces( array $pieces, $context ): array {
		if ( is_singular( 'wpss_service' ) ) {
			$pieces[] = new ServiceSchemaPiece( $context );
		}

		return $pieces;
	}

	/**
	 * Modify webpage schema.
	 *
	 * @param array $data Schema data.
	 * @return array
	 */
	public function modify_webpage_schema( array $data ): array {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $data;
		}

		$service_id = get_the_ID();
		$price      = get_post_meta( $service_id, '_wpss_starting_price', true );

		// Add about reference.
		$data['about'] = array(
			'@id' => get_permalink( $service_id ) . '#service',
		);

		return $data;
	}

	/**
	 * Modify breadcrumbs for services.
	 *
	 * @param array $links Breadcrumb links.
	 * @return array
	 */
	public function modify_breadcrumbs( array $links ): array {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $links;
		}

		$service_id = get_the_ID();
		$new_links  = array();

		// Keep home link.
		if ( isset( $links[0] ) ) {
			$new_links[] = $links[0];
		}

		// Add Services archive.
		$new_links[] = array(
			'url'  => get_post_type_archive_link( 'wpss_service' ),
			'text' => __( 'Services', 'wp-sell-services' ),
		);

		// Add category.
		$categories = get_the_terms( $service_id, 'wpss_service_category' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			$category = $categories[0];

			// Add parent category if exists.
			if ( $category->parent ) {
				$parent = get_term( $category->parent );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$new_links[] = array(
						'url'  => get_term_link( $parent ),
						'text' => $parent->name,
					);
				}
			}

			$new_links[] = array(
				'url'  => get_term_link( $category ),
				'text' => $category->name,
			);
		}

		// Add current page (last item without link).
		$new_links[] = array(
			'text' => get_the_title( $service_id ),
		);

		return $new_links;
	}

	/**
	 * Modify sitemap entry for services.
	 *
	 * @param array  $entry     Sitemap entry.
	 * @param string $post_type Post type.
	 * @param object $post      Post object.
	 * @return array
	 */
	public function modify_sitemap_entry( $entry, $post_type, $post ): array {
		if ( 'wpss_service' !== $post_type ) {
			return $entry;
		}

		// Add images to sitemap entry.
		$images   = array();
		$thumb_id = get_post_thumbnail_id( $post->ID );

		if ( $thumb_id ) {
			$images[] = array(
				'src'   => wp_get_attachment_image_url( $thumb_id, 'full' ),
				'title' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: $post->post_title,
			);
		}

		// Add gallery images.
		$gallery_raw = get_post_meta( $post->ID, '_wpss_gallery', true );
		$gallery_ids = wpss_get_gallery_ids( $gallery_raw );
		foreach ( $gallery_ids as $image_id ) {
			$images[] = array(
				'src'   => wp_get_attachment_image_url( $image_id, 'full' ),
				'title' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: $post->post_title,
			);
		}

		if ( ! empty( $images ) ) {
			$entry['images'] = $images;
		}

		return $entry;
	}

	/**
	 * Exclude paused services from sitemap.
	 *
	 * @param bool   $exclude Whether to exclude.
	 * @param object $post    Post object.
	 * @return bool
	 */
	public function exclude_from_sitemap( $exclude, $post ): bool {
		if ( 'wpss_service' !== $post->post_type ) {
			return $exclude;
		}

		$status = get_post_meta( $post->ID, '_wpss_service_status', true );

		return 'paused' === $status;
	}

	/**
	 * Add SEO hints meta box.
	 *
	 * @return void
	 */
	public function add_seo_hints_meta_box(): void {
		add_meta_box(
			'wpss_seo_hints',
			__( 'Service SEO Tips', 'wp-sell-services' ),
			array( $this, 'render_seo_hints_meta_box' ),
			'wpss_service',
			'side',
			'low'
		);
	}

	/**
	 * Render SEO hints meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_seo_hints_meta_box( $post ): void {
		$hints = array();

		// Check title length.
		$title_length = strlen( $post->post_title );
		if ( $title_length < 30 ) {
			$hints[] = array(
				'type'    => 'warning',
				'message' => __( 'Title is too short. Consider making it 40-60 characters.', 'wp-sell-services' ),
			);
		} elseif ( $title_length > 70 ) {
			$hints[] = array(
				'type'    => 'warning',
				'message' => __( 'Title is too long. Keep it under 60 characters.', 'wp-sell-services' ),
			);
		} else {
			$hints[] = array(
				'type'    => 'good',
				'message' => __( 'Title length is good.', 'wp-sell-services' ),
			);
		}

		// Check featured image.
		if ( ! has_post_thumbnail( $post->ID ) ) {
			$hints[] = array(
				'type'    => 'error',
				'message' => __( 'Add a featured image for better visibility.', 'wp-sell-services' ),
			);
		} else {
			$hints[] = array(
				'type'    => 'good',
				'message' => __( 'Featured image is set.', 'wp-sell-services' ),
			);
		}

		// Check content length.
		$content_length = str_word_count( wp_strip_all_tags( $post->post_content ) );
		if ( $content_length < 100 ) {
			$hints[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %d: word count */
					__( 'Description has only %d words. Aim for 200+ words.', 'wp-sell-services' ),
					$content_length
				),
			);
		} else {
			$hints[] = array(
				'type'    => 'good',
				'message' => __( 'Description length is good.', 'wp-sell-services' ),
			);
		}

		// Check category.
		$categories = get_the_terms( $post->ID, 'wpss_service_category' );
		if ( ! $categories || is_wp_error( $categories ) ) {
			$hints[] = array(
				'type'    => 'error',
				'message' => __( 'Add at least one category for better discoverability.', 'wp-sell-services' ),
			);
		} else {
			$hints[] = array(
				'type'    => 'good',
				'message' => __( 'Category is assigned.', 'wp-sell-services' ),
			);
		}

		// Check price.
		$price = get_post_meta( $post->ID, '_wpss_starting_price', true );
		if ( ! $price ) {
			$hints[] = array(
				'type'    => 'warning',
				'message' => __( 'Set a starting price to show in search results.', 'wp-sell-services' ),
			);
		}

		// Output hints.
		echo '<ul class="wpss-seo-hints">';
		foreach ( $hints as $hint ) {
			$icon_class = array(
				'error'   => 'dashicons-warning',
				'warning' => 'dashicons-info',
				'good'    => 'dashicons-yes-alt',
			);
			$color      = array(
				'error'   => '#dc3232',
				'warning' => '#ffb900',
				'good'    => '#46b450',
			);

			printf(
				'<li style="color: %s; margin-bottom: 8px;"><span class="dashicons %s"></span> %s</li>',
				esc_attr( $color[ $hint['type'] ] ),
				esc_attr( $icon_class[ $hint['type'] ] ),
				esc_html( $hint['message'] )
			);
		}
		echo '</ul>';
	}

	/**
	 * Add primary taxonomy for services.
	 *
	 * @param array  $taxonomies Taxonomies.
	 * @param string $post_type  Post type.
	 * @return array
	 */
	public function add_primary_taxonomy( array $taxonomies, string $post_type ): array {
		if ( 'wpss_service' === $post_type ) {
			$taxonomies[] = 'wpss_service_category';
		}

		return $taxonomies;
	}

	/**
	 * Modify robots meta for services.
	 *
	 * @param string $robots Robots directive.
	 * @return string
	 */
	public function modify_robots( string $robots ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $robots;
		}

		$service_id = get_the_ID();
		$status     = get_post_meta( $service_id, '_wpss_service_status', true );

		// Noindex paused services.
		if ( 'paused' === $status ) {
			return 'noindex, follow';
		}

		return $robots;
	}
}
