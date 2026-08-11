<?php
/**
 * Moderation: report/dispute reasons, account status and user blocking.
 *
 * Split out of src/functions.php, which had grown to 6,187 lines and 148
 * global functions in a single file. This is a positional move only - no
 * function was renamed, resignatured or changed, so every call site is
 * untouched. src/functions.php now just requires these files.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * The report-reason vocabulary.
 *
 * ONE map, feeding the REST `reason` enum, the moderation screen's labels and
 * the app contract alike. Every other vocabulary in this plugin that lived in
 * more than one place has drifted — order statuses were the last one, answering
 * "Pending_payment" over REST because the API kept its own copy. This one starts
 * with a single source on purpose.
 *
 * The keys are the wire format and must not be renamed once shipped: they are
 * stored on every report row. The labels are display copy and translatable.
 *
 * PORTFOLIO STANDARD. These reasons are deliberately generic — nothing here
 * mentions services, orders or vendors — so the same vocabulary, the same enum
 * and the same app screen work unchanged in every Wbcom plugin. A product that
 * genuinely needs an extra reason adds it through the filter rather than
 * renaming these.
 *
 * @since 1.5.1
 *
 * @return array<string,string> Reason key => translated label.
 */
function wpss_get_report_reasons(): array {
	$reasons = array(
		'spam'         => __( 'Spam or advertising', 'wp-sell-services' ),
		'offensive'    => __( 'Offensive or abusive', 'wp-sell-services' ),
		'harassment'   => __( 'Harassment or bullying', 'wp-sell-services' ),
		'scam'         => __( 'Scam or fraud', 'wp-sell-services' ),
		'off_platform' => __( 'Trying to take payment off the platform', 'wp-sell-services' ),
		'misleading'   => __( 'Misleading or inaccurate', 'wp-sell-services' ),
		'intellectual' => __( 'Copyright or trademark violation', 'wp-sell-services' ),
		'adult'        => __( 'Adult or explicit content', 'wp-sell-services' ),
		'other'        => __( 'Something else', 'wp-sell-services' ),
	);

	/**
	 * Filter the report reasons offered to members.
	 *
	 * Renaming a key orphans every report already stored under the old one.
	 * Add and remove; do not rename.
	 *
	 * @since 1.5.1
	 *
	 * @param array<string,string> $reasons Reason key => label.
	 */
	return apply_filters( 'wpss_report_reasons', $reasons );
}

/**
 * Why a buyer opens a dispute.
 *
 * These six existed as a hardcoded `<select>` in `templates/order/order-view.php`
 * and nowhere else — not filterable, not published, and invisible to the REST
 * API, whose `reason` arg is a free string with no enum. So a client had two
 * bad options: invent its own list, or ask the member to type one.
 *
 * Lifted here for the same reason the order statuses were: one map, so the web
 * form, any client and the site owner's filter cannot disagree about what a
 * dispute can be about. The keys are the wire format and are stored on every
 * dispute row — add and remove, never rename.
 *
 * @since 1.5.1
 *
 * @return array<string,string> Reason key => translated label.
 */
function wpss_get_dispute_reasons(): array {
	$reasons = array(
		'not_delivered'    => __( 'Work not delivered', 'wp-sell-services' ),
		'poor_quality'     => __( 'Poor quality work', 'wp-sell-services' ),
		'not_as_described' => __( 'Not as described', 'wp-sell-services' ),
		'communication'    => __( 'Communication issues', 'wp-sell-services' ),
		'deadline'         => __( 'Missed deadline', 'wp-sell-services' ),
		'other'            => __( 'Other', 'wp-sell-services' ),
	);

	/**
	 * Filter the reasons a buyer may give for opening a dispute.
	 *
	 * @since 1.5.1
	 *
	 * @param array<string,string> $reasons Reason key => label.
	 */
	return apply_filters( 'wpss_dispute_reasons', $reasons );
}

/**
 * What can be reported.
 *
 * Kept beside the reasons because the two travel together: a client needs both
 * to render the sheet, and the REST enum is built from this list.
 *
 * `user` is the one that matters for app-store review. Reporting a listing or a
 * message is content moderation; reporting a PERSON is what Guideline 1.2 asks
 * for, and it is the one every plugin in this portfolio was missing.
 *
 * @since 1.5.1
 *
 * @return array<string,string> Target type => translated label.
 */
function wpss_get_report_target_types(): array {
	$types = array(
		'user'    => __( 'Member', 'wp-sell-services' ),
		'service' => __( 'Service', 'wp-sell-services' ),
		'review'  => __( 'Review', 'wp-sell-services' ),
		'message' => __( 'Message', 'wp-sell-services' ),
	);

	/**
	 * Filter what members may report.
	 *
	 * @since 1.5.1
	 *
	 * @param array<string,string> $types Target type => label.
	 */
	return apply_filters( 'wpss_report_target_types', $types );
}

/**
 * A member's account standing.
 *
 * Stored on the WordPress user, not on a vendor profile row, and that placement
 * is the whole point. Buyers and sellers are the same kind of thing here — WP
 * users — and an abusive buyer was previously impossible to stop because the
 * only status column in this plugin lived on `wpss_vendor_profiles`. A parallel
 * "buyer status" table would have been a second concept to keep in sync; a user
 * meta key is one.
 *
 * DISTINCT FROM VENDOR STATUS, deliberately. `wpss_get_vendor_status()` answers
 * "how far through the seller application are you?" (pending, active, rejected).
 * This answers "are you in good standing on this marketplace?" A member can be
 * an approved vendor AND banned; both gates run.
 *
 * @since 1.5.1
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @return string One of 'active', 'suspended', 'banned'.
 */
function wpss_get_account_status( int $user_id = 0 ): string {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	$status  = (string) get_user_meta( $user_id, 'wpss_account_status', true );

	// No meta means never actioned, which is the overwhelming majority of
	// members. Absence is good standing, not an unknown to fail closed on.
	if ( '' === $status ) {
		$status = 'active';
	}

	/**
	 * Filter a member's account standing.
	 *
	 * @since 1.5.1
	 *
	 * @param string $status  One of active, suspended, banned.
	 * @param int    $user_id User ID.
	 */
	return (string) apply_filters( 'wpss_account_status', $status, $user_id );
}

/**
 * Guard: does this member's account standing forbid taking part?
 *
 * Same shape and same philosophy as {@see wpss_vendor_status_block()}: it blocks
 * NEW activity, not the completion of obligations a buyer has already paid for.
 * A banned seller can still deliver work someone paid for, and a banned buyer
 * can still receive it and mark it complete. Stranding paid work punishes the
 * counterparty, who did nothing wrong, and leaves the owner refunding by hand.
 *
 * Administrators are never blocked.
 *
 * @since 1.5.1
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @return WP_Error|null WP_Error when the standing forbids it, null when allowed.
 */
function wpss_account_status_block( int $user_id = 0 ) {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( user_can( $user_id, 'manage_options' ) ) {
		return null;
	}

	$status = wpss_get_account_status( $user_id );

	if ( 'active' === $status ) {
		return null;
	}

	$blocked = array(
		'suspended' => __( 'Your account is suspended.', 'wp-sell-services' ),
		'banned'    => __( 'Your account has been closed.', 'wp-sell-services' ),
	);

	// An unrecognised standing is refused rather than waved through, so a state
	// added later has to be classified on purpose.
	$message = $blocked[ $status ] ?? __( 'Your account is not active.', 'wp-sell-services' );

	return new WP_Error( 'wpss_account_' . $status, $message, array( 'status' => 403 ) );
}

/**
 * Members this user has blocked.
 *
 * User meta rather than a table: a block list is small, per-user, always read
 * whole, and never reported on. A table would buy nothing and cost a join on
 * every message read.
 *
 * @since 1.5.1
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @return int[] Blocked user IDs.
 */
function wpss_get_blocked_users( int $user_id = 0 ): array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	$blocked = get_user_meta( $user_id, 'wpss_blocked_users', true );

	return is_array( $blocked ) ? array_values( array_unique( array_map( 'absint', $blocked ) ) ) : array();
}

/**
 * Is there a block between these two members, in EITHER direction?
 *
 * Direction-blind on purpose. If A blocks B, B must not be able to reach A
 * either — a block a determined person can walk around by messaging first is
 * not a block, and "they blocked me so I can still contact them" is exactly the
 * hole app-store review looks for.
 *
 * @since 1.5.1
 *
 * @param int $user_a First user ID.
 * @param int $user_b Second user ID.
 * @return bool
 */
function wpss_is_blocked_between( int $user_a, int $user_b ): bool {
	if ( $user_a <= 0 || $user_b <= 0 || $user_a === $user_b ) {
		return false;
	}

	return in_array( $user_b, wpss_get_blocked_users( $user_a ), true )
		|| in_array( $user_a, wpss_get_blocked_users( $user_b ), true );
}
