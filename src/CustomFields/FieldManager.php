<?php
/**
 * Field Manager
 *
 * @package WPSellServices\CustomFields
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\CustomFields;

defined( 'ABSPATH' ) || exit;

/**
 * Manages custom field types registration and retrieval.
 *
 * @since 1.0.0
 */
class FieldManager {

	/**
	 * Registered field types.
	 *
	 * @var array<string, FieldInterface>
	 */
	private array $field_types = [];

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize field manager.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_default_fields();

		/**
		 * Fires after default field types are registered.
		 *
		 * @param FieldManager $manager Field manager instance.
		 */
		do_action( 'wpss_register_field_types', $this );
	}

	/**
	 * Register default field types.
	 *
	 * @return void
	 */
	private function register_default_fields(): void {
		$this->register( new Fields\TextField() );
		$this->register( new Fields\TextareaField() );
		$this->register( new Fields\SelectField() );
		$this->register( new Fields\MultiSelectField() );
		$this->register( new Fields\RadioField() );
		$this->register( new Fields\CheckboxField() );
		$this->register( new Fields\FileUploadField() );
		$this->register( new Fields\DateField() );
		$this->register( new Fields\NumberField() );
	}

	/**
	 * Register a field type.
	 *
	 * @param FieldInterface $field Field type instance.
	 * @return void
	 */
	public function register( FieldInterface $field ): void {
		$this->field_types[ $field->get_type() ] = $field;
	}

	/**
	 * Unregister a field type.
	 *
	 * @param string $type Field type identifier.
	 * @return void
	 */
	public function unregister( string $type ): void {
		unset( $this->field_types[ $type ] );
	}

	/**
	 * Get a field type instance.
	 *
	 * @param string $type Field type identifier.
	 * @return FieldInterface|null
	 */
	public function get( string $type ): ?FieldInterface {
		return $this->field_types[ $type ] ?? null;
	}

	/**
	 * Get all registered field types.
	 *
	 * @return array<string, FieldInterface>
	 */
	public function get_all(): array {
		return $this->field_types;
	}

	/**
	 * Get field types for select dropdown.
	 *
	 * @return array<string, string>
	 */
	public function get_types_for_select(): array {
		$types = [];
		foreach ( $this->field_types as $type => $field ) {
			$types[ $type ] = $field->get_label();
		}
		return $types;
	}

	/**
	 * Check if field type exists.
	 *
	 * @param string $type Field type identifier.
	 * @return bool
	 */
	public function has( string $type ): bool {
		return isset( $this->field_types[ $type ] );
	}
}
