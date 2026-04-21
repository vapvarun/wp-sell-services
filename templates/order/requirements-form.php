<?php
/**
 * Template: Requirements Form
 *
 * Reusable form component for submitting order requirements.
 * Can be included in full-page or modal contexts.
 * Uses CSS classes from orders.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int    $order_id     Order ID.
 * @var object $order        Order object.
 * @var array  $requirements Service requirements configuration.
 * @var array  $submitted    Previously submitted requirements (for editing).
 * @var bool   $compact      Whether to use compact layout.
 */

defined( 'ABSPATH' ) || exit;

// Enqueue orders styles.
wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );

// Enqueue requirements form script.
wp_enqueue_script( 'wpss-requirements-form', WPSS_PLUGIN_URL . 'assets/js/requirements-form.js', array( 'jquery' ), WPSS_VERSION, true );

if ( empty( $order_id ) || empty( $order ) ) {
	return;
}

$requirements = $requirements ?? wpss_get_service_requirements( $order->service_id );
$submitted    = $submitted ?? wpss_get_order_requirements( $order_id );
$compact      = $compact ?? false;
$form_id      = 'wpss-requirements-form-' . $order_id;
?>

<form id="<?php echo esc_attr( $form_id ); ?>"
		class="wpss-requirements-form <?php echo $compact ? 'wpss-requirements-form--compact' : ''; ?>"
		method="post"
		enctype="multipart/form-data"
		data-order-id="<?php echo esc_attr( $order_id ); ?>">

	<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>
	<input type="hidden" name="action" value="wpss_submit_requirements">
	<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

	<?php if ( empty( $requirements ) ) : ?>
		<!-- Default Requirements -->
		<div class="wpss-requirements-form__field">
			<label class="wpss-requirements-form__label" for="req_description_<?php echo esc_attr( $order_id ); ?>">
				<?php esc_html_e( 'Project Description', 'wp-sell-services' ); ?>
				<span class="wpss-requirements-form__required">*</span>
			</label>
			<textarea
				name="requirements[description]"
				id="req_description_<?php echo esc_attr( $order_id ); ?>"
				class="wpss-requirements-form__textarea"
				rows="5"
				required
				placeholder="<?php esc_attr_e( 'Please describe your project in detail...', 'wp-sell-services' ); ?>"
			><?php echo esc_textarea( $submitted['description'] ?? '' ); ?></textarea>
			<p class="wpss-requirements-form__hint">
				<?php esc_html_e( 'Include as much detail as possible to help the seller understand your needs.', 'wp-sell-services' ); ?>
			</p>
		</div>

		<div class="wpss-requirements-form__field">
			<label class="wpss-requirements-form__label" for="req_files_<?php echo esc_attr( $order_id ); ?>">
				<?php esc_html_e( 'Reference Files (Optional)', 'wp-sell-services' ); ?>
			</label>
			<div class="wpss-requirements-form__upload" id="file-upload-area-<?php echo esc_attr( $order_id ); ?>">
				<input
					type="file"
					name="requirement_files[]"
					id="req_files_<?php echo esc_attr( $order_id ); ?>"
					class="wpss-requirements-form__upload-input"
					multiple
					accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.psd,.ai,.eps">
				<div class="wpss-requirements-form__upload-placeholder">
					<i data-lucide="upload" class="wpss-icon wpss-requirements-form__upload-icon" aria-hidden="true"></i>
					<p class="wpss-requirements-form__upload-text"><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
					<span class="wpss-requirements-form__upload-hint">
						<?php esc_html_e( 'Max 10 files, 25MB each', 'wp-sell-services' ); ?>
					</span>
				</div>
				<div class="wpss-requirements-form__file-list" id="file-list-<?php echo esc_attr( $order_id ); ?>"></div>
			</div>
			<?php if ( ! empty( $submitted['files'] ) ) : ?>
				<div class="wpss-requirements-form__existing-files">
					<p class="wpss-requirements-form__hint"><?php esc_html_e( 'Previously uploaded files:', 'wp-sell-services' ); ?></p>
					<ul>
						<?php foreach ( $submitted['files'] as $file ) : ?>
							<li>
								<a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank">
									<?php echo esc_html( $file['name'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<!-- Custom Requirements -->
		<?php foreach ( $requirements as $index => $requirement ) : ?>
			<?php
			$field_id    = 'req_' . $index . '_' . $order_id;
			$field_name  = 'requirements[' . $index . ']';
			$field_type  = $requirement['type'] ?? 'text';
			$is_required = ! empty( $requirement['required'] );
			$value       = $submitted[ $index ] ?? ( $requirement['default'] ?? '' );
			?>

			<div class="wpss-requirements-form__field wpss-requirements-form__field--<?php echo esc_attr( $field_type ); ?>">
				<label class="wpss-requirements-form__label" for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $requirement['question'] ?? $requirement['label'] ?? '' ); ?>
					<?php if ( $is_required ) : ?>
						<span class="wpss-requirements-form__required">*</span>
					<?php endif; ?>
				</label>

				<?php if ( ! empty( $requirement['description'] ) ) : ?>
					<p class="wpss-requirements-form__description">
						<?php echo esc_html( $requirement['description'] ); ?>
					</p>
				<?php endif; ?>

				<?php
				switch ( $field_type ) :
					case 'textarea':
						?>
						<textarea
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__textarea"
							rows="<?php echo esc_attr( $requirement['rows'] ?? 4 ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							<?php echo ! empty( $requirement['maxlength'] ) ? 'maxlength="' . esc_attr( $requirement['maxlength'] ) . '"' : ''; ?>
							placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>"
						><?php echo esc_textarea( $value ); ?></textarea>
						<?php
						break;

					case 'select':
						?>
						<select
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__select"
							<?php echo $is_required ? 'required' : ''; ?>>
							<option value=""><?php esc_html_e( '-- Select an option --', 'wp-sell-services' ); ?></option>
							<?php foreach ( $requirement['options'] ?? array() as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php
						break;

					case 'multiselect':
						$selected_values = is_array( $value ) ? $value : array();
						?>
						<select
							name="<?php echo esc_attr( $field_name ); ?>[]"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__select"
							multiple
							<?php echo $is_required ? 'required' : ''; ?>>
							<?php foreach ( $requirement['options'] ?? array() as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php echo in_array( $option_value, $selected_values, true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="wpss-requirements-form__hint"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple options', 'wp-sell-services' ); ?></p>
						<?php
						break;

					case 'radio':
						?>
						<div class="wpss-requirements-form__radio-group">
							<?php foreach ( $requirement['options'] ?? array() as $option_value => $option_label ) : ?>
								<label class="wpss-requirements-form__radio-option">
									<input
										type="radio"
										name="<?php echo esc_attr( $field_name ); ?>"
										value="<?php echo esc_attr( $option_value ); ?>"
										<?php checked( $value, $option_value ); ?>
										<?php echo $is_required ? 'required' : ''; ?>>
									<span><?php echo esc_html( $option_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<?php
						break;

					case 'checkbox':
						$checked_values = is_array( $value ) ? $value : array();
						?>
						<div class="wpss-requirements-form__checkbox-group">
							<?php foreach ( $requirement['options'] ?? array() as $option_value => $option_label ) : ?>
								<label class="wpss-requirements-form__checkbox-option">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $field_name ); ?>[]"
										value="<?php echo esc_attr( $option_value ); ?>"
										<?php echo in_array( $option_value, $checked_values, true ) ? 'checked' : ''; ?>>
									<span><?php echo esc_html( $option_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<?php
						break;

					case 'file':
						$max_files = $requirement['max_files'] ?? 5;
						$max_size  = $requirement['max_size'] ?? 25;
						$accept    = $requirement['accept'] ?? 'image/*,.pdf,.doc,.docx,.zip';
						?>
						<div class="wpss-requirements-form__upload" data-max-files="<?php echo esc_attr( $max_files ); ?>">
							<input
								type="file"
								name="<?php echo esc_attr( $field_name ); ?>[]"
								id="<?php echo esc_attr( $field_id ); ?>"
								class="wpss-requirements-form__upload-input"
								multiple
								accept="<?php echo esc_attr( $accept ); ?>"
								<?php echo $is_required ? 'required' : ''; ?>>
							<div class="wpss-requirements-form__upload-placeholder">
								<i data-lucide="upload" class="wpss-icon wpss-requirements-form__upload-icon" aria-hidden="true"></i>
								<p class="wpss-requirements-form__upload-text"><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
								<span class="wpss-requirements-form__upload-hint">
									<?php
									printf(
										/* translators: 1: max files, 2: max size */
										esc_html__( 'Max %1$d files, %2$dMB each', 'wp-sell-services' ),
										absint( $max_files ),
										absint( $max_size )
									);
									?>
								</span>
							</div>
							<div class="wpss-requirements-form__file-list"></div>
						</div>
						<?php
						break;

					case 'date':
						?>
						<input
							type="date"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__input"
							value="<?php echo esc_attr( $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							<?php echo ! empty( $requirement['min'] ) ? 'min="' . esc_attr( $requirement['min'] ) . '"' : ''; ?>
							<?php echo ! empty( $requirement['max'] ) ? 'max="' . esc_attr( $requirement['max'] ) . '"' : ''; ?>>
						<?php
						break;

					case 'number':
						?>
						<input
							type="number"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__input"
							value="<?php echo esc_attr( $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							<?php echo isset( $requirement['min'] ) ? 'min="' . esc_attr( $requirement['min'] ) . '"' : ''; ?>
							<?php echo isset( $requirement['max'] ) ? 'max="' . esc_attr( $requirement['max'] ) . '"' : ''; ?>
							<?php echo ! empty( $requirement['step'] ) ? 'step="' . esc_attr( $requirement['step'] ) . '"' : ''; ?>
							placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>">
						<?php
						break;

					case 'url':
						?>
						<input
							type="url"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__input"
							value="<?php echo esc_url( $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? 'https://' ); ?>">
						<?php
						break;

					case 'email':
						?>
						<input
							type="email"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__input"
							value="<?php echo esc_attr( $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>">
						<?php
						break;

					default: // text
						?>
						<input
							type="text"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__input"
							value="<?php echo esc_attr( $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							<?php echo ! empty( $requirement['maxlength'] ) ? 'maxlength="' . esc_attr( $requirement['maxlength'] ) . '"' : ''; ?>
							placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>">
						<?php
				endswitch;
				?>

				<?php if ( ! empty( $requirement['hint'] ) ) : ?>
					<p class="wpss-requirements-form__hint"><?php echo esc_html( $requirement['hint'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- Additional Notes -->
	<div class="wpss-requirements-form__field">
		<label class="wpss-requirements-form__label" for="req_notes_<?php echo esc_attr( $order_id ); ?>">
			<?php esc_html_e( 'Additional Notes (Optional)', 'wp-sell-services' ); ?>
		</label>
		<textarea
			name="requirements[additional_notes]"
			id="req_notes_<?php echo esc_attr( $order_id ); ?>"
			class="wpss-requirements-form__textarea"
			rows="3"
			placeholder="<?php esc_attr_e( 'Any additional information or special requests...', 'wp-sell-services' ); ?>"
		><?php echo esc_textarea( $submitted['additional_notes'] ?? '' ); ?></textarea>
	</div>

	<!-- Submit Button -->
	<div class="wpss-requirements-form__submit">
		<button type="submit" class="wpss-requirements-form__submit-btn wpss-btn wpss-btn--primary wpss-btn--lg">
			<span class="wpss-requirements-form__submit-text"><?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?></span>
			<span class="wpss-requirements-form__submit-loading" style="display: none;">
				<span class="wpss-requirements-form__spinner"></span>
				<?php esc_html_e( 'Submitting...', 'wp-sell-services' ); ?>
			</span>
		</button>
		<p class="wpss-requirements-form__notice">
			<?php esc_html_e( 'Once submitted, the seller will start working on your order.', 'wp-sell-services' ); ?>
		</p>
	</div>
</form>
