<?php
/**
 * SEO Manager
 *
 * @package WPSellServices\SEO
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

/**
 * Handles SEO functionality for the plugin.
 *
 * @since 1.0.0
 */
class SEO {

	/**
	 * Schema markup instance.
	 *
	 * @var SchemaMarkup
	 */
	private SchemaMarkup $schema;

	/**
	 * Yoast integration instance.
	 *
	 * @var YoastIntegration|null
	 */
	private ?YoastIntegration $yoast = null;

	/**
	 * Rank Math integration instance.
	 *
	 * @var RankMathIntegration|null
	 */
	private ?RankMathIntegration $rank_math = null;

	/**
	 * Initialize SEO functionality.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->schema = new SchemaMarkup();
		$this->schema->init();

		// Initialize Yoast integration if available.
		if ( $this->is_yoast_active() ) {
			$this->yoast = new YoastIntegration();
			$this->yoast->init();
		}

		// Initialize Rank Math integration if available.
		if ( $this->is_rank_math_active() ) {
			$this->rank_math = new RankMathIntegration();
			$this->rank_math->init();
		}

		// Register hooks.
		add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );
		add_filter( 'document_title_parts', [ $this, 'modify_title' ] );
		add_filter( 'get_canonical_url', [ $this, 'modify_canonical_url' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'output_open_graph' ], 5 );

		// Sitemap hooks.
		add_filter( 'wp_sitemaps_post_types', [ $this, 'add_to_sitemap' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'sitemap_query_args' ], 10, 2 );
	}

	/**
	 * Check if Yoast SEO is active.
	 *
	 * @return bool
	 */
	public function is_yoast_active(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Check if Rank Math is active.
	 *
	 * @return bool
	 */
	public function is_rank_math_active(): bool {
		return class_exists( 'RankMath' );
	}

	/**
	 * Check if All in One SEO is active.
	 *
	 * @return bool
	 */
	public function is_aioseo_active(): bool {
		return defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Output custom meta tags.
	 *
	 * @return void
	 */
	public function output_meta_tags(): void {
		// Skip if another SEO plugin handles this.
		if ( $this->is_yoast_active() || $this->is_rank_math_active() || $this->is_aioseo_active() ) {
			return;
		}

		if ( ! is_singular( 'wpss_service' ) ) {
			return;
		}

		$service_id  = get_the_ID();
		$description = $this->get_meta_description( $service_id );

		if ( $description ) {
			printf(
				'<meta name="description" content="%s" />' . "\n",
				esc_attr( $description )
			);
		}

		// Robots meta.
		$robots = $this->get_robots_meta( $service_id );
		if ( $robots ) {
			printf(
				'<meta name="robots" content="%s" />' . "\n",
				esc_attr( $robots )
			);
		}
	}

	/**
	 * Modify document title.
	 *
	 * @param array $title_parts Title parts.
	 * @return array
	 */
	public function modify_title( array $title_parts ): array {
		// Skip if another SEO plugin handles this.
		if ( $this->is_yoast_active() || $this->is_rank_math_active() || $this->is_aioseo_active() ) {
			return $title_parts;
		}

		if ( is_singular( 'wpss_service' ) ) {
			$service_id = get_the_ID();
			$price      = get_post_meta( $service_id, '_wpss_starting_price', true );

			if ( $price ) {
				$title_parts['title'] .= ' | ' . sprintf(
					/* translators: %s: price */
					__( 'Starting at %s', 'wp-sell-services' ),
					wpss_format_currency( (float) $price )
				);
			}
		}

		if ( is_post_type_archive( 'wpss_service' ) ) {
			$title_parts['title'] = __( 'Services', 'wp-sell-services' );
		}

		if ( is_tax( 'wpss_service_category' ) ) {
			$term = get_queried_object();
			/* translators: %s: category name */
			$title_parts['title'] = sprintf( __( '%s Services', 'wp-sell-services' ), $term->name );
		}

		return $title_parts;
	}

	/**
	 * Modify canonical URL.
	 *
	 * @param string   $canonical_url Canonical URL.
	 * @param \WP_Post $post          Post object.
	 * @return string
	 */
	public function modify_canonical_url( string $canonical_url, $post ): string {
		if ( 'wpss_service' !== $post->post_type ) {
			return $canonical_url;
		}

		// Remove any query parameters for clean canonical.
		return strtok( $canonical_url, '?' ) ?: $canonical_url;
	}

	/**
	 * Output Open Graph tags.
	 *
	 * @return void
	 */
	public function output_open_graph(): void {
		// Skip if another SEO plugin handles this.
		if ( $this->is_yoast_active() || $this->is_rank_math_active() || $this->is_aioseo_active() ) {
			return;
		}

		if ( ! is_singular( 'wpss_service' ) ) {
			return;
		}

		$service_id = get_the_ID();
		$og_data    = $this->get_open_graph_data( $service_id );

		foreach ( $og_data as $property => $content ) {
			if ( empty( $content ) ) {
				continue;
			}
			printf(
				'<meta property="%s" content="%s" />' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}

	/**
	 * Get Open Graph data for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_open_graph_data( int $service_id ): array {
		$post        = get_post( $service_id );
		$description = $this->get_meta_description( $service_id );
		$image       = get_the_post_thumbnail_url( $service_id, 'large' );
		$price       = get_post_meta( $service_id, '_wpss_starting_price', true );

		$data = [
			'og:type'        => 'product',
			'og:title'       => get_the_title( $service_id ),
			'og:description' => $description,
			'og:url'         => get_permalink( $service_id ),
			'og:site_name'   => get_bloginfo( 'name' ),
		];

		if ( $image ) {
			$data['og:image'] = $image;
		}

		if ( $price ) {
			$data['product:price:amount']   = $price;
			$data['product:price:currency'] = wpss_get_currency();
		}

		// Twitter cards.
		$data['twitter:card']        = 'summary_large_image';
		$data['twitter:title']       = $data['og:title'];
		$data['twitter:description'] = $data['og:description'];

		if ( $image ) {
			$data['twitter:image'] = $image;
		}

		return apply_filters( 'wpss_open_graph_data', $data, $service_id );
	}

	/**
	 * Get meta description for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return string
	 */
	public function get_meta_description( int $service_id ): string {
		$post = get_post( $service_id );

		if ( ! $post ) {
			return '';
		}

		// Try excerpt first.
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		// Generate from content.
		$content = wp_strip_all_tags( $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );

		return wp_trim_words( $content, 25, '...' );
	}

	/**
	 * Get robots meta for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return string
	 */
	public function get_robots_meta( int $service_id ): string {
		$post = get_post( $service_id );

		if ( ! $post ) {
			return '';
		}

		// Don't index draft/pending services.
		if ( 'publish' !== $post->post_status ) {
			return 'noindex,nofollow';
		}

		// Check if service is active.
		$status = get_post_meta( $service_id, '_wpss_service_status', true );
		if ( 'paused' === $status ) {
			return 'noindex,follow';
		}

		return 'index,follow';
	}

	/**
	 * Add service post type to sitemap.
	 *
	 * @param array $post_types Post types.
	 * @return array
	 */
	public function add_to_sitemap( array $post_types ): array {
		// Services are already a public post type, so should be included.
		// This filter allows removal if needed.
		return apply_filters( 'wpss_sitemap_post_types', $post_types );
	}

	/**
	 * Modify sitemap query args.
	 *
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function sitemap_query_args( array $args, string $post_type ): array {
		if ( 'wpss_service' !== $post_type ) {
			return $args;
		}

		// Only include active published services.
		$args['meta_query'] = [
			'relation' => 'OR',
			[
				'key'     => '_wpss_service_status',
				'value'   => 'active',
				'compare' => '=',
			],
			[
				'key'     => '_wpss_service_status',
				'compare' => 'NOT EXISTS',
			],
		];

		return $args;
	}

	/**
	 * Get breadcrumb data for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_breadcrumbs( int $service_id ): array {
		$breadcrumbs = [
			[
				'name' => __( 'Home', 'wp-sell-services' ),
				'url'  => home_url( '/' ),
			],
			[
				'name' => __( 'Services', 'wp-sell-services' ),
				'url'  => get_post_type_archive_link( 'wpss_service' ),
			],
		];

		// Add category.
		$categories = get_the_terms( $service_id, 'wpss_service_category' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			$category      = $categories[0];
			$breadcrumbs[] = [
				'name' => $category->name,
				'url'  => get_term_link( $category ),
			];
		}

		// Add service.
		$breadcrumbs[] = [
			'name' => get_the_title( $service_id ),
			'url'  => get_permalink( $service_id ),
		];

		return apply_filters( 'wpss_breadcrumbs', $breadcrumbs, $service_id );
	}

	/**
	 * Get schema instance.
	 *
	 * @return SchemaMarkup
	 */
	public function get_schema(): SchemaMarkup {
		return $this->schema;
	}

	/**
	 * Get Yoast instance.
	 *
	 * @return YoastIntegration|null
	 */
	public function get_yoast(): ?YoastIntegration {
		return $this->yoast;
	}

	/**
	 * Get Rank Math instance.
	 *
	 * @return RankMathIntegration|null
	 */
	public function get_rank_math(): ?RankMathIntegration {
		return $this->rank_math;
	}
}
