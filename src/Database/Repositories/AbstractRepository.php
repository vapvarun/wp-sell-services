<?php
/**
 * Abstract Repository
 *
 * Base class for all database repositories.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

use WPSellServices\Database\SchemaManager;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractRepository class.
 *
 * @since 1.0.0
 */
abstract class AbstractRepository {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $wpdb;

	/**
	 * Schema manager instance.
	 *
	 * @var SchemaManager
	 */
	protected SchemaManager $schema;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected string $primary_key = 'id';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->schema = new SchemaManager();
		$this->table  = $this->get_table_name();
	}

	/**
	 * Get the table name for this repository.
	 *
	 * @return string Table name.
	 */
	abstract protected function get_table_name(): string;

	/**
	 * Find a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return object|null Record object or null.
	 */
	public function find( int $id ): ?object {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$this->primary_key} = %d",
				$id
			)
		);

		return $result ?: null;
	}

	/**
	 * Find records by column value.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Value to match.
	 * @return array<object> Array of records.
	 */
	public function find_by( string $column, mixed $value ): array {
		$format = is_int( $value ) ? '%d' : '%s';

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$column} = {$format}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$value
			)
		);
	}

	/**
	 * Find a single record by column value.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Value to match.
	 * @return object|null Record object or null.
	 */
	public function find_one_by( string $column, mixed $value ): ?object {
		$format = is_int( $value ) ? '%d' : '%s';

		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$column} = {$format} LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$value
			)
		);

		return $result ?: null;
	}

	/**
	 * Get all records.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of records.
	 */
	public function get_all( array $args = [] ): array {
		$defaults = [
			'orderby'  => $this->primary_key,
			'order'    => 'DESC',
			'limit'    => 0,
			'offset'   => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$sql = "SELECT * FROM {$this->table}";
		$sql .= $this->wpdb->prepare(
			' ORDER BY %s %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
			$args['orderby'],
			$args['order']
		);

		if ( $args['limit'] > 0 ) {
			$sql .= $this->wpdb->prepare(
				' LIMIT %d OFFSET %d',
				$args['limit'],
				$args['offset']
			);
		}

		return $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Insert a new record.
	 *
	 * @param array<string, mixed> $data   Record data.
	 * @param array<string>        $format Data formats.
	 * @return int|false Insert ID or false on failure.
	 */
	public function insert( array $data, array $format = [] ): int|false {
		if ( empty( $format ) ) {
			$format = $this->get_formats( $data );
		}

		$result = $this->wpdb->insert( $this->table, $data, $format );

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a record.
	 *
	 * @param int                  $id           Record ID.
	 * @param array<string, mixed> $data         Data to update.
	 * @param array<string>        $format       Data formats.
	 * @param array<string>        $where_format Where formats.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data, array $format = [], array $where_format = [ '%d' ] ): bool {
		if ( empty( $format ) ) {
			$format = $this->get_formats( $data );
		}

		$result = $this->wpdb->update(
			$this->table,
			$data,
			[ $this->primary_key => $id ],
			$format,
			$where_format
		);

		return false !== $result;
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		$result = $this->wpdb->delete(
			$this->table,
			[ $this->primary_key => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Count records.
	 *
	 * @param array<string, mixed> $where Where conditions.
	 * @return int Record count.
	 */
	public function count( array $where = [] ): int {
		$sql = "SELECT COUNT(*) FROM {$this->table}";

		if ( ! empty( $where ) ) {
			$conditions = [];
			$values     = [];

			foreach ( $where as $column => $value ) {
				$format       = is_int( $value ) ? '%d' : '%s';
				$conditions[] = "{$column} = {$format}";
				$values[]     = $value;
			}

			$sql .= ' WHERE ' . implode( ' AND ', $conditions );
			$sql  = $this->wpdb->prepare( $sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Check if a record exists.
	 *
	 * @param int $id Record ID.
	 * @return bool True if exists.
	 */
	public function exists( int $id ): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE {$this->primary_key} = %d",
				$id
			)
		);

		return (int) $result > 0;
	}

	/**
	 * Get data formats based on value types.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @return array<string> Format array.
	 */
	protected function get_formats( array $data ): array {
		$formats = [];

		foreach ( $data as $value ) {
			if ( is_int( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Run a raw query.
	 *
	 * @param string             $sql    SQL query.
	 * @param array<int, mixed> $args   Query arguments.
	 * @return array<object>|null Results or null.
	 */
	protected function query( string $sql, array $args = [] ): ?array {
		if ( ! empty( $args ) ) {
			$sql = $this->wpdb->prepare( $sql, ...$args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Begin a transaction.
	 *
	 * @return void
	 */
	protected function begin_transaction(): void {
		$this->wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit a transaction.
	 *
	 * @return void
	 */
	protected function commit(): void {
		$this->wpdb->query( 'COMMIT' );
	}

	/**
	 * Rollback a transaction.
	 *
	 * @return void
	 */
	protected function rollback(): void {
		$this->wpdb->query( 'ROLLBACK' );
	}
}
