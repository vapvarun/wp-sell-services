<?php
/**
 * Service Metabox
 *
 * Custom metabox for Service post type.
 *
 * @package WPSellServices\Admin\Metaboxes
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Metaboxes;

use WPSellServices\PostTypes\ServicePostType;
use WPSellServices\Assets\ScriptRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceMetabox class.
 *
 * @since 1.0.0
 */
class ServiceMetabox {

	/**
	 * Initialize metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'save_post_' . ServicePostType::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes(): void {
		// Single consolidated metabox with tabbed/wizard interface.
		add_meta_box(
			'wpss_service_data',
			__( 'Service Data', 'wp-sell-services' ),
			array( $this, 'render_service_data_metabox' ),
			ServicePostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Canonical requirement field types (value => label).
	 *
	 * Single source of truth for the requirement-type enum: consumed by both the
	 * render templates and the save validator so the allowed values can never drift
	 * between the form and the sanitiser.
	 *
	 * @return array<string, string>
	 */
	private function get_requirement_types(): array {
		return array(
			'text'     => __( 'Short Text', 'wp-sell-services' ),
			'textarea' => __( 'Long Text', 'wp-sell-services' ),
			'number'   => __( 'Number', 'wp-sell-services' ),
			'checkbox' => __( 'Yes/No', 'wp-sell-services' ),
			'select'   => __( 'Dropdown', 'wp-sell-services' ),
			'radio'    => __( 'Multiple Choice', 'wp-sell-services' ),
			'file'     => __( 'File Upload', 'wp-sell-services' ),
			'date'     => __( 'Date', 'wp-sell-services' ),
		);
	}

	/**
	 * Requirement types that expose a choices field (dropdown / multiple choice).
	 *
	 * @return array<int, string>
	 */
	private function get_choice_requirement_types(): array {
		return array( 'select', 'radio' );
	}

	/**
	 * Canonical add-on field types (value => label).
	 *
	 * @return array<string, string>
	 */
	private function get_addon_field_types(): array {
		return array(
			'checkbox' => __( 'Checkbox (Yes/No)', 'wp-sell-services' ),
			'quantity' => __( 'Quantity Selector', 'wp-sell-services' ),
			'dropdown' => __( 'Dropdown Select', 'wp-sell-services' ),
			'text'     => __( 'Text Input', 'wp-sell-services' ),
		);
	}

	/**
	 * Canonical add-on price types (value => label).
	 *
	 * @return array<string, string>
	 */
	private function get_addon_price_types(): array {
		return array(
			'flat'           => __( 'Flat Price', 'wp-sell-services' ),
			'percentage'     => __( 'Percentage of Order', 'wp-sell-services' ),
			'quantity_based' => __( 'Per Quantity', 'wp-sell-services' ),
		);
	}

	/**
	 * Enqueue metabox assets.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		global $post_type;

		if ( ServicePostType::POST_TYPE !== $post_type ) {
			return;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		$plugin_url = WPSS_PLUGIN_URL;
		$version    = WPSS_VERSION;

		// Service edit specific styles.
		wp_enqueue_style(
			'wpss-service-edit',
			$plugin_url . 'assets/css/service-edit.css',
			array(),
			$version
		);

		// Service edit specific scripts.
		ScriptRegistry::enqueue(
			'wpss-service-edit',
			'assets/js/service-edit.js',
			array( 'jquery' ),
			true,
			$version
		);

		wp_localize_script(
			'wpss-service-edit',
			'wpssServiceEdit',
			array(
				'i18n' => array(
					'next'     => __( 'Next', 'wp-sell-services' ),
					'previous' => __( 'Previous', 'wp-sell-services' ),
					'finish'   => __( 'Finish', 'wp-sell-services' ),
					'skip'     => __( 'Skip to full editor', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Render service details metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'wpss_service_meta', 'wpss_service_nonce' );

		$status = get_post_meta( $post->ID, '_wpss_status', true );
		$status = ! empty( $status ) ? $status : 'active';
		?>
		<div class="wpss-details-wrapper">
			<div class="wpss-details-grid">
				<div class="wpss-detail-card">
					<div class="wpss-detail-icon">
						<i data-lucide="eye" class="wpss-icon" aria-hidden="true"></i>
					</div>
					<div class="wpss-detail-content">
						<label for="wpss_status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></label>
						<div class="wpss-detail-input">
							<select id="wpss_status" name="wpss_status" class="wpss-status-select">
								<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'wp-sell-services' ); ?></option>
								<option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'wp-sell-services' ); ?></option>
								<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'wp-sell-services' ); ?></option>
							</select>
						</div>
						<p class="description"><?php esc_html_e( 'Control service visibility', 'wp-sell-services' ); ?></p>
					</div>
				</div>
			</div>
			<p class="wpss-details-note">
				<i data-lucide="info" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Delivery time and revisions are configured per package below.', 'wp-sell-services' ); ?>
			</p>

			<?php $this->render_extra_fields( $post ); ?>
		</div>
		<?php
	}

	/**
	 * Apply the `wpss_service_meta_fields` filter and render the returned fields.
	 *
	 * Shared by the active Overview panel and the legacy details metabox so the
	 * extension surface renders identically wherever it is used.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_extra_fields( \WP_Post $post ): void {
		/**
		 * Filter additional service meta fields rendered in the metabox.
		 *
		 * Pro uses this to add recurring billing toggle and other options.
		 *
		 * @since 1.1.0
		 *
		 * @param array $extra_fields Array of extra field HTML strings.
		 * @param int   $post_id      The service post ID.
		 */
		$extra_fields = apply_filters( 'wpss_service_meta_fields', array(), $post->ID );

		if ( empty( $extra_fields ) ) {
			return;
		}

		$allowed_html = $this->get_extra_fields_allowed_html();
		?>
		<div class="wpss-extra-fields" style="margin-top: 15px;">
			<?php
			foreach ( $extra_fields as $field_html ) {
				echo wp_kses( (string) $field_html, $allowed_html );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Allowed HTML for extension-supplied metabox fields.
	 *
	 * `wp_kses_post()` strips form elements (`<input>`, `<select>`, `<option>`,
	 * `<textarea>`, `<label>`), which silently removed the controls Pro injects
	 * through `wpss_service_meta_fields` — only headings and label text
	 * survived. This allow-list extends the post context with the form tags
	 * those fields need while still routing all output through wp_kses()
	 * (extension HTML is never echoed raw).
	 *
	 * @return array<string, mixed> Allowed tags/attributes for wp_kses().
	 */
	private function get_extra_fields_allowed_html(): array {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['input']    = array(
			'type'        => true,
			'id'          => true,
			'name'        => true,
			'value'       => true,
			'class'       => true,
			'style'       => true,
			'checked'     => true,
			'placeholder' => true,
			'min'         => true,
			'max'         => true,
			'step'        => true,
			'required'    => true,
			'readonly'    => true,
			'disabled'    => true,
			'data-*'      => true,
			'aria-*'      => true,
		);
		$allowed['select']   = array(
			'id'       => true,
			'name'     => true,
			'class'    => true,
			'style'    => true,
			'multiple' => true,
			'disabled' => true,
			'data-*'   => true,
			'aria-*'   => true,
		);
		$allowed['option']   = array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
		);
		$allowed['optgroup'] = array(
			'label'    => true,
			'disabled' => true,
		);
		$allowed['textarea'] = array(
			'id'          => true,
			'name'        => true,
			'class'       => true,
			'style'       => true,
			'rows'        => true,
			'cols'        => true,
			'placeholder' => true,
			'readonly'    => true,
			'disabled'    => true,
			'aria-*'      => true,
		);
		$allowed['label']    = array(
			'for'   => true,
			'class' => true,
			'style' => true,
		);
		$allowed['fieldset'] = array(
			'class' => true,
			'style' => true,
		);
		$allowed['legend']   = array(
			'class' => true,
			'style' => true,
		);

		return $allowed;
	}


	/**
	 * Render a single package item.
	 *
	 * @param int   $index   Package index.
	 * @param array $package Package data.
	 * @return void
	 */
	private function render_package_item( int $index, array $package ): void {
		$is_first     = ( 0 === $index );
		$package_name = ! empty( $package['name'] ) ? $package['name'] : __( 'New Package', 'wp-sell-services' );
		$price        = ! empty( $package['price'] ) ? (float) $package['price'] : 0;
		?>
		<div class="wpss-package-item" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="wpss-package-header">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<span class="wpss-package-title"><?php echo esc_html( $package_name ); ?></span>
				<span class="wpss-package-price-display">
					<?php if ( $price > 0 ) : ?>
						<?php echo esc_html( wpss_format_price( $price ) ); ?>
					<?php endif; ?>
				</span>
				<div class="wpss-package-actions">
					<button type="button" class="wpss-package-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<i data-lucide="chevron-down" class="wpss-icon" aria-hidden="true"></i>
					</button>
					<?php if ( ! $is_first ) : ?>
						<button type="button" class="wpss-remove-package" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
						</button>
					<?php endif; ?>
				</div>
			</div>
			<div class="wpss-package-body">
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-wide">
						<label><?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_packages[<?php echo esc_attr( $index ); ?>][name]" aria-label="<?php esc_attr_e( 'Package name', 'wp-sell-services' ); ?>"
								value="<?php echo esc_attr( $package['name'] ?? '' ); ?>"
								class="widefat wpss-package-name-input"
								placeholder="<?php esc_attr_e( 'e.g., Standard, Premium, Enterprise', 'wp-sell-services' ); ?>">
					</div>
					<div class="wpss-package-field">
						<label>
							<i data-lucide="banknote" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][price]" aria-label="<?php esc_attr_e( 'Package price', 'wp-sell-services' ); ?>"
									value="<?php echo esc_attr( $package['price'] ?? '' ); ?>"
									class="wpss-package-price-input"
									min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>" placeholder="<?php echo esc_attr( wpss_get_price_input_attrs()['placeholder'] ); ?>">
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea name="wpss_packages[<?php echo esc_attr( $index ); ?>][description]" aria-label="<?php esc_attr_e( 'Package description', 'wp-sell-services' ); ?>"
								rows="2" class="widefat"
								placeholder="<?php esc_attr_e( 'Describe what\'s included in this package...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $package['description'] ?? '' ); ?></textarea>
					</div>
				</div>
				<div class="wpss-package-row wpss-package-row-grid">
					<div class="wpss-package-field">
						<label>
							<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][delivery_days]" aria-label="<?php esc_attr_e( 'Delivery time in days', 'wp-sell-services' ); ?>"
									value="<?php echo esc_attr( ! empty( $package['delivery_days'] ) ? $package['delivery_days'] : '' ); ?>"
									min="1" max="365" placeholder="7">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-package-field">
						<label>
							<i data-lucide="refresh-cw" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][revisions]" aria-label="<?php esc_attr_e( 'Number of revisions', 'wp-sell-services' ); ?>"
									value="<?php echo esc_attr( ! empty( $package['revisions'] ) ? $package['revisions'] : '' ); ?>"
									min="0" max="20" placeholder="2">
							<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label>
							<i data-lucide="check-circle-2" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?>
						</label>
						<textarea name="wpss_packages[<?php echo esc_attr( $index ); ?>][features]" aria-label="<?php esc_attr_e( 'Features included', 'wp-sell-services' ); ?>"
								rows="3" class="widefat"
								placeholder="<?php esc_attr_e( "Feature 1\nFeature 2\nFeature 3", 'wp-sell-services' ); ?>"><?php echo esc_textarea( implode( "\n", (array) ( $package['features'] ?? array() ) ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Enter one feature per line', 'wp-sell-services' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}



	/**
	 * Render a single requirement item.
	 *
	 * @param int   $index Requirement index.
	 * @param array $req   Requirement data.
	 * @return void
	 */
	private function render_requirement_item( int $index, array $req ): void {
		$type         = $req['type'] ?? 'text';
		$show_choices = in_array( $type, $this->get_choice_requirement_types(), true );
		?>
		<div class="wpss-requirement-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="wpss-requirement-row">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<div class="wpss-requirement-fields">
					<div class="wpss-requirement-main">
						<input type="text" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][question]"
								value="<?php echo esc_attr( $req['question'] ?? '' ); ?>"
								aria-label="<?php esc_attr_e( 'Requirement question', 'wp-sell-services' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter your question...', 'wp-sell-services' ); ?>" class="widefat">
					</div>
					<div class="wpss-requirement-options">
						<select name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][type]" class="wpss-requirement-type"
								aria-label="<?php esc_attr_e( 'Requirement field type', 'wp-sell-services' ); ?>">
							<?php foreach ( $this->get_requirement_types() as $req_type_value => $req_type_label ) : ?>
								<option value="<?php echo esc_attr( $req_type_value ); ?>" <?php selected( $type, $req_type_value ); ?>><?php echo esc_html( $req_type_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<label class="wpss-requirement-required">
							<input type="checkbox" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][required]"
									value="1" <?php checked( ! empty( $req['required'] ) ); ?>>
							<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
						</label>
						<button type="button" class="wpss-remove-requirement" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>" aria-label="<?php esc_attr_e( 'Remove requirement', 'wp-sell-services' ); ?>">
							<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
						</button>
					</div>
					<div class="wpss-requirement-choices" <?php echo $show_choices ? '' : 'style="display:none;"'; ?>>
						<input type="text" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][choices]"
								value="<?php echo esc_attr( $req['choices'] ?? '' ); ?>"
								aria-label="<?php esc_attr_e( 'Requirement choices', 'wp-sell-services' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter choices separated by comma (e.g., Option 1, Option 2, Option 3)', 'wp-sell-services' ); ?>" class="widefat">
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render gallery metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_gallery_metabox( \WP_Post $post ): void {
		$gallery_raw = get_post_meta( $post->ID, '_wpss_gallery', true );
		$gallery_ids = wpss_get_gallery_ids( $gallery_raw );
		?>
		<div class="wpss-gallery-wrapper">
			<input type="hidden" name="wpss_gallery_present" value="1">
			<div id="wpss-gallery-images" class="wpss-gallery-grid">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php if ( $attachment_id ) : ?>
						<div class="wpss-gallery-item" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
							<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
							<button type="button" class="wpss-remove-image" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">&times;</button>
							<input type="hidden" name="wpss_gallery[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" id="wpss-add-gallery-images">
				<i data-lucide="images" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Add Images', 'wp-sell-services' ); ?>
			</button>
			<p class="description"><?php esc_html_e( 'Drag to reorder images.', 'wp-sell-services' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render stats metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_stats_metabox( \WP_Post $post ): void {
		$order_count    = get_post_meta( $post->ID, '_wpss_order_count', true );
		$order_count    = ! empty( $order_count ) ? $order_count : 0;
		$review_count   = get_post_meta( $post->ID, '_wpss_review_count', true );
		$review_count   = ! empty( $review_count ) ? $review_count : 0;
		$average_rating = get_post_meta( $post->ID, '_wpss_rating_average', true );
		$average_rating = ! empty( $average_rating ) ? $average_rating : 0;
		$view_count     = get_post_meta( $post->ID, '_wpss_views', true );
		$view_count     = ! empty( $view_count ) ? $view_count : 0;
		?>
		<div class="wpss-stats-wrapper">
			<div class="wpss-stats-grid">
				<div class="wpss-stat-item">
					<i data-lucide="shopping-cart" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( (string) $order_count ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-item">
					<i data-lucide="star" class="wpss-icon wpss-stat-icon" style="color: #f5a623;" aria-hidden="true"></i>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( number_format( (float) $average_rating, 1 ) ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-item">
					<i data-lucide="quote" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( (string) $review_count ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-item">
					<i data-lucide="eye" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( (string) $view_count ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Views', 'wp-sell-services' ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


	/**
	 * Get add-ons for the admin editor with the wizard meta-key fallback.
	 *
	 * Reads through wpss_get_service_extras() (`_wpss_extras` first, then
	 * `_wpss_addons`) so wizard-created services surface their add-ons in the
	 * metabox. Wizard rows store the extra delivery days as `extra_days`
	 * (legacy rows as `delivery_time`) while the metabox fields use
	 * `delivery_days_extra`, so the value is mapped with the same coalesce
	 * wpss_resolve_checkout_addons() already uses. The remaining metabox-only
	 * fields (field_type, price_type, quantities, options, is_required) fall
	 * back to the render defaults.
	 *
	 * @param int $post_id Service post ID.
	 * @return array<int, array<string, mixed>> Add-on rows in the metabox field shape.
	 */
	private function get_addons_for_editor( int $post_id ): array {
		$addons = wpss_get_service_extras( $post_id );

		$normalized = array();
		foreach ( $addons as $addon ) {
			if ( ! is_array( $addon ) ) {
				continue;
			}
			$addon['delivery_days_extra'] = (int) ( $addon['delivery_days_extra'] ?? $addon['extra_days'] ?? $addon['delivery_time'] ?? 0 );
			$normalized[]                 = $addon;
		}

		return $normalized;
	}

	/**
	 * Render a single addon item.
	 *
	 * @param int   $index       Addon index.
	 * @param array $addon       Addon data.
	 * @param array $field_types Available field types.
	 * @param array $price_types Available price types.
	 * @return void
	 */
	private function render_addon_item( int $index, array $addon, array $field_types, array $price_types ): void {
		$field_type    = $addon['field_type'] ?? 'checkbox';
		$price_type    = $addon['price_type'] ?? 'flat';
		$show_quantity = 'quantity' === $field_type;
		$show_dropdown = 'dropdown' === $field_type;
		$title         = $addon['title'] ?? __( 'New Add-on', 'wp-sell-services' );
		?>
		<div class="wpss-addon-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="wpss-addon-header">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<span class="wpss-addon-title"><?php echo esc_html( $title ); ?></span>
				<div class="wpss-addon-actions">
					<button type="button" class="wpss-addon-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<i data-lucide="chevron-down" class="wpss-icon" aria-hidden="true"></i>
					</button>
					<button type="button" class="wpss-remove-addon" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
					</button>
				</div>
			</div>
			<div class="wpss-addon-body">
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_title" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][title]"
								value="<?php echo esc_attr( $addon['title'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g., Extra Fast Delivery', 'wp-sell-services' ); ?>" class="widefat wpss-addon-title-input">
					</div>
				</div>
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_description" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][description]" rows="2" class="widefat"
									placeholder="<?php esc_attr_e( 'Brief description of this add-on...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $addon['description'] ?? '' ); ?></textarea>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_field_type"><?php esc_html_e( 'Field Type', 'wp-sell-services' ); ?></label>
						<select id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_field_type" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][field_type]" class="wpss-addon-field-type">
							<?php foreach ( $field_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_price_type"><?php esc_html_e( 'Price Type', 'wp-sell-services' ); ?></label>
						<select id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_price_type" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][price_type]" class="wpss-addon-price-type">
							<?php foreach ( $price_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $price_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_price"><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
							<input type="number" id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_price" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][price]"
									value="<?php echo esc_attr( $addon['price'] ?? '' ); ?>"
									min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>" placeholder="<?php echo esc_attr( wpss_get_price_input_attrs()['placeholder'] ); ?>">
						</div>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid wpss-addon-quantity-fields" <?php echo $show_quantity ? '' : 'style="display: none;"'; ?>>
					<div class="wpss-addon-field">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_min_quantity"><?php esc_html_e( 'Min Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_min_quantity" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][min_quantity]"
								value="<?php echo esc_attr( $addon['min_quantity'] ?? '1' ); ?>" min="1" max="100">
					</div>
					<div class="wpss-addon-field">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_max_quantity"><?php esc_html_e( 'Max Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_max_quantity" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][max_quantity]"
								value="<?php echo esc_attr( $addon['max_quantity'] ?? '10' ); ?>" min="1" max="100">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-dropdown-fields" <?php echo $show_dropdown ? '' : 'style="display: none;"'; ?>>
					<div class="wpss-addon-field wpss-addon-field-full">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_options"><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_options" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][options]" class="widefat"
								value="<?php echo esc_attr( $addon['options'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Option 1, Option 2, Option 3 (comma separated)', 'wp-sell-services' ); ?>">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label for="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_delivery_days_extra"><?php esc_html_e( 'Extra Delivery Days', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-suffix">
							<input type="number" id="wpss_addon_<?php echo esc_attr( (string) $index ); ?>_delivery_days_extra" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][delivery_days_extra]"
									value="<?php echo esc_attr( $addon['delivery_days_extra'] ?? '0' ); ?>" min="0" max="30">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-addon-field wpss-addon-field-checkbox">
						<label>
							<input type="checkbox" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][is_required]"
									value="1" <?php checked( ! empty( $addon['is_required'] ) ); ?>>
							<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['wpss_service_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wpss_service_nonce'] ), 'wpss_service_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save status field.
		// Note: Delivery time and revisions are now per-package only (see packages below).
		if ( isset( $_POST['wpss_status'] ) ) {
			update_post_meta( $post_id, '_wpss_status', sanitize_key( $_POST['wpss_status'] ) );
		}

		// Save packages (indexed array format).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$packages_data = isset( $_POST['wpss_packages'] ) ? wp_unslash( $_POST['wpss_packages'] ) : array();
		if ( is_array( $packages_data ) && ! empty( $packages_data ) ) {
			$packages = array();
			foreach ( $packages_data as $package ) {
				// Only save packages with name or price.
				if ( ! empty( $package['name'] ) || ! empty( $package['price'] ) ) {
					// Revisions: preserve -1 (Unlimited). absint() would turn the
					// wizard's Unlimited (-1) into 1 on every admin re-save.
					$revisions_raw = isset( $package['revisions'] ) ? (int) $package['revisions'] : 0;
					$revisions_val = $revisions_raw < 0 ? -1 : $revisions_raw;

					$packages[] = array(
						'name'          => sanitize_text_field( $package['name'] ?? '' ),
						'description'   => sanitize_textarea_field( $package['description'] ?? '' ),
						'price'         => (float) ( $package['price'] ?? 0 ),
						'delivery_days' => absint( $package['delivery_days'] ?? 0 ),
						'revisions'     => $revisions_val,
						'features'      => array_filter( array_map( 'sanitize_text_field', explode( "\n", $package['features'] ?? '' ) ) ),
					);
				}
			}
			update_post_meta( $post_id, '_wpss_packages', $packages );

			// Update computed meta values from packages.
			$prices        = array_filter( wp_list_pluck( $packages, 'price' ) );
			$delivery_days = array_filter( wp_list_pluck( $packages, 'delivery_days' ) );
			$revisions     = wp_list_pluck( $packages, 'revisions' );

			// Starting price = minimum package price.
			$starting_price = ! empty( $prices ) ? min( $prices ) : 0;
			update_post_meta( $post_id, '_wpss_starting_price', $starting_price );

			// Fastest delivery = minimum delivery days (for SEO/display).
			// Both delivery meta keys are kept in sync so meta-query filters
			// (archive + REST) match services regardless of creation path.
			$fastest_delivery = ! empty( $delivery_days ) ? min( $delivery_days ) : 7;
			update_post_meta( $post_id, '_wpss_fastest_delivery', $fastest_delivery );
			update_post_meta( $post_id, '_wpss_delivery_days', $fastest_delivery );

			// Max revisions = maximum revisions across packages. A package with
			// -1 (Unlimited) is the highest possible, so it wins over any finite
			// count instead of being beaten numerically by max().
			if ( in_array( -1, array_map( 'intval', $revisions ), true ) ) {
				$max_revisions = -1;
			} else {
				$max_revisions = ! empty( $revisions ) ? max( $revisions ) : 0;
			}
			update_post_meta( $post_id, '_wpss_max_revisions', $max_revisions );
			update_post_meta( $post_id, '_wpss_revisions', $max_revisions );
		}

		// Save FAQs.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$faqs_data = isset( $_POST['wpss_faqs'] ) ? wp_unslash( $_POST['wpss_faqs'] ) : array();
		if ( is_array( $faqs_data ) && ! empty( $faqs_data ) ) {
			$faqs = array();
			foreach ( $faqs_data as $faq ) {
				if ( ! empty( $faq['question'] ) ) {
					$faqs[] = array(
						'question' => sanitize_text_field( $faq['question'] ),
						'answer'   => sanitize_textarea_field( $faq['answer'] ?? '' ),
					);
				}
			}
			update_post_meta( $post_id, '_wpss_faqs', $faqs );
		} elseif ( isset( $_POST['wpss_faqs_present'] ) ) {
			// The FAQ panel was on the form and submitted zero rows: the admin
			// removed every FAQ, so clear the meta. Without this sentinel guard
			// an empty submit silently kept the old FAQs (could not clear them).
			delete_post_meta( $post_id, '_wpss_faqs' );
		}

		// Save requirements.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$requirements_data = isset( $_POST['wpss_requirements'] ) ? wp_unslash( $_POST['wpss_requirements'] ) : array();
		if ( is_array( $requirements_data ) && ! empty( $requirements_data ) ) {
			$requirements = array();
			$valid_types  = array_keys( $this->get_requirement_types() );
			foreach ( $requirements_data as $req ) {
				if ( ! empty( $req['question'] ) ) {
					$type = sanitize_key( $req['type'] ?? 'text' );
					if ( ! in_array( $type, $valid_types, true ) ) {
						$type = 'text';
					}
					$requirement = array(
						'question' => sanitize_text_field( $req['question'] ),
						'type'     => $type,
						'required' => ! empty( $req['required'] ),
					);
					// Save choices for select and radio types.
					if ( in_array( $type, $this->get_choice_requirement_types(), true ) && ! empty( $req['choices'] ) ) {
						$requirement['choices'] = sanitize_text_field( $req['choices'] );
					}
					$requirements[] = $requirement;
				}
			}
			update_post_meta( $post_id, '_wpss_requirements', $requirements );
		} elseif ( isset( $_POST['wpss_requirements_present'] ) ) {
			// The requirements panel was on the form and submitted zero rows:
			// the admin removed every requirement, so clear the meta (same
			// sentinel pattern as FAQs / add-ons / gallery).
			delete_post_meta( $post_id, '_wpss_requirements' );
		}

		// Save addons.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$addons_data = isset( $_POST['wpss_addons'] ) ? wp_unslash( $_POST['wpss_addons'] ) : array();
		if ( is_array( $addons_data ) && ! empty( $addons_data ) ) {
			$addons      = array();
			$valid_types = array_keys( $this->get_addon_field_types() );
			$valid_price = array_keys( $this->get_addon_price_types() );

			foreach ( $addons_data as $addon ) {
				if ( ! empty( $addon['title'] ) ) {
					$field_type = sanitize_key( $addon['field_type'] ?? 'checkbox' );
					$price_type = sanitize_key( $addon['price_type'] ?? 'flat' );

					if ( ! in_array( $field_type, $valid_types, true ) ) {
						$field_type = 'checkbox';
					}
					if ( ! in_array( $price_type, $valid_price, true ) ) {
						$price_type = 'flat';
					}

					$addons[] = array(
						'title'               => sanitize_text_field( $addon['title'] ),
						'description'         => sanitize_textarea_field( $addon['description'] ?? '' ),
						'field_type'          => $field_type,
						'price_type'          => $price_type,
						'price'               => (float) ( $addon['price'] ?? 0 ),
						'min_quantity'        => absint( $addon['min_quantity'] ?? 1 ),
						'max_quantity'        => absint( $addon['max_quantity'] ?? 10 ),
						'options'             => sanitize_text_field( $addon['options'] ?? '' ),
						'delivery_days_extra' => absint( $addon['delivery_days_extra'] ?? 0 ),
						'is_required'         => ! empty( $addon['is_required'] ),
					);
				}
			}
			update_post_meta( $post_id, '_wpss_addons', $addons );
			// Admin data wins from now on: drop the wizard key so
			// wpss_get_service_extras() resolves to the rows just saved
			// instead of preferring stale `_wpss_extras` wizard data.
			delete_post_meta( $post_id, '_wpss_extras' );
		} elseif ( isset( $_POST['wpss_addons_present'] ) ) {
			// The add-ons UI was rendered on this form and submitted zero
			// rows: the admin removed every add-on, so clear both keys.
			// Without the sentinel guard, save paths that never render the
			// add-ons panel would silently wipe wizard-created add-ons.
			delete_post_meta( $post_id, '_wpss_addons' );
			delete_post_meta( $post_id, '_wpss_extras' );
		}

		// Save gallery -- only process if the gallery metabox was rendered on this page.
		// The sentinel field wpss_gallery_present indicates the gallery UI was present in the form.
		if ( isset( $_POST['wpss_gallery_present'] ) ) {
			// Read the explicit key into a local before iterating (no direct superglobal iteration);
			// values are attachment IDs, so unslash + absint fully sanitises each entry.
			$gallery_raw = isset( $_POST['wpss_gallery'] ) ? wp_unslash( $_POST['wpss_gallery'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint below.
			if ( is_array( $gallery_raw ) ) {
				$gallery_ids = array_filter( array_map( 'absint', $gallery_raw ) );

				// Preserve video URL from existing gallery meta if present.
				$existing_raw = get_post_meta( $post_id, '_wpss_gallery', true );
				$video_url    = wpss_get_gallery_video_url( $existing_raw );

				// Save in wizard-compatible structured format for consistency.
				update_post_meta(
					$post_id,
					'_wpss_gallery',
					array(
						'images' => array_values( $gallery_ids ),
						'video'  => $video_url,
					)
				);

				// Set featured image from first gallery image if not already set.
				if ( ! has_post_thumbnail( $post_id ) && ! empty( $gallery_ids ) ) {
					set_post_thumbnail( $post_id, reset( $gallery_ids ) );
				}
			} else {
				// Gallery UI was present but all images were removed.
				delete_post_meta( $post_id, '_wpss_gallery' );
			}
		}
		// If wpss_gallery_present is not set (e.g., AJAX/wizard save),
		// leave existing gallery meta untouched to prevent accidental deletion.

		/**
		 * Fires after service meta is saved.
		 *
		 * @since 1.0.0
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 */
		do_action( 'wpss_service_meta_saved', $post_id, $post );
	}

	/**
	 * Get service data tab definitions.
	 *
	 * @return array Tab configuration.
	 */
	private function get_service_data_tabs(): array {
		// Packet H: tab icons are now Lucide names (no `dashicons-` prefix).
		// The render_tabs_mode() template reads this key verbatim into a
		// <i data-lucide="…"> tag.
		return array(
			'overview'     => array(
				'label'    => __( 'Overview', 'wp-sell-services' ),
				'icon'     => 'info',
				'priority' => 10,
			),
			'pricing'      => array(
				'label'    => __( 'Pricing', 'wp-sell-services' ),
				'icon'     => 'banknote',
				'priority' => 20,
			),
			'media'        => array(
				'label'    => __( 'Media', 'wp-sell-services' ),
				'icon'     => 'images',
				'priority' => 30,
			),
			'addons'       => array(
				'label'    => __( 'Add-ons', 'wp-sell-services' ),
				'icon'     => 'plus-circle',
				'priority' => 40,
			),
			'requirements' => array(
				'label'    => __( 'Requirements', 'wp-sell-services' ),
				'icon'     => 'list',
				'priority' => 50,
			),
			'faq'          => array(
				'label'    => __( 'FAQ', 'wp-sell-services' ),
				'icon'     => 'help-circle',
				'priority' => 60,
			),
		);
	}

	/**
	 * Check if this is a new service (not yet saved).
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool True if new service (auto-draft), false otherwise.
	 */
	private function is_new_service( \WP_Post $post ): bool {
		return 'auto-draft' === $post->post_status;
	}

	/**
	 * Render consolidated service data metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_service_data_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'wpss_service_meta', 'wpss_service_nonce' );

		$is_new = $this->is_new_service( $post );
		$tabs   = $this->get_service_data_tabs();
		?>
		<div class="wpss-service-data-wrap" data-view-mode="<?php echo $is_new ? 'wizard' : 'tabs'; ?>">
			<?php if ( $is_new ) : ?>
				<?php $this->render_wizard_mode( $post, $tabs ); ?>
			<?php else : ?>
				<?php $this->render_tabs_mode( $post, $tabs ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render tabs mode for existing services.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $tabs Tab definitions.
	 * @return void
	 */
	private function render_tabs_mode( \WP_Post $post, array $tabs ): void {
		?>
		<div class="wpss-service-tabs-wrap">
			<ul class="wpss-service-tabs">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<li data-tab="<?php echo esc_attr( $key ); ?>">
						<a href="#wpss_<?php echo esc_attr( $key ); ?>_panel">
							<i data-lucide="<?php echo esc_attr( $tab['icon'] ); ?>" class="wpss-icon" aria-hidden="true"></i>
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="wpss-service-panels">
				<?php $this->render_all_panels( $post ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render wizard mode for new services.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $tabs Tab definitions (used as wizard steps).
	 * @return void
	 */
	private function render_wizard_mode( \WP_Post $post, array $tabs ): void {
		$step_number = 1;
		?>
		<div class="wpss-wizard-wrap">
			<div class="wpss-wizard-progress">
				<ol class="wpss-wizard-steps">
					<?php foreach ( $tabs as $key => $tab ) : ?>
						<li class="wpss-wizard-step" data-step="<?php echo esc_attr( $key ); ?>">
							<span class="wpss-step-number"><?php echo esc_html( (string) $step_number ); ?></span>
							<span class="wpss-step-label"><?php echo esc_html( $tab['label'] ); ?></span>
						</li>
						<?php ++$step_number; ?>
					<?php endforeach; ?>
				</ol>
			</div>
			<div class="wpss-wizard-panels">
				<?php $this->render_all_panels( $post ); ?>
			</div>
			<div class="wpss-wizard-nav">
				<button type="button" class="button wpss-wizard-prev" disabled>
					<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="button wpss-wizard-skip">
					<?php esc_html_e( 'Skip to full editor', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="button button-primary wpss-wizard-next">
					<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render all tab panels.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_all_panels( \WP_Post $post ): void {
		?>
		<div id="wpss_overview_panel" class="wpss-panel">
			<?php $this->render_overview_content( $post ); ?>
		</div>
		<div id="wpss_pricing_panel" class="wpss-panel">
			<?php $this->render_pricing_content( $post ); ?>
		</div>
		<div id="wpss_media_panel" class="wpss-panel">
			<?php $this->render_media_content( $post ); ?>
		</div>
		<div id="wpss_addons_panel" class="wpss-panel">
			<?php $this->render_addons_content( $post ); ?>
		</div>
		<div id="wpss_requirements_panel" class="wpss-panel">
			<?php
			// Sentinel: marks that the requirements UI was on this form, so a
			// save with zero rows means "cleared" rather than "not rendered".
			?>
			<input type="hidden" name="wpss_requirements_present" value="1">
			<?php $this->render_requirements_content( $post ); ?>
		</div>
		<div id="wpss_faq_panel" class="wpss-panel">
			<?php // Sentinel: same pattern as requirements/add-ons/gallery. ?>
			<input type="hidden" name="wpss_faqs_present" value="1">
			<?php $this->render_faq_content( $post ); ?>
		</div>
		<?php
	}

	/**
	 * Render overview panel content (status + stats).
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_overview_content( \WP_Post $post ): void {
		$status = get_post_meta( $post->ID, '_wpss_status', true );
		$status = ! empty( $status ) ? $status : 'active';

		$order_count    = (int) get_post_meta( $post->ID, '_wpss_order_count', true );
		$review_count   = (int) get_post_meta( $post->ID, '_wpss_review_count', true );
		$average_rating = (float) get_post_meta( $post->ID, '_wpss_rating_average', true );
		$view_count     = (int) get_post_meta( $post->ID, '_wpss_views', true );
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Overview', 'wp-sell-services' ); ?></h3>

		<div class="wpss-overview-grid">
			<div class="wpss-overview-section">
				<h4><?php esc_html_e( 'Service Status', 'wp-sell-services' ); ?></h4>
				<div class="wpss-details-grid">
					<div class="wpss-detail-card">
						<div class="wpss-detail-icon">
							<i data-lucide="eye" class="wpss-icon" aria-hidden="true"></i>
						</div>
						<div class="wpss-detail-content">
							<label for="wpss_status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></label>
							<div class="wpss-detail-input">
								<select id="wpss_status" name="wpss_status" class="wpss-status-select">
									<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'wp-sell-services' ); ?></option>
									<option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'wp-sell-services' ); ?></option>
									<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'wp-sell-services' ); ?></option>
								</select>
							</div>
							<p class="description"><?php esc_html_e( 'Control service visibility', 'wp-sell-services' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="wpss-overview-section">
				<h4><?php esc_html_e( 'Statistics', 'wp-sell-services' ); ?></h4>
				<div class="wpss-stats-grid">
					<div class="wpss-stat-item">
						<i data-lucide="shopping-cart" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( (string) $order_count ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-stat-item">
						<i data-lucide="star" class="wpss-icon wpss-stat-icon" style="color: #f5a623;" aria-hidden="true"></i>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( number_format( $average_rating, 1 ) ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-stat-item">
						<i data-lucide="quote" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( (string) $review_count ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-stat-item">
						<i data-lucide="eye" class="wpss-icon wpss-stat-icon" aria-hidden="true"></i>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( (string) $view_count ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Views', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<p class="wpss-details-note">
			<i data-lucide="info" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Delivery time and revisions are configured per package in the Pricing tab.', 'wp-sell-services' ); ?>
		</p>

		<?php $this->render_extra_fields( $post ); ?>
		<?php
	}

	/**
	 * Render pricing panel content (packages).
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_pricing_content( \WP_Post $post ): void {
		$packages = get_post_meta( $post->ID, '_wpss_packages', true );

		// Migrate old keyed format to new indexed format.
		if ( ! empty( $packages ) && ! isset( $packages[0] ) ) {
			$packages = array_values( $packages );
		}

		// Default: 1 package.
		if ( empty( $packages ) || ! is_array( $packages ) ) {
			$packages = array(
				array(
					'name'          => __( 'Standard', 'wp-sell-services' ),
					'description'   => '',
					'price'         => '',
					'delivery_days' => '',
					'revisions'     => '',
					'features'      => array(),
				),
			);
		}

		$package_count = count( $packages );
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Pricing Packages', 'wp-sell-services' ); ?></h3>

		<p class="description"><?php esc_html_e( 'Define your service package. Add more packages for tiered pricing (up to 3).', 'wp-sell-services' ); ?></p>

		<div id="wpss-packages-list">
			<?php foreach ( $packages as $index => $package ) : ?>
				<?php $this->render_package_item( (int) $index, $package ); ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button button-secondary" id="wpss-add-package"
				<?php echo $package_count >= 3 ? 'style="display:none;"' : ''; ?>>
			<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Add Package', 'wp-sell-services' ); ?>
		</button>

		<script type="text/html" id="tmpl-wpss-package-item">
			<?php $this->render_package_template(); ?>
		</script>
		<?php
	}

	/**
	 * Render media panel content (gallery).
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_media_content( \WP_Post $post ): void {
		$gallery_raw = get_post_meta( $post->ID, '_wpss_gallery', true );
		$gallery_ids = wpss_get_gallery_ids( $gallery_raw );
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Gallery', 'wp-sell-services' ); ?></h3>

		<p class="description"><?php esc_html_e( 'Add images to showcase your service. Drag to reorder.', 'wp-sell-services' ); ?></p>

		<div class="wpss-gallery-wrapper wpss-media-panel">
			<input type="hidden" name="wpss_gallery_present" value="1">
			<div id="wpss-gallery-images" class="wpss-gallery-grid">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php if ( $attachment_id ) : ?>
						<div class="wpss-gallery-item" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
							<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
							<button type="button" class="wpss-remove-image" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">&times;</button>
							<input type="hidden" name="wpss_gallery[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" id="wpss-add-gallery-images">
				<i data-lucide="images" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Add Images', 'wp-sell-services' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render addons panel content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_addons_content( \WP_Post $post ): void {
		$addons = $this->get_addons_for_editor( $post->ID );

		$field_types = $this->get_addon_field_types();
		$price_types = $this->get_addon_price_types();
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Service Add-ons', 'wp-sell-services' ); ?></h3>

		<input type="hidden" name="wpss_addons_present" value="1">

		<p class="description"><?php esc_html_e( 'Add extra services buyers can purchase with this service.', 'wp-sell-services' ); ?></p>

		<div id="wpss-addons-list">
			<?php foreach ( $addons as $index => $addon ) : ?>
				<?php $this->render_addon_item( $index, $addon, $field_types, $price_types ); ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button button-secondary" id="wpss-add-addon">
			<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Add Add-on', 'wp-sell-services' ); ?>
		</button>

		<script type="text/html" id="tmpl-wpss-addon-item">
			<?php $this->render_addon_template( $field_types, $price_types ); ?>
		</script>
		<?php
	}

	/**
	 * Render requirements panel content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_requirements_content( \WP_Post $post ): void {
		$requirements = get_post_meta( $post->ID, '_wpss_requirements', true );
		$requirements = ! empty( $requirements ) ? $requirements : array();
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Buyer Requirements', 'wp-sell-services' ); ?></h3>

		<p class="description"><?php esc_html_e( 'Questions to ask buyers when they place an order.', 'wp-sell-services' ); ?></p>

		<div id="wpss-requirements-list">
			<?php foreach ( $requirements as $index => $req ) : ?>
				<?php $this->render_requirement_item( $index, $req ); ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button button-secondary" id="wpss-add-requirement">
			<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Add Requirement', 'wp-sell-services' ); ?>
		</button>

		<script type="text/html" id="tmpl-wpss-requirement-item">
			<?php $this->render_requirement_template(); ?>
		</script>
		<?php
	}

	/**
	 * Render FAQ panel content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	private function render_faq_content( \WP_Post $post ): void {
		$faqs = get_post_meta( $post->ID, '_wpss_faqs', true );
		$faqs = ! empty( $faqs ) ? $faqs : array();
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Frequently Asked Questions', 'wp-sell-services' ); ?></h3>

		<p class="description"><?php esc_html_e( 'Add frequently asked questions about your service.', 'wp-sell-services' ); ?></p>

		<div id="wpss-faqs-list">
			<?php foreach ( $faqs as $index => $faq ) : ?>
				<div class="wpss-faq-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
					<div class="wpss-faq-header">
						<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
						<input type="text" name="wpss_faqs[<?php echo esc_attr( (string) $index ); ?>][question]" aria-label="<?php esc_attr_e( 'FAQ question', 'wp-sell-services' ); ?>"
								value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>" class="widefat">
						<button type="button" class="wpss-remove-faq" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
						</button>
					</div>
					<textarea name="wpss_faqs[<?php echo esc_attr( (string) $index ); ?>][answer]" aria-label="<?php esc_attr_e( 'FAQ answer', 'wp-sell-services' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"
								rows="3" class="widefat"><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button button-secondary" id="wpss-add-faq">
			<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Add FAQ', 'wp-sell-services' ); ?>
		</button>

		<script type="text/html" id="tmpl-wpss-faq-item">
			<?php $this->render_faq_template(); ?>
		</script>
		<?php
	}

	/**
	 * Render package JS template.
	 *
	 * @return void
	 */
	private function render_package_template(): void {
		?>
		<div class="wpss-package-item collapsed" data-index="{{data.index}}">
			<div class="wpss-package-header">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<span class="wpss-package-title"><?php esc_html_e( 'New Package', 'wp-sell-services' ); ?></span>
				<span class="wpss-package-price-display"></span>
				<div class="wpss-package-actions">
					<button type="button" class="wpss-package-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<i data-lucide="chevron-down" class="wpss-icon" aria-hidden="true"></i>
					</button>
					<button type="button" class="wpss-remove-package" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
					</button>
				</div>
			</div>
			<div class="wpss-package-body">
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-wide">
						<label><?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_packages[{{data.index}}][name]" aria-label="<?php esc_attr_e( 'Package name', 'wp-sell-services' ); ?>"
								class="widefat wpss-package-name-input"
								placeholder="<?php esc_attr_e( 'e.g., Standard, Premium, Enterprise', 'wp-sell-services' ); ?>">
					</div>
					<div class="wpss-package-field">
						<label>
							<i data-lucide="banknote" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
							<input type="number" name="wpss_packages[{{data.index}}][price]" aria-label="<?php esc_attr_e( 'Package price', 'wp-sell-services' ); ?>"
									class="wpss-package-price-input"
									min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>" placeholder="<?php echo esc_attr( wpss_get_price_input_attrs()['placeholder'] ); ?>">
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea name="wpss_packages[{{data.index}}][description]" aria-label="<?php esc_attr_e( 'Package description', 'wp-sell-services' ); ?>"
								rows="2" class="widefat"
								placeholder="<?php esc_attr_e( 'Describe what\'s included in this package...', 'wp-sell-services' ); ?>"></textarea>
					</div>
				</div>
				<div class="wpss-package-row wpss-package-row-grid">
					<div class="wpss-package-field">
						<label>
							<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[{{data.index}}][delivery_days]" aria-label="<?php esc_attr_e( 'Delivery time in days', 'wp-sell-services' ); ?>"
									min="1" max="365" placeholder="7">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-package-field">
						<label>
							<i data-lucide="refresh-cw" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[{{data.index}}][revisions]" aria-label="<?php esc_attr_e( 'Number of revisions', 'wp-sell-services' ); ?>"
									min="0" max="20" placeholder="2">
							<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label>
							<i data-lucide="check-circle-2" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?>
						</label>
						<textarea name="wpss_packages[{{data.index}}][features]" aria-label="<?php esc_attr_e( 'Features included', 'wp-sell-services' ); ?>"
								rows="3" class="widefat"
								placeholder="<?php esc_attr_e( "Feature 1\nFeature 2\nFeature 3", 'wp-sell-services' ); ?>"></textarea>
						<p class="description"><?php esc_html_e( 'Enter one feature per line', 'wp-sell-services' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render addon JS template.
	 *
	 * @param array $field_types Available field types.
	 * @param array $price_types Available price types.
	 * @return void
	 */
	private function render_addon_template( array $field_types, array $price_types ): void {
		?>
		<div class="wpss-addon-item" data-index="{{data.index}}">
			<div class="wpss-addon-header">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<span class="wpss-addon-title"><?php esc_html_e( 'New Add-on', 'wp-sell-services' ); ?></span>
				<div class="wpss-addon-actions">
					<button type="button" class="wpss-addon-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<i data-lucide="chevron-down" class="wpss-icon" aria-hidden="true"></i>
					</button>
					<button type="button" class="wpss-remove-addon" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
					</button>
				</div>
			</div>
			<div class="wpss-addon-body">
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label for="wpss_addon_{{data.index}}_title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss_addon_{{data.index}}_title" name="wpss_addons[{{data.index}}][title]"
								placeholder="<?php esc_attr_e( 'e.g., Extra Fast Delivery', 'wp-sell-services' ); ?>" class="widefat wpss-addon-title-input">
					</div>
				</div>
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label for="wpss_addon_{{data.index}}_description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea id="wpss_addon_{{data.index}}_description" name="wpss_addons[{{data.index}}][description]" rows="2" class="widefat"
									placeholder="<?php esc_attr_e( 'Brief description of this add-on...', 'wp-sell-services' ); ?>"></textarea>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label for="wpss_addon_{{data.index}}_field_type"><?php esc_html_e( 'Field Type', 'wp-sell-services' ); ?></label>
						<select id="wpss_addon_{{data.index}}_field_type" name="wpss_addons[{{data.index}}][field_type]" class="wpss-addon-field-type">
							<?php foreach ( $field_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label for="wpss_addon_{{data.index}}_price_type"><?php esc_html_e( 'Price Type', 'wp-sell-services' ); ?></label>
						<select id="wpss_addon_{{data.index}}_price_type" name="wpss_addons[{{data.index}}][price_type]" class="wpss-addon-price-type">
							<?php foreach ( $price_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label for="wpss_addon_{{data.index}}_price"><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
							<input type="number" id="wpss_addon_{{data.index}}_price" name="wpss_addons[{{data.index}}][price]"
									min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>" placeholder="<?php echo esc_attr( wpss_get_price_input_attrs()['placeholder'] ); ?>">
						</div>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid wpss-addon-quantity-fields" style="display: none;">
					<div class="wpss-addon-field">
						<label for="wpss_addon_{{data.index}}_min_quantity"><?php esc_html_e( 'Min Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" id="wpss_addon_{{data.index}}_min_quantity" name="wpss_addons[{{data.index}}][min_quantity]"
								value="1" min="1" max="100">
					</div>
					<div class="wpss-addon-field">
						<label for="wpss_addon_{{data.index}}_max_quantity"><?php esc_html_e( 'Max Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" id="wpss_addon_{{data.index}}_max_quantity" name="wpss_addons[{{data.index}}][max_quantity]"
								value="10" min="1" max="100">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-dropdown-fields" style="display: none;">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label for="wpss_addon_{{data.index}}_options"><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss_addon_{{data.index}}_options" name="wpss_addons[{{data.index}}][options]" class="widefat"
								placeholder="<?php esc_attr_e( 'Option 1, Option 2, Option 3 (comma separated)', 'wp-sell-services' ); ?>">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label for="wpss_addon_{{data.index}}_delivery_days_extra"><?php esc_html_e( 'Extra Delivery Days', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-suffix">
							<input type="number" id="wpss_addon_{{data.index}}_delivery_days_extra" name="wpss_addons[{{data.index}}][delivery_days_extra]"
									value="0" min="0" max="30">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-addon-field wpss-addon-field-checkbox">
						<label>
							<input type="checkbox" name="wpss_addons[{{data.index}}][is_required]" value="1">
							<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render requirement JS template.
	 *
	 * @return void
	 */
	private function render_requirement_template(): void {
		?>
		<div class="wpss-requirement-item" data-index="{{data.index}}">
			<div class="wpss-requirement-row">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<div class="wpss-requirement-fields">
					<div class="wpss-requirement-main">
						<input type="text" name="wpss_requirements[{{data.index}}][question]"
								aria-label="<?php esc_attr_e( 'Requirement question', 'wp-sell-services' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter your question...', 'wp-sell-services' ); ?>" class="widefat">
					</div>
					<div class="wpss-requirement-options">
						<select name="wpss_requirements[{{data.index}}][type]" class="wpss-requirement-type"
								aria-label="<?php esc_attr_e( 'Requirement field type', 'wp-sell-services' ); ?>">
							<?php foreach ( $this->get_requirement_types() as $req_type_value => $req_type_label ) : ?>
								<option value="<?php echo esc_attr( $req_type_value ); ?>"><?php echo esc_html( $req_type_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<label class="wpss-requirement-required">
							<input type="checkbox" name="wpss_requirements[{{data.index}}][required]" value="1">
							<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
						</label>
						<button type="button" class="wpss-remove-requirement" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>" aria-label="<?php esc_attr_e( 'Remove requirement', 'wp-sell-services' ); ?>">
							<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
						</button>
					</div>
					<div class="wpss-requirement-choices" style="display:none;">
						<input type="text" name="wpss_requirements[{{data.index}}][choices]"
								aria-label="<?php esc_attr_e( 'Requirement choices', 'wp-sell-services' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter choices separated by comma (e.g., Option 1, Option 2, Option 3)', 'wp-sell-services' ); ?>" class="widefat">
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render FAQ JS template.
	 *
	 * @return void
	 */
	private function render_faq_template(): void {
		?>
		<div class="wpss-faq-item" data-index="{{data.index}}">
			<div class="wpss-faq-header">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<input type="text" name="wpss_faqs[{{data.index}}][question]" aria-label="<?php esc_attr_e( 'FAQ question', 'wp-sell-services' ); ?>"
						placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>" class="widefat">
				<button type="button" class="wpss-remove-faq" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
					<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
				</button>
			</div>
			<textarea name="wpss_faqs[{{data.index}}][answer]" aria-label="<?php esc_attr_e( 'FAQ answer', 'wp-sell-services' ); ?>"
						placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"
						rows="3" class="widefat"></textarea>
		</div>
		<?php
	}
}
