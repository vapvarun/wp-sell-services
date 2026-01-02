<?php
/**
 * WooCommerce Order Provider
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

use WPSellServices\Integrations\Contracts\OrderProviderInterface;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\ServiceItem;

/**
 * Provides order functionality through WooCommerce.
 *
 * @since 1.0.0
 */
class WCOrderProvider implements OrderProviderInterface {

	/**
	 * Create service order from WooCommerce order.
	 *
	 * @param int $wc_order_id WooCommerce order ID.
	 * @return ServiceOrder|null
	 */
	public function create_from_platform_order( int $wc_order_id ): ?ServiceOrder {
		$wc_order = wc_get_order( $wc_order_id );

		if ( ! $wc_order ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Check each order item for service products.
		foreach ( $wc_order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();

			// Check if this product is a service.
			if ( ! $this->is_service_product( $product_id ) ) {
				continue;
			}

			$service_id = $this->get_service_id_from_product( $product_id );

			if ( ! $service_id ) {
				continue;
			}

			// Get service data.
			$service   = wpss_get_service( $service_id );
			$vendor_id = $service ? $service->vendor_id : 0;

			// Get selected package and addons from item meta.
			$package_id = (int) $item->get_meta( '_wpss_package_id' );
			$addons     = $item->get_meta( '_wpss_addons' ) ?: array();

			// Calculate totals.
			$subtotal     = (float) $item->get_subtotal();
			$addons_total = 0.0;

			// Get package details.
			$delivery_days = (int) get_post_meta( $service_id, '_wpss_delivery_days', true ) ?: 7;
			$revisions     = (int) get_post_meta( $service_id, '_wpss_revisions', true ) ?: 0;

			if ( $package_id ) {
				$package = $this->get_package_data( $service_id, $package_id );
				if ( $package ) {
					$delivery_days = $package['delivery_days'] ?? $delivery_days;
					$revisions     = $package['revisions'] ?? $revisions;
				}
			}

			// Add addon delivery days and calculate addon totals.
			if ( ! empty( $addons ) && is_array( $addons ) ) {
				foreach ( $addons as $addon ) {
					// Add extra delivery days from addon (can be negative for rush delivery).
					$delivery_days += (int) ( $addon['delivery_days_extra'] ?? 0 );

					// Calculate addon price contribution.
					$addons_total += (float) ( $addon['price'] ?? 0 );
				}

				// Ensure delivery days doesn't go below 1.
				$delivery_days = max( 1, $delivery_days );
			}

			// Calculate deadline.
			$deadline = new \DateTimeImmutable( '+' . $delivery_days . ' days' );

			// Create order record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'order_number'       => wpss_generate_order_number(),
					'customer_id'        => $wc_order->get_customer_id(),
					'vendor_id'          => $vendor_id,
					'service_id'         => $service_id,
					'package_id'         => $package_id ?: null,
					'addons'             => wp_json_encode( $addons ),
					'platform'           => 'woocommerce',
					'platform_order_id'  => $wc_order_id,
					'platform_item_id'   => $item_id,
					'subtotal'           => $subtotal,
					'addons_total'       => $addons_total,
					'total'              => $subtotal + $addons_total,
					'currency'           => $wc_order->get_currency(),
					'status'             => ServiceOrder::STATUS_PENDING_PAYMENT,
					'delivery_deadline'  => $deadline->format( 'Y-m-d H:i:s' ),
					'original_deadline'  => $deadline->format( 'Y-m-d H:i:s' ),
					'payment_method'     => $wc_order->get_payment_method(),
					'payment_status'     => 'pending',
					'revisions_included' => $revisions,
					'created_at'         => current_time( 'mysql' ),
					'updated_at'         => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			$order_id = (int) $wpdb->insert_id;

			if ( $order_id ) {
				// Store service order ID in WC item meta.
				wc_update_order_item_meta( $item_id, '_wpss_order_id', $order_id );

				return wpss_get_order( $order_id );
			}
		}

		return null;
	}

	/**
	 * Get service order by platform order ID.
	 *
	 * @param int $platform_order_id Platform order ID.
	 * @return ServiceOrder|null
	 */
	public function get_by_platform_order( int $platform_order_id ): ?ServiceOrder {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = 'woocommerce' AND platform_order_id = %d LIMIT 1",
				$platform_order_id
			)
		);

		return $row ? ServiceOrder::from_db( $row ) : null;
	}

	/**
	 * Sync order status with platform.
	 *
	 * @param ServiceOrder $order Service order.
	 * @return bool
	 */
	public function sync_status( ServiceOrder $order ): bool {
		if ( 'woocommerce' !== $order->platform || ! $order->platform_order_id ) {
			return false;
		}

		$wc_order = wc_get_order( $order->platform_order_id );

		if ( ! $wc_order ) {
			return false;
		}

		// Map WC status to service order status.
		$wc_status = $wc_order->get_status();

		switch ( $wc_status ) {
			case 'pending':
			case 'on-hold':
				$this->update_status( $order->id, ServiceOrder::STATUS_PENDING_PAYMENT );
				break;

			case 'processing':
			case 'completed':
				if ( ServiceOrder::STATUS_PENDING_PAYMENT === $order->status ) {
					$this->update_status( $order->id, ServiceOrder::STATUS_PENDING_REQUIREMENTS );
					$this->mark_as_paid( $order->id, $wc_order->get_transaction_id() );
				}
				break;

			case 'cancelled':
			case 'failed':
			case 'refunded':
				$this->update_status( $order->id, ServiceOrder::STATUS_CANCELLED );
				break;
		}

		return true;
	}

	/**
	 * Get payment status from platform.
	 *
	 * @param ServiceOrder $order Service order.
	 * @return string
	 */
	public function get_payment_status( ServiceOrder $order ): string {
		if ( 'woocommerce' !== $order->platform || ! $order->platform_order_id ) {
			return $order->payment_status;
		}

		$wc_order = wc_get_order( $order->platform_order_id );

		if ( ! $wc_order ) {
			return $order->payment_status;
		}

		if ( $wc_order->is_paid() ) {
			return 'paid';
		}

		$wc_status = $wc_order->get_status();

		if ( in_array( $wc_status, array( 'cancelled', 'failed' ), true ) ) {
			return 'failed';
		}

		if ( 'refunded' === $wc_status ) {
			return 'refunded';
		}

		return 'pending';
	}

	/**
	 * Process refund through platform.
	 *
	 * @param ServiceOrder $order  Service order.
	 * @param float        $amount Refund amount.
	 * @param string       $reason Refund reason.
	 * @return bool
	 */
	public function process_refund( ServiceOrder $order, float $amount, string $reason = '' ): bool {
		if ( 'woocommerce' !== $order->platform || ! $order->platform_order_id ) {
			return false;
		}

		$wc_order = wc_get_order( $order->platform_order_id );

		if ( ! $wc_order ) {
			return false;
		}

		$refund = wc_create_refund(
			array(
				'amount'   => $amount,
				'reason'   => $reason,
				'order_id' => $order->platform_order_id,
			)
		);

		if ( is_wp_error( $refund ) ) {
			wpss_log( 'Refund failed: ' . $refund->get_error_message(), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Get orders URL.
	 *
	 * @return string
	 */
	public function get_orders_url(): string {
		$page_id = wc_get_page_id( 'myaccount' );

		if ( ! $page_id ) {
			return home_url( '/my-account/orders/' );
		}

		return wc_get_endpoint_url( 'orders', '', get_permalink( $page_id ) );
	}

	/**
	 * Get single order view URL.
	 *
	 * @param ServiceOrder $order Service order.
	 * @return string
	 */
	public function get_order_url( ServiceOrder $order ): string {
		return home_url( '/service-order/' . $order->id . '/' );
	}

	/**
	 * Update order status.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   New status.
	 * @return bool
	 */
	private function update_status( int $order_id, string $status ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark order as paid.
	 *
	 * @param int    $order_id       Order ID.
	 * @param string $transaction_id Transaction ID.
	 * @return bool
	 */
	private function mark_as_paid( int $order_id, string $transaction_id = '' ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'payment_status' => 'paid',
				'transaction_id' => $transaction_id,
				'paid_at'        => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $order_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Check if product is a service.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_service_product( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_wpss_is_service', true );
	}

	/**
	 * Get service ID from WC product.
	 *
	 * @param int $product_id WC product ID.
	 * @return int|null
	 */
	private function get_service_id_from_product( int $product_id ): ?int {
		$service_id = get_post_meta( $product_id, '_wpss_service_id', true );
		return $service_id ? (int) $service_id : null;
	}

	/**
	 * Get package data from service.
	 *
	 * @param int $service_id Service ID.
	 * @param int $package_id Package ID.
	 * @return array|null
	 */
	private function get_package_data( int $service_id, int $package_id ): ?array {
		$packages = get_post_meta( $service_id, '_wpss_packages', true ) ?: array();

		// Convert to indexed array and match by position (package_id is the index).
		$packages = array_values( $packages );

		return $packages[ $package_id ] ?? null;
	}

	/**
	 * Get order by WPSS order ID.
	 *
	 * @param int $order_id WPSS order ID.
	 * @return ServiceOrder|null
	 */
	public function get_order( int $order_id ): ?ServiceOrder {
		return wpss_get_order( $order_id );
	}

	/**
	 * Get order item by platform item ID.
	 *
	 * @param int $item_id WooCommerce item ID.
	 * @return ServiceItem|null
	 */
	public function get_order_item( int $item_id ): ?ServiceItem {
		$wpss_order_id = wc_get_order_item_meta( $item_id, '_wpss_order_id', true );

		if ( ! $wpss_order_id ) {
			return null;
		}

		$order = $this->get_order( (int) $wpss_order_id );

		if ( ! $order ) {
			return null;
		}

		return new ServiceItem(
			$item_id,
			$order->service_id,
			$order->package_id,
			$order->vendor_id,
			(float) $order->total,
			1
		);
	}

	/**
	 * Get orders for a customer.
	 *
	 * @param int   $user_id Customer user ID.
	 * @param array $args    Query arguments.
	 * @return ServiceOrder[]
	 */
	public function get_customer_orders( int $user_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'WHERE customer_id = %d AND platform = %s', $user_id, 'woocommerce' );

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( array( ServiceOrder::class, 'from_db' ), $rows ?: array() );
	}

	/**
	 * Get orders for a vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return ServiceOrder[]
	 */
	public function get_vendor_orders( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'WHERE vendor_id = %d AND platform = %s', $vendor_id, 'woocommerce' );

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( array( ServiceOrder::class, 'from_db' ), $rows ?: array() );
	}

	/**
	 * Check if WC order has service items.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	public function has_service_items( int $order_id ): bool {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return false;
		}

		foreach ( $wc_order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( $this->is_service_product( $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all service items from WC order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return ServiceItem[]
	 */
	public function get_service_items( int $order_id ): array {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return array();
		}

		$items = array();

		foreach ( $wc_order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();

			if ( ! $this->is_service_product( $product_id ) ) {
				continue;
			}

			$service_id = $this->get_service_id_from_product( $product_id );

			if ( ! $service_id ) {
				continue;
			}

			$service   = wpss_get_service( $service_id );
			$vendor_id = $service ? $service->vendor_id : 0;

			$items[] = new ServiceItem(
				$item_id,
				$service_id,
				(int) $item->get_meta( '_wpss_package_id' ),
				$vendor_id,
				(float) $item->get_subtotal(),
				$item->get_quantity()
			);
		}

		return $items;
	}

	/**
	 * Update WC order item meta.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	public function update_item_meta( int $item_id, string $key, mixed $value ): bool {
		return (bool) wc_update_order_item_meta( $item_id, $key, $value );
	}

	/**
	 * Get WC order item meta.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return single value.
	 * @return mixed
	 */
	public function get_item_meta( int $item_id, string $key, bool $single = true ): mixed {
		return wc_get_order_item_meta( $item_id, $key, $single );
	}

	/**
	 * Get customer data from WC order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array{id: int, email: string, name: string}|null
	 */
	public function get_customer_data( int $order_id ): ?array {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return null;
		}

		return array(
			'id'    => $wc_order->get_customer_id(),
			'email' => $wc_order->get_billing_email(),
			'name'  => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
		);
	}

	/**
	 * Handle WC order completion.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_complete( int $order_id ): void {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return;
		}

		// Check if we already processed this.
		if ( $wc_order->get_meta( '_wpss_processed' ) ) {
			return;
		}

		// Process service items.
		foreach ( $wc_order->get_items() as $item_id => $item ) {
			$wpss_order_id = $item->get_meta( '_wpss_order_id' );

			if ( ! $wpss_order_id ) {
				// Create WPSS order for this item.
				$this->create_from_platform_order( $order_id );
				break;
			}

			// Update existing order to paid status.
			$wpss_order = $this->get_order( (int) $wpss_order_id );

			if ( $wpss_order && ServiceOrder::STATUS_PENDING_PAYMENT === $wpss_order->status ) {
				$this->mark_as_paid( (int) $wpss_order_id, $wc_order->get_transaction_id() );
				$this->update_status( (int) $wpss_order_id, ServiceOrder::STATUS_PENDING_REQUIREMENTS );
			}
		}

		// Mark as processed.
		$wc_order->update_meta_data( '_wpss_processed', true );
		$wc_order->save();
	}
}
