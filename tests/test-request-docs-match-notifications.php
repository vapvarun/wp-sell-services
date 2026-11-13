<?php
/**
 * The buyer request docs promise only the notifications the code sends.
 *
 * Run: wp eval-file tests/test-request-docs-match-notifications.php
 *
 * The docs said vendors are notified when a request is posted, that closing a
 * request notifies pending proposals, and that withdrawing a proposal notifies
 * the buyer (Basecamp 10488472455, comment 2). Nothing listens on
 * wpss_buyer_request_created or wpss_proposal_withdrawn, the only listener on
 * wpss_buyer_request_status_changed acts on expiry, and the dashboard Close
 * button only moves the request to draft. Each claim is allowed again the day
 * the code sends that notification.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$docs = '';
foreach ( glob( WPSS_PLUGIN_DIR . 'docs/website/buyer-requests/*.md' ) as $file ) {
	$docs .= (string) file_get_contents( $file ) . "\n";
}
$check( 'fixture: the buyer request docs were read', str_contains( $docs, 'proposal' ) );

$notifies_on_post = has_action( 'wpss_buyer_request_created' );
$check(
	'no doc says vendors are notified of a new request, while nothing sends it',
	$notifies_on_post || ! preg_match( '/\bvendors are notified\b(?! *(when|if) (a|your) proposal)/i', $docs )
);

// The one status listener (Plugin.php) only acts on expiry; closing writes draft.
$check(
	'no doc says closing a request notifies pending proposals',
	! preg_match( '/(pending proposals|vendors with pending proposals) are notified/i', $docs )
);

$check(
	'no doc says the buyer is notified of a withdrawn proposal, while nothing sends it',
	has_action( 'wpss_proposal_withdrawn' ) || ! preg_match( '/Withdraw\*\*\. The buyer is notified/i', $docs )
);

$check( 'the close docs name the real button and say vendors are not notified', str_contains( $docs, 'click **Close** next to the request in **Dashboard > Buyer Requests**' ) && str_contains( $docs, 'are not notified' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
