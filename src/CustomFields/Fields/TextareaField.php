<?php
/**
 * Textarea Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CustomFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-line textarea field.
 *
 * @since 1.0.0
 */
class TextareaField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'textarea';
	}

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Textarea', 'wp-sell-services' );
	}

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-editor-paragraph';
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
				'rows'       => 5,
				'min_length' => 0,
				'max_length' => 0,
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
		$value = $value ?? $field['default'];

		$extra = [ 'rows' => $field['rows'] ];

		if ( ! empty( $field['min_length'] ) ) {
			$extra['minlength'] = $field['min_length'];
		}

		if ( ! empty( $field['max_length'] ) ) {
			$extra['maxlength'] = $field['max_length'];
		}

		return sprintf(
			'<textarea %s>%s</textarea>',
			$this->build_attributes( $field, $extra ),
			esc_textarea( $value )
		);
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
			<label><?php esc_html_e( 'Rows', 'wp-sell-services' ); ?></label>
			<input type="number" name="rows" value="<?php echo esc_attr( $field['rows'] ); ?>" min="2" max="20">
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Min Length', 'wp-sell-services' ); ?></label>
			<input type="number" name="min_length" value="<?php echo esc_attr( $field['min_length'] ); ?>" min="0">
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Max Length', 'wp-sell-services' ); ?></label>
			<input type="number" name="max_length" value="<?php echo esc_attr( $field['max_length'] ); ?>" min="0">
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
		$length = mb_strlen( (string) $value );

		if ( $field['min_length'] > 0 && $length < $field['min_length'] ) {
			return new \WP_Error(
				'min_length',
				/* translators: 1: field label, 2: minimum length */
				sprintf( __( '%1$s must be at least %2$d characters.', 'wp-sell-services' ), $field['label'], $field['min_length'] )
			);
		}

		if ( $field['max_length'] > 0 && $length > $field['max_length'] ) {
			return new \WP_Error(
				'max_length',
				/* translators: 1: field label, 2: maximum length */
				sprintf( __( '%1$s must not exceed %2$d characters.', 'wp-sell-services' ), $field['label'], $field['max_length'] )
			);
		}

		return true;
	}

	/**
	 * Sanitize the field value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param array $field Field configuration.
	 * @return string
	 */
	public function sanitize( $value, array $field ) {
		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Format the value for display.
	 *
	 * @param mixed $value Value to format.
	 * @param array $field Field configuration.
	 * @return string
	 */
	public function format_value( $value, array $field ): string {
		return nl2br( esc_html( (string) $value ) );
	}
}
