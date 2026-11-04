<?php
/**
 * The vendor's service editor opens when the gallery, packages or FAQ meta is
 * not the array the wizard writes (a string, a bare attachment ID, an empty
 * string). Before, normalize_gallery( array ) threw a TypeError and the editor
 * answered 500 for every service an import had written.
 *
 * Run: wp eval-file tests/test-wizard-opens-with-odd-meta.php
 *
 * Writes three odd meta values on a throwaway service, deleted on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\ServiceWizard;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

// A throwaway service, removed with its meta at shutdown, so no real listing is touched.
$f    = wpss_test_fixtures();
$id   = ( $f->service )( 'draft' );
$keys = array( '_wpss_gallery', '_wpss_packages', '_wpss_faqs' );

$wizard = new ServiceWizard();
$read   = new ReflectionMethod( $wizard, 'get_service_data' );

foreach ( array( 'a string' => '256', 'an empty string' => '', 'an int' => 256 ) as $label => $odd ) {
	foreach ( $keys as $key ) {
		update_post_meta( $id, $key, $odd );
	}
	try {
		$data = $read->invoke( $wizard, $id );
		$check( "editor data builds when the meta is {$label}", is_array( $data ) && isset( $data['gallery'], $data['packages'] ) );
	} catch ( \Throwable $e ) {
		$check( "editor data builds when the meta is {$label}", false, $e->getMessage() );
	}
}
