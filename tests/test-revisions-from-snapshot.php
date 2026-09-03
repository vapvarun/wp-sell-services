<?php
/**
 * A standalone order carries its package's revisions, and the buyer's
 * revision note reaches the vendor.
 *
 * Checkout said "2 revisions included", the package snapshot said 2, the
 * order row said 0: no checkout path passed revisions to create_order(), so
 * the Request revision button never showed. When a revision did go through,
 * the reason was only a status-change note nobody rendered
 * (Basecamp 10264292240).
 *
 * Run: wp eval-file tests/test-revisions-from-snapshot.php
 *
 * Creates a throwaway buyer and two orders, and removes them at the end.
 * Also runs the schema upgrade, which backfills existing rows on this site.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Database\SchemaManager;
use WPSellServices\Services\OrderService;

global $wpdb;

$failures = array();
$orders   = $wpdb->prefix . 'wpss_orders';

// A published service, not owned by the buyer, whose first package includes
// revisions (2 here, or -1 unlimited).
$service_id = 0;
$expected   = 0;
foreach ( get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
	)
) as $candidate ) {
	$packages = get_post_meta( $candidate->ID, '_wpss_packages', true );
	$rev      = (int) ( $packages[0]['revisions'] ?? 0 );
	if ( is_array( $packages ) && 0 !== $rev && (float) ( $packages[0]['price'] ?? 0 ) > 0 ) {
		$service_id = (int) $candidate->ID;
		$expected   = $rev;
		break;
	}
}

if ( ! $service_id ) {
	WP_CLI::error( 'No published service with a first package that includes revisions.' );
}

$buyer_id = wp_insert_user(
	array(
		'user_login' => 'wpss_rev_probe_' . wp_rand( 1000, 9999 ),
		'user_email' => 'wpss-rev-probe-' . wp_rand( 1000, 9999 ) . '@example.invalid',
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
if ( is_wp_error( $buyer_id ) ) {
	WP_CLI::error( $buyer_id->get_error_message() );
}
wp_set_current_user( $buyer_id );

// 1. Resolve + settle a single intent: the order row carries the package's revisions.
$intents = new CheckoutIntentService();
$intent  = $intents->resolve(
	array(
		'service_id' => $service_id,
		'package_id' => 0,
	),
	$buyer_id
);

$order_id = 0;
if ( is_wp_error( $intent ) ) {
	$failures[] = 'resolve(): ' . $intent->get_error_message();
} else {
	$settled = $intents->settle( $intent, 'offline', 'rev-probe-' . time(), $intent->amount, $intent->currency );
	if ( empty( $settled['success'] ) ) {
		$failures[] = 'settle(): ' . ( $settled['error'] ?? 'failed' );
	} else {
		$order_id = (int) $settled['order_id'];
		$order    = wpss_get_order( $order_id );
		if ( $expected !== (int) $order->revisions_included ) {
			$failures[] = "order {$order_id}: revisions_included is {$order->revisions_included}, package says {$expected}.";
		}
	}
}

// 2. The buyer's reason is readable through the model and the REST order.
if ( $order_id ) {
	$reason = 'Probe: make the headline larger and use the blue logo.';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update( $orders, array( 'status' => 'pending_approval' ), array( 'id' => $order_id ) );

	if ( ! ( new OrderService() )->request_revision( $order_id, $reason ) ) {
		$failures[] = "order {$order_id}: request_revision() refused.";
	} else {
		$order = wpss_get_order( $order_id );
		if ( ! $order || $reason !== $order->get_revision_reason() ) {
			$failures[] = "order {$order_id}: get_revision_reason() did not return the note.";
		}

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/orders/' . $order_id ) );
		$data     = $response->get_data();
		if ( $reason !== ( $data['revision_reason'] ?? null ) ) {
			$failures[] = "order {$order_id}: REST order has no revision_reason (status {$response->get_status()}).";
		}
	}
}

// 3. The upgrade routine repairs an existing row from its snapshot.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->insert(
	$orders,
	array(
		'order_number'       => 'REV-PROBE-' . wp_rand( 1000, 9999 ),
		'customer_id'        => $buyer_id,
		'vendor_id'          => (int) get_post_field( 'post_author', $service_id ),
		'service_id'         => $service_id,
		'package_id'         => 0,
		'platform'           => 'standalone',
		'subtotal'           => 10,
		'total'              => 10,
		'currency'           => wpss_get_currency(),
		'status'             => 'pending_payment',
		'payment_status'     => 'pending',
		'revisions_included' => 0,
		'meta'               => wp_json_encode( array( 'package_snapshot' => array( 'revisions' => $expected ) ) ),
		'created_at'         => current_time( 'mysql' ),
		'updated_at'         => current_time( 'mysql' ),
	)
);
$legacy_id = (int) $wpdb->insert_id;

update_option( SchemaManager::VERSION_OPTION, '1.6.1' );
( new SchemaManager() )->install();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$backfilled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT revisions_included FROM {$orders} WHERE id = %d", $legacy_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( $expected !== $backfilled ) {
	$failures[] = "legacy order {$legacy_id}: upgrade left revisions_included at {$backfilled}, snapshot says {$expected}.";
}

// Cleanup.
foreach ( array_filter( array( $order_id, $legacy_id ) ) as $oid ) {
	$conversation_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpss_conversations WHERE order_id = %d", $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $conversation_ids as $cid ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => (int) $cid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'order_id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $orders, array( 'id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $buyer_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer_id );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		WP_CLI::warning( $failure );
	}
	WP_CLI::error( count( $failures ) . ' revision contract failure(s).' );
}

WP_CLI::success( "Order carries {$expected} revision(s) from its package, the reason reads back through the model and REST, and the upgrade backfilled the legacy row." );
