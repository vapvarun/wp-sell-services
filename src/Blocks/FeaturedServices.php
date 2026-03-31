<?php
/**
 * Featured Services Block
 *
 * Displays featured services in a carousel or grid.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * FeaturedServices class.
 *
 * @since 1.0.0
 */
class FeaturedServices extends AbstractBlock {

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'featured-services';
	}

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Featured Services', 'wp-sell-services' );
	}

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Display featured services in a carousel or grid.', 'wp-sell-services' );
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'star-filled';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return array( 'featured', 'popular', 'carousel', 'slider', 'top' );
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return array(
			'layout'     => array(
				'type'    => 'string',
				'default' => 'carousel',
			),
			'columns'    => array(
				'type'    => 'number',
				'default' => 4,
			),
			'limit'      => array(
				'type'    => 'number',
				'default' => 8,
			),
			'autoplay'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'interval'   => array(
				'type'    => 'number',
				'default' => 5000,
			),
			'showDots'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showArrows' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showRating' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showPrice'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'title'      => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render( array $attributes, string $content = '' ): string {
		$this->start_render();

		$defaults = array(
			'layout'     => 'carousel',
			'columns'    => 4,
			'limit'      => 8,
			'autoplay'   => true,
			'interval'   => 5000,
			'showDots'   => true,
			'showArrows' => true,
			'showRating' => true,
			'showPrice'  => true,
			'title'      => __( 'Featured Services', 'wp-sell-services' ),
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		// Query featured services.
		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'posts_per_page' => $attributes['limit'],
			'meta_query'     => array(
				array(
					'key'     => '_wpss_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_wpss_rating_average',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			?>
			<div <?php echo $this->get_wrapper_attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?>>
				<p class="wpss-no-featured"><?php esc_html_e( 'No featured services found.', 'wp-sell-services' ); ?></p>
			</div>
			<?php
			return $this->end_render();
		}

		$wrapper_classes = array(
			'wpss-featured-' . $attributes['layout'],
			'wpss-grid-cols-' . $attributes['columns'],
		);

		$carousel_data = '';
		if ( 'carousel' === $attributes['layout'] ) {
			$carousel_data = sprintf(
				'data-autoplay="%s" data-interval="%d" data-dots="%s" data-arrows="%s"',
				$attributes['autoplay'] ? 'true' : 'false',
				$attributes['interval'],
				$attributes['showDots'] ? 'true' : 'false',
				$attributes['showArrows'] ? 'true' : 'false'
			);
		}
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?> <?php echo $carousel_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $carousel_data is built with sprintf using hardcoded attribute names and sanitized values. ?>>
			<?php if ( ! empty( $attributes['title'] ) ) : ?>
				<h2 class="wpss-featured-title"><?php echo esc_html( $attributes['title'] ); ?></h2>
			<?php endif; ?>

			<div class="wpss-featured-container">
				<?php if ( 'carousel' === $attributes['layout'] && $attributes['showArrows'] ) : ?>
					<button type="button" class="wpss-carousel-arrow wpss-carousel-prev" aria-label="<?php esc_attr_e( 'Previous', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</button>
				<?php endif; ?>

				<div class="wpss-featured-track">
					<div class="wpss-featured-slides">
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();
							$this->render_featured_card( $attributes );
						endwhile;
						?>
					</div>
				</div>

				<?php if ( 'carousel' === $attributes['layout'] && $attributes['showArrows'] ) : ?>
					<button type="button" class="wpss-carousel-arrow wpss-carousel-next" aria-label="<?php esc_attr_e( 'Next', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( 'carousel' === $attributes['layout'] && $attributes['showDots'] ) : ?>
				<div class="wpss-carousel-dots"></div>
			<?php endif; ?>
		</div>
		<?php

		wp_reset_postdata();

		return $this->end_render();
	}

	/**
	 * Render a single featured service card.
	 *
	 * @param array $attributes Block attributes.
	 * @return void
	 */
	private function render_featured_card( array $attributes ): void {
		$service_id   = get_the_ID();
		$thumbnail    = get_the_post_thumbnail_url( $service_id, 'medium_large' );
		$seller_id    = get_post_field( 'post_author', $service_id );
		$price        = get_post_meta( $service_id, '_wpss_starting_price', true );
		$rating       = get_post_meta( $service_id, '_wpss_rating_average', true );
		$review_count = get_post_meta( $service_id, '_wpss_review_count', true );
		?>
		<div class="wpss-featured-slide">
			<article class="wpss-featured-card">
				<a href="<?php the_permalink(); ?>" class="wpss-featured-card-link">
					<div class="wpss-featured-thumbnail">
						<span class="wpss-featured-badge"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></span>
						<?php if ( $thumbnail ) : ?>
							<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
						<?php else : ?>
							<div class="wpss-thumbnail-placeholder">
								<span class="dashicons dashicons-format-image"></span>
							</div>
						<?php endif; ?>
					</div>

					<div class="wpss-featured-content">
						<div class="wpss-featured-seller">
							<?php echo get_avatar( $seller_id, 24 ); ?>
							<span><?php echo esc_html( get_the_author_meta( 'display_name', $seller_id ) ); ?></span>
						</div>

						<h3 class="wpss-featured-service-title"><?php the_title(); ?></h3>

						<?php if ( $attributes['showRating'] && $rating ) : ?>
							<div class="wpss-featured-rating">
								<span class="wpss-star">&#9733;</span>
								<span class="wpss-rating-value"><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?></span>
								<span class="wpss-review-count">(<?php echo esc_html( $review_count ?: 0 ); ?>)</span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $attributes['showPrice'] && $price ) : ?>
						<div class="wpss-featured-footer">
							<span class="wpss-price-label"><?php esc_html_e( 'Starting at', 'wp-sell-services' ); ?></span>
							<span class="wpss-price-value"><?php echo esc_html( wpss_format_currency( (float) $price ) ); ?></span>
						</div>
					<?php endif; ?>
				</a>
			</article>
		</div>
		<?php
	}
}
