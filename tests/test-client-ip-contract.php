<?php
/**
 * One answer to "who is this visitor", and a spoofed header cannot change it.
 *
 * Run: wp eval-file tests/test-client-ip-contract.php
 *
 * RateLimiter and AuditLogService took the FIRST value of X-Forwarded-For or
 * CF-Connecting-IP from any request, so a script sending a different header
 * each time was a different visitor each time: 35 live searches against a
 * 30/min guest limit, 0 blocked (Basecamp 10336398096). AuthController kept a
 * third, separate limiter of its own.
 *
 * Headers are trusted only from the hop that set them: Cloudflare's published
 * ranges for CF-Connecting-IP, and proxies the owner declares for
 * X-Forwarded-For. Nothing is written that is not removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Core\RateLimiter;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$saved_server = $_SERVER;
$ip           = static fn(): string => function_exists( 'wpss_client_ip' ) ? (string) wpss_client_ip() : 'MISSING';
$request      = static function ( string $remote, array $headers = array() ): void {
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_REAL_IP'] );
	$_SERVER['REMOTE_ADDR'] = $remote;
	foreach ( $headers as $key => $value ) {
		$_SERVER[ $key ] = $value;
	}
};

$touched = array();

try {
	// --- 1. A plain connection: the header is ignored ---------------------------
	$request( '203.0.113.7', array( 'HTTP_X_FORWARDED_FOR' => '198.51.100.99', 'HTTP_CF_CONNECTING_IP' => '198.51.100.98' ) );
	$check( 'a direct visitor is REMOTE_ADDR, whatever the headers claim', '203.0.113.7' === $ip() );

	// --- 2. Cloudflare: honoured only when the connection comes from Cloudflare --
	$request( '173.245.48.10', array( 'HTTP_CF_CONNECTING_IP' => '198.51.100.9' ) );
	$check( 'behind Cloudflare, CF-Connecting-IP is the visitor', '198.51.100.9' === $ip() );
	$request( '2606:4700::1', array( 'HTTP_CF_CONNECTING_IP' => '2001:db8::9' ) );
	$check( '  and over IPv6 too', '2001:db8::9' === $ip() );

	// --- 3. A declared proxy: the right-most hop it did not add ----------------
	$trust = static fn() => array( '10.0.0.0/8' );
	add_filter( 'wpss_trusted_proxies', $trust );
	$request( '10.0.0.5', array( 'HTTP_X_FORWARDED_FOR' => '198.51.100.20, 10.0.0.9' ) );
	$check( 'behind a declared proxy, X-Forwarded-For is read', '198.51.100.20' === $ip() );
	$request( '10.0.0.5', array( 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.20' ) );
	$check( '  and a forged left-most hop is ignored (right-most untrusted wins)', '198.51.100.20' === $ip() );
	remove_filter( 'wpss_trusted_proxies', $trust );
	$request( '10.0.0.5', array( 'HTTP_X_FORWARDED_FOR' => '198.51.100.20' ) );
	$check( 'an undeclared proxy is not trusted', '10.0.0.5' === $ip() );

	// --- 4. The rate limit holds against rotating headers ----------------------
	$blocked = 0;
	for ( $i = 1; $i <= 30; $i++ ) {
		$fake = '198.51.100.' . $i;
		$request( '203.0.113.7', array( 'HTTP_X_FORWARDED_FOR' => $fake, 'HTTP_CF_CONNECTING_IP' => $fake ) );
		$touched[] = $fake;
		if ( RateLimiter::check_and_track( 'public_signup' ) ) {
			++$blocked;
		}
	}
	$check( sprintf( 'rotating X-Forwarded-For does not reset the 20/hour signup limit (%d of 30 blocked)', $blocked ), $blocked >= 10 );

	// --- 5. One reader of the client address ------------------------------------
	$readers = array();
	$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR . 'src', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		$src = (string) file_get_contents( $file->getPathname() );
		if ( preg_match( "/\\\$_SERVER\[\s*'(REMOTE_ADDR|HTTP_X_FORWARDED_FOR|HTTP_CF_CONNECTING_IP|HTTP_X_REAL_IP)'/", $src ) ) {
			$readers[] = str_replace( WPSS_PLUGIN_DIR, '', $file->getPathname() );
		}
	}
	$check( 'only wpss_client_ip() reads the client address (found in: ' . implode( ', ', $readers ) . ')', array( 'src/functions/misc.php' ) === $readers );
} finally {
	// Remove every limiter bucket this script could have created, old keying or new.
	foreach ( array_merge( array( '203.0.113.7', '0.0.0.0' ), $touched ) as $addr ) {
		delete_transient( 'wpss_rate_public_signup_ip_' . md5( $addr ) );
	}
	$_SERVER = $saved_server;
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
