<?php
/**
 * A negative number typed into a count/amount/days setting is stored as 0, not
 * flipped to its positive twin by absint() (Basecamp 10403454753): -5 minutes
 * became 5, and -3 clearance days became a 3-day hold on vendor money.
 *
 * Run: wp eval-file tests/test-settings-no-negative-numbers.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$s = new \WPSellServices\Admin\Settings();

$d = $s->sanitize_notification_delivery_settings( array( 'message_email_delay_minutes' => -5 ) );
$check( 'message delay -5 stores 0', 0 === $d['message_email_delay_minutes'] );
$check( 'message delay 999 stores the 120 cap', 120 === $s->sanitize_notification_delivery_settings( array( 'message_email_delay_minutes' => 999 ) )['message_email_delay_minutes'] );
$check( 'message delay 30 is kept', 30 === $s->sanitize_notification_delivery_settings( array( 'message_email_delay_minutes' => '30' ) )['message_email_delay_minutes'] );

$p = $s->sanitize_payouts_settings( array( 'min_withdrawal' => -50, 'clearance_days' => -3, 'auto_withdrawal_threshold' => -500 ) );
$check( 'min withdrawal -50 stores 0', 0 === (int) $p['min_withdrawal'] );
$check( 'clearance days -3 stores 0, not a 3-day hold', 0 === (int) $p['clearance_days'] );
$check( 'auto-withdrawal threshold -500 stores 0', 0 === (int) $p['auto_withdrawal_threshold'] );

// Tuning fields clamp the raw integer (Basecamp 10475418682): absint() ran
// before the min/max clamp, so -4 days was stored as 4.
if ( function_exists( 'get_registered_settings' ) ) {
	if ( ! isset( get_registered_settings()['wpss_dispute_reminder_days'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$s->register_settings();
	}
	$registered = get_registered_settings();
	$tune       = static fn( string $option, $value ) => call_user_func( $registered[ $option ]['sanitize_callback'], $value );
	if ( isset( $registered['wpss_dispute_reminder_days'], $registered['wpss_decimal_places'] ) ) {
		$check( 'reminder days -4 stores the minimum 1, not 4', 1 === $tune( 'wpss_dispute_reminder_days', -4 ) );
		$check( 'reminder days 999 stores the 30 cap', 30 === $tune( 'wpss_dispute_reminder_days', 999 ) );
		$check( 'reminder days "7" is kept', 7 === $tune( 'wpss_dispute_reminder_days', '7' ) );
		$check( 'decimal places -2 stores 0, not 2', 0 === $tune( 'wpss_decimal_places', -2 ) );
	} else {
		$check( 'the tuning settings are registered in this request', false );
	}
}

$rt = $s->sanitize_realtime_settings( array( 'port' => -6001 ) );
$check( 'realtime port -6001 falls back to 443, not 6001', 443 === $rt['port'] );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
