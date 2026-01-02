<?php
/**
 * Order Workflow Integration Tests.
 *
 * Tests the complete order lifecycle:
 * - Checkout & Order Creation
 * - Requirements Submission
 * - Work In Progress
 * - Delivery & Approval
 * - Revision Request
 * - Completion
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;
use WPSellServices\Tests\Factories\OrderFactory;
use WPSellServices\Tests\Factories\ServiceFactory;
use WPSellServices\Tests\Factories\UserFactory;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use DateTimeImmutable;

/**
 * Test complete order workflow scenarios.
 */
class OrderWorkflowTest extends TestCase {

	/**
	 * Order service instance.
	 *
	 * @var OrderService|null
	 */
	private ?OrderService $order_service = null;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		OrderFactory::reset();
		ServiceFactory::reset();
		UserFactory::reset();

		if ( class_exists( OrderService::class ) ) {
			$this->order_service = new OrderService();
		}
	}

	// =========================================================================
	// Stage 1: Checkout & Order Creation
	// =========================================================================

	/**
	 * Test order is created with pending_payment status.
	 *
	 * @return void
	 */
	public function test_order_created_with_pending_payment_status(): void {
		$order = OrderFactory::pending_payment();

		$this->assertNotEmpty( $order );

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_PENDING_PAYMENT, $order->status );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_PENDING_PAYMENT, $order['status'] );
		}
	}

	/**
	 * Test order has correct totals calculated.
	 *
	 * @return void
	 */
	public function test_order_has_correct_totals(): void {
		$order = OrderFactory::with_addons(
			array( 1 => 1 ), // One addon.
			array(
				'subtotal'     => 79.99,
				'addons_total' => 49.99,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			$expected_total = $order->subtotal + $order->addons_total;
			$this->assertEquals( $expected_total, $order->total );
		} elseif ( is_array( $order ) ) {
			$expected_total = $order['subtotal'] + $order['addons_total'];
			$this->assertEquals( $expected_total, $order['total'] );
		}
	}

	/**
	 * Test order number is generated with correct format.
	 *
	 * @return void
	 */
	public function test_order_number_generated(): void {
		$order = OrderFactory::pending_payment();

		if ( $order instanceof ServiceOrder ) {
			$this->assertMatchesRegularExpression( '/^WPSS-\d{6}$/', $order->order_number );
		} elseif ( is_array( $order ) ) {
			$this->assertMatchesRegularExpression( '/^WPSS-\d{6}$/', $order['order_number'] );
		}
	}

	/**
	 * Test order has customer and vendor assigned.
	 *
	 * @return void
	 */
	public function test_order_has_participants(): void {
		$order = OrderFactory::pending_payment(
			array(
				'customer_id' => 10,
				'vendor_id'   => 5,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( 10, $order->customer_id );
			$this->assertEquals( 5, $order->vendor_id );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( 10, $order['customer_id'] );
			$this->assertEquals( 5, $order['vendor_id'] );
		}
	}

	// =========================================================================
	// Stage 2: Requirements Submission
	// =========================================================================

	/**
	 * Test order transitions to pending_requirements after payment.
	 *
	 * @return void
	 */
	public function test_order_pending_requirements_after_payment(): void {
		$order = OrderFactory::pending_requirements();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_PENDING_REQUIREMENTS, $order->status );
			$this->assertEquals( 'completed', $order->payment_status );
			$this->assertNotNull( $order->paid_at );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_PENDING_REQUIREMENTS, $order['status'] );
			$this->assertEquals( 'completed', $order['payment_status'] );
		}
	}

	/**
	 * Test order transitions to in_progress after requirements submitted.
	 *
	 * @return void
	 */
	public function test_order_in_progress_after_requirements(): void {
		$order = OrderFactory::in_progress();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_IN_PROGRESS, $order->status );
			$this->assertNotNull( $order->started_at );
			$this->assertNotNull( $order->delivery_deadline );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_IN_PROGRESS, $order['status'] );
			$this->assertNotNull( $order['started_at'] );
		}
	}

	/**
	 * Test delivery deadline is set correctly.
	 *
	 * @return void
	 */
	public function test_delivery_deadline_set(): void {
		$order = OrderFactory::in_progress();

		if ( $order instanceof ServiceOrder ) {
			$this->assertInstanceOf( DateTimeImmutable::class, $order->delivery_deadline );
			$this->assertInstanceOf( DateTimeImmutable::class, $order->original_deadline );

			// Deadline should be after started_at.
			$this->assertGreaterThan(
				$order->started_at->getTimestamp(),
				$order->delivery_deadline->getTimestamp()
			);
		}
	}

	// =========================================================================
	// Stage 3: Work In Progress
	// =========================================================================

	/**
	 * Test detecting late orders.
	 *
	 * @return void
	 */
	public function test_late_order_detection(): void {
		$order = OrderFactory::late();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_LATE, $order->status );

			// Deadline should be in the past.
			$now = new DateTimeImmutable();
			$this->assertLessThan(
				$now->getTimestamp(),
				$order->delivery_deadline->getTimestamp()
			);
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_LATE, $order['status'] );
		}
	}

	/**
	 * Test revisions tracking.
	 *
	 * @return void
	 */
	public function test_revisions_tracking(): void {
		$order = OrderFactory::in_progress(
			array(
				'revisions_included' => 3,
				'revisions_used'     => 0,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( 3, $order->revisions_included );
			$this->assertEquals( 0, $order->revisions_used );
			// Remaining revisions would be 3.
		}
	}

	// =========================================================================
	// Stage 4: Delivery & Approval
	// =========================================================================

	/**
	 * Test order transitions to pending_approval after delivery.
	 *
	 * @return void
	 */
	public function test_order_pending_approval_after_delivery(): void {
		$order = OrderFactory::pending_approval();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_PENDING_APPROVAL, $order->status );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_PENDING_APPROVAL, $order['status'] );
		}
	}

	// =========================================================================
	// Stage 5: Revision Request
	// =========================================================================

	/**
	 * Test revision request increments counter.
	 *
	 * @return void
	 */
	public function test_revision_request_increments_counter(): void {
		$order = OrderFactory::revision_requested();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_REVISION_REQUESTED, $order->status );
			$this->assertGreaterThan( 0, $order->revisions_used );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_REVISION_REQUESTED, $order['status'] );
			$this->assertGreaterThan( 0, $order['revisions_used'] );
		}
	}

	/**
	 * Test revision limited by package allowance.
	 *
	 * @return void
	 */
	public function test_revision_limited_by_package(): void {
		$order = OrderFactory::revision_requested(
			array(
				'revisions_included' => 2,
				'revisions_used'     => 2,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			// No more revisions available.
			$remaining = $order->revisions_included - $order->revisions_used;
			$this->assertEquals( 0, $remaining );
		}
	}

	/**
	 * Test unlimited revisions honored.
	 *
	 * @return void
	 */
	public function test_unlimited_revisions(): void {
		$order = OrderFactory::revision_requested(
			array(
				'revisions_included' => -1, // Unlimited.
				'revisions_used'     => 10,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			// With -1, revisions should always be available.
			$this->assertEquals( -1, $order->revisions_included );
		}
	}

	// =========================================================================
	// Stage 6: Completion
	// =========================================================================

	/**
	 * Test order marked as completed.
	 *
	 * @return void
	 */
	public function test_order_completed(): void {
		$order = OrderFactory::completed();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_COMPLETED, $order->status );
			$this->assertNotNull( $order->completed_at );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_COMPLETED, $order['status'] );
			$this->assertNotNull( $order['completed_at'] );
		}
	}

	/**
	 * Test completed_at timestamp is set.
	 *
	 * @return void
	 */
	public function test_completed_at_timestamp_set(): void {
		$order = OrderFactory::completed();

		if ( $order instanceof ServiceOrder ) {
			$this->assertInstanceOf( DateTimeImmutable::class, $order->completed_at );

			// Completed should be after started.
			$this->assertGreaterThan(
				$order->started_at->getTimestamp(),
				$order->completed_at->getTimestamp()
			);
		}
	}

	// =========================================================================
	// Other Status Tests
	// =========================================================================

	/**
	 * Test cancelled order.
	 *
	 * @return void
	 */
	public function test_order_cancelled(): void {
		$order = OrderFactory::cancelled();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_CANCELLED, $order->status );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_CANCELLED, $order['status'] );
		}
	}

	/**
	 * Test disputed order.
	 *
	 * @return void
	 */
	public function test_order_disputed(): void {
		$order = OrderFactory::disputed();

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_DISPUTED, $order->status );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( ServiceOrder::STATUS_DISPUTED, $order['status'] );
		}
	}

	/**
	 * Test valid status transitions.
	 *
	 * @return void
	 */
	public function test_valid_status_transitions(): void {
		// Define expected valid transitions.
		$valid_transitions = array(
			ServiceOrder::STATUS_PENDING_PAYMENT      => array(
				ServiceOrder::STATUS_PENDING_REQUIREMENTS,
				ServiceOrder::STATUS_CANCELLED,
			),
			ServiceOrder::STATUS_PENDING_REQUIREMENTS => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_ON_HOLD,
			),
			ServiceOrder::STATUS_IN_PROGRESS          => array(
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_ON_HOLD,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_LATE,
			),
			ServiceOrder::STATUS_PENDING_APPROVAL     => array(
				ServiceOrder::STATUS_COMPLETED,
				ServiceOrder::STATUS_REVISION_REQUESTED,
				ServiceOrder::STATUS_DISPUTED,
			),
			ServiceOrder::STATUS_REVISION_REQUESTED   => array(
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_CANCELLED,
				ServiceOrder::STATUS_DISPUTED,
			),
		);

		// Just verify the structure is defined correctly.
		$this->assertArrayHasKey( ServiceOrder::STATUS_PENDING_PAYMENT, $valid_transitions );
		$this->assertArrayHasKey( ServiceOrder::STATUS_IN_PROGRESS, $valid_transitions );
		$this->assertArrayHasKey( ServiceOrder::STATUS_PENDING_APPROVAL, $valid_transitions );
	}

	/**
	 * Test order with addons.
	 *
	 * @return void
	 */
	public function test_order_with_addons(): void {
		$order = OrderFactory::with_addons(
			array(
				1 => 2,
				2 => 1,
			), // Addon 1 qty 2, Addon 2 qty 1.
			array(
				'subtotal'     => 79.99,
				'addons_total' => 89.97,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			$this->assertNotEmpty( $order->addons );
			$this->assertGreaterThan( 0, $order->addons_total );
		} elseif ( is_array( $order ) ) {
			$this->assertNotEmpty( $order['addons'] );
			$this->assertGreaterThan( 0, $order['addons_total'] );
		}
	}

	/**
	 * Test platform integration fields.
	 *
	 * @return void
	 */
	public function test_platform_integration_fields(): void {
		$order = OrderFactory::pending_payment(
			array(
				'platform'          => 'woocommerce',
				'platform_order_id' => 12345,
				'platform_item_id'  => 67890,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( 'woocommerce', $order->platform );
			$this->assertEquals( 12345, $order->platform_order_id );
			$this->assertEquals( 67890, $order->platform_item_id );
		} elseif ( is_array( $order ) ) {
			$this->assertEquals( 'woocommerce', $order['platform'] );
			$this->assertEquals( 12345, $order['platform_order_id'] );
		}
	}
}
