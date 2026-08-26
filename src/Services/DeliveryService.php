<?php
/**
 * Delivery Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

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
		$failed_files    = array();

		foreach ( $files as $file ) {
			$processed = $this->process_file( $file, $order_id );
			if ( $processed ) {
				$processed_files[] = $processed;
			} else {
				$failed_files[] = $file['name'] ?? 'unknown';
				wpss_log( "Failed to process delivery file for order {$order_id}: " . ( $file['name'] ?? 'unknown' ), 'error' );
			}
		}

		if ( ! empty( $failed_files ) ) {
			wpss_log(
				sprintf( 'Order %d delivery: %d of %d files failed to process', $order_id, count( $failed_files ), count( $files ) ),
				'warning'
			);
		}

		$delivery_data = array(
			'order_id'    => $order_id,
			'vendor_id'   => $order->vendor_id,
			'version'     => $version,
			'message'     => $message,
			'attachments' => $processed_files,
			'status'      => 'pending',
			'created_at'  => current_time( 'mysql' ),
		);

		/**
		 * Filter delivery data before saving to the database.
		 *
		 * Allows modification of the delivery message, files, and other data
		 * before the delivery record is inserted.
		 *
		 * @since 1.1.0
		 * @param array $delivery_data Delivery data including message and attachments.
		 * @param int   $order_id      Order ID.
		 */
		$delivery_data = apply_filters( 'wpss_pre_submit_delivery', $delivery_data, $order_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$deliveries_table,
			array(
				'order_id'    => $delivery_data['order_id'],
				'vendor_id'   => $delivery_data['vendor_id'],
				'version'     => $delivery_data['version'],
				'message'     => $delivery_data['message'],
				'attachments' => wp_json_encode( $delivery_data['attachments'] ),
				'status'      => $delivery_data['status'],
				'created_at'  => $delivery_data['created_at'],
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

		// Update order status - must succeed for delivery to be valid.
		$order_service  = new OrderService();
		$status_updated = $order_service->update_status( $order_id, ServiceOrder::STATUS_PENDING_APPROVAL );

		if ( ! $status_updated ) {
			wpss_log( "Delivery created (ID: {$delivery_id}) but status update to pending_approval failed for order {$order_id}.", 'error' );
		}

		/**
		 * Fires when a delivery is submitted.
		 *
		 * @param int $delivery_id Delivery ID.
		 * @param int $order_id    Order ID.
		 */
		do_action( 'wpss_delivery_submitted', $delivery_id, $order_id );

		return $status_updated;
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
		$order_service  = new OrderService();
		$status_updated = $order_service->update_status( $order_id, ServiceOrder::STATUS_COMPLETED );

		if ( ! $status_updated ) {
			wpss_log( "Delivery accepted but status update to completed failed for order {$order_id}.", 'error' );
			return false;
		}

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
				SET status = 'revision_requested'
				WHERE order_id = %d AND status = 'pending'",
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

		// Update order status - must succeed for revision to be valid.
		$order_service  = new OrderService();
		$status_updated = $order_service->request_revision( $order_id, $reason );

		if ( ! $status_updated ) {
			wpss_log( "Revision request failed: status update for order {$order_id} did not succeed.", 'error' );
			return false;
		}

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
		// Everything this used to do by hand - type check, wp_handle_upload(),
		// an attachment post, a stored public URL - now lives in
		// wpss_store_order_file(), which also pushes to the configured cloud
		// provider and keeps the file out of the web root. Deliveries were the
		// reason cloud storage existed and never received a single file
		// (Basecamp 10239805812).
		$allowed = $this->get_allowed_file_types();
		$checked = wp_check_filetype( (string) ( $file['name'] ?? '' ) );

		if ( empty( $checked['ext'] ) || ! in_array( $checked['ext'], $allowed, true ) ) {
			return null;
		}

		return wpss_store_order_file( $file, $order_id, 'delivery' );
	}

	/**
	 * Get allowed file types for delivery.
	 *
	 * @return array
	 */
	private function get_allowed_file_types(): array {
		// Security note: svg, html, css, and js are intentionally NOT allowed -
		// each can carry executable content (script tags, expressions, imports)
		// and is an XSS risk when served back to other users.
		$types = array(
			'jpg',
			'jpeg',
			'png',
			'gif',
			'webp',
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
		);

		/**
		 * Filter allowed file types for delivery.
		 *
		 * @param array $types Allowed file extensions.
		 */
		return apply_filters( 'wpss_delivery_allowed_file_types', $types );
	}
}
