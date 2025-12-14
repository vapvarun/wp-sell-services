<?php
/**
 * Number Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CustomFields\Fields;

/**
 * Number input field.
 *
 * @since 1.0.0
 */
class NumberField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'number';
	}

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Number', 'wp-sell-services' );
	}

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-performance';
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
				'min'  => '',
				'max'  => '',
				'step' => 1,
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

		$extra = [
			'type' => 'number',
			'step' => $field['step'],
		];

		if ( '' !== $field['min'] ) {
			$extra['min'] = $field['min'];
		}

		if ( '' !== $field['max'] ) {
			$extra['max'] = $field['max'];
		}

		return sprintf(
			'<input %s value="%s">',
			$this->build_attributes( $field, $extra ),
			esc_attr( $value )
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
			<label><?php esc_html_e( 'Minimum Value', 'wp-sell-services' ); ?></label>
			<input type="number" name="min" value="<?php echo esc_attr( $field['min'] ); ?>" step="any">
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Maximum Value', 'wp-sell-services' ); ?></label>
			<input type="number" name="max" value="<?php echo esc_attr( $field['max'] ); ?>" step="any">
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Step', 'wp-sell-services' ); ?></label>
			<input type="number" name="step" value="<?php echo esc_attr( $field['step'] ); ?>" step="any" min="0.01">
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

		if ( ! is_numeric( $value ) ) {
			return new \WP_Error(
				'not_numeric',
				/* translators: %s: field label */
				sprintf( __( '%s must be a number.', 'wp-sell-services' ), $field['label'] )
			);
		}

		$num = (float) $value;

		// Check min.
		if ( '' !== $field['min'] && $num < (float) $field['min'] ) {
			return new \WP_Error(
				'below_min',
				/* translators: 1: field label, 2: minimum value */
				sprintf( __( '%1$s must be at least %2$s.', 'wp-sell-services' ), $field['label'], $field['min'] )
			);
		}

		// Check max.
		if ( '' !== $field['max'] && $num > (float) $field['max'] ) {
			return new \WP_Error(
				'above_max',
				/* translators: 1: field label, 2: maximum value */
				sprintf( __( '%1$s must not exceed %2$s.', 'wp-sell-services' ), $field['label'], $field['max'] )
			);
		}

		return true;
	}

	/**
	 * Sanitize the field value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param array $field Field configuration.
	 * @return float|int
	 */
	public function sanitize( $value, array $field ) {
		$field = $this->parse_field( $field );

		// Return int if step is whole number.
		if ( (float) $field['step'] === floor( (float) $field['step'] ) ) {
			return (int) $value;
		}

		return (float) $value;
	}

	/**
	 * Format the value for display.
	 *
	 * @param mixed $value Value to format.
	 * @param array $field Field configuration.
	 * @return string
	 */
	public function format_value( $value, array $field ): string {
		return esc_html( number_format_i18n( (float) $value, 2 ) );
	}
}
