<?php
/**
 * Service Package Repository
 *
 * Database operations for service packages.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * ServicePackageRepository class.
 *
 * @since 1.0.0
 */
class ServicePackageRepository extends AbstractRepository {

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->schema->get_table_name( 'service_packages' );
	}

	/**
	 * Get packages for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return array<object> Array of packages.
	 */
	public function get_by_service( int $service_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE service_id = %d
				ORDER BY sort_order ASC, id ASC",
				$service_id
			)
		);
	}

	/**
	 * Get the starting price for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return float|null Starting price or null.
	 */
	public function get_starting_price( int $service_id ): ?float {
		$price = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MIN(price) FROM {$this->table}
				WHERE service_id = %d",
				$service_id
			)
		);

		return null !== $price ? (float) $price : null;
	}

	/**
	 * Get the minimum delivery days for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return int|null Minimum days or null.
	 */
	public function get_min_delivery_days( int $service_id ): ?int {
		$days = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MIN(delivery_days) FROM {$this->table}
				WHERE service_id = %d",
				$service_id
			)
		);

		return null !== $days ? (int) $days : null;
	}

	/**
	 * Save packages for a service (replaces all existing).
	 *
	 * @param int                        $service_id Service post ID.
	 * @param array<array<string,mixed>> $packages   Array of package data.
	 * @return bool True on success.
	 */
	public function save_packages( int $service_id, array $packages ): bool {
		$this->begin_transaction();

		try {
			// Delete existing packages.
			$this->delete_by_service( $service_id );

			// Insert new packages.
			foreach ( $packages as $index => $package ) {
				$features = $package['features'] ?? [];
				if ( is_array( $features ) ) {
					$features = wp_json_encode( $features );
				}

				$this->insert(
					[
						'service_id'    => $service_id,
						'name'          => $package['name'] ?? '',
						'description'   => $package['description'] ?? '',
						'price'         => (float) ( $package['price'] ?? 0 ),
						'delivery_days' => (int) ( $package['delivery_days'] ?? 1 ),
						'revisions'     => (int) ( $package['revisions'] ?? 0 ),
						'features'      => $features,
						'sort_order'    => $index,
					]
				);
			}

			$this->commit();
			return true;
		} catch ( \Exception $e ) {
			$this->rollback();
			return false;
		}
	}

	/**
	 * Delete all packages for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return int Number of deleted packages.
	 */
	public function delete_by_service( int $service_id ): int {
		$result = $this->wpdb->delete(
			$this->table,
			[ 'service_id' => $service_id ],
			[ '%d' ]
		);

		return $result ?: 0;
	}

	/**
	 * Update package order.
	 *
	 * @param int $package_id Package ID.
	 * @param int $sort_order New sort order.
	 * @return bool True on success.
	 */
	public function update_order( int $package_id, int $sort_order ): bool {
		return $this->update( $package_id, [ 'sort_order' => $sort_order ] );
	}

	/**
	 * Get package with decoded features.
	 *
	 * @param int $package_id Package ID.
	 * @return object|null Package with features array.
	 */
	public function get_with_features( int $package_id ): ?object {
		$package = $this->find( $package_id );

		if ( $package && ! empty( $package->features ) ) {
			$package->features = json_decode( $package->features, true );
		}

		return $package;
	}
}
