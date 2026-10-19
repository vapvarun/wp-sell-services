<?php
/**
 * Order and payment status labels, groups and priority.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The words for a cancellation reason.
 *
 * The one list: the order screens, the notification, the email and the status
 * history each carried their own copy, and an immediate cancel stored the raw
 * key ("changed_mind") where a person would read it (Basecamp 10351457462).
 * A reason that is not one of the keys - free text from a vendor or an app -
 * is returned as written.
 *
 * @since 1.8.0
 *
 * @param string $reason Reason key, or free text.
 * @return string
 */
function wpss_get_cancellation_reason_label( string $reason ): string {
	$labels = array(
		'changed_mind'         => __( 'Changed my mind', 'wp-sell-services' ),
		'found_alternative'    => __( 'Found an alternative', 'wp-sell-services' ),
		'taking_too_long'      => __( 'Taking too long', 'wp-sell-services' ),
		'wrong_order'          => __( 'Ordered by mistake', 'wp-sell-services' ),
		'communication_issues' => __( 'Communication issues with vendor', 'wp-sell-services' ),
		'other'                => __( 'Other', 'wp-sell-services' ),
	);

	return $labels[ $reason ] ?? $reason;
}

/**
 * Get order status label.
 *
 * @param string $status Status key.
 * @return string
 */
function wpss_get_order_status_label( string $status ): string {
	$statuses = wpss_get_order_statuses();

	return $statuses[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
}

/**
 * Get all order statuses.
 *
 * @return array
 */
function wpss_get_order_statuses(): array {
	$statuses = array(
		'pending_payment'        => __( 'Pending Payment', 'wp-sell-services' ),
		'pending_requirements'   => __( 'Pending Requirements', 'wp-sell-services' ),
		'pending_approval'       => __( 'Pending Approval', 'wp-sell-services' ),
		'pending'                => __( 'Pending', 'wp-sell-services' ),
		'accepted'               => __( 'Accepted', 'wp-sell-services' ),
		'rejected'               => __( 'Rejected', 'wp-sell-services' ),
		'requirements_submitted' => __( 'Requirements Submitted', 'wp-sell-services' ),
		'in_progress'            => __( 'In Progress', 'wp-sell-services' ),
		'delivered'              => __( 'Delivered', 'wp-sell-services' ),
		'on_hold'                => __( 'On Hold', 'wp-sell-services' ),
		'late'                   => __( 'Late', 'wp-sell-services' ),
		'cancellation_requested' => __( 'Cancellation Requested', 'wp-sell-services' ),
		'revision_requested'     => __( 'Revision Requested', 'wp-sell-services' ),
		'completed'              => __( 'Completed', 'wp-sell-services' ),
		'cancelled'              => __( 'Cancelled', 'wp-sell-services' ),
		'disputed'               => __( 'Disputed', 'wp-sell-services' ),
		'refunded'               => __( 'Refunded', 'wp-sell-services' ),
		'partially_refunded'     => __( 'Partially Refunded', 'wp-sell-services' ),
	);

	/**
	 * Filter order statuses.
	 *
	 * @param array $statuses Order statuses array.
	 */
	return apply_filters( 'wpss_order_statuses', $statuses );
}

/**
 * Get order status labels array.
 *
 * Alias for wpss_get_order_statuses() for backward compatibility.
 *
 * @since 1.1.0
 *
 * @return array<string, string> Status key => label pairs.
 */
function wpss_get_order_status_labels(): array {
	return wpss_get_order_statuses();
}

/**
 * Group every order status into the handful of buckets a buyer thinks in.
 *
 * A buyer with 149 orders does not scan eighteen statuses; they want to know
 * what needs them, what is running, and what is over. This is the single
 * definition of those buckets - the filter chips, the query behind them and
 * the per-chip counts all read it, so a status cannot end up in a chip that
 * does not select it (Basecamp 10240019463).
 *
 * Order matters: it is the order the chips render in, and the order the list
 * sorts by when no chip is chosen, so the rows that need the buyer come first.
 * `all` deliberately carries no statuses - it means "no WHERE clause", not
 * "every status", so a status added later still appears under All without
 * being added here first.
 *
 * Every status ServiceOrder declares belongs to exactly one bucket. That is
 * asserted in tests/test-order-filters-contract.php rather than trusted.
 *
 * @since 1.7.0
 *
 * @return array<string, array{label: string, statuses: string[]}>
 */
function wpss_get_order_status_groups(): array {
	$groups = array(
		'all'       => array(
			'label'    => __( 'All', 'wp-sell-services' ),
			'statuses' => array(),
		),
		'awaiting'  => array(
			'label'    => __( 'Awaiting payment', 'wp-sell-services' ),
			'statuses' => array( 'pending_payment', 'pending' ),
		),
		'active'    => array(
			'label'    => __( 'Active', 'wp-sell-services' ),
			'statuses' => array(
				'pending_requirements',
				'requirements_submitted',
				'accepted',
				'in_progress',
				'revision_requested',
				'delivered',
				'pending_approval',
				'on_hold',
				'late',
			),
		),
		'disputed'  => array(
			'label'    => __( 'Needs attention', 'wp-sell-services' ),
			'statuses' => array( 'disputed', 'cancellation_requested' ),
		),
		'completed' => array(
			'label'    => __( 'Completed', 'wp-sell-services' ),
			'statuses' => array( 'completed' ),
		),
		'closed'    => array(
			'label'    => __( 'Cancelled', 'wp-sell-services' ),
			'statuses' => array( 'cancelled', 'rejected', 'refunded', 'partially_refunded' ),
		),
	);

	/**
	 * Filter the order status groups used by the dashboard filter chips.
	 *
	 * A group whose statuses are removed disappears from the chip row; adding
	 * a status to a group makes it selectable there. Keep the buckets mutually
	 * exclusive - a status in two groups is counted twice.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, array{label: string, statuses: string[]}> $groups Status groups.
	 */
	return apply_filters( 'wpss_order_status_groups', $groups );
}

/**
 * Resolve a group key from the request into the statuses it selects.
 *
 * Returns an empty array for `all` and for anything unrecognised, which the
 * repository reads as "no status filter" - so a hand-edited query arg widens
 * the list rather than emptying it.
 *
 * @since 1.7.0
 *
 * @param string $group_key Group key.
 * @return string[] Statuses, empty for no filter.
 */
function wpss_resolve_order_status_group( string $group_key ): array {
	$groups = wpss_get_order_status_groups();

	return isset( $groups[ $group_key ] ) ? $groups[ $group_key ]['statuses'] : array();
}

/**
 * Status groups for a dashboard list, plus an `Other` chip for any status the
 * data holds that no group claims (see wpss_resolve_ungrouped_statuses()).
 *
 * Appended rather than inserted, so a stray status never outranks a real one.
 * Buyer and seller lists both resolve their chips through here.
 *
 * @since 1.7.1
 *
 * @param array<string, int> $status_counts Status => count, from a *_grouped() repository count.
 * @return array<string, array{label: string, statuses: string[]}> Groups keyed by chip.
 */
function wpss_get_order_filter_groups( array $status_counts ): array {
	$groups    = wpss_get_order_status_groups();
	$ungrouped = wpss_resolve_ungrouped_statuses( $status_counts );

	if ( $ungrouped ) {
		$groups['other'] = array(
			'label'    => __( 'Other', 'wp-sell-services' ),
			'statuses' => $ungrouped,
		);
	}

	return $groups;
}

/**
 * Statuses present in the data that no group claims.
 *
 * The buckets are built from ServiceOrder's declared statuses, but the column
 * is a plain varchar and the database has been written by more than one
 * release. This install carries an order whose status is `pending_review` -
 * a DISPUTE status, not an order status, so it is in no bucket and the chips
 * summed to 14 against a real total of 15. One order the buyer could not reach
 * from any filter.
 *
 * Rather than name that one value and wait for the next one, anything the
 * groups do not claim is collected here and offered as its own chip. The chips
 * then always add up to the list, whatever ends up in the column.
 *
 * @since 1.7.0
 *
 * @param array<string, int> $status_counts Status => count, from count_by_customer_grouped().
 * @return string[] Statuses no group claims and that actually have rows.
 */
function wpss_resolve_ungrouped_statuses( array $status_counts ): array {
	$claimed = array();

	foreach ( wpss_get_order_status_groups() as $key => $group ) {
		if ( 'all' === $key ) {
			continue;
		}
		foreach ( $group['statuses'] as $status ) {
			$claimed[ $status ] = true;
		}
	}

	$ungrouped = array();

	foreach ( $status_counts as $status => $count ) {
		if ( $count > 0 && ! isset( $claimed[ $status ] ) ) {
			$ungrouped[] = (string) $status;
		}
	}

	return $ungrouped;
}

/**
 * Statuses in the order a buyer should meet them, most actionable first.
 *
 * Flattened from the group order above so the default list surfaces the orders
 * waiting on the buyer before the ones that are finished, without hiding
 * anything. Used to build the ORDER BY.
 *
 * @since 1.7.0
 *
 * @return string[]
 */
function wpss_get_order_status_priority(): array {
	$priority = array();

	foreach ( wpss_get_order_status_groups() as $key => $group ) {
		if ( 'all' === $key ) {
			continue;
		}
		foreach ( $group['statuses'] as $status ) {
			$priority[] = $status;
		}
	}

	return $priority;
}

/**
 * The one payment label for an order: "Refunded · Stripe", "Paid · Stripe",
 * "Not paid · Offline Payment".
 *
 * The order screens and the admin Orders list each had a copy; the list's
 * copy had no refunded branch and showed "Paid · Stripe" on a refunded order
 * (Basecamp 10346167214).
 *
 * @since 1.8.0
 *
 * @param string $payment_status The order's payment_status.
 * @param bool   $paid_at_set    Whether the order has a paid_at time.
 * @param string $payment_method The order's payment_method slug.
 * @return string
 */
function wpss_get_payment_status_label( string $payment_status, bool $paid_at_set, string $payment_method ): string {
	$method = wpss_get_payment_method_label( $payment_method );

	if ( 'refunded' === $payment_status ) {
		/* translators: %s: payment method, e.g. Stripe. */
		return '' !== $method ? sprintf( __( 'Refunded · %s', 'wp-sell-services' ), $method ) : __( 'Refunded', 'wp-sell-services' );
	}

	// Cash on delivery marks the store order paid before any cash changes hands; do not claim money was received.
	if ( 'cod' === $payment_method ) {
		return __( 'Pay on delivery', 'wp-sell-services' );
	}

	if ( 'paid' === $payment_status || $paid_at_set ) {
		/* translators: %s: payment method, e.g. Stripe. */
		return '' !== $method ? sprintf( __( 'Paid · %s', 'wp-sell-services' ), $method ) : __( 'Paid', 'wp-sell-services' );
	}

	/* translators: %s: payment method, e.g. Offline Payment. */
	return '' !== $method ? sprintf( __( 'Not paid · %s', 'wp-sell-services' ), $method ) : __( 'Not paid', 'wp-sell-services' );
}

/**
 * Human label for an order's payment_method.
 *
 * A registered gateway names itself (get_name()); anything else - a rail slug
 * such as woocommerce or bacs, or 'manual' from Create Order - is shown as
 * words. Empty means no method was recorded (Basecamp 10337161480).
 *
 * @since 1.8.0
 *
 * @param string $method Stored payment_method.
 * @return string Label, '' when none was recorded.
 */
function wpss_get_payment_method_label( string $method ): string {
	if ( '' === $method ) {
		return '';
	}

	$gateway = wpss()->get_payment_gateway( $method );
	if ( $gateway ) {
		return $gateway->get_name();
	}

	// A WooCommerce gateway slug (cod, bacs, cheque) names itself with its own title.
	if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
		$woo_gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $woo_gateways[ $method ] ) ) {
			return wp_strip_all_tags( $woo_gateways[ $method ]->get_title() );
		}
	}

	return ucwords( str_replace( array( '_', '-' ), ' ', $method ) );
}

/**
 * Map a payment rail's order status onto a WPSS order status.
 *
 * ONE table for every rail, replacing the private arrays that used to live in
 * WCOrderProvider, FluentCartAdapter and EDDOrderProvider. Those three had
 * drifted into disagreeing about the same event:
 *
 * - WooCommerce mapped `refunded` to `refunded`; FluentCart mapped it to
 *   `cancelled`, losing the refund state entirely. The Woo map carried a comment
 *   explaining why `cancelled` was wrong and the fix was never carried across.
 * - FluentCart mapped `partially_refunded` to `on_hold`, even though WPSS has a
 *   real `partially_refunded` status.
 * - The same status was spelled `on_hold` by one rail and `on-hold` by another.
 * - EDD emitted `processing` and `failed`, which are not WPSS statuses at all.
 *
 * Two rules make the table safe to extend:
 *
 * 1. KEYS ARE NORMALISED. Rails spell the same status differently ('on-hold' vs
 *    'on_hold', 'complete' vs 'completed'), so the incoming key is lowercased
 *    and hyphens folded to underscores before lookup. The spelling divergence
 *    cannot come back.
 *
 * 2. PAID TRANSITIONS ARE NOT IN THE TABLE. `completed` / `processing` /
 *    `complete` deliberately return null. Payment must go through
 *    StandaloneOrderProvider::mark_as_paid(), which is the only place
 *    `wpss_order_paid` is fired - and that hook is what credits the vendor,
 *    records commission and drives milestones, extensions and tips. A rail that
 *    "helpfully" mapped completed -> in_progress moved the order forward while
 *    paying nobody, which is exactly how three rails shipped without ever
 *    crediting a vendor. Returning null here is load-bearing, not an omission.
 *
 * @since 1.6.0
 *
 * @param string $platform    Rail identifier ('woocommerce', 'edd', 'fluentcart').
 * @param string $rail_status The rail's own status string.
 * @return string|null WPSS status, or null when the rail status must not drive a status change.
 */
function wpss_map_rail_status( string $platform, string $rail_status ): ?string {
	$key = str_replace( '-', '_', strtolower( trim( $rail_status ) ) );

	// Shared across every rail. A refund is a refund on all of them.
	$shared = array(
		'cancelled'          => 'cancelled',
		'canceled'           => 'cancelled',
		'failed'             => 'cancelled',
		'refunded'           => 'refunded',
		'partially_refunded' => 'partially_refunded',
		'on_hold'            => 'on_hold',
		'pending'            => 'pending_payment',
	);

	// Rail-specific vocabulary only. Anything a rail shares with the others
	// belongs above, not here.
	$per_rail = array(
		'edd' => array(
			'abandoned' => 'cancelled',
			'revoked'   => 'cancelled',
		),
	);

	$map = array_merge( $shared, $per_rail[ strtolower( $platform ) ] ?? array() );

	/**
	 * Filters the rail status map.
	 *
	 * Keys must already be normalised (lowercase, underscores). Values must be
	 * real WPSS statuses - anything else is discarded below.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, string> $map      Normalised rail status => WPSS status.
	 * @param string                $platform Rail identifier.
	 */
	$map = (array) apply_filters( 'wpss_rail_status_map', $map, $platform );

	$mapped = $map[ $key ] ?? null;

	if ( null === $mapped ) {
		return null;
	}

	// Never hand back a status the plugin does not know. A rail (or a filter)
	// producing an unregistered status is how EDD came to write 'processing',
	// which then had no label, no filter entry and no transition rules.
	return array_key_exists( $mapped, wpss_get_order_statuses() ) ? $mapped : null;
}
