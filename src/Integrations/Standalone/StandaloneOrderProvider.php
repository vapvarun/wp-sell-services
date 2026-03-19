<?php
/**
 * Standalone Order Provider
 *
 * @package WPSellServices\Integrations\Standalone
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Integrations\Standalone;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Integrations\Contracts\OrderProviderInterface;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\ServiceItem;
use WPSellServices\Services\CommissionService;

/**
 * Order provider for standalone mode.
 *
 * @since 1.0.0
 */
class StandaloneOrderProvider implements OrderProviderInterface {

	/**
	 * Create service order from payment.
	 *
	 * @param array $order_data Order data.
	 * @return ServiceOrder|null
	 */
	public function create_order( array $order_data ): ?ServiceOrder {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$service = wpss_get_service( (int) $order_data['service_id'] );

		if ( ! $service ) {
			return null;
		}

		// Calculate totals.
		$subtotal     = (float) ( $order_data['subtotal'] ?? 0 );
		$addons_total = (float) ( $order_data['addons_total'] ?? 0 );
		$total        = $subtotal + $addons_total;

		// Apply tax.
		$tax_settings = get_option( 'wpss_tax', [] );
		$tax_enabled  = ! empty( $tax_settings['enable_tax'] );
		$tax_rate     = $tax_enabled ? (float) ( $tax_settings['tax_rate'] ?? 0 ) : 0;
		$tax_included = ! empty( $tax_settings['tax_included'] );

		/**
		 * Filters the tax rate applied at checkout.
		 *
		 * @since 1.1.0
		 *
		 * @param float $tax_rate   Site-wide tax rate from settings.
		 * @param int   $vendor_id  Vendor user ID.
		 * @param int   $service_id Service post ID.
		 */
		$tax_rate   = (float) apply_filters( 'wpss_checkout_tax_rate', $tax_rate, $service->vendor_id, $service->id );
		$tax_amount = 0;

		if ( $tax_rate > 0 ) {
			if ( $tax_included ) {
				$tax_amount = $total - ( $total / ( 1 + $tax_rate / 100 ) );
			} else {
				$tax_amount = $total * ( $tax_rate / 100 );
				$total     += $tax_amount;
			}
		}

		// Get delivery info.
		$delivery_days = (int) ( $order_data['delivery_days'] ?? 7 );
		$revisions = (int) ( $order_data['revisions'] ?? 0 );

		// Pre-calculate commission rate so order details can display expected earnings.
		$commission_rate = CommissionService::get_global_commission_rate();

		// Check for vendor-specific commission rate.
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$vendor_rate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT custom_commission_rate FROM {$profiles_table} WHERE user_id = %d",
				$service->vendor_id
			)
		);
		if ( null !== $vendor_rate && '' !== $vendor_rate ) {
			$commission_rate = (float) $vendor_rate;
		}

		$platform_fee    = round( $total * ( $commission_rate / 100 ), 2 );
		$vendor_earnings = round( $total - $platform_fee, 2 );

		// Snapshot the package data at order creation time so it's immune to later edits.
		$package_snapshot = null;
		if ( $service && ! empty( $order_data['package_id'] ) ) {
			$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: [];
			if ( isset( $packages[ $order_data['package_id'] ] ) ) {
				$package_snapshot = $packages[ $order_data['package_id'] ];
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'order_number'        => wpss_generate_order_number(),
				'customer_id'         => (int) $order_data['customer_id'],
				'vendor_id'           => $service->vendor_id,
				'service_id'          => $service->id,
				'package_id'          => ! empty( $order_data['package_id'] ) ? (int) $order_data['package_id'] : null,
				'addons'              => wp_json_encode( $order_data['addons'] ?? [] ),
				'platform'            => 'standalone',
				'platform_order_id'   => null,
				'platform_item_id'    => null,
				'subtotal'            => $subtotal,
				'addons_total'        => $addons_total,
				'total'               => $total,
				'currency'            => $order_data['currency'] ?? wpss_get_currency(),
				'status'              => ServiceOrder::STATUS_PENDING_PAYMENT,
				'payment_method'      => $order_data['payment_method'] ?? null,
				'payment_status'      => 'pending',
				'revisions_included'  => $revisions,
				'commission_rate'     => $commission_rate,
				'platform_fee'        => $platform_fee,
				'vendor_earnings'     => $vendor_earnings,
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
				'meta'                => wp_json_encode( array_filter( [
					'tax_rate'         => $tax_rate,
					'tax_amount'       => round( $tax_amount, 2 ),
					'package_snapshot' => $package_snapshot,
				] ) ),
			],
			[ '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%s', '%s', '%s' ]
		);

		$order_id = (int) $wpdb->insert_id;

		if ( $order_id ) {
			/**
			 * Fires after a standalone order is created.
			 *
			 * Pro uses this for recurring service subscription creation.
			 *
			 * @since 1.1.0
			 *
			 * @param int   $order_id   The new order ID.
			 * @param array $order_data The order creation data.
			 */
			do_action( 'wpss_order_created', $order_id, $order_data );
		}

		return $order_id ? wpss_get_order( $order_id ) : null;
	}

	/**
	 * Create service order from platform order (not used in standalone).
	 *
	 * @param int $platform_order_id Platform order ID.
	 * @return ServiceOrder|null
	 */
	public function create_from_platform_order( int $platform_order_id ): ?ServiceOrder {
		return null;
	}

	/**
	 * Get service order by platform order ID (not used in standalone).
	 *
	 * @param int $platform_order_id Platform order ID.
	 * @return ServiceOrder|null
	 */
	public function get_by_platform_order( int $platform_order_id ): ?ServiceOrder {
		return null;
	}

	/**
	 * Sync order status with platform (not used in standalone).
	 *
	 * @param ServiceOrder $order Service order.
	 * @return bool
	 */
	public function sync_status( ServiceOrder $order ): bool {
		return true;
	}

	/**
	 * Get payment status.
	 *
	 * @param ServiceOrder $order Service order.
	 * @return string
	 */
	public function get_payment_status( ServiceOrder $order ): string {
		return $order->payment_status;
	}

	/**
	 * Process refund.
	 *
	 * @param ServiceOrder $order  Service order.
	 * @param float        $amount Refund amount.
	 * @param string       $reason Refund reason.
	 * @return bool
	 */
	public function process_refund( ServiceOrder $order, float $amount, string $reason = '' ): bool {
		if ( empty( $order->transaction_id ) ) {
			return false;
		}

		// Get the payment gateway used.
		$gateway_id = $order->payment_method;
		$gateways = apply_filters( 'wpss_payment_gateways', [] );

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return false;
		}

		$gateway = $gateways[ $gateway_id ];
		$result = $gateway->process_refund( $order->transaction_id, $amount, $reason );

		return ! empty( $result['success'] );
	}

	/**
	 * Get orders URL.
	 *
	 * @return string
	 */
	public function get_orders_url(): string {
		return add_query_arg( 'section', 'orders', wpss_get_page_url( 'dashboard' ) );
	}

	/**
	 * Get single order view URL.
	 *
	 * @param ServiceOrder $order Service order.
	 * @return string
	 */
	public function get_order_url( ServiceOrder $order ): string {
		return wpss_get_order_url( $order->id );
	}

	/**
	 * Mark order as paid.
	 *
	 * @param int    $order_id       Order ID.
	 * @param string $transaction_id Transaction ID.
	 * @param string $payment_method Payment method.
	 * @return bool
	 */
	public function mark_as_paid( int $order_id, string $transaction_id, string $payment_method ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Get current order.
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Calculate delivery deadline only if not already set (e.g., by convert_to_order).
		$update_data = [
			'status'         => ServiceOrder::STATUS_PENDING_REQUIREMENTS,
			'payment_status' => 'paid',
			'payment_method' => $payment_method,
			'transaction_id' => $transaction_id,
			'paid_at'        => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		];

		if ( empty( $order->delivery_deadline ) ) {
			$delivery_days = 7;
			$service       = $order->get_service();
			if ( $service ) {
				$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: [];
				if ( isset( $packages[ $order->package_id ] ) ) {
					$delivery_days = (int) ( $packages[ $order->package_id ]['delivery_days'] ?? 7 );
				}
			}

			$deadline                      = new \DateTimeImmutable( '+' . $delivery_days . ' days' );
			$update_data['delivery_deadline'] = $deadline->format( 'Y-m-d H:i:s' );
			$update_data['original_deadline'] = $deadline->format( 'Y-m-d H:i:s' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$update_data,
			[ 'id' => $order_id ]
		);

		if ( $result ) {
			// Fire status change hooks so notifications and workflows trigger.
			do_action( 'wpss_order_status_changed', $order_id, ServiceOrder::STATUS_PENDING_REQUIREMENTS, ServiceOrder::STATUS_PENDING_PAYMENT );
			do_action( 'wpss_order_status_pending_requirements', $order_id, ServiceOrder::STATUS_PENDING_PAYMENT );

			/**
			 * Fires when an order is marked as paid.
			 *
			 * @param int    $order_id       Order ID.
			 * @param string $transaction_id Transaction ID.
			 */
			do_action( 'wpss_order_paid', $order_id, $transaction_id );
		}

		return (bool) $result;
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
	 * @param int $item_id Item ID (same as order ID in standalone).
	 * @return ServiceItem|null
	 */
	public function get_order_item( int $item_id ): ?ServiceItem {
		$order = $this->get_order( $item_id );

		if ( ! $order ) {
			return null;
		}

		$item = new ServiceItem();
		$item->id         = $order->id;
		$item->item_id    = $order->id;
		$item->order_id   = $order->id;
		$item->service_id = $order->service_id;
		$item->package_id = $order->package_id ?? 0;
		$item->vendor_id  = $order->vendor_id;
		$item->price      = (float) $order->subtotal;
		$item->total      = (float) $order->total;
		$item->quantity   = 1;

		return $item;
	}

	/**
	 * Get orders for a customer.
	 *
	 * @param int   $user_id Customer user ID.
	 * @param array $args    Query arguments.
	 * @return ServiceOrder[]
	 */
	public function get_customer_orders( int $user_id, array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = [
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		];
		$args     = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'WHERE customer_id = %d AND platform = %s', $user_id, 'standalone' );

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

		return array_map( [ ServiceOrder::class, 'from_db' ], $rows ?: [] );
	}

	/**
	 * Get orders for a vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return ServiceOrder[]
	 */
	public function get_vendor_orders( int $vendor_id, array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = [
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		];
		$args     = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'WHERE vendor_id = %d AND platform = %s', $vendor_id, 'standalone' );

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

		return array_map( [ ServiceOrder::class, 'from_db' ], $rows ?: [] );
	}

	/**
	 * Check if order contains service items.
	 *
	 * In standalone mode, all orders are service orders.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function has_service_items( int $order_id ): bool {
		$order = $this->get_order( $order_id );
		return $order !== null;
	}

	/**
	 * Get all service items from an order.
	 *
	 * In standalone mode, each order has one service item.
	 *
	 * @param int $order_id Order ID.
	 * @return ServiceItem[]
	 */
	public function get_service_items( int $order_id ): array {
		$item = $this->get_order_item( $order_id );
		return $item ? [ $item ] : [];
	}

	/**
	 * Ensure order meta table exists.
	 *
	 * @return void
	 */
	private function maybe_create_meta_table(): void {
		if ( get_option( 'wpss_order_meta_table_version' ) === '1.0' ) {
			return;
		}

		global $wpdb;
		$table           = $wpdb->prefix . 'wpss_order_meta';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext,
			PRIMARY KEY (meta_id),
			KEY order_id (order_id),
			KEY meta_key (meta_key(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'wpss_order_meta_table_version', '1.0' );
	}

	/**
	 * Update order item meta.
	 *
	 * @param int    $item_id Item ID (same as order ID).
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	public function update_item_meta( int $item_id, string $key, mixed $value ): bool {
		$this->maybe_create_meta_table();

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_meta';

		// Check if meta exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$table} WHERE order_id = %d AND meta_key = %s LIMIT 1",
				$item_id,
				$key
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (bool) $wpdb->update(
				$table,
				[ 'meta_value' => maybe_serialize( $value ) ],
				[ 'meta_id' => $existing ]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (bool) $wpdb->insert(
			$table,
			[
				'order_id'   => $item_id,
				'meta_key'   => $key,
				'meta_value' => maybe_serialize( $value ),
			]
		);
	}

	/**
	 * Get order item meta.
	 *
	 * @param int    $item_id Item ID (same as order ID).
	 * @param string $key     Meta key.
	 * @param bool   $single  Return single value.
	 * @return mixed
	 */
	public function get_item_meta( int $item_id, string $key, bool $single = true ): mixed {
		$this->maybe_create_meta_table();

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_meta';

		if ( $single ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$table} WHERE order_id = %d AND meta_key = %s LIMIT 1",
					$item_id,
					$key
				)
			);

			return $value ? maybe_unserialize( $value ) : '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table} WHERE order_id = %d AND meta_key = %s",
				$item_id,
				$key
			)
		);

		return array_map( 'maybe_unserialize', $rows ?: [] );
	}

	/**
	 * Get customer data from order.
	 *
	 * @param int $order_id Order ID.
	 * @return array{id: int, email: string, name: string}|null
	 */
	public function get_customer_data( int $order_id ): ?array {
		$order = $this->get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		$user = get_userdata( $order->customer_id );

		if ( ! $user ) {
			return null;
		}

		return [
			'id'    => $user->ID,
			'email' => $user->user_email,
			'name'  => $user->display_name,
		];
	}

	/**
	 * Handle order completion callback.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_complete( int $order_id ): void {
		$order = $this->get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		/**
		 * Fires when a standalone order is completed.
		 *
		 * @param ServiceOrder $order The completed order.
		 */
		do_action( 'wpss_standalone_order_complete', $order );
	}
}
