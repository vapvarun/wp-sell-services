<?php
/**
 * The money settings say how the clearance period meets the dispute window (Basecamp 10550887879).
 *
 * Clearance defaults to 0 and disputes stay open 14 days after completion, so a buyer can dispute
 * money the vendor has already withdrawn. The Payouts field and the wizard finish card now quote
 * the live dispute window. The 0 default is the owner's call and does not change.
 *
 * Run: wp eval-file tests/test-clearance-names-dispute-window.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders_was = get_option( 'wpss_orders' );
$set        = static function ( array $values ) use ( $orders_was ): void {
	update_option( 'wpss_orders', array_merge( (array) $orders_was, $values ) );
};
$note       = static fn() => function_exists( 'wpss_clearance_dispute_note' ) ? wpss_clearance_dispute_note() : 'missing';
$wizard     = static function (): string {
	ob_start();
	( new \WPSellServices\Admin\Pages\SetupWizardPage() )->render();
	return (string) ob_get_clean();
};
$payouts    = static function (): string {
	global $wp_settings_fields;
	$wp_settings_fields = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- rebuilt from the real registration below.
	require_once ABSPATH . 'wp-admin/includes/template.php';
	( new \WPSellServices\Admin\Settings() )->register_settings();
	return (string) ( $wp_settings_fields['wpss_payouts']['wpss_payouts_section']['clearance_days']['args']['description'] ?? '' );
};

try {
	wp_set_current_user( 1 );

	$set( array( 'allow_disputes' => 1, 'dispute_window_days' => 14 ) );
	$check( 'With a 14-day window the note names 14 days', false !== strpos( $note(), '14 days' ), $note() );
	$check( '  and the Payouts clearance field carries it', false !== strpos( $payouts(), '14 days after an order completes' ) );
	$check( '  and so does the wizard finish card', false !== strpos( $wizard(), 'dispute for 14 days after completion' ) );

	$set( array( 'dispute_window_days' => 30 ) );
	$check( 'Change the window and the number follows', false !== strpos( $note(), '30 days' ) && false !== strpos( $wizard(), 'dispute for 30 days' ) );

	// The window cannot be saved below 1 day; disputes are switched off with allow_disputes instead.
	$set( array( 'allow_disputes' => 0, 'dispute_window_days' => 14 ) );
	$check( 'Disputes off: no dispute can follow a withdrawal, so no note anywhere', '' === $note() && false === strpos( $wizard(), 'Clearance Period at least' ) && false === strpos( $payouts(), 'open a dispute' ) );

	$check( 'The clearance default stays 0', 0 === (int) ( wpss_settings_defaults()['wpss_payouts']['clearance_days'] ?? 0 ) );
} finally {
	update_option( 'wpss_orders', $orders_was );
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
