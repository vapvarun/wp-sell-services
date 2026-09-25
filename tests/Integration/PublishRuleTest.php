<?php
/**
 * A live listing is the owner's to take down, not the plugin's.
 *
 * enforce_publish_rules() used to demote ANY published service failing the
 * checklist, on every save. The reasoning was defensible - one rule, always
 * applied, keeps incomplete listings away from buyers - but the cost landed on
 * the wrong person. The checklist is enforced at save time, not retroactively,
 * so a site running since before a rule existed holds services that are live,
 * selling and non-compliant. The owner opens one to fix a typo, presses Save,
 * and the listing leaves the storefront with no warning (Basecamp 10330918067).
 *
 * The rule now only blocks something GOING live incomplete, which is what it
 * was for. Two cases, and both matter:
 *
 *   - already live + incomplete + saved -> stays live, banner says what is
 *     missing, owner decides when to fix it;
 *   - draft + incomplete + published    -> held back, as before.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

class PublishRuleTest extends TestCase {

	/** @var int[] */
	private array $created = array();

	/** @var int[] */
	private array $terms = array();

	protected function set_up(): void {
		parent::set_up();

		// The post type's REST route only exists once rest_api_init has fired.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		do_action( 'rest_api_init' );
	}

	protected function tear_down(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created = array();
		foreach ( $this->terms as $term_id ) {
			wp_delete_term( $term_id, 'wpss_service_category' );
		}
		$this->terms = array();
		unset( $_POST['wpss_service_nonce'] );

		parent::tear_down();
	}

	/**
	 * Create an INCOMPLETE service: no category, short description, no image.
	 *
	 * @param  string $status Starting status.
	 * @return int
	 */
	private function incomplete_service( string $status ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => 'wpss_service',
				'post_title'   => 'Publish rule fixture',
				'post_content' => 'Too short.',
				'post_status'  => 'draft',
				'post_author'  => 1,
			)
		);

		$this->assertIsInt( $id );
		$this->created[] = $id;

		if ( 'publish' === $status ) {
			/*
			 * Set the status in the row directly, not through wp_update_post().
			 *
			 * The fixture this test needs is a LEGACY live service: one
			 * published before the checklist existed, which is the whole reason
			 * the "already live stays live" rule is there. Publishing it through
			 * the normal path would - correctly - be caught as "going live
			 * incomplete" and held back, so the fixture could never represent
			 * the case it exists for.
			 *
			 * Writing the row is exactly what such a service looks like on a
			 * site that upgraded into the rule.
			 */
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- modelling a pre-existing row; the hooks are the thing under test.
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}

		return $id;
	}

	/**
	 * Run an editor save the way wp-admin does: remember, write, enforce.
	 *
	 * @param  int    $id       Service.
	 * @param  string $submitted Status the editor submitted.
	 * @return string Stored status afterwards.
	 */
	private function editor_save( int $id, string $submitted ): string {
		$_POST['wpss_service_nonce'] = wp_create_nonce( 'wpss_service_meta' );
		wp_set_current_user( 1 );

		/*
		 * Drive the REAL hooks, never the handler by hand.
		 *
		 * This used to `new ServiceMetabox()` and call
		 * remember_status_before_save()/enforce_publish_rules() directly. That
		 * constructed an instance whose registration then armed the guard as a
		 * SIDE EFFECT of running the test - so the REST test that checks the
		 * guard is armed was satisfied by this fixture rather than by the
		 * plugin's own wiring, and a mutation removing that wiring did not turn
		 * anything red. The tests were testing the test.
		 *
		 * wp_update_post() fires pre_post_update and save_post, which is what a
		 * real editor save does; if the plugin has not registered its guards,
		 * nothing runs and the assertion fails, which is the point.
		 */
		wp_update_post( array( 'ID' => $id, 'post_status' => $submitted ) );

		clean_post_cache( $id );

		return (string) get_post_status( $id );
	}

	/**
	 * The regression: a live service survives an unrelated edit.
	 */
	public function test_an_already_live_service_stays_live_when_saved(): void {
		$id = $this->incomplete_service( 'publish' );

		$this->assertSame(
			'publish',
			$this->editor_save( $id, 'publish' ),
			'A service that was already live must not be taken off the storefront by saving it. '
			. 'The owner edits a price or a typo; losing the listing is not an acceptable side effect.'
		);
	}

	/**
	 * The protection that must survive the change.
	 */
	public function test_a_draft_going_live_incomplete_is_still_held_back(): void {
		$id = $this->incomplete_service( 'draft' );

		$this->assertSame(
			'draft',
			$this->editor_save( $id, 'publish' ),
			'An incomplete service must not be able to go live for the first time.'
		);
	}

	/**
	 * The block editor's own route, which is where this rule was unreachable.
	 *
	 * enforce_publish_rules() was registered from ServiceMetabox::init(), which
	 * Plugin::define_admin_hooks() only reaches when is_admin() - and is_admin()
	 * is FALSE during a REST request. The block editor publishes over
	 * /wp/v2/wpss-services/<id>, so the class was never constructed on the path
	 * most owners use and an incomplete service published with a 200.
	 *
	 * The classic-path tests above passed throughout, which is exactly why this
	 * one has to exist: they exercised the only path that was ever guarded.
	 */
	public function test_an_incomplete_service_cannot_be_published_over_rest(): void {
		/*
		 * Assert the gate is ARMED before trusting what it reports.
		 *
		 * Without this the test passed with the guard not registered at all:
		 * something else in the stack left the post a draft, and "still a draft"
		 * read as success. A guard that has never been seen to be the thing
		 * doing the work is not a guard.
		 */
		$this->assertNotFalse(
			has_action( 'save_post_wpss_service', array( 'WPSellServices\\Admin\\Metaboxes\\ServiceMetabox', 'enforce_publish_rules' ) )
			|| $this->publish_guard_is_registered(),
			'enforce_publish_rules is not hooked on save_post_wpss_service, so this test would prove nothing.'
		);

		$id = $this->incomplete_service( 'draft' );

		wp_set_current_user( 1 );

		$request = new \WP_REST_Request( 'POST', '/wp/v2/wpss-services/' . $id );
		$request->set_param( 'status', 'publish' );
		$response = rest_do_request( $request );

		/*
		 * TRIPWIRE, and it caught this test being worthless.
		 *
		 * Without it, a 404 from an unregistered route leaves the post a draft
		 * and the assertion below passes having proved nothing - which is
		 * exactly what happened the first time this was written. The request has
		 * to SUCCEED for "and yet it is still a draft" to mean anything.
		 */
		$this->assertSame(
			200,
			$response->get_status(),
			'The REST publish did not even reach the post type (got ' . $response->get_status() . '). '
			. 'A failed request leaves the status untouched, so the assertion below would pass vacuously.'
		);

		$this->assertSame(
			'draft',
			get_post_status( $id ),
			'The block editor publishes over REST. An incomplete service must be held back there too, '
			. 'not only on a classic form submit.'
		);
	}

	/**
	 * Is enforce_publish_rules() hooked, on any instance?
	 *
	 * has_action() with a class name does not match a callable bound to an
	 * object, and the guard is registered from an instance, so the registry is
	 * walked directly.
	 *
	 * @return bool
	 */
	private function publish_guard_is_registered(): bool {
		$hook = $GLOBALS['wp_filter']['save_post_wpss_service'] ?? null;

		if ( ! $hook ) {
			return false;
		}

		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = $callback['function'] ?? null;

				if ( is_array( $fn ) && is_object( $fn[0] ) && 'enforce_publish_rules' === ( $fn[1] ?? '' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * A complete service publishes normally - the rule is not a blanket block.
	 */
	public function test_a_complete_service_publishes(): void {
		$id = $this->incomplete_service( 'draft' );

		wp_update_post( array( 'ID' => $id, 'post_content' => str_repeat( 'Long enough. ', 20 ) ) );
		$term = wp_insert_term( 'Publish rule cat ' . wp_generate_password( 6, false ), 'wpss_service_category' );
		if ( ! is_wp_error( $term ) ) {
			$this->terms[] = (int) $term['term_id'];
			wp_set_object_terms( $id, $term['term_id'], 'wpss_service_category' );
		}
		update_post_meta( $id, '_wpss_packages', array( array( 'name' => 'Basic', 'price' => 20, 'delivery_days' => 2, 'enabled' => true ) ) );
		update_post_meta( $id, '_thumbnail_id', 1 );

		$this->assertSame( 'publish', $this->editor_save( $id, 'publish' ) );
	}
}
