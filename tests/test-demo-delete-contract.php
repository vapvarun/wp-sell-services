<?php
/**
 * Deleting demo content never erases a real buyer's order.
 *
 * Run: wp eval-file tests/test-demo-delete-contract.php
 *
 * Deleting a service deletes its orders, so "delete the samples" removed the
 * order a real buyer had placed on a demo service (Basecamp 10379596258).
 * The routine deletes ALL demo content, so this refuses to run on a site that
 * already has some: it would delete it. Makes its own fixtures otherwise.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

use WPSellServices\Tests\Factories\UserFactory;

if ( wpss_has_demo_content() ) {
	echo "FAIL  this site has demo content; the test would delete it. Run it on a site without any.\n";
	exit( 1 );
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$suffix = wp_generate_password( 6, false, false );
$buyer  = UserFactory::customer( array( 'user_login' => 'wpss_demodel_buyer_' . $suffix, 'user_email' => 'wpss_demodel_buyer_' . $suffix . '@example.test' ) );
$made   = array();

$demo_vendor = static function ( string $tag ) use ( $suffix ): int {
	$user = UserFactory::vendor( array( 'user_login' => 'wpss_demodel_' . $tag . '_' . $suffix, 'user_email' => 'wpss_demodel_' . $tag . '_' . $suffix . '@example.test' ) );
	update_user_meta( $user->ID, '_wpss_demo_content', 1 );

	return (int) $user->ID;
};

$demo_service = static function ( int $vendor_id ) use ( $suffix ): int {
	$id = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'Demo delete fixture ' . $suffix, 'post_author' => $vendor_id ) );
	update_post_meta( $id, '_wpss_demo_content', 1 );

	return $id;
};

$order = static function ( int $service_id, int $vendor_id, ?string $meta ) use ( $wpdb, $orders, $buyer, $suffix, &$made ): int {
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$orders,
		array(
			'order_number'   => 'WPSS-DEMODEL-' . $suffix . '-' . count( $made ),
			'customer_id'    => $buyer->ID,
			'vendor_id'      => $vendor_id,
			'service_id'     => $service_id,
			'platform'       => 'standalone',
			'total'          => 25,
			'currency'       => 'USD',
			'status'         => 'in_progress',
			'payment_status' => 'paid',
			'meta'           => $meta,
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$made[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$exists = static fn( int $id ): bool => (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$kept_vendor = $demo_vendor( 'kept' );
$gone_vendor = $demo_vendor( 'gone' );
$kept        = $demo_service( $kept_vendor );
$seeded      = $demo_service( $gone_vendor );
$unused      = $demo_service( $gone_vendor );

try {
	$real   = $order( $kept, $kept_vendor, null );
	$sample = $order( $seeded, $gone_vendor, wp_json_encode( array( 'status_history' => array( array( 'note' => 'Seeded demo order.' ) ) ) ) );

	$result = wpss_delete_demo_content();

	$check( 'a demo service a real buyer ordered is kept, and counted', null !== get_post( $kept ) && 1 === $result['kept'] );
	$check( '  with the order', $exists( $real ) );
	$check( '  and its seller', false !== get_userdata( $kept_vendor ) );
	$check( 'a demo service with only the importer\'s own order is deleted', null === get_post( $seeded ) && ! $exists( $sample ) );
	$check( 'an unordered demo service is deleted', null === get_post( $unused ) && 2 === $result['services'] );
	$check( 'a demo seller with nothing kept is deleted', false === get_userdata( $gone_vendor ) && 1 === $result['vendors'] );
} finally {
	foreach ( $made as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
	foreach ( array( $kept, $seeded, $unused ) as $id ) {
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array( $kept_vendor, $gone_vendor ) as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_delete_user( $id );
	}
	UserFactory::cleanup();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
