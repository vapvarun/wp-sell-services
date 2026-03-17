<?php
/**
 * Model Interface
 *
 * Defines the contract for all model classes to ensure consistent factory methods.
 *
 * @package WPSellServices\Database\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Database\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Model Interface.
 *
 * All model classes must implement this interface to ensure
 * consistent factory method naming (from_db, NOT from_row).
 *
 * @since 1.0.0
 */
interface ModelInterface {

	/**
	 * Create a model instance from a database row.
	 *
	 * IMPORTANT: This method MUST be named "from_db", NOT "from_row".
	 * This naming convention is enforced across the entire codebase.
	 *
	 * @param object $row Database row object from wpdb->get_row().
	 * @return static New model instance populated with row data.
	 */
	public static function from_db( object $row ): static;

	/**
	 * Convert the model to an array for database insertion/update.
	 *
	 * @return array<string, mixed> Array of column => value pairs.
	 */
	public function to_array(): array;

	/**
	 * Get the primary key value.
	 *
	 * @return int|null The ID or null if not set.
	 */
	public function get_id(): ?int;
}
