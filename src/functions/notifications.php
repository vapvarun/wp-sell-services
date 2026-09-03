<?php
/**
 * Notifications: email preference categories and recipient presence.
 *
 * The shared seam behind three separate Basecamp cards - role-aware preferences
 * (#10159633379), skipping message email when the recipient is online
 * (#10159633576), and any new notification category a feature adds. They are one
 * design rather than three because they all answer the same question: SHOULD
 * this person receive this email, right now.
 *
 * @package WPSellServices
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The email preference categories a given user should be offered.
 *
 * ONE registry, role-aware. This list used to be hardcoded in
 * templates/dashboard/sections/profile.php, written entirely from a vendor's
 * point of view - so a buyer was shown "New orders on your services", "Tips
 * received" and "Withdrawal status updates", none of which can ever apply to
 * them, and none of which they could act on (#10159633379).
 *
 * Categories are the SAME keys EmailService::get_user_pref_category() maps
 * notification types onto, and they persist under the same
 * `wpss_email_preferences` user meta, so nothing already saved is invalidated
 * by this becoming role-aware. A user who gains the vendor role simply starts
 * seeing the selling categories, with any preference they had already set
 * still honoured.
 *
 * A category is offered when it can actually fire for that person:
 *
 * - Everyone can be messaged, disputed with, and have an order cancelled.
 * - A buyer receives delivery/completion mail about orders they PLACED.
 * - Only a vendor is paid, tipped, or withdraws.
 *
 * @since 1.6.0
 *
 * @param int $user_id User to build the list for. Defaults to current user.
 * @return array<string, array{label: string, desc: string}> Category key => copy.
 */
function wpss_get_email_preference_categories( int $user_id = 0 ): array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	$is_vendor = $user_id > 0 && function_exists( 'wpss_is_vendor' ) && wpss_is_vendor( $user_id );

	// Shared by everyone who can hold an order at all. The copy is written
	// neutrally so it reads correctly to a buyer and a seller alike.
	$categories = array(
		'messages'     => array(
			'label' => __( 'New messages', 'wp-sell-services' ),
			'desc'  => __( 'When someone sends you a message on an active order.', 'wp-sell-services' ),
		),
		'orders'       => $is_vendor
			? array(
				'label' => __( 'New orders', 'wp-sell-services' ),
				'desc'  => __( 'When a buyer places a new order on one of your services.', 'wp-sell-services' ),
			)
			: array(
				'label' => __( 'Order updates', 'wp-sell-services' ),
				'desc'  => __( 'Confirmation when you place an order, and progress updates on it.', 'wp-sell-services' ),
			),
		'completion'   => $is_vendor
			? array(
				'label' => __( 'Order completion + reviews', 'wp-sell-services' ),
				'desc'  => __( 'When a buyer approves a delivery or leaves a review.', 'wp-sell-services' ),
			)
			: array(
				'label' => __( 'Deliveries + completion', 'wp-sell-services' ),
				'desc'  => __( 'When your order is delivered, and when it is marked complete.', 'wp-sell-services' ),
			),
		'cancellation' => $is_vendor
			? array(
				'label' => __( 'Cancellation requests', 'wp-sell-services' ),
				'desc'  => __( 'When a buyer requests to cancel an active order.', 'wp-sell-services' ),
			)
			: array(
				'label' => __( 'Cancellations', 'wp-sell-services' ),
				'desc'  => __( 'When one of your orders is cancelled.', 'wp-sell-services' ),
			),
		'disputes'     => array(
			'label' => __( 'Disputes', 'wp-sell-services' ),
			'desc'  => __( 'Always recommended — disputes need a quick response to avoid escalation.', 'wp-sell-services' ),
		),
	);

	// Selling-only. A buyer is never tipped, never withdraws, and never receives
	// a proposal or a milestone request.
	if ( $is_vendor ) {
		$categories['tips']        = array(
			'label' => __( 'Tips received', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer sends you a tip on a completed order.', 'wp-sell-services' ),
		);
		$categories['withdrawals'] = array(
			'label' => __( 'Withdrawal status updates', 'wp-sell-services' ),
			'desc'  => __( 'When your withdrawal request is approved, paid out, or rejected.', 'wp-sell-services' ),
		);
		$categories['proposals']   = array(
			'label' => __( 'Proposals + milestones + extensions', 'wp-sell-services' ),
			'desc'  => __( 'When a proposal is accepted or a milestone / extension is paid.', 'wp-sell-services' ),
		);
	}

	/**
	 * Filters the email preference categories offered to a user.
	 *
	 * Add a category here when a feature introduces a new notification type, and
	 * map the type onto the same key in
	 * EmailService::get_user_pref_category() - a category with no types mapped
	 * to it is a switch that does nothing.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, array{label: string, desc: string}> $categories Categories.
	 * @param int                                               $user_id    User ID.
	 * @param bool                                              $is_vendor  Whether the user sells.
	 */
	return (array) apply_filters( 'wpss_email_preference_categories', $categories, $user_id, $is_vendor );
}

/**
 * Whether a user is currently active on the site.
 *
 * Reuses `_wpss_last_active` - the presence key VendorService::update_last_active()
 * already writes and that the seller card and vendor card already read - rather
 * than introducing a second, competing definition of "online". The 15-minute
 * window matches what those surfaces show, so a member described as online in
 * the UI is treated as online here too.
 *
 * @since 1.6.0
 *
 * @param int $user_id User ID.
 * @return bool True when the user has been active within the presence window.
 */
function wpss_user_is_online( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	$last_active = get_user_meta( $user_id, '_wpss_last_active', true );

	if ( ! $last_active ) {
		return false;
	}

	$seen = strtotime( (string) $last_active );

	if ( ! $seen ) {
		return false;
	}

	/**
	 * Filters the presence window, in seconds.
	 *
	 * @since 1.6.0
	 *
	 * @param int $window  Seconds since last activity that still counts as online.
	 * @param int $user_id User ID.
	 */
	$window = (int) apply_filters( 'wpss_presence_window', 15 * MINUTE_IN_SECONDS, $user_id );

	return ( time() - $seen ) < $window;
}

/**
 * Whether a message email should be suppressed because the recipient is here.
 *
 * Emailing someone who is reading the message on screen is noise, and it is the
 * most frequent email this plugin sends - every message on every active order
 * (#10159633576).
 *
 * OFF BY DEFAULT. Presence depends on `_wpss_last_active` being written as
 * people browse, and on a site where that is sparse this would silently
 * suppress mail people expect. Owners opt in, and the filter below lets a site
 * make its own call.
 *
 * @since 1.6.0
 *
 * @param int $recipient_id Recipient user ID.
 * @return bool True when the message email should be skipped.
 */
function wpss_should_skip_message_email( int $recipient_id ): bool {
	$settings = get_option( 'wpss_notification_settings', array() );
	// ON unless the owner has explicitly turned it off.
	//
	// This shipped off by default, which meant nobody got the benefit without
	// finding a setting they had no reason to look for -- and the behaviour it
	// prevents is the one people complain about: an email about a message they
	// are, at that moment, reading on screen.
	//
	// `array_key_exists` rather than `! empty`, so an explicit `false` is
	// honoured. Reading a missing key as "off" is what made the default
	// unreachable in the first place.
	$enabled = ! array_key_exists( 'skip_message_email_when_online', $settings )
		|| ! empty( $settings['skip_message_email_when_online'] );

	$skip = $enabled && wpss_user_is_online( $recipient_id );

	/**
	 * Filters whether a message email is skipped for an online recipient.
	 *
	 * @since 1.6.0
	 *
	 * @param bool $skip         Whether to skip the email.
	 * @param int  $recipient_id Recipient user ID.
	 * @param bool $enabled      Whether the site has the behaviour switched on.
	 */
	return (bool) apply_filters( 'wpss_skip_message_email_when_online', $skip, $recipient_id, $enabled );
}

/**
 * Whether an admin notification toggle is on.
 *
 * ONE reading of `wpss_notifications`, shared by EmailService and
 * NotificationService. The two used to disagree - EmailService read a missing
 * key as enabled, NotificationService read it as disabled - so a type added to
 * the registry after a site had saved its settings sent branded mail and
 * withheld plain mail for the same event. A key nobody has unticked is on.
 *
 * @since 1.7.1
 *
 * @param string $setting_key Setting key, e.g. `notify_new_order`.
 * @return bool True unless the owner has explicitly unticked the box.
 */
function wpss_notification_type_enabled( string $setting_key ): bool {
	$settings = get_option( 'wpss_notifications' );

	if ( ! is_array( $settings ) || ! array_key_exists( $setting_key, $settings ) ) {
		return true;
	}

	return ! empty( $settings[ $setting_key ] );
}

/**
 * Notification type => the Settings > Emails checkbox that governs it.
 *
 * ONE map. EmailService and NotificationService each kept their own, and the
 * two disagreed about `cancellation_requested`: the branded mail was gated on
 * `notify_cancellation_requested`, the plain mail on `notify_order_cancelled`.
 * So unticking "Cancellation Requested" silenced half the event, and unticking
 * "Order Cancelled" silenced the other half of an event the owner had not
 * touched (Basecamp #10268056021). A checkbox must do what its label says, and
 * it cannot while one type resolves to two settings.
 *
 * Keys are the type strings the senders pass - the values of
 * EmailService::TYPE_* and NotificationService::TYPE_*, plus the raw workflow
 * strings OrderWorkflowManager / DisputeWorkflowManager use. Literals rather
 * than class constants so this file stays free of class loading.
 *
 * Values are `notify_` + a key from Settings::get_notification_types(), which
 * is what renders the checkbox and what the sanitizer persists. A value with no
 * checkbox behind it is a gate nobody can open; tests/test-notification-toggle-contract.php
 * fails on that, and on any type that resolves to more than one setting.
 *
 * A type absent from this map is always sent - a new event ships live until an
 * owner is given a control for it. Deliberate absences: `seller_level_promotion`
 * (a milestone the vendor should always hear about), `test_email` (the owner
 * asked for it), and `proposal_rejected` / the `receipt_*` and `vendor_*`
 * account decisions, which have no checkbox of their own yet.
 *
 * @since 1.7.1
 *
 * @return array<string, string> Notification type => setting key.
 */
function wpss_notification_type_settings(): array {
	return array(
		// Orders: placing one, and everything up to work starting.
		'new_order'                  => 'notify_new_order',
		'order_created'              => 'notify_new_order',
		'order_status'               => 'notify_new_order',
		'order_confirmation'         => 'notify_new_order',
		'order_started'              => 'notify_new_order',
		'order_in_progress'          => 'notify_new_order',
		'requirements_submitted'     => 'notify_new_order',
		'submit_requirements'        => 'notify_new_order',
		'requirements_reminder'      => 'notify_new_order',
		'order_late'                 => 'notify_new_order',
		'deadline_reminder'          => 'notify_new_order',
		// Delivery.
		'delivery_ready'             => 'notify_delivery_submitted',
		'delivery_submitted'         => 'notify_delivery_submitted',
		'delivery_received'          => 'notify_delivery_submitted',
		'revision_requested'         => 'notify_revision_requested',
		// Completion.
		'order_completed'            => 'notify_order_completed',
		'order_completed_vendor'     => 'notify_order_completed',
		'order_auto_completed'       => 'notify_order_completed',
		'delivery_accepted'          => 'notify_order_completed',
		// Cancellation. The REQUEST and the CANCELLATION are two events with two
		// checkboxes, and each type belongs to exactly one of them.
		// `cancellation_submitted` is the buyer's copy of the request the vendor
		// is told about, so it follows the request; `cancellation_auto_approved`
		// is sent when the order is actually cancelled, so it follows that.
		'cancellation_requested'     => 'notify_cancellation_requested',
		'cancellation_submitted'     => 'notify_cancellation_requested',
		'order_cancelled'            => 'notify_order_cancelled',
		'cancellation_auto_approved' => 'notify_order_cancelled',
		// Messages.
		'new_message'                => 'notify_new_message',
		'vendor_contact'             => 'notify_vendor_contact',
		// Reviews.
		'review_received'            => 'notify_new_review',
		'review_reply'               => 'notify_review_reply',
		// Disputes.
		'dispute_opened'             => 'notify_dispute_opened',
		'dispute_resolved'           => 'notify_dispute_opened',
		'dispute_response_received'  => 'notify_dispute_opened',
		'dispute_reminder'           => 'notify_dispute_opened',
		'dispute_admin'              => 'notify_dispute_opened',
		'dispute_escalated'          => 'notify_dispute_escalated',
		'dispute_cancelled'          => 'notify_dispute_cancelled',
		// Withdrawals.
		'withdrawal_requested'       => 'notify_withdrawal_requested',
		'withdrawal_auto'            => 'notify_withdrawal_requested',
		'withdrawal_approved'        => 'notify_withdrawal_approved',
		'withdrawal_rejected'        => 'notify_withdrawal_rejected',
		// Proposals, milestones, extensions.
		'proposal_submitted'         => 'notify_proposal_submitted',
		'proposal_accepted'          => 'notify_proposal_accepted',
		'milestone_proposed'         => 'notify_milestone_proposed',
		'milestone_paid'             => 'notify_milestone_paid',
		'milestone_submitted'        => 'notify_milestone_submitted',
		'milestone_approved'         => 'notify_milestone_approved',
		'extension_proposed'         => 'notify_extension_proposed',
		'extension_approved'         => 'notify_extension_approved',
		'extension_declined'         => 'notify_extension_declined',
		// Tips.
		'tip_received'               => 'notify_tip_received',
		'tip_receipt'                => 'notify_tip_receipt',
		// Service moderation.
		'moderation_approved'        => 'notify_moderation',
		'moderation_rejected'        => 'notify_moderation',
		'moderation_pending'         => 'notify_moderation',
		// Buyer requests.
		'request_expired'            => 'notify_request_expired',
	);
}

/**
 * Whether a notification type may be sent at all.
 *
 * The one gate both senders ask, so the branded mail and the plain mail can
 * never answer differently for the same event.
 *
 * @since 1.7.1
 *
 * @param string $type Notification type, e.g. `cancellation_requested`.
 * @return bool True unless the owner has unticked the box that governs it.
 */
function wpss_notification_type_allowed( string $type ): bool {
	$map = wpss_notification_type_settings();

	// Unknown type: send. A new event ships live until it is given a control.
	return ! isset( $map[ $type ] ) || wpss_notification_type_enabled( $map[ $type ] );
}
