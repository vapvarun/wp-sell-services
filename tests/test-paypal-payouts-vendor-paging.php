<?php
/**
 * The PayPal Payouts vendor list reaches every vendor, a page at a time.
 *
 * The Create Batch Payout table and GET /paypal-payouts/pending read at most
 * 500 vendors, and asked for each one's email and balance in turn. Both are
 * paged now and read the page's emails in one go.
 *
 * Run: wp eval-file tests/test-paypal-payouts-vendor-paging.php
 *
 * Seeds 520 vendors (QA-PP-*) straight into the user tables and deletes them.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\PayPalPayouts\VendorPayoutProfileService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( VendorPayoutProfileService::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

global $wpdb;
$seed  = 520;
$stamp = strtolower( wp_generate_password( 6, false ) );
$ids   = array();
$now   = current_time( 'mysql', true );
for ( $i = 0; $i < $seed; $i++ ) {
	$login = "qa-pp-{$stamp}-{$i}";
	$wpdb->insert( $wpdb->users, array( 'user_login' => $login, 'user_nicename' => $login, 'user_email' => "{$login}@example.com", 'display_name' => sprintf( 'QA-PP %s %03d', $stamp, $i ), 'user_registered' => $now, 'user_pass' => '' ) );
	$ids[] = (int) $wpdb->insert_id;
}
$rows = array();
foreach ( $ids as $id ) {
	$rows[] = $wpdb->prepare( '(%d, %s, %s)', $id, 'wpss_payout_method', 'paypal' );
	$rows[] = $wpdb->prepare( '(%d, %s, %s)', $id, 'wpss_payout_details', maybe_serialize( array( 'email' => "pp{$id}@example.com" ) ) );
}
$wpdb->query( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES " . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
clean_user_cache( $ids[0] );
$check( "seeded {$seed} PayPal vendors", count( array_filter( $ids ) ) === $seed );

$service = new VendorPayoutProfileService();
$total   = method_exists( $service, 'count_vendors_with_paypal' ) ? $service->count_vendors_with_paypal() : 0;
$check( 'the count includes every seeded vendor', $total >= $seed, "count {$total}" );

// Walk every page the way the screen does.
$seen  = array();
$worst = 0;
for ( $page = 1; $page <= max( 1, (int) ceil( $total / 20 ) ); $page++ ) {
	$before = $wpdb->num_queries;
	foreach ( $service->get_vendors_with_paypal( $page, 20 ) as $row ) {
		$seen[ $row['vendor_id'] ] = $row['paypal_email'];
	}
	$worst = max( $worst, $wpdb->num_queries - $before );
}
$missing = array_diff( $ids, array_keys( $seen ) );
$check( 'paging reaches every seeded vendor', ! $missing, count( $missing ) . ' never listed' );
$check( 'each row carries its email', isset( $seen[ $ids[ $seed - 1 ] ] ) && "pp{$ids[ $seed - 1 ]}@example.com" === $seen[ $ids[ $seed - 1 ] ] );
$check( 'a page of 20 costs a fixed few queries, not one per vendor', $worst <= 6, "{$worst} queries" );

// The REST door pages the same list.
wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
$request = new WP_REST_Request( 'GET', '/wpss/v1/paypal-payouts/pending' );
$request->set_query_params( array( 'page' => 2, 'per_page' => 50 ) );
$response = rest_do_request( $request );
$headers  = $response->get_headers();
$check( 'REST answers 200', 200 === $response->get_status(), (string) $response->get_status() );
$check( 'REST returns one page', 50 === count( (array) $response->get_data() ), count( (array) $response->get_data() ) . ' rows' );
$check( 'REST reports the full total', (int) ( $headers['X-WP-Total'] ?? 0 ) >= $seed, (string) ( $headers['X-WP-Total'] ?? 'none' ) );

$last = new WP_REST_Request( 'GET', '/wpss/v1/paypal-payouts/pending' );
$last->set_query_params( array( 'page' => (int) ( $headers['X-WP-TotalPages'] ?? 1 ), 'per_page' => 50 ) );
$check( 'REST reaches the last page', count( (array) rest_do_request( $last )->get_data() ) > 0 );

// Clean up.
$in = implode( ',', array_map( 'intval', $ids ) );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE user_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DELETE FROM {$wpdb->users} WHERE ID IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( $ids as $id ) {
	clean_user_cache( $id );
}
$check( 'seeded vendors removed', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE %s", 'qa-pp-' . $stamp . '-%' ) ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
