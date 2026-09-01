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

defined( 'ABSPATH' ) || exit;

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
			'placeholder'        => [
				'type'    => 'string',
				'default' => '',
			],
			'showCategoryFilter' => [
				'type'    => 'boolean',
				'default' => true,
			],
			'buttonText'         => [
				'type'    => 'string',
				'default' => '',
			],
			'style'              => [
				'type'    => 'string',
				'default' => 'default',
			],
			// Where the form submits. Empty means the service archive.
			// [wpss_service_search] has always accepted an `action` attribute;
			// the block hardcoded the archive, so the two surfaces could not
			// share a renderer until this existed.
			'action'             => [
				'type'    => 'string',
				'default' => '',
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
			// Short enough to fit a sidebar as well as a full-width hero. The
			// long form ("What service are you looking for?") clipped at 260px,
			// and this is a block attribute, so an owner who has the width can
			// still set whatever copy they like per instance.
			'placeholder'        => __( 'Search services...', 'wp-sell-services' ),
			'showCategoryFilter' => true,
			'buttonText'         => __( 'Search', 'wp-sell-services' ),
			'style'              => 'default',
		];

		$attributes = wp_parse_args( $attributes, $defaults );

		// Ensure empty strings fall back to defaults.
		foreach ( $defaults as $key => $default ) {
			if ( is_string( $default ) && isset( $attributes[ $key ] ) && '' === $attributes[ $key ] ) {
				$attributes[ $key ] = $default;
			}
		}

		// Read-only, bookmarkable search/archive filters from the query string -
		// no state change, so nonce verification does not apply. Values sanitized inline.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Param names must match the archive's pre_get_posts reader
		// (ServiceArchiveView::modify_archive_query reads `search` / `category`,
		// as does the archive's own filter bar). The block previously emitted
		// `wpss_search` / `wpss_category`, which the archive ignored — so the
		// block's search + category filters silently did nothing.
		$search_value = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$category     = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$wrapper_classes = [ 'wpss-search-style-' . $attributes['style'] ];
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe markup. ?>>
			<form class="wpss-search-form" method="get" action="<?php echo esc_url( ! empty( $attributes['action'] ) ? $attributes['action'] : get_post_type_archive_link( 'wpss_service' ) ); ?>">
				<div class="wpss-search-fields">
					<div class="wpss-search-input-wrap">
						<i data-lucide="search" class="wpss-icon wpss-search-icon" aria-hidden="true"></i>
						<input
							type="text"
							name="search"
							class="wpss-search-input"
							value="<?php echo esc_attr( $search_value ); ?>"
							placeholder="<?php echo esc_attr( $attributes['placeholder'] ); ?>"
						>
					</div>

					<?php if ( $attributes['showCategoryFilter'] ) : ?>
						<div class="wpss-category-select-wrap">
							<?php
							// Top-level only, and bounded.
							//
							// This had neither: it fetched EVERY category on the
							// site, flat, so children appeared as siblings and a
							// marketplace with a large taxonomy rendered a select
							// with thousands of options. The shortcode scoped to
							// parent = 0 but was equally unbounded; now that both
							// surfaces share this one query, both are fixed.
							$categories = get_terms(
								[
									'taxonomy'   => 'wpss_service_category',
									'hide_empty' => true,
									'parent'     => 0,
									'number'     => (int) apply_filters( 'wpss_search_categories_limit', 100 ),
								]
							);
							?>
							<select name="category" class="wpss-category-select">
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
