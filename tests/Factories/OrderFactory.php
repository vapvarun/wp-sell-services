<?php
/**
 * Order Factory for testing.
 *
 * @package WPSellServices\Tests\Factories
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Factories;

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use DateTimeImmutable;

/**
 * Creates test orders at various stages of the workflow.
 */
class OrderFactory {

	/**
	 * Counter for unique order generation.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Create an order pending payment.
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function pending_payment( array $attrs = array() ): ServiceOrder|array {
		return self::create(
			array_merge(
				array(
					'status' => ServiceOrder::STATUS_PENDING_PAYMENT,
				),
				$attrs
			)
		);
	}

	/**
	 * Create an order pending requirements (paid, waiting for buyer input).
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function pending_requirements( array $attrs = array() ): ServiceOrder|array {
		return self::create(
			array_merge(
				array(
					'status'         => ServiceOrder::STATUS_PENDING_REQUIREMENTS,
					'payment_status' => 'completed',
					'paid_at'        => new DateTimeImmutable( '-1 hour' ),
				),
				$attrs
			)
		);
	}

	/**
	 * Create an order in progress (vendor working).
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function in_progress( array $attrs = array() ): ServiceOrder|array {
		$now     = new DateTimeImmutable();
		$started = $now->modify( '-2 days' );

		return self::create(
			array_merge(
				array(
					'status'            => ServiceOrder::STATUS_IN_PROGRESS,
					'payment_status'    => 'completed',
					'paid_at'           => $started->modify( '-1 hour' ),
					'started_at'        => $started,
					'delivery_deadline' => $started->modify( '+5 days' ),
					'original_deadline' => $started->modify( '+5 days' ),
				),
				$attrs
			)
		);
	}

	/**
	 * Create an order pending approval (delivered, waiting for buyer).
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function pending_approval( array $attrs = array() ): ServiceOrder|array {
		$now     = new DateTimeImmutable();
		$started = $now->modify( '-4 days' );

		return self::create(
			array_merge(
				array(
					'status'            => ServiceOrder::STATUS_PENDING_APPROVAL,
					'payment_status'    => 'completed',
					'paid_at'           => $started->modify( '-1 hour' ),
					'started_at'        => $started,
					'delivery_deadline' => $started->modify( '+5 days' ),
					'original_deadline' => $started->modify( '+5 days' ),
				),
				$attrs
			)
		);
	}

	/**
	 * Create an order with revision requested.
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function revision_requested( array $attrs = array() ): ServiceOrder|array {
		$now     = new DateTimeImmutable();
		$started = $now->modify( '-5 days' );

		return self::create(
			array_merge(
				array(
					'status'            => ServiceOrder::STATUS_REVISION_REQUESTED,
					'payment_status'    => 'completed',
					'paid_at'           => $started->modify( '-1 hour' ),
					'started_at'        => $started,
					'delivery_deadline' => $started->modify( '+5 days' ),
					'original_deadline' => $started->modify( '+5 days' ),
					'revisions_used'    => 1,
				),
				$attrs
			)
		);
	}

	/**
	 * Create a completed order.
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function completed( array $attrs = array() ): ServiceOrder|array {
		$now       = new DateTimeImmutable();
		$started   = $now->modify( '-7 days' );
		$completed = $now->modify( '-1 day' );

		return self::create(
			array_merge(
				array(
					'status'            => ServiceOrder::STATUS_COMPLETED,
					'payment_status'    => 'completed',
					'paid_at'           => $started->modify( '-1 hour' ),
					'started_at'        => $started,
					'completed_at'      => $completed,
					'delivery_deadline' => $started->modify( '+5 days' ),
					'original_deadline' => $started->modify( '+5 days' ),
				),
				$attrs
			)
		);
	}

	/**
	 * Create a cancelled order.
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function cancelled( array $attrs = array() ): ServiceOrder|array {
		return self::create(
			array_merge(
				array(
					'status' => ServiceOrder::STATUS_CANCELLED,
				),
				$attrs
			)
		);
	}

	/**
	 * Create a disputed order.
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function disputed( array $attrs = array() ): ServiceOrder|array {
		$now     = new DateTimeImmutable();
		$started = $now->modify( '-10 days' );

		return self::create(
			array_merge(
				array(
					'status'            => ServiceOrder::STATUS_DISPUTED,
					'payment_status'    => 'completed',
					'paid_at'           => $started->modify( '-1 hour' ),
					'started_at'        => $started,
					'delivery_deadline' => $started->modify( '+5 days' ),
					'original_deadline' => $started->modify( '+5 days' ),
				),
				$attrs
			)
		);
	}

	/**
	 * Create a late order (past deadline).
	 *
	 * @param array $attrs Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function late( array $attrs = array() ): ServiceOrder|array {
		$now     = new DateTimeImmutable();
		$started = $now->modify( '-10 days' );

		return self::create(
			array_merge(
				array(
					'status'            => ServiceOrder::STATUS_LATE,
					'payment_status'    => 'completed',
					'paid_at'           => $started->modify( '-1 hour' ),
					'started_at'        => $started,
					'delivery_deadline' => $started->modify( '+5 days' ), // 5 days ago.
					'original_deadline' => $started->modify( '+5 days' ),
				),
				$attrs
			)
		);
	}

	/**
	 * Create an order with conversation messages.
	 *
	 * @param int   $message_count Number of messages.
	 * @param array $attrs         Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function with_messages( int $message_count = 5, array $attrs = array() ): ServiceOrder|array {
		$order = self::in_progress( $attrs );

		// In real integration tests, would create messages here.
		// For now, just return the order.
		return $order;
	}

	/**
	 * Create an order with specific addons selected.
	 *
	 * @param array $addon_ids Array of addon IDs with quantities.
	 * @param array $attrs     Override attributes.
	 * @return ServiceOrder|array
	 */
	public static function with_addons( array $addon_ids, array $attrs = array() ): ServiceOrder|array {
		$addons = array();
		foreach ( $addon_ids as $id => $quantity ) {
			$addons[] = array(
				'id'       => $id,
				'quantity' => $quantity,
			);
		}

		return self::create(
			array_merge(
				array(
					'addons'       => $addons,
					'addons_total' => 49.99, // Would be calculated from actual addons.
				),
				$attrs
			)
		);
	}

	/**
	 * Create a base order with given attributes.
	 *
	 * @param array $attrs Order attributes.
	 * @return ServiceOrder|array
	 */
	private static function create( array $attrs ): ServiceOrder|array {
		++self::$counter;

		$now = new DateTimeImmutable();

		$defaults = array(
			'id'                 => self::$counter,
			'order_number'       => 'WPSS-' . str_pad( (string) self::$counter, 6, '0', STR_PAD_LEFT ),
			'customer_id'        => 2,
			'vendor_id'          => 1,
			'service_id'         => 1,
			'package_id'         => 1,
			'addons'             => array(),
			'platform'           => 'standalone',
			'platform_order_id'  => null,
			'platform_item_id'   => null,
			'subtotal'           => 79.99,
			'addons_total'       => 0.0,
			'total'              => 79.99,
			'currency'           => 'USD',
			'status'             => ServiceOrder::STATUS_PENDING_PAYMENT,
			'delivery_deadline'  => null,
			'original_deadline'  => null,
			'payment_method'     => 'stripe',
			'payment_status'     => 'pending',
			'transaction_id'     => null,
			'paid_at'            => null,
			'revisions_included' => 3,
			'revisions_used'     => 0,
			'created_at'         => $now,
			'updated_at'         => $now,
			'started_at'         => null,
			'completed_at'       => null,
		);

		$data = array_merge( $defaults, $attrs );

		// Calculate total if addons provided.
		if ( ! empty( $data['addons'] ) && $data['addons_total'] > 0 ) {
			$data['total'] = $data['subtotal'] + $data['addons_total'];
		}

		// If OrderService is available, use it.
		if ( class_exists( OrderService::class ) && ! defined( 'WPSS_STUB_MODE' ) ) {
			try {
				// Would use OrderService to create real order.
				// For now, create ServiceOrder directly.
			} catch ( \Throwable $e ) {
				// Fall through.
			}
		}

		// Create ServiceOrder object or return array.
		if ( class_exists( ServiceOrder::class ) ) {
			$order = new ServiceOrder();
			foreach ( $data as $key => $value ) {
				if ( property_exists( $order, $key ) ) {
					$order->$key = $value;
				}
			}
			return $order;
		}

		return $data;
	}

	/**
	 * Reset the counter (for test isolation).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$counter = 0;
	}
}
