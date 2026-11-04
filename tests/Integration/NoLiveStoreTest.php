<?php
/**
 * The test suite never reaches the live store (tests/no-live-store.php).
 *
 * Fails if the guard stops loading: the preset activation would then run on
 * every CI install and send activations to wbcomdesigns.com.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

class NoLiveStoreTest extends TestCase {

	public function test_no_licence_call_reaches_the_live_store(): void {
		// The preset activation is switched off for the whole suite.
		$this->assertTrue( defined( 'WPSS_NO_LICENSE_PHONE_HOME' ) && WPSS_NO_LICENSE_PHONE_HOME );

		// Both store hosts are refused before any request leaves the machine.
		foreach ( array( 'https://wbcomdesigns.com/', 'https://updates.wbcomdesigns.com/' ) as $url ) {
			$response = wp_remote_get( $url );
			$this->assertInstanceOf( \WP_Error::class, $response, $url );
			$this->assertSame( 'wpss_tests_no_store', $response->get_error_code(), $url );
		}

		// The licence helper (the one path every licence call takes) therefore gets no reply.
		$this->assertFalse(
			\WPSellServices\wpss_licence_request(
				array(
					'edd_action' => 'check_license',
					'item_id'    => 1660955,
				)
			)
		);
	}
}
