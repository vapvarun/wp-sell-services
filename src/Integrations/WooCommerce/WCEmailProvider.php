<?php
/**
 * WooCommerce Email Provider
 *
 * Integrates custom service order emails with WooCommerce email system.
 *
 * @package WPSellServices\Integrations\WooCommerce
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\WooCommerce;

use WPSellServices\Models\ServiceOrder;

/**
 * Handles WooCommerce email integration for service orders.
 *
 * @since 1.0.0
 */
class WCEmailProvider {

	/**
	 * Initialize email hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register custom email classes.
		add_filter( 'woocommerce_email_classes', [ $this, 'register_email_classes' ] );

		// Add email actions.
		add_filter( 'woocommerce_email_actions', [ $this, 'register_email_actions' ] );

		// Service order status triggers.
		add_action( 'wpss_order_status_changed', [ $this, 'trigger_status_emails' ], 10, 3 );
		add_action( 'wpss_requirements_submitted', [ $this, 'trigger_requirements_email' ], 10, 3 );
		add_action( 'wpss_delivery_submitted', [ $this, 'trigger_delivery_email' ], 10, 2 );
		add_action( 'wpss_new_order_message', [ $this, 'trigger_message_email' ], 10, 3 );
	}

	/**
	 * Register custom WooCommerce email classes.
	 *
	 * @param array $email_classes Existing email classes.
	 * @return array
	 */
	public function register_email_classes( array $email_classes ): array {
		$email_classes['WPSS_Email_New_Order']              = new WPSS_Email_New_Order();
		$email_classes['WPSS_Email_Requirements_Submitted'] = new WPSS_Email_Requirements_Submitted();
		$email_classes['WPSS_Email_Order_In_Progress']      = new WPSS_Email_Order_In_Progress();
		$email_classes['WPSS_Email_Delivery_Ready']         = new WPSS_Email_Delivery_Ready();
		$email_classes['WPSS_Email_Order_Completed']        = new WPSS_Email_Order_Completed();
		$email_classes['WPSS_Email_Revision_Requested']     = new WPSS_Email_Revision_Requested();
		$email_classes['WPSS_Email_New_Message']            = new WPSS_Email_New_Message();
		$email_classes['WPSS_Email_Order_Cancelled']        = new WPSS_Email_Order_Cancelled();
		$email_classes['WPSS_Email_Dispute_Opened']         = new WPSS_Email_Dispute_Opened();

		return $email_classes;
	}

	/**
	 * Register email trigger actions.
	 *
	 * @param array $actions Email actions.
	 * @return array
	 */
	public function register_email_actions( array $actions ): array {
		$actions[] = 'wpss_order_status_pending_requirements';
		$actions[] = 'wpss_order_status_in_progress';
		$actions[] = 'wpss_order_status_pending_approval';
		$actions[] = 'wpss_order_status_completed';
		$actions[] = 'wpss_order_status_revision_requested';
		$actions[] = 'wpss_order_status_cancelled';
		$actions[] = 'wpss_order_status_disputed';
		$actions[] = 'wpss_requirements_submitted';
		$actions[] = 'wpss_delivery_submitted';
		$actions[] = 'wpss_new_order_message';

		return $actions;
	}

	/**
	 * Trigger emails based on status change.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function trigger_status_emails( int $order_id, string $new_status, string $old_status ): void {
		$wc_emails = \WC()->mailer()->get_emails();

		switch ( $new_status ) {
			case ServiceOrder::STATUS_PENDING_REQUIREMENTS:
				if ( isset( $wc_emails['WPSS_Email_New_Order'] ) ) {
					$wc_emails['WPSS_Email_New_Order']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_IN_PROGRESS:
				if ( isset( $wc_emails['WPSS_Email_Order_In_Progress'] ) ) {
					$wc_emails['WPSS_Email_Order_In_Progress']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_PENDING_APPROVAL:
				if ( isset( $wc_emails['WPSS_Email_Delivery_Ready'] ) ) {
					$wc_emails['WPSS_Email_Delivery_Ready']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_COMPLETED:
				if ( isset( $wc_emails['WPSS_Email_Order_Completed'] ) ) {
					$wc_emails['WPSS_Email_Order_Completed']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_REVISION_REQUESTED:
				if ( isset( $wc_emails['WPSS_Email_Revision_Requested'] ) ) {
					$wc_emails['WPSS_Email_Revision_Requested']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_CANCELLED:
				if ( isset( $wc_emails['WPSS_Email_Order_Cancelled'] ) ) {
					$wc_emails['WPSS_Email_Order_Cancelled']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_DISPUTED:
				if ( isset( $wc_emails['WPSS_Email_Dispute_Opened'] ) ) {
					$wc_emails['WPSS_Email_Dispute_Opened']->trigger( $order_id );
				}
				break;
		}
	}

	/**
	 * Trigger requirements submitted email.
	 *
	 * @param int   $order_id    Order ID.
	 * @param array $field_data  Submitted data.
	 * @param array $attachments Attachments.
	 * @return void
	 */
	public function trigger_requirements_email( int $order_id, array $field_data, array $attachments ): void {
		$wc_emails = \WC()->mailer()->get_emails();

		if ( isset( $wc_emails['WPSS_Email_Requirements_Submitted'] ) ) {
			$wc_emails['WPSS_Email_Requirements_Submitted']->trigger( $order_id, $field_data );
		}
	}

	/**
	 * Trigger delivery submitted email.
	 *
	 * @param int   $order_id    Order ID.
	 * @param array $delivery    Delivery data.
	 * @return void
	 */
	public function trigger_delivery_email( int $order_id, array $delivery ): void {
		$wc_emails = \WC()->mailer()->get_emails();

		if ( isset( $wc_emails['WPSS_Email_Delivery_Ready'] ) ) {
			$wc_emails['WPSS_Email_Delivery_Ready']->trigger( $order_id );
		}
	}

	/**
	 * Trigger new message email.
	 *
	 * @param int    $order_id    Order ID.
	 * @param int    $sender_id   Sender user ID.
	 * @param string $message     Message content.
	 * @return void
	 */
	public function trigger_message_email( int $order_id, int $sender_id, string $message ): void {
		$wc_emails = \WC()->mailer()->get_emails();

		if ( isset( $wc_emails['WPSS_Email_New_Message'] ) ) {
			$wc_emails['WPSS_Email_New_Message']->trigger( $order_id, $sender_id, $message );
		}
	}
}

/**
 * Base class for WPSS emails.
 */
abstract class WPSS_Email_Base extends \WC_Email {

	/**
	 * Service order object.
	 *
	 * @var ServiceOrder|null
	 */
	public ?ServiceOrder $service_order = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->template_base = WPSS_PLUGIN_PATH . 'templates/emails/';
		$this->customer_email = true;

		parent::__construct();
	}

	/**
	 * Get service order.
	 *
	 * @param int $order_id Order ID.
	 * @return ServiceOrder|null
	 */
	protected function get_service_order( int $order_id ): ?ServiceOrder {
		return wpss_get_order( $order_id );
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return $this->subject;
	}

	/**
	 * Get default heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return $this->heading;
	}
}

/**
 * New Order Email (to vendor).
 */
class WPSS_Email_New_Order extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_new_order';
		$this->title          = __( 'New Service Order', 'wp-sell-services' );
		$this->description    = __( 'Sent to vendor when a new service order is placed.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] New Service Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'New Order Received', 'wp-sell-services' );
		$this->template_html  = 'new-order.php';
		$this->template_plain = 'plain/new-order.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		$vendor = get_user_by( 'id', $this->service_order->vendor_id );
		if ( ! $vendor ) {
			return;
		}

		$this->recipient = $vendor->user_email;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Requirements Submitted Email (to vendor).
 */
class WPSS_Email_Requirements_Submitted extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_requirements_submitted';
		$this->title          = __( 'Requirements Submitted', 'wp-sell-services' );
		$this->description    = __( 'Sent to vendor when customer submits order requirements.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Requirements Received - Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'Requirements Received', 'wp-sell-services' );
		$this->template_html  = 'requirements-submitted.php';
		$this->template_plain = 'plain/requirements-submitted.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $field_data Submitted requirements.
	 * @return void
	 */
	public function trigger( int $order_id, array $field_data = [] ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		$vendor = get_user_by( 'id', $this->service_order->vendor_id );
		if ( ! $vendor ) {
			return;
		}

		$this->recipient = $vendor->user_email;
		$this->object    = [ 'order' => $this->service_order, 'requirements' => $field_data ];

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'requirements'  => $this->object['requirements'] ?? [],
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'requirements'  => $this->object['requirements'] ?? [],
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Order In Progress Email (to customer).
 */
class WPSS_Email_Order_In_Progress extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_order_in_progress';
		$this->title          = __( 'Order In Progress', 'wp-sell-services' );
		$this->description    = __( 'Sent to customer when vendor starts working on the order.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Work Started on Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'Your Order is In Progress', 'wp-sell-services' );
		$this->template_html  = 'order-in-progress.php';
		$this->template_plain = 'plain/order-in-progress.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		$customer = get_user_by( 'id', $this->service_order->customer_id );
		if ( ! $customer ) {
			return;
		}

		$this->recipient = $customer->user_email;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Delivery Ready Email (to customer).
 */
class WPSS_Email_Delivery_Ready extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_delivery_ready';
		$this->title          = __( 'Delivery Ready', 'wp-sell-services' );
		$this->description    = __( 'Sent to customer when vendor submits a delivery.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Delivery Ready - Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'Your Delivery is Ready', 'wp-sell-services' );
		$this->template_html  = 'delivery-ready.php';
		$this->template_plain = 'plain/delivery-ready.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		$customer = get_user_by( 'id', $this->service_order->customer_id );
		if ( ! $customer ) {
			return;
		}

		$this->recipient = $customer->user_email;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Order Completed Email (to both).
 */
class WPSS_Email_Order_Completed extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_order_completed';
		$this->title          = __( 'Order Completed', 'wp-sell-services' );
		$this->description    = __( 'Sent when a service order is completed.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Order #{order_number} Completed', 'wp-sell-services' );
		$this->heading        = __( 'Order Completed', 'wp-sell-services' );
		$this->template_html  = 'order-completed.php';
		$this->template_plain = 'plain/order-completed.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		// Send to customer.
		$customer = get_user_by( 'id', $this->service_order->customer_id );
		if ( $customer && $this->is_enabled() ) {
			$this->recipient = $customer->user_email;
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		// Send to vendor.
		$vendor = get_user_by( 'id', $this->service_order->vendor_id );
		if ( $vendor && $this->is_enabled() ) {
			$this->recipient = $vendor->user_email;
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Revision Requested Email (to vendor).
 */
class WPSS_Email_Revision_Requested extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_revision_requested';
		$this->title          = __( 'Revision Requested', 'wp-sell-services' );
		$this->description    = __( 'Sent to vendor when customer requests a revision.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Revision Requested - Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'Revision Requested', 'wp-sell-services' );
		$this->template_html  = 'revision-requested.php';
		$this->template_plain = 'plain/revision-requested.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		$vendor = get_user_by( 'id', $this->service_order->vendor_id );
		if ( ! $vendor ) {
			return;
		}

		$this->recipient = $vendor->user_email;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * New Message Email.
 */
class WPSS_Email_New_Message extends WPSS_Email_Base {

	/**
	 * Message content.
	 *
	 * @var string
	 */
	public string $message_content = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_new_message';
		$this->title          = __( 'New Message', 'wp-sell-services' );
		$this->description    = __( 'Sent when a new message is received on an order.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] New Message - Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'New Message', 'wp-sell-services' );
		$this->template_html  = 'new-message.php';
		$this->template_plain = 'plain/new-message.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $sender_id Sender user ID.
	 * @param string $message Message content.
	 * @return void
	 */
	public function trigger( int $order_id, int $sender_id, string $message ): void {
		$this->service_order   = $this->get_service_order( $order_id );
		$this->message_content = $message;

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		// Determine recipient (opposite of sender).
		$recipient_id = ( $sender_id === $this->service_order->customer_id )
			? $this->service_order->vendor_id
			: $this->service_order->customer_id;

		$recipient = get_user_by( 'id', $recipient_id );
		if ( ! $recipient ) {
			return;
		}

		$this->recipient = $recipient->user_email;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'           => $this->service_order,
				'message_content' => $this->message_content,
				'email_heading'   => $this->get_heading(),
				'plain_text'      => false,
				'email'           => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'           => $this->service_order,
				'message_content' => $this->message_content,
				'email_heading'   => $this->get_heading(),
				'plain_text'      => true,
				'email'           => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Order Cancelled Email.
 */
class WPSS_Email_Order_Cancelled extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_order_cancelled';
		$this->title          = __( 'Order Cancelled', 'wp-sell-services' );
		$this->description    = __( 'Sent when a service order is cancelled.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Order #{order_number} Cancelled', 'wp-sell-services' );
		$this->heading        = __( 'Order Cancelled', 'wp-sell-services' );
		$this->template_html  = 'order-cancelled.php';
		$this->template_plain = 'plain/order-cancelled.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		// Send to both parties.
		$customer = get_user_by( 'id', $this->service_order->customer_id );
		$vendor   = get_user_by( 'id', $this->service_order->vendor_id );

		if ( $customer && $this->is_enabled() ) {
			$this->recipient = $customer->user_email;
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		if ( $vendor && $this->is_enabled() ) {
			$this->recipient = $vendor->user_email;
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}

/**
 * Dispute Opened Email.
 */
class WPSS_Email_Dispute_Opened extends WPSS_Email_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpss_dispute_opened';
		$this->title          = __( 'Dispute Opened', 'wp-sell-services' );
		$this->description    = __( 'Sent when a dispute is opened on an order.', 'wp-sell-services' );
		$this->subject        = __( '[{site_title}] Dispute Opened - Order #{order_number}', 'wp-sell-services' );
		$this->heading        = __( 'Dispute Opened', 'wp-sell-services' );
		$this->template_html  = 'dispute-opened.php';
		$this->template_plain = 'plain/dispute-opened.php';
		$this->placeholders   = [
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		];

		parent::__construct();
	}

	/**
	 * Trigger email.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( int $order_id ): void {
		$this->service_order = $this->get_service_order( $order_id );

		if ( ! $this->service_order ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->service_order->order_number;

		// Send to both parties and admin.
		$recipients = [
			get_user_by( 'id', $this->service_order->customer_id ),
			get_user_by( 'id', $this->service_order->vendor_id ),
		];

		foreach ( $recipients as $recipient ) {
			if ( $recipient && $this->is_enabled() ) {
				$this->recipient = $recipient->user_email;
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'         => $this->service_order,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}
