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
			// Budget bounds. [wpss_buyer_requests] has always accepted these;
			// the block had no equivalent, so the shortcode could not be
			// expressed as a wrapper around the block without losing them.
			'budgetMin'      => [
				'type'    => 'number',
				'default' => 0,
			],
			'budgetMax'      => [
				'type'    => 'number',
				'default' => 0,
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
		wpss_enqueue_frontend_assets();

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
			'budgetMin'      => 0,
			'budgetMax'      => 0,
		];

		$attributes = wp_parse_args( $attributes, $defaults );

		// ONE definition of the open-requests query, owned by BuyerRequestService.
		//
		// This block used to assemble the query itself and spelled out only the
		// status half of "open", missing the expiry half the service applies. It
		// therefore listed EXPIRED requests that [wpss_buyer_requests] correctly
		// hid, so a seller could open one and pitch for work that had already
		// closed. Verified before the fix: an expired request appeared in the
		// block and not in the shortcode.
		//
		// Building the whole argument set here - not just the meta fragment -
		// is what lets the shortcode be a thin wrapper around this block instead
		// of a second implementation that can drift again.
		$query = new \WP_Query(
			\WPSellServices\Services\BuyerRequestService::open_query_args(
				[
					'posts_per_page' => $attributes['perPage'],
					'paged'          => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
					'order_by'       => $attributes['orderBy'],
					'order'          => $attributes['order'],
					'category_id'    => $attributes['category'],
					'budget_min'     => $attributes['budgetMin'],
					'budget_max'     => $attributes['budgetMax'],
				]
			)
		);

		$wrapper_classes = [ 'wpss-requests-' . $attributes['layout'] ];
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?>>
			<?php if ( $query->have_posts() ) : ?>
				<div class="wpss-requests-list">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						// The shared, theme-overridable card - the same one the
						// request archive renders. This block emitted its own
						// inline markup, so a theme override applied on the
						// archive and silently did not here, and the two cards
						// drifted apart in what they showed.
						wpss_get_template_part( 'content', 'request-card' );
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
					<i data-lucide="megaphone" class="wpss-icon" aria-hidden="true"></i>
					<p><?php esc_html_e( 'No buyer requests found.', 'wp-sell-services' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php

		wp_reset_postdata();

		return $this->end_render();
	}
}
