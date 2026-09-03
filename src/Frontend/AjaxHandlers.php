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

defined( 'ABSPATH' ) || exit;

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
use WPSellServices\Services\VendorService;
use WPSellServices\Models\ServiceOrder;

/**
 * Handles frontend AJAX requests.
 *
 * Authorization model (object-ownership, not role capabilities)
 * -------------------------------------------------------------
 * These are marketplace endpoints for logged-in buyers and vendors, not admin
 * screens. The right authorization question is almost never "does this user
 * hold capability X?" but "does this user *own* the object they are acting on?"
 * (their order, their service, their proposal, their conversation, their cart).
 * A nonce check alone proves the request is intentional; it does not prove
 * ownership. Every mutating handler therefore enforces ownership through ONE of
 * the four accepted patterns below. A security scan that flags "nonce verified
 * but no current_user_can()" on a handler matching one of these patterns is a
 * false positive for this class — capability checks are the wrong tool here.
 *
 * Pattern A — inline object-ownership guard.
 *   The handler loads the object and compares its owner column to
 *   get_current_user_id() before mutating, e.g.
 *     `if ( ! $order || (int) $order->vendor_id !== $user_id ) { reject; }`
 *   or for CPTs `(int) $post->post_author !== $user_id`. Used by the order,
 *   service, request, proposal-accept/reject and portfolio-edit handlers.
 *
 * Pattern B — service-layer ownership enforcement.
 *   The handler forwards get_current_user_id() to a service method that rejects
 *   when the caller does not own the row (e.g. ProposalService::withdraw(),
 *   MilestoneService::submit()/approve()/decline()/delete_unpaid(),
 *   ExtensionOrderService::decline(), ConversationService::mark_as_read() /
 *   user_can_access()). The guard lives once in the service so every caller
 *   (AJAX + REST + CLI) shares it. The handler must pass the *current* user id,
 *   never a client-supplied id.
 *
 * Pattern C — self-scoped data only.
 *   The handler reads or writes data keyed to the current user and can never
 *   touch another user's data: own user-meta cart/favorites/email-prefs/profile,
 *   notifications and dashboard stats filtered by `user_id = %d` /
 *   `vendor_id = %d`, or a `wpss_user_id` query var pinned to
 *   get_current_user_id(). No object id from the client widens the scope.
 *
 * Pattern D — intentionally public.
 *   Read-only, non-sensitive, `nopriv`-exposed endpoints (live_search,
 *   load_reviews, load_services) and the guest helpful-vote (mark_review_helpful,
 *   deduped per user/IP). These have no ownership concept by design and are
 *   rate-limited instead.
 *
 * Admin-only / cross-user actions are the exception: those DO require an
 * explicit current_user_can( 'manage_options' ) check (see order_action,
 * submit_requirements, add_dispute_evidence) in addition to the nonce.
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
		// Extension request (vendor -> buyer, paid sub-order flow).
		add_action( 'wp_ajax_wpss_request_extension', array( $this, 'ajax_request_extension' ) );
		add_action( 'wp_ajax_wpss_decline_extension', array( $this, 'ajax_decline_extension' ) );

		// Milestones — pay-first phases with delivery + approval step.
		add_action( 'wp_ajax_wpss_propose_milestone', array( $this, 'ajax_propose_milestone' ) );
		add_action( 'wp_ajax_wpss_submit_milestone', array( $this, 'ajax_submit_milestone' ) );
		add_action( 'wp_ajax_wpss_approve_milestone', array( $this, 'ajax_approve_milestone' ) );
		add_action( 'wp_ajax_wpss_request_milestone_revision', array( $this, 'ajax_request_milestone_revision' ) );
		add_action( 'wp_ajax_wpss_decline_milestone', array( $this, 'ajax_decline_milestone' ) );
		add_action( 'wp_ajax_wpss_delete_milestone', array( $this, 'ajax_delete_milestone' ) );

		// Order actions. No accept/decline: this product is payment-first, so
		// there is no acceptance step; those verbs only ever wrote legacy
		// statuses onto orders that had already moved past them.
		add_action( 'wp_ajax_wpss_start_work', array( $this, 'start_work' ) );
		add_action( 'wp_ajax_wpss_deliver_order', array( $this, 'deliver_order' ) );
		add_action( 'wp_ajax_wpss_request_revision', array( $this, 'request_revision' ) );
		add_action( 'wp_ajax_wpss_accept_delivery', array( $this, 'accept_delivery' ) );
		add_action( 'wp_ajax_wpss_cancel_order', array( $this, 'cancel_order' ) );
		add_action( 'wp_ajax_wpss_accept_cancellation', array( $this, 'accept_cancellation' ) );
		add_action( 'wp_ajax_wpss_reject_cancellation', array( $this, 'reject_cancellation' ) );

		// Requirements.
		add_action( 'wp_ajax_wpss_submit_requirements', array( $this, 'submit_requirements' ) );

		// Messages.
		add_action( 'wp_ajax_wpss_send_message', array( $this, 'send_message' ) );
		add_action( 'wp_ajax_wpss_send_direct_message', array( $this, 'send_direct_message' ) );
		add_action( 'wp_ajax_wpss_get_messages', array( $this, 'get_messages' ) );
		add_action( 'wp_ajax_wpss_get_new_messages', array( $this, 'get_new_messages' ) );
		add_action( 'wp_ajax_wpss_mark_messages_read', array( $this, 'mark_messages_read' ) );

		// Reviews.
		add_action( 'wp_ajax_wpss_submit_review', array( $this, 'submit_review' ) );
		add_action( 'wp_ajax_wpss_load_reviews', array( $this, 'load_reviews' ) );
		add_action( 'wp_ajax_nopriv_wpss_load_reviews', array( $this, 'load_reviews' ) );
		add_action( 'wp_ajax_wpss_mark_review_helpful', array( $this, 'mark_review_helpful' ) );
		add_action( 'wp_ajax_nopriv_wpss_mark_review_helpful', array( $this, 'mark_review_helpful' ) );

		// Daily Action Scheduler sweep of expired guest helpful-vote rows
		// (kept here next to mark_review_helpful so the producer + consumer
		// for the `_wpss_vote_*` option_name pattern live in the same file).
		add_action(
			'wpss_cleanup_review_votes',
			static function (): void {
				( new \WPSellServices\Database\Repositories\ReviewRepository() )->cleanup_expired_helpful_votes();
			}
		);

		// Disputes.
		add_action( 'wp_ajax_wpss_open_dispute', array( $this, 'open_dispute' ) );
		add_action( 'wp_ajax_wpss_add_dispute_evidence', array( $this, 'add_dispute_evidence' ) );

		// Buyer requests.
		add_action( 'wp_ajax_wpss_post_request', array( $this, 'post_request' ) );
		add_action( 'wp_ajax_wpss_submit_proposal', array( $this, 'submit_proposal' ) );
		add_action( 'wp_ajax_wpss_accept_proposal', array( $this, 'accept_proposal' ) );
		add_action( 'wp_ajax_wpss_reject_proposal', array( $this, 'reject_proposal' ) );
		add_action( 'wp_ajax_wpss_withdraw_proposal', array( $this, 'withdraw_proposal' ) );
		add_action( 'wp_ajax_wpss_update_request', array( $this, 'update_request' ) );
		add_action( 'wp_ajax_wpss_update_request_status', array( $this, 'update_request_status' ) );
		add_action( 'wp_ajax_wpss_delete_request', array( $this, 'delete_request' ) );

		// Service actions.
		add_action( 'wp_ajax_wpss_favorite_service', array( $this, 'favorite_service' ) );
		add_action( 'wp_ajax_wpss_unfavorite_service', array( $this, 'unfavorite_service' ) );
		add_action( 'wp_ajax_wpss_get_favorites', array( $this, 'get_favorites' ) );
		add_action( 'wp_ajax_wpss_update_service_status', array( $this, 'update_service_status' ) );
		add_action( 'wp_ajax_wpss_delete_service', array( $this, 'delete_service' ) );

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
		add_action( 'wp_ajax_wpss_remove_cart_item', array( $this, 'remove_cart_item' ) );
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

		// Profile.
		add_action( 'wp_ajax_wpss_update_vendor_profile', array( $this, 'update_vendor_profile' ) );

		// Per-vendor email preferences (VS11 from plans/ORDER-FLOW-AUDIT.md).
		add_action( 'wp_ajax_wpss_save_email_preferences', array( $this, 'save_email_preferences' ) );

		// Portfolio (AJAX fallback for non-REST contexts).
		add_action( 'wp_ajax_wpss_add_portfolio_item', array( $this, 'add_portfolio_item' ) );
		add_action( 'wp_ajax_wpss_update_portfolio_item', array( $this, 'update_portfolio_item' ) );
		add_action( 'wp_ajax_wpss_delete_portfolio_item', array( $this, 'delete_portfolio_item' ) );
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

		// Process uploaded files from $_FILES via the shared normalizer (same
		// shape the REST deliverables endpoint feeds DeliveryService::submit()).
		$files = array();
		if ( ! empty( $_FILES['files'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Grouped $_FILES is normalized/sanitized inside wpss_normalize_uploaded_files().
			$files = wpss_normalize_uploaded_files( (array) $_FILES['files'] );
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
			return;
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		// Check if user is part of the order.
		if ( ! $order || ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to cancel this order.', 'wp-sell-services' ) ) );
			return;
		}

		// Buyers cannot directly cancel orders in cancellation_requested status — vendor must respond.
		if ( (int) $order->customer_id === $user_id && 'cancellation_requested' === $order->status ) {
			wp_send_json_error( array( 'message' => __( 'Your cancellation request is awaiting vendor response.', 'wp-sell-services' ) ) );
			return;
		}

		// Vendors can only cancel orders in pending status via AJAX — all other cancellations go through REST API.
		if ( (int) $order->vendor_id === $user_id && 'pending' !== $order->status ) {
			wp_send_json_error( array( 'message' => __( 'You cannot cancel this order in its current status.', 'wp-sell-services' ) ) );
			return;
		}

		if ( empty( $reason ) ) {
			wp_send_json_error( array( 'message' => __( 'Reason is required for cancellation.', 'wp-sell-services' ) ) );
			return;
		}

		// Buyer on in_progress orders must go through request_cancellation (24h window + delivery check).
		if ( (int) $order->customer_id === $user_id && 'in_progress' === $order->status ) {
			$note   = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
			$result = $order_service->request_cancellation( $order_id, $user_id, $reason, $note );
		} else {
			$result = $order_service->cancel( $order_id, $user_id, $reason );
		}

		if ( $result['success'] ) {
			$message = 'in_progress' === $order->status && (int) $order->customer_id === $user_id
				? __( 'Your cancellation request has been submitted. The vendor has 48 hours to respond.', 'wp-sell-services' )
				: __( 'Order cancelled.', 'wp-sell-services' );
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Accept cancellation request (vendor action).
	 *
	 * @return void
	 */
	public function accept_cancellation(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		if ( RateLimiter::check_and_track( 'order_action', get_current_user_id() ) ) {
			RateLimiter::send_error( 'order_action' );
			return;
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
			return;
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wp-sell-services' ) ) );
			return;
		}

		if ( 'cancellation_requested' !== $order->status ) {
			wp_send_json_error( array( 'message' => __( 'This order does not have a pending cancellation request.', 'wp-sell-services' ) ) );
			return;
		}

		$result = $order_service->cancel( $order_id, $user_id, __( 'Vendor accepted cancellation request.', 'wp-sell-services' ) );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Cancellation accepted. Order has been cancelled.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Reject cancellation request (vendor action — escalates to dispute).
	 *
	 * @return void
	 */
	public function reject_cancellation(): void {
		check_ajax_referer( 'wpss_order_action', 'nonce' );

		if ( RateLimiter::check_and_track( 'order_action', get_current_user_id() ) ) {
			RateLimiter::send_error( 'order_action' );
			return;
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$user_id  = get_current_user_id();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wp-sell-services' ) ) );
			return;
		}

		$order_service = new OrderService();
		$order         = $order_service->get( $order_id );

		if ( ! $order || (int) $order->vendor_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wp-sell-services' ) ) );
			return;
		}

		if ( 'cancellation_requested' !== $order->status ) {
			wp_send_json_error( array( 'message' => __( 'This order does not have a pending cancellation request.', 'wp-sell-services' ) ) );
			return;
		}

		// Escalate to dispute.
		$dispute_service = new DisputeService();
		$dispute_result  = $dispute_service->open(
			$order_id,
			$user_id,
			__( 'Cancellation Dispute', 'wp-sell-services' ),
			__( 'Vendor disputed the buyer cancellation request.', 'wp-sell-services' )
		);

		if ( $dispute_result ) {
			wp_send_json_success( array( 'message' => __( 'Cancellation disputed. The order has been escalated for admin review.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to create dispute. Please try again.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Submit requirements.
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

		if ( ! $order || ( (int) $order->customer_id !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to submit requirements.', 'wp-sell-services' ) ) );
		}

		// The form posts requirements[index]; answers are stored keyed by the
		// requirement id, which is what the service validates against.
		$service      = $order->get_service();
		$requirements = $service ? wpss_get_service_requirements( $service->id ) : array();

		$field_data = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified above; RequirementsService::sanitize() sanitises every value by field type.
		$posted = isset( $_POST['requirements'] ) && is_array( $_POST['requirements'] ) ? wp_unslash( $_POST['requirements'] ) : array();
		foreach ( $posted as $index => $value ) {
			// Numeric index: a configured question. String key ('description',
			// 'additional_notes'): the default form's freeform fields.
			$key = is_numeric( $index ) ? ( $requirements[ (int) $index ]['id'] ?? '' ) : sanitize_key( (string) $index );
			if ( '' !== $key ) {
				$field_data[ $key ] = $value;
			}
		}

		// Per-requirement file inputs. PHP folds name="requirements[3]" into
		// $_FILES['requirements'][prop][3], so each question's file is sliced
		// back out and keyed by the requirement id like its answer.
		$files = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw $_FILES entries are validated (type, size, MIME) and sanitized by wp_handle_upload()/wp_check_filetype() inside RequirementsService::process_uploads().
		$posted_files = isset( $_FILES['requirements'] ) && is_array( $_FILES['requirements']['name'] ?? null ) ? $_FILES['requirements'] : array();
		foreach ( $requirements as $index => $requirement ) {
			if ( empty( $posted_files['name'][ $index ] ) ) {
				continue;
			}
			$files[ $requirement['id'] ] = array();
			foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $prop ) {
				$files[ $requirement['id'] ][ $prop ] = $posted_files[ $prop ][ $index ] ?? null;
			}
		}

		// Named file inputs used by the requirements templates.
		$named_file_inputs = apply_filters(
			'wpss_requirements_file_inputs',
			array( 'requirement_files', 'requirements_files' ),
			$order_id
		);
		foreach ( $named_file_inputs as $file_key ) {
			$file_key = sanitize_key( $file_key );
			if ( isset( $_FILES[ $file_key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw $_FILES entry is validated (type, size, MIME) and sanitized by wp_handle_upload()/wp_check_filetype() inside RequirementsService::process_uploads().
				$files[ $file_key ] = $_FILES[ $file_key ];
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

		// We only inspect the structure of $_FILES['attachments']['name'] (count
		// of non-empty entries) — the names themselves are sanitized inside the
		// upload loop further down. WPCS still flags the structural read, so
		// the suppression is intentional and scoped to this single check.
		$has_attachments = ! empty( $_FILES['attachments']['name'] )
			&& is_array( $_FILES['attachments']['name'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Counting entries; values sanitized in the upload loop below.
			&& count( array_filter( (array) $_FILES['attachments']['name'] ) ) > 0;

		if ( ! $content && ! $has_attachments ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message or attach a file.', 'wp-sell-services' ) ) );
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

		// Handle file attachments via the shared validator/uploader (same
		// allow-list, MIME re-check, size cap, and upload behaviour as the REST
		// ConversationsController::send_message path).
		$attachments_data = array();
		$skipped_files    = array();
		if ( ! empty( $_FILES['attachments'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw $_FILES group is validated/sanitized inside wpss_handle_message_attachments().
			$uploaded         = wpss_handle_message_attachments( (array) $_FILES['attachments'], $order_id, 'message' );
			$attachments_data = $uploaded['attachments'];
			$skipped_files    = $uploaded['skipped'];
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

		// Render the new row through the shared renderer so the markup matches
		// the initial server render + the REST message response exactly.
		$html = wpss_render_message_row( $message, $user_id );

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
	 * Send a message to a direct (non-order) conversation.
	 *
	 * @return void
	 */
	public function send_direct_message(): void {
		check_ajax_referer( 'wpss_send_message', 'wpss_message_nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$content         = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$user_id         = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		if ( ! $conversation_id || ! $content ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message.', 'wp-sell-services' ) ) );
		}

		$conversation_service = new ConversationService();

		if ( ! $conversation_service->user_can_access( $conversation_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this conversation.', 'wp-sell-services' ) ) );
		}

		$message = $conversation_service->send_message( $conversation_id, $user_id, $content );

		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send message.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Message sent.', 'wp-sell-services' ) ) );
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

		// Build HTML for each message via the shared renderer (same markup as
		// the initial server render + the REST message response). These are all
		// other-party messages (the query excludes the current user), so they
		// render with avatar + sender name.
		$result = array();

		foreach ( $messages as $msg ) {
			$result[] = array(
				'id'   => (int) $msg->id,
				'html' => wpss_render_message_row( $msg, $user_id ),
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
			?>
			<div class="wpss-review">
				<?php
				// Same partial as the initial page render, so page two of the
				// reviews list cannot drift from page one.
				$wpss_review = $review;
				require WPSS_PLUGIN_DIR . 'templates/partials/review-body.php';
				?>

				<div class="wpss-review-actions">
					<button type="button" class="wpss-review-helpful-btn" data-review="<?php echo esc_attr( $review->id ); ?>">
						<span class="wpss-helpful-icon">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns hand-built SVG with internally-escaped attributes.
							echo \WPSellServices\Services\Icon::render( 'thumbs-up', array( 'class' => 'wpss-icon--sm' ) );
							?>
						</span>
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

		// Check if disputes are enabled in settings.
		if ( ! wpss_get_option( 'orders', 'allow_disputes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Disputes are not enabled on this platform.', 'wp-sell-services' ) ) );
		}

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
			wp_send_json_error( array( 'message' => $dispute_service->last_error() ?: __( 'Failed to open dispute.', 'wp-sell-services' ) ) );
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
		$order      = $order_repo->find( (int) $dispute->order_id );

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

		$evidence_files = array();

		if ( ! empty( $_FILES['evidence_file'] ) && ! empty( $_FILES['evidence_file']['name'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$file    = $_FILES['evidence_file'];
			$refused = wpss_check_upload( $file );

			if ( $refused ) {
				wp_send_json_error( array( 'message' => $refused->get_error_message() ) );
			}

			// Same private store and read gate as a delivery; no URL is kept.
			$record = wpss_store_order_file( $file, (int) $order->id, 'dispute' );

			if ( ! $record ) {
				wp_send_json_error( array( 'message' => __( 'Could not store the file.', 'wp-sell-services' ) ) );
			}

			$evidence_files   = array( $record );
			$evidence_content = wpss_get_order_file_url( $record );
			$evidence_type    = 0 === strpos( (string) $record['type'], 'image/' ) ? 'image' : 'file';
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
			$evidence_files ? '' : $evidence_content,
			$evidence_type !== 'text' ? $description : '',
			$evidence_files
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
				<span class="wpss-evidence-author"><strong><?php echo esc_html( $evidence_user ? $evidence_user->display_name : '' ); ?></strong></span>
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
								<i data-lucide="file" class="wpss-icon" aria-hidden="true"></i>
								<span><?php echo esc_html( $evidence_files ? (string) $evidence_files[0]['name'] : basename( $evidence_content ) ); ?></span>
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

		$skills_raw = sanitize_text_field( wp_unslash( $_POST['skills_required'] ?? '' ) );

		$data = array(
			'title'           => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'     => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'category_id'     => absint( $_POST['category'] ?? 0 ),
			'budget_min'      => floatval( $_POST['budget_min'] ?? 0 ),
			'budget_max'      => floatval( $_POST['budget_max'] ?? 0 ),
			'skills_required' => $skills_raw ? array_map( 'trim', explode( ',', $skills_raw ) ) : array(),
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
	 * Update buyer request content.
	 *
	 * @return void
	 */
	public function update_request(): void {
		check_ajax_referer( 'wpss_edit_request', 'wpss_request_nonce' );

		$user_id    = get_current_user_id();
		$request_id = absint( $_POST['request_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'wp-sell-services' ) ) );
		}

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// Verify ownership.
		$post = get_post( $request_id );
		if ( ! $post || 'wpss_request' !== $post->post_type || (int) $post->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$deadline   = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
		$skills_raw = sanitize_text_field( wp_unslash( $_POST['skills_required'] ?? '' ) );

		$data = array(
			'title'           => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'     => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'category_id'     => absint( $_POST['category'] ?? 0 ),
			'budget_min'      => floatval( $_POST['budget_min'] ?? 0 ),
			'budget_max'      => floatval( $_POST['budget_max'] ?? 0 ),
			'skills_required' => $skills_raw ? array_map( 'trim', explode( ',', $skills_raw ) ) : array(),
		);

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

		// Update the request post status if provided.
		$new_status = sanitize_key( $_POST['post_status'] ?? '' );
		if ( $new_status && in_array( $new_status, array( 'publish', 'draft' ), true ) ) {
			wp_update_post(
				array(
					'ID'          => $request_id,
					'post_status' => $new_status,
				)
			);
		}

		$request_service = new BuyerRequestService();
		$result          = $request_service->update( $request_id, $data );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Request updated successfully.', 'wp-sell-services' ),
					'request_id' => $request_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update request.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Update buyer request status (close/reopen).
	 *
	 * @return void
	 */
	public function update_request_status(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$request_id = absint( $_POST['request_id'] ?? 0 );
		$status     = sanitize_key( $_POST['status'] ?? '' );
		$user_id    = get_current_user_id();

		if ( ! $request_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$post = get_post( $request_id );

		if ( ! $post || 'wpss_request' !== $post->post_type || (int) $post->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$allowed_statuses = array( 'publish', 'draft' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'wp-sell-services' ) ) );
		}

		wp_update_post(
			array(
				'ID'          => $request_id,
				'post_status' => $status,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Request updated.', 'wp-sell-services' ) ) );
	}

	/**
	 * Delete buyer request.
	 *
	 * @return void
	 */
	public function delete_request(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$request_id = absint( $_POST['request_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$post = get_post( $request_id );

		if ( ! $post || 'wpss_request' !== $post->post_type || (int) $post->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		wp_delete_post( $request_id, true );

		wp_send_json_success( array( 'message' => __( 'Request deleted.', 'wp-sell-services' ) ) );
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
		$contract_type = sanitize_key( (string) ( $_POST['contract_type'] ?? 'fixed' ) );
		$is_milestone  = ProposalService::CONTRACT_TYPE_MILESTONE === $contract_type;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw array normalised by ProposalService::normalize_milestones()
		$milestones_raw = isset( $_POST['milestones'] ) && is_array( $_POST['milestones'] ) ? wp_unslash( $_POST['milestones'] ) : array();

		if ( ! $request_id || ! $description ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'wp-sell-services' ) ) );
		}

		// Fixed contracts need an explicit price + delivery days from the form.
		// Milestone contracts derive price + days from the phase list server-side,
		// so skipping those checks here is intentional — the service layer still
		// rejects an empty milestone array.
		if ( ! $is_milestone && ( ! $price || ! $delivery_days ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'wp-sell-services' ) ) );
		}

		if ( $is_milestone && empty( $milestones_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one milestone phase.', 'wp-sell-services' ) ) );
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
				'contract_type' => $contract_type,
				'milestones'    => $milestones_raw,
			)
		);

		if ( is_wp_error( $proposal_id ) ) {
			wp_send_json_error( array( 'message' => $proposal_id->get_error_message() ) );
		}

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
			// Milestone contracts have a $0 parent (already "paid") and per-phase
			// payments. Sending the buyer through /service-checkout?pay_order=N
			// would show them a dead-end "This order has already been paid."
			// notice. Route straight to the order view instead, where the phase
			// timeline + Pay-phase-1 button live.
			$is_milestone_contract = ProposalService::CONTRACT_TYPE_MILESTONE === ( $proposal->contract_type ?? ProposalService::CONTRACT_TYPE_FIXED );
			if ( $is_milestone_contract ) {
				$redirect_url = wpss_get_order_url( (int) $result['order_id'] );
				$message      = __( 'Proposal accepted — your project is set up. Opening the order…', 'wp-sell-services' );
			} else {
				$redirect_url = wpss_ensure_pay_order( (int) $result['order_id'] );
				$message      = __( 'Proposal accepted! Redirecting to payment…', 'wp-sell-services' );
			}

			wp_send_json_success(
				array(
					'message'  => $message,
					'order_id' => $result['order_id'],
					'redirect' => $redirect_url,
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
		$proposal         = $proposal_service->get( $proposal_id );

		if ( ! $proposal ) {
			wp_send_json_error( array( 'message' => __( 'Proposal not found.', 'wp-sell-services' ) ) );
		}

		// Verify the current user owns the buyer request.
		$request = get_post( $proposal->request_id );
		if ( ! $request || (int) $request->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to decline this proposal.', 'wp-sell-services' ) ) );
		}

		$result = $proposal_service->reject( $proposal_id, $user_id, $reason );

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
	 * Favorite service.
	 *
	 * @return void
	 */
	public function favorite_service(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$favorites = \WPSellServices\Services\FavoritesService::add( $user_id, $service_id );

		wp_send_json_success(
			array(
				'message' => __( 'Added to favorites.', 'wp-sell-services' ),
				'count'   => count( $favorites ),
			)
		);
	}

	/**
	 * Unfavorite service.
	 *
	 * @return void
	 */
	public function unfavorite_service(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$favorites = \WPSellServices\Services\FavoritesService::remove( $user_id, $service_id );

		wp_send_json_success(
			array(
				'message' => __( 'Removed from favorites.', 'wp-sell-services' ),
				'count'   => count( $favorites ),
			)
		);
	}

	/**
	 * Get favorites.
	 *
	 * @return void
	 */
	public function get_favorites(): void {
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$user_id   = get_current_user_id();
		$favorites = \WPSellServices\Services\FavoritesService::get_ids( $user_id );

		wp_send_json_success( array( 'favorites' => $favorites ) );
	}

	/**
	 * Toggle service status between publish and draft.
	 *
	 * @return void
	 */
	public function update_service_status(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$new_status = ( 'publish' === $service->post_status ) ? 'draft' : 'publish';

		wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => $new_status,
			)
		);

		wp_send_json_success(
			array(
				'message'    => __( 'Service status updated.', 'wp-sell-services' ),
				'new_status' => $new_status,
			)
		);
	}

	/**
	 * Delete a service owned by the current user.
	 *
	 * @return void
	 */
	public function delete_service(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$user_id    = get_current_user_id();

		if ( ! $service_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		wp_trash_post( $service_id );

		wp_send_json_success( array( 'message' => __( 'Service deleted.', 'wp-sell-services' ) ) );
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

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- validated by wpss_check_upload(), stored by media_handle_upload().

		$refused = wpss_check_upload( $file );

		if ( $refused ) {
			wp_send_json_error( array( 'message' => $refused->get_error_message() ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		// Match how deliveries are stored. A requirement upload is a buyer
		// handing over a brief, a brand asset or a document, and it was landing
		// as a public attachment listed in everyone's media library while the
		// docs told them it was private. Deliveries have used `private` since
		// 1.0 - the same rule applies to what the buyer sends the other way.
		//
		// This hides it from the library and from public listings. The file
		// itself still sits in the uploads tree, so treat its URL as unlisted
		// rather than secret; protecting the path is tracked separately.
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_status' => 'private',
			)
		);

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'filename'      => basename( get_attached_file( $attachment_id ) ?: '' ),
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

		// Rate-limit guests by IP (logged-in users by user ID). Live search is
		// `nopriv`-exposed, so without an IP-scoped limit a single guest can
		// hammer this endpoint and walk the wpss_service post type at speed.
		$current_user_id = get_current_user_id();
		$rate_limit_user = $current_user_id > 0 ? $current_user_id : null;
		if ( RateLimiter::check_and_track( 'live_search', $rate_limit_user ) ) {
			RateLimiter::send_error( 'live_search' );
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

		// We only inspect the structure of $_FILES['attachments']['name'] (count
		// of non-empty entries) — the names themselves are sanitized inside the
		// upload loop further down. WPCS still flags the structural read, so
		// the suppression is intentional and scoped to this single check.
		$has_attachments = ! empty( $_FILES['attachments']['name'] )
			&& is_array( $_FILES['attachments']['name'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Counting entries; values sanitized in the upload loop below.
			&& count( array_filter( (array) $_FILES['attachments']['name'] ) ) > 0;

		if ( ! $message && ! $has_attachments ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message or attach a file.', 'wp-sell-services' ) ) );
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

		// Pre-sale: there is no order yet, so the shared helper keeps these in
		// the media library (private) rather than the order store.
		$attachments = array();
		if ( ! empty( $_FILES['attachments'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw $_FILES group is validated/sanitized inside wpss_handle_message_attachments().
			$attachments = array_slice( wpss_handle_message_attachments( (array) $_FILES['attachments'], 0, 'contact' )['attachments'], 0, 5 );
		}

		// Get sender info.
		//
		// Guarded because send_vendor_contact() typehints \WP_User: handing it
		// the `false` get_userdata() returns is a TypeError, not a blank name.
		// The sender is the logged-in user, so this only fires if the account
		// went away mid-request - rare, but a 500 either way.
		$sender = get_userdata( $user_id );

		if ( ! $sender instanceof \WP_User ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please sign in again.', 'wp-sell-services' ) ) );
		}

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

		// Send HTML email to vendor using the branded template.
		$email_service = new EmailService();
		$email_service->send_vendor_contact( $vendor, $sender, $message, $service_title, $attachments );

		// Create a conversation so the message appears in the dashboard.
		$conversation_service = new ConversationService();

		$conv_subject = $service_title
			? sprintf(
				/* translators: %s: service title */
				__( 'Inquiry about "%s"', 'wp-sell-services' ),
				$service_title
			)
			: __( 'Direct Message', 'wp-sell-services' );

		$conversation = $conversation_service->create_direct( $user_id, $vendor_id, $conv_subject, $service_id );

		if ( $conversation ) {
			$conversation_service->send_message( $conversation->id, $user_id, $message, $attachments );
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
	 * Decide what happens when a logged-out buyer presses Continue to Checkout.
	 *
	 * The cart is user meta, so a guest genuinely cannot have one - that part of
	 * the old guard was right. What was wrong was where it sent them: straight to
	 * wp-login.php with redirect_to pointing at the SERVICE page, which threw away
	 * the package and add-ons they had just chosen. They logged in, landed back on
	 * the pricing table, and had to pick everything again.
	 *
	 * The single-service checkout does not need a cart. It takes the service,
	 * package and add-ons from the URL, and when the owner has enabled
	 * account-at-checkout it already collects the buyer's name and email and
	 * creates their account before the order row is inserted
	 * ({@see wpss_checkout_creates_accounts()}). So the account step is one screen
	 * further along, inside our own chrome, and the buyer never needs to see
	 * wp-login.php at all.
	 *
	 * When account-at-checkout is off the owner has said buyers must already have
	 * an account, so the login gate stays - but it now returns to CHECKOUT with the
	 * selection intact rather than to the service page.
	 *
	 * Always exits.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	private function handle_guest_checkout_intent(): void {
		// Verified before any posted selection is trusted, exactly as the
		// logged-in path does. The nonce was minted for user 0 on a page
		// rendered to a guest, so it verifies for a guest.
		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$package_id = absint( $_POST['package_index'] ?? 0 );
		$quantity   = max( 1, absint( $_POST['quantity'] ?? 1 ) );
		// Both keys are accepted here for the same reason as below: 'extras' is
		// the legacy name and single-service.js posts 'addons'.
		$addons_raw = wp_unslash( $_POST['extras'] ?? $_POST['addons'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- values are sanitized with absint() on the next line.
		$addons     = ! empty( $addons_raw ) ? array_map( 'absint', (array) $addons_raw ) : array();

		$checkout_url = '';
		if ( $service_id && 'publish' === get_post_status( $service_id ) ) {
			// Exits with the honest refusal when the rail cannot check out, so
			// the guest is told now rather than after a round trip to log in.
			$checkout_url = $this->require_checkout_url( $service_id, $package_id, $addons, $quantity );
		}

		if ( $checkout_url && wpss_checkout_creates_accounts() ) {
			wp_send_json_success(
				array(
					'checkout_url'   => $checkout_url,
					// Tells the client nothing was added to a cart, so it
					// navigates instead of flashing "Added" at someone whose
					// cart does not exist.
					'guest_checkout' => true,
				)
			);
		}

		wp_send_json_error(
			array(
				'message'   => __( 'Please log in to continue to checkout.', 'wp-sell-services' ),
				'login_url' => wp_login_url( $checkout_url ?: ( wp_get_referer() ?: home_url() ) ),
			)
		);
	}

	/**
	 * The checkout URL for a selection, or refuse when the rail cannot build one.
	 *
	 * A store rail that has no catalog checkout returns an empty string from its
	 * checkout provider. Carrying on regardless is what made the dead end silent:
	 * the buyer got "Added to cart", a cart row, and a Checkout link with an
	 * empty href, which reloaded the service page forever with no error anywhere
	 * (Basecamp 10268056147, 10268056083).
	 *
	 * The refusal lives here, at the one seam every add-to-cart path goes
	 * through, so a rail is honest by default rather than by special case.
	 *
	 * Exits when no URL can be built.
	 *
	 * @since 1.7.1
	 *
	 * @param int             $service_id Service CPT ID.
	 * @param int             $package_id Package index.
	 * @param array<int, int> $addons     Selected addon indexes.
	 * @param int             $quantity   Quantity, appended when above one.
	 * @return string
	 */
	private function require_checkout_url( int $service_id, int $package_id, array $addons, int $quantity = 1 ): string {
		$checkout_url = wpss_get_service_checkout_url( $service_id, $package_id, $addons );

		if ( '' === $checkout_url ) {
			$adapter = wpss_get_active_adapter();

			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: name of the active store platform, e.g. Fluent Cart */
						__( 'Checkout is not available for this service on %s, so nothing was added to your cart. Please let the site owner know.', 'wp-sell-services' ),
						$adapter ? $adapter->get_name() : __( 'this store platform', 'wp-sell-services' )
					),
				)
			);
		}

		return $quantity > 1 ? add_query_arg( 'quantity', $quantity, $checkout_url ) : $checkout_url;
	}

	/**
	 * Add service to cart.
	 *
	 * Handles adding a service with selected package and extras to the standalone cart.
	 *
	 * @return void
	 */
	public function add_service_to_cart(): void {
		if ( ! is_user_logged_in() ) {
			$this->handle_guest_checkout_intent();
		}

		check_ajax_referer( 'wpss_service_nonce', 'nonce' );

		$service_id    = absint( $_POST['service_id'] ?? 0 );
		$package_index = sanitize_text_field( wp_unslash( $_POST['package_index'] ?? '0' ) );
		$quantity      = absint( $_POST['quantity'] ?? 1 );
		// Accept both 'extras' (legacy) and 'addons' (single-service.js sends this key).
		$extras_raw = wp_unslash( $_POST['extras'] ?? $_POST['addons'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- values are sanitized with absint() on the next line.
		$extras     = ! empty( $extras_raw ) ? array_map( 'absint', (array) $extras_raw ) : array();

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		// Check if e-commerce adapter is active.
		$adapter = wpss_get_active_adapter();
		if ( ! $adapter ) {
			wp_send_json_error( array( 'message' => __( 'No e-commerce platform is active. Please check your settings.', 'wp-sell-services' ) ) );
		}

		// Get the service.
		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
		}

		// Cannot buy own service.
		if ( (int) $service->post_author === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot purchase your own service.', 'wp-sell-services' ) ) );
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
					'delivery_time' => wpss_get_service_delivery_days( $service_id ),
				),
			);
		}

		// Validate package index.
		if ( ! isset( $packages[ $package_index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package selected.', 'wp-sell-services' ) ) );
		}

		$selected_package = $packages[ $package_index ];
		$package_price    = (float) ( $selected_package['price'] ?? 0 );

		// Calculate extras price.
		$all_extras      = wpss_get_service_extras( $service_id );
		$extras_price    = 0;
		$extras_days     = 0;
		$selected_extras = array();

		foreach ( $extras as $extra_index ) {
			if ( isset( $all_extras[ $extra_index ] ) ) {
				$extras_price     += (float) ( $all_extras[ $extra_index ]['price'] ?? 0 );
				$extras_days      += (int) $all_extras[ $extra_index ]['delivery_days_extra'];
				$selected_extras[] = array(
					'id'    => $extra_index,
					'title' => $all_extras[ $extra_index ]['title'] ?? '',
					'price' => (float) ( $all_extras[ $extra_index ]['price'] ?? 0 ),
				);
			}
		}

		$total = ( $package_price + $extras_price ) * $quantity;

		$cart_item = array(
			'service_id' => $service_id,
			'package_id' => $package_index,
			'package'    => $selected_package,
			'addons'     => $selected_extras,
			'quantity'   => $quantity,
			'total'      => $total,
		);

		/**
		 * Filter to let e-commerce adapters handle cart addition natively.
		 *
		 * When a non-standalone adapter (e.g. WooCommerce) is active, it should
		 * handle adding the service to its own cart system instead of the
		 * standalone user-meta cart.
		 *
		 * Return an array with 'handled' => true to skip standalone cart logic.
		 * Optionally include 'checkout_url' and 'cart_count' in the returned array.
		 *
		 * @since 1.3.0
		 *
		 * @param array|false                                               $result    False by default (not handled).
		 * @param array                                                     $cart_item Cart item data.
		 * @param \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null $adapter   Active adapter.
		 */
		// Resolved before anything is written to any cart: a rail that cannot
		// check this service out refuses here instead of leaving the buyer with
		// a cart row and a Checkout button that goes nowhere.
		$checkout_url = $this->require_checkout_url( $service_id, (int) $package_index, $extras, $quantity );

		$adapter_result = apply_filters( 'wpss_add_service_to_cart', false, $cart_item, $adapter );

		if ( is_array( $adapter_result ) && ! empty( $adapter_result['handled'] ) ) {
			// Adapter handled the cart addition (e.g. WooCommerce, EDD).
			if ( ! empty( $adapter_result['error'] ) ) {
				wp_send_json_error( array( 'message' => $adapter_result['error'] ) );
			}

			if ( ! empty( $adapter_result['checkout_url'] ) ) {
				$checkout_url = (string) $adapter_result['checkout_url'];
				if ( $quantity > 1 ) {
					$checkout_url = add_query_arg( 'quantity', $quantity, $checkout_url );
				}
			}

			wp_send_json_success(
				array(
					'message'      => __( 'Added to cart!', 'wp-sell-services' ),
					'cart_count'   => $adapter_result['cart_count'] ?? 1,
					'checkout_url' => $checkout_url,
				)
			);
		}

		// Standalone cart: store in user meta.
		$user_id = get_current_user_id();
		$cart    = get_user_meta( $user_id, '_wpss_cart', true );

		if ( ! is_array( $cart ) ) {
			$cart = array();
		}

		$item_key              = md5( $service_id . '-' . $package_index . '-' . wp_json_encode( $extras ) );
		$cart_item['added_at'] = current_time( 'mysql', true );
		$cart[ $item_key ]     = $cart_item;

		update_user_meta( $user_id, '_wpss_cart', $cart );

		wp_send_json_success(
			array(
				'message'      => __( 'Added to cart!', 'wp-sell-services' ),
				'cart_count'   => count( $cart ),
				'checkout_url' => $checkout_url,
			)
		);
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

		// Bodies are stored as plain text, but rows written before that change
		// still carry email markup — reduce them on the way out, exactly as the
		// REST controller does, so no client has to strip tags itself.
		foreach ( $notifications as $wpss_notification ) {
			if ( isset( $wpss_notification->message ) ) {
				$wpss_notification->message = \WPSellServices\Services\NotificationMessage::to_plain( (string) $wpss_notification->message );
			}
		}

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

		// Cannot buy own service.
		$service = get_post( $service_id );
		if ( $service && (int) $service->post_author === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot purchase your own service.', 'wp-sell-services' ) ) );
		}

		// Update standalone cart (user meta).
		$user_id = get_current_user_id();
		$cart    = get_user_meta( $user_id, '_wpss_cart', true );

		if ( is_array( $cart ) ) {
			foreach ( $cart as $cart_key => $cart_item ) {
				if ( isset( $cart_item['service_id'] ) && (int) $cart_item['service_id'] === $service_id ) {
					$cart[ $cart_key ]['package_id'] = $package_index;
					$cart[ $cart_key ]['quantity']   = $quantity;

					update_user_meta( $user_id, '_wpss_cart', $cart );

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
		check_ajax_referer( 'wpss_requirements_nonce', 'nonce' );

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

		// Advance order to in_progress so the vendor can start working.
		if ( ServiceOrder::STATUS_PENDING_REQUIREMENTS === $order->status ) {
			$order_service->start_work( $order_id );
		}

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
		$attributes = isset( $_POST['attributes'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['attributes'] ) ), true ) : array();

		// Shared renderer — same code path as the REST grid endpoint so card
		// markup + extension hooks stay identical across both transports.
		$grid = wpss_render_services_grid( is_array( $attributes ) ? $attributes : array(), $page );

		wp_send_json_success(
			array(
				'html'       => $grid['html'],
				'pagination' => $grid['pagination'],
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
		global $wpdb;

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );

		// Accept nonce from both dashboard context and order detail page context.
		if ( ! wp_verify_nonce( $nonce, 'wpss_dashboard_nonce' ) && ! wp_verify_nonce( $nonce, 'wpss_order_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-sell-services' ) ) );
		}

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

		// One allow map for every verb: who may say it, and which status it
		// moves the order to. Being a participant is not enough - a buyer
		// could refund themselves and a vendor could cancel a completed order,
		// reversing their own credit. Refunds are the owner's call unless the
		// site explicitly lets vendors issue them; buyers cancel only before
		// work starts (vendors use the cancellation-request flow instead).
		$actor   = wpss_order_actor_role( $order, $user_id );
		$allowed = array(
			'start'               => array( array( 'vendor' ), ServiceOrder::STATUS_IN_PROGRESS ),
			'cancel'              => array( array( 'buyer', 'admin' ), ServiceOrder::STATUS_CANCELLED ),
			'refund'              => array(
				wpss_get_option( 'orders', 'allow_vendor_refunds' ) ? array( 'admin', 'vendor' ) : array( 'admin' ),
				ServiceOrder::STATUS_REFUNDED,
			),
			'accept-cancellation' => array( array( 'vendor' ), ServiceOrder::STATUS_CANCELLED ),
			'reject-cancellation' => array( array( 'vendor' ), ServiceOrder::STATUS_DISPUTED ),
		);

		if ( ! isset( $allowed[ $action ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'wp-sell-services' ) ) );
		}

		list( $actor_roles, $target_status ) = $allowed[ $action ];

		if ( ! in_array( $actor, $actor_roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// Admins may force a transition (can_transition() honours the cap and
		// audits it); everyone else needs the natural map, and a buyer's cancel
		// is narrower still - only while nothing has been started.
		if ( 'buyer' === $actor && 'cancel' === $action ) {
			$status_ok = in_array(
				$order->status,
				array( ServiceOrder::STATUS_PENDING_PAYMENT, ServiceOrder::STATUS_PENDING_REQUIREMENTS, ServiceOrder::STATUS_REQUIREMENTS_SUBMITTED ),
				true
			);
		} else {
			$status_ok = 'admin' === $actor || $order_service->can_transition_naturally( $order->status, $target_status );
		}

		if ( ! $status_ok ) {
			wp_send_json_error( array( 'message' => __( 'This action is not available in the order\'s current status.', 'wp-sell-services' ) ) );
		}

		$result = array( 'success' => false );

		switch ( $action ) {
			case 'start':
				$result['success'] = $order_service->start_work( $order_id );
				break;

			case 'cancel':
				// Assign to ['success'], not over the whole array.
				// update_status() returns a BOOL, so `$result = ...` left the
				// check below reading an array offset on a bool - always empty -
				// and every successful cancellation answered "Action failed"
				// while having actually cancelled the order.
				$result['success'] = $order_service->update_status( $order_id, 'cancelled' );
				break;

			case 'refund':
				// Refundable iff the buyer paid — at any workflow stage. One
				// authority (wpss_order_is_refundable) governs every refund
				// surface so the AJAX path, the admin buttons and any future
				// caller cannot disagree about which orders qualify.
				if ( wpss_order_is_refundable( $order ) ) {
					// How much is going back. Absent or zero means the whole order.
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified at the top of this handler; the (float) cast sanitizes the numeric amount.
					$wpss_refund_amount = isset( $_POST['refund_amount'] ) ? (float) wp_unslash( $_POST['refund_amount'] ) : 0.0;
					$wpss_order_total   = (float) $order->total;
					$wpss_is_partial    = $wpss_refund_amount > 0 && $wpss_refund_amount < $wpss_order_total;

					// The amount must be on the row before the status hook runs
					// — the refund handlers read it there to size both the buyer
					// refund and the vendor's reversal. Writing it here and
					// transitioning separately left the column claiming a refund
					// whenever the transition was refused; apply_refund_status()
					// owns that ordering and undoes the write if the order does
					// not actually move.
					$result['success'] = $order_service->apply_refund_status(
						$order_id,
						$wpss_is_partial ? round( $wpss_refund_amount, wpss_get_currency_decimals( $order->currency ?? '' ) ) : $wpss_order_total,
						$wpss_is_partial ? 'partially_refunded' : 'refunded'
					);
				} else {
					wp_send_json_error( array( 'message' => __( 'Order cannot be refunded in its current status.', 'wp-sell-services' ) ) );
				}
				break;

			case 'accept-cancellation':
				if ( 'cancellation_requested' === $order->status ) {
					$result = $order_service->cancel( $order_id, $user_id, __( 'Vendor accepted cancellation request.', 'wp-sell-services' ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Cannot accept cancellation.', 'wp-sell-services' ) ) );
				}
				break;

			case 'reject-cancellation':
				if ( 'cancellation_requested' === $order->status ) {
					$dispute_service = new DisputeService();
					$dispute_result  = $dispute_service->open(
						$order_id,
						$user_id,
						__( 'Cancellation Dispute', 'wp-sell-services' ),
						__( 'Vendor disputed the buyer cancellation request.', 'wp-sell-services' )
					);
					$result          = array( 'success' => (bool) $dispute_result );
					if ( ! $dispute_result ) {
						$result['message'] = __( 'Failed to create dispute.', 'wp-sell-services' );
					}
				} else {
					wp_send_json_error( array( 'message' => __( 'Cannot dispute cancellation.', 'wp-sell-services' ) ) );
				}
				break;
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

		// WP_Filesystem abstracts FILES; this is the php://output stream opened
		// above to stream a CSV straight to the browser, which it has no
		// equivalent for. fclose() is the only way to close it.
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a filesystem path.
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

		if ( ! empty( $withdrawal->is_auto ) ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => __( 'Auto-withdrawals cannot be cancelled manually.', 'wp-sell-services' ) ) );
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

	/**
	 * Update vendor/customer profile from the unified dashboard.
	 *
	 * Handles both vendor profiles (with vendor-specific fields like tagline, bio)
	 * and regular customer profiles (display name only).
	 *
	 * @return void
	 */
	/**
	 * Save per-vendor email preferences.
	 *
	 * Stores a key=>bool array in user meta `wpss_email_preferences`. Missing
	 * key OR true means "send"; explicit false means "mute". Categories map
	 * to email types in EmailService::is_email_type_enabled().
	 *
	 * VS11 from plans/ORDER-FLOW-AUDIT.md.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function save_email_preferences(): void {
		check_ajax_referer( 'wpss_save_email_prefs', 'wpss_email_prefs_nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		// Only the categories this user was actually OFFERED.
		//
		// The list is role-aware now (Basecamp #10159633379), so a buyer's form
		// carries no tips / withdrawals / proposals checkboxes. This used to
		// iterate a hardcoded list of all eight and read "checkbox absent" as
		// "explicitly muted" - which would have recorded those three as OFF for
		// every buyer who ever saved the form, and left them silently muted if
		// that person later became a vendor, with nothing in the UI to explain
		// why their sales mail had stopped.
		//
		// Preferences the form did not show are preserved as they were stored.
		$valid_keys = array_keys( wpss_get_email_preference_categories( $user_id ) );

		$existing = get_user_meta( $user_id, 'wpss_email_preferences', true );
		$existing = is_array( $existing ) ? $existing : array();

		$submitted   = isset( $_POST['prefs'] ) && is_array( $_POST['prefs'] ) ? wp_unslash( $_POST['prefs'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Whitelisted booleans below.
		$preferences = $existing;
		foreach ( $valid_keys as $key ) {
			// Checkbox is present only if checked. Absence = explicit false (muted).
			$preferences[ $key ] = isset( $submitted[ $key ] );
		}

		update_user_meta( $user_id, 'wpss_email_preferences', $preferences );

		// Verify persistence instead of trusting update_user_meta()'s return
		// (false also means "value unchanged"). Read back and compare so a
		// DB-level failure surfaces as an error, not a fake success
		// (Basecamp #9983538201).
		wp_cache_delete( $user_id, 'user_meta' );
		$persisted = get_user_meta( $user_id, 'wpss_email_preferences', true );

		if ( $persisted !== $preferences ) {
			wp_send_json_error(
				array( 'message' => __( 'Your preferences could not be saved. Please try again.', 'wp-sell-services' ) ),
				500
			);
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Preferences saved.', 'wp-sell-services' ),
				'preferences' => $preferences,
			)
		);
	}

	/**
	 * AJAX handler for the dashboard profile editor.
	 *
	 * Receives `multipart/form-data` from the profile section, applies updates
	 * to the WordPress user (display name, avatar via custom upload) and to
	 * the vendor profile model (tagline, bio, country, intro video, vacation
	 * mode). Validation, sanitization, and capability checks happen here
	 * because the form mixes core-user fields and vendor-specific fields in
	 * one submission. Responds with `wp_send_json_*`.
	 *
	 * @return void
	 */
	public function update_vendor_profile(): void {
		check_ajax_referer( 'wpss_update_profile', 'wpss_profile_nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ) );
		}

		// Billing address — available to ALL users, not just vendors, because a
		// buyer needs one for invoices and they never see the vendor fields.
		// Shares the exact helper the checkout save-back uses, so both surfaces
		// write the same WooCommerce-compatible keys with the same sanitising.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		wpss_save_billing_from_request( $_POST, $user_id );

		// Update display name (available for all users).
		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		if ( ! empty( $display_name ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
				)
			);
		}

		// Update avatar (available for all users).
		$avatar_id = absint( $_POST['avatar_id'] ?? 0 );
		if ( $avatar_id > 0 ) {
			update_user_meta( $user_id, '_wpss_avatar_id', $avatar_id );
		} else {
			delete_user_meta( $user_id, '_wpss_avatar_id' );
		}

		// Check if user is a vendor (canonical capability/role check - role-based
		// vendors do not always carry the _wpss_is_vendor meta).
		//
		// A raw get_user_meta( '_wpss_is_vendor' ) read sat directly above this
		// line, its result overwritten one statement later. Dead since the
		// canonical check was added; removed so nobody reinstates it by reading
		// the first line and stopping.
		$is_vendor = wpss_is_vendor( $user_id );

		if ( $is_vendor ) {
			// Build the field set from the posted form, then persist through the
			// canonical VendorService::update_profile() (writes the
			// wpss_vendor_profiles table) - the same path the REST twin uses, so
			// both transports stay byte-identical and write to one store.
			$cover_id  = absint( $_POST['cover_id'] ?? 0 );
			$post_data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked at top; each field sanitized individually inside wpss_build_vendor_profile_update().

			// The composer posts the whole form, so an absent vacation_mode
			// checkbox means "off" - make that explicit so the builder writes 0.
			$post_data['vacation_mode'] = empty( $post_data['vacation_mode'] ) ? 0 : 1;

			$profile_data = wpss_build_vendor_profile_update( $post_data, $avatar_id, $cover_id );

			if ( ! empty( $profile_data ) ) {
				$updated = ( new VendorService() )->update_profile( $user_id, $profile_data );

				if ( ! $updated ) {
					wp_send_json_error(
						array( 'message' => __( 'Your profile could not be saved. Please try again or contact support.', 'wp-sell-services' ) ),
						500
					);
				}
			}

			/**
			 * Fires after a vendor profile is saved from a frontend form.
			 *
			 * Receives the unslashed submitted fields so extensions can persist
			 * their own profile inputs (e.g. Pro's PayPal payout email).
			 * Consumers MUST sanitize the values they read.
			 *
			 * @since 1.2.0
			 *
			 * @param int   $user_id   Vendor user ID.
			 * @param array $post_data Unslashed submitted form fields.
			 */
			do_action( 'wpss_vendor_profile_saved', $user_id, $post_data );
		}

		wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'wp-sell-services' ) ) );
	}

	/**
	 * Add portfolio item via AJAX.
	 *
	 * @return void
	 */
	public function add_portfolio_item(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! wpss_is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$data = array(
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'external_url' => esc_url_raw( wp_unslash( $_POST['external_url'] ?? '' ) ),
			'is_featured'  => ! empty( $_POST['is_featured'] ),
			'service_id'   => isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0,
		);

		// Handle media (JSON array of attachment IDs).
		if ( ! empty( $_POST['media'] ) ) {
			$media_raw = sanitize_text_field( wp_unslash( $_POST['media'] ) );
			$media     = json_decode( $media_raw, true );
			if ( is_array( $media ) ) {
				$data['media'] = array_map( 'absint', $media );
			}
		}

		// Handle tags (comma-separated string).
		if ( ! empty( $_POST['tags'] ) ) {
			$tags_raw     = sanitize_text_field( wp_unslash( $_POST['tags'] ) );
			$data['tags'] = array_map( 'trim', explode( ',', $tags_raw ) );
		}

		$portfolio_service = new \WPSellServices\Services\PortfolioService();
		$result            = $portfolio_service->create( $user_id, $data );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Update portfolio item via AJAX.
	 *
	 * @return void
	 */
	public function update_portfolio_item(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! wpss_is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid portfolio item.', 'wp-sell-services' ) ) );
		}

		$portfolio_service = new \WPSellServices\Services\PortfolioService();

		// Verify ownership.
		$item = $portfolio_service->get( $item_id );
		if ( ! $item || (int) $item['vendor_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Portfolio item not found.', 'wp-sell-services' ) ) );
		}

		$data = array();

		if ( isset( $_POST['title'] ) ) {
			$data['title'] = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		}
		if ( isset( $_POST['description'] ) ) {
			$data['description'] = wp_kses_post( wp_unslash( $_POST['description'] ) );
		}
		if ( isset( $_POST['external_url'] ) ) {
			$data['external_url'] = esc_url_raw( wp_unslash( $_POST['external_url'] ) );
		}
		if ( isset( $_POST['is_featured'] ) ) {
			$data['is_featured'] = ! empty( $_POST['is_featured'] );
		}
		if ( isset( $_POST['service_id'] ) ) {
			$data['service_id'] = absint( $_POST['service_id'] );
		}

		// Handle media (JSON array of attachment IDs).
		if ( isset( $_POST['media'] ) ) {
			$media_raw = sanitize_text_field( wp_unslash( $_POST['media'] ) );
			$media     = json_decode( $media_raw, true );
			if ( is_array( $media ) ) {
				$data['media'] = array_map( 'absint', $media );
			}
		}

		// Handle tags (comma-separated string).
		if ( isset( $_POST['tags'] ) ) {
			$tags_raw     = sanitize_text_field( wp_unslash( $_POST['tags'] ) );
			$data['tags'] = array_map( 'trim', explode( ',', $tags_raw ) );
		}

		$result = $portfolio_service->update( $item_id, $data );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Delete portfolio item via AJAX.
	 *
	 * @return void
	 */
	public function delete_portfolio_item(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! wpss_is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid portfolio item.', 'wp-sell-services' ) ) );
		}

		$portfolio_service = new \WPSellServices\Services\PortfolioService();
		$result            = $portfolio_service->delete( $item_id, $user_id );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Remove a single item from the standalone cart (AJAX).
	 *
	 * Expects POST: nonce (wpss_cart_nonce), item_key (string).
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	public function remove_cart_item(): void {
		check_ajax_referer( 'wpss_cart_nonce', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';

		if ( ! $item_key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cart item.', 'wp-sell-services' ) ) );
		}

		$user_id = get_current_user_id();
		$cart    = get_user_meta( $user_id, '_wpss_cart', true );

		if ( ! is_array( $cart ) ) {
			$cart = array();
		}

		if ( isset( $cart[ $item_key ] ) ) {
			unset( $cart[ $item_key ] );

			if ( empty( $cart ) ) {
				delete_user_meta( $user_id, '_wpss_cart' );
			} else {
				update_user_meta( $user_id, '_wpss_cart', $cart );
			}
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Item removed from cart.', 'wp-sell-services' ),
				'cart_count' => count( $cart ),
			)
		);
	}

	/**
	 * AJAX: Vendor submits a paid extension request.
	 *
	 * Creates the wpss_extension_requests row and a pending_payment
	 * sub-order on wpss_orders via ExtensionOrderService, then returns the
	 * checkout URL so the buyer can be linked to payment. The sub-order
	 * carries the money; the extension_requests row carries the history.
	 *
	 * @return void
	 */
	public function ajax_request_extension(): void {
		check_ajax_referer( 'wpss_request_extension' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$order_id   = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$amount     = isset( $_POST['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : 0.0;
		$extra_days = isset( $_POST['extra_days'] ) ? absint( wp_unslash( $_POST['extra_days'] ) ) : 0;
		$reason     = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing order.', 'wp-sell-services' ) ) );
		}

		$service = new \WPSellServices\Services\ExtensionOrderService();
		$result  = $service->create_extension_request( $order_id, $amount, $extra_days, get_current_user_id(), $reason );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success(
			array(
				'message'      => $result['message'],
				'request_id'   => $result['request_id'],
				'pay_order_id' => $result['pay_order_id'],
				'checkout_url' => $result['checkout_url'],
			)
		);
	}

	/**
	 * AJAX: Buyer declines a pending extension request.
	 *
	 * Cancels the pending sub-order (so buyer isn't charged and the vendor
	 * can raise a revised one) and marks the extension_requests row rejected.
	 *
	 * @return void
	 */
	public function ajax_decline_extension(): void {
		check_ajax_referer( 'wpss_decline_extension' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$request_id = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0;
		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing request.', 'wp-sell-services' ) ) );
		}

		$service = new \WPSellServices\Services\ExtensionOrderService();
		$result  = $service->decline( $request_id, get_current_user_id(), $note );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX: Vendor proposes a milestone on an active order.
	 *
	 * @return void
	 */
	public function ajax_propose_milestone(): void {
		check_ajax_referer( 'wpss_propose_milestone' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$order_id     = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$deliverables = isset( $_POST['deliverables'] ) ? sanitize_textarea_field( wp_unslash( $_POST['deliverables'] ) ) : '';
		$amount       = isset( $_POST['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : 0.0;
		$days         = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing order.', 'wp-sell-services' ) ) );
		}

		$service = new \WPSellServices\Services\MilestoneService();
		$result  = $service->propose( $order_id, get_current_user_id(), $title, $description, $amount, $days, $deliverables );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success(
			array(
				'message'      => $result['message'],
				'milestone_id' => $result['milestone_id'],
				'checkout_url' => $result['checkout_url'],
			)
		);
	}

	/**
	 * AJAX: Vendor submits a milestone as delivered.
	 *
	 * @return void
	 */
	public function ajax_submit_milestone(): void {
		check_ajax_referer( 'wpss_milestone_action' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$milestone_id = isset( $_POST['milestone_id'] ) ? absint( wp_unslash( $_POST['milestone_id'] ) ) : 0;
		$note         = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		$service = new \WPSellServices\Services\MilestoneService();
		$result  = $service->submit( $milestone_id, get_current_user_id(), $note );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX: Buyer approves a submitted milestone.
	 *
	 * @return void
	 */
	public function ajax_approve_milestone(): void {
		check_ajax_referer( 'wpss_milestone_action' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$milestone_id = isset( $_POST['milestone_id'] ) ? absint( wp_unslash( $_POST['milestone_id'] ) ) : 0;

		$service = new \WPSellServices\Services\MilestoneService();
		$result  = $service->approve( $milestone_id, get_current_user_id() );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX: Buyer sends a submitted phase back for changes.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	public function ajax_request_milestone_revision(): void {
		check_ajax_referer( 'wpss_milestone_action' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$milestone_id = isset( $_POST['milestone_id'] ) ? absint( wp_unslash( $_POST['milestone_id'] ) ) : 0;
		$reason       = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		$service = new \WPSellServices\Services\MilestoneService();
		$result  = $service->request_revision( $milestone_id, get_current_user_id(), $reason );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX: Buyer declines an unpaid milestone.
	 *
	 * @return void
	 */
	public function ajax_decline_milestone(): void {
		check_ajax_referer( 'wpss_milestone_action' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$milestone_id = isset( $_POST['milestone_id'] ) ? absint( wp_unslash( $_POST['milestone_id'] ) ) : 0;

		$service = new \WPSellServices\Services\MilestoneService();
		$result  = $service->decline( $milestone_id, get_current_user_id() );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX: Vendor deletes an unpaid milestone they proposed.
	 *
	 * @return void
	 */
	public function ajax_delete_milestone(): void {
		check_ajax_referer( 'wpss_milestone_action' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-sell-services' ) ), 401 );
		}

		$milestone_id = isset( $_POST['milestone_id'] ) ? absint( wp_unslash( $_POST['milestone_id'] ) ) : 0;

		$service = new \WPSellServices\Services\MilestoneService();
		$result  = $service->delete_unpaid( $milestone_id, get_current_user_id() );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}
}
