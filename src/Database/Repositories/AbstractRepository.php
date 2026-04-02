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
	 * Allowed columns for ordering and filtering.
	 * Override in child classes to specify allowed columns.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array( 'id', 'created_at', 'updated_at' );

	/**
	 * Allowed order directions.
	 *
	 * @var array<string>
	 */
	protected array $allowed_order = array( 'ASC', 'DESC' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $this->get_table_name();
	}

	/**
	 * Get a prefixed WPSS table name.
	 *
	 * @param string $name Table name without prefix (e.g. 'orders').
	 * @return string Full table name with wpdb prefix.
	 */
	protected function table_name( string $name ): string {
		return $this->wpdb->prefix . 'wpss_' . $name;
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
		// Validate column name against whitelist.
		$column = sanitize_key( $column );
		if ( ! $this->is_valid_column( $column ) ) {
			return array();
		}

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
		// Validate column name against whitelist.
		$column = sanitize_key( $column );
		if ( ! $this->is_valid_column( $column ) ) {
			return null;
		}

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
	public function get_all( array $args = array() ): array {
		$defaults = array(
			'orderby' => $this->primary_key,
			'order'   => 'DESC',
			'limit'   => 0,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist to prevent SQL injection.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		$sql = "SELECT * FROM {$this->table} ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
	public function insert( array $data, array $format = array() ): int|false {
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
	public function update( int $id, array $data, array $format = array(), array $where_format = array( '%d' ) ): bool {
		if ( empty( $format ) ) {
			$format = $this->get_formats( $data );
		}

		$result = $this->wpdb->update(
			$this->table,
			$data,
			array( $this->primary_key => $id ),
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
			array( $this->primary_key => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Count records.
	 *
	 * @param array<string, mixed> $where Where conditions.
	 * @return int Record count.
	 */
	public function count( array $where = array() ): int {
		$sql = "SELECT COUNT(*) FROM {$this->table}";

		if ( ! empty( $where ) ) {
			$conditions = array();
			$values     = array();

			foreach ( $where as $column => $value ) {
				// Validate column name against whitelist to prevent SQL injection.
				$column = sanitize_key( $column );
				if ( ! $this->is_valid_column( $column ) ) {
					continue;
				}
				$format       = is_int( $value ) ? '%d' : '%s';
				$conditions[] = "{$column} = {$format}";
				$values[]     = $value;
			}

			// Only add WHERE clause if we have valid conditions.
			if ( ! empty( $conditions ) ) {
				$sql .= ' WHERE ' . implode( ' AND ', $conditions );
				$sql  = $this->wpdb->prepare( $sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
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
	 * Validate and sanitize an ORDER BY column name.
	 *
	 * @param string $column Column name to validate.
	 * @return string Validated column name or primary key as fallback.
	 */
	protected function validate_orderby( string $column ): string {
		$column = sanitize_key( $column );
		return in_array( $column, $this->allowed_columns, true ) ? $column : $this->primary_key;
	}

	/**
	 * Validate and sanitize an ORDER direction.
	 *
	 * @param string $order Order direction to validate.
	 * @return string Validated order direction (ASC or DESC).
	 */
	protected function validate_order( string $order ): string {
		$order = strtoupper( trim( $order ) );
		return in_array( $order, $this->allowed_order, true ) ? $order : 'DESC';
	}

	/**
	 * Validate a column name for WHERE clauses.
	 *
	 * @param string $column Column name to validate.
	 * @return bool True if column is allowed.
	 */
	protected function is_valid_column( string $column ): bool {
		return in_array( sanitize_key( $column ), $this->allowed_columns, true );
	}

	/**
	 * Get data formats based on value types.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @return array<string> Format array.
	 */
	protected function get_formats( array $data ): array {
		$formats = array();

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
	 * @param string            $sql    SQL query.
	 * @param array<int, mixed> $args   Query arguments.
	 * @return array<object>|null Results or null.
	 */
	protected function query( string $sql, array $args = array() ): ?array {
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
