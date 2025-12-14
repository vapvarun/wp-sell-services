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
		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
		add_action( 'save_post_' . ServicePostType::POST_TYPE, [ $this, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes(): void {
		add_meta_box(
			'wpss_service_details',
			__( 'Service Details', 'wp-sell-services' ),
			[ $this, 'render_details_metabox' ],
			ServicePostType::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpss_service_packages',
			__( 'Pricing Packages', 'wp-sell-services' ),
			[ $this, 'render_packages_metabox' ],
			ServicePostType::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpss_service_faqs',
			__( 'FAQs', 'wp-sell-services' ),
			[ $this, 'render_faqs_metabox' ],
			ServicePostType::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'wpss_service_requirements',
			__( 'Buyer Requirements', 'wp-sell-services' ),
			[ $this, 'render_requirements_metabox' ],
			ServicePostType::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'wpss_service_gallery',
			__( 'Gallery', 'wp-sell-services' ),
			[ $this, 'render_gallery_metabox' ],
			ServicePostType::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'wpss_service_stats',
			__( 'Statistics', 'wp-sell-services' ),
			[ $this, 'render_stats_metabox' ],
			ServicePostType::POST_TYPE,
			'side',
			'default'
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

		if ( $post_type !== ServicePostType::POST_TYPE ) {
			return;
		}

		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Render service details metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'wpss_service_meta', 'wpss_service_nonce' );

		$delivery_time = get_post_meta( $post->ID, '_wpss_delivery_time', true );
		$revision_limit = get_post_meta( $post->ID, '_wpss_revision_limit', true );
		$status = get_post_meta( $post->ID, '_wpss_status', true ) ?: 'active';
		?>
		<div class="wpss-details-wrapper">
			<div class="wpss-details-grid">
				<div class="wpss-detail-card">
					<div class="wpss-detail-icon">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="wpss-detail-content">
						<label for="wpss_delivery_time"><?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?></label>
						<div class="wpss-detail-input">
							<input type="number" id="wpss_delivery_time" name="wpss_delivery_time"
								   value="<?php echo esc_attr( $delivery_time ); ?>" min="1" max="365" placeholder="7">
							<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'Default delivery time for this service', 'wp-sell-services' ); ?></p>
					</div>
				</div>

				<div class="wpss-detail-card">
					<div class="wpss-detail-icon">
						<span class="dashicons dashicons-update"></span>
					</div>
					<div class="wpss-detail-content">
						<label for="wpss_revision_limit"><?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?></label>
						<div class="wpss-detail-input">
							<input type="number" id="wpss_revision_limit" name="wpss_revision_limit"
								   value="<?php echo esc_attr( $revision_limit ); ?>" min="0" max="20" placeholder="2">
							<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'Number of free revisions included', 'wp-sell-services' ); ?></p>
					</div>
				</div>

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
		<?php
	}

	/**
	 * Render packages metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_packages_metabox( \WP_Post $post ): void {
		$packages = get_post_meta( $post->ID, '_wpss_packages', true ) ?: [];

		// Ensure at least Basic package exists.
		$default_packages = [
			'basic'    => [
				'name'          => __( 'Basic', 'wp-sell-services' ),
				'description'   => '',
				'price'         => '',
				'delivery_days' => '',
				'revisions'     => '',
				'features'      => [],
			],
			'standard' => [
				'name'          => __( 'Standard', 'wp-sell-services' ),
				'description'   => '',
				'price'         => '',
				'delivery_days' => '',
				'revisions'     => '',
				'features'      => [],
			],
			'premium'  => [
				'name'          => __( 'Premium', 'wp-sell-services' ),
				'description'   => '',
				'price'         => '',
				'delivery_days' => '',
				'revisions'     => '',
				'features'      => [],
			],
		];

		$packages = wp_parse_args( $packages, $default_packages );
		$tier_icons = [
			'basic'    => 'dashicons-star-empty',
			'standard' => 'dashicons-star-half',
			'premium'  => 'dashicons-star-filled',
		];
		?>
		<div class="wpss-packages-wrapper">
			<p class="description"><?php esc_html_e( 'Define pricing tiers for your service. At minimum, Basic package is required.', 'wp-sell-services' ); ?></p>

			<div class="wpss-packages-nav">
				<?php $first = true; ?>
				<?php foreach ( $packages as $tier => $package ) : ?>
					<button type="button" class="wpss-package-nav-btn <?php echo $first ? 'active' : ''; ?>" data-tier="<?php echo esc_attr( $tier ); ?>">
						<span class="dashicons <?php echo esc_attr( $tier_icons[ $tier ] ?? 'dashicons-star-empty' ); ?>"></span>
						<?php echo esc_html( ucfirst( $tier ) ); ?>
					</button>
					<?php $first = false; ?>
				<?php endforeach; ?>
			</div>

			<div class="wpss-packages-content">
				<?php $first = true; ?>
				<?php foreach ( $packages as $tier => $package ) : ?>
					<div class="wpss-package-panel <?php echo $first ? 'active' : ''; ?>" data-tier="<?php echo esc_attr( $tier ); ?>">
						<div class="wpss-package-fields">
							<div class="wpss-field-row">
								<div class="wpss-field-group wpss-field-half">
									<label><?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?></label>
									<input type="text" name="wpss_packages[<?php echo esc_attr( $tier ); ?>][name]"
										   value="<?php echo esc_attr( $package['name'] ?? '' ); ?>" class="widefat"
										   placeholder="<?php echo esc_attr( ucfirst( $tier ) ); ?>">
								</div>
								<div class="wpss-field-group wpss-field-quarter">
									<label>
										<span class="dashicons dashicons-money-alt"></span>
										<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
									</label>
									<div class="wpss-input-with-prefix">
										<span class="wpss-input-prefix">$</span>
										<input type="number" name="wpss_packages[<?php echo esc_attr( $tier ); ?>][price]"
											   value="<?php echo esc_attr( $package['price'] ?? '' ); ?>"
											   min="0" step="0.01" placeholder="0.00">
									</div>
								</div>
							</div>

							<div class="wpss-field-row">
								<div class="wpss-field-group wpss-field-full">
									<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
									<textarea name="wpss_packages[<?php echo esc_attr( $tier ); ?>][description]"
											  rows="2" class="widefat"
											  placeholder="<?php esc_attr_e( 'Describe what\'s included in this package...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $package['description'] ?? '' ); ?></textarea>
								</div>
							</div>

							<div class="wpss-field-row">
								<div class="wpss-field-group wpss-field-third">
									<label>
										<span class="dashicons dashicons-clock"></span>
										<?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?>
									</label>
									<div class="wpss-input-with-suffix">
										<input type="number" name="wpss_packages[<?php echo esc_attr( $tier ); ?>][delivery_days]"
											   value="<?php echo esc_attr( $package['delivery_days'] ?? '' ); ?>"
											   min="1" max="365" placeholder="7">
										<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
									</div>
								</div>
								<div class="wpss-field-group wpss-field-third">
									<label>
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?>
									</label>
									<div class="wpss-input-with-suffix">
										<input type="number" name="wpss_packages[<?php echo esc_attr( $tier ); ?>][revisions]"
											   value="<?php echo esc_attr( $package['revisions'] ?? '' ); ?>"
											   min="0" max="20" placeholder="2">
										<span class="wpss-input-suffix"><?php esc_html_e( 'times', 'wp-sell-services' ); ?></span>
									</div>
								</div>
							</div>

							<div class="wpss-field-row">
								<div class="wpss-field-group wpss-field-full">
									<label>
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?>
									</label>
									<textarea name="wpss_packages[<?php echo esc_attr( $tier ); ?>][features]"
											  rows="4" class="widefat"
											  placeholder="<?php esc_attr_e( "Feature 1\nFeature 2\nFeature 3", 'wp-sell-services' ); ?>"><?php echo esc_textarea( implode( "\n", (array) ( $package['features'] ?? [] ) ) ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Enter one feature per line', 'wp-sell-services' ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<?php $first = false; ?>
				<?php endforeach; ?>
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
		$faqs = get_post_meta( $post->ID, '_wpss_faqs', true ) ?: [];
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
		$requirements = get_post_meta( $post->ID, '_wpss_requirements', true ) ?: [];
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
		$type = $req['type'] ?? 'text';
		$show_choices = in_array( $type, [ 'select', 'radio' ], true );
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
		$gallery = get_post_meta( $post->ID, '_wpss_gallery', true ) ?: [];
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
		$order_count = get_post_meta( $post->ID, '_wpss_order_count', true ) ?: 0;
		$review_count = get_post_meta( $post->ID, '_wpss_review_count', true ) ?: 0;
		$average_rating = get_post_meta( $post->ID, '_wpss_average_rating', true ) ?: 0;
		$view_count = get_post_meta( $post->ID, '_wpss_view_count', true ) ?: 0;
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

		// Save simple fields.
		if ( isset( $_POST['wpss_delivery_time'] ) ) {
			update_post_meta( $post_id, '_wpss_delivery_time', absint( $_POST['wpss_delivery_time'] ) );
		}

		if ( isset( $_POST['wpss_revision_limit'] ) ) {
			update_post_meta( $post_id, '_wpss_revision_limit', absint( $_POST['wpss_revision_limit'] ) );
		}

		if ( isset( $_POST['wpss_status'] ) ) {
			update_post_meta( $post_id, '_wpss_status', sanitize_key( $_POST['wpss_status'] ) );
		}

		// Save packages.
		if ( isset( $_POST['wpss_packages'] ) && is_array( $_POST['wpss_packages'] ) ) {
			$packages = [];
			foreach ( $_POST['wpss_packages'] as $tier => $package ) {
				$packages[ sanitize_key( $tier ) ] = [
					'name'          => sanitize_text_field( $package['name'] ?? '' ),
					'description'   => sanitize_textarea_field( $package['description'] ?? '' ),
					'price'         => (float) ( $package['price'] ?? 0 ),
					'delivery_days' => absint( $package['delivery_days'] ?? 0 ),
					'revisions'     => absint( $package['revisions'] ?? 0 ),
					'features'      => array_filter( array_map( 'sanitize_text_field', explode( "\n", $package['features'] ?? '' ) ) ),
				];
			}
			update_post_meta( $post_id, '_wpss_packages', $packages );

			// Update starting price.
			$prices = array_filter( wp_list_pluck( $packages, 'price' ) );
			$starting_price = ! empty( $prices ) ? min( $prices ) : 0;
			update_post_meta( $post_id, '_wpss_starting_price', $starting_price );
		}

		// Save FAQs.
		if ( isset( $_POST['wpss_faqs'] ) && is_array( $_POST['wpss_faqs'] ) ) {
			$faqs = [];
			foreach ( $_POST['wpss_faqs'] as $faq ) {
				if ( ! empty( $faq['question'] ) ) {
					$faqs[] = [
						'question' => sanitize_text_field( $faq['question'] ),
						'answer'   => sanitize_textarea_field( $faq['answer'] ?? '' ),
					];
				}
			}
			update_post_meta( $post_id, '_wpss_faqs', $faqs );
		}

		// Save requirements.
		if ( isset( $_POST['wpss_requirements'] ) && is_array( $_POST['wpss_requirements'] ) ) {
			$requirements = [];
			$valid_types  = [ 'text', 'textarea', 'number', 'checkbox', 'select', 'radio', 'file', 'multiple_files', 'date' ];
			foreach ( $_POST['wpss_requirements'] as $req ) {
				if ( ! empty( $req['question'] ) ) {
					$type = sanitize_key( $req['type'] ?? 'text' );
					if ( ! in_array( $type, $valid_types, true ) ) {
						$type = 'text';
					}
					$requirement = [
						'question' => sanitize_text_field( $req['question'] ),
						'type'     => $type,
						'required' => ! empty( $req['required'] ),
					];
					// Save choices for select and radio types.
					if ( in_array( $type, [ 'select', 'radio' ], true ) && ! empty( $req['choices'] ) ) {
						$requirement['choices'] = sanitize_text_field( $req['choices'] );
					}
					$requirements[] = $requirement;
				}
			}
			update_post_meta( $post_id, '_wpss_requirements', $requirements );
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
}
