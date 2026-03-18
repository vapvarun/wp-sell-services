<?php
/**
 * Search Service
 *
 * Unified search across services, vendors, and requests.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Taxonomies\ServiceCategoryTaxonomy;
use WPSellServices\Taxonomies\ServiceTagTaxonomy;
use WPSellServices\PostTypes\ServicePostType;
use WPSellServices\PostTypes\BuyerRequestPostType;

defined( 'ABSPATH' ) || exit;

/**
 * SearchService class.
 *
 * @since 1.0.0
 */
class SearchService {

	/**
	 * Search types.
	 */
	public const TYPE_SERVICE = 'service';
	public const TYPE_VENDOR  = 'vendor';
	public const TYPE_REQUEST = 'request';
	public const TYPE_ALL     = 'all';

	/**
	 * Vendor profile repository.
	 *
	 * @var VendorProfileRepository
	 */
	private VendorProfileRepository $vendor_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_repo = new VendorProfileRepository();
	}

	/**
	 * Unified search.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return array<string, mixed> Search results.
	 */
	public function search( string $query, array $args = array() ): array {
		$defaults = array(
			'type'        => self::TYPE_ALL,
			'limit'       => 20,
			'page'        => 1,
			'category_id' => 0,
			'min_price'   => 0,
			'max_price'   => 0,
			'min_rating'  => 0,
			'country'     => '',
			'sort_by'     => 'relevance',
			'sort_order'  => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$results = array(
			'query'    => $query,
			'args'     => $args,
			'services' => array(),
			'vendors'  => array(),
			'requests' => array(),
			'totals'   => array(
				'services' => 0,
				'vendors'  => 0,
				'requests' => 0,
			),
		);

		switch ( $args['type'] ) {
			case self::TYPE_SERVICE:
				$results['services']           = $this->search_services( $query, $args );
				$results['totals']['services'] = $this->count_services( $query, $args );
				break;

			case self::TYPE_VENDOR:
				$results['vendors']           = $this->search_vendors( $query, $args );
				$results['totals']['vendors'] = $this->count_vendors( $query, $args );
				break;

			case self::TYPE_REQUEST:
				$results['requests']           = $this->search_requests( $query, $args );
				$results['totals']['requests'] = $this->count_requests( $query, $args );
				break;

			case self::TYPE_ALL:
			default:
				$results['services']           = $this->search_services( $query, $args );
				$results['vendors']            = $this->search_vendors( $query, array_merge( $args, array( 'limit' => 5 ) ) );
				$results['requests']           = $this->search_requests( $query, array_merge( $args, array( 'limit' => 5 ) ) );
				$results['totals']['services'] = $this->count_services( $query, $args );
				$results['totals']['vendors']  = $this->count_vendors( $query, $args );
				$results['totals']['requests'] = $this->count_requests( $query, $args );
				break;
		}

		/**
		 * Filter search results.
		 *
		 * @since 1.0.0
		 * @param array  $results Search results.
		 * @param string $query   Search query.
		 * @param array  $args    Search arguments.
		 */
		return apply_filters( 'wpss_search_results', $results, $query, $args );
	}

	/**
	 * Search services.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return array<\WP_Post> Service posts.
	 */
	public function search_services( string $query, array $args = array() ): array {
		$query_args = array(
			'post_type'      => ServicePostType::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => $args['limit'] ?? 20,
			'paged'          => $args['page'] ?? 1,
		);

		// Category filter.
		if ( ! empty( $args['category_id'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => ServiceCategoryTaxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( (int) $args['category_id'] ),
				),
			);
		}

		// Price filter.
		$meta_query = array();

		if ( ! empty( $args['min_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_wpss_starting_price',
				'value'   => (float) $args['min_price'],
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		if ( ! empty( $args['max_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_wpss_starting_price',
				'value'   => (float) $args['max_price'],
				'compare' => '<=',
				'type'    => 'DECIMAL',
			);
		}

		// Rating filter.
		if ( ! empty( $args['min_rating'] ) ) {
			$meta_query[] = array(
				'key'     => '_wpss_rating_average',
				'value'   => (float) $args['min_rating'],
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation']   = 'AND';
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// Sorting.
		switch ( $args['sort_by'] ?? 'relevance' ) {
			case 'price_low':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_wpss_starting_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['order']    = 'ASC';
				break;

			case 'price_high':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_wpss_starting_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['order']    = 'DESC';
				break;

			case 'rating':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_wpss_rating_average'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['order']    = 'DESC';
				break;

			case 'newest':
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'DESC';
				break;

			case 'popular':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_wpss_order_count'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['order']    = 'DESC';
				break;

			case 'relevance':
			default:
				// Default WordPress relevance.
				break;
		}

		// Exclude services from vendors on vacation mode.
		global $wpdb;
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$vacation_vendors = $wpdb->get_col(
			"SELECT user_id FROM {$profiles_table} WHERE vacation_mode = 1"
		);

		if ( ! empty( $vacation_vendors ) ) {
			$query_args['author__not_in'] = array_map( 'intval', $vacation_vendors );
		}

		$wp_query = new \WP_Query( $query_args );

		return $wp_query->posts;
	}

	/**
	 * Count services matching query.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return int Count.
	 */
	private function count_services( string $query, array $args ): int {
		$args['limit'] = 1;
		$args['page']  = 1;

		$query_args = array(
			'post_type'      => ServicePostType::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);

		if ( ! empty( $args['category_id'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => ServiceCategoryTaxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( (int) $args['category_id'] ),
				),
			);
		}

		$wp_query = new \WP_Query( $query_args );

		return $wp_query->found_posts;
	}

	/**
	 * Search vendors.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return array<object> Vendor profiles.
	 */
	public function search_vendors( string $query, array $args = array() ): array {
		$search_args = array(
			'limit'      => $args['limit'] ?? 20,
			'offset'     => ( ( $args['page'] ?? 1 ) - 1 ) * ( $args['limit'] ?? 20 ),
			'min_rating' => $args['min_rating'] ?? 0,
		);

		if ( ! empty( $args['country'] ) ) {
			$search_args['country'] = $args['country'];
		}

		return $this->vendor_repo->search( $query, $search_args );
	}

	/**
	 * Count vendors matching query.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return int Count.
	 */
	private function count_vendors( string $query, array $args ): int {
		global $wpdb;

		$vendor_profiles = $wpdb->prefix . 'wpss_vendor_profiles';

		$where = '(display_name LIKE %s OR tagline LIKE %s OR bio LIKE %s) AND is_available = 1 AND vacation_mode = 0';
		$like  = '%' . $wpdb->esc_like( $query ) . '%';

		$values = array( $like, $like, $like );

		if ( ! empty( $args['country'] ) ) {
			$where   .= ' AND country = %s';
			$values[] = $args['country'];
		}

		if ( ! empty( $args['min_rating'] ) ) {
			$where   .= ' AND average_rating >= %f';
			$values[] = (float) $args['min_rating'];
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$vendor_profiles} WHERE {$where}",
				$values
			)
		);
	}

	/**
	 * Search buyer requests.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return array<\WP_Post> Request posts.
	 */
	public function search_requests( string $query, array $args = array() ): array {
		$query_args = array(
			'post_type'      => BuyerRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => $args['limit'] ?? 20,
			'paged'          => $args['page'] ?? 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_wpss_status',
					'value' => 'open',
				),
			),
		);

		// Category filter.
		if ( ! empty( $args['category_id'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => ServiceCategoryTaxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( (int) $args['category_id'] ),
				),
			);
		}

		// Budget filter.
		if ( ! empty( $args['min_price'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_wpss_budget_min',
				'value'   => (float) $args['min_price'],
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		if ( ! empty( $args['max_price'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_wpss_budget_max',
				'value'   => (float) $args['max_price'],
				'compare' => '<=',
				'type'    => 'DECIMAL',
			);
		}

		$wp_query = new \WP_Query( $query_args );

		return $wp_query->posts;
	}

	/**
	 * Count requests matching query.
	 *
	 * @param string               $query Search query.
	 * @param array<string, mixed> $args Search arguments.
	 * @return int Count.
	 */
	private function count_requests( string $query, array $args ): int {
		$query_args = array(
			'post_type'      => BuyerRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_wpss_status',
					'value' => 'open',
				),
			),
		);

		$wp_query = new \WP_Query( $query_args );

		return $wp_query->found_posts;
	}

	/**
	 * Get search suggestions (autocomplete).
	 *
	 * @param string $query Partial search query.
	 * @param int    $limit Number of suggestions.
	 * @return array<string, mixed> Suggestions by type.
	 */
	public function get_suggestions( string $query, int $limit = 10 ): array {
		$suggestions = array(
			'services'   => array(),
			'categories' => array(),
			'tags'       => array(),
			'vendors'    => array(),
		);

		if ( strlen( $query ) < 2 ) {
			return $suggestions;
		}

		// Service title suggestions.
		$services = new \WP_Query(
			array(
				'post_type'      => ServicePostType::POST_TYPE,
				'post_status'    => 'publish',
				's'              => $query,
				'posts_per_page' => $limit,
				'fields'         => 'ids',
			)
		);

		foreach ( $services->posts as $post_id ) {
			$suggestions['services'][] = array(
				'id'    => $post_id,
				'title' => get_the_title( $post_id ),
				'url'   => get_permalink( $post_id ),
			);
		}

		// Category suggestions.
		$categories = get_terms(
			array(
				'taxonomy'   => ServiceCategoryTaxonomy::TAXONOMY,
				'search'     => $query,
				'number'     => $limit,
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$suggestions['categories'][] = array(
					'id'   => $category->term_id,
					'name' => $category->name,
					'url'  => get_term_link( $category ),
				);
			}
		}

		// Tag suggestions.
		$tags = get_terms(
			array(
				'taxonomy'   => ServiceTagTaxonomy::TAXONOMY,
				'search'     => $query,
				'number'     => $limit,
				'hide_empty' => true,
			)
		);

		if ( ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				$suggestions['tags'][] = array(
					'id'   => $tag->term_id,
					'name' => $tag->name,
					'url'  => get_term_link( $tag ),
				);
			}
		}

		// Vendor suggestions.
		$vendors = $this->vendor_repo->search( $query, array( 'limit' => $limit ) );
		foreach ( $vendors as $vendor ) {
			$suggestions['vendors'][] = array(
				'id'           => $vendor->user_id,
				'display_name' => $vendor->display_name,
				'url'          => get_author_posts_url( $vendor->user_id ),
			);
		}

		/**
		 * Filter search suggestions.
		 *
		 * @since 1.0.0
		 * @param array  $suggestions Search suggestions.
		 * @param string $query       Search query.
		 */
		return apply_filters( 'wpss_search_suggestions', $suggestions, $query );
	}

	/**
	 * Get popular searches.
	 *
	 * @param int $limit Number of searches.
	 * @return array<string> Popular search terms.
	 */
	public function get_popular_searches( int $limit = 10 ): array {
		$searches = get_option( 'wpss_popular_searches', array() );

		if ( empty( $searches ) ) {
			return array();
		}

		// Sort by count.
		arsort( $searches );

		return array_slice( array_keys( $searches ), 0, $limit );
	}

	/**
	 * Track a search query.
	 *
	 * @param string $query Search query.
	 * @return void
	 */
	public function track_search( string $query ): void {
		$query = sanitize_text_field( strtolower( trim( $query ) ) );

		if ( strlen( $query ) < 2 ) {
			return;
		}

		$searches = get_option( 'wpss_popular_searches', array() );

		if ( ! isset( $searches[ $query ] ) ) {
			$searches[ $query ] = 0;
		}

		++$searches[ $query ];

		// Keep only top 100 searches.
		if ( count( $searches ) > 100 ) {
			arsort( $searches );
			$searches = array_slice( $searches, 0, 100, true );
		}

		update_option( 'wpss_popular_searches', $searches );
	}

	/**
	 * Get related services.
	 *
	 * @param int $service_id Service post ID.
	 * @param int $limit Number of services.
	 * @return array<\WP_Post> Related services.
	 */
	public function get_related_services( int $service_id, int $limit = 4 ): array {
		// First try by tags.
		$related = ServiceTagTaxonomy::get_related_services( $service_id, $limit );

		if ( count( $related ) >= $limit ) {
			return $related;
		}

		// Supplement with same category.
		$categories = wp_get_post_terms( $service_id, ServiceCategoryTaxonomy::TAXONOMY, array( 'fields' => 'ids' ) );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$existing_ids = array_merge( array( $service_id ), wp_list_pluck( $related, 'ID' ) );

			$more = new \WP_Query(
				array(
					'post_type'      => ServicePostType::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => $limit - count( $related ),
					'post__not_in'   => $existing_ids,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => ServiceCategoryTaxonomy::TAXONOMY,
							'field'    => 'term_id',
							'terms'    => $categories,
						),
					),
					'orderby'        => 'rand',
				)
			);

			$related = array_merge( $related, $more->posts );
		}

		return array_slice( $related, 0, $limit );
	}

	/**
	 * Get available sort options.
	 *
	 * @return array<string, string> Sort options.
	 */
	public static function get_sort_options(): array {
		return array(
			'relevance'  => __( 'Relevance', 'wp-sell-services' ),
			'newest'     => __( 'Newest', 'wp-sell-services' ),
			'popular'    => __( 'Most Popular', 'wp-sell-services' ),
			'rating'     => __( 'Highest Rated', 'wp-sell-services' ),
			'price_low'  => __( 'Price: Low to High', 'wp-sell-services' ),
			'price_high' => __( 'Price: High to Low', 'wp-sell-services' ),
		);
	}
}
