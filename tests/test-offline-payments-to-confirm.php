<?php
/**
 * The owner's Action items count offline payments waiting for Mark as paid (Basecamp 10550842399).
 *
 * Offline is the gateway that works out of the box, and its orders sit in pending_payment until
 * the owner confirms the money, yet every Action items card read 0. One count now feeds a
 * Payments to confirm card and the Orders menu bubble, and the card links to a list of just those.
 *
 * Run: wp eval-file tests/test-offline-payments-to-confirm.php
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

$has   = function_exists( 'wpss_count_payments_to_confirm' );
$count = static fn() => function_exists( 'wpss_count_payments_to_confirm' ) ? wpss_count_payments_to_confirm() : -1;
$check( 'One count of offline payments to confirm exists', $has );

$f       = wpss_test_fixtures();
$base    = $count();
$pending = array(
	'status'         => 'pending_payment',
	'payment_status' => 'pending',
	'paid_at'        => null,
);
$waiting = ( $f->order )( $pending + array( 'payment_method' => 'offline' ) );
( $f->order )( $pending + array( 'payment_method' => 'stripe' ) );
( $f->order )( array( 'payment_method' => 'offline' ) );

$check( 'An offline order waiting for payment is counted', $base + 1 === $count(), "{$base} -> {$count()}" );

$_GET = array(
	'page'           => 'wpss-orders',
	'status'         => 'pending_payment',
	'payment_method' => 'offline',
);
set_current_screen( 'sell-services_page_wpss-orders' );
wp_set_current_user( $f->admin );
$table = new \WPSellServices\Admin\Tables\OrdersListTable();
$table->prepare_items();
$ids = array_map( static fn( $row ) => (int) ( is_array( $row ) ? $row['id'] : $row->id ), (array) $table->items );
$_GET = array();
wp_set_current_user( 0 );

$ours = array_values( array_intersect( $ids, $f->orders ) );
$check( 'The card\'s link lists that order and none of the others', array( $waiting ) === $ours, wp_json_encode( $ours ) );

$admin = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Admin.php' );
$check( 'The Dashboard card and the Orders menu bubble both read that count', 2 === substr_count( $admin, 'wpss_count_payments_to_confirm()' ) && false !== strpos( $admin, "'Payments to confirm'" ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
