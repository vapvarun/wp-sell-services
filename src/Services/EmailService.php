<?php
/**
 * Email Service
 *
 * Standalone email system with branded templates.
 * Works independently of WooCommerce while integrating with it when available.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Handles all email sending with branded templates.
 *
 * @since 1.0.0
 */
class EmailService {

	/**
	 * Email types.
	 */
	public const TYPE_NEW_ORDER              = 'new_order';
	public const TYPE_REQUIREMENTS_SUBMITTED = 'requirements_submitted';
	public const TYPE_ORDER_IN_PROGRESS      = 'order_in_progress';
	public const TYPE_DELIVERY_READY         = 'delivery_ready';
	public const TYPE_ORDER_COMPLETED        = 'order_completed';
	public const TYPE_REVISION_REQUESTED     = 'revision_requested';
	public const TYPE_NEW_MESSAGE            = 'new_message';
	public const TYPE_ORDER_CANCELLED        = 'order_cancelled';
	public const TYPE_DISPUTE_OPENED         = 'dispute_opened';
	public const TYPE_REQUIREMENTS_REMINDER  = 'requirements_reminder';
	public const TYPE_SELLER_LEVEL_PROMOTION   = 'seller_level_promotion';
	public const TYPE_CANCELLATION_REQUESTED  = 'cancellation_requested';
	public const TYPE_WITHDRAWAL_REQUESTED    = 'withdrawal_requested';
	public const TYPE_WITHDRAWAL_AUTO         = 'withdrawal_auto';
	public const TYPE_VENDOR_CONTACT          = 'vendor_contact';
	public const TYPE_WITHDRAWAL_APPROVED    = 'withdrawal_approved';
	public const TYPE_WITHDRAWAL_REJECTED    = 'withdrawal_rejected';
	public const TYPE_PROPOSAL_SUBMITTED    = 'proposal_submitted';
	public const TYPE_PROPOSAL_ACCEPTED     = 'proposal_accepted';

	/**
	 * Default email settings. Lazily initialized to avoid early __() calls.
	 *
	 * @var array|null
	 */
	private ?array $settings = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Settings are lazy-loaded to avoid calling __() before 'init'.
	}

	/**
	 * Get settings, initializing lazily.
	 *
	 * @return array
	 */
	private function settings(): array {
		if ( null === $this->settings ) {
			$this->settings = $this->get_email_settings();
		}

		return $this->settings;
	}

	/**
	 * Initialize email hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into order status changes.
		add_action( 'wpss_order_status_changed', array( $this, 'handle_status_change' ), 20, 3 );

		// Hook into specific events.
		add_action( 'wpss_requirements_submitted', array( $this, 'send_requirements_submitted' ), 20, 3 );
		add_action( 'wpss_delivery_submitted', array( $this, 'send_delivery_ready' ), 20, 2 );
		add_action( 'wpss_new_order_message', array( $this, 'send_new_message' ), 20, 3 );

		// New email types.
		add_action( 'wpss_send_requirements_reminder_email', array( $this, 'send_requirements_reminder' ), 10, 3 );
		add_action( 'wpss_vendor_level_promoted', array( $this, 'send_level_promotion' ), 10, 3 );

		// Withdrawal processed (approved/rejected).
		add_action( 'wpss_withdrawal_processed', array( $this, 'send_withdrawal_status' ), 10, 3 );

		// Proposal notifications.
		add_action( 'wpss_proposal_submitted', array( $this, 'send_proposal_submitted' ), 10, 4 );
		add_action( 'wpss_proposal_accepted', array( $this, 'send_proposal_accepted' ), 10, 3 );
	}

	/**
	 * Get email settings.
	 *
	 * @return array
	 */
	private function get_email_settings(): array {
		// Use WooCommerce mailer settings when available, otherwise fall back to platform name.
		$from_name  = wpss_get_platform_name();
		$from_email = get_option( 'admin_email' );

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$from_name  = WC()->mailer()->get_from_name();
			$from_email = WC()->mailer()->get_from_address();
		}

		return array(
			'from_name'    => $from_name,
			'from_email'   => $from_email,
			'header_image' => '',
			'footer_text'  => sprintf(
				/* translators: %1$s: year, %2$s: platform name */
				__( '© %1$s %2$s. All rights reserved.', 'wp-sell-services' ),
				gmdate( 'Y' ),
				wpss_get_platform_name()
			),
			'base_color'   => '#7f54b3',
			'bg_color'     => '#f7f7f7',
			'body_color'   => '#ffffff',
			'text_color'   => '#3c3c3c',
		);
	}

	/**
	 * Handle order status changes.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function handle_status_change( int $order_id, string $new_status, string $old_status ): void {

		$order = wpss_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		switch ( $new_status ) {
			case ServiceOrder::STATUS_PENDING_REQUIREMENTS:
				$this->send_new_order( $order );
				break;

			case ServiceOrder::STATUS_IN_PROGRESS:
				$this->send_order_in_progress( $order );
				break;

			case ServiceOrder::STATUS_COMPLETED:
				$this->send_order_completed( $order );
				break;

			case ServiceOrder::STATUS_REVISION_REQUESTED:
				$this->send_revision_requested( $order );
				break;

			case ServiceOrder::STATUS_CANCELLATION_REQUESTED:
				$this->send_cancellation_requested( $order );
				break;

			case ServiceOrder::STATUS_CANCELLED:
				$this->send_order_cancelled( $order );
				break;

			case ServiceOrder::STATUS_DISPUTED:
				$this->send_dispute_opened( $order );
				break;
		}
	}

	// WooCommerce email deferral removed — WPSS always sends its own emails
	// regardless of WooCommerce state. This is a marketplace plugin with its
	// own email system; WC emails are for WC products, not for services.

	/**
	 * Send new order emails to vendor and buyer.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_new_order( ServiceOrder $order ): bool {
		$vendor   = get_user_by( 'id', $order->vendor_id );
		$customer = get_user_by( 'id', $order->customer_id );

		$service_title = get_the_title( $order->service_id );

		// Send to vendor.
		if ( $vendor ) {
			$subject = sprintf(
				/* translators: %1$s: site name, %2$s: order number */
				__( '[%1$s] New Service Order #%2$s', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$order->order_number
			);

			$this->send(
				$vendor->user_email,
				$subject,
				self::TYPE_NEW_ORDER,
				array(
					'order'          => $order,
					'recipient'      => $vendor,
					'email_heading'  => __( 'New Order Received!', 'wp-sell-services' ),
					'service_title'  => $service_title,
					'customer_name'  => $this->get_customer_name( $order->customer_id ),
					'is_customer'    => false,
					'reply_to_email' => $customer ? $customer->user_email : '',
					'reply_to_name'  => $customer ? $customer->display_name : '',
				)
			);
		}

		// Send order confirmation to buyer.
		if ( $customer ) {
			$subject = sprintf(
				/* translators: %1$s: site name, %2$s: order number */
				__( '[%1$s] Order Confirmed #%2$s', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$order->order_number
			);

			$this->send(
				$customer->user_email,
				$subject,
				self::TYPE_NEW_ORDER,
				array(
					'order'          => $order,
					'recipient'      => $customer,
					'email_heading'  => __( 'Order Confirmed!', 'wp-sell-services' ),
					'service_title'  => $service_title,
					'vendor_name'    => $this->get_vendor_name( $order->vendor_id ),
					'is_customer'    => true,
					'reply_to_email' => $vendor ? $vendor->user_email : '',
					'reply_to_name'  => $vendor ? $vendor->display_name : '',
				)
			);
		}

		return true;
	}

	/**
	 * Send requirements submitted email to vendor.
	 *
	 * @param int   $order_id    Order ID.
	 * @param array $field_data  Field data.
	 * @param array $attachments Attachments.
	 * @return bool
	 */
	public function send_requirements_submitted( int $order_id, array $field_data, array $attachments ): bool {
		$order = wpss_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$vendor = get_user_by( 'id', $order->vendor_id );
		if ( ! $vendor ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Requirements Submitted - Order #%2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		$customer = get_user_by( 'id', $order->customer_id );

		$template_vars = array(
			'order'           => $order,
			'recipient'       => $vendor,
			'email_heading'   => __( 'Requirements Received', 'wp-sell-services' ),
			'field_data'      => $field_data,
			'has_attachments' => ! empty( $attachments ),
			'reply_to_email'  => $customer ? $customer->user_email : '',
			'reply_to_name'   => $customer ? $customer->display_name : '',
		);

		return $this->send( $vendor->user_email, $subject, self::TYPE_REQUIREMENTS_SUBMITTED, $template_vars );
	}

	/**
	 * Send order in progress emails to customer and vendor.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_order_in_progress( ServiceOrder $order ): bool {
		$customer = get_user_by( 'id', $order->customer_id );
		$vendor   = get_user_by( 'id', $order->vendor_id );

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Order #%2$s is Now In Progress', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		// Send to customer.
		if ( $customer ) {
			$this->send(
				$customer->user_email,
				$subject,
				self::TYPE_ORDER_IN_PROGRESS,
				array(
					'order'          => $order,
					'recipient'      => $customer,
					'email_heading'  => __( 'Your Order is In Progress', 'wp-sell-services' ),
					'vendor_name'    => $this->get_vendor_name( $order->vendor_id ),
					'is_customer'    => true,
					'customer_name'  => $this->get_customer_name( $order->customer_id ),
					'reply_to_email' => $vendor ? $vendor->user_email : '',
					'reply_to_name'  => $vendor ? $vendor->display_name : '',
				)
			);
		}

		// Send to vendor (requirements received, start working).
		if ( $vendor ) {
			$this->send(
				$vendor->user_email,
				$subject,
				self::TYPE_ORDER_IN_PROGRESS,
				array(
					'order'         => $order,
					'recipient'     => $vendor,
					'email_heading' => __( 'Requirements Received - Start Working', 'wp-sell-services' ),
					'customer_name' => $this->get_customer_name( $order->customer_id ),
					'is_customer'   => false,
				)
			);
		}

		return true;
	}

	/**
	 * Send delivery ready email to customer.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @param int $order_id    Order ID.
	 * @return bool
	 */
	public function send_delivery_ready( int $delivery_id, int $order_id ): bool {

		$order = wpss_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$customer = get_user_by( 'id', $order->customer_id );
		if ( ! $customer ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Delivery Ready - Order #%2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		$vendor = get_user_by( 'id', $order->vendor_id );

		$template_vars = array(
			'order'          => $order,
			'recipient'      => $customer,
			'email_heading'  => __( 'Your Delivery is Ready!', 'wp-sell-services' ),
			'vendor_name'    => $this->get_vendor_name( $order->vendor_id ),
			'reply_to_email' => $vendor ? $vendor->user_email : '',
			'reply_to_name'  => $vendor ? $vendor->display_name : '',
		);

		return $this->send( $customer->user_email, $subject, self::TYPE_DELIVERY_READY, $template_vars );
	}

	/**
	 * Send order completed email to both parties.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_order_completed( ServiceOrder $order ): bool {
		$customer = get_user_by( 'id', $order->customer_id );
		$vendor   = get_user_by( 'id', $order->vendor_id );

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Order #%2$s Completed', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		// Send to customer.
		if ( $customer ) {
			$this->send(
				$customer->user_email,
				$subject,
				self::TYPE_ORDER_COMPLETED,
				array(
					'order'          => $order,
					'recipient'      => $customer,
					'email_heading'  => __( 'Order Completed!', 'wp-sell-services' ),
					'is_customer'    => true,
					'vendor_name'    => $this->get_vendor_name( $order->vendor_id ),
					'customer_name'  => $this->get_customer_name( $order->customer_id ),
					'reply_to_email' => $vendor ? $vendor->user_email : '',
					'reply_to_name'  => $vendor ? $vendor->display_name : '',
				)
			);
		}

		// Send to vendor.
		if ( $vendor ) {
			$this->send(
				$vendor->user_email,
				$subject,
				self::TYPE_ORDER_COMPLETED,
				array(
					'order'          => $order,
					'recipient'      => $vendor,
					'email_heading'  => __( 'Order Completed!', 'wp-sell-services' ),
					'is_customer'    => false,
					'vendor_name'    => $this->get_vendor_name( $order->vendor_id ),
					'customer_name'  => $this->get_customer_name( $order->customer_id ),
					'reply_to_email' => $customer ? $customer->user_email : '',
					'reply_to_name'  => $customer ? $customer->display_name : '',
				)
			);
		}

		return true;
	}

	/**
	 * Send revision requested email to vendor.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_revision_requested( ServiceOrder $order ): bool {
		$vendor = get_user_by( 'id', $order->vendor_id );
		if ( ! $vendor ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Revision Requested - Order #%2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		$customer = get_user_by( 'id', $order->customer_id );

		$template_vars = array(
			'order'              => $order,
			'recipient'          => $vendor,
			'email_heading'      => __( 'Revision Requested', 'wp-sell-services' ),
			'revisions_used'     => $order->revisions_used ?? 0,
			'revisions_included' => $order->revisions_included ?? 0,
			'reply_to_email'     => $customer ? $customer->user_email : '',
			'reply_to_name'      => $customer ? $customer->display_name : '',
		);

		return $this->send( $vendor->user_email, $subject, self::TYPE_REVISION_REQUESTED, $template_vars );
	}

	/**
	 * Send new message email.
	 *
	 * @param int    $order_id  Order ID.
	 * @param int    $sender_id Sender user ID.
	 * @param string $message   Message content.
	 * @return bool
	 */
	public function send_new_message( int $order_id, int $sender_id, string $message ): bool {

		$order = wpss_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Determine recipient (opposite of sender).
		$recipient_id = ( $sender_id === $order->vendor_id ) ? $order->customer_id : $order->vendor_id;
		$recipient    = get_user_by( 'id', $recipient_id );
		$sender       = get_user_by( 'id', $sender_id );

		if ( ! $recipient || ! $sender ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] New Message - Order #%2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		$template_vars = array(
			'order'           => $order,
			'recipient'       => $recipient,
			'sender'          => $sender,
			'email_heading'   => __( 'New Message Received', 'wp-sell-services' ),
			'message_content' => $message,
			'sender_name'     => $sender->display_name,
			'sender_email'    => $sender->user_email,
			'reply_to_email'  => $sender->user_email,
			'reply_to_name'   => $sender->display_name,
		);

		return $this->send( $recipient->user_email, $subject, self::TYPE_NEW_MESSAGE, $template_vars );
	}

	/**
	 * Send cancellation requested emails to vendor and buyer.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_cancellation_requested( ServiceOrder $order ): bool {
		$vendor   = get_user_by( 'id', $order->vendor_id );
		$customer = get_user_by( 'id', $order->customer_id );

		// Parse cancellation data from vendor_notes.
		$cancel_data = json_decode( $order->vendor_notes ?? '', true );
		if ( ! is_array( $cancel_data ) ) {
			$cancel_data = array();
		}
		$reason_key  = $cancel_data['reason'] ?? '';
		$note        = $cancel_data['note'] ?? '';

		$reason_labels = array(
			'changed_mind'         => __( 'Changed my mind', 'wp-sell-services' ),
			'found_alternative'    => __( 'Found an alternative', 'wp-sell-services' ),
			'taking_too_long'      => __( 'Taking too long', 'wp-sell-services' ),
			'wrong_order'          => __( 'Ordered by mistake', 'wp-sell-services' ),
			'communication_issues' => __( 'Communication issues with vendor', 'wp-sell-services' ),
			'other'                => __( 'Other', 'wp-sell-services' ),
		);

		$reason_label = $reason_labels[ $reason_key ] ?? $reason_key;

		// Use the stored requested_at time for deadline, not current time.
		try {
			$requested_at = ! empty( $cancel_data['requested_at'] )
				? new \DateTimeImmutable( $cancel_data['requested_at'] )
				: new \DateTimeImmutable();
		} catch ( \Exception $e ) {
			$requested_at = new \DateTimeImmutable();
		}
		$deadline     = $requested_at->modify( '+48 hours' );
		$deadline_str = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $deadline->getTimestamp() );

		// Send to vendor.
		if ( $vendor ) {
			$subject = sprintf(
				/* translators: %1$s: site name, %2$s: order number */
				__( '[%1$s] Cancellation Requested - Order #%2$s', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$order->order_number
			);

			$this->send(
				$vendor->user_email,
				$subject,
				self::TYPE_CANCELLATION_REQUESTED,
				array(
					'order'          => $order,
					'recipient'      => $vendor,
					'email_heading'  => __( 'Cancellation Requested', 'wp-sell-services' ),
					'buyer_name'     => $this->get_customer_name( $order->customer_id ),
					'reason'         => $reason_label,
					'note'           => $note,
					'deadline'       => $deadline_str,
					'is_customer'    => false,
					'reply_to_email' => $customer ? $customer->user_email : '',
					'reply_to_name'  => $customer ? $customer->display_name : '',
				)
			);
		}

		// Send confirmation to buyer.
		if ( $customer ) {
			$subject = sprintf(
				/* translators: %1$s: site name, %2$s: order number */
				__( '[%1$s] Cancellation Request Submitted - Order #%2$s', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$order->order_number
			);

			$this->send(
				$customer->user_email,
				$subject,
				self::TYPE_CANCELLATION_REQUESTED,
				array(
					'order'          => $order,
					'recipient'      => $customer,
					'email_heading'  => __( 'Cancellation Request Submitted', 'wp-sell-services' ),
					'buyer_name'     => $this->get_customer_name( $order->customer_id ),
					'vendor_name'    => $this->get_vendor_name( $order->vendor_id ),
					'reason'         => $reason_label,
					'note'           => $note,
					'deadline'       => $deadline_str,
					'is_customer'    => true,
					'reply_to_email' => $vendor ? $vendor->user_email : '',
					'reply_to_name'  => $vendor ? $vendor->display_name : '',
				)
			);
		}

		return true;
	}

	/**
	 * Send order cancelled email.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_order_cancelled( ServiceOrder $order ): bool {
		$customer = get_user_by( 'id', $order->customer_id );
		$vendor   = get_user_by( 'id', $order->vendor_id );

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Order #%2$s Cancelled', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		$template_vars = array(
			'order'         => $order,
			'email_heading' => __( 'Order Cancelled', 'wp-sell-services' ),
		);

		// Send to both parties.
		if ( $customer ) {
			$template_vars['recipient'] = $customer;
			$this->send( $customer->user_email, $subject, self::TYPE_ORDER_CANCELLED, $template_vars );
		}

		if ( $vendor ) {
			$template_vars['recipient'] = $vendor;
			$this->send( $vendor->user_email, $subject, self::TYPE_ORDER_CANCELLED, $template_vars );
		}

		return true;
	}

	/**
	 * Send dispute opened email.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return bool
	 */
	public function send_dispute_opened( ServiceOrder $order ): bool {
		$customer = get_user_by( 'id', $order->customer_id );
		$vendor   = get_user_by( 'id', $order->vendor_id );

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Dispute Opened - Order #%2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$order->order_number
		);

		$base_vars = array(
			'order'         => $order,
			'email_heading' => __( 'Dispute Opened', 'wp-sell-services' ),
		);

		// Send to customer with customer-specific context.
		if ( $customer ) {
			$this->send(
				$customer->user_email,
				$subject,
				self::TYPE_DISPUTE_OPENED,
				array_merge( $base_vars, array(
					'recipient'   => $customer,
					'is_customer' => true,
					'vendor_name' => $this->get_vendor_name( $order->vendor_id ),
				) )
			);
		}

		// Send to vendor with vendor-specific context.
		if ( $vendor ) {
			$this->send(
				$vendor->user_email,
				$subject,
				self::TYPE_DISPUTE_OPENED,
				array_merge( $base_vars, array(
					'recipient'     => $vendor,
					'is_customer'   => false,
					'customer_name' => $this->get_customer_name( $order->customer_id ),
				) )
			);
		}

		// Also notify admin with full details.
		$admin_email = get_option( 'admin_email' );
		$this->send(
			$admin_email,
			$subject,
			self::TYPE_DISPUTE_OPENED,
			array_merge( $base_vars, array(
				'recipient' => get_user_by( 'email', $admin_email ) ?: $customer,
				'is_admin'  => true,
			) )
		);

		return true;
	}

	/**
	 * Send requirements reminder email to buyer.
	 *
	 * @param int    $order_id      Order ID.
	 * @param int    $reminder_num  Reminder number (1, 2, or 3).
	 * @param string $message       Reminder message.
	 * @return bool
	 */
	public function send_requirements_reminder( int $order_id, int $reminder_num, string $message ): bool {
		$order = wpss_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$customer = get_user_by( 'id', $order->customer_id );
		if ( ! $customer ) {
			return false;
		}

		$subjects = array(
			1 => __( '[%1$s] Submit Your Requirements - Order #%2$s', 'wp-sell-services' ),
			2 => __( '[%1$s] Reminder: Requirements Needed - Order #%2$s', 'wp-sell-services' ),
			3 => __( '[%1$s] Final Reminder: Action Required - Order #%2$s', 'wp-sell-services' ),
		);

		$headings = array(
			1 => __( 'Submit Your Requirements', 'wp-sell-services' ),
			2 => __( 'Reminder: Requirements Needed', 'wp-sell-services' ),
			3 => __( 'Final Reminder: Action Required', 'wp-sell-services' ),
		);

		$subject = sprintf(
			$subjects[ $reminder_num ] ?? $subjects[1],
			wpss_get_platform_name(),
			$order->order_number
		);

		$template_vars = array(
			'order'         => $order,
			'recipient'     => $customer,
			'email_heading' => $headings[ $reminder_num ] ?? $headings[1],
			'reminder_num'  => $reminder_num,
			'message'       => $message,
			'vendor_name'   => $this->get_vendor_name( $order->vendor_id ),
			'service_title' => get_the_title( $order->service_id ),
		);

		return $this->send( $customer->user_email, $subject, self::TYPE_REQUIREMENTS_REMINDER, $template_vars );
	}

	/**
	 * Send seller level promotion email.
	 *
	 * @param int    $user_id       Vendor user ID.
	 * @param string $new_level     New level.
	 * @param string $current_level Previous level.
	 * @return bool
	 */
	public function send_level_promotion( int $user_id, string $new_level, string $current_level ): bool {
		$vendor = get_user_by( 'id', $user_id );
		if ( ! $vendor ) {
			return false;
		}

		$level_label = SellerLevelService::get_level_label( $new_level );

		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: new level */
			__( '[%1$s] Congratulations! You\'ve been promoted to %2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$level_label
		);

		$template_vars = array(
			'recipient'      => $vendor,
			'email_heading'  => __( 'Congratulations! Level Up!', 'wp-sell-services' ),
			'new_level'      => $new_level,
			'new_level_label' => $level_label,
			'old_level'      => $current_level,
			'old_level_label' => SellerLevelService::get_level_label( $current_level ),
		);

		return $this->send( $vendor->user_email, $subject, self::TYPE_SELLER_LEVEL_PROMOTION, $template_vars );
	}

	/**
	 * Send withdrawal request notification to admin.
	 *
	 * @param int   $vendor_id     Vendor user ID.
	 * @param float $amount        Requested amount.
	 * @param int   $withdrawal_id Withdrawal record ID.
	 * @param bool  $is_auto       Whether this is an auto-withdrawal.
	 * @return bool
	 */
	public function send_withdrawal_notification( int $vendor_id, float $amount, int $withdrawal_id, bool $is_auto = false ): bool {
		$vendor      = get_user_by( 'id', $vendor_id );
		$admin_email = get_option( 'admin_email' );
		$type        = $is_auto ? self::TYPE_WITHDRAWAL_AUTO : self::TYPE_WITHDRAWAL_REQUESTED;

		if ( $is_auto ) {
			$subject = sprintf(
				/* translators: %s: platform name */
				__( '[%s] Auto Withdrawal Request', 'wp-sell-services' ),
				wpss_get_platform_name()
			);
			$email_heading = __( 'Auto Withdrawal Request', 'wp-sell-services' );
		} else {
			$subject = sprintf(
				/* translators: %s: platform name */
				__( '[%s] New Withdrawal Request', 'wp-sell-services' ),
				wpss_get_platform_name()
			);
			$email_heading = __( 'New Withdrawal Request', 'wp-sell-services' );
		}

		$template_vars = array(
			'recipient'       => get_user_by( 'email', $admin_email ),
			'email_heading'   => $email_heading,
			'vendor'          => $vendor,
			'amount'          => $amount,
			'withdrawal_id'   => $withdrawal_id,
			'is_auto'         => $is_auto,
			'admin_panel_url' => admin_url( 'admin.php?page=wpss-withdrawals' ),
		);

		return $this->send( $admin_email, $subject, $type, $template_vars );
	}

	/**
	 * Send vendor contact email.
	 *
	 * Sent when a customer uses the "Contact Me" button on a vendor profile.
	 *
	 * @param \WP_User $vendor        Vendor user object.
	 * @param \WP_User $sender        Sender (customer) user object.
	 * @param string   $message       Message content.
	 * @param string   $service_title Service title (optional).
	 * @param array    $attachments   Attachment data (optional).
	 * @return bool
	 */
	public function send_vendor_contact( \WP_User $vendor, \WP_User $sender, string $message, string $service_title = '', array $attachments = array() ): bool {
		$subject = $service_title
			? sprintf(
				/* translators: 1: platform name, 2: sender name, 3: service title */
				__( '[%1$s] New message from %2$s about "%3$s"', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$sender->display_name,
				$service_title
			)
			: sprintf(
				/* translators: 1: platform name, 2: sender name */
				__( '[%1$s] New message from %2$s', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$sender->display_name
			);

		$dashboard_url = add_query_arg( 'section', 'messages', wpss_get_dashboard_url() );

		$template_vars = array(
			'recipient'      => $vendor,
			'email_heading'  => __( 'New Message Received', 'wp-sell-services' ),
			'sender'         => $sender,
			'sender_name'    => $sender->display_name,
			'sender_email'   => $sender->user_email,
			'message'        => $message,
			'service_title'  => $service_title,
			'attachments'    => $attachments,
			'dashboard_url'  => $dashboard_url,
			'reply_to_email' => $sender->user_email,
			'reply_to_name'  => $sender->display_name,
		);

		return $this->send( $vendor->user_email, $subject, self::TYPE_VENDOR_CONTACT, $template_vars );
	}

	/**
	 * Send withdrawal status email to vendor.
	 *
	 * @param int    $withdrawal_id Withdrawal ID.
	 * @param string $status        New status (approved, completed, rejected).
	 * @param object $withdrawal    Withdrawal DB row.
	 * @return bool
	 */
	public function send_withdrawal_status( int $withdrawal_id, string $status, object $withdrawal ): bool {
		$vendor = get_user_by( 'id', $withdrawal->vendor_id );

		if ( ! $vendor ) {
			return false;
		}

		$amount = wpss_format_price( (float) $withdrawal->amount );

		if ( 'rejected' === $status ) {
			$subject = sprintf(
				/* translators: 1: platform name, 2: amount */
				__( '[%1$s] Withdrawal Request for %2$s Rejected', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$amount
			);
			$type = self::TYPE_WITHDRAWAL_REJECTED;
		} else {
			$subject = sprintf(
				/* translators: 1: platform name, 2: amount */
				__( '[%1$s] Withdrawal of %2$s Approved', 'wp-sell-services' ),
				wpss_get_platform_name(),
				$amount
			);
			$type = self::TYPE_WITHDRAWAL_APPROVED;
		}

		$template_vars = array(
			'recipient'      => $vendor,
			'email_heading'  => 'rejected' === $status ? __( 'Withdrawal Rejected', 'wp-sell-services' ) : __( 'Withdrawal Approved', 'wp-sell-services' ),
			'amount'         => $amount,
			'withdrawal_id'  => $withdrawal_id,
			'status'         => $status,
			'admin_note'     => $withdrawal->admin_note ?? '',
			'dashboard_url'  => add_query_arg( 'section', 'earnings', wpss_get_dashboard_url() ),
		);

		return $this->send( $vendor->user_email, $subject, $type, $template_vars );
	}

	/**
	 * Send proposal submitted email to the request owner (buyer).
	 *
	 * @param int   $proposal_id Proposal ID.
	 * @param int   $request_id  Buyer request ID.
	 * @param int   $vendor_id   Vendor who submitted.
	 * @param array $data        Proposal data.
	 * @return bool
	 */
	public function send_proposal_submitted( int $proposal_id, int $request_id, int $vendor_id, array $data ): bool {
		$request = get_post( $request_id );

		if ( ! $request ) {
			return false;
		}

		$buyer  = get_user_by( 'id', $request->post_author );
		$vendor = get_user_by( 'id', $vendor_id );

		if ( ! $buyer || ! $vendor ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: platform name, 2: vendor name */
			__( '[%1$s] New Proposal from %2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$vendor->display_name
		);

		$template_vars = array(
			'recipient'      => $buyer,
			'email_heading'  => __( 'New Proposal Received', 'wp-sell-services' ),
			'content'        => sprintf(
				/* translators: 1: vendor name, 2: request title */
				__( '%1$s has submitted a proposal for your request "%2$s". Review it and accept if it meets your needs.', 'wp-sell-services' ),
				$vendor->display_name,
				$request->post_title
			),
			'button_url'     => get_permalink( $request_id ),
			'button_text'    => __( 'View Proposals', 'wp-sell-services' ),
			'reply_to_email' => $vendor->user_email,
			'reply_to_name'  => $vendor->display_name,
		);

		return $this->send( $buyer->user_email, $subject, self::TYPE_PROPOSAL_SUBMITTED, $template_vars );
	}

	/**
	 * Send proposal accepted email to the vendor.
	 *
	 * @param int    $proposal_id Proposal ID.
	 * @param object $proposal    Proposal data.
	 * @param object $request     Request data (WP_Post or similar).
	 * @return bool
	 */
	public function send_proposal_accepted( int $proposal_id, object $proposal, object $request ): bool {
		$vendor = get_user_by( 'id', $proposal->vendor_id ?? 0 );
		$buyer  = get_user_by( 'id', $request->post_author ?? 0 );

		if ( ! $vendor || ! $buyer ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: platform name, 2: request title */
			__( '[%1$s] Your Proposal Was Accepted - %2$s', 'wp-sell-services' ),
			wpss_get_platform_name(),
			$request->post_title
		);

		$template_vars = array(
			'recipient'      => $vendor,
			'email_heading'  => __( 'Proposal Accepted!', 'wp-sell-services' ),
			'content'        => sprintf(
				/* translators: 1: buyer name, 2: request title */
				__( 'Congratulations! %1$s has accepted your proposal for "%2$s". An order will be created automatically.', 'wp-sell-services' ),
				$buyer->display_name,
				$request->post_title
			),
			'button_url'     => wpss_get_page_url( 'dashboard' ) ? add_query_arg( 'section', 'sales', wpss_get_page_url( 'dashboard' ) ) : '',
			'button_text'    => __( 'View Orders', 'wp-sell-services' ),
			'reply_to_email' => $buyer->user_email,
			'reply_to_name'  => $buyer->display_name,
		);

		return $this->send( $vendor->user_email, $subject, self::TYPE_PROPOSAL_ACCEPTED, $template_vars );
	}

	/**
	 * Send an email using the template system.
	 *
	 * @param string $to            Recipient email.
	 * @param string $subject       Email subject.
	 * @param string $type          Email type.
	 * @param array  $template_vars Template variables.
	 * @return bool
	 */
	public function send( string $to, string $subject, string $type, array $template_vars = array() ): bool {
		// Check if this email type is disabled in admin notification settings.
		if ( ! $this->is_email_type_enabled( $type ) ) {
			return false;
		}

		// Merge settings into template vars.
		$template_vars = array_merge( $this->settings(), $template_vars );
		$template_vars['site_url']  = home_url();
		$template_vars['site_name'] = wpss_get_platform_name();

		/**
		 * Filter email header/template variables for white-labelling.
		 *
		 * @since 1.1.0
		 *
		 * @param array  $template_vars Template variables including site_name, site_url, header_image, etc.
		 * @param string $type          Email type constant.
		 */
		$template_vars = apply_filters( 'wpss_email_header_vars', $template_vars, $type );

		// Get email content.
		$content = $this->get_email_content( $type, $template_vars );

		if ( empty( $content ) ) {
			return false;
		}

		/**
		 * Filter the email "from" name for white-labelling.
		 *
		 * @since 1.1.0
		 *
		 * @param string $from_name The sender name.
		 */
		$from_name = apply_filters( 'wpss_email_from_name', $this->settings()['from_name'] );

		// Set headers.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $this->settings()['from_email'] ),
		);

		// Add Reply-To header when reply_to_email is provided by the sending method.
		if ( ! empty( $template_vars['reply_to_email'] ) ) {
			$reply_name = $template_vars['reply_to_name'] ?? '';
			$headers[]  = sprintf( 'Reply-To: %s <%s>', $reply_name, $template_vars['reply_to_email'] );
		}

		/**
		 * Filter email before sending.
		 *
		 * @param array $email Email data.
		 * @param string $type Email type.
		 */
		$email = apply_filters(
			'wpss_email_before_send',
			array(
				'to'      => $to,
				'subject' => $subject,
				'content' => $content,
				'headers' => $headers,
			),
			$type
		);

		return wp_mail( $email['to'], $email['subject'], $email['content'], $email['headers'] );
	}

	/**
	 * Get email content from template.
	 *
	 * @param string $type          Email type.
	 * @param array  $template_vars Template variables.
	 * @return string
	 */
	private function get_email_content( string $type, array $template_vars ): string {
		$template_file = $this->get_template_file( $type );

		if ( ! $template_file ) {
			return '';
		}

		// Get header.
		$header = $this->get_template_part( 'header', $template_vars );

		// Get body content.
		ob_start();
		extract( $template_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $template_file;
		$body = ob_get_clean();

		// Get footer.
		$footer = $this->get_template_part( 'footer', $template_vars );

		return $header . $body . $footer;
	}

	/**
	 * Get template file path.
	 *
	 * Checks theme override first, then plugin templates.
	 *
	 * @param string $type Email type.
	 * @return string|null
	 */
	private function get_template_file( string $type ): ?string {
		$template_name = $this->get_template_name( $type );

		// Check theme override.
		$theme_template = locate_template( 'wp-sell-services/emails/' . $template_name );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Plugin template.
		$plugin_template = WPSS_PLUGIN_DIR . 'templates/emails/' . $template_name;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return null;
	}

	/**
	 * Get template part (header/footer).
	 *
	 * @param string $part          Part name.
	 * @param array  $template_vars Template variables.
	 * @return string
	 */
	private function get_template_part( string $part, array $template_vars ): string {
		$template_name = "email-{$part}.php";

		// Check theme override.
		$theme_template = locate_template( 'wp-sell-services/emails/' . $template_name );
		$template_file  = $theme_template ?: WPSS_PLUGIN_DIR . 'templates/emails/' . $template_name;

		if ( ! file_exists( $template_file ) ) {
			return '';
		}

		ob_start();
		extract( $template_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $template_file;
		return ob_get_clean();
	}

	/**
	 * Get template file name for email type.
	 *
	 * @param string $type Email type.
	 * @return string
	 */
	private function get_template_name( string $type ): string {
		$templates = array(
			self::TYPE_NEW_ORDER              => 'new-order.php',
			self::TYPE_REQUIREMENTS_SUBMITTED => 'requirements-submitted.php',
			self::TYPE_ORDER_IN_PROGRESS      => 'order-in-progress.php',
			self::TYPE_DELIVERY_READY         => 'delivery-ready.php',
			self::TYPE_ORDER_COMPLETED        => 'order-completed.php',
			self::TYPE_REVISION_REQUESTED     => 'revision-requested.php',
			self::TYPE_NEW_MESSAGE            => 'new-message.php',
			self::TYPE_ORDER_CANCELLED        => 'order-cancelled.php',
			self::TYPE_DISPUTE_OPENED         => 'dispute-opened.php',
			self::TYPE_REQUIREMENTS_REMINDER  => 'requirements-reminder.php',
			self::TYPE_SELLER_LEVEL_PROMOTION  => 'seller-level-promotion.php',
			self::TYPE_CANCELLATION_REQUESTED => 'cancellation-requested.php',
			self::TYPE_WITHDRAWAL_REQUESTED   => 'withdrawal-requested.php',
			self::TYPE_WITHDRAWAL_AUTO        => 'withdrawal-auto.php',
			self::TYPE_VENDOR_CONTACT         => 'vendor-contact.php',
			self::TYPE_WITHDRAWAL_APPROVED   => 'withdrawal-approved.php',
			self::TYPE_WITHDRAWAL_REJECTED   => 'withdrawal-rejected.php',
			self::TYPE_PROPOSAL_SUBMITTED   => 'generic.php',
			self::TYPE_PROPOSAL_ACCEPTED    => 'generic.php',
		);

		return $templates[ $type ] ?? 'generic.php';
	}

	/**
	 * Get customer display name.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return string
	 */
	private function get_customer_name( int $customer_id ): string {
		$customer = get_user_by( 'id', $customer_id );
		return $customer ? $customer->display_name : __( 'Customer', 'wp-sell-services' );
	}

	/**
	 * Get vendor display name.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return string
	 */
	private function get_vendor_name( int $vendor_id ): string {
		$vendor = get_user_by( 'id', $vendor_id );
		return $vendor ? $vendor->display_name : __( 'Vendor', 'wp-sell-services' );
	}

	/**
	 * Check if a given email type is enabled (static accessor).
	 *
	 * Allows external callers (EarningsService, ModerationService, etc.) to
	 * check the admin notification settings before sending direct wp_mail().
	 *
	 * @since 1.2.2
	 * @param string $type Email type or setting key.
	 * @return bool
	 */
	public static function is_type_enabled( string $type ): bool {
		return ( new self() )->is_email_type_enabled( $type );
	}

	/**
	 * Check if a given email type is enabled in admin notification settings.
	 *
	 * Reads the `wpss_notifications` option and maps email types to their
	 * corresponding setting keys. Returns false if the type is explicitly
	 * disabled, true otherwise (including when the setting doesn't exist,
	 * which preserves the default-enabled behavior).
	 *
	 * @since 1.2.1
	 * @param string $type Email type constant.
	 * @return bool True if the email type is enabled or has no setting, false if disabled.
	 */
	private function is_email_type_enabled( string $type ): bool {
		$notification_settings = get_option( 'wpss_notifications' );

		// Map EmailService type constants to admin setting keys.
		$type_to_setting = array(
			self::TYPE_NEW_ORDER              => 'notify_new_order',
			self::TYPE_REQUIREMENTS_SUBMITTED => 'notify_new_order',
			self::TYPE_ORDER_IN_PROGRESS      => 'notify_new_order',
			self::TYPE_DELIVERY_READY         => 'notify_delivery_submitted',
			self::TYPE_ORDER_COMPLETED        => 'notify_order_completed',
			self::TYPE_REVISION_REQUESTED     => 'notify_revision_requested',
			self::TYPE_NEW_MESSAGE            => 'notify_new_message',
			self::TYPE_ORDER_CANCELLED        => 'notify_order_cancelled',
			self::TYPE_DISPUTE_OPENED         => 'notify_dispute_opened',
			self::TYPE_REQUIREMENTS_REMINDER  => 'notify_new_order',
			// TYPE_CANCELLATION_REQUESTED is intentionally unmapped — always enabled (critical vendor communication).
			// TYPE_SELLER_LEVEL_PROMOTION is unmapped — always enabled (important vendor milestone).
			// TYPE_WITHDRAWAL_REQUESTED is unmapped — always enabled (financial notification to admin).
			// TYPE_WITHDRAWAL_AUTO is unmapped — always enabled (financial notification to admin).
			// TYPE_WITHDRAWAL_APPROVED is unmapped — always enabled (financial notification to vendor).
			// TYPE_WITHDRAWAL_REJECTED is unmapped — always enabled (financial notification to vendor).
			// TYPE_PROPOSAL_SUBMITTED is unmapped — always enabled (buyer request workflow).
			// TYPE_PROPOSAL_ACCEPTED is unmapped — always enabled (buyer request workflow).
			'moderation_approved'             => 'notify_moderation',
			'moderation_rejected'             => 'notify_moderation',
			'moderation_pending'              => 'notify_moderation',
			'dispute_admin'                   => 'notify_dispute_opened',
			self::TYPE_VENDOR_CONTACT         => 'notify_new_message',
		);

		if ( ! isset( $type_to_setting[ $type ] ) ) {
			// Unknown type: allow sending (do not block unrecognized types).
			return true;
		}

		$setting_key = $type_to_setting[ $type ];

		// Option never saved (fresh install) or empty (broken save) → default to enabled.
		if ( false === $notification_settings || ! is_array( $notification_settings ) || empty( $notification_settings ) ) {
			return true;
		}

		// Missing key defaults to enabled — emails should work out of the box.
		// Only explicitly set to false (unchecked checkbox) disables an email type.
		if ( ! array_key_exists( $setting_key, $notification_settings ) ) {
			return true;
		}

		return ! empty( $notification_settings[ $setting_key ] );
	}
}
