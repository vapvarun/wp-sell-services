<?php
/**
 * Mail sent inside a database transaction waits for COMMIT and is dropped on ROLLBACK (Basecamp 10435884968).
 *
 * Dispute open, cancel and resolve move the order (whose status mails go out)
 * inside a transaction that can still roll back. The plugin's one sender holds
 * its mail while a transaction is open; one listener on the SQL follows the
 * transaction, so every START TRANSACTION site in Free and Pro is covered.
 * Mail from outside the plugin is never touched: wp_mail() runs once per mail.
 *
 * Run: wp eval-file tests/test-mail-waits-for-commit.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\EmailService;
use WPSellServices\Services\OrderService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

// Counts what actually reaches the mailer; runs after the hold, and stops the real send.
$sent    = array();
$counter = static function ( $short_circuit, $atts ) use ( &$sent ) {
	if ( null === $short_circuit ) {
		$sent[] = preg_replace( '/^\[[^\]]*\]\s*/', '', (string) $atts['subject'] );
		return true;
	}
	return $short_circuit;
};
add_filter( 'pre_wp_mail', $counter, 20, 2 );

$orders = $wpdb->prefix . 'wpss_orders';
$order  = $wpdb->get_row( "SELECT id, status, meta FROM {$orders} WHERE status = 'in_progress' ORDER BY id LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$since  = current_time( 'mysql', true );
$emails = new EmailService();
$mail   = static function ( string $subject ) use ( $emails ): bool {
	return $emails->send( 'qa-mail-hold@example.test', $subject, 'mail_hold_test', array( 'message' => 'x' ) );
};

try {
	// 1. Outside a transaction nothing changes.
	$mail( 'outside' );
	$check( 'mail outside a transaction is sent at once', array( 'outside' ) === $sent );

	// 2. ROLLBACK drops it.
	$sent = array();
	$wpdb->query( 'START TRANSACTION' );
	$held = $mail( 'rolled back' );
	$wpdb->query( 'ROLLBACK' );
	$wpdb->query( 'SELECT 1' );
	$check( 'a held mail still reports success to its caller', true === $held );
	$check( 'mail from a rolled-back transaction is never sent', array() === $sent );

	// 3. COMMIT releases it, after the COMMIT has run.
	$wpdb->query( 'START TRANSACTION' );
	$mail( 'committed' );
	$check( 'mail is held while the transaction is open', array() === $sent );
	$wpdb->query( 'COMMIT' );
	$check( 'held mail does not go out before the COMMIT has run', array() === $sent );
	$wpdb->query( 'SELECT 1' );
	$check( 'held mail goes out once the COMMIT has run', array( 'committed' ) === $sent );

	// 4. A START inside an open transaction commits it in MySQL: its mail is released.
	$sent = array();
	$wpdb->query( 'START TRANSACTION' );
	$mail( 'first' );
	$wpdb->query( 'START TRANSACTION' );
	$wpdb->query( 'SELECT 1' );
	$check( 'an implicit commit releases the earlier mail', array( 'first' ) === $sent );
	$wpdb->query( 'ROLLBACK' );

	// 5. Mail from outside the plugin goes straight through, once, so mail loggers see each send one time.
	$sent = array();
	$wpdb->query( 'START TRANSACTION' );
	wp_mail( 'qa-mail-hold@example.test', 'third party', 'x' );
	$wpdb->query( 'ROLLBACK' );
	$wpdb->query( 'SELECT 1' );
	$check( 'another plugin\'s wp_mail() inside a transaction is not held or repeated', array( 'third party' ) === $sent );

	// 6. Real flow: an order status change rolled back sends no status mail.
	if ( ! $order ) {
		$check( 'fixture: an in_progress order exists', false );
	} else {
		$service = new OrderService();

		$sent = array();
		$wpdb->query( 'START TRANSACTION' );
		$moved = $service->update_status( (int) $order->id, 'on_hold', 'mail-hold test' );
		$wpdb->query( 'COMMIT' );
		$wpdb->query( 'SELECT 1' );
		$control = $sent;
		$check( 'control: the committed status change mailed (' . implode( ' | ', $control ) . ')', $moved && count( $control ) > 0 );

		$wpdb->update( $orders, array( 'status' => $order->status ), array( 'id' => $order->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();

		$sent = array();
		$wpdb->query( 'START TRANSACTION' );
		$service->update_status( (int) $order->id, 'on_hold', 'mail-hold test' );
		$wpdb->query( 'ROLLBACK' );
		$wpdb->query( 'SELECT 1' );
		wp_cache_flush();
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $order->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( 'the rolled-back status change left the order as it was (' . $status . ')', $order->status === $status );
		$check( 'the rolled-back status change sent no mail (' . implode( ' | ', $sent ) . ')', array() === $sent );
	}
} finally {
	remove_filter( 'pre_wp_mail', $counter, 20 );
	if ( $order ) {
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$orders,
			array(
				'status' => $order->status,
				'meta'   => $order->meta,
			),
			array( 'id' => $order->id )
		);
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE created_at >= %s AND message LIKE %s", $since, '%hold%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
	}
}

echo $fails ? "\n$fails FAILED\n" : "\nOK\n";
exit( $fails ? 1 : 0 );
