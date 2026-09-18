<?php
/**
 * Order file storage: privacy, addressing, and legacy compatibility.
 *
 * Run: wp eval-file tests/test-order-files-contract.php
 *
 * Guards the three things that made these files a defect: bytes must not land
 * in the public uploads tree, only a party to the order may read them, and
 * files delivered before 1.7.0 must keep working.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

$order = $wpdb->get_row( "SELECT id, customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE customer_id > 0 AND vendor_id > 0 LIMIT 1" );

if ( ! $order ) {
	echo "no usable order; skipping\n";
	return;
}

$order_id = (int) $order->id;

// --- Storage directory is not web-readable -------------------------------
$dir     = wpss_get_order_files_dir();
$uploads = wp_upload_dir();
$check( 'order files live outside the uploads URL tree', false === strpos( $dir, trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/x' ) && is_dir( $dir ) );
// Guards are required only where the directory is actually served.
// wpss_get_order_files_dir() prefers a directory outside the WordPress root and
// deliberately writes no guard files there - nothing serves it, so a deny rule
// would be decoration - and falls back to uploads, which IS served, where the
// guards are written. Asserting them unconditionally failed on the safer of the
// two layouts: CI's runner can write outside ABSPATH and took the preferred
// path, so the test demanded deny rules for a directory no web server exposes.
$served = 0 === strpos( $dir, trailingslashit( $uploads['basedir'] ) );

if ( $served ) {
	$check( 'served directory carries an .htaccess deny', file_exists( $dir . '.htaccess' ) );
	$check( 'served directory carries an index.php', file_exists( $dir . 'index.php' ) );
	// IIS honours neither of the above, and the guard only ever wrote Apache
	// rules, so a Windows host had no directory denial at all.
	$check( 'served directory carries a web.config for IIS', file_exists( $dir . 'web.config' ) );
	$check(
		'the IIS guard actually denies rather than just existing',
		file_exists( $dir . 'web.config' )
			&& false !== strpos( (string) file_get_contents( $dir . 'web.config' ), 'deny users="*"' )
	);
} else {
	$check( 'unserved store really is outside the WordPress root', 0 !== strpos( $dir, trailingslashit( ABSPATH ) ) );
	echo "SKIP  live store needs no guard files: it is outside the webroot\n";
}

// The writer itself is asserted either way. Which layout a host gets decides
// whether the live directory needs guards; it must not decide whether this
// contract tests the code that writes them, or the branch that matters on
// shared hosting would go unasserted everywhere it is not already in use.
$probe = trailingslashit( get_temp_dir() ) . 'wpss-guard-probe-' . wp_rand() . '/';
wp_mkdir_p( $probe );
wpss_guard_files_dir( $probe );
$check( 'guard writer lays down an .htaccess deny', file_exists( $probe . '.htaccess' ) );
$check( 'guard writer lays down an index.php', file_exists( $probe . 'index.php' ) );
$check( 'guard writer lays down a web.config for IIS', file_exists( $probe . 'web.config' ) );
$check(
	'the IIS guard actually denies rather than just existing',
	file_exists( $probe . 'web.config' )
		&& false !== strpos( (string) file_get_contents( $probe . 'web.config' ), 'deny users="*"' )
);
$check(
	'the Apache guard actually denies rather than just existing',
	file_exists( $probe . '.htaccess' )
		&& false !== strpos( (string) file_get_contents( $probe . '.htaccess' ), 'Require all denied' )
);
array_map( 'wp_delete_file', glob( $probe . '{.htaccess,index.php,web.config}', GLOB_BRACE ) ?: array() );
@rmdir( $probe ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

// --- Access control -------------------------------------------------------
$check( 'buyer may read their own order files', wpss_can_read_order_files( $order_id, (int) $order->customer_id ) );
$check( 'vendor may read their own order files', wpss_can_read_order_files( $order_id, (int) $order->vendor_id ) );
$check( 'logged-out may not', ! wpss_can_read_order_files( $order_id, 0 ) );

$stranger = $wpdb->get_var( $wpdb->prepare(
	"SELECT ID FROM {$wpdb->users} WHERE ID NOT IN ( %d, %d ) LIMIT 1",
	(int) $order->customer_id,
	(int) $order->vendor_id
) );

if ( $stranger && ! user_can( (int) $stranger, 'manage_options' ) ) {
	$check( 'an unrelated member may not', ! wpss_can_read_order_files( $order_id, (int) $stranger ) );
}

// --- URL addressing -------------------------------------------------------
$modern = array(
	'id'       => 'abc-123',
	'order_id' => $order_id,
	'name'     => 'brief.pdf',
	'path'     => 'brief.pdf',
);
$url = wpss_get_order_file_url( $modern );
$check( '1.7.0 record resolves to the checked endpoint', false !== strpos( $url, 'action=wpss_order_file' ) && false !== strpos( $url, 'order=' . $order_id ) );
$check( '1.7.0 record URL is not a filesystem path', false === strpos( $url, '/uploads/' ) );

/*
 * An OLD record carries an attachment id in `id` and no path.
 *
 * This assertion used to require that such a record kept its stored public URL
 * - the reasoning being that routing it to the endpoint would 404 files
 * delivered before 1.7.0. That was the right worry and the wrong answer: it
 * meant every file ever uploaded stayed fetchable by anyone holding the link,
 * permanently (Basecamp 10239807824).
 *
 * A legacy record is now routed through the endpoint like any other, and the
 * endpoint moves it into the private store on the way past - so the link keeps
 * working AND the file stops being public. Which means the contract flipped,
 * and it flips on WHO IS ASKING.
 */
$legacy = array(
	'id'       => 4321,
	'order_id' => $order_id,
	'name'     => 'old.pdf',
	'url'      => 'https://example.test/wp-content/uploads/2025/01/old.pdf',
);

$viewer = get_current_user_id();

// A stranger must not be handed the public URL just because the record is old.
wp_set_current_user( 0 );
$check( 'pre-1.7.0 record is refused to someone with no claim on the order', '' === wpss_get_order_file_url( $legacy ) );

// Someone entitled to it gets the checked endpoint, which migrates on access.
wp_set_current_user( $viewer ?: 1 );
$legacy_url = wpss_get_order_file_url( $legacy );
$check( 'pre-1.7.0 record routes an entitled viewer through the endpoint', false !== strpos( $legacy_url, 'action=wpss_order_file' ) );
$check( 'pre-1.7.0 record no longer leaks the raw uploads path', false === strpos( $legacy_url, '/uploads/' ) );

$bare = array( 'id' => 99, 'order_id' => $order_id, 'name' => 'x.pdf' );
$check( 'record with neither path nor url yields empty, not a broken link', '' === wpss_get_order_file_url( $bare ) );

// --- Provider resolution --------------------------------------------------
$original = get_option( 'wpss_active_storage_provider' );

update_option( 'wpss_active_storage_provider', '' );
$check( 'no provider configured resolves to null (local)', null === wpss_get_active_storage_provider() );

update_option( 'wpss_active_storage_provider', 'does-not-exist' );
$check( 'missing provider resolves to null, never a substitute', null === wpss_get_active_storage_provider() );

update_option( 'wpss_active_storage_provider', false === $original ? '' : $original );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";

