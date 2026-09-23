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
use WPSellServices\Admin\Metaboxes\ServiceMetabox;

class PublishRuleTest extends TestCase {

	/** @var int[] */
	private array $created = array();

	protected function tear_down(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created = array();
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
				'post_status'  => $status,
				'post_author'  => 1,
			)
		);

		$this->assertIsInt( $id );
		$this->created[] = $id;

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

		$metabox = new ServiceMetabox();

		$metabox->remember_status_before_save( $id );
		wp_update_post( array( 'ID' => $id, 'post_status' => $submitted ) );
		$metabox->enforce_publish_rules( $id, get_post( $id ) );

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
	 * A complete service publishes normally - the rule is not a blanket block.
	 */
	public function test_a_complete_service_publishes(): void {
		$id = $this->incomplete_service( 'draft' );

		wp_update_post( array( 'ID' => $id, 'post_content' => str_repeat( 'Long enough. ', 20 ) ) );
		$term = wp_insert_term( 'Publish rule cat ' . wp_generate_password( 6, false ), 'wpss_service_category' );
		if ( ! is_wp_error( $term ) ) {
			wp_set_object_terms( $id, $term['term_id'], 'wpss_service_category' );
		}
		update_post_meta( $id, '_wpss_packages', array( array( 'name' => 'Basic', 'price' => 20, 'delivery_days' => 2, 'enabled' => true ) ) );
		update_post_meta( $id, '_thumbnail_id', 1 );

		$this->assertSame( 'publish', $this->editor_save( $id, 'publish' ) );
	}
}
