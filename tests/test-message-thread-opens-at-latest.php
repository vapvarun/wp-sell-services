<?php
/**
 * A message thread opens on the newest message (Basecamp 10244580248).
 *
 * Run: wp eval-file tests/test-message-thread-opens-at-latest.php
 *
 * The dashboard thread had no height limit, so it grew to fit every message, the page scrolled
 * instead, and `scrollTop = scrollHeight` did nothing. Both chats also pinned before images loaded.
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

$rule = static function ( string $css, string $selector ): string {
	$at = strpos( $css, $selector . ' {' );
	return false === $at ? '' : substr( $css, $at, strpos( $css, '}', $at ) - $at );
};

foreach ( array( 'unified-dashboard.css', 'unified-dashboard.min.css' ) as $file ) {
	$css = (string) file_get_contents( $root . 'assets/css/' . $file );
	$css = (string) preg_replace( '/\s+/', ' ', str_replace( array( '{', '}', ';' ), array( ' { ', ' } ', '; ' ), $css ) );

	$thread = $rule( $css, '.wpss-section--messages .wpss-conversation-thread' );
	$list   = $rule( $css, '.wpss-section--messages .wpss-conversation-thread__messages' );
	$check( "{$file}: the thread has a height limit", 1 === preg_match( '/(?<![-\w])height:\s*min\(/', $thread ) );
	$check( "{$file}: the message list may shrink so it scrolls inside the thread", 1 === preg_match( '/min-height:\s*0(?![.\d])/', $list ) );
}

$dash  = (string) file_get_contents( $root . 'templates/dashboard/sections/messages.php' );
$order = (string) file_get_contents( $root . 'templates/order/conversation.php' );
$check( 'dashboard thread re-pins to the bottom as images load', str_contains( $dash, "addEventListener('load', pinToBottom" ) );
$check( 'order chat re-pins to the bottom as images load', str_contains( $order, "addEventListener('load', scrollToBottom" ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
