<?php
/**
 * Payment Receipt Service
 *
 * Buyer-submitted proof of an offline payment, and the admin decision on it.
 *
 * @package WPSellServices\Services
 * @since   1.6.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Database\Repositories\PaymentReceiptRepository;

/**
 * Handles offline payment proof: submission, verification, rejection.
 *
 * Basecamp #10194890682. A buyer paying by bank transfer has no way to tell the
 * marketplace they have paid, and an admin has no evidence to check before
 * releasing the work - the existing Mark as Paid is a one-way switch with no
 * record of what it was based on.
 *
 * @since 1.6.0
 */
class PaymentReceiptService {

	/**
	 * Receipt repository.
	 *
	 * @var PaymentReceiptRepository
	 */
	private PaymentReceiptRepository $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new PaymentReceiptRepository();
	}

	/**
	 * Whether the site accepts proof-of-payment uploads.
	 *
	 * Off by default. A marketplace taking only card payments should not be
	 * shown an upload box it will never use.
	 *
	 * @since 1.6.0
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = get_option( 'wpss_offline_receipt_settings', array() );

		return ! empty( $settings['enabled'] );
	}

	/**
	 * Whether an order can accept proof of payment right now.
	 *
	 * Offline, unpaid, and belonging to the person asking. Anything else is
	 * either already settled or not theirs to prove.
	 *
	 * @since 1.6.0
	 *
	 * @param object $order   Order row or model.
	 * @param int    $user_id User attempting the upload.
	 * @return bool
	 */
	public function can_submit( object $order, int $user_id ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( (int) ( $order->customer_id ?? 0 ) !== $user_id || $user_id <= 0 ) {
			return false;
		}

		if ( 'offline' !== (string) ( $order->payment_method ?? '' ) ) {
			return false;
		}

		return 'paid' !== (string) ( $order->payment_status ?? '' );
	}

	/**
	 * Record a buyer's proof of payment.
	 *
	 * Uploads go through wpss_handle_message_attachments(), the path the
	 * conversation attachments already use - it validates extension and
	 * server-side MIME rather than trusting what the browser claimed. These are
	 * attacker-supplied files from any logged-in buyer, so a third upload path
	 * with its own idea of what is safe is exactly what not to build.
	 *
	 * @since 1.6.0
	 *
	 * @param int                  $order_id Order ID.
	 * @param int                  $user_id  Uploading user.
	 * @param array<string, mixed> $files    Raw $_FILES slice.
	 * @param string               $note     Optional buyer note (reference number, etc).
	 * @return int|\WP_Error Receipt ID, or error.
	 */
	public function submit( int $order_id, int $user_id, array $files, string $note = '' ) {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'wpss_order_not_found', __( 'Order not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_submit( $order, $user_id ) ) {
			return new \WP_Error( 'wpss_receipt_not_allowed', __( 'This order does not accept payment proof.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		if ( $this->repo->get_pending_for_order( $order_id ) ) {
			return new \WP_Error( 'wpss_receipt_pending', __( 'A receipt is already awaiting review on this order.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		$result      = wpss_handle_message_attachments( $files );
		$attachments = $result['attachments'] ?? array();

		if ( empty( $attachments ) ) {
			$skipped = $result['skipped'] ?? array();

			return new \WP_Error(
				'wpss_receipt_upload_failed',
				$skipped
					? implode( ' ', array_map( 'strval', $skipped ) )
					: __( 'No file was uploaded.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		$first = (array) $attachments[0];

		$receipt_id = $this->repo->insert(
			array(
				'order_id'      => $order_id,
				'uploaded_by'   => $user_id,
				'attachment_id' => (int) ( $first['id'] ?? 0 ),
				'note'          => $note,
				'status'        => 'submitted',
				'created_at'    => current_time( 'mysql' ),
			)
		);

		if ( ! $receipt_id ) {
			return new \WP_Error( 'wpss_receipt_save_failed', __( 'Could not save the receipt.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires when a buyer submits proof of an offline payment.
		 *
		 * @since 1.6.0
		 *
		 * @param int $receipt_id Receipt ID.
		 * @param int $order_id   Order ID.
		 * @param int $user_id    Uploading user.
		 */
		do_action( 'wpss_payment_receipt_submitted', (int) $receipt_id, $order_id, $user_id );

		return (int) $receipt_id;
	}

	/**
	 * Approve a receipt and settle the order.
	 *
	 * The claim is a conditional UPDATE off `submitted`, so two admins pressing
	 * Approve at the same moment cannot both proceed - only the caller that
	 * actually moved the row goes on to touch money.
	 *
	 * Settlement then routes through StandaloneOrderProvider::mark_as_paid(),
	 * the SAME path every rail uses. That method is already idempotent and is
	 * the only place `wpss_order_paid` fires, which is what credits the vendor,
	 * records commission and drives milestones. Writing a second settlement path
	 * here is how three rails ended up never paying anyone.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $receipt_id  Receipt ID.
	 * @param int    $reviewer_id Admin user ID.
	 * @param string $admin_note  Optional note.
	 * @return true|\WP_Error
	 */
	public function verify( int $receipt_id, int $reviewer_id, string $admin_note = '' ) {
		$receipt = $this->repo->find( $receipt_id );

		if ( ! $receipt ) {
			return new \WP_Error( 'wpss_receipt_not_found', __( 'Receipt not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( ! $this->repo->claim_for_review( $receipt_id, 'verified', $reviewer_id, $admin_note ) ) {
			return new \WP_Error(
				'wpss_receipt_already_reviewed',
				__( 'This receipt has already been reviewed.', 'wp-sell-services' ),
				array( 'status' => 409 )
			);
		}

		$order_id = (int) $receipt->order_id;

		( new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider() )
			->mark_as_paid( $order_id, 'offline-receipt-' . $receipt_id, 'offline' );

		/**
		 * Fires when an admin verifies proof of an offline payment.
		 *
		 * @since 1.6.0
		 *
		 * @param int $receipt_id  Receipt ID.
		 * @param int $order_id    Order ID.
		 * @param int $reviewer_id Admin user ID.
		 */
		do_action( 'wpss_payment_receipt_verified', $receipt_id, $order_id, $reviewer_id );

		return true;
	}

	/**
	 * Reject a receipt so the buyer can supply better evidence.
	 *
	 * Deliberately does NOT cancel the order - a blurry photo is not a failed
	 * purchase, and the buyer needs the order alive to upload again.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $receipt_id  Receipt ID.
	 * @param int    $reviewer_id Admin user ID.
	 * @param string $admin_note  Reason, shown to the buyer.
	 * @return true|\WP_Error
	 */
	public function reject( int $receipt_id, int $reviewer_id, string $admin_note = '' ) {
		$receipt = $this->repo->find( $receipt_id );

		if ( ! $receipt ) {
			return new \WP_Error( 'wpss_receipt_not_found', __( 'Receipt not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( ! $this->repo->claim_for_review( $receipt_id, 'rejected', $reviewer_id, $admin_note ) ) {
			return new \WP_Error(
				'wpss_receipt_already_reviewed',
				__( 'This receipt has already been reviewed.', 'wp-sell-services' ),
				array( 'status' => 409 )
			);
		}

		/**
		 * Fires when an admin rejects proof of an offline payment.
		 *
		 * @since 1.6.0
		 *
		 * @param int    $receipt_id  Receipt ID.
		 * @param int    $order_id    Order ID.
		 * @param int    $reviewer_id Admin user ID.
		 * @param string $admin_note  Reason given.
		 */
		do_action( 'wpss_payment_receipt_rejected', $receipt_id, (int) $receipt->order_id, $reviewer_id, $admin_note );

		return true;
	}

	/**
	 * Receipts on an order, newest first.
	 *
	 * @since 1.6.0
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, object>
	 */
	public function get_for_order( int $order_id ): array {
		return $this->repo->get_for_order( $order_id );
	}
}
