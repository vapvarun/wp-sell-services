<?php
/**
 * Field Validator
 *
 * @package WPSellServices\CustomFields
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CustomFields;

/**
 * Validates and sanitizes custom field submissions.
 *
 * @since 1.0.0
 */
class FieldValidator {

	/**
	 * Field manager instance.
	 *
	 * @var FieldManager
	 */
	private FieldManager $manager;

	/**
	 * Constructor.
	 *
	 * @param FieldManager|null $manager Field manager instance.
	 */
	public function __construct( ?FieldManager $manager = null ) {
		$this->manager = $manager ?? FieldManager::instance();
	}

	/**
	 * Validate all fields in a submission.
	 *
	 * @param array $fields     Field configurations.
	 * @param array $submission Submitted data.
	 * @return true|\WP_Error True on success, WP_Error with all validation errors.
	 */
	public function validate_all( array $fields, array $submission ) {
		$errors = new \WP_Error();

		foreach ( $fields as $field ) {
			$field_id = $field['id'] ?? '';
			$value = $submission[ $field_id ] ?? null;

			$result = $this->validate_field( $field, $value );

			if ( is_wp_error( $result ) ) {
				foreach ( $result->get_error_codes() as $code ) {
					$errors->add( $code, $result->get_error_message( $code ) );
				}
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Validate a single field.
	 *
	 * @param array $field Field configuration.
	 * @param mixed $value Submitted value.
	 * @return true|\WP_Error
	 */
	public function validate_field( array $field, $value ) {
		$field_id = $field['id'] ?? 'unknown';
		$label = $field['label'] ?? $field_id;
		$required = ! empty( $field['required'] );
		$type = $field['type'] ?? 'text';

		// Check required.
		if ( $required && $this->is_empty( $value ) ) {
			return new \WP_Error(
				'required_field_' . $field_id,
				/* translators: %s: field label */
				sprintf( __( '%s is required.', 'wp-sell-services' ), $label )
			);
		}

		// Skip validation if empty and not required.
		if ( $this->is_empty( $value ) ) {
			return true;
		}

		// Get field type and validate.
		$field_type = $this->manager->get( $type );

		if ( ! $field_type ) {
			return true; // Unknown field type, skip validation.
		}

		return $field_type->validate( $value, $field );
	}

	/**
	 * Sanitize all fields in a submission.
	 *
	 * @param array $fields     Field configurations.
	 * @param array $submission Submitted data.
	 * @return array Sanitized data.
	 */
	public function sanitize_all( array $fields, array $submission ): array {
		$sanitized = [];

		foreach ( $fields as $field ) {
			$field_id = $field['id'] ?? '';
			$value = $submission[ $field_id ] ?? null;

			$sanitized[ $field_id ] = $this->sanitize_field( $field, $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single field value.
	 *
	 * @param array $field Field configuration.
	 * @param mixed $value Value to sanitize.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_field( array $field, $value ) {
		$type = $field['type'] ?? 'text';
		$field_type = $this->manager->get( $type );

		if ( ! $field_type ) {
			return sanitize_text_field( (string) $value );
		}

		return $field_type->sanitize( $value, $field );
	}

	/**
	 * Check if a value is empty.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private function is_empty( $value ): bool {
		if ( null === $value || '' === $value ) {
			return true;
		}

		if ( is_array( $value ) && empty( $value ) ) {
			return true;
		}

		return false;
	}
}
