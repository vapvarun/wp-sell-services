<?php
/**
 * Delivery Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Models\Message;

/**
 * Handles delivery business logic.
 *
 * @since 1.0.0
 */
class DeliveryService {

	/**
	 * Submit delivery for order.
	 *
	 * @param int    $order_id    Order ID.
	 * @param string $message     Delivery message.
	 * @param array  $files       Delivery files.
	 * @return bool
	 */
	public function submit( int $order_id, string $message, array $files = array() ): bool {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Validate order status allows delivery.
		if ( ! in_array( $order->status, array( ServiceOrder::STATUS_IN_PROGRESS, ServiceOrder::STATUS_REVISION_REQUESTED, ServiceOrder::STATUS_LATE ), true ) ) {
			return false;
		}

		global $wpdb;
		$deliveries_table = $wpdb->prefix . 'wpss_deliveries';

		// Get version number.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$version_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$deliveries_table} WHERE order_id = %d",
				$order_id
			)
		);

		$version = $version_count + 1;

		// Process uploaded files.
		$processed_files = array();
		foreach ( $files as $file ) {
			$processed = $this->process_file( $file, $order_id );
			if ( $processed ) {
				$processed_files[] = $processed;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$deliveries_table,
			array(
				'order_id'    => $order_id,
				'vendor_id'   => $order->vendor_id,
				'version'     => $version,
				'message'     => $message,
				'attachments' => wp_json_encode( $processed_files ),
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		$delivery_id = (int) $wpdb->insert_id;

		if ( ! $delivery_id ) {
			return false;
		}

		// Add delivery message to conversation.
		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->get_by_order( $order_id );

		if ( $conversation ) {
			$conversation_service->send_message(
				$conversation->id,
				$order->vendor_id,
				$message,
				$processed_files,
				Message::TYPE_DELIVERY
			);
		}

		// Update order status.
		$order_service = new OrderService();
		$order_service->update_status( $order_id, ServiceOrder::STATUS_PENDING_APPROVAL );

		/**
		 * Fires when a delivery is submitted.
		 *
		 * @param int $delivery_id Delivery ID.
		 * @param int $order_id    Order ID.
		 */
		do_action( 'wpss_delivery_submitted', $delivery_id, $order_id );

		return true;
	}

	/**
	 * Accept delivery.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function accept( int $order_id ): bool {
		$order = wpss_get_order( $order_id );

		if ( ! $order || ServiceOrder::STATUS_PENDING_APPROVAL !== $order->status ) {
			return false;
		}

		// Mark latest delivery as accepted.
		global $wpdb;
		$deliveries_table = $wpdb->prefix . 'wpss_deliveries';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$deliveries_table}
				SET status = 'accepted'
				WHERE order_id = %d AND status = 'pending'",
				$order_id
			)
		);

		// Complete order.
		$order_service = new OrderService();
		$order_service->update_status( $order_id, ServiceOrder::STATUS_COMPLETED );

		/**
		 * Fires when delivery is accepted.
		 *
		 * @param int $order_id Order ID.
		 */
		do_action( 'wpss_delivery_accepted', $order_id );

		return true;
	}

	/**
	 * Request revision.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $reason   Revision reason.
	 * @return bool
	 */
	public function request_revision( int $order_id, string $reason ): bool {
		$order = wpss_get_order( $order_id );

		if ( ! $order || ! $order->can_request_revision() ) {
			return false;
		}

		if ( ServiceOrder::STATUS_PENDING_APPROVAL !== $order->status ) {
			return false;
		}

		// Mark latest delivery as revision requested.
		global $wpdb;
		$deliveries_table = $wpdb->prefix . 'wpss_deliveries';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$deliveries_table}
				SET status = 'revision_requested', updated_at = %s
				WHERE order_id = %d AND status = 'pending'",
				current_time( 'mysql' ),
				$order_id
			)
		);

		// Add revision message to conversation.
		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->get_by_order( $order_id );

		if ( $conversation ) {
			$conversation_service->send_message(
				$conversation->id,
				$order->customer_id,
				$reason,
				array(),
				Message::TYPE_REVISION
			);
		}

		// Update order status.
		$order_service = new OrderService();
		$order_service->request_revision( $order_id, $reason );

		/**
		 * Fires when revision is requested.
		 *
		 * @param int    $order_id Order ID.
		 * @param string $reason   Revision reason.
		 */
		do_action( 'wpss_revision_requested', $order_id, $reason );

		return true;
	}

	/**
	 * Get deliveries for order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_order_deliveries( int $order_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_deliveries';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);
	}

	/**
	 * Process uploaded file.
	 *
	 * @param array $file     File data from $_FILES.
	 * @param int   $order_id Order ID for organization.
	 * @return array|null Processed file data.
	 */
	private function process_file( array $file, int $order_id ): ?array {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return null;
		}

		// Verify file type.
		$allowed_types = $this->get_allowed_file_types();
		$file_type     = wp_check_filetype( $file['name'] );

		if ( ! in_array( $file_type['ext'], $allowed_types, true ) ) {
			return null;
		}

		// Use WordPress media handling.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload_overrides = array(
			'test_form' => false,
		);

		$uploaded = wp_handle_upload( $file, $upload_overrides );

		if ( isset( $uploaded['error'] ) ) {
			wpss_log( 'File upload error: ' . $uploaded['error'], 'error' );
			return null;
		}

		// Create attachment.
		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => sanitize_file_name( $file['name'] ),
				'post_mime_type' => $uploaded['type'],
				'post_status'    => 'private',
				'post_content'   => '',
			),
			$uploaded['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return null;
		}

		// Generate attachment metadata.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Store order reference.
		update_post_meta( $attachment_id, '_wpss_order_id', $order_id );

		return array(
			'id'   => $attachment_id,
			'name' => $file['name'],
			'url'  => $uploaded['url'],
			'type' => $uploaded['type'],
			'size' => $file['size'],
		);
	}

	/**
	 * Get allowed file types for delivery.
	 *
	 * @return array
	 */
	private function get_allowed_file_types(): array {
		$types = array(
			'jpg',
			'jpeg',
			'png',
			'gif',
			'webp',
			'svg',
			'pdf',
			'doc',
			'docx',
			'xls',
			'xlsx',
			'ppt',
			'pptx',
			'zip',
			'rar',
			'7z',
			'mp3',
			'wav',
			'ogg',
			'mp4',
			'mov',
			'avi',
			'webm',
			'txt',
			'csv',
			'json',
			'xml',
			'psd',
			'ai',
			'eps',
			'sketch',
			'fig',
			'html',
			'css',
			'js',
			'php',
		);

		/**
		 * Filter allowed file types for delivery.
		 *
		 * @param array $types Allowed file extensions.
		 */
		return apply_filters( 'wpss_delivery_allowed_file_types', $types );
	}
}
