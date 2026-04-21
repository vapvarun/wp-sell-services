<?php
/**
 * Multi-Select Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CustomFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-select dropdown field.
 *
 * @since 1.0.0
 */
class MultiSelectField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'multiselect';
	}

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Multi-Select', 'wp-sell-services' );
	}

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'list';
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
				'options'      => [],
				'min_selected' => 0,
				'max_selected' => 0,
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
		$value   = (array) ( $value ?? $field['default'] );
		$options = $field['options'];

		$extra = [ 'multiple' => true ];

		ob_start();
		?>
		<select <?php echo $this->build_attributes( $field, $extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( in_array( $option_value, $value, true ) ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="wpss-field-hint"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple options.', 'wp-sell-services' ); ?></p>
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
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Min Selections', 'wp-sell-services' ); ?></label>
			<input type="number" name="min_selected" value="<?php echo esc_attr( $field['min_selected'] ); ?>" min="0">
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Max Selections', 'wp-sell-services' ); ?></label>
			<input type="number" name="max_selected" value="<?php echo esc_attr( $field['max_selected'] ); ?>" min="0">
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
		$value   = (array) $value;
		$options = array_keys( $field['options'] );
		$count   = count( $value );

		// Check all values are valid options.
		foreach ( $value as $v ) {
			if ( ! in_array( $v, $options, true ) ) {
				return new \WP_Error(
					'invalid_option',
					/* translators: %s: field label */
					sprintf( __( 'Invalid option selected for %s.', 'wp-sell-services' ), $field['label'] )
				);
			}
		}

		// Check min/max selections.
		if ( $field['min_selected'] > 0 && $count < $field['min_selected'] ) {
			return new \WP_Error(
				'min_selected',
				/* translators: 1: field label, 2: minimum selections */
				sprintf( __( 'Please select at least %2$d options for %1$s.', 'wp-sell-services' ), $field['label'], $field['min_selected'] )
			);
		}

		if ( $field['max_selected'] > 0 && $count > $field['max_selected'] ) {
			return new \WP_Error(
				'max_selected',
				/* translators: 1: field label, 2: maximum selections */
				sprintf( __( 'Please select no more than %2$d options for %1$s.', 'wp-sell-services' ), $field['label'], $field['max_selected'] )
			);
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
		return array_map( 'sanitize_text_field', (array) $value );
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
		$value = (array) $value;

		$labels = array_map(
			function ( $v ) use ( $field ) {
				return $field['options'][ $v ] ?? $v;
			},
			$value
		);

		return esc_html( implode( ', ', $labels ) );
	}
}
