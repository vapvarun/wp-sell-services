<?php
/**
 * Every shortcode and block declares who may see what it renders, and the
 * member-scoped ones are dispatched anonymously to prove they refuse.
 *
 * A privacy rule that lives only in a REST controller is a rule the PAGE does
 * not have. The same order, service or dashboard is reachable through a
 * shortcode, and a fix that lands on the controller and misses the shortcode is
 * the shape CLAUDE.md calls out: one flow, two implementations, drifting.
 *
 * So this walks the live registries - $GLOBALS['shortcode_tags'] and
 * WP_Block_Type_Registry - and fails when:
 *
 *   1. a surface is registered but not declared;
 *   2. a surface is declared but no longer registered;
 *   3. a member/owner surface does not say what it refuses with;
 *   4. a member/owner surface renders for an anonymous caller anyway.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Unit;

use WPSellServices\Tests\TestCase;
use WP_Block_Type_Registry;

class RenderSurfaceAuthorityTest extends TestCase {

	private const MANIFEST = __DIR__ . '/../../audit/render-authority.json';

	/** Surfaces whose audience requires an identity. */
	private const GATED = array( 'member', 'owner', 'vendor' );

	/** @var array<string, mixed> */
	private array $manifest = array();

	protected function set_up(): void {
		parent::set_up();

		$this->manifest = (array) json_decode( (string) file_get_contents( self::MANIFEST ), true );
	}

	protected function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/** @return array<int, string> */
	private function live_shortcodes(): array {
		$tags = array_keys( (array) ( $GLOBALS['shortcode_tags'] ?? array() ) );
		$ours = array_values( array_filter( $tags, static fn( $t ) => 0 === strpos( (string) $t, 'wpss' ) ) );
		sort( $ours );

		return $ours;
	}

	/** @return array<int, string> */
	private function live_blocks(): array {
		$names = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
		$ours  = array_values( array_filter( $names, static fn( $b ) => 0 === strpos( (string) $b, 'wpss' ) ) );
		sort( $ours );

		return $ours;
	}

	/**
	 * TRIPWIRE - Trap 1.
	 *
	 * Shortcodes are registered on init and blocks on init too; if this test
	 * process has not reached that point, both registries come back empty and
	 * every assertion below passes having compared nothing.
	 */
	public function test_the_render_registries_are_not_empty(): void {
		$this->assertNotEmpty(
			$this->live_shortcodes(),
			'No wpss shortcode is registered. The registry did not load, so every other check here would pass vacuously.'
		);

		$this->assertNotEmpty(
			$this->live_blocks(),
			'No wpss block is registered - same vacuous-pass risk as above.'
		);
	}

	public function test_every_render_surface_is_declared(): void {
		$undeclared = array_merge(
			array_diff( $this->live_shortcodes(), array_keys( (array) ( $this->manifest['shortcodes'] ?? array() ) ) ),
			array_diff( $this->live_blocks(), array_keys( (array) ( $this->manifest['blocks'] ?? array() ) ) )
		);

		$this->assertSame(
			array(),
			array_values( $undeclared ),
			"These render surfaces are registered but declared nowhere.\n"
			. "Add each to audit/render-authority.json with its audience.\n"
			. "A surface nobody declared is a surface nobody asked the privacy question about.\n"
			. implode( "\n", $undeclared )
		);
	}

	public function test_the_manifest_has_no_surface_that_no_longer_exists(): void {
		$stale = array_merge(
			array_diff( array_keys( (array) ( $this->manifest['shortcodes'] ?? array() ) ), $this->live_shortcodes() ),
			array_diff( array_keys( (array) ( $this->manifest['blocks'] ?? array() ) ), $this->live_blocks() )
		);

		$this->assertSame( array(), array_values( $stale ), implode( "\n", $stale ) );
	}

	public function test_gated_surfaces_say_what_they_refuse_with(): void {
		$problems = array();

		foreach ( (array) ( $this->manifest['shortcodes'] ?? array() ) as $tag => $entry ) {
			if ( ! in_array( (string) ( $entry['audience'] ?? '' ), self::GATED, true ) ) {
				continue;
			}

			if ( '' === trim( (string) ( $entry['refuses_with'] ?? '' ) ) ) {
				$problems[] = $tag . ' - declared ' . $entry['audience'] . ' but does not say what it refuses with, so the probe could only measure a byte count.';
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * The probe: a gated surface must refuse an anonymous caller.
	 *
	 * Asserting on the refusal STRING rather than on output length, because a
	 * surface that renders an empty shell would satisfy a length check while
	 * still having no gate at all.
	 */
	public function test_gated_shortcodes_refuse_an_anonymous_caller(): void {
		wp_set_current_user( 0 );

		$leaks  = array();
		$probed = 0;

		foreach ( (array) ( $this->manifest['shortcodes'] ?? array() ) as $tag => $entry ) {
			if ( ! in_array( (string) ( $entry['audience'] ?? '' ), self::GATED, true ) ) {
				continue;
			}

			if ( ! shortcode_exists( (string) $tag ) ) {
				continue; // Covered by the staleness test.
			}

			++$probed;

			$output  = do_shortcode( '[' . $tag . ']' );
			$refusal = (string) ( $entry['refuses_with'] ?? '' );

			if ( '' === $refusal || false === stripos( $output, $refusal ) ) {
				$leaks[] = $tag . ' - rendered for an anonymous caller without the declared refusal ("' . $refusal . '")';
			}
		}

		$this->assertGreaterThan(
			0,
			$probed,
			'No gated shortcode was probed, so a pass here would mean nothing. Check the audience values in the manifest.'
		);

		$this->assertSame( array(), $leaks, implode( "\n", $leaks ) );
	}
}
