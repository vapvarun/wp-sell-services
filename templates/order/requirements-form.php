<?php
/**
 * Template: Requirements Form
 *
 * The one buyer requirements form. The order page, the standalone
 * requirements page and the dashboard all include it; there is no second
 * copy to drift from it (Basecamp 10264294443).
 *
 * Fields are rendered from the service's normalised requirement schema
 * (wpss_get_service_requirements()) and posted as requirements[index]; the
 * AJAX handler maps the index back to the requirement id.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int    $order_id        Order ID.
 * @var object $order           Order object (service_id is read).
 * @var bool   $late_submission Optional. Whether work already started (late submission).
 * @var bool   $compact         Optional. Whether to use compact layout.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $order_id ) || empty( $order ) ) {
	return;
}

wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );
\WPSellServices\Assets\ScriptRegistry::enqueue( 'wpss-requirements-form', 'assets/js/requirements-form.js', array( 'jquery' ) );
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

$requirements    = wpss_get_service_requirements( (int) $order->service_id );
$submitted       = wpss_get_order_requirements( (int) $order_id );
$compact         = ! empty( $compact );
$late_submission = ! empty( $late_submission );
$form_id         = 'wpss-requirements-form-' . $order_id;

// Required-field count for the progress bar. The default form has one.
$required_count = empty( $requirements ) ? 1 : count( array_filter( array_column( $requirements, 'required' ) ) );

/**
 * Fires before the requirements form content.
 *
 * @since 1.0.0
 *
 * @param int    $order_id Order ID.
 * @param object $order    Order object.
 */
do_action( 'wpss_before_requirements_form_component', $order_id, $order );
?>

<form id="<?php echo esc_attr( $form_id ); ?>"
		class="wpss-requirements-form <?php echo $compact ? 'wpss-requirements-form--compact' : ''; ?>"
		method="post"
		enctype="multipart/form-data"
		data-order-id="<?php echo esc_attr( $order_id ); ?>"
		data-required-count="<?php echo esc_attr( (string) $required_count ); ?>">

	<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>
	<input type="hidden" name="action" value="wpss_submit_requirements">
	<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
	<?php if ( $late_submission ) : ?>
		<input type="hidden" name="late_submission" value="1">
	<?php endif; ?>

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
		<!-- Default form: the vendor configured no questions -->
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
			><?php echo esc_textarea( (string) ( $submitted['description'] ?? '' ) ); ?></textarea>
			<p class="wpss-requirements-form__hint">
				<?php esc_html_e( 'Include as much detail as possible to help the seller understand your needs.', 'wp-sell-services' ); ?>
			</p>
		</div>

		<div class="wpss-requirements-form__field">
			<label class="wpss-requirements-form__label" for="req_files_<?php echo esc_attr( $order_id ); ?>">
				<?php esc_html_e( 'Reference Files (Optional)', 'wp-sell-services' ); ?>
			</label>
			<div class="wpss-requirements-form__upload">
				<input
					type="file"
					name="requirement_files[]"
					id="req_files_<?php echo esc_attr( $order_id ); ?>"
					class="wpss-requirements-form__upload-input"
					multiple>
				<div class="wpss-requirements-form__upload-placeholder">
					<i data-lucide="upload" class="wpss-icon wpss-requirements-form__upload-icon" aria-hidden="true"></i>
					<p class="wpss-requirements-form__upload-text"><?php esc_html_e( 'Drag files here or click to upload', 'wp-sell-services' ); ?></p>
					<span class="wpss-requirements-form__upload-hint">
						<?php
						printf(
							/* translators: %s: max file size */
							esc_html__( 'Maximum file size: %s', 'wp-sell-services' ),
							esc_html( size_format( wpss_get_max_upload_size() ) )
						);
						?>
					</span>
				</div>
				<div class="wpss-requirements-form__file-list"></div>
			</div>
		</div>

	<?php else : ?>
		<?php foreach ( $requirements as $index => $requirement ) : ?>
			<?php
			$field_id    = 'req_' . $index . '_' . $order_id;
			$field_name  = 'requirements[' . $index . ']';
			$field_type  = $requirement['type'];
			$is_required = $requirement['required'];
			$value       = $submitted[ $requirement['id'] ] ?? '';
			$values      = is_array( $value ) ? array_map( 'strval', $value ) : array();
			?>

			<div class="wpss-requirements-form__field wpss-requirements-form__field--<?php echo esc_attr( $field_type ); ?>">
				<label class="wpss-requirements-form__label" for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $requirement['label'] ); ?>
					<?php if ( $is_required ) : ?>
						<span class="wpss-requirements-form__required">*</span>
					<?php endif; ?>
				</label>

				<?php if ( '' !== $requirement['description'] ) : ?>
					<p class="wpss-requirements-form__description"><?php echo esc_html( $requirement['description'] ); ?></p>
				<?php endif; ?>

				<?php
				switch ( $field_type ) :
					case 'textarea':
						?>
						<textarea
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__textarea"
							rows="4"
							<?php echo $is_required ? 'required' : ''; ?>
						><?php echo esc_textarea( (string) $value ); ?></textarea>
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
							<?php foreach ( $requirement['options'] as $option ) : ?>
								<option value="<?php echo esc_attr( $option ); ?>" <?php selected( (string) $value, $option ); ?>><?php echo esc_html( $option ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php
						break;

					case 'multiselect':
						?>
						<select
							name="<?php echo esc_attr( $field_name ); ?>[]"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__select"
							multiple
							<?php echo $is_required ? 'required' : ''; ?>>
							<?php foreach ( $requirement['options'] as $option ) : ?>
								<option value="<?php echo esc_attr( $option ); ?>" <?php selected( in_array( $option, $values, true ) ); ?>><?php echo esc_html( $option ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="wpss-requirements-form__hint"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple options', 'wp-sell-services' ); ?></p>
						<?php
						break;

					case 'radio':
						?>
						<div class="wpss-requirements-form__radio-group" id="<?php echo esc_attr( $field_id ); ?>">
							<?php foreach ( $requirement['options'] as $option ) : ?>
								<label class="wpss-requirements-form__radio-option">
									<input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $option ); ?>" <?php checked( (string) $value, $option ); ?> <?php echo $is_required ? 'required' : ''; ?>>
									<span><?php echo esc_html( $option ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<?php
						break;

					case 'checkbox':
						if ( empty( $requirement['options'] ) ) :
							// A yes/no question.
							?>
							<label class="wpss-requirements-form__checkbox-option">
								<input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php esc_attr_e( 'Yes', 'wp-sell-services' ); ?>" <?php checked( '' !== (string) $value ); ?> <?php echo $is_required ? 'required' : ''; ?>>
								<span><?php esc_html_e( 'Yes', 'wp-sell-services' ); ?></span>
							</label>
							<?php
							break;
						endif;
						?>
						<div class="wpss-requirements-form__checkbox-group" id="<?php echo esc_attr( $field_id ); ?>">
							<?php foreach ( $requirement['options'] as $option ) : ?>
								<label class="wpss-requirements-form__checkbox-option">
									<input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo esc_attr( $option ); ?>" <?php checked( in_array( $option, $values, true ) ); ?>>
									<span><?php echo esc_html( $option ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<?php
						break;

					case 'file':
						?>
						<div class="wpss-requirements-form__upload" data-max-files="1">
							<input
								type="file"
								name="<?php echo esc_attr( $field_name ); ?>"
								id="<?php echo esc_attr( $field_id ); ?>"
								class="wpss-requirements-form__upload-input"
								<?php echo $is_required ? 'required' : ''; ?>>
							<div class="wpss-requirements-form__upload-placeholder">
								<i data-lucide="upload" class="wpss-icon wpss-requirements-form__upload-icon" aria-hidden="true"></i>
								<p class="wpss-requirements-form__upload-text"><?php esc_html_e( 'Drag a file here or click to upload', 'wp-sell-services' ); ?></p>
								<span class="wpss-requirements-form__upload-hint">
									<?php
									printf(
										/* translators: %s: max file size */
										esc_html__( 'Maximum file size: %s', 'wp-sell-services' ),
										esc_html( size_format( wpss_get_max_upload_size() ) )
									);
									?>
								</span>
							</div>
							<div class="wpss-requirements-form__file-list"></div>
						</div>
						<?php
						break;

					default:
						// text, number, url, email, date map straight onto input types.
						?>
						<input
							type="<?php echo esc_attr( $field_type ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="wpss-requirements-form__input"
							value="<?php echo esc_attr( (string) $value ); ?>"
							<?php echo $is_required ? 'required' : ''; ?>>
						<?php
				endswitch;
				?>
			</div>
		<?php endforeach; ?>

		<?php
		/**
		 * Fires after the configured requirement fields, before the notes field.
		 *
		 * @since 1.0.0
		 *
		 * @param object $order Order object.
		 */
		do_action( 'wpss_requirements_form_fields', $order );
		?>
	<?php endif; ?>

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
		><?php echo esc_textarea( (string) ( $submitted['additional_notes'] ?? '' ) ); ?></textarea>
	</div>

	<div class="wpss-requirements-form__submit">
		<button type="submit" class="wpss-requirements-form__submit-btn wpss-btn wpss-btn--primary wpss-btn--lg">
			<span class="wpss-requirements-form__submit-text"><?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?></span>
			<span class="wpss-requirements-form__submit-loading" style="display: none;">
				<span class="wpss-requirements-form__spinner"></span>
				<?php esc_html_e( 'Submitting...', 'wp-sell-services' ); ?>
			</span>
		</button>
		<p class="wpss-requirements-form__notice">
			<?php
			echo $late_submission
				? esc_html__( 'The seller has already started; your answers will be sent to them right away.', 'wp-sell-services' )
				: esc_html__( 'Once submitted, the seller will start working on your order.', 'wp-sell-services' );
			?>
		</p>
	</div>
</form>

<?php
/**
 * Fires after the requirements form content.
 *
 * @since 1.0.0
 *
 * @param int    $order_id Order ID.
 * @param object $order    Order object.
 */
do_action( 'wpss_after_requirements_form_component', $order_id, $order );
?>

<style>
	.wpss-requirements-form__progress {
		margin-bottom: 24px;
		padding: 12px 16px;
		background: var(--wpss-bg-subtle, #f9fafb);
		border: 1px solid var(--wpss-border, #e5e7eb);
		border-radius: 8px;
	}
	.wpss-requirements-form__progress-text {
		font-size: 13px;
		font-weight: 600;
		color: var(--wpss-text-secondary, #374151);
		margin-bottom: 8px;
	}
	.wpss-requirements-form__progress-bar {
		width: 100%;
		height: 6px;
		background: var(--wpss-border, #e5e7eb);
		border-radius: 9999px;
		overflow: hidden;
	}
	.wpss-requirements-form__progress-fill {
		height: 100%;
		background: linear-gradient( 90deg, var(--wpss-primary, #4f46e5), var(--wpss-primary, #7c3aed) );
		border-radius: 9999px;
		transition: width 0.3s ease;
	}
	.wpss-requirements-form__progress--complete .wpss-requirements-form__progress-fill {
		background: linear-gradient( 90deg, var(--wpss-success, #10b981), var(--wpss-success, #059669) );
	}
	.wpss-requirements-form__progress--complete .wpss-requirements-form__progress-text {
		color: var(--wpss-success-dark, #047857);
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
