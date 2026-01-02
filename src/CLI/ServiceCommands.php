<?php
/**
 * WP-CLI Commands for WP Sell Services
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CLI;

use WP_CLI;
use WP_CLI_Command;

/**
 * Manage WP Sell Services from the command line.
 *
 * ## EXAMPLES
 *
 *     # Create 20 demo services
 *     $ wp wpss demo create --count=20
 *
 *     # Delete all services
 *     $ wp wpss demo delete --yes
 *
 *     # List all services with stats
 *     $ wp wpss service list
 *
 * @since 1.0.0
 */
class ServiceCommands extends WP_CLI_Command {

	/**
	 * Demo service templates organized by category.
	 *
	 * @var array
	 */
	private array $service_templates = array();

	/**
	 * Constructor - initialize service templates.
	 */
	public function __construct() {
		$this->service_templates = $this->get_service_templates();
	}

	/**
	 * Create demo services for testing.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : Number of services to create.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--category=<slug>]
	 * : Only create services in this category.
	 *
	 * [--featured=<number>]
	 * : Number of services to mark as featured.
	 * ---
	 * default: 5
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Create 20 demo services
	 *     $ wp wpss demo create --count=20
	 *
	 *     # Create 10 services in Graphics category
	 *     $ wp wpss demo create --count=10 --category=graphics-design
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function create( array $args, array $assoc_args ): void {
		$count         = (int) ( $assoc_args['count'] ?? 20 );
		$category_slug = $assoc_args['category'] ?? null;
		$featured_count = (int) ( $assoc_args['featured'] ?? 5 );

		// Filter templates by category if specified.
		$templates = $this->service_templates;
		if ( $category_slug ) {
			$templates = array_filter(
				$templates,
				function ( $t ) use ( $category_slug ) {
					return sanitize_title( $t['category'] ) === $category_slug;
				}
			);
			if ( empty( $templates ) ) {
				WP_CLI::error( "No templates found for category: {$category_slug}" );
			}
		}

		// Ensure we have enough templates.
		$templates = array_values( $templates );
		$template_count = count( $templates );

		WP_CLI::log( "Creating {$count} demo services..." );
		WP_CLI::log( '' );

		$created  = 0;
		$errors   = 0;
		$featured = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Creating services', $count );

		for ( $i = 0; $i < $count; $i++ ) {
			// Cycle through templates.
			$template = $templates[ $i % $template_count ];

			// Add variation to make services unique.
			$variation = (int) floor( $i / $template_count );
			$service_data = $this->apply_variation( $template, $variation );

			// Mark some as featured.
			if ( $featured < $featured_count && ( $i % 4 === 0 || $template['featured'] ?? false ) ) {
				$service_data['featured'] = true;
				$featured++;
			}

			$result = $this->create_service( $service_data );

			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$created++;
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::log( '' );
		WP_CLI::success( "Created {$created} services ({$featured} featured)" );
		if ( $errors > 0 ) {
			WP_CLI::warning( "Failed to create {$errors} services" );
		}
	}

	/**
	 * Delete all demo/test services.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * [--all]
	 * : Delete ALL services (not just demos).
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete all services with confirmation
	 *     $ wp wpss demo delete
	 *
	 *     # Delete without confirmation
	 *     $ wp wpss demo delete --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function delete( array $args, array $assoc_args ): void {
		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		$count = count( $services );

		if ( 0 === $count ) {
			WP_CLI::success( 'No services to delete.' );
			return;
		}

		WP_CLI::confirm( "Delete {$count} services?", $assoc_args );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting services', $count );

		foreach ( $services as $post_id ) {
			wp_delete_post( $post_id, true );
			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( "Deleted {$count} services." );
	}

	/**
	 * List all services with stats.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--category=<slug>]
	 * : Filter by category slug.
	 *
	 * [--featured]
	 * : Only show featured services.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all services
	 *     $ wp wpss service list
	 *
	 *     # List as JSON
	 *     $ wp wpss service list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_services( array $args, array $assoc_args ): void {
		$query_args = array(
			'post_type'      => 'wpss_service',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		// Filter by category.
		if ( ! empty( $assoc_args['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'wpss_service_category',
					'field'    => 'slug',
					'terms'    => $assoc_args['category'],
				),
			);
		}

		// Filter by featured.
		if ( isset( $assoc_args['featured'] ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => '_wpss_featured',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

		$services = get_posts( $query_args );

		if ( empty( $services ) ) {
			WP_CLI::log( 'No services found.' );
			return;
		}

		$items = array();
		foreach ( $services as $service ) {
			$categories = wp_get_post_terms( $service->ID, 'wpss_service_category', array( 'fields' => 'names' ) );

			$items[] = array(
				'ID'        => $service->ID,
				'Title'     => mb_substr( $service->post_title, 0, 50 ) . ( strlen( $service->post_title ) > 50 ? '...' : '' ),
				'Category'  => is_array( $categories ) ? implode( ', ', $categories ) : '',
				'Price'     => '$' . get_post_meta( $service->ID, '_wpss_starting_price', true ),
				'Rating'    => get_post_meta( $service->ID, '_wpss_rating_average', true ) ?: '0',
				'Orders'    => get_post_meta( $service->ID, '_wpss_order_count', true ) ?: '0',
				'Views'     => get_post_meta( $service->ID, '_wpss_view_count', true ) ?: '0',
				'Featured'  => get_post_meta( $service->ID, '_wpss_featured', true ) ? 'Yes' : 'No',
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $items, array( 'ID', 'Title', 'Category', 'Price', 'Rating', 'Orders', 'Views', 'Featured' ) );
	}

	/**
	 * Show service statistics summary.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss service stats
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function stats( array $args, array $assoc_args ): void {
		global $wpdb;

		$total = wp_count_posts( 'wpss_service' );
		$published = $total->publish ?? 0;

		// Get category counts.
		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => false,
			)
		);

		// Get featured count.
		$featured = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = '_wpss_featured' AND meta_value = '1'"
		);

		// Get average rating.
		$avg_rating = $wpdb->get_var(
			"SELECT AVG(meta_value) FROM {$wpdb->postmeta}
			WHERE meta_key = '_wpss_rating_average' AND meta_value > 0"
		);

		// Get total orders.
		$total_orders = $wpdb->get_var(
			"SELECT SUM(meta_value) FROM {$wpdb->postmeta}
			WHERE meta_key = '_wpss_order_count'"
		);

		WP_CLI::log( '' );
		WP_CLI::log( '=== WP Sell Services Statistics ===' );
		WP_CLI::log( '' );
		WP_CLI::log( "Total Services:    {$published}" );
		WP_CLI::log( "Featured:          {$featured}" );
		WP_CLI::log( "Categories:        " . count( $categories ) );
		WP_CLI::log( "Average Rating:    " . round( (float) $avg_rating, 2 ) );
		WP_CLI::log( "Total Orders:      " . ( $total_orders ?: 0 ) );
		WP_CLI::log( '' );

		if ( ! empty( $categories ) ) {
			WP_CLI::log( 'Services by Category:' );
			foreach ( $categories as $cat ) {
				WP_CLI::log( "  - {$cat->name}: {$cat->count}" );
			}
			WP_CLI::log( '' );
		}
	}

	/**
	 * Create a single service from data.
	 *
	 * @param array $data Service data.
	 * @return int|\WP_Error Post ID or error.
	 */
	private function create_service( array $data ) {
		// Create post.
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wpss_service',
				'post_title'   => $data['title'],
				'post_content' => $data['content'],
				'post_excerpt' => $data['excerpt'] ?? '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ?: 1,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Assign category.
		$cat = get_term_by( 'name', $data['category'], 'wpss_service_category' );
		if ( $cat ) {
			wp_set_object_terms( $post_id, $cat->term_id, 'wpss_service_category' );
		}

		// Assign tags.
		if ( ! empty( $data['tags'] ) ) {
			wp_set_object_terms( $post_id, $data['tags'], 'wpss_service_tag' );
		}

		// Save packages and compute derived values.
		if ( ! empty( $data['packages'] ) ) {
			update_post_meta( $post_id, '_wpss_packages', $data['packages'] );

			$prices    = wp_list_pluck( $data['packages'], 'price' );
			$delivery  = wp_list_pluck( $data['packages'], 'delivery_days' );
			$revisions = wp_list_pluck( $data['packages'], 'revisions' );

			update_post_meta( $post_id, '_wpss_starting_price', min( $prices ) );
			update_post_meta( $post_id, '_wpss_fastest_delivery', min( $delivery ) );
			update_post_meta( $post_id, '_wpss_max_revisions', max( $revisions ) );
		}

		// Save other meta.
		if ( ! empty( $data['faqs'] ) ) {
			update_post_meta( $post_id, '_wpss_faqs', $data['faqs'] );
		}
		if ( ! empty( $data['requirements'] ) ) {
			update_post_meta( $post_id, '_wpss_requirements', $data['requirements'] );
		}
		if ( ! empty( $data['addons'] ) ) {
			update_post_meta( $post_id, '_wpss_addons', $data['addons'] );
		}

		// Save stats.
		if ( ! empty( $data['stats'] ) ) {
			update_post_meta( $post_id, '_wpss_view_count', $data['stats']['views'] );
			update_post_meta( $post_id, '_wpss_order_count', $data['stats']['orders'] );
			update_post_meta( $post_id, '_wpss_rating_average', $data['stats']['rating'] );
			update_post_meta( $post_id, '_wpss_rating_count', $data['stats']['reviews'] );
			update_post_meta( $post_id, '_wpss_review_count', $data['stats']['reviews'] );
		}

		// Set featured.
		if ( ! empty( $data['featured'] ) ) {
			update_post_meta( $post_id, '_wpss_featured', 1 );
		}

		return $post_id;
	}

	/**
	 * Apply variation to template for unique services.
	 *
	 * @param array $template Base template.
	 * @param int   $variation Variation index.
	 * @return array Modified template.
	 */
	private function apply_variation( array $template, int $variation ): array {
		if ( 0 === $variation ) {
			return $template;
		}

		$prefixes = array(
			'I will professionally',
			'I will expertly',
			'I will quickly',
			'I will affordably',
			'I will creatively',
		);

		$suffixes = array(
			' for your success',
			' that converts',
			' with fast delivery',
			' guaranteed satisfaction',
			' with premium quality',
		);

		// Modify title.
		$prefix_idx = $variation % count( $prefixes );
		$suffix_idx = $variation % count( $suffixes );

		$title = $template['title'];
		$title = preg_replace( '/^I will /', $prefixes[ $prefix_idx ] . ' ', $title );
		$title .= $suffixes[ $suffix_idx ];

		$template['title'] = $title;

		// Vary stats.
		if ( ! empty( $template['stats'] ) ) {
			$multiplier = 0.5 + ( $variation * 0.3 );
			$template['stats']['views']   = (int) ( $template['stats']['views'] * $multiplier );
			$template['stats']['orders']  = (int) ( $template['stats']['orders'] * $multiplier );
			$template['stats']['reviews'] = (int) ( $template['stats']['reviews'] * $multiplier );
			$template['stats']['rating']  = min( 5.0, max( 4.0, $template['stats']['rating'] + ( ( $variation % 3 ) * 0.1 - 0.1 ) ) );
		}

		// Vary prices.
		if ( ! empty( $template['packages'] ) ) {
			$price_mult = 1 + ( $variation * 0.15 );
			foreach ( $template['packages'] as &$pkg ) {
				$pkg['price'] = (int) round( $pkg['price'] * $price_mult );
			}
		}

		return $template;
	}

	/**
	 * Regenerate computed meta values for all services.
	 *
	 * Recalculates _wpss_starting_price, _wpss_fastest_delivery, and
	 * _wpss_max_revisions from package data.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss service regenerate-meta
	 *
	 * @subcommand regenerate-meta
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function regenerate_meta( array $args, array $assoc_args ): void {
		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		$count = count( $services );
		if ( 0 === $count ) {
			WP_CLI::log( 'No services found.' );
			return;
		}

		WP_CLI::log( "Regenerating meta for {$count} services..." );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing', $count );
		$updated  = 0;

		foreach ( $services as $post_id ) {
			$packages = get_post_meta( $post_id, '_wpss_packages', true );

			if ( ! empty( $packages ) && is_array( $packages ) ) {
				$prices    = array_filter( wp_list_pluck( $packages, 'price' ) );
				$delivery  = array_filter( wp_list_pluck( $packages, 'delivery_days' ) );
				$revisions = wp_list_pluck( $packages, 'revisions' );

				if ( ! empty( $prices ) ) {
					update_post_meta( $post_id, '_wpss_starting_price', min( $prices ) );
				}
				if ( ! empty( $delivery ) ) {
					update_post_meta( $post_id, '_wpss_fastest_delivery', min( $delivery ) );
				}
				if ( ! empty( $revisions ) ) {
					update_post_meta( $post_id, '_wpss_max_revisions', max( $revisions ) );
				}

				$updated++;
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( "Updated meta for {$updated} services." );
	}

	/**
	 * Get service templates for demo data.
	 *
	 * @return array Service templates.
	 */
	private function get_service_templates(): array {
		return array(
			// Graphics & Design.
			array(
				'title'    => 'I will design a stunning minimalist logo for your brand',
				'content'  => 'Get a professional, modern logo that captures your brand essence. I specialize in minimalist designs that are memorable, scalable, and perfect for all platforms.',
				'excerpt'  => 'Professional minimalist logo design with unlimited revisions.',
				'category' => 'Graphics & Design',
				'tags'     => array( 'logo design', 'minimalist', 'branding' ),
				'packages' => array(
					array( 'name' => 'Basic', 'description' => '1 concept, PNG format', 'price' => 25, 'delivery_days' => 3, 'revisions' => 3 ),
					array( 'name' => 'Standard', 'description' => '3 concepts, all formats', 'price' => 75, 'delivery_days' => 5, 'revisions' => -1 ),
					array( 'name' => 'Premium', 'description' => '5 concepts + brand guide', 'price' => 150, 'delivery_days' => 7, 'revisions' => -1 ),
				),
				'faqs'     => array(
					array( 'question' => 'What formats will I receive?', 'answer' => 'AI, EPS, PDF, PNG, and JPG files.' ),
				),
				'stats'    => array( 'views' => 2847, 'orders' => 156, 'rating' => 4.9, 'reviews' => 89 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will create social media graphics and posts',
				'content'  => 'Stand out on social media with scroll-stopping graphics for Instagram, Facebook, LinkedIn, and more.',
				'excerpt'  => 'Custom social media graphics that boost engagement.',
				'category' => 'Graphics & Design',
				'tags'     => array( 'social media', 'instagram', 'graphics' ),
				'packages' => array(
					array( 'name' => 'Starter', 'description' => '5 posts for 1 platform', 'price' => 30, 'delivery_days' => 2, 'revisions' => 2 ),
					array( 'name' => 'Growth', 'description' => '15 posts for 2 platforms', 'price' => 80, 'delivery_days' => 4, 'revisions' => 3 ),
					array( 'name' => 'Business', 'description' => '30 posts + calendar', 'price' => 180, 'delivery_days' => 7, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 1523, 'orders' => 78, 'rating' => 4.8, 'reviews' => 45 ),
				'featured' => false,
			),
			array(
				'title'    => 'I will design UI/UX for your mobile app',
				'content'  => 'Get a user-friendly, beautiful interface for your digital product in Figma.',
				'excerpt'  => 'Professional UI/UX design for apps and websites.',
				'category' => 'Graphics & Design',
				'tags'     => array( 'UI design', 'UX', 'figma', 'mobile app' ),
				'packages' => array(
					array( 'name' => 'Basic', 'description' => '3 screens', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Standard', 'description' => '8 screens + prototype', 'price' => 350, 'delivery_days' => 7, 'revisions' => 3 ),
					array( 'name' => 'Premium', 'description' => 'Full app + design system', 'price' => 800, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 2345, 'orders' => 56, 'rating' => 4.9, 'reviews' => 34 ),
				'featured' => true,
			),

			// Digital Marketing.
			array(
				'title'    => 'I will create a complete SEO strategy',
				'content'  => 'Dominate search results with a data-driven SEO strategy including keyword research and competitor analysis.',
				'excerpt'  => 'Complete SEO audit and strategy to improve rankings.',
				'category' => 'Digital Marketing',
				'tags'     => array( 'SEO', 'keyword research', 'google ranking' ),
				'packages' => array(
					array( 'name' => 'Audit', 'description' => 'Technical SEO audit', 'price' => 50, 'delivery_days' => 3, 'revisions' => 1 ),
					array( 'name' => 'Strategy', 'description' => 'Audit + keyword research', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Enterprise', 'description' => 'Complete roadmap', 'price' => 400, 'delivery_days' => 10, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 3421, 'orders' => 198, 'rating' => 4.9, 'reviews' => 112 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will manage your Google Ads campaigns',
				'content'  => 'Get more leads with expertly managed Google Ads campaigns and conversion tracking.',
				'excerpt'  => 'Expert Google Ads management for maximum ROI.',
				'category' => 'Digital Marketing',
				'tags'     => array( 'google ads', 'PPC', 'advertising' ),
				'packages' => array(
					array( 'name' => 'Setup', 'description' => 'Campaign setup', 'price' => 100, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Monthly', 'description' => '1 month management', 'price' => 300, 'delivery_days' => 30, 'revisions' => -1 ),
					array( 'name' => 'Quarterly', 'description' => '3 months + tracking', 'price' => 800, 'delivery_days' => 90, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 1876, 'orders' => 67, 'rating' => 4.7, 'reviews' => 38 ),
				'featured' => false,
			),

			// Programming & Tech.
			array(
				'title'    => 'I will build a responsive WordPress website',
				'content'  => 'Get a professional, fast-loading WordPress website that looks amazing on all devices.',
				'excerpt'  => 'Custom WordPress website design and development.',
				'category' => 'Programming & Tech',
				'tags'     => array( 'wordpress', 'web development', 'responsive' ),
				'packages' => array(
					array( 'name' => 'Landing', 'description' => 'Single page website', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Business', 'description' => '5-page site + blog', 'price' => 400, 'delivery_days' => 10, 'revisions' => 3 ),
					array( 'name' => 'E-commerce', 'description' => 'WooCommerce store', 'price' => 800, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 4521, 'orders' => 234, 'rating' => 4.9, 'reviews' => 156 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will fix bugs in your WordPress website',
				'content'  => 'Having WordPress problems? I\'ll diagnose and fix any issues quickly.',
				'excerpt'  => 'Expert WordPress troubleshooting and bug fixing.',
				'category' => 'Programming & Tech',
				'tags'     => array( 'wordpress', 'bug fix', 'troubleshooting' ),
				'packages' => array(
					array( 'name' => 'Quick Fix', 'description' => 'Fix 1 issue', 'price' => 30, 'delivery_days' => 1, 'revisions' => 1 ),
					array( 'name' => 'Full Debug', 'description' => 'Fix up to 5 issues', 'price' => 80, 'delivery_days' => 2, 'revisions' => 2 ),
					array( 'name' => 'Site Rescue', 'description' => 'Complete recovery', 'price' => 200, 'delivery_days' => 3, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 3287, 'orders' => 312, 'rating' => 4.8, 'reviews' => 198 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will develop a React or Next.js application',
				'content'  => 'Build powerful web applications with React or Next.js using modern best practices.',
				'excerpt'  => 'Custom React/Next.js development.',
				'category' => 'Programming & Tech',
				'tags'     => array( 'react', 'nextjs', 'javascript' ),
				'packages' => array(
					array( 'name' => 'Component', 'description' => 'Single component', 'price' => 100, 'delivery_days' => 3, 'revisions' => 2 ),
					array( 'name' => 'Module', 'description' => 'Feature module', 'price' => 350, 'delivery_days' => 7, 'revisions' => 3 ),
					array( 'name' => 'Full App', 'description' => 'Complete application', 'price' => 1500, 'delivery_days' => 21, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 1654, 'orders' => 45, 'rating' => 5.0, 'reviews' => 28 ),
				'featured' => false,
			),

			// Video & Animation.
			array(
				'title'    => 'I will edit your YouTube videos professionally',
				'content'  => 'Make your videos stand out with professional editing, transitions, and effects.',
				'excerpt'  => 'Professional YouTube video editing.',
				'category' => 'Video & Animation',
				'tags'     => array( 'video editing', 'youtube', 'content creator' ),
				'packages' => array(
					array( 'name' => 'Basic', 'description' => 'Up to 10 min', 'price' => 50, 'delivery_days' => 3, 'revisions' => 1 ),
					array( 'name' => 'Standard', 'description' => 'Up to 20 min + graphics', 'price' => 120, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Premium', 'description' => 'Up to 30 min + thumbnails', 'price' => 250, 'delivery_days' => 7, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 2876, 'orders' => 178, 'rating' => 4.8, 'reviews' => 95 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will create a 2D animated explainer video',
				'content'  => 'Explain your product with an engaging animated video that captures attention.',
				'excerpt'  => 'Custom 2D animated explainer videos.',
				'category' => 'Video & Animation',
				'tags'     => array( 'animation', 'explainer video', 'motion graphics' ),
				'packages' => array(
					array( 'name' => '30 Sec', 'description' => '30-second video', 'price' => 150, 'delivery_days' => 7, 'revisions' => 2 ),
					array( 'name' => '60 Sec', 'description' => '1-minute video', 'price' => 280, 'delivery_days' => 10, 'revisions' => 3 ),
					array( 'name' => '90 Sec', 'description' => '90-second premium', 'price' => 450, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 1543, 'orders' => 67, 'rating' => 4.9, 'reviews' => 41 ),
				'featured' => false,
			),

			// Writing & Translation.
			array(
				'title'    => 'I will write SEO-optimized blog articles',
				'content'  => 'Get high-quality, researched content that drives organic traffic.',
				'excerpt'  => 'SEO-optimized blog articles and content writing.',
				'category' => 'Writing & Translation',
				'tags'     => array( 'blog writing', 'SEO content', 'copywriting' ),
				'packages' => array(
					array( 'name' => '500 Words', 'description' => '500-word article', 'price' => 25, 'delivery_days' => 2, 'revisions' => 1 ),
					array( 'name' => '1000 Words', 'description' => '1000-word + images', 'price' => 50, 'delivery_days' => 3, 'revisions' => 2 ),
					array( 'name' => '2000 Words', 'description' => 'Long-form content', 'price' => 100, 'delivery_days' => 5, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 3654, 'orders' => 287, 'rating' => 4.9, 'reviews' => 176 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will translate English to Spanish',
				'content'  => 'Professional translation by a native speaker for all content types.',
				'excerpt'  => 'Native Spanish translation services.',
				'category' => 'Writing & Translation',
				'tags'     => array( 'translation', 'spanish', 'localization' ),
				'packages' => array(
					array( 'name' => 'Basic', 'description' => 'Up to 500 words', 'price' => 20, 'delivery_days' => 1, 'revisions' => 1 ),
					array( 'name' => 'Standard', 'description' => 'Up to 2000 words', 'price' => 60, 'delivery_days' => 3, 'revisions' => 2 ),
					array( 'name' => 'Premium', 'description' => 'Up to 5000 words', 'price' => 120, 'delivery_days' => 5, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 1234, 'orders' => 89, 'rating' => 5.0, 'reviews' => 56 ),
				'featured' => false,
			),

			// Business.
			array(
				'title'    => 'I will be your virtual assistant',
				'content'  => 'Free up your time with reliable admin support for emails, scheduling, and research.',
				'excerpt'  => 'Professional virtual assistant for admin needs.',
				'category' => 'Business',
				'tags'     => array( 'virtual assistant', 'admin support', 'data entry' ),
				'packages' => array(
					array( 'name' => '5 Hours', 'description' => '5 hours VA support', 'price' => 50, 'delivery_days' => 7, 'revisions' => 0 ),
					array( 'name' => '10 Hours', 'description' => '10 hours + priority', 'price' => 90, 'delivery_days' => 7, 'revisions' => 0 ),
					array( 'name' => '20 Hours', 'description' => '20 hours + reports', 'price' => 160, 'delivery_days' => 14, 'revisions' => 0 ),
				),
				'stats'    => array( 'views' => 2134, 'orders' => 156, 'rating' => 4.9, 'reviews' => 98 ),
				'featured' => true,
			),
			array(
				'title'    => 'I will create a professional business plan',
				'content'  => 'Get a comprehensive business plan for investors with market analysis and projections.',
				'excerpt'  => 'Professional business plan writing.',
				'category' => 'Business',
				'tags'     => array( 'business plan', 'startup', 'investor' ),
				'packages' => array(
					array( 'name' => 'Lean', 'description' => '10-page lean plan', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Standard', 'description' => '25-page + financials', 'price' => 350, 'delivery_days' => 10, 'revisions' => 3 ),
					array( 'name' => 'Investor', 'description' => '40+ pages + deck', 'price' => 700, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'stats'    => array( 'views' => 1456, 'orders' => 45, 'rating' => 4.7, 'reviews' => 28 ),
				'featured' => false,
			),
		);
	}
}

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'wpss demo', ServiceCommands::class, array( 'shortdesc' => 'Manage demo services.' ) );
	WP_CLI::add_command( 'wpss service', ServiceCommands::class, array( 'shortdesc' => 'Manage services.' ) );
}
