<?php
/**
 * One notification type vocabulary: the Type filter offers every type a member has, each row has an icon, and the
 * model, template and AJAX list no longer carry their own copies (Basecamp 10403104468, QA bounce 2026-10-18).
 *
 * Run: wp eval-file tests/test-notification-types-registry.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table    = $wpdb->prefix . 'wpss_notifications';
$registry = function_exists( 'wpss_notification_types' ) ? wpss_notification_types() : array();
$check( 'registry exists with label and icon per type', $registry && ! array_filter( $registry, static fn( $r ) => empty( $r['label'] ) || empty( $r['icon'] ) ) );

$missing = array_diff( array_keys( wpss_notification_type_settings() ), array_keys( $registry ) );
$check( 'every type that has an email setting has a registry row (' . implode( ',', $missing ) . ')', ! $missing );

// Class check: every literal type the services send has a registry row, so no bell falls back to a generic icon.
$sent = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/src', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' === $file->getExtension() && preg_match_all( '/->send\(\s*[^,]+,\s*[\'"]([a-z_]+)[\'"]/', file_get_contents( $file->getPathname() ), $m ) ) {
		$sent = array_merge( $sent, $m[1] );
	}
}
$unregistered = array_diff( array_unique( $sent ), array_keys( $registry ) );
$check( 'every type sent by the services has a registry row (' . implode( ',', $unregistered ) . ')', ! $unregistered );

// And each has its own message case, so none of them reads the generic "You have a new notification".
$svc_src   = file_get_contents( dirname( __DIR__ ) . '/src/Services/NotificationService.php' );
$no_message = array_filter( array_unique( $sent ), static fn( $type ) => false === strpos( $svc_src, "case '{$type}':" ) );
$check( 'every type sent by the services has a message case (' . implode( ',', $no_message ) . ')', ! $no_message );

// A member's dropdown is their own types, and each filters to exactly its rows. Seed a member with a
// spread of types, including ones that used to have no filter option.
$f     = wpss_test_fixtures();
$user  = $f->buyer;
$seed  = array( 'order_late', 'refund_pending', 'order_started', 'order_status', 'new_message', 'brand_new_thing' );
foreach ( $seed as $i => $type ) {
	wpss_add_notification( $user, $type, "QA {$type} row", array() );
}
$mine = wpss_get_user_notification_types( $user );
$check( 'dropdown types equal the member\'s distinct types', array() === array_diff( $seed, $mine ) && array() === array_diff( $mine, $seed ) );
$check( 'a type that had no filter before is offered (refund_pending)', in_array( 'refund_pending', $mine, true ) );
$bad = 0;
foreach ( $mine as $type ) {
	$rows = wpss_get_user_notifications( $user, array( 'type' => $type, 'limit' => 200 ) );
	$bad += count( array_filter( $rows, static fn( $r ) => $r->type !== $type ) ) + ( $rows ? 0 : 1 );
}
$check( 'filtering by each of the member\'s types returns only that type', 0 === $bad );

$check( 'unknown type: humanised label, bell icon', 'Brand new thing' === wpss_notification_type_label( 'brand_new_thing' ) && 'bell' === wpss_notification_type_icon( 'brand_new_thing' ) );

// The duplicate vocabularies are gone.
$root  = dirname( __DIR__ ) . '/';
$model = (string) file_get_contents( $root . 'src/Models/Notification.php' );
$tpl   = (string) file_get_contents( $root . 'templates/partials/notifications-list.php' );
$ajax  = (string) file_get_contents( $root . 'src/Frontend/AjaxHandlers.php' );
$check( 'model carries no TYPE_ constants, label list or icon map', ! preg_match( '/const TYPE_|function get_types|\$icons = \[/', $model ) );
$check( 'template carries no icon map', false === strpos( $tpl, '$wpss_notif_icons' ) );
$i = (int) strpos( $ajax, 'function get_notifications(' );
$check( 'AJAX get_notifications uses the shared list query', str_contains( substr( $ajax, $i, 1800 ), 'wpss_get_user_notifications' ) && ! str_contains( substr( $ajax, $i, 1800 ), 'SELECT *' ) );

exit( $fails ? 1 : 0 );
