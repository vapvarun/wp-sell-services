<?php
/**
 * An order records what was bought product-neutrally: product_type defaults to 'service' and
 * product_id mirrors service_id, on new orders and (by the batched backfill) on existing ones.
 * service_id stays authoritative; nothing is migrated off it.
 *
 * Run: wp eval-file tests/test-order-product-columns.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table = $wpdb->prefix . 'wpss_orders';

// Take the upgrade path a 1.8.0 site takes: stored DB version behind, real install().
update_option( \WPSellServices\Database\SchemaManager::VERSION_OPTION, '1.8.0' );
( new \WPSellServices\Database\SchemaManager() )->install();
$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL

$check( 'orders has product_type', in_array( 'product_type', $cols, true ) );
$check( 'orders has product_id', in_array( 'product_id', $cols, true ) );
$check( 'orders has the (product_type, product_id) index', null !== $wpdb->get_var( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_product'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL

$repo = new \WPSellServices\Database\Repositories\OrderRepository();
$base = array(
	'order_number' => 'QA-PROD-' . wp_generate_password( 6, false ),
	'customer_id'  => 1,
	'vendor_id'    => 1,
	'service_id'   => 424242,
	'subtotal'     => 10,
	'total'        => 10,
);

$id  = $repo->insert( $base );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT product_type, product_id, service_id FROM `{$table}` WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$check( 'a new order is product_type service', 'service' === ( $row->product_type ?? '' ) );
$check( 'a new order gets product_id = service_id', 424242 === (int) ( $row->product_id ?? 0 ) );
$check( 'service_id is untouched', 424242 === (int) ( $row->service_id ?? 0 ) );

$own = $repo->insert( array_merge( $base, array( 'order_number' => $base['order_number'] . 'B', 'product_id' => 7 ) ) );
$check( 'a caller-supplied product_id is kept', 7 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM `{$table}` WHERE id = %d", $own ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL

// An order written before the column existed: NULL product_id, filled by the backfill.
$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET product_id = NULL WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$backfill = new ReflectionMethod( \WPSellServices\Database\SchemaManager::class, 'backfill_product_id' );
$backfill->invoke( new \WPSellServices\Database\SchemaManager() );
$check( 'the backfill sets product_id from service_id', 424242 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM `{$table}` WHERE id = %d", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$check( 'the backfill does not overwrite a set product_id', 7 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM `{$table}` WHERE id = %d", $own ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL

$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN (%d, %d)", $id, $own ) ); // phpcs:ignore WordPress.DB.PreparedSQL

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
