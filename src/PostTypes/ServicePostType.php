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
		add_filter( 'post_updated_messages', [ $this, 'filter_post_messages' ] );
		add_filter( 'enter_title_here', [ $this, 'filter_title_placeholder' ], 10, 2 );
		add_action( 'save_post_wpss_service', [ $this, 'sync_delivery_days_meta' ], 20, 2 );
	}

	/**
	 * Sync _wpss_delivery_days flat meta from packages on every save.
	 *
	 * Ensures the delivery time filter on the archive page always has data
	 * to filter on, even for services not created via the wizard.
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
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			// Packet H: data-URL Lucide `shopping-cart` glyph (house-style icons).
			// `menu_icon` requires an SVG URL or dashicon class; we inline the
			// Lucide shopping-cart SVG so WordPress renders it in the admin menu.
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
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] )
				? sprintf( __( 'Service restored to revision from %s', 'wp-sell-services' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
				: false,
			/* translators: %s: post permalink */
			6  => sprintf( __( 'Service published. <a href="%s">View Service</a>', 'wp-sell-services' ), esc_url( $permalink ) ),
			7  => __( 'Service saved.', 'wp-sell-services' ),
			/* translators: %s: post permalink */
			8  => sprintf( __( 'Service submitted. <a target="_blank" href="%s">Preview Service</a>', 'wp-sell-services' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
			/* translators: 1: Publish box date format, 2: Post permalink */
			9  => sprintf(
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
