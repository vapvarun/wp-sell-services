<?php
/**
 * Every upload hint states the limit that is actually enforced (Basecamp 10553847173).
 *
 * The requirements form printed PHP's upload_max_filesize while the plugin enforced
 * Settings > System > Max Upload Size, and the delivery dialog, request form and digital
 * file hint printed the setting alone. wpss_get_max_upload_size() is now the smaller of
 * the two, and every hint and the app config read it.
 *
 * Run: wp eval-file tests/test-upload-limit-one-source.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$setting = (int) wpss_get_option( 'advanced', 'max_file_size' );
$php     = null;
$cap     = static function () use ( &$php ) {
	return $php;
};
add_filter( 'upload_size_limit', $cap, 999 );

// The server allows less than the setting: the server wins.
$php = max( 1, $setting - 1 ) * MB_IN_BYTES;
$check( 'a lower PHP limit is the limit shown', $php === wpss_get_max_upload_size(), (string) wpss_get_max_upload_size() );

// The server allows more than the setting: the owner's setting wins.
$php = ( $setting + 50 ) * MB_IN_BYTES;
$check( 'a lower plugin setting is the limit shown', $setting * MB_IN_BYTES === wpss_get_max_upload_size(), (string) wpss_get_max_upload_size() );

$res = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/settings' ) );
$cfg = (array) $res->get_data();
$check( 'the app config reports the same limit', (int) ( $cfg['max_file_size'] ?? -1 ) === wpss_get_max_upload_size(), (string) ( $cfg['max_file_size'] ?? 'missing' ) );

remove_filter( 'upload_size_limit', $cap, 999 );

// Every hint reads the helper, never the raw setting or PHP's limit.
$root  = dirname( __DIR__ );
$hints = array(
	'templates/order/requirements-form.php',
	'templates/order/order-view.php',
	'templates/partials/request-attachments.php',
	'src/Frontend/ServiceWizard.php',
	'src/API/API.php',
);
foreach ( $hints as $file ) {
	$code = (string) file_get_contents( $root . '/' . $file );
	$check( "{$file} states the limit through wpss_get_max_upload_size()", false !== strpos( $code, 'wpss_get_max_upload_size()' ) );
}
$wizard = (string) file_get_contents( $root . '/src/Frontend/ServiceWizard.php' );
$check( 'the digital file hint does not print the raw setting', false === strpos( $wizard, "esc_html( (int) wpss_get_option( 'advanced', 'max_file_size' ) . ' MB' )" ) );
$view = (string) file_get_contents( $root . '/templates/order/order-view.php' );
$check( 'the delivery hint does not print the raw setting', false === strpos( $view, "(int) wpss_get_option( 'advanced', 'max_file_size' )" ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
