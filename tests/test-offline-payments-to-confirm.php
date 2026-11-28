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
$by_hand = ( $f->order )( $pending + array( 'payment_method' => 'bank_transfer' ) ); // Manual Order: bank transfer.
( $f->order )( $pending + array( 'payment_method' => 'stripe' ) );
( $f->order )( array( 'payment_method' => 'offline' ) );

$check( 'Offline and bank-transfer orders waiting for payment are counted', $base + 2 === $count(), "{$base} -> {$count()}" );

// The card's own link, read from the Dashboard source so a changed link is what gets tested.
preg_match( "/admin\\.php\\?page=wpss-orders&status=pending_payment[^']*/", (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Admin.php' ), $link );
parse_str( (string) wp_parse_url( $link[0] ?? '', PHP_URL_QUERY ), $_GET );
set_current_screen( 'sell-services_page_wpss-orders' );
wp_set_current_user( $f->admin );
$table = new \WPSellServices\Admin\Tables\OrdersListTable();
$table->prepare_items();
$listed = (int) $table->get_pagination_arg( 'total_items' );
$ids = array_map( static fn( $row ) => (int) ( is_array( $row ) ? $row['id'] : $row->id ), (array) $table->items );
$_GET = array();
wp_set_current_user( 0 );

$ours = array_values( array_intersect( $ids, $f->orders ) );
sort( $ours );
$check( 'The card\'s link lists both waiting orders and none of the others', array( $waiting, $by_hand ) === $ours, wp_json_encode( $ours ) );
$check( 'The list holds exactly as many orders as the card says', $count() === $listed, "{$count()} vs {$listed}" );

$admin = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Admin.php' );
preg_match( '/\$filter_keys\s*=\s*array\(([^)]*)\)/', $admin, $keys );
$check( 'Export CSV on that list keeps its filter (the file is the list on screen)', str_contains( $keys[1] ?? '', "'to_confirm'" ) && str_contains( $keys[1] ?? '', "'payment_method'" ) && 2 === substr_count( $admin, 'array_flip( $filter_keys )' ) );
$check( 'The Dashboard card and the Orders menu bubble both read that count', 2 === substr_count( $admin, 'wpss_count_payments_to_confirm()' ) && false !== strpos( $admin, "'Payments to confirm'" ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
