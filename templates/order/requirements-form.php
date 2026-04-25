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

// VS4 (plans/ORDER-FLOW-AUDIT.md): defensive fallback. If $requirements is
// not an array (corrupt meta) the form would have rendered 0 fields with
// no explanation. Detect explicit corruption vs the legitimate empty case
// (vendor never configured questions — we render the "Project Description"
// default form for that).
$requirements_meta_corrupt = ( null !== $requirements && ! is_array( $requirements ) );
if ( $requirements_meta_corrupt ) {
	$requirements = array(); // Force the default form to render.
}

// CB2 (plans/ORDER-FLOW-AUDIT.md): track required-field count for progress bar.
$required_count = 0;
if ( ! empty( $requirements ) && is_array( $requirements ) ) {
	foreach ( $requirements as $req ) {
		if ( is_array( $req ) && ! empty( $req['required'] ) ) {
			++$required_count;
		}
	}
} else {
	// Default form has 1 required field (Project Description).
	$required_count = 1;
}
?>

<?php if ( $requirements_meta_corrupt ) : ?>
	<div class="wpss-requirements-form__notice wpss-requirements-form__notice--warning">
		<i data-lucide="alert-triangle" class="wpss-icon" aria-hidden="true"></i>
		<div>
			<strong><?php esc_html_e( 'Default questions used', 'wp-sell-services' ); ?></strong>
			<p><?php esc_html_e( "We couldn't load this service's custom questions, so the standard project description form is shown below. Your seller will see your answers either way.", 'wp-sell-services' ); ?></p>
		</div>
	</div>
<?php endif; ?>

<form id="<?php echo esc_attr( $form_id ); ?>"
		class="wpss-requirements-form <?php echo $compact ? 'wpss-requirements-form--compact' : ''; ?>"
		method="post"
		enctype="multipart/form-data"
		data-order-id="<?php echo esc_attr( $order_id ); ?>"
		data-required-count="<?php echo esc_attr( (string) $required_count ); ?>">

	<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>
	<input type="hidden" name="action" value="wpss_submit_requirements">
	<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

	<?php if ( $required_count > 0 ) : ?>
		<div class="wpss-requirements-form__progress" data-wpss-req-progress>
			<div class="wpss-requirements-form__progress-text">
				<span data-wpss-req-progress-label>
					<?php
					printf(
						/* translators: 1: filled count, 2: total required */
						esc_html__( '%1$d of %2$d required answered', 'wp-sell-services' ),
						0,
						(int) $required_count
					);
					?>
				</span>
			</div>
			<div class="wpss-requirements-form__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $required_count ); ?>" aria-valuenow="0">
				<div class="wpss-requirements-form__progress-fill" data-wpss-req-progress-fill style="width: 0%;"></div>
			</div>
		</div>
	<?php endif; ?>

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

<style>
	/* CB2 + VS4 (plans/ORDER-FLOW-AUDIT.md) progress bar + corrupt-meta notice */
	.wpss-requirements-form__progress {
		margin-bottom: 24px;
		padding: 12px 16px;
		background: #f9fafb;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
	}
	.wpss-requirements-form__progress-text {
		font-size: 13px;
		font-weight: 600;
		color: #374151;
		margin-bottom: 8px;
	}
	.wpss-requirements-form__progress-bar {
		width: 100%;
		height: 6px;
		background: #e5e7eb;
		border-radius: 9999px;
		overflow: hidden;
	}
	.wpss-requirements-form__progress-fill {
		height: 100%;
		background: linear-gradient( 90deg, #4f46e5, #7c3aed );
		border-radius: 9999px;
		transition: width 0.3s ease;
	}
	.wpss-requirements-form__progress--complete .wpss-requirements-form__progress-fill {
		background: linear-gradient( 90deg, #10b981, #059669 );
	}
	.wpss-requirements-form__progress--complete .wpss-requirements-form__progress-text {
		color: #047857;
	}
	.wpss-requirements-form__notice--warning {
		display: flex;
		gap: 12px;
		padding: 12px 16px;
		background: #fffbeb;
		border: 1px solid #fde68a;
		border-radius: 8px;
		margin-bottom: 16px;
		color: #92400e;
	}
	.wpss-requirements-form__notice--warning .wpss-icon {
		flex-shrink: 0;
		margin-top: 3px;
	}
	.wpss-requirements-form__notice--warning strong {
		display: block;
		margin-bottom: 4px;
	}
	.wpss-requirements-form__notice--warning p {
		margin: 0;
		font-size: 13px;
		line-height: 1.5;
	}
</style>

<script>
	(function () {
		var form = document.getElementById( <?php echo wp_json_encode( $form_id ); ?> );
		if ( ! form ) { return; }
		var totalRequired = parseInt( form.dataset.requiredCount || '0', 10 );
		if ( totalRequired === 0 ) { return; }
		var progressWrap = form.querySelector( '[data-wpss-req-progress]' );
		var progressLabel = form.querySelector( '[data-wpss-req-progress-label]' );
		var progressFill = form.querySelector( '[data-wpss-req-progress-fill]' );
		var progressBar = form.querySelector( '.wpss-requirements-form__progress-bar' );
		if ( ! progressWrap || ! progressLabel || ! progressFill ) { return; }

		function isFilled( field ) {
			if ( field.type === 'checkbox' || field.type === 'radio' ) {
				return form.querySelectorAll( 'input[name="' + field.name + '"]:checked' ).length > 0;
			}
			if ( field.type === 'file' ) {
				return field.files && field.files.length > 0;
			}
			return ( field.value || '' ).trim() !== '';
		}

		function update() {
			var requiredFields = form.querySelectorAll( '[required]' );
			var filled = 0;
			var seenNames = {};
			requiredFields.forEach( function ( field ) {
				if ( field.type === 'checkbox' || field.type === 'radio' ) {
					if ( seenNames[ field.name ] ) { return; }
					seenNames[ field.name ] = true;
				}
				if ( isFilled( field ) ) { filled += 1; }
			} );

			var capped = Math.min( filled, totalRequired );
			var pct = Math.round( ( capped / totalRequired ) * 100 );
			progressFill.style.width = pct + '%';
			progressLabel.textContent = <?php echo wp_json_encode( __( '%1$d of %2$d required answered', 'wp-sell-services' ) ); ?>
				.replace( '%1$d', String( capped ) )
				.replace( '%2$d', String( totalRequired ) );
			progressBar.setAttribute( 'aria-valuenow', String( capped ) );
			progressWrap.classList.toggle( 'wpss-requirements-form__progress--complete', capped >= totalRequired );
		}

		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );
		update();
	})();
</script>
