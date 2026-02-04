<?php
/**
 * Service Category Taxonomy
 *
 * Registers and manages the service category taxonomy.
 *
 * @package WPSellServices\Taxonomies
 * @since   1.0.0
 */

namespace WPSellServices\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceCategoryTaxonomy class.
 *
 * @since 1.0.0
 */
class ServiceCategoryTaxonomy {

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'wpss_service_category';

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
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'wpss_service_category_add_form_fields', array( $this, 'add_form_fields' ) );
		add_action( 'wpss_service_category_edit_form_fields', array( $this, 'edit_form_fields' ), 10, 2 );
		add_action( 'created_wpss_service_category', array( $this, 'save_term_meta' ) );
		add_action( 'edited_wpss_service_category', array( $this, 'save_term_meta' ) );
		add_filter( 'manage_edit-wpss_service_category_columns', array( $this, 'add_columns' ) );
		add_filter( 'manage_wpss_service_category_custom_column', array( $this, 'column_content' ), 10, 3 );
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register(): void {
		$labels = array(
			'name'                       => _x( 'Service Categories', 'taxonomy general name', 'wp-sell-services' ),
			'singular_name'              => _x( 'Service Category', 'taxonomy singular name', 'wp-sell-services' ),
			'search_items'               => __( 'Search Categories', 'wp-sell-services' ),
			'popular_items'              => __( 'Popular Categories', 'wp-sell-services' ),
			'all_items'                  => __( 'All Categories', 'wp-sell-services' ),
			'parent_item'                => __( 'Parent Category', 'wp-sell-services' ),
			'parent_item_colon'          => __( 'Parent Category:', 'wp-sell-services' ),
			'edit_item'                  => __( 'Edit Category', 'wp-sell-services' ),
			'view_item'                  => __( 'View Category', 'wp-sell-services' ),
			'update_item'                => __( 'Update Category', 'wp-sell-services' ),
			'add_new_item'               => __( 'Add New Category', 'wp-sell-services' ),
			'new_item_name'              => __( 'New Category Name', 'wp-sell-services' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'wp-sell-services' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'wp-sell-services' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'wp-sell-services' ),
			'not_found'                  => __( 'No categories found.', 'wp-sell-services' ),
			'no_terms'                   => __( 'No categories', 'wp-sell-services' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'wp-sell-services' ),
			'items_list'                 => __( 'Categories list', 'wp-sell-services' ),
			'back_to_items'              => __( '&larr; Back to Categories', 'wp-sell-services' ),
			'menu_name'                  => __( 'Categories', 'wp-sell-services' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'service-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'wpss_manage_services',
			),
		);

		/**
		 * Filter service category taxonomy arguments.
		 *
		 * @since 1.0.0
		 * @param array $args Taxonomy arguments.
		 */
		$args = apply_filters( 'wpss_service_category_taxonomy_args', $args );

		register_taxonomy( self::TAXONOMY, self::POST_TYPE, $args );
	}

	/**
	 * Add custom fields to the add term form.
	 *
	 * @return void
	 */
	public function add_form_fields(): void {
		wp_nonce_field( 'wpss_save_category_meta', 'wpss_category_meta_nonce' );
		?>
		<div class="form-field term-icon-wrap">
			<label for="wpss-category-icon"><?php esc_html_e( 'Icon', 'wp-sell-services' ); ?></label>
			<input type="text" name="wpss_category_icon" id="wpss-category-icon" value="" class="regular-text">
			<p class="description"><?php esc_html_e( 'Enter a Dashicons class (e.g., dashicons-admin-tools) or custom icon class.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="form-field term-image-wrap">
			<label for="wpss-category-image"><?php esc_html_e( 'Category Image', 'wp-sell-services' ); ?></label>
			<input type="hidden" name="wpss_category_image" id="wpss-category-image" value="">
			<div id="wpss-category-image-preview"></div>
			<button type="button" class="button wpss-upload-image" data-target="wpss-category-image"><?php esc_html_e( 'Upload Image', 'wp-sell-services' ); ?></button>
			<button type="button" class="button wpss-remove-image" data-target="wpss-category-image" style="display:none;"><?php esc_html_e( 'Remove Image', 'wp-sell-services' ); ?></button>
		</div>

		<div class="form-field term-color-wrap">
			<label for="wpss-category-color"><?php esc_html_e( 'Category Color', 'wp-sell-services' ); ?></label>
			<input type="text" name="wpss_category_color" id="wpss-category-color" value="" class="wpss-color-picker" data-default-color="#1e40af">
			<p class="description"><?php esc_html_e( 'Select a color for this category.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="form-field term-featured-wrap">
			<label for="wpss-category-featured">
				<input type="checkbox" name="wpss_category_featured" id="wpss-category-featured" value="1">
				<?php esc_html_e( 'Featured Category', 'wp-sell-services' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Featured categories may be displayed prominently on the site.', 'wp-sell-services' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add custom fields to the edit term form.
	 *
	 * @param \WP_Term $term     Current term object.
	 * @param string   $taxonomy Current taxonomy slug.
	 * @return void
	 */
	public function edit_form_fields( \WP_Term $term, string $taxonomy ): void {
		$icon     = get_term_meta( $term->term_id, '_wpss_icon', true );
		$image_id = get_term_meta( $term->term_id, '_wpss_image', true );
		$color    = get_term_meta( $term->term_id, '_wpss_color', true );
		$featured = get_term_meta( $term->term_id, '_wpss_featured', true );
		wp_nonce_field( 'wpss_save_category_meta', 'wpss_category_meta_nonce' );
		?>
		<tr class="form-field term-icon-wrap">
			<th scope="row"><label for="wpss-category-icon"><?php esc_html_e( 'Icon', 'wp-sell-services' ); ?></label></th>
			<td>
				<input type="text" name="wpss_category_icon" id="wpss-category-icon" value="<?php echo esc_attr( $icon ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Enter a Dashicons class (e.g., dashicons-admin-tools) or custom icon class.', 'wp-sell-services' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-image-wrap">
			<th scope="row"><label for="wpss-category-image"><?php esc_html_e( 'Category Image', 'wp-sell-services' ); ?></label></th>
			<td>
				<input type="hidden" name="wpss_category_image" id="wpss-category-image" value="<?php echo esc_attr( $image_id ); ?>">
				<div id="wpss-category-image-preview">
					<?php if ( $image_id ) : ?>
						<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
					<?php endif; ?>
				</div>
				<button type="button" class="button wpss-upload-image" data-target="wpss-category-image"><?php esc_html_e( 'Upload Image', 'wp-sell-services' ); ?></button>
				<button type="button" class="button wpss-remove-image" data-target="wpss-category-image" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove Image', 'wp-sell-services' ); ?></button>
			</td>
		</tr>

		<tr class="form-field term-color-wrap">
			<th scope="row"><label for="wpss-category-color"><?php esc_html_e( 'Category Color', 'wp-sell-services' ); ?></label></th>
			<td>
				<input type="text" name="wpss_category_color" id="wpss-category-color" value="<?php echo esc_attr( $color ); ?>" class="wpss-color-picker" data-default-color="#1e40af">
				<p class="description"><?php esc_html_e( 'Select a color for this category.', 'wp-sell-services' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-featured-wrap">
			<th scope="row"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></th>
			<td>
				<label for="wpss-category-featured">
					<input type="checkbox" name="wpss_category_featured" id="wpss-category-featured" value="1" <?php checked( $featured, '1' ); ?>>
					<?php esc_html_e( 'Featured Category', 'wp-sell-services' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Featured categories may be displayed prominently on the site.', 'wp-sell-services' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta when creating/editing a term.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term_meta( int $term_id ): void {
		// Verify nonce.
		if ( ! isset( $_POST['wpss_category_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpss_category_meta_nonce'] ) ), 'wpss_save_category_meta' ) ) {
			return;
		}

		// Verify capability.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Icon.
		if ( isset( $_POST['wpss_category_icon'] ) ) {
			update_term_meta( $term_id, '_wpss_icon', sanitize_text_field( wp_unslash( $_POST['wpss_category_icon'] ) ) );
		}

		// Image.
		if ( isset( $_POST['wpss_category_image'] ) ) {
			update_term_meta( $term_id, '_wpss_image', absint( $_POST['wpss_category_image'] ) );
		}

		// Color.
		if ( isset( $_POST['wpss_category_color'] ) ) {
			update_term_meta( $term_id, '_wpss_color', sanitize_hex_color( wp_unslash( $_POST['wpss_category_color'] ) ) );
		}

		// Featured.
		$featured = isset( $_POST['wpss_category_featured'] ) ? '1' : '0';
		update_term_meta( $term_id, '_wpss_featured', $featured );
	}

	/**
	 * Add custom columns to the taxonomy list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			if ( 'name' === $key ) {
				$new_columns['wpss_icon'] = __( 'Icon', 'wp-sell-services' );
			}
			$new_columns[ $key ] = $value;
		}

		$new_columns['wpss_featured'] = __( 'Featured', 'wp-sell-services' );

		return $new_columns;
	}

	/**
	 * Output content for custom columns.
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string Column content.
	 */
	public function column_content( string $content, string $column_name, int $term_id ): string {
		switch ( $column_name ) {
			case 'wpss_icon':
				$icon = get_term_meta( $term_id, '_wpss_icon', true );
				if ( $icon ) {
					$content = '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
				} else {
					$content = '—';
				}
				break;

			case 'wpss_featured':
				$featured = get_term_meta( $term_id, '_wpss_featured', true );
				$content  = $featured ? '<span class="dashicons dashicons-star-filled" style="color:#f59e0b;"></span>' : '—';
				break;
		}

		return $content;
	}

	/**
	 * Get featured categories.
	 *
	 * @param int $limit Number of categories to return.
	 * @return array<\WP_Term> Array of featured categories.
	 */
	public static function get_featured( int $limit = 8 ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => $limit,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wpss_featured',
						'value' => '1',
					),
				),
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Get category icon.
	 *
	 * @param int $term_id Term ID.
	 * @return string Icon class.
	 */
	public static function get_icon( int $term_id ): string {
		return get_term_meta( $term_id, '_wpss_icon', true ) ?: '';
	}

	/**
	 * Get category image.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $size    Image size.
	 * @return string Image HTML.
	 */
	public static function get_image( int $term_id, string $size = 'medium' ): string {
		$image_id = get_term_meta( $term_id, '_wpss_image', true );
		return $image_id ? wp_get_attachment_image( $image_id, $size ) : '';
	}

	/**
	 * Get category color.
	 *
	 * @param int $term_id Term ID.
	 * @return string Hex color.
	 */
	public static function get_color( int $term_id ): string {
		return get_term_meta( $term_id, '_wpss_color', true ) ?: '#1e40af';
	}
}
