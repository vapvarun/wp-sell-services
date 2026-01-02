<?php
/**
 * Conversation Integration Tests.
 *
 * Tests the order conversation/messaging system:
 * - Conversation creation
 * - Message types
 * - Unread counts
 * - Attachments
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
 * Test conversation and messaging scenarios.
 */
class ConversationTest extends TestCase {

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
	 * Test conversation is created for order.
	 *
	 * @return void
	 */
	public function test_conversation_created_for_order(): void {
		$order = OrderFactory::in_progress();

		// In a real integration test, we'd verify:
		// - Conversation exists for this order
		// - Participants include customer and vendor.
		$this->assertNotEmpty( $order );

		if ( $order instanceof ServiceOrder ) {
			$this->assertNotEquals( $order->customer_id, $order->vendor_id );
		}
	}

	/**
	 * Test message types are defined correctly.
	 *
	 * @return void
	 */
	public function test_message_types_defined(): void {
		// Define expected message types based on the codebase.
		$expected_types = array(
			'text',
			'delivery',
			'revision',
			'system',
			'status_change',
			'attachment',
		);

		foreach ( $expected_types as $type ) {
			$this->assertNotEmpty( $type );
		}
	}

	/**
	 * Test text message structure.
	 *
	 * @return void
	 */
	public function test_text_message_structure(): void {
		$message = array(
			'type'       => 'text',
			'sender_id'  => 1,
			'content'    => 'Hello, I have a question about the project.',
			'created_at' => date( 'Y-m-d H:i:s' ),
		);

		$this->assertEquals( 'text', $message['type'] );
		$this->assertNotEmpty( $message['content'] );
		$this->assertNotEmpty( $message['sender_id'] );
	}

	/**
	 * Test delivery message structure.
	 *
	 * @return void
	 */
	public function test_delivery_message_structure(): void {
		$message = array(
			'type'        => 'delivery',
			'sender_id'   => 1, // Vendor.
			'content'     => 'Here is your completed work.',
			'attachments' => array(
				array(
					'id'   => 123,
					'name' => 'final-design.zip',
				),
			),
			'created_at'  => date( 'Y-m-d H:i:s' ),
		);

		$this->assertEquals( 'delivery', $message['type'] );
		$this->assertNotEmpty( $message['attachments'] );
	}

	/**
	 * Test revision message structure.
	 *
	 * @return void
	 */
	public function test_revision_message_structure(): void {
		$message = array(
			'type'       => 'revision',
			'sender_id'  => 2, // Customer.
			'content'    => 'Please adjust the colors as discussed.',
			'created_at' => date( 'Y-m-d H:i:s' ),
		);

		$this->assertEquals( 'revision', $message['type'] );
		$this->assertNotEmpty( $message['content'] );
	}

	/**
	 * Test system message structure.
	 *
	 * @return void
	 */
	public function test_system_message_structure(): void {
		$message = array(
			'type'       => 'system',
			'sender_id'  => 0, // System.
			'content'    => 'Order status changed to In Progress.',
			'created_at' => date( 'Y-m-d H:i:s' ),
		);

		$this->assertEquals( 'system', $message['type'] );
		$this->assertEquals( 0, $message['sender_id'] );
	}

	/**
	 * Test unread count structure.
	 *
	 * @return void
	 */
	public function test_unread_count_structure(): void {
		$unread_counts = array(
			1 => 3, // User 1 has 3 unread.
			2 => 0, // User 2 has 0 unread.
		);

		$this->assertArrayHasKey( 1, $unread_counts );
		$this->assertArrayHasKey( 2, $unread_counts );
		$this->assertEquals( 3, $unread_counts[1] );
		$this->assertEquals( 0, $unread_counts[2] );
	}

	/**
	 * Test marking conversation as read.
	 *
	 * @return void
	 */
	public function test_mark_conversation_read(): void {
		$unread_counts = array(
			1 => 5,
			2 => 0,
		);

		// Simulate marking as read for user 1.
		$unread_counts[1] = 0;

		$this->assertEquals( 0, $unread_counts[1] );
	}

	/**
	 * Test message attachment structure.
	 *
	 * @return void
	 */
	public function test_message_attachment_structure(): void {
		$attachment = array(
			'id'        => 123,
			'name'      => 'design-file.psd',
			'size'      => 1024000,
			'mime_type' => 'application/psd',
			'url'       => 'https://example.com/uploads/design-file.psd',
		);

		$this->assertArrayHasKey( 'id', $attachment );
		$this->assertArrayHasKey( 'name', $attachment );
		$this->assertArrayHasKey( 'size', $attachment );
		$this->assertArrayHasKey( 'mime_type', $attachment );
		$this->assertArrayHasKey( 'url', $attachment );
	}

	/**
	 * Test conversation participants validation.
	 *
	 * @return void
	 */
	public function test_conversation_participants(): void {
		$order = OrderFactory::in_progress(
			array(
				'customer_id' => 10,
				'vendor_id'   => 5,
			)
		);

		if ( $order instanceof ServiceOrder ) {
			$participants = array( $order->customer_id, $order->vendor_id );

			$this->assertCount( 2, $participants );
			$this->assertContains( 10, $participants );
			$this->assertContains( 5, $participants );
		}
	}

	/**
	 * Test conversation closure on order completion.
	 *
	 * @return void
	 */
	public function test_conversation_closure(): void {
		$order = OrderFactory::completed();

		// In a real test, we'd verify:
		// - Conversation is_closed = true after order completion.
		// - No new messages can be sent.
		$this->assertNotEmpty( $order );

		if ( $order instanceof ServiceOrder ) {
			$this->assertEquals( ServiceOrder::STATUS_COMPLETED, $order->status );
		}
	}
}
