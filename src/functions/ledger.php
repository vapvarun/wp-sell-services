<?php
/**
 * Ledger, refunds, commission base and wallet balance.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ledger transaction types that DEBIT the vendor (money leaving the wallet).
 *
 * Amounts are always stored POSITIVE; the sign is applied on read. Every
 * consumer that sums, filters or renders the ledger must agree on which types
 * are debits, so this is the one list.
 *
 * It used to be hardcoded in five places across both plugins (the balance
 * helper, two EarningsService queries, two LedgerExporter branches). Adding a
 * type meant finding all five, and missing one produced a silently wrong
 * balance or a wrong statement CSV.
 *
 * @since 1.2.3
 *
 * @return string[] Debit transaction types.
 */
function wpss_get_ledger_debit_types(): array {
	/**
	 * Filter the ledger transaction types treated as debits.
	 *
	 * @since 1.2.3
	 *
	 * @param string[] $types Debit transaction types.
	 */
	$types = apply_filters(
		'wpss_ledger_debit_types',
		array(
			'withdrawal',
			'debit',
			'dispute_refund',
			// Payout rails that settle the vendor OUTSIDE this wallet register
			// their own debit types through the filter below — Pro's Stripe
			// Connect adds 'connect_transfer'. Free pays vendors manually, so
			// its own list stays rail-free.
		)
	);

	return array_values( array_unique( array_map( 'sanitize_key', (array) $types ) ) );
}

/**
 * Build a quoted, comma-separated SQL list of the ledger debit types.
 *
 * Values pass through sanitize_key() in wpss_get_ledger_debit_types(), so they
 * are already restricted to [a-z0-9_-]; they are quoted here for interpolation
 * into an IN () clause. Kept private to this file's SQL builders.
 *
 * @since 1.2.3
 *
 * @return string e.g. "'withdrawal','debit','dispute_refund','connect_transfer'"
 */
function wpss_get_ledger_debit_types_sql(): string {
	/*
	 * sanitize_key() each type before it reaches IN ().
	 *
	 * The list comes through the wpss_ledger_debit_types filter, so it is
	 * developer-controlled rather than request-controlled and this is not an
	 * injection - but a third party returning a value with a quote in it would
	 * break the balance query, and a balance query that errors is a money
	 * surface that silently shows nothing (Basecamp 10321653509).
	 */
	$types = array_filter( array_map( 'sanitize_key', wpss_get_ledger_debit_types() ) );

	return "'" . implode( "','", $types ) . "'";
}

/**
 * How much was refunded on an order, for DISPLAY.
 *
 * `wpss_orders.refunded_amount` carries a sentinel the money layer relies on:
 * NULL means "fully refunded", a number means "this much was refunded". That is
 * deliberate and load-bearing — OrderWorkflowManager::settle_refund() reads NULL
 * to mean "reverse everything" — but it is a terrible thing for a template to
 * interpret. Reading the column directly and testing `> 0` silently drops every
 * FULL refund, which is how the buyer's order view came to show "Refunded" with
 * no figure next to it while a partial refund showed one.
 *
 * So the sentinel is resolved in exactly one place, here, and every display and
 * invoice surface asks this instead of touching the column. Returns 0.0 when
 * nothing was refunded.
 *
 * @since 1.2.3
 *
 * @param object $order Order exposing refunded_amount, total, status, payment_status.
 * @return float Amount refunded to the buyer.
 */
function wpss_get_order_refunded_amount( object $order ): float {
	$recorded = $order->refunded_amount ?? null;

	if ( null !== $recorded ) {
		return (float) $recorded;
	}

	// NULL means one of two very different things: a full refund, or no refund
	// at all. Only the status can tell them apart.
	$is_refunded = 'refunded' === ( $order->status ?? '' )
		|| 'refunded' === ( $order->payment_status ?? '' );

	return $is_refunded ? (float) ( $order->total ?? 0 ) : 0.0;
}

/**
 * Where the buyer's money stands after a refund was decided.
 *
 * - 'pending':  no gateway could send it (offline, or paid outside one); the
 *               admin sends it by hand and "Mark refund sent" clears it.
 * - 'failed':   the gateway refused; the admin retries.
 * - 'refunded': it went back.
 * - '':         no refund on this order.
 *
 * One read for the cancellation email and the order page, so the buyer is told
 * the same thing in both (Basecamp 10336731713).
 *
 * @since 1.8.0
 *
 * @param object $order Order row or ServiceOrder.
 * @return array{state: string, amount: float}
 */
function wpss_get_order_refund_state( object $order ): array {
	$order_id = (int) ( $order->id ?? 0 );
	$pending  = (float) wpss_get_order_provider()->get_item_meta( $order_id, \WPSellServices\Services\OrderWorkflowManager::REFUND_PENDING_META );

	if ( $pending > 0 ) {
		return array(
			'state'  => 'pending',
			'amount' => $pending,
		);
	}

	$failed = \WPSellServices\Services\OrderWorkflowManager::get_failed_refund( $order_id );

	if ( $failed ) {
		return array(
			'state'  => 'failed',
			'amount' => (float) ( $failed['amount'] ?? 0 ),
		);
	}

	$refunded = wpss_get_order_refunded_amount( $order );

	return $refunded > 0 ? array(
		'state'  => 'refunded',
		'amount' => $refunded,
	) : array(
		'state'  => '',
		'amount' => 0.0,
	);
}

/**
 * THE single authority for "can this order be refunded".
 *
 * Replaces two hardcoded, contradictory status lists — the vendor/customer AJAX
 * path allowed pending_payment / pending_requirements / accepted; the admin
 * button allowed only completed / cancelled — neither of which covered
 * in_progress, delivered, revision, late, on_hold or disputed. Every refund
 * surface (AJAX action, admin metabox button, admin order-view button) now asks
 * this one function, so the answer can never diverge by screen again.
 *
 * Policy (owner, 2026-07-23): if the buyer PAID, the order is refundable at ANY
 * workflow stage — quality problems routinely surface AFTER delivery, so the
 * gate is payment capture, not workflow progress. An unpaid order is not
 * refundable because there is nothing to give back; a fully-refunded order is
 * not refundable because there is nothing left. A partial refund keeps
 * payment_status 'paid' (only the order status moves to partially_refunded), so
 * this still returns true for it — the remaining balance can be clawed back.
 *
 * The `wpss_order_is_refundable` filter lets a site owner TIGHTEN this (e.g.
 * block refunds once an order is completed); widening past "paid" is on them.
 *
 * @since 1.2.4
 *
 * @param object|int $order Order object (exposing status + payment_status) or ID.
 * @return bool True when the order may be refunded.
 */
function wpss_order_is_refundable( $order ): bool {
	if ( is_numeric( $order ) ) {
		$order = wpss_get_order( (int) $order );
	}

	if ( ! is_object( $order ) ) {
		return false;
	}

	// Money captured ('paid' survives a partial refund) and not yet fully
	// returned ('refunded' payment_status, or the terminal refunded status,
	// ends it).
	$refundable = 'paid' === ( $order->payment_status ?? '' )
		&& 'refunded' !== ( $order->status ?? '' );

	/**
	 * Filter whether an order may be refunded.
	 *
	 * @since 1.2.4
	 *
	 * @param bool   $refundable Default policy: any paid, not-fully-refunded order.
	 * @param object $order      The order being tested.
	 */
	return (bool) apply_filters( 'wpss_order_is_refundable', $refundable, $order );
}

/**
 * Orders whose refund still has to be sent by hand, with the amount owed.
 *
 * Written by OrderWorkflowManager when an offline / test gateway is asked to
 * refund (no money can move there), cleared by "Mark refund sent" on the
 * admin order view. Drives the admin notice and the order-view box.
 *
 * @since 1.7.1
 *
 * @return array<int, float> order_id => amount, oldest first.
 */
function wpss_get_pending_manual_refunds(): array {
	global $wpdb;

	// The meta table is created lazily by the first item-meta write
	// (StandaloneOrderProvider::maybe_create_meta_table); before that there
	// is nothing pending.
	if ( '1.0' !== get_option( 'wpss_order_meta_table_version' ) ) {
		return array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			// Only orders that still exist: a flag left on a deleted order showed
			// the owner a dead link to send money for nothing (Basecamp 10336652112).
			"SELECT m.order_id, m.meta_value FROM {$wpdb->prefix}wpss_order_meta m INNER JOIN {$wpdb->prefix}wpss_orders o ON o.id = m.order_id WHERE m.meta_key = %s ORDER BY m.order_id ASC",
			\WPSellServices\Services\OrderWorkflowManager::REFUND_PENDING_META
		)
	);

	$pending = array();

	foreach ( (array) $rows as $row ) {
		$amount = (float) maybe_unserialize( (string) $row->meta_value );

		if ( $amount > 0 ) {
			$pending[ (int) $row->order_id ] = $amount;
		}
	}

	return $pending;
}

/**
 * Refunds the owner has to look at: ones the gateway refused, rail refunds
 * waiting for a gig (`unassigned`), and fully refunded orders whose paid extensions or tips were never refunded.
 *
 * The second list is mostly history: before 1.8.0 a full refund stopped at the
 * parent (Basecamp 10336467671). It also catches a refund that started at the
 * payment rail, which never touches the separately charged children. Nothing
 * here is changed automatically - each one needs a person to decide.
 *
 * @since 1.8.0
 *
 * @param int $limit Maximum orders per list.
 * @return array{failed: array<int, array<string, mixed>>, uncascaded: int[], unassigned: array<int, array<string, mixed>>}
 */
function wpss_get_refund_review_items( int $limit = 20 ): array {
	global $wpdb;

	$items = array(
		'failed'     => array(),
		'uncascaded' => array(),
		'unassigned' => \WPSellServices\Services\PendingRefundService::all( $limit ),
	);

	if ( '1.0' === get_option( 'wpss_order_meta_table_version' ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, meta_value FROM {$wpdb->prefix}wpss_order_meta WHERE meta_key = %s AND meta_value <> '' ORDER BY order_id ASC LIMIT %d",
				\WPSellServices\Services\OrderWorkflowManager::REFUND_FAILED_META,
				$limit
			)
		);

		foreach ( (array) $rows as $row ) {
			$flag = maybe_unserialize( (string) $row->meta_value );

			if ( is_array( $flag ) && ! empty( $flag['amount'] ) ) {
				$items['failed'][ (int) $row->order_id ] = $flag;
			}
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- child side uses idx_platform (platform, platform_order_id).
	$items['uncascaded'] = array_map(
		'intval',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.id FROM {$wpdb->prefix}wpss_orders p
				INNER JOIN {$wpdb->prefix}wpss_orders c
					ON c.platform IN (%s, %s) AND c.platform_order_id = p.id
				WHERE p.status = %s AND c.payment_status = 'paid' AND c.status <> %s
				ORDER BY p.id ASC LIMIT %d",
				\WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_EXTENSION,
				\WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_TIP,
				\WPSellServices\Models\ServiceOrder::STATUS_REFUNDED,
				\WPSellServices\Models\ServiceOrder::STATUS_REFUNDED,
				$limit
			)
		)
	);

	return $items;
}

/**
 * Vendor's share of a refund.
 *
 * THE single proportional formula. A refund gives the buyer back some or all of
 * what they still had paid; the vendor gives back the same proportion of what
 * they still hold, and the platform gives back the same proportion of its fee.
 *
 * Refunds accumulate (1.7.1): each event is sized against what was LEFT before
 * it, because the order's vendor_earnings is reduced by every earlier partial.
 * Total 100, earnings 90: a 10 refund returns 9 (90 x 10/100); a later 15
 * returns 13.50 (81 x 15/90); refunding the remaining 75 returns 67.50 (67.5 x
 * 75/75). A full refund is simply the case where $refunded equals the
 * remainder, so one formula covers both and there is no separate "partial"
 * path to drift.
 *
 * @since 1.2.3
 * @since 1.7.1 Added $previously_refunded so a second partial is sized against the remainder.
 *
 * @param object $order               Order exposing total, currency and vendor_earnings (as they stand now).
 * @param float  $refunded            Amount going back to the buyer in THIS event.
 * @param float  $previously_refunded Amount already refunded before this event.
 * @return float Vendor's share, never negative, never more than they still hold.
 */
function wpss_get_refund_vendor_share( object $order, float $refunded, float $previously_refunded = 0.0 ): float {
	$total    = (float) ( $order->total ?? 0 );
	$earnings = (float) ( $order->vendor_earnings ?? 0 );
	$left     = $total - max( 0.0, $previously_refunded );

	if ( $left <= 0 || $earnings <= 0 || $refunded <= 0 ) {
		return 0.0;
	}

	// Clamp: refunding more than is left would otherwise claw back more than
	// the vendor still holds.
	$refunded = min( $refunded, $left );

	return round( $earnings * ( $refunded / $left ), wpss_get_currency_decimals( (string) ( $order->currency ?? '' ) ) );
}

/**
 * Get a vendor's balance derived from the wallet ledger.
 *
 * THE canonical balance. Sums the wallet ledger rather than trusting the last
 * row's `balance_after`, because that denormalised column is only correct while
 * every write happens in order through one path — it silently drifts after a
 * withdrawal, a reversal, or any out-of-order insert.
 *
 * Free code used to read the last row's `balance_after` in six places while Pro
 * derived the sum, so the two disagreed the moment a withdrawal landed and the
 * vendor's displayed balance, withdrawable amount and ledger diverged
 * permanently. `balance_after` is now a cache; this is the source of truth.
 *
 * @since 1.2.2
 *
 * @param int  $user_id Vendor user ID.
 * @param bool $lock    Optional. Lock the ledger rows (FOR UPDATE) — pass true
 *                      inside a transaction that is about to write a new row.
 * @return float Current balance.
 */
function wpss_get_ledger_balance( int $user_id, bool $lock = false ): float {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_wallet_transactions';

	// Only COMPLETED rows count toward a spendable balance — a pending or failed
	// transaction must never inflate it. (Pro's provider already filtered this;
	// the free helper did not, which would have been a second silent divergence.)
	$debit_types = wpss_get_ledger_debit_types_sql();

	// -ABS(), not -amount. The convention is that a debit row stores a
	// POSITIVE amount and the sign is applied here - but one legacy row was
	// written negative, and `-amount` turned that into `-(-50)` = +50, so a
	// withdrawal ADDED fifty dollars to the vendor's balance. It overstated
	// that vendor by 100.00. ABS() makes the balance correct whatever sign a
	// row carries, which matters because this is the only place the sign is
	// applied and the rows outlive any writer we fix.
	$sql = "SELECT COALESCE( SUM( CASE WHEN type IN ( {$debit_types} ) THEN -ABS( amount ) ELSE amount END ), 0 )
		FROM {$table}
		WHERE user_id = %d AND status = 'completed'";

	if ( $lock ) {
		$sql .= ' FOR UPDATE';
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (float) $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
}

/**
 * Everything a vendor has ever been credited, net of reversals.
 *
 * The "Total Earned" figure. Debit rows (withdrawal / debit / dispute_refund)
 * are excluded so this reads as money earned rather than a running balance;
 * reversal rows are negative credits and do count, so a refunded order is not
 * "earned". One formula: the Earnings page header, the vendor profile row and
 * the admin vendor list all read it from here.
 *
 * @since 1.7.1
 *
 * @param int $user_id Vendor user ID.
 * @return float
 */
function wpss_get_ledger_total_earned( int $user_id ): float {
	global $wpdb;

	$debit_types = wpss_get_ledger_debit_types_sql();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (float) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE( SUM( amount ), 0 ) FROM {$wpdb->prefix}wpss_wallet_transactions
			WHERE user_id = %d AND status = 'completed' AND type NOT IN ({$debit_types})",
			$user_id
		)
	);
}

/**
 * Write one wallet ledger row.
 *
 * THE insert path for the ledger. Every credit (order earning, tip, milestone,
 * extension), reversal and withdrawal debit goes through here so three things
 * cannot drift: the column list, the duplicate handling and the vendor
 * profile's cached totals.
 *
 * The ledger carries UNIQUE (reference_type, reference_id, type) since 1.7.1.
 * A retried cron, a replayed webhook or a double-clicked button that reaches
 * this insert a second time is refused by the database, not by a
 * read-then-insert race; that refusal is reported as success because the row
 * the caller wanted already exists. Any other failure is reported as failure.
 *
 * After a write the vendor profile's total_earnings / net_earnings are
 * recomputed from the ledger (two SUMs), so the profile row, the Earnings page
 * and the ledger say one number. Runs inside whatever transaction the caller
 * has open, so a rollback undoes the recompute too.
 *
 * @since 1.7.1
 *
 * @param array<string, mixed> $row Column => value. balance_after defaults to
 *                                  the ledger balance after this row, status
 *                                  to 'completed', currency to the store's.
 * @return bool True when the row exists after the call (inserted or already
 *              there), false on a real database error.
 */
function wpss_insert_ledger_row( array $row ): bool {
	global $wpdb;

	$user_id = (int) ( $row['user_id'] ?? 0 );
	$amount  = (float) ( $row['amount'] ?? 0 );

	if ( $user_id <= 0 ) {
		return false;
	}

	/*
	 * A money row must carry its reference, because the UNIQUE index cannot
	 * enforce it otherwise.
	 *
	 * uniq_reference (reference_type, reference_id, type) is the double-credit
	 * and replay defence, and the duplicate-entry branch below treats a
	 * collision as success precisely because of it. But MySQL permits unlimited
	 * duplicate rows when an indexed column is NULL, and both reference columns
	 * default to NULL - so a row written without a reference had no idempotency
	 * protection at all, silently, while looking exactly like a protected one
	 * (Basecamp 10321653478, finding 2).
	 *
	 * Every caller in both plugins passes both fields today; this refuses the
	 * one that forgets tomorrow rather than crediting twice.
	 */
	$reference_type = trim( (string) ( $row['reference_type'] ?? '' ) );
	$reference_id   = (int) ( $row['reference_id'] ?? 0 );

	if ( '' === $reference_type || $reference_id <= 0 ) {
		wpss_log(
			sprintf(
				'Refused a ledger row of type "%s" for user %d: a money row must carry reference_type and reference_id, or the uniq_reference index cannot dedupe it.',
				(string) ( $row['type'] ?? '' ),
				$user_id
			),
			'error'
		);

		return false;
	}

	$is_debit = in_array( (string) ( $row['type'] ?? '' ), wpss_get_ledger_debit_types(), true );

	$row += array(

		/*
		 * Locked read for the fallback. Every caller computes balance_after
		 * from a balance it already holds under FOR UPDATE and passes it in, so
		 * this default is only reached by a caller that did not - and reading
		 * it unlocked there is how a stored running balance goes
		 * non-monotonic under concurrency and confuses anyone reconciling a
		 * statement (Basecamp 10321653509).
		 */
		'balance_after' => wpss_get_ledger_balance( $user_id, true ) + ( $is_debit ? -abs( $amount ) : $amount ),
		'currency'      => wpss_get_currency(),
		'status'        => 'completed',
		'created_at'    => current_time( 'mysql', true ),
	);

	$formats = array(
		'user_id'        => '%d',
		'type'           => '%s',
		'amount'         => '%f',
		'balance_after'  => '%f',
		'currency'       => '%s',
		'description'    => '%s',
		'reference_type' => '%s',
		'reference_id'   => '%d',
		'status'         => '%s',
		'created_at'     => '%s',
	);
	$row     = array_intersect_key( $row, $formats );

	/*
	 * Formats in $row's key order, not $formats'.
	 *
	 * $wpdb->insert() binds the format list POSITIONALLY against the data
	 * array, but array_intersect_key( $formats, $row ) returns them in
	 * $formats' order. The two only agreed because every caller happened to
	 * write its keys in the same sequence as the list above. A caller that
	 * ordered them differently silently wrote each value into the NEXT
	 * column's format - observed while testing this function: a row came back
	 * with currency = '0' and reference_type = '0.000000'.
	 *
	 * Latent rather than live, since no shipped caller orders them differently,
	 * but this is a money table and the failure is silent corruption rather
	 * than an error.
	 */
	$insert_formats = array();

	foreach ( array_keys( $row ) as $column ) {
		$insert_formats[] = $formats[ $column ];
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$inserted = $wpdb->insert( $wpdb->prefix . 'wpss_wallet_transactions', $row, $insert_formats );

	if ( false === $inserted ) {
		if ( false === stripos( (string) $wpdb->last_error, 'Duplicate entry' ) ) {
			return false;
		}

		wpss_log(
			sprintf(
				'Ledger row %s %s#%s for user %d already exists; the duplicate insert was ignored.',
				(string) ( $row['type'] ?? '' ),
				(string) ( $row['reference_type'] ?? '' ),
				(string) ( $row['reference_id'] ?? '' ),
				$user_id
			)
		);
	}

	if ( $inserted ) {
		( new \WPSellServices\Services\AuditLogService() )->log(
			'ledger.insert',
			'ledger',
			(int) $wpdb->insert_id,
			array(
				'action'   => (string) ( $row['type'] ?? '' ),
				'to_value' => (string) $amount,
				'context'  => array(
					'user_id'        => $user_id,
					'type'           => (string) ( $row['type'] ?? '' ),
					'amount'         => $amount,
					'reference_type' => (string) ( $row['reference_type'] ?? '' ),
					'reference_id'   => (int) ( $row['reference_id'] ?? 0 ),
					'description'    => (string) ( $row['description'] ?? '' ),
				),
			)
		);
	}

	( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->sync_ledger_totals( $user_id );

	return true;
}

/**
 * The amount commission is taken on for a stored order: its price without tax.
 *
 * The subtotal plus addons_total is already pre-tax when tax is added on top. When
 * the tax is included in the price it is not, so the tax recorded on the order is
 * taken off (Basecamp 10336467589). Orders created before 1.8.0 did not record
 * whether their tax was included and keep the old reading.
 *
 * @since 1.8.0
 *
 * @param object $order Order row or ServiceOrder.
 * @return float
 */
function wpss_order_commission_base( object $order ): float {
	$base = (float) ( $order->subtotal ?? 0 ) + (float) ( $order->addons_total ?? 0 );
	$meta = $order->meta ?? null;
	$meta = is_string( $meta ) ? json_decode( $meta, true ) : (array) $meta;

	if ( is_array( $meta ) && ! empty( $meta['tax_included'] ) ) {
		$base -= (float) ( $meta['tax_amount'] ?? 0 );
	}

	return max( 0.0, $base );
}

/**
 * Ledger holders whose balance has gone below zero, in ONE query.
 *
 * A vendor ends up here when a past payout exceeded what they had actually
 * earned. On this install that came from the pre-1.7.0 ledger bug: `-amount`
 * turned one negatively-stored withdrawal row into a credit, the balance was
 * overstated, and the vendor was paid against the inflated figure. The -ABS()
 * fix made the sum correct, and the correct sum shows the hole.
 *
 * The site owner needs to find these before a vendor opens a ticket about a
 * minus sign, so this is surfaced on the Vendors screen. Deliberately one
 * GROUP BY with a HAVING rather than looping vendors and calling
 * wpss_get_ledger_balance() each time - that is the N+1 this codebase keeps
 * being bitten by, and it would scale with the vendor count.
 *
 * Uses the same debit-type list and the same -ABS() convention as
 * wpss_get_ledger_balance(), so the two can never disagree about what a
 * balance is.
 *
 * @since 1.7.0
 *
 * @param int $limit Maximum rows to return.
 * @return array<int,array{user_id:int,balance:float}> Most negative first.
 */
function wpss_get_negative_ledger_balances( int $limit = 50 ): array {
	global $wpdb;

	$table       = $wpdb->prefix . 'wpss_wallet_transactions';
	$debit_types = wpss_get_ledger_debit_types_sql();
	$limit       = max( 1, min( 500, $limit ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- debit types are a fixed internal list; limit is clamped above.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id,
				COALESCE( SUM( CASE WHEN type IN ( {$debit_types} ) THEN -ABS( amount ) ELSE amount END ), 0 ) AS balance
			FROM {$table}
			WHERE status = 'completed'
			GROUP BY user_id
			HAVING balance < 0
			ORDER BY balance ASC
			LIMIT %d",
			$limit
		)
	);

	$out = array();

	foreach ( (array) $rows as $row ) {
		$out[] = array(
			'user_id' => (int) $row->user_id,
			'balance' => (float) $row->balance,
		);
	}

	return $out;
}

/**
 * Get wallet manager instance.
 *
 * Returns the WalletManager from WP Sell Services Pro if available.
 * Provides access to wallet balance, credit, debit operations.
 *
 * @since 1.1.0
 *
 * @return object|null WalletManager instance or null if Pro not active.
 */
function wpss_get_wallet_manager(): ?object {
	/**
	 * Filter the wallet manager instance.
	 *
	 * Pro plugin uses this to provide the WalletManager.
	 *
	 * @since 1.1.0
	 * @param object|null $wallet_manager WalletManager instance.
	 */
	return apply_filters( 'wpss_wallet_manager', null );
}

/**
 * Get wallet balance for a user.
 *
 * @since 1.1.0
 *
 * @param int|null $user_id User ID. Defaults to current user.
 * @return float Wallet balance or 0 if wallet not available.
 */
function wpss_get_wallet_balance( ?int $user_id = null ): float {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return 0.0;
	}

	$wallet = wpss_get_wallet_manager();

	if ( ! $wallet || ! method_exists( $wallet, 'get_balance' ) ) {
		// No wallet manager (Pro inactive): fall back to the ledger instead of
		// reporting 0.00. Free sites still accrue vendor earnings in
		// wpss_wallet_transactions, so returning zero here understated every
		// balance on a free-only install.
		return wpss_get_ledger_balance( $user_id );
	}

	return (float) $wallet->get_balance( $user_id );
}

/**
 * Check if wallet feature is available.
 *
 * @since 1.1.0
 *
 * @return bool True if wallet is available (Pro active with wallet enabled).
 */
function wpss_has_wallet(): bool {
	$wallet = wpss_get_wallet_manager();

	return null !== $wallet && method_exists( $wallet, 'get_balance' );
}
