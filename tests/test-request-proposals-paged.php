<?php
/**
 * GET /buyer-requests/{id}/proposals pages like every other list.
 *
 * Run: wp eval-file tests/test-request-proposals-paged.php
 *
 * The route ignored page/per_page and stopped at 50 rows with no totals, so a
 * client could neither page nor tell that more existed. Unpaged calls still get
 * up to 50, as before.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// Own rows, all with one created_at: the tie-break is what keeps pages stable.
$table   = $wpdb->prefix . 'wpss_proposals';
$buyer   = (int) wp_insert_user( array( 'user_login' => 'qa_pp_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$request = (int) wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA paging request', 'post_author' => $buyer ) );
for ( $i = 0; $i < 5; $i++ ) {
	$wpdb->insert( $table, array( 'request_id' => $request, 'vendor_id' => $buyer + 1 + $i, 'cover_letter' => 'qa', 'proposed_price' => 10, 'proposed_days' => 1, 'created_at' => '2026-01-01 00:00:00' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
$row = (object) array( 'request_id' => $request, 'n' => 5 );

wp_set_current_user( (int) get_post_field( 'post_author', (int) $row->request_id ) );
$get = static function ( array $query ) use ( $row ) {
	$request = new WP_REST_Request( 'GET', '/wpss/v1/buyer-requests/' . (int) $row->request_id . '/proposals' );
	$request->set_query_params( $query );

	return rest_do_request( $request );
};

$all = $get( array() );
$check( 'unpaged: every proposal up to 50, with X-WP-Total', 200 === $all->get_status() && count( $all->get_data() ) === min( 50, (int) $row->n ) && (int) $row->n === (int) ( $all->get_headers()['X-WP-Total'] ?? -1 ) );

$second = $get( array( 'per_page' => 1, 'page' => 2 ) );
$check( 'per_page=1&page=2 returns the second proposal', 1 === count( $second->get_data() ) && ( $second->get_data()[0]['id'] ?? 0 ) === ( $all->get_data()[1]['id'] ?? -1 ) );
$check( 'and X-WP-TotalPages counts them', (int) $row->n === (int) ( $second->get_headers()['X-WP-TotalPages'] ?? -1 ) );

$seen = array();
for ( $page = 1; $page <= 5; $page++ ) {
	foreach ( $get( array( 'per_page' => 1, 'page' => $page ) )->get_data() as $item ) {
		$seen[] = $item['id'];
	}
}
$check( 'identical created_at: five pages of one show five different proposals', 5 === count( array_unique( $seen ) ), implode( ',', $seen ) );
$check( 'and arrive in id order', $seen === array_values( array_unique( $seen ) ) && $seen === (array) array_values( $seen ) && $seen === ( static function ( $a ) { sort( $a ); return $a; } )( $seen ) );

// MySQL happens to return ties in id order, so also pin the SQL: the sort must carry the id.
$sql = '';
add_filter( 'query', static function ( $q ) use ( &$sql, $table ) { if ( false !== strpos( $q, $table ) && false !== strpos( $q, 'ORDER BY' ) ) { $sql = $q; } return $q; } );
( new \WPSellServices\Services\ProposalService() )->get_by_request( $request, array( 'limit' => 2, 'offset' => 2 ) );
$check( 'the proposals query breaks ties by id', (bool) preg_match( '/ORDER BY\s+created_at\s+ASC,\s*id\s+ASC/i', $sql ), $sql );

$wpdb->delete( $table, array( 'request_id' => $request ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_delete_post( $request, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
