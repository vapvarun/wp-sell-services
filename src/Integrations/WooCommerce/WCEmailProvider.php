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
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );

		// Add email actions.
		add_filter( 'woocommerce_email_actions', array( $this, 'register_email_actions' ) );

		// Service order status triggers.
		add_action( 'wpss_order_status_changed', array( $this, 'trigger_status_emails' ), 10, 3 );
		add_action( 'wpss_requirements_submitted', array( $this, 'trigger_requirements_email' ), 10, 3 );
		add_action( 'wpss_delivery_submitted', array( $this, 'trigger_delivery_email' ), 10, 2 );
		add_action( 'wpss_new_order_message', array( $this, 'trigger_message_email' ), 10, 3 );
	}

	/**
	 * Load email classes file.
	 *
	 * This method loads the email class definitions only when WooCommerce is ready.
	 *
	 * @return void
	 */
	private function load_email_classes(): void {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}

		$classes_file = __DIR__ . '/WCEmailClasses.php';

		if ( file_exists( $classes_file ) ) {
			require_once $classes_file;
		}

		$loaded = true;
	}

	/**
	 * Register custom WooCommerce email classes.
	 *
	 * @param array $email_classes Existing email classes.
	 * @return array
	 */
	public function register_email_classes( array $email_classes ): array {
		// Load email classes now that WooCommerce is ready.
		$this->load_email_classes();

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
		// Ensure WooCommerce mailer is available.
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$wc_emails = WC()->mailer()->get_emails();

		switch ( $new_status ) {
			case ServiceOrder::STATUS_PENDING_REQUIREMENTS:
				if ( $this->is_notification_enabled( 'notify_new_order' ) && isset( $wc_emails['WPSS_Email_New_Order'] ) ) {
					$wc_emails['WPSS_Email_New_Order']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_IN_PROGRESS:
				if ( $this->is_notification_enabled( 'notify_new_order' ) && isset( $wc_emails['WPSS_Email_Order_In_Progress'] ) ) {
					$wc_emails['WPSS_Email_Order_In_Progress']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_PENDING_APPROVAL:
				if ( $this->is_notification_enabled( 'notify_delivery_submitted' ) && isset( $wc_emails['WPSS_Email_Delivery_Ready'] ) ) {
					$wc_emails['WPSS_Email_Delivery_Ready']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_COMPLETED:
				if ( $this->is_notification_enabled( 'notify_order_completed' ) && isset( $wc_emails['WPSS_Email_Order_Completed'] ) ) {
					$wc_emails['WPSS_Email_Order_Completed']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_REVISION_REQUESTED:
				if ( $this->is_notification_enabled( 'notify_revision_requested' ) && isset( $wc_emails['WPSS_Email_Revision_Requested'] ) ) {
					$wc_emails['WPSS_Email_Revision_Requested']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_CANCELLED:
				if ( $this->is_notification_enabled( 'notify_order_cancelled' ) && isset( $wc_emails['WPSS_Email_Order_Cancelled'] ) ) {
					$wc_emails['WPSS_Email_Order_Cancelled']->trigger( $order_id );
				}
				break;

			case ServiceOrder::STATUS_DISPUTED:
				if ( $this->is_notification_enabled( 'notify_dispute_opened' ) && isset( $wc_emails['WPSS_Email_Dispute_Opened'] ) ) {
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
		// Suppress unused parameter warning.
		unset( $attachments );

		// Check if new order notifications are enabled (requirements are part of the order flow).
		if ( ! $this->is_notification_enabled( 'notify_new_order' ) ) {
			return;
		}

		// Ensure WooCommerce mailer is available.
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$wc_emails = WC()->mailer()->get_emails();

		if ( isset( $wc_emails['WPSS_Email_Requirements_Submitted'] ) ) {
			$wc_emails['WPSS_Email_Requirements_Submitted']->trigger( $order_id, $field_data );
		}
	}

	/**
	 * Trigger delivery submitted email.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @param int $order_id    Order ID.
	 * @return void
	 */
	public function trigger_delivery_email( int $delivery_id, int $order_id ): void {
		// Suppress unused parameter warning.
		unset( $delivery_id );

		// Check if delivery submitted notifications are enabled.
		if ( ! $this->is_notification_enabled( 'notify_delivery_submitted' ) ) {
			return;
		}

		// Ensure WooCommerce mailer is available.
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$wc_emails = WC()->mailer()->get_emails();

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
		// Check if new message notifications are enabled.
		if ( ! $this->is_notification_enabled( 'notify_new_message' ) ) {
			return;
		}

		// Ensure WooCommerce mailer is available.
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$wc_emails = WC()->mailer()->get_emails();

		if ( isset( $wc_emails['WPSS_Email_New_Message'] ) ) {
			$wc_emails['WPSS_Email_New_Message']->trigger( $order_id, $sender_id, $message );
		}
	}

	/**
	 * Check if a notification type is enabled in admin settings.
	 *
	 * @since 1.2.1
	 * @param string $setting_key The notification setting key (e.g. 'notify_new_order').
	 * @return bool True if enabled or setting not found, false if explicitly disabled.
	 */
	private function is_notification_enabled( string $setting_key ): bool {
		$notification_settings = get_option( 'wpss_notifications', array() );

		if ( isset( $notification_settings[ $setting_key ] ) && ! $notification_settings[ $setting_key ] ) {
			return false;
		}

		return true;
	}
}
