<?php
/**
 * AJAX Handlers
 *
 * Handles all frontend AJAX requests.
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Core\RateLimiter;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\DeliveryService;
use WPSellServices\Services\ReviewService;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;
use WPSellServices\Services\RequirementsService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\EmailService;

/**
 * Handles frontend AJAX requests.
 *
 * @since 1.0.0
 */
class AjaxHandlers {

	/**
	 * Valid dashboard tabs for the main dashboard view.
	 *
	 * @var array<int, string>
	 */
	private const VALID_DASHBOARD_TABS = array( 'overview', 'orders', 'sales', 'services', 'requests', 'messages', 'earnings', 'profile', 'create', 'create-request' );

	/**
	 * Valid dashboard tabs for filtering, searching, and pagination.
	 *
	 * @var array<int, string>
	 */
	private const VALID_FILTERABLE_TABS = array( 'orders', 'sales', 'services', 'requests', 'messages', 'earnings' );

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	public function init(): void {
		// Order actions.
		add_action( 'wp_ajax_wpss_accept_order', array( $this, 'accept_order' ) );
		add_action( 'wp_ajax_wpss_decline_order', array( $this, 'decline_order' ) );
		add_action( 'wp_ajax_wpss_start_work', array( $this, 'start_work' ) );
		add_action( 'wp_ajax_wpss_deliver_order', array( $this, 'deliver_order' ) );
		add_action( 'wp_ajax_wpss_request_revision', array( $this, 'request_revision' ) );
		add_action( 'wp_ajax_wpss_accept_delivery', array( $this, 'accept_delivery' ) );
		add_action( 'wp_ajax_wpss_cancel_order', array( $this, 'cancel_order' ) );

		// Requirements.
		add_action( 'wp_ajax_wpss_submit_requirements', array( $this, 'submit_requirements' ) );

		// Messages.
		add_action( 'wp_ajax_wpss_send_message', array( $this, 'send_message' ) );
		add_action( 'wp_ajax_wpss_get_messages', array( $this, 'get_messages' ) );
		add_action( 'wp_ajax_wpss_get_new_messages', array( $this, 'get_new_messages' ) );
		add_action( 'wp_ajax_wpss_mark_messages_read', array( $this, 'mark_messages_read' ) );

		// Reviews.
		add_action( 'wp_ajax_wpss_submit_review', array( $this, 'submit_review' ) );
		add_action( 'wp_ajax_wpss_load_reviews', array( $this, 'load_reviews' ) );
		add_action( 'wp_ajax_nopriv_wpss_load_reviews', array( $this, 'load_reviews' ) );
		add_action( 'wp_ajax_wpss_mark_review_helpful', array( $this, 'mark_review_helpful' ) );
		add_action( 'wp_ajax_nopriv_wpss_mark_review_helpful', array( $this, 'mark_review_helpful' ) );

		// Disputes.
		add_action( 'wp_ajax_wpss_open_dispute', array( $this, 'open_dispute' ) );
		add_action( 'wp_ajax_wpss_add_dispute_evidence', array( $this, 'add_dispute_evidence' ) );

		// Buyer requests.
		add_action( 'wp_ajax_wpss_post_request', array( $this, 'post_request' ) );
		add_action( 'wp_ajax_wpss_submit_proposal', array( $this, 'submit_proposal' ) );
		add_action( 'wp_ajax_wpss_accept_proposal', array( $this, 'accept_proposal' ) );
		add_action( 'wp_ajax_wpss_reject_proposal', array( $this, 'reject_proposal' ) );
		add_action( 'wp_ajax_wpss_withdraw_proposal', array( $this, 'withdraw_proposal' ) );

		// User registration.
		add_action( 'wp_ajax_nopriv_wpss_register_user', array( $this, 'register_user' ) );

		// Service actions.
		add_action( 'wp_ajax_wpss_favorite_service', array( $this, 'favorite_service' ) );
		add_action( 'wp_ajax_wpss_unfavorite_service', array( $this, 'unfavorite_service' ) );
		add_action( 'wp_ajax_wpss_get_favorites', array( $this, 'get_favorites' ) );

		// File upload.
		add_action( 'wp_ajax_wpss_upload_file', array( $this, 'upload_file' ) );

		// Search.
		add_action( 'wp_ajax_wpss_live_search', array( $this, 'live_search' ) );
		add_action( 'wp_ajax_nopriv_wpss_live_search', array( $this, 'live_search' ) );

		// Contact vendor.
		add_action( 'wp_ajax_wpss_contact_vendor', array( $this, 'contact_vendor' ) );

		// Add to cart.
		add_action( 'wp_ajax_wpss_add_service_to_cart', array( $this, 'add_service_to_cart' ) );
		add_action( 'wp_ajax_nopriv_wpss_add_service_to_cart', array( $this, 'add_service_to_cart' ) );

		// Notifications.
		add_action( 'wp_ajax_wpss_get_notifications', array( $this, 'get_notifications' ) );
		add_action( 'wp_ajax_wpss_mark_notification_read', array( $this, 'mark_notification_read' ) );
		add_action( 'wp_ajax_wpss_mark_all_notifications_read', array( $this, 'mark_all_notifications_read' ) );

		// Checkout/Cart.
		add_action( 'wp_ajax_wpss_update_cart_item', array( $this, 'update_cart_item' ) );
		add_action( 'wp_ajax_wpss_remove_requirement_file', array( $this, 'remove_requirement_file' ) );
		add_action( 'wp_ajax_wpss_skip_requirements', array( $this, 'skip_requirements' ) );

		// Blocks/Services.
		add_action( 'wp_ajax_wpss_load_services', array( $this, 'load_services' ) );
		add_action( 'wp_ajax_nopriv_wpss_load_services', array( $this, 'load_services' ) );

		// Dashboard.
		add_action( 'wp_ajax_wpss_get_dashboard_tab', array( $this, 'get_dashboard_tab' ) );
		add_action( 'wp_ajax_wpss_get_dashboard_stats', array( $this, 'get_dashboard_stats' ) );
		add_action( 'wp_ajax_wpss_service_action', array( $this, 'service_action' ) );
		add_action( 'wp_ajax_wpss_order_action', array( $this, 'order_action' ) );
		add_action( 'wp_ajax_wpss_filter_dashboard', array( $this, 'filter_dashboard' ) );
		add_action( 'wp_ajax_wpss_bulk_action', array( $this, 'bulk_action' ) );
		add_action( 'wp_ajax_wpss_search_dashboard', array( $this, 'search_dashboard' ) );
		add_action( 'wp_ajax_wpss_paginate_dashboard', array( $this, 'paginate_dashboard' ) );
		add_action( 'wp_ajax_wpss_export_data', array( $this, 'export_data' ) );

		// Withdrawals.
		add_action( 'wp_ajax_wpss_cancel_withdrawal', array( $this, 'cancel_withdrawal' ) );
	}

	/**
	 * Accept order (vendor).
	 *
	 * @return void
	 */
	public function accept_order(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'order_action', get_current_user_id() ) ) {
			RateLimiter::send_error( 'order_action' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to accept this order.', 'wp-sell-services' ) ) );
		}

		$result = $order_service->update_status( $order_id, 'accepted' );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Order accepted successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to accept order.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Start working on order (vendor).
	 *
	 * @return void
	 */
	public function start_work(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'order_action', get_current_user_id() ) ) {
			RateLimiter::send_error( 'order_action' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to start work on this order.', 'wp-sell-services' ) ) );
		}

		// Check if order is in correct status to start work.
		$allowed_statuses = array( 'accepted', 'requirements_submitted' );
		if ( ! in_array( $order->status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Order cannot be started in its current status.', 'wp-sell-services' ) ) );
		}

		$result = $order_service->start_work( $order_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Work started! Delivery deadline has been set.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to start work on order.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Decline order (vendor).
	 *
	 * @return void
	 */
	public function decline_order(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$reason   = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to decline this order.', 'wp-sell-services' ) ) );
		}

		$result = $order_service->update_status( $order_id, 'rejected' );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Order declined.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to decline order.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Deliver order (vendor).
	 *
	 * @return void
	 */
	public function deliver_order(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'delivery', get_current_user_id() ) ) {
			RateLimiter::send_error( 'delivery' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$message  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$user_id  = get_current_user_id();

		// Process uploaded files from $_FILES.
		$files = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! empty( $_FILES['files'] ) && is_array( $_FILES['files']['name'] ) ) {
			$file_count = count( $_FILES['files']['name'] );
			for ( $i = 0; $i < $file_count; $i++ ) {
				if ( empty( $_FILES['files']['name'][ $i ] ) ) {
					continue;
				}
				$files[] = array(
					'name'     => sanitize_file_name( $_FILES['files']['name'][ $i ] ),
					'type'     => sanitize_mime_type( $_FILES['files']['type'][ $i ] ),
					'tmp_name' => $_FILES['files']['tmp_name'][ $i ], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					'error'    => (int) $_FILES['files']['error'][ $i ],
					'size'     => (int) $_FILES['files']['size'][ $i ],
				);
			}
		}

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to deliver this order.', 'wp-sell-services' ) ) );
		}

		$delivery_service = new DeliveryService();
		$result           = $delivery_service->submit( $order_id, $message, $files );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Delivery submitted successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to submit delivery.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Request revision (customer).
	 *
	 * @return void
	 */
	public function request_revision(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$reason   = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$user_id  = get_current_user_id();

		if ( ! $order_id || ! $reason ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a reason for revision.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->customer_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to request revision.', 'wp-sell-services' ) ) );
		}

		$delivery_service = new DeliveryService();
		$result           = $delivery_service->request_revision( $order_id, $reason );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Revision requested successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to request revision. Please try again.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Accept delivery (customer).
	 *
	 * @return void
	 */
	public function accept_delivery(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->customer_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to accept this delivery.', 'wp-sell-services' ) ) );
		}

		$delivery_service = new DeliveryService();
		$result           = $delivery_service->accept( $order_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Order completed successfully!', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to accept delivery.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Cancel order.
	 *
	 * @return void
	 */
	public function cancel_order(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$reason   = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		// Check if user is part of the order.
		if ( ! $order || ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to cancel this order.', 'wp-sell-services' ) ) );
		}

		$result = $order_service->cancel( $order_id, $user_id, $reason );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Order cancelled.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Submit requirements.
	 *
	 * Handles both legacy format (field_*) and new format (requirements[index]).
	 *
	 * @return void
	 */
	public function submit_requirements(): void {
		// Support both nonce names for backward compatibility.
		$nonce = sanitize_text_field( wp_unslash( $_POST['wpss_requirements_nonce'] ?? $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wpss_submit_requirements' ) && ! wp_verify_nonce( $nonce, 'wpss_requirements_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-sell-services' ) ) );
		}

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'requirements', get_current_user_id() ) ) {
			RateLimiter::send_error( 'requirements' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->customer_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to submit requirements.', 'wp-sell-services' ) ) );
		}

		// Get service requirements to map indices to questions.
		$service      = $order->get_service();
		$requirements = $service ? get_post_meta( $service->id, '_wpss_requirements', true ) : array();
		if ( ! is_array( $requirements ) ) {
			$requirements = array();
		}

		// Collect field data - support both formats.
		$field_data = array();

		// New format: requirements[index] => value.
		// Handles both numeric indices (custom requirements) and string keys (default/special fields).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified above, individual values sanitized in the loop below.
		$posted_requirements = isset( $_POST['requirements'] ) ? $_POST['requirements'] : array();
		if ( ! empty( $posted_requirements ) && is_array( $posted_requirements ) ) {
			foreach ( $posted_requirements as $index => $value ) {
				$sanitized_value = is_array( $value )
					? array_map( 'sanitize_textarea_field', array_map( 'wp_unslash', $value ) )
					: sanitize_textarea_field( wp_unslash( $value ) );

				if ( is_numeric( $index ) ) {
					// Numeric index: map to requirement definition by array position.
					$index = absint( $index );
					if ( isset( $requirements[ $index ] ) ) {
						$question                = $requirements[ $index ]['question'] ?? "field_{$index}";
						$field_data[ $question ] = $sanitized_value;
					}
				} else {
					// String key (e.g. 'description', 'additional_notes'): use directly.
					$field_data[ sanitize_key( $index ) ] = $sanitized_value;
				}
			}
		}

		// Legacy format: field_* keys.
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, 'field_' ) === 0 ) {
				$field_id                = str_replace( 'field_', '', $key );
				$field_data[ $field_id ] = is_array( $value )
					? array_map( 'sanitize_text_field', $value )
					: sanitize_text_field( wp_unslash( $value ) );
			}
		}

		// Handle file uploads.
		$files = array();
		if ( ! empty( $_FILES ) ) {
			foreach ( $_FILES as $key => $file ) {
				// Support requirements[index] format.
				if ( strpos( $key, 'requirements' ) === 0 ) {
					preg_match( '/requirements\[(\d+)\]/', $key, $matches );
					if ( ! empty( $matches[1] ) ) {
						$index = absint( $matches[1] );
						if ( isset( $requirements[ $index ] ) ) {
							$question           = $requirements[ $index ]['question'] ?? "field_{$index}";
							$files[ $question ] = $file;
						}
					}
				} else {
					$files[ $key ] = $file;
				}
			}
		}

		$requirements_service = new RequirementsService();
		$result               = $requirements_service->submit( $order_id, $field_data, $files );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'  => __( 'Requirements submitted successfully. The vendor will start working on your order.', 'wp-sell-services' ),
					'redirect' => wpss_get_order_url( $order_id ),
				)
			);
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Send message.
	 *
	 * Accepts order_id and message from the frontend conversation template.
	 *
	 * @return void
	 */
	public function send_message(): void {
		check_ajax_referer( 'wpss_send_message', 'nonce' );

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'message', get_current_user_id() ) ) {
			RateLimiter::send_error( 'message' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$content  = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		if ( ! $content ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message.', 'wp-sell-services' ) ) );
		}

		// Get order and verify access.
		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		// Check if user is part of this order.
		$is_vendor   = (int) $order->vendor_id === $user_id;
		$is_customer = (int) $order->customer_id === $user_id;

		if ( ! $is_vendor && ! $is_customer ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to send messages in this order.', 'wp-sell-services' ) ) );
		}

		// Handle file attachments.
		$attachments_data = array();
		$skipped_files    = array();
		if ( ! empty( $_FILES['attachments'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			// Allowed file types for conversation attachments.
			$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt' );
			$allowed_mimes = array(
				'image/jpeg',
				'image/png',
				'image/gif',
				'image/webp',
				'application/pdf',
				'application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/zip',
				'text/plain',
			);
			$max_size      = 10 * 1024 * 1024; // 10MB per file.

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$files = $_FILES['attachments'];

			// Handle multiple files.
			if ( is_array( $files['name'] ) ) {
				$file_count = count( $files['name'] );
				for ( $i = 0; $i < $file_count; $i++ ) {
					if ( empty( $files['name'][ $i ] ) ) {
						continue;
					}

					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);

					$file_name = sanitize_file_name( $file['name'] );

					// Validate file extension.
					$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
					if ( ! in_array( $ext, $allowed_types, true ) ) {
						$skipped_files[] = $file_name . ': ' . __( 'unsupported file type', 'wp-sell-services' );
						continue;
					}

					// Validate MIME type to prevent extension spoofing.
					$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
					$mime_type = $file_info['type'] ?? '';
					if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
						$skipped_files[] = $file_name . ': ' . __( 'invalid MIME type', 'wp-sell-services' );
						continue;
					}

					// Validate file size.
					if ( $file['size'] > $max_size ) {
						$skipped_files[] = $file_name . ': ' . __( 'file too large (max 10MB)', 'wp-sell-services' );
						continue;
					}

					$_FILES['upload_file'] = $file;
					$attachment_id         = media_handle_upload( 'upload_file', 0 );

					if ( ! is_wp_error( $attachment_id ) ) {
						$attachments_data[] = array(
							'id'   => $attachment_id,
							'url'  => wp_get_attachment_url( $attachment_id ),
							'name' => $files['name'][ $i ],
							'type' => $mime_type, // Use server-verified MIME type, not client-provided.
						);
					} else {
						$skipped_files[] = $file_name . ': ' . $attachment_id->get_error_message();
					}
				}
			}
		}

		// Get or create conversation for this order.
		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->get_by_order( $order_id );

		if ( ! $conversation ) {
			$conversation = $conversation_service->create_for_order( $order_id );
		}

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create conversation.', 'wp-sell-services' ) ) );
		}

		// Send the message using ConversationService.
		$message = $conversation_service->send_message(
			$conversation->id,
			$user_id,
			$content,
			$attachments_data
		);

		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send message.', 'wp-sell-services' ) ) );
		}

		$message_id = $message->id;
		$user       = get_userdata( $user_id );

		// Generate HTML for the new message.
		ob_start();
		?>
		<div class="wpss-messaging__message wpss-messaging__message--sent" data-message-id="<?php echo esc_attr( $message_id ); ?>">
			<div class="wpss-messaging__message-content">
				<div class="wpss-messaging__bubble">
					<div class="wpss-messaging__text">
						<?php echo wp_kses_post( nl2br( $content ) ); ?>
					</div>
					<?php if ( ! empty( $attachments_data ) ) : ?>
						<div class="wpss-messaging__attachments">
							<?php foreach ( $attachments_data as $attachment ) : ?>
								<?php $is_image = strpos( $attachment['type'], 'image/' ) === 0; ?>
								<?php if ( $is_image ) : ?>
									<a href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" class="wpss-messaging__attachment-image">
										<img src="<?php echo esc_url( $attachment['url'] ); ?>" alt="<?php echo esc_attr( $attachment['name'] ); ?>">
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" class="wpss-messaging__attachment-file">
										<span class="wpss-messaging__attachment-icon">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
												<polyline points="14 2 14 8 20 8"/>
											</svg>
										</span>
										<span class="wpss-messaging__attachment-info">
											<span class="wpss-messaging__attachment-name"><?php echo esc_html( $attachment['name'] ); ?></span>
										</span>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( wp_date( get_option( 'time_format' ) ) ); ?>
				</span>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		$response = array(
			'message'    => __( 'Message sent.', 'wp-sell-services' ),
			'message_id' => $message_id,
			'html'       => $html,
		);

		if ( ! empty( $skipped_files ) ) {
			$response['warnings'] = $skipped_files;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Get messages.
	 *
	 * @return void
	 */
	public function get_messages(): void {
		check_ajax_referer( 'wpss_message_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$after_id        = absint( $_POST['after_id'] ?? 0 );
		$user_id         = get_current_user_id();

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'wp-sell-services' ) ) );
		}

		$conversation_service = new ConversationService();

		if ( ! $conversation_service->user_can_access( $conversation_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this conversation.', 'wp-sell-services' ) ) );
		}

		$messages = $conversation_service->get_messages(
			$conversation_id,
			array(
				'after_id' => $after_id,
				'limit'    => 50,
			)
		);

		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * Get new messages for polling.
	 *
	 * Used by the conversation template to poll for new messages.
	 *
	 * @return void
	 */
	public function get_new_messages(): void {
		check_ajax_referer( 'wpss_frontend_nonce', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$last_id  = absint( $_POST['last_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		// Verify user has access to this order.
		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		$is_vendor   = (int) $order->vendor_id === $user_id;
		$is_customer = (int) $order->customer_id === $user_id;

		if ( ! $is_vendor && ! $is_customer ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'wp-sell-services' ) ) );
		}

		global $wpdb;

		// First get the conversation for this order.
		$conversations_table = $wpdb->prefix . 'wpss_conversations';
		$messages_table      = $wpdb->prefix . 'wpss_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$conversations_table} WHERE order_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		if ( ! $conversation ) {
			wp_send_json_success( array( 'messages' => array() ) );
		}

		// Get new messages after last_id that weren't sent by current user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name as sender_name
				FROM {$messages_table} m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.conversation_id = %d AND m.id > %d AND m.sender_id != %d
				ORDER BY m.created_at ASC",
				$conversation->id,
				$last_id,
				$user_id
			)
		);

		if ( empty( $messages ) ) {
			wp_send_json_success( array( 'messages' => array() ) );
		}

		// Mark new messages as read by updating read_by JSON array.
		$message_ids = wp_list_pluck( $messages, 'id' );
		foreach ( $message_ids as $message_id ) {
			// Get current read_by value.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$read_by_json = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT read_by FROM {$messages_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$message_id
				)
			);

			$read_by = $read_by_json ? json_decode( $read_by_json, true ) : array();
			if ( ! is_array( $read_by ) ) {
				$read_by = array();
			}

			// Add current user if not already in list.
			if ( ! in_array( $user_id, $read_by, true ) ) {
				$read_by[] = $user_id;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$messages_table,
					array( 'read_by' => wp_json_encode( $read_by ) ),
					array( 'id' => $message_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		// Build HTML for each message.
		$result = array();
		foreach ( $messages as $msg ) {
			$is_system = 'system' === $msg->type;

			ob_start();
			if ( $is_system ) :
				?>
				<div class="wpss-messaging__system">
					<span class="wpss-messaging__system-text">
						<?php echo wp_kses_post( $msg->content ); ?>
						<span class="wpss-messaging__message-time">
							<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $msg->created_at ) ) ); ?>
						</span>
					</span>
				</div>
				<?php
			else :
				?>
				<div class="wpss-messaging__message" data-message-id="<?php echo esc_attr( $msg->id ); ?>">
					<div class="wpss-messaging__message-avatar">
						<?php echo get_avatar( $msg->sender_id, 32 ); ?>
					</div>
					<div class="wpss-messaging__message-content">
						<div class="wpss-messaging__bubble">
							<span class="wpss-messaging__sender"><?php echo esc_html( $msg->sender_name ); ?></span>
							<div class="wpss-messaging__text">
								<?php echo wp_kses_post( nl2br( $msg->content ) ); ?>
							</div>
							<?php if ( ! empty( $msg->attachments ) ) : ?>
								<?php $attachments = json_decode( $msg->attachments, true ); ?>
								<?php if ( ! empty( $attachments ) ) : ?>
									<div class="wpss-messaging__attachments">
										<?php foreach ( $attachments as $attachment ) : ?>
											<?php
											$file_url  = $attachment['url'] ?? '';
											$file_name = $attachment['name'] ?? basename( $file_url );
											$file_type = $attachment['type'] ?? '';
											$is_image  = strpos( $file_type, 'image/' ) === 0;
											?>
											<?php if ( $is_image && $file_url ) : ?>
												<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-image">
													<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>">
												</a>
											<?php else : ?>
												<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-file">
													<span class="wpss-messaging__attachment-icon">
														<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
															<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
															<polyline points="14 2 14 8 20 8"/>
														</svg>
													</span>
													<span class="wpss-messaging__attachment-info">
														<span class="wpss-messaging__attachment-name"><?php echo esc_html( $file_name ); ?></span>
													</span>
												</a>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php endif; ?>
						</div>
						<span class="wpss-messaging__message-time">
							<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $msg->created_at ) ) ); ?>
						</span>
					</div>
				</div>
				<?php
			endif;
			$html = ob_get_clean();

			$result[] = array(
				'id'   => (int) $msg->id,
				'html' => $html,
			);
		}

		wp_send_json_success( array( 'messages' => $result ) );
	}

	/**
	 * Mark messages as read.
	 *
	 * @return void
	 */
	public function mark_messages_read(): void {
		check_ajax_referer( 'wpss_message_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$user_id         = get_current_user_id();

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation.', 'wp-sell-services' ) ) );
		}

		$conversation_service = new ConversationService();
		$result               = $conversation_service->mark_as_read( $conversation_id, $user_id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to mark messages as read. Please try again.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Submit review.
	 *
	 * @return void
	 */
	public function submit_review(): void {
		check_ajax_referer( 'wpss_submit_review', 'wpss_review_nonce' );

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'review', get_current_user_id() ) ) {
			RateLimiter::send_error( 'review' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$rating   = absint( $_POST['rating'] ?? 0 );
		$comment  = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		$user_id  = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to submit a review.', 'wp-sell-services' ) ) );
		}

		if ( ! $order_id || ! $rating ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a rating.', 'wp-sell-services' ) ) );
		}

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rating.', 'wp-sell-services' ) ) );
		}

		$review_service = new ReviewService();

		// Check if user can review this order with detailed reason.
		$can_review = $review_service->can_review( $order_id, $user_id );
		if ( ! $can_review['can_review'] ) {
			wp_send_json_error( array( 'message' => $can_review['reason'] ) );
		}

		$review = $review_service->create(
			$order_id,
			$user_id,
			array(
				'rating'  => $rating,
				'content' => $comment,
			)
		);

		if ( $review ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Review submitted successfully.', 'wp-sell-services' ),
					'review_id' => $review->id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to submit review. Please try again.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Load more reviews for a service (AJAX pagination).
	 *
	 * @return void
	 */
	public function load_reviews(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$page       = absint( $_POST['page'] ?? 1 );
		$per_page   = 10;

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		$review_service = new ReviewService();
		$reviews        = $review_service->get_service_reviews(
			$service_id,
			array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);

		// Get total count for has_more check.
		$rating_count = (int) get_post_meta( $service_id, '_wpss_rating_count', true );
		$total_loaded = $page * $per_page;
		$has_more     = $total_loaded < $rating_count;

		// Generate HTML for reviews.
		ob_start();
		foreach ( $reviews as $review ) {
			$reviewer = get_userdata( $review->reviewer_id );
			?>
			<div class="wpss-review">
				<div class="wpss-review-header">
					<img src="<?php echo esc_url( get_avatar_url( $review->reviewer_id, array( 'size' => 48 ) ) ); ?>"
						alt="<?php echo esc_attr( $reviewer ? $reviewer->display_name : '' ); ?>"
						class="wpss-review-avatar">
					<div class="wpss-review-info">
						<strong class="wpss-review-author">
							<?php echo esc_html( $reviewer ? $reviewer->display_name : __( 'Anonymous', 'wp-sell-services' ) ); ?>
						</strong>
						<div class="wpss-review-rating">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<span class="wpss-star <?php echo $i <= $review->rating ? 'filled' : ''; ?>">★</span>
							<?php endfor; ?>
						</div>
					</div>
					<span class="wpss-review-date">
						<?php echo esc_html( wpss_time_ago( $review->created_at->format( 'Y-m-d H:i:s' ) ) ); ?>
					</span>
				</div>

				<div class="wpss-review-content">
					<?php echo wp_kses_post( wpautop( $review->content ) ); ?>
				</div>

				<?php if ( ! empty( $review->response ) ) : ?>
					<div class="wpss-review-reply">
						<div class="wpss-reply-header">
							<strong><?php esc_html_e( 'Seller Response:', 'wp-sell-services' ); ?></strong>
							<?php if ( $review->response_at ) : ?>
								<span class="wpss-reply-date">
									<?php echo esc_html( wpss_time_ago( $review->response_at->format( 'Y-m-d H:i:s' ) ) ); ?>
								</span>
							<?php endif; ?>
						</div>
						<?php echo wp_kses_post( wpautop( $review->response ) ); ?>
					</div>
				<?php endif; ?>

				<div class="wpss-review-actions">
					<button type="button" class="wpss-review-helpful-btn" data-review="<?php echo esc_attr( $review->id ); ?>">
						<span class="wpss-helpful-icon">👍</span>
						<span class="wpss-helpful-text"><?php esc_html_e( 'Helpful', 'wp-sell-services' ); ?></span>
						<?php if ( $review->helpful_count > 0 ) : ?>
							<span class="wpss-helpful-count">(<?php echo esc_html( $review->helpful_count ); ?>)</span>
						<?php endif; ?>
					</button>
				</div>
			</div>
			<?php
		}
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'     => $html,
				'has_more' => $has_more,
			)
		);
	}

	/**
	 * Mark a review as helpful.
	 *
	 * Uses atomic database operations to prevent race conditions where
	 * concurrent requests could both pass the duplicate vote check.
	 *
	 * @return void
	 */
	public function mark_review_helpful(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		// Rate limiting (uses IP for guests since nopriv allowed).
		$current_user_id = get_current_user_id();
		$rate_limit_user = $current_user_id > 0 ? $current_user_id : null;
		if ( RateLimiter::check_and_track( 'helpful_vote', $rate_limit_user ) ) {
			RateLimiter::send_error( 'helpful_vote' );
		}

		$review_id = absint( $_POST['review_id'] ?? 0 );

		if ( ! $review_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid review.', 'wp-sell-services' ) ) );
		}

		global $wpdb;
		$reviews_table = $wpdb->prefix . 'wpss_reviews';

		// Verify review exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$reviews_table} WHERE id = %d",
				$review_id
			)
		);

		if ( ! $review_exists ) {
			wp_send_json_error( array( 'message' => __( 'Review not found.', 'wp-sell-services' ) ) );
		}

		// Build unique vote identifier.
		$user_id    = get_current_user_id();
		$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$vote_key   = '_wpss_vote_' . $review_id . '_' . ( $user_id ? 'u' . $user_id : 'ip' . md5( $ip_address ) );

		// Use atomic INSERT IGNORE to prevent race condition.
		// If two concurrent requests try to insert the same key, only one will succeed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$vote_key,
				time() + WEEK_IN_SECONDS,
				'no'
			)
		);

		// Check if our insert succeeded (rows_affected = 1) or row already existed (rows_affected = 0).
		if ( 0 === $wpdb->rows_affected ) {
			wp_send_json_error( array( 'message' => __( 'You have already marked this review as helpful.', 'wp-sell-services' ) ) );
		}

		// Increment helpful count atomically.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$reviews_table} SET helpful_count = helpful_count + 1 WHERE id = %d",
				$review_id
			)
		);

		// Get updated count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT helpful_count FROM {$reviews_table} WHERE id = %d",
				$review_id
			)
		);

		wp_send_json_success(
			array(
				'count'   => $new_count,
				'message' => __( 'Thanks for your feedback!', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * Open dispute for order.
	 *
	 * @return void
	 */
	public function open_dispute(): void {
		check_ajax_referer( 'wpss_open_dispute', 'wpss_dispute_nonce' );

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'dispute', get_current_user_id() ) ) {
			RateLimiter::send_error( 'dispute' );
		}

		$order_id    = absint( $_POST['order_id'] ?? 0 );
		$reason      = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$user_id     = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to open a dispute.', 'wp-sell-services' ) ) );
		}

		if ( ! $order_id || ! $reason || ! $description ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'wp-sell-services' ) ) );
		}

		$dispute_service = new DisputeService();
		$dispute_id      = $dispute_service->open( $order_id, $user_id, $reason, $description );

		if ( $dispute_id ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Dispute opened successfully. Our team will review your case.', 'wp-sell-services' ),
					'dispute_id' => $dispute_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to open dispute. A dispute may already exist for this order.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Add evidence to a dispute.
	 *
	 * @return void
	 */
	public function add_dispute_evidence(): void {
		check_ajax_referer( 'wpss_add_evidence', 'nonce' );

		$dispute_id  = absint( $_POST['dispute_id'] ?? 0 );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$user_id     = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to add evidence.', 'wp-sell-services' ) ) );
		}

		if ( ! $dispute_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid dispute.', 'wp-sell-services' ) ) );
		}

		$dispute_service = new DisputeService();
		$dispute         = $dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			wp_send_json_error( array( 'message' => __( 'Dispute not found.', 'wp-sell-services' ) ) );
		}

		// Verify user can add evidence (is part of the order).
		$order_repo = new \WPSellServices\Database\Repositories\OrderRepository();
		$order      = $order_repo->find( $dispute->order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		$is_customer = (int) $order->customer_id === $user_id;
		$is_vendor   = (int) $order->vendor_id === $user_id;
		$is_admin    = current_user_can( 'manage_options' );

		if ( ! $is_customer && ! $is_vendor && ! $is_admin ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to add evidence to this dispute.', 'wp-sell-services' ) ) );
		}

		// Handle file upload if present.
		$evidence_type    = 'text';
		$evidence_content = $description;

		if ( ! empty( $_FILES['evidence_file'] ) && ! empty( $_FILES['evidence_file']['name'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$file = $_FILES['evidence_file'];

			// Verify file upload.
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$upload_overrides = array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'gif'      => 'image/gif',
					'pdf'      => 'application/pdf',
					'doc'      => 'application/msword',
					'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'zip'      => 'application/zip',
					'txt'      => 'text/plain',
				),
			);

			$uploaded = wp_handle_upload( $file, $upload_overrides );

			if ( isset( $uploaded['error'] ) ) {
				wp_send_json_error( array( 'message' => $uploaded['error'] ) );
			}

			$evidence_content = $uploaded['url'];
			$file_type        = wp_check_filetype( $uploaded['file'] );

			if ( strpos( $file_type['type'], 'image/' ) === 0 ) {
				$evidence_type = 'image';
			} else {
				$evidence_type = 'file';
			}
		}

		// Must have either description or file.
		if ( empty( $description ) && $evidence_type === 'text' ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a message or attach a file.', 'wp-sell-services' ) ) );
		}

		// Add the evidence.
		$evidence_id = $dispute_service->add_evidence(
			$dispute_id,
			$user_id,
			$evidence_type,
			$evidence_content,
			$evidence_type !== 'text' ? $description : ''
		);

		if ( ! $evidence_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to add evidence.', 'wp-sell-services' ) ) );
		}

		// Generate HTML for the new evidence item.
		$evidence_user = get_userdata( $user_id );
		$is_own        = true;

		ob_start();
		?>
		<div class="wpss-evidence-item wpss-evidence-own">
			<div class="wpss-evidence-bubble">
				<div class="wpss-evidence-content">
					<?php if ( ! empty( $description ) ) : ?>
						<div class="wpss-evidence-text">
							<?php echo wp_kses_post( nl2br( $description ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $evidence_type === 'image' && ! empty( $evidence_content ) ) : ?>
						<div class="wpss-evidence-image">
							<a href="<?php echo esc_url( $evidence_content ); ?>" target="_blank">
								<img src="<?php echo esc_url( $evidence_content ); ?>" alt="<?php esc_attr_e( 'Evidence image', 'wp-sell-services' ); ?>">
							</a>
						</div>
					<?php elseif ( $evidence_type === 'file' && ! empty( $evidence_content ) ) : ?>
						<div class="wpss-evidence-file">
							<a href="<?php echo esc_url( $evidence_content ); ?>" target="_blank" class="wpss-file-link">
								<span class="dashicons dashicons-media-default"></span>
								<span><?php echo esc_html( basename( $evidence_content ) ); ?></span>
							</a>
						</div>
					<?php endif; ?>
				</div>
				<span class="wpss-evidence-time">
					<?php echo esc_html( wp_date( get_option( 'time_format' ), time() ) ); ?>
				</span>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'message'     => __( 'Evidence added successfully.', 'wp-sell-services' ),
				'evidence_id' => $evidence_id,
				'html'        => $html,
			)
		);
	}

	/**
	 * Post buyer request.
	 *
	 * @return void
	 */
	public function post_request(): void {
		check_ajax_referer( 'wpss_post_request', 'wpss_request_nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to post a request.', 'wp-sell-services' ) ) );
		}

		$deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );

		$data = array(
			'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'category_id' => absint( $_POST['category'] ?? 0 ),
			'budget_min'  => floatval( $_POST['budget_min'] ?? 0 ),
			'budget_max'  => floatval( $_POST['budget_max'] ?? 0 ),
		);

		// Calculate delivery_days and expires_at from the deadline date.
		if ( $deadline ) {
			$deadline_timestamp = strtotime( $deadline );
			if ( $deadline_timestamp && $deadline_timestamp > time() ) {
				$days_until_deadline   = max( 1, (int) ceil( ( $deadline_timestamp - time() ) / DAY_IN_SECONDS ) );
				$data['delivery_days'] = $days_until_deadline;
				$data['expires_at']    = gmdate( 'Y-m-d H:i:s', $deadline_timestamp );
			}
		}

		if ( ! $data['title'] || ! $data['description'] ) {
			wp_send_json_error( array( 'message' => __( 'Title and description are required.', 'wp-sell-services' ) ) );
		}

		$request_service = new BuyerRequestService();
		$request_id      = $request_service->create( $data );

		if ( $request_id ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Request posted successfully.', 'wp-sell-services' ),
					'request_id' => $request_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to create request.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Submit proposal.
	 *
	 * @return void
	 */
	public function submit_proposal(): void {
		check_ajax_referer( 'wpss_submit_proposal', 'wpss_proposal_nonce' );

		$request_id    = absint( $_POST['request_id'] ?? 0 );
		$vendor_id     = get_current_user_id();
		$description   = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$price         = floatval( $_POST['price'] ?? 0 );
		$delivery_days = absint( $_POST['delivery_days'] ?? 0 );

		if ( ! $request_id || ! $description || ! $price || ! $delivery_days ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'wp-sell-services' ) ) );
		}

		// Check if user is a vendor.
		if ( ! wpss_is_vendor( $vendor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be a vendor to submit proposals.', 'wp-sell-services' ) ) );
		}

		$proposal_service = new ProposalService();
		$proposal_id      = $proposal_service->submit(
			$request_id,
			$vendor_id,
			array(
				'description'   => $description,
				'price'         => $price,
				'delivery_days' => $delivery_days,
			)
		);

		if ( $proposal_id ) {
			wp_send_json_success(
				array(
					'message'     => __( 'Proposal submitted successfully.', 'wp-sell-services' ),
					'proposal_id' => $proposal_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to submit proposal. You may have already submitted a proposal for this request.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Accept proposal.
	 *
	 * @return void
	 */
	public function accept_proposal(): void {
		check_ajax_referer( 'wpss_proposal_action', 'nonce' );

		$proposal_id = absint( $_POST['proposal_id'] ?? 0 );
		$user_id     = get_current_user_id();

		if ( ! $proposal_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid proposal.', 'wp-sell-services' ) ) );
		}

		// Get proposal to find the request_id.
		$proposal_service = new ProposalService();
		$proposal         = $proposal_service->get( $proposal_id );

		if ( ! $proposal ) {
			wp_send_json_error( array( 'message' => __( 'Proposal not found.', 'wp-sell-services' ) ) );
		}

		$request_id = $proposal->request_id;

		// Check if user owns the request.
		$request = get_post( $request_id );
		if ( ! $request || (int) $request->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to accept this proposal.', 'wp-sell-services' ) ) );
		}

		$request_service = new BuyerRequestService();
		$result          = $request_service->convert_to_order( (int) $request_id, (int) $proposal_id );

		if ( $result['success'] && ! empty( $result['order_id'] ) ) {
			wp_send_json_success(
				array(
					'message'  => __( 'Proposal accepted. Order created!', 'wp-sell-services' ),
					'order_id' => $result['order_id'],
					'redirect' => home_url( '/my-account/service-orders/' . $result['order_id'] . '/' ),
				)
			);
		} else {
			if ( ! isset( $result['message'] ) ) {
				$result['message'] = __( 'Failed to create order. Please try again.', 'wp-sell-services' );
			}
			wp_send_json_error( $result );
		}
	}

	/**
	 * Reject proposal (buyer).
	 *
	 * @return void
	 */
	public function reject_proposal(): void {
		check_ajax_referer( 'wpss_proposal_action', 'nonce' );

		$proposal_id = absint( $_POST['proposal_id'] ?? 0 );
		$reason      = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$user_id     = get_current_user_id();

		if ( ! $proposal_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid proposal.', 'wp-sell-services' ) ) );
		}

		$proposal_service = new ProposalService();
		$result           = $proposal_service->reject( $proposal_id, $user_id, $reason );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Proposal declined.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to decline proposal.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Withdraw proposal (vendor).
	 *
	 * @return void
	 */
	public function withdraw_proposal(): void {
		check_ajax_referer( 'wpss_proposal_action', 'nonce' );

		$proposal_id = absint( $_POST['proposal_id'] ?? 0 );
		$user_id     = get_current_user_id();

		if ( ! $proposal_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid proposal.', 'wp-sell-services' ) ) );
		}

		$proposal_service = new ProposalService();
		$result           = $proposal_service->withdraw( $proposal_id, $user_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Proposal withdrawn.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to withdraw proposal.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Register user.
	 *
	 * @return void
	 */
	public function register_user(): void {
		check_ajax_referer( 'wpss_register', 'wpss_register_nonce' );

		if ( ! get_option( 'users_can_register' ) ) {
			wp_send_json_error( array( 'message' => __( 'Registration is disabled.', 'wp-sell-services' ) ) );
		}

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password = $_POST['password'] ?? '';

		if ( ! $username || ! $email || ! $password ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'wp-sell-services' ) ) );
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'wp-sell-services' ) ) );
		}

		if ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'Username already exists.', 'wp-sell-services' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email already exists.', 'wp-sell-services' ) ) );
		}

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		// Send notification email.
		wp_new_user_notification( $user_id, null, 'user' );

		// Auto-login.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_send_json_success(
			array(
				'message'  => __( 'Registration successful!', 'wp-sell-services' ),
				'redirect' => home_url(),
			)
		);
	}

	/**
	 * Favorite service.
	 *
	 * @return void
	 */
	public function favorite_service(): void {
		check_ajax_referer( 'wpss_favorite_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$favorites_raw = get_user_meta( $user_id, '_wpss_favorite_services', true );
		$favorites     = $favorites_raw ? $favorites_raw : array();

		if ( ! in_array( $service_id, $favorites, true ) ) {
			$favorites[] = $service_id;
			update_user_meta( $user_id, '_wpss_favorite_services', $favorites );
		}

		wp_send_json_success( array( 'message' => __( 'Added to favorites.', 'wp-sell-services' ) ) );
	}

	/**
	 * Unfavorite service.
	 *
	 * @return void
	 */
	public function unfavorite_service(): void {
		check_ajax_referer( 'wpss_favorite_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$favorites_raw = get_user_meta( $user_id, '_wpss_favorite_services', true );
		$favorites     = $favorites_raw ? $favorites_raw : array();
		$favorites     = array_diff( $favorites, array( $service_id ) );

		update_user_meta( $user_id, '_wpss_favorite_services', array_values( $favorites ) );

		wp_send_json_success( array( 'message' => __( 'Removed from favorites.', 'wp-sell-services' ) ) );
	}

	/**
	 * Get favorites.
	 *
	 * @return void
	 */
	public function get_favorites(): void {
		check_ajax_referer( 'wpss_favorite_nonce', 'nonce' );

		$user_id       = get_current_user_id();
		$favorites_raw = get_user_meta( $user_id, '_wpss_favorite_services', true );
		$favorites     = $favorites_raw ? $favorites_raw : array();

		wp_send_json_success( array( 'favorites' => $favorites ) );
	}

	/**
	 * Upload file.
	 *
	 * @return void
	 */
	public function upload_file(): void {
		check_ajax_referer( 'wpss_upload_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to upload files.', 'wp-sell-services' ) ) );
		}

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'file_upload', get_current_user_id() ) ) {
			RateLimiter::send_error( 'file_upload' );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'wp-sell-services' ) ) );
		}

		$file = $_FILES['file'];

		// Check file size.
		$max_size = (int) get_option( 'wpss_max_file_size', 10 ) * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
					/* translators: %s: max file size */
						__( 'File size exceeds maximum allowed (%s MB).', 'wp-sell-services' ),
						get_option( 'wpss_max_file_size', 10 )
					),
				)
			);
		}

		// Verify MIME type matches extension (prevent extension spoofing).
		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		if ( ! $filetype['ext'] || ! $filetype['type'] ) {
			wp_send_json_error( array( 'message' => __( 'File type could not be verified.', 'wp-sell-services' ) ) );
		}

		// Check file type against allowed list.
		$allowed_types = explode( ',', get_option( 'wpss_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,zip' ) );
		$ext           = strtolower( $filetype['ext'] );

		if ( ! in_array( $ext, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'File type not allowed.', 'wp-sell-services' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'filename'      => basename( get_attached_file( $attachment_id ) ),
			)
		);
	}

	/**
	 * Live search.
	 *
	 * @return void
	 */
	public function live_search(): void {
		// Verify nonce for logged-in users, skip for guests (public search).
		if ( is_user_logged_in() ) {
			check_ajax_referer( 'wpss_search_nonce', 'nonce' );
		}

		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );

		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// Only show approved services in search results.
		$services = new \WP_Query(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'publish',
				's'              => $query,
				'posts_per_page' => 5,
				'meta_query'     => array(
					array(
						'key'     => '_wpss_moderation_status',
						'value'   => 'approved',
						'compare' => '=',
					),
				),
			)
		);

		$results = array();
		foreach ( $services->posts as $post ) {
			$thumb_url = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
			$results[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'url'       => get_permalink( $post->ID ),
				'thumbnail' => $thumb_url ? $thumb_url : '',
				'price'     => get_post_meta( $post->ID, '_wpss_starting_price', true ),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Contact vendor.
	 *
	 * Allows logged-in users to send a message to a service vendor.
	 *
	 * @return void
	 */
	public function contact_vendor(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to contact vendors.', 'wp-sell-services' ) ) );
		}

		// Rate limiting.
		if ( RateLimiter::check_and_track( 'contact', $user_id ) ) {
			RateLimiter::send_error( 'contact' );
		}

		$vendor_id  = absint( $_POST['vendor_id'] ?? 0 );
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid vendor.', 'wp-sell-services' ) ) );
		}

		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message.', 'wp-sell-services' ) ) );
		}

		// Prevent contacting yourself.
		if ( $user_id === $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'You cannot contact yourself.', 'wp-sell-services' ) ) );
		}

		// Verify vendor exists.
		$vendor = get_userdata( $vendor_id );
		if ( ! $vendor ) {
			wp_send_json_error( array( 'message' => __( 'Vendor not found.', 'wp-sell-services' ) ) );
		}

		// Get service title for context.
		$service_title = '';
		if ( $service_id ) {
			$service = get_post( $service_id );
			if ( $service && 'wpss_service' === $service->post_type ) {
				$service_title = $service->post_title;
			}
		}

		// Handle file attachments.
		$attachments = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File uploads validated below.
		if ( ! empty( $_FILES['attachments'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			// Allowed file types for contact attachments.
			$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt' );
			$allowed_mimes = array(
				'image/jpeg',
				'image/png',
				'image/gif',
				'image/webp',
				'application/pdf',
				'application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/zip',
				'text/plain',
			);
			$max_size      = 10 * 1024 * 1024; // 10MB per file.

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$files = $_FILES['attachments'];

			// Handle multiple files.
			if ( is_array( $files['name'] ) ) {
				$file_count = count( $files['name'] );
				$max_files  = 5;
				$limit      = min( $file_count, $max_files );

				for ( $i = 0; $i < $limit; $i++ ) {
					if ( empty( $files['name'][ $i ] ) || UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
						continue;
					}

					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);

					// Validate file extension.
					$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
					if ( ! in_array( $ext, $allowed_types, true ) ) {
						continue; // Skip invalid file types.
					}

					// Validate MIME type to prevent extension spoofing.
					$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
					$mime_type = $file_info['type'] ?? '';
					if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
						continue; // Skip invalid MIME types.
					}

					// Validate file size.
					if ( $file['size'] > $max_size ) {
						continue; // Skip files that are too large.
					}

					$_FILES['upload_file'] = $file;
					$attachment_id         = media_handle_upload( 'upload_file', 0 );

					if ( ! is_wp_error( $attachment_id ) ) {
						$attachments[] = array(
							'id'   => $attachment_id,
							'url'  => wp_get_attachment_url( $attachment_id ),
							'name' => $files['name'][ $i ],
						);
					}
				}
			}
		}

		// Get sender info.
		$sender = get_userdata( $user_id );

		// Create notification for vendor.
		$notification_service = new \WPSellServices\Services\NotificationService();

		$notification_title = sprintf(
			/* translators: %s: sender name */
			__( 'New message from %s', 'wp-sell-services' ),
			$sender->display_name
		);

		$notification_message = $service_title
			? sprintf(
				/* translators: 1: sender name, 2: service title */
				__( '%1$s sent you a message about "%2$s"', 'wp-sell-services' ),
				$sender->display_name,
				$service_title
			)
			: sprintf(
				/* translators: %s: sender name */
				__( '%s sent you a message', 'wp-sell-services' ),
				$sender->display_name
			);

		$notification_data = array(
			'sender_id'   => $user_id,
			'service_id'  => $service_id,
			'message'     => $message,
			'attachments' => $attachments,
		);

		$notification_service->create(
			$vendor_id,
			'contact_message',
			$notification_title,
			$notification_message,
			$notification_data
		);

		// Send email to vendor.
		$email_subject = $notification_title;
		$email_message = sprintf(
			/* translators: 1: vendor name, 2: sender name */
			__(
				"Hi %1\$s,\n\n%2\$s has sent you a message:",
				'wp-sell-services'
			),
			$vendor->display_name,
			$sender->display_name
		);
		$email_message .= "\n\n" . $message;

		if ( $service_title ) {
			$email_message .= "\n\n" . sprintf(
				/* translators: %s: service title */
				__( 'Regarding: %s', 'wp-sell-services' ),
				$service_title
			);
		}

		if ( ! empty( $attachments ) ) {
			$email_message .= "\n\n" . __( 'Attachments:', 'wp-sell-services' );
			foreach ( $attachments as $attachment ) {
				$email_message .= "\n- " . $attachment['name'] . ': ' . $attachment['url'];
			}
		}

		$email_message .= "\n\n" . sprintf(
			/* translators: %s: reply email */
			__( 'You can reply to this email or contact the sender at: %s', 'wp-sell-services' ),
			$sender->user_email
		);

		if ( EmailService::is_type_enabled( 'vendor_contact' ) ) {
			wp_mail( $vendor->user_email, $email_subject, $email_message );
		}

		/**
		 * Fires after a vendor contact message is sent.
		 *
		 * @param int    $vendor_id   Vendor user ID.
		 * @param int    $user_id     Sender user ID.
		 * @param int    $service_id  Service ID (may be 0).
		 * @param string $message     Message content.
		 * @param array  $attachments Attachment data.
		 */
		do_action( 'wpss_vendor_contacted', $vendor_id, $user_id, $service_id, $message, $attachments );

		wp_send_json_success( array( 'message' => __( 'Your message has been sent successfully!', 'wp-sell-services' ) ) );
	}

	/**
	 * Add service to cart.
	 *
	 * Handles adding a service with selected package and extras to WooCommerce cart.
	 *
	 * @return void
	 */
	public function add_service_to_cart(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$service_id    = absint( $_POST['service_id'] ?? 0 );
		$package_index = sanitize_text_field( wp_unslash( $_POST['package_index'] ?? '0' ) );
		$quantity      = absint( $_POST['quantity'] ?? 1 );
		$extras        = isset( $_POST['extras'] ) ? array_map( 'absint', (array) $_POST['extras'] ) : array();

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		// Check if e-commerce adapter is active.
		$adapter = wpss_get_active_adapter();
		if ( ! $adapter ) {
			wp_send_json_error( array( 'message' => __( 'No e-commerce platform is active. Please configure WooCommerce or another supported platform.', 'wp-sell-services' ) ) );
		}

		// Verify WooCommerce is available for cart operations.
		// The free version requires WooCommerce for payment processing.
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'WooCommerce is required for checkout. Please install and activate WooCommerce to purchase services.', 'wp-sell-services' ),
				)
			);
		}

		// Get the service.
		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
		}

		// Get packages.
		$packages_raw = get_post_meta( $service_id, '_wpss_packages', true );
		$packages     = $packages_raw ? $packages_raw : array();

		// If no packages defined, create a default one.
		if ( empty( $packages ) ) {
			$starting_price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
			$packages       = array(
				array(
					'name'          => __( 'Standard', 'wp-sell-services' ),
					'price'         => $starting_price,
					'delivery_time' => (int) get_post_meta( $service_id, '_wpss_delivery_time', true ),
				),
			);
		}

		// Validate package index.
		if ( ! isset( $packages[ $package_index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package selected.', 'wp-sell-services' ) ) );
		}

		$selected_package = $packages[ $package_index ];
		$package_price    = (float) ( $selected_package['price'] ?? 0 );

		// Get linked WC product or create one.
		$product_id = $this->get_or_create_wc_product( $service_id, $selected_package, $package_price );

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create product for checkout.', 'wp-sell-services' ) ) );
		}

		// Calculate extras price.
		$extras_raw   = get_post_meta( $service_id, '_wpss_extras', true );
		$all_extras   = $extras_raw ? $extras_raw : array();
		$extras_price = 0;
		$extras_days  = 0;

		foreach ( $extras as $extra_index ) {
			if ( isset( $all_extras[ $extra_index ] ) ) {
				$extras_price += (float) ( $all_extras[ $extra_index ]['price'] ?? 0 );
				$extras_days  += (int) ( $all_extras[ $extra_index ]['delivery_time'] ?? 0 );
			}
		}

		// Add to cart.
		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			$quantity,
			0,
			array(),
			array(
				'wpss_service_id' => $service_id,
				'wpss_package_id' => $package_index,
				'wpss_addons'     => $extras,
			)
		);

		if ( ! $cart_item_key ) {
			$error_message = wc_get_notices( 'error' );
			wc_clear_notices();

			if ( ! empty( $error_message ) ) {
				$first_error = reset( $error_message );
				$message     = is_array( $first_error ) ? ( $first_error['notice'] ?? '' ) : $first_error;
				wp_send_json_error( array( 'message' => wp_strip_all_tags( $message ) ) );
			}

			wp_send_json_error( array( 'message' => __( 'Could not add to cart. Please try again.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Added to cart!', 'wp-sell-services' ),
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'cart_url'   => wc_get_cart_url(),
			)
		);
	}

	/**
	 * Get or create WooCommerce product for a service.
	 *
	 * @param int   $service_id Service post ID.
	 * @param array $package    Selected package data.
	 * @param float $price      Package price.
	 * @return int|null Product ID or null on failure.
	 */
	private function get_or_create_wc_product( int $service_id, array $package, float $price ): ?int {
		// Ensure WooCommerce is available.
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return null;
		}

		// Check if service already has a linked WC product.
		$platform_ids_raw = get_post_meta( $service_id, '_wpss_platform_ids', true );
		$platform_ids     = $platform_ids_raw ? $platform_ids_raw : array();
		$product_id       = $platform_ids['woocommerce'] ?? 0;

		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				// Update price to match selected package.
				$product->set_regular_price( $price );
				$product->save();
				return $product_id;
			}
		}

		// Create a new WC product.
		$service = get_post( $service_id );
		if ( ! $service ) {
			return null;
		}

		$product = new \WC_Product_Simple();
		$product->set_name( $service->post_title );
		$product->set_description( $service->post_content );
		$product->set_short_description( $service->post_excerpt );
		$product->set_regular_price( $price );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		// Removed: set_sold_individually(true) - services can be purchased multiple times.

		// Copy featured image.
		$thumbnail_id = get_post_thumbnail_id( $service_id );
		if ( $thumbnail_id ) {
			$product->set_image_id( $thumbnail_id );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return null;
		}

		// Mark as service product.
		update_post_meta( $product_id, '_wpss_is_service', 'yes' );
		update_post_meta( $product_id, '_wpss_service_id', $service_id );

		// Store the link back to service.
		$platform_ids['woocommerce'] = $product_id;
		update_post_meta( $service_id, '_wpss_platform_ids', $platform_ids );

		return $product_id;
	}

	/**
	 * Get notifications.
	 *
	 * @return void
	 */
	public function get_notifications(): void {
		check_ajax_referer( 'wpss_notification_nonce', 'nonce' );

		$user_id = get_current_user_id();
		$limit   = absint( $_POST['limit'] ?? 10 );
		$offset  = absint( $_POST['offset'] ?? 0 );

		global $wpdb;

		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_notifications
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			)
		);

		$unread_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_notifications
				WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);

		wp_send_json_success(
			array(
				'notifications' => $notifications,
				'unread_count'  => (int) $unread_count,
			)
		);
	}

	/**
	 * Mark notification as read.
	 *
	 * @return void
	 */
	public function mark_notification_read(): void {
		check_ajax_referer( 'wpss_notification_nonce', 'nonce' );

		$notification_id = absint( $_POST['notification_id'] ?? 0 );
		$user_id         = get_current_user_id();

		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification.', 'wp-sell-services' ) ) );
		}

		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'wpss_notifications',
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			),
			array(
				'id'      => $notification_id,
				'user_id' => $user_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		wp_send_json_success();
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @return void
	 */
	public function mark_all_notifications_read(): void {
		check_ajax_referer( 'wpss_notification_nonce', 'nonce' );

		$user_id = get_current_user_id();

		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'wpss_notifications',
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			),
			array(
				'user_id' => $user_id,
				'is_read' => 0,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		wp_send_json_success( array( 'message' => __( 'All notifications marked as read.', 'wp-sell-services' ) ) );
	}

	/**
	 * Update cart item (package, quantity, extras).
	 *
	 * @return void
	 */
	public function update_cart_item(): void {
		check_ajax_referer( 'wpss_checkout_nonce', 'nonce' );

		$service_id    = absint( $_POST['service_id'] ?? 0 );
		$package_index = sanitize_text_field( wp_unslash( $_POST['package_index'] ?? '0' ) );
		$quantity      = absint( $_POST['quantity'] ?? 1 );
		$extras        = array_map( 'absint', (array) ( $_POST['extras'] ?? array() ) );

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		// Update WooCommerce cart if active.
		if ( class_exists( 'WooCommerce' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
				if ( isset( $cart_item['wpss_service_id'] ) && (int) $cart_item['wpss_service_id'] === $service_id ) {
					WC()->cart->set_quantity( $cart_key, $quantity );

					// Update cart item data.
					WC()->cart->cart_contents[ $cart_key ]['wpss_package_index'] = $package_index;
					WC()->cart->cart_contents[ $cart_key ]['wpss_extras']        = $extras;
					WC()->cart->calculate_totals();

					wp_send_json_success( array( 'message' => __( 'Cart updated.', 'wp-sell-services' ) ) );
				}
			}
		}

		wp_send_json_success( array( 'message' => __( 'Cart updated.', 'wp-sell-services' ) ) );
	}

	/**
	 * Remove requirement file.
	 *
	 * @return void
	 */
	public function remove_requirement_file(): void {
		check_ajax_referer( 'wpss_checkout_nonce', 'nonce' );

		$file_id = absint( $_POST['file_id'] ?? 0 );
		$user_id = get_current_user_id();

		if ( ! $file_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// Verify user owns the attachment.
		$attachment = get_post( $file_id );
		if ( ! $attachment || (int) $attachment->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// Delete attachment.
		wp_delete_attachment( $file_id, true );

		wp_send_json_success( array( 'message' => __( 'File removed.', 'wp-sell-services' ) ) );
	}

	/**
	 * Skip requirements step.
	 *
	 * @return void
	 */
	public function skip_requirements(): void {
		check_ajax_referer( 'wpss_checkout_nonce', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->customer_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// Mark requirements as skipped (can be submitted later).
		update_post_meta( $order_id, '_wpss_requirements_skipped', true );

		$redirect_url = wpss_get_page_url( 'orders' );

		wp_send_json_success(
			array(
				'message'  => __( 'Requirements skipped. You can submit them later.', 'wp-sell-services' ),
				'redirect' => $redirect_url,
			)
		);
	}

	/**
	 * Load services via AJAX (for blocks).
	 *
	 * @return void
	 */
	public function load_services(): void {
		check_ajax_referer( 'wpss_blocks_frontend', 'nonce' );

		$page       = absint( $_POST['page'] ?? 1 );
		$attributes = isset( $_POST['attributes'] ) ? json_decode( wp_unslash( $_POST['attributes'] ), true ) : array();

		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $attributes['postsPerPage'] ?? 12 ),
			'paged'          => $page,
			'orderby'        => sanitize_key( $attributes['orderBy'] ?? 'date' ),
			'order'          => in_array( ( $attributes['order'] ?? 'DESC' ), array( 'ASC', 'DESC' ), true ) ? $attributes['order'] : 'DESC',
		);

		// Category filter.
		if ( ! empty( $attributes['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'wpss_service_category',
					'field'    => 'term_id',
					'terms'    => absint( $attributes['category'] ),
				),
			);
		}

		$query = new \WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				wpss_get_template_part( 'content', 'service-card' );
			}
		} else {
			echo '<p class="wpss-no-services">' . esc_html__( 'No services found.', 'wp-sell-services' ) . '</p>';
		}
		wp_reset_postdata();
		$html = ob_get_clean();

		// Pagination.
		ob_start();
		wpss_pagination( $query );
		$pagination = ob_get_clean();

		wp_send_json_success(
			array(
				'html'       => $html,
				'pagination' => $pagination,
			)
		);
	}

	/**
	 * Get dashboard tab content.
	 *
	 * @return void
	 */
	public function get_dashboard_tab(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$tab = sanitize_key( $_POST['tab'] ?? 'overview' );
		if ( ! in_array( $tab, self::VALID_DASHBOARD_TABS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tab.', 'wp-sell-services' ) ) );
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		ob_start();

		// Load appropriate template based on tab.
		$template = "dashboard/tabs/{$tab}";
		if ( ! wpss_get_template_part( $template ) ) {
			echo '<p>' . esc_html__( 'Tab content not found.', 'wp-sell-services' ) . '</p>';
		}

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return void
	 */
	public function get_dashboard_stats(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$range   = sanitize_key( $_POST['range'] ?? 'month' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		global $wpdb;

		// Calculate date range.
		$end_date   = current_time( 'Y-m-d 23:59:59' );
		$start_date = match ( $range ) {
			'day'   => current_time( 'Y-m-d 00:00:00' ),
			'week'  => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ),
			'year'  => gmdate( 'Y-01-01 00:00:00' ),
			default => gmdate( 'Y-m-01 00:00:00' ), // month
		};

		$orders_table = $wpdb->prefix . 'wpss_orders';

		// Get stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN status IN ('in_progress', 'pending_requirements') THEN 1 ELSE 0 END) as active,
					COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) as earnings
				FROM {$orders_table}
				WHERE vendor_id = %d AND created_at BETWEEN %s AND %s",
				$user_id,
				$start_date,
				$end_date
			)
		);

		$stats = array(
			'total_orders' => array(
				'value'  => (int) ( $stats_row->total_orders ?? 0 ),
				'change' => 0,
			),
			'completed'    => array(
				'value'  => (int) ( $stats_row->completed ?? 0 ),
				'change' => 0,
			),
			'active'       => array(
				'value'  => (int) ( $stats_row->active ?? 0 ),
				'change' => 0,
			),
			'earnings'     => array(
				'value'  => wpss_format_price( (float) ( $stats_row->earnings ?? 0 ) ),
				'change' => 0,
			),
		);

		// Chart data (simple daily aggregation).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$chart_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(created_at) as date,
					COUNT(*) as orders,
					COALESCE(SUM(total), 0) as earnings
				FROM {$orders_table}
				WHERE vendor_id = %d AND created_at BETWEEN %s AND %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$user_id,
				$start_date,
				$end_date
			)
		);

		$labels        = array();
		$earnings_data = array();
		$orders_data   = array();

		foreach ( $chart_data as $row ) {
			$labels[]        = wp_date( 'M j', strtotime( $row->date ) );
			$earnings_data[] = (float) $row->earnings;
			$orders_data[]   = (int) $row->orders;
		}

		// Status distribution for doughnut chart.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CASE
						WHEN status IN ('pending_requirements', 'accepted') THEN 'active'
						WHEN status = 'in_progress' THEN 'in_progress'
						WHEN status = 'completed' THEN 'completed'
						WHEN status IN ('cancelled', 'refunded') THEN 'cancelled'
						ELSE 'other'
					END as status_group,
					COUNT(*) as count
				FROM {$orders_table}
				WHERE vendor_id = %d
				GROUP BY status_group",
				$user_id
			)
		);

		$status_counts = array( 0, 0, 0, 0 ); // active, in_progress, completed, cancelled
		foreach ( $status_data as $row ) {
			switch ( $row->status_group ) {
				case 'active':
					$status_counts[0] = (int) $row->count;
					break;
				case 'in_progress':
					$status_counts[1] = (int) $row->count;
					break;
				case 'completed':
					$status_counts[2] = (int) $row->count;
					break;
				case 'cancelled':
					$status_counts[3] = (int) $row->count;
					break;
			}
		}

		wp_send_json_success(
			array(
				'stats'  => $stats,
				'charts' => array(
					'earnings' => array(
						'labels' => $labels,
						'data'   => $earnings_data,
					),
					'orders'   => array(
						'labels' => $labels,
						'data'   => $orders_data,
					),
					'status'   => array(
						'data' => $status_counts,
					),
				),
			)
		);
	}

	/**
	 * Handle service action from dashboard.
	 *
	 * @return void
	 */
	public function service_action(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$action     = sanitize_key( $_POST['service_action'] ?? '' );
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $action ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// Verify ownership.
		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$message = '';

		switch ( $action ) {
			case 'pause':
				wp_update_post(
					array(
						'ID'          => $service_id,
						'post_status' => 'draft',
					)
				);
				$message = __( 'Service paused.', 'wp-sell-services' );
				break;

			case 'unpublish':
				wp_update_post(
					array(
						'ID'          => $service_id,
						'post_status' => 'draft',
					)
				);
				$message = __( 'Service unpublished.', 'wp-sell-services' );
				break;

			case 'publish':
				wp_update_post(
					array(
						'ID'          => $service_id,
						'post_status' => 'publish',
					)
				);
				$message = __( 'Service published.', 'wp-sell-services' );
				break;

			case 'delete':
				$trashed = wp_trash_post( $service_id );
				if ( ! $trashed ) {
					wp_send_json_error( array( 'message' => __( 'Failed to delete service.', 'wp-sell-services' ) ) );
				}
				$message = __( 'Service deleted.', 'wp-sell-services' );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Handle order action from dashboard.
	 *
	 * @return void
	 */
	public function order_action(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$action   = sanitize_key( $_POST['order_action'] ?? '' );
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id || ! $action ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		// Verify user is part of order.
		if ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$result = array( 'success' => false );

		switch ( $action ) {
			case 'accept':
				if ( (int) $order->vendor_id === $user_id ) {
					$result = $order_service->update_status( $order_id, 'accepted' );
				}
				break;

			case 'start':
				if ( (int) $order->vendor_id === $user_id ) {
					$result['success'] = $order_service->start_work( $order_id );
				}
				break;

			case 'cancel':
				$result = $order_service->update_status( $order_id, 'cancelled' );
				break;

			case 'refund':
				// Only customer can request refund, or vendor can issue refund.
				if ( in_array( $order->status, array( 'pending_payment', 'pending_requirements', 'accepted' ), true ) ) {
					$result = $order_service->update_status( $order_id, 'refunded' );
				} else {
					wp_send_json_error( array( 'message' => __( 'Order cannot be refunded in its current status.', 'wp-sell-services' ) ) );
				}
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'wp-sell-services' ) ) );
		}

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Order updated.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Action failed.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Filter dashboard content.
	 *
	 * @return void
	 */
	public function filter_dashboard(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$tab = sanitize_key( $_POST['tab'] ?? 'orders' );
		if ( ! in_array( $tab, self::VALID_FILTERABLE_TABS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tab.', 'wp-sell-services' ) ) );
		}
		$filter  = sanitize_key( $_POST['filter'] ?? 'all' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		ob_start();

		// Load filtered content based on tab.
		$template = "dashboard/partials/{$tab}-list";
		set_query_var( 'wpss_filter', $filter );
		set_query_var( 'wpss_user_id', $user_id );
		wpss_get_template_part( $template );

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Handle bulk action.
	 *
	 * @return void
	 */
	public function bulk_action(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$action  = sanitize_key( $_POST['bulk_action'] ?? '' );
		$ids     = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
		$type    = sanitize_key( $_POST['type'] ?? 'services' );
		$user_id = get_current_user_id();

		if ( empty( $ids ) || ! $action ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$processed = 0;

		if ( 'services' === $type ) {
			foreach ( $ids as $service_id ) {
				$service = get_post( $service_id );
				if ( $service && 'wpss_service' === $service->post_type && (int) $service->post_author === $user_id ) {
					switch ( $action ) {
						case 'delete':
							wp_trash_post( $service_id );
							++$processed;
							break;
						case 'pause':
							wp_update_post(
								array(
									'ID'          => $service_id,
									'post_status' => 'draft',
								)
							);
							++$processed;
							break;
						case 'publish':
							wp_update_post(
								array(
									'ID'          => $service_id,
									'post_status' => 'publish',
								)
							);
							++$processed;
							break;
					}
				}
			}
		}

		/* translators: %d: number of items processed */
		wp_send_json_success( array( 'message' => sprintf( __( '%d items updated.', 'wp-sell-services' ), $processed ) ) );
	}

	/**
	 * Search dashboard content.
	 *
	 * @return void
	 */
	public function search_dashboard(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$tab = sanitize_key( $_POST['tab'] ?? 'orders' );
		if ( ! in_array( $tab, self::VALID_FILTERABLE_TABS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tab.', 'wp-sell-services' ) ) );
		}
		$query   = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		ob_start();

		// Load search results based on tab.
		$template = "dashboard/partials/{$tab}-list";
		set_query_var( 'wpss_search', $query );
		set_query_var( 'wpss_user_id', $user_id );
		wpss_get_template_part( $template );

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Paginate dashboard content.
	 *
	 * @return void
	 */
	public function paginate_dashboard(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$tab = sanitize_key( $_POST['tab'] ?? 'orders' );
		if ( ! in_array( $tab, self::VALID_FILTERABLE_TABS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tab.', 'wp-sell-services' ) ) );
		}
		$page    = absint( $_POST['page'] ?? 1 );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		ob_start();

		// Load paginated content.
		$template = "dashboard/partials/{$tab}-list";
		set_query_var( 'wpss_page', $page );
		set_query_var( 'wpss_user_id', $user_id );
		wpss_get_template_part( $template );

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Export data as CSV.
	 *
	 * @return void
	 */
	public function export_data(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$allowed_types = array( 'orders', 'sales', 'earnings' );
		$type          = sanitize_key( $_POST['type'] ?? 'orders' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			wp_die( esc_html__( 'Invalid export type.', 'wp-sell-services' ) );
		}
		$tab     = sanitize_key( $_POST['tab'] ?? 'orders' );
		$range   = sanitize_key( $_POST['range'] ?? 'month' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_die( esc_html__( 'Please log in.', 'wp-sell-services' ) );
		}

		global $wpdb;

		// Calculate date range.
		$end_date   = current_time( 'Y-m-d 23:59:59' );
		$start_date = match ( $range ) {
			'day'   => current_time( 'Y-m-d 00:00:00' ),
			'week'  => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ),
			'year'  => gmdate( 'Y-01-01 00:00:00' ),
			default => gmdate( 'Y-m-01 00:00:00' ),
		};

		$filename = sanitize_file_name( "wpss-{$type}-export-" . gmdate( 'Y-m-d' ) . '.csv' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		if ( 'orders' === $type ) {
			fputcsv( $output, array( 'Order ID', 'Service', 'Customer', 'Status', 'Total', 'Created' ) );

			$orders_table = $wpdb->prefix . 'wpss_orders';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$orders = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$orders_table}
					WHERE vendor_id = %d AND created_at BETWEEN %s AND %s
					ORDER BY created_at DESC",
					$user_id,
					$start_date,
					$end_date
				)
			);

			foreach ( $orders as $order ) {
				$service  = get_post( $order->service_id );
				$customer = get_userdata( $order->customer_id );
				fputcsv(
					$output,
					array(
						$order->id,
						self::sanitize_csv_cell( $service ? $service->post_title : 'N/A' ),
						self::sanitize_csv_cell( $customer ? $customer->display_name : 'N/A' ),
						$order->status,
						$order->total,
						$order->created_at,
					)
				);
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Cancel withdrawal request.
	 *
	 * @return void
	 */
	public function cancel_withdrawal(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$withdrawal_id = absint( $_POST['withdrawal_id'] ?? 0 );
		$user_id       = get_current_user_id();

		if ( ! $withdrawal_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		global $wpdb;

		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';

		// Lock the withdrawal row to prevent double-cancel race conditions.
		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$withdrawal = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$withdrawals_table} WHERE id = %d AND vendor_id = %d FOR UPDATE",
				$withdrawal_id,
				$user_id
			)
		);

		if ( ! $withdrawal ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => __( 'Withdrawal not found.', 'wp-sell-services' ) ) );
		}

		if ( 'pending' !== $withdrawal->status ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => __( 'Only pending withdrawals can be cancelled.', 'wp-sell-services' ) ) );
		}

		// Cancel withdrawal.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$withdrawals_table,
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $withdrawal_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Restore balance atomically using SQL arithmetic.
		$vendor_table = $wpdb->prefix . 'wpss_vendor_profiles';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$vendor_table} SET pending_balance = GREATEST(0, pending_balance - %f), available_balance = available_balance + %f WHERE user_id = %d",
				(float) $withdrawal->amount,
				(float) $withdrawal->amount,
				$user_id
			)
		);

		$wpdb->query( 'COMMIT' );

		wp_send_json_success( array( 'message' => __( 'Withdrawal cancelled. Balance restored.', 'wp-sell-services' ) ) );
	}

	/**
	 * Sanitize a CSV cell value to prevent CSV injection.
	 *
	 * Prefixes cells starting with dangerous characters (=, +, -, @, |, %)
	 * with a tab character to prevent formula execution in spreadsheet applications.
	 *
	 * @param string $value Cell value.
	 * @return string Sanitized cell value.
	 */
	private static function sanitize_csv_cell( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		$dangerous_chars = array( '=', '+', '-', '@', "\t", "\r", '|', '%' );
		if ( in_array( $value[0], $dangerous_chars, true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
