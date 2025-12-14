<?php
/**
 * Field Interface
 *
 * @package WPSellServices\CustomFields
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CustomFields;

/**
 * Interface for custom field types.
 *
 * @since 1.0.0
 */
interface FieldInterface {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Get the field type label.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Get field type icon.
	 *
	 * @return string
	 */
	public function get_icon(): string;

	/**
	 * Render the field for input.
	 *
	 * @param array $field Field configuration.
	 * @param mixed $value Current value.
	 * @return string HTML output.
	 */
	public function render( array $field, $value = null ): string;

	/**
	 * Render the field in admin settings.
	 *
	 * @param array $field Field configuration.
	 * @return string HTML output.
	 */
	public function render_settings( array $field ): string;

	/**
	 * Validate the field value.
	 *
	 * @param mixed $value     Value to validate.
	 * @param array $field     Field configuration.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function validate( $value, array $field );

	/**
	 * Sanitize the field value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param array $field Field configuration.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( $value, array $field );

	/**
	 * Format the value for display.
	 *
	 * @param mixed $value Value to format.
	 * @param array $field Field configuration.
	 * @return string Formatted value.
	 */
	public function format_value( $value, array $field ): string;
}
