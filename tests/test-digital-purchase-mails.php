<?php
/**
 * A paid digital product is announced as a purchase, not as work (Basecamp 10553849784).
 *
 * Paying a digital order moved it pending_payment -> in_progress -> pending_approval in one
 * request, so both sides got "started working" and "delivery ready, accept or request a
 * revision", and neither got a receipt or a new-sale message. The order's kind is now frozen
 * with the payment, the new-order pair is worded as a download, and the automatic hand-over
 * sends no delivery message. A digital product with no file still waits for the vendor, so it
 * keeps the in-progress messages.
 *
 * Run: wp eval-file tests/test-digital-purchase-mails.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f   = wpss_test_fixtures();
$sid = ( $f->service )( 'publish' );
wpss_set_service_type( $sid, 'digital' );

$sent = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, array $atts ) use ( &$sent ) {
		$sent[] = array(
			'to'      => (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ),
			'subject' => (string) $atts['subject'],
			'body'    => wp_strip_all_tags( (string) $atts['message'] ),
		);
		return true;
	},
	10,
	2
);

$pay = static function () use ( $f, $sid, &$sent ): int {
	$sent = array();
	foreach ( array( $f->buyer, $f->vendor ) as $uid ) {
		delete_transient( 'wpss_email_cooldown_' . md5( get_userdata( $uid )->user_email . '|order_in_progress' ) );
	}
	$oid = ( $f->order )(
		array(
			'service_id'     => $sid,
			'status'         => 'pending_payment',
			'payment_status' => 'pending',
			'paid_at'        => null,
		)
	);
	( new StandaloneOrderProvider() )->mark_as_paid( $oid, 'qa_digital_' . $oid, 'offline' );
	return $oid;
};
$subjects = static function () use ( &$sent ): string {
	return implode( ' | ', array_column( $sent, 'subject' ) );
};
$titles   = static function ( int $oid ) use ( $wpdb ): array {
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, title FROM {$wpdb->prefix}wpss_notifications WHERE data LIKE %s", '%"order_id":' . $oid . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return array_map( static fn( $r ): string => $r->user_id . ':' . $r->title, (array) $rows );
};
$mail_to  = static function ( int $uid ) use ( &$sent ): array {
	return array_values( array_filter( $sent, static fn( $m ) => str_contains( $m['to'], get_userdata( $uid )->user_email ) ) );
};

try {
	// 1. With a file: delivered at once, announced as a purchase.
	update_post_meta( $sid, '_wpss_product_files', array( array( 'id' => 'qa1', 'name' => 'guide.pdf', 'path' => 'qa/guide.pdf', 'size' => 10, 'version' => 1 ) ) );
	$oid   = $pay();
	$order = wpss_get_order( $oid );
	$check( 'the paid order is delivered at once', 'pending_approval' === $order->status, (string) $order->status );
	$check( 'its kind is recorded as digital', 'digital' === wpss_get_order_kind( $order ) );

	$subj = $subjects();
	$check( 'the buyer gets a receipt (Order Confirmed)', str_contains( $subj, 'Order Confirmed' ), $subj );
	$check( 'the vendor gets a new sale', str_contains( $subj, 'New Sale' ), $subj );
	$check( 'no "In Progress" email', ! str_contains( $subj, 'In Progress' ), $subj );
	$check( 'no "Delivery Ready" email', ! str_contains( $subj, 'Delivery Ready' ), $subj );
	$buyer_mail = implode( ' ', array_column( $mail_to( (int) $f->buyer ), 'body' ) );
	$check( 'the receipt says the download is ready', str_contains( $buyer_mail, 'Your download is ready' ), substr( $buyer_mail, 0, 200 ) );
	$check( 'the receipt does not ask for requirements', ! str_contains( $buyer_mail, 'submit your requirements' ) );

	$notes = implode( ' | ', $titles( $oid ) );
	$check( 'buyer notice: Your Download Is Ready', str_contains( $notes, $f->buyer . ':Your Download Is Ready' ), $notes );
	$check( 'vendor notice: New Sale', str_contains( $notes, $f->vendor . ':New Sale' ), $notes );
	$check( 'no "started" or "delivery ready" notices', ! preg_match( '/In Progress|ready to start|Delivery Ready/i', $notes ), $notes );

	// 2. No file uploaded: the vendor delivers by hand, so the in-progress messages stay.
	delete_post_meta( $sid, '_wpss_product_files' );
	$oid  = $pay();
	$subj = $subjects();
	$check( 'without a file the order waits in progress', 'in_progress' === wpss_get_order( $oid )->status );
	$check( 'and both sides get the In Progress email, not a download receipt', str_contains( $subj, 'In Progress' ) && ! str_contains( $subj, 'New Sale' ), $subj );
} finally {
	$order_ids = array_map( 'intval', $f->orders );
	if ( $order_ids ) {
		$in = implode( ',', $order_ids );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_deliveries WHERE order_id IN ({$in})" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_conversations WHERE order_id IN ({$in})" ); // phpcs:ignore WordPress.DB
	}
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
