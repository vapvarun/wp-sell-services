<?php
/**
 * Repository Interface
 *
 * Defines the contract for all repository classes to ensure consistent method names.
 *
 * @package WPSellServices\Database\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Database\Contracts;

/**
 * Repository Interface.
 *
 * All repository classes must implement this interface to ensure
 * consistent method naming across the codebase.
 *
 * @since 1.0.0
 */
interface RepositoryInterface {

	/**
	 * Find a single record by ID.
	 *
	 * @param int $id Record ID.
	 * @return object|null Record object or null if not found.
	 */
	public function find( int $id ): ?object;

	/**
	 * Find all records.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of records.
	 */
	public function find_all( array $args = [] ): array;

	/**
	 * Find records by a specific column value.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Value to match.
	 * @return array<object> Array of matching records.
	 */
	public function find_by( string $column, mixed $value ): array;

	/**
	 * Find a single record by column value.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Value to match.
	 * @return object|null Record object or null.
	 */
	public function find_one_by( string $column, mixed $value ): ?object;

	/**
	 * Insert a new record.
	 *
	 * @param array<string, mixed> $data Record data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function insert( array $data ): int|false;

	/**
	 * Update an existing record.
	 *
	 * @param int                  $id   Record ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data ): bool;

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool;

	/**
	 * Count total records.
	 *
	 * @param array<string, mixed> $where Optional WHERE conditions.
	 * @return int Total count.
	 */
	public function count( array $where = [] ): int;

	/**
	 * Get the table name for this repository.
	 *
	 * @return string Full table name with prefix.
	 */
	public function get_table(): string;
}
