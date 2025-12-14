<?php
/**
 * File Upload Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CustomFields\Fields;

/**
 * File upload field.
 *
 * @since 1.0.0
 */
class FileUploadField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'file';
	}

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'File Upload', 'wp-sell-services' );
	}

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-upload';
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array_merge(
			parent::get_default_settings(),
			[
				'allowed_types' => [ 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip' ],
				'max_size'      => 10, // MB.
				'multiple'      => false,
				'max_files'     => 5,
			]
		);
	}

	/**
	 * Render the field for input.
	 *
	 * @param array $field Field configuration.
	 * @param mixed $value Current value.
	 * @return string HTML output.
	 */
	public function render( array $field, $value = null ): string {
		$field = $this->parse_field( $field );
		$allowed = implode( ', ', array_map( fn( $t ) => '.' . $t, $field['allowed_types'] ) );
		$accept = implode( ',', array_map( fn( $t ) => '.' . $t, $field['allowed_types'] ) );

		$extra = [
			'type'   => 'file',
			'accept' => $accept,
		];

		if ( $field['multiple'] ) {
			$extra['multiple'] = true;
		}

		ob_start();
		?>
		<div class="wpss-file-upload-wrapper">
			<input <?php echo $this->build_attributes( $field, $extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

			<p class="wpss-field-hint">
				<?php
				/* translators: 1: allowed file types, 2: max file size */
				printf(
					esc_html__( 'Allowed types: %1$s. Max size: %2$d MB.', 'wp-sell-services' ),
					esc_html( $allowed ),
					(int) $field['max_size']
				);
				?>
				<?php if ( $field['multiple'] ) : ?>
					<?php
					/* translators: %d: max number of files */
					printf( esc_html__( 'Max files: %d.', 'wp-sell-services' ), (int) $field['max_files'] );
					?>
				<?php endif; ?>
			</p>

			<?php if ( ! empty( $value ) ) : ?>
				<div class="wpss-uploaded-files">
					<strong><?php esc_html_e( 'Uploaded files:', 'wp-sell-services' ); ?></strong>
					<ul>
						<?php foreach ( (array) $value as $file_id ) : ?>
							<?php $url = wp_get_attachment_url( $file_id ); ?>
							<?php if ( $url ) : ?>
								<li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( basename( $url ) ); ?></a></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render type-specific settings.
	 *
	 * @param array $field Field configuration.
	 * @return string HTML output.
	 */
	protected function render_type_settings( array $field ): string {
		$field = $this->parse_field( $field );

		ob_start();
		?>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Allowed File Types', 'wp-sell-services' ); ?></label>
			<input type="text" name="allowed_types" value="<?php echo esc_attr( implode( ', ', $field['allowed_types'] ) ); ?>">
			<p class="description"><?php esc_html_e( 'Comma-separated extensions (e.g., jpg, pdf, zip)', 'wp-sell-services' ); ?></p>
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Max File Size (MB)', 'wp-sell-services' ); ?></label>
			<input type="number" name="max_size" value="<?php echo esc_attr( $field['max_size'] ); ?>" min="1" max="100">
		</div>
		<div class="wpss-setting-row">
			<label>
				<input type="checkbox" name="multiple" value="1" <?php checked( $field['multiple'] ); ?>>
				<?php esc_html_e( 'Allow Multiple Files', 'wp-sell-services' ); ?>
			</label>
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Max Files', 'wp-sell-services' ); ?></label>
			<input type="number" name="max_files" value="<?php echo esc_attr( $field['max_files'] ); ?>" min="1" max="20">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate the field value.
	 *
	 * @param mixed $value Value to validate.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	public function validate( $value, array $field ) {
		$field = $this->parse_field( $field );

		// Value should be array of attachment IDs.
		$value = (array) $value;
		$count = count( $value );

		// Check max files.
		if ( $field['multiple'] && $field['max_files'] > 0 && $count > $field['max_files'] ) {
			return new \WP_Error(
				'max_files',
				/* translators: 1: field label, 2: max files */
				sprintf( __( 'Maximum %2$d files allowed for %1$s.', 'wp-sell-services' ), $field['label'], $field['max_files'] )
			);
		}

		// Validate each attachment.
		foreach ( $value as $attachment_id ) {
			$attachment = get_post( $attachment_id );

			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new \WP_Error(
					'invalid_file',
					__( 'Invalid file uploaded.', 'wp-sell-services' )
				);
			}

			// Check file type.
			$file_path = get_attached_file( $attachment_id );
			$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

			if ( ! in_array( $extension, $field['allowed_types'], true ) ) {
				return new \WP_Error(
					'invalid_type',
					/* translators: %s: file extension */
					sprintf( __( 'File type .%s is not allowed.', 'wp-sell-services' ), $extension )
				);
			}

			// Check file size.
			$file_size = filesize( $file_path );
			$max_bytes = $field['max_size'] * 1024 * 1024;

			if ( $file_size > $max_bytes ) {
				return new \WP_Error(
					'file_too_large',
					/* translators: %d: max size in MB */
					sprintf( __( 'File exceeds maximum size of %d MB.', 'wp-sell-services' ), $field['max_size'] )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize the field value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param array $field Field configuration.
	 * @return array
	 */
	public function sanitize( $value, array $field ) {
		return array_map( 'absint', (array) $value );
	}

	/**
	 * Format the value for display.
	 *
	 * @param mixed $value Value to format.
	 * @param array $field Field configuration.
	 * @return string
	 */
	public function format_value( $value, array $field ): string {
		$value = (array) $value;

		if ( empty( $value ) ) {
			return '';
		}

		$links = [];
		foreach ( $value as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$filename = basename( $url );
				$links[] = sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( $url ),
					esc_html( $filename )
				);
			}
		}

		return implode( '<br>', $links );
	}
}
