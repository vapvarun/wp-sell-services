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

$stored  = $wpdb->get_col( "SELECT DISTINCT type FROM {$table}" );
$missing = array_diff( $stored, array_keys( $registry ) );
$check( 'every stored type has a registry row (' . implode( ',', $missing ) . ')', ! $missing );

// A member's dropdown is their own types, and each filters to exactly its rows.
$user = (int) $wpdb->get_var( "SELECT user_id FROM {$table} GROUP BY user_id ORDER BY COUNT(DISTINCT type) DESC LIMIT 1" );
if ( $user ) {
	$mine = wpss_get_user_notification_types( $user );
	$real = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT type FROM {$table} WHERE user_id = %d", $user ) );
	$check( 'dropdown types equal the member\'s distinct types', array() === array_diff( $real, $mine ) && array() === array_diff( $mine, $real ) );
	$bad = 0;
	foreach ( $mine as $type ) {
		$rows = wpss_get_user_notifications( $user, array( 'type' => $type, 'limit' => 200 ) );
		$bad += count( array_filter( $rows, static fn( $r ) => $r->type !== $type ) ) + ( $rows ? 0 : 1 );
	}
	$check( 'filtering by each of the member\'s types returns only that type', 0 === $bad );
}

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
