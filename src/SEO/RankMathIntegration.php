<?php
/**
 * Rank Math SEO Integration
 *
 * @package WPSellServices\SEO
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

/**
 * Integrates with Rank Math SEO plugin.
 *
 * @since 1.0.0
 */
class RankMathIntegration {

	/**
	 * Initialize Rank Math integration.
	 *
	 * @return void
	 */
	public function init(): void {
		// Title and meta.
		add_filter( 'rank_math/frontend/title', array( $this, 'modify_title' ), 10, 1 );
		add_filter( 'rank_math/frontend/description', array( $this, 'modify_description' ), 10, 1 );

		// Open Graph.
		add_filter( 'rank_math/opengraph/type', array( $this, 'set_og_type' ), 10, 1 );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'modify_og_title' ), 10, 1 );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'modify_og_description' ), 10, 1 );

		// Twitter.
		add_filter( 'rank_math/opengraph/twitter/title', array( $this, 'modify_og_title' ), 10, 1 );
		add_filter( 'rank_math/opengraph/twitter/description', array( $this, 'modify_og_description' ), 10, 1 );

		// Schema.
		add_filter( 'rank_math/json_ld', array( $this, 'add_schema' ), 99, 2 );

		// Breadcrumbs.
		add_filter( 'rank_math/frontend/breadcrumb/items', array( $this, 'modify_breadcrumbs' ), 10, 2 );

		// Sitemap.
		add_filter( 'rank_math/sitemap/entry', array( $this, 'modify_sitemap_entry' ), 10, 3 );
		add_filter( 'rank_math/sitemap/exclude_post', array( $this, 'exclude_from_sitemap' ), 10, 2 );

		// Robots.
		add_filter( 'rank_math/frontend/robots', array( $this, 'modify_robots' ), 10, 1 );

		// Primary term.
		add_filter( 'rank_math/primary_term_taxonomies', array( $this, 'add_primary_taxonomy' ), 10, 2 );
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
		if ( ! is_singular( 'wpss_service' ) || $description ) {
			return $description;
		}

		$service_id = get_the_ID();
		$post       = get_post( $service_id );

		if ( ! $post ) {
			return $description;
		}

		if ( $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 25, '...' );
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
	 * Add custom schema to Rank Math output.
	 *
	 * @param array  $data   Schema data.
	 * @param object $jsonld JSON-LD object.
	 * @return array
	 */
	public function add_schema( array $data, $jsonld ): array {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $data;
		}

		$service_id = get_the_ID();
		$post       = get_post( $service_id );

		if ( ! $post ) {
			return $data;
		}

		$vendor_id  = (int) $post->post_author;
		$vendor     = get_userdata( $vendor_id );
		$categories = get_the_terms( $service_id, 'wpss_service_category' );

		// Get service meta.
		$starting_price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
		$delivery_days  = (int) get_post_meta( $service_id, '_wpss_fastest_delivery', true );
		$rating         = (float) get_post_meta( $service_id, '_wpss_rating_average', true );
		$review_count   = (int) get_post_meta( $service_id, '_wpss_review_count', true );

		$service_schema = array(
			'@type'       => array( 'Service', 'Product' ),
			'@id'         => get_permalink( $service_id ) . '#service',
			'name'        => get_the_title( $service_id ),
			'description' => wp_strip_all_tags( $post->post_excerpt ?: wp_trim_words( $post->post_content, 50 ) ),
			'url'         => get_permalink( $service_id ),
		);

		// Add image.
		$image_url = get_the_post_thumbnail_url( $service_id, 'large' );
		if ( $image_url ) {
			$service_schema['image'] = $image_url;
		}

		// Add category.
		if ( $categories && ! is_wp_error( $categories ) ) {
			$service_schema['category']    = $categories[0]->name;
			$service_schema['serviceType'] = $categories[0]->name;
		}

		// Add provider.
		if ( $vendor ) {
			$service_schema['provider'] = array(
				'@type' => 'Person',
				'name'  => $vendor->display_name,
				'url'   => get_author_posts_url( $vendor_id ),
			);

			$avatar = get_avatar_url( $vendor_id, array( 'size' => 256 ) );
			if ( $avatar ) {
				$service_schema['provider']['image'] = $avatar;
			}
		}

		// Add offers.
		if ( $starting_price > 0 ) {
			$currency = wpss_get_currency();

			$service_schema['offers'] = array(
				'@type'           => 'Offer',
				'price'           => $starting_price,
				'priceCurrency'   => $currency,
				'availability'    => 'https://schema.org/InStock',
				'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			);
		}

		// Add aggregate rating.
		if ( $rating > 0 && $review_count > 0 ) {
			$service_schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $rating, 1 ),
				'bestRating'  => 5,
				'worstRating' => 1,
				'ratingCount' => $review_count,
			);
		}

		// Add to schema data.
		$data['Service'] = $service_schema;

		return $data;
	}

	/**
	 * Modify breadcrumbs.
	 *
	 * @param array  $items   Breadcrumb items.
	 * @param object $crumbs Breadcrumbs object.
	 * @return array
	 */
	public function modify_breadcrumbs( array $items, $crumbs ): array {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $items;
		}

		$service_id = get_the_ID();
		$new_items  = array();

		// Keep home.
		if ( isset( $items[0] ) ) {
			$new_items[] = $items[0];
		}

		// Add Services archive.
		$new_items[] = array(
			get_post_type_archive_link( 'wpss_service' ),
			__( 'Services', 'wp-sell-services' ),
		);

		// Add category.
		$categories = get_the_terms( $service_id, 'wpss_service_category' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			$category = $categories[0];

			if ( $category->parent ) {
				$parent = get_term( $category->parent );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$new_items[] = array(
						get_term_link( $parent ),
						$parent->name,
					);
				}
			}

			$new_items[] = array(
				get_term_link( $category ),
				$category->name,
			);
		}

		// Add current service.
		$new_items[] = array(
			'',
			get_the_title( $service_id ),
		);

		return $new_items;
	}

	/**
	 * Modify sitemap entry.
	 *
	 * @param array  $entry Sitemap entry.
	 * @param string $type  Entry type.
	 * @param object $object Object.
	 * @return array
	 */
	public function modify_sitemap_entry( $entry, $type, $object ): array {
		if ( 'post' !== $type || 'wpss_service' !== ( $object->post_type ?? '' ) ) {
			return $entry;
		}

		// Add images.
		$images = array();

		$thumb_url = get_the_post_thumbnail_url( $object->ID, 'full' );
		if ( $thumb_url ) {
			$images[] = array(
				'src'   => $thumb_url,
				'title' => $object->post_title,
			);
		}

		$gallery_raw = get_post_meta( $object->ID, '_wpss_gallery', true );
		$gallery_ids = wpss_get_gallery_ids( $gallery_raw );
		foreach ( $gallery_ids as $image_id ) {
			$url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $url ) {
				$images[] = array(
					'src'   => $url,
					'title' => $object->post_title,
				);
			}
		}

		if ( ! empty( $images ) ) {
			$entry['images'] = $images;
		}

		return $entry;
	}

	/**
	 * Exclude paused services from sitemap.
	 *
	 * @param bool $exclude Whether to exclude.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function exclude_from_sitemap( $exclude, $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post || 'wpss_service' !== $post->post_type ) {
			return $exclude;
		}

		$status = get_post_meta( $post_id, '_wpss_service_status', true );

		return 'paused' === $status;
	}

	/**
	 * Modify robots meta.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function modify_robots( array $robots ): array {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $robots;
		}

		$service_id = get_the_ID();
		$status     = get_post_meta( $service_id, '_wpss_service_status', true );

		if ( 'paused' === $status ) {
			$robots['index'] = 'noindex';
		}

		return $robots;
	}

	/**
	 * Add primary taxonomy.
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
}
