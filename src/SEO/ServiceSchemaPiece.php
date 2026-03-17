<?php
/**
 * Service Schema Piece for Yoast SEO
 *
 * @package WPSellServices\SEO
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\SEO;

defined( 'ABSPATH' ) || exit;

use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

/**
 * Generates Service schema for Yoast SEO schema graph.
 *
 * @since 1.0.0
 */
class ServiceSchemaPiece extends Abstract_Schema_Piece {

	/**
	 * Determines whether the schema piece is needed.
	 *
	 * @return bool
	 */
	public function is_needed(): bool {
		return is_singular( 'wpss_service' );
	}

	/**
	 * Generates the schema piece.
	 *
	 * @return array|false
	 */
	public function generate() {
		$service_id = get_the_ID();
		$post       = get_post( $service_id );

		if ( ! $post ) {
			return false;
		}

		$vendor_id  = (int) $post->post_author;
		$vendor     = get_userdata( $vendor_id );
		$categories = get_the_terms( $service_id, 'wpss_service_category' );

		// Get service meta.
		$starting_price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
		$delivery_days  = (int) get_post_meta( $service_id, '_wpss_fastest_delivery', true );
		$rating         = (float) get_post_meta( $service_id, '_wpss_rating_average', true );
		$review_count   = (int) get_post_meta( $service_id, '_wpss_review_count', true );

		$schema = array(
			'@type'            => array( 'Service', 'Product' ),
			'@id'              => $this->context->canonical . '#service',
			'name'             => $this->context->title,
			'description'      => $this->get_description( $post ),
			'url'              => $this->context->canonical,
			'mainEntityOfPage' => array(
				'@id' => $this->context->canonical . '#webpage',
			),
		);

		// Add image.
		$image_id = get_post_thumbnail_id( $service_id );
		if ( $image_id ) {
			$schema['image'] = array(
				'@id' => $this->context->canonical . '#primaryimage',
			);
		}

		// Add category.
		if ( $categories && ! is_wp_error( $categories ) ) {
			$schema['category']    = $categories[0]->name;
			$schema['serviceType'] = $categories[0]->name;
		}

		// Add provider.
		if ( $vendor ) {
			$schema['provider'] = $this->get_provider_schema( $vendor );
			$schema['brand']    = array(
				'@type' => 'Brand',
				'name'  => $vendor->display_name,
			);
		}

		// Add offers.
		if ( $starting_price > 0 ) {
			$currency = wpss_get_currency();

			$schema['offers'] = array(
				'@type'           => 'Offer',
				'price'           => $starting_price,
				'priceCurrency'   => $currency,
				'availability'    => 'https://schema.org/InStock',
				'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
				'url'             => $this->context->canonical,
			);

			if ( $vendor ) {
				$schema['offers']['seller'] = array(
					'@type' => 'Person',
					'name'  => $vendor->display_name,
				);
			}
		}

		// Add aggregate rating.
		if ( $rating > 0 && $review_count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $rating, 1 ),
				'bestRating'  => 5,
				'worstRating' => 1,
				'ratingCount' => $review_count,
				'reviewCount' => $review_count,
			);
		}

		// Add reviews if available.
		$reviews = $this->get_reviews( $service_id );
		if ( ! empty( $reviews ) ) {
			$schema['review'] = $reviews;
		}

		// Add service-specific properties.
		if ( $delivery_days > 0 ) {
			$schema['termsOfService'] = sprintf(
				/* translators: %d: number of days */
				__( 'Delivery within %d days', 'wp-sell-services' ),
				$delivery_days
			);
		}

		// Add area served.
		$schema['areaServed'] = array(
			'@type' => 'Place',
			'name'  => 'Worldwide',
		);

		// Add FAQs if available.
		$faqs = $this->get_faq_schema( $service_id );
		if ( $faqs ) {
			$schema['hasFAQPage'] = $faqs;
		}

		return $schema;
	}

	/**
	 * Get description from post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_description( $post ): string {
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 50, '...' );
	}

	/**
	 * Get provider schema.
	 *
	 * @param \WP_User $vendor Vendor user.
	 * @return array
	 */
	private function get_provider_schema( $vendor ): array {
		$schema = array(
			'@type' => 'Person',
			'@id'   => get_author_posts_url( $vendor->ID ) . '#person',
			'name'  => $vendor->display_name,
			'url'   => get_author_posts_url( $vendor->ID ),
		);

		// Add avatar.
		$avatar = get_avatar_url( $vendor->ID, array( 'size' => 256 ) );
		if ( $avatar ) {
			$schema['image'] = $avatar;
		}

		// Add job title.
		$title = get_user_meta( $vendor->ID, 'wpss_vendor_title', true );
		if ( $title ) {
			$schema['jobTitle'] = $title;
		}

		// Add vendor rating.
		$rating       = (float) get_user_meta( $vendor->ID, 'wpss_vendor_rating', true );
		$review_count = (int) get_user_meta( $vendor->ID, 'wpss_vendor_review_count', true );

		if ( $rating > 0 && $review_count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $rating, 1 ),
				'bestRating'  => 5,
				'ratingCount' => $review_count,
			);
		}

		return $schema;
	}

	/**
	 * Get reviews for schema.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	private function get_reviews( int $service_id ): array {
		global $wpdb;

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name as reviewer_name
				FROM {$wpdb->prefix}wpss_reviews r
				LEFT JOIN {$wpdb->users} u ON r.reviewer_id = u.ID
				WHERE r.service_id = %d
				AND r.status = 'approved'
				ORDER BY r.created_at DESC
				LIMIT 5",
				$service_id
			)
		);

		if ( empty( $reviews ) ) {
			return array();
		}

		$schema_reviews = array();
		foreach ( $reviews as $review ) {
			$schema_reviews[] = array(
				'@type'         => 'Review',
				'author'        => array(
					'@type' => 'Person',
					'name'  => $review->reviewer_name ?: __( 'Anonymous', 'wp-sell-services' ),
				),
				'datePublished' => gmdate( 'c', strtotime( $review->created_at ) ),
				'reviewBody'    => $review->comment,
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => (int) $review->rating,
					'bestRating'  => 5,
					'worstRating' => 1,
				),
			);
		}

		return $schema_reviews;
	}

	/**
	 * Get FAQ schema.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null
	 */
	private function get_faq_schema( int $service_id ): ?array {
		$faqs = get_post_meta( $service_id, '_wpss_faqs', true );

		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return null;
		}

		$items = array();
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$items[] = array(
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $faq['answer'],
				),
			);
		}

		if ( empty( $items ) ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => $this->context->canonical . '#faq',
			'mainEntity' => $items,
		);
	}
}
