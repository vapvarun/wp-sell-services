<?php
/**
 * All Services list screen: the columns and filters an owner manages a catalog by.
 *
 * @package WPSellServices\Admin
 * @since   1.8.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

use WPSellServices\PostTypes\ServicePostType;

/**
 * Adds thumbnail, starting price, orders, rating and featured columns, and
 * category and moderation-state filters, to edit.php?post_type=wpss_service.
 *
 * The list showed title, author, categories and date only, so at a few hundred
 * services title search was the only way to find one (Basecamp 10337190248).
 * Vendor filtering is the Author column: each name links to that vendor's
 * services, which scales where a dropdown of every vendor would not. Every
 * value comes from post meta the list query already primes; thumbnails are
 * primed once for the page.
 */
class ServiceListScreen {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'manage_' . ServicePostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . ServicePostType::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters' ) );
		add_filter( 'default_hidden_columns', array( $this, 'hidden_columns' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'featured_state' ), 10, 2 );
	}

	/**
	 * Mark featured services beside the title, the way WordPress marks Draft.
	 *
	 * @param array<string, string> $states Post states.
	 * @param \WP_Post              $post   Post.
	 * @return array<string, string>
	 */
	public function featured_state( array $states, \WP_Post $post ): array {
		if ( ServicePostType::POST_TYPE === $post->post_type && '1' === (string) get_post_meta( $post->ID, '_wpss_featured', true ) ) {
			$states['wpss_featured'] = __( 'Featured', 'wp-sell-services' );
		}

		return $states;
	}

	/**
	 * Hide Tags by default so the title keeps room; Screen Options brings it back.
	 *
	 * @param string[]   $hidden Hidden column keys.
	 * @param \WP_Screen $screen Current screen.
	 * @return string[]
	 */
	public function hidden_columns( array $hidden, \WP_Screen $screen ): array {
		if ( 'edit-' . ServicePostType::POST_TYPE === $screen->id ) {
			$hidden[] = 'taxonomy-wpss_service_tag';
		}

		return $hidden;
	}

	/**
	 * Add the columns around the title.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['wpss_thumb'] = '<span class="screen-reader-text">' . esc_html__( 'Image', 'wp-sell-services' ) . '</span>';
			}

			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['wpss_price']  = __( 'Starting price', 'wp-sell-services' );
				$out['wpss_orders'] = __( 'Orders / rating', 'wp-sell-services' );
			}
		}

		if ( isset( $out['author'] ) ) {
			$out['author'] = __( 'Vendor', 'wp-sell-services' );
		}

		return $out;
	}

	/**
	 * Render one of our columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Service ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'wpss_thumb':
				static $primed = false;
				if ( ! $primed ) {
					update_post_thumbnail_cache();
					$primed = true;
				}
				$thumb = get_the_post_thumbnail( $post_id, array( 48, 48 ), array( 'class' => 'wpss-list-thumb' ) );
				echo $thumb ? wp_kses_post( $thumb ) : '<span class="wpss-list-thumb wpss-list-thumb--empty" aria-hidden="true"></span>';
				break;

			case 'wpss_price':
				$price = (float) get_post_meta( $post_id, '_wpss_starting_price', true );
				echo $price > 0 ? esc_html( wpss_format_price( $price ) ) : '&mdash;';
				break;

			case 'wpss_orders':
				$reviews = (int) get_post_meta( $post_id, '_wpss_review_count', true );
				echo esc_html(
					sprintf(
						/* translators: %s: number of orders */
						_n( '%s order', '%s orders', (int) get_post_meta( $post_id, '_wpss_order_count', true ), 'wp-sell-services' ),
						number_format_i18n( (int) get_post_meta( $post_id, '_wpss_order_count', true ) )
					)
				);
				echo '<br><span class="description">';
				if ( $reviews > 0 ) {
					echo esc_html(
						sprintf(
							/* translators: 1: average rating out of 5, 2: number of reviews */
							_n( '%1$s from %2$s review', '%1$s from %2$s reviews', $reviews, 'wp-sell-services' ),
							number_format_i18n( (float) get_post_meta( $post_id, '_wpss_rating_average', true ), 1 ),
							number_format_i18n( $reviews )
						)
					);
				} else {
					esc_html_e( 'No reviews yet', 'wp-sell-services' );
				}
				echo '</span>';
				break;
		}
	}

	/**
	 * Category and moderation-state dropdowns above the list.
	 *
	 * @param string $post_type Post type of the screen.
	 * @return void
	 */
	public function filters( string $post_type ): void {
		if ( ServicePostType::POST_TYPE !== $post_type ) {
			return;
		}

		wp_dropdown_categories(
			array(
				'taxonomy'        => 'wpss_service_category',
				'name'            => 'wpss_service_category',
				'value_field'     => 'slug',
				'show_option_all' => __( 'All categories', 'wp-sell-services' ),
				'hierarchical'    => true,
				'hide_empty'      => false,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
				'selected'        => isset( $_GET['wpss_service_category'] ) ? sanitize_title( wp_unslash( $_GET['wpss_service_category'] ) ) : '',
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$current = isset( $_GET['wpss_moderation_state'] ) ? sanitize_key( wp_unslash( $_GET['wpss_moderation_state'] ) ) : '';
		$states  = array(
			''         => __( 'All moderation states', 'wp-sell-services' ),
			'pending'  => __( 'Pending review', 'wp-sell-services' ),
			'approved' => __( 'Approved', 'wp-sell-services' ),
			'rejected' => __( 'Rejected', 'wp-sell-services' ),
		);
		?>
		<label for="wpss-filter-moderation" class="screen-reader-text"><?php esc_html_e( 'Filter by moderation state', 'wp-sell-services' ); ?></label>
		<select name="wpss_moderation_state" id="wpss-filter-moderation">
			<?php foreach ( $states as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply the moderation-state filter to the list query.
	 *
	 * The category dropdown needs nothing here: its name is the taxonomy's
	 * query var.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function apply_filters( \WP_Query $query ): void {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() || ServicePostType::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$state = isset( $_GET['wpss_moderation_state'] ) ? sanitize_key( wp_unslash( $_GET['wpss_moderation_state'] ) ) : '';

		if ( in_array( $state, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$query->set( 'wpss_moderation_state', $state );
		}
	}
}
