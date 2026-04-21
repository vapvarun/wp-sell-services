<?php
/**
 * Template: Order Requirements
 *
 * Displays the requirements form for buyers to submit after purchase.
 * Uses CSS classes from orders.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $order_id Order ID passed from parent template.
 *
 * Available Hooks:
 * - wpss_before_requirements_form
 * - wpss_requirements_form_fields
 * - wpss_after_requirements_form
 * - wpss_requirements_form_args (filter)
 */

defined( 'ABSPATH' ) || exit;

// Enqueue orders styles.
wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );

// Enqueue requirements form script and localize wpss_ajax.
wp_enqueue_script( 'wpss-requirements-form', WPSS_PLUGIN_URL . 'assets/js/requirements-form.js', array( 'jquery' ), WPSS_VERSION, true );
wp_localize_script(
	'wpss-requirements-form',
	'wpss_ajax',
	array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'i18n'     => array(
			'submit_error' => __( 'Failed to submit requirements.', 'wp-sell-services' ),
			'ajax_error'   => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
		),
	)
);

if ( empty( $order_id ) ) {
	return;
}

$order = wpss_get_order( $order_id );

if ( ! $order ) {
	echo '<div class="wpss-notice wpss-notice--error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</div>';
	return;
}

$user_id     = get_current_user_id();
$is_customer = (int) $order->customer_id === $user_id;

// Only customers can submit requirements.
if ( ! $is_customer && ! current_user_can( 'manage_options' ) ) {
	echo '<div class="wpss-notice wpss-notice--error">' . esc_html__( 'You do not have permission to view this page.', 'wp-sell-services' ) . '</div>';
	return;
}

// Check if requirements are needed.
if ( ! in_array( $order->status, array( 'pending_requirements', 'pending_payment' ), true ) ) {
	wp_safe_redirect( wpss_get_order_url( $order_id ) );
	exit;
}

$service      = get_post( $order->service_id );
$vendor       = get_userdata( $order->vendor_id );
$requirements = wpss_get_service_requirements( $order->service_id );

// Check if requirements already submitted.
$submitted_requirements = wpss_get_order_requirements( $order_id );

/**
 * Hook: wpss_before_requirements_form
 *
 * Fires before the requirements form is displayed.
 *
 * @since 1.0.0
 *
 * @param object $order Order object.
 */
do_action( 'wpss_before_requirements_form', $order );
?>

<div class="wpss-requirements-page">
	<div class="wpss-requirements-page__header">
		<h1 class="wpss-requirements-page__title"><?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?></h1>
		<p class="wpss-requirements-page__intro">
			<?php
			printf(
				/* translators: %s: vendor name */
				esc_html__( 'Please provide the information %s needs to start working on your order.', 'wp-sell-services' ),
				esc_html( $vendor ? $vendor->display_name : __( 'the seller', 'wp-sell-services' ) )
			);
			?>
		</p>
	</div>

	<div class="wpss-requirements-page__layout">
		<div class="wpss-requirements-page__main">
			<!-- Order Summary -->
			<div class="wpss-requirements-page__summary">
				<?php if ( $service && has_post_thumbnail( $service->ID ) ) : ?>
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->ID, 'thumbnail' ) ); ?>"
						alt="<?php echo esc_attr( $service->post_title ); ?>"
						class="wpss-requirements-page__summary-thumb">
				<?php endif; ?>
				<div class="wpss-requirements-page__summary-info">
					<h3 class="wpss-requirements-page__summary-title"><?php echo esc_html( $service ? $service->post_title : __( 'Service', 'wp-sell-services' ) ); ?></h3>
					<p class="wpss-requirements-page__summary-order">
						<?php
						printf(
							/* translators: %s: order number */
							esc_html__( 'Order #%s', 'wp-sell-services' ),
							esc_html( $order->order_number )
						);
						?>
					</p>
				</div>
			</div>

			<!-- Requirements Form -->
			<?php
			$form_args = array(
				'form_id'    => 'wpss-requirements-form',
				'form_class' => 'wpss-requirements-form',
				'order_id'   => $order_id,
				'order'      => $order,
			);

			/**
			 * Filter: wpss_requirements_form_args
			 *
			 * Filters the requirements form arguments.
			 *
			 * @since 1.0.0
			 *
			 * @param array  $form_args Array of form arguments.
			 * @param object $order     Order object.
			 */
			$form_args = apply_filters( 'wpss_requirements_form_args', $form_args, $order );
			?>
			<form class="<?php echo esc_attr( $form_args['form_class'] ); ?>" id="<?php echo esc_attr( $form_args['form_id'] ); ?>" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>
				<input type="hidden" name="action" value="wpss_submit_requirements">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

				<?php if ( ! empty( $requirements ) ) : ?>
					<?php foreach ( $requirements as $index => $requirement ) : ?>
						<div class="wpss-requirements-form__field">
							<label class="wpss-requirements-form__label" for="requirement_<?php echo esc_attr( $index ); ?>">
								<?php echo esc_html( $requirement['question'] ); ?>
								<?php if ( ! empty( $requirement['required'] ) ) : ?>
									<span class="wpss-requirements-form__required">*</span>
								<?php endif; ?>
							</label>

							<?php
							$field_id    = 'requirement_' . $index;
							$field_name  = 'requirements[' . $index . ']';
							$saved_value = $submitted_requirements[ $index ] ?? '';
							?>

							<?php if ( 'textarea' === ( $requirement['type'] ?? 'text' ) ) : ?>
								<textarea
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
									class="wpss-requirements-form__textarea"
									rows="4"
									<?php echo ! empty( $requirement['required'] ) ? 'required' : ''; ?>
									placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>"
								><?php echo esc_textarea( $saved_value ); ?></textarea>

							<?php elseif ( 'file' === ( $requirement['type'] ?? 'text' ) ) : ?>
								<div class="wpss-requirements-form__upload">
									<input type="file"
											id="<?php echo esc_attr( $field_id ); ?>"
											name="<?php echo esc_attr( $field_name ); ?>"
											class="wpss-requirements-form__upload-input"
											<?php echo ! empty( $requirement['required'] ) ? 'required' : ''; ?>
											<?php echo ! empty( $requirement['multiple'] ) ? 'multiple' : ''; ?>
											accept="<?php echo esc_attr( $requirement['accept'] ?? '' ); ?>">
									<div class="wpss-requirements-form__upload-placeholder">
										<i data-lucide="upload" class="wpss-icon wpss-requirements-form__upload-icon" aria-hidden="true"></i>
										<p class="wpss-requirements-form__upload-text"><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
										<span class="wpss-requirements-form__upload-hint">
											<?php
											$max_size = wpss_get_max_upload_size();
											printf(
												/* translators: %s: max file size */
												esc_html__( 'Maximum file size: %s', 'wp-sell-services' ),
												esc_html( size_format( $max_size ) )
											);
											?>
										</span>
									</div>
									<div class="wpss-requirements-form__file-list"></div>
								</div>

							<?php elseif ( 'select' === ( $requirement['type'] ?? 'text' ) && ! empty( $requirement['options'] ) ) : ?>
								<select
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
									class="wpss-requirements-form__select"
									<?php echo ! empty( $requirement['required'] ) ? 'required' : ''; ?>>
									<option value=""><?php esc_html_e( 'Select an option', 'wp-sell-services' ); ?></option>
									<?php foreach ( $requirement['options'] as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $saved_value, $option ); ?>>
											<?php echo esc_html( $option ); ?>
										</option>
									<?php endforeach; ?>
								</select>

							<?php else : ?>
								<input type="text"
										id="<?php echo esc_attr( $field_id ); ?>"
										name="<?php echo esc_attr( $field_name ); ?>"
										class="wpss-requirements-form__input"
										value="<?php echo esc_attr( $saved_value ); ?>"
										<?php echo ! empty( $requirement['required'] ) ? 'required' : ''; ?>
										placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>">
							<?php endif; ?>

							<?php if ( ! empty( $requirement['description'] ) ) : ?>
								<p class="wpss-requirements-form__hint"><?php echo esc_html( $requirement['description'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<?php
					/**
					 * Hook: wpss_requirements_form_fields
					 *
					 * Fires after the requirement fields are displayed, allowing custom fields.
					 *
					 * @since 1.0.0
					 *
					 * @param object $order Order object.
					 */
					do_action( 'wpss_requirements_form_fields', $order );
					?>
				<?php else : ?>
					<!-- Default requirement field if none defined -->
					<div class="wpss-requirements-form__field">
						<label class="wpss-requirements-form__label" for="requirements_description">
							<?php esc_html_e( 'Describe what you need', 'wp-sell-services' ); ?>
							<span class="wpss-requirements-form__required">*</span>
						</label>
						<textarea
							id="requirements_description"
							name="requirements[description]"
							class="wpss-requirements-form__textarea"
							rows="6"
							required
							placeholder="<?php esc_attr_e( 'Please provide all the details the seller needs to complete your order...', 'wp-sell-services' ); ?>"
						><?php echo esc_textarea( $submitted_requirements['description'] ?? '' ); ?></textarea>
					</div>

					<div class="wpss-requirements-form__field">
						<label class="wpss-requirements-form__label" for="requirements_files">
							<?php esc_html_e( 'Attach Files (optional)', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-requirements-form__upload">
							<input type="file"
									id="requirements_files"
									name="requirements_files[]"
									class="wpss-requirements-form__upload-input"
									multiple>
							<div class="wpss-requirements-form__upload-placeholder">
								<i data-lucide="upload" class="wpss-icon wpss-requirements-form__upload-icon" aria-hidden="true"></i>
								<p class="wpss-requirements-form__upload-text"><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
								<span class="wpss-requirements-form__upload-hint">
									<?php esc_html_e( 'You can upload logos, reference images, documents, etc.', 'wp-sell-services' ); ?>
								</span>
							</div>
							<div class="wpss-requirements-form__file-list"></div>
						</div>
					</div>
				<?php endif; ?>

				<div class="wpss-requirements-form__submit">
					<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg">
						<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
					</button>
					<p class="wpss-requirements-form__notice">
						<?php esc_html_e( 'Once you submit, the seller will be notified and can start working on your order.', 'wp-sell-services' ); ?>
					</p>
				</div>
			</form>
		</div>

		<aside class="wpss-requirements-page__sidebar">
			<!-- Order Details -->
			<div class="wpss-order-card">
				<div class="wpss-order-card__header">
					<h4 class="wpss-order-card__title"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h4>
				</div>
				<div class="wpss-order-card__body">
					<dl class="wpss-order-card__list">
						<dt><?php esc_html_e( 'Order Number', 'wp-sell-services' ); ?></dt>
						<dd><?php echo esc_html( $order->order_number ); ?></dd>

						<dt><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></dt>
						<dd><strong><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></strong></dd>

						<?php if ( $order->delivery_deadline ) : ?>
							<dt><?php esc_html_e( 'Expected Delivery', 'wp-sell-services' ); ?></dt>
							<dd>
								<?php
								$deadline = $order->delivery_deadline instanceof \DateTimeImmutable
									? $order->delivery_deadline->format( 'Y-m-d H:i:s' )
									: $order->delivery_deadline;
								echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $deadline ) ) );
								?>
							</dd>
						<?php endif; ?>
					</dl>
				</div>
			</div>

			<!-- Seller Info -->
			<?php if ( $vendor ) : ?>
				<div class="wpss-order-card">
					<div class="wpss-order-card__header">
						<h4 class="wpss-order-card__title"><?php esc_html_e( 'Your Seller', 'wp-sell-services' ); ?></h4>
					</div>
					<div class="wpss-order-card__body">
						<div class="wpss-order-card__user">
							<img src="<?php echo esc_url( get_avatar_url( $vendor->ID, array( 'size' => 60 ) ) ); ?>"
								alt="<?php echo esc_attr( $vendor->display_name ); ?>"
								class="wpss-order-card__avatar">
							<div class="wpss-order-card__user-info">
								<strong><?php echo esc_html( $vendor->display_name ); ?></strong>
								<?php
								$vendor_rating = (float) get_user_meta( $vendor->ID, '_wpss_rating_average', true );
								$vendor_count  = (int) get_user_meta( $vendor->ID, '_wpss_rating_count', true );
								?>
								<?php if ( $vendor_count > 0 ) : ?>
									<span class="wpss-order-card__rating">
										<i data-lucide="star" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
										<?php echo esc_html( number_format( $vendor_rating, 1 ) ); ?>
										(<?php echo esc_html( $vendor_count ); ?>)
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Help Box -->
			<div class="wpss-order-card wpss-order-card--info">
				<div class="wpss-order-card__header">
					<h4 class="wpss-order-card__title"><?php esc_html_e( 'Need Help?', 'wp-sell-services' ); ?></h4>
				</div>
				<div class="wpss-order-card__body">
					<p><?php esc_html_e( 'Be as detailed as possible so the seller can deliver exactly what you need.', 'wp-sell-services' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Include all relevant information', 'wp-sell-services' ); ?></li>
						<li><?php esc_html_e( 'Attach reference files if helpful', 'wp-sell-services' ); ?></li>
						<li><?php esc_html_e( 'Mention any specific preferences', 'wp-sell-services' ); ?></li>
					</ul>
				</div>
			</div>
		</aside>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_requirements_form
 *
 * Fires after the requirements form is displayed.
 *
 * @since 1.0.0
 *
 * @param object $order Order object.
 */
do_action( 'wpss_after_requirements_form', $order );
