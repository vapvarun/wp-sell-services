<?php
/**
 * Service Tag Taxonomy
 *
 * Registers and manages the service tag taxonomy.
 *
 * @package WPSellServices\Taxonomies
 * @since   1.0.0
 */

namespace WPSellServices\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceTagTaxonomy class.
 *
 * @since 1.0.0
 */
class ServiceTagTaxonomy {

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'wpss_service_tag';

	/**
	 * Post type this taxonomy applies to.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'wpss_service';

	/**
	 * Initialize the taxonomy.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register' ], 5 );
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register(): void {
		$labels = [
			'name'                       => _x( 'Service Tags', 'taxonomy general name', 'wp-sell-services' ),
			'singular_name'              => _x( 'Service Tag', 'taxonomy singular name', 'wp-sell-services' ),
			'search_items'               => __( 'Search Tags', 'wp-sell-services' ),
			'popular_items'              => __( 'Popular Tags', 'wp-sell-services' ),
			'all_items'                  => __( 'All Tags', 'wp-sell-services' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Tag', 'wp-sell-services' ),
			'view_item'                  => __( 'View Tag', 'wp-sell-services' ),
			'update_item'                => __( 'Update Tag', 'wp-sell-services' ),
			'add_new_item'               => __( 'Add New Tag', 'wp-sell-services' ),
			'new_item_name'              => __( 'New Tag Name', 'wp-sell-services' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'wp-sell-services' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'wp-sell-services' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'wp-sell-services' ),
			'not_found'                  => __( 'No tags found.', 'wp-sell-services' ),
			'no_terms'                   => __( 'No tags', 'wp-sell-services' ),
			'items_list_navigation'      => __( 'Tags list navigation', 'wp-sell-services' ),
			'items_list'                 => __( 'Tags list', 'wp-sell-services' ),
			'back_to_items'              => __( '&larr; Back to Tags', 'wp-sell-services' ),
			'menu_name'                  => __( 'Tags', 'wp-sell-services' ),
		];

		$args = [
			'labels'             => $labels,
			'hierarchical'       => false,
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'show_tagcloud'      => true,
			'query_var'          => true,
			'rewrite'            => [
				'slug'       => 'service-tag',
				'with_front' => false,
			],
			'capabilities'       => [
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'wpss_manage_services',
			],
		];

		/**
		 * Filter service tag taxonomy arguments.
		 *
		 * @since 1.0.0
		 * @param array $args Taxonomy arguments.
		 */
		$args = apply_filters( 'wpss_service_tag_taxonomy_args', $args );

		register_taxonomy( self::TAXONOMY, self::POST_TYPE, $args );
	}

	/**
	 * Get popular tags.
	 *
	 * @param int $limit Number of tags to return.
	 * @return array<\WP_Term> Array of popular tags.
	 */
	public static function get_popular( int $limit = 20 ): array {
		$terms = get_terms(
			[
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => true,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			]
		);

		return is_wp_error( $terms ) ? [] : $terms;
	}

	/**
	 * Get tags for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return array<\WP_Term> Array of tags.
	 */
	public static function get_service_tags( int $service_id ): array {
		$terms = wp_get_post_terms( $service_id, self::TAXONOMY );
		return is_wp_error( $terms ) ? [] : $terms;
	}

	/**
	 * Get related services by tags.
	 *
	 * @param int $service_id Service post ID.
	 * @param int $limit      Number of services to return.
	 * @return array<\WP_Post> Array of related services.
	 */
	public static function get_related_services( int $service_id, int $limit = 4 ): array {
		$tags = self::get_service_tags( $service_id );

		if ( empty( $tags ) ) {
			return [];
		}

		$tag_ids = wp_list_pluck( $tags, 'term_id' );

		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => $limit,
				'post__not_in'   => [ $service_id ],
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $tag_ids,
					],
				],
				'orderby'        => 'rand',
			]
		);

		return $query->posts;
	}

	/**
	 * Search tags by name.
	 *
	 * @param string $search Search term.
	 * @param int    $limit  Number of tags to return.
	 * @return array<\WP_Term> Array of matching tags.
	 */
	public static function search( string $search, int $limit = 10 ): array {
		$terms = get_terms(
			[
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => $limit,
				'search'     => $search,
			]
		);

		return is_wp_error( $terms ) ? [] : $terms;
	}
}
