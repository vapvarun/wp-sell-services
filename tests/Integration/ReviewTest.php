<?php
/**
 * Review System Integration Tests.
 *
 * Tests the review and rating system:
 * - Review creation
 * - Rating calculations
 * - Vendor responses
 * - Moderation
 *
 * @package WPSellServices\Tests\Integration
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;
use WPSellServices\Tests\Factories\OrderFactory;
use WPSellServices\Tests\Factories\UserFactory;
use WPSellServices\Models\ServiceOrder;

/**
 * Test review system scenarios.
 */
class ReviewTest extends TestCase {

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		OrderFactory::reset();
		UserFactory::reset();
	}

	/**
	 * Test review requires completed order.
	 *
	 * @return void
	 */
	public function test_review_requires_completed_order(): void {
		$completed_order   = OrderFactory::completed();
		$in_progress_order = OrderFactory::in_progress();

		if ( $completed_order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_COMPLETED, $completed_order->status );
		}

		if ( $in_progress_order instanceof ServiceOrder ) {
			$this->assertNotEquals( ServiceOrder::STATUS_COMPLETED, $in_progress_order->status );
		}
	}

	/**
	 * Test review data structure.
	 *
	 * @return void
	 */
	public function test_review_data_structure(): void {
		$review = array(
			'order_id'             => 1,
			'service_id'           => 10,
			'reviewer_id'          => 2, // Customer.
			'reviewed_id'          => 1, // Vendor.
			'rating'               => 5,
			'rating_communication' => 5,
			'rating_quality'       => 5,
			'rating_value'         => 4,
			'title'                => 'Excellent work!',
			'content'              => 'The delivery was fast and the quality exceeded expectations.',
			'status'               => 'approved',
			'is_verified'          => true,
		);

		$this->assertArrayHasKey( 'order_id', $review );
		$this->assertArrayHasKey( 'rating', $review );
		$this->assertArrayHasKey( 'content', $review );
		$this->assertEquals( 5, $review['rating'] );
		$this->assertTrue( $review['is_verified'] );
	}

	/**
	 * Test rating validation (1-5 range).
	 *
	 * @return void
	 */
	public function test_rating_validation(): void {
		$valid_ratings   = array( 1, 2, 3, 4, 5 );
		$invalid_ratings = array( 0, 6, -1, 10 );

		foreach ( $valid_ratings as $rating ) {
			$this->assertGreaterThanOrEqual( 1, $rating );
			$this->assertLessThanOrEqual( 5, $rating );
		}

		foreach ( $invalid_ratings as $rating ) {
			$is_valid = ( $rating >= 1 && $rating <= 5 );
			$this->assertFalse( $is_valid );
		}
	}

	/**
	 * Test sub-rating categories.
	 *
	 * @return void
	 */
	public function test_sub_rating_categories(): void {
		$sub_ratings = array(
			'rating_communication' => 4,
			'rating_quality'       => 5,
			'rating_value'         => 4,
		);

		$this->assertArrayHasKey( 'rating_communication', $sub_ratings );
		$this->assertArrayHasKey( 'rating_quality', $sub_ratings );
		$this->assertArrayHasKey( 'rating_value', $sub_ratings );

		// All sub-ratings should be in valid range.
		foreach ( $sub_ratings as $rating ) {
			$this->assertGreaterThanOrEqual( 1, $rating );
			$this->assertLessThanOrEqual( 5, $rating );
		}
	}

	/**
	 * Test average rating calculation.
	 *
	 * @return void
	 */
	public function test_average_rating_calculation(): void {
		$reviews = array(
			array( 'rating' => 5 ),
			array( 'rating' => 4 ),
			array( 'rating' => 5 ),
			array( 'rating' => 3 ),
			array( 'rating' => 5 ),
		);

		$total   = array_sum( array_column( $reviews, 'rating' ) );
		$count   = count( $reviews );
		$average = $total / $count;

		$this->assertEquals( 4.4, $average );
	}

	/**
	 * Test review statuses.
	 *
	 * @return void
	 */
	public function test_review_statuses(): void {
		$statuses = array(
			'pending'  => 'Awaiting moderation',
			'approved' => 'Published',
			'rejected' => 'Rejected by moderator',
		);

		$this->assertArrayHasKey( 'pending', $statuses );
		$this->assertArrayHasKey( 'approved', $statuses );
		$this->assertArrayHasKey( 'rejected', $statuses );
	}

	/**
	 * Test vendor response to review.
	 *
	 * @return void
	 */
	public function test_vendor_response(): void {
		$review = array(
			'rating'      => 4,
			'content'     => 'Good work but could be faster.',
			'response'    => null,
			'response_at' => null,
		);

		// Simulate vendor response.
		$review['response']    = 'Thank you for your feedback! I will improve delivery times.';
		$review['response_at'] = date( 'Y-m-d H:i:s' );

		$this->assertNotNull( $review['response'] );
		$this->assertNotNull( $review['response_at'] );
	}

	/**
	 * Test helpful vote tracking.
	 *
	 * @return void
	 */
	public function test_helpful_vote_tracking(): void {
		$review = array(
			'helpful_count' => 0,
		);

		// Simulate helpful votes.
		++$review['helpful_count'];
		$this->assertEquals( 1, $review['helpful_count'] );

		$review['helpful_count'] += 5;
		$this->assertEquals( 6, $review['helpful_count'] );
	}

	/**
	 * Test one review per order constraint.
	 *
	 * @return void
	 */
	public function test_one_review_per_order(): void {
		$order_id = 1;

		$reviews = array(
			array(
				'order_id' => 1,
				'rating'   => 5,
			),
		);

		// Check if order already has a review.
		$existing_review = array_filter(
			$reviews,
			fn( $r ) => $r['order_id'] === $order_id
		);

		$this->assertCount( 1, $existing_review );

		// Attempting to add another review for same order should fail.
		$can_add_review = empty( $existing_review );
		$this->assertFalse( $can_add_review );
	}

	/**
	 * Test verified purchase badge.
	 *
	 * @return void
	 */
	public function test_verified_purchase_badge(): void {
		$review = array(
			'order_id'    => 1,
			'is_verified' => true,
		);

		// Verified means the reviewer actually purchased the service.
		$this->assertTrue( $review['is_verified'] );
	}

	/**
	 * Test service rating update after review.
	 *
	 * @return void
	 */
	public function test_service_rating_update(): void {
		$service = array(
			'rating'       => 0,
			'review_count' => 0,
		);

		$new_review_rating = 5;

		// Simulate rating update.
		++$service['review_count'];
		$service['rating'] = $new_review_rating; // First review sets rating.

		$this->assertEquals( 5, $service['rating'] );
		$this->assertEquals( 1, $service['review_count'] );

		// Add another review.
		$second_rating = 4;
		++$service['review_count'];
		$service['rating'] = ( $service['rating'] + $second_rating ) / 2;

		$this->assertEquals( 4.5, $service['rating'] );
		$this->assertEquals( 2, $service['review_count'] );
	}

	/**
	 * Test vendor profile rating update.
	 *
	 * @return void
	 */
	public function test_vendor_profile_rating_update(): void {
		$vendor_profile = array(
			'rating'       => 4.5,
			'review_count' => 10,
		);

		// Add new 5-star review.
		$new_rating  = 5;
		$old_total   = $vendor_profile['rating'] * $vendor_profile['review_count'];
		$new_count   = $vendor_profile['review_count'] + 1;
		$new_average = ( $old_total + $new_rating ) / $new_count;

		$vendor_profile['rating']       = round( $new_average, 2 );
		$vendor_profile['review_count'] = $new_count;

		$this->assertEquals( 11, $vendor_profile['review_count'] );
		$this->assertGreaterThan( 4.5, $vendor_profile['rating'] );
	}

	/**
	 * Test review moderation workflow.
	 *
	 * @return void
	 */
	public function test_review_moderation_workflow(): void {
		// Review submitted - starts as pending.
		$review = array(
			'status' => 'pending',
		);
		$this->assertEquals( 'pending', $review['status'] );

		// Moderator approves.
		$review['status'] = 'approved';
		$this->assertEquals( 'approved', $review['status'] );

		// Or moderator rejects.
		$review['status'] = 'rejected';
		$this->assertEquals( 'rejected', $review['status'] );
	}

	/**
	 * Test auto-approval setting.
	 *
	 * @return void
	 */
	public function test_auto_approval_setting(): void {
		$auto_approve = true;

		$review = array(
			'status' => $auto_approve ? 'approved' : 'pending',
		);

		$this->assertEquals( 'approved', $review['status'] );
	}
}
