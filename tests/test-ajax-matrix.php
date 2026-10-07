<?php
/**
 * Every AJAX action answers without a fatal, and none succeeds without a
 * nonce unless it is a public read by design.
 *
 * Run: wp eval-file tests/test-ajax-matrix.php
 *
 * 108 AJAX actions had no check at all. This POSTs each one to the real
 * admin-ajax.php with no nonce and an empty body: the nopriv actions as a
 * logged-out visitor, the rest as a logged-in buyer (a real session cookie).
 * A 500 or a PHP fatal in the body fails. A {"success":true} answer fails
 * unless the action is listed in $nonce_free with its reason - an action that
 * acts without a nonce is a CSRF hole.
 *
 * Needs the site to be served at home_url(); prints SKIP when nothing listens.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';
require_once __DIR__ . '/Factories/SessionCookies.php';

use WPSellServices\Tests\Factories\SessionCookies;
use WPSellServices\Tests\Factories\UserFactory;

$probe = wp_remote_get( home_url( '/' ), array( 'timeout' => 5, 'sslverify' => false ) );
if ( is_wp_error( $probe ) ) {
	echo 'SKIP  nothing is serving ' . home_url( '/' ) . ': ' . $probe->get_error_message() . "\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	if ( ! $ok ) {
		echo 'FAIL  ' . $label . "\n";
		++$fails;
	}
};

// Actions that may answer success with no nonce, each for a stated reason.
$nonce_free = array(
	// A guest's search is a public read (a WP_Query over published services);
	// the handler checks the nonce for members and writes nothing.
	'wpss_live_search' => true,
);

// Every action registered in src/, split by who may call it.
$src = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/src', FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( 'php' === $f->getExtension() ) {
		$src .= file_get_contents( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
preg_match_all( "/['\"]wp_ajax_(nopriv_)?([a-z0-9_]+)['\"]/", $src, $m, PREG_SET_ORDER );
$actions = array(
	'anon'  => array(),
	'buyer' => array(),
);
foreach ( $m as $hit ) {
	$actions[ $hit[1] ? 'anon' : 'buyer' ][ $hit[2] ] = true;
}

$buyer   = UserFactory::customer( array( 'user_login' => 'wpss_ajaxmatrix_' . wp_generate_password( 6, false, false ) ) );
$cookies = array(
	'anon'  => array(),
	'buyer' => SessionCookies::for_user( $buyer->ID ),
);

$calls = 0;

try {
	foreach ( $actions as $who => $list ) {
		foreach ( array_keys( $list ) as $action ) {
			$res = wp_remote_post(
				admin_url( 'admin-ajax.php' ),
				array(
					'timeout'   => 20,
					'sslverify' => false,
					'cookies'   => $cookies[ $who ],
					'body'      => array( 'action' => $action ),
				)
			);
			++$calls;

			if ( is_wp_error( $res ) ) {
				$check( "{$action} ({$who}) request failed: " . $res->get_error_message(), false );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $res );
			$body = (string) wp_remote_retrieve_body( $res );
			$json = json_decode( $body, true );

			$check( "{$action} ({$who}) -> {$code}", 500 !== $code );
			$check( "{$action} ({$who}) printed a PHP error", ! preg_match( '/(Fatal error|Uncaught|critical error|Warning:|Notice:)/', $body ) );
			$check( "{$action} ({$who}) succeeded with no nonce; add it to \$nonce_free with a reason if that is by design", ! ( is_array( $json ) && ! empty( $json['success'] ) ) || isset( $nonce_free[ $action ] ) );
		}
	}
} finally {
	SessionCookies::end( $buyer->ID );
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $buyer->ID ) ); // phpcs:ignore WordPress.DB
	UserFactory::cleanup();
}

echo count( $actions['anon'] ) . ' public and ' . count( $actions['buyer'] ) . " member actions, {$calls} requests\n";
echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
