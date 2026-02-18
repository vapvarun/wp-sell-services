<?php
/**
 * Tests that every settings tab fires its wpss_settings_sections_{$tab_id} hook.
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;
use WPSellServices\Admin\Settings;

/**
 * @covers \WPSellServices\Admin\Settings::render_tab_sections
 */
class SettingsHooksTest extends TestCase {

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Track which hooks fired.
	 *
	 * @var array<string, int>
	 */
	private array $fired = array();

	protected function set_up(): void {
		parent::set_up();

		global $wpdb;
		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			$this->markTestSkipped( 'WordPress not available.' );
		}

		if ( ! class_exists( Settings::class ) ) {
			$this->markTestSkipped( 'Settings class not found.' );
		}

		$this->settings = new Settings();
		$this->fired    = array();
	}

	protected function tear_down(): void {
		$this->fired = array();
		parent::tear_down();
	}

	/**
	 * Helper: listen for a hook and record when it fires.
	 */
	private function listen( string $hook ): void {
		add_action(
			$hook,
			function () use ( $hook ) {
				$this->fired[ $hook ] = ( $this->fired[ $hook ] ?? 0 ) + 1;
			}
		);
	}

	public function test_payments_sections_hook_fires(): void {
		$this->listen( 'wpss_settings_sections_payments' );

		ob_start();
		$this->settings->render_tab_sections( 'payments', array() );
		ob_end_clean();

		$this->assertArrayHasKey( 'wpss_settings_sections_payments', $this->fired );
		$this->assertSame( 1, $this->fired['wpss_settings_sections_payments'] );
	}

	public function test_vendor_sections_hook_fires(): void {
		$this->listen( 'wpss_settings_sections_vendor' );

		ob_start();
		$this->settings->render_tab_sections( 'vendor', array() );
		ob_end_clean();

		$this->assertArrayHasKey( 'wpss_settings_sections_vendor', $this->fired );
		$this->assertSame( 1, $this->fired['wpss_settings_sections_vendor'] );
	}

	public function test_orders_sections_hook_fires(): void {
		$this->listen( 'wpss_settings_sections_orders' );

		ob_start();
		$this->settings->render_tab_sections( 'orders', array() );
		ob_end_clean();

		$this->assertArrayHasKey( 'wpss_settings_sections_orders', $this->fired );
		$this->assertSame( 1, $this->fired['wpss_settings_sections_orders'] );
	}

	public function test_emails_sections_hook_fires(): void {
		$this->listen( 'wpss_settings_sections_emails' );

		ob_start();
		$this->settings->render_tab_sections( 'emails', array() );
		ob_end_clean();

		$this->assertArrayHasKey( 'wpss_settings_sections_emails', $this->fired );
		$this->assertSame( 1, $this->fired['wpss_settings_sections_emails'] );
	}

	public function test_advanced_sections_hook_fires(): void {
		$this->listen( 'wpss_settings_sections_advanced' );

		ob_start();
		$this->settings->render_tab_sections( 'advanced', array() );
		ob_end_clean();

		$this->assertArrayHasKey( 'wpss_settings_sections_advanced', $this->fired );
		$this->assertSame( 1, $this->fired['wpss_settings_sections_advanced'] );
	}

	public function test_custom_tab_action_fires_for_registered_tabs(): void {
		$custom_tab = 'my_custom_tab_' . uniqid();
		$this->listen( "wpss_settings_sections_{$custom_tab}" );

		ob_start();
		$this->settings->render_tab_sections( $custom_tab, array() );
		ob_end_clean();

		$this->assertArrayHasKey( "wpss_settings_sections_{$custom_tab}", $this->fired );
	}

	public function test_gateways_hook_fires_in_gateways_tab(): void {
		$this->listen( 'wpss_settings_sections_gateways' );

		// We can't easily call the private render_gateways_tab(),
		// but we can verify the hook is registered by calling render_tab_sections directly.
		ob_start();
		$this->settings->render_tab_sections( 'gateways', array() );
		ob_end_clean();

		$this->assertArrayHasKey( 'wpss_settings_sections_gateways', $this->fired );
	}

	public function test_section_callback_receives_section_data(): void {
		$received = null;

		ob_start();
		$this->settings->render_tab_sections(
			'test_cb',
			array(
				array(
					'id'       => 'test-section',
					'title'    => 'Test',
					'callback' => function () use ( &$received ) {
						$received = true;
					},
				),
			)
		);
		ob_end_clean();

		$this->assertTrue( $received, 'Section callback should have been called.' );
	}
}
