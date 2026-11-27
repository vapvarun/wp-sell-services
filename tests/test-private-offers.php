<?php
/**
 * Private offers from a conversation to one buyer, Basecamp 10551449528.
 *
 * Run: wp eval-file tests/test-private-offers.php
 *
 * An offer is a hidden (private) buyer request owned by the buyer, plus the vendor's proposal on it,
 * plus an offer message in the thread. Only the buyer, the vendor and admins may see it; accepting it
 * makes a normal order at the offered price, days and revisions; decline, withdraw and expiry close it.
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

$check( 'the private offer helpers exist', function_exists( 'wpss_send_private_offer' ) && function_exists( 'wpss_get_private_offer_fields' ) );

global $wpdb;
$f        = wpss_test_fixtures();
$outsider = (int) wp_insert_user(
	array(
		'user_login' => $f->tag . '_outsider',
		'user_pass'  => wp_generate_password(),
		'user_email' => $f->tag . '_outsider@example.test',
		'role'       => 'subscriber',
	)
);
$rival    = (int) wp_insert_user(
	array(
		'user_login' => $f->tag . '_rival',
		'user_pass'  => wp_generate_password(),
		'user_email' => $f->tag . '_rival@example.test',
		'role'       => 'wpss_vendor',
	)
);
$f->users[] = $outsider;
$f->users[] = $rival;
( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->upsert( $rival, array( 'status' => 'active' ) );

$conversations = new \WPSellServices\Services\ConversationService();
$conv          = $conversations->create_direct( (int) $f->vendor, (int) $f->buyer, 'QA pre-sale chat' );
$conv_id       = (int) ( $conv->id ?? 0 );
$fired         = array();
$mails         = array();
$requests      = array();
$spy           = static function () use ( &$fired ) {
	$fired[ current_action() ] = ( $fired[ current_action() ] ?? 0 ) + 1;
};
$track         = static function ( $proposal_id, $request_id ) use ( &$requests ) {
	$requests[] = (int) $request_id;
};
$mail_spy      = static function ( $short, $atts ) use ( &$mails ) {
	$mails[] = (string) $atts['subject'];
	return true;
};
foreach ( array( 'wpss_private_offer_sent', 'wpss_private_offer_accepted', 'wpss_private_offer_declined', 'wpss_private_offer_expired' ) as $hook ) {
	add_action( $hook, $spy );
}
add_action( 'wpss_private_offer_sent', $track, 10, 2 );
add_filter( 'pre_wp_mail', $mail_spy, 10, 2 );

$terms = array(
	'title'         => 'Logo refresh for the bakery',
	'description'   => 'Two logo directions and one round of refinements, files in SVG and PNG.',
	'price'         => 120,
	'delivery_days' => 5,
	'revisions'     => 3,
);
$send  = static function ( int $user, array $body ) use ( $conv_id ) {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( 'POST', '/wpss/v1/conversations/' . $conv_id . '/offers' );
	$req->set_body_params( $body );
	return rest_do_request( $req );
};
$read  = static function ( int $user, int $rid ): int {
	wp_set_current_user( $user );
	return rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/buyer-requests/' . $rid ) )->get_status();
};
$answer = static function ( int $user, array $offer, string $verb ) {
	wp_set_current_user( $user );
	return rest_do_request( new WP_REST_Request( 'POST', $offer[ 'accept' === $verb ? 'accept_route' : 'decline_route' ] ) );
};

try {
	// 1. Who may send, and where.
	$check( 'a pre-sale conversation exists between vendor and buyer', $conv_id > 0 );
	$check( 'the buyer cannot send an offer', 403 === $send( (int) $f->buyer, $terms )->get_status() );
	$check( 'a member outside the conversation cannot send one', 403 === $send( $rival, $terms )->get_status() );
	$check( 'a zero price is refused with the field named', 'price' === ( ( $send( (int) $f->vendor, array( 'price' => 0 ) + $terms )->get_data()['data']['field'] ) ?? '' ) );
	$order_conv = $conversations->create_for_order( ( $f->order )() );
	$check( 'an order conversation takes no offer', $order_conv && is_wp_error( wpss_send_private_offer( (int) $order_conv->id, (int) $f->vendor, $terms ) ) );

	// 2. The vendor sends one.
	$mails = array();
	$res   = $send( (int) $f->vendor, $terms );
	$offer = (array) $res->get_data();
	$rid   = (int) ( $offer['request_id'] ?? 0 );
	$check( 'the vendor sends an offer through REST', 201 === $res->get_status() && 'pending' === ( $offer['state'] ?? '' ), wp_json_encode( $offer ) );
	$check( '  stored as a private request owned by the buyer', 'private' === get_post_status( $rid ) && (int) get_post_field( 'post_author', $rid ) === (int) $f->buyer );
	$check( '  with the vendor\'s pending proposal at the offered price', 120.0 === (float) $offer['price'] && 5 === $offer['delivery_days'] && 3 === $offer['revisions'] );
	$msg = $conversations->get_message( (int) $offer['message_id'] );
	$check( '  and an offer message in the thread naming its proposal', $msg && 'offer' === $msg->type && (int) ( $msg->metadata['proposal_id'] ?? 0 ) === (int) $offer['id'] );
	$check( '  the buyer is emailed about a private offer, not a proposal on their request', (bool) array_filter( $mails, static fn( $s ) => str_contains( $s, 'private offer' ) ), wp_json_encode( $mails ) );
	$check( '  wpss_private_offer_sent fired', 1 === ( $fired['wpss_private_offer_sent'] ?? 0 ) );

	// 3. Only the buyer, the vendor and admins see it.
	$check( 'REST read: buyer 200, vendor 200, admin 200', 200 === $read( (int) $f->buyer, $rid ) && 200 === $read( (int) $f->vendor, $rid ) && 200 === $read( (int) $f->admin, $rid ) );
	$check( '  another member and a visitor get 404', 404 === $read( $outsider, $rid ) && 404 === $read( 0, $rid ) );
	$open = wp_list_pluck( ( new \WPSellServices\Services\BuyerRequestService() )->get_open( array( 'posts_per_page' => 200 ) ), 'id' );
	$check( '  it is not in the open request list', ! in_array( $rid, array_map( 'intval', $open ), true ) );
	wp_set_current_user( 0 );
	$listed = wp_list_pluck( (array) rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/buyer-requests' ) )->get_data(), 'id' );
	$check( '  nor in GET /buyer-requests', ! in_array( $rid, array_map( 'intval', $listed ), true ) );
	wp_set_current_user( $outsider );
	$found = ( new WP_Query( array( 's' => 'Logo refresh for the bakery', 'post_type' => 'any', 'fields' => 'ids' ) ) )->posts;
	$check( '  nor in site search for another member', ! in_array( $rid, array_map( 'intval', $found ), true ) );
	$check( '  nor readable as an offer by them', null === wpss_private_offer_for_client( (int) $offer['id'], $outsider ) );
	$check( '  another vendor cannot bid on it', false === ( new \WPSellServices\Services\ProposalService() )->submit( $rid, $rival, array( 'description' => 'Me too', 'price' => 90 ) ) );

	// 3b. The buyer owns the hidden request but cannot rewrite the offer through any request door.
	$put = static function ( int $user, array $body ) use ( $rid ) {
		wp_set_current_user( $user );
		$req = new WP_REST_Request( 'PUT', '/wpss/v1/buyer-requests/' . $rid );
		$req->set_body_params( $body );
		return rest_do_request( $req );
	};
	$expiry = get_post_meta( $rid, '_wpss_expires_at', true );
	$check( 'the buyer cannot edit the offer over REST (title, deadline, status): 403', 403 === $put( (int) $f->buyer, array( 'title' => 'Free logo', 'deadline' => gmdate( 'Y-m-d', time() + 80 * DAY_IN_SECONDS ), 'status' => 'closed' ) )->get_status() );
	wp_set_current_user( (int) $f->buyer );
	$check( '  nor through the request service', false === ( new \WPSellServices\Services\BuyerRequestService() )->update( $rid, array( 'title' => 'Free logo' ) ) );
	$check( '  title, expiry, status and privacy are unchanged', 'Logo refresh for the bakery' === get_the_title( $rid ) && get_post_meta( $rid, '_wpss_expires_at', true ) === $expiry && 'open' === get_post_meta( $rid, '_wpss_status', true ) && 'private' === get_post_status( $rid ) );
	$check( '  the vendor cannot edit it either (not the owner): 403', 403 === $put( (int) $f->vendor, array( 'title' => 'Free logo' ) )->get_status() );
	$check( '  an admin still can', 200 === $put( (int) $f->admin, array( 'description' => $terms['description'] ) )->get_status() );

	// 4. The thread shows it to each side.
	wp_set_current_user( (int) $f->buyer );
	$rows = (array) rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/conversations/' . $conv_id . '/messages' ) )->get_data();
	$rows = isset( $rows['data'] ) ? $rows['data'] : $rows;
	$row  = array_values( array_filter( $rows, static fn( $r ) => 'offer' === ( $r['type'] ?? '' ) ) )[0] ?? array();
	$check( 'messages REST carries the offer; the buyer may answer', true === ( $row['offer']['can_respond'] ?? null ) && str_contains( (string) ( $row['html'] ?? '' ), 'data-wpss-offer="accept"' ) );
	$vendor_html = wpss_render_message_row( $msg, (int) $f->vendor );
	$check( '  the vendor sees it waiting, with no buttons', str_contains( $vendor_html, 'wpss-offer-card' ) && ! str_contains( $vendor_html, 'data-wpss-offer=' ) && false === wpss_private_offer_for_client( (int) $offer['id'], (int) $f->vendor )['can_respond'] );

	// 5. Decline closes it.
	$second = (array) $send( (int) $f->vendor, array( 'title' => 'Menu boards' ) + $terms )->get_data();
	$mails  = array();
	$check( 'the buyer declines an offer', 200 === $answer( (int) $f->buyer, $second, 'decline' )->get_status() );
	$note = $wpdb->get_row( $wpdb->prepare( "SELECT title, message FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d AND type = 'proposal_rejected' ORDER BY id DESC LIMIT 1", $f->vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( '  the vendor is told the offer was declined, not that another vendor won', $note && str_contains( $note->title, 'offer was declined' ) && ! str_contains( (string) $note->message, 'another vendor' ) && (bool) array_filter( $mails, static fn( $s ) => str_contains( $s, 'private offer was declined' ) ), wp_json_encode( array( $note, $mails ) ) );
	$check( '  it reads declined and its request is closed', 'declined' === wpss_private_offer_for_client( (int) $second['id'], (int) $f->buyer )['state'] && 'closed' === get_post_meta( (int) $second['request_id'], '_wpss_status', true ) && 1 === ( $fired['wpss_private_offer_declined'] ?? 0 ) );
	$check( '  and cannot be accepted afterwards', 200 !== $answer( (int) $f->buyer, $second, 'accept' )->get_status() );

	// 6. Expiry: refused at once, closed by the daily sweep.
	$third = (array) $send( (int) $f->vendor, array( 'title' => 'Shop sign' ) + $terms )->get_data();
	update_post_meta( (int) $third['request_id'], '_wpss_expires_at', gmdate( 'Y-m-d H:i:s', time() - 60 ) );
	$check( 'an offer past its window reads expired', 'expired' === wpss_private_offer_for_client( (int) $third['id'], (int) $f->buyer )['state'] );
	$late = $answer( (int) $f->buyer, $third, 'accept' );
	$check( '  and accepting it is refused before the sweep runs', 400 === $late->get_status() && str_contains( (string) $late->get_data()['message'], 'expired' ) );
	( new \WPSellServices\Services\BuyerRequestService() )->expire_old_requests();
	$check( '  the sweep closes it and fires wpss_private_offer_expired', 'expired' === get_post_meta( (int) $third['request_id'], '_wpss_status', true ) && 1 === ( $fired['wpss_private_offer_expired'] ?? 0 ) );

	// 7. Withdraw closes it too.
	$fourth = (array) $send( (int) $f->vendor, array( 'title' => 'Stickers' ) + $terms )->get_data();
	( new \WPSellServices\Services\ProposalService() )->withdraw( (int) $fourth['id'], (int) $f->vendor );
	$check( 'a withdrawn offer closes its request', 'closed' === get_post_meta( (int) $fourth['request_id'], '_wpss_status', true ) && 'withdrawn' === wpss_private_offer_for_client( (int) $fourth['id'], (int) $f->buyer )['state'] );

	// 8. Accept: a normal order at the offered terms.
	$mails    = array();
	$accepted = $answer( (int) $f->buyer, $offer, 'accept' );
	$data     = (array) $accepted->get_data();
	$oid      = (int) ( $data['order_id'] ?? 0 );
	if ( $oid ) {
		$f->orders[] = $oid;
	}
	$order = $oid ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $oid ) ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( 'the buyer accepts: an order waiting for payment, with a pay link', 200 === $accepted->get_status() && $order && 'pending_payment' === $order->status && '' !== (string) ( $data['checkout_url'] ?? '' ), wp_json_encode( $data ) );
	$check( '  at the offered price, days and revisions', $order && 120.0 === (float) $order->subtotal && 3 === (int) $order->revisions_included && 5 === (int) round( ( strtotime( $order->delivery_deadline ) - strtotime( $order->created_at ) ) / DAY_IN_SECONDS ) );
	$check( '  the vendor hears their private offer was accepted', (bool) array_filter( $mails, static fn( $s ) => str_contains( $s, 'private offer was accepted' ) ), wp_json_encode( $mails ) );
	$check( '  the order says it came from a private offer', $order && str_starts_with( wpss_get_order_subject( $order, 'admin' )['label'], 'Private offer:' ) );
	$after = wpss_private_offer_for_client( (int) $offer['id'], (int) $f->buyer );
	$check( '  the card reads accepted and links the order; the hook fired', 'accepted' === $after['state'] && '' !== $after['order_url'] && 1 === ( $fired['wpss_private_offer_accepted'] ?? 0 ) );

	// 9. Surfaces.
	$_GET['conversation_id'] = $conv_id;
	$section                 = static function ( int $user ): string {
		wp_set_current_user( $user );
		ob_start();
		wpss_get_template_part(
			'dashboard/sections/messages',
			'',
			array(
				'user_id'   => $user,
				'is_vendor' => wpss_is_vendor( $user ),
			)
		);
		return (string) ob_get_clean();
	};
	$check( 'the vendor\'s thread offers "Send an offer"; the buyer\'s does not', str_contains( $section( (int) $f->vendor ), 'data-wpss-offer-form' ) && ! str_contains( $section( (int) $f->buyer ), 'data-wpss-offer-form' ) );
	unset( $_GET['conversation_id'] );
	$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/unified-dashboard.min.js' );
	$check( '  the served dashboard JS sends and answers offers', str_contains( $js, '/offers' ) && str_contains( $js, 'data-wpss-offer' ) );
} finally {
	remove_filter( 'pre_wp_mail', $mail_spy );
	wp_set_current_user( 0 );
	$proposals = $requests ? $wpdb->get_col( 'SELECT id FROM ' . $wpdb->prefix . 'wpss_proposals WHERE request_id IN (' . implode( ',', array_map( 'intval', $requests ) ) . ')' ) : array(); // phpcs:ignore WordPress.DB
	foreach ( $requests as $request_id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => $request_id ) );
		wp_delete_post( $request_id, true );
	}
	foreach ( $f->orders as $order_id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $order_id ) );
		$conversation = $conversations->get_by_order( $order_id );
		if ( $conversation ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $conversation->id ) );
			$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => $conversation->id ) );
		}
	}
	if ( $conv_id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $conv_id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => $conv_id ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
