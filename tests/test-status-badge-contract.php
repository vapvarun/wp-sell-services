<?php
/**
 * Status badge contract.
 *
 * The same order state read "Awaiting payment" on a chip and "Pending Payment" on its badge, badges came in
 * three cases from CSS text-transform, and an admin "Hired" request printed as plain text because the palette
 * had no rule for it. One label map per family, one helper (wpss_status_class()) and one palette fix all three.
 * This asserts the palette has a colour for every status a badge can show, on the frontend and in wp-admin,
 * and that every label is sentence case so no CSS has to fix the case.
 *
 * Run: wp eval-file tests/test-status-badge-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Models\ExtensionRequest;
use WPSellServices\Models\Review;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\ModerationService;
use WPSellServices\Services\ProposalService;
use WPSellServices\Services\VendorService;

$fail = 0;
$css  = array(
	'design-system.css' => (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/design-system.css' ),
	'admin.css'         => (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/admin.css' ),
);
$css = array_map( static fn( $body ) => (string) preg_replace( '#/\*.*?\*/#s', '', $body ), $css ); // A class named in a comment is not a colour.

$labelled = array(
	'order'      => wpss_get_order_statuses(),
	'request'    => BuyerRequestService::get_statuses(),
	'dispute'    => DisputeService::get_statuses(),
	'proposal'   => ProposalService::get_statuses(),
	'extension'  => ExtensionRequest::get_statuses(),
	'review'     => Review::get_statuses(),
	'moderation' => ModerationService::get_statuses(),
);

// Keys rendered through wpss_status_class() whose labels live beside their template.
$keys = array(
	'vendor'     => array_keys( VendorService::STATUS_EVENTS ),
	'offer'      => array( 'pending', 'accepted', 'declined', 'withdrawn', 'expired' ),
	'service'    => array( 'publish', 'paused', 'pending', 'draft', 'rejected', 'private', 'future' ),
	'withdrawal' => array( 'pending', 'approved', 'processing', 'completed', 'rejected', 'cancelled' ),
	'delivery'   => array( 'pending', 'accepted', 'revision_requested', 'rejected' ),
	'report'     => array( 'upheld', 'dismissed' ),
);
foreach ( $labelled as $family => $map ) {
	$keys[ $family ] = array_keys( $map );
}

foreach ( $keys as $family => $list ) {
	foreach ( $list as $status ) {
		$class = 'wpss-status-' . str_replace( '_', '-', sanitize_key( $status ) );
		foreach ( $css as $file => $body ) {
			if ( ! preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![\w-])/', $body ) ) {
				echo "FAIL {$family} '{$status}' has no .{$class} colour in {$file}\n";
				++$fail;
			}
		}
	}
}

foreach ( $labelled as $family => $map ) {
	foreach ( $map as $status => $label ) {
		$words = preg_split( '/\s+/', trim( (string) $label ) );
		$first = array_shift( $words );
		$bad   = array_filter( $words, static fn( $w ) => preg_match( '/^\p{Lu}\p{Ll}/u', $w ) );
		if ( '' === $first || ! preg_match( '/^\p{Lu}/u', $first ) || $bad ) {
			echo "FAIL {$family} '{$status}' label '{$label}' is not sentence case\n";
			++$fail;
		}
	}
}

// No stylesheet may force the case of a badge back on.
foreach ( glob( WPSS_PLUGIN_DIR . 'assets/css/*.css' ) as $file ) {
	if ( str_contains( $file, '.min.' ) || str_contains( $file, '-rtl.' ) ) {
		continue;
	}
	// "Pro" is a product mark, not a status: its capitals are the brand's.
	if ( preg_match_all( '/([^{}]*(?:status|badge)[^{}]*)\{[^}]*text-transform:\s*(uppercase|capitalize)/', (string) file_get_contents( $file ), $m ) ) {
		foreach ( array_filter( $m[1], static fn( $sel ) => ! preg_match( '/pro-badge|pro-teaser__badge/', $sel ) ) as $sel ) {
			echo 'FAIL ' . basename( $file ) . ' forces case on ' . trim( preg_replace( '/\s+/', ' ', $sel ) ) . "\n";
			++$fail;
		}
	}
}

echo $fail ? "{$fail} FAILED\n" : "PASS status badge contract\n";
