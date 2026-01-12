<?php
/**
 * Schema Validation Tests
 *
 * Tests that model classes correctly map to database schema.
 *
 * @package WPSellServices\Tests\Integration
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;
use WPSellServices\Core\SchemaValidator;
use WPSellServices\Models\VendorProfile;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Database\SchemaManager;

/**
 * Schema Validation Test class.
 *
 * @since 1.0.0
 */
class SchemaValidationTest extends TestCase {

	/**
	 * Schema validator instance.
	 *
	 * @var SchemaValidator
	 */
	private SchemaValidator $validator;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new SchemaValidator();
	}

	/**
	 * Test that all models pass schema validation.
	 */
	public function test_all_models_pass_validation(): void {
		$results = $this->validator->validate_all();

		foreach ( $results as $model => $result ) {
			$this->assertEmpty(
				$result['errors'],
				"Model {$model} has schema errors: " . implode( ', ', $result['errors'] )
			);
		}
	}

	/**
	 * Test VendorProfile column mappings are correct.
	 */
	public function test_vendor_profile_column_mappings(): void {
		global $wpdb;

		// Skip if table doesn't exist.
		$table  = $wpdb->prefix . 'wpss_vendor_profiles';
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ); // phpcs:ignore

		if ( ! $exists ) {
			$this->markTestSkipped( 'vendor_profiles table does not exist' );
		}

		// Test that from_db correctly maps columns.
		$mock_row = (object) [
			'id'                    => 1,
			'user_id'               => 100,
			'display_name'          => 'Test Vendor',
			'tagline'               => 'Professional Developer', // Maps to title.
			'bio'                   => 'Test bio',
			'avatar_id'             => 10,
			'cover_image_id'        => 20, // Maps to cover_id.
			'country'               => 'US',
			'verification_tier'     => 'rising', // Maps to tier.
			'avg_rating'            => 4.5, // Maps to rating.
			'total_reviews'         => 50, // Maps to review_count.
			'completed_orders'      => 100, // Maps to orders_completed.
			'response_time_hours'   => 2.5, // Maps to response_time.
			'on_time_delivery_rate' => 95.0, // Maps to delivery_rate.
			'is_available'          => true,
			'vacation_mode'         => false,
			'social_links'          => '{}',
			'created_at'            => '2024-01-01 00:00:00',
			'updated_at'            => '2024-01-01 00:00:00',
		];

		$profile = VendorProfile::from_db( $mock_row );

		$this->assertEquals( 'Professional Developer', $profile->title );
		$this->assertEquals( 20, $profile->cover_id );
		$this->assertEquals( 'rising', $profile->tier );
		$this->assertEquals( 4.5, $profile->rating );
		$this->assertEquals( 50, $profile->review_count );
		$this->assertEquals( 100, $profile->orders_completed );
		$this->assertEquals( 2.5, $profile->response_time );
		$this->assertEquals( 95.0, $profile->delivery_rate );
	}

	/**
	 * Test ServiceOrder from_db works correctly.
	 */
	public function test_service_order_from_db(): void {
		$mock_row = (object) [
			'id'                 => 1,
			'order_number'       => 'WPSS-ABC123',
			'customer_id'        => 100,
			'vendor_id'          => 200,
			'service_id'         => 300,
			'package_id'         => 1,
			'addons'             => '[]',
			'platform'           => 'woocommerce',
			'platform_order_id'  => 500,
			'platform_item_id'   => 501,
			'subtotal'           => 100.00,
			'addons_total'       => 20.00,
			'total'              => 120.00,
			'currency'           => 'USD',
			'commission_rate'    => 20.0,
			'platform_fee'       => 24.00,
			'vendor_earnings'    => 96.00,
			'status'             => 'in_progress',
			'payment_method'     => 'stripe',
			'payment_status'     => 'completed',
			'transaction_id'     => 'txn_123',
			'revisions_included' => 3,
			'revisions_used'     => 1,
			'delivery_deadline'  => '2024-01-10 00:00:00',
			'original_deadline'  => '2024-01-10 00:00:00',
			'paid_at'            => '2024-01-01 00:00:00',
			'created_at'         => '2024-01-01 00:00:00',
			'updated_at'         => '2024-01-02 00:00:00',
			'started_at'         => '2024-01-01 12:00:00',
			'completed_at'       => null,
		];

		$order = ServiceOrder::from_db( $mock_row );

		$this->assertEquals( 1, $order->id );
		$this->assertEquals( 'WPSS-ABC123', $order->order_number );
		$this->assertEquals( 96.00, $order->vendor_earnings );
		$this->assertEquals( 24.00, $order->platform_fee );
		$this->assertEquals( 20.0, $order->commission_rate );
		$this->assertEquals( 'in_progress', $order->status );
		$this->assertEquals( 2, $order->get_remaining_revisions() );
	}

	/**
	 * Test that orders table has required columns.
	 */
	public function test_orders_table_has_required_columns(): void {
		global $wpdb;

		$table   = $wpdb->prefix . 'wpss_orders';
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore

		if ( empty( $columns ) ) {
			$this->markTestSkipped( 'orders table does not exist' );
		}

		$required = [
			'id',
			'order_number',
			'customer_id',
			'vendor_id',
			'service_id',
			'status',
			'total',
			'vendor_earnings',
			'platform_fee',
			'commission_rate',
		];

		foreach ( $required as $column ) {
			$this->assertContains(
				$column,
				$columns,
				"Orders table missing required column: {$column}"
			);
		}
	}

	/**
	 * Test schema validator report generation.
	 */
	public function test_schema_validator_generates_report(): void {
		$report = $this->validator->get_report();

		$this->assertIsString( $report );
		$this->assertStringContainsString( 'Schema Validation Report', $report );
	}

	/**
	 * Test schema validator error collection.
	 */
	public function test_schema_validator_collects_errors(): void {
		$errors = $this->validator->get_errors();

		$this->assertIsArray( $errors );
		// Errors array may be empty if all validations pass.
	}
}
