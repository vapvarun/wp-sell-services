<?php
/**
 * On the EDD download screen, an added requirement row is the saved row's twin.
 *
 * The saved rows were rendered from Free's requirement-type map, but the
 * "+ Add Requirement" button built its row from a fourth hand-written copy in
 * edd.js offering four types, so a requirement added there could not be a
 * date, number or checkbox. The button now clones a <template> rendered by
 * the same PHP method. The row also gained a phone breakpoint and a label on
 * its icon-only remove button.
 *
 * Run: wp eval-file tests/test-edd-requirement-rows-one-renderer.php
 *
 * Needs Easy Digital Downloads active. Creates one draft download and deletes it.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\Integrations\EDD\EDDProductProvider;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( EDDProductProvider::class ) || ! function_exists( 'edd_has_variable_prices' ) ) {
	echo "SKIP  Pro or Easy Digital Downloads is not active\n";
	return;
}

$pro = dirname( __DIR__, 2 ) . '/wp-sell-services-pro/';

$download = wp_insert_post( array( 'post_type' => 'download', 'post_status' => 'draft', 'post_title' => 'QA EDD requirement rows' ) );
ob_start();
( new EDDProductProvider() )->render_service_fields( (int) $download );
$html = (string) ob_get_clean();
wp_delete_post( (int) $download, true );

$check( 'the screen renders an add-row template', 1 === preg_match( '#<template id="wpss-requirement-template">(.*?)</template>#s', $html, $m ) );
$template = $m[1] ?? '';
foreach ( array_keys( wpss_requirement_type_labels() ) as $type ) {
	$check( "an added row offers the {$type} type", false !== strpos( $template, 'value="' . $type . '"' ) );
}
$check( 'the template indexes its fields with a placeholder', false !== strpos( $template, 'wpss_requirements[__INDEX__][label]' ) );
$check( 'the remove button has an accessible name', false !== strpos( $template, 'aria-label=' ) );

$js = (string) file_get_contents( $pro . 'assets/js/edd.js' );
$check( 'edd.js keeps no requirement-type list of its own', false === strpos( $js, '<option' ) && false === strpos( $js, 'value="textarea"' ) );
$check( 'edd.js clones the template', false !== strpos( $js, 'wpss-requirement-template' ) );

foreach ( array( 'edd.css', 'edd-rtl.css', 'edd.min.css', 'edd-rtl.min.css' ) as $sheet ) {
	$check( "{$sheet} has a phone breakpoint", false !== strpos( (string) file_get_contents( $pro . 'assets/css/' . $sheet ), '@media' ) );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
