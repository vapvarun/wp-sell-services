<?php
/**
 * A vendor with no services is sent to create one, not to an empty list (Basecamp 10550911651).
 *
 * A new vendor lands on Sales Orders, whose empty state offered View My Services, which opens an
 * empty Services list. With no service at all (drafts and pending ones count) it now offers
 * Create your first service, to the service editor.
 *
 * Run: wp eval-file tests/test-new-vendor-sales-next-step.php
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

$f      = wpss_test_fixtures();
$render = static function () use ( $f ): array {
	wp_set_current_user( $f->vendor );
	$user_id        = $f->vendor;
	$is_vendor      = true;
	$vendor_service = new \WPSellServices\Services\VendorService();
	ob_start();
	include dirname( __DIR__ ) . '/templates/dashboard/sections/sales.php';
	$html = (string) ob_get_clean();
	preg_match( '#<div class="wpss-empty-state">.*?<a href="([^"]+)" class="wpss-btn wpss-btn--primary">\s*([^<]+?)\s*</a>#s', $html, $m );
	return array( trim( $m[2] ?? '' ), html_entity_decode( $m[1] ?? '' ) );
};

try {
	list( $label, $url ) = $render();
	$check( 'No services: Create Your First Service', 'Create your first service' === $label, $label );
	$check( '  to the service editor', wpss_get_service_editor_url() === $url, $url );

	( $f->service )( 'draft' );
	list( $label, $url ) = $render();
	$check( 'A draft service: View My Services, as before', 'View my services' === $label, $label );
} finally {
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
