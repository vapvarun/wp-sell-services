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
		wp_enqueue_script(
			'wpss-service-edit',
			$plugin_url . 'assets/js/service-edit.js',
			array( 'jquery' ),
			$version,
			true
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
						<span class="dashicons dashicons-visibility"></span>
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
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'Delivery time and revisions are configured per package below.', 'wp-sell-services' ); ?>
			</p>

			<?php
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

			if ( ! empty( $extra_fields ) ) :
				?>
				<div class="wpss-extra-fields" style="margin-top: 15px;">
					<?php
					foreach ( $extra_fields as $field_html ) {
						echo wp_kses_post( $field_html );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render packages metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_packages_metabox( \WP_Post $post ): void {
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
		<div class="wpss-packages-wrapper">
			<p class="description"><?php esc_html_e( 'Define your service package. Add more packages for tiered pricing (up to 3).', 'wp-sell-services' ); ?></p>

			<div id="wpss-packages-list">
				<?php foreach ( $packages as $index => $package ) : ?>
					<?php $this->render_package_item( (int) $index, $package ); ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button button-secondary" id="wpss-add-package"
					<?php echo $package_count >= 3 ? 'style="display:none;"' : ''; ?>>
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Package', 'wp-sell-services' ); ?>
			</button>
		</div>

		<script type="text/html" id="tmpl-wpss-package-item">
			<div class="wpss-package-item collapsed" data-index="{{data.index}}">
				<div class="wpss-package-header">
					<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
					<span class="wpss-package-title"><?php esc_html_e( 'New Package', 'wp-sell-services' ); ?></span>
					<span class="wpss-package-price-display"></span>
					<div class="wpss-package-actions">
						<button type="button" class="wpss-package-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<button type="button" class="wpss-remove-package" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
				<div class="wpss-package-body">
					<div class="wpss-package-row">
						<div class="wpss-package-field wpss-package-field-wide">
							<label><?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?></label>
							<input type="text" name="wpss_packages[{{data.index}}][name]"
									class="widefat wpss-package-name-input"
									placeholder="<?php esc_attr_e( 'e.g., Standard, Premium, Enterprise', 'wp-sell-services' ); ?>">
						</div>
						<div class="wpss-package-field">
							<label>
								<span class="dashicons dashicons-money-alt"></span>
								<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
							</label>
							<div class="wpss-input-with-prefix">
								<span class="wpss-input-prefix">$</span>
								<input type="number" name="wpss_packages[{{data.index}}][price]"
										class="wpss-package-price-input"
										min="0" step="0.01" placeholder="0.00">
							</div>
						</div>
					</div>
					<div class="wpss-package-row">
						<div class="wpss-package-field wpss-package-field-full">
							<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
							<textarea name="wpss_packages[{{data.index}}][description]"
									rows="2" class="widefat"
									placeholder="<?php esc_attr_e( 'Describe what\'s included in this package...', 'wp-sell-services' ); ?>"></textarea>
						</div>
					</div>
					<div class="wpss-package-row wpss-package-row-grid">
						<div class="wpss-package-field">
							<label>
								<span class="dashicons dashicons-clock"></span>
								<?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?>
							</label>
							<div class="wpss-input-with-suffix">
								<input type="number" name="wpss_packages[{{data.index}}][delivery_days]"
										min="1" max="365" placeholder="7">
								<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-package-field">
							<label>
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?>
							</label>
							<div class="wpss-input-with-suffix">
								<input type="number" name="wpss_packages[{{data.index}}][revisions]"
										min="0" max="20" placeholder="2">
								<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
							</div>
						</div>
					</div>
					<div class="wpss-package-row">
						<div class="wpss-package-field wpss-package-field-full">
							<label>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?>
							</label>
							<textarea name="wpss_packages[{{data.index}}][features]"
									rows="3" class="widefat"
									placeholder="<?php esc_attr_e( "Feature 1\nFeature 2\nFeature 3", 'wp-sell-services' ); ?>"></textarea>
							<p class="description"><?php esc_html_e( 'Enter one feature per line', 'wp-sell-services' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</script>
		<?php
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
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<span class="wpss-package-title"><?php echo esc_html( $package_name ); ?></span>
				<span class="wpss-package-price-display">
					<?php if ( $price > 0 ) : ?>
						$<?php echo esc_html( number_format( $price, 2 ) ); ?>
					<?php endif; ?>
				</span>
				<div class="wpss-package-actions">
					<button type="button" class="wpss-package-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<?php if ( ! $is_first ) : ?>
						<button type="button" class="wpss-remove-package" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					<?php endif; ?>
				</div>
			</div>
			<div class="wpss-package-body">
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-wide">
						<label><?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_packages[<?php echo esc_attr( $index ); ?>][name]"
								value="<?php echo esc_attr( $package['name'] ?? '' ); ?>"
								class="widefat wpss-package-name-input"
								placeholder="<?php esc_attr_e( 'e.g., Standard, Premium, Enterprise', 'wp-sell-services' ); ?>">
					</div>
					<div class="wpss-package-field">
						<label>
							<span class="dashicons dashicons-money-alt"></span>
							<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix">$</span>
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][price]"
									value="<?php echo esc_attr( $package['price'] ?? '' ); ?>"
									class="wpss-package-price-input"
									min="0" step="0.01" placeholder="0.00">
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea name="wpss_packages[<?php echo esc_attr( $index ); ?>][description]"
								rows="2" class="widefat"
								placeholder="<?php esc_attr_e( 'Describe what\'s included in this package...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $package['description'] ?? '' ); ?></textarea>
					</div>
				</div>
				<div class="wpss-package-row wpss-package-row-grid">
					<div class="wpss-package-field">
						<label>
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][delivery_days]"
									value="<?php echo esc_attr( $package['delivery_days'] ?? '' ); ?>"
									min="1" max="365" placeholder="7">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-package-field">
						<label>
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[<?php echo esc_attr( $index ); ?>][revisions]"
									value="<?php echo esc_attr( $package['revisions'] ?? '' ); ?>"
									min="0" max="20" placeholder="2">
							<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label>
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?>
						</label>
						<textarea name="wpss_packages[<?php echo esc_attr( $index ); ?>][features]"
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
	 * Render FAQs metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_faqs_metabox( \WP_Post $post ): void {
		$faqs = get_post_meta( $post->ID, '_wpss_faqs', true );
		$faqs = ! empty( $faqs ) ? $faqs : array();
		?>
		<div class="wpss-faqs-wrapper">
			<p class="description"><?php esc_html_e( 'Add frequently asked questions about your service.', 'wp-sell-services' ); ?></p>
			<div id="wpss-faqs-list">
				<?php foreach ( $faqs as $index => $faq ) : ?>
					<div class="wpss-faq-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
						<div class="wpss-faq-header">
							<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
							<input type="text" name="wpss_faqs[<?php echo esc_attr( (string) $index ); ?>][question]"
									value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>"
									placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>" class="widefat">
							<button type="button" class="wpss-remove-faq" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
						<textarea name="wpss_faqs[<?php echo esc_attr( (string) $index ); ?>][answer]"
									placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"
									rows="3" class="widefat"><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" id="wpss-add-faq">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add FAQ', 'wp-sell-services' ); ?>
			</button>
		</div>

		<script type="text/html" id="tmpl-wpss-faq-item">
			<div class="wpss-faq-item" data-index="{{data.index}}">
				<div class="wpss-faq-header">
					<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
					<input type="text" name="wpss_faqs[{{data.index}}][question]"
							placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>" class="widefat">
					<button type="button" class="wpss-remove-faq" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
				<textarea name="wpss_faqs[{{data.index}}][answer]"
							placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"
							rows="3" class="widefat"></textarea>
			</div>
		</script>
		<?php
	}

	/**
	 * Render requirements metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_requirements_metabox( \WP_Post $post ): void {
		$requirements = get_post_meta( $post->ID, '_wpss_requirements', true );
		$requirements = ! empty( $requirements ) ? $requirements : array();
		?>
		<div class="wpss-requirements-wrapper">
			<p class="description"><?php esc_html_e( 'Questions to ask buyers when they place an order.', 'wp-sell-services' ); ?></p>

			<div id="wpss-requirements-list">
				<?php foreach ( $requirements as $index => $req ) : ?>
					<?php $this->render_requirement_item( $index, $req ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" id="wpss-add-requirement">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Requirement', 'wp-sell-services' ); ?>
			</button>
		</div>

		<script type="text/html" id="tmpl-wpss-requirement-item">
			<div class="wpss-requirement-item" data-index="{{data.index}}">
				<div class="wpss-requirement-row">
					<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
					<div class="wpss-requirement-fields">
						<div class="wpss-requirement-main">
							<input type="text" name="wpss_requirements[{{data.index}}][question]"
									placeholder="<?php esc_attr_e( 'Enter your question...', 'wp-sell-services' ); ?>" class="widefat">
						</div>
						<div class="wpss-requirement-options">
							<select name="wpss_requirements[{{data.index}}][type]" class="wpss-requirement-type">
								<option value="text"><?php esc_html_e( 'Short Text', 'wp-sell-services' ); ?></option>
								<option value="textarea"><?php esc_html_e( 'Long Text', 'wp-sell-services' ); ?></option>
								<option value="number"><?php esc_html_e( 'Number', 'wp-sell-services' ); ?></option>
								<option value="checkbox"><?php esc_html_e( 'Yes/No', 'wp-sell-services' ); ?></option>
								<option value="select"><?php esc_html_e( 'Dropdown', 'wp-sell-services' ); ?></option>
								<option value="radio"><?php esc_html_e( 'Multiple Choice', 'wp-sell-services' ); ?></option>
								<option value="file"><?php esc_html_e( 'File Upload', 'wp-sell-services' ); ?></option>
								<option value="multiple_files"><?php esc_html_e( 'Multiple Files', 'wp-sell-services' ); ?></option>
								<option value="date"><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></option>
							</select>
							<label class="wpss-requirement-required">
								<input type="checkbox" name="wpss_requirements[{{data.index}}][required]" value="1">
								<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
							</label>
							<button type="button" class="wpss-remove-requirement" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
						<div class="wpss-requirement-choices" style="display:none;">
							<input type="text" name="wpss_requirements[{{data.index}}][choices]"
									placeholder="<?php esc_attr_e( 'Enter choices separated by comma (e.g., Option 1, Option 2, Option 3)', 'wp-sell-services' ); ?>" class="widefat">
						</div>
					</div>
				</div>
			</div>
		</script>
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
		$show_choices = in_array( $type, array( 'select', 'radio' ), true );
		?>
		<div class="wpss-requirement-item" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="wpss-requirement-row">
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<div class="wpss-requirement-fields">
					<div class="wpss-requirement-main">
						<input type="text" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][question]"
								value="<?php echo esc_attr( $req['question'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter your question...', 'wp-sell-services' ); ?>" class="widefat">
					</div>
					<div class="wpss-requirement-options">
						<select name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][type]" class="wpss-requirement-type">
							<option value="text" <?php selected( $type, 'text' ); ?>><?php esc_html_e( 'Short Text', 'wp-sell-services' ); ?></option>
							<option value="textarea" <?php selected( $type, 'textarea' ); ?>><?php esc_html_e( 'Long Text', 'wp-sell-services' ); ?></option>
							<option value="number" <?php selected( $type, 'number' ); ?>><?php esc_html_e( 'Number', 'wp-sell-services' ); ?></option>
							<option value="checkbox" <?php selected( $type, 'checkbox' ); ?>><?php esc_html_e( 'Yes/No', 'wp-sell-services' ); ?></option>
							<option value="select" <?php selected( $type, 'select' ); ?>><?php esc_html_e( 'Dropdown', 'wp-sell-services' ); ?></option>
							<option value="radio" <?php selected( $type, 'radio' ); ?>><?php esc_html_e( 'Multiple Choice', 'wp-sell-services' ); ?></option>
							<option value="file" <?php selected( $type, 'file' ); ?>><?php esc_html_e( 'File Upload', 'wp-sell-services' ); ?></option>
							<option value="multiple_files" <?php selected( $type, 'multiple_files' ); ?>><?php esc_html_e( 'Multiple Files', 'wp-sell-services' ); ?></option>
							<option value="date" <?php selected( $type, 'date' ); ?>><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></option>
						</select>
						<label class="wpss-requirement-required">
							<input type="checkbox" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][required]"
									value="1" <?php checked( ! empty( $req['required'] ) ); ?>>
							<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
						</label>
						<button type="button" class="wpss-remove-requirement" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
					<div class="wpss-requirement-choices" <?php echo $show_choices ? '' : 'style="display:none;"'; ?>>
						<input type="text" name="wpss_requirements[<?php echo esc_attr( (string) $index ); ?>][choices]"
								value="<?php echo esc_attr( $req['choices'] ?? '' ); ?>"
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
		$gallery = get_post_meta( $post->ID, '_wpss_gallery', true );
		$gallery = ! empty( $gallery ) ? $gallery : array();
		?>
		<div class="wpss-gallery-wrapper">
			<div id="wpss-gallery-images" class="wpss-gallery-grid">
				<?php foreach ( $gallery as $attachment_id ) : ?>
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
				<span class="dashicons dashicons-format-gallery"></span>
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
		$view_count     = get_post_meta( $post->ID, '_wpss_view_count', true );
		$view_count     = ! empty( $view_count ) ? $view_count : 0;
		?>
		<div class="wpss-stats-wrapper">
			<div class="wpss-stats-grid">
				<div class="wpss-stat-item">
					<span class="wpss-stat-icon dashicons dashicons-cart"></span>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( (string) $order_count ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-item">
					<span class="wpss-stat-icon dashicons dashicons-star-filled" style="color: #f5a623;"></span>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( number_format( (float) $average_rating, 1 ) ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-item">
					<span class="wpss-stat-icon dashicons dashicons-testimonial"></span>
					<div class="wpss-stat-data">
						<span class="wpss-stat-value"><?php echo esc_html( (string) $review_count ); ?></span>
						<span class="wpss-stat-label"><?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-stat-item">
					<span class="wpss-stat-icon dashicons dashicons-visibility"></span>
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
	 * Render addons metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_addons_metabox( \WP_Post $post ): void {
		$addons = get_post_meta( $post->ID, '_wpss_addons', true );
		if ( empty( $addons ) || ! is_array( $addons ) ) {
			$addons = array();
		}

		$field_types = array(
			'checkbox' => __( 'Checkbox (Yes/No)', 'wp-sell-services' ),
			'quantity' => __( 'Quantity Selector', 'wp-sell-services' ),
			'dropdown' => __( 'Dropdown Select', 'wp-sell-services' ),
			'text'     => __( 'Text Input', 'wp-sell-services' ),
		);

		$price_types = array(
			'flat'           => __( 'Flat Price', 'wp-sell-services' ),
			'percentage'     => __( 'Percentage of Order', 'wp-sell-services' ),
			'quantity_based' => __( 'Per Quantity', 'wp-sell-services' ),
		);
		?>
		<div class="wpss-addons-wrapper">
			<p class="description"><?php esc_html_e( 'Add extra services buyers can purchase with this service.', 'wp-sell-services' ); ?></p>

			<div id="wpss-addons-list">
				<?php foreach ( $addons as $index => $addon ) : ?>
					<?php $this->render_addon_item( $index, $addon, $field_types, $price_types ); ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button button-secondary" id="wpss-add-addon">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Add-on', 'wp-sell-services' ); ?>
			</button>
		</div>

		<script type="text/html" id="tmpl-wpss-addon-item">
			<div class="wpss-addon-item" data-index="{{data.index}}">
				<div class="wpss-addon-header">
					<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
					<span class="wpss-addon-title"><?php esc_html_e( 'New Add-on', 'wp-sell-services' ); ?></span>
					<div class="wpss-addon-actions">
						<button type="button" class="wpss-addon-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<button type="button" class="wpss-remove-addon" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
				<div class="wpss-addon-body">
					<div class="wpss-addon-row">
						<div class="wpss-addon-field wpss-addon-field-full">
							<label><?php esc_html_e( 'Title', 'wp-sell-services' ); ?></label>
							<input type="text" name="wpss_addons[{{data.index}}][title]"
									placeholder="<?php esc_attr_e( 'e.g., Extra Fast Delivery', 'wp-sell-services' ); ?>" class="widefat wpss-addon-title-input">
						</div>
					</div>
					<div class="wpss-addon-row">
						<div class="wpss-addon-field wpss-addon-field-full">
							<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
							<textarea name="wpss_addons[{{data.index}}][description]" rows="2" class="widefat"
										placeholder="<?php esc_attr_e( 'Brief description of this add-on...', 'wp-sell-services' ); ?>"></textarea>
						</div>
					</div>
					<div class="wpss-addon-row wpss-addon-row-grid">
						<div class="wpss-addon-field">
							<label><?php esc_html_e( 'Field Type', 'wp-sell-services' ); ?></label>
							<select name="wpss_addons[{{data.index}}][field_type]" class="wpss-addon-field-type">
								<?php foreach ( $field_types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wpss-addon-field">
							<label><?php esc_html_e( 'Price Type', 'wp-sell-services' ); ?></label>
							<select name="wpss_addons[{{data.index}}][price_type]" class="wpss-addon-price-type">
								<?php foreach ( $price_types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wpss-addon-field">
							<label><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></label>
							<div class="wpss-input-with-prefix">
								<span class="wpss-input-prefix">$</span>
								<input type="number" name="wpss_addons[{{data.index}}][price]"
										min="0" step="0.01" placeholder="0.00">
							</div>
						</div>
					</div>
					<div class="wpss-addon-row wpss-addon-row-grid wpss-addon-quantity-fields" style="display: none;">
						<div class="wpss-addon-field">
							<label><?php esc_html_e( 'Min Quantity', 'wp-sell-services' ); ?></label>
							<input type="number" name="wpss_addons[{{data.index}}][min_quantity]"
									value="1" min="1" max="100">
						</div>
						<div class="wpss-addon-field">
							<label><?php esc_html_e( 'Max Quantity', 'wp-sell-services' ); ?></label>
							<input type="number" name="wpss_addons[{{data.index}}][max_quantity]"
									value="10" min="1" max="100">
						</div>
					</div>
					<div class="wpss-addon-row wpss-addon-dropdown-fields" style="display: none;">
						<div class="wpss-addon-field wpss-addon-field-full">
							<label><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
							<input type="text" name="wpss_addons[{{data.index}}][options]" class="widefat"
									placeholder="<?php esc_attr_e( 'Option 1, Option 2, Option 3 (comma separated)', 'wp-sell-services' ); ?>">
						</div>
					</div>
					<div class="wpss-addon-row wpss-addon-row-grid">
						<div class="wpss-addon-field">
							<label><?php esc_html_e( 'Extra Delivery Days', 'wp-sell-services' ); ?></label>
							<div class="wpss-input-with-suffix">
								<input type="number" name="wpss_addons[{{data.index}}][delivery_days_extra]"
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
		</script>
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
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<span class="wpss-addon-title"><?php echo esc_html( $title ); ?></span>
				<div class="wpss-addon-actions">
					<button type="button" class="wpss-addon-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="wpss-remove-addon" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>
			<div class="wpss-addon-body">
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label><?php esc_html_e( 'Title', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][title]"
								value="<?php echo esc_attr( $addon['title'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g., Extra Fast Delivery', 'wp-sell-services' ); ?>" class="widefat wpss-addon-title-input">
					</div>
				</div>
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][description]" rows="2" class="widefat"
									placeholder="<?php esc_attr_e( 'Brief description of this add-on...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $addon['description'] ?? '' ); ?></textarea>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Field Type', 'wp-sell-services' ); ?></label>
						<select name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][field_type]" class="wpss-addon-field-type">
							<?php foreach ( $field_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Price Type', 'wp-sell-services' ); ?></label>
						<select name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][price_type]" class="wpss-addon-price-type">
							<?php foreach ( $price_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $price_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix">$</span>
							<input type="number" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][price]"
									value="<?php echo esc_attr( $addon['price'] ?? '' ); ?>"
									min="0" step="0.01" placeholder="0.00">
						</div>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid wpss-addon-quantity-fields" <?php echo $show_quantity ? '' : 'style="display: none;"'; ?>>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Min Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][min_quantity]"
								value="<?php echo esc_attr( $addon['min_quantity'] ?? '1' ); ?>" min="1" max="100">
					</div>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Max Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][max_quantity]"
								value="<?php echo esc_attr( $addon['max_quantity'] ?? '10' ); ?>" min="1" max="100">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-dropdown-fields" <?php echo $show_dropdown ? '' : 'style="display: none;"'; ?>>
					<div class="wpss-addon-field wpss-addon-field-full">
						<label><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][options]" class="widefat"
								value="<?php echo esc_attr( $addon['options'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Option 1, Option 2, Option 3 (comma separated)', 'wp-sell-services' ); ?>">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Extra Delivery Days', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_addons[<?php echo esc_attr( (string) $index ); ?>][delivery_days_extra]"
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
					$packages[] = array(
						'name'          => sanitize_text_field( $package['name'] ?? '' ),
						'description'   => sanitize_textarea_field( $package['description'] ?? '' ),
						'price'         => (float) ( $package['price'] ?? 0 ),
						'delivery_days' => absint( $package['delivery_days'] ?? 0 ),
						'revisions'     => absint( $package['revisions'] ?? 0 ),
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
			$fastest_delivery = ! empty( $delivery_days ) ? min( $delivery_days ) : 7;
			update_post_meta( $post_id, '_wpss_fastest_delivery', $fastest_delivery );

			// Max revisions = maximum revisions across packages.
			$max_revisions = ! empty( $revisions ) ? max( $revisions ) : 0;
			update_post_meta( $post_id, '_wpss_max_revisions', $max_revisions );
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
		}

		// Save requirements.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$requirements_data = isset( $_POST['wpss_requirements'] ) ? wp_unslash( $_POST['wpss_requirements'] ) : array();
		if ( is_array( $requirements_data ) && ! empty( $requirements_data ) ) {
			$requirements = array();
			$valid_types  = array( 'text', 'textarea', 'number', 'checkbox', 'select', 'radio', 'file', 'multiple_files', 'date' );
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
					if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $req['choices'] ) ) {
						$requirement['choices'] = sanitize_text_field( $req['choices'] );
					}
					$requirements[] = $requirement;
				}
			}
			update_post_meta( $post_id, '_wpss_requirements', $requirements );
		}

		// Save addons.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$addons_data = isset( $_POST['wpss_addons'] ) ? wp_unslash( $_POST['wpss_addons'] ) : array();
		if ( is_array( $addons_data ) && ! empty( $addons_data ) ) {
			$addons      = array();
			$valid_types = array( 'checkbox', 'quantity', 'dropdown', 'text' );
			$valid_price = array( 'flat', 'percentage', 'quantity_based' );

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
		} else {
			delete_post_meta( $post_id, '_wpss_addons' );
		}

		// Save gallery.
		if ( isset( $_POST['wpss_gallery'] ) && is_array( $_POST['wpss_gallery'] ) ) {
			$gallery = array_map( 'absint', $_POST['wpss_gallery'] );
			$gallery = array_filter( $gallery );
			update_post_meta( $post_id, '_wpss_gallery', $gallery );
		} else {
			delete_post_meta( $post_id, '_wpss_gallery' );
		}

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
		return array(
			'overview'     => array(
				'label'    => __( 'Overview', 'wp-sell-services' ),
				'icon'     => 'dashicons-info',
				'priority' => 10,
			),
			'pricing'      => array(
				'label'    => __( 'Pricing', 'wp-sell-services' ),
				'icon'     => 'dashicons-money-alt',
				'priority' => 20,
			),
			'media'        => array(
				'label'    => __( 'Media', 'wp-sell-services' ),
				'icon'     => 'dashicons-format-gallery',
				'priority' => 30,
			),
			'addons'       => array(
				'label'    => __( 'Add-ons', 'wp-sell-services' ),
				'icon'     => 'dashicons-plus-alt',
				'priority' => 40,
			),
			'requirements' => array(
				'label'    => __( 'Requirements', 'wp-sell-services' ),
				'icon'     => 'dashicons-list-view',
				'priority' => 50,
			),
			'faq'          => array(
				'label'    => __( 'FAQ', 'wp-sell-services' ),
				'icon'     => 'dashicons-editor-help',
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
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
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
			<?php $this->render_requirements_content( $post ); ?>
		</div>
		<div id="wpss_faq_panel" class="wpss-panel">
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
		$view_count     = (int) get_post_meta( $post->ID, '_wpss_view_count', true );
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Overview', 'wp-sell-services' ); ?></h3>

		<div class="wpss-overview-grid">
			<div class="wpss-overview-section">
				<h4><?php esc_html_e( 'Service Status', 'wp-sell-services' ); ?></h4>
				<div class="wpss-details-grid">
					<div class="wpss-detail-card">
						<div class="wpss-detail-icon">
							<span class="dashicons dashicons-visibility"></span>
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
						<span class="wpss-stat-icon dashicons dashicons-cart"></span>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( (string) $order_count ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-stat-item">
						<span class="wpss-stat-icon dashicons dashicons-star-filled" style="color: #f5a623;"></span>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( number_format( $average_rating, 1 ) ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Rating', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-stat-item">
						<span class="wpss-stat-icon dashicons dashicons-testimonial"></span>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( (string) $review_count ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-stat-item">
						<span class="wpss-stat-icon dashicons dashicons-visibility"></span>
						<div class="wpss-stat-data">
							<span class="wpss-stat-value"><?php echo esc_html( (string) $view_count ); ?></span>
							<span class="wpss-stat-label"><?php esc_html_e( 'Views', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<p class="wpss-details-note">
			<span class="dashicons dashicons-info-outline"></span>
			<?php esc_html_e( 'Delivery time and revisions are configured per package in the Pricing tab.', 'wp-sell-services' ); ?>
		</p>
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
			<span class="dashicons dashicons-plus-alt2"></span>
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
		$gallery = get_post_meta( $post->ID, '_wpss_gallery', true );
		$gallery = ! empty( $gallery ) ? $gallery : array();
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Gallery', 'wp-sell-services' ); ?></h3>

		<p class="description"><?php esc_html_e( 'Add images to showcase your service. Drag to reorder.', 'wp-sell-services' ); ?></p>

		<div class="wpss-gallery-wrapper wpss-media-panel">
			<div id="wpss-gallery-images" class="wpss-gallery-grid">
				<?php foreach ( $gallery as $attachment_id ) : ?>
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
				<span class="dashicons dashicons-format-gallery"></span>
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
		$addons = get_post_meta( $post->ID, '_wpss_addons', true );
		if ( empty( $addons ) || ! is_array( $addons ) ) {
			$addons = array();
		}

		$field_types = array(
			'checkbox' => __( 'Checkbox (Yes/No)', 'wp-sell-services' ),
			'quantity' => __( 'Quantity Selector', 'wp-sell-services' ),
			'dropdown' => __( 'Dropdown Select', 'wp-sell-services' ),
			'text'     => __( 'Text Input', 'wp-sell-services' ),
		);

		$price_types = array(
			'flat'           => __( 'Flat Price', 'wp-sell-services' ),
			'percentage'     => __( 'Percentage of Order', 'wp-sell-services' ),
			'quantity_based' => __( 'Per Quantity', 'wp-sell-services' ),
		);
		?>
		<h3 class="wpss-panel-title"><?php esc_html_e( 'Service Add-ons', 'wp-sell-services' ); ?></h3>

		<p class="description"><?php esc_html_e( 'Add extra services buyers can purchase with this service.', 'wp-sell-services' ); ?></p>

		<div id="wpss-addons-list">
			<?php foreach ( $addons as $index => $addon ) : ?>
				<?php $this->render_addon_item( $index, $addon, $field_types, $price_types ); ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button button-secondary" id="wpss-add-addon">
			<span class="dashicons dashicons-plus-alt2"></span>
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
			<span class="dashicons dashicons-plus-alt2"></span>
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
						<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
						<input type="text" name="wpss_faqs[<?php echo esc_attr( (string) $index ); ?>][question]"
								value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>" class="widefat">
						<button type="button" class="wpss-remove-faq" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
					<textarea name="wpss_faqs[<?php echo esc_attr( (string) $index ); ?>][answer]"
								placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"
								rows="3" class="widefat"><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button button-secondary" id="wpss-add-faq">
			<span class="dashicons dashicons-plus-alt2"></span>
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
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<span class="wpss-package-title"><?php esc_html_e( 'New Package', 'wp-sell-services' ); ?></span>
				<span class="wpss-package-price-display"></span>
				<div class="wpss-package-actions">
					<button type="button" class="wpss-package-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="wpss-remove-package" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>
			<div class="wpss-package-body">
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-wide">
						<label><?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_packages[{{data.index}}][name]"
								class="widefat wpss-package-name-input"
								placeholder="<?php esc_attr_e( 'e.g., Standard, Premium, Enterprise', 'wp-sell-services' ); ?>">
					</div>
					<div class="wpss-package-field">
						<label>
							<span class="dashicons dashicons-money-alt"></span>
							<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix">$</span>
							<input type="number" name="wpss_packages[{{data.index}}][price]"
									class="wpss-package-price-input"
									min="0" step="0.01" placeholder="0.00">
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea name="wpss_packages[{{data.index}}][description]"
								rows="2" class="widefat"
								placeholder="<?php esc_attr_e( 'Describe what\'s included in this package...', 'wp-sell-services' ); ?>"></textarea>
					</div>
				</div>
				<div class="wpss-package-row wpss-package-row-grid">
					<div class="wpss-package-field">
						<label>
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[{{data.index}}][delivery_days]"
									min="1" max="365" placeholder="7">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-package-field">
						<label>
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_packages[{{data.index}}][revisions]"
									min="0" max="20" placeholder="2">
							<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
				<div class="wpss-package-row">
					<div class="wpss-package-field wpss-package-field-full">
						<label>
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?>
						</label>
						<textarea name="wpss_packages[{{data.index}}][features]"
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
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<span class="wpss-addon-title"><?php esc_html_e( 'New Add-on', 'wp-sell-services' ); ?></span>
				<div class="wpss-addon-actions">
					<button type="button" class="wpss-addon-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="wpss-remove-addon" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>
			<div class="wpss-addon-body">
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label><?php esc_html_e( 'Title', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_addons[{{data.index}}][title]"
								placeholder="<?php esc_attr_e( 'e.g., Extra Fast Delivery', 'wp-sell-services' ); ?>" class="widefat wpss-addon-title-input">
					</div>
				</div>
				<div class="wpss-addon-row">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
						<textarea name="wpss_addons[{{data.index}}][description]" rows="2" class="widefat"
									placeholder="<?php esc_attr_e( 'Brief description of this add-on...', 'wp-sell-services' ); ?>"></textarea>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Field Type', 'wp-sell-services' ); ?></label>
						<select name="wpss_addons[{{data.index}}][field_type]" class="wpss-addon-field-type">
							<?php foreach ( $field_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Price Type', 'wp-sell-services' ); ?></label>
						<select name="wpss_addons[{{data.index}}][price_type]" class="wpss-addon-price-type">
							<?php foreach ( $price_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-prefix">
							<span class="wpss-input-prefix">$</span>
							<input type="number" name="wpss_addons[{{data.index}}][price]"
									min="0" step="0.01" placeholder="0.00">
						</div>
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid wpss-addon-quantity-fields" style="display: none;">
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Min Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" name="wpss_addons[{{data.index}}][min_quantity]"
								value="1" min="1" max="100">
					</div>
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Max Quantity', 'wp-sell-services' ); ?></label>
						<input type="number" name="wpss_addons[{{data.index}}][max_quantity]"
								value="10" min="1" max="100">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-dropdown-fields" style="display: none;">
					<div class="wpss-addon-field wpss-addon-field-full">
						<label><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
						<input type="text" name="wpss_addons[{{data.index}}][options]" class="widefat"
								placeholder="<?php esc_attr_e( 'Option 1, Option 2, Option 3 (comma separated)', 'wp-sell-services' ); ?>">
					</div>
				</div>
				<div class="wpss-addon-row wpss-addon-row-grid">
					<div class="wpss-addon-field">
						<label><?php esc_html_e( 'Extra Delivery Days', 'wp-sell-services' ); ?></label>
						<div class="wpss-input-with-suffix">
							<input type="number" name="wpss_addons[{{data.index}}][delivery_days_extra]"
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
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<div class="wpss-requirement-fields">
					<div class="wpss-requirement-main">
						<input type="text" name="wpss_requirements[{{data.index}}][question]"
								placeholder="<?php esc_attr_e( 'Enter your question...', 'wp-sell-services' ); ?>" class="widefat">
					</div>
					<div class="wpss-requirement-options">
						<select name="wpss_requirements[{{data.index}}][type]" class="wpss-requirement-type">
							<option value="text"><?php esc_html_e( 'Short Text', 'wp-sell-services' ); ?></option>
							<option value="textarea"><?php esc_html_e( 'Long Text', 'wp-sell-services' ); ?></option>
							<option value="number"><?php esc_html_e( 'Number', 'wp-sell-services' ); ?></option>
							<option value="checkbox"><?php esc_html_e( 'Yes/No', 'wp-sell-services' ); ?></option>
							<option value="select"><?php esc_html_e( 'Dropdown', 'wp-sell-services' ); ?></option>
							<option value="radio"><?php esc_html_e( 'Multiple Choice', 'wp-sell-services' ); ?></option>
							<option value="file"><?php esc_html_e( 'File Upload', 'wp-sell-services' ); ?></option>
							<option value="multiple_files"><?php esc_html_e( 'Multiple Files', 'wp-sell-services' ); ?></option>
							<option value="date"><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></option>
						</select>
						<label class="wpss-requirement-required">
							<input type="checkbox" name="wpss_requirements[{{data.index}}][required]" value="1">
							<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
						</label>
						<button type="button" class="wpss-remove-requirement" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
					<div class="wpss-requirement-choices" style="display:none;">
						<input type="text" name="wpss_requirements[{{data.index}}][choices]"
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
				<span class="dashicons dashicons-menu wpss-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-sell-services' ); ?>"></span>
				<input type="text" name="wpss_faqs[{{data.index}}][question]"
						placeholder="<?php esc_attr_e( 'Enter question...', 'wp-sell-services' ); ?>" class="widefat">
				<button type="button" class="wpss-remove-faq" title="<?php esc_attr_e( 'Remove', 'wp-sell-services' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
			<textarea name="wpss_faqs[{{data.index}}][answer]"
						placeholder="<?php esc_attr_e( 'Enter answer...', 'wp-sell-services' ); ?>"
						rows="3" class="widefat"></textarea>
		</div>
		<?php
	}
}
