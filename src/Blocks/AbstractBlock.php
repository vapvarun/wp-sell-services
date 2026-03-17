<?php
/**
 * Abstract Block Base Class
 *
 * Base class for all Gutenberg blocks.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractBlock class.
 *
 * @since 1.0.0
 */
abstract class AbstractBlock {

	/**
	 * Block namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'wpss';

	/**
	 * Get block name (without namespace).
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	abstract public function get_title(): string;

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'store';
	}

	/**
	 * Get block category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'wp-sell-services';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return [ 'services', 'marketplace', 'fiverr' ];
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return [];
	}

	/**
	 * Get block supports.
	 *
	 * @return array
	 */
	public function get_supports(): array {
		return [
			'align'  => [ 'wide', 'full' ],
			'anchor' => true,
		];
	}

	/**
	 * Register the block.
	 *
	 * @return void
	 */
	public function register(): void {
		register_block_type(
			$this->namespace . '/' . $this->get_name(),
			[
				'title'           => $this->get_title(),
				'description'     => $this->get_description(),
				'icon'            => $this->get_icon(),
				'category'        => $this->get_category(),
				'keywords'        => $this->get_keywords(),
				'attributes'      => $this->get_attributes(),
				'supports'        => $this->get_supports(),
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	abstract public function render( array $attributes, string $content = '' ): string;

	/**
	 * Get wrapper attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $extra      Extra classes.
	 * @return string
	 */
	protected function get_wrapper_attributes( array $attributes, array $extra = [] ): string {
		$classes = [ 'wpss-block', 'wpss-block-' . $this->get_name() ];

		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . $attributes['align'];
		}

		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$classes = array_merge( $classes, $extra );

		$wrapper_attributes = get_block_wrapper_attributes(
			[
				'class' => implode( ' ', $classes ),
			]
		);

		if ( ! empty( $attributes['anchor'] ) ) {
			$wrapper_attributes .= ' id="' . esc_attr( $attributes['anchor'] ) . '"';
		}

		return $wrapper_attributes;
	}

	/**
	 * Start output buffering for render.
	 *
	 * @return void
	 */
	protected function start_render(): void {
		ob_start();
	}

	/**
	 * End output buffering and return content.
	 *
	 * @return string
	 */
	protected function end_render(): string {
		return ob_get_clean();
	}
}
