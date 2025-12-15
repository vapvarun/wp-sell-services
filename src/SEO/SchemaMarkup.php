<?php
/**
 * Schema Markup Generator
 *
 * @package WPSellServices\SEO
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

/**
 * Generates JSON-LD structured data for services.
 *
 * @since 1.0.0
 */
class SchemaMarkup {

	/**
	 * Initialize schema markup.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_head', [ $this, 'output_schema' ], 10 );
		add_action( 'wp_head', [ $this, 'output_organization_schema' ], 10 );
		add_action( 'wp_head', [ $this, 'output_breadcrumb_schema' ], 10 );
	}

	/**
	 * Output JSON-LD schema.
	 *
	 * @return void
	 */
	public function output_schema(): void {
		$schema = null;

		if ( is_singular( 'wpss_service' ) ) {
			$schema = $this->get_service_schema( get_the_ID() );
		} elseif ( is_post_type_archive( 'wpss_service' ) ) {
			$schema = $this->get_service_list_schema();
		} elseif ( is_tax( 'wpss_service_category' ) ) {
			$schema = $this->get_category_schema( get_queried_object() );
		} elseif ( is_page() && $this->is_vendor_page() ) {
			$schema = $this->get_vendor_schema();
		}

		if ( $schema ) {
			$this->output_json_ld( $schema );
		}
	}

	/**
	 * Output organization schema on homepage.
	 *
	 * @return void
	 */
	public function output_organization_schema(): void {
		if ( ! is_front_page() ) {
			return;
		}

		$schema = $this->get_organization_schema();
		$this->output_json_ld( $schema );
	}

	/**
	 * Output breadcrumb schema.
	 *
	 * @return void
	 */
	public function output_breadcrumb_schema(): void {
		if ( ! is_singular( 'wpss_service' ) && ! is_tax( 'wpss_service_category' ) ) {
			return;
		}

		$schema = $this->get_breadcrumb_schema();
		if ( $schema ) {
			$this->output_json_ld( $schema );
		}
	}

	/**
	 * Get service schema (Product + Service hybrid).
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_service_schema( int $service_id ): array {
		$post       = get_post( $service_id );
		$vendor_id  = (int) $post->post_author;
		$vendor     = get_userdata( $vendor_id );
		$categories = get_the_terms( $service_id, 'wpss_service_category' );

		// Get service meta.
		$starting_price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
		$delivery_days  = (int) get_post_meta( $service_id, '_wpss_delivery_days', true );
		$rating         = (float) get_post_meta( $service_id, '_wpss_rating', true );
		$review_count   = (int) get_post_meta( $service_id, '_wpss_review_count', true );
		$orders_count   = (int) get_post_meta( $service_id, '_wpss_orders_count', true );

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => [ 'Service', 'Product' ],
			'@id'         => get_permalink( $service_id ) . '#service',
			'name'        => get_the_title( $service_id ),
			'description' => wp_strip_all_tags( $post->post_excerpt ?: wp_trim_words( $post->post_content, 50 ) ),
			'url'         => get_permalink( $service_id ),
		];

		// Add image.
		$image_url = get_the_post_thumbnail_url( $service_id, 'large' );
		if ( $image_url ) {
			$schema['image'] = $image_url;
		}

		// Add category.
		if ( $categories && ! is_wp_error( $categories ) ) {
			$schema['category'] = $categories[0]->name;
		}

		// Add provider (vendor).
		if ( $vendor ) {
			$schema['provider'] = $this->get_person_schema( $vendor_id );
			$schema['brand']    = [
				'@type' => 'Brand',
				'name'  => $vendor->display_name,
			];
		}

		// Add offers/pricing.
		if ( $starting_price > 0 ) {
			$currency = get_option( 'wpss_currency', 'USD' );

			$schema['offers'] = [
				'@type'           => 'Offer',
				'price'           => $starting_price,
				'priceCurrency'   => $currency,
				'availability'    => 'https://schema.org/InStock',
				'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
				'url'             => get_permalink( $service_id ),
			];

			// Add seller to offer.
			if ( $vendor ) {
				$schema['offers']['seller'] = [
					'@type' => 'Person',
					'name'  => $vendor->display_name,
				];
			}
		}

		// Add aggregate rating.
		if ( $rating > 0 && $review_count > 0 ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $rating, 1 ),
				'bestRating'  => 5,
				'worstRating' => 1,
				'ratingCount' => $review_count,
				'reviewCount' => $review_count,
			];
		}

		// Add service-specific properties.
		$schema['serviceType'] = $categories && ! is_wp_error( $categories ) ? $categories[0]->name : 'Professional Service';

		if ( $delivery_days > 0 ) {
			$schema['termsOfService'] = sprintf(
				/* translators: %d: number of days */
				__( 'Delivery within %d days', 'wp-sell-services' ),
				$delivery_days
			);
		}

		// Add area served.
		$schema['areaServed'] = [
			'@type' => 'Place',
			'name'  => 'Worldwide',
		];

		return apply_filters( 'wpss_service_schema', $schema, $service_id );
	}

	/**
	 * Get service list schema for archive page.
	 *
	 * @return array
	 */
	public function get_service_list_schema(): array {
		global $wp_query;

		$items = [];
		$pos   = 1;

		if ( $wp_query->have_posts() ) {
			while ( $wp_query->have_posts() ) {
				$wp_query->the_post();
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $pos++,
					'item'     => [
						'@type' => 'Service',
						'@id'   => get_permalink() . '#service',
						'name'  => get_the_title(),
						'url'   => get_permalink(),
					],
				];
			}
			wp_reset_postdata();
		}

		$schema = [
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => __( 'Services', 'wp-sell-services' ),
			'itemListElement' => $items,
		];

		return apply_filters( 'wpss_service_list_schema', $schema );
	}

	/**
	 * Get category schema.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	public function get_category_schema( $term ): array {
		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'CollectionPage',
			'@id'         => get_term_link( $term ) . '#webpage',
			'name'        => $term->name,
			'description' => $term->description ?: sprintf(
				/* translators: %s: category name */
				__( 'Browse %s services', 'wp-sell-services' ),
				$term->name
			),
			'url'         => get_term_link( $term ),
		];

		// Add main entity (ItemList of services).
		$services = get_posts(
			[
				'post_type'      => 'wpss_service',
				'posts_per_page' => 10,
				'tax_query'      => [
					[
						'taxonomy' => 'wpss_service_category',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					],
				],
			]
		);

		if ( $services ) {
			$items = [];
			$pos   = 1;
			foreach ( $services as $service ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $pos++,
					'item'     => [
						'@type' => 'Service',
						'name'  => $service->post_title,
						'url'   => get_permalink( $service->ID ),
					],
				];
			}

			$schema['mainEntity'] = [
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			];
		}

		return apply_filters( 'wpss_category_schema', $schema, $term );
	}

	/**
	 * Get vendor/person schema.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_person_schema( int $user_id ): array {
		$user         = get_userdata( $user_id );
		$profile_url  = get_author_posts_url( $user_id );
		$avatar_url   = get_avatar_url( $user_id, [ 'size' => 256 ] );
		$vendor_title = get_user_meta( $user_id, 'wpss_vendor_title', true );
		$vendor_bio   = get_user_meta( $user_id, 'description', true );

		$schema = [
			'@type'  => 'Person',
			'@id'    => $profile_url . '#person',
			'name'   => $user->display_name,
			'url'    => $profile_url,
		];

		if ( $avatar_url ) {
			$schema['image'] = $avatar_url;
		}

		if ( $vendor_title ) {
			$schema['jobTitle'] = $vendor_title;
		}

		if ( $vendor_bio ) {
			$schema['description'] = wp_trim_words( $vendor_bio, 50 );
		}

		// Add vendor stats.
		$rating       = (float) get_user_meta( $user_id, 'wpss_vendor_rating', true );
		$review_count = (int) get_user_meta( $user_id, 'wpss_vendor_review_count', true );

		if ( $rating > 0 && $review_count > 0 ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $rating, 1 ),
				'bestRating'  => 5,
				'ratingCount' => $review_count,
			];
		}

		return apply_filters( 'wpss_person_schema', $schema, $user_id );
	}

	/**
	 * Get vendor page schema.
	 *
	 * @return array|null
	 */
	public function get_vendor_schema(): ?array {
		// Check for vendor query var.
		$vendor_slug = get_query_var( 'wpss_vendor' );
		if ( ! $vendor_slug ) {
			return null;
		}

		$user = get_user_by( 'slug', $vendor_slug );
		if ( ! $user ) {
			return null;
		}

		$schema = $this->get_person_schema( $user->ID );

		// Add services offered.
		$services = get_posts(
			[
				'post_type'      => 'wpss_service',
				'posts_per_page' => 10,
				'author'         => $user->ID,
			]
		);

		if ( $services ) {
			$schema['makesOffer'] = array_map(
				function ( $service ) {
					return [
						'@type'       => 'Offer',
						'itemOffered' => [
							'@type' => 'Service',
							'name'  => $service->post_title,
							'url'   => get_permalink( $service->ID ),
						],
					];
				},
				$services
			);
		}

		return apply_filters( 'wpss_vendor_page_schema', $schema, $user->ID );
	}

	/**
	 * Get organization schema.
	 *
	 * @return array
	 */
	public function get_organization_schema(): array {
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'@id'      => home_url( '/#organization' ),
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		];

		$description = get_bloginfo( 'description' );
		if ( $description ) {
			$schema['description'] = $description;
		}

		// Add logo if available.
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$schema['logo'] = $logo_url;
			}
		}

		return apply_filters( 'wpss_organization_schema', $schema );
	}

	/**
	 * Get breadcrumb schema.
	 *
	 * @return array|null
	 */
	public function get_breadcrumb_schema(): ?array {
		$breadcrumbs = [];

		if ( is_singular( 'wpss_service' ) ) {
			$service_id = get_the_ID();

			$breadcrumbs[] = [
				'name' => __( 'Home', 'wp-sell-services' ),
				'url'  => home_url( '/' ),
			];

			$breadcrumbs[] = [
				'name' => __( 'Services', 'wp-sell-services' ),
				'url'  => get_post_type_archive_link( 'wpss_service' ),
			];

			$categories = get_the_terms( $service_id, 'wpss_service_category' );
			if ( $categories && ! is_wp_error( $categories ) ) {
				$breadcrumbs[] = [
					'name' => $categories[0]->name,
					'url'  => get_term_link( $categories[0] ),
				];
			}

			$breadcrumbs[] = [
				'name' => get_the_title(),
				'url'  => get_permalink(),
			];
		} elseif ( is_tax( 'wpss_service_category' ) ) {
			$term = get_queried_object();

			$breadcrumbs[] = [
				'name' => __( 'Home', 'wp-sell-services' ),
				'url'  => home_url( '/' ),
			];

			$breadcrumbs[] = [
				'name' => __( 'Services', 'wp-sell-services' ),
				'url'  => get_post_type_archive_link( 'wpss_service' ),
			];

			// Add parent term if exists.
			if ( $term->parent ) {
				$parent = get_term( $term->parent );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$breadcrumbs[] = [
						'name' => $parent->name,
						'url'  => get_term_link( $parent ),
					];
				}
			}

			$breadcrumbs[] = [
				'name' => $term->name,
				'url'  => get_term_link( $term ),
			];
		}

		if ( empty( $breadcrumbs ) ) {
			return null;
		}

		$items = [];
		foreach ( $breadcrumbs as $pos => $crumb ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $pos + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			];
		}

		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		];
	}

	/**
	 * Get FAQ schema from service content.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null
	 */
	public function get_faq_schema( int $service_id ): ?array {
		$faqs = get_post_meta( $service_id, '_wpss_faqs', true );

		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return null;
		}

		$items = [];
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$items[] = [
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $faq['answer'],
				],
			];
		}

		if ( empty( $items ) ) {
			return null;
		}

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $items,
		];
	}

	/**
	 * Get review schema.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null
	 */
	public function get_reviews_schema( int $service_id ): ?array {
		global $wpdb;

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name as reviewer_name
				FROM {$wpdb->prefix}wpss_reviews r
				LEFT JOIN {$wpdb->users} u ON r.reviewer_id = u.ID
				WHERE r.service_id = %d
				AND r.status = 'approved'
				ORDER BY r.created_at DESC
				LIMIT 10",
				$service_id
			)
		);

		if ( empty( $reviews ) ) {
			return null;
		}

		$items = [];
		foreach ( $reviews as $review ) {
			$items[] = [
				'@type'        => 'Review',
				'author'       => [
					'@type' => 'Person',
					'name'  => $review->reviewer_name,
				],
				'datePublished' => gmdate( 'c', strtotime( $review->created_at ) ),
				'reviewBody'    => $review->comment,
				'reviewRating'  => [
					'@type'       => 'Rating',
					'ratingValue' => (int) $review->rating,
					'bestRating'  => 5,
					'worstRating' => 1,
				],
			];
		}

		return [
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'review'   => $items,
		];
	}

	/**
	 * Output JSON-LD script tag.
	 *
	 * @param array $schema Schema data.
	 * @return void
	 */
	private function output_json_ld( array $schema ): void {
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Check if current page is vendor profile page.
	 *
	 * @return bool
	 */
	private function is_vendor_page(): bool {
		return (bool) get_query_var( 'wpss_vendor' );
	}
}
