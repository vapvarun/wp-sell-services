<?php
/**
 * Service Post Type
 *
 * @package WPSellServices\PostTypes
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Handles registration and configuration of the Service custom post type.
 *
 * @since 1.0.0
 */
class ServicePostType {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'wpss_service';

	/**
	 * Initialize the post type.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		// Seed a starter category set once, AFTER the taxonomy is registered, so a
		// fresh install (or one where the owner skipped the setup wizard) is never
		// left with an empty Category dropdown that hard-blocks the Service Wizard
		// (BC 10134408693). Runs once; never re-seeds if the owner has categories.
		add_action( 'init', [ $this, 'maybe_seed_default_categories' ], 20 );
		add_filter( 'post_updated_messages', [ $this, 'filter_post_messages' ] );
		add_filter( 'enter_title_here', [ $this, 'filter_title_placeholder' ], 10, 2 );
		add_filter( 'use_block_editor_for_post_type', [ $this, 'use_classic_editor' ], 10, 2 );
		add_action( 'save_post_wpss_service', [ $this, 'sync_delivery_days_meta' ], 20, 2 );
		add_action( 'added_post_meta', [ $this, 'sync_starting_price' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'sync_starting_price' ], 10, 4 );
	}

	/**
	 * Seed a starter set of service categories on first run.
	 *
	 * Guarded so it runs at most once and never overwrites an owner's own
	 * categories: if any term already exists, it just marks the job done.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function maybe_seed_default_categories(): void {
		if ( get_option( 'wpss_default_categories_seeded' ) ) {
			return;
		}

		// Mark done up-front so a transient failure never loops every request.
		update_option( 'wpss_default_categories_seeded', 1, false );

		// Respect an owner who already curated categories.
		$existing = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			return;
		}

		$this->seed_default_categories();
	}

	/**
	 * Insert the default service categories. Returns the created term IDs.
	 *
	 * Separated from maybe_seed_default_categories() so it is directly testable
	 * and reusable (e.g. from the setup wizard's "skip categories" path).
	 *
	 * @since 1.3.0
	 * @return int[] Created term IDs.
	 */
	public function seed_default_categories(): array {
		$defaults = apply_filters(
			'wpss_default_service_categories',
			array(
				__( 'Graphics & Design', 'wp-sell-services' ),
				__( 'Programming & Tech', 'wp-sell-services' ),
				__( 'Digital Marketing', 'wp-sell-services' ),
				__( 'Writing & Translation', 'wp-sell-services' ),
				__( 'Video & Animation', 'wp-sell-services' ),
				__( 'Music & Audio', 'wp-sell-services' ),
				__( 'Business', 'wp-sell-services' ),
				__( 'AI Services', 'wp-sell-services' ),
			)
		);

		// Lucide icons for the defaults, so a fresh marketplace's category
		// list and Icon column are not blank (Basecamp 10337190248).
		$icons = array(
			__( 'Graphics & Design', 'wp-sell-services' )  => 'palette',
			__( 'Programming & Tech', 'wp-sell-services' ) => 'code',
			__( 'Digital Marketing', 'wp-sell-services' )  => 'megaphone',
			__( 'Writing & Translation', 'wp-sell-services' ) => 'pen-line',
			__( 'Video & Animation', 'wp-sell-services' )  => 'clapperboard',
			__( 'Music & Audio', 'wp-sell-services' )      => 'music',
			__( 'Business', 'wp-sell-services' )           => 'briefcase',
			__( 'AI Services', 'wp-sell-services' )        => 'sparkles',
		);

		$created = array();
		foreach ( $defaults as $name ) {
			$name = trim( (string) $name );
			if ( '' === $name || term_exists( $name, 'wpss_service_category' ) ) {
				continue;
			}
			$term = wp_insert_term( $name, 'wpss_service_category' );
			if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
				$created[] = (int) $term['term_id'];
				if ( isset( $icons[ $name ] ) ) {
					update_term_meta( (int) $term['term_id'], '_wpss_icon', $icons[ $name ] );
				}
			}
		}

		return $created;
	}

	/**
	 * Sync the delivery-days flat meta keys from packages on every save.
	 *
	 * Ensures the delivery time filters (archive page reads
	 * `_wpss_delivery_days`, REST max_delivery_days reads
	 * `_wpss_fastest_delivery`) always have data to filter on, even for
	 * services not created via the wizard. Both keys are written so either
	 * meta query matches regardless of creation path.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function sync_delivery_days_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$packages = get_post_meta( $post_id, '_wpss_packages', true );

		if ( empty( $packages ) || ! is_array( $packages ) ) {
			return;
		}

		// Use the first enabled package's delivery_days.
		$first         = reset( $packages );
		$delivery_days = (int) ( $first['delivery_days'] ?? $first['delivery_time'] ?? 0 );

		if ( $delivery_days > 0 ) {
			update_post_meta( $post_id, '_wpss_delivery_days', $delivery_days );
		}

		// Fastest delivery = minimum delivery days across all packages.
		$all_days = array();
		foreach ( $packages as $package ) {
			$days = (int) ( $package['delivery_days'] ?? $package['delivery_time'] ?? 0 );
			if ( $days > 0 ) {
				$all_days[] = $days;
			}
		}

		if ( ! empty( $all_days ) ) {
			update_post_meta( $post_id, '_wpss_fastest_delivery', min( $all_days ) );
		}
	}

	/**
	 * A description saved by the block editor, as the wizard's plain text.
	 *
	 * Block comments go, and so do the <p> wrappers: the wizard edits
	 * paragraphs as blank-line-separated text and the storefront puts them back
	 * with wpautop(). Inline HTML the wizard allows (bold, links, lists) stays.
	 *
	 * @since 1.8.0
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function strip_block_markup( string $content ): string {
		return trim( (string) preg_replace( array( '/<!--\s*\/?wp:[^>]*?-->\n?/', '#</?p>#' ), '', $content ) );
	}

	/**
	 * Keep the stored starting price in step with the packages.
	 *
	 * Service cards, the archive's price filter and sort, and the admin list
	 * read _wpss_starting_price, but only the vendor wizard wrote it - and from
	 * its first package rather than the cheapest - so a service edited in
	 * wp-admin, over REST or by WP-CLI kept showing its old "Starting at"
	 * (Basecamp 10337190248). Every package writer updates _wpss_packages, so
	 * the price follows that write.
	 *
	 * @since 1.8.0
	 *
	 * @param int    $meta_id  Meta row ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $packages Meta value.
	 * @return void
	 */
	public function sync_starting_price( $meta_id, $post_id, $meta_key, $packages ): void {
		// An empty write leaves the price alone: older services keep their
		// packages only in the wpss_service_packages table.
		if ( '_wpss_packages' !== $meta_key || ! is_array( $packages ) || ! $packages ) {
			return;
		}

		update_post_meta( (int) $post_id, '_wpss_starting_price', self::starting_price( $packages ) );
	}

	/**
	 * The lowest price among a service's enabled packages, 0 when none is priced.
	 *
	 * @since 1.8.0
	 *
	 * @param array<int|string, mixed> $packages Packages as stored in _wpss_packages.
	 * @return float
	 */
	public static function starting_price( array $packages ): float {
		$prices = array();

		foreach ( $packages as $package ) {
			if ( is_array( $package ) && false !== ( $package['enabled'] ?? true ) && (float) ( $package['price'] ?? 0 ) > 0 ) {
				$prices[] = (float) $package['price'];
			}
		}

		return $prices ? min( $prices ) : 0.0;
	}

	/**
	 * Register the service post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => _x( 'Services', 'Post type general name', 'wp-sell-services' ),
			'singular_name'         => _x( 'Service', 'Post type singular name', 'wp-sell-services' ),
			'menu_name'             => _x( 'Services', 'Admin Menu text', 'wp-sell-services' ),
			'name_admin_bar'        => _x( 'Service', 'Add New on Toolbar', 'wp-sell-services' ),
			'add_new'               => __( 'Add New', 'wp-sell-services' ),
			'add_new_item'          => __( 'Add New Service', 'wp-sell-services' ),
			'new_item'              => __( 'New Service', 'wp-sell-services' ),
			'edit_item'             => __( 'Edit Service', 'wp-sell-services' ),
			'view_item'             => __( 'View Service', 'wp-sell-services' ),
			'all_items'             => __( 'All Services', 'wp-sell-services' ),
			'search_items'          => __( 'Search Services', 'wp-sell-services' ),
			'parent_item_colon'     => __( 'Parent Services:', 'wp-sell-services' ),
			'not_found'             => __( 'No services found.', 'wp-sell-services' ),
			'not_found_in_trash'    => __( 'No services found in Trash.', 'wp-sell-services' ),
			'featured_image'        => _x( 'Service Cover Image', 'Overrides the "Featured Image" phrase', 'wp-sell-services' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'wp-sell-services' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'wp-sell-services' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'wp-sell-services' ),
			'archives'              => _x( 'Service Archives', 'The post type archive label', 'wp-sell-services' ),
			'insert_into_item'      => _x( 'Insert into service', 'Overrides the "Insert into post" phrase', 'wp-sell-services' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this service', 'Overrides the "Uploaded to this post" phrase', 'wp-sell-services' ),
			'filter_items_list'     => _x( 'Filter services list', 'Screen reader text', 'wp-sell-services' ),
			'items_list_navigation' => _x( 'Services list navigation', 'Screen reader text', 'wp-sell-services' ),
			'items_list'            => _x( 'Services list', 'Screen reader text', 'wp-sell-services' ),
		];

		$args = [
			'labels'             => $labels,
			'description'        => __( 'Service offerings for sale.', 'wp-sell-services' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'wp-sell-services', // Show under our custom admin menu.
			'query_var'          => true,
			'rewrite'            => [
				'slug'       => $this->get_slug(),
				'with_front' => false,
			],
			// Own capability set (edit_wpss_services...) so listing a service
			// needs the vendor role or site staff, not merely edit_posts - which
			// every author holds. Granted in Activator::create_roles().
			'capability_type'    => array( 'wpss_service', 'wpss_services' ),
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			// Packet H: data-URL Lucide `shopping-cart` glyph (house-style icons).
			// `menu_icon` requires an SVG URL or dashicon class; we inline the
			// Lucide shopping-cart SVG so WordPress renders it in the admin menu.
			// Not obfuscation: WordPress's `menu_icon` API accepts a dashicon
			// class or an SVG data URL, and a data URL must be base64. The SVG
			// source is inline and readable directly above/below.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required encoding for an SVG data URL.
			'menu_icon'          => 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>' ),
			'supports'           => [
				'title',
				'editor',
				'author',
				'thumbnail',
				'excerpt',
				'custom-fields',
				'revisions',
			],
			'show_in_rest'       => true,
			'rest_base'          => 'wpss-services',
		];

		/**
		 * Filter service post type arguments.
		 *
		 * @param array $args Post type arguments.
		 */
		$args = apply_filters( 'wpss_service_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register taxonomies for services.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$this->register_tag_taxonomy();
	}

	/**
	 * Register service tag taxonomy.
	 *
	 * @return void
	 */
	private function register_tag_taxonomy(): void {
		$labels = [
			'name'                       => _x( 'Service Tags', 'Taxonomy general name', 'wp-sell-services' ),
			'singular_name'              => _x( 'Service Tag', 'Taxonomy singular name', 'wp-sell-services' ),
			'search_items'               => __( 'Search Tags', 'wp-sell-services' ),
			'popular_items'              => __( 'Popular Tags', 'wp-sell-services' ),
			'all_items'                  => __( 'All Tags', 'wp-sell-services' ),
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
			'back_to_items'              => __( '← Back to Tags', 'wp-sell-services' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'rest_base'         => 'service-tags',
			'rewrite'           => [
				'slug'       => 'service-tag',
				'with_front' => false,
			],
		];

		/**
		 * Filter service tag taxonomy arguments.
		 *
		 * @param array $args Taxonomy arguments.
		 */
		$args = apply_filters( 'wpss_service_tag_args', $args );

		register_taxonomy( 'wpss_service_tag', self::POST_TYPE, $args );
	}

	/**
	 * Get the service post type slug.
	 *
	 * @return string
	 */
	private function get_slug(): string {
		/**
		 * Filter the service post type slug.
		 *
		 * @param string $slug The slug.
		 */
		return apply_filters( 'wpss_service_slug', 'service' );
	}

	/**
	 * Filter post updated messages.
	 *
	 * @param array $messages Existing messages.
	 * @return array
	 */
	public function filter_post_messages( array $messages ): array {
		global $post;

		$permalink = get_permalink( $post );

		$messages[ self::POST_TYPE ] = [
			0  => '', // Unused. Messages start at index 1.
			/* translators: %s: post permalink */
			1  => sprintf( __( 'Service updated. <a target="_blank" href="%s">View Service</a>', 'wp-sell-services' ), esc_url( $permalink ) ),
			2  => __( 'Custom field updated.', 'wp-sell-services' ),
			3  => __( 'Custom field deleted.', 'wp-sell-services' ),
			4  => __( 'Service updated.', 'wp-sell-services' ),
			// The `revision` parameter is supplied by WordPress core's own redirect
			// after a revision restore, and this whole array is core's
			// `post_updated_messages` pattern - core reads $_GET the same way here.
			// It renders one admin notice and changes no state, so there is no
			// action for a nonce to protect. Cast to int on use.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			5  => isset( $_GET['revision'] )
				/* translators: %s: date and time of the revision */
				? sprintf( __( 'Service restored to revision from %s', 'wp-sell-services' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
				: false,
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			/* translators: %s: post permalink */
			6  => sprintf( __( 'Service published. <a href="%s">View Service</a>', 'wp-sell-services' ), esc_url( $permalink ) ),
			7  => __( 'Service saved.', 'wp-sell-services' ),
			/* translators: %s: post permalink */
			8  => sprintf( __( 'Service submitted. <a target="_blank" href="%s">Preview Service</a>', 'wp-sell-services' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
			9  => sprintf(
				/* translators: 1: Publish box date format, 2: Post permalink */
				__( 'Service scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Service</a>', 'wp-sell-services' ),
				date_i18n( __( 'M j, Y @ G:i', 'wp-sell-services' ), strtotime( $post->post_date ) ),
				esc_url( $permalink )
			),
			/* translators: %s: post permalink */
			10 => sprintf( __( 'Service draft updated. <a target="_blank" href="%s">Preview Service</a>', 'wp-sell-services' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
		];

		return $messages;
	}

	/**
	 * Edit services on the classic screen.
	 *
	 * The vendor wizard writes the description as plain text and the block
	 * editor wrote block markup into the same field, so a service an admin had
	 * touched showed <!-- wp: --> comments to its vendor. The block editor also
	 * folded the Service Data box into a collapsed drawer. The classic screen
	 * keeps one content format and shows the service data inline, open
	 * (Basecamp 10337190248). REST (show_in_rest) is unaffected.
	 *
	 * @since 1.8.0
	 *
	 * @param bool   $use_block_editor Whether the post type uses the block editor.
	 * @param string $post_type        Post type.
	 * @return bool
	 */
	public function use_classic_editor( bool $use_block_editor, string $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : $use_block_editor;
	}

	/**
	 * Filter the title placeholder text.
	 *
	 * @param string   $title The title placeholder.
	 * @param \WP_Post $post  The post object.
	 * @return string
	 */
	public function filter_title_placeholder( string $title, \WP_Post $post ): string {
		if ( self::POST_TYPE === $post->post_type ) {
			return __( 'Enter service title here', 'wp-sell-services' );
		}

		return $title;
	}
}
