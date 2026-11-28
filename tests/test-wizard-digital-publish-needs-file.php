<?php
/**
 * The wizard's publish answer refuses a digital product with no file, on the server.
 *
 * Only the wizard's script checked for the file: a publish request that skipped it put a digital
 * product live with nothing to download, listed but refused at checkout. The server now asks the
 * shared validator with the saved draft's file, as the editor and REST publish checks already do.
 *
 * Run: wp eval-file tests/test-wizard-digital-publish-needs-file.php
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

$f        = wpss_test_fixtures();
$draft    = ( $f->service )( 'draft' );
$wizard   = new \WPSellServices\Frontend\ServiceWizard();
$validate = new ReflectionMethod( $wizard, 'validate_service_data' );
$validate->setAccessible( true );
$needs    = __( 'Please upload the file buyers download.', 'wp-sell-services' );
$errors   = static fn( string $type ) => (array) $validate->invoke(
	$wizard,
	array(
		'title'        => 'QA digital product',
		'description'  => str_repeat( 'A clear description of the file. ', 5 ),
		'service_type' => $type,
		'packages'     => array( 'basic' => array( 'name' => 'Basic', 'price' => 10, 'delivery_time' => 1 ) ),
	),
	$draft
);

$check( 'digital product, no file on the draft: publish refused with the file message', in_array( $needs, $errors( 'digital' ), true ), wp_json_encode( $errors( 'digital' ) ) );
$check( 'fixed-price service: no file asked for', ! in_array( $needs, $errors( 'fixed' ), true ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
