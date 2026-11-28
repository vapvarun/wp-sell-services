<?php
/**
 * Public pages share one shell (2.0.0 desktop unification, batch 5).
 *
 *   - The vendor directory searches, and its count, list and pages agree on the result.
 *   - Log In, Create account and Become a Vendor each print one H1 of their own on their page,
 *     and an H2 anywhere else the shortcode is placed.
 *   - The single-service sidebar uses the same Contact Me button as the seller block.
 *
 * The requests archive search is covered by tests/test-request-archive-toolbar.php.
 *
 * Run: wp eval-file tests/test-public-pages-shell.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ): void {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Vendor directory search.
$vendors = new \WPSellServices\Services\VendorService();
$all     = $vendors->count_all( array() );
$first   = $vendors->get_all( array( 'limit' => 1 ) );
$name    = $first ? (string) $first[0]->display_name : '';
$check( 'the directory has a vendor to search for', $all > 0 && '' !== $name );
$check( 'a search with no match counts nothing', 0 === $vendors->count_all( array( 'search' => 'zzqx-no-such-vendor' ) ) );
$found = $vendors->count_all( array( 'search' => $name ) );
$rows  = $vendors->get_all(
	array(
		'search' => $name,
		'limit'  => 100,
	)
);
$check( 'a search by name finds that vendor', $found >= 1 && in_array( (int) $first[0]->user_id, array_map( static fn( $v ) => (int) $v->user_id, $rows ), true ) );
$check( 'the count and the list agree for a search', count( $rows ) === $found );
$check( 'a search with a literal % stays a literal', 0 === $vendors->count_all( array( 'search' => '100%zzqx' ) ) );

// One H1 per page: off its own page the shortcode heading is an H2.
foreach ( array( 'login', 'registration', 'become_vendor' ) as $key ) {
	$check( "{$key} heading is an H2 off its own page", 'h2' === \WPSellServices\Frontend\ShellHeader::heading_tag( $key ) );
}
$src = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/Shortcodes.php' );
$check( 'no signup page heading is a hardcoded H2', 0 === preg_match( '/<h2 class="wpss-vr__(title|hero-title)"/', $src ) );
$shell = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/ShellHeader.php' );
$check( 'the three pages are shell pages', 1 === preg_match( "/'become_vendor', 'login', 'registration'/", $shell ) );

// One contact button on the single service.
$packages = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/templates/partials/service-packages.php' );
$check( 'the package sidebar uses the Contact Me button', str_contains( $packages, 'wpss-contact-btn' ) && ! str_contains( $packages, 'wpss-contact-link' ) );

echo $fails ? "{$fails} FAILED\n" : "all passed\n";
