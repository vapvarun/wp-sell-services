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

	/** @var int[] Services created directly through the manager in a test. */
	private array $created = array();

	protected function set_up(): void {
		parent::set_up();

		ServiceFactory::reset();
		UserFactory::reset();

		$this->service_manager = new ServiceManager();
	}

	/**
	 * Delete the services this test published.
	 *
	 * Without this the rows survive the run. The suite falls back to the LIVE
	 * Local site when the WordPress test library is absent, so every run left
	 * more published listings behind - 304 of them by the time a browser smoke
	 * walked the catalogue, found one, and bought it.
	 */
	protected function tear_down(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created = array();

		ServiceFactory::cleanup();

		parent::tear_down();
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

	/**
	 * An unpublishable service is held as a draft, never published.
	 *
	 * The wizard, the admin metabox and REST all call
	 * wpss_validate_service_publishable() before letting a service go live.
	 * ServiceManager - the documented programmatic entry point - did not, so
	 * create( [ 'status' => 'publish' ] ) put a live, purchasable listing with
	 * no category and no description into the catalogue (Basecamp 10330733407).
	 *
	 * Asserts the STORED post_status, because that is what a buyer sees.
	 */
	public function test_an_unpublishable_service_is_held_as_a_draft(): void {
		$id = $this->service_manager->create(
			array(
				'title'      => 'Held back probe service',
				'content'    => 'Too short to publish.',
				'categories' => array(),
				'status'     => 'publish',
			)
		);

		$this->assertIsInt( $id, 'The service should still be created - held back, not refused.' );
		$this->created[] = $id;

		$this->assertSame(
			'draft',
			get_post_status( $id ),
			'A service missing its category and with a 21-character description must not be published.'
		);
	}

	/**
	 * A service that passes the checklist still publishes.
	 *
	 * The other half: holding everything back would be its own bug.
	 */
	public function test_a_publishable_service_still_publishes(): void {
		$id = $this->service_manager->create(
			array(
				'title'      => 'Ready probe service',
				'content'    => str_repeat( 'Long enough description. ', 12 ),
				'categories' => array( 1 ),
				'packages'   => array( ServiceFactory::package_data( 'Basic', 25.00, 3, 1 ) ),
				'status'     => 'publish',
			)
		);

		$this->assertIsInt( $id );
		$this->created[] = $id;

		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	/**
	 * A seeder can opt out deliberately, and only deliberately.
	 */
	public function test_the_filter_can_allow_an_unpublishable_service_through(): void {
		add_filter( 'wpss_service_manager_enforce_publishable', '__return_false' );

		$id = $this->service_manager->create(
			array(
				'title'      => 'Seeded probe service',
				'content'    => 'Short.',
				'categories' => array(),
				'status'     => 'publish',
			)
		);

		$this->assertIsInt( $id );
		$this->created[] = $id;

		$this->assertSame( 'publish', get_post_status( $id ), 'The filter must be able to allow a seeder through.' );
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
