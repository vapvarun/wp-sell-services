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
 * Why an order that is not pending_payment cannot be paid, in words the buyer can act on.
 *
 * Every pay door answered "This order has already been paid." for any status but
 * pending_payment, so a buyer who was charged and then refunded on a cancelled order read it as a
 * double charge (Basecamp 10416548799). Cancelled and refunded orders now say what happened.
 *
 * @since 1.9.0
 *
 * @param \WPSellServices\Models\ServiceOrder $order The order.
 * @return string Message.
 */
function wpss_get_order_not_payable_message( $order ): string {
	switch ( (string) ( $order->status ?? '' ) ) {
		case 'cancelled':
			return __( 'This order was cancelled and can no longer be paid.', 'wp-sell-services' );
		case 'refunded':
			return __( 'This order was refunded and can no longer be paid.', 'wp-sell-services' );
		default:
			return __( 'This order has already been paid.', 'wp-sell-services' );
	}
}

/**
 * The title an order is shown under.
 *
 * A service order is its service's title. A request order has no service post
 * (service_id 0), and get_post( 0 ) / get_the_title( 0 ) answer with whatever
 * page the visitor is on - the Dashboard - so the title comes from the request
 * the proposal answered instead. An empty string means the caller picks its own
 * generic label.
 *
 * @since 1.9.1
 *
 * @param object $order ServiceOrder or order row (JSON meta string).
 * @return string Order title, or '' when it has none.
 */
function wpss_get_order_title( object $order ): string {
	$service_id = (int) ( $order->service_id ?? 0 );

	if ( $service_id > 0 ) {
		return (string) get_the_title( $service_id );
	}

	$meta = $order->meta ?? array();
	$meta = is_string( $meta ) ? (array) json_decode( $meta, true ) : (array) $meta;

	if ( ! empty( $meta['proposal_snapshot']['request_title'] ) ) {
		return (string) $meta['proposal_snapshot']['request_title'];
	}

	$request_id = 'request' === ( $order->platform ?? '' ) ? (int) ( $order->platform_order_id ?? 0 ) : 0;

	return $request_id > 0 ? (string) get_the_title( $request_id ) : '';
}

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
 * The platform that collected the money for an order, or '' when WP Sell Services did.
 *
 * Standalone checkout and our own gateways collect the money here, so we refund it
 * here. An order paid through a shop platform (WooCommerce, EDD, FluentCart) is
 * refunded in that platform; we only record the refund it reports. Ownership is
 * decided by where the order was paid, never by its payment method: a Woo order
 * can carry "stripe" as its method and still belongs to Woo.
 *
 * @since 1.9.0
 *
 * @param object $order Order row or model.
 * @return string Human-readable platform name, or '' when we collected the money.
 */
function wpss_order_money_rail( object $order ): string {
	/**
	 * Filters the platform that owns an order's money.
	 *
	 * An ecommerce adapter returns its name for orders it collected (for
	 * example "WooCommerce"); everything else is ours to refund.
	 *
	 * @since 1.9.0
	 *
	 * @param string $rail  Platform name, '' for orders we collected.
	 * @param object $order Order row or model.
	 */
	return (string) apply_filters( 'wpss_order_money_rail', '', $order );
}

/**
 * Whether a user may refund an order from the dashboard.
 *
 * Admin only by default. The filter is the extension point for letting vendors
 * (or a support role) refund; it must also size the amount, since the verb only
 * knows full refunds today.
 *
 * @since 1.9.0
 *
 * @param string $actor   Role from wpss_order_actor_role().
 * @param object $order   Order being refunded.
 * @param int    $user_id Acting user ID.
 * @return bool
 */
function wpss_actor_can_refund_order( string $actor, object $order, int $user_id ): bool {
	/**
	 * Filters whether a user may refund an order.
	 *
	 * @since 1.9.0
	 *
	 * @param bool   $allowed Default: true for the admin actor only.
	 * @param object $order   Order being refunded.
	 * @param int    $user_id Acting user ID.
	 * @param string $actor   'admin', 'vendor', 'buyer' or ''.
	 */
	return (bool) apply_filters( 'wpss_can_refund_order', 'admin' === $actor, $order, $user_id, $actor );
}

/**
 * Whether an order is past its delivery deadline while the seller owes work.
 *
 * The one definition of "late": the late-order sweep
 * (OrderWorkflowManager::check_late_orders()) moves in_progress and
 * revision_requested orders past their deadline to `late`, so an order is late
 * when it is `late`, or still in one of those two before the sweep reaches it.
 * Waiting on the buyer (payment, requirements, approval) is not late.
 *
 * @since 1.8.0
 *
 * @param object $order Order model or list row (status, delivery_deadline).
 * @return bool
 */
function wpss_is_order_late( object $order ): bool {
	if ( 'late' === $order->status ) {
		return true;
	}

	if ( ! in_array( $order->status, array( 'in_progress', 'revision_requested' ), true ) || empty( $order->delivery_deadline ) ) {
		return false;
	}

	$deadline = $order->delivery_deadline instanceof \DateTimeInterface
		? $order->delivery_deadline->format( 'Y-m-d H:i:s' )
		: (string) $order->delivery_deadline;

	return $deadline < current_time( 'mysql', true );
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
 * @param int         $order_id Order ID.
 * @param string      $section  Dashboard section (e.g. 'sales' for vendor orders).
 * @param object|null $order    The order row when the caller already holds it: a list of 20 rows then makes no 20 lookups.
 * @return string
 */
function wpss_get_order_url( int $order_id, string $section = '', ?object $order = null ): string {
	$order = $order ?? wpss_get_order( $order_id );

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
	/*
	 * Superseded by wpss_get_post_checkout_url(), which every redirect site now
	 * uses because it takes the fallback destination as an argument - a paid
	 * order with outstanding requirements belongs on the requirements form, not
	 * the order view, and this function cannot know that. Kept because it is a
	 * public function a site may call; it has no callers inside the plugin.
	 */
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

	/*
	 * Fall back to the pretty dashboard order view.
	 *
	 * There used to be a final fallback here that built
	 * /{order-slug}/{id}/confirmation/. That address was served by
	 * templates/order/order-confirmation.php, which 1.7.2 removed because
	 * nothing rendered it - so the last line of this function became a
	 * guaranteed 404 for anyone who reached it. Returning an empty string lets
	 * a caller notice there is no destination instead of sending a buyer to a
	 * dead page.
	 */
	return wpss_get_order_url( $order_id );
}

/**
 * Where a buyer goes after a successful checkout.
 *
 * Settings > Pages > Order Confirmation Page existed, saved correctly, and was
 * read by nothing: wpss_get_order_confirmation_url() had ZERO callers outside
 * its own definition while eleven redirect sites each built their own URL, so
 * the owner could pick a thank-you page and buyers never saw it (Basecamp
 * 10304592376).
 *
 * The fallback is passed in rather than assumed, because the right destination
 * without a confirmation page differs by site: a paid order with requirements
 * belongs on the requirements form, not the order view. Passing it keeps every
 * existing redirect byte-identical when no confirmation page is configured.
 *
 * @since 1.7.1
 *
 * @param int    $order_id     Order ID.
 * @param string $fallback_url Where to go when no confirmation page is set.
 * @param string $context      Which rail is redirecting, for the filter.
 * @return string
 */
function wpss_get_post_checkout_url( int $order_id, string $fallback_url = '', string $context = '' ): string {
	$confirmation_page = (int) get_option( 'wpss_order_confirmation_page' );
	$url               = '';

	if ( $confirmation_page ) {
		$permalink = get_permalink( $confirmation_page );

		if ( $permalink ) {
			$url = add_query_arg( 'order_id', $order_id, $permalink );
		}
	}

	if ( '' === $url ) {
		$url = '' !== $fallback_url ? $fallback_url : wpss_get_order_url( $order_id );
	}

	/**
	 * Filter the post-checkout redirect.
	 *
	 * @since 1.7.1
	 *
	 * @param string $url      Resolved destination.
	 * @param int    $order_id Order ID.
	 * @param string $context  Rail that is redirecting: offline, stripe, test,
	 *                         intent, cart, edd.
	 */
	return (string) apply_filters( 'wpss_post_checkout_url', $url, $order_id, $context );
}

/**
 * Whether buyers can tip on this site, and whether tip records are shown.
 *
 * Tipping is a Pro feature. Off by default, so the free plugin alone never
 * offers a tip or shows one; Pro answers this filter from its own on/off
 * setting, which defaults to on.
 *
 * This gates what people SEE and START - the tip button, the REST route that
 * creates a tip, tip receipts, the tip email, the tip commission setting. It
 * deliberately does NOT gate the money path. Crediting a paid tip, refunding
 * one and the abandoned-tip cleanup keep running, because they are the same
 * sub-order code milestones and paid extensions use, and because a buyer who
 * has already paid for a tip must still reach the vendor. Hiding a record is
 * reversible; leaving a buyer's money uncredited is not.
 *
 * @since 1.7.1
 *
 * @return bool
 */
function wpss_tipping_enabled(): bool {
	/**
	 * Filter whether tipping is available.
	 *
	 * @since 1.7.1
	 *
	 * @param bool $enabled False unless an extension turns it on.
	 */
	return (bool) apply_filters( 'wpss_tipping_enabled', false );
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
 * SQL predicate that keeps only real orders: no tips, extensions or milestones.
 *
 * Every "how many orders" count reads this one list, so the seller card, the
 * REST stats, the seller level and the profile row cannot disagree with
 * OrderRepository. The slugs come from a fixed map, not from request input.
 *
 * @since 1.9.1
 * @param string $column Platform column, qualified when the query joins tables.
 * @return string Predicate to AND into a WHERE clause.
 */
function wpss_order_rows_sql( string $column = 'platform' ): string {
	$slugs = array_map(
		static fn( string $platform ): string => "'" . esc_sql( sanitize_key( $platform ) ) . "'",
		wpss_get_sub_order_platforms()
	);

	return $slugs ? "COALESCE({$column}, '') NOT IN (" . implode( ', ', $slugs ) . ')' : '1=1';
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
 * The delivery terms a new order starts with, for any rail that creates one.
 *
 * Revisions come from the package the buyer bought (-1 is unlimited), falling
 * back to the service's own count; the deadline is the line's days from now
 * (see wpss_line_delivery_days()) and is also the original deadline. One place,
 * so an order from a store rail is due and revisable exactly like a standalone one.
 *
 * @since 1.9.1
 *
 * @param int                              $service_id Service post ID.
 * @param int                              $package_id Package the buyer bought (stable id or index).
 * @param array<int, array<string, mixed>> $addons Priced add-on rows, when the rail has them.
 * @return array{revisions_included: int, revisions_used: int, delivery_deadline: string, original_deadline: string}
 */
function wpss_order_delivery_terms( int $service_id, int $package_id, array $addons = array() ): array {
	$resolved = wpss_resolve_service_package( $service_id, $package_id );
	$package  = $resolved ? $resolved['package'] : array();
	$deadline = gmdate( 'Y-m-d H:i:s', strtotime( '+' . wpss_line_delivery_days( $package, $addons ) . ' days' ) );

	return array(
		'revisions_included' => (int) ( $package['revisions'] ?? wpss_get_service_revisions( $service_id ) ),
		'revisions_used'     => 0,
		'delivery_deadline'  => $deadline,
		'original_deadline'  => $deadline,
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

unset( $wpss_aggregates_hook );

/**
 * Whether the buyer has submitted payment that is waiting on manual confirmation.
 *
 * `pending_payment` means two different things and every surface was reading
 * only the first: "the buyer has not paid yet" AND "the buyer chose an offline
 * method and we are waiting for the admin to confirm the transfer". Offline is
 * correct to sit in that status - nothing is broken in the payment path - but
 * rendering it as "not paid yet" means the buyer is shown the same Pay button
 * they just used, with no acknowledgement that anything happened. They conclude
 * it failed and pay again, which is the milestone "redirect loop" in Basecamp
 * 10305169436 and the same silence on ordinary orders and extensions.
 *
 * `payment_method` is the discriminator: it is NULL until the buyer submits and
 * carries the chosen method afterwards. Verified across a milestone phase, an
 * extension and a standalone order.
 *
 * @since 1.7.1
 *
 * @param object|int $order Order row or id.
 * @return bool True when payment was submitted and awaits confirmation.
 */
function wpss_order_awaits_payment_confirmation( $order ): bool {
	$order = is_object( $order ) ? $order : wpss_get_order( (int) $order );

	if ( ! is_object( $order ) ) {
		return false;
	}

	if ( 'pending_payment' !== ( $order->status ?? '' ) ) {
		return false;
	}

	// A paid order has left this status; a gateway that captured instantly
	// never lingers here. Anything still here WITH a method chosen is an
	// offline-style instruction the site owner has to confirm by hand.
	return '' !== (string) ( $order->payment_method ?? '' )
		&& 'paid' !== ( $order->payment_status ?? '' );
}

/**
 * What to tell a buyer whose offline order is waiting on payment.
 *
 * Right after an offline order is placed nothing has been paid - the buyer
 * still has to send it - yet every view said "Payment submitted. We will
 * confirm your transfer shortly." above instructions that begin "after you
 * place your order" (Basecamp 10336467932). "Received" is only true once the
 * buyer has uploaded proof of payment for review. The one wording for the
 * order, extension and milestone views and the Offline gateway's panel.
 *
 * @since 1.8.0
 *
 * @param object $order Order awaiting confirmation (wpss_order_awaits_payment_confirmation()).
 * @return string
 */
function wpss_offline_payment_notice( object $order ): string {
	$proof = \WPSellServices\Services\PaymentReceiptService::is_enabled()
		&& null !== ( new \WPSellServices\Database\Repositories\PaymentReceiptRepository() )->get_pending_for_order( (int) $order->id );

	return $proof
		? __( 'Payment proof received. We will confirm it shortly and let you know.', 'wp-sell-services' )
		: __( 'Order placed and waiting for your payment. Work starts once it is confirmed.', 'wp-sell-services' );
}

/**
 * What "Order again" would put in the buyer's cart for a finished order, or why it cannot.
 *
 * The one reader behind the order page's button and POST /orders/{id}/reorder, so the
 * two can never disagree. It rebuilds the choice the buyer made (package, add-ons,
 * quantity) against what the service offers TODAY and prices it through the pricer
 * checkout charges through, so what the button promises is what checkout shows. Three
 * things move since the first order and each is said, never hidden: a package that was
 * removed refuses (a different tier would be a silent substitution), an add-on that is
 * gone is dropped and named, and a changed price is reported with both figures.
 * A service that is deleted, paused or whose seller is away is refused by the pricer
 * with its own reason.
 *
 * Not offered for a custom quote or proposal order (there is no package to repeat), a
 * tip, milestone or extension (they belong to a parent order), or an order that is not
 * completed.
 *
 * @since 1.9.0
 *
 * @param \WPSellServices\Models\ServiceOrder $order     The finished order.
 * @param int                                 $viewer_id Who is asking; only the order's customer may repeat it.
 * @return array{service_id:int, package_id:int, quantity:int, line:array<string,mixed>, warnings:string[], price_changed:bool, previous_total:float, current_total:float}|\WP_Error
 */
function wpss_get_reorder_plan( \WPSellServices\Models\ServiceOrder $order, int $viewer_id ) {
	if ( $viewer_id < 1 || (int) $order->customer_id !== $viewer_id ) {
		return new WP_Error( 'wpss_reorder_not_owner', __( 'Only the buyer can order this again.', 'wp-sell-services' ), array( 'status' => 403 ) );
	}

	$sub_orders = array(
		\WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_TIP,
		\WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_MILESTONE,
		\WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_EXTENSION,
	);
	$snapshot   = $order->get_package_snapshot();

	if ( \WPSellServices\Models\ServiceOrder::STATUS_COMPLETED !== $order->status
		|| in_array( $order->platform, $sub_orders, true )
		|| ! empty( $order->meta['proposal_snapshot'] )
		|| null === $order->package_id
		|| ! $snapshot ) {
		return new WP_Error( 'wpss_reorder_not_available', __( 'This order cannot be ordered again.', 'wp-sell-services' ), array( 'status' => 409 ) );
	}

	$service_id = (int) $order->service_id;

	// The package by its stable id; a legacy snapshot without one by its position, but only if
	// it is still the same package (same name) - a different tier is not "the same order".
	$resolved = isset( $snapshot['id'] ) ? wpss_resolve_service_package( $service_id, (int) $snapshot['id'] ) : null;

	if ( ! $resolved && ! isset( $snapshot['id'] ) ) {
		$live = wpss_get_service_packages( $service_id )[ (int) $order->package_id ] ?? null;

		if ( is_array( $live ) && (string) ( $live['name'] ?? '' ) === (string) ( $snapshot['name'] ?? '' ) ) {
			$resolved = array(
				'package' => $live,
				'index'   => (int) $order->package_id,
			);
		}
	}

	if ( ! $resolved ) {
		return new WP_Error( 'wpss_reorder_package_gone', __( 'The package you bought is no longer offered. Open the service to choose another.', 'wp-sell-services' ), array( 'status' => 409 ) );
	}

	// Quantity is not stored on the order; it is whole multiples of the package price.
	$unit     = (float) ( $snapshot['price'] ?? 0 );
	$ratio    = $unit > 0 ? (float) $order->subtotal / $unit : 1.0;
	$quantity = ( $ratio >= 1 && abs( $ratio - round( $ratio ) ) < 0.001 ) ? (int) round( $ratio ) : 1;

	// A store rail sells one delivery per line: asking for more priced a quantity its cart then
	// dropped to 1 (combo walk, 1.9.0). The standalone cart keeps the quantity.
	$bought_quantity = $quantity;
	$adapter         = wpss_get_active_adapter();
	if ( $adapter && 'standalone' !== $adapter->get_id() ) {
		$quantity = 1;
	}

	$selection = wpss_normalize_addon_selection( $order->addons );
	$line      = \WPSellServices\Checkout\CheckoutIntentService::price_service_line( $service_id, (int) $resolved['index'], $quantity, $selection );

	if ( is_wp_error( $line ) ) {
		return $line;
	}

	$warnings = array();
	$dropped  = array_diff( array_keys( $selection ), array_map( 'intval', array_column( $line['addons'], 'id' ) ) );

	if ( $dropped ) {
		$names = array();
		foreach ( (array) $order->addons as $row ) {
			if ( is_array( $row ) && in_array( (int) ( $row['id'] ?? -1 ), $dropped, true ) ) {
				$names[] = (string) ( $row['title'] ?? $row['name'] ?? '' );
			}
		}

		$warnings[] = sprintf(
			/* translators: %s: comma-separated add-on names */
			__( 'No longer available, so left out: %s.', 'wp-sell-services' ),
			implode( ', ', array_filter( $names ) ) ?: __( 'an add-on from your last order', 'wp-sell-services' )
		);
	}

	if ( $quantity < $bought_quantity ) {
		$warnings[] = sprintf(
			/* translators: 1: quantity on the last order, 2: quantity added now */
			__( 'You bought %1$d last time; this store sells %2$d per order, so %2$d is added.', 'wp-sell-services' ),
			$bought_quantity,
			$quantity
		);
	}

	// What the last order cost for the same quantity, so a lower quantity is not read as a price change.
	$previous = round( ( (float) $order->subtotal * $quantity / max( 1, $bought_quantity ) ) + (float) $order->addons_total, wpss_get_currency_decimals() );
	$current  = round( (float) $line['subtotal'] + (float) $line['addons_total'], wpss_get_currency_decimals() );
	$changed  = abs( $current - $previous ) > 0.005;

	if ( $changed ) {
		$warnings[] = sprintf(
			/* translators: 1: price on the last order, 2: price today */
			__( 'The price has changed since your last order: it was %1$s and is now %2$s.', 'wp-sell-services' ),
			wpss_format_price( $previous ),
			wpss_format_price( $current )
		);
	}

	return array(
		'service_id'     => $service_id,
		'package_id'     => (int) $resolved['index'],
		'quantity'       => $quantity,
		'line'           => $line,
		'warnings'       => $warnings,
		'price_changed'  => $changed,
		'previous_total' => $previous,
		'current_total'  => $current,
	);
}
