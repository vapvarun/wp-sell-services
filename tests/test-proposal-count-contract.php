<?php
/**
 * "Proposals N" is the same number on every surface, from the proposals table.
 *
 * Run: wp eval-file tests/test-proposal-count-contract.php
 *
 * The request card read `_wpss_proposal_count` meta that only the demo seeder
 * wrote, so every real request read "Proposals 0" (Basecamp 10337193560).
 * BuyerRequestService::get_proposal_count() is now the one count, primed for a
 * whole list in one query. A throwaway request, buyer and vendor are created
 * and removed; existing requests are only read.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table    = $wpdb->prefix . 'wpss_proposals';
$service  = new BuyerRequestService();
$in_table = static function ( int $id ) use ( $wpdb, $table ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE request_id = %d AND status != 'withdrawn'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};
$fresh    = static function (): void {
	wp_cache_flush_group( BuyerRequestService::PROPOSAL_COUNT_GROUP );
};

// 1 + 4. Existing requests with proposals: card, model, REST and table agree.
$sample = array_map( 'intval', $wpdb->get_col( "SELECT request_id FROM {$table} GROUP BY request_id ORDER BY COUNT(*) DESC LIMIT 5" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$agree  = true;
$seen   = 0;
foreach ( $sample as $id ) {
	$fresh();
	$model    = \WPSellServices\Models\BuyerRequest::from_post( get_post( $id ) );
	$response = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/buyer-requests/' . $id ) );
	$rest     = 200 === $response->get_status() ? (array) $response->get_data() : null; // Closed requests are not public.
	$want     = $in_table( $id );
	$seen    += null === $rest ? 0 : 1;
	if ( $service->get_proposal_count( $id ) !== $want || ( $model ? $model->proposal_count : -1 ) !== $want || ( null !== $rest && (int) ( $rest['proposal_count'] ?? -1 ) !== $want ) ) {
		$agree = false;
		echo "      request {$id}: table {$want}, service {$service->get_proposal_count( $id )}, model " . ( $model ? $model->proposal_count : 'none' ) . ', REST ' . ( $rest['proposal_count'] ?? 'n/a' ) . "\n";
	}
}
$check( sprintf( '1+4. %d existing requests: service, model and REST (%d public) all equal the table', count( $sample ), $seen ), $sample && $agree );

$list = (array) rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/buyer-requests' ) )->get_data();
$ok   = (bool) $list;
foreach ( $list as $item ) {
	$ok = $ok && isset( $item['id'], $item['proposal_count'] ) && (int) $item['proposal_count'] === $in_table( (int) $item['id'] );
}
$check( sprintf( '4. the REST list (%d requests) returns the table count for each', count( $list ) ), $ok );

// 3. A list costs one count query, not one per card.
$fresh();
$query = new WP_Query( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'posts_per_page' => 20 ) );
$before = $wpdb->num_queries;
foreach ( $query->posts as $post ) {
	$service->get_proposal_count( $post->ID );
}
$check( sprintf( '3. %d cards read their counts with %d extra queries (primed by the list query)', count( $query->posts ), $wpdb->num_queries - $before ), count( $query->posts ) > 1 && 0 === $wpdb->num_queries - $before );

// 2. Send and withdraw move the count.
$buyer   = wp_insert_user( array( 'user_login' => 'dev_f1_req_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_req_buyer@example.test' ) );
$vendor  = wp_insert_user( array( 'user_login' => 'dev_f1_req_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_req_vendor@example.test' ) );
$request = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'dev_f1 proposal count request', 'post_content' => 'Need a logo.', 'post_author' => $buyer ) );
update_post_meta( $request, '_wpss_status', BuyerRequestService::STATUS_OPEN );

try {
	$fresh();
	$check( '2. a new request reads 0', 0 === $service->get_proposal_count( $request ) );

	$proposal = ( new ProposalService() )->submit( $request, $vendor, array( 'description' => 'I can do this.', 'price' => 50, 'delivery_days' => 3 ) );
	$fresh();
	$check( '2. sending a proposal makes it 1', is_int( $proposal ) && $proposal > 0 && 1 === $service->get_proposal_count( $request ) );

	( new ProposalService() )->withdraw( (int) $proposal, $vendor );
	$fresh();
	$check( '2. withdrawing it makes it 0 again', 0 === $service->get_proposal_count( $request ) );
} finally {
	$wpdb->delete( $table, array( 'request_id' => $request ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE user_id IN ( %d, %d )", $buyer, $vendor ) );
	wp_delete_post( $request, true );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
