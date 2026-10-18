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

use WPSellServices\Admin\Metaboxes\ServiceMetabox;
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
		add_filter( 'bulk_actions-edit-' . ServicePostType::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . ServicePostType::POST_TYPE, array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );
		add_action( 'admin_notices', array( $this, 'publish_blocked_notice' ) );
	}

	/**
	 * Say why a service the owner tried to publish is still a draft.
	 *
	 * Shown on that service's editor and on the Services list for as long as the service is a draft with a
	 * refused publish on record. The reasons are read live from the publish rules, never stored, so the
	 * notice is accurate however many screens passed since the save and disappears when the service qualifies.
	 *
	 * @return void
	 */
	public function publish_blocked_notice(): void {
		$screen = get_current_screen();

		if ( ! $screen || ServicePostType::POST_TYPE !== $screen->post_type || ! in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
			return;
		}

		if ( 'post' === $screen->base ) {
			$post = get_post();
			$ids  = $post && 'draft' === $post->post_status && get_post_meta( $post->ID, ServiceMetabox::PUBLISH_BLOCKED_META, true ) ? array( $post->ID ) : array();
		} else {
			$ids = get_posts(
				array(
					'post_type'      => ServicePostType::POST_TYPE,
					'post_status'    => 'draft',
					'meta_key'       => ServiceMetabox::PUBLISH_BLOCKED_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one key, capped page.
					'posts_per_page' => 5,
					'orderby'        => 'modified',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
		}

		foreach ( $ids as $id ) {
			$errors = wpss_get_service_publish_errors( (int) $id );

			if ( ! $errors ) {
				continue;
			}

			echo '<div class="notice notice-warning"><p><strong>';
			echo esc_html(
				sprintf(
					/* translators: %s: service title */
					__( '"%s" was saved as a draft, not published. It is missing:', 'wp-sell-services' ),
					get_the_title( (int) $id )
				)
			);
			echo '</strong></p><ul class="ul-disc">';
			foreach ( $errors as $message ) {
				echo '<li>' . esc_html( $message ) . '</li>';
			}
			echo '</ul>';
			if ( 'edit' === $screen->base ) {
				printf( '<p><a href="%s">%s</a></p>', esc_url( (string) get_edit_post_link( (int) $id, 'raw' ) ), esc_html__( 'Finish this service', 'wp-sell-services' ) );
			}
			echo '</div>';
		}
	}

	/**
	 * Add Feature / Unfeature to the bulk menu, for those who may curate.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		if ( wpss_user_can_feature_service() ) {
			$actions['wpss_feature']   = __( 'Mark as featured', 'wp-sell-services' );
			$actions['wpss_unfeature'] = __( 'Remove from featured', 'wp-sell-services' );
		}

		return $actions;
	}

	/**
	 * Apply a Feature / Unfeature bulk action.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'wpss_feature', 'wpss_unfeature' ), true ) ) {
			return $redirect;
		}

		$done = 0;

		foreach ( array_map( 'absint', (array) $ids ) as $id ) {
			if ( ServicePostType::POST_TYPE !== get_post_type( $id ) || ! wpss_user_can_feature_service( $id ) ) {
				continue;
			}

			wpss_set_service_featured( $id, 'wpss_feature' === $action );
			++$done;
		}

		return add_query_arg(
			array(
				'wpss_featured_done' => $done,
				'wpss_featured_mode' => 'wpss_feature' === $action ? 'on' : 'off',
			),
			remove_query_arg( array( 'wpss_featured_done', 'wpss_featured_mode' ), $redirect )
		);
	}

	/**
	 * Say how many services the bulk action changed.
	 *
	 * @return void
	 */
	public function bulk_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only counter on a redirect.
		if ( ! isset( $_GET['wpss_featured_done'], $_GET['wpss_featured_mode'] ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = absint( wp_unslash( $_GET['wpss_featured_done'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$on = 'on' === sanitize_key( wp_unslash( $_GET['wpss_featured_mode'] ) );

		if ( $on ) {
			/* translators: %d: number of services */
			$message = _n( '%d service marked as featured.', '%d services marked as featured.', $count, 'wp-sell-services' );
		} else {
			/* translators: %d: number of services */
			$message = _n( '%d service removed from featured.', '%d services removed from featured.', $count, 'wp-sell-services' );
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( $message, $count ) ) );
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
