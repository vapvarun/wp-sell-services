<?php
/**
 * Abstract Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CustomFields\Fields;

defined( 'ABSPATH' ) || exit;

use WPSellServices\CustomFields\FieldInterface;

/**
 * Base class for custom field types.
 *
 * @since 1.0.0
 */
abstract class AbstractField implements FieldInterface {

	/**
	 * Get default field options.
	 *
	 * @return array
	 */
	protected function get_default_settings(): array {
		return [
			'id'          => '',
			'label'       => '',
			'description' => '',
			'required'    => false,
			'placeholder' => '',
			'default'     => '',
			'class'       => '',
		];
	}

	/**
	 * Merge field config with defaults.
	 *
	 * @param array $field Field configuration.
	 * @return array
	 */
	protected function parse_field( array $field ): array {
		return wp_parse_args( $field, $this->get_default_settings() );
	}

	/**
	 * Build input attributes.
	 *
	 * @param array $field Field configuration.
	 * @param array $extra Extra attributes.
	 * @return string
	 */
	protected function build_attributes( array $field, array $extra = [] ): string {
		$field = $this->parse_field( $field );

		$attrs = [
			'id'    => $field['id'],
			'name'  => 'wpss_requirements[' . $field['id'] . ']',
			'class' => 'wpss-field-input ' . $field['class'],
		];

		if ( ! empty( $field['placeholder'] ) ) {
			$attrs['placeholder'] = $field['placeholder'];
		}

		if ( ! empty( $field['required'] ) ) {
			$attrs['required'] = true;
		}

		$attrs = array_merge( $attrs, $extra );

		$parts = [];
		foreach ( $attrs as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$parts[] = esc_attr( $key );
				}
			} else {
				$parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Render common settings fields for admin builder.
	 *
	 * @param array $field Field configuration.
	 * @return string HTML output.
	 */
	public function render_settings( array $field ): string {
		$field = $this->parse_field( $field );

		ob_start();
		?>
		<div class="wpss-field-settings">
			<div class="wpss-setting-row">
				<label><?php esc_html_e( 'Label', 'wp-sell-services' ); ?></label>
				<input type="text" name="label" value="<?php echo esc_attr( $field['label'] ); ?>" required>
			</div>

			<div class="wpss-setting-row">
				<label><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
				<textarea name="description"><?php echo esc_textarea( $field['description'] ); ?></textarea>
			</div>

			<div class="wpss-setting-row">
				<label><?php esc_html_e( 'Placeholder', 'wp-sell-services' ); ?></label>
				<input type="text" name="placeholder" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
			</div>

			<div class="wpss-setting-row">
				<label>
					<input type="checkbox" name="required" value="1" <?php checked( $field['required'] ); ?>>
					<?php esc_html_e( 'Required', 'wp-sell-services' ); ?>
				</label>
			</div>

			<?php echo $this->render_type_settings( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render type-specific settings.
	 *
	 * Override in child classes for custom settings.
	 *
	 * @param array $field Field configuration.
	 * @return string HTML output.
	 */
	protected function render_type_settings( array $field ): string {
		return '';
	}

	/**
	 * Default validation (returns true).
	 *
	 * @param mixed $value Value to validate.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	public function validate( $value, array $field ) {
		return true;
	}

	/**
	 * Default sanitization.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param array $field Field configuration.
	 * @return mixed
	 */
	public function sanitize( $value, array $field ) {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Default value formatting.
	 *
	 * @param mixed $value Value to format.
	 * @param array $field Field configuration.
	 * @return string
	 */
	public function format_value( $value, array $field ): string {
		return esc_html( (string) $value );
	}
}
