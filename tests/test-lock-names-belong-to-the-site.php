<?php
/**
 * A named MySQL lock belongs to one site, not to the whole database server.
 *
 * GET_LOCK names are server-wide. The publish, settle, payout-batch, Woo-order
 * and UTC-migration locks used bare names, so two sites on one MySQL server (or
 * two subsites of a network) refused each other: one site's PayPal batch made
 * the other's say "a batch is already running". Every lock now goes through
 * wpss_lock_name(), which adds the site's table prefix.
 *
 * Run: wp eval-file tests/test-lock-names-belong-to-the-site.php
 *
 * Opens a second database connection to hold a lock as "the other site"; writes nothing.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

// 1. Every lock in both plugins is named through the one helper.
$bare = array();
foreach ( array( dirname( __DIR__ ) . '/src', WP_PLUGIN_DIR . '/wp-sell-services-pro/src' ) as $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$src = (string) file_get_contents( (string) $file );
		if ( preg_match_all( '/(?:GET|RELEASE)_LOCK\(\s*%s[^;]*;|(?:GET|RELEASE)_LOCK\(\s*\'[^%]/', $src, $m ) ) {
			foreach ( $m[0] as $call ) {
				// The name is either built by the helper in the call, or held in $lock, which must be.
				$ok = false !== strpos( $call, 'wpss_lock_name(' )
					|| ( false !== strpos( $call, '$lock' ) && preg_match( '/\$lock\s*=\s*wpss_lock_name\(/', $src ) );
				if ( ! $ok ) {
					$bare[] = basename( (string) $file );
				}
			}
		}
	}
}
$check( 'every lock is named through wpss_lock_name()', array() === $bare, implode( ', ', array_unique( $bare ) ) );

// 2. A long prefix still yields a name MySQL accepts.
$prefix        = $wpdb->prefix;
$wpdb->prefix  = str_repeat( 'p', 60 ) . '_';
$long          = wpss_lock_name( 'wpss_publishing_123456_654321' );
$wpdb->prefix  = $prefix;
$check( 'a long name is cut to 64 characters', strlen( $long ) <= 64 && '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $long ) ) );
$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $long ) );

// 3. Another site on the same server holding its batch lock does not block this site.
$host = DB_HOST;
$port = null;
$sock = null;
if ( false !== strpos( $host, ':' ) ) {
	list( $host, $rest ) = explode( ':', $host, 2 );
	is_numeric( $rest ) ? $port = (int) $rest : $sock = $rest;
}
$sock  = $sock ?: ini_get( 'mysqli.default_socket' );
$other = mysqli_init();
if ( ! @$other->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port, $sock ?: null ) ) {
	$check( 'opened a second connection for the other site', false, mysqli_connect_error() );
} else {
	$wpdb->prefix = 'wpssothersite_';
	$theirs       = wpss_lock_name( 'wpss_pro_paypal_payout_batch' );
	$wpdb->prefix = $prefix;
	$other->query( "SELECT GET_LOCK( '" . $other->real_escape_string( $theirs ) . "', 0 )" );

	$mine = wpss_lock_name( 'wpss_pro_paypal_payout_batch' );
	$got  = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $mine ) );
	$check( "the other site's batch lock does not block this site's batch", $got && $mine !== $theirs, "{$mine} vs {$theirs}" );
	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $mine ) );

	// Control: the same site's name is still exclusive.
	$other->query( "SELECT GET_LOCK( '" . $other->real_escape_string( $mine ) . "', 0 )" );
	$check( "this site's own lock still excludes a second holder (control)", '0' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $mine ) ) );
	$other->close();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
