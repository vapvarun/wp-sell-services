<?php
/**
 * A single service carries one Service JSON-LD, whichever SEO plugin is active.
 *
 * Run: wp eval-file tests/test-seo-service-schema-once.php
 *
 * Yoast and Rank Math add our Service piece to their own graph
 * (ServiceSchemaPiece, rank_math/json_ld). SchemaMarkup printed its own copy
 * next to it, so Search Console could report duplicate structured data. Their
 * integrations now return false on `wpss_output_service_schema` once their
 * graph carries the piece, which skips only the single-service JSON-LD. When
 * their schema output is off (Rank Math before its setup wizard) ours stays. Archive, category and vendor schema have no
 * replacement in those plugins and must keep printing, and AIOSEO (whose
 * integration only steps aside) must keep ours.
 *
 * Each case starts the real integration's init(), renders the real wp_head
 * schema output for a real query, and counts the JSON-LD blocks.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\SEO\AioseoIntegration;
use WPSellServices\SEO\RankMathIntegration;
use WPSellServices\SEO\SchemaMarkup;
use WPSellServices\SEO\YoastIntegration;

global $wp_query, $wp_the_query;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$service_id = wpss_test_live_service();
$terms      = wp_get_object_terms( $service_id, 'wpss_service_category' );
$term       = $terms[0] ?? null;
$page_id    = (int) get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
$check( 'fixture: a live service in a category, and a page to stand in for the vendor page', $service_id && $term && $page_id );

// Yoast is not installed under test; ServiceSchemaPiece only needs its base class to load.
if ( ! class_exists( 'Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece' ) ) {
	eval( 'namespace Yoast\WP\SEO\Generators\Schema; abstract class Abstract_Schema_Piece { public $context; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only stub.
}

/*
 * Point the main query at a surface, run what the SEO plugin does at wp_head
 * priority 1 (build its graph), then render our wp_head schema at priority 10
 * and return the @type of every JSON-LD block we printed.
 */
$types_on = static function ( array $query_args, array $vars, ?callable $seo_head ) use ( &$wp_query, &$wp_the_query ): array {
	$wp_the_query = new WP_Query( $query_args );
	$wp_query     = $wp_the_query;
	foreach ( $vars as $key => $value ) {
		set_query_var( $key, $value );
	}
	if ( $wp_query->have_posts() ) {
		$wp_query->the_post();
	}
	if ( $seo_head ) {
		$seo_head();
	}
	$schema = new SchemaMarkup();
	ob_start();
	$schema->output_schema();
	$schema->output_breadcrumb_schema();
	$html = (string) ob_get_clean();
	wp_reset_postdata();

	preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $m );
	return array_map( static fn( $json ) => implode( '+', (array) ( json_decode( $json, true )['@type'] ?? '?' ) ), $m[1] );
};

$surfaces = array(
	'service'  => array( array( 'p' => $service_id, 'post_type' => 'wpss_service', 'post_status' => 'any' ), array() ),
	'archive'  => array( array( 'post_type' => 'wpss_service' ), array() ),
	'category' => array( array( 'wpss_service_category' => $term ? $term->slug : '' ), array() ),
	'vendor'   => array( array( 'page_id' => $page_id ), array( 'wpss_vendor' => get_userdata( (int) get_post_field( 'post_author', $service_id ) )->user_nicename ) ),
);

// label => [ integration class, what the SEO plugin does in its head, expected count of our Service blocks ].
$cases = array(
	'no SEO plugin'                         => array( null, null, 1 ),
	'Yoast, graph built'                    => array( YoastIntegration::class, static fn( $i ) => $i->add_schema_pieces( array(), null ), 0 ),
	'Yoast, schema output off'              => array( YoastIntegration::class, null, 1 ),
	'Rank Math, graph built'                => array( RankMathIntegration::class, static fn( $i ) => $i->add_schema( array(), null ), 0 ),
	'Rank Math, setup wizard not finished'  => array( RankMathIntegration::class, null, 1 ),
	'AIOSEO'                                => array( AioseoIntegration::class, null, 1 ),
);

foreach ( $cases as $label => list( $class, $head, $expected ) ) {
	remove_all_filters( 'wpss_output_service_schema' );
	$integration = $class ? new $class() : null;
	if ( $integration ) {
		$integration->init();
	}

	$found = array();
	foreach ( $surfaces as $surface => list( $args, $vars ) ) {
		$found[ $surface ] = $types_on( $args, $vars, $head ? static fn() => $head( $integration ) : null );
	}
	echo "      {$label}: " . wp_json_encode( $found ) . "\n";

	$service_blocks = count( array_filter( $found['service'], static fn( $t ) => str_contains( $t, 'Service' ) ) );
	$check( "{$label}: single service prints {$expected} Service JSON-LD of ours", $expected === $service_blocks );
	$check( "{$label}: single service keeps its BreadcrumbList", in_array( 'BreadcrumbList', $found['service'], true ) );
	$check( "{$label}: archive keeps its ItemList", in_array( 'ItemList', $found['archive'], true ) );
	$check( "{$label}: category keeps its schema and BreadcrumbList", count( $found['category'] ) >= 2 && in_array( 'BreadcrumbList', $found['category'], true ) );
	$check( "{$label}: vendor page keeps its schema", array() !== $found['vendor'] );
}
remove_all_filters( 'wpss_output_service_schema' );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
