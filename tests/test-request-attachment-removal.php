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
