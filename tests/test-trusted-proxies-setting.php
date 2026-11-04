<?php
/**
 * Settings > System > Trusted proxies: the owner can declare a load balancer (Basecamp 10340538963).
 *
 * Run: wp eval-file tests/test-trusted-proxies-setting.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'the reader exists', function_exists( 'wpss_get_trusted_proxies' ) );
if ( ! function_exists( 'wpss_get_trusted_proxies' ) ) {
	echo "FAILED: {$fails}\n";
	return;
}

$before = get_option( 'wpss_trusted_proxies', '' );
$server = $_SERVER;
delete_option( 'wpss_trusted_proxies' );

$ip = static function ( string $remote, string $xff ) {
	$_SERVER['REMOTE_ADDR']          = $remote;
	$_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
	unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
	return wpss_client_ip();
};

// Empty field: today's secure default.
$check( 'empty: a spoofed header from an untrusted source is ignored', '10.1.1.1' === $ip( '10.1.1.1', '203.0.113.9' ) );

// Valid save.
update_option( 'wpss_trusted_proxies', " 10.1.1.1\n\n192.168.0.0/16 , 2001:db8::/32 " );
$check( 'valid entries are stored one per line', "10.1.1.1\n192.168.0.0/16\n2001:db8::/32" === get_option( 'wpss_trusted_proxies' ) );
$check( 'listed proxy: each guest gets their own address', '203.0.113.9' === $ip( '10.1.1.1', '203.0.113.9' ) && '198.51.100.4' === $ip( '10.1.1.1', '198.51.100.4' ) );
$check( 'listed range: a hop inside it is skipped', '203.0.113.9' === $ip( '10.1.1.1', '203.0.113.9, 192.168.4.4' ) );
$check( 'unlisted source still cannot spoof', '10.9.9.9' === $ip( '10.9.9.9', '203.0.113.9' ) );

// The filter still adds to the field.
add_filter( 'wpss_trusted_proxies', static fn( $p ) => array_merge( $p, array( '10.9.9.9' ) ) );
$check( 'the filter receives the field and can add to it', '203.0.113.9' === $ip( '10.9.9.9', '203.0.113.9' ) && '203.0.113.9' === $ip( '10.1.1.1', '203.0.113.9' ) );
remove_all_filters( 'wpss_trusted_proxies' );

// Invalid save is refused whole and keeps the stored list.
global $wp_settings_errors;
$wp_settings_errors = array();
require_once ABSPATH . 'wp-admin/includes/template.php';
update_option( 'wpss_trusted_proxies', "10.1.1.1\n10.0.0.0/33\nnot-an-ip" );
$check( 'an invalid CIDR is refused and the old list kept', "10.1.1.1\n192.168.0.0/16\n2001:db8::/32" === get_option( 'wpss_trusted_proxies' ) );
$errors = get_settings_errors( 'wpss_trusted_proxies' );
$check( 'the refusal says which entries', $errors && str_contains( $errors[0]['message'], '10.0.0.0/33' ) && str_contains( $errors[0]['message'], 'not-an-ip' ) );

$check( 'validator: v4, v6, ranges', wpss_is_ip_or_cidr( '1.2.3.4' ) && wpss_is_ip_or_cidr( '1.2.3.0/24' ) && wpss_is_ip_or_cidr( '::1' ) && wpss_is_ip_or_cidr( '2001:db8::/128' ) );
$check( 'validator: rejects bad bits and junk', ! wpss_is_ip_or_cidr( '1.2.3.0/33' ) && ! wpss_is_ip_or_cidr( '::/129' ) && ! wpss_is_ip_or_cidr( '1.2.3.0/' ) && ! wpss_is_ip_or_cidr( '1.2.3.0/x' ) && ! wpss_is_ip_or_cidr( 'host.example' ) );

// REST entry point: core settings route lists it.
$reg = get_registered_settings();
$check( 'registered for the REST settings route', ! empty( $reg['wpss_trusted_proxies']['show_in_rest'] ) );

// The settings page shows sanitizer errors instead of always "Settings saved.".
$admin = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Admin/Admin.php' );
$check( 'settings toast is built from get_settings_errors()', str_contains( $admin, 'foreach ( get_settings_errors() as $notice )' ) );

$_SERVER = $server;
update_option( 'wpss_trusted_proxies', $before );
if ( '' === $before ) {
	delete_option( 'wpss_trusted_proxies' );
}
echo $fails ? "FAILED: {$fails}\n" : "OK\n";
