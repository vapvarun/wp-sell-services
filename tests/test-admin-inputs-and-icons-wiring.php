<?php
/**
 * Source guard for two admin wiring rules that a render test would not see:
 * the number inputs and select-all checkboxes that had no accessible name keep
 * one, and every place admin-vendors.js injects server markup asks for the
 * Lucide icons to be drawn.
 *
 * Run: wp eval-file tests/test-admin-inputs-and-icons-wiring.php
 *
 * Reads source files only; changes nothing.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$dir = dirname( __DIR__ ) . '/';

// Each id must sit in an <input> that carries aria-label, or have a <label for="id"> in the same file.
$inputs = array(
	'src/Admin/Pages/ManualOrderPage.php'       => array( 'wpss-total-override', 'wpss-commission-rate' ),
	'src/Admin/Pages/VendorsPage.php'           => array( 'wpss-commission-rate-detail' ),
	'src/Admin/Pages/ServiceModerationPage.php' => array( 'cb-select-all-1', 'cb-select-all-2' ),
);
foreach ( $inputs as $file => $ids ) {
	$src = (string) file_get_contents( $dir . $file );
	foreach ( $ids as $id ) {
		$named = false !== strpos( $src, 'for="' . $id . '"' );
		if ( preg_match_all( '/<input[^>]*id="' . preg_quote( $id, '/' ) . '"[^>]*>/s', $src, $all ) ) {
			$named = $named || ! array_filter( $all[0], static fn( $tag ) => false === strpos( $tag, 'aria-label' ) );
		}
		$check( "{$id} in " . basename( $file ) . ' has an accessible name', $named );
	}
}

// Every injection of server markup is followed by the icon refresh.
$js    = (string) file_get_contents( $dir . 'assets/js/admin-vendors.js' );
$count = preg_match_all( '/\.html\((?:tabCache\[tab\]|response\.data\.html)\);/', $js );
$fired = preg_match_all( '/\.html\((?:tabCache\[tab\]|response\.data\.html)\);\s*wpssRefreshIcons\(\);/', $js );
$check( 'admin-vendors.js refreshes icons after each of its ' . $count . ' markup injections', $count > 0 && $count === $fired, $fired . ' of ' . $count );
$min = (string) file_get_contents( $dir . 'assets/js/admin-vendors.min.js' );
$check( 'the shipped admin-vendors.min.js carries the refresh', false !== strpos( $min, 'wpss:icons:refresh' ) );
