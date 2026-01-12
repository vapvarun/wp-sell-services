<?php
/**
 * Schema Validator
 *
 * Validates that model properties match database schema columns.
 * Helps catch model-DB drift before it causes runtime errors.
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Core;

use WPSellServices\Database\SchemaManager;

/**
 * Schema Validator class.
 *
 * Compares model classes against database schema to detect:
 * - Missing columns in models
 * - Missing columns in database
 * - Type mismatches
 * - Naming convention issues
 *
 * @since 1.0.0
 */
class SchemaValidator {

	/**
	 * Schema manager instance.
	 *
	 * @var SchemaManager
	 */
	private SchemaManager $schema;

	/**
	 * Model to table mappings.
	 *
	 * Maps model class names to their database tables and property mappings.
	 *
	 * @var array<string, array{table: string, mappings: array<string, string>}>
	 */
	private array $model_mappings = [
		'WPSellServices\\Models\\ServiceOrder'   => [
			'table'    => 'orders',
			'mappings' => [], // Direct mapping (property name = column name).
		],
		'WPSellServices\\Models\\VendorProfile'  => [
			'table'    => 'vendor_profiles',
			'mappings' => [
				// Model property => DB column (when different).
				'title'         => 'tagline',
				'cover_id'      => 'cover_image_id',
				'response_time' => 'response_time_hours',
				'delivery_rate' => 'on_time_delivery_rate',
				'tier'          => 'verification_tier',
				'rating'        => 'avg_rating',
				'review_count'  => 'total_reviews',
			],
		],
		'WPSellServices\\Models\\Service'        => [
			'table'    => 'services', // This is a CPT, not custom table.
			'is_cpt'   => true,
		],
		'WPSellServices\\Models\\Conversation'   => [
			'table'    => 'conversations',
			'mappings' => [],
		],
		'WPSellServices\\Models\\Dispute'        => [
			'table'    => 'disputes',
			'mappings' => [],
		],
		'WPSellServices\\Models\\Review'         => [
			'table'    => 'reviews',
			'mappings' => [],
		],
	];

	/**
	 * Properties to ignore during validation (not stored in DB).
	 *
	 * @var array<string>
	 */
	private array $ignored_properties = [
		'languages',
		'skills',
		'certifications',
		'education',
		'response_rate',
		'completion_rate',
		'member_since',
		'last_active',
		'vacation_until', // Computed from vacation_mode.
		'is_verified',    // Computed from verification_tier.
		'original_deadline',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->schema = new SchemaManager();
	}

	/**
	 * Validate all model-schema mappings.
	 *
	 * @return array<string, array{errors: array<string>, warnings: array<string>}>
	 */
	public function validate_all(): array {
		$results = [];

		foreach ( $this->model_mappings as $model_class => $config ) {
			// Skip CPTs (they use wp_posts).
			if ( ! empty( $config['is_cpt'] ) ) {
				continue;
			}

			$results[ $model_class ] = $this->validate_model( $model_class, $config );
		}

		return $results;
	}

	/**
	 * Validate a single model against its schema.
	 *
	 * @param string               $model_class Model class name.
	 * @param array<string, mixed> $config      Model configuration.
	 * @return array{errors: array<string>, warnings: array<string>}
	 */
	public function validate_model( string $model_class, array $config ): array {
		$errors   = [];
		$warnings = [];

		// Check if class exists.
		if ( ! class_exists( $model_class ) ) {
			$errors[] = "Model class {$model_class} does not exist";
			return compact( 'errors', 'warnings' );
		}

		// Get model properties via reflection.
		$reflection       = new \ReflectionClass( $model_class );
		$model_properties = [];

		foreach ( $reflection->getProperties( \ReflectionProperty::IS_PUBLIC ) as $property ) {
			$name = $property->getName();
			if ( ! in_array( $name, $this->ignored_properties, true ) ) {
				$model_properties[] = $name;
			}
		}

		// Get database columns.
		$table_name = $config['table'];
		$db_columns = $this->get_table_columns( $table_name );

		if ( empty( $db_columns ) ) {
			$warnings[] = "Table {$table_name} does not exist or has no columns";
			return compact( 'errors', 'warnings' );
		}

		// Get property to column mappings.
		$mappings = $config['mappings'] ?? [];

		// Check each model property has a corresponding column.
		foreach ( $model_properties as $property ) {
			$expected_column = $mappings[ $property ] ?? $property;

			if ( ! in_array( $expected_column, $db_columns, true ) ) {
				// Check for common naming issues.
				$snake_case = $this->to_snake_case( $property );
				if ( in_array( $snake_case, $db_columns, true ) && $snake_case !== $expected_column ) {
					$warnings[] = "Property '{$property}' might need mapping to column '{$snake_case}'";
				} else {
					$errors[] = "Property '{$property}' expects column '{$expected_column}' but it doesn't exist in table '{$table_name}'";
				}
			}
		}

		// Check for DB columns not mapped to properties.
		$mapped_columns = array_values( $mappings );
		foreach ( $db_columns as $column ) {
			// Skip standard columns.
			if ( in_array( $column, [ 'id', 'created_at', 'updated_at' ], true ) ) {
				continue;
			}

			// Check if column is mapped.
			$is_mapped = in_array( $column, $mapped_columns, true );

			// Check if column matches a property directly.
			$matches_property = in_array( $column, $model_properties, true );

			// Check camelCase version.
			$camel_case        = $this->to_camel_case( $column );
			$matches_camelcase = in_array( $camel_case, $model_properties, true );

			if ( ! $is_mapped && ! $matches_property && ! $matches_camelcase ) {
				$warnings[] = "Column '{$column}' in table '{$table_name}' has no corresponding model property";
			}
		}

		return compact( 'errors', 'warnings' );
	}

	/**
	 * Get columns for a table.
	 *
	 * @param string $table_name Table name without prefix.
	 * @return array<string> Column names.
	 */
	private function get_table_columns( string $table_name ): array {
		global $wpdb;

		$full_table = $wpdb->prefix . 'wpss_' . $table_name;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$full_table
			)
		);

		return $columns ?: [];
	}

	/**
	 * Convert camelCase to snake_case.
	 *
	 * @param string $input Input string.
	 * @return string Snake case string.
	 */
	private function to_snake_case( string $input ): string {
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) );
	}

	/**
	 * Convert snake_case to camelCase.
	 *
	 * @param string $input Input string.
	 * @return string Camel case string.
	 */
	private function to_camel_case( string $input ): string {
		return lcfirst( str_replace( '_', '', ucwords( $input, '_' ) ) );
	}

	/**
	 * Validate and return a formatted report.
	 *
	 * @return string Human-readable validation report.
	 */
	public function get_report(): string {
		$results = $this->validate_all();
		$output  = "Schema Validation Report\n";
		$output .= "========================\n\n";

		$has_issues = false;

		foreach ( $results as $model => $result ) {
			$short_name = substr( $model, strrpos( $model, '\\' ) + 1 );

			if ( empty( $result['errors'] ) && empty( $result['warnings'] ) ) {
				$output .= "✓ {$short_name}: OK\n";
				continue;
			}

			$has_issues = true;
			$output    .= "✗ {$short_name}:\n";

			foreach ( $result['errors'] as $error ) {
				$output .= "  ERROR: {$error}\n";
			}

			foreach ( $result['warnings'] as $warning ) {
				$output .= "  WARNING: {$warning}\n";
			}

			$output .= "\n";
		}

		if ( ! $has_issues ) {
			$output .= "\nAll models validated successfully!\n";
		}

		return $output;
	}

	/**
	 * Check if validation passes (no errors).
	 *
	 * @return bool True if no errors found.
	 */
	public function is_valid(): bool {
		$results = $this->validate_all();

		foreach ( $results as $result ) {
			if ( ! empty( $result['errors'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get all errors from validation.
	 *
	 * @return array<string> All error messages.
	 */
	public function get_errors(): array {
		$results = $this->validate_all();
		$errors  = [];

		foreach ( $results as $model => $result ) {
			foreach ( $result['errors'] as $error ) {
				$errors[] = "[{$model}] {$error}";
			}
		}

		return $errors;
	}
}
