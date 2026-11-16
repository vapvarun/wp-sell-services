<?php
/**
 * The theme-title suppression never blanks a title read from wp_head.
 *
 * Run: wp eval-file tests/test-shell-title-kept-in-head.php
 *
 * ShellHeader blanks the queried post's title in the body so the plugin header is the only H1. It also
 * blanked it in the head, so a SEO plugin reading get_the_title() there (Rank Math's Service schema)
 * dropped the Service name.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f   = wpss_test_fixtures();
$sid = (int) ( $f->service )( 'publish' );
$raw = get_post( $sid )->post_title;

global $wp_query, $wp_the_query;
$wp_the_query = new WP_Query( array( 'p' => $sid, 'post_type' => 'wpss_service' ) ); // phpcs:ignore
$wp_query     = $wp_the_query; // phpcs:ignore
add_filter( 'wpss_suppress_theme_title', '__return_true' );

$body = get_the_title( $sid );
$head = '';
remove_all_actions( 'wp_head' ); // Only our probe runs: the real head callbacks need a full page.
add_action( 'wp_head', static function () use ( $sid, &$head ) { $head = get_the_title( $sid ); }, 99999 );
do_action( 'wp_head' );

$check( 'body: the shell still blanks the theme title (one H1)', '' === $body, var_export( $body, true ) );
$check( 'head: the real title is readable', $raw === $head, var_export( $head, true ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
