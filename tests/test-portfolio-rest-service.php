<?php
/**
 * The portfolio REST routes write through PortfolioService, so tags are kept,
 * a partial PUT changes only what was sent, and the item cap and hooks apply on
 * every door (Basecamp 10403565741). The routes wrote the table themselves and
 * dropped tags, skipped the item cap and never fired wpss_portfolio_item_created.
 *
 * Run: wp eval-file tests/test-portfolio-rest-service.php
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

$vendors = get_users( array( 'role' => 'wpss_vendor', 'number' => 2, 'fields' => 'ID' ) );
if ( count( $vendors ) < 2 ) {
	echo "SKIP  needs two vendors\n";
	return;
}
list( $v1, $v2 ) = array_map( 'intval', $vendors );
$table = $wpdb->prefix . 'wpss_portfolio_items';

$call = static function ( string $method, string $path, array $body = array() ) {
	$req = new WP_REST_Request( $method, '/wpss/v1' . $path );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	return rest_do_request( $req );
};

$created = array();
$fired   = 0;
$count   = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_portfolio_item_created', $count );

wp_set_current_user( $v1 );

$res = $call( 'POST', '/portfolio', array( 'title' => 'QA-RFT one', 'tags' => array( 'alpha', 'beta' ) ) );
$id  = (int) ( $res->get_data()['id'] ?? 0 );
$created[] = $id;
$check( 'create with a tag list returns 201 and the tags', 201 === $res->get_status() && array( 'alpha', 'beta' ) === ( $res->get_data()['tags'] ?? null ) );
$check( 'the tags are in the database', array( 'alpha', 'beta' ) === json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT tags FROM {$table} WHERE id = %d", $id ) ), true ) );
$check( 'wpss_portfolio_item_created fired for a REST create', 1 === $fired );

$res = $call( 'POST', '/portfolio', array( 'title' => 'QA-RFT two', 'tags' => 'logo, branding , logo' ) );
$created[] = (int) ( $res->get_data()['id'] ?? 0 );
$check( 'create with "a, b" (dashboard form) splits, trims, de-duplicates', array( 'logo', 'branding' ) === ( $res->get_data()['tags'] ?? null ) );

$res = $call( 'PUT', "/portfolio/{$id}", array( 'tags' => array( 'gamma' ) ) );
$data = $res->get_data();
$check( 'PUT tags alone: tags replaced, title and description untouched', 200 === $res->get_status() && array( 'gamma' ) === $data['tags'] && 'QA-RFT one' === $data['title'] );

$res = $call( 'PUT', "/portfolio/{$id}", array( 'title' => 'QA-RFT renamed' ) );
$check( 'PUT title alone: tags kept', 'QA-RFT renamed' === $res->get_data()['title'] && array( 'gamma' ) === $res->get_data()['tags'] );

$res = $call( 'PUT', "/portfolio/{$id}", array( 'tags' => '' ) );
$check( 'PUT empty tags clears them', array() === $res->get_data()['tags'] );

$res = $call( 'PUT', "/portfolio/{$id}", array( 'title' => '   ' ) );
$check( 'PUT blank title is refused, not stored', 400 === $res->get_status() && 'QA-RFT renamed' === $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$table} WHERE id = %d", $id ) ) );

// Item cap now applies over REST.
$mine = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d", $v1 ) );
update_option( 'wpss_max_portfolio_items', $mine );
$res = $call( 'POST', '/portfolio', array( 'title' => 'QA-RFT over the cap' ) );
$check( 'REST create past wpss_max_portfolio_items is refused', 400 === $res->get_status() );
delete_option( 'wpss_max_portfolio_items' );

// Another vendor cannot touch it.
wp_set_current_user( $v2 );
$check( 'another vendor gets 403 on PUT', 403 === $call( 'PUT', "/portfolio/{$id}", array( 'title' => 'x' ) )->get_status() );
$check( 'another vendor gets 403 on DELETE', 403 === $call( 'DELETE', "/portfolio/{$id}" )->get_status() );

wp_set_current_user( $v1 );
$res = $call( 'DELETE', "/portfolio/{$id}" );
$check( 'owner DELETE removes it', 200 === $res->get_status() && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) ) );

remove_action( 'wpss_portfolio_item_created', $count );
$wpdb->query( "DELETE FROM {$table} WHERE title LIKE 'QA-RFT%'" );
wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
