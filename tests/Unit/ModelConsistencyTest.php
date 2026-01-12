<?php
/**
 * Model Consistency Tests
 *
 * Tests that all models follow naming conventions and implement required methods.
 *
 * @package WPSellServices\Tests\Unit
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Unit;

use WPSellServices\Tests\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Model Consistency Test class.
 *
 * Ensures all model classes follow established patterns:
 * - Have from_db() method (NOT from_row)
 * - Have consistent property types
 * - Follow naming conventions
 *
 * @since 1.0.0
 */
class ModelConsistencyTest extends TestCase {

	/**
	 * Model classes to test.
	 *
	 * @var array<string>
	 */
	private array $model_classes = [
		'WPSellServices\\Models\\ServiceOrder',
		'WPSellServices\\Models\\VendorProfile',
		'WPSellServices\\Models\\Service',
		'WPSellServices\\Models\\Conversation',
		'WPSellServices\\Models\\Message',
		'WPSellServices\\Models\\Dispute',
		'WPSellServices\\Models\\Review',
		'WPSellServices\\Models\\Delivery',
	];

	/**
	 * Test all models have from_db method, not from_row.
	 */
	public function test_models_use_from_db_not_from_row(): void {
		foreach ( $this->model_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$reflection = new ReflectionClass( $class );
			$short_name = $reflection->getShortName();

			// Should NOT have from_row.
			$this->assertFalse(
				$reflection->hasMethod( 'from_row' ),
				"Model {$short_name} uses deprecated from_row() - should use from_db()"
			);

			// Should have from_db (except Service which uses from_post).
			if ( $short_name !== 'Service' ) {
				$this->assertTrue(
					$reflection->hasMethod( 'from_db' ),
					"Model {$short_name} is missing from_db() method"
				);

				// Verify from_db is static.
				if ( $reflection->hasMethod( 'from_db' ) ) {
					$method = $reflection->getMethod( 'from_db' );
					$this->assertTrue(
						$method->isStatic(),
						"Model {$short_name}::from_db() should be static"
					);
				}
			}
		}
	}

	/**
	 * Test Service model uses from_post instead of from_db.
	 */
	public function test_service_model_uses_from_post(): void {
		$class = 'WPSellServices\\Models\\Service';

		if ( ! class_exists( $class ) ) {
			$this->markTestSkipped( 'Service model class not found' );
		}

		$reflection = new ReflectionClass( $class );

		$this->assertTrue(
			$reflection->hasMethod( 'from_post' ),
			'Service model should have from_post() method'
		);

		$method = $reflection->getMethod( 'from_post' );
		$this->assertTrue(
			$method->isStatic(),
			'Service::from_post() should be static'
		);
	}

	/**
	 * Test models have id property.
	 */
	public function test_models_have_id_property(): void {
		foreach ( $this->model_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$reflection = new ReflectionClass( $class );
			$short_name = $reflection->getShortName();

			$this->assertTrue(
				$reflection->hasProperty( 'id' ),
				"Model {$short_name} is missing 'id' property"
			);
		}
	}

	/**
	 * Test repository classes use from_db when hydrating models.
	 *
	 * This test scans repository files for incorrect from_row calls.
	 */
	public function test_repositories_use_from_db(): void {
		$repo_path = dirname( __DIR__, 2 ) . '/src/Database/Repositories';

		if ( ! is_dir( $repo_path ) ) {
			$this->markTestSkipped( 'Repositories directory not found' );
		}

		$files = glob( $repo_path . '/*.php' );

		foreach ( $files as $file ) {
			$content   = file_get_contents( $file );
			$filename  = basename( $file );

			// Check for incorrect from_row usage.
			$has_from_row = preg_match( '/->from_row\s*\(|::from_row\s*\(/', $content );

			$this->assertFalse(
				(bool) $has_from_row,
				"Repository {$filename} uses deprecated from_row() - should use from_db()"
			);
		}
	}

	/**
	 * Test ServiceOrder has all required status constants.
	 */
	public function test_service_order_has_status_constants(): void {
		$class = 'WPSellServices\\Models\\ServiceOrder';

		if ( ! class_exists( $class ) ) {
			$this->markTestSkipped( 'ServiceOrder class not found' );
		}

		$required_statuses = [
			'STATUS_PENDING_PAYMENT',
			'STATUS_PENDING_REQUIREMENTS',
			'STATUS_IN_PROGRESS',
			'STATUS_PENDING_APPROVAL',
			'STATUS_REVISION_REQUESTED',
			'STATUS_COMPLETED',
			'STATUS_CANCELLED',
			'STATUS_DISPUTED',
		];

		$reflection = new ReflectionClass( $class );
		$constants  = $reflection->getConstants();

		foreach ( $required_statuses as $status ) {
			$this->assertArrayHasKey(
				$status,
				$constants,
				"ServiceOrder missing status constant: {$status}"
			);
		}
	}

	/**
	 * Test VendorProfile has tier constants.
	 */
	public function test_vendor_profile_has_tier_constants(): void {
		$class = 'WPSellServices\\Models\\VendorProfile';

		if ( ! class_exists( $class ) ) {
			$this->markTestSkipped( 'VendorProfile class not found' );
		}

		$required_tiers = [
			'TIER_NEW',
			'TIER_RISING',
			'TIER_TOP_RATED',
			'TIER_PRO',
		];

		$reflection = new ReflectionClass( $class );
		$constants  = $reflection->getConstants();

		foreach ( $required_tiers as $tier ) {
			$this->assertArrayHasKey(
				$tier,
				$constants,
				"VendorProfile missing tier constant: {$tier}"
			);
		}
	}

	/**
	 * Test models with timestamps have DateTimeImmutable properties.
	 */
	public function test_timestamp_properties_use_datetime_immutable(): void {
		$timestamp_props = [ 'created_at', 'updated_at', 'completed_at', 'started_at' ];

		foreach ( $this->model_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$reflection = new ReflectionClass( $class );
			$short_name = $reflection->getShortName();

			foreach ( $timestamp_props as $prop_name ) {
				if ( ! $reflection->hasProperty( $prop_name ) ) {
					continue;
				}

				$property = $reflection->getProperty( $prop_name );
				$type     = $property->getType();

				if ( $type ) {
					$type_name = $type->getName();
					$this->assertTrue(
						$type_name === 'DateTimeImmutable' || $type->allowsNull(),
						"Model {$short_name}::\${$prop_name} should be DateTimeImmutable|null"
					);
				}
			}
		}
	}
}
