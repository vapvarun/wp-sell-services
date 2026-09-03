<?php
/**
 * The one check that fails if the toggle contract drifts again.
 *
 * Five cards have now been filed for the same shape: a notification the admin
 * can switch off in Settings > Emails that keeps sending, a key EmailService
 * gates on that no control writes, or - #10268056021 - one event answering to
 * two different checkboxes because each sender kept its own type => setting
 * map and they drifted apart.
 *
 * So this asserts three things at once:
 *   1. There is exactly ONE map, wpss_notification_type_settings(), and neither
 *      sender has grown a private copy of it again.
 *   2. Every setting it names is a checkbox Settings renders and the sanitizer
 *      persists, and every checkbox reaches at least one type.
 *   3. Behaviourally, unticking a box silences that event on BOTH senders -
 *      the branded EmailService mail and the NotificationService row's mail -
 *      and silences nothing else.
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
use WPSellServices\Services\NotificationService;

global $wpdb;

$failures = array();

$types = array_keys( ( new Settings() )->get_notification_types() );
$map   = wpss_notification_type_settings();

// ---------------------------------------------------------------------------
// 1. One map. A sender that declares its own has forked the contract again.
// ---------------------------------------------------------------------------
foreach ( array( 'EmailService', 'NotificationService' ) as $class ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . "src/Services/{$class}.php" );
	if ( preg_match( '/\$type_to_setting\s*=\s*array\(/', $src ) ) {
		$failures[] = "{$class} declares its own type => setting map again — two maps can disagree, which is the bug.";
	}
}

// ---------------------------------------------------------------------------
// 2. Set contract between the one map and the checkboxes.
// ---------------------------------------------------------------------------
$mapped = array_values( array_unique( array_values( $map ) ) );

foreach ( $mapped as $key ) {
	if ( ! in_array( substr( $key, strlen( 'notify_' ) ), $types, true ) ) {
		$failures[] = "The map gates on '{$key}' but no checkbox writes it — the notification can never be turned off.";
	}
}
foreach ( $types as $type ) {
	if ( ! in_array( 'notify_' . $type, $mapped, true ) ) {
		$failures[] = "Settings renders a '{$type}' checkbox that no notification type consults — unticking it does nothing.";
	}
}

// Every type either sender knows must resolve through the one map to exactly
// one setting, or to none at all (deliberately always-on). "Knows" = a TYPE_*
// constant on either class, plus the raw workflow strings the map itself keys.
$known = array_keys( $map );
foreach ( array( EmailService::class, NotificationService::class ) as $class ) {
	foreach ( ( new ReflectionClass( $class ) )->getConstants() as $name => $value ) {
		if ( 0 === strpos( $name, 'TYPE_' ) && is_string( $value ) ) {
			$known[] = $value;
		}
	}
}
foreach ( array_unique( $known ) as $type ) {
	$resolved = array_keys( $map, $map[ $type ] ?? null, true );
	if ( isset( $map[ $type ] ) && ! in_array( $type, $resolved, true ) ) {
		$failures[] = "'{$type}' does not resolve to a single setting.";
	}
	// A type the senders know but the map does not is always-on. That is a
	// deliberate state, not a failure — it is reported, not asserted.
}

// ---------------------------------------------------------------------------
// 3. Behaviour. Capture the RAW stored option so it is restored byte-exact.
// ---------------------------------------------------------------------------
$raw_saved = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpss_notifications' ) );

$stamp = wp_rand();
$uid   = (int) wp_insert_user(
	array(
		'user_login' => "toggle{$stamp}",
		'user_email' => "toggle{$stamp}@example.test",
		'user_pass'  => wp_generate_password(),
	)
);

$email_gate = new ReflectionMethod( EmailService::class, 'is_email_type_enabled' );
$email_gate->setAccessible( true );
$es = new EmailService();

$row_gate = new ReflectionMethod( NotificationService::class, 'should_send_email' );
$row_gate->setAccessible( true );
$ns = new NotificationService();

/**
 * Both senders' verdict on one type, with every box ticked except $off.
 *
 * @param string $off Setting key to untick, or '' to tick everything.
 * @return callable(string):array{0:bool,1:bool} Type => [branded, plain].
 */
$with_off = static function ( string $off ) use ( $types, $email_gate, $es, $row_gate, $ns, $uid ): callable {
	$settings = array();
	foreach ( $types as $t ) {
		$settings[ 'notify_' . $t ] = true;
	}
	if ( '' !== $off ) {
		$settings[ $off ] = false;
	}
	update_option( 'wpss_notifications', $settings );

	return static function ( string $type ) use ( $email_gate, $es, $row_gate, $ns, $uid ): array {
		return array(
			(bool) $email_gate->invoke( $es, $type ),
			(bool) $row_gate->invoke( $ns, $uid, $type ),
		);
	};
};

$expect = static function ( string $label, array $got, bool $want ) use ( &$failures ): void {
	if ( array( $want, $want ) !== $got ) {
		$failures[] = sprintf(
			'%s: expected both senders %s, got branded=%s plain=%s.',
			$label,
			$want ? 'enabled' : 'suppressed',
			$got[0] ? 'on' : 'off',
			$got[1] ? 'on' : 'off'
		);
	}
};

// 3a. Untick "Cancellation Requested": both copies of that event go quiet,
//     nothing else does.
$gate = $with_off( 'notify_cancellation_requested' );
$expect( 'cancellation_requested with its own box off', $gate( 'cancellation_requested' ), false );
$expect( 'cancellation_submitted (the buyer half of the same event)', $gate( 'cancellation_submitted' ), false );
$expect( 'order_cancelled must be untouched', $gate( 'order_cancelled' ), true );
$expect( 'cancellation_auto_approved must be untouched', $gate( 'cancellation_auto_approved' ), true );
$expect( 'new_order must be untouched', $gate( 'new_order' ), true );

// 3b. Untick "Order Cancelled": only the cancellation itself goes quiet.
$gate = $with_off( 'notify_order_cancelled' );
$expect( 'order_cancelled with its own box off', $gate( 'order_cancelled' ), false );
$expect( 'cancellation_auto_approved (the order IS cancelled)', $gate( 'cancellation_auto_approved' ), false );
$expect( 'cancellation_requested must be untouched', $gate( 'cancellation_requested' ), true );
$expect( 'cancellation_submitted must be untouched', $gate( 'cancellation_submitted' ), true );
$expect( 'order_completed must be untouched', $gate( 'order_completed' ), true );

// 3c. Every box off: every mapped type is suppressed on both senders.
$gate = $with_off( '' );
$all_off = array();
foreach ( $types as $t ) {
	$all_off[ 'notify_' . $t ] = false;
}
update_option( 'wpss_notifications', $all_off );
foreach ( array_keys( $map ) as $type ) {
	$got = array( (bool) $email_gate->invoke( $es, $type ), (bool) $row_gate->invoke( $ns, $uid, $type ) );
	if ( array( false, false ) !== $got ) {
		$failures[] = "Every toggle is off, yet '{$type}' still reports enabled on " .
			( $got[0] ? 'EmailService' : 'NotificationService' ) . '.';
	}
}

// 3d. A missing key still means enabled. A type added after a site last saved
//     its settings must ship live, not silently muted.
update_option( 'wpss_notifications', array( 'notify_new_order' => false ) );
$expect( 'a key absent from the saved option', array( (bool) $email_gate->invoke( $es, 'cancellation_requested' ), (bool) $row_gate->invoke( $ns, $uid, 'cancellation_requested' ) ), true );
$expect( 'a key present and unticked', array( (bool) $email_gate->invoke( $es, 'new_order' ), (bool) $row_gate->invoke( $ns, $uid, 'new_order' ) ), false );

delete_option( 'wpss_notifications' );
$expect( 'no saved option at all', array( (bool) $email_gate->invoke( $es, 'order_cancelled' ), (bool) $row_gate->invoke( $ns, $uid, 'order_cancelled' ) ), true );

// ---------------------------------------------------------------------------
// Restore. The option goes back byte-exact; the throwaway user goes away.
// ---------------------------------------------------------------------------
delete_option( 'wpss_notifications' );
if ( null !== $raw_saved ) {
	$wpdb->insert(
		$wpdb->options,
		array(
			'option_name'  => 'wpss_notifications',
			'option_value' => $raw_saved,
			'autoload'     => 'yes',
		)
	);
}
wp_cache_delete( 'wpss_notifications', 'options' );
wp_cache_delete( 'alloptions', 'options' );
wp_cache_delete( 'notoptions', 'options' );

$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $uid ) );
$wpdb->delete( $wpdb->users, array( 'ID' => $uid ) );
wp_cache_delete( $uid, 'users' );

$restored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpss_notifications' ) );
if ( $restored !== $raw_saved ) {
	$failures[] = 'wpss_notifications was not restored byte-exact.';
}

// ---------------------------------------------------------------------------
$always_on = array();
foreach ( array_unique( $known ) as $type ) {
	if ( ! isset( $map[ $type ] ) && ! in_array( $type, array( 'test_email' ), true ) ) {
		$always_on[] = $type;
	}
}
sort( $always_on );

echo 'Switchable types: ' . count( $types ) . ' | mapped notification types: ' . count( $map ) .
	' | setting keys consulted: ' . count( $mapped ) . "\n";
echo 'Always-on (no checkbox, by design): ' . ( $always_on ? implode( ', ', $always_on ) : 'none' ) . "\n";

if ( $failures ) {
	echo "\nFAIL\n";
	foreach ( $failures as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}

echo "\nPASS — one map, every checkbox reaches a notification, and each box silences its own event only.\n";
