<?php
/**
 * Date Field
 *
 * @package WPSellServices\CustomFields\Fields
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CustomFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Date picker field.
 *
 * @since 1.0.0
 */
class DateField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'date';
	}

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Date', 'wp-sell-services' );
	}

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-calendar-alt';
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
				'min_date'    => '',
				'max_date'    => '',
				'date_format' => 'Y-m-d',
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

		$extra = [ 'type' => 'date' ];

		if ( ! empty( $field['min_date'] ) ) {
			$extra['min'] = $field['min_date'];
		}

		if ( ! empty( $field['max_date'] ) ) {
			$extra['max'] = $field['max_date'];
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
			<label><?php esc_html_e( 'Min Date', 'wp-sell-services' ); ?></label>
			<input type="date" name="min_date" value="<?php echo esc_attr( $field['min_date'] ); ?>">
			<p class="description"><?php esc_html_e( 'Leave empty for no minimum. Use "today" for current date.', 'wp-sell-services' ); ?></p>
		</div>
		<div class="wpss-setting-row">
			<label><?php esc_html_e( 'Max Date', 'wp-sell-services' ); ?></label>
			<input type="date" name="max_date" value="<?php echo esc_attr( $field['max_date'] ); ?>">
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

		// Validate date format.
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return new \WP_Error(
				'invalid_date',
				/* translators: %s: field label */
				sprintf( __( 'Invalid date format for %s.', 'wp-sell-services' ), $field['label'] )
			);
		}

		// Check min date.
		if ( ! empty( $field['min_date'] ) ) {
			$min = $field['min_date'];

			if ( 'today' === $min ) {
				$min = gmdate( 'Y-m-d' );
			}

			if ( $value < $min ) {
				return new \WP_Error(
					'date_too_early',
					/* translators: 1: field label, 2: minimum date */
					sprintf( __( '%1$s must be on or after %2$s.', 'wp-sell-services' ), $field['label'], $min )
				);
			}
		}

		// Check max date.
		if ( ! empty( $field['max_date'] ) && $value > $field['max_date'] ) {
			return new \WP_Error(
				'date_too_late',
				/* translators: 1: field label, 2: maximum date */
				sprintf( __( '%1$s must be on or before %2$s.', 'wp-sell-services' ), $field['label'], $field['max_date'] )
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
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );

		if ( ! $date ) {
			return esc_html( $value );
		}

		return esc_html( wp_date( get_option( 'date_format' ), $date->getTimestamp() ) );
	}
}
