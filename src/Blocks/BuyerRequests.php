<?php
/**
 * Buyer Requests Block
 *
 * Displays buyer requests for sellers to browse.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * BuyerRequests class.
 *
 * @since 1.0.0
 */
class BuyerRequests extends AbstractBlock {

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'buyer-requests';
	}

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Buyer Requests', 'wp-sell-services' );
	}

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Display buyer requests for sellers to browse and respond.', 'wp-sell-services' );
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'megaphone';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return [ 'requests', 'jobs', 'buyer', 'projects', 'offers' ];
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return [
			'perPage'        => [
				'type'    => 'number',
				'default' => 10,
			],
			'category'       => [
				'type'    => 'number',
				'default' => 0,
			],
			'orderBy'        => [
				'type'    => 'string',
				'default' => 'date',
			],
			'order'          => [
				'type'    => 'string',
				'default' => 'DESC',
			],
			'showPagination' => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showBudget'     => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showDeadline'   => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showOffers'     => [
				'type'    => 'boolean',
				'default' => true,
			],
			'layout'         => [
				'type'    => 'string',
				'default' => 'list',
			],
		];
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

		$defaults = [
			'perPage'        => 10,
			'category'       => 0,
			'orderBy'        => 'date',
			'order'          => 'DESC',
			'showPagination' => true,
			'showBudget'     => true,
			'showDeadline'   => true,
			'showOffers'     => true,
			'layout'         => 'list',
		];

		$attributes = wp_parse_args( $attributes, $defaults );

		// Query arguments.
		$args = [
			'post_type'      => 'wpss_request',
			'post_status'    => 'publish',
			'posts_per_page' => $attributes['perPage'],
			'orderby'        => $attributes['orderBy'],
			'order'          => $attributes['order'],
			'paged'          => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
			'meta_query'     => [
				[
					'key'     => '_wpss_request_status',
					'value'   => 'open',
					'compare' => '=',
				],
			],
		];

		// Filter by category.
		if ( ! empty( $attributes['category'] ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'wpss_service_category',
					'field'    => 'term_id',
					'terms'    => $attributes['category'],
				],
			];
		}

		$query = new \WP_Query( $args );

		$wrapper_classes = [ 'wpss-requests-' . $attributes['layout'] ];
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?>>
			<?php if ( $query->have_posts() ) : ?>
				<div class="wpss-requests-list">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$this->render_request_card( $attributes );
					endwhile;
					?>
				</div>

				<?php if ( $attributes['showPagination'] && $query->max_num_pages > 1 ) : ?>
					<div class="wpss-pagination">
						<?php
						echo wp_kses_post(
							paginate_links(
								[
									'total'     => $query->max_num_pages,
									'current'   => max( 1, get_query_var( 'paged' ) ),
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								]
							)
						);
						?>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<div class="wpss-no-requests">
					<span class="dashicons dashicons-megaphone"></span>
					<p><?php esc_html_e( 'No buyer requests found.', 'wp-sell-services' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php

		wp_reset_postdata();

		return $this->end_render();
	}

	/**
	 * Render a single request card.
	 *
	 * @param array $attributes Block attributes.
	 * @return void
	 */
	private function render_request_card( array $attributes ): void {
		$request_id   = get_the_ID();
		$buyer_id     = get_post_field( 'post_author', $request_id );
		$budget_min   = get_post_meta( $request_id, '_wpss_budget_min', true );
		$budget_max   = get_post_meta( $request_id, '_wpss_budget_max', true );
		$deadline     = get_post_meta( $request_id, '_wpss_deadline', true );
		$offers_count = $this->get_offers_count( $request_id );
		$categories   = get_the_terms( $request_id, 'wpss_service_category' );
		?>
		<article class="wpss-request-card">
			<div class="wpss-request-header">
				<div class="wpss-request-buyer">
					<?php echo get_avatar( $buyer_id, 40 ); ?>
					<div class="wpss-buyer-info">
						<span class="wpss-buyer-name"><?php echo esc_html( get_the_author_meta( 'display_name', $buyer_id ) ); ?></span>
						<span class="wpss-request-date"><?php echo esc_html( human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'wp-sell-services' ); ?></span>
					</div>
				</div>

				<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
					<div class="wpss-request-categories">
						<?php foreach ( $categories as $category ) : ?>
							<span class="wpss-category-tag"><?php echo esc_html( $category->name ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="wpss-request-content">
				<h3 class="wpss-request-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>

				<div class="wpss-request-excerpt">
					<?php the_excerpt(); ?>
				</div>
			</div>

			<div class="wpss-request-footer">
				<div class="wpss-request-meta">
					<?php if ( $attributes['showBudget'] && ( $budget_min || $budget_max ) ) : ?>
						<span class="wpss-request-budget">
							<span class="dashicons dashicons-money-alt"></span>
							<?php
							if ( $budget_min && $budget_max ) {
								printf(
									'%s - %s',
									esc_html( wpss_format_currency( (float) $budget_min ) ),
									esc_html( wpss_format_currency( (float) $budget_max ) )
								);
							} elseif ( $budget_max ) {
								printf(
									/* translators: %s: budget amount */
									esc_html__( 'Up to %s', 'wp-sell-services' ),
									esc_html( wpss_format_currency( (float) $budget_max ) )
								);
							} else {
								echo esc_html( wpss_format_currency( (float) $budget_min ) );
							}
							?>
						</span>
					<?php endif; ?>

					<?php if ( $attributes['showDeadline'] && $deadline ) : ?>
						<span class="wpss-request-deadline">
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $deadline ) ) ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $attributes['showOffers'] ) : ?>
						<span class="wpss-request-offers">
							<span class="dashicons dashicons-format-chat"></span>
							<?php
							printf(
								/* translators: %d: number of offers */
								esc_html( _n( '%d offer', '%d offers', $offers_count, 'wp-sell-services' ) ),
								absint( $offers_count )
							);
							?>
						</span>
					<?php endif; ?>
				</div>

				<a href="<?php the_permalink(); ?>" class="wpss-button wpss-button-outline">
					<?php esc_html_e( 'Send Offer', 'wp-sell-services' ); ?>
				</a>
			</div>
		</article>
		<?php
	}

	/**
	 * Get offers count for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return int
	 */
	private function get_offers_count( int $request_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_request_offers
				WHERE request_id = %d",
				$request_id
			)
		);
	}
}
