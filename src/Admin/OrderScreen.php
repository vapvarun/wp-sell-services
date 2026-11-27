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
		add_action( 'wp_ajax_wpss_admin_mark_order_paid', array( $this, 'ajax_mark_paid' ) );
		add_action( 'wp_ajax_wpss_review_payment_receipt', array( $this, 'ajax_review_payment_receipt' ) );
		add_action( 'wpss_admin_order_actions', array( $this, 'render_admin_order_actions' ), 10, 2 );
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

		// The order detail renders the order view's partials (Basecamp 10337161480).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen switch.
		if ( isset( $_GET['action'] ) && 'view' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			wpss_enqueue_order_view_style();
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
					'confirmRefund'        => __( 'Refund this order? The gateway payment will be refunded where supported.', 'wp-sell-services' ),
					'confirmPartialRefund' => __( 'Issue this partial refund? The buyer is refunded the amount entered and the vendor\'s proportional share is clawed back.', 'wp-sell-services' ),
					'refund'               => __( 'Refund', 'wp-sell-services' ),
					'error'                => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'confirmMarkPaid'      => __( 'Are you sure you want to mark this order as paid?', 'wp-sell-services' ),
					'processing'           => __( 'Processing...', 'wp-sell-services' ),
					'markPaid'             => __( 'Mark as paid', 'wp-sell-services' ),
					'requestFailed'        => __( 'Request failed. Please try again.', 'wp-sell-services' ),
					'rejectNeedsNote'      => __( 'Give the buyer a reason so they know what to send instead.', 'wp-sell-services' ),
					'decisionFailed'       => __( 'Could not record that decision.', 'wp-sell-services' ),
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

		// Same rule as the order form and the Refund box: status changes move money, so administrators only.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $order_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// A refund is a money action, not a bare status flip: refund() sizes the
		// amount, asks the gateway first, and records the refund only when the
		// money moved (or is an honest manual refund). An empty or full amount is a full refund; a smaller value is a
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

			$refund = $this->order_service->refund(
				$order_id,
				$is_partial ? round( $amount, 2 ) : $order_total,
				$is_partial ? 'partially_refunded' : 'refunded',
				array( 'origin' => 'admin' )
			);

			// refund() asks the gateway before the order moves, so a refusal
			// leaves the order as it was and flags it for Retry. Say so, with
			// the gateway's own words - not "Status updated".
			if ( ! $refund['ok'] ) {
				wp_send_json_error(
					array(
						'message' => 'failed' === $refund['outcome']
							? sprintf(
								/* translators: %s: gateway error message */
								__( 'The refund did not go through: %s. Nothing was changed on the order; use Retry refund once the problem is fixed.', 'wp-sell-services' ),
								$refund['message']
							)
							: $refund['message'],
					)
				);
			}

			$result = true;
		} else {
			if ( ! isset( \WPSellServices\Services\OrderService::get_settable_statuses()[ $status ] ) ) {
				wp_send_json_error( array( 'message' => __( 'That status cannot be set directly. Refunds and disputes have their own actions.', 'wp-sell-services' ) ) );
			}

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
			'created_at' => current_time( 'mysql', true ),
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
					'created_at' => wpss_date_label( time(), '', 'M j, g:i a' ),
				),
			)
		);
	}

	/**
	 * AJAX: Admin marks order as paid.
	 *
	 * @return void
	 */
	public function ajax_mark_paid(): void {
		check_ajax_referer( 'wpss_admin_mark_paid', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int is sanitization.
		$order_id       = isset( $_POST['order_id'] ) ? (int) wp_unslash( $_POST['order_id'] ) : 0;
		$transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';

		$result = ( new \WPSellServices\Services\ManualPaymentService() )->settle( $order_id, $transaction_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Order marked as paid successfully.', 'wp-sell-services' ),
				'new_status' => $result['status'],
			)
		);
	}

	/**
	 * Mark as paid, and any proof the buyer sent, for an order paid through a manual method.
	 *
	 * @since 2.0.0 Moved from OfflineGateway, so every manual method has it (Basecamp 10551488242).
	 *
	 * @param object $order  Order object.
	 * @param string $status Current order status.
	 * @return void
	 */
	public function render_admin_order_actions( object $order, string $status ): void {
		// Manual-method orders awaiting payment, and unpaid manual sub-orders whatever their status.
		$is_sub_order_door = ( new \WPSellServices\Services\ManualPaymentService() )->is_unpaid_sub_order( $order );

		if ( ! $is_sub_order_door && ( ! wpss_is_manual_payment_method( (string) ( $order->payment_method ?? '' ) ) || 'pending_payment' !== $status ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'wpss_admin_mark_paid' );
		?>
		<div class="wpss-offline-admin-actions" style="background: #fff3cd; border: 1px solid #ffc107; padding: 16px; border-radius: 4px; margin: 16px 0;">
			<h4 style="margin-top: 0; color: #856404;">
				<?php esc_html_e( 'Offline Payment - Awaiting Confirmation', 'wp-sell-services' ); ?>
			</h4>
			<p style="color: #856404; margin-bottom: 12px;">
				<?php esc_html_e( 'This order is awaiting offline payment. Mark it as paid once you receive the payment.', 'wp-sell-services' ); ?>
			</p>

			<?php
			// Buyer-submitted proof, and the decision on it (Basecamp #10194890682).
			//
			// Rendered INSIDE the existing Payment Actions box rather than as a
			// new screen: an admin deciding whether money arrived wants the
			// evidence and the Mark-as-Paid control in one place, not two.
			//
			// Approving routes through PaymentReceiptService::verify(), which
			// settles via the same mark_as_paid() path this box already uses -
			// one money-writing path, not two.
			$this->render_receipt_review( (int) $order->id );
			?>
			<div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
				<div>
					<label for="wpss-transaction-id" style="display: block; margin-bottom: 4px; font-weight: 500;">
						<?php esc_html_e( 'Transaction ID (optional)', 'wp-sell-services' ); ?>
					</label>
					<input type="text"
						id="wpss-transaction-id"
						name="transaction_id"
						placeholder="<?php esc_attr_e( 'Bank ref, receipt #, etc.', 'wp-sell-services' ); ?>"
						style="width: 200px;">
				</div>
				<button type="button"
					class="button button-primary wpss-mark-paid-btn"
					data-order-id="<?php echo esc_attr( $order->id ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Mark as paid', 'wp-sell-services' ); ?>
				</button>
			</div>
		</div>

		<?php
	}

	/**
	 * Render buyer-submitted payment proof and the approve / reject controls.
	 *
	 * Nothing renders when the feature is off or no proof has been submitted -
	 * an admin on a card-only marketplace should never see this.
	 *
	 * @since 1.6.0
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function render_receipt_review( int $order_id ): void {
		if ( ! \WPSellServices\Services\PaymentReceiptService::is_enabled() ) {
			return;
		}

		$service  = new \WPSellServices\Services\PaymentReceiptService();
		$receipts = $service->get_for_order( $order_id );

		if ( ! $receipts ) {
			printf(
				'<p style="color:#856404;margin:0 0 12px;"><em>%s</em></p>',
				esc_html__( 'The buyer has not uploaded proof of payment yet.', 'wp-sell-services' )
			);
			return;
		}

		$nonce = wp_create_nonce( 'wpss_receipt_review' );

		echo '<div class="wpss-receipt-review" style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:12px;margin:0 0 12px;">';
		printf( '<h4 style="margin:0 0 8px;">%s</h4>', esc_html__( 'Payment proof', 'wp-sell-services' ) );

		foreach ( $receipts as $receipt ) {
			$file   = wpss_get_receipt_file( $receipt );
			$url    = $file['url'];
			$is_img = $file['is_image'];
			$who    = get_userdata( (int) $receipt->uploaded_by );

			echo '<div class="wpss-receipt-review__item" style="border-top:1px solid #eee;padding:10px 0;">';

			printf(
				'<p style="margin:0 0 6px;"><strong>%s</strong> &middot; %s</p>',
				esc_html( ucfirst( (string) $receipt->status ) ),
				esc_html(
					sprintf(
						/* translators: 1: uploader name, 2: date */
						__( 'uploaded by %1$s on %2$s', 'wp-sell-services' ),
						wpss_get_member_display_name( (int) ( $who->ID ?? 0 ), __( 'a buyer', 'wp-sell-services' ) ),
						wpss_date_label( (string) $receipt->created_at )
					)
				)
			);

			if ( $receipt->note ) {
				printf( '<p style="margin:0 0 6px;">%s</p>', esc_html( (string) $receipt->note ) );
			}

			if ( $url && $is_img ) {
				printf(
					'<a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" style="max-width:220px;height:auto;border:1px solid #ddd;border-radius:3px;"></a>',
					esc_url( $url ),
					esc_attr__( 'Payment proof', 'wp-sell-services' )
				);
			} elseif ( $url ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $url ),
					esc_html__( 'Open the uploaded file', 'wp-sell-services' )
				);
			}

			if ( 'submitted' === (string) $receipt->status ) {
				?>
				<div style="margin-top:10px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
					<div>
						<label for="wpss-receipt-note-<?php echo esc_attr( (string) $receipt->id ); ?>" style="display:block;margin-bottom:4px;font-weight:500;">
							<?php esc_html_e( 'Note to buyer (required to reject)', 'wp-sell-services' ); ?>
						</label>
						<input type="text" id="wpss-receipt-note-<?php echo esc_attr( (string) $receipt->id ); ?>" class="wpss-receipt-note" style="width:260px;">
					</div>
					<button type="button" class="button button-primary wpss-receipt-action" data-action="verify"
						data-receipt="<?php echo esc_attr( (string) $receipt->id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Approve + mark paid', 'wp-sell-services' ); ?>
					</button>
					<button type="button" class="button wpss-receipt-action" data-action="reject"
						data-receipt="<?php echo esc_attr( (string) $receipt->id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Reject', 'wp-sell-services' ); ?>
					</button>
				</div>
				<?php
			} elseif ( $receipt->admin_note ) {
				printf(
					'<p style="margin:6px 0 0;color:#666;"><em>%s</em></p>',
					esc_html( (string) $receipt->admin_note )
				);
			}

			echo '</div>';
		}

		echo '</div>';
		?>
		<?php
	}

	/**
	 * AJAX: record an admin decision on a payment receipt.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	public function ajax_review_payment_receipt(): void {
		check_ajax_referer( 'wpss_receipt_review', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'wp-sell-services' ) ), 403 );
		}

		$receipt_id = isset( $_POST['receipt_id'] ) ? absint( $_POST['receipt_id'] ) : 0;
		$decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! in_array( $decision, array( 'verify', 'reject' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown decision.', 'wp-sell-services' ) ), 400 );
		}

		$service = new \WPSellServices\Services\PaymentReceiptService();
		$result  = 'verify' === $decision
			? $service->verify( $receipt_id, get_current_user_id(), $note )
			: $service->reject( $receipt_id, get_current_user_id(), $note );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				(int) ( $result->get_error_data()['status'] ?? 400 )
			);
		}

		wp_send_json_success();
	}
}
