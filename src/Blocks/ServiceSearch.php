<?php
/**
 * Service Search Block
 *
 * Displays a search form for services.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Blocks;

/**
 * ServiceSearch class.
 *
 * @since 1.0.0
 */
class ServiceSearch extends AbstractBlock {

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'service-search';
	}

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Service Search', 'wp-sell-services' );
	}

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Display a search form to find services.', 'wp-sell-services' );
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'search';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return [ 'search', 'find', 'services', 'filter' ];
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return [
			'placeholder'       => [
				'type'    => 'string',
				'default' => '',
			],
			'showCategoryFilter' => [
				'type'    => 'boolean',
				'default' => true,
			],
			'buttonText'        => [
				'type'    => 'string',
				'default' => '',
			],
			'style'             => [
				'type'    => 'string',
				'default' => 'default',
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
			'placeholder'        => __( 'What service are you looking for?', 'wp-sell-services' ),
			'showCategoryFilter' => true,
			'buttonText'         => __( 'Search', 'wp-sell-services' ),
			'style'              => 'default',
		];

		$attributes   = wp_parse_args( $attributes, $defaults );
		$search_value = isset( $_GET['wpss_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wpss_search'] ) ) : '';
		$category     = isset( $_GET['wpss_category'] ) ? absint( $_GET['wpss_category'] ) : 0;

		$wrapper_classes = [ 'wpss-search-style-' . $attributes['style'] ];
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); ?>>
			<form class="wpss-search-form" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'wpss_service' ) ); ?>">
				<div class="wpss-search-fields">
					<div class="wpss-search-input-wrap">
						<span class="wpss-search-icon dashicons dashicons-search"></span>
						<input
							type="text"
							name="wpss_search"
							class="wpss-search-input"
							value="<?php echo esc_attr( $search_value ); ?>"
							placeholder="<?php echo esc_attr( $attributes['placeholder'] ); ?>"
						>
					</div>

					<?php if ( $attributes['showCategoryFilter'] ) : ?>
						<div class="wpss-category-select-wrap">
							<?php
							$categories = get_terms(
								[
									'taxonomy'   => 'wpss_service_category',
									'hide_empty' => true,
								]
							);
							?>
							<select name="wpss_category" class="wpss-category-select">
								<option value=""><?php esc_html_e( 'All Categories', 'wp-sell-services' ); ?></option>
								<?php if ( ! is_wp_error( $categories ) ) : ?>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $category, $cat->term_id ); ?>>
											<?php echo esc_html( $cat->name ); ?>
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</div>
					<?php endif; ?>

					<button type="submit" class="wpss-search-button">
						<?php echo esc_html( $attributes['buttonText'] ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php

		return $this->end_render();
	}
}
