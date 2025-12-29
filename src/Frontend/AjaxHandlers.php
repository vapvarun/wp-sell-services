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

use WPSellServices\Services\OrderService;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\DeliveryService;
use WPSellServices\Services\ReviewService;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;
use WPSellServices\Services\RequirementsService;
use WPSellServices\Services\DisputeService;

/**
 * Handles frontend AJAX requests.
 *
 * @since 1.0.0
 */
class AjaxHandlers {

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	public function init(): void {
		// Order actions.
		add_action( 'wp_ajax_wpss_accept_order', array( $this, 'accept_order' ) );
		add_action( 'wp_ajax_wpss_decline_order', array( $this, 'decline_order' ) );
		add_action( 'wp_ajax_wpss_deliver_order', array( $this, 'deliver_order' ) );
		add_action( 'wp_ajax_wpss_request_revision', array( $this, 'request_revision' ) );
		add_action( 'wp_ajax_wpss_accept_delivery', array( $this, 'accept_delivery' ) );
		add_action( 'wp_ajax_wpss_cancel_order', array( $this, 'cancel_order' ) );

		// Requirements.
		add_action( 'wp_ajax_wpss_submit_requirements', array( $this, 'submit_requirements' ) );

		// Messages.
		add_action( 'wp_ajax_wpss_send_message', array( $this, 'send_message' ) );
		add_action( 'wp_ajax_wpss_get_messages', array( $this, 'get_messages' ) );
		add_action( 'wp_ajax_wpss_mark_messages_read', array( $this, 'mark_messages_read' ) );

		// Reviews.
		add_action( 'wp_ajax_wpss_submit_review', array( $this, 'submit_review' ) );

		// Disputes.
		add_action( 'wp_ajax_wpss_open_dispute', array( $this, 'open_dispute' ) );

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

		// Notifications.
		add_action( 'wp_ajax_wpss_get_notifications', array( $this, 'get_notifications' ) );
		add_action( 'wp_ajax_wpss_mark_notification_read', array( $this, 'mark_notification_read' ) );
		add_action( 'wp_ajax_wpss_mark_all_notifications_read', array( $this, 'mark_all_notifications_read' ) );
	}

	/**
	 * Accept order (vendor).
	 *
	 * @return void
	 */
	public function accept_order(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order['vendor_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to accept this order.', 'wp-sell-services' ) ) );
		}

		$result = $order_service->update_status( $order_id, 'accepted' );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Order accepted successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
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

		if ( ! $order || (int) $order['vendor_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to decline this order.', 'wp-sell-services' ) ) );
		}

		$result = $order_service->decline( $order_id, $reason );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Order declined.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Deliver order (vendor).
	 *
	 * @return void
	 */
	public function deliver_order(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$message  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$files    = isset( $_POST['files'] ) ? array_map( 'absint', $_POST['files'] ) : array();
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order['vendor_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to deliver this order.', 'wp-sell-services' ) ) );
		}

		$delivery_service = new DeliveryService();
		$result           = $delivery_service->submit( $order_id, $user_id, $message, $files );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Delivery submitted successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
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

		if ( ! $order || (int) $order['customer_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to request revision.', 'wp-sell-services' ) ) );
		}

		$delivery_service = new DeliveryService();
		$result           = $delivery_service->request_revision( $order_id, $user_id, $reason );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Revision requested successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
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

		if ( ! $order || (int) $order['customer_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to accept this delivery.', 'wp-sell-services' ) ) );
		}

		$delivery_service = new DeliveryService();
		$result           = $delivery_service->accept( $order_id, $user_id );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Order completed successfully!', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
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
		if ( ! $order || ( (int) $order['customer_id'] !== $user_id && (int) $order['vendor_id'] !== $user_id ) ) {
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
	 * @return void
	 */
	public function submit_requirements(): void {
		check_ajax_referer( 'wpss_requirements_nonce', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order['customer_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to submit requirements.', 'wp-sell-services' ) ) );
		}

		// Collect field data.
		$field_data = array();
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, 'field_' ) === 0 ) {
				$field_id                = str_replace( 'field_', '', $key );
				$field_data[ $field_id ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( wp_unslash( $value ) );
			}
		}

		// Collect files.
		$files = isset( $_POST['files'] ) ? array_map( 'absint', $_POST['files'] ) : array();

		$requirements_service = new RequirementsService();
		$result               = $requirements_service->submit( $order_id, $field_data, $files );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Requirements submitted successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Send message.
	 *
	 * @return void
	 */
	public function send_message(): void {
		check_ajax_referer( 'wpss_message_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$content         = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
		$attachments     = isset( $_POST['attachments'] ) ? array_map( 'absint', $_POST['attachments'] ) : array();
		$user_id         = get_current_user_id();

		if ( ! $conversation_id || ! $content ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message.', 'wp-sell-services' ) ) );
		}

		$conversation_service = new ConversationService();

		// Check permission.
		if ( ! $conversation_service->user_can_access( $conversation_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to send messages in this conversation.', 'wp-sell-services' ) ) );
		}

		$result = $conversation_service->send_message( $conversation_id, $user_id, $content, $attachments );

		if ( $result['success'] ) {
			$message = $conversation_service->get_message( $result['message_id'] );
			wp_send_json_success(
				array(
					'message' => __( 'Message sent.', 'wp-sell-services' ),
					'data'    => $message,
				)
			);
		} else {
			wp_send_json_error( $result );
		}
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
			wp_send_json_error();
		}
	}

	/**
	 * Submit review.
	 *
	 * @return void
	 */
	public function submit_review(): void {
		check_ajax_referer( 'wpss_review_nonce', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$rating   = absint( $_POST['rating'] ?? 0 );
		$comment  = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		$user_id  = get_current_user_id();

		if ( ! $order_id || ! $rating ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a rating.', 'wp-sell-services' ) ) );
		}

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rating.', 'wp-sell-services' ) ) );
		}

		$review_service = new ReviewService();
		$review         = $review_service->create(
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
			wp_send_json_error( array( 'message' => __( 'Failed to submit review. Order may not be eligible for review.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Open dispute for order.
	 *
	 * @return void
	 */
	public function open_dispute(): void {
		check_ajax_referer( 'wpss_open_dispute', 'wpss_dispute_nonce' );

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

		$data = array(
			'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'category'    => absint( $_POST['category'] ?? 0 ),
			'budget_min'  => floatval( $_POST['budget_min'] ?? 0 ),
			'budget_max'  => floatval( $_POST['budget_max'] ?? 0 ),
			'deadline'    => sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) ),
		);

		if ( ! $data['title'] || ! $data['description'] ) {
			wp_send_json_error( array( 'message' => __( 'Title and description are required.', 'wp-sell-services' ) ) );
		}

		$request_service = new BuyerRequestService();
		$result          = $request_service->create( $user_id, $data );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Request posted successfully.', 'wp-sell-services' ),
					'request_id' => $result['request_id'],
				)
			);
		} else {
			wp_send_json_error( $result );
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
		$result          = $request_service->convert_to_order( $request_id, $proposal_id );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'  => __( 'Proposal accepted. Order created!', 'wp-sell-services' ),
					'order_id' => $result['order_id'],
					'redirect' => home_url( '/my-account/service-orders/' . $result['order_id'] . '/' ),
				)
			);
		} else {
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

		// Check file type.
		$allowed_types = explode( ',', get_option( 'wpss_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,zip' ) );
		$ext           = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

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
		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );

		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$services = new \WP_Query(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'publish',
				's'              => $query,
				'posts_per_page' => 5,
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
}
