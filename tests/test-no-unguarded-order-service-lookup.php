<?php
/**
 * No code reads an order's service straight from `$order->service_id`.
 *
 * Request, milestone, extension and tip orders have service_id 0, and
 * get_post( 0 ) / get_the_title( 0 ) / get_permalink( 0 ) return the page being
 * viewed, so a notification, REST row or CSV named the wrong thing ("Dashboard").
 * Titles come from wpss_get_order_title(); a post lookup is guarded by an id > 0.
 *
 * Run: wp eval-file tests/test-no-unguarded-order-service-lookup.php
 *
 * Read-only source scan of Free and, when it sits beside it, Pro.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$roots = array( dirname( __DIR__ ) . '/src' );
$pro   = dirname( dirname( __DIR__ ) ) . '/wp-sell-services-pro/src';
if ( is_dir( $pro ) ) {
	$roots[] = $pro;
}

// A bare lookup on the raw id: get_post( $order->service_id ), get_the_title( $row->service_id ) ...
$bare = '/\b(?:get_post|get_the_title|get_permalink)\(\s*\$(?:order|review|row|tip_order|parent|parent_order)->service_id\s*\)/';

$hits = array();
foreach ( $roots as $root ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		foreach ( file( $file->getPathname() ) as $n => $line ) {
			// A lookup behind an id check ( > 0, or a plain truth test ) is the guarded form.
			if ( preg_match( '/^\s*(?:\/\/|\*|\/\*)/', $line ) ) {
				continue;
			}
			if ( preg_match( $bare, $line ) && ! preg_match( '/service_id\s*(?:>\s*0|\?)/', $line ) ) {
				$hits[] = str_replace( dirname( dirname( __DIR__ ) ) . '/', '', $file->getPathname() ) . ':' . ( $n + 1 );
			}
		}
	}
}

$check( 'no bare get_post / get_the_title / get_permalink on an order service id', array() === $hits, implode( ', ', $hits ) );
