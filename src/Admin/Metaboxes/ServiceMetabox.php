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

		$this->register_publish_guards();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'limit_notice' ) );
	}

	/**
	 * Tell the editor which lists were cut to the service limits on the last save.
	 *
	 * @return void
	 */
	public function limit_notice(): void {
		$key      = 'wpss_service_limit_notice_' . get_current_user_id();
		$messages = get_transient( $key );

		if ( ! is_array( $messages ) ) {
			return;
		}

		delete_transient( $key );
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( implode( ' ', $messages ) ) . '</p></div>';
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
		// Shared with the frontend wizard so the two authoring surfaces cannot
		// drift again. See wpss_requirement_type_labels().
		return wpss_requirement_type_labels();
	}

	/**
	 * Requirement types that expose a choices field (dropdown / multiple choice).
	 *
	 * @return array<int, string>
	 */
	private function get_choice_requirement_types(): array {
		return wpss_requirement_choice_types();
	}

	/**
	 * Canonical add-on field types (value => label).
	 *
	 * @return array<string, string>
	 */
	private function get_addon_field_types(): array {
		return wpss_get_addon_field_types();
	}

	/**
	 * Canonical add-on price types (value => label).
	 *
	 * @return array<string, string>
	 */
	private function get_addon_price_types(): array {
		return wpss_get_addon_price_types();
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
	 * Apply the `wpss_service_meta_fields` filter and render the returned fields.
	 *
	 * Rendered by the Overview panel.
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
	 * @param int|string $index   Package index.
	 * @param array      $package Package data.
	 * @return void
	 */
	private function render_package_item( $index, array $package ): void {
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
				<?php $express = wpss_sanitize_package_express( $package ); ?>
				<div class="wpss-package-row wpss-package-row-grid">
					<div class="wpss-package-field">
						<label>
							<i data-lucide="zap" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Express price', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][express_price]" aria-label="<?php esc_attr_e( 'Express delivery price', 'wp-sell-services' ); ?>"
									value="<?php echo esc_attr( $express['express_price'] > 0 ? $express['express_price'] : '' ); ?>"
									min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>">
						</div>
						<p class="description"><?php esc_html_e( 'Optional. Blank means not offered.', 'wp-sell-services' ); ?></p>
					</div>
					<div class="wpss-package-field">
						<label>
							<i data-lucide="timer" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Express delivery', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][express_days]" aria-label="<?php esc_attr_e( 'Express delivery time in days', 'wp-sell-services' ); ?>"
									value="<?php echo esc_attr( $express['express_days'] > 0 ? $express['express_days'] : '' ); ?>"
									min="1" max="364">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'Replaces the delivery time; must be shorter.', 'wp-sell-services' ); ?></p>
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
		$type         = $req['type'];
		$show_choices = in_array( $type, $this->get_choice_requirement_types(), true );
		?>
		<div class="wpss-requirement-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="wpss-requirement-row">
				<i data-lucide="grip-vertical" class="wpss-icon wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>" aria-hidden="true"></i>
				<div class="wpss-requirement-fields">
					<div class="wpss-requirement-main">
						<input type="hidden" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $req['id'] ); ?>">
						<input type="text" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][label]"
								value="<?php echo esc_attr( $req['label'] ); ?>"
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
						<input type="text" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][options]"
								value="<?php echo esc_attr( implode( ', ', $req['options'] ) ); ?>"
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
					<i data-lucide="star" class="wpss-icon wpss-stat-icon wpss-stat-icon--pending" aria-hidden="true"></i>
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

		// The metabox JS caps the rows; the server is the enforcer (wp-admin
		// post-new is reachable by any vendor holding edit_posts).
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each list is sanitised where it is saved below.
		$capped = wpss_enforce_service_limits(
			array(
				'packages'     => isset( $_POST['wpss_packages'] ) ? wp_unslash( $_POST['wpss_packages'] ) : array(),
				'faqs'         => isset( $_POST['wpss_faqs'] ) ? wp_unslash( $_POST['wpss_faqs'] ) : array(),
				'requirements' => isset( $_POST['wpss_requirements'] ) ? wp_unslash( $_POST['wpss_requirements'] ) : array(),
				'extras'       => isset( $_POST['wpss_addons'] ) ? wp_unslash( $_POST['wpss_addons'] ) : array(),
				'gallery'      => isset( $_POST['wpss_gallery'] ) ? wp_unslash( $_POST['wpss_gallery'] ) : array(),
			)
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! empty( $capped['truncated'] ) ) {
			set_transient( 'wpss_service_limit_notice_' . get_current_user_id(), array_values( $capped['truncated'] ), MINUTE_IN_SECONDS );
		}

		// Save status field.
		// Note: Delivery time and revisions are now per-package only (see packages below).
		// Featured is marketplace curation, not authoring: a vendor holds
		// edit_post on their own service, so gating this on edit_post alone
		// would let any vendor promote themselves into the featured slot.
		// The field is not rendered for them either, so a vendor's save
		// carries no wpss_featured key and leaves the owner's choice intact.
		if ( isset( $_POST['wpss_featured'] ) && wpss_user_can_feature_service( $post_id ) ) {
			$wpss_featured = ! empty( $_POST['wpss_featured'] );

			/**
			 * Filter whether a service is featured as it saves.
			 *
			 * The owner's checkbox is the default. A site can force the flag from
			 * its own rule - a vendor tier, a paid placement, a campaign window -
			 * without having to intercept save_post.
			 *
			 * @since 1.7.1
			 *
			 * @param bool $wpss_featured Whether the service should be featured.
			 * @param int  $post_id       Service post ID.
			 */
			$wpss_featured = (bool) apply_filters( 'wpss_service_is_featured', $wpss_featured, $post_id );

			if ( $wpss_featured ) {
				update_post_meta( $post_id, '_wpss_featured', 1 );
			} else {
				// Deleted rather than stored as 0. Both readers compare the value
				// to '1', so a 0 would not match either way - but leaving rows
				// behind for every service anyone ever unticked is meta nobody
				// reads, and EXISTS is the obvious way for a future query to ask
				// this question.
				delete_post_meta( $post_id, '_wpss_featured' );
			}
		}

		// Only active / paused: visibility belongs to the post status, so the
		// old "Draft" choice here was a third status control nothing read.
		if ( isset( $_POST['wpss_status'] ) ) {
			update_post_meta( $post_id, '_wpss_status', 'paused' === sanitize_key( $_POST['wpss_status'] ) ? 'paused' : 'active' );
		}

		// Save packages (indexed array format).
		$packages_data = $capped['meta']['packages'];
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
					) + wpss_sanitize_package_express( (array) $package );
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
		$faqs_data = $capped['meta']['faqs'];
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

		// Save requirements. The metabox lists a subset of the requirement
		// types; wpss_normalize_service_requirements() is the one sanitiser.
		if ( is_array( $capped['meta']['requirements'] ) && ! empty( $capped['meta']['requirements'] ) ) {
			wpss_save_service_requirements( $post_id, $capped['meta']['requirements'] );
		} elseif ( isset( $_POST['wpss_requirements_present'] ) ) {
			// The requirements panel was on the form and submitted zero rows:
			// the admin removed every requirement, so clear the meta (same
			// sentinel pattern as FAQs / add-ons / gallery).
			delete_post_meta( $post_id, '_wpss_requirements' );
		}

		// Save add-ons; `_wpss_addons` is the one store.
		if ( is_array( $capped['meta']['extras'] ) && ! empty( $capped['meta']['extras'] ) ) {
			wpss_save_service_addons( $post_id, $capped['meta']['extras'] );
		} elseif ( isset( $_POST['wpss_addons_present'] ) ) {
			// The add-ons UI was rendered on this form and submitted zero
			// rows: the admin removed every add-on, so clear the key. Without
			// the sentinel guard, save paths that never render the add-ons
			// panel would silently wipe wizard-created add-ons.
			delete_post_meta( $post_id, '_wpss_addons' );
		}

		// Save gallery -- only process if the gallery metabox was rendered on this page.
		// The sentinel field wpss_gallery_present indicates the gallery UI was present in the form.
		if ( isset( $_POST['wpss_gallery_present'] ) ) {
			// Read the explicit key into a local before iterating (no direct superglobal iteration);
			// values are attachment IDs, so unslash + absint fully sanitises each entry.
			$gallery_raw = isset( $_POST['wpss_gallery'] ) ? $capped['meta']['gallery'] : null;
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
	 * Keep a service that fails the marketplace rules out of the marketplace.
	 *
	 * The wizard refuses to publish a service with no category, no main image,
	 * no delivery time or a sub-minimum price. wp-admin enforced none of that,
	 * so the same service published from the backend and went live - see
	 * wpss_validate_service_publishable().
	 *
	 * An invalid service is taken off the marketplace whatever route put it
	 * there. Distinguishing "going live now" from "already live" is not
	 * reliable here: the block editor publishes over REST and posts the
	 * metaboxes in a SECOND request, so by the time this runs - after the meta
	 * it must validate has been written - the row already reads `publish` and
	 * the transition looks like publish -> publish either way. One rule, always
	 * applied, is both simpler and the one that protects buyers; the notice
	 * names every reason so the owner can fix and publish again.
	 *
	 * This only fires on an explicit editor save, never in bulk: Quick Edit and
	 * bulk edit post no `wpss_service_nonce`, and the frontend wizard, the REST
	 * controllers and the moderation Approve action do not either, so each of
	 * those keeps its own publishing rules.
	 *
	 * @since 1.7.1
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	/**
	 * Status as stored before the current request, keyed by post id.
	 *
	 * @var array<int, string>
	 */
	private static array $status_before_save = array();

	/**
	 * Register only the publish rule, on every request.
	 *
	 * Separate from init() for the same reason define_moderation_hooks() is
	 * separate from define_admin_hooks(): that method returns early when
	 * ! is_admin(), and is_admin() is FALSE during a REST request. The block
	 * editor publishes over /wp/v2/wpss-services/<id>, so this class was never
	 * constructed on the path most owners actually use and the rule simply did
	 * not exist there - a service with no category, a 38-character description
	 * and no image published cleanly through the native Publish button
	 * (reproduced 2026-09-23, REST 200).
	 *
	 * Called by Plugin::define_publish_rule_hooks() on every request, and by
	 * init() so a classic-editor load does not register it twice.
	 *
	 * @since 1.7.2
	 *
	 * @return void
	 */
	public function register_publish_guards(): void {
		/*
		 * Static, not has_action().
		 *
		 * has_action() compares the callable, so a SECOND instance of this class
		 * registers happily - and both then run. The first consumed the stashed
		 * pre-save status and returned; the second found no stash, read the
		 * service as "going live now" and demoted a perfectly good published
		 * service. Registration is per-request, so a static flag is the right
		 * shape and cannot be fooled by a new instance.
		 */
		static $registered = false;

		if ( $registered ) {
			return;
		}

		$registered = true;

		/*
		 * Late, so it is the last word on post_status. The Moderation Status
		 * metabox saves on the same hook at the same priority and maps an
		 * approved service to `publish`; at priority 10 the winner would depend
		 * on which class registered first, and an invalid service could be
		 * demoted here then re-published a moment later in the same request.
		 */
		add_action( 'save_post_' . ServicePostType::POST_TYPE, array( $this, 'enforce_publish_rules' ), 99, 2 );
		add_action( 'pre_post_update', array( $this, 'remember_status_before_save' ), 10, 1 );
	}

	/**
	 * Record what the service's status was BEFORE this request changed it.
	 *
	 * The pre_post_update hook runs inside wp_insert_post() before the row is
	 * written, so get_post_status() here still returns the stored value. save_post - and
	 * therefore enforce_publish_rules() - runs after, when that value is gone.
	 *
	 * This is what makes "already live" distinguishable from "going live now",
	 * which the block editor's two-request publish otherwise hides: by the
	 * second request the row already reads `publish` either way.
	 *
	 * @since 1.7.2
	 *
	 * @param  int $post_id Post being updated.
	 * @return void
	 */
	public function remember_status_before_save( int $post_id ): void {
		if ( ServicePostType::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		self::$status_before_save[ $post_id ] = (string) get_post_status( $post_id );
	}

	/**
	 * Keep an incomplete service from GOING live, without taking a live one down.
	 *
	 * Runs on an explicit editor save only: Quick Edit, bulk edit, the frontend
	 * wizard, the REST controllers and the moderation Approve action post no
	 * `wpss_service_nonce`, so each keeps its own publishing rules.
	 *
	 * @since 1.7.1
	 *
	 * @param  int      $post_id Post ID.
	 * @param  \WP_Post $post    Post object.
	 * @return void
	 */
	public function enforce_publish_rules( int $post_id, \WP_Post $post ): void {
		/*
		 * Deliberately NOT gated on wpss_service_nonce.
		 *
		 * It used to be, on the reasoning that this is "the editor's rule" - and
		 * that made it unreachable from the editor most people actually use. The
		 * block editor publishes over the REST route the post type registers
		 * (/wp/v2/wpss-services/<id>) and never posts a classic form, so the
		 * nonce is absent and the whole check returned early. A service with no
		 * category, a 38-character description and no image published cleanly
		 * through the native Publish button: REST 200, post_status=publish.
		 * Reproduced 2026-09-23.
		 *
		 * A nonce is CSRF protection, not authorisation, and it was doing
		 * neither here - the capability check below is what decides whether this
		 * user may change this post, and it runs on every path. Dropping the
		 * nonce condition brings the block editor, Quick Edit, bulk edit, REST
		 * and WP-CLI under the same rule, which is what "an incomplete service
		 * must not go live" has to mean to be worth anything.
		 *
		 * Nothing legitimately published is at risk: the already-live guard
		 * further down returns before any demotion. save_meta() keeps its nonce
		 * check, because writing metabox fields from a request genuinely does
		 * need CSRF protection.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// post_status on $post is the value as saved; re-read so a handler that
		// already changed it in this request is respected.
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$errors = wpss_get_service_publish_errors( $post_id );

		if ( empty( $errors ) ) {
			return;
		}

		/*
		 * A service that was ALREADY live stays live.
		 *
		 * Until 1.7.2 this demoted any published service failing the checklist,
		 * on every save. The reasoning was sound as far as it went - one rule,
		 * always applied, protects buyers from incomplete listings - but the
		 * cost lands on the wrong person. The checklist is enforced at save
		 * time, not retroactively, so a site running since before a rule
		 * existed holds services that are live, selling, and non-compliant. The
		 * owner opens one to fix a typo, presses Save, and their listing leaves
		 * the storefront with no warning. They may not notice for days, and
		 * nothing tells the buyers either.
		 *
		 * Taking a shop's listing down is the owner's decision to make, not a
		 * side effect of editing it. So the rule now only blocks something
		 * GOING live incomplete, which is what it was really for. An already
		 * live service keeps its status and the metabox banner says plainly
		 * what it is missing - "This service is live and buyers see it as it
		 * is." - so the owner is told and chooses when to fix it.
		 *
		 * The pre-save status is what makes this reliable; see
		 * remember_status_before_save() for why the transition itself cannot be
		 * read here.
		 */
		// Read, never consume: if anything else ends up reading this in the same
		// request it must get the same answer, not an empty string that looks
		// like "this was never live".
		$was = self::$status_before_save[ $post_id ] ?? '';

		if ( 'publish' === $was ) {
			return;
		}

		// Detach both handlers: wp_update_post() fires save_post again, which
		// would re-enter this very method as well as re-running the meta save.
		remove_action( 'save_post_' . ServicePostType::POST_TYPE, array( $this, 'save_meta' ), 10 );
		remove_action( 'save_post_' . ServicePostType::POST_TYPE, array( $this, 'enforce_publish_rules' ), 99 );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		add_action( 'save_post_' . ServicePostType::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'save_post_' . ServicePostType::POST_TYPE, array( $this, 'enforce_publish_rules' ), 99, 2 );
	}

	/**
	 * Show what still stands between this service and the marketplace.
	 *
	 * Computed on every render rather than stashed in a transient after a save.
	 * A one-shot message could not be delivered reliably: the block editor drops
	 * classic admin notices, and its metabox-refresh request consumed the
	 * transient without the result reaching the screen - the owner got a service
	 * silently dropped back to draft and no reason for it.
	 *
	 * Reading the rules live also makes this a checklist rather than an error:
	 * it is there while the owner fills the form, not only after a refused
	 * publish, and it disappears the moment the service qualifies.
	 *
	 * @since 1.7.1
	 *
	 * @param \WP_Post $post Service being edited.
	 * @return void
	 */
	private function render_invalid_notice( \WP_Post $post ): void {
		if ( $this->is_new_service( $post ) ) {
			return;
		}

		$errors = wpss_get_service_publish_errors( $post->ID );

		if ( empty( $errors ) ) {
			return;
		}

		/*
		 * `wpss-notice warning`, NOT WordPress's `notice notice-warning`.
		 *
		 * wp-admin's own JS hoists any element carrying `.notice` out of where
		 * it was printed and into the page's notice area. Inside the block
		 * editor that target sits in `div.wrap.hide-if-js.block-editor-no-js` -
		 * the no-JavaScript fallback - which is display:none for every real
		 * owner. So the reasons rendered, were moved, and were never seen: the
		 * exact silent failure this notice exists to prevent, reintroduced by
		 * the class name. Verified: one occurrence in the document, zero inside
		 * #wpss_service_data.
		 *
		 * The plugin's own class carries the same left-border treatment from
		 * admin.css and nothing relocates it.
		 */

		/*
		 * Two states, two sentences.
		 *
		 * This always said "This service stays a draft until: ..." - including
		 * on rows whose post_status is already `publish`. For a live service
		 * that sentence is simply false, and it is false in the dangerous
		 * direction: it tells the owner the listing is safely held back while
		 * buyers can see it and buy it. A published service with an incomplete
		 * checklist reached this branch and was reported as a draft
		 * (Basecamp 10330733407).
		 *
		 * A service can be published and incomplete because the checklist is
		 * enforced at save time, not retroactively - a row published before a
		 * rule existed, or imported, keeps its status.
		 */
		$is_live = in_array( $post->post_status, array( 'publish', 'pending' ), true );

		echo '<div class="wpss-notice warning wpss-service-invalid-notice" style="margin:0 0 16px;"><p><strong>';
		if ( $is_live ) {
			esc_html_e( 'This service is live and buyers see it as it is. It is still missing:', 'wp-sell-services' );
		} else {
			esc_html_e( 'Not ready for the marketplace yet. This service stays a draft until:', 'wp-sell-services' );
		}
		echo '</strong></p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( $errors as $message ) {
			echo '<li>' . esc_html( $message ) . '</li>';
		}
		echo '</ul></div>';
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

		// Rendered here, not through admin_notices: the block editor suppresses
		// classic notices, and a service silently dropped back to draft with no
		// stated reason is a worse experience than the missing validation was.
		// The editor re-fetches this metabox after every save, so the reasons
		// appear straight away without a reload.
		$this->render_invalid_notice( $post );

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

		$is_featured = (bool) get_post_meta( $post->ID, '_wpss_featured', true );
		$can_feature = wpss_user_can_feature_service( $post->ID );

		$order_count    = (int) get_post_meta( $post->ID, '_wpss_order_count', true );
		$review_count   = (int) get_post_meta( $post->ID, '_wpss_review_count', true );
		$average_rating = (float) get_post_meta( $post->ID, '_wpss_rating_average', true );
		$view_count     = (int) get_post_meta( $post->ID, '_wpss_views', true );
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Overview', 'wp-sell-services' ); ?></h3>

		<div class="wpss-overview-grid">
			<div class="wpss-overview-section">
				<h4><?php esc_html_e( 'Availability', 'wp-sell-services' ); ?></h4>
				<div class="wpss-details-grid">
					<div class="wpss-detail-card">
						<div class="wpss-detail-icon">
							<i data-lucide="eye" class="wpss-icon" aria-hidden="true"></i>
						</div>
						<div class="wpss-detail-content">
							<label for="wpss_status"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></label>
							<div class="wpss-detail-input">
								<select id="wpss_status" name="wpss_status" class="wpss-status-select">
									<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Accepting orders', 'wp-sell-services' ); ?></option>
									<option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'wp-sell-services' ); ?></option>
								</select>
							</div>
							<p class="description"><?php esc_html_e( 'Paused keeps the page visible but stops new orders. Whether the service is live is set by Publish and Moderation.', 'wp-sell-services' ); ?></p>
						</div>
					</div>

					<?php
					/*
					 * The only writer of _wpss_featured outside WP-CLI and the demo
					 * seeder.
					 *
					 * [wpss_featured_services] and the Featured Services block both
					 * filter on this meta and render correctly - the display half was
					 * finished and the authoring half was never built, so the feature
					 * was unreachable on a real site (Basecamp 10308762619).
					 */
					?>
					<div class="wpss-detail-card">
						<div class="wpss-detail-icon">
							<i data-lucide="sparkles" class="wpss-icon" aria-hidden="true"></i>
						</div>
						<div class="wpss-detail-content">
							<label for="wpss_featured"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></label>
							<?php if ( $can_feature ) : ?>
								<div class="wpss-detail-input">
									<?php // Hidden 0 so unticking is submitted - a bare checkbox is simply absent when off. ?>
									<input type="hidden" name="wpss_featured" value="0">
									<label class="wpss-featured-toggle">
										<input type="checkbox" id="wpss_featured" name="wpss_featured" value="1" <?php checked( $is_featured ); ?>>
										<?php esc_html_e( 'Show in featured listings', 'wp-sell-services' ); ?>
									</label>
								</div>
								<p class="description"><?php esc_html_e( 'Shows it in the featured row on your homepage (the Featured Services block and the [wpss_featured_services] shortcode).', 'wp-sell-services' ); ?></p>
							<?php else : ?>
								<?php
								// Read-only for a vendor. Silence would be worse than a
								// disabled control: a vendor whose service the marketplace
								// has promoted should be able to see that it has.
								?>
								<p class="wpss-detail-value">
									<?php
									if ( $is_featured ) {
										esc_html_e( 'Featured by the marketplace', 'wp-sell-services' );
									} else {
										esc_html_e( 'Not featured', 'wp-sell-services' );
									}
									?>
								</p>
								<p class="description"><?php esc_html_e( 'Only the site owner can feature a service.', 'wp-sell-services' ); ?></p>
							<?php endif; ?>
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
						<i data-lucide="star" class="wpss-icon wpss-stat-icon wpss-stat-icon--pending" aria-hidden="true"></i>
						<div class="wpss-stat-data">
							<?php if ( $review_count > 0 ) : ?>
								<span class="wpss-stat-value"><?php echo esc_html( number_format_i18n( $average_rating, 1 ) ); ?></span>
								<span class="wpss-stat-label"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></span>
							<?php else : ?>
								<span class="wpss-stat-label"><?php esc_html_e( 'No reviews yet', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
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
		$addons = wpss_get_service_extras( $post->ID );

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
		$requirements = wpss_get_service_requirements( $post->ID );
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
		/*
		 * A package the owner just added opens ready to fill in. It used to render
		 * collapsed, so "Add Package" appeared to do nothing but add a grey bar the
		 * owner then had to find and click. Packages loaded from saved data still
		 * render collapsed - that is a list to scan, this is a form to complete.
		 * See Basecamp 10286092451. The markup is the saved package's own, so a
		 * field added there (Express, Basecamp 10337201764) is here too.
		 */
		$this->render_package_item( '{{data.index}}', array() );
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
						<input type="text" name="wpss_requirements[{{data.index}}][label]"
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
						<input type="text" name="wpss_requirements[{{data.index}}][options]"
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
