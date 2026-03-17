<?php
/**
 * WP-CLI Commands for WP Sell Services
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

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

			// Insert actual rows into wpss_reviews so the table matches post meta.
			if ( $data['stats']['reviews'] > 0 ) {
				$this->insert_demo_reviews(
					$post_id,
					(int) get_post_field( 'post_author', $post_id ),
					(float) $data['stats']['rating'],
					(int) $data['stats']['reviews']
				);
			}
		}

		// Set featured.
		if ( ! empty( $data['featured'] ) ) {
			update_post_meta( $post_id, '_wpss_featured', 1 );
		}

		return $post_id;
	}

	/**
	 * Insert demo review rows into the wpss_reviews table.
	 *
	 * Generates a rating distribution that matches the target average and spreads
	 * the reviews over the past year so the timestamps look realistic.
	 *
	 * @param int   $service_id Service post ID.
	 * @param int   $vendor_id  Vendor (post author) user ID.
	 * @param float $target_avg Target average rating (e.g. 4.9).
	 * @param int   $count      Number of reviews to insert.
	 * @return void
	 */
	private function insert_demo_reviews( int $service_id, int $vendor_id, float $target_avg, int $count ): void {
		global $wpdb;

		$reviews_table = $wpdb->prefix . 'wpss_reviews';
		$ratings       = $this->generate_rating_distribution( $target_avg, $count );
		$review_texts  = array(
			'Excellent work! Delivered exactly what I needed and the quality was outstanding.',
			'Very professional and communicative. Would definitely hire again.',
			'Great service, fast delivery, and the results exceeded my expectations.',
			'Top-notch quality. The attention to detail was impressive.',
			'Super fast turnaround and the work was exactly as described.',
			'Amazing quality! I have used this service multiple times and am always satisfied.',
			'Delivered on time with great results. Highly recommended!',
			'Fantastic experience from start to finish. The work is incredible.',
			'Outstanding quality and very responsive. Could not be happier.',
			'Very pleased with the final result. Professional and talented.',
			'Exactly what I was looking for! Will order again for sure.',
			'Great communication and delivered a top-quality product.',
			'Absolutely love the work done. Very creative and professional.',
			'Really impressed with the level of detail and professionalism.',
			'Wonderful experience. The results are beyond what I expected.',
			'Very skilled and talented. The work speaks for itself.',
			'Highly recommend this service. Delivered everything promised and more.',
			'Perfect execution. I am very happy with the results.',
			'Great value for the price. The quality far exceeded my expectations.',
			'Could not be more satisfied. Will definitely be back for more.',
		);

		$total = count( $ratings );

		foreach ( $ratings as $i => $rating ) {
			$days_ago   = (int) round( ( $i / $total ) * 365 );
			$created_at = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) );

			$wpdb->insert(
				$reviews_table,
				array(
					'order_id'    => 0,
					'reviewer_id' => 1,
					'reviewee_id' => $vendor_id,
					'service_id'  => $service_id,
					'customer_id' => 1,
					'vendor_id'   => $vendor_id,
					'rating'      => $rating,
					'review'      => $review_texts[ $i % count( $review_texts ) ],
					'status'      => 'approved',
					'created_at'  => $created_at,
				),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Generate individual rating values whose average equals the target.
	 *
	 * Works for averages in the 4.0–5.0 range. All non-5-star reviews are
	 * treated as 4-star reviews for simplicity.
	 *
	 * @param float $target_avg Target average rating.
	 * @param int   $count      Total number of ratings.
	 * @return int[] Array of individual rating integers.
	 */
	private function generate_rating_distribution( float $target_avg, int $count ): array {
		if ( $count <= 0 ) {
			return array();
		}

		$total_needed = (int) round( $target_avg * $count );
		$fives        = $total_needed - ( 4 * $count ); // from: 5x + 4(n-x) = total.
		$fives        = max( 0, min( $count, $fives ) );
		$fours        = $count - $fives;

		$ratings = array_merge(
			array_fill( 0, $fives, 5 ),
			array_fill( 0, $fours, 4 )
		);

		shuffle( $ratings );
		return $ratings;
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
				'title'        => 'I will design a stunning minimalist logo for your brand',
				'content'      => 'Get a professional, modern logo that captures your brand essence. I specialize in minimalist designs that are memorable, scalable, and perfect for all platforms. With 8+ years of experience, I have helped 500+ businesses establish their visual identity.',
				'excerpt'      => 'Professional minimalist logo design with unlimited revisions.',
				'category'     => 'Graphics & Design',
				'tags'         => array( 'logo design', 'minimalist', 'branding', 'business logo' ),
				'packages'     => array(
					array( 'name' => 'Basic', 'description' => '1 logo concept, PNG format, 3 revisions', 'price' => 25, 'delivery_days' => 3, 'revisions' => 3 ),
					array( 'name' => 'Standard', 'description' => '3 logo concepts, all formats (AI, EPS, PDF, PNG, JPG), unlimited revisions', 'price' => 75, 'delivery_days' => 5, 'revisions' => -1 ),
					array( 'name' => 'Premium', 'description' => '5 concepts + brand guidelines + social media kit + stationery mockups', 'price' => 150, 'delivery_days' => 7, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'What file formats will I receive?', 'answer' => 'You will receive AI, EPS, PDF, PNG (transparent), and JPG files suitable for both print and web use.' ),
					array( 'question' => 'Can I request revisions?', 'answer' => 'Absolutely! Revisions are included based on your package. Basic includes 3 revisions, Standard and Premium include unlimited revisions.' ),
					array( 'question' => 'How do I provide feedback?', 'answer' => 'I will share initial concepts via the order page. You can leave detailed feedback and I will revise accordingly.' ),
				),
				'requirements' => array(
					array( 'question' => 'What is your business name and industry?', 'type' => 'text', 'required' => true ),
					array( 'question' => 'Do you have any color preferences or brand colors?', 'type' => 'textarea', 'required' => false ),
					array( 'question' => 'Share any reference logos or styles you like', 'type' => 'file', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Express 24-hour delivery', 'description' => 'Get your logo concepts within 24 hours', 'price' => 30, 'delivery_days_extra' => -2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Social media kit', 'description' => 'Optimized logo versions for all social platforms (FB, IG, Twitter, LinkedIn)', 'price' => 25, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
					array( 'title' => 'Brand guidelines PDF', 'description' => 'Document with logo usage rules, colors, and typography', 'price' => 40, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 2847, 'orders' => 156, 'rating' => 4.9, 'reviews' => 89 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will create eye-catching social media graphics and posts',
				'content'      => 'Stand out on social media with scroll-stopping graphics! I create custom designs for Instagram, Facebook, LinkedIn, Twitter, and Pinterest that match your brand and engage your audience.',
				'excerpt'      => 'Custom social media graphics that boost engagement and grow your following.',
				'category'     => 'Graphics & Design',
				'tags'         => array( 'social media', 'instagram', 'facebook', 'graphics', 'posts' ),
				'packages'     => array(
					array( 'name' => 'Starter', 'description' => '5 custom posts for 1 platform, PNG format', 'price' => 30, 'delivery_days' => 2, 'revisions' => 2 ),
					array( 'name' => 'Growth', 'description' => '15 posts for 2 platforms + 5 stories templates', 'price' => 80, 'delivery_days' => 4, 'revisions' => 3 ),
					array( 'name' => 'Business', 'description' => '30 posts for all platforms + content calendar + reels covers', 'price' => 180, 'delivery_days' => 7, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Which platforms do you design for?', 'answer' => 'Instagram, Facebook, Twitter/X, LinkedIn, Pinterest, and TikTok. I optimize sizes for each platform.' ),
					array( 'question' => 'Can I edit the files myself later?', 'answer' => 'Yes! I provide editable Canva templates or source files upon request.' ),
				),
				'requirements' => array(
					array( 'question' => 'Share your brand guidelines or logo', 'type' => 'file', 'required' => true ),
					array( 'question' => 'What is the main goal of these posts? (awareness, sales, engagement)', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Any specific topics or themes to cover?', 'type' => 'textarea', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Animated posts (GIF)', 'description' => '5 animated versions of your posts', 'price' => 35, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Hashtag research', 'description' => 'Curated hashtag sets for maximum reach', 'price' => 15, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 1523, 'orders' => 78, 'rating' => 4.8, 'reviews' => 45 ),
				'featured'     => false,
			),
			array(
				'title'        => 'I will design professional UI/UX for your mobile app or website',
				'content'      => 'Get a user-friendly, beautiful interface for your digital product. I create intuitive designs in Figma that delight users and boost conversions. Includes user flows, wireframes, and high-fidelity mockups.',
				'excerpt'      => 'Professional UI/UX design for apps and websites in Figma.',
				'category'     => 'Graphics & Design',
				'tags'         => array( 'UI design', 'UX design', 'figma', 'mobile app', 'web design' ),
				'packages'     => array(
					array( 'name' => 'Basic', 'description' => '3 screens with components, mobile or web', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Standard', 'description' => '8 screens with interactive Figma prototype', 'price' => 350, 'delivery_days' => 7, 'revisions' => 3 ),
					array( 'name' => 'Premium', 'description' => 'Full app design (15+ screens) with design system and developer handoff', 'price' => 800, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'What tools do you use?', 'answer' => 'I design in Figma and can export to any format. I also provide Zeplin or direct Figma developer access.' ),
					array( 'question' => 'Do you provide the source files?', 'answer' => 'Yes, you get full ownership of the Figma file with organized layers and components.' ),
					array( 'question' => 'Can you work with my existing brand?', 'answer' => 'Absolutely! I will follow your brand guidelines or help create a visual style if you do not have one.' ),
				),
				'requirements' => array(
					array( 'question' => 'Describe your app or website idea', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Who is your target audience?', 'type' => 'text', 'required' => true ),
					array( 'question' => 'Share any wireframes or reference designs', 'type' => 'file', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'User flow diagrams', 'description' => 'Complete user journey mapping', 'price' => 50, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
					array( 'title' => 'Responsive web + mobile', 'description' => 'Design for both desktop and mobile', 'price' => 100, 'delivery_days_extra' => 3, 'field_type' => 'checkbox' ),
					array( 'title' => 'Design system', 'description' => 'Reusable component library in Figma', 'price' => 150, 'delivery_days_extra' => 3, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 2345, 'orders' => 56, 'rating' => 4.9, 'reviews' => 34 ),
				'featured'     => true,
			),

			// Digital Marketing.
			array(
				'title'        => 'I will create a complete SEO strategy to boost your rankings',
				'content'      => 'Dominate search results with a data-driven SEO strategy. I provide comprehensive keyword research, competitor analysis, on-page optimization, and a detailed action plan to improve your organic visibility and drive qualified traffic.',
				'excerpt'      => 'Complete SEO audit and strategy to improve your Google rankings.',
				'category'     => 'Digital Marketing',
				'tags'         => array( 'SEO', 'keyword research', 'google ranking', 'organic traffic' ),
				'packages'     => array(
					array( 'name' => 'SEO Audit', 'description' => 'Technical SEO audit with prioritized recommendations report', 'price' => 50, 'delivery_days' => 3, 'revisions' => 1 ),
					array( 'name' => 'Full Strategy', 'description' => 'Audit + keyword research (50 keywords) + 3-month content plan', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Enterprise', 'description' => 'Complete SEO roadmap + competitor analysis + monthly consultation call', 'price' => 400, 'delivery_days' => 10, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'How long until I see results?', 'answer' => 'SEO is a long-term strategy. You will typically see improvements in 3-6 months, with significant results in 6-12 months.' ),
					array( 'question' => 'Do you guarantee first page rankings?', 'answer' => 'No one can guarantee rankings as Google controls the algorithm. However, my strategies consistently improve visibility and traffic.' ),
					array( 'question' => 'What tools do you use?', 'answer' => 'I use Ahrefs, SEMrush, Screaming Frog, Google Search Console, and Google Analytics for comprehensive analysis.' ),
				),
				'requirements' => array(
					array( 'question' => 'What is your website URL?', 'type' => 'text', 'required' => true ),
					array( 'question' => 'Who are your main competitors? (List 2-3 URLs)', 'type' => 'textarea', 'required' => false ),
					array( 'question' => 'What are your target keywords or topics?', 'type' => 'textarea', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Competitor backlink analysis', 'description' => 'Deep dive into competitor link profiles with opportunities', 'price' => 75, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Local SEO optimization', 'description' => 'Google Business Profile setup and local citations', 'price' => 50, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Monthly reporting', 'description' => 'Track rankings and traffic for 3 months', 'price' => 100, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 3421, 'orders' => 198, 'rating' => 4.9, 'reviews' => 112 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will manage your Google Ads campaigns for maximum ROI',
				'content'      => 'Get more leads and sales with expertly managed Google Ads campaigns. I handle everything from keyword research to ad copy, bidding optimization, and conversion tracking. Certified Google Ads specialist with 5+ years experience.',
				'excerpt'      => 'Expert Google Ads management to maximize your advertising ROI.',
				'category'     => 'Digital Marketing',
				'tags'         => array( 'google ads', 'PPC', 'paid advertising', 'lead generation' ),
				'packages'     => array(
					array( 'name' => 'Setup', 'description' => 'Campaign setup with keyword research, ad groups, and conversion tracking', 'price' => 100, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Monthly', 'description' => 'Full month campaign management with weekly optimization and reporting', 'price' => 300, 'delivery_days' => 30, 'revisions' => -1 ),
					array( 'name' => 'Quarterly', 'description' => '3 months management with A/B testing, remarketing, and advanced tracking', 'price' => 800, 'delivery_days' => 90, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Is the ad spend included?', 'answer' => 'No, this covers management only. Ad spend is paid directly to Google. I recommend a minimum of $500/month ad budget.' ),
					array( 'question' => 'What types of campaigns do you manage?', 'answer' => 'Search, Display, Shopping, YouTube, and Performance Max campaigns.' ),
					array( 'question' => 'How often will I receive reports?', 'answer' => 'Weekly performance updates and monthly detailed reports with insights and recommendations.' ),
				),
				'requirements' => array(
					array( 'question' => 'What is your monthly ad budget?', 'type' => 'text', 'required' => true ),
					array( 'question' => 'What product/service are you advertising?', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Do you have existing Google Ads account access?', 'type' => 'text', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Landing page design', 'description' => 'Custom high-converting landing page for your campaign', 'price' => 150, 'delivery_days_extra' => 3, 'field_type' => 'checkbox' ),
					array( 'title' => 'Conversion tracking setup', 'description' => 'Google Tag Manager and Analytics 4 configuration', 'price' => 50, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 1876, 'orders' => 67, 'rating' => 4.7, 'reviews' => 38 ),
				'featured'     => false,
			),

			// Programming & Tech.
			array(
				'title'        => 'I will build a modern responsive WordPress website',
				'content'      => 'Get a professional, fast-loading WordPress website that looks amazing on all devices. I use the latest themes and plugins to create secure, SEO-friendly sites that convert visitors into customers. Includes training on how to manage your site.',
				'excerpt'      => 'Custom WordPress website design and development with responsive design.',
				'category'     => 'Programming & Tech',
				'tags'         => array( 'wordpress', 'web development', 'responsive design', 'website' ),
				'packages'     => array(
					array( 'name' => 'Landing Page', 'description' => 'Single page website with contact form, mobile responsive', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Business Site', 'description' => '5-page website with blog, SEO setup, and contact forms', 'price' => 400, 'delivery_days' => 10, 'revisions' => 3 ),
					array( 'name' => 'E-commerce', 'description' => 'Full WooCommerce store with payment setup, up to 50 products', 'price' => 800, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Do I need to have hosting?', 'answer' => 'Yes, you will need hosting and a domain. I can recommend reliable options like SiteGround or Cloudways, or set it up for you.' ),
					array( 'question' => 'Will I be able to update the site myself?', 'answer' => 'Absolutely! I provide a video tutorial and documentation. WordPress is very user-friendly.' ),
					array( 'question' => 'Do you provide ongoing maintenance?', 'answer' => 'I offer optional maintenance packages. Otherwise, you can manage updates yourself or hire someone later.' ),
				),
				'requirements' => array(
					array( 'question' => 'What is the purpose of your website?', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Share any reference websites you like', 'type' => 'textarea', 'required' => false ),
					array( 'question' => 'Do you have a logo and brand colors?', 'type' => 'file', 'required' => false ),
					array( 'question' => 'Provide your hosting credentials (or let me know if you need hosting)', 'type' => 'text', 'required' => true ),
				),
				'addons'       => array(
					array( 'title' => 'Premium theme license', 'description' => 'Includes a premium theme ($59 value)', 'price' => 40, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
					array( 'title' => 'Speed optimization', 'description' => 'Advanced caching, image optimization, and performance tuning', 'price' => 50, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
					array( 'title' => 'SSL certificate setup', 'description' => 'Secure your site with HTTPS', 'price' => 20, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 4521, 'orders' => 234, 'rating' => 4.9, 'reviews' => 156 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will fix bugs and issues in your WordPress website',
				'content'      => 'Having WordPress problems? I will diagnose and fix any issues - from white screen of death to plugin conflicts, slow loading, security issues, and more. Fast turnaround with money-back guarantee if I cannot fix it.',
				'excerpt'      => 'Expert WordPress troubleshooting and bug fixing service.',
				'category'     => 'Programming & Tech',
				'tags'         => array( 'wordpress', 'bug fix', 'troubleshooting', 'maintenance' ),
				'packages'     => array(
					array( 'name' => 'Quick Fix', 'description' => 'Fix 1 specific issue or bug', 'price' => 30, 'delivery_days' => 1, 'revisions' => 1 ),
					array( 'name' => 'Full Debug', 'description' => 'Comprehensive site audit and fix up to 5 issues', 'price' => 80, 'delivery_days' => 2, 'revisions' => 2 ),
					array( 'name' => 'Site Rescue', 'description' => 'Complete site recovery, malware removal, and optimization', 'price' => 200, 'delivery_days' => 3, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'What if you cannot fix the issue?', 'answer' => 'Full refund if I cannot resolve your WordPress issue. I have a 99% success rate.' ),
					array( 'question' => 'Will you need admin access?', 'answer' => 'Yes, I will need wp-admin access and usually FTP/SFTP access as well.' ),
					array( 'question' => 'Do you take backups before making changes?', 'answer' => 'Always! I create a full backup before any work and can restore if needed.' ),
				),
				'requirements' => array(
					array( 'question' => 'Describe the issue you are experiencing', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Provide wp-admin login URL and credentials', 'type' => 'text', 'required' => true ),
					array( 'question' => 'When did the issue start? Any recent changes?', 'type' => 'textarea', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Priority support (start within 1 hour)', 'description' => 'Jump to front of queue', 'price' => 25, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
					array( 'title' => 'Security hardening', 'description' => 'Implement security best practices after fix', 'price' => 40, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 3287, 'orders' => 312, 'rating' => 4.8, 'reviews' => 198 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will develop a custom React or Next.js web application',
				'content'      => 'Build powerful, scalable web applications with React or Next.js. From dashboards to SaaS platforms, I deliver clean, maintainable code with modern best practices. Full-stack capabilities with Node.js backend.',
				'excerpt'      => 'Custom React/Next.js development for modern web applications.',
				'category'     => 'Programming & Tech',
				'tags'         => array( 'react', 'nextjs', 'javascript', 'web app', 'frontend' ),
				'packages'     => array(
					array( 'name' => 'Component', 'description' => 'Single React component or feature with tests', 'price' => 100, 'delivery_days' => 3, 'revisions' => 2 ),
					array( 'name' => 'Module', 'description' => 'Complete feature module with API integration and state management', 'price' => 350, 'delivery_days' => 7, 'revisions' => 3 ),
					array( 'name' => 'Full App', 'description' => 'Complete web application with authentication, database, and deployment', 'price' => 1500, 'delivery_days' => 21, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Do you provide the source code?', 'answer' => 'Yes, you receive full ownership of all source code via GitHub repository.' ),
					array( 'question' => 'What technologies do you use?', 'answer' => 'React/Next.js, TypeScript, Tailwind CSS, Node.js, PostgreSQL/MongoDB, and modern deployment tools.' ),
					array( 'question' => 'Can you integrate with existing backends?', 'answer' => 'Absolutely! I can work with any REST or GraphQL API.' ),
				),
				'requirements' => array(
					array( 'question' => 'Describe your project requirements in detail', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Do you have designs or wireframes?', 'type' => 'file', 'required' => false ),
					array( 'question' => 'What is your deadline?', 'type' => 'text', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'TypeScript implementation', 'description' => 'Full TypeScript with strict type safety', 'price' => 100, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Unit tests', 'description' => 'Jest/React Testing Library test coverage', 'price' => 150, 'delivery_days_extra' => 3, 'field_type' => 'checkbox' ),
					array( 'title' => 'CI/CD setup', 'description' => 'GitHub Actions deployment pipeline', 'price' => 75, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 1654, 'orders' => 45, 'rating' => 5.0, 'reviews' => 28 ),
				'featured'     => false,
			),

			// Video & Animation.
			array(
				'title'        => 'I will edit your YouTube videos professionally',
				'content'      => 'Make your YouTube videos stand out with professional editing. I add engaging intros, transitions, graphics, color grading, and sound design to keep viewers watching. Experienced with vlogs, tutorials, gaming, and business content.',
				'excerpt'      => 'Professional YouTube video editing with effects and optimization.',
				'category'     => 'Video & Animation',
				'tags'         => array( 'video editing', 'youtube', 'content creator', 'post production' ),
				'packages'     => array(
					array( 'name' => 'Basic', 'description' => 'Basic cuts, transitions, and music, up to 10 minutes', 'price' => 50, 'delivery_days' => 3, 'revisions' => 1 ),
					array( 'name' => 'Standard', 'description' => 'Full editing with graphics, text, and color grading, up to 20 minutes', 'price' => 120, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Premium', 'description' => 'Cinematic editing + custom thumbnails + end screens, up to 30 minutes', 'price' => 250, 'delivery_days' => 7, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'What footage format do you accept?', 'answer' => 'I work with all common formats including MP4, MOV, AVI, MKV, and RAW files from any camera.' ),
					array( 'question' => 'How do I send you the footage?', 'answer' => 'Google Drive, Dropbox, or WeTransfer links work best. I will provide a link if needed.' ),
					array( 'question' => 'Can you match my channel style?', 'answer' => 'Yes! Send me examples of your previous videos and I will match your brand style.' ),
				),
				'requirements' => array(
					array( 'question' => 'Share your raw footage via cloud link (Google Drive, Dropbox)', 'type' => 'text', 'required' => true ),
					array( 'question' => 'What type of video is this? (vlog, tutorial, review, etc.)', 'type' => 'text', 'required' => true ),
					array( 'question' => 'Any specific editing style or reference videos?', 'type' => 'textarea', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Custom thumbnail design', 'description' => 'Eye-catching thumbnail optimized for CTR', 'price' => 15, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
					array( 'title' => 'Rush delivery (24 hours)', 'description' => 'Get your video back within 24 hours', 'price' => 50, 'delivery_days_extra' => -2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Subtitles/Captions', 'description' => 'Hardcoded or SRT file for accessibility', 'price' => 25, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 2876, 'orders' => 178, 'rating' => 4.8, 'reviews' => 95 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will create an engaging 2D animated explainer video',
				'content'      => 'Explain your product or service with an engaging animated video. I create custom 2D animations that simplify complex ideas and capture attention. Perfect for websites, social media, and presentations.',
				'excerpt'      => 'Custom 2D animated explainer videos for your business.',
				'category'     => 'Video & Animation',
				'tags'         => array( 'animation', 'explainer video', '2D animation', 'motion graphics' ),
				'packages'     => array(
					array( 'name' => '30 Seconds', 'description' => '30-second animated video with professional voiceover', 'price' => 150, 'delivery_days' => 7, 'revisions' => 2 ),
					array( 'name' => '60 Seconds', 'description' => '1-minute video with custom illustrations and music', 'price' => 280, 'delivery_days' => 10, 'revisions' => 3 ),
					array( 'name' => '90 Seconds', 'description' => '90-second premium animation with storyboard and multiple revisions', 'price' => 450, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Is voiceover included?', 'answer' => 'Yes, professional voiceover in English is included. Other languages available for extra cost.' ),
					array( 'question' => 'Can I see the storyboard first?', 'answer' => 'Yes! I create a storyboard for approval before starting animation (Premium package).' ),
					array( 'question' => 'What style of animation do you do?', 'answer' => 'Modern flat design, whiteboard, character animation, and infographic styles.' ),
				),
				'requirements' => array(
					array( 'question' => 'Provide your script or key points to cover', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Share your logo and brand colors', 'type' => 'file', 'required' => true ),
					array( 'question' => 'Any reference videos or animation style you like?', 'type' => 'textarea', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Script writing', 'description' => 'Professional scriptwriting based on your brief', 'price' => 50, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Additional language voiceover', 'description' => 'Add another language version', 'price' => 40, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Source files', 'description' => 'After Effects project files', 'price' => 75, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 1543, 'orders' => 67, 'rating' => 4.9, 'reviews' => 41 ),
				'featured'     => false,
			),

			// Writing & Translation.
			array(
				'title'        => 'I will write SEO-optimized blog articles that rank on Google',
				'content'      => 'Get high-quality, researched blog content that drives organic traffic. I write engaging articles optimized for search engines with proper headings, keywords, and internal linking. All content is 100% original and human-written.',
				'excerpt'      => 'SEO-optimized blog articles and content writing service.',
				'category'     => 'Writing & Translation',
				'tags'         => array( 'blog writing', 'SEO content', 'copywriting', 'articles' ),
				'packages'     => array(
					array( 'name' => '500 Words', 'description' => '500-word SEO article with 1 target keyword and meta description', 'price' => 25, 'delivery_days' => 2, 'revisions' => 1 ),
					array( 'name' => '1000 Words', 'description' => '1000-word article with images suggestions and internal linking', 'price' => 50, 'delivery_days' => 3, 'revisions' => 2 ),
					array( 'name' => '2000 Words', 'description' => 'Long-form pillar content with research, sources, and FAQ schema', 'price' => 100, 'delivery_days' => 5, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Do you use AI to write?', 'answer' => 'All content is 100% human-written and original. I use AI only for research assistance.' ),
					array( 'question' => 'Can you match my brand voice?', 'answer' => 'Yes! Share examples of your existing content and I will match your style perfectly.' ),
					array( 'question' => 'Do you do keyword research?', 'answer' => 'I can work with your keywords or research optimal ones for an additional fee.' ),
				),
				'requirements' => array(
					array( 'question' => 'What topic should I write about?', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'Target keyword(s) if you have them', 'type' => 'text', 'required' => false ),
					array( 'question' => 'Link to your website or existing content', 'type' => 'text', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Keyword research', 'description' => 'Find the best keywords for your topic', 'price' => 20, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
					array( 'title' => 'Royalty-free images', 'description' => '3-5 relevant images included', 'price' => 10, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
					array( 'title' => 'WordPress publishing', 'description' => 'Upload and format in your WordPress site', 'price' => 15, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 3654, 'orders' => 287, 'rating' => 4.9, 'reviews' => 176 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will translate your content from English to Spanish',
				'content'      => 'Professional English to Spanish translation by a native speaker. I deliver accurate, culturally-adapted translations for websites, documents, marketing materials, legal texts, and more. Both Latin American and Castilian Spanish available.',
				'excerpt'      => 'Native Spanish translation services for all content types.',
				'category'     => 'Writing & Translation',
				'tags'         => array( 'translation', 'spanish', 'localization', 'language' ),
				'packages'     => array(
					array( 'name' => 'Basic', 'description' => 'Up to 500 words translation with proofreading', 'price' => 20, 'delivery_days' => 1, 'revisions' => 1 ),
					array( 'name' => 'Standard', 'description' => 'Up to 2000 words with glossary consistency', 'price' => 60, 'delivery_days' => 3, 'revisions' => 2 ),
					array( 'name' => 'Premium', 'description' => 'Up to 5000 words + localization + formatting preserved', 'price' => 120, 'delivery_days' => 5, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'Which Spanish dialect do you use?', 'answer' => 'I can adapt to Latin American (neutral) or Castilian (Spain) Spanish based on your target audience.' ),
					array( 'question' => 'Can you translate technical content?', 'answer' => 'Yes, I specialize in marketing, legal, medical, and technical translations.' ),
					array( 'question' => 'Do you offer certified translation?', 'answer' => 'Yes, certified translations for official documents are available as an add-on.' ),
				),
				'requirements' => array(
					array( 'question' => 'Upload your document or paste the text', 'type' => 'file', 'required' => true ),
					array( 'question' => 'Target audience region (Latin America or Spain)?', 'type' => 'text', 'required' => true ),
					array( 'question' => 'Any specific terminology or glossary to follow?', 'type' => 'file', 'required' => false ),
				),
				'addons'       => array(
					array( 'title' => 'Certified translation', 'description' => 'Official certification for legal/official documents', 'price' => 30, 'delivery_days_extra' => 1, 'field_type' => 'checkbox' ),
					array( 'title' => 'Rush delivery', 'description' => 'Same-day delivery for urgent needs', 'price' => 25, 'delivery_days_extra' => -1, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 1234, 'orders' => 89, 'rating' => 5.0, 'reviews' => 56 ),
				'featured'     => false,
			),

			// Business.
			array(
				'title'        => 'I will be your dedicated virtual assistant',
				'content'      => 'Free up your time with a reliable virtual assistant. I handle email management, calendar scheduling, data entry, research, travel booking, and administrative tasks efficiently. Proficient in all major productivity tools.',
				'excerpt'      => 'Professional virtual assistant for all your admin needs.',
				'category'     => 'Business',
				'tags'         => array( 'virtual assistant', 'admin support', 'data entry', 'scheduling' ),
				'packages'     => array(
					array( 'name' => '5 Hours', 'description' => '5 hours of VA support, task list management', 'price' => 50, 'delivery_days' => 7, 'revisions' => 0 ),
					array( 'name' => '10 Hours', 'description' => '10 hours with priority response time', 'price' => 90, 'delivery_days' => 7, 'revisions' => 0 ),
					array( 'name' => '20 Hours', 'description' => '20 hours + weekly progress reports + dedicated availability', 'price' => 160, 'delivery_days' => 14, 'revisions' => 0 ),
				),
				'faqs'         => array(
					array( 'question' => 'What tools do you use?', 'answer' => 'I am proficient in Google Workspace, Microsoft Office, Asana, Trello, Slack, Notion, Calendly, and more.' ),
					array( 'question' => 'What time zone do you work in?', 'answer' => 'I work EST hours but can adjust for overlap with your time zone.' ),
					array( 'question' => 'Is my data secure?', 'answer' => 'Absolutely. I sign NDAs and follow strict data protection practices.' ),
				),
				'requirements' => array(
					array( 'question' => 'What tasks do you need help with?', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'What tools do you currently use?', 'type' => 'text', 'required' => false ),
					array( 'question' => 'Preferred communication method (Slack, email, etc.)?', 'type' => 'text', 'required' => true ),
				),
				'addons'       => array(
					array( 'title' => 'Weekend availability', 'description' => 'Work on Saturday and Sunday', 'price' => 20, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
					array( 'title' => 'Phone/call handling', 'description' => 'Answer calls on your behalf', 'price' => 30, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 2134, 'orders' => 156, 'rating' => 4.9, 'reviews' => 98 ),
				'featured'     => true,
			),
			array(
				'title'        => 'I will create a professional business plan for investors',
				'content'      => 'Get a comprehensive business plan for investors, banks, or internal use. I create detailed plans with market analysis, financial projections, competitor analysis, and actionable strategies. MBA-level quality.',
				'excerpt'      => 'Professional business plan writing for startups and SMEs.',
				'category'     => 'Business',
				'tags'         => array( 'business plan', 'startup', 'investor', 'financial projections' ),
				'packages'     => array(
					array( 'name' => 'Lean Plan', 'description' => '10-page lean business plan with executive summary and basic financials', 'price' => 150, 'delivery_days' => 5, 'revisions' => 2 ),
					array( 'name' => 'Standard', 'description' => '25-page detailed plan with market research, 3-year projections', 'price' => 350, 'delivery_days' => 10, 'revisions' => 3 ),
					array( 'name' => 'Investor Ready', 'description' => '40+ page plan with pitch deck, financial model (Excel), and presentation', 'price' => 700, 'delivery_days' => 14, 'revisions' => -1 ),
				),
				'faqs'         => array(
					array( 'question' => 'What information do you need from me?', 'answer' => 'Basic details about your business idea, target market, revenue model, and goals. I provide a questionnaire.' ),
					array( 'question' => 'Will this work for bank loans?', 'answer' => 'Yes, my business plans follow SBA guidelines and are accepted by banks and investors.' ),
					array( 'question' => 'Do you sign NDAs?', 'answer' => 'Yes, I can sign your NDA before starting. Your idea is safe with me.' ),
				),
				'requirements' => array(
					array( 'question' => 'Describe your business idea and goals', 'type' => 'textarea', 'required' => true ),
					array( 'question' => 'What industry are you in?', 'type' => 'text', 'required' => true ),
					array( 'question' => 'What is the purpose of this plan? (investors, bank loan, internal)', 'type' => 'text', 'required' => true ),
				),
				'addons'       => array(
					array( 'title' => 'Pitch deck design', 'description' => '10-slide investor presentation in PowerPoint', 'price' => 100, 'delivery_days_extra' => 2, 'field_type' => 'checkbox' ),
					array( 'title' => 'Financial model (Excel)', 'description' => 'Interactive Excel with 5-year projections', 'price' => 150, 'delivery_days_extra' => 3, 'field_type' => 'checkbox' ),
					array( 'title' => 'Consultation call', 'description' => '30-minute strategy discussion', 'price' => 50, 'delivery_days_extra' => 0, 'field_type' => 'checkbox' ),
				),
				'stats'        => array( 'views' => 1456, 'orders' => 45, 'rating' => 4.7, 'reviews' => 28 ),
				'featured'     => false,
			),
		);
	}
}

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'wpss demo', ServiceCommands::class, array( 'shortdesc' => 'Manage demo services.' ) );
	WP_CLI::add_command( 'wpss service', ServiceCommands::class, array( 'shortdesc' => 'Manage services.' ) );
	WP_CLI::add_command( 'wpss validate', ValidateCommand::class, array( 'shortdesc' => 'Validate models and schema.' ) );
}
