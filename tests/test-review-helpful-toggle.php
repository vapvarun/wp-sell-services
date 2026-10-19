<?php
/**
 * "Helpful" on a review is a toggle: a second click takes the vote back, the vote survives the daily sweep,
 * nobody votes on their own review, and both doors (REST and AJAX) share one writer
 * (Basecamp 10385175812).
 *
 * Run: wp eval-file tests/test-review-helpful-toggle.php
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\ReviewRepository;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_reviews';
$repo  = new ReviewRepository();

$author = (int) wp_insert_user( array( 'user_login' => 'qa_hv_a_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$voter  = (int) wp_insert_user( array( 'user_login' => 'qa_hv_b_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$wpdb->insert( $table, array( 'order_id' => 99900000 + wp_rand( 1, 99999 ), 'reviewer_id' => $author, 'reviewee_id' => 1, 'service_id' => 1, 'customer_id' => $author, 'vendor_id' => 1, 'rating' => 5, 'review' => 'QA helpful toggle', 'helpful_count' => 0 ) );
$rid   = (int) $wpdb->insert_id;
$count = static fn(): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT helpful_count FROM {$table} WHERE id = %d", $rid ) );

$first = $repo->toggle_helpful( $rid, $voter );
$check( 'first click adds the vote', is_array( $first ) && true === $first['marked'] && 1 === $first['count'] );
$check( 'the page can tell the reader already voted', array( $rid ) === $repo->helpful_voted_ids( array( $rid ), $voter ) );

// The daily sweep must not forget a signed-in vote (it used to, after a week, which let the reader vote again).
$wpdb->update( $wpdb->options, array( 'option_value' => 1 ), array( 'option_name' => ReviewRepository::helpful_vote_key( $rid, $voter ) ) );
$repo->cleanup_expired_helpful_votes();
$check( 'a signed-in vote survives the expiry sweep', array( $rid ) === $repo->helpful_voted_ids( array( $rid ), $voter ) );

$second = $repo->toggle_helpful( $rid, $voter );
$check( 'second click takes the vote back', is_array( $second ) && false === $second['marked'] && 0 === $second['count'] && 0 === $count() );
$check( 'after taking it back the page shows no vote', array() === $repo->helpful_voted_ids( array( $rid ), $voter ) );

$third = $repo->toggle_helpful( $rid, $voter );
$check( 'a third click votes again, count 1 not 2', is_array( $third ) && true === $third['marked'] && 1 === $count() );

$check( 'the author cannot vote on their own review', 'own_review' === $repo->toggle_helpful( $rid, $author ) && 1 === $count() );
$check( 'a review that does not exist is refused', 'not_found' === $repo->toggle_helpful( 987654321, $voter ) );

// A signed-out reader toggles too, and that row (and only that row) can expire.
$ip = '203.0.113.' . wp_rand( 1, 250 );
$guest = $repo->toggle_helpful( $rid, 0, $ip );
$check( 'a signed-out reader can vote', is_array( $guest ) && 2 === $count() );
$wpdb->update( $wpdb->options, array( 'option_value' => 1 ), array( 'option_name' => ReviewRepository::helpful_vote_key( $rid, 0, $ip ) ) );
$repo->cleanup_expired_helpful_votes();
$check( 'the sweep removes the expired signed-out row', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", ReviewRepository::helpful_vote_key( $rid, 0, $ip ) ) ) );

// REST: same writer, own review refused, state reported.
wp_set_current_user( $voter );
$call = static function ( int $id ) {
	return rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1/reviews/' . $id . '/helpful' ) );
};
$off = $call( $rid );
$check( 'REST toggles the vote off and reports it', 200 === $off->get_status() && false === $off->get_data()['marked'] );
wp_set_current_user( $author );
$own = $call( $rid );
$check( 'REST refuses a vote on your own review', 403 === $own->get_status() );

$item = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/reviews/' . $rid ) );
$d    = $item->get_data();
$check( 'the REST review tells the author they cannot vote', is_array( $d ) && isset( $d['viewer_can_vote'] ) && false === $d['viewer_can_vote'] );
wp_set_current_user( $voter );
$d = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/reviews/' . $rid ) )->get_data();
$check( 'the REST review carries the reader\'s vote state', isset( $d['viewer_can_vote'], $d['viewer_marked'] ) && true === $d['viewer_can_vote'] && false === $d['viewer_marked'] );

// The shared control (partials/review-helpful.php): a toggle for a reader, a plain note for the author.
$render = static function ( int $viewer, bool $voted ) use ( $rid, $author ): string {
	wp_set_current_user( $viewer );
	$wpss_helpful = array( 'review_id' => $rid, 'helpful_count' => 3, 'reviewer_id' => $author, 'voted' => $voted );
	ob_start();
	require wpss_locate_template( 'partials/review-helpful.php' );

	return (string) ob_get_clean();
};
$html = $render( $voter, true );
$check( 'the control renders pressed for a reader who voted', false !== strpos( $html, 'aria-pressed="true"' ) && false !== strpos( $html, 'marked' ) );
$html = $render( $voter, false );
$check( 'the control renders unpressed for a reader who has not', false !== strpos( $html, 'aria-pressed="false"' ) );
$html = $render( $author, false );
$check( 'the author sees a count, not a button', false === strpos( $html, '<button' ) && false !== strpos( $html, 'found this helpful' ) );
$html = $render( 0, false );
$check( 'a signed-out reader sees a count, not a button', false === strpos( $html, '<button' ) );

// Cleanup.
wp_set_current_user( 0 );
$wpdb->delete( $table, array( 'id' => $rid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_wpss_vote_' . $rid . '_' ) . '%' ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $author );
wp_delete_user( $voter );

exit( $fails ? 1 : 0 );
