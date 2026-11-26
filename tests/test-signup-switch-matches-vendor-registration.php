<?php
/**
 * Open vendor registration is never promised while WordPress lets nobody sign up (Basecamp 10550842350).
 *
 * The wizard offered "Open - any member can start selling" while core's Anyone can register was
 * off (its default), so every visitor met "Sign-up is closed" and no WPSS screen said why. The
 * wizard now offers core's switch, and setup health names the mismatch.
 *
 * Run: wp eval-file tests/test-signup-switch-matches-vendor-registration.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Admin\Admin;
use WPSellServices\Admin\Pages\SetupWizardPage;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f             = wpss_test_fixtures();
$register_was  = get_option( 'users_can_register' );
$vendor_was    = get_option( 'wpss_vendor' );
$registration  = static function ( string $mode ) use ( $vendor_was ): void {
	update_option( 'wpss_vendor', array_merge( (array) $vendor_was, array( 'vendor_registration' => $mode ) ) );
};
$signup_row    = static fn() => isset( ( new Admin() )->get_setup_health_problems()['signup'] );

$die = static fn() => static function () {
	throw new RuntimeException( 'done' );
};
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', $die );
$save = static function ( int $box ): void {
	$_POST    = array(
		'nonce'                      => wp_create_nonce( 'wpss_wizard_nonce' ),
		'step'                       => 4,
		'vendor_registration'        => 'open',
		'max_services_per_vendor'    => 20,
		'require_service_moderation' => 0,
		'users_can_register'         => $box,
	);
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new SetupWizardPage() )->ajax_save_step();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	ob_end_clean();
};

try {
	wp_set_current_user( $f->admin );

	update_option( 'users_can_register', 0 );
	$registration( 'open' );
	$check( 'Setup health: open vendor registration with sign-up off is named', $signup_row() );
	$registration( 'approval' );
	$check( '  and with approval required too', $signup_row() );
	$registration( 'closed' );
	$check( '  but not when vendor registration is closed (invite-only)', ! $signup_row() );
	$registration( 'open' );
	update_option( 'users_can_register', 1 );
	$check( '  and not once Anyone can register is on', ! $signup_row() );

	update_option( 'users_can_register', 0 );
	$save( 1 );
	$check( 'Wizard: ticking the box turns Anyone can register on', '1' === (string) get_option( 'users_can_register' ) );
	$save( 0 );
	$check( 'Wizard: unticking it turns it off again', '0' === (string) get_option( 'users_can_register' ) );
} finally {
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die );
	$_POST    = array();
	$_REQUEST = array();
	update_option( 'users_can_register', $register_was );
	update_option( 'wpss_vendor', $vendor_was );
	wp_set_current_user( 0 );
}

$check( 'The site\'s own settings are back as they were', get_option( 'users_can_register' ) == $register_was && get_option( 'wpss_vendor' ) == $vendor_was ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
