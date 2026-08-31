<?php
/**
 * Money: currency registry, formatting, minor units, refunds and the wallet/ledger helpers.
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
 * Format price with currency symbol.
 *
 * @param float  $price    The price to format.
 * @param string $currency Currency code.
 * @return string
 */
function wpss_format_price( float $price, string $currency = '' ): string {
	if ( empty( $currency ) ) {
		$currency = wpss_get_currency();
	}

	// Use the canonical symbol map (wpss_get_currency_symbol) so every
	// supported currency renders with its proper symbol out of the box - no
	// code snippet required. Falls back to the code plus a space for a
	// currency with no known symbol.
	$symbol = wpss_get_currency_symbol( $currency );
	if ( $symbol === $currency ) {
		$symbol .= ' ';
	}
	$decimals = wpss_get_currency_decimals( $currency );

	/**
	 * Filter the formatted price.
	 *
	 * @param string $formatted Formatted price string.
	 * @param float  $price     Original price.
	 * @param string $currency  Currency code.
	 */
	// Negative amounts put the minus BEFORE the symbol ("-$90.00"), not after
	// it ("$-90.00"). Concatenating the symbol onto a pre-signed number gave
	// the latter, which reads as a typo — and negatives became reachable in the
	// UI once refunds started driving vendor balances below zero.
	$is_negative = $price < 0;
	$formatted   = $symbol . number_format( abs( $price ), $decimals );

	if ( $is_negative ) {
		$formatted = '-' . $formatted;
	}

	return apply_filters(
		'wpss_format_price',
		$formatted,
		$price,
		$currency
	);
}

/**
 * Format a CATALOG price (what a shopper pays) with an optional display hint.
 *
 * Identical to wpss_format_price() by default, but scoped to catalog surfaces —
 * service card, package tiers, single-service price — so an add-on (Pro) can
 * append a "≈ €46" converted hint next to the base amount WITHOUT the hint ever
 * appearing on vendor-facing money (wallet, earnings, payouts) that runs through
 * plain wpss_format_price().
 *
 * The stored value is ALWAYS the base amount; any hint is presentation only and
 * changes nothing in the database, the charge, or the ledger.
 *
 * @since 1.3.0
 *
 * @param float  $amount  Catalog amount in the store base currency.
 * @param string $context Where it is shown ('card', 'package', 'single', …).
 * @return string Base-price HTML, with a display hint appended if one is hooked.
 */
function wpss_catalog_price_html( float $amount, string $context = '' ): string {
	// The base amount travels with the markup so a display-currency add-on can
	// render its hint IN THE BROWSER instead of forking the server response.
	// That distinction is the whole point: this HTML is identical for every
	// visitor, so it stays cacheable, and a page cache can never serve one
	// shopper's currency to another. %F (not %f) keeps the decimal separator a
	// dot regardless of locale, so JS can parseFloat() it.
	//
	// PORTFOLIO CONTRACT — `data-wbcom-amount` is deliberately NOT wpss-prefixed.
	// A site can run several Wbcom products at once (BuddyNext bundles them), and
	// per-plugin attributes would mean each one shipping its own rate fetch,
	// timezone map and renderer for the same job. One neutral attribute lets a
	// single currency layer hint prices emitted by any of them. See
	// ~/.claude/workflows/wbcom-display-currency-standard.md. The wpss-prefixed
	// class stays for styling, which IS plugin-specific.
	$html = sprintf(
		'<span class="wpss-price wbcom-price" data-wbcom-amount="%s">%s</span>',
		esc_attr( sprintf( '%.4F', $amount ) ),
		wpss_format_price( $amount )
	);

	/**
	 * Filter catalog price HTML to append a display-currency hint.
	 *
	 * Hooked by Pro's display-currency feature. Receives the base-formatted HTML
	 * and the raw base amount; must return HTML that still shows the base price
	 * (append, never replace) so the shopper always sees what they are charged.
	 *
	 * Server-side hinting is the no-JS fallback only — see Pro's
	 * DisplayCurrencyManager::append_hint(), which deliberately fires only on an
	 * explicit ?wpss_currency= request because a query-var URL is cache-keyed
	 * separately by every page cache.
	 *
	 * @since 1.5.1
	 *
	 * @param string $html    Base price HTML, wrapped in a .wpss-price span.
	 * @param float  $amount  Raw base amount.
	 * @param string $context Catalog surface identifier.
	 */
	return apply_filters( 'wpss_catalog_price_html', $html, $amount, $context );
}

/**
 * Get the number of decimal places to display for a currency.
 *
 * Zero-decimal currencies (e.g. JPY, KRW, VND) are conventionally shown
 * without minor units - "¥120,000", not "¥120,000.00". This is the single
 * source of truth used by both PHP price formatting and the client-side
 * JS formatter (localized as `currencyDecimals`).
 *
 * @since 1.2.1
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return int Number of decimal places (0 or 2).
 */
function wpss_get_currency_decimals( string $currency = '' ): int {
	if ( empty( $currency ) ) {
		$currency = wpss_get_currency();
	}

	$registry = wpss_get_currency_registry();
	$decimals = isset( $registry[ $currency ] ) ? (int) $registry[ $currency ]['decimals'] : 2;

	if ( in_array( $currency, wpss_get_zero_decimal_currencies(), true ) ) {
		$decimals = 0;
	}

	if ( in_array( $currency, wpss_get_three_decimal_currencies(), true ) ) {
		$decimals = 3;
	}

	/**
	 * Filter the number of decimal places for a currency.
	 *
	 * @since 1.2.1
	 *
	 * @param int    $decimals Number of decimal places.
	 * @param string $currency Currency code.
	 */
	return (int) apply_filters( 'wpss_currency_decimals', $decimals, $currency );
}

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
	return "'" . implode( "','", wpss_get_ledger_debit_types() ) . "'";
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
 * Vendor's share of a refund.
 *
 * THE single proportional formula. A refund gives the buyer back some or all of
 * what they paid; the vendor gives back the same proportion of what they earned,
 * and the platform gives back the same proportion of its fee. A full refund is
 * simply the case where $refunded equals the order total, so one formula covers
 * both and there is no separate "partial" path to drift.
 *
 * @since 1.2.3
 *
 * @param object $order    Order exposing total and vendor_earnings.
 * @param float  $refunded Amount refunded to the buyer.
 * @return float Vendor's share, never negative, never more than they earned.
 */
function wpss_get_refund_vendor_share( object $order, float $refunded ): float {
	$total    = (float) ( $order->total ?? 0 );
	$earnings = (float) ( $order->vendor_earnings ?? 0 );

	if ( $total <= 0 || $earnings <= 0 || $refunded <= 0 ) {
		return 0.0;
	}

	// Clamp: refunding more than the order total would otherwise claw back more
	// than the vendor ever earned.
	$refunded = min( $refunded, $total );

	return round( $earnings * ( $refunded / $total ), 2 );
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
 * The one tax calculation.
 *
 * Tax was written out inline in four places and MISSING from a fifth, which is
 * how a buyer came to be shown $100.30 and charged $85.00: the checkout
 * template computed and displayed tax, the order row recorded a taxed total,
 * and CheckoutIntentService - the figure the gateway actually charges - built
 * its amount from package price plus add-ons and never applied any tax at all
 * (Basecamp 10254444011).
 *
 * Tax is not revenue to split. Commission stays on the pre-tax base, which is
 * what CommissionService already does via $order->subtotal + addons_total, so
 * this returns the base back separately rather than folding it in.
 *
 * The inclusive branch is the one worth reading twice: when a price already
 * contains tax, the tax is extracted from it and the total is unchanged. Get
 * that backwards and every inclusive-tax site over-charges by the rate.
 *
 * @since 1.7.0
 *
 * @param float $base       Pre-tax amount (package price plus add-ons).
 * @param int   $vendor_id  Vendor user ID, for the rate filter.
 * @param int   $service_id Service post ID, for the rate filter.
 * @return array{rate: float, amount: float, base: float, total: float, included: bool, label: string, enabled: bool}
 */
function wpss_calculate_tax( float $base, int $vendor_id = 0, int $service_id = 0 ): array {
	$settings = get_option( 'wpss_tax', array() );
	$enabled  = ! empty( $settings['enable_tax'] );
	$included = ! empty( $settings['tax_included'] );
	$label    = (string) ( $settings['tax_label'] ?? __( 'Tax', 'wp-sell-services' ) );
	$rate     = $enabled ? (float) ( $settings['tax_rate'] ?? 0 ) : 0.0;

	/** This filter is documented in StandaloneOrderProvider::create_order() */
	$rate = (float) apply_filters( 'wpss_checkout_tax_rate', $rate, $vendor_id, $service_id );

	$amount = 0.0;

	if ( $rate > 0 && $base > 0 ) {
		$amount = $included
			? $base - ( $base / ( 1 + $rate / 100 ) )
			: $base * ( $rate / 100 );
	}

	return array(
		'enabled'  => $enabled,
		'included' => $included,
		'label'    => $label,
		'rate'     => $rate,
		'base'     => $base,
		'amount'   => $amount,
		// Inclusive: the tax is already inside the price, so the buyer pays the
		// base. Exclusive: it is added on top.
		'total'    => $included ? $base : $base + $amount,
	);
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
 * Get the list of three-decimal currency codes (ISO 4217).
 *
 * These are charged in thousandths, not hundredths — a 10.000 BHD charge is
 * 10000 minor units, not 1000. They are NOT in the built-in currency registry,
 * so without this list they silently fell back to two decimals and every
 * gateway amount for them was 10x wrong.
 *
 * @since 1.2.2
 *
 * @return string[] Uppercase currency codes.
 */
function wpss_get_three_decimal_currencies(): array {
	$codes = array( 'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' );

	/**
	 * Filter the list of three-decimal currency codes.
	 *
	 * @since 1.2.2
	 *
	 * @param string[] $codes Uppercase currency codes.
	 */
	return apply_filters( 'wpss_three_decimal_currencies', $codes );
}

/**
 * Convert a major-unit amount to integer minor units for the given currency.
 *
 * Mirrors WooCommerce's `wc_add_number_precision()`: scale by the currency's
 * decimal places and round to an integer, so money is compared and transported
 * as integers instead of floats. Currency-aware by construction — JPY/KRW have
 * no minor unit (x1), USD/EUR have two (x100), BHD/KWD have three (x1000).
 *
 * THE canonical converter. Do not hand-roll `* 100` or a `0.01` epsilon
 * anywhere — both are wrong for zero-decimal and three-decimal currencies.
 *
 * @since 1.2.2
 *
 * @param float  $amount   Amount in major units (e.g. dollars).
 * @param string $currency Optional. Currency code. Defaults to the store currency.
 * @return int Amount in minor units (e.g. cents).
 */
function wpss_amount_to_minor_units( float $amount, string $currency = '' ): int {
	$decimals = wpss_get_currency_decimals( $currency );

	return (int) round( $amount * ( 10 ** $decimals ) );
}

/**
 * Convert integer minor units back to a major-unit amount.
 *
 * Inverse of {@see wpss_amount_to_minor_units()}; mirrors WooCommerce's
 * `wc_remove_number_precision()`. Use this when reading an amount back from a
 * gateway — Stripe reports amounts in the smallest currency unit.
 *
 * @since 1.2.2
 *
 * @param int    $minor    Amount in minor units (e.g. cents).
 * @param string $currency Optional. Currency code. Defaults to the store currency.
 * @return float Amount in major units.
 */
function wpss_amount_from_minor_units( int $minor, string $currency = '' ): float {
	$decimals = wpss_get_currency_decimals( $currency );

	return $minor / ( 10 ** $decimals );
}

/**
 * Whether two amounts are equal for the given currency.
 *
 * Compares integer minor units, so there is no float epsilon to tune and the
 * comparison is exact at the currency's real precision. Use this for every
 * "did the buyer pay what we expected?" check.
 *
 * @since 1.2.2
 *
 * @param float  $a        First amount in major units.
 * @param float  $b        Second amount in major units.
 * @param string $currency Optional. Currency code. Defaults to the store currency.
 * @return bool True when the two amounts are the same to the currency's precision.
 */
function wpss_amounts_match( float $a, float $b, string $currency = '' ): bool {
	return wpss_amount_to_minor_units( $a, $currency ) === wpss_amount_to_minor_units( $b, $currency );
}

/**
 * Get the list of zero-decimal currency codes.
 *
 * Derived from the registry (codes whose `decimals` is 0), so it stays in sync
 * automatically. Exposed as its own accessor so JS surfaces that format
 * arbitrary per-row currencies (e.g. the wallet ledger) can localize it.
 *
 * @since 1.2.1
 *
 * @return array<int, string> Currency codes rendered without minor units.
 */
function wpss_get_zero_decimal_currencies(): array {
	$codes = array();
	foreach ( wpss_get_currency_registry() as $code => $data ) {
		if ( 0 === (int) $data['decimals'] ) {
			$codes[] = $code;
		}
	}

	/**
	 * Filter the list of zero-decimal currency codes.
	 *
	 * @since 1.2.1
	 *
	 * @param array<int, string> $codes Currency codes rendered without minor units.
	 */
	return apply_filters( 'wpss_zero_decimal_currencies', $codes );
}

/**
 * Get the HTML attributes for a price <input type="number"> in a currency.
 *
 * Zero-decimal currencies (JPY, KRW, VND) get whole-number step + placeholder;
 * everything else gets cent precision. Single source so no template hardcodes
 * step="0.01" / placeholder="0.00".
 *
 * @since 1.2.1
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return array{step: string, placeholder: string} Input step + placeholder.
 */
function wpss_get_price_input_attrs( string $currency = '' ): array {
	$decimals = wpss_get_currency_decimals( $currency );

	return array(
		'step'        => $decimals > 0 ? '0.01' : '1',
		'placeholder' => $decimals > 0 ? '0.00' : '0',
	);
}

/**
 * Get the default currency.
 *
 * @return string
 */
function wpss_get_currency(): string {
	// Read from wpss_general settings array.
	$general_settings = get_option( 'wpss_general', array() );
	$currency         = $general_settings['currency'] ?? 'USD';

	/**
	 * Filter the default currency.
	 *
	 * @param string $currency Currency code.
	 */
	return apply_filters( 'wpss_currency', $currency );
}

/**
 * Format currency (alias for wpss_format_price).
 *
 * @param float  $amount   Amount to format.
 * @param string $currency Currency code.
 * @return string
 */
function wpss_format_currency( float $amount, string $currency = '' ): string {
	return wpss_format_price( $amount, $currency );
}

/**
 * Get currency symbol.
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return string
 */
function wpss_get_currency_symbol( string $currency = '' ): string {
	if ( empty( $currency ) ) {
		$currency = wpss_get_currency();
	}

	$symbols = array();
	foreach ( wpss_get_currency_registry() as $code => $data ) {
		$symbols[ $code ] = $data['symbol'];
	}

	/**
	 * Filter currency symbols.
	 *
	 * @param array $symbols Currency symbols array (code => symbol).
	 */
	$symbols = apply_filters( 'wpss_currency_symbols', $symbols );

	return $symbols[ $currency ] ?? $currency;
}

/**
 * Get currency format string for JavaScript price formatting.
 *
 * Returns a format string like '$%s' or '€%s' where %s is replaced
 * with the formatted price value in JavaScript.
 *
 * @since 1.1.0
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return string Format string with %s placeholder.
 */
function wpss_get_currency_format( string $currency = '' ): string {
	$symbol = wpss_get_currency_symbol( $currency );

	/**
	 * Filter the currency format string.
	 *
	 * @since 1.1.0
	 * @param string $format   Format string (e.g., '$%s').
	 * @param string $symbol   Currency symbol.
	 * @param string $currency Currency code.
	 */
	return apply_filters( 'wpss_currency_format', $symbol . '%s', $symbol, $currency );
}

/**
 * Get the canonical currency registry.
 *
 * Single source of truth for every supported currency - its display name,
 * symbol, and number of decimal places. Modeled on WooCommerce's currency
 * list: all currency surfaces (settings dropdowns, price formatting, symbols,
 * decimals, JS localization) derive from this one array, so the name, symbol,
 * and decimals lists can never drift apart.
 *
 * Free owns this registry; Pro (and any add-on) reuses it through the
 * accessors below rather than maintaining its own currency list.
 *
 * @since 1.2.1
 *
 * @return array<string, array{name: string, symbol: string, decimals: int}>
 */
function wpss_get_currency_registry(): array {
	$registry = array(
		'USD' => array(
			'name'     => __( 'US Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'EUR' => array(
			'name'     => __( 'Euro', 'wp-sell-services' ),
			'symbol'   => '€',
			'decimals' => 2,
		),
		'GBP' => array(
			'name'     => __( 'British Pound', 'wp-sell-services' ),
			'symbol'   => '£',
			'decimals' => 2,
		),
		'JPY' => array(
			'name'     => __( 'Japanese Yen', 'wp-sell-services' ),
			'symbol'   => '¥',
			'decimals' => 0,
		),
		'INR' => array(
			'name'     => __( 'Indian Rupee', 'wp-sell-services' ),
			'symbol'   => '₹',
			'decimals' => 2,
		),
		'AUD' => array(
			'name'     => __( 'Australian Dollar', 'wp-sell-services' ),
			'symbol'   => 'A$',
			'decimals' => 2,
		),
		'CAD' => array(
			'name'     => __( 'Canadian Dollar', 'wp-sell-services' ),
			'symbol'   => 'C$',
			'decimals' => 2,
		),
		'CHF' => array(
			'name'     => __( 'Swiss Franc', 'wp-sell-services' ),
			'symbol'   => 'CHF',
			'decimals' => 2,
		),
		'CNY' => array(
			'name'     => __( 'Chinese Yuan', 'wp-sell-services' ),
			'symbol'   => '¥',
			'decimals' => 2,
		),
		'KRW' => array(
			'name'     => __( 'South Korean Won', 'wp-sell-services' ),
			'symbol'   => '₩',
			'decimals' => 0,
		),
		'BRL' => array(
			'name'     => __( 'Brazilian Real', 'wp-sell-services' ),
			'symbol'   => 'R$',
			'decimals' => 2,
		),
		'MXN' => array(
			'name'     => __( 'Mexican Peso', 'wp-sell-services' ),
			'symbol'   => 'MX$',
			'decimals' => 2,
		),
		'SGD' => array(
			'name'     => __( 'Singapore Dollar', 'wp-sell-services' ),
			'symbol'   => 'S$',
			'decimals' => 2,
		),
		'HKD' => array(
			'name'     => __( 'Hong Kong Dollar', 'wp-sell-services' ),
			'symbol'   => 'HK$',
			'decimals' => 2,
		),
		'NOK' => array(
			'name'     => __( 'Norwegian Krone', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 2,
		),
		'SEK' => array(
			'name'     => __( 'Swedish Krona', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 2,
		),
		'DKK' => array(
			'name'     => __( 'Danish Krone', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 2,
		),
		'NZD' => array(
			'name'     => __( 'New Zealand Dollar', 'wp-sell-services' ),
			'symbol'   => 'NZ$',
			'decimals' => 2,
		),
		'ZAR' => array(
			'name'     => __( 'South African Rand', 'wp-sell-services' ),
			'symbol'   => 'R',
			'decimals' => 2,
		),
		'RUB' => array(
			'name'     => __( 'Russian Ruble', 'wp-sell-services' ),
			'symbol'   => '₽',
			'decimals' => 2,
		),
		'TRY' => array(
			'name'     => __( 'Turkish Lira', 'wp-sell-services' ),
			'symbol'   => '₺',
			'decimals' => 2,
		),
		'PLN' => array(
			'name'     => __( 'Polish Zloty', 'wp-sell-services' ),
			'symbol'   => 'zł',
			'decimals' => 2,
		),
		'THB' => array(
			'name'     => __( 'Thai Baht', 'wp-sell-services' ),
			'symbol'   => '฿',
			'decimals' => 2,
		),
		'MYR' => array(
			'name'     => __( 'Malaysian Ringgit', 'wp-sell-services' ),
			'symbol'   => 'RM',
			'decimals' => 2,
		),
		'PHP' => array(
			'name'     => __( 'Philippine Peso', 'wp-sell-services' ),
			'symbol'   => '₱',
			'decimals' => 2,
		),
		'IDR' => array(
			'name'     => __( 'Indonesian Rupiah', 'wp-sell-services' ),
			'symbol'   => 'Rp',
			'decimals' => 2,
		),
		'VND' => array(
			'name'     => __( 'Vietnamese Dong', 'wp-sell-services' ),
			'symbol'   => '₫',
			'decimals' => 0,
		),
		'AED' => array(
			'name'     => __( 'UAE Dirham', 'wp-sell-services' ),
			'symbol'   => 'د.إ',
			'decimals' => 2,
		),
		'SAR' => array(
			'name'     => __( 'Saudi Riyal', 'wp-sell-services' ),
			'symbol'   => '﷼',
			'decimals' => 2,
		),
		'EGP' => array(
			'name'     => __( 'Egyptian Pound', 'wp-sell-services' ),
			'symbol'   => 'E£',
			'decimals' => 2,
		),
		'NGN' => array(
			'name'     => __( 'Nigerian Naira', 'wp-sell-services' ),
			'symbol'   => '₦',
			'decimals' => 2,
		),
		'AFN' => array(
			'name'     => __( 'Afghan Afghani', 'wp-sell-services' ),
			'symbol'   => '؋',
			'decimals' => 2,
		),
		'ALL' => array(
			'name'     => __( 'Albanian Lek', 'wp-sell-services' ),
			'symbol'   => 'L',
			'decimals' => 2,
		),
		'AMD' => array(
			'name'     => __( 'Armenian Dram', 'wp-sell-services' ),
			'symbol'   => '֏',
			'decimals' => 2,
		),
		'ANG' => array(
			'name'     => __( 'Netherlands Antillean Guilder', 'wp-sell-services' ),
			'symbol'   => 'ƒ',
			'decimals' => 2,
		),
		'AOA' => array(
			'name'     => __( 'Angolan Kwanza', 'wp-sell-services' ),
			'symbol'   => 'Kz',
			'decimals' => 2,
		),
		'ARS' => array(
			'name'     => __( 'Argentine Peso', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'AWG' => array(
			'name'     => __( 'Aruban Florin', 'wp-sell-services' ),
			'symbol'   => 'ƒ',
			'decimals' => 2,
		),
		'AZN' => array(
			'name'     => __( 'Azerbaijani Manat', 'wp-sell-services' ),
			'symbol'   => '₼',
			'decimals' => 2,
		),
		'BAM' => array(
			'name'     => __( 'Bosnia-Herzegovina Mark', 'wp-sell-services' ),
			'symbol'   => 'KM',
			'decimals' => 2,
		),
		'BBD' => array(
			'name'     => __( 'Barbadian Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'BDT' => array(
			'name'     => __( 'Bangladeshi Taka', 'wp-sell-services' ),
			'symbol'   => '৳',
			'decimals' => 2,
		),
		'BGN' => array(
			'name'     => __( 'Bulgarian Lev', 'wp-sell-services' ),
			'symbol'   => 'лв',
			'decimals' => 2,
		),
		'BHD' => array(
			'name'     => __( 'Bahraini Dinar', 'wp-sell-services' ),
			'symbol'   => 'BD',
			'decimals' => 3,
		),
		'BIF' => array(
			'name'     => __( 'Burundian Franc', 'wp-sell-services' ),
			'symbol'   => 'FBu',
			'decimals' => 0,
		),
		'BMD' => array(
			'name'     => __( 'Bermudan Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'BND' => array(
			'name'     => __( 'Brunei Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'BOB' => array(
			'name'     => __( 'Bolivian Boliviano', 'wp-sell-services' ),
			'symbol'   => 'Bs.',
			'decimals' => 2,
		),
		'BSD' => array(
			'name'     => __( 'Bahamian Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'BTN' => array(
			'name'     => __( 'Bhutanese Ngultrum', 'wp-sell-services' ),
			'symbol'   => 'Nu.',
			'decimals' => 2,
		),
		'BWP' => array(
			'name'     => __( 'Botswanan Pula', 'wp-sell-services' ),
			'symbol'   => 'P',
			'decimals' => 2,
		),
		'BYN' => array(
			'name'     => __( 'Belarusian Ruble', 'wp-sell-services' ),
			'symbol'   => 'Br',
			'decimals' => 2,
		),
		'BZD' => array(
			'name'     => __( 'Belize Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'CDF' => array(
			'name'     => __( 'Congolese Franc', 'wp-sell-services' ),
			'symbol'   => 'FC',
			'decimals' => 2,
		),
		'CLP' => array(
			'name'     => __( 'Chilean Peso', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 0,
		),
		'COP' => array(
			'name'     => __( 'Colombian Peso', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'CRC' => array(
			'name'     => __( 'Costa Rican Colon', 'wp-sell-services' ),
			'symbol'   => '₡',
			'decimals' => 2,
		),
		'CUP' => array(
			'name'     => __( 'Cuban Peso', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'CVE' => array(
			'name'     => __( 'Cape Verdean Escudo', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'CZK' => array(
			'name'     => __( 'Czech Koruna', 'wp-sell-services' ),
			'symbol'   => 'Kc',
			'decimals' => 2,
		),
		'DJF' => array(
			'name'     => __( 'Djiboutian Franc', 'wp-sell-services' ),
			'symbol'   => 'Fdj',
			'decimals' => 0,
		),
		'DOP' => array(
			'name'     => __( 'Dominican Peso', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'DZD' => array(
			'name'     => __( 'Algerian Dinar', 'wp-sell-services' ),
			'symbol'   => 'DA',
			'decimals' => 2,
		),
		'ETB' => array(
			'name'     => __( 'Ethiopian Birr', 'wp-sell-services' ),
			'symbol'   => 'Br',
			'decimals' => 2,
		),
		'FJD' => array(
			'name'     => __( 'Fijian Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'GEL' => array(
			'name'     => __( 'Georgian Lari', 'wp-sell-services' ),
			'symbol'   => '₾',
			'decimals' => 2,
		),
		'GHS' => array(
			'name'     => __( 'Ghanaian Cedi', 'wp-sell-services' ),
			'symbol'   => '₵',
			'decimals' => 2,
		),
		'GMD' => array(
			'name'     => __( 'Gambian Dalasi', 'wp-sell-services' ),
			'symbol'   => 'D',
			'decimals' => 2,
		),
		'GNF' => array(
			'name'     => __( 'Guinean Franc', 'wp-sell-services' ),
			'symbol'   => 'FG',
			'decimals' => 0,
		),
		'GTQ' => array(
			'name'     => __( 'Guatemalan Quetzal', 'wp-sell-services' ),
			'symbol'   => 'Q',
			'decimals' => 2,
		),
		'GYD' => array(
			'name'     => __( 'Guyanaese Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'HNL' => array(
			'name'     => __( 'Honduran Lempira', 'wp-sell-services' ),
			'symbol'   => 'L',
			'decimals' => 2,
		),
		'HRK' => array(
			'name'     => __( 'Croatian Kuna', 'wp-sell-services' ),
			'symbol'   => 'kn',
			'decimals' => 2,
		),
		'HTG' => array(
			'name'     => __( 'Haitian Gourde', 'wp-sell-services' ),
			'symbol'   => 'G',
			'decimals' => 2,
		),
		'HUF' => array(
			'name'     => __( 'Hungarian Forint', 'wp-sell-services' ),
			'symbol'   => 'Ft',
			'decimals' => 2,
		),
		'ILS' => array(
			'name'     => __( 'Israeli Shekel', 'wp-sell-services' ),
			'symbol'   => '₪',
			'decimals' => 2,
		),
		'IQD' => array(
			'name'     => __( 'Iraqi Dinar', 'wp-sell-services' ),
			'symbol'   => 'ID',
			'decimals' => 3,
		),
		'IRR' => array(
			'name'     => __( 'Iranian Rial', 'wp-sell-services' ),
			'symbol'   => 'IR',
			'decimals' => 2,
		),
		'ISK' => array(
			'name'     => __( 'Icelandic Krona', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 0,
		),
		'JMD' => array(
			'name'     => __( 'Jamaican Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'JOD' => array(
			'name'     => __( 'Jordanian Dinar', 'wp-sell-services' ),
			'symbol'   => 'JD',
			'decimals' => 3,
		),
		'KES' => array(
			'name'     => __( 'Kenyan Shilling', 'wp-sell-services' ),
			'symbol'   => 'KSh',
			'decimals' => 2,
		),
		'KGS' => array(
			'name'     => __( 'Kyrgystani Som', 'wp-sell-services' ),
			'symbol'   => 'c',
			'decimals' => 2,
		),
		'KHR' => array(
			'name'     => __( 'Cambodian Riel', 'wp-sell-services' ),
			'symbol'   => '៛',
			'decimals' => 2,
		),
		'KMF' => array(
			'name'     => __( 'Comorian Franc', 'wp-sell-services' ),
			'symbol'   => 'CF',
			'decimals' => 0,
		),
		'KWD' => array(
			'name'     => __( 'Kuwaiti Dinar', 'wp-sell-services' ),
			'symbol'   => 'KD',
			'decimals' => 3,
		),
		'KYD' => array(
			'name'     => __( 'Cayman Islands Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'KZT' => array(
			'name'     => __( 'Kazakhstani Tenge', 'wp-sell-services' ),
			'symbol'   => '₸',
			'decimals' => 2,
		),
		'LAK' => array(
			'name'     => __( 'Laotian Kip', 'wp-sell-services' ),
			'symbol'   => '₭',
			'decimals' => 2,
		),
		'LBP' => array(
			'name'     => __( 'Lebanese Pound', 'wp-sell-services' ),
			'symbol'   => 'LL',
			'decimals' => 2,
		),
		'LKR' => array(
			'name'     => __( 'Sri Lankan Rupee', 'wp-sell-services' ),
			'symbol'   => 'Rs',
			'decimals' => 2,
		),
		'LRD' => array(
			'name'     => __( 'Liberian Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'LSL' => array(
			'name'     => __( 'Lesotho Loti', 'wp-sell-services' ),
			'symbol'   => 'L',
			'decimals' => 2,
		),
		'LYD' => array(
			'name'     => __( 'Libyan Dinar', 'wp-sell-services' ),
			'symbol'   => 'LD',
			'decimals' => 3,
		),
		'MAD' => array(
			'name'     => __( 'Moroccan Dirham', 'wp-sell-services' ),
			'symbol'   => 'DH',
			'decimals' => 2,
		),
		'MDL' => array(
			'name'     => __( 'Moldovan Leu', 'wp-sell-services' ),
			'symbol'   => 'L',
			'decimals' => 2,
		),
		'MGA' => array(
			'name'     => __( 'Malagasy Ariary', 'wp-sell-services' ),
			'symbol'   => 'Ar',
			'decimals' => 2,
		),
		'MKD' => array(
			'name'     => __( 'Macedonian Denar', 'wp-sell-services' ),
			'symbol'   => 'den',
			'decimals' => 2,
		),
		'MMK' => array(
			'name'     => __( 'Myanmar Kyat', 'wp-sell-services' ),
			'symbol'   => 'K',
			'decimals' => 2,
		),
		'MNT' => array(
			'name'     => __( 'Mongolian Tugrik', 'wp-sell-services' ),
			'symbol'   => '₮',
			'decimals' => 2,
		),
		'MOP' => array(
			'name'     => __( 'Macanese Pataca', 'wp-sell-services' ),
			'symbol'   => 'MOP$',
			'decimals' => 2,
		),
		'MUR' => array(
			'name'     => __( 'Mauritian Rupee', 'wp-sell-services' ),
			'symbol'   => 'Rs',
			'decimals' => 2,
		),
		'MVR' => array(
			'name'     => __( 'Maldivian Rufiyaa', 'wp-sell-services' ),
			'symbol'   => 'Rf',
			'decimals' => 2,
		),
		'MWK' => array(
			'name'     => __( 'Malawian Kwacha', 'wp-sell-services' ),
			'symbol'   => 'MK',
			'decimals' => 2,
		),
		'MZN' => array(
			'name'     => __( 'Mozambican Metical', 'wp-sell-services' ),
			'symbol'   => 'MT',
			'decimals' => 2,
		),
		'NAD' => array(
			'name'     => __( 'Namibian Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'NIO' => array(
			'name'     => __( 'Nicaraguan Cordoba', 'wp-sell-services' ),
			'symbol'   => 'C$',
			'decimals' => 2,
		),
		'NPR' => array(
			'name'     => __( 'Nepalese Rupee', 'wp-sell-services' ),
			'symbol'   => 'Rs',
			'decimals' => 2,
		),
		'OMR' => array(
			'name'     => __( 'Omani Rial', 'wp-sell-services' ),
			'symbol'   => 'RO',
			'decimals' => 3,
		),
		'PAB' => array(
			'name'     => __( 'Panamanian Balboa', 'wp-sell-services' ),
			'symbol'   => 'B/.',
			'decimals' => 2,
		),
		'PEN' => array(
			'name'     => __( 'Peruvian Sol', 'wp-sell-services' ),
			'symbol'   => 'S/',
			'decimals' => 2,
		),
		'PGK' => array(
			'name'     => __( 'Papua New Guinean Kina', 'wp-sell-services' ),
			'symbol'   => 'K',
			'decimals' => 2,
		),
		'PKR' => array(
			'name'     => __( 'Pakistani Rupee', 'wp-sell-services' ),
			'symbol'   => 'Rs',
			'decimals' => 2,
		),
		'PYG' => array(
			'name'     => __( 'Paraguayan Guarani', 'wp-sell-services' ),
			'symbol'   => '₲',
			'decimals' => 0,
		),
		'QAR' => array(
			'name'     => __( 'Qatari Rial', 'wp-sell-services' ),
			'symbol'   => 'QR',
			'decimals' => 2,
		),
		'RON' => array(
			'name'     => __( 'Romanian Leu', 'wp-sell-services' ),
			'symbol'   => 'lei',
			'decimals' => 2,
		),
		'RSD' => array(
			'name'     => __( 'Serbian Dinar', 'wp-sell-services' ),
			'symbol'   => 'din.',
			'decimals' => 2,
		),
		'RWF' => array(
			'name'     => __( 'Rwandan Franc', 'wp-sell-services' ),
			'symbol'   => 'FRw',
			'decimals' => 0,
		),
		'SBD' => array(
			'name'     => __( 'Solomon Islands Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'SCR' => array(
			'name'     => __( 'Seychellois Rupee', 'wp-sell-services' ),
			'symbol'   => 'Rs',
			'decimals' => 2,
		),
		'SDG' => array(
			'name'     => __( 'Sudanese Pound', 'wp-sell-services' ),
			'symbol'   => 'SDG',
			'decimals' => 2,
		),
		'SLE' => array(
			'name'     => __( 'Sierra Leonean Leone', 'wp-sell-services' ),
			'symbol'   => 'Le',
			'decimals' => 2,
		),
		'SOS' => array(
			'name'     => __( 'Somali Shilling', 'wp-sell-services' ),
			'symbol'   => 'S',
			'decimals' => 2,
		),
		'SRD' => array(
			'name'     => __( 'Surinamese Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'SSP' => array(
			'name'     => __( 'South Sudanese Pound', 'wp-sell-services' ),
			'symbol'   => 'GBP',
			'decimals' => 2,
		),
		'SVC' => array(
			'name'     => __( 'Salvadoran Colon', 'wp-sell-services' ),
			'symbol'   => '₡',
			'decimals' => 2,
		),
		'SZL' => array(
			'name'     => __( 'Swazi Lilangeni', 'wp-sell-services' ),
			'symbol'   => 'E',
			'decimals' => 2,
		),
		'TJS' => array(
			'name'     => __( 'Tajikistani Somoni', 'wp-sell-services' ),
			'symbol'   => 'SM',
			'decimals' => 2,
		),
		'TMT' => array(
			'name'     => __( 'Turkmenistani Manat', 'wp-sell-services' ),
			'symbol'   => 'm',
			'decimals' => 2,
		),
		'TND' => array(
			'name'     => __( 'Tunisian Dinar', 'wp-sell-services' ),
			'symbol'   => 'DT',
			'decimals' => 3,
		),
		'TOP' => array(
			'name'     => __( 'Tongan Paanga', 'wp-sell-services' ),
			'symbol'   => 'T$',
			'decimals' => 2,
		),
		'TTD' => array(
			'name'     => __( 'Trinidad and Tobago Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'TWD' => array(
			'name'     => __( 'New Taiwan Dollar', 'wp-sell-services' ),
			'symbol'   => 'NT$',
			'decimals' => 2,
		),
		'TZS' => array(
			'name'     => __( 'Tanzanian Shilling', 'wp-sell-services' ),
			'symbol'   => 'TSh',
			'decimals' => 2,
		),
		'UAH' => array(
			'name'     => __( 'Ukrainian Hryvnia', 'wp-sell-services' ),
			'symbol'   => '₴',
			'decimals' => 2,
		),
		'UGX' => array(
			'name'     => __( 'Ugandan Shilling', 'wp-sell-services' ),
			'symbol'   => 'USh',
			'decimals' => 0,
		),
		'UYU' => array(
			'name'     => __( 'Uruguayan Peso', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'UZS' => array(
			'name'     => __( 'Uzbekistani Som', 'wp-sell-services' ),
			'symbol'   => 'soum',
			'decimals' => 2,
		),
		'VES' => array(
			'name'     => __( 'Venezuelan Bolivar', 'wp-sell-services' ),
			'symbol'   => 'Bs.',
			'decimals' => 2,
		),
		'VUV' => array(
			'name'     => __( 'Vanuatu Vatu', 'wp-sell-services' ),
			'symbol'   => 'VT',
			'decimals' => 0,
		),
		'WST' => array(
			'name'     => __( 'Samoan Tala', 'wp-sell-services' ),
			'symbol'   => 'T',
			'decimals' => 2,
		),
		'XAF' => array(
			'name'     => __( 'Central African CFA Franc', 'wp-sell-services' ),
			'symbol'   => 'FCFA',
			'decimals' => 0,
		),
		'XCD' => array(
			'name'     => __( 'East Caribbean Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'XOF' => array(
			'name'     => __( 'West African CFA Franc', 'wp-sell-services' ),
			'symbol'   => 'CFA',
			'decimals' => 0,
		),
		'XPF' => array(
			'name'     => __( 'CFP Franc', 'wp-sell-services' ),
			'symbol'   => 'F',
			'decimals' => 0,
		),
		'YER' => array(
			'name'     => __( 'Yemeni Rial', 'wp-sell-services' ),
			'symbol'   => 'YR',
			'decimals' => 2,
		),
		'ZMW' => array(
			'name'     => __( 'Zambian Kwacha', 'wp-sell-services' ),
			'symbol'   => 'ZK',
			'decimals' => 2,
		),
		'ZWL' => array(
			'name'     => __( 'Zimbabwean Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
	);

	/**
	 * Filter the canonical currency registry.
	 *
	 * Add, remove, or adjust a currency (name / symbol / decimals) in ONE
	 * place and every currency surface updates. Preferred over the
	 * per-surface currency filters.
	 *
	 * @since 1.2.1
	 *
	 * @param array<string, array{name: string, symbol: string, decimals: int}> $registry Currency registry.
	 */
	return apply_filters( 'wpss_currency_registry', $registry );
}

/**
 * Get supported currencies (code => name).
 *
 * Derived from {@see wpss_get_currency_registry()}.
 *
 * @return array<string, string>
 */
function wpss_get_currencies(): array {
	$currencies = array();
	foreach ( wpss_get_currency_registry() as $code => $data ) {
		$currencies[ $code ] = $data['name'];
	}

	/**
	 * Filter supported currencies.
	 *
	 * @param array $currencies Currencies array (code => name).
	 */
	return apply_filters( 'wpss_currencies', $currencies );
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
