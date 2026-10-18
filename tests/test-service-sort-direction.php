<?php
/**
 * The Service Grid's Order control reverses Rating and Popularity, unrated services stay last
 * (Basecamp 10416251563, 10416251479).
 *
 * Run: wp eval-file tests/test-service-sort-direction.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$f = wpss_test_fixtures();
$a = ( $f->service )( 'publish' );
$b = ( $f->service )( 'publish' );
$c = ( $f->service )( 'publish' );
$u = ( $f->service )( 'publish' ); // Unrated.

update_post_meta( $a, '_wpss_rating_average', 4.9 );
update_post_meta( $b, '_wpss_rating_average', 4.2 );
update_post_meta( $c, '_wpss_rating_average', 3.5 );
update_post_meta( $a, '_wpss_order_count', 30 );
update_post_meta( $b, '_wpss_order_count', 20 );
update_post_meta( $c, '_wpss_order_count', 10 );

$mine  = array( $a, $b, $c, $u );
$order = static function ( string $sort ) use ( $mine ): array {
	$q = new WP_Query( wpss_apply_service_sort( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post__in' => $mine, 'posts_per_page' => 10, 'fields' => 'ids', 'no_found_rows' => true ), $sort ) );
	return array_map( 'intval', $q->posts );
};

$check( 'rating: highest first, unrated last', $order( 'rating' ) === array( $a, $b, $c, $u ) );
$check( 'rating_low: lowest first, unrated still last', $order( 'rating_low' ) === array( $c, $b, $a, $u ) );
$check( 'popular: most sold first', $order( 'popular' ) === array( $a, $b, $c, $u ) );
$check( 'popular_low: least sold first, unsold still last', $order( 'popular_low' ) === array( $c, $b, $a, $u ) );

$html = (string) ( wpss_render_services_grid( array( 'orderBy' => 'rating', 'order' => 'ASC', 'limit' => 50, 'columns' => 3 ) )['html'] ?? '' );
$pos  = static fn( int $id ): int|false => strpos( $html, 'post-' . $id ) ?: strpos( $html, 'data-service-id="' . $id . '"' );
$check( 'the grid honours Order = Ascending for Rating', false !== $pos( $c ) && false !== $pos( $a ) && $pos( $c ) < $pos( $a ) );

exit( $fails ? 1 : 0 );
