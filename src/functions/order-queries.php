<?php
/**
 * Order lists, counts and revenue aggregates.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get total order count for a user (as customer).
 *
 * @since 1.2.0
 *
 * @param int $user_id User ID.
 * @return int Order count.
 */
function wpss_get_user_order_count( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d",
			$user_id
		)
	);
}

/**
 * Get active order count for a user (as customer).
 *
 * Active orders are those not in completed, cancelled, or refunded status.
 *
 * @since 1.2.0
 *
 * @param int $user_id User ID.
 * @return int Active order count.
 */
function wpss_get_user_active_order_count( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND status NOT IN ('completed', 'cancelled', 'refunded')",
			$user_id
		)
	);
}

/**
 * Get orders for a user (as customer).
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, status).
 * @return array Array of order objects.
 */
function wpss_get_user_orders( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE customer_id = %d AND " . wpss_order_rows_sql();
	$params = array( $user_id );

	if ( ! empty( $args['status'] ) ) {
		$sql     .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	// Return hydrated models, never raw rows. $wpdb hands back every column as a
	// string, and the callers are strict_types files that pass these values into
	// typed helpers - `wpss_format_price( float $price, ... )` threw
	// "must be of type float, string given" and white-screened [wpss_account]
	// for any logged-in user with at least one order.
	return array_map( array( \WPSellServices\Models\ServiceOrder::class, 'from_db' ), $rows ?: array() );
}

/**
 * Get orders for a vendor.
 *
 * @since 1.2.0
 *
 * @param int   $vendor_id Vendor user ID.
 * @param array $args      Query arguments (limit, offset, status).
 * @return array Array of order objects.
 */
function wpss_get_vendor_orders( int $vendor_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE vendor_id = %d AND " . wpss_order_rows_sql();
	$params = array( $vendor_id );

	if ( ! empty( $args['status'] ) ) {
		$sql     .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	// Return hydrated models, never raw rows. $wpdb hands back every column as a
	// string, and the callers are strict_types files that pass these values into
	// typed helpers - `wpss_format_price( float $price, ... )` threw
	// "must be of type float, string given" and white-screened [wpss_account]
	// for any logged-in user with at least one order.
	return array_map( array( \WPSellServices\Models\ServiceOrder::class, 'from_db' ), $rows ?: array() );
}

/**
 * Count a buyer's orders.
 *
 * A dedicated COUNT(*) so a caller never fetches rows just to size a list.
 * Pairs with wpss_get_user_orders(), which already takes limit + offset but had
 * no way to learn the total - so nothing built on it could paginate, and
 * [wpss_my_orders] simply showed the first 20 with no route to the rest.
 *
 * @since 1.5.1
 *
 * @param int    $user_id Customer user ID.
 * @param string $status  Optional. Restrict to one order status.
 * @return int Number of matching orders.
 */
function wpss_count_user_orders( int $user_id, string $status = '' ): int {
	return wpss_count_orders_for( 'customer_id', $user_id, $status );
}

/**
 * Count a vendor's orders.
 *
 * @since 1.5.1
 *
 * @param int    $vendor_id Vendor user ID.
 * @param string $status    Optional. Restrict to one order status.
 * @return int Number of matching orders.
 */
function wpss_count_vendor_orders( int $vendor_id, string $status = '' ): int {
	return wpss_count_orders_for( 'vendor_id', $vendor_id, $status );
}

/**
 * Shared COUNT(*) behind the two order-count helpers.
 *
 * A column name cannot be bound with a placeholder, so it is chosen from a
 * fixed allow-list instead of being interpolated from the caller. Anything else
 * returns 0 rather than reaching the query.
 *
 * @since 1.5.1
 *
 * @param string $column  Either 'customer_id' or 'vendor_id'.
 * @param int    $user_id User ID to match.
 * @param string $status  Optional. Restrict to one order status.
 * @return int Number of matching orders.
 */
function wpss_count_orders_for( string $column, int $user_id, string $status = '' ): int {
	global $wpdb;

	if ( ! in_array( $column, array( 'customer_id', 'vendor_id' ), true ) ) {
		return 0;
	}

	$table    = $wpdb->prefix . 'wpss_orders';
	$is_order = wpss_order_rows_sql(); // Tips, extensions and milestones are not orders of their own.

	if ( '' !== $status ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed above; both values are bound.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d AND status = %s AND {$is_order}", $user_id, $status );
	} else {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed above; the value is bound.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d AND {$is_order}", $user_id );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is the prepared statement built above.
	return (int) $wpdb->get_var( $sql );
}

/**
 * Marketplace-wide order aggregates for the admin dashboard.
 *
 * One full-table scan cached for five minutes; dropped the moment an order is
 * created, paid or changes status so the tiles never show a stale count for
 * longer than it takes to reload the page.
 *
 * @since 1.7.1
 *
 * @return object{total:int,in_progress:int,completed:int,pending:int,revenue:float,commission:float}
 */
function wpss_get_order_aggregates(): object {
	$cached = get_transient( 'wpss_order_aggregates' );

	if ( is_object( $cached ) ) {
		return $cached;
	}

	$aggregates = ( new \WPSellServices\Database\Repositories\OrderRepository() )->get_aggregates();
	set_transient( 'wpss_order_aggregates', $aggregates, 5 * MINUTE_IN_SECONDS );

	return $aggregates;
}

/**
 * Revenue - what buyers paid, net of refunds - by the one definition.
 *
 * Every revenue figure shown anywhere comes from here; see
 * OrderRepository::get_revenue() for the rule and the arguments. Ungrouped,
 * the result is one row; read `[0]`.
 *
 * @since 1.8.0
 *
 * @param array<string, mixed> $args from, to, vendor_id, group_by, limit.
 * @return array<int, object{key:string,orders:int,gross:float,refunded:float,revenue:float,commission:float,vendor_earnings:float}>
 */
function wpss_get_revenue( array $args = array() ): array {
	return ( new \WPSellServices\Database\Repositories\OrderRepository() )->get_revenue( $args );
}

/**
 * Revenue per day across a date range, with every day present.
 *
 * Days with no paid orders are 0 rather than missing, so a chart spaces its
 * points by time and a curve never invents a value between them. The one
 * series behind the admin Analytics chart, its REST route and the vendor
 * Analytics chart.
 *
 * @since 1.8.0
 *
 * @param string               $from First day (Y-m-d).
 * @param string               $to   Last day (Y-m-d).
 * @param array<string, mixed> $args Other wpss_get_revenue() filters (vendor_id, platform).
 * @return array{labels:string[],revenue:float[],commission:float[],vendor_earnings:float[],orders:int[]}
 */
function wpss_get_revenue_series( string $from, string $to, array $args = array() ): array {
	$rows = wpss_get_revenue(
		array(
			'from'     => $from . ' 00:00:00',
			'to'       => $to . ' 23:59:59',
			'group_by' => 'day',
		) + $args
	);

	$by_day = array();
	foreach ( $rows as $row ) {
		$by_day[ $row->key ] = $row;
	}

	$series = array(
		'labels'          => array(),
		'revenue'         => array(),
		'commission'      => array(),
		'vendor_earnings' => array(),
		'orders'          => array(),
	);

	$last = strtotime( $to );

	for ( $day = strtotime( $from ); $day <= $last; $day = strtotime( '+1 day', $day ) ) {
		$key                         = gmdate( 'Y-m-d', $day );
		$row                         = $by_day[ $key ] ?? null;
		$series['labels'][]          = $key;
		$series['revenue'][]         = $row ? $row->revenue : 0.0;
		$series['commission'][]      = $row ? $row->commission : 0.0;
		$series['vendor_earnings'][] = $row ? $row->vendor_earnings : 0.0;
		$series['orders'][]          = $row ? $row->orders : 0;
	}

	return $series;
}

/**
 * One order's part of revenue: the PHP twin of OrderRepository::get_revenue().
 *
 * For rendering a single row the same way the totals count it: an order that
 * is unpaid, cancelled or rejected counts for nothing (so its row shows no
 * earnings), and a refund takes its share off the commission and the vendor's
 * earnings. Keep the rule in step with the SQL.
 *
 * @since 1.8.0
 *
 * @param object $order Order row (status, payment_status, paid_at, total, refunded_amount, platform_fee, vendor_earnings).
 * @return object{counts:bool,revenue:float,commission:float,vendor_earnings:float}
 */
function wpss_get_order_revenue( object $order ): object {
	$status = (string) ( $order->status ?? '' );
	$paid   = ! empty( $order->paid_at ) || in_array( (string) ( $order->payment_status ?? '' ), array( 'paid', 'completed', 'refunded' ), true );
	$counts = $paid && ! in_array( $status, array( 'pending_payment', 'pending', 'cancelled', 'rejected' ), true );
	$total  = (float) ( $order->total ?? 0 );

	if ( ! $counts || $total <= 0 ) {
		return (object) array(
			'counts'          => $counts,
			'revenue'         => 0.0,
			'commission'      => 0.0,
			'vendor_earnings' => 0.0,
		);
	}

	if ( null !== ( $order->refunded_amount ?? null ) ) {
		$refunded = (float) $order->refunded_amount;
	} else {
		$refunded = ( 'refunded' === $status || ( 'refunded' === ( $order->payment_status ?? '' ) && 'partially_refunded' !== $status ) ) ? $total : 0.0;
	}

	$refunded = min( $total, $refunded );
	$share    = ( $total - $refunded ) / $total;

	// Credited orders already carry the net after a refund's reversal; see
	// OrderRepository::get_revenue().
	$credited = in_array( (int) ( $order->id ?? 0 ), wpss_get_credited_order_ids( array( (int) ( $order->id ?? 0 ) ) ), true );
	$scale    = $credited ? 1.0 : $share;

	return (object) array(
		'counts'          => true,
		'revenue'         => $total - $refunded,
		'commission'      => (float) ( $order->platform_fee ?? 0 ) * $scale,
		'vendor_earnings' => (float) ( $order->vendor_earnings ?? 0 ) * $scale,
	);
}

/**
 * Which of these orders the vendor has been credited for (a ledger credit).
 *
 * Remembered for the request, so a list primes its page in one query and each
 * row's wpss_get_order_revenue() reads from memory.
 *
 * @since 1.8.0
 *
 * @param int[] $order_ids Order IDs.
 * @return int[] The credited ones.
 */
function wpss_get_credited_order_ids( array $order_ids ): array {
	static $known = array();

	$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
	$missing   = array_diff( $order_ids, array_keys( $known ) );

	if ( $missing ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$credited = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT reference_id FROM {$wpdb->prefix}wpss_wallet_transactions WHERE reference_type = 'order' AND amount > 0 AND reference_id IN ({$placeholders})", ...$missing ) ) );
		foreach ( $missing as $id ) {
			$known[ $id ] = in_array( $id, $credited, true );
		}
	}

	return array_values( array_filter( $order_ids, static fn( $id ) => ! empty( $known[ $id ] ) ) );
}

/**
 * Drop the cached admin order aggregates.
 *
 * @since 1.7.1
 *
 * @return void
 */
function wpss_flush_order_aggregates(): void {
	delete_transient( 'wpss_order_aggregates' );
}

// A second partial refund is not a status change, so the refund hooks are listed too.
foreach ( array( 'wpss_order_created', 'wpss_order_paid', 'wpss_order_status_changed', 'wpss_order_status_refunded', 'wpss_order_status_partially_refunded' ) as $wpss_aggregates_hook ) {
	add_action( $wpss_aggregates_hook, 'wpss_flush_order_aggregates' );
}
