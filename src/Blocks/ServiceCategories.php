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
			'layout'       => [
				'type'    => 'string',
				'default' => 'grid',
			],
			'columns'      => [
				'type'    => 'number',
				'default' => 4,
			],
			'showCount'    => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showIcon'     => [
				'type'    => 'boolean',
				'default' => true,
			],
			'showImage'    => [
				'type'    => 'boolean',
				'default' => false,
			],
			'hideEmpty'    => [
				'type'    => 'boolean',
				'default' => false,
			],
			'parentOnly'   => [
				'type'    => 'boolean',
				'default' => false,
			],
			'maxItems'     => [
				'type'    => 'number',
				'default' => 8,
			],
			'orderBy'      => [
				'type'    => 'string',
				'default' => 'name',
			],
			'order'        => [
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
			<div <?php echo $this->get_wrapper_attributes( $attributes ); ?>>
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
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); ?>>
			<div class="wpss-categories-list">
				<?php foreach ( $categories as $category ) : ?>
					<?php $this->render_category_card( $category, $attributes ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		return $this->end_render();
	}

	/**
	 * Render a single category card.
	 *
	 * @param \WP_Term $category   Category term.
	 * @param array    $attributes Block attributes.
	 * @return void
	 */
	private function render_category_card( \WP_Term $category, array $attributes ): void {
		$link     = add_query_arg(
			[
				'wpss_search'   => '',
				'wpss_category' => $category->term_id,
			],
			get_post_type_archive_link( 'wpss_service' )
		);
		$icon     = get_term_meta( $category->term_id, '_wpss_icon', true );
		$image_id = get_term_meta( $category->term_id, '_wpss_image', true );
		$image    = $image_id ? wp_get_attachment_image_url( (int) $image_id, 'medium' ) : '';
		?>
		<a href="<?php echo esc_url( $link ); ?>" class="wpss-category-card">
			<?php if ( $attributes['showImage'] && $image ) : ?>
				<div class="wpss-category-image">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $category->name ); ?>" loading="lazy">
				</div>
			<?php elseif ( $attributes['showIcon'] ) : ?>
				<div class="wpss-category-icon">
					<?php if ( $icon ) : ?>
						<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
					<?php else : ?>
						<span class="dashicons dashicons-category"></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="wpss-category-content">
				<h4 class="wpss-category-name"><?php echo esc_html( $category->name ); ?></h4>

				<?php if ( $attributes['showCount'] ) : ?>
					<span class="wpss-category-count">
						<?php
						printf(
							/* translators: %d: number of services */
							esc_html( _n( '%d service', '%d services', $category->count, 'wp-sell-services' ) ),
							$category->count
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		</a>
		<?php
	}
}
