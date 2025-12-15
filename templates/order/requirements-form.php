<?php
/**
 * Template: Requirements Form
 *
 * Reusable form component for submitting order requirements.
 * Can be included in full-page or modal contexts.
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

if ( empty( $order_id ) || empty( $order ) ) {
	return;
}

$requirements = $requirements ?? wpss_get_service_requirements( $order->service_id );
$submitted    = $submitted ?? wpss_get_order_requirements( $order_id );
$compact      = $compact ?? false;
$form_id      = 'wpss-requirements-form-' . $order_id;
?>

<form id="<?php echo esc_attr( $form_id ); ?>"
	  class="wpss-requirements-form <?php echo $compact ? 'wpss-requirements-form-compact' : ''; ?>"
	  method="post"
	  enctype="multipart/form-data"
	  data-order-id="<?php echo esc_attr( $order_id ); ?>">

	<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>
	<input type="hidden" name="action" value="wpss_submit_requirements">
	<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

	<?php if ( empty( $requirements ) ) : ?>
		<!-- Default Requirements -->
		<div class="wpss-requirement-field">
			<label for="req_description_<?php echo esc_attr( $order_id ); ?>">
				<?php esc_html_e( 'Project Description', 'wp-sell-services' ); ?>
				<span class="wpss-required">*</span>
			</label>
			<textarea
				name="requirements[description]"
				id="req_description_<?php echo esc_attr( $order_id ); ?>"
				class="wpss-textarea wpss-requirement-input"
				rows="5"
				required
				placeholder="<?php esc_attr_e( 'Please describe your project in detail...', 'wp-sell-services' ); ?>"
			><?php echo esc_textarea( $submitted['description'] ?? '' ); ?></textarea>
			<p class="wpss-field-hint">
				<?php esc_html_e( 'Include as much detail as possible to help the seller understand your needs.', 'wp-sell-services' ); ?>
			</p>
		</div>

		<div class="wpss-requirement-field">
			<label for="req_files_<?php echo esc_attr( $order_id ); ?>">
				<?php esc_html_e( 'Reference Files (Optional)', 'wp-sell-services' ); ?>
			</label>
			<div class="wpss-file-upload-area" id="file-upload-area-<?php echo esc_attr( $order_id ); ?>">
				<input
					type="file"
					name="requirement_files[]"
					id="req_files_<?php echo esc_attr( $order_id ); ?>"
					class="wpss-file-input"
					multiple
					accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.psd,.ai,.eps">
				<div class="wpss-upload-placeholder">
					<span class="dashicons dashicons-cloud-upload"></span>
					<p><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
					<span class="wpss-upload-hint">
						<?php esc_html_e( 'Max 10 files, 25MB each', 'wp-sell-services' ); ?>
					</span>
				</div>
				<div class="wpss-file-list" id="file-list-<?php echo esc_attr( $order_id ); ?>"></div>
			</div>
			<?php if ( ! empty( $submitted['files'] ) ) : ?>
				<div class="wpss-existing-files">
					<p class="wpss-field-hint"><?php esc_html_e( 'Previously uploaded files:', 'wp-sell-services' ); ?></p>
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
			$field_id   = 'req_' . $index . '_' . $order_id;
			$field_name = 'requirements[' . $index . ']';
			$field_type = $requirement['type'] ?? 'text';
			$is_required = ! empty( $requirement['required'] );
			$value      = $submitted[ $index ] ?? ( $requirement['default'] ?? '' );
			?>

			<div class="wpss-requirement-field wpss-requirement-type-<?php echo esc_attr( $field_type ); ?>">
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $requirement['question'] ?? $requirement['label'] ?? '' ); ?>
					<?php if ( $is_required ) : ?>
						<span class="wpss-required">*</span>
					<?php endif; ?>
				</label>

				<?php if ( ! empty( $requirement['description'] ) ) : ?>
					<p class="wpss-field-description">
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
							class="wpss-textarea wpss-requirement-input"
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
							class="wpss-select wpss-requirement-input"
							<?php echo $is_required ? 'required' : ''; ?>>
							<option value=""><?php esc_html_e( '-- Select an option --', 'wp-sell-services' ); ?></option>
							<?php foreach ( $requirement['options'] ?? [] as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php
						break;

					case 'multiselect':
						$selected_values = is_array( $value ) ? $value : [];
						?>
						<select
							name="<?php echo esc_attr( $field_name ); ?>[]"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-select wpss-requirement-input"
							multiple
							<?php echo $is_required ? 'required' : ''; ?>>
							<?php foreach ( $requirement['options'] ?? [] as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php echo in_array( $option_value, $selected_values, true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="wpss-field-hint"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple options', 'wp-sell-services' ); ?></p>
						<?php
						break;

					case 'radio':
						?>
						<div class="wpss-radio-group">
							<?php foreach ( $requirement['options'] ?? [] as $option_value => $option_label ) : ?>
								<label class="wpss-radio-option">
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
						$checked_values = is_array( $value ) ? $value : [];
						?>
						<div class="wpss-checkbox-group">
							<?php foreach ( $requirement['options'] ?? [] as $option_value => $option_label ) : ?>
								<label class="wpss-checkbox-option">
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
						<div class="wpss-file-upload-area wpss-requirement-file" data-max-files="<?php echo esc_attr( $max_files ); ?>">
							<input
								type="file"
								name="<?php echo esc_attr( $field_name ); ?>[]"
								id="<?php echo esc_attr( $field_id ); ?>"
								class="wpss-file-input"
								multiple
								accept="<?php echo esc_attr( $accept ); ?>"
								<?php echo $is_required ? 'required' : ''; ?>>
							<div class="wpss-upload-placeholder">
								<span class="dashicons dashicons-cloud-upload"></span>
								<p><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
								<span class="wpss-upload-hint">
									<?php
									printf(
										/* translators: 1: max files, 2: max size */
										esc_html__( 'Max %1$d files, %2$dMB each', 'wp-sell-services' ),
										$max_files,
										$max_size
									);
									?>
								</span>
							</div>
							<div class="wpss-file-list"></div>
						</div>
						<?php
						break;

					case 'date':
						?>
						<input
							type="date"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-input wpss-requirement-input"
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
							class="wpss-input wpss-requirement-input"
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
							class="wpss-input wpss-requirement-input"
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
							class="wpss-input wpss-requirement-input"
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
							class="wpss-input wpss-requirement-input"
							value="<?php echo esc_attr( $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>
							<?php echo ! empty( $requirement['maxlength'] ) ? 'maxlength="' . esc_attr( $requirement['maxlength'] ) . '"' : ''; ?>
							placeholder="<?php echo esc_attr( $requirement['placeholder'] ?? '' ); ?>">
						<?php
				endswitch;
				?>

				<?php if ( ! empty( $requirement['hint'] ) ) : ?>
					<p class="wpss-field-hint"><?php echo esc_html( $requirement['hint'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- Additional Notes -->
	<div class="wpss-requirement-field">
		<label for="req_notes_<?php echo esc_attr( $order_id ); ?>">
			<?php esc_html_e( 'Additional Notes (Optional)', 'wp-sell-services' ); ?>
		</label>
		<textarea
			name="requirements[additional_notes]"
			id="req_notes_<?php echo esc_attr( $order_id ); ?>"
			class="wpss-textarea wpss-requirement-input"
			rows="3"
			placeholder="<?php esc_attr_e( 'Any additional information or special requests...', 'wp-sell-services' ); ?>"
		><?php echo esc_textarea( $submitted['additional_notes'] ?? '' ); ?></textarea>
	</div>

	<!-- Submit Button -->
	<div class="wpss-requirements-submit">
		<button type="submit" class="wpss-btn wpss-btn-primary wpss-btn-lg wpss-submit-requirements">
			<span class="wpss-btn-text"><?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?></span>
			<span class="wpss-btn-loading" style="display: none;">
				<span class="wpss-spinner"></span>
				<?php esc_html_e( 'Submitting...', 'wp-sell-services' ); ?>
			</span>
		</button>
		<p class="wpss-requirements-notice">
			<?php esc_html_e( 'Once submitted, the seller will start working on your order.', 'wp-sell-services' ); ?>
		</p>
	</div>
</form>

<style>
.wpss-requirements-form {
	max-width: 700px;
}

.wpss-requirements-form-compact {
	max-width: none;
}

.wpss-requirement-field {
	margin-bottom: 25px;
}

.wpss-requirement-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-required {
	color: var(--wpss-danger-color, #d63638);
	margin-left: 2px;
}

.wpss-field-description {
	font-size: 13px;
	color: var(--wpss-text-secondary, #646970);
	margin: 0 0 10px;
}

.wpss-input,
.wpss-textarea,
.wpss-select {
	width: 100%;
	padding: 10px 14px;
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: var(--wpss-border-radius, 6px);
	font-size: 14px;
	line-height: 1.5;
	transition: border-color 0.2s, box-shadow 0.2s;
}

.wpss-input:focus,
.wpss-textarea:focus,
.wpss-select:focus {
	outline: none;
	border-color: var(--wpss-primary-color, #2271b1);
	box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.15);
}

.wpss-textarea {
	resize: vertical;
	min-height: 100px;
}

.wpss-field-hint {
	font-size: 12px;
	color: var(--wpss-text-muted, #8c8f94);
	margin: 6px 0 0;
}

/* Radio & Checkbox Groups */
.wpss-radio-group,
.wpss-checkbox-group {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.wpss-radio-option,
.wpss-checkbox-option {
	display: flex;
	align-items: center;
	gap: 10px;
	cursor: pointer;
	padding: 10px 14px;
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: var(--wpss-border-radius, 6px);
	transition: border-color 0.2s, background 0.2s;
}

.wpss-radio-option:hover,
.wpss-checkbox-option:hover {
	border-color: var(--wpss-primary-color, #2271b1);
	background: var(--wpss-bg-light, #f6f7f7);
}

.wpss-radio-option input,
.wpss-checkbox-option input {
	margin: 0;
}

/* File Upload */
.wpss-file-upload-area {
	border: 2px dashed var(--wpss-border-color, #dcdcde);
	border-radius: var(--wpss-border-radius, 8px);
	padding: 30px 20px;
	text-align: center;
	position: relative;
	transition: border-color 0.2s, background 0.2s;
}

.wpss-file-upload-area:hover,
.wpss-file-upload-area.wpss-drag-over {
	border-color: var(--wpss-primary-color, #2271b1);
	background: rgba(34, 113, 177, 0.05);
}

.wpss-file-upload-area .wpss-file-input {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	opacity: 0;
	cursor: pointer;
}

.wpss-upload-placeholder {
	pointer-events: none;
}

.wpss-upload-placeholder .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
	color: var(--wpss-text-muted, #8c8f94);
	margin-bottom: 10px;
}

.wpss-upload-placeholder p {
	margin: 0 0 5px;
	font-weight: 500;
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-upload-hint {
	font-size: 12px;
	color: var(--wpss-text-muted, #8c8f94);
}

.wpss-file-list {
	margin-top: 15px;
	text-align: left;
}

.wpss-file-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 12px;
	background: var(--wpss-bg-light, #f6f7f7);
	border-radius: 6px;
	margin-bottom: 8px;
}

.wpss-file-item:last-child {
	margin-bottom: 0;
}

.wpss-file-info {
	display: flex;
	align-items: center;
	gap: 10px;
}

.wpss-file-icon {
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--wpss-card-bg, #fff);
	border-radius: 4px;
}

.wpss-file-name {
	font-size: 13px;
	font-weight: 500;
}

.wpss-file-size {
	font-size: 11px;
	color: var(--wpss-text-muted, #8c8f94);
}

.wpss-file-remove {
	cursor: pointer;
	color: var(--wpss-danger-color, #d63638);
	padding: 5px;
}

.wpss-file-remove:hover {
	color: var(--wpss-danger-dark, #b32d2e);
}

.wpss-existing-files {
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid var(--wpss-border-color, #dcdcde);
}

.wpss-existing-files ul {
	margin: 5px 0 0 20px;
	padding: 0;
}

.wpss-existing-files li {
	margin-bottom: 5px;
}

/* Submit */
.wpss-requirements-submit {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 1px solid var(--wpss-border-color, #dcdcde);
}

.wpss-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 12px 24px;
	font-size: 15px;
	font-weight: 600;
	border: none;
	border-radius: var(--wpss-border-radius, 6px);
	cursor: pointer;
	transition: all 0.2s;
}

.wpss-btn-primary {
	background: var(--wpss-primary-color, #2271b1);
	color: #fff;
}

.wpss-btn-primary:hover {
	background: var(--wpss-primary-dark, #135e96);
}

.wpss-btn-primary:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.wpss-btn-lg {
	padding: 14px 32px;
	font-size: 16px;
}

.wpss-spinner {
	display: inline-block;
	width: 16px;
	height: 16px;
	border: 2px solid rgba(255, 255, 255, 0.3);
	border-top-color: #fff;
	border-radius: 50%;
	animation: wpss-spin 0.8s linear infinite;
}

@keyframes wpss-spin {
	to { transform: rotate(360deg); }
}

.wpss-requirements-notice {
	font-size: 13px;
	color: var(--wpss-text-secondary, #646970);
	margin: 15px 0 0;
}

@media (max-width: 600px) {
	.wpss-file-upload-area {
		padding: 20px 15px;
	}

	.wpss-upload-placeholder .dashicons {
		font-size: 36px;
		width: 36px;
		height: 36px;
	}
}
</style>

<script>
(function($) {
	'use strict';

	$('.wpss-requirements-form').each(function() {
		var $form = $(this);
		var $submitBtn = $form.find('.wpss-submit-requirements');
		var $btnText = $submitBtn.find('.wpss-btn-text');
		var $btnLoading = $submitBtn.find('.wpss-btn-loading');

		// File upload handling.
		$form.find('.wpss-file-upload-area').each(function() {
			var $area = $(this);
			var $input = $area.find('.wpss-file-input');
			var $fileList = $area.find('.wpss-file-list');
			var maxFiles = $area.data('max-files') || 10;
			var files = [];

			// Drag and drop.
			$area.on('dragover dragenter', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$area.addClass('wpss-drag-over');
			});

			$area.on('dragleave dragend drop', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$area.removeClass('wpss-drag-over');
			});

			$area.on('drop', function(e) {
				var droppedFiles = e.originalEvent.dataTransfer.files;
				handleFiles(droppedFiles);
			});

			$input.on('change', function() {
				handleFiles(this.files);
			});

			function handleFiles(newFiles) {
				for (var i = 0; i < newFiles.length && files.length < maxFiles; i++) {
					files.push(newFiles[i]);
				}
				renderFiles();
			}

			function renderFiles() {
				$fileList.empty();
				files.forEach(function(file, index) {
					var size = formatFileSize(file.size);
					var icon = getFileIcon(file.type);
					var $item = $('<div class="wpss-file-item">')
						.append('<div class="wpss-file-info">' +
							'<span class="wpss-file-icon dashicons dashicons-' + icon + '"></span>' +
							'<div><span class="wpss-file-name">' + file.name + '</span>' +
							'<span class="wpss-file-size">' + size + '</span></div></div>')
						.append('<span class="wpss-file-remove dashicons dashicons-no-alt" data-index="' + index + '"></span>');
					$fileList.append($item);
				});
			}

			$fileList.on('click', '.wpss-file-remove', function() {
				var index = $(this).data('index');
				files.splice(index, 1);
				renderFiles();
			});

			function formatFileSize(bytes) {
				if (bytes === 0) return '0 Bytes';
				var k = 1024;
				var sizes = ['Bytes', 'KB', 'MB', 'GB'];
				var i = Math.floor(Math.log(bytes) / Math.log(k));
				return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
			}

			function getFileIcon(mimeType) {
				if (mimeType.indexOf('image/') === 0) return 'format-image';
				if (mimeType.indexOf('video/') === 0) return 'video-alt3';
				if (mimeType.indexOf('audio/') === 0) return 'format-audio';
				if (mimeType === 'application/pdf') return 'pdf';
				if (mimeType.indexOf('spreadsheet') > -1 || mimeType.indexOf('excel') > -1) return 'media-spreadsheet';
				if (mimeType.indexOf('document') > -1 || mimeType.indexOf('word') > -1) return 'media-document';
				if (mimeType.indexOf('zip') > -1 || mimeType.indexOf('archive') > -1) return 'media-archive';
				return 'media-default';
			}
		});

		// Form submission.
		$form.on('submit', function(e) {
			e.preventDefault();

			// Validate required fields.
			var isValid = true;
			$form.find('[required]').each(function() {
				if (!$(this).val()) {
					isValid = false;
					$(this).addClass('wpss-error');
				} else {
					$(this).removeClass('wpss-error');
				}
			});

			if (!isValid) {
				$form.find('.wpss-error:first').focus();
				return;
			}

			$btnText.hide();
			$btnLoading.show();
			$submitBtn.prop('disabled', true);

			var formData = new FormData($form[0]);

			$.ajax({
				url: wpss_ajax.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							window.location.reload();
						}
					} else {
						alert(response.data.message || '<?php esc_html_e( 'Failed to submit requirements.', 'wp-sell-services' ); ?>');
						$btnText.show();
						$btnLoading.hide();
						$submitBtn.prop('disabled', false);
					}
				},
				error: function() {
					alert('<?php esc_html_e( 'An error occurred. Please try again.', 'wp-sell-services' ); ?>');
					$btnText.show();
					$btnLoading.hide();
					$submitBtn.prop('disabled', false);
				}
			});
		});
	});

})(jQuery);
</script>
