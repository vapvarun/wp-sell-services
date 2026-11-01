<?php
/**
 * Order chat and the unread badges update without a reload (wiring guard).
 *
 * Run: wp eval-file tests/test-live-updates-wiring.php
 *
 * The behaviour itself is a browser walk (open the page, add a message or notification elsewhere, watch it arrive);
 * this guards the pieces that walk depends on, including the minified file the site really serves.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root = WP_PLUGIN_DIR . '/wp-sell-services/';
$chat = (string) file_get_contents( $root . 'templates/order/conversation.php' );
$js   = (string) file_get_contents( $root . 'assets/js/frontend.js' );
$min  = (string) file_get_contents( $root . 'assets/js/frontend.min.js' );

$check( 'chat looks the conversation up when the page opened before the first message', str_contains( $chat, "'/conversation'" ) && str_contains( $chat, 'discoverConversation' ) );
$check( 'chat polls only while the tab is visible', str_contains( $chat, "document.visibilityState !== 'visible'" ) );
$check( 'chat catches up when the tab comes back', str_contains( $chat, "addEventListener('visibilitychange', pollMessages)" ) );
$check( 'a polled message clears the empty state', 1 === preg_match( '/messagesContainer\.find\(\'\.wpss-messaging__empty\'\)\.remove\(\);\s*\$messagesContainer\.append\(msg\.html\)/', $chat ) );
$check( 'badges poll the unread-count route, only while visible', str_contains( $js, 'notifications/unread-count' ) && str_contains( $js, "document.visibilityState !== 'visible'" ) );
$check( 'the badge poll sets the server number, never +1', str_contains( $js, 'badge.textContent = String(data.unread_count)' ) );
$check( 'the served .min carries the badge poll', str_contains( $min, 'notifications/unread-count' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
