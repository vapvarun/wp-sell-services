<?php
/**
 * Gutenberg Blocks Manager
 *
 * Registers and manages all Gutenberg blocks for the plugin.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * BlocksManager class.
 *
 * @since 1.0.0
 */
class BlocksManager {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registered blocks.
	 *
	 * @var array<string, AbstractBlock>
	 */
	private array $blocks = [];

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize blocks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register blocks.
		$this->register_blocks();

		// Enqueue block assets.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_block_assets' ] );

		// Register block category.
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );
	}

	/**
	 * Register all plugin blocks.
	 *
	 * @return void
	 */
	private function register_blocks(): void {
		$block_classes = [
			ServiceGrid::class,
			ServiceSearch::class,
			ServiceCategories::class,
			FeaturedServices::class,
			SellerCard::class,
			BuyerRequests::class,
		];

		foreach ( $block_classes as $block_class ) {
			if ( class_exists( $block_class ) ) {
				$block                              = new $block_class();
				$this->blocks[ $block->get_name() ] = $block;
				$block->register();
			}
		}

		/**
		 * Filter registered blocks.
		 *
		 * @param array $blocks Registered block instances.
		 */
		$this->blocks = apply_filters( 'wpss_blocks', $this->blocks );
	}

	/**
	 * Enqueue editor-only assets.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = \WPSS_PLUGIN_DIR . 'assets/js/blocks.asset.php';

		$dependencies = [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor' ];
		$version      = \WPSS_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset        = include $asset_file;
			$dependencies = array_merge( $dependencies, $asset['dependencies'] ?? [] );
			$version      = $asset['version'] ?? $version;
		}

		wp_enqueue_script(
			'wpss-blocks-editor',
			\WPSS_PLUGIN_URL . 'assets/js/blocks.js',
			array_unique( $dependencies ),
			$version,
			true
		);

		wp_localize_script(
			'wpss-blocks-editor',
			'wpssBlocks',
			[
				'pluginUrl'  => \WPSS_PLUGIN_URL,
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wpss_blocks' ),
				'categories' => $this->get_service_categories(),
				'i18n'       => [
					'blockTitle'      => __( 'WP Sell Services', 'wp-sell-services' ),
					'services'        => __( 'Services', 'wp-sell-services' ),
					'search'          => __( 'Search', 'wp-sell-services' ),
					'categories'      => __( 'Categories', 'wp-sell-services' ),
					'featured'        => __( 'Featured', 'wp-sell-services' ),
					'seller'          => __( 'Seller', 'wp-sell-services' ),
					'requests'        => __( 'Requests', 'wp-sell-services' ),
					'columns'         => __( 'Columns', 'wp-sell-services' ),
					'perPage'         => __( 'Services per page', 'wp-sell-services' ),
					'showPagination'  => __( 'Show pagination', 'wp-sell-services' ),
					'showFilters'     => __( 'Show filters', 'wp-sell-services' ),
					'showRating'      => __( 'Show rating', 'wp-sell-services' ),
					'showPrice'       => __( 'Show price', 'wp-sell-services' ),
					'category'        => __( 'Category', 'wp-sell-services' ),
					'orderBy'         => __( 'Order by', 'wp-sell-services' ),
					'order'           => __( 'Order', 'wp-sell-services' ),
					'allCategories'   => __( 'All Categories', 'wp-sell-services' ),
					'placeholder'     => __( 'Search services...', 'wp-sell-services' ),
					'searchButton'    => __( 'Search', 'wp-sell-services' ),
					'noServicesFound' => __( 'No services found.', 'wp-sell-services' ),
				],
			]
		);

		wp_enqueue_style(
			'wpss-blocks-editor',
			\WPSS_PLUGIN_URL . 'assets/css/blocks-editor.css',
			[ 'wp-edit-blocks' ],
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-blocks-editor', 'rtl', 'replace' );
	}

	/**
	 * Enqueue frontend block assets.
	 *
	 * @return void
	 */
	public function enqueue_block_assets(): void {
		// Only enqueue on frontend when blocks are used.
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'wpss-blocks',
			\WPSS_PLUGIN_URL . 'assets/css/blocks.css',
			[],
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-blocks', 'rtl', 'replace' );

		wp_enqueue_script(
			'wpss-blocks',
			\WPSS_PLUGIN_URL . 'assets/js/blocks-frontend.js',
			[ 'jquery' ],
			\WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-blocks',
			'wpssBlocksFrontend',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_blocks_frontend' ),
			]
		);
	}

	/**
	 * Register custom block category.
	 *
	 * @param array                    $categories Block categories.
	 * @param \WP_Block_Editor_Context $context    Block editor context.
	 * @return array
	 */
	public function register_block_category( array $categories, $context ): array {
		return array_merge(
			[
				[
					'slug'  => 'wp-sell-services',
					'title' => __( 'WP Sell Services', 'wp-sell-services' ),
					'icon'  => 'store',
				],
			],
			$categories
		);
	}

	/**
	 * Get service categories for block editor.
	 *
	 * @return array
	 */
	private function get_service_categories(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$categories = [];

		foreach ( $terms as $term ) {
			$categories[] = [
				'value' => $term->term_id,
				'label' => $term->name,
			];
		}

		return $categories;
	}

	/**
	 * Get a registered block by name.
	 *
	 * @param string $name Block name.
	 * @return AbstractBlock|null
	 */
	public function get_block( string $name ): ?AbstractBlock {
		return $this->blocks[ $name ] ?? null;
	}

	/**
	 * Get all registered blocks.
	 *
	 * @return array<string, AbstractBlock>
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}
}
