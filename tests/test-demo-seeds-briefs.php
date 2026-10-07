<?php
/**
 * Demo data carries buyer files on requests and order briefs.
 *
 * Run: wp eval-file tests/test-demo-seeds-briefs.php
 *
 * Every seeded order rendered the requirements partial's empty branch, so a
 * fatal on the populated branch passed every browser check and shipped in the
 * 1.8.0 build (Basecamp 10380173025). Runs the two seeder steps on its own
 * fixtures, renders the order view, then deletes demo content - so it refuses
 * to run on a site that already has demo content.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

use WPSellServices\Demo\MarketplaceSeeder;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Tests\Factories\UserFactory;

if ( wpss_has_demo_content() ) {
	echo "SKIP  this site has demo content; the test deletes demo content. Run it on a site without any.\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$suffix   = wp_generate_password( 6, false, false );
$buyer    = UserFactory::customer( array( 'user_login' => 'wpss_seedbrief_buyer_' . $suffix, 'user_email' => 'wpss_seedbrief_buyer_' . $suffix . '@example.test' ) );
$vendor   = UserFactory::vendor( array( 'user_login' => 'wpss_seedbrief_vendor_' . $suffix, 'user_email' => 'wpss_seedbrief_vendor_' . $suffix . '@example.test' ) );
$seeder   = new MarketplaceSeeder();
$order_id = 0;

$call = static function ( string $method, ...$args ) use ( $seeder ) {
	$m = new ReflectionMethod( MarketplaceSeeder::class, $method );
	$m->setAccessible( true );

	return $m->invoke( $seeder, ...$args );
};

try {
	$wpdb->insert( // phpcs:ignore WordPress.DB
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number' => 'WPSS-SEEDBRIEF-' . $suffix,
			'customer_id'  => $buyer->ID,
			'vendor_id'    => $vendor->ID,
			'service_id'   => 0,
			'platform'     => 'standalone',
			'total'        => 10,
			'currency'     => 'USD',
			'status'       => ServiceOrder::STATUS_IN_PROGRESS,
			'meta'         => wp_json_encode( array( 'status_history' => array( array( 'note' => 'Seeded demo order.' ) ) ) ),
			'created_at'   => current_time( 'mysql', true ),
		)
	);
	$order_id = (int) $wpdb->insert_id;

	$written = $call( 'seed_requirements', array( array( 'id' => $order_id, 'status' => ServiceOrder::STATUS_IN_PROGRESS, 'customer_id' => $buyer->ID, 'vendor_id' => $vendor->ID, 'service_id' => 0 ) ) );
	$order   = ServiceOrder::find( $order_id );
	$brief   = $order->get_submitted_requirements();
	$check( 'an order brief is seeded', 1 === $written );
	$check( 'the brief carries a buyer-owned file record', 1 === count( $brief['attachments'] ) && is_array( $brief['attachments'][0] ) );

	ob_start();
	wpss_get_template_part( 'order/requirements', '', array( 'wpss_order' => $order, 'wpss_viewer' => 'vendor' ) );
	$check( 'the order view renders the populated files branch', str_contains( (string) ob_get_clean(), 'Files the buyer attached' ) );

	$category = (int) ( get_terms( array( 'taxonomy' => 'wpss_service_category', 'number' => 1, 'hide_empty' => false, 'fields' => 'ids' ) )[0] ?? 0 );
	$call( 'seed_requests_and_proposals', array( $buyer->ID ), array( array( 'user_id' => $vendor->ID, 'blueprint' => array( 'skills' => array( 'design' ) ) ) ), array( 'Design' => $category ) );
	$requests = get_posts( array( 'post_type' => 'wpss_request', 'author' => $buyer->ID, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	$with     = array_filter( $requests, static fn( $id ) => ! empty( get_post_meta( $id, '_wpss_attachments', true ) ) );
	$file     = $with ? (int) get_post_meta( reset( $with ), '_wpss_attachments', true )[0] : 0;
	$check( 'some seeded requests carry a brief file', count( $with ) > 0 && count( $with ) < count( $requests ) );
	$check( 'the request file is the buyer\'s and stamped with its request', $file && (int) get_post_field( 'post_author', $file ) === $buyer->ID && in_array( (int) reset( $with ), array_map( 'intval', get_post_meta( $file, '_wpss_request_id', false ) ), true ) );

	wpss_delete_demo_content();
	$check( 'deleting demo content removes the seeded order brief', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_order_requirements WHERE order_id = %d", $order_id ) ) ); // phpcs:ignore WordPress.DB
	$check( 'deleting demo content removes the seeded files', null === get_post( $file ) );
} finally {
	if ( $order_id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $order_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $order_id ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( get_posts( array( 'post_type' => array( 'wpss_request', 'attachment' ), 'author' => $buyer->ID, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $leftover ) {
		wp_delete_post( $leftover, true );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_proposals WHERE vendor_id = %d", $vendor->ID ) ); // phpcs:ignore WordPress.DB
	delete_option( 'wpss_demo_content_imported' );
	UserFactory::cleanup();
}

echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
