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

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
