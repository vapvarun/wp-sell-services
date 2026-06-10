<?php
/**
 * Typed Row Accessor
 *
 * Read-only wrapper around a raw database row (the `object` shape `$wpdb`
 * returns, or an associative array) that exposes strictly-typed accessors.
 *
 * Admin viewers read dozens of columns off `$wpdb` rows whose property types
 * PHPStan can only see as `mixed`. Every `(int) $row->actor_id` cast and every
 * `$row->status ?? ''` fallback then has to be re-proven safe at every call
 * site, and any direct `$row->foo` access trips "access offset on mixed" at
 * level 6. This accessor centralises the coercion: the viewer asks for an
 * `int`, `string`, `bool`, `float`, or a nullable variant by key and gets a
 * value PHPStan knows the type of — once, in one place.
 *
 * It performs NO queries and holds NO business logic; it only narrows types.
 *
 * @package WPSellServices\Data
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable typed accessor over a single raw DB row.
 *
 * @since 1.2.0
 */
final class AdminRow {

	/**
	 * The wrapped row, normalised to an associative array of column => value.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>|object $row Raw row from $wpdb (object or array).
	 */
	public function __construct( array|object $row ) {
		$this->data = is_object( $row ) ? get_object_vars( $row ) : $row;
	}

	/**
	 * Convenience factory.
	 *
	 * @param array<string, mixed>|object $row Raw row from $wpdb.
	 * @return self
	 */
	public static function from( array|object $row ): self {
		return new self( $row );
	}

	/**
	 * Whether the underlying row defines the given column.
	 *
	 * @param string $key Column name.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * Return the raw, un-narrowed value for a column.
	 *
	 * @param string $key Column name.
	 * @return mixed
	 */
	public function raw( string $key ): mixed {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Read a column as an integer.
	 *
	 * @param string $key     Column name.
	 * @param int    $fallback Fallback when absent/null.
	 * @return int
	 */
	public function int( string $key, int $fallback = 0 ): int {
		$value = $this->data[ $key ] ?? null;

		if ( null === $value ) {
			return $fallback;
		}

		return is_scalar( $value ) ? (int) $value : $fallback;
	}

	/**
	 * Read a column as a float.
	 *
	 * @param string $key     Column name.
	 * @param float  $fallback Fallback when absent/null.
	 * @return float
	 */
	public function float( string $key, float $fallback = 0.0 ): float {
		$value = $this->data[ $key ] ?? null;

		if ( null === $value ) {
			return $fallback;
		}

		return is_scalar( $value ) ? (float) $value : $fallback;
	}

	/**
	 * Read a column as a string.
	 *
	 * @param string $key     Column name.
	 * @param string $fallback Fallback when absent/null.
	 * @return string
	 */
	public function string( string $key, string $fallback = '' ): string {
		$value = $this->data[ $key ] ?? null;

		if ( null === $value ) {
			return $fallback;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return $fallback;
	}

	/**
	 * Read a column as a boolean.
	 *
	 * Treats the common MySQL tinyint(1) truthiness ("1"/1/true) consistently.
	 *
	 * @param string $key     Column name.
	 * @param bool   $fallback Fallback when absent/null.
	 * @return bool
	 */
	public function bool( string $key, bool $fallback = false ): bool {
		if ( ! array_key_exists( $key, $this->data ) ) {
			return $fallback;
		}

		$value = $this->data[ $key ];

		if ( null === $value ) {
			return $fallback;
		}

		// empty() treats 0, 0.0, '', '0', and false as falsy, which matches
		// MySQL tinyint(1) truthiness for admin viewer columns.
		return ! empty( $value );
	}

	/**
	 * Read a column as a nullable integer (preserving SQL NULL).
	 *
	 * @param string $key Column name.
	 * @return int|null
	 */
	public function nullable_int( string $key ): ?int {
		$value = $this->data[ $key ] ?? null;

		if ( null === $value || '' === $value ) {
			return null;
		}

		return is_scalar( $value ) ? (int) $value : null;
	}

	/**
	 * Read a column as a nullable string (preserving SQL NULL).
	 *
	 * @param string $key Column name.
	 * @return string|null
	 */
	public function nullable_string( string $key ): ?string {
		$value = $this->data[ $key ] ?? null;

		if ( null === $value ) {
			return null;
		}

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Read a column as a nullable float (preserving SQL NULL).
	 *
	 * @param string $key Column name.
	 * @return float|null
	 */
	public function nullable_float( string $key ): ?float {
		$value = $this->data[ $key ] ?? null;

		if ( null === $value || '' === $value ) {
			return null;
		}

		return is_scalar( $value ) ? (float) $value : null;
	}

	/**
	 * Decode a JSON column into an associative array.
	 *
	 * Returns an empty array when the column is absent, null, empty, or
	 * does not decode to an array.
	 *
	 * @param string $key Column name holding a JSON string.
	 * @return array<string, mixed>
	 */
	public function json( string $key ): array {
		$value = $this->data[ $key ] ?? null;

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
