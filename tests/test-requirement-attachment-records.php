<?php
/**
 * Requirement attachments stored as bare media ids render, serve and report.
 *
 * Run: wp eval-file tests/test-requirement-attachment-records.php
 *
 * A buyer request converted to an order, and REST POST
 * /orders/{id}/requirements, stored `[95]` where readers expect file records:
 * the order page fataled (Basecamp 10380173025, 10380395139) and the REST
 * reads dropped the file (Basecamp 10380601755). Also asserts an id naming
 * someone else's media never becomes a record on the order. Uses an existing
 * order, adds its own newest requirements row and media, and removes them.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Models\ServiceOrder;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table    = $wpdb->prefix . 'wpss_order_requirements';
$order_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}wpss_orders WHERE customer_id > 0 AND customer_id <> 1 ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB

if ( ! $order_id ) {
	echo "SKIP  no order with a buyer to attach a fixture to\n";
	return;
}

$order = ServiceOrder::find( $order_id );
$made  = array();
$row   = 0;

$media = static function ( int $author ) use ( &$made ): int {
	$id     = (int) wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_title'     => 'wpss-requirement-brief',
			'post_mime_type' => 'image/png',
			'post_author'    => $author,
		)
	);
	$made[] = $id;

	return $id;
};

try {
	$own     = $media( (int) $order->customer_id );
	$foreign = $media( 1 );

	// The shape the two old writers stored.
	$wpdb->insert( $table, array( 'order_id' => $order_id, 'field_data' => '[]', 'attachments' => wp_json_encode( array( $own, $foreign ) ), 'submitted_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
	$row = (int) $wpdb->insert_id;

	$submitted = $order->get_submitted_requirements();
	$check( 'model returns records only', array( true ) === array_values( array_unique( array_map( 'is_array', $submitted['attachments'] ) ) ) );
	$check( 'buyer\'s own media becomes a record', 1 === count( $submitted['attachments'] ) && (string) $own === $submitted['attachments'][0]['id'] );

	ob_start();
	try {
		wpss_get_template_part( 'order/requirements', '', array( 'wpss_order' => $order, 'wpss_viewer' => 'vendor' ) );
		$html = (string) ob_get_clean();
		$check( 'order page renders without a fatal', true );
		$check( 'order page lists the attached file', str_contains( $html, 'Files the buyer attached' ) );
	} catch ( \Throwable $e ) {
		ob_end_clean();
		$check( 'order page renders without a fatal: ' . $e->getMessage(), false );
	}

	$check( 'file endpoint finds the buyer\'s file', null !== wpss_find_order_file( $order_id, (string) $own ) );
	$check( 'file endpoint refuses someone else\'s media', null === wpss_find_order_file( $order_id, (string) $foreign ) );

	wp_set_current_user( 1 );
	$res  = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/orders/' . $order_id . '/requirements' ) );
	$data = $res->get_data();
	$check( 'REST requirements reports submitted', 'submitted' === ( $data['status'] ?? '' ) );
	$check( 'REST requirements returns the attachment', 1 === count( $data['attachments'] ?? array() ) && (string) $own === $data['attachments'][0]['id'] );

	$stored = wpss_normalize_requirement_attachments( array( $own, $foreign, 'x', 0 ), (int) $order->customer_id );
	$check( 'writer normaliser keeps only the buyer\'s media', 1 === count( $stored ) && (string) $own === $stored[0]['id'] );
} finally {
	if ( $row ) {
		$wpdb->delete( $table, array( 'id' => $row ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $made as $id ) {
		wp_delete_attachment( $id, true );
	}
}

echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
