<?php
/**
 * Service Creation Integration Tests.
 *
 * Every assertion here runs against the Service model ServiceManager
 * actually persisted and read back, not against the array the factory was
 * handed. The earlier version wrapped every assertion in
 * `if ( is_array( $service_data ) )`, a branch for a "standalone mode (no
 * WordPress)" that cannot occur under WP_UnitTestCase - so eleven tests
 * passed while asserting nothing about the product, and
 * test_addon_price_calculation was flagged risky because its whole body was
 * skipped. Asserting on the round trip is the only version of this file that
 * can fail when service creation breaks.
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

	private ?ServiceManager $service_manager = null;

	protected function set_up(): void {
		parent::set_up();

		ServiceFactory::reset();
		UserFactory::reset();

		$this->service_manager = new ServiceManager();
	}

	/**
	 * The factory falls back to returning its input array when
	 * ServiceManager::create() throws. That fallback silently turns a
	 * persistence failure into a skipped test, so every test asserts the
	 * round trip produced a real model first.
	 *
	 * @param  Service|array $subject What the factory returned.
	 * @return Service
	 */
	private function persisted( $subject ): Service {
		$this->assertInstanceOf(
			Service::class,
			$subject,
			'ServiceFactory fell back to its input array, which means ServiceManager::create() failed.'
		);

		return $subject;
	}

	public function test_create_simple_service(): void {
		$service = $this->persisted( ServiceFactory::simple() );

		$this->assertNotEmpty( $service->title );
		$this->assertNotEmpty( $service->description );
		$this->assertCount( 1, $service->packages );

		$package = $service->packages[0];
		$this->assertSame( 'Basic', $package->name );
		$this->assertEquals( 49.99, $package->price );
		$this->assertSame( 3, $package->delivery_days );
	}

	public function test_create_service_with_requirements(): void {
		$service = $this->persisted( ServiceFactory::with_requirements() );

		$this->assertCount( 3, $service->requirements );

		// wpss_normalize_service_requirements() is the one schema every
		// reader sees: field_type/is_required are input aliases, the stored
		// row is type/required.
		$types = array_column( $service->requirements, 'type' );
		$this->assertContains( 'text', $types );
		$this->assertContains( 'select', $types );
		$this->assertContains( 'file', $types );

		$required = array_filter(
			$service->requirements,
			fn( $r ) => ! empty( $r['required'] )
		);
		$this->assertCount( 2, $required );
	}

	public function test_create_single_plan_service(): void {
		$service = $this->persisted( ServiceFactory::single_plan() );

		$this->assertCount( 1, $service->packages );

		$package = $service->packages[0];
		$this->assertSame( 'Complete Package', $package->name );
		$this->assertEquals( 149.99, $package->price );
		$this->assertNotEmpty( $package->features );
	}

	public function test_create_multi_plan_service(): void {
		$service = $this->persisted( ServiceFactory::multi_plan() );

		$this->assertCount( 3, $service->packages );

		$this->assertSame(
			array( 'Basic', 'Standard', 'Premium' ),
			array_map( fn( $p ) => $p->name, $service->packages )
		);
		$this->assertEqualsWithDelta(
			array( 29.99, 59.99, 99.99 ),
			array_map( fn( $p ) => (float) $p->price, $service->packages ),
			0.001
		);
		$this->assertSame(
			array( 5, 3, 1 ),
			array_map( fn( $p ) => (int) $p->delivery_days, $service->packages )
		);

		// Premium offers unlimited revisions, stored as -1.
		$this->assertSame( -1, (int) $service->packages[2]->revisions );
	}

	public function test_create_service_with_addons(): void {
		$service = $this->persisted( ServiceFactory::with_addons() );

		$this->assertCount( 3, $service->addons );

		$names = array_column( $service->addons, 'title' );
		$this->assertContains( 'Rush Delivery', $names );
		$this->assertContains( 'Extra Revisions', $names );
		$this->assertContains( 'Source Files', $names );

		$rush = $this->addon_named( $service, 'Rush Delivery' );
		$this->assertSame( 2, (int) $rush['delivery_days_extra'] );
	}

	public function test_create_complete_service(): void {
		$service = $this->persisted( ServiceFactory::complete() );

		$this->assertCount( 3, $service->packages );
		$this->assertNotEmpty( $service->addons );
		$this->assertNotEmpty( $service->requirements );
		$this->assertNotEmpty( $service->faqs );
	}

	public function test_service_validation_fails_without_title(): void {
		$result = $this->service_manager->create(
			array(
				'title'   => '',
				'content' => 'Description without title.',
			)
		);

		$this->assertFalse( $result );
	}

	public function test_service_starting_price_calculated(): void {
		$service = $this->persisted( ServiceFactory::multi_plan() );

		$this->assertEqualsWithDelta( 29.99, $service->get_starting_price(), 0.001 );
	}

	public function test_service_fastest_delivery_calculated(): void {
		$service = $this->persisted( ServiceFactory::multi_plan() );

		$this->assertSame( 1, $service->get_fastest_delivery() );
	}

	public function test_create_draft_service(): void {
		$service = $this->persisted( ServiceFactory::draft() );

		$this->assertSame( 'draft', $service->status );
	}

	public function test_create_pending_service(): void {
		$service = $this->persisted( ServiceFactory::pending() );

		$this->assertSame( 'pending', $service->status );
	}

	public function test_package_data_structure(): void {
		$package = ServiceFactory::package_data(
			'Test Package',
			99.99,
			5,
			3,
			array( 'Feature A', 'Feature B' )
		);

		$this->assertSame( 'Test Package', $package['name'] );
		$this->assertEquals( 99.99, $package['price'] );
		$this->assertSame( 5, $package['delivery_days'] );
		$this->assertSame( 3, $package['revisions'] );
		$this->assertSame( array( 'Feature A', 'Feature B' ), $package['features'] );
		$this->assertTrue( $package['is_active'] );
	}

	public function test_addon_price_calculation(): void {
		$service = $this->persisted( ServiceFactory::with_addons() );

		$extra_revisions = $this->addon_named( $service, 'Extra Revisions' );

		$this->assertEqualsWithDelta( 19.99, (float) $extra_revisions['price'], 0.001 );
		$this->assertSame( 3, (int) $extra_revisions['max_quantity'] );
		$this->assertEqualsWithDelta( 39.98, (float) $extra_revisions['price'] * 2, 0.001 );
	}

	/**
	 * @param  Service $service Persisted service.
	 * @param  string  $name    Add-on name.
	 * @return array<string, mixed>
	 */
	private function addon_named( Service $service, string $name ): array {
		foreach ( $service->addons as $addon ) {
			if ( ( $addon['title'] ?? '' ) === $name ) {
				return $addon;
			}
		}

		$this->fail( 'Add-on "' . $name . '" did not survive the round trip through ServiceManager.' );
	}
}
