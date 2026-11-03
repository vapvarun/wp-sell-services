<?php
/**
 * A FluentCart payment finds its WPSS orders by key, not by scanning every order.
 *
 * Tips, milestones and extensions paid on FluentCart carry the minted
 * FluentCart order id in their meta JSON. The paid event found them with
 * "meta LIKE '%...%'", a full scan of wpss_orders, on every FluentCart
 * payment, shop orders included. The resolver now writes a back-link on the
 * FluentCart order (as the Woo rail does); the scan survives only for fee-only
 * orders minted before that, and runs once.
 *
 * Run: wp eval-file tests/test-fluentcart-pay-order-lookup-by-key.php
 *
 * Needs FluentCart active (activate it for the run). Seeds QA-FCK-* rows and deletes them.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\Integrations\FluentCart\FluentCartOrderProvider;
use WPSellServicesPro\Integrations\FluentCart\FluentCartPayOrderResolver;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( FluentCartOrderProvider::class ) || ! class_exists( '\FluentCart\App\Models\Order' ) ) {
	echo "SKIP  Pro or FluentCart is not active\n";
	return;
}

global $wpdb;
$orders  = $wpdb->prefix . 'wpss_orders';
$stamp   = strtoupper( wp_generate_password( 5, false ) );
$made_fc = array();
$made_wp = array();
$link    = '_wpss_pay_order_id'; // FluentCartPayOrderResolver::FC_META_WPSS_PAY_ORDER, spelled out so the old code runs.

$wpss_order = static function ( string $suffix, array $meta ) use ( $wpdb, $orders, $stamp, &$made_wp ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => "QA-FCK-{$stamp}-{$suffix}",
			'customer_id'    => 1,
			'vendor_id'      => 1,
			'service_id'     => 0,
			'platform'       => 'tip',
			'subtotal'       => 12,
			'total'          => 12,
			'currency'       => 'USD',
			'status'         => 'pending_payment',
			'payment_status' => 'pending',
			'meta'           => wp_json_encode( $meta ),
		)
	);
	$made_wp[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$fc_order = static function ( string $line_type ) use ( &$made_fc ) {
	$o = \FluentCart\App\Models\Order::create( array( 'customer_id' => 0, 'payment_status' => 'pending', 'status' => 'pending', 'currency' => 'USD', 'subtotal' => 1200, 'total_amount' => 1200, 'total_paid' => 0, 'uuid' => wp_generate_uuid4(), 'type' => 'payment', 'mode' => 'test' ) );
	\FluentCart\App\Models\OrderItem::create( array( 'order_id' => $o->id, 'post_id' => 0, 'object_id' => 0, 'payment_type' => $line_type, 'fulfillment_type' => 'digital', 'post_title' => '', 'title' => 'QA-FCK', 'quantity' => 1, 'cost' => 0, 'unit_price' => 1200, 'subtotal' => 1200, 'line_total' => 1200 ) );
	$made_fc[] = (int) $o->id;
	return $o;
};

$scans = 0;
$spy   = static function ( $sql ) use ( &$scans, $orders ) {
	if ( false !== stripos( $sql, $orders ) && false !== stripos( $sql, 'meta LIKE' ) ) {
		++$scans;
	}
	return $sql;
};
add_filter( 'query', $spy );
$provider = new FluentCartOrderProvider();
$ids      = static fn( array $rows ) => array_map( static fn( $r ) => (int) $r->id, $rows );

// 1. A pay order minted now: the resolver writes the back-link.
$new_id = $wpss_order( 'new', array() );
$minted = ( new ReflectionMethod( FluentCartPayOrderResolver::class, 'create_fc_order' ) )->invoke( new FluentCartPayOrderResolver(), wpss_get_order( $new_id ) );
$made_fc[] = (int) ( $minted->id ?? 0 );
$check( 'the resolver minted a FluentCart order', $minted && $minted->id > 0 );
$check( 'and linked it back to the WPSS order', $minted && $new_id === (int) $minted->getMeta( $link, 0 ) );
$scans = 0;
$found = $provider->find_orders_for_fc_order( (int) $minted->id );
$check( 'its payment finds the WPSS order', array( $new_id ) === $ids( $found ), wp_json_encode( $ids( $found ) ) );
$check( 'without scanning order meta', 0 === $scans, "{$scans} scans" );

// 2. A shop order with product lines and no WPSS link.
$shop  = $fc_order( 'onetime' );
$scans = 0;
$check( 'an unrelated shop order finds nothing', array() === $provider->find_orders_for_fc_order( (int) $shop->id ) );
$check( 'and scans nothing', 0 === $scans, "{$scans} scans" );

// 3. A pay order minted before the back-link existed.
$legacy    = $fc_order( 'fee' );
$legacy_id = $wpss_order( 'old', array( 'fc_pay_order_id' => (int) $legacy->id ) );
$wpss_order( 'near', array( 'fc_pay_order_id' => (int) ( $legacy->id . '9' ) ) ); // ":12" vs ":129".
$check( 'a legacy pay order is still found', array( $legacy_id ) === $ids( $provider->find_orders_for_fc_order( (int) $legacy->id ) ) );
$check( 'and gains the back-link', $legacy_id === (int) $legacy->getMeta( $link, 0 ) );
$scans = 0;
$provider->find_orders_for_fc_order( (int) $legacy->id );
$check( 'so the next event does not scan', 0 === $scans, "{$scans} scans" );

// 4. A back-link the WPSS order does not confirm is ignored.
$stray = $fc_order( 'fee' );
$stray->updateMeta( $link, $new_id );
$check( 'a back-link both ends do not agree on pays nothing', array() === $provider->find_orders_for_fc_order( (int) $stray->id ) );

remove_filter( 'query', $spy );

// Clean up.
foreach ( array_filter( $made_fc ) as $id ) {
	\FluentCart\App\Models\OrderItem::query()->where( 'order_id', $id )->delete();
	\FluentCart\App\Models\OrderMeta::query()->where( 'order_id', $id )->delete();
	\FluentCart\App\Models\Order::query()->where( 'id', $id )->delete();
}
foreach ( $made_wp as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) );
}
$check( 'seeded rows removed', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE order_number LIKE %s", "QA-FCK-{$stamp}-%" ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
