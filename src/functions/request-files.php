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
