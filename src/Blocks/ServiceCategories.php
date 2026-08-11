<?php
/**
 * Service Categories Block
 *
 * Displays service categories in various layouts.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceCategories class.
 *
 * @since 1.0.0
 */
class ServiceCategories extends AbstractBlock {

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'service-categories';
	}

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Service Categories', 'wp-sell-services' );
	}

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Display service categories in a grid or list.', 'wp-sell-services' );
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'category';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return [ 'categories', 'taxonomy', 'navigation', 'browse' ];
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return [
			'layout'     => [
				'type'    => 'string',
				'default' => 'grid',
			],
			'columns'    => [
				'type'    => 'number',
				'default' => 4,
			],
			'showCount'  => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showIcon'   => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showImage'  => [
				'type'    => 'boolean',
				'default' => false,
			],
			'hideEmpty'  => [
				'type'    => 'boolean',
				'default' => false,
			],
			'parentOnly' => [
				'type'    => 'boolean',
				'default' => false,
			],
			'maxItems'   => [
				'type'    => 'number',
				'default' => 8,
			],
			'orderBy'    => [
				'type'    => 'string',
				'default' => 'name',
			],
			'order'      => [
				'type'    => 'string',
				'default' => 'ASC',
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
			'layout'     => 'grid',
			'columns'    => 4,
			'showCount'  => true,
			'showIcon'   => true,
			'showImage'  => false,
			'hideEmpty'  => false,
			'parentOnly' => false,
			'maxItems'   => 8,
			'orderBy'    => 'name',
			'order'      => 'ASC',
		];

		$attributes = wp_parse_args( $attributes, $defaults );

		$args = [
			'taxonomy'   => 'wpss_service_category',
			'hide_empty' => $attributes['hideEmpty'],
			'number'     => $attributes['maxItems'],
			'orderby'    => $attributes['orderBy'],
			'order'      => $attributes['order'],
		];

		if ( $attributes['parentOnly'] ) {
			$args['parent'] = 0;
		}

		$categories = get_terms( $args );

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			?>
			<div <?php echo $this->get_wrapper_attributes( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?>>
				<p class="wpss-no-categories"><?php esc_html_e( 'No categories found.', 'wp-sell-services' ); ?></p>
			</div>
			<?php
			return $this->end_render();
		}

		$wrapper_classes = [
			'wpss-categories-' . $attributes['layout'],
			'wpss-grid-cols-' . $attributes['columns'],
		];
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?>>
			<div class="wpss-categories-list">
				<?php
				foreach ( $categories as $category ) :
					// ONE category card, the theme-overridable partial - the same one
					// [wpss_service_categories] renders. This block owned a private
					// render_category_card() and the shortcode owned different inline
					// markup, so a theme override applied to neither and the two drifted
					// in heading level, icon markup and image handling.
					wpss_get_template_part(
						'partials/category-card',
						'',
						[
							'category'   => $category,
							'show_count' => (bool) $attributes['showCount'],
							'show_icon'  => (bool) $attributes['showIcon'],
							'show_image' => (bool) $attributes['showImage'],
						]
					);
				endforeach;
				?>
			</div>
		</div>
		<?php

		return $this->end_render();
	}
}
