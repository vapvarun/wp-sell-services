<?php
/**
 * Marketplace Demo Seeder
 *
 * Seeds a full, realistic WP Sell Services marketplace: vendors with profiles
 * and portfolios, buyers, services, orders across every order status, reviews,
 * buyer requests + proposals, conversations + messages, favorites, and
 * withdrawals.
 *
 * Written as a service that returns a structured summary array (never an inline
 * CLI script), so it can be driven from WP-CLI, REST, or a unit test. See the
 * wp-plugin-development skill, Part 1.0 ("write the logic as a service that
 * returns a structured array").
 *
 * @package WPSellServices\Demo
 * @since   1.1.2
 */

declare(strict_types=1);

namespace WPSellServices\Demo;

use WPSellServices\Models\BuyerRequest;
use WPSellServices\Models\Proposal;
use WPSellServices\Models\Review;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\VendorProfile;
use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Services\CommissionService;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\EarningsService;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds a complete demo marketplace using the plugin's existing models and
 * data tables.
 *
 * @since 1.1.2
 */
class MarketplaceSeeder {

	/**
	 * Vendor display-name pool. Each entry seeds one vendor with a profile.
	 *
	 * @var array<int, array{login: string, name: string, country: string, tier: string, tagline: string, skills: array<int, string>}>
	 */
	private array $vendor_blueprints = array(
		array(
			'login'   => 'wpss_vendor_maya',
			'name'    => 'Maya Chen',
			'country' => 'Singapore',
			'tier'    => VendorProfile::TIER_PRO,
			'tagline' => 'Brand & logo designer with 10+ years crafting identities',
			'skills'  => array( 'Logo Design', 'Branding', 'Illustration' ),
		),
		array(
			'login'   => 'wpss_vendor_diego',
			'name'    => 'Diego Alvarez',
			'country' => 'Spain',
			'tier'    => VendorProfile::TIER_TOP_RATED,
			'tagline' => 'Full-stack WordPress developer & plugin specialist',
			'skills'  => array( 'WordPress', 'PHP', 'REST API' ),
		),
		array(
			'login'   => 'wpss_vendor_aisha',
			'name'    => 'Aisha Khan',
			'country' => 'United Arab Emirates',
			'tier'    => VendorProfile::TIER_TOP_RATED,
			'tagline' => 'SEO strategist driving organic growth for SaaS brands',
			'skills'  => array( 'SEO', 'Content Strategy', 'Analytics' ),
		),
		array(
			'login'   => 'wpss_vendor_liam',
			'name'    => 'Liam O\'Connor',
			'country' => 'Ireland',
			'tier'    => VendorProfile::TIER_RISING,
			'tagline' => 'Motion designer & explainer-video producer',
			'skills'  => array( 'Animation', 'Video Editing', 'After Effects' ),
		),
		array(
			'login'   => 'wpss_vendor_yuki',
			'name'    => 'Yuki Tanaka',
			'country' => 'Japan',
			'tier'    => VendorProfile::TIER_RISING,
			'tagline' => 'Copywriter turning features into stories that sell',
			'skills'  => array( 'Copywriting', 'Editing', 'Localization' ),
		),
		array(
			'login'   => 'wpss_vendor_sofia',
			'name'    => 'Sofia Rossi',
			'country' => 'Italy',
			'tier'    => VendorProfile::TIER_NEW,
			'tagline' => 'UI/UX designer focused on conversion-first interfaces',
			'skills'  => array( 'UI Design', 'Figma', 'Prototyping' ),
		),
	);

	/**
	 * Buyer display-name pool.
	 *
	 * @var array<int, array{login: string, name: string}>
	 */
	private array $buyer_blueprints = array(
		array(
			'login' => 'wpss_buyer_noah',
			'name'  => 'Noah Williams',
		),
		array(
			'login' => 'wpss_buyer_emma',
			'name'  => 'Emma Johnson',
		),
		array(
			'login' => 'wpss_buyer_oliver',
			'name'  => 'Oliver Brown',
		),
		array(
			'login' => 'wpss_buyer_ava',
			'name'  => 'Ava Davis',
		),
		array(
			'login' => 'wpss_buyer_ethan',
			'name'  => 'Ethan Miller',
		),
		array(
			'login' => 'wpss_buyer_mia',
			'name'  => 'Mia Wilson',
		),
		array(
			'login' => 'wpss_buyer_lucas',
			'name'  => 'Lucas Moore',
		),
		array(
			'login' => 'wpss_buyer_amelia',
			'name'  => 'Amelia Taylor',
		),
		array(
			'login' => 'wpss_buyer_james',
			'name'  => 'James Anderson',
		),
		array(
			'login' => 'wpss_buyer_harper',
			'name'  => 'Harper Thomas',
		),
		array(
			'login' => 'wpss_buyer_ben',
			'name'  => 'Benjamin Lee',
		),
		array(
			'login' => 'wpss_buyer_ella',
			'name'  => 'Ella Martinez',
		),
	);

	/**
	 * Default service-category names the marketplace expects.
	 *
	 * @var array<int, string>
	 */
	private array $category_names = array(
		'Graphics & Design',
		'Programming & Tech',
		'Digital Marketing',
		'Video & Animation',
		'Writing & Translation',
	);

	/**
	 * Sample review bodies reused across seeded reviews.
	 *
	 * @var array<int, string>
	 */
	private array $review_bodies = array(
		'Excellent work, delivered ahead of schedule and exactly to brief.',
		'Communication was clear throughout and the quality exceeded expectations.',
		'Professional, fast, and easy to work with. Will hire again.',
		'Great attention to detail. Made the revisions without any fuss.',
		'Outstanding result. Highly recommended to anyone on the fence.',
		'Solid delivery. A couple of small tweaks needed but handled quickly.',
		'Top-tier talent. The final files were polished and well organized.',
		'Really happy with the outcome - captured the vision perfectly.',
	);

	/**
	 * Conversation message snippets (buyer-first ordering).
	 *
	 * @var array<int, string>
	 */
	private array $message_snippets = array(
		'Hi! I am interested in this service - do you have availability this week?',
		'Absolutely, I can start right away. Could you share more about the goal?',
		'Sure, here are the brand colors and a few references I like.',
		'Perfect, that gives me everything I need. I will get started.',
		'Thanks! Looking forward to seeing the first draft.',
		'First draft is on the way - let me know your thoughts when you can.',
	);

	/**
	 * Conversation service used to seed conversations + messages.
	 *
	 * @var ConversationService
	 */
	private ConversationService $conversations;

	/**
	 * Optional logger callback for progress output (e.g. WP_CLI::log).
	 *
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Whether to attach demo images (service galleries, portfolio items, vendor
	 * avatars). On by default so the seeded marketplace is image-complete out of
	 * the box - the demo is the customer's first impression. Disable via the
	 * seed() 'images' arg (e.g. CLI --no-images) for a fast text-only seed.
	 *
	 * @var bool
	 */
	private bool $seed_images = true;

	/**
	 * Constructor.
	 *
	 * @param callable|null $logger Optional progress logger receiving a string.
	 */
	public function __construct( ?callable $logger = null ) {
		$this->conversations = new ConversationService();
		$this->logger        = $logger;
	}

	/**
	 * Seed the full marketplace.
	 *
	 * @param array<string, int|bool> $args {
	 *     Optional overrides.
	 *
	 *     @type int  $orders Minimum number of orders to create. Default 55.
	 *     @type bool $images Whether to attach demo images. Default false.
	 * }
	 * @return array<string, int> Structured summary of what was created.
	 */
	public function seed( array $args = array() ): array {
		$min_orders        = max( 50, (int) ( $args['orders'] ?? 55 ) );
		$this->seed_images = ! isset( $args['images'] ) || (bool) $args['images'];

		$summary = array(
			'categories'    => 0,
			'vendors'       => 0,
			'buyers'        => 0,
			'services'      => 0,
			'portfolio'     => 0,
			'orders'        => 0,
			'reviews'       => 0,
			'requests'      => 0,
			'proposals'     => 0,
			'conversations' => 0,
			'messages'      => 0,
			'favorites'     => 0,
			'withdrawals'   => 0,
		);

		// Model-site shape: the marketplace IS the site, so the mapped
		// services page becomes the static front page (matches how customers
		// run marketplace homepages and keeps browse journeys reproducible).
		$services_page_id = (int) wpss_get_page_id( 'services_page' );
		if ( $services_page_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $services_page_id );
			$this->log( 'Front page set to the mapped services page (#' . $services_page_id . ').' );
		}

		$category_ids          = $this->ensure_categories();
		$summary['categories'] = count( $category_ids );
		$this->log( 'Categories ready: ' . count( $category_ids ) );

		$vendors            = $this->seed_vendors();
		$summary['vendors'] = count( $vendors );
		$this->log( 'Vendors created: ' . count( $vendors ) );

		$buyers            = $this->seed_buyers();
		$summary['buyers'] = count( $buyers );
		$this->log( 'Buyers created: ' . count( $buyers ) );

		$services            = $this->seed_services( $vendors, $category_ids );
		$summary['services'] = count( $services );
		$this->log( 'Services created: ' . count( $services ) );

		$summary['portfolio'] = $this->seed_portfolios( $vendors, $services );
		$this->log( 'Portfolio items created: ' . $summary['portfolio'] );

		$orders            = $this->seed_orders( $services, $buyers, $min_orders );
		$summary['orders'] = count( $orders );
		$this->log( 'Orders created: ' . count( $orders ) );

		$summary['reviews'] = $this->seed_reviews( $orders );
		$this->log( 'Reviews created: ' . $summary['reviews'] );

		$conversation_counts      = $this->seed_conversations( $orders );
		$summary['conversations'] = $conversation_counts['conversations'];
		$summary['messages']      = $conversation_counts['messages'];
		$this->log( 'Conversations created: ' . $summary['conversations'] . ' (messages: ' . $summary['messages'] . ')' );

		$request_counts       = $this->seed_requests_and_proposals( $buyers, $vendors, $category_ids );
		$summary['requests']  = $request_counts['requests'];
		$summary['proposals'] = $request_counts['proposals'];
		$this->log( 'Buyer requests created: ' . $summary['requests'] . ' (proposals: ' . $summary['proposals'] . ')' );

		$summary['favorites'] = $this->seed_favorites( $buyers, $services );
		$this->log( 'Favorites created: ' . $summary['favorites'] );

		$summary['withdrawals'] = $this->seed_withdrawals( $vendors );
		$this->log( 'Withdrawals created: ' . $summary['withdrawals'] );

		$this->refresh_vendor_stats( $vendors );

		return $summary;
	}

	/**
	 * Ensure all default service categories exist, returning name => term_id.
	 *
	 * @return array<string, int>
	 */
	private function ensure_categories(): array {
		$ids = array();

		foreach ( $this->category_names as $name ) {
			$term = get_term_by( 'name', $name, 'wpss_service_category' );

			if ( $term instanceof \WP_Term ) {
				$ids[ $name ] = (int) $term->term_id;
				continue;
			}

			$created = wp_insert_term( $name, 'wpss_service_category' );
			if ( ! is_wp_error( $created ) ) {
				$ids[ $name ] = (int) $created['term_id'];
			}
		}

		return $ids;
	}

	/**
	 * Create vendor users + vendor_profiles rows.
	 *
	 * @return array<int, array{user_id: int, blueprint: array<string, mixed>}>
	 */
	private function seed_vendors(): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'wpss_vendor_profiles';
		$vendors = array();

		foreach ( $this->vendor_blueprints as $index => $blueprint ) {
			$user_id = $this->ensure_user(
				$blueprint['login'],
				$blueprint['name'],
				'wpss_vendor'
			);

			if ( ! $user_id ) {
				continue;
			}

			// Tier-driven realistic stats.
			$completed  = $this->tier_completed_orders( $blueprint['tier'], $index );
			$rating     = $this->tier_rating( $blueprint['tier'] );
			$created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( 90 + $index * 45 ) . ' days' ) );

			$data = array(
				'user_id'               => $user_id,
				'display_name'          => $blueprint['name'],
				'tagline'               => $blueprint['tagline'],
				'bio'                   => sprintf(
					'%s. Based in %s, I have helped %d+ clients ship work they are proud of. Let us build something great together.',
					$blueprint['tagline'],
					$blueprint['country'],
					$completed
				),
				'status'                => 'active',
				'verification_tier'     => $blueprint['tier'],
				'country'               => $blueprint['country'],
				'social_links'          => wp_json_encode(
					array(
						'twitter'  => 'https://twitter.com/' . sanitize_title( $blueprint['name'] ),
						'linkedin' => 'https://linkedin.com/in/' . sanitize_title( $blueprint['name'] ),
					)
				),
				'total_orders'          => $completed + 3,
				'completed_orders'      => $completed,
				'avg_rating'            => $rating,
				'total_reviews'         => (int) round( $completed * 0.7 ),
				'response_time_hours'   => 1 + ( $index % 5 ),
				'on_time_delivery_rate' => 92.0 + ( $index % 8 ),
				'is_available'          => 1,
				'created_at'            => $created_at,
			);

			// Store skills as user meta (profile UI reads these as future-feature data).
			update_user_meta( $user_id, '_wpss_vendor_skills', $blueprint['skills'] );

			$existing = VendorProfile::get_by_user_id( $user_id );
			if ( $existing ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, $data, array( 'user_id' => $user_id ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert( $table, $data );
			}

			// Vendor avatar (square) so profiles and cards are not faceless.
			$avatar_id = $this->sideload_image( 'wpss-vendor-' . $user_id, 400, 400, 0, $blueprint['name'] );
			if ( $avatar_id ) {
				update_user_meta( $user_id, '_wpss_avatar_id', $avatar_id );
			}

			$vendors[] = array(
				'user_id'   => $user_id,
				'blueprint' => $blueprint,
			);
		}

		return $vendors;
	}

	/**
	 * Create buyer users.
	 *
	 * @return array<int, int> Buyer user IDs.
	 */
	private function seed_buyers(): array {
		$buyers = array();

		foreach ( $this->buyer_blueprints as $blueprint ) {
			$user_id = $this->ensure_user( $blueprint['login'], $blueprint['name'], 'subscriber' );
			if ( $user_id ) {
				$buyers[] = $user_id;
			}
		}

		return $buyers;
	}

	/**
	 * Create services owned by the seeded vendors.
	 *
	 * @param array<int, array{user_id: int, blueprint: array<string, mixed>}> $vendors      Vendors.
	 * @param array<string, int>                                               $category_ids Category map.
	 * @return array<int, array{id: int, vendor_id: int, starting_price: float, delivery_days: int, revisions: int}>
	 */
	private function seed_services( array $vendors, array $category_ids ): array {
		if ( empty( $vendors ) ) {
			return array();
		}

		$category_keys = array_keys( $category_ids );
		$titles        = array(
			'I will design a modern, memorable logo for your brand',
			'I will build a custom WordPress plugin to spec',
			'I will run a full technical SEO audit of your site',
			'I will create a 60-second animated explainer video',
			'I will write conversion-focused website copy',
			'I will design a polished, responsive landing page',
			'I will develop a REST API integration for your app',
			'I will craft a complete brand identity kit',
			'I will optimize your store for higher conversions',
			'I will produce social media motion graphics',
		);

		$services    = array();
		$title_index = 0;

		foreach ( $vendors as $v_index => $vendor ) {
			// Each vendor gets 2 services so we comfortably exceed the 5+ vendor /
			// multi-service target.
			for ( $s = 0; $s < 2; $s++ ) {
				$title    = $titles[ $title_index % count( $titles ) ];
				$category = $category_keys[ ( $v_index + $s ) % count( $category_keys ) ];
				++$title_index;

				$packages = $this->build_packages( $v_index + $s );

				$post_id = wp_insert_post(
					array(
						'post_type'    => 'wpss_service',
						'post_title'   => $title,
						'post_content' => 'Professional, reliable delivery with clear communication and unlimited collaboration. I have shipped this work for clients across many industries and stand behind every order.',
						'post_excerpt' => 'High-quality work, on time, every time.',
						'post_status'  => 'publish',
						'post_author'  => $vendor['user_id'],
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				if ( isset( $category_ids[ $category ] ) ) {
					wp_set_object_terms( $post_id, array( $category_ids[ $category ] ), 'wpss_service_category' );
				}

				$prices    = wp_list_pluck( $packages, 'price' );
				$delivery  = wp_list_pluck( $packages, 'delivery_days' );
				$revisions = wp_list_pluck( $packages, 'revisions' );

				update_post_meta( $post_id, '_wpss_packages', $packages );
				update_post_meta( $post_id, '_wpss_starting_price', min( $prices ) );
				update_post_meta( $post_id, '_wpss_fastest_delivery', min( $delivery ) );
				update_post_meta( $post_id, '_wpss_delivery_days', min( $delivery ) );
				// Both revision meta keys are kept in sync so the wizard key
				// and the admin/REST key agree regardless of creation path.
				update_post_meta( $post_id, '_wpss_max_revisions', max( $revisions ) );
				update_post_meta( $post_id, '_wpss_revisions', max( $revisions ) );

				if ( 0 === $s ) {
					update_post_meta( $post_id, '_wpss_featured', 1 );
				}

				// Service gallery (landscape). First image doubles as the WP
				// featured image so both the grid card and single-service gallery
				// render. Stored as attachment IDs (the format Service::normalize
				// _gallery_ids expects).
				$gallery = array();
				for ( $g = 0; $g < 3; $g++ ) {
					$attachment_id = $this->sideload_image( 'wpss-service-' . $post_id . '-' . $g, 800, 600, $post_id, $title );
					if ( $attachment_id ) {
						$gallery[] = $attachment_id;
					}
				}
				if ( ! empty( $gallery ) ) {
					update_post_meta( $post_id, '_wpss_gallery', $gallery );
					set_post_thumbnail( $post_id, $gallery[0] );
				}

				$services[] = array(
					'id'             => (int) $post_id,
					'vendor_id'      => $vendor['user_id'],
					'starting_price' => (float) min( $prices ),
					'delivery_days'  => (int) min( $delivery ),
					'revisions'      => (int) max( $revisions ),
				);
			}
		}

		return $services;
	}

	/**
	 * Build a Basic/Standard/Premium package set with price variation.
	 *
	 * @param int $variation Variation seed.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_packages( int $variation ): array {
		$base = 25 + ( $variation * 10 );

		return array(
			array(
				'name'          => 'Basic',
				'description'   => 'Everything you need to get started.',
				'price'         => $base,
				'delivery_days' => 3,
				'revisions'     => 2,
				'features'      => array( 'Core deliverable', '2 revisions', 'Source file' ),
			),
			array(
				'name'          => 'Standard',
				'description'   => 'The popular choice for most clients.',
				'price'         => $base * 2,
				'delivery_days' => 5,
				'revisions'     => 5,
				'features'      => array( 'Everything in Basic', '5 revisions', 'Commercial license' ),
			),
			array(
				'name'          => 'Premium',
				'description'   => 'The full package with priority support.',
				'price'         => $base * 4,
				'delivery_days' => 7,
				'revisions'     => -1,
				'features'      => array( 'Everything in Standard', 'Unlimited revisions', 'Priority support' ),
			),
		);
	}

	/**
	 * Seed portfolio items for each vendor.
	 *
	 * @param array<int, array{user_id: int, blueprint: array<string, mixed>}>                                      $vendors  Vendors.
	 * @param array<int, array{id: int, vendor_id: int, starting_price: float, delivery_days: int, revisions: int}> $services Services.
	 * @return int Number of portfolio rows created.
	 */
	private function seed_portfolios( array $vendors, array $services ): int {
		global $wpdb;

		$table   = $wpdb->prefix . 'wpss_portfolio_items';
		$created = 0;

		foreach ( $vendors as $vendor ) {
			$vendor_service = null;
			foreach ( $services as $service ) {
				if ( $service['vendor_id'] === $vendor['user_id'] ) {
					$vendor_service = $service['id'];
					break;
				}
			}

			for ( $i = 1; $i <= 3; $i++ ) {
				// Portfolio image (attachment IDs, the format PortfolioController
				// stores + the UI renders).
				$media_ids     = array();
				$portfolio_img = $this->sideload_image( 'wpss-portfolio-' . $vendor['user_id'] . '-' . $i, 800, 600, 0, $vendor['blueprint']['name'] . ' project' );
				if ( $portfolio_img ) {
					$media_ids[] = $portfolio_img;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$table,
					array(
						'vendor_id'   => $vendor['user_id'],
						'service_id'  => $vendor_service,
						'title'       => sprintf( '%s - Project #%d', $vendor['blueprint']['name'], $i ),
						'description' => 'A representative sample of recent client work delivered end to end.',
						'media'       => wp_json_encode( $media_ids ),
						'tags'        => wp_json_encode( $vendor['blueprint']['skills'] ),
						'is_featured' => 1 === $i ? 1 : 0,
						'sort_order'  => $i,
						'created_at'  => gmdate( 'Y-m-d H:i:s', strtotime( "-{$i} months" ) ),
					)
				);
				++$created;
			}
		}

		return $created;
	}

	/**
	 * Seed orders across every primary order status.
	 *
	 * @param array<int, array{id: int, vendor_id: int, starting_price: float, delivery_days: int, revisions: int}> $services   Services.
	 * @param array<int, int>                                                                                       $buyers     Buyer IDs.
	 * @param int                                                                                                   $min_orders Minimum orders.
	 * @return array<int, array{id: int, status: string, customer_id: int, vendor_id: int, service_id: int}>
	 */
	private function seed_orders( array $services, array $buyers, int $min_orders ): array {
		global $wpdb;

		if ( empty( $services ) || empty( $buyers ) ) {
			return array();
		}

		$table    = $wpdb->prefix . 'wpss_orders';
		$statuses = $this->primary_order_statuses();
		$orders   = array();

		// Distribute so every status appears at least twice, then top up to min_orders.
		$total     = max( $min_orders, count( $statuses ) * 2 );
		$buyer_n   = count( $buyers );
		$service_n = count( $services );
		$status_n  = count( $statuses );

		for ( $i = 0; $i < $total; $i++ ) {
			$status   = $statuses[ $i % $status_n ];
			$service  = $services[ $i % $service_n ];
			$customer = $buyers[ $i % $buyer_n ];

			// Avoid a vendor ordering their own service.
			if ( $customer === $service['vendor_id'] ) {
				$customer = $buyers[ ( $i + 1 ) % $buyer_n ];
			}

			$subtotal     = $service['starting_price'] * ( 1 + ( $i % 3 ) );
			$addons_total = 0 === $i % 4 ? 15.0 : 0.0;
			$order_total  = $subtotal + $addons_total;

			// Compute through the single authority so demo orders carry the same
			// breakdown the live engine would produce (no divergent local math).
			$breakdown       = CommissionService::compute_breakdown(
				(float) $order_total,
				(object) array(
					'id'         => 0,
					'vendor_id'  => (int) $service['vendor_id'],
					'service_id' => (int) $service['id'],
				)
			);
			$platform_fee    = $breakdown['platform_fee'];
			$earnings        = $breakdown['vendor_earnings'];
			$commission_rate = $breakdown['commission_rate'];

			$created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( ( $i * 3 ) + 1 ) . ' days' ) );
			$deadline   = gmdate( 'Y-m-d H:i:s', strtotime( $created_at . ' +' . $service['delivery_days'] . ' days' ) );

			$is_paid      = ! in_array( $status, array( ServiceOrder::STATUS_PENDING_PAYMENT ), true );
			$is_completed = ServiceOrder::STATUS_COMPLETED === $status;

			$data = array(
				'order_number'       => sprintf( 'WPSS-%s-%04d', gmdate( 'Ymd', strtotime( $created_at ) ), $i + 1 ),
				'customer_id'        => $customer,
				'vendor_id'          => $service['vendor_id'],
				'service_id'         => $service['id'],
				'package_id'         => null,
				'addons'             => wp_json_encode( array() ),
				'platform'           => 'standalone',
				'subtotal'           => $subtotal,
				'addons_total'       => $addons_total,
				'total'              => $order_total,
				'currency'           => 'USD',
				'commission_rate'    => $commission_rate,
				'platform_fee'       => $platform_fee,
				'vendor_earnings'    => $earnings,
				'status'             => $status,
				'delivery_deadline'  => $deadline,
				'original_deadline'  => $deadline,
				'payment_method'     => $is_paid ? 'standalone' : null,
				'payment_status'     => $is_paid ? 'paid' : 'pending',
				'transaction_id'     => $is_paid ? 'demo_txn_' . wp_generate_password( 12, false ) : null,
				'paid_at'            => $is_paid ? $created_at : null,
				'revisions_included' => $service['revisions'],
				'revisions_used'     => ServiceOrder::STATUS_REVISION_REQUESTED === $status ? 1 : 0,
				'created_at'         => $created_at,
				'updated_at'         => $created_at,
				'started_at'         => $is_paid ? $created_at : null,
				'meta'               => wp_json_encode(
					array(
						'status_history' => array(
							array(
								'status'    => $status,
								'timestamp' => $created_at,
								'note'      => 'Seeded demo order.',
							),
						),
					)
				),
				'completed_at'       => $is_completed ? $deadline : null,
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert( $table, $data );
			if ( ! $inserted ) {
				continue;
			}

			$orders[] = array(
				'id'          => (int) $wpdb->insert_id,
				'status'      => $status,
				'customer_id' => $customer,
				'vendor_id'   => $service['vendor_id'],
				'service_id'  => $service['id'],
			);
		}

		return $orders;
	}

	/**
	 * Seed reviews for completed orders.
	 *
	 * @param array<int, array{id: int, status: string, customer_id: int, vendor_id: int, service_id: int}> $orders Orders.
	 * @return int Number of reviews created.
	 */
	private function seed_reviews( array $orders ): int {
		global $wpdb;

		$table   = $wpdb->prefix . 'wpss_reviews';
		$created = 0;

		foreach ( $orders as $index => $order ) {
			if ( ServiceOrder::STATUS_COMPLETED !== $order['status'] ) {
				continue;
			}

			$rating     = 4 + ( $index % 2 ); // 4 or 5.
			$created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( ( $index * 2 ) + 1 ) . ' days' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert(
				$table,
				array(
					'order_id'    => $order['id'],
					'reviewer_id' => $order['customer_id'],
					'reviewee_id' => $order['vendor_id'],
					'service_id'  => $order['service_id'],
					'customer_id' => $order['customer_id'],
					'vendor_id'   => $order['vendor_id'],
					'rating'      => $rating,
					'review'      => $this->review_bodies[ $index % count( $this->review_bodies ) ],
					'status'      => Review::STATUS_APPROVED,
					'is_public'   => 1,
					'created_at'  => $created_at,
				)
			);

			if ( $inserted ) {
				++$created;
			}
		}

		return $created;
	}

	/**
	 * Seed conversations + messages for active orders.
	 *
	 * @param array<int, array{id: int, status: string, customer_id: int, vendor_id: int, service_id: int}> $orders Orders.
	 * @return array{conversations: int, messages: int}
	 */
	private function seed_conversations( array $orders ): array {
		$active_statuses = array(
			ServiceOrder::STATUS_IN_PROGRESS,
			ServiceOrder::STATUS_PENDING_APPROVAL,
			ServiceOrder::STATUS_REVISION_REQUESTED,
			ServiceOrder::STATUS_COMPLETED,
			ServiceOrder::STATUS_DELIVERED,
		);

		$conversation_count = 0;
		$message_count      = 0;

		foreach ( $orders as $order ) {
			if ( ! in_array( $order['status'], $active_statuses, true ) ) {
				continue;
			}

			$conversation = $this->conversations->create_direct(
				$order['customer_id'],
				$order['vendor_id'],
				__( 'Order discussion', 'wp-sell-services' ),
				$order['service_id']
			);

			if ( ! $conversation ) {
				continue;
			}

			++$conversation_count;

			// Alternate sender: buyer starts, vendor replies.
			foreach ( $this->message_snippets as $m_index => $snippet ) {
				$sender  = 0 === $m_index % 2 ? $order['customer_id'] : $order['vendor_id'];
				$message = $this->conversations->send_message(
					$conversation->id,
					$sender,
					$snippet
				);
				if ( $message ) {
					++$message_count;
				}
			}
		}

		return array(
			'conversations' => $conversation_count,
			'messages'      => $message_count,
		);
	}

	/**
	 * Seed buyer requests and vendor proposals.
	 *
	 * @param array<int, int>                                                  $buyers       Buyer IDs.
	 * @param array<int, array{user_id: int, blueprint: array<string, mixed>}> $vendors      Vendors.
	 * @param array<string, int>                                               $category_ids Category map.
	 * @return array{requests: int, proposals: int}
	 */
	private function seed_requests_and_proposals( array $buyers, array $vendors, array $category_ids ): array {
		global $wpdb;

		if ( empty( $buyers ) || empty( $vendors ) ) {
			return array(
				'requests'  => 0,
				'proposals' => 0,
			);
		}

		$proposals_table = $wpdb->prefix . 'wpss_proposals';
		$category_keys   = array_keys( $category_ids );

		$request_titles = array(
			'Need a logo and brand kit for a new coffee startup',
			'Looking for a developer to build a custom booking plugin',
			'Want a technical SEO audit for a 200-page WooCommerce store',
			'Need a 90-second animated explainer for our SaaS',
			'Seeking a copywriter for a 5-page marketing website',
			'Need a Figma-to-WordPress conversion for a landing page',
		);

		$request_count  = 0;
		$proposal_count = 0;

		foreach ( $request_titles as $r_index => $title ) {
			$buyer    = $buyers[ $r_index % count( $buyers ) ];
			$category = $category_keys[ $r_index % count( $category_keys ) ];

			$post_id = wp_insert_post(
				array(
					'post_type'    => BuyerRequestPostType::POST_TYPE,
					'post_title'   => $title,
					'post_content' => 'Looking for an experienced professional. Please share relevant samples, your proposed approach, timeline, and budget. Open to ongoing collaboration for the right fit.',
					'post_status'  => 'publish',
					'post_author'  => $buyer,
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $r_index * 4 + 2 ) . ' days' ) ),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			++$request_count;

			$budget_min = 100 + ( $r_index * 50 );
			$budget_max = $budget_min * 3;

			update_post_meta( $post_id, '_wpss_status', BuyerRequest::STATUS_OPEN );
			update_post_meta( $post_id, '_wpss_budget_min', $budget_min );
			update_post_meta( $post_id, '_wpss_budget_max', $budget_max );
			update_post_meta( $post_id, '_wpss_deadline', gmdate( 'Y-m-d', strtotime( '+' . ( $r_index + 1 ) . ' weeks' ) ) );

			if ( isset( $category_ids[ $category ] ) ) {
				wp_set_object_terms( $post_id, array( $category_ids[ $category ] ), 'wpss_service_category' );
			}

			// Each request gets 2-3 proposals from different vendors.
			$proposal_total    = 2 + ( $r_index % 2 );
			$request_props     = 0;
			$proposal_statuses = array(
				Proposal::STATUS_PENDING,
				Proposal::STATUS_ACCEPTED,
				Proposal::STATUS_REJECTED,
				Proposal::STATUS_WITHDRAWN,
			);

			for ( $p = 0; $p < $proposal_total; $p++ ) {
				$vendor = $vendors[ ( $r_index + $p ) % count( $vendors ) ];
				$status = $proposal_statuses[ ( $r_index + $p ) % count( $proposal_statuses ) ];

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$inserted = $wpdb->insert(
					$proposals_table,
					array(
						'request_id'     => $post_id,
						'vendor_id'      => $vendor['user_id'],
						'service_id'     => null,
						'cover_letter'   => sprintf(
							'Hi! I specialize in %s and would love to help with this project. I can deliver a polished result within your timeline. Here is my proposed approach and a few relevant samples.',
							strtolower( implode( ', ', $vendor['blueprint']['skills'] ) )
						),
						'proposed_price' => $budget_min + ( $p * 50 ),
						'proposed_days'  => 5 + ( $p * 2 ),
						'contract_type'  => 'fixed',
						'status'         => $status,
						'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $r_index * 4 + 1 ) . ' days' ) ),
					)
				);

				if ( $inserted ) {
					++$proposal_count;
					++$request_props;
				}
			}

			update_post_meta( $post_id, '_wpss_proposal_count', $request_props );
		}

		return array(
			'requests'  => $request_count,
			'proposals' => $proposal_count,
		);
	}

	/**
	 * Seed buyer favorites (wishlist) as user meta.
	 *
	 * Writes the canonical favorites meta (FavoritesService::META_KEY) - every
	 * surface reads and writes through FavoritesService since 1.2.0.
	 *
	 * @param array<int, int>                                                                                       $buyers   Buyer IDs.
	 * @param array<int, array{id: int, vendor_id: int, starting_price: float, delivery_days: int, revisions: int}> $services Services.
	 * @return int Number of favorite relationships created.
	 */
	private function seed_favorites( array $buyers, array $services ): int {
		if ( empty( $buyers ) || empty( $services ) ) {
			return 0;
		}

		$service_ids = wp_list_pluck( $services, 'id' );
		$created     = 0;

		foreach ( $buyers as $b_index => $buyer ) {
			// Each buyer favorites 2-4 distinct services.
			$count     = 2 + ( $b_index % 3 );
			$favorites = array();

			for ( $i = 0; $i < $count; $i++ ) {
				$service_id = (int) $service_ids[ ( $b_index + $i ) % count( $service_ids ) ];
				if ( ! in_array( $service_id, $favorites, true ) ) {
					$favorites[] = $service_id;
				}
			}

			update_user_meta( $buyer, \WPSellServices\Services\FavoritesService::META_KEY, $favorites );
			$created += count( $favorites );
		}

		return $created;
	}

	/**
	 * Seed vendor withdrawals across pending/approved/completed/rejected states.
	 *
	 * @param array<int, array{user_id: int, blueprint: array<string, mixed>}> $vendors Vendors.
	 * @return int Number of withdrawal rows created.
	 */
	private function seed_withdrawals( array $vendors ): int {
		global $wpdb;

		$table    = $wpdb->prefix . 'wpss_withdrawals';
		$statuses = array_keys( EarningsService::get_withdrawal_statuses() );
		$methods  = array( 'paypal', 'bank_transfer', 'paypal', 'stripe' );
		$created  = 0;

		foreach ( $vendors as $index => $vendor ) {
			// Skip the newest vendor to keep the data realistic (no payouts yet).
			if ( VendorProfile::TIER_NEW === $vendor['blueprint']['tier'] ) {
				continue;
			}

			$status     = $statuses[ $index % count( $statuses ) ];
			$method     = $methods[ $index % count( $methods ) ];
			$amount     = 150.0 + ( $index * 75 );
			$created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $index * 7 + 5 ) . ' days' ) );
			$processed  = in_array( $status, EarningsService::get_processable_withdrawal_statuses(), true );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert(
				$table,
				array(
					'vendor_id'    => $vendor['user_id'],
					'amount'       => $amount,
					'method'       => $method,
					'details'      => wp_json_encode( array( 'account' => 'demo@' . sanitize_title( $vendor['blueprint']['name'] ) . '.test' ) ),
					'status'       => $status,
					'is_auto'      => 0,
					'admin_note'   => EarningsService::WITHDRAWAL_REJECTED === $status ? 'Insufficient available balance at time of request.' : '',
					'processed_at' => $processed ? gmdate( 'Y-m-d H:i:s', strtotime( $created_at . ' +2 days' ) ) : null,
					'created_at'   => $created_at,
				)
			);

			if ( $inserted ) {
				++$created;
			}
		}

		return $created;
	}

	/**
	 * Recompute aggregate review stats on each vendor profile from seeded rows.
	 *
	 * @param array<int, array{user_id: int, blueprint: array<string, mixed>}> $vendors Vendors.
	 * @return void
	 */
	private function refresh_vendor_stats( array $vendors ): void {
		global $wpdb;

		$reviews_table = $wpdb->prefix . 'wpss_reviews';
		$profile_table = $wpdb->prefix . 'wpss_vendor_profiles';

		foreach ( $vendors as $vendor ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS total, AVG(rating) AS avg_rating FROM {$reviews_table} WHERE vendor_id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$vendor['user_id'],
					Review::STATUS_APPROVED
				)
			);

			if ( ! $row || 0 === (int) $row->total ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$profile_table,
				array(
					'total_reviews' => (int) $row->total,
					'avg_rating'    => round( (float) $row->avg_rating, 2 ),
				),
				array( 'user_id' => $vendor['user_id'] )
			);
		}
	}

	/**
	 * Ensure a user exists with the given login + role, returning the user ID.
	 *
	 * @param string $login Login (also used to derive a demo email).
	 * @param string $name  Display name.
	 * @param string $role  Role to grant.
	 * @return int User ID, or 0 on failure.
	 */
	private function ensure_user( string $login, string $name, string $role ): int {
		$existing = get_user_by( 'login', $login );
		if ( $existing instanceof \WP_User ) {
			$existing->add_role( $role );
			return (int) $existing->ID;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 20, true ),
				'user_email'   => $login . '@example.test',
				'display_name' => $name,
				'first_name'   => explode( ' ', $name )[0],
				'role'         => $role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		return (int) $user_id;
	}

	/**
	 * The 11 primary order statuses the marketplace surfaces in its UI.
	 *
	 * @return array<int, string>
	 */
	private function primary_order_statuses(): array {
		return array(
			ServiceOrder::STATUS_PENDING_PAYMENT,
			ServiceOrder::STATUS_PENDING_REQUIREMENTS,
			ServiceOrder::STATUS_IN_PROGRESS,
			ServiceOrder::STATUS_PENDING_APPROVAL,
			ServiceOrder::STATUS_REVISION_REQUESTED,
			ServiceOrder::STATUS_COMPLETED,
			ServiceOrder::STATUS_CANCELLED,
			ServiceOrder::STATUS_DISPUTED,
			ServiceOrder::STATUS_ON_HOLD,
			ServiceOrder::STATUS_LATE,
			ServiceOrder::STATUS_CANCELLATION_REQUESTED,
		);
	}

	/**
	 * Derive a believable completed-orders count from a vendor tier.
	 *
	 * @param string $tier  Vendor tier.
	 * @param int    $index Vendor index for spread.
	 * @return int
	 */
	private function tier_completed_orders( string $tier, int $index ): int {
		switch ( $tier ) {
			case VendorProfile::TIER_PRO:
				return 320 + ( $index * 12 );
			case VendorProfile::TIER_TOP_RATED:
				return 140 + ( $index * 8 );
			case VendorProfile::TIER_RISING:
				return 45 + ( $index * 5 );
			default:
				return 6 + $index;
		}
	}

	/**
	 * Derive a believable average rating from a vendor tier.
	 *
	 * @param string $tier Vendor tier.
	 * @return float
	 */
	private function tier_rating( string $tier ): float {
		switch ( $tier ) {
			case VendorProfile::TIER_PRO:
				return 5.0;
			case VendorProfile::TIER_TOP_RATED:
				return 4.9;
			case VendorProfile::TIER_RISING:
				return 4.7;
			default:
				return 4.5;
		}
	}

	/**
	 * Attach a bundled demo image to the media library and return its ID.
	 *
	 * Images ship inside the plugin (assets/demo-media/) so the seeded
	 * marketplace is image-complete with NO network dependency - the demo is the
	 * customer's first impression and must look good on any install. A file is
	 * chosen from the matching pool deterministically by seed (square => avatars,
	 * otherwise the landscape service pool), copied into the media library, and
	 * the bundled original is left untouched. If the bundled folder is ever
	 * missing, a locally generated placeholder is used so a seed is never
	 * imageless.
	 *
	 * @param string $seed   Deterministic seed that varies which bundled file is picked.
	 * @param int    $width  Intended width (square => avatar pool; used for the placeholder fallback).
	 * @param int    $height Intended height (used for the placeholder fallback).
	 * @param int    $parent Parent post ID to attach to (0 for none).
	 * @param string $label  Human label used for alt text + placeholder fallback.
	 * @return int Attachment ID, or 0 when images are disabled or all paths fail.
	 */
	private function sideload_image( string $seed, int $width, int $height, int $parent, string $label ): int {
		if ( ! $this->seed_images ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$pool  = ( $width === $height ) ? 'avatars' : 'services';
		$files = glob( WPSS_PLUGIN_DIR . 'assets/demo-media/' . $pool . '/*.jpg' );

		if ( ! empty( $files ) ) {
			sort( $files );
			$source = $files[ abs( (int) crc32( $seed ) ) % count( $files ) ];
			$tmp    = wp_tempnam( basename( $source ) );

			if ( $tmp && copy( $source, $tmp ) ) {
				$file          = array(
					'name'     => sanitize_file_name( basename( $source ) ),
					'tmp_name' => $tmp,
				);
				$attachment_id = media_handle_sideload( $file, $parent, $label );
				if ( is_wp_error( $attachment_id ) ) {
					if ( file_exists( $tmp ) ) {
						wp_delete_file( $tmp );
					}
				} else {
					return (int) $attachment_id;
				}
			}
		}

		// Bundled media missing - guarantee an image via a generated placeholder.
		return $this->generate_placeholder_image( $label, $width, $height, $parent );
	}

	/**
	 * Generate a simple branded placeholder image locally and attach it.
	 *
	 * Used as the offline fallback for sideload_image() so a demo seed never
	 * leaves a service, portfolio item, or vendor without an image.
	 *
	 * @param string $label  Label drawn on the placeholder + used as alt text.
	 * @param int    $width  Image width in pixels.
	 * @param int    $height Image height in pixels.
	 * @param int    $parent Parent post ID to attach to (0 for none).
	 * @return int Attachment ID, or 0 when GD is unavailable / insertion fails.
	 */
	private function generate_placeholder_image( string $label, int $width, int $height, int $parent ): int {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$image = imagecreatetruecolor( $width, $height );
		$bg    = imagecolorallocate(
			$image,
			60 + ( (int) crc32( $label ) % 120 ),
			70 + ( (int) crc32( $label . 'g' ) % 120 ),
			90 + ( (int) crc32( $label . 'b' ) % 120 )
		);
		imagefilledrectangle( $image, 0, 0, $width, $height, $bg );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		imagestring( $image, 5, 20, (int) ( $height / 2 ) - 8, strtoupper( substr( $label, 0, 28 ) ), $white );

		$uploads  = wp_upload_dir();
		$filename = 'wpss-demo-' . md5( $label . $width . 'x' . $height ) . '.png';
		$path     = trailingslashit( $uploads['path'] ) . $filename;

		if ( ! imagepng( $image, $path ) ) {
			imagedestroy( $image );
			return 0;
		}
		imagedestroy( $image );

		$filetype   = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => $label,
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $path, $parent );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );

		return (int) $attachment_id;
	}

	/**
	 * Emit a progress line through the optional logger.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message );
		}
	}
}
