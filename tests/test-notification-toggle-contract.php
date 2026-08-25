<?php
/**
 * The one check that fails if the toggle contract drifts again.
 *
 * Four cards have now been filed for the same shape: a notification the admin
 * can switch off in Settings > Emails that keeps sending, or a key EmailService
 * gates on that no control writes. Both directions are a set mismatch between
 * Settings::get_notification_types() and EmailService's type->setting map.
 *
 * Run: wp eval-file tests/test-notification-toggle-contract.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Settings;
use WPSellServices\Services\EmailService;

$failures = array();

$types = array_keys( ( new Settings() )->get_notification_types() );

$m = new ReflectionMethod( EmailService::class, 'is_email_type_enabled' );
$m->setAccessible( true );
$svc = new EmailService();

// The map is private; read the setting keys it actually consults.
$src = file_get_contents( WPSS_PLUGIN_DIR . 'src/Services/EmailService.php' );
preg_match( '/\$type_to_setting = array\((.*?)\n\t\t\);/s', $src, $mm );
preg_match_all( "/=>\s*'(notify_[a-z_]+)'/", $mm[1] ?? '', $km );
$mapped = array_values( array_unique( $km[1] ) );

// 1. Every setting key the map gates on must be one a control can write.
foreach ( $mapped as $key ) {
	if ( ! in_array( substr( $key, strlen( 'notify_' ) ), $types, true ) ) {
		$failures[] = "EmailService gates on '{$key}' but no checkbox writes it — the email can never be turned off.";
	}
}

// 2. Every checkbox must reach at least one email type, or it is a dead control.
foreach ( $types as $type ) {
	if ( ! in_array( 'notify_' . $type, $mapped, true ) ) {
		$failures[] = "Settings renders a '{$type}' checkbox that no email type consults — unticking it does nothing.";
	}
}

// 3. Behavioural: unticking a box must actually stop that email.
$saved = get_option( 'wpss_notifications' );
$off   = is_array( $saved ) ? $saved : array();
foreach ( $types as $type ) {
	$off[ 'notify_' . $type ] = false;
}
update_option( 'wpss_notifications', $off );

$constants = ( new ReflectionClass( EmailService::class ) )->getConstants();
foreach ( $constants as $name => $value ) {
	if ( 0 !== strpos( $name, 'TYPE_' ) || ! is_string( $value ) ) {
		continue;
	}
	// Only assert on types the map really gates. A bare mention is not a
	// mapping: TYPE_SELLER_LEVEL_PROMOTION appears in that block as a comment
	// saying it is deliberately always-on.
	if ( ! preg_match( '/self::' . preg_quote( $name, '/' ) . '\s*=>/', $mm[1] ?? '' ) ) {
		continue;
	}
	if ( true === $m->invoke( $svc, $value ) ) {
		$failures[] = "Every toggle is off, yet {$name} still reports enabled.";
	}
}

update_option( 'wpss_notifications', $saved );

echo 'Switchable types: ' . count( $types ) . ' | setting keys consulted: ' . count( $mapped ) . "\n";

if ( $failures ) {
	echo "\nFAIL\n";
	foreach ( $failures as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}

echo "PASS — every checkbox reaches an email, and every gated email has a checkbox.\n";
