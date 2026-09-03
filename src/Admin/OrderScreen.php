<?php
/**
 * Order Screen (admin)
 *
 * Assets and AJAX handlers for the WPSS Orders admin screen.
 *
 * Previously OrderMetabox, which was two things at once. Its EIGHT metaboxes
 * were registered against post type `wpss_orders` - a post type this plugin
 * never registers, so that screen does not exist and none of them could ever
 * render. Roughly 700 lines that no user could reach.
 *
 * Its AJAX handlers, by contrast, are what the live admin order screen runs on:
 * assets/js/admin-order.js posts wpss_admin_update_order_status (status changes
 * AND the Process Refund button) and wpss_admin_add_order_note. Deleting the
 * class wholesale - as the audit plan originally said to - would have taken
 * those with it. (A submit-requirements-on-behalf handler lived here too, for
 * a form nothing rendered; requirements have one writer, RequirementsService.)
 *
 * So the dead half is gone, the live half is here under a name that says what
 * it does, and `wpss_admin_order_actions` now fires from
 * Admin::render_order_detail() instead of from the metabox that never ran.
 *
 * @package WPSellServices\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use WPSellServices\Assets\ScriptRegistry;

/**
 * Handles the admin orders screen's assets and AJAX actions.
 *
 * @since 1.0.0
 */
class OrderScreen {

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private OrderService $order_service;

	/**
	 * Constructor.
	 *
	 * The conversation and delivery repositories used to be constructed here
	 * too, but their only readers were the metabox render methods that could
	 * never run. Nothing left in this class touches them.
	 */
	public function __construct() {
		$this->order_repo    = new OrderRepository();
		$this->order_service = new OrderService();
	}

	/**
	 * Initialize metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpss_admin_update_order_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_wpss_admin_add_order_note', array( $this, 'ajax_add_note' ) );
	}

	/**
	 * Enqueue metabox assets.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		// The orders admin page. The hook suffix survives a white-labeled parent
		// menu, which is why this matches on the suffix rather than a fixed id.
		//
		// A second branch here also matched screen id 'wpss_orders', the metabox
		// screen for a post type that was never registered. It could not match.
		if ( ! str_ends_with( $screen_id, '_page_wpss-orders' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpss-order-metabox',
			\WPSS_PLUGIN_URL . 'assets/css/orders.css',
			array(),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-order-metabox', 'rtl', 'replace' );

		// Shared design-system primitives (wpssConfirm / wpssToast). Same
		// handle + src as Admin::enqueue_scripts() — no-op when already queued.
		ScriptRegistry::enqueue_ui();

		ScriptRegistry::enqueue(
			'wpss-order-metabox',
			'assets/js/admin-order.js',
			array( 'jquery', ScriptRegistry::HANDLE_UI )
		);

		wp_localize_script(
			'wpss-order-metabox',
			'wpssOrderAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_order_admin' ),
				'i18n'    => array(
					'confirmStatusChange'  => __( 'Are you sure you want to change the order status?', 'wp-sell-services' ),
					'confirmRefund'        => __( 'Refund this order? The gateway payment will be refunded where supported.', 'wp-sell-services' ),
					'confirmPartialRefund' => __( 'Issue this partial refund? The buyer is refunded the amount entered and the vendor\'s proportional share is clawed back.', 'wp-sell-services' ),
					'refund'               => __( 'Refund', 'wp-sell-services' ),
					'noteAdded'            => __( 'Note added successfully.', 'wp-sell-services' ),
					'requirementsSaved'    => __( 'Requirements saved successfully.', 'wp-sell-services' ),
					'error'                => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'enterNote'            => __( 'Please enter a note.', 'wp-sell-services' ),
					'update'               => __( 'Update', 'wp-sell-services' ),
					'updating'             => __( 'Updating...', 'wp-sell-services' ),
					'savingRequirements'   => __( 'Saving...', 'wp-sell-services' ),
				),
			)
		);
	}



	/**
	 * AJAX: Update order status.
	 *
	 * @return void
	 */
	public function ajax_update_status(): void {
		check_ajax_referer( 'wpss_order_admin', 'nonce' );

		if ( ! current_user_can( 'wpss_manage_orders' ) && ! current_user_can( 'wpss_vendor_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $order_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// Non-admin users must be the vendor on the order.
		if ( ! current_user_can( 'manage_options' ) ) {
			$order_check = $this->order_service->get( $order_id );
			if ( ! $order_check || (int) $order_check->vendor_id !== get_current_user_id() ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
			}
		}

		// A refund is a money action, not a bare status flip: apply_refund_status()
		// sizes the amount, writes refunded_amount BEFORE the transition (the
		// refund/reversal hooks read it there) and undoes the write if the move is
		// refused. An empty or full amount is a full refund; a smaller value is a
		// partial, and the status is resolved accordingly so the admin never has
		// to pick "partially_refunded" by hand. Any non-refund status keeps the
		// plain update_status() path.
		if ( in_array( $status, array( 'refunded', 'partially_refunded' ), true ) ) {
			$order = $this->order_service->get( $order_id );

			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
			}

			if ( ! wpss_order_is_refundable( $order ) ) {
				wp_send_json_error( array( 'message' => __( 'This order cannot be refunded in its current state.', 'wp-sell-services' ) ) );
			}

			$order_total = (float) $order->total;
			// Cast to float IS the sanitisation for a money field; there is no
			// sanitize_* that returns a float. Nonce is verified above.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$amount     = isset( $_POST['refund_amount'] ) ? (float) wp_unslash( $_POST['refund_amount'] ) : 0.0;
			$is_partial = $amount > 0 && $amount < $order_total;

			$result = $this->order_service->apply_refund_status(
				$order_id,
				$is_partial ? round( $amount, 2 ) : $order_total,
				$is_partial ? 'partially_refunded' : 'refunded'
			);

			// The status moved, but did the money? apply_refund_status() stays
			// a bool for its other callers; the gateway outcome is read back
			// here so a failed refund is an error the admin sees, not a
			// silent "Status updated". A manual (offline) outcome reloads into
			// the pending-refund box on this screen.
			$outcome = $result ? \WPSellServices\Services\OrderWorkflowManager::get_last_refund_result( $order_id ) : null;

			if ( is_array( $outcome ) && empty( $outcome['success'] ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: gateway error message */
							__( 'The order is marked refunded, but the gateway refund failed: %s. Refund the buyer from your gateway dashboard.', 'wp-sell-services' ),
							(string) ( $outcome['message'] ?? __( 'Unknown error', 'wp-sell-services' ) )
						),
					)
				);
			}
		} else {
			// Use OrderService instead of repository to ensure hooks fire.
			// This triggers wpss_order_status_changed and wpss_order_status_{status} hooks
			// which are needed for commission recording, notifications, etc.
			$result = $this->order_service->update_status( $order_id, $status );
		}

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Status updated successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * AJAX: Add admin note.
	 *
	 * @return void
	 */
	public function ajax_add_note(): void {
		check_ajax_referer( 'wpss_order_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $order_id || ! $note ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$row = $this->order_repo->find( $order_id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		$order = ServiceOrder::from_db( $row );

		$notes   = $order->get_admin_notes() ?: array();
		$notes[] = array(
			'content'    => $note,
			'author_id'  => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		);

		// Store admin notes in the meta JSON column (no admin_notes column exists).
		$meta                = $order->meta;
		$meta['admin_notes'] = $notes;
		$this->order_repo->update( $order_id, array( 'meta' => wp_json_encode( $meta ) ) );

		wp_send_json_success(
			array(
				'message' => __( 'Note added successfully.', 'wp-sell-services' ),
				'note'    => array(
					'content'    => $note,
					'author'     => wp_get_current_user()->display_name,
					'created_at' => wp_date( 'M j, g:i a' ),
				),
			)
		);
	}
}
