<?php
/**
 * Abstract SEO Integration
 *
 * The service title, description, Open Graph and primary-term rules every SEO plugin
 * integration shares. A subclass only maps them onto its plugin's hooks.
 *
 * @package WPSellServices\SEO
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Shared service SEO rules.
 *
 * @since 1.9.0
 */
abstract class AbstractSeoIntegration implements SeoIntegrationInterface {

	/**
	 * Whether this plugin put our Service piece into its own graph on this request.
	 *
	 * @var bool
	 */
	protected bool $service_schema_added = false;

	/**
	 * Skip our own single-service JSON-LD once the SEO plugin's graph carries it.
	 *
	 * Yoast and Rank Math print their graph at wp_head priority 1, before
	 * SchemaMarkup at 10, so the flag is known by then. When their schema output
	 * is off (Rank Math before its setup wizard, a disabled schema module) the
	 * piece is never added and ours still prints, so the page is never left with none.
	 *
	 * @since 1.9.3
	 *
	 * @param bool $show Whether to print our Service JSON-LD.
	 * @return bool
	 */
	public function keep_own_service_schema( bool $show ): bool {
		return $this->service_schema_added ? false : $show;
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
		$delivery_days = wpss_get_service_delivery_days( $service_id );

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
				/* translators: %d: number of days */
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
