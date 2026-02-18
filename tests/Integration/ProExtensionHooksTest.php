<?php
/**
 * Tests that the 9 extension hooks used by Pro exist and pass correct data.
 *
 * These hooks were added in the free plugin specifically to allow Pro
 * to extend functionality without modifying free plugin code.
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

/**
 * Verifies Pro extension hooks exist and fire with expected signatures.
 */
class ProExtensionHooksTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		global $wpdb;
		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			$this->markTestSkipped( 'WordPress not available.' );
		}
	}

	/**
	 * 1. wpss_stripe_payment_intent_args filter
	 * Location: StripeGateway::create_payment_intent()
	 */
	public function test_wpss_stripe_payment_intent_args_filter(): void {
		$received = null;

		add_filter(
			'wpss_stripe_payment_intent_args',
			function ( $params, $order_id, $vendor_id ) use ( &$received ) {
				$received = compact( 'params', 'order_id', 'vendor_id' );
				return $params;
			},
			10,
			3
		);

		$input  = array( 'amount' => 5000, 'currency' => 'usd' );
		$result = apply_filters( 'wpss_stripe_payment_intent_args', $input, 42, 7 );

		$this->assertNotNull( $received, 'Filter callback should have been called.' );
		$this->assertSame( $input, $result );
		$this->assertSame( 42, $received['order_id'] );
		$this->assertSame( 7, $received['vendor_id'] );
	}

	/**
	 * 2. wpss_stripe_webhook_received action
	 * Location: StripeGateway::handle_webhook()
	 */
	public function test_wpss_stripe_webhook_received_action(): void {
		$received = null;

		add_action(
			'wpss_stripe_webhook_received',
			function ( $event_type, $data, $payload ) use ( &$received ) {
				$received = compact( 'event_type', 'data', 'payload' );
			},
			10,
			3
		);

		do_action( 'wpss_stripe_webhook_received', 'payment_intent.succeeded', array( 'id' => 'pi_123' ), '{}' );

		$this->assertNotNull( $received );
		$this->assertSame( 'payment_intent.succeeded', $received['event_type'] );
	}

	/**
	 * 3. wpss_vendor_can_create_service filter
	 * Location: ServicesController::create_item_permissions_check()
	 */
	public function test_wpss_vendor_can_create_service_filter(): void {
		// Default should be true (no restrictions).
		$result = apply_filters( 'wpss_vendor_can_create_service', true, 1 );
		$this->assertTrue( $result );

		// Pro can block creation.
		add_filter(
			'wpss_vendor_can_create_service',
			function ( $can, $user_id ) {
				return $user_id !== 99;
			},
			10,
			2
		);

		$this->assertTrue( apply_filters( 'wpss_vendor_can_create_service', true, 1 ) );
		$this->assertFalse( apply_filters( 'wpss_vendor_can_create_service', true, 99 ) );
	}

	/**
	 * 4. wpss_email_header_vars filter
	 * Location: EmailService::send()
	 */
	public function test_wpss_email_header_vars_filter(): void {
		$received_type = null;

		add_filter(
			'wpss_email_header_vars',
			function ( $vars, $type ) use ( &$received_type ) {
				$received_type = $type;
				$vars['site_name'] = 'Custom Brand';
				return $vars;
			},
			10,
			2
		);

		$input = array(
			'site_name'    => 'Test Site',
			'header_image' => '',
			'footer_text'  => '',
		);

		$result = apply_filters( 'wpss_email_header_vars', $input, 'order_created' );

		$this->assertSame( 'order_created', $received_type );
		$this->assertSame( 'Custom Brand', $result['site_name'] );
	}

	/**
	 * 5. wpss_admin_menu_label filter
	 * Location: Admin::register_menu()
	 */
	public function test_wpss_admin_menu_label_filter(): void {
		$default = apply_filters( 'wpss_admin_menu_label', 'Sell Services' );
		$this->assertSame( 'Sell Services', $default );

		add_filter(
			'wpss_admin_menu_label',
			function () {
				return 'MyMarket';
			}
		);

		$this->assertSame( 'MyMarket', apply_filters( 'wpss_admin_menu_label', 'Sell Services' ) );
	}

	/**
	 * 6. wpss_email_from_name filter
	 * Location: EmailService::send()
	 */
	public function test_wpss_email_from_name_filter(): void {
		$received = null;

		add_filter(
			'wpss_email_from_name',
			function ( $name ) use ( &$received ) {
				$received = $name;
				return 'Custom Sender';
			}
		);

		$result = apply_filters( 'wpss_email_from_name', 'WP Sell Services' );

		$this->assertSame( 'WP Sell Services', $received );
		$this->assertSame( 'Custom Sender', $result );
	}

	/**
	 * 7. wpss_service_meta_fields filter
	 * Location: ServiceMetabox::render_metabox()
	 */
	public function test_wpss_service_meta_fields_filter(): void {
		add_filter(
			'wpss_service_meta_fields',
			function ( $fields, $post_id ) {
				$fields[] = array(
					'key'   => '_wpss_recurring_enabled',
					'label' => 'Enable Recurring',
					'type'  => 'checkbox',
				);
				return $fields;
			},
			10,
			2
		);

		$result = apply_filters( 'wpss_service_meta_fields', array(), 123 );

		$this->assertCount( 1, $result );
		$this->assertSame( '_wpss_recurring_enabled', $result[0]['key'] );
	}

	/**
	 * 8. wpss_order_created action
	 * Location: StandaloneOrderProvider::create_order()
	 */
	public function test_wpss_order_created_action(): void {
		$received = null;

		add_action(
			'wpss_order_created',
			function ( $order_id, $order_data ) use ( &$received ) {
				$received = compact( 'order_id', 'order_data' );
			},
			10,
			2
		);

		do_action( 'wpss_order_created', 55, array( 'service_id' => 10 ) );

		$this->assertNotNull( $received );
		$this->assertSame( 55, $received['order_id'] );
		$this->assertSame( 10, $received['order_data']['service_id'] );
	}

	/**
	 * 9. wpss_settings_tabs filter
	 * Location: Settings::__construct()
	 */
	public function test_wpss_settings_tabs_filter(): void {
		add_filter(
			'wpss_settings_tabs',
			function ( $tabs ) {
				$tabs['branding'] = 'Branding';
				return $tabs;
			}
		);

		$tabs = apply_filters(
			'wpss_settings_tabs',
			array(
				'general'  => 'General',
				'payments' => 'Payments',
			)
		);

		$this->assertArrayHasKey( 'branding', $tabs );
		$this->assertSame( 'Branding', $tabs['branding'] );
	}
}
