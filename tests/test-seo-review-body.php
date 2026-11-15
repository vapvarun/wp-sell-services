<?php
/**
 * Service schema Review nodes carry the review text (reviewBody) and log no PHP warning.
 *
 * Run: wp eval-file tests/test-seo-review-body.php
 *
 * The wpss_reviews column is `review`; both schema writers read a `comment` property that does not exist.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\SEO\SchemaMarkup;

// Yoast need not be active: stand in for its abstract piece so our class loads.
if ( ! class_exists( 'Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece' ) ) {
	eval( 'namespace Yoast\WP\SEO\Generators\Schema; abstract class Abstract_Schema_Piece {}' ); // phpcs:ignore
}
use WPSellServices\SEO\ServiceSchemaPiece;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f   = wpss_test_fixtures();
$sid = (int) ( $f->service )( 'publish' );
$oid = (int) ( $f->order )( array( 'service_id' => $sid ) );
$wpdb->insert(
	$wpdb->prefix . 'wpss_reviews',
	array(
		'order_id'      => $oid,
		'reviewer_id'   => $f->buyer,
		'reviewer_name' => 'QA Buyer',
		'reviewee_id'   => $f->vendor,
		'service_id'    => $sid,
		'customer_id'   => $f->buyer,
		'vendor_id'     => $f->vendor,
		'rating'        => 5,
		'review'        => 'Delivered early and exactly as described.',
		'review_type'   => 'customer_to_vendor',
		'status'        => 'approved',
		'is_public'     => 1,
		'created_at'    => current_time( 'mysql', true ),
		'updated_at'    => current_time( 'mysql', true ),
	)
);
$rid = (int) $wpdb->insert_id;
$check( 'review row inserted', $rid > 0, $wpdb->last_error );

$warnings = array();
set_error_handler( static function ( $no, $str ) use ( &$warnings ) { $warnings[] = $str; return true; } ); // phpcs:ignore

$rm = new ReflectionMethod( ServiceSchemaPiece::class, 'get_reviews' );
$piece = ( new ReflectionClass( ServiceSchemaPiece::class ) )->newInstanceWithoutConstructor();
$nodes = $rm->invoke( $piece, $sid );
$check( 'ServiceSchemaPiece Review node has the review text', ( $nodes[0]['reviewBody'] ?? '' ) === 'Delivered early and exactly as described.', wp_json_encode( $nodes ) );

$schema = ( new SchemaMarkup() )->get_reviews_schema( $sid );
$check( 'SchemaMarkup Review node has the review text', ( $schema[0]['reviewBody'] ?? ( $schema['review'][0]['reviewBody'] ?? '' ) ) === 'Delivered early and exactly as described.', wp_json_encode( $schema ) );

restore_error_handler();
$check( 'no PHP warning while building them', array() === $warnings, implode( '; ', $warnings ) );

$wpdb->delete( $wpdb->prefix . 'wpss_reviews', array( 'id' => $rid ) );
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
