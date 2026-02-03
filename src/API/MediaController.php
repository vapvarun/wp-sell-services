<?php
/**
 * Media REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\API;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for file uploads.
 *
 * @since 1.0.0
 */
class MediaController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'media';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /media - Upload file.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /media/{id} - Get file info.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_read_permissions' ),
				),
			)
		);

		// DELETE /media/{id} - Delete file.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_owner_permissions' ),
				),
			)
		);
	}

	/**
	 * Upload file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file provided.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];

		// Validate file size.
		$max_size = (int) get_option( 'wpss_max_file_size', 10 ) * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'file_too_large',
				/* translators: %s: maximum file size */
				sprintf( __( 'File size exceeds the maximum of %s.', 'wp-sell-services' ), size_format( $max_size ) ),
				array( 'status' => 400 )
			);
		}

		// Verify MIME type matches extension to prevent disguised uploads.
		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( ! $filetype['ext'] || ! $filetype['type'] ) {
			return new WP_Error( 'invalid_type', __( 'File type could not be verified.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Validate file type against allowed list using the verified extension.
		$allowed_types = explode( ',', get_option( 'wpss_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,zip' ) );
		$file_ext      = strtolower( $filetype['ext'] );

		if ( ! in_array( $file_ext, $allowed_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'File type not allowed.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Use WordPress media handling.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'upload_failed',
				$attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Tag attachment with WPSS context.
		update_post_meta( $attachment_id, '_wpss_upload', true );
		update_post_meta( $attachment_id, '_wpss_uploader', get_current_user_id() );

		$context = $request->get_param( 'context' );
		if ( $context ) {
			update_post_meta( $attachment_id, '_wpss_upload_context', sanitize_text_field( $context ) );
		}

		return new WP_REST_Response( $this->format_attachment( $attachment_id ), 201 );
	}

	/**
	 * Get file info.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$attachment_id = (int) $request->get_param( 'id' );
		$attachment    = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'not_found', __( 'File not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_attachment( $attachment_id ) );
	}

	/**
	 * Delete file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$attachment_id = (int) $request->get_param( 'id' );

		$result = wp_delete_attachment( $attachment_id, true );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete file.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Check owner permissions for delete.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_owner_permissions( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		$attachment_id = (int) $request->get_param( 'id' );
		$attachment    = get_post( $attachment_id );

		if ( ! $attachment ) {
			return new WP_Error( 'not_found', __( 'File not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( (int) $attachment->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not own this file.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check read permissions for file info.
	 *
	 * Allows file uploader, order participants, and admins.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_read_permissions( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$attachment_id = (int) $request->get_param( 'id' );
		$attachment    = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'not_found', __( 'File not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$user_id = get_current_user_id();

		// File uploader can access.
		$uploader = (int) get_post_meta( $attachment_id, '_wpss_uploader', true );
		if ( $uploader === $user_id || (int) $attachment->post_author === $user_id ) {
			return true;
		}

		// Check if file is linked to an order the user participates in.
		$context = get_post_meta( $attachment_id, '_wpss_upload_context', true );
		if ( $context ) {
			// Allow if user owns any order resource.
			$order_id = (int) get_post_meta( $attachment_id, '_wpss_order_id', true );
			if ( $order_id && $this->user_owns_resource( $order_id, 'order' ) ) {
				return true;
			}
		}

		return new WP_Error( 'rest_forbidden', __( 'You do not have access to this file.', 'wp-sell-services' ), array( 'status' => 403 ) );
	}

	/**
	 * Format attachment for response.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private function format_attachment( int $attachment_id ): array {
		$url       = wp_get_attachment_url( $attachment_id );
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$filepath  = get_attached_file( $attachment_id );
		$filetype  = wp_check_filetype( $filepath );
		$file_size = $filepath ? @filesize( $filepath ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$data = array(
			'id'        => $attachment_id,
			'url'       => $url,
			'filename'  => $filepath ? basename( $filepath ) : '',
			'filesize'  => $file_size ?: 0,
			'mime_type' => $filetype['type'],
			'type'      => wp_ext2type( $filetype['ext'] ) ?: 'other',
		);

		// Add image-specific data.
		if ( wp_attachment_is_image( $attachment_id ) ) {
			$data['sizes'] = array(
				'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
				'medium'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
				'large'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
				'full'      => $url,
			);

			if ( ! empty( $metadata['width'] ) ) {
				$data['width']  = (int) $metadata['width'];
				$data['height'] = (int) $metadata['height'];
			}
		}

		return $data;
	}
}
