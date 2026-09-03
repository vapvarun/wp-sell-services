<?php
/**
 * Dashboard order verbs are gated by who is asking and where the order is.
 *
 * Run: wp eval-file tests/test-order-action-gates.php
 *
 * Guards the 1.7.1 fresh-eyes findings F1 and F2: a buyer could refund
 * themselves, a vendor could cancel a completed order, the retired accept and
 * decline verbs still answered, every author was a vendor, vendors held the
 * admin force-transition cap, and a seller could bid on their own request.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\OrderService;
use WPSellServices\Services\ProposalService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_orders';

// wp_send_json() exits; route it through wp_die() and turn that into an
// exception so each verb can be asserted and the script keeps going.
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter(
	'wp_die_ajax_handler',
	static function () {
		return static function (): void {
			throw new RuntimeException( 'json_sent' );
		};
	},
	PHP_INT_MAX
);

$buyer_id  = wp_insert_user(
	array(
		'user_login' => 'wpss_gate_buyer_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$vendor_id = wp_insert_user(
	array(
		'user_login' => 'wpss_gate_vendor_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'wpss_vendor',
	)
);

if ( is_wp_error( $buyer_id ) || is_wp_error( $vendor_id ) ) {
	echo "could not seed users; skipping\n";
	return;
}

$wpdb->insert(
	$table,
	array(
		'order_number'   => 'WPSS-GATE-CONTRACT',
		'customer_id'    => $buyer_id,
		'vendor_id'      => $vendor_id,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$set_order = static function ( string $status ) use ( $wpdb, $table, $order_id ): void {
	$wpdb->update(
		$table,
		array( 'status' => $status, 'refunded_amount' => null, 'payment_status' => 'paid' ),
		array( 'id' => $order_id )
	);
};
$order_row = static function () use ( $wpdb, $table, $order_id ): object {
	return $wpdb->get_row( $wpdb->prepare( "SELECT status, refunded_amount FROM {$table} WHERE id = %d", $order_id ) );
};

/**
 * Fire wpss_order_action as $user_id and return the decoded JSON reply.
 *
 * @param int                  $user_id Acting user.
 * @param string               $verb    order_action value.
 * @param array<string, mixed> $extra   Extra POST fields.
 * @return array<string, mixed>
 */
$call = static function ( int $user_id, string $verb, array $extra = array() ) use ( $order_id ): array {
	wp_set_current_user( $user_id );
	$_POST             = array_merge(
		array(
			'order_action' => $verb,
			'order_id'     => $order_id,
		),
		$extra
	);
	$_REQUEST['nonce'] = wp_create_nonce( 'wpss_order_action' );

	ob_start();
	try {
		do_action( 'wp_ajax_wpss_order_action' );
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Reply captured below.
	}
	$json = json_decode( (string) ob_get_clean(), true );

	return is_array( $json ) ? $json : array( 'success' => null );
};

// --- F1: refund and cancel ------------------------------------------------
$set_order( 'in_progress' );
$reply = $call( $buyer_id, 'refund', array( 'refund_amount' => 5 ) );
$row   = $order_row();
$check( 'buyer refund is refused', false === $reply['success'] );
$check( '  and the order is untouched', 'in_progress' === $row->status && null === $row->refunded_amount );

$set_order( 'in_progress' );
$reply = $call( $vendor_id, 'refund', array( 'refund_amount' => 5 ) );
$row   = $order_row();
$check( 'vendor refund is refused while allow_vendor_refunds is off', false === $reply['success'] );
$check( '  and the order is untouched', 'in_progress' === $row->status && null === $row->refunded_amount );

$set_order( 'completed' );
$reply = $call( $vendor_id, 'cancel' );
$check( 'vendor cancel on a completed order is refused', false === $reply['success'] );
$check( '  and the order is still completed', 'completed' === $order_row()->status );

$set_order( 'in_progress' );
$reply = $call( $buyer_id, 'cancel' );
$check( 'buyer cancel after work started is refused', false === $reply['success'] );
$check( '  and the order is still in progress', 'in_progress' === $order_row()->status );

$set_order( 'pending_requirements' );
$reply = $call( $buyer_id, 'cancel' );
$check( 'buyer cancel before work starts succeeds', true === $reply['success'] );
$check( '  and the order is cancelled', 'cancelled' === $order_row()->status );

$set_order( 'in_progress' );
$reply = $call( 1, 'refund', array( 'refund_amount' => 25 ) );
$row   = $order_row();
$check( 'admin partial refund answers success', true === $reply['success'] );
$check( '  and records the amount actually refunded', 25.0 === (float) $row->refunded_amount && 'partially_refunded' === $row->status );

$check( 'wpss_accept_order is no longer registered', false === has_action( 'wp_ajax_wpss_accept_order' ) );
$check( 'wpss_decline_order is no longer registered', false === has_action( 'wp_ajax_wpss_decline_order' ) );

// --- F2: capabilities and vendor identity ---------------------------------
$author      = get_role( 'author' );
$author_wpss = $author ? array_filter( array_keys( $author->capabilities ), static fn( $c ) => 0 === strpos( $c, 'wpss_' ) ) : array();
$check( 'author role holds no wpss_* capability', empty( $author_wpss ) );

$vendor_role = get_role( 'wpss_vendor' );
$check( 'vendor role does not hold wpss_manage_orders', $vendor_role && ! $vendor_role->has_cap( 'wpss_manage_orders' ) );

wp_set_current_user( $vendor_id );
$check( 'vendor cannot force completed -> pending_payment', false === ( new OrderService() )->can_transition( 'completed', 'pending_payment' ) );

$check( 'vendor role without a profile row is not a vendor', false === wpss_is_vendor( $vendor_id ) );

$request_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_request',
		'post_status' => 'publish',
		'post_title'  => 'Gate contract request',
		'post_author' => $vendor_id,
	)
);
$proposal   = ( new ProposalService() )->submit(
	(int) $request_id,
	$vendor_id,
	array(
		'description'   => 'Bidding on my own request.',
		'price'         => 50,
		'delivery_days' => 2,
	)
);
$check( 'a vendor cannot propose on their own request', is_wp_error( $proposal ) );
if ( is_int( $proposal ) ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $proposal ) );
}

// --- Cleanup ---------------------------------------------------------------
wp_set_current_user( 0 );
wp_delete_post( (int) $request_id, true );
$wpdb->delete( $table, array( 'id' => $order_id ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer_id );
wp_delete_user( $vendor_id );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
