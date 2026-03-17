<?php
/**
 * Service Grid Block
 *
 * Displays services in a responsive grid layout.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceGrid class.
 *
 * @since 1.0.0
 */
class ServiceGrid extends AbstractBlock {

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'service-grid';
	}

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Service Grid', 'wp-sell-services' );
	}

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Display services in a responsive grid layout.', 'wp-sell-services' );
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'grid-view';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return array( 'services', 'grid', 'listing', 'gigs', 'marketplace' );
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return array(
			'columns'        => array(
				'type'    => 'number',
				'default' => 3,
			),
			'perPage'        => array(
				'type'    => 'number',
				'default' => 9,
			),
			'category'       => array(
				'type'    => 'number',
				'default' => 0,
			),
			'orderBy'        => array(
				'type'    => 'string',
				'default' => 'date',
			),
			'order'          => array(
				'type'    => 'string',
				'default' => 'DESC',
			),
			'showPagination' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showRating'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showPrice'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showSeller'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'featured'       => array(
				'type'    => 'boolean',
				'default' => false,
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
			'columns'        => 3,
			'perPage'        => 9,
			'category'       => 0,
			'orderBy'        => 'date',
			'order'          => 'DESC',
			'showPagination' => true,
			'showRating'     => true,
			'showPrice'      => true,
			'showSeller'     => true,
			'featured'       => false,
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		// Query arguments.
		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'posts_per_page' => $attributes['perPage'],
			'orderby'        => $attributes['orderBy'],
			'order'          => $attributes['order'],
			'paged'          => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
		);

		// Filter by category.
		if ( ! empty( $attributes['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'wpss_service_category',
					'field'    => 'term_id',
					'terms'    => $attributes['category'],
				),
			);
		}

		// Filter featured only.
		if ( $attributes['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_wpss_featured',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

		$query = new \WP_Query( $args );

		$wrapper_classes = array( 'wpss-grid-cols-' . $attributes['columns'] );
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); ?>>
			<?php if ( $query->have_posts() ) : ?>
				<div class="wpss-services-grid">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$this->render_service_card( $attributes );
					endwhile;
					?>
				</div>

				<?php if ( $attributes['showPagination'] && $query->max_num_pages > 1 ) : ?>
					<div class="wpss-pagination">
						<?php
						echo paginate_links(
							array(
								'total'     => $query->max_num_pages,
								'current'   => max( 1, get_query_var( 'paged' ) ),
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						);
						?>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<div class="wpss-no-services">
					<p><?php esc_html_e( 'No services found.', 'wp-sell-services' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php

		wp_reset_postdata();

		return $this->end_render();
	}

	/**
	 * Render a single service card.
	 *
	 * @param array $attributes Block attributes.
	 * @return void
	 */
	private function render_service_card( array $attributes ): void {
		$service_id   = get_the_ID();
		$thumbnail    = get_the_post_thumbnail_url( $service_id, 'medium_large' );
		$seller_id    = get_post_field( 'post_author', $service_id );
		$price        = get_post_meta( $service_id, '_wpss_starting_price', true );
		$rating       = get_post_meta( $service_id, '_wpss_rating_average', true );
		$review_count = get_post_meta( $service_id, '_wpss_review_count', true );
		?>
		<article class="wpss-service-card">
			<a href="<?php the_permalink(); ?>" class="wpss-service-card-link">
				<div class="wpss-service-thumbnail">
					<?php if ( $thumbnail ) : ?>
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
					<?php else : ?>
						<div class="wpss-service-thumbnail-placeholder">
							<span class="dashicons dashicons-format-image"></span>
						</div>
					<?php endif; ?>
				</div>

				<div class="wpss-service-content">
					<?php if ( $attributes['showSeller'] ) : ?>
						<div class="wpss-service-seller">
							<?php echo get_avatar( $seller_id, 24 ); ?>
							<span class="wpss-seller-name"><?php echo esc_html( get_the_author_meta( 'display_name', $seller_id ) ); ?></span>
						</div>
					<?php endif; ?>

					<h3 class="wpss-service-title"><?php the_title(); ?></h3>

					<?php if ( $attributes['showRating'] && $rating ) : ?>
						<div class="wpss-service-rating">
							<span class="wpss-star">&#9733;</span>
							<span class="wpss-rating-value"><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?></span>
							<span class="wpss-review-count">(<?php echo esc_html( $review_count ?: 0 ); ?>)</span>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $attributes['showPrice'] && $price ) : ?>
					<div class="wpss-service-footer">
						<span class="wpss-price-label"><?php esc_html_e( 'Starting at', 'wp-sell-services' ); ?></span>
						<span class="wpss-price-value"><?php echo esc_html( wpss_format_currency( (float) $price ) ); ?></span>
					</div>
				<?php endif; ?>
			</a>
		</article>
		<?php
	}
}
