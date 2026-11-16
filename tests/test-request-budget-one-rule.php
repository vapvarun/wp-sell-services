<?php
/**
 * Guard: every reader shows a buyer request's budget by the one rule.
 *
 * A maximum typed alone used to read "Negotiable" on the request page and card
 * while the dashboard and admin list said "Up to". Run: wp eval-file tests/test-request-budget-one-rule.php
 *
 * @package WPSellServices
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/exit-on-fail.php';

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};
$text  = static fn( string $html ): string => trim( wp_strip_all_tags( $html ) );

$check( 'range: both set and different', preg_match( '/150.*-.*400/s', $text( wpss_request_budget_html( 150.0, 400.0 ) ) ) === 1, $text( wpss_request_budget_html( 150.0, 400.0 ) ) );
$check( 'maximum alone reads Up to', 0 === strpos( $text( wpss_request_budget_html( 0.0, 400.0 ) ), 'Up to' ), $text( wpss_request_budget_html( 0.0, 400.0 ) ) );
$check( 'minimum alone is a fixed price', false === strpos( $text( wpss_request_budget_html( 150.0, 0.0 ) ), '-' ) && false !== strpos( $text( wpss_request_budget_html( 150.0, 0.0 ) ), '150' ) );
$check( 'equal amounts are one price, not a range', false === strpos( $text( wpss_request_budget_html( 300.0, 300.0 ) ), '-' ) );
$check( 'neither set is empty so the caller says Negotiable', '' === wpss_request_budget_html( 0.0, 0.0 ) );

$root = dirname( __DIR__ );
foreach ( array( 'templates/single-request.php', 'templates/content-request-card.php', 'templates/dashboard/sections/requests.php' ) as $file ) {
	$check( "$file asks the shared rule", str_contains( (string) file_get_contents( "$root/$file" ), 'wpss_request_budget_html(' ) );
}
