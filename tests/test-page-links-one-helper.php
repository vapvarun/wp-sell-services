<?php
/**
 * Plugin page links come from wpss_get_page_url() / wpss_get_page_id().
 *
 * Run: wp eval-file tests/test-page-links-one-helper.php
 *
 * Links were built three ways: the page map read raw (a trashed page still
 * counted), hard-coded /services/ and /dashboard/ paths, and two different
 * become-a-vendor slugs (/become-a-vendor/ in admin, /become-a-seller/ on the
 * vendor dashboard). The helpers drop a trashed or drafted page and fall back
 * to the same default slugs, so no shipped URL changes.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// The page map is read raw only where it is edited, written or diagnosed.
$allowed  = array( 'src/Core/Activator.php', 'src/Core/Plugin.php', 'src/Admin/Settings.php', 'src/Admin/Pages/SetupWizardPage.php', 'src/CLI/PreflightCommand.php', 'src/functions/vendors.php', 'src/functions/urls.php' );
$raw      = array();
$hardcode = array();
$it       = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$rel = str_replace( WPSS_PLUGIN_DIR, '', (string) $file );
	if ( '.php' !== substr( $rel, -4 ) || ! preg_match( '#^(src|templates)/#', $rel ) ) {
		continue;
	}
	foreach ( file( (string) $file ) as $n => $line ) {
		if ( false !== strpos( $line, "get_option( 'wpss_pages'" ) && ! in_array( $rel, $allowed, true ) ) {
			$raw[] = $rel . ':' . ( $n + 1 );
		}
		if ( preg_match( "#home_url\(\s*'/(services|dashboard|become-a-)#", $line ) ) {
			$hardcode[] = $rel . ':' . ( $n + 1 );
		}
	}
}
$check( 'no link reads the page map raw', array() === $raw, implode( ', ', $raw ) );
$check( 'no link hard-codes a plugin page path', array() === $hardcode, implode( ', ', $hardcode ) );

$wizard = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Pages/SetupWizardPage.php' );
$check( 'the setup wizard counts only published pages as set up', 2 <= substr_count( $wizard, "wpss_get_page_id( 'dashboard' )" ) + substr_count( $wizard, 'wpss_get_page_id( $key )' ) );

// One become-a-vendor link.
foreach ( array( 'src/Admin/Pages/VendorsPage.php', 'templates/myaccount/vendor-dashboard.php' ) as $rel ) {
	$check( "{$rel} links to wpss_get_become_vendor_url()", false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . $rel ), 'wpss_get_become_vendor_url()' ) );
}

// Behaviour: a drafted checkout page is not the payment page, and the app sees no link to it.
$pages = get_option( 'wpss_pages', array() );
$draft = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'QA drafted checkout' ) );
update_option( 'wpss_pages', array_merge( (array) $pages, array( 'become_vendor' => $draft ) ) );
try {
	$settings = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/settings' ) )->get_data();
	$check( 'a drafted page is not offered to the app', array_key_exists( 'become_vendor', (array) ( $settings['pages'] ?? array() ) ) && null === $settings['pages']['become_vendor'] );
	$check( '  and the dashboard id matches wpss_get_page_id()', ( wpss_get_page_id( 'dashboard' ) ?: null ) === ( $settings['pages']['dashboard'] ?? 'missing' ) );
} finally {
	update_option( 'wpss_pages', $pages );
	wp_delete_post( $draft, true );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
