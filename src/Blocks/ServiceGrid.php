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
		wpss_enqueue_frontend_assets();

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

		// ONE grid implementation.
		//
		// This block used to build its own WP_Query and emit its own inline
		// card markup, so it rendered neither templates/content-service-card.php
		// (which a theme can override) nor the wpss_service_card_* hooks, and
		// showed no favourites toggle. wpss_render_services_grid() is the
		// canonical renderer and already did all three; it was simply never
		// called from here.
		// The block's own attribute is `perPage`; the shared renderer reads
		// `postsPerPage`. Passing $attributes straight through looks correct and
		// is not - the key simply misses and the renderer falls back to its
		// default of 12, so a block set to show 6 quietly rendered 12.
		$grid = wpss_render_services_grid(
			array_merge(
				$attributes,
				array( 'postsPerPage' => absint( $attributes['perPage'] ) )
			),
			max( 1, (int) get_query_var( 'paged' ) )
		);

		printf(
			'<div class="wpss-services-grid wpss-columns-%s">%s</div>',
			esc_attr( (string) $attributes['columns'] ),
			$grid['html'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built by the shared renderer, escaped at source.
		);

		if ( ! empty( $attributes['showPagination'] ) ) {
			echo $grid['pagination']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output from the shared renderer.
		}

		return $this->end_render();
	}
}
