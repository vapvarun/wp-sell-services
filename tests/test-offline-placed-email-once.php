<?php
/**
 * The offline "how to pay" email goes out once per order, and never clobbers the order's meta.
 *
 * The once-guard read the order, checked a flag, then wrote the whole meta blob
 * back with the flag set. Two pay requests at once both passed the check and
 * mailed buyer, vendor and admin twice, and the write-back could erase a meta
 * key another writer had just saved. One conditional JSON_SET now claims the
 * send and touches only its own key.
 *
 * Run: wp eval-file tests/test-offline-placed-email-once.php
 *
 * Sends nothing: wp_mail is short-circuited. Fixture rows are removed at shutdown.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EmailService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f    = wpss_test_fixtures();
$sent = 0;
add_filter(
	'pre_wp_mail',
	static function () use ( &$sent ) {
		++$sent;
		return true;
	}
);
$meta = static fn( int $id ): array => (array) json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $id ) ), true ); // phpcs:ignore WordPress.DB

$order = ( $f->order )(
	array(
		'status'         => 'pending_payment',
		'payment_status' => 'pending',
		'meta'           => wp_json_encode( array( 'offline_method' => 'Bank transfer' ) ),
	)
);
$svc   = new EmailService();

$svc->send_offline_order_placed( $order );
$first = $sent;
$check( 'the first call mails', $first > 0, "{$first} mails" );
$svc->send_offline_order_placed( $order );
$check( 'a repeat mails nobody', $first === $sent, ( $sent - $first ) . ' extra mails' );
$m = $meta( $order );
$check( 'the order is marked as emailed', ! empty( $m['offline_placed_emailed'] ) );
$check( 'and keeps the meta it already had', 'Bank transfer' === ( $m['offline_method'] ?? '' ), (string) wp_json_encode( $m ) );

// An order whose meta is an empty JSON array still gets marked, once.
$bare = ( $f->order )( array( 'status' => 'pending_payment', 'payment_status' => 'pending', 'meta' => '[]' ) );
$sent = 0;
$svc->send_offline_order_placed( $bare );
$svc->send_offline_order_placed( $bare );
$check( 'an order with empty meta is mailed once and marked', $sent > 0 && ! empty( $meta( $bare )['offline_placed_emailed'] ) && $sent === $first );

// The claim is a single conditional UPDATE, so two requests at once cannot both win.
$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/EmailService.php' );
$fn  = substr( $src, (int) strpos( $src, 'function send_offline_order_placed' ), 2500 );
$check( 'the send is claimed by one conditional UPDATE', false !== strpos( $fn, "JSON_EXTRACT( meta, '$.offline_placed_emailed' ) IS NULL" ) && false === strpos( $fn, "'meta' => wp_json_encode( \$meta )" ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
