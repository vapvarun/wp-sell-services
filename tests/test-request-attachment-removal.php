<?php
/**
 * A file taken off a buyer request is deleted, not just unlisted.
 *
 * Run: wp eval-file tests/test-request-attachment-removal.php
 *
 * The edit form's Remove only dropped the row; the media item stayed in the
 * public uploads folder and its link kept working (Basecamp 10377676994).
 * Makes its own buyer, request and media items and removes them.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Tests\Factories\UserFactory;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$suffix = wp_generate_password( 6, false, false );
$buyer  = UserFactory::customer( array( 'user_login' => 'wpss_reqfile_buyer_' . $suffix, 'user_email' => 'wpss_reqfile_buyer_' . $suffix . '@example.test' ) );
$made   = array();

$media = static function ( string $context ) use ( $buyer, &$made ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_title'     => 'wpss-request-file-fixture',
			'post_mime_type' => 'image/png',
			'post_author'    => $buyer->ID,
		)
	);
	update_post_meta( $id, '_wpss_upload_context', $context );
	$made[] = $id;

	return $id;
};

$service    = new BuyerRequestService();
$request_id = 0;

try {
	$first     = $media( 'request' );
	$second    = $media( 'request' );
	$portfolio = $media( 'portfolio' );

	wp_set_current_user( $buyer->ID );
	$request_id = (int) $service->create(
		array(
			'title'       => 'Request attachment removal ' . $suffix,
			'description' => 'Fixture for tests/test-request-attachment-removal.php.',
			'user_id'     => $buyer->ID,
			'attachments' => array( $first, $second, $portfolio ),
		)
	);
	$listed = static fn(): array => array_map( 'intval', (array) get_post_meta( $request_id, '_wpss_attachments', true ) );

	$check( 'the request lists its three files', $request_id > 0 && array( $first, $second, $portfolio ) === $listed() );

	$service->update( $request_id, array( 'title' => 'Request attachment removal ' . $suffix . ' (edited)' ) );
	$check( 'an update that sends no list keeps every file', array( $first, $second, $portfolio ) === $listed() && null !== get_post( $first ) );

	$service->update( $request_id, array( 'attachments' => array( $second ) ) );
	$check( 'a file left out of the list is off the request', array( $second ) === $listed() );
	$check( '  and deleted', null === get_post( $first ) );
	$check( '  the file still listed is kept', null !== get_post( $second ) );
	$check( '  a file not uploaded for a request is unlisted but never deleted', null !== get_post( $portfolio ) );

	// Once a vendor has proposed, the files are the brief that proposal was
	// written against: taking one off the list hides it, and destroys nothing.
	global $wpdb;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->prefix . 'wpss_proposals',
		array(
			'request_id'     => $request_id,
			'vendor_id'      => 1,
			'cover_letter'   => 'Fixture proposal.',
			'proposed_price' => 50,
			'proposed_days'  => 3,
			'status'         => 'withdrawn',
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$proposal = (int) $wpdb->insert_id;
	$check( 'fixture: a proposal exists on the request', $proposal > 0 );

	$service->update( $request_id, array( 'attachments' => array() ) );
	$check( 'after a proposal, a file cannot be taken off the request', array( $second ) === $listed() );
	$check( '  and is kept', null !== get_post( $second ) );
	$extra = $media( 'request' );
	$service->update( $request_id, array( 'attachments' => array( $extra ) ) );
	$check( '  a new file can still be added beside it', array( $second, $extra ) === $listed() );
	$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $proposal ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// The file is attached while the request is still untouched, through the
	// service, the way the edit form and REST do it. Then a vendor proposes.
	$third = $media( 'request' );
	$service->update( $request_id, array( 'attachments' => array( $third ) ) );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->prefix . 'wpss_proposals',
		array(
			'request_id'     => $request_id,
			'vendor_id'      => 1,
			'cover_letter'   => 'Fixture proposal.',
			'proposed_price' => 50,
			'proposed_days'  => 3,
			'status'         => 'accepted',
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$proposal = (int) $wpdb->insert_id;
	update_post_meta( $request_id, '_wpss_status', BuyerRequestService::STATUS_HIRED );

	// The author controls the request's status, so the gate must not read it:
	// with a proposal on it, no status makes the files deletable.
	$service->update( $request_id, array( 'status' => BuyerRequestService::STATUS_OPEN ) );
	$service->update( $request_id, array( 'attachments' => array() ) );
	$check( 'reopening a request with a proposal, then emptying the list, deletes nothing', null !== get_post( $third ) );

	// The same rule on DELETE /media/{id}, the other way to delete the file -
	// and it holds after the file has been taken off the list: unlisting it
	// first must not unlock the delete.
	wp_set_current_user( $buyer->ID );
	$check( 'the file is still on the list after that update', in_array( $third, $listed(), true ) );
	$answer = rest_do_request( new WP_REST_Request( 'DELETE', '/wpss/v1/media/' . $third ) );
	$check( 'DELETE /media/{id} refuses a file of a request with a proposal (' . $answer->get_status() . ')', 409 === $answer->get_status() && null !== get_post( $third ) );

	// The lock sits on WordPress's own delete, so every door obeys it: the
	// order requirement-file route deletes any file its caller uploaded and
	// had no check of its own (QA bounce).
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number'   => 'WPSS-REQFILE-' . $suffix,
			'customer_id'    => $buyer->ID,
			'vendor_id'      => 1,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'total'          => 10,
			'currency'       => 'USD',
			'status'         => 'pending_requirements',
			'payment_status' => 'paid',
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$own_order = (int) $wpdb->insert_id;
	$answer    = rest_do_request( new WP_REST_Request( 'DELETE', '/wpss/v1/orders/' . $own_order . '/requirements/files/' . $third ) );
	$check( 'the order requirement-file route, on the member\'s own order, cannot delete it (' . $answer->get_status() . ')', 409 === $answer->get_status() && null !== get_post( $third ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $own_order ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$check( '  nor a plain wp_delete_attachment() as that member', ! wp_delete_attachment( $third, true ) && null !== get_post( $third ) );
	// A request lists any file its author owns, so the lock cannot depend on
	// what the file was uploaded as (security review of the first fix).
	$elsewhere = $media( 'requirement' );
	$service->update( $request_id, array( 'attachments' => array( $third, $elsewhere ) ) );
	$check( '  nor a file uploaded for something else and then added to the request', in_array( $elsewhere, $listed(), true ) && ! wp_delete_attachment( $elsewhere, true ) && null !== get_post( $elsewhere ) );
	wp_set_current_user( 1 );
	$check( '  the lock does not stop a site administrator', false === wpss_guard_locked_request_file( null, get_post( $third ) ) ? false : true );
	wp_set_current_user( $buyer->ID );

	// The request itself: with a proposal on it, it can be closed, not deleted,
	// from the website and from REST alike. Deleting it used to unlock its
	// files as well (Basecamp 10379690155).
	$check( 'a request with a proposal cannot be deleted through the service', false === $service->delete( $request_id ) && 'trash' !== get_post_status( $request_id ) );
	$answer = rest_do_request( new WP_REST_Request( 'DELETE', '/wpss/v1/buyer-requests/' . $request_id ) );
	$check( '  nor through REST (' . $answer->get_status() . ')', 409 === $answer->get_status() && null !== get_post( $request_id ) && 'trash' !== get_post_status( $request_id ) );
	$_POST = array( 'request_id' => $request_id, 'nonce' => wp_create_nonce( 'wpss_dashboard_nonce' ) );
	$_REQUEST = $_POST;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'ajax end' ); } );
	ob_start();
	try {
		( new \WPSellServices\Frontend\AjaxHandlers() )->delete_request();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$said = json_decode( (string) ob_get_clean(), true );
	remove_all_filters( 'wp_die_ajax_handler' );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST = array();
	$_REQUEST = array();
	$check( '  nor from the dashboard button\'s request', false === ( $said['success'] ?? null ) && null !== get_post( $request_id ) && 'trash' !== get_post_status( $request_id ) );

	// With the proposal gone the request is untouched again and the file is free.
	$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $proposal ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$answer = rest_do_request( new WP_REST_Request( 'DELETE', '/wpss/v1/media/' . $third ) );
	$check( '  and deletes it once no request with a proposal lists it (' . $answer->get_status() . ')', 200 === $answer->get_status() && null === get_post( $third ) );

	// A request locks only a file it really lists. In the stored list an array
	// position reads like a file ID, and another member's request is not ours.
	$other  = UserFactory::customer( array( 'user_login' => 'wpss_reqfile_other_' . $suffix, 'user_email' => 'wpss_reqfile_other_' . $suffix . '@example.test' ) );
	$theirs = (int) wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'Other member request ' . $suffix, 'post_author' => $other->ID ) );
	$mine   = $media( 'request' );
	// Another member's request, with a proposal on it, names our file in its
	// list (written straight to meta: the service would have filtered it out).
	update_post_meta( $theirs, '_wpss_attachments', array( 999998, $mine ) );
	$wpdb->insert( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => $theirs, 'vendor_id' => 1, 'cover_letter' => 'Fixture.', 'proposed_price' => 50, 'proposed_days' => 3, 'status' => 'pending', 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$their_proposal = (int) $wpdb->insert_id;
	$check( 'another member\'s request naming our file does not lock it', false === $service->is_file_locked( $mine ) );

	update_post_meta( $theirs, '_wpss_attachments', array( 999998, 999999 ) );
	$check( 'a list position that reads like a file ID does not lock that file', false === $service->is_file_locked( 1 ) && false === $service->is_file_locked( 0 ) );

	$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $their_proposal ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	wp_delete_post( $theirs, true );
} finally {
	wp_set_current_user( 0 );
	if ( $request_id ) {
		wp_delete_post( $request_id, true );
	}
	foreach ( $made as $id ) {
		wp_delete_attachment( $id, true );
	}
	UserFactory::cleanup();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
