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

	// Selling-only. A buyer is never tipped and never withdraws.
	if ( $is_vendor ) {
		$categories['tips']        = array(
			'label' => __( 'Tips received', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer sends you a tip on a completed order.', 'wp-sell-services' ),
		);
		$categories['withdrawals'] = array(
			'label' => __( 'Withdrawal status updates', 'wp-sell-services' ),
			'desc'  => __( 'When your withdrawal request is approved, paid out, or rejected.', 'wp-sell-services' ),
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
		'new_order'                    => 'notify_new_order',
		'order_created'                => 'notify_new_order',
		'order_status'                 => 'notify_new_order',
		'order_confirmation'           => 'notify_new_order',
		'order_started'                => 'notify_new_order',
		'order_in_progress'            => 'notify_new_order',
		'requirements_submitted'       => 'notify_new_order',
		'submit_requirements'          => 'notify_new_order',
		'requirements_reminder'        => 'notify_new_order',
		'order_late'                   => 'notify_new_order',
		'deadline_reminder'            => 'notify_new_order',
		// Delivery.
		'delivery_ready'               => 'notify_delivery_submitted',
		'delivery_submitted'           => 'notify_delivery_submitted',
		'delivery_received'            => 'notify_delivery_submitted',
		'revision_requested'           => 'notify_revision_requested',
		// Completion.
		'order_completed'              => 'notify_order_completed',
		'order_completed_vendor'       => 'notify_order_completed',
		'order_auto_completed'         => 'notify_order_completed',
		'delivery_accepted'            => 'notify_order_completed',
		// Cancellation. The REQUEST and the CANCELLATION are two events with two
		// checkboxes, and each type belongs to exactly one of them.
		// `cancellation_submitted` is the buyer's copy of the request the vendor
		// is told about, so it follows the request; `cancellation_auto_approved`
		// is sent when the order is actually cancelled, so it follows that.
		'cancellation_requested'       => 'notify_cancellation_requested',
		'cancellation_submitted'       => 'notify_cancellation_requested',
		'order_cancelled'              => 'notify_order_cancelled',
		'cancellation_auto_approved'   => 'notify_order_cancelled',
		// Messages.
		'new_message'                  => 'notify_new_message',
		'vendor_contact'               => 'notify_vendor_contact',
		// Reviews.
		'review_received'              => 'notify_new_review',
		'review_reply'                 => 'notify_review_reply',
		// Disputes.
		'dispute_opened'               => 'notify_dispute_opened',
		'dispute_resolved'             => 'notify_dispute_opened',
		'dispute_response_received'    => 'notify_dispute_opened',
		'dispute_reminder'             => 'notify_dispute_opened',
		'dispute_admin'                => 'notify_dispute_opened',
		'dispute_escalated'            => 'notify_dispute_escalated',
		'dispute_cancelled'            => 'notify_dispute_cancelled',
		// Withdrawals.
		'withdrawal_requested'         => 'notify_withdrawal_requested',
		'withdrawal_auto'              => 'notify_withdrawal_requested',
		'withdrawal_approved'          => 'notify_withdrawal_approved',
		'withdrawal_completed'         => 'notify_withdrawal_completed',
		'withdrawal_rejected'          => 'notify_withdrawal_rejected',
		// Proposals, milestones, extensions.
		'proposal_submitted'           => 'notify_proposal_submitted',
		'proposal_accepted'            => 'notify_proposal_accepted',
		'milestone_proposed'           => 'notify_milestone_proposed',
		'milestone_paid'               => 'notify_milestone_paid',
		'milestone_submitted'          => 'notify_milestone_submitted',
		'milestone_approved'           => 'notify_milestone_approved',
		'milestone_revision_requested' => 'notify_milestone_submitted',
		'extension_proposed'           => 'notify_extension_proposed',
		'extension_approved'           => 'notify_extension_approved',
		'extension_declined'           => 'notify_extension_declined',
		// Tips.
		'tip_received'                 => 'notify_tip_received',
		'tip_receipt'                  => 'notify_tip_receipt',
		// Service moderation.
		'moderation_approved'          => 'notify_moderation',
		'moderation_rejected'          => 'notify_moderation',
		'moderation_pending'           => 'notify_moderation',
		// Buyer requests.
		'request_expired'              => 'notify_request_expired',
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

/**
 * Save a member's email preferences from a submitted checkbox set.
 *
 * Only the categories this user is OFFERED are written (the list is role-aware,
 * Basecamp #10159633379): a checkbox that is absent means muted, and categories
 * the form did not show keep their stored value, so a buyer who saves never
 * mutes the seller mail they would get after becoming a vendor.
 *
 * One writer for the profile form (wpss_save_member_profile(), REST and AJAX)
 * and the older standalone AJAX action.
 *
 * @since 1.8.0
 *
 * @param int                 $user_id   Member.
 * @param array<string,mixed> $submitted Checked keys => any value.
 * @return array<string,bool>|null Saved preferences, or null if the write did not persist.
 */
function wpss_save_email_preferences( int $user_id, array $submitted ): ?array {
	$existing    = get_user_meta( $user_id, 'wpss_email_preferences', true );
	$preferences = is_array( $existing ) ? $existing : array();

	foreach ( array_keys( wpss_get_email_preference_categories( $user_id ) ) as $key ) {
		$preferences[ $key ] = isset( $submitted[ $key ] );
	}

	update_user_meta( $user_id, 'wpss_email_preferences', $preferences );

	// Read back rather than trust update_user_meta()'s return, which is also
	// false for "unchanged" (Basecamp #9983538201).
	wp_cache_delete( $user_id, 'user_meta' );

	return get_user_meta( $user_id, 'wpss_email_preferences', true ) === $preferences ? $preferences : null;
}

/**
 * Add a notification for a user.
 *
 * Helper function to simplify adding notifications via NotificationService.
 *
 * @since 1.0.0
 *
 * @param int    $user_id User ID to notify.
 * @param string $type    Notification type.
 * @param string $message Notification message.
 * @param array  $data    Additional data.
 * @return int|false Notification ID or false on failure.
 */
function wpss_add_notification( int $user_id, string $type, string $message, array $data = array() ) {
	$notification_service = new \WPSellServices\Services\NotificationService();

	// Generate title from type.
	$type_titles = array(
		'order_created'        => __( 'New Order', 'wp-sell-services' ),
		'order_status'         => __( 'Order Update', 'wp-sell-services' ),
		'new_message'          => __( 'New Message', 'wp-sell-services' ),
		'delivery_submitted'   => __( 'Delivery Submitted', 'wp-sell-services' ),
		'delivery_accepted'    => __( 'Delivery Accepted', 'wp-sell-services' ),
		'revision_requested'   => __( 'Revision Requested', 'wp-sell-services' ),
		'review_received'      => __( 'New Review', 'wp-sell-services' ),
		'dispute_opened'       => __( 'Dispute Opened', 'wp-sell-services' ),
		'dispute_resolved'     => __( 'Dispute Resolved', 'wp-sell-services' ),
		'deadline_warning'     => __( 'Deadline Warning', 'wp-sell-services' ),
		'service_approved'     => __( 'Service Approved', 'wp-sell-services' ),
		'service_rejected'     => __( 'Service Requires Changes', 'wp-sell-services' ),
		'withdrawal_pending'   => __( 'Withdrawal Request', 'wp-sell-services' ),
		'withdrawal_approved'  => __( 'Withdrawal Approved', 'wp-sell-services' ),
		'withdrawal_completed' => __( 'Withdrawal Paid', 'wp-sell-services' ),
		'withdrawal_rejected'  => __( 'Withdrawal Rejected', 'wp-sell-services' ),
	);

	$title = $type_titles[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );

	return $notification_service->create( $user_id, $type, $title, $message, $data );
}

/**
 * Where a notification leads: the thing it is about.
 *
 * An explicit action_url wins; otherwise the IDs the notification already
 * carries name the target - a thread with no order goes to Messages, anything
 * with an order goes to that order (the dashboard picks the buyer or seller
 * side), then a request, a withdrawal. Most rows carry only an order_id, so
 * the list, the REST payload and the email button all read the link from here
 * instead of each rows' empty action_url column (Basecamp 10337217098).
 *
 * @since 1.8.0
 *
 * @param array       $data       The notification's data payload.
 * @param string|null $action_url The stored action_url column, if any.
 * @return string URL, or '' when the notification points at nothing.
 */
function wpss_get_notification_url( array $data, ?string $action_url = null ): string {
	foreach ( array( $action_url, $data['action_url'] ?? '', $data['link'] ?? '' ) as $url ) {
		if ( ! empty( $url ) ) {
			return (string) $url;
		}
	}

	if ( ! empty( $data['conversation_id'] ) && empty( $data['order_id'] ) ) {
		return add_query_arg(
			array(
				'section'         => 'messages',
				'conversation_id' => (int) $data['conversation_id'],
			),
			wpss_get_dashboard_url()
		);
	}

	// A dispute notification opens the dispute, not its order (Basecamp
	// 10337159668). The dashboard view admits only the two parties, so the
	// site's admin goes to the admin dispute screen.
	if ( ! empty( $data['dispute_id'] ) ) {
		return current_user_can( 'manage_options' )
			? admin_url( 'admin.php?page=wpss-disputes&action=view&dispute_id=' . (int) $data['dispute_id'] )
			: add_query_arg( 'dispute', (int) $data['dispute_id'], wpss_get_dashboard_url( 'disputes' ) );
	}

	foreach ( array( 'order_id', 'parent_order_id' ) as $key ) {
		if ( ! empty( $data[ $key ] ) ) {
			$url = wpss_get_order_url( (int) $data[ $key ] );
			if ( '' !== $url ) {
				return $url;
			}
		}
	}

	if ( ! empty( $data['request_id'] ) ) {
		return (string) get_permalink( (int) $data['request_id'] );
	}

	if ( ! empty( $data['withdrawal_id'] ) ) {
		return wpss_get_dashboard_url( 'earnings' );
	}

	return '';
}

/**
 * WHERE clause for a member's notifications: the one place list, count and pager agree.
 *
 * @since 1.9.0
 *
 * @param int   $user_id User ID.
 * @param array $args    unread_only (bool), is_read (0|1), type (string notification type).
 * @return array{0: string, 1: array} SQL fragment starting at "user_id = %d", and its params.
 */
function wpss_notifications_where( int $user_id, array $args ): array {
	$sql    = 'user_id = %d';
	$params = array( $user_id );

	if ( ! empty( $args['unread_only'] ) ) {
		$sql .= ' AND is_read = 0';
	} elseif ( isset( $args['is_read'] ) ) {
		$sql     .= ' AND is_read = %d';
		$params[] = (int) (bool) $args['is_read'];
	}

	if ( ! empty( $args['type'] ) ) {
		$sql     .= ' AND type = %s';
		$params[] = sanitize_key( (string) $args['type'] );
	}

	return array( $sql, $params );
}

/**
 * Get notifications for a user, newest first.
 *
 * Ordered by created_at then id so rows written in the same second page
 * without repeats or gaps.
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, unread_only, type).
 * @return array Array of notification objects.
 */
function wpss_get_user_notifications( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	$args = wp_parse_args(
		$args,
		array(
			'limit'  => 20,
			'offset' => 0,
		)
	);

	list( $where, $params ) = wpss_notifications_where( $user_id, $args );
	$params[]               = max( 1, (int) $args['limit'] );
	$params[]               = max( 0, (int) $args['offset'] );

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is hardcoded fragments with placeholders; values come via prepare().
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $params ) );
}

/**
 * Count a user's notifications.
 *
 * Needed so the notifications screen can page: it used to take the 50 most
 * recent and stop, with nothing in the UI to reach anything older.
 *
 * @since 1.7.2
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (unread_only, type).
 * @return int
 */
function wpss_count_user_notifications( int $user_id, array $args = array() ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	list( $where, $params ) = wpss_notifications_where( $user_id, $args );

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is hardcoded fragments with placeholders; values come via prepare().
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
}

/**
 * Every notification type this plugin writes: label and Lucide icon.
 *
 * The one vocabulary behind the notification center's Type dropdown, the row
 * icon and the REST `icon` field. A type absent here still lists, filters and
 * shows a humanised label with the bell, so a new event is never hidden from the
 * filter; add a row to give it its own icon.
 *
 * @since 1.9.0
 *
 * @return array<string, array{label: string, icon: string}>
 */
function wpss_notification_types(): array {
	$types = array(
		'order_created'                => array( __( 'New order', 'wp-sell-services' ), 'package' ),
		'new_order'                    => array( __( 'New order', 'wp-sell-services' ), 'package' ),
		'order_confirmation'           => array( __( 'Order confirmation', 'wp-sell-services' ), 'package' ),
		'order_status'                 => array( __( 'Order update', 'wp-sell-services' ), 'refresh-cw' ),
		'order_started'                => array( __( 'Order ready to start', 'wp-sell-services' ), 'play' ),
		'order_in_progress'            => array( __( 'Order in progress', 'wp-sell-services' ), 'refresh-cw' ),
		'order_late'                   => array( __( 'Order late', 'wp-sell-services' ), 'triangle-alert' ),
		'order_cancelled'              => array( __( 'Order cancelled', 'wp-sell-services' ), 'x-circle' ),
		'order_completed'              => array( __( 'Order completed', 'wp-sell-services' ), 'check-circle' ),
		'order_completed_vendor'       => array( __( 'Order completed', 'wp-sell-services' ), 'check-circle' ),
		'order_auto_completed'         => array( __( 'Order auto-completed', 'wp-sell-services' ), 'check-circle' ),
		'deadline_reminder'            => array( __( 'Deadline reminder', 'wp-sell-services' ), 'calendar-clock' ),
		'deadline_warning'             => array( __( 'Deadline warning', 'wp-sell-services' ), 'calendar-clock' ),
		'requirements_pending'         => array( __( 'Requirements needed', 'wp-sell-services' ), 'clipboard-list' ),
		'requirements_reminder'        => array( __( 'Requirements reminder', 'wp-sell-services' ), 'clipboard-list' ),
		'requirements_submitted'       => array( __( 'Requirements submitted', 'wp-sell-services' ), 'clipboard-check' ),
		'submit_requirements'          => array( __( 'Requirements needed', 'wp-sell-services' ), 'clipboard-list' ),
		'requirements_timeout'         => array( __( 'Requirements timed out', 'wp-sell-services' ), 'triangle-alert' ),
		'new_message'                  => array( __( 'New message', 'wp-sell-services' ), 'message-circle' ),
		'vendor_contact'               => array( __( 'Vendor enquiry', 'wp-sell-services' ), 'message-circle' ),
		'delivery_submitted'           => array( __( 'Delivery submitted', 'wp-sell-services' ), 'upload' ),
		'delivery_ready'               => array( __( 'Delivery ready', 'wp-sell-services' ), 'upload' ),
		'delivery_received'            => array( __( 'Delivery received', 'wp-sell-services' ), 'upload' ),
		'delivery_accepted'            => array( __( 'Delivery accepted', 'wp-sell-services' ), 'check-circle' ),
		'revision_requested'           => array( __( 'Revision requested', 'wp-sell-services' ), 'rotate-ccw' ),
		'cancellation_requested'       => array( __( 'Cancellation requested', 'wp-sell-services' ), 'x-circle' ),
		'cancellation_submitted'       => array( __( 'Cancellation requested', 'wp-sell-services' ), 'x-circle' ),
		'cancellation_auto_approved'   => array( __( 'Cancellation approved', 'wp-sell-services' ), 'x-circle' ),
		'refund_pending'               => array( __( 'Refund pending', 'wp-sell-services' ), 'receipt' ),
		'refund_failed'                => array( __( 'Refund failed', 'wp-sell-services' ), 'triangle-alert' ),
		'review_received'              => array( __( 'Review received', 'wp-sell-services' ), 'star' ),
		'review_reply'                 => array( __( 'Review reply', 'wp-sell-services' ), 'star' ),
		'dispute_opened'               => array( __( 'Dispute opened', 'wp-sell-services' ), 'alert-triangle' ),
		'dispute_resolved'             => array( __( 'Dispute resolved', 'wp-sell-services' ), 'check' ),
		'dispute_response_received'    => array( __( 'Dispute response', 'wp-sell-services' ), 'message-square' ),
		'dispute_reminder'             => array( __( 'Dispute reminder', 'wp-sell-services' ), 'alert-triangle' ),
		'dispute_admin'                => array( __( 'Dispute update', 'wp-sell-services' ), 'alert-triangle' ),
		'dispute_escalated'            => array( __( 'Dispute escalated', 'wp-sell-services' ), 'alert-triangle' ),
		'dispute_response_reminder'    => array( __( 'Dispute response due', 'wp-sell-services' ), 'clock' ),
		'report_resolved'              => array( __( 'Report reviewed', 'wp-sell-services' ), 'flag' ),
		'dispute_cancelled'            => array( __( 'Dispute cancelled', 'wp-sell-services' ), 'x-circle' ),
		'proposal_submitted'           => array( __( 'Proposal submitted', 'wp-sell-services' ), 'briefcase' ),
		'proposal_received'            => array( __( 'Proposal received', 'wp-sell-services' ), 'briefcase' ),
		'proposal_accepted'            => array( __( 'Proposal accepted', 'wp-sell-services' ), 'thumbs-up' ),
		'proposal_rejected'            => array( __( 'Proposal rejected', 'wp-sell-services' ), 'thumbs-down' ),
		'milestone_proposed'           => array( __( 'Milestone proposed', 'wp-sell-services' ), 'flag' ),
		'milestone_paid'               => array( __( 'Milestone paid', 'wp-sell-services' ), 'banknote' ),
		'milestone_submitted'          => array( __( 'Milestone submitted', 'wp-sell-services' ), 'upload' ),
		'milestone_approved'           => array( __( 'Milestone approved', 'wp-sell-services' ), 'check-circle' ),
		'milestone_revision_requested' => array( __( 'Revision requested', 'wp-sell-services' ), 'rotate-ccw' ),
		'milestone_cancel_warning'     => array( __( 'Milestone cancellation', 'wp-sell-services' ), 'triangle-alert' ),
		'extension_proposed'           => array( __( 'Extension proposed', 'wp-sell-services' ), 'clock' ),
		'extension_approved'           => array( __( 'Extension approved', 'wp-sell-services' ), 'calendar' ),
		'extension_declined'           => array( __( 'Extension declined', 'wp-sell-services' ), 'x' ),
		'tip_received'                 => array( __( 'Tip received', 'wp-sell-services' ), 'heart' ),
		'tip_receipt'                  => array( __( 'Tip sent', 'wp-sell-services' ), 'heart' ),
		'withdrawal_requested'         => array( __( 'Withdrawal requested', 'wp-sell-services' ), 'landmark' ),
		'withdrawal_auto'              => array( __( 'Automatic withdrawal', 'wp-sell-services' ), 'landmark' ),
		'withdrawal_approved'          => array( __( 'Withdrawal approved', 'wp-sell-services' ), 'landmark' ),
		'withdrawal_completed'         => array( __( 'Withdrawal paid', 'wp-sell-services' ), 'landmark' ),
		'withdrawal_rejected'          => array( __( 'Withdrawal rejected', 'wp-sell-services' ), 'x-circle' ),
		'moderation_approved'          => array( __( 'Service approved', 'wp-sell-services' ), 'check-circle' ),
		'service_approved'             => array( __( 'Service approved', 'wp-sell-services' ), 'check-circle' ),
		'moderation_rejected'          => array( __( 'Service rejected', 'wp-sell-services' ), 'x-circle' ),
		'service_rejected'             => array( __( 'Service rejected', 'wp-sell-services' ), 'x-circle' ),
		'moderation_pending'           => array( __( 'Service awaiting review', 'wp-sell-services' ), 'clock' ),
		'request_expired'              => array( __( 'Request expired', 'wp-sell-services' ), 'clock' ),
		'vendor_approved'              => array( __( 'Vendor approved', 'wp-sell-services' ), 'check-circle' ),
		'vendor_rejected'              => array( __( 'Vendor rejected', 'wp-sell-services' ), 'x-circle' ),
		'vendor_suspended'             => array( __( 'Vendor suspended', 'wp-sell-services' ), 'triangle-alert' ),
	);

	$rows = array();
	foreach ( $types as $type => $row ) {
		$rows[ $type ] = array(
			'label' => $row[0],
			'icon'  => $row[1],
		);
	}

	/**
	 * Filter the notification type registry.
	 *
	 * @since 1.9.0
	 *
	 * @param array<string, array{label: string, icon: string}> $rows Type => label and Lucide icon name.
	 */
	return (array) apply_filters( 'wpss_notification_types', $rows );
}

/**
 * Display label for a notification type; unknown types are humanised.
 *
 * @since 1.9.0
 *
 * @param string $type Notification type.
 * @return string
 */
function wpss_notification_type_label( string $type ): string {
	return wpss_notification_types()[ $type ]['label'] ?? ucfirst( str_replace( '_', ' ', $type ) );
}

/**
 * Lucide icon name for a notification type; the bell when the type is unknown.
 *
 * @since 1.9.0
 *
 * @param string $type Notification type.
 * @return string
 */
function wpss_notification_type_icon( string $type ): string {
	return wpss_notification_types()[ $type ]['icon'] ?? 'bell';
}

/**
 * The notification types this member actually has, for the Type filter.
 *
 * Built from the member's own rows so the dropdown never offers an empty option
 * and never hides a type that exists. Covered by idx_user_created.
 *
 * @since 1.9.0
 *
 * @param int $user_id User ID.
 * @return string[] Types, sorted by their label.
 */
function wpss_get_user_notification_types( int $user_id ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	$types = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT type FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	usort( $types, static fn( string $a, string $b ): int => strcasecmp( wpss_notification_type_label( $a ), wpss_notification_type_label( $b ) ) );

	return $types;
}
