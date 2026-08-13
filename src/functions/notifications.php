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
	$enabled  = ! empty( $settings['skip_message_email_when_online'] );

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
