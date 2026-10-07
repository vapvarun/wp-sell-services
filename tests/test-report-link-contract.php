<?php
/**
 * The website's Report link: who gets one, and what it carries.
 *
 * Run: wp eval-file tests/test-report-link-contract.php
 *
 * POST wpss/v1/reports and the admin Member Reports screen shipped with no
 * entry point on the website (Basecamp 10378022010). The link is printed by
 * one function; this pins who it is printed for. Makes its own two members.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

use WPSellServices\Tests\Factories\UserFactory;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$suffix = wp_generate_password( 6, false, false );
$owner  = UserFactory::vendor( array( 'user_login' => 'wpss_report_owner_' . $suffix, 'user_email' => 'wpss_report_owner_' . $suffix . '@example.test' ) );
$member = UserFactory::customer( array( 'user_login' => 'wpss_report_member_' . $suffix, 'user_email' => 'wpss_report_member_' . $suffix . '@example.test' ) );

$render = static function ( int $as, string $type = 'user' ) use ( $owner ): string {
	wp_set_current_user( $as );
	ob_start();
	wpss_render_report_link( $type, $owner->ID, $owner->ID, 'Report this seller' );

	return (string) ob_get_clean();
};

try {
	$html = $render( $member->ID );
	$check( 'a member gets a button carrying what is reported', false !== strpos( $html, '<button' ) && false !== strpos( $html, 'data-report-type="user"' ) && false !== strpos( $html, 'data-report-id="' . $owner->ID . '"' ) );
	$check( '  and the dialog is queued for the footer, once', 10 === has_action( 'wp_footer', 'wpss_render_report_modal' ) );

	$check( 'the owner gets nothing on their own profile', '' === $render( $owner->ID ) );

	$html = $render( 0 );
	$check( 'a visitor gets a link to sign in, not a button', false !== strpos( $html, '<a ' ) && false === strpos( $html, '<button' ) && false === strpos( $html, 'data-report-id' ) );

	$check( 'a type members may not report prints nothing', '' === $render( $member->ID, 'order' ) );

	ob_start();
	wpss_render_report_modal();
	$modal = (string) ob_get_clean();
	$check( 'the dialog offers every report reason', count( wpss_get_report_reasons() ) === substr_count( $modal, '<option value="' ) - 1 );
} finally {
	wp_set_current_user( 0 );
	remove_action( 'wp_footer', 'wpss_render_report_modal' );
	UserFactory::cleanup();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
