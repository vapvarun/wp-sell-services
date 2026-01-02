<?php
/**
 * Service Creation Integration Tests.
 *
 * Tests all variations of service creation:
 * - Simple service
 * - With requirements
 * - Single plan
 * - Multiple plans
 * - With addons
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;
use WPSellServices\Tests\Factories\ServiceFactory;
use WPSellServices\Tests\Factories\UserFactory;
use WPSellServices\Models\Service;
use WPSellServices\Services\ServiceManager;

/**
 * Test service creation scenarios.
 */
class ServiceCreationTest extends TestCase {

	/**
	 * Service manager instance.
	 *
	 * @var ServiceManager|null
	 */
	private ?ServiceManager $service_manager = null;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		ServiceFactory::reset();
		UserFactory::reset();

		// Only instantiate ServiceManager when WordPress is available.
		global $wpdb;
		if ( isset( $wpdb ) && $wpdb instanceof \wpdb && class_exists( ServiceManager::class ) ) {
			$this->service_manager = new ServiceManager();
		}
	}

	/**
	 * Test creating a simple service with title, description, single package.
	 *
	 * @return void
	 */
	public function test_create_simple_service(): void {
		$service_data = ServiceFactory::simple();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			// Standalone mode - verify data structure.
			$this->assertArrayHasKey( 'title', $service_data );
			$this->assertArrayHasKey( 'content', $service_data );
			$this->assertArrayHasKey( 'packages', $service_data );
			$this->assertCount( 1, $service_data['packages'] );

			$package = $service_data['packages'][0];
			$this->assertEquals( 'Basic', $package['name'] );
			$this->assertEquals( 49.99, $package['price'] );
			$this->assertEquals( 3, $package['delivery_days'] );
		} else {
			// Integration mode - verify Service object.
			$this->assertInstanceOf( Service::class, $service_data );
			$this->assertNotEmpty( $service_data->title );
			$this->assertNotEmpty( $service_data->packages );
		}
	}

	/**
	 * Test creating a service with buyer requirements.
	 *
	 * @return void
	 */
	public function test_create_service_with_requirements(): void {
		$service_data = ServiceFactory::with_requirements();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			$this->assertArrayHasKey( 'requirements', $service_data );
			$this->assertCount( 3, $service_data['requirements'] );

			// Verify requirement types.
			$types = array_column( $service_data['requirements'], 'field_type' );
			$this->assertContains( 'text', $types );
			$this->assertContains( 'select', $types );
			$this->assertContains( 'file', $types );

			// Verify required flags.
			$required_count = count(
				array_filter(
					$service_data['requirements'],
					fn( $r ) => $r['is_required'] === true
				)
			);
			$this->assertEquals( 2, $required_count );
		}
	}

	/**
	 * Test creating a service with a single pricing plan.
	 *
	 * @return void
	 */
	public function test_create_single_plan_service(): void {
		$service_data = ServiceFactory::single_plan();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			$this->assertArrayHasKey( 'packages', $service_data );
			$this->assertCount( 1, $service_data['packages'] );

			$package = $service_data['packages'][0];
			$this->assertEquals( 'Complete Package', $package['name'] );
			$this->assertEquals( 149.99, $package['price'] );
			$this->assertNotEmpty( $package['features'] );
		}
	}

	/**
	 * Test creating a service with multiple pricing plans (Basic/Standard/Premium).
	 *
	 * @return void
	 */
	public function test_create_multi_plan_service(): void {
		$service_data = ServiceFactory::multi_plan();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			$this->assertArrayHasKey( 'packages', $service_data );
			$this->assertCount( 3, $service_data['packages'] );

			$names = array_column( $service_data['packages'], 'name' );
			$this->assertEquals( array( 'Basic', 'Standard', 'Premium' ), $names );

			// Verify prices are ascending.
			$prices = array_column( $service_data['packages'], 'price' );
			$this->assertEquals( array( 29.99, 59.99, 99.99 ), $prices );

			// Verify delivery days are descending (faster for higher tiers).
			$delivery = array_column( $service_data['packages'], 'delivery_days' );
			$this->assertEquals( array( 5, 3, 1 ), $delivery );

			// Verify Premium has unlimited revisions.
			$premium = $service_data['packages'][2];
			$this->assertEquals( -1, $premium['revisions'] );
		}
	}

	/**
	 * Test creating a service with add-ons/extras.
	 *
	 * @return void
	 */
	public function test_create_service_with_addons(): void {
		$service_data = ServiceFactory::with_addons();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			$this->assertArrayHasKey( 'addons', $service_data );
			$this->assertCount( 3, $service_data['addons'] );

			$names = array_column( $service_data['addons'], 'name' );
			$this->assertContains( 'Rush Delivery', $names );
			$this->assertContains( 'Extra Revisions', $names );
			$this->assertContains( 'Source Files', $names );

			// Verify Rush Delivery reduces delivery time.
			$rush = array_filter(
				$service_data['addons'],
				fn( $a ) => $a['name'] === 'Rush Delivery'
			);
			$rush = array_values( $rush )[0];
			$this->assertEquals( -2, $rush['extra_days'] );
		}
	}

	/**
	 * Test creating a complete service with all features.
	 *
	 * @return void
	 */
	public function test_create_complete_service(): void {
		$service_data = ServiceFactory::complete();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			// Has multiple packages.
			$this->assertArrayHasKey( 'packages', $service_data );
			$this->assertCount( 3, $service_data['packages'] );

			// Has addons.
			$this->assertArrayHasKey( 'addons', $service_data );
			$this->assertGreaterThan( 0, count( $service_data['addons'] ) );

			// Has requirements.
			$this->assertArrayHasKey( 'requirements', $service_data );
			$this->assertGreaterThan( 0, count( $service_data['requirements'] ) );

			// Has FAQs.
			$this->assertArrayHasKey( 'faqs', $service_data );
			$this->assertGreaterThan( 0, count( $service_data['faqs'] ) );
		}
	}

	/**
	 * Test service validation fails without title.
	 *
	 * @return void
	 */
	public function test_service_validation_fails_without_title(): void {
		if ( ! $this->service_manager ) {
			$this->markTestSkipped( 'ServiceManager not available.' );
		}

		$result = $this->service_manager->create(
			array(
				'title'   => '', // Empty title.
				'content' => 'Description without title.',
			)
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test that service gets starting price from lowest package.
	 *
	 * @return void
	 */
	public function test_service_starting_price_calculated(): void {
		$service_data = ServiceFactory::multi_plan();

		if ( is_array( $service_data ) ) {
			// Find minimum price.
			$min_price = min( array_column( $service_data['packages'], 'price' ) );
			$this->assertEquals( 29.99, $min_price );
		} elseif ( $service_data instanceof Service ) {
			$starting_price = $service_data->get_starting_price();
			$this->assertEquals( 29.99, $starting_price );
		}
	}

	/**
	 * Test that service gets fastest delivery from packages.
	 *
	 * @return void
	 */
	public function test_service_fastest_delivery_calculated(): void {
		$service_data = ServiceFactory::multi_plan();

		if ( is_array( $service_data ) ) {
			// Find minimum delivery days.
			$min_days = min( array_column( $service_data['packages'], 'delivery_days' ) );
			$this->assertEquals( 1, $min_days );
		} elseif ( $service_data instanceof Service ) {
			$fastest = $service_data->get_fastest_delivery();
			$this->assertEquals( 1, $fastest );
		}
	}

	/**
	 * Test creating a draft service.
	 *
	 * @return void
	 */
	public function test_create_draft_service(): void {
		$service_data = ServiceFactory::draft();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			$this->assertEquals( 'draft', $service_data['status'] );
		} elseif ( $service_data instanceof Service ) {
			$this->assertEquals( 'draft', $service_data->status );
		}
	}

	/**
	 * Test creating a pending service (for moderation).
	 *
	 * @return void
	 */
	public function test_create_pending_service(): void {
		$service_data = ServiceFactory::pending();

		$this->assertNotEmpty( $service_data );

		if ( is_array( $service_data ) ) {
			$this->assertEquals( 'pending', $service_data['status'] );
		} elseif ( $service_data instanceof Service ) {
			$this->assertEquals( 'pending', $service_data->status );
		}
	}

	/**
	 * Test package data structure.
	 *
	 * @return void
	 */
	public function test_package_data_structure(): void {
		$package = ServiceFactory::package_data(
			'Test Package',
			99.99,
			5,
			3,
			array( 'Feature A', 'Feature B' )
		);

		$this->assertArrayHasKey( 'name', $package );
		$this->assertArrayHasKey( 'price', $package );
		$this->assertArrayHasKey( 'delivery_days', $package );
		$this->assertArrayHasKey( 'revisions', $package );
		$this->assertArrayHasKey( 'features', $package );
		$this->assertArrayHasKey( 'is_active', $package );

		$this->assertEquals( 'Test Package', $package['name'] );
		$this->assertEquals( 99.99, $package['price'] );
		$this->assertEquals( 5, $package['delivery_days'] );
		$this->assertEquals( 3, $package['revisions'] );
		$this->assertEquals( array( 'Feature A', 'Feature B' ), $package['features'] );
		$this->assertTrue( $package['is_active'] );
	}

	/**
	 * Test addon price calculation.
	 *
	 * @return void
	 */
	public function test_addon_price_calculation(): void {
		$service_data = ServiceFactory::with_addons();

		if ( is_array( $service_data ) ) {
			$extra_revisions = array_filter(
				$service_data['addons'],
				fn( $a ) => $a['name'] === 'Extra Revisions'
			);
			$extra_revisions = array_values( $extra_revisions )[0];

			// Price is $19.99, max quantity is 3.
			$this->assertEquals( 19.99, $extra_revisions['price'] );
			$this->assertEquals( 3, $extra_revisions['max_quantity'] );

			// Calculate total for 2 units.
			$total = $extra_revisions['price'] * 2;
			$this->assertEquals( 39.98, $total );
		}
	}
}
