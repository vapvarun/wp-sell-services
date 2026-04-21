<?php
/**
 * Select Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CustomFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Dropdown select field.
 *
 * @since 1.0.0
 */
class SelectField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'select';
	}

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Dropdown', 'wp-sell-services' );
	}

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'chevron-down';
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
				'options'           => [],
				'placeholder_label' => __( 'Select an option', 'wp-sell-services' ),
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
		$field   = $this->parse_field( $field );
		$value   = $value ?? $field['default'];
		$options = $field['options'];

		ob_start();
		?>
		<select <?php echo $this->build_attributes( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( ! empty( $field['placeholder_label'] ) ) : ?>
				<option value=""><?php echo esc_html( $field['placeholder_label'] ); ?></option>
			<?php endif; ?>

			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
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
		$field   = $this->parse_field( $field );
		$options = $field['options'];

		ob_start();
		?>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
			<div class="wpss-options-builder">
				<div class="wpss-options-list">
					<?php foreach ( $options as $value => $label ) : ?>
						<div class="wpss-option-row">
							<input type="text" name="option_value[]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'Value', 'wp-sell-services' ); ?>">
							<input type="text" name="option_label[]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Label', 'wp-sell-services' ); ?>">
							<button type="button" class="wpss-remove-option">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="wpss-add-option button"><?php esc_html_e( 'Add Option', 'wp-sell-services' ); ?></button>
			</div>
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
		$field   = $this->parse_field( $field );
		$options = array_keys( $field['options'] );

		if ( ! in_array( $value, $options, true ) ) {
			return new \WP_Error(
				'invalid_option',
				/* translators: %s: field label */
				sprintf( __( 'Invalid option selected for %s.', 'wp-sell-services' ), $field['label'] )
			);
		}

		return true;
	}

	/**
	 * Format the value for display.
	 *
	 * @param mixed $value Value to format.
	 * @param array $field Field configuration.
	 * @return string
	 */
	public function format_value( $value, array $field ): string {
		$field = $this->parse_field( $field );
		return esc_html( $field['options'][ $value ] ?? $value );
	}
}
