<?php
/**
 * Functionality Gap Detection Tests.
 *
 * These tests check if expected functionality exists in the plugin.
 * Failed tests = functionality gaps that need implementation.
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

/**
 * Detect functionality gaps by checking class/method existence.
 *
 * Run these tests to see what's implemented vs what's missing.
 * Each failed test = a functionality gap to fill.
 */
class FunctionalityGapTest extends TestCase {

	/*
	|--------------------------------------------------------------------------
	| Core Services - Do they exist?
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test ServiceManager exists and has required methods.
	 *
	 * @return void
	 */
	public function test_service_manager_exists(): void {
		$class = 'WPSellServices\\Services\\ServiceManager';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: ServiceManager class doesn't exist at {$class}"
		);

		if ( class_exists( $class ) ) {
			$methods = array( 'create', 'update', 'delete', 'get', 'get_by_author' );
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: ServiceManager::{$method}() not implemented"
				);
			}
		}
	}

	/**
	 * Test OrderService exists and has required methods.
	 *
	 * @return void
	 */
	public function test_order_service_exists(): void {
		$class = 'WPSellServices\\Services\\OrderService';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: OrderService class doesn't exist at {$class}"
		);

		if ( class_exists( $class ) ) {
			$methods = array(
				'create',
				'get',
				'update_status',
				'get_by_customer',
				'get_by_vendor',
			);
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: OrderService::{$method}() not implemented"
				);
			}
		}
	}

	/**
	 * Test RequirementsService exists.
	 *
	 * @return void
	 */
	public function test_requirements_service_exists(): void {
		$class = 'WPSellServices\\Services\\RequirementsService';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: RequirementsService class doesn't exist - buyer requirements not implemented"
		);

		if ( class_exists( $class ) ) {
			$methods = array( 'submit', 'get_for_order', 'validate' );
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: RequirementsService::{$method}() not implemented"
				);
			}
		}
	}

	/**
	 * Test DeliveryService exists.
	 *
	 * @return void
	 */
	public function test_delivery_service_exists(): void {
		$class = 'WPSellServices\\Services\\DeliveryService';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: DeliveryService class doesn't exist - delivery flow not implemented"
		);

		if ( class_exists( $class ) ) {
			$methods = array( 'create', 'get_for_order', 'accept', 'request_revision' );
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: DeliveryService::{$method}() not implemented"
				);
			}
		}
	}

	/**
	 * Test ConversationService exists.
	 *
	 * @return void
	 */
	public function test_conversation_service_exists(): void {
		$class = 'WPSellServices\\Services\\ConversationService';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: ConversationService class doesn't exist - messaging not implemented"
		);

		if ( class_exists( $class ) ) {
			$methods = array( 'create', 'send_message', 'get_messages', 'mark_as_read' );
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: ConversationService::{$method}() not implemented"
				);
			}
		}
	}

	/**
	 * Test ReviewService exists.
	 *
	 * @return void
	 */
	public function test_review_service_exists(): void {
		$class = 'WPSellServices\\Services\\ReviewService';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: ReviewService class doesn't exist - reviews not implemented"
		);

		if ( class_exists( $class ) ) {
			$methods = array( 'create', 'get_for_service', 'get_for_vendor', 'respond' );
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: ReviewService::{$method}() not implemented"
				);
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Models - Do they exist with required properties?
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test Service model exists.
	 *
	 * @return void
	 */
	public function test_service_model_exists(): void {
		$class = 'WPSellServices\\Models\\Service';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: Service model doesn't exist"
		);

		if ( class_exists( $class ) ) {
			$methods = array(
				'get_packages',
				'get_addons',
				'get_requirements',
				'get_starting_price',
				'get_fastest_delivery',
			);
			foreach ( $methods as $method ) {
				$this->assertTrue(
					method_exists( $class, $method ),
					"GAP: Service::{$method}() not implemented"
				);
			}
		}
	}

	/**
	 * Test ServiceOrder model exists.
	 *
	 * @return void
	 */
	public function test_order_model_exists(): void {
		$class = 'WPSellServices\\Models\\ServiceOrder';

		$this->assertTrue(
			class_exists( $class ),
			"GAP: ServiceOrder model doesn't exist"
		);

		if ( class_exists( $class ) ) {
			// Check status constants.
			$statuses = array(
				'STATUS_PENDING_PAYMENT',
				'STATUS_PENDING_REQUIREMENTS',
				'STATUS_IN_PROGRESS',
				'STATUS_PENDING_APPROVAL',
				'STATUS_REVISION_REQUESTED',
				'STATUS_COMPLETED',
				'STATUS_CANCELLED',
				'STATUS_DISPUTED',
			);
			foreach ( $statuses as $status ) {
				$this->assertTrue(
					defined( "{$class}::{$status}" ),
					"GAP: ServiceOrder::{$status} constant not defined"
				);
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Database Tables - Do they exist?
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test required database tables exist.
	 *
	 * @return void
	 */
	public function test_database_tables_exist(): void {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			$this->markTestSkipped( 'WordPress not loaded - run with WP_TESTS_DIR' );
		}

		$required_tables = array(
			'wpss_orders'           => 'Service orders storage',
			'wpss_service_packages' => 'Service pricing packages',
			'wpss_service_addons'   => 'Service add-ons/extras',
			'wpss_conversations'    => 'Order messaging',
			'wpss_messages'         => 'Individual messages',
			'wpss_deliveries'       => 'Order deliveries',
			'wpss_reviews'          => 'Service reviews',
			'wpss_disputes'         => 'Order disputes',
		);

		foreach ( $required_tables as $table => $purpose ) {
			$full_table = $wpdb->prefix . $table;
			$exists     = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$full_table
				)
			);

			$this->assertNotNull(
				$exists,
				"GAP: Table {$full_table} doesn't exist - {$purpose} not implemented"
			);
		}
	}

	/*
	|--------------------------------------------------------------------------
	| REST API Endpoints - Do they exist?
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test REST API controllers exist.
	 *
	 * @return void
	 */
	public function test_rest_api_controllers_exist(): void {
		$controllers = array(
			'WPSellServices\\API\\Controllers\\ServicesController'     => 'Services API',
			'WPSellServices\\API\\Controllers\\OrdersController'       => 'Orders API',
			'WPSellServices\\API\\Controllers\\ConversationsController' => 'Messaging API',
			'WPSellServices\\API\\Controllers\\DeliveriesController'   => 'Deliveries API',
			'WPSellServices\\API\\Controllers\\ReviewsController'      => 'Reviews API',
		);

		foreach ( $controllers as $class => $purpose ) {
			$this->assertTrue(
				class_exists( $class ),
				"GAP: {$class} doesn't exist - {$purpose} not implemented"
			);
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Fiverr Feature Parity - What's missing?
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test Fiverr-like features exist.
	 *
	 * @return void
	 */
	public function test_fiverr_feature_parity(): void {
		$features = array(
			// Core features.
			array(
				'class'  => 'WPSellServices\\Services\\ServiceManager',
				'method' => 'create',
				'feature' => 'Gig creation',
			),
			array(
				'class'  => 'WPSellServices\\Models\\ServicePackage',
				'method' => null,
				'feature' => 'Tiered pricing (Basic/Standard/Premium)',
			),
			array(
				'class'  => 'WPSellServices\\Models\\ServiceAddon',
				'method' => null,
				'feature' => 'Gig extras/add-ons',
			),

			// Order workflow.
			array(
				'class'  => 'WPSellServices\\Services\\RequirementsService',
				'method' => 'submit',
				'feature' => 'Buyer requirements collection',
			),
			array(
				'class'  => 'WPSellServices\\Services\\DeliveryService',
				'method' => 'create',
				'feature' => 'Order delivery with files',
			),
			array(
				'class'  => 'WPSellServices\\Services\\DeliveryService',
				'method' => 'request_revision',
				'feature' => 'Revision requests',
			),

			// Communication.
			array(
				'class'  => 'WPSellServices\\Services\\ConversationService',
				'method' => 'send_message',
				'feature' => 'Order messaging',
			),

			// Reviews.
			array(
				'class'  => 'WPSellServices\\Services\\ReviewService',
				'method' => 'create',
				'feature' => 'Review & rating system',
			),

			// Seller features.
			array(
				'class'  => 'WPSellServices\\Models\\VendorProfile',
				'method' => null,
				'feature' => 'Seller profile with stats',
			),

			// Buyer requests.
			array(
				'class'  => 'WPSellServices\\Services\\RequestService',
				'method' => 'create',
				'feature' => 'Buyer requests (job posts)',
			),
			array(
				'class'  => 'WPSellServices\\Services\\ProposalService',
				'method' => 'submit',
				'feature' => 'Seller proposals on requests',
			),

			// Disputes.
			array(
				'class'  => 'WPSellServices\\Services\\DisputeService',
				'method' => 'create',
				'feature' => 'Order dispute resolution',
			),

			// Missing Fiverr features (expected to fail).
			array(
				'class'  => 'WPSellServices\\Services\\TippingService',
				'method' => 'tip',
				'feature' => 'Tipping after order completion',
			),
			array(
				'class'  => 'WPSellServices\\Services\\MilestoneService',
				'method' => 'create',
				'feature' => 'Order milestones for large projects',
			),
			array(
				'class'  => 'WPSellServices\\Services\\SellerLevelService',
				'method' => 'calculate_level',
				'feature' => 'Seller levels (New/Level 1/Level 2/Top Rated)',
			),
		);

		$implemented = array();
		$missing     = array();

		foreach ( $features as $feature ) {
			$exists = class_exists( $feature['class'] );
			if ( $exists && $feature['method'] ) {
				$exists = method_exists( $feature['class'], $feature['method'] );
			}

			if ( $exists ) {
				$implemented[] = $feature['feature'];
			} else {
				$missing[] = $feature['feature'];
			}
		}

		// Output feature status.
		echo "\n\n=== FIVERR FEATURE PARITY REPORT ===\n";
		echo "\nImplemented (" . count( $implemented ) . "):\n";
		foreach ( $implemented as $f ) {
			echo "  [x] {$f}\n";
		}

		echo "\nMissing (" . count( $missing ) . "):\n";
		foreach ( $missing as $f ) {
			echo "  [ ] {$f}\n";
		}
		echo "\n";

		// This test always passes - it's informational.
		$this->assertTrue( true );
	}

	/*
	|--------------------------------------------------------------------------
	| Order Status Transitions - Are they all implemented?
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test order status transition methods exist.
	 *
	 * @return void
	 */
	public function test_order_status_transitions(): void {
		$class = 'WPSellServices\\Services\\OrderWorkflowManager';

		if ( ! class_exists( $class ) ) {
			$class = 'WPSellServices\\Services\\OrderService';
		}

		if ( ! class_exists( $class ) ) {
			$this->markTestSkipped( "GAP: No order workflow manager class found" );
		}

		$transitions = array(
			'start_order'      => 'pending_requirements → in_progress',
			'deliver_order'    => 'in_progress → pending_approval',
			'request_revision' => 'pending_approval → revision_requested',
			'accept_delivery'  => 'pending_approval → completed',
			'cancel_order'     => 'any → cancelled',
			'open_dispute'     => 'any → disputed',
			'extend_deadline'  => 'Add extra days to deadline',
		);

		foreach ( $transitions as $method => $description ) {
			$this->assertTrue(
				method_exists( $class, $method ),
				"GAP: {$class}::{$method}() not implemented - {$description}"
			);
		}
	}
}
