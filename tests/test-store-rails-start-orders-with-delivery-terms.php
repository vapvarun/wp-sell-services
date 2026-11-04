<?php
/**
 * An order from any store rail starts with the package's revisions and a
 * deadline, and FluentCart announces its orders like every other rail.
 *
 * Run: wp eval-file tests/test-store-rails-start-orders-with-delivery-terms.php
 *
 * Reads packages only; nothing is written.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

// A published service whose packages differ in days, so the package choice is visible.
$service_id = 0;
$picked     = array();
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 60, 'fields' => 'ids' ) ) as $candidate ) {
	$packages = wpss_get_service_packages( (int) $candidate );
	$days     = array_unique( array_map( static fn( $p ) => (int) ( $p['delivery_days'] ?? 0 ), $packages ) );
	if ( count( $packages ) >= 2 && count( $days ) >= 2 ) {
		$service_id = (int) $candidate;
		$picked     = array_values( $packages );
		break;
	}
}

if ( ! $service_id ) {
	$check( 'a service with packages of different days exists', false, 'none on this site' );
	return;
}

foreach ( array( 0, 1 ) as $index ) {
	$package = $picked[ $index ];
	$terms   = wpss_order_delivery_terms( $service_id, $index );
	$due     = strtotime( $terms['delivery_deadline'] . ' UTC' );
	$expect  = time() + ( wpss_line_delivery_days( $package, array() ) * DAY_IN_SECONDS );

	$check( "package {$index}: revisions come from the package", (int) ( $package['revisions'] ?? 0 ) === $terms['revisions_included'], (string) $terms['revisions_included'] );
	$check( "package {$index}: the deadline is the package's days from now", abs( $due - $expect ) < 120, $terms['delivery_deadline'] );
	$check( "package {$index}: the original deadline matches", $terms['original_deadline'] === $terms['delivery_deadline'] && 0 === $terms['revisions_used'] );
}

$pro = dirname( __DIR__, 2 ) . '/wp-sell-services-pro/src/Integrations';
if ( is_dir( $pro ) ) {
	foreach ( array( 'EDD/EDDOrderProvider.php', 'FluentCart/FluentCartOrderProvider.php', 'WooCommerce/WCOrderProvider.php' ) as $file ) {
		$source = (string) file_get_contents( $pro . '/' . $file );
		$check( "{$file} takes its delivery terms from the shared helper", false !== strpos( $source, 'wpss_order_delivery_terms(' ) );
	}
	$fluent = (string) file_get_contents( $pro . '/FluentCart/FluentCartOrderProvider.php' );
	$check( 'FluentCart orders go through wpss_after_order_created', false !== strpos( $fluent, 'wpss_after_order_created(' ) );
}
