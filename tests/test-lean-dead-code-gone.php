<?php
/**
 * Dead code, duplicate getters and always-true guards stay gone (1.9.3 lean card).
 *
 * Run: wp eval-file tests/test-lean-dead-code-gone.php
 *
 * - src/functions.php always loads before any class or template runs, so a
 *   function_exists( 'wpss_...' ) check on our own helper was always true and
 *   its else branch (a hard-coded '$', a hard-coded /checkout/) was dead.
 *   SchemaManager keeps its checks: uninstall.php runs it without the helpers.
 * - Uncalled methods and the unused Proposal model are removed.
 * - Duplicate getters delegate to one implementation.
 * - ServiceOrder::update() was a second, unguarded status writer with its own
 *   history store; a status change now goes through OrderService::update_status().
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// 1. No always-true guard on our own helpers.
$hits = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$rel = str_replace( WPSS_PLUGIN_DIR, '', (string) $file );
	if ( '.php' !== substr( $rel, -4 ) || ! preg_match( '#^(src|templates)/#', $rel ) || 'src/Database/SchemaManager.php' === $rel ) {
		continue;
	}
	foreach ( file( (string) $file ) as $n => $line ) {
		// wpss_pro() is Pro's; wpss_get_vendor_profile_url() is not a Free helper.
		if ( preg_match( "/function_exists\(\s*'(wpss_\w+)'/", $line, $m ) && ! in_array( $m[1], array( 'wpss_pro', 'wpss_get_vendor_profile_url' ), true ) ) {
			$hits[] = $rel . ':' . ( $n + 1 );
		}
	}
}
$check( 'no function_exists() guard on a helper functions.php always loads', array() === $hits, implode( ', ', $hits ) );

// 2. Dead code is gone.
$check( 'the unused Proposal model is gone', ! class_exists( '\WPSellServices\Models\Proposal' ) );
$check( 'wpss_sanitize_html() is gone', ! function_exists( 'wpss_sanitize_html' ) );
$check( 'OrderRepository::update_status() is gone', ! method_exists( '\WPSellServices\Database\Repositories\OrderRepository', 'update_status' ) );
$check( 'ServiceOrder::get_status_history() is gone', ! method_exists( '\WPSellServices\Models\ServiceOrder', 'get_status_history' ) );
$check( 'ServicePackage::get_formatted_price() is gone', ! method_exists( '\WPSellServices\Models\ServicePackage', 'get_formatted_price' ) );
$check( 'Message::get_relative_time() is gone', ! method_exists( '\WPSellServices\Models\Message', 'get_relative_time' ) );

// 3. One implementation per getter.
$check( 'Dispute::get_statuses() is DisputeService::get_statuses()', \WPSellServices\Models\Dispute::get_statuses() === \WPSellServices\Services\DisputeService::get_statuses() );
$fn = new ReflectionFunction( 'wpss_get_order' );
$body = implode( '', array_slice( file( $fn->getFileName() ), $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1 ) );
$check( 'wpss_get_order() is ServiceOrder::find()', false !== strpos( $body, 'ServiceOrder::find(' ) && false === stripos( $body, 'SELECT' ) );

// 4. ServiceOrder::update() hands a status change to OrderService::update_status().
$f        = wpss_test_fixtures();
$order_id = ( $f->order )( array( 'status' => 'in_progress' ) );
$order    = wpss_get_order( $order_id );
wp_set_current_user( $f->buyer );
$fired = 0;
$count = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_order_status_changed', $count );
$check( 'a transition OrderService refuses is refused through the model too', false === $order->update( array( 'status' => 'pending_payment' ) ) && 'in_progress' === wpss_get_order( $order_id )->status && 0 === $fired );
wp_set_current_user( 1 );
$order = wpss_get_order( $order_id );
$ok    = $order->update( array( 'status' => 'cancelled' ) );
$meta  = json_decode( (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT meta FROM {$GLOBALS['wpdb']->prefix}wpss_orders WHERE id = %d", $order_id ) ), true ); // phpcs:ignore WordPress.DB
$check( 'a permitted change goes through and fires the hook once', $ok && 'cancelled' === wpss_get_order( $order_id )->status && 1 === $fired, "ok={$ok} fired={$fired}" );
$check( '  and writes no second history store in meta', ! isset( $meta['status_history'] ) );
remove_action( 'wpss_order_status_changed', $count );
$check( 'a plain field update still works', $order->update( array( 'payment_status' => 'refunded' ) ) && 'refunded' === wpss_get_order( $order_id )->payment_status );
wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
