<?php
/**
 * wpss_get_legal_links() is the single source for Terms/Privacy.
 *
 * Run: wp eval-file tests/test-legal-links-contract.php
 *
 * Guards the two things that made the setting a lie: an unmapped page must be
 * null (never 0, never an empty link), and the app config endpoint must report
 * exactly what checkout renders. No declare(strict_types) - wp eval-file evals.
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$original = get_option( 'wpss_terms_page' );

// Unmapped: null, not 0, not ''.
update_option( 'wpss_terms_page', 0 );
$links = wpss_get_legal_links();
$check( 'unmapped terms is null', null === $links['terms_url'] );
$check( 'keys are exactly terms_url + privacy_policy_url', array( 'privacy_policy_url', 'terms_url' ) === ( static function ( $k ) { sort( $k ); return $k; } )( array_keys( $links ) ) );

// Pointing at a non-published page must not produce a link.
$draft = wp_insert_post( array( 'post_title' => 'wpss legal contract draft', 'post_type' => 'page', 'post_status' => 'draft' ) );
update_option( 'wpss_terms_page', $draft );
$check( 'draft terms page yields null', null === wpss_get_legal_links()['terms_url'] );

// Published page yields its permalink.
wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ) );
$check( 'published terms page yields its permalink', get_permalink( $draft ) === wpss_get_legal_links()['terms_url'] );

// The app config endpoint must not fork from the helper.
$config = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/settings' ) )->get_data();
$legal  = $config['app']['legal'] ?? $config['legal'] ?? array();
$expect = wpss_get_legal_links();
// array_key_exists, not ??: null IS the correct value for an unmapped page,
// and ?? cannot tell that apart from the key being missing entirely.
foreach ( array( 'terms_url', 'privacy_policy_url' ) as $key ) {
	$check(
		sprintf( 'app config %s matches helper', $key ),
		array_key_exists( $key, $legal ) && $legal[ $key ] === $expect[ $key ]
	);
}

wp_delete_post( $draft, true );
update_option( 'wpss_terms_page', $original );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
