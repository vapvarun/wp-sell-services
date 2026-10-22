<?php
/**
 * The vendor's service editor opens when the gallery, packages or FAQ meta is
 * not the array the wizard writes (a string, a bare attachment ID, an empty
 * string). Before, normalize_gallery( array ) threw a TypeError and the editor
 * answered 500 for every service an import had written.
 *
 * Run: wp eval-file tests/test-wizard-opens-with-odd-meta.php
 *
 * Rewrites three meta keys on one existing QA service and restores them on any
 * exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\ServiceWizard;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$ids = get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'author' => 7831, 'numberposts' => 1, 'fields' => 'ids' ) );
if ( ! $ids ) {
	echo "SKIP  no QA service to open\n";
	return;
}
$id   = (int) $ids[0];
$keys = array( '_wpss_gallery', '_wpss_packages', '_wpss_faqs' );
$was  = array();
foreach ( $keys as $key ) {
	$was[ $key ] = metadata_exists( 'post', $id, $key ) ? get_post_meta( $id, $key, true ) : null;
}
$restore = static function () use ( $id, $was ) {
	foreach ( $was as $key => $value ) {
		if ( null === $value ) {
			delete_post_meta( $id, $key );
		} else {
			update_post_meta( $id, $key, $value );
		}
	}
};

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
