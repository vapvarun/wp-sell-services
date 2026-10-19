<?php
/**
 * Product-type registry: "service" is registered by core as the first type, the registry holds only
 * conforming types, the service type answers for its own posts, and earnings print the title through
 * the registry instead of reading the post.
 *
 * Run: wp eval-file tests/test-product-type-registry.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Integrations\Contracts\ProductTypeInterface;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$types = wpss()->get_product_types();
$check( 'service is registered by core', isset( $types['service'] ) );
$check( 'every registered type implements the contract', count( array_filter( $types, static fn( $t ) => ! $t instanceof ProductTypeInterface ) ) === 0 );
$check( 'get_product_type() resolves by id and refuses an unknown one', wpss()->get_product_type( 'service' ) === $types['service'] && null === wpss()->get_product_type( 'goods' ) );

$service = get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 1 ) )[0] ?? null;
if ( ! $service ) {
	echo "SKIP  no service on this site\n";
	exit( $fails ? 1 : 0 );
}
$page = get_posts( array( 'post_type' => 'page', 'numberposts' => 1 ) )[0] ?? null;
$type = $types['service'];
$check( 'service type owns a service post', $type->owns( $service->ID ) );
$check( 'service type does not own a page or a missing id', ( ! $page || ! $type->owns( $page->ID ) ) && ! $type->owns( 999999999 ) );
$check( 'title comes from the service', $type->get_title( $service->ID ) === get_the_title( $service->ID ) );
$check( 'vendor is the service author', $type->get_vendor_id( $service->ID ) === (int) $service->post_author );
$check( 'a missing product has an empty title and no vendor', '' === $type->get_title( 999999999 ) && 0 === $type->get_vendor_id( 999999999 ) );

// Earnings line label: read through the registry for a real completed order, falling back when the product is gone.
global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';
$order = $wpdb->get_row( "SELECT id, vendor_id, service_id FROM {$table} WHERE status = 'completed' AND service_id > 0 ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
if ( $order && get_post( (int) $order->service_id ) ) {
	$rows = ( new \WPSellServices\Services\EarningsService() )->get_history( (int) $order->vendor_id, array( 'limit' => 200 ) );
	$row  = null;
	foreach ( $rows as $r ) {
		if ( (int) $r['id'] === (int) $order->id ) {
			$row = $r;
		}
	}
	$check( 'earnings history prints the service title', $row && $row['service_name'] === get_the_title( (int) $order->service_id ) );
} else {
	echo "SKIP  no completed order with a live service\n";
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
