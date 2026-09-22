<?php
/**
 * Selecting only the FIRST add-on must reach the charge.
 *
 * Add-on ids are 0-based indices into _wpss_addons, so a buyer who ticks only
 * the first add-on submits the string "0" - which PHP evaluates as falsy. Two
 * display sites and, worse, the canonical money resolver all guarded on plain
 * truthiness, so that selection was silently dropped: the buyer saw the add-on
 * in the UI, was billed without it, and the vendor lost the revenue.
 *
 * It only ever broke for the first add-on selected ALONE. "1" worked. "0,1"
 * worked. Every test and every manual check that happened to use a second
 * add-on passed. A browser smoke found it by buying one.
 *
 * So the case this file exists for is the single index "0", and the assertion
 * is on the resolved MONEY, not on whether a function was called.
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;
use WPSellServices\Tests\Factories\ServiceFactory;

class CheckoutAddonResolutionTest extends TestCase {

	/** @var int */
	private int $service_id = 0;

	protected function set_up(): void {
		parent::set_up();

		ServiceFactory::reset();

		$service = ServiceFactory::with_addons();

		// Rush Delivery 49.99, Extra Revisions 19.99, Source Files 29.99.
		$this->service_id = $service instanceof \WPSellServices\Models\Service ? (int) $service->id : 0;

		$this->assertGreaterThan( 0, $this->service_id, 'Fixture service was not persisted.' );
		$this->assertCount( 3, wpss_get_service_extras( $this->service_id ), 'Fixture add-ons did not round-trip.' );
	}

	protected function tear_down(): void {
		ServiceFactory::cleanup();

		parent::tear_down();
	}

	/**
	 * @return array<string, array{0: string, 1: float, 2: int}>
	 */
	public static function selection_provider(): array {
		return array(
			// The regression: the first add-on, alone.
			'first add-on alone ("0")' => array( '0', 49.99, 1 ),
			'second alone ("1")'       => array( '1', 19.99, 1 ),
			'third alone ("2")'        => array( '2', 29.99, 1 ),
			'first and second ("0,1")' => array( '0,1', 69.98, 2 ),
			'all three ("0,1,2")'      => array( '0,1,2', 99.97, 3 ),
			'none ("")'                => array( '', 0.0, 0 ),
		);
	}

	/**
	 * @dataProvider selection_provider
	 *
	 * @param string $addon_ids Comma-separated indices, as the checkout submits them.
	 * @param float  $expected  Expected add-on total.
	 * @param int    $count     Expected number of resolved add-ons.
	 */
	public function test_the_resolver_prices_every_selection( string $addon_ids, float $expected, int $count ): void {
		$resolved = wpss_resolve_checkout_addons( $this->service_id, $addon_ids );

		$this->assertCount(
			$count,
			$resolved['addons'],
			'Selection "' . $addon_ids . '" resolved ' . count( $resolved['addons'] ) . ' add-on(s), expected ' . $count . '.'
		);

		$this->assertEqualsWithDelta(
			$expected,
			$resolved['addons_total'],
			0.001,
			'Selection "' . $addon_ids . '" priced at ' . $resolved['addons_total'] . ', expected ' . $expected
			. '. A selection that resolves to 0.00 means the buyer is charged without what they picked.'
		);
	}

	/**
	 * A negative "extra days" clamps to 0 - it never inverts.
	 *
	 * wpss_normalize_service_addons() used absint(), so an add-on entered as
	 * "deliver two days sooner" (-2) was stored as "two days later" (+2): the
	 * buyer paid extra for a worse delivery date and nothing reported it
	 * (Basecamp 10330601718). Negative is not a supported concept - the field
	 * is "Extra Delivery Days", both inputs carry min="0", and the service page
	 * renders "(+N days)" - so 0 is the honest reading, and +2 is the one
	 * answer that must never come back.
	 */
	public function test_negative_extra_days_clamp_to_zero_and_never_invert(): void {
		$rows = wpss_normalize_service_addons(
			array(
				array( 'title' => 'Rush delivery', 'price' => 25, 'delivery_days_extra' => -2 ),
				array( 'title' => 'Slow and steady', 'price' => 5, 'delivery_days_extra' => 3 ),
			)
		);

		$this->assertSame( 0, $rows[0]['delivery_days_extra'], 'A negative must clamp to 0.' );
		$this->assertNotSame( 2, $rows[0]['delivery_days_extra'], 'A negative must never be stored as its positive counterpart.' );
		$this->assertSame( 3, $rows[1]['delivery_days_extra'], 'A positive value is untouched.' );
	}

	/**
	 * The guard must reject only the empty string.
	 *
	 * Stated separately from the pricing cases because this is the exact
	 * confusion that caused the bug, and it should fail loudly if anyone
	 * reintroduces a truthiness test.
	 */
	public function test_empty_and_zero_are_not_the_same_selection(): void {
		$none  = wpss_resolve_checkout_addons( $this->service_id, '' );
		$first = wpss_resolve_checkout_addons( $this->service_id, '0' );

		$this->assertSame( array(), $none['addons'], '"" must mean no add-ons.' );
		$this->assertNotSame(
			array(),
			$first['addons'],
			'"0" must mean the FIRST add-on, not "no add-ons". PHP treats the string "0" as falsy; the guard has to compare against \'\' explicitly.'
		);
	}
}
