<?php
/**
 * Buyer Request Post Type
 *
 * @package WPSellServices\PostTypes
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\PostTypes;

/**
 * Handles registration and configuration of the Buyer Request custom post type.
 *
 * Buyer requests allow customers to post job requirements that vendors can respond to.
 *
 * @since 1.0.0
 */
class BuyerRequestPostType {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'wpss_request';

	/**
	 * Initialize the post type.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_filter( 'post_updated_messages', [ $this, 'filter_post_messages' ] );
		add_filter( 'enter_title_here', [ $this, 'filter_title_placeholder' ], 10, 2 );
	}

	/**
	 * Register the buyer request post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => _x( 'Buyer Requests', 'Post type general name', 'wp-sell-services' ),
			'singular_name'         => _x( 'Buyer Request', 'Post type singular name', 'wp-sell-services' ),
			'menu_name'             => _x( 'Buyer Requests', 'Admin Menu text', 'wp-sell-services' ),
			'name_admin_bar'        => _x( 'Buyer Request', 'Add New on Toolbar', 'wp-sell-services' ),
			'add_new'               => __( 'Add New', 'wp-sell-services' ),
			'add_new_item'          => __( 'Add New Request', 'wp-sell-services' ),
			'new_item'              => __( 'New Request', 'wp-sell-services' ),
			'edit_item'             => __( 'Edit Request', 'wp-sell-services' ),
			'view_item'             => __( 'View Request', 'wp-sell-services' ),
			'all_items'             => __( 'All Requests', 'wp-sell-services' ),
			'search_items'          => __( 'Search Requests', 'wp-sell-services' ),
			'not_found'             => __( 'No requests found.', 'wp-sell-services' ),
			'not_found_in_trash'    => __( 'No requests found in Trash.', 'wp-sell-services' ),
			'archives'              => _x( 'Request Archives', 'The post type archive label', 'wp-sell-services' ),
			'filter_items_list'     => _x( 'Filter requests list', 'Screen reader text', 'wp-sell-services' ),
			'items_list_navigation' => _x( 'Requests list navigation', 'Screen reader text', 'wp-sell-services' ),
			'items_list'            => _x( 'Requests list', 'Screen reader text', 'wp-sell-services' ),
		];

		$args = [
			'labels'              => $labels,
			'description'         => __( 'Buyer requests for services.', 'wp-sell-services' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Will be added to our custom admin menu.
			'query_var'           => true,
			'rewrite'             => [
				'slug'       => $this->get_slug(),
				'with_front' => false,
			],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => true,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => [
				'title',
				'editor',
				'author',
				'thumbnail',
			],
			'show_in_rest'        => true,
			'rest_base'           => 'buyer-requests',
			'rest_namespace'      => 'wpss/v1',
		];

		/**
		 * Filter buyer request post type arguments.
		 *
		 * @param array $args Post type arguments.
		 */
		$args = apply_filters( 'wpss_buyer_request_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get the buyer request post type slug.
	 *
	 * @return string
	 */
	private function get_slug(): string {
		/**
		 * Filter the buyer request post type slug.
		 *
		 * @param string $slug The slug.
		 */
		return apply_filters( 'wpss_buyer_request_slug', 'buyer-request' );
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
			0  => '',
			/* translators: %s: post permalink */
			1  => sprintf( __( 'Request updated. <a target="_blank" href="%s">View Request</a>', 'wp-sell-services' ), esc_url( $permalink ) ),
			2  => __( 'Custom field updated.', 'wp-sell-services' ),
			3  => __( 'Custom field deleted.', 'wp-sell-services' ),
			4  => __( 'Request updated.', 'wp-sell-services' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] )
				? sprintf( __( 'Request restored to revision from %s', 'wp-sell-services' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
				: false,
			/* translators: %s: post permalink */
			6  => sprintf( __( 'Request published. <a href="%s">View Request</a>', 'wp-sell-services' ), esc_url( $permalink ) ),
			7  => __( 'Request saved.', 'wp-sell-services' ),
			/* translators: %s: post permalink */
			8  => sprintf( __( 'Request submitted. <a target="_blank" href="%s">Preview Request</a>', 'wp-sell-services' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
			/* translators: 1: Publish box date format, 2: Post permalink */
			9  => sprintf(
				__( 'Request scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Request</a>', 'wp-sell-services' ),
				date_i18n( __( 'M j, Y @ G:i', 'wp-sell-services' ), strtotime( $post->post_date ) ),
				esc_url( $permalink )
			),
			/* translators: %s: post permalink */
			10 => sprintf( __( 'Request draft updated. <a target="_blank" href="%s">Preview Request</a>', 'wp-sell-services' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
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
			return __( 'Enter request title here', 'wp-sell-services' );
		}

		return $title;
	}
}
