<?php
/**
 * Orders: lookup, numbering, statuses, requirements, package snapshots and counts.
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
 * Get order by ID.
 *
 * @param int $order_id Order ID.
 * @return \WPSellServices\Models\ServiceOrder|null
 */
function wpss_get_order( int $order_id ): ?\WPSellServices\Models\ServiceOrder {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_orders';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$order_id
		)
	);

	return $row ? \WPSellServices\Models\ServiceOrder::from_db( $row ) : null;
}

/**
 * Generate a unique, human-quotable order number.
 *
 * THE single generator. Every rail calls this so one install never mixes
 * formats: six call sites had hand-rolled the same eight-character shape while
 * the standalone rail produced something else entirely, so a buyer's order
 * number looked different depending on which checkout created it.
 *
 * The old standalone format was six random digits plus time() — e.g.
 * WPSS-309001-1785562349. Twenty-two characters for a buyer to read out to
 * support, and the length bought nothing: the timestamp was there for
 * uniqueness and so was the random number, yet neither was ever checked against
 * the table, so two orders created in the same second still collided at roughly
 * one in 900k. It also published each order's creation time.
 *
 * @since 1.0.0
 *
 * @return string
 */
function wpss_generate_order_number(): string {
	global $wpdb;

	$prefix = apply_filters( 'wpss_order_number_prefix', 'WPSS-' );
	$table  = $wpdb->prefix . 'wpss_orders';

	// Uniqueness is now verified rather than assumed. Ten attempts is far more
	// than 36^8 needs; the time-suffixed fallback keeps checkout working rather
	// than failing a payment over a cosmetic identifier.
	for ( $attempt = 0; $attempt < 10; $attempt++ ) {
		$candidate = $prefix . strtoupper( wp_generate_password( 8, false ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$taken = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_number = %s LIMIT 1", $candidate ) );

		if ( ! $taken ) {
			return $candidate;
		}
	}

	wpss_log( 'Order number generation hit 10 collisions; falling back to a time-suffixed number.', 'warning' );

	return $prefix . strtoupper( wp_generate_password( 8, false ) ) . '-' . time();
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
 * Check if user can view order.
 *
 * @param int      $order_id Order ID.
 * @param int|null $user_id  User ID. Defaults to current user.
 * @return bool
 */
function wpss_user_can_view_order( int $order_id, ?int $user_id = null ): bool {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	// Admins can view all orders.
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return false;
	}

	// Order participants can view.
	return (int) $order->customer_id === $user_id || (int) $order->vendor_id === $user_id;
}

/**
 * Resolve which side of an order a user is acting from.
 *
 * Order verbs are allowed per side, not per capability: a buyer is a plain
 * subscriber and a vendor is whoever the row names, so both are ownership
 * checks. Admin is answered first because an administrator who also sells
 * acts with the owner's authority on any order.
 *
 * @since 1.7.1
 *
 * @param object $order   Order with customer_id and vendor_id.
 * @param int    $user_id Acting user ID.
 * @return string 'admin', 'vendor', 'buyer', or '' for a stranger.
 */
function wpss_order_actor_role( object $order, int $user_id ): string {
	if ( ! $user_id ) {
		return '';
	}

	if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'wpss_manage_orders' ) ) {
		return 'admin';
	}

	if ( (int) $order->vendor_id === $user_id ) {
		return 'vendor';
	}

	if ( (int) $order->customer_id === $user_id ) {
		return 'buyer';
	}

	return '';
}

/**
 * Resolve the order ID named by the current request.
 *
 * Prefers the pretty-permalink query var (`wpss_order_id`) and falls back to
 * the legacy `?order_id=` query arg so old bookmarks and emailed links keep
 * working until they are 301'd.
 *
 * @since 1.7.0
 *
 * @return int Order ID, or 0 when the request names no order.
 */
function wpss_resolve_request_order_id(): int {
	$order_id = (int) get_query_var( 'wpss_order_id', 0 );

	if ( $order_id > 0 ) {
		return $order_id;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
	return isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
}

/**
 * Resolve the order action named by the current request (e.g. requirements).
 *
 * @since 1.7.0
 *
 * @return string Sanitized action slug, or empty string.
 */
function wpss_resolve_request_order_action(): string {
	$action = (string) get_query_var( 'wpss_order_action', '' );

	if ( '' !== $action ) {
		return sanitize_key( $action );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
	return isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
}

/**
 * Get order view URL.
 *
 * Pretty shape (when permalinks are on): `/{dashboard}/{section}/{id}/`
 * e.g. `/dashboard/orders/3407/` or `/dashboard/sales/3407/`.
 * Plain permalinks keep the `?order_id=` query form.
 *
 * @param int    $order_id Order ID.
 * @param string $section  Dashboard section (e.g. 'sales' for vendor orders).
 * @return string
 */
function wpss_get_order_url( int $order_id, string $section = '' ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	// Empty section means the buyer-side address. Callers that mean the vendor
	// sales view pass 'sales' explicitly (see sales list "View" links).
	if ( '' === $section ) {
		$section = 'orders';
	}

	$dashboard_url = wpss_get_dashboard_url( $section );

	if ( $dashboard_url ) {
		return wpss_append_dashboard_order( $dashboard_url, $order_id );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order_id . '/' );
}

/**
 * Human label for a submitted requirement key.
 *
 * The buyer's freeform brief is stored under the key `description`, and both
 * the order view and the admin order screen had to decide what to call it.
 * Only the order view did: admin printed the raw key, so the site owner read
 * "description" where the buyer and seller read "What the buyer asked for"
 * (Basecamp 10254444197). One flow, two labels.
 *
 * Any other key is a question the owner configured, so it is titled from its
 * own text rather than renamed.
 *
 * @since 1.7.0
 *
 * @param string $key Submitted field key.
 * @return string Label to show.
 */
function wpss_requirement_field_label( string $key ): string {
	$label = 'description' === $key
		? __( 'What the buyer asked for', 'wp-sell-services' )
		: ucfirst( str_replace( array( '_', '-' ), ' ', $key ) );

	/**
	 * Filter the label shown for a submitted requirement field.
	 *
	 * @since 1.7.0
	 *
	 * @param string $label Resolved label.
	 * @param string $key   Raw field key.
	 */
	return (string) apply_filters( 'wpss_requirement_field_label', $label, $key );
}

/**
 * Get order requirements URL.
 *
 * Pretty shape: `/{dashboard}/orders/{id}/requirements/`.
 *
 * A SUB-ORDER has no requirements step. A tip, a paid extension and a
 * milestone phase are all payments against work that already has its brief, so
 * sending the buyer to `/requirements/` gave them a URL and a page heading that
 * described something the screen was not - the content underneath is a tip
 * receipt or a phase receipt, and correct. Those land on the order itself.
 *
 * Every gateway routes its post-payment redirect through here, so fixing it in
 * one place covers Stripe, PayPal, offline and the intent service rather than
 * four separate redirects that would drift.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function wpss_get_order_requirements_url( int $order_id ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	$sub_order_platforms = function_exists( 'wpss_get_sub_order_platforms' ) ? wpss_get_sub_order_platforms() : array();

	if ( in_array( (string) ( $order->platform ?? '' ), $sub_order_platforms, true ) ) {
		return wpss_get_order_url( $order_id );
	}

	$dashboard_url = wpss_get_dashboard_url( 'orders' );

	if ( $dashboard_url ) {
		return wpss_append_dashboard_order( $dashboard_url, $order_id, 'requirements' );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order_id . '/requirements/' );
}

/**
 * Get service requirements (questions buyer must answer).
 *
 * @param int $service_id Service ID.
 * @return array
 */
function wpss_get_service_requirements( int $service_id ): array {
	$requirements = get_post_meta( $service_id, '_wpss_requirements', true );
	$requirements = is_array( $requirements ) ? $requirements : array();

	return array_map( 'wpss_normalize_requirement_choices', $requirements );
}

/**
 * Normalize a requirement's choice list into one canonical shape.
 *
 * Choice-type requirements (select / radio / multiple) were saved under two
 * different keys and types — the frontend wizard wrote `options` (comma string),
 * the admin metabox wrote `choices` (comma string) — while the buyer form reads
 * `options` as a value=>label ARRAY and validation reads `choices`. That mismatch
 * left dropdowns empty and choice validation broken (BC 10134408650).
 *
 * This makes every consumer agree: it sets BOTH
 *   - `choices` : canonical comma STRING  (admin field + RequirementsService validation)
 *   - `options` : value=>label ARRAY      (buyer requirements form)
 * derived from whichever key/type was stored. Non-choice fields are untouched.
 *
 * @since 1.3.0
 *
 * @param array<string,mixed> $req A single requirement definition.
 * @return array<string,mixed>
 */
function wpss_normalize_requirement_choices( array $req ): array {
	$raw = $req['options'] ?? $req['choices'] ?? '';

	if ( is_array( $raw ) ) {
		// Already an array — could be a plain list or a value=>label map.
		$list = array();
		foreach ( $raw as $key => $value ) {
			$list[] = is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : trim( (string) $key );
		}
	} else {
		$list = array_map( 'trim', explode( ',', (string) $raw ) );
	}

	$list = array_values( array_unique( array_filter( $list, static fn( $v ) => '' !== $v ) ) );

	if ( empty( $list ) ) {
		return $req; // Not a choice field (or no choices) — leave as-is.
	}

	$req['choices'] = implode( ', ', $list );
	$req['options'] = array_combine( $list, $list );

	return $req;
}

/**
 * Get submitted order requirements.
 *
 * @param int $order_id Order ID.
 * @return array
 */
function wpss_get_order_requirements( int $order_id ): array {
	global $wpdb;

	// Primed by wpss_prime_order_requirements() for a list; consumed once so a
	// write later in the same request is never answered from here.
	$primed = wpss_prime_order_requirements();

	if ( array_key_exists( $order_id, $primed ) ) {
		$value = $primed[ $order_id ];
		wpss_prime_order_requirements( array(), $order_id );
		return $value;
	}

	// The table is created by the installer, and the installed schema version
	// is an autoloaded option: reading it costs nothing, where the SHOW TABLES
	// this used to run cost one query per order on every list.
	if ( '0.0.0' === (string) get_option( \WPSellServices\Database\SchemaManager::VERSION_OPTION, '0.0.0' ) ) {
		// Fall back to order meta.
		$requirements = get_metadata( 'wpss_order', $order_id, '_requirements', true );
		return is_array( $requirements ) ? $requirements : array();
	}

	$table = $wpdb->prefix . 'wpss_order_requirements';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT field_data FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
			$order_id
		),
		ARRAY_A
	);

	if ( ! $row || empty( $row['field_data'] ) ) {
		return array();
	}

	$decoded = json_decode( $row['field_data'], true );

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Load the submitted requirements for a page of orders in one query.
 *
 * GET /orders ran wpss_get_order_requirements() per row - two queries each,
 * one of them a SHOW TABLES. Priming here turns that into one SELECT for the
 * page. Each primed value is handed out ONCE by wpss_get_order_requirements()
 * and then forgotten, so a submission written later in the same request reads
 * fresh from the table rather than from a stale prime.
 *
 * @since 1.7.1
 *
 * @param int[]    $order_ids Orders about to be shaped. Empty to only read the store.
 * @param int|null $forget    Internal: drop one primed id after it has been read.
 * @return array<int,array<string,mixed>> The current primed map.
 */
function wpss_prime_order_requirements( array $order_ids = array(), ?int $forget = null ): array {
	static $primed = array();

	if ( null !== $forget ) {
		unset( $primed[ $forget ] );
		return $primed;
	}

	$order_ids = array_values( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ) );

	if ( empty( $order_ids ) || '0.0.0' === (string) get_option( \WPSellServices\Database\SchemaManager::VERSION_OPTION, '0.0.0' ) ) {
		return $primed;
	}

	global $wpdb;

	$table        = $wpdb->prefix . 'wpss_order_requirements';
	$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

	// Ascending id so the newest row for an order overwrites the older ones -
	// the same "ORDER BY id DESC LIMIT 1" the single read uses.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT order_id, field_data FROM {$table} WHERE order_id IN ({$placeholders}) ORDER BY id ASC", $order_ids ), ARRAY_A );

	foreach ( $order_ids as $order_id ) {
		$primed[ $order_id ] = array();
	}

	foreach ( (array) $rows as $row ) {
		$decoded = json_decode( (string) $row['field_data'], true );

		$primed[ (int) $row['order_id'] ] = is_array( $decoded ) ? $decoded : array();
	}

	return $primed;
}

/**
 * Get order confirmation URL (thank you page).
 *
 * Custom confirmation pages keep a single `?order_id=` arg (one-shot thank-you
 * pages are not worth nested rewrites). When unset, the pretty dashboard order
 * URL is the confirmation surface.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function wpss_get_order_confirmation_url( int $order_id ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	$confirmation_page = (int) get_option( 'wpss_order_confirmation_page' );

	if ( $confirmation_page ) {
		$permalink = get_permalink( $confirmation_page );
		if ( $permalink ) {
			return add_query_arg( 'order_id', $order_id, $permalink );
		}
	}

	// Fall back to the pretty dashboard order view.
	$url = wpss_get_order_url( $order_id );
	if ( $url ) {
		return $url;
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order_id . '/confirmation/' );
}

/**
 * Check if late requirements submission is allowed.
 *
 * @since 1.0.0
 *
 * @return bool Whether late requirements submission is enabled.
 */
function wpss_allow_late_requirements_submission(): bool {
	$allow_late = (bool) wpss_get_option( 'orders', 'allow_late_requirements' );

	/**
	 * Filter whether late requirements submission is allowed.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $allow_late Whether late submission is allowed.
	 */
	return (bool) apply_filters( 'wpss_allow_late_requirements_submission', $allow_late );
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
 * Platform slugs that mark an order as a sub-order of another order.
 *
 * Sub-orders (tips, extras, revisions) hang off a parent order, so they must
 * never surface as standalone rows in a buyer's or vendor's order list.
 *
 * @since 1.4.0
 *
 * @return string[] Platform slugs.
 */
function wpss_get_sub_order_platforms(): array {
	return array_keys( \WPSellServices\Models\ServiceOrder::get_sub_order_types() );
}

/**
 * Freeze the package an order was bought on.
 *
 * Package data lives in the service's `_wpss_packages` post meta, which the
 * vendor can edit at any time. Without a copy taken at purchase, a rename or a
 * price change silently rewrites what every past order says it was — the buyer
 * opens an old order and sees a package they never bought.
 *
 * @since 1.4.0
 *
 * @param int      $service_id Service post ID.
 * @param int|null $package_id Package INDEX into the service's packages meta.
 * @return array<string, mixed>|null Frozen package data, or null when the order has no package.
 */
function wpss_build_package_snapshot( int $service_id, ?int $package_id ): ?array {
	if ( null === $package_id || $service_id <= 0 ) {
		return null;
	}

	$packages = get_post_meta( $service_id, '_wpss_packages', true );

	if ( is_array( $packages ) && isset( $packages[ $package_id ] ) && is_array( $packages[ $package_id ] ) ) {
		return $packages[ $package_id ];
	}

	return wpss_build_package_snapshot_from_legacy_id( $service_id, $package_id );
}

/**
 * Recover a package snapshot for an order whose package_id is a legacy row id.
 *
 * `package_id` has meant two different things over the life of this plugin: a
 * PRIMARY KEY in `wpss_service_packages`, and a POSITIONAL index into the
 * service's `_wpss_packages` meta. The index won, and the table lookup was
 * removed - but orders written under the old meaning still carry row ids, and
 * on those the index lookup simply misses. They render as a generic "Package"
 * with no price tier, and until now nothing could tell them apart from an order
 * whose tier was genuinely deleted.
 *
 * Real example from a live database: order #26 on service 251 carries
 * package_id 17 while that service has three packages (indices 0-2). Row 17 in
 * wpss_service_packages is "Standard", service_id 251 - the order's own
 * service. Ten orders on that site were in this state.
 *
 * THE SERVICE-ID MATCH IS THE SAFETY CHECK, not a detail. A bare id lookup
 * would happily hand back another vendor's package and freeze the wrong name
 * and price onto someone's order. If the row belongs to a different service the
 * id is meaningless here and nothing is recovered.
 *
 * Recovery only. New orders always store an index and snapshot themselves at
 * payment, so nothing written from 1.6.0 onward needs this path.
 *
 * @since 1.6.0
 *
 * @param int $service_id Service post ID the order belongs to.
 * @param int $package_id The order's package_id, read as a legacy row id.
 * @return array<string, mixed>|null Snapshot, or null when it cannot be recovered.
 */
function wpss_build_package_snapshot_from_legacy_id( int $service_id, int $package_id ): ?array {
	global $wpdb;

	if ( $package_id <= 0 || $service_id <= 0 ) {
		return null;
	}

	$table = $wpdb->prefix . 'wpss_service_packages';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT name, description, price, delivery_days, revisions, features FROM {$table} WHERE id = %d AND service_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$package_id,
			$service_id
		),
		ARRAY_A
	);

	if ( ! $row ) {
		return null;
	}

	$features = json_decode( (string) ( $row['features'] ?? '' ), true );

	return array(
		'name'          => (string) ( $row['name'] ?? '' ),
		'description'   => (string) ( $row['description'] ?? '' ),
		'price'         => (float) ( $row['price'] ?? 0 ),
		'delivery_days' => (int) ( $row['delivery_days'] ?? 0 ),
		'revisions'     => (int) ( $row['revisions'] ?? 0 ),
		'features'      => is_array( $features ) ? $features : array(),
	);
}

/**
 * Run the shared post-creation steps for a service order.
 *
 * Every rail creates its order row itself — standalone, WooCommerce, EDD,
 * recurring renewals, admin manual orders — and they had each grown their own
 * idea of what happens next. Only standalone froze the package, and only
 * standalone and the manual-order screen fired `wpss_order_created`, so
 * anything listening to that hook silently never ran for a WooCommerce or EDD
 * purchase.
 *
 * A buyer's order should behave the same whoever sold it and however they
 * paid, so the steps that must happen for every order live here and each rail
 * calls this once after its insert.
 *
 * Safe to call more than once: the snapshot is only written when missing.
 *
 * @since 1.4.0
 *
 * @param int                  $order_id   Newly created WPSS order ID.
 * @param array<string, mixed> $order_data Raw creation data, passed to the hook.
 * @return void
 */
function wpss_after_order_created( int $order_id, array $order_data = array() ): void {
	if ( $order_id <= 0 ) {
		return;
	}

	wpss_capture_order_package_snapshot( $order_id );

	/**
	 * Fires after a service order is created, on every e-commerce rail.
	 *
	 * @since 1.1.0
	 *
	 * @param int   $order_id   The new order ID.
	 * @param array $order_data The order creation data.
	 */
	do_action( 'wpss_order_created', $order_id, $order_data );
}

/**
 * Write the package snapshot onto an order that does not have one yet.
 *
 * Idempotent, and a no-op for order types that cannot carry a package (tips,
 * milestones, extensions) or for orders bought without one.
 *
 * @since 1.4.0
 *
 * @param int $order_id WPSS order ID.
 * @return bool Whether a snapshot was written.
 */
function wpss_capture_order_package_snapshot( int $order_id ): bool {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_orders';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT service_id, package_id, platform, meta FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order_id
		)
	);

	if ( ! $row || null === $row->package_id ) {
		return false;
	}

	if ( in_array( (string) $row->platform, wpss_get_sub_order_platforms(), true ) ) {
		return false;
	}

	$meta = json_decode( (string) $row->meta, true );
	$meta = is_array( $meta ) ? $meta : array();

	if ( ! empty( $meta['package_snapshot'] ) ) {
		return false;
	}

	$snapshot = wpss_build_package_snapshot( (int) $row->service_id, (int) $row->package_id );

	if ( null === $snapshot ) {
		return false;
	}

	$meta['package_snapshot'] = $snapshot;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return false !== $wpdb->update(
		$table,
		array( 'meta' => wp_json_encode( $meta ) ),
		array( 'id' => $order_id ),
		array( '%s' ),
		array( '%d' )
	);
}

/**
 * Get the payment-rail receipt reference for an order, if any.
 *
 * A WPSS order is the order, and its lifecycle is the same whichever rail took
 * the money — standalone Stripe/PayPal, WooCommerce, EDD, Razorpay. Those rails
 * keep their own order flow and issue their own receipt number, so one purchase
 * ends up with two identities and support lookups fail when the buyer quotes the
 * receipt and the seller searches order numbers (or the reverse).
 *
 * This returns the rail's reference so it can sit beside the WPSS order number
 * as a secondary identifier. Rail-neutral by design: each integration answers
 * the filter rather than adding its own block to the order template.
 *
 * @since 1.4.0
 *
 * @param object $order WPSS order.
 * @return array{label: string, number: string, url: string}|null Reference, or null when the rail has none.
 */
function wpss_get_order_payment_reference( object $order ): ?array {
	/**
	 * Filter the payment-rail receipt reference shown on an order.
	 *
	 * Return an array with `label` (e.g. "WooCommerce Order"), `number` (the
	 * receipt number as the buyer sees it) and optionally `url` (a link the
	 * current user is allowed to open). Return null for rails with no separate
	 * receipt — standalone gateways record the transaction on the order itself.
	 *
	 * @since 1.4.0
	 *
	 * @param array|null $reference Reference data, or null.
	 * @param object     $order     WPSS order.
	 */
	$reference = apply_filters( 'wpss_order_payment_reference', null, $order );

	if ( ! is_array( $reference ) || empty( $reference['number'] ) ) {
		return null;
	}

	return array(
		'label'  => (string) ( $reference['label'] ?? __( 'Payment Reference', 'wp-sell-services' ) ),
		'number' => (string) $reference['number'],
		'url'    => (string) ( $reference['url'] ?? '' ),
	);
}

/**
 * Resolve what an order is FOR, in one place.
 *
 * Not every order points at a service post. Buyer-request orders are sold off a
 * proposal, and tips / extensions / milestones are sub-orders of a parent — all
 * of them carry `service_id = 0` by design. Five surfaces rendered that line
 * with five different fallbacks, and the three that only knew how to say
 * "Deleted" told the site owner a catalog service had been removed when none
 * ever existed (Basecamp 10208199238).
 *
 * Resolution order, most specific first:
 *
 * 1. Sub-order (tip / extension / milestone) — describe the parent relationship.
 * 2. Buyer-request order — the request post, else the frozen request title from
 *    `meta.proposal_snapshot.request_title`, else a generic label.
 * 3. `service_id <= 0` on a catalog order — a real data-integrity problem, said
 *    plainly rather than dressed up as a deletion.
 * 4. Service post gone — "Deleted service #N", with the ID so it can be traced.
 * 5. The service post.
 *
 * @since 1.6.0
 *
 * @param object $order   Order model or raw `wpss_orders` row.
 * @param string $context 'admin' for edit links, 'public' for permalinks.
 * @return array{label: string, url: string, service_id: int, is_service: bool} Resolved subject.
 */
function wpss_get_order_subject( object $order, string $context = 'public' ): array {
	$platform   = (string) ( $order->platform ?? 'standalone' );
	$parent_id  = (int) ( $order->platform_order_id ?? 0 );
	$service_id = (int) ( $order->service_id ?? 0 );
	$is_admin   = 'admin' === $context;

	$subject = array(
		'label'      => '',
		'url'        => '',
		'service_id' => 0,
		'is_service' => false,
	);

	// 1. Sub-orders describe their parent, never a service post they never had.
	$sub_order_labels = array(
		/* translators: %s: parent order number. */
		'tip'       => __( 'Tip on order #%s', 'wp-sell-services' ),
		/* translators: %s: parent order number. */
		'extension' => __( 'Extension on order #%s', 'wp-sell-services' ),
		/* translators: %s: parent order number. */
		'milestone' => __( 'Milestone of order #%s', 'wp-sell-services' ),
	);

	if ( isset( $sub_order_labels[ $platform ] ) ) {
		if ( $parent_id > 0 ) {
			$subject['label'] = sprintf( $sub_order_labels[ $platform ], $parent_id );
			$subject['url']   = $is_admin
				? admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $parent_id )
				: wpss_get_order_url( $parent_id );

			return $subject;
		}

		// Parent reference missing — surface that instead of hiding it behind
		// a generic "Deleted" label.
		$subject['label'] = sprintf(
			/* translators: %s: sub-order platform (Tip/Extension/Milestone). */
			__( '%s sub-order (parent missing)', 'wp-sell-services' ),
			ucfirst( $platform )
		);

		return $subject;
	}

	// 2. Buyer-request orders carry the request post ID in platform_order_id.
	if ( 'request' === $platform ) {
		$request_post = $parent_id > 0 ? get_post( $parent_id ) : null;

		if ( $request_post && 'wpss_request' === $request_post->post_type ) {
			$subject['label'] = sprintf(
				/* translators: %s: buyer request title. */
				__( 'Request: %s', 'wp-sell-services' ),
				$request_post->post_title
			);
			$subject['url'] = (string) ( $is_admin
				? get_edit_post_link( $request_post->ID, 'raw' )
				: get_permalink( $request_post->ID ) );

			return $subject;
		}

		// The request post can be gone (or never resolvable on a rail order),
		// but the title the buyer actually hired against was frozen onto the
		// order at conversion. Prefer it over a generic label.
		$meta = $order->meta ?? array();

		if ( is_string( $meta ) && '' !== $meta ) {
			$meta = json_decode( $meta, true );
		}

		$frozen_title = is_array( $meta ) && ! empty( $meta['proposal_snapshot']['request_title'] )
			? (string) $meta['proposal_snapshot']['request_title']
			: '';

		$subject['label'] = '' !== $frozen_title
			? sprintf(
				/* translators: %s: buyer request title. */
				__( 'Request: %s', 'wp-sell-services' ),
				$frozen_title
			)
			: __( 'Buyer request', 'wp-sell-services' );

		return $subject;
	}

	// 3. Catalog order with no service at all — a data-integrity problem.
	if ( $service_id <= 0 ) {
		$subject['label'] = __( 'No service linked', 'wp-sell-services' );

		return $subject;
	}

	$service_post = get_post( $service_id );

	// 4. The service post really is gone.
	if ( ! $service_post || 'wpss_service' !== $service_post->post_type ) {
		$subject['label'] = sprintf(
			/* translators: %d: removed service post ID. */
			__( 'Deleted service #%d', 'wp-sell-services' ),
			$service_id
		);

		return $subject;
	}

	// 5. A real service.
	$subject['label']      = $service_post->post_title;
	$subject['url']        = (string) ( $is_admin
		? get_edit_post_link( $service_post->ID, 'raw' )
		: get_permalink( $service_post->ID ) );
	$subject['service_id'] = $service_id;
	$subject['is_service'] = true;

	return $subject;
}

/**
 * Get total order count for a user (as customer).
 *
 * @since 1.2.0
 *
 * @param int $user_id User ID.
 * @return int Order count.
 */
function wpss_get_user_order_count( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d",
			$user_id
		)
	);
}

/**
 * Get active order count for a user (as customer).
 *
 * Active orders are those not in completed, cancelled, or refunded status.
 *
 * @since 1.2.0
 *
 * @param int $user_id User ID.
 * @return int Active order count.
 */
function wpss_get_user_active_order_count( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND status NOT IN ('completed', 'cancelled', 'refunded')",
			$user_id
		)
	);
}

/**
 * Get orders for a user (as customer).
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, status).
 * @return array Array of order objects.
 */
function wpss_get_user_orders( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE customer_id = %d";
	$params = array( $user_id );

	if ( ! empty( $args['status'] ) ) {
		$sql     .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	// Return hydrated models, never raw rows. $wpdb hands back every column as a
	// string, and the callers are strict_types files that pass these values into
	// typed helpers - `wpss_format_price( float $price, ... )` threw
	// "must be of type float, string given" and white-screened [wpss_account]
	// for any logged-in user with at least one order.
	return array_map( array( \WPSellServices\Models\ServiceOrder::class, 'from_db' ), $rows ?: array() );
}

/**
 * Get orders for a vendor.
 *
 * @since 1.2.0
 *
 * @param int   $vendor_id Vendor user ID.
 * @param array $args      Query arguments (limit, offset, status).
 * @return array Array of order objects.
 */
function wpss_get_vendor_orders( int $vendor_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE vendor_id = %d";
	$params = array( $vendor_id );

	if ( ! empty( $args['status'] ) ) {
		$sql     .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	// Return hydrated models, never raw rows. $wpdb hands back every column as a
	// string, and the callers are strict_types files that pass these values into
	// typed helpers - `wpss_format_price( float $price, ... )` threw
	// "must be of type float, string given" and white-screened [wpss_account]
	// for any logged-in user with at least one order.
	return array_map( array( \WPSellServices\Models\ServiceOrder::class, 'from_db' ), $rows ?: array() );
}

/**
 * Count a buyer's orders.
 *
 * A dedicated COUNT(*) so a caller never fetches rows just to size a list.
 * Pairs with wpss_get_user_orders(), which already takes limit + offset but had
 * no way to learn the total - so nothing built on it could paginate, and
 * [wpss_my_orders] simply showed the first 20 with no route to the rest.
 *
 * @since 1.5.1
 *
 * @param int    $user_id Customer user ID.
 * @param string $status  Optional. Restrict to one order status.
 * @return int Number of matching orders.
 */
function wpss_count_user_orders( int $user_id, string $status = '' ): int {
	return wpss_count_orders_for( 'customer_id', $user_id, $status );
}

/**
 * Count a vendor's orders.
 *
 * @since 1.5.1
 *
 * @param int    $vendor_id Vendor user ID.
 * @param string $status    Optional. Restrict to one order status.
 * @return int Number of matching orders.
 */
function wpss_count_vendor_orders( int $vendor_id, string $status = '' ): int {
	return wpss_count_orders_for( 'vendor_id', $vendor_id, $status );
}

/**
 * Shared COUNT(*) behind the two order-count helpers.
 *
 * A column name cannot be bound with a placeholder, so it is chosen from a
 * fixed allow-list instead of being interpolated from the caller. Anything else
 * returns 0 rather than reaching the query.
 *
 * @since 1.5.1
 *
 * @param string $column  Either 'customer_id' or 'vendor_id'.
 * @param int    $user_id User ID to match.
 * @param string $status  Optional. Restrict to one order status.
 * @return int Number of matching orders.
 */
function wpss_count_orders_for( string $column, int $user_id, string $status = '' ): int {
	global $wpdb;

	if ( ! in_array( $column, array( 'customer_id', 'vendor_id' ), true ) ) {
		return 0;
	}

	$table = $wpdb->prefix . 'wpss_orders';

	if ( '' !== $status ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed above; both values are bound.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d AND status = %s", $user_id, $status );
	} else {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed above; the value is bound.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d", $user_id );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is the prepared statement built above.
	return (int) $wpdb->get_var( $sql );
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
