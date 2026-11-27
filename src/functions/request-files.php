<?php
/**
 * Buyer-request attachments and the guards that lock them once proposed.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current visitor may see a buyer request's attachments.
 *
 * Signed-in members only: briefs and mockups can carry a client's private
 * details, and anyone who can act on a request (quote, review) is signed in
 * already. Owner decision on Basecamp 10337217098. The website and REST both
 * ask here.
 *
 * @since 1.8.0
 *
 * @param int $request_id Buyer request post ID.
 * @return bool
 */
function wpss_can_view_request_attachments( int $request_id ): bool {
	/**
	 * Filter who may see a buyer request's attachments.
	 *
	 * @since 1.8.0
	 *
	 * @param bool $can        Default: the visitor is signed in.
	 * @param int  $request_id Buyer request post ID.
	 */
	return (bool) apply_filters( 'wpss_can_view_request_attachments', is_user_logged_in(), $request_id );
}

/**
 * Keep a member from deleting a file that a buyer request still needs.
 *
 * A request's files are locked once a seller has proposed on it
 * (BuyerRequestService::is_file_locked()). Checking that in each route that
 * deletes media left a door open every time: the media route had the check,
 * the order requirement-file route and its AJAX twin did not, and each
 * deletes any file its caller uploaded (Basecamp 10377676994). This sits on
 * WordPress's own delete, so every door goes through it, including ones
 * added later and the Media Library.
 *
 * Site administrators, WP-CLI and background jobs are not stopped: closing an
 * account or clearing demo content must still be able to remove files.
 *
 * @since 1.8.0
 *
 * @param mixed    $delete Short-circuit value; non-null stops the delete.
 * @param \WP_Post $post   The attachment.
 * @return mixed False to refuse the delete, otherwise $delete unchanged.
 */
function wpss_guard_locked_request_file( $delete, $post ) {
	if ( null !== $delete || ! $post instanceof \WP_Post || ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
		return $delete;
	}

	// Asked for every file, whatever it was uploaded as: a request lists any
	// file its author owns, so the upload context says nothing about the lock.
	return ( new \WPSellServices\Services\BuyerRequestService() )->is_file_locked( (int) $post->ID ) ? false : $delete;
}

add_filter( 'pre_delete_attachment', 'wpss_guard_locked_request_file', 10, 2 );

/**
 * Keep a member from trashing or deleting a buyer request a seller has proposed on.
 *
 * The rule lived in BuyerRequestService::delete() only. The request is a
 * post type WordPress exposes itself (wp/v2 REST, the posts screen), so a
 * member whose role may delete posts had a door that never asked. Deleting
 * the request also unlocks its files. On WordPress's own trash and delete,
 * every door asks.
 *
 * Site administrators, WP-CLI and background jobs are not stopped. Closing an
 * account lifts it for its own requests (AccountDeletionService).
 *
 * @since 1.8.0
 *
 * @param mixed    $check Short-circuit value; non-null stops the trash or delete.
 * @param \WP_Post $post  The post.
 * @return mixed False to refuse, otherwise $check unchanged.
 */
function wpss_guard_proposed_request( $check, $post ) {
	if ( null !== $check || ! $post instanceof \WP_Post || 'wpss_request' !== $post->post_type || ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
		return $check;
	}

	return ( new \WPSellServices\Services\BuyerRequestService() )->is_untouched( (int) $post->ID ) ? $check : false;
}

add_filter( 'pre_trash_post', 'wpss_guard_proposed_request', 10, 2 );

add_filter( 'pre_delete_post', 'wpss_guard_proposed_request', 10, 2 );

/**
 * The same rule for a request changed out from under the guard above.
 *
 * Saving a post with the status "trash" never calls wp_trash_post(), and
 * saving it as another post type leaves a post neither the guard nor the file
 * lock recognises as a request (XML-RPC, a bulk edit, any wp_update_post()).
 * Asked of the post as it is stored, not as the save describes it: the
 * request keeps its status and its type.
 *
 * @since 1.8.0
 *
 * @param array<string, mixed> $data    Post data about to be saved.
 * @param array<string, mixed> $postarr Raw post array, with the ID on an update.
 * @return array<string, mixed>
 */
function wpss_guard_proposed_request_status( $data, $postarr ) {
	$post = empty( $postarr['ID'] ) ? null : get_post( (int) $postarr['ID'] );

	if ( ! $post || 'wpss_request' !== $post->post_type || false !== wpss_guard_proposed_request( null, $post ) ) {
		return $data;
	}

	// The type is kept whatever the stored status, a request already in the
	// trash included; only the move INTO the trash is a status question.
	$data['post_type'] = $post->post_type;

	if ( 'trash' === ( $data['post_status'] ?? '' ) && 'trash' !== $post->post_status ) {
		$data['post_status'] = $post->post_status;
	}

	return $data;
}

add_filter( 'wp_insert_post_data', 'wpss_guard_proposed_request_status', 10, 2 );

/**
 * Store an uploaded buyer-request brief in the private store.
 *
 * The brief keeps an attachment post as its identity - the integer id every request lists, its
 * owner, the proposal file lock and the delete guards all key on it - but its bytes live in the
 * order-files store under `r{attachment}/`, behind the request read gate, never in
 * wp-content/uploads (Basecamp 10551464986).
 *
 * @since 2.0.0
 *
 * @param array<string,mixed> $file One entry from $_FILES, already checked by wpss_check_upload().
 * @return int|WP_Error Attachment id.
 */
function wpss_store_request_brief( array $file ) {
	$name          = sanitize_text_field( (string) ( $file['name'] ?? '' ) );
	$attachment_id = wp_insert_attachment(
		array(
			'post_title'     => sanitize_file_name( $name ),
			'post_status'    => 'private',
			'post_mime_type' => (string) ( wp_check_filetype( $name )['type'] ?? '' ),
			'guid'           => '',
		),
		false,
		0,
		true
	);

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	$record = wpss_store_order_file( $file, (int) $attachment_id, 'request' );

	if ( ! $record ) {
		wp_delete_post( (int) $attachment_id, true );

		return new WP_Error( 'upload_failed', __( 'The file could not be saved.', 'wp-sell-services' ), array( 'status' => 500 ) );
	}

	// Addressed by the attachment id, like every brief already listed on a request.
	$record['id'] = (string) $attachment_id;

	update_post_meta( $attachment_id, '_wpss_file', $record );
	update_post_meta( $attachment_id, '_wpss_original_name', $name );

	return (int) $attachment_id;
}

/**
 * A buyer-request brief as a file record, whichever way it was stored.
 *
 * A brief stored since 2.0.0 carries its record on the attachment (`_wpss_file`). An older brief
 * uploaded for a request is still in wp-content/uploads: its record has a `url` and an owner, and
 * wpss_locate_order_file() moves it into the private store the first time someone allowed opens it.
 * Any other media the author listed keeps its own public address.
 *
 * @since 2.0.0
 *
 * @param int $attachment_id Attachment id.
 * @return array<string,mixed>|null
 */
function wpss_request_file_record( int $attachment_id ): ?array {
	if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
		return null;
	}

	$stored = get_post_meta( $attachment_id, '_wpss_file', true );

	if ( is_array( $stored ) && ( ! empty( $stored['path'] ) || ! empty( $stored['remote_path'] ) ) ) {
		return array( 'attachment_id' => $attachment_id ) + $stored;
	}

	$file   = (string) get_attached_file( $attachment_id );
	$record = array(
		'id'   => (string) $attachment_id,
		'name' => wpss_attachment_display_name( $attachment_id ),
		'type' => (string) get_post_mime_type( $attachment_id ),
		'size' => '' !== $file && is_readable( $file ) ? (int) filesize( $file ) : 0,
		'url'  => (string) wp_get_attachment_url( $attachment_id ),
	);

	if ( 'request' === get_post_meta( $attachment_id, '_wpss_upload_context', true ) ) {
		$record['attachment_id'] = $attachment_id;
	}

	return $record;
}

/**
 * The gated link to a brief listed on a buyer request.
 *
 * @since 2.0.0
 *
 * @param int $attachment_id Attachment id listed in the request's `_wpss_attachments`.
 * @param int $request_id    Buyer request id.
 * @return string '' when there is no file to link.
 */
function wpss_request_file_url( int $attachment_id, int $request_id ): string {
	$record = wpss_request_file_record( $attachment_id );

	if ( ! $record || ( empty( $record['path'] ) && empty( $record['remote_path'] ) && empty( $record['url'] ) ) ) {
		return '';
	}

	return wpss_file_link( 'request', $request_id, (string) $attachment_id );
}

/**
 * Remove a private brief's bytes when its attachment post is deleted.
 *
 * WordPress deletes the files it knows about; a private brief's bytes are ours.
 *
 * @since 2.0.0
 *
 * @param int $attachment_id Attachment being deleted.
 * @return void
 */
function wpss_discard_request_brief( int $attachment_id ): void {
	$record = get_post_meta( $attachment_id, '_wpss_file', true );

	if ( is_array( $record ) && ( ! empty( $record['path'] ) || ! empty( $record['remote_path'] ) ) ) {
		wpss_discard_order_file( array( 'attachment_id' => $attachment_id ) + $record, 0 );

		foreach ( wpss_order_files_dirs() as $dir ) {
			@rmdir( $dir . 'r' . $attachment_id . '/' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removed only when empty.
		}
	}
}

add_action( 'delete_attachment', 'wpss_discard_request_brief' );

/**
 * A request's listed briefs as links, for the request page, its admin box and REST.
 *
 * @since 2.0.0
 *
 * @param mixed $attachments The request's `_wpss_attachments` value (ids; a JSON string from older rows).
 * @param int   $request_id  Buyer request id.
 * @return array<int,array{id:int,url:string,filename:string,type:string}>
 */
function wpss_request_files_for_client( $attachments, int $request_id ): array {
	if ( ! is_array( $attachments ) ) {
		$attachments = json_decode( (string) $attachments, true ) ?: array();
	}

	$files = array();

	foreach ( array_filter( array_map( 'absint', $attachments ) ) as $attachment_id ) {
		$url = wpss_request_file_url( $attachment_id, $request_id );

		if ( '' !== $url ) {
			$record  = (array) wpss_request_file_record( $attachment_id );
			$files[] = array(
				'id'       => $attachment_id,
				'url'      => $url,
				'filename' => wpss_attachment_display_name( $attachment_id ),
				'type'     => (string) ( $record['type'] ?? '' ),
			);
		}
	}

	return $files;
}
