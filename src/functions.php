<?php
/**
 * Helper Functions
 *
 * @package WPSellServices
 * @since   1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get a plugin option value.
 *
 * Retrieves a setting from one of the plugin's option groups.
 *
 * @param string $group   Option group name (e.g., 'general', 'vendors', 'orders').
 * @param string $key     Option key within the group.
 * @param mixed  $default Default value if option doesn't exist.
 * @return mixed
 */
function wpss_get_option( string $group, string $key, $default = null ) {
	$option_name = 'wpss_' . $group;
	$options     = get_option( $option_name, array() );

	return $options[ $key ] ?? $default;
}

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
 * THE status → CSS class authority for status badges.
 *
 * One place that turns any status value into its badge class, so no surface can
 * invent its own mapping and drift. Two prior bugs this replaces:
 *
 *  - `OrdersListTable` kept a hand-maintained status→class array that was
 *    MISSING refunded / delivered / accepted / resolved, so every one of those
 *    fell through to a `wpss-status-pending` default and a refunded order
 *    rendered amber ("pending") instead of its own colour.
 *  - Half the render sites emitted `wpss-status-<raw_status>` (underscore) while
 *    the other half emitted `str_replace('_','-', $status)` (hyphen), so the CSS
 *    had to carry BOTH spellings of every multi-word status.
 *
 * Emits the HYPHEN spelling (CSS-idiomatic) — every render site routed through
 * here produces the same class, and the CSS needs one rule per status, not two.
 * The status keeps its own semantic colour, defined once in the status-badge
 * CSS. Filterable so a site can recolour a status without editing core.
 *
 * @since 1.3.0
 *
 * @param string $status Raw status value (e.g. 'revision_requested').
 * @return string Space-joined classes: the badge base + the status class.
 */
function wpss_status_class( string $status ): string {
	$status = sanitize_key( $status );
	$class  = 'wpss-status-badge wpss-status-' . str_replace( '_', '-', $status );

	/**
	 * Filter the CSS classes for a status badge.
	 *
	 * @since 1.3.0
	 *
	 * @param string $class  Space-joined badge classes.
	 * @param string $status Raw status value.
	 */
	return apply_filters( 'wpss_status_class', $class, $status );
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

	$sql = "SELECT COALESCE( SUM( CASE WHEN type IN ( {$debit_types} ) THEN -amount ELSE amount END ), 0 )
		FROM {$table}
		WHERE user_id = %d AND status = 'completed'";

	if ( $lock ) {
		$sql .= ' FOR UPDATE';
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (float) $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
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
 * Get the platform name.
 *
 * @since 1.1.0
 *
 * @return string Platform name or site name as fallback.
 */
function wpss_get_platform_name(): string {
	// Read from wpss_general settings array.
	$general_settings = get_option( 'wpss_general', array() );
	$platform_name    = $general_settings['platform_name'] ?? '';

	// Fall back to site name if empty.
	if ( empty( $platform_name ) ) {
		$platform_name = get_bloginfo( 'name' );
	}

	/**
	 * Filter the platform name.
	 *
	 * @since 1.1.0
	 * @param string $platform_name Platform name.
	 */
	return apply_filters( 'wpss_platform_name', $platform_name );
}

/**
 * Get the plugin instance.
 *
 * @return \WPSellServices\Core\Plugin
 */
function wpss(): \WPSellServices\Core\Plugin {
	return \WPSellServices\Core\Plugin::get_instance();
}

/**
 * Get template part.
 *
 * @param string $slug Template slug.
 * @param string $name Optional template name.
 * @param array  $args Optional arguments to pass to template.
 * @return void
 */
function wpss_get_template_part( string $slug, string $name = '', array $args = array() ): void {
	$template = '';

	// Look in theme first.
	if ( $name ) {
		$template = locate_template( "wp-sell-services/{$slug}-{$name}.php" );
	}

	if ( ! $template ) {
		$template = locate_template( "wp-sell-services/{$slug}.php" );
	}

	// Fall back to plugin templates.
	if ( ! $template ) {
		if ( $name && file_exists( WPSS_PLUGIN_DIR . "templates/{$slug}-{$name}.php" ) ) {
			$template = WPSS_PLUGIN_DIR . "templates/{$slug}-{$name}.php";
		} elseif ( file_exists( WPSS_PLUGIN_DIR . "templates/{$slug}.php" ) ) {
			$template = WPSS_PLUGIN_DIR . "templates/{$slug}.php";
		}
	}

	/**
	 * Filter the template file path.
	 *
	 * @param string $template Template file path.
	 * @param string $slug     Template slug.
	 * @param string $name     Template name.
	 */
	$template = apply_filters( 'wpss_get_template_part', $template, $slug, $name );

	$template_name = $name ? "{$slug}-{$name}" : $slug;

	/** This filter is documented in src/functions.php wpss_get_template() */
	$args = apply_filters( 'wpss_template_args', $args, $template_name );

	if ( $template ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}

/**
 * Get template.
 *
 * @param string $template_name Template name.
 * @param array  $args          Arguments to pass to template.
 * @param string $template_path Template path in theme.
 * @param string $default_path  Default template path.
 * @return void
 */
function wpss_get_template( string $template_name, array $args = array(), string $template_path = '', string $default_path = '' ): void {
	if ( empty( $template_path ) ) {
		$template_path = 'wp-sell-services/';
	}

	if ( empty( $default_path ) ) {
		$default_path = WPSS_PLUGIN_DIR . 'templates/';
	}

	// Look within theme first.
	$template = locate_template( $template_path . $template_name );

	// Fall back to plugin.
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	/**
	 * Filter the template file path.
	 *
	 * @param string $template      Template file path.
	 * @param string $template_name Template name.
	 * @param array  $args          Template arguments.
	 */
	$template = apply_filters( 'wpss_get_template', $template, $template_name, $args );

	/**
	 * Filter the template arguments before rendering.
	 *
	 * Allows modification or addition of variables passed to a template
	 * before extraction and rendering.
	 *
	 * @since 1.1.0
	 * @param array  $args          Template arguments.
	 * @param string $template_name Template name being loaded.
	 */
	$args = apply_filters( 'wpss_template_args', $args, $template_name );

	if ( file_exists( $template ) ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}

/**
 * Check if current request is a REST request.
 *
 * @return bool
 */
function wpss_is_rest_request(): bool {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	// Check for REST URL pattern.
	$rest_url    = wp_parse_url( get_rest_url() );
	$current_url = wp_parse_url( add_query_arg( array() ) );

	return isset( $rest_url['path'], $current_url['path'] )
		&& strpos( $current_url['path'], $rest_url['path'] ) === 0;
}

/**
 * Get service by ID.
 *
 * @param int $service_id Service post ID.
 * @return \WPSellServices\Models\Service|null
 */
function wpss_get_service( int $service_id ): ?\WPSellServices\Models\Service {
	$post = get_post( $service_id );

	if ( ! $post || \WPSellServices\PostTypes\ServicePostType::POST_TYPE !== $post->post_type ) {
		return null;
	}

	return \WPSellServices\Models\Service::from_post( $post );
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
 * Get vendor profile by user ID.
 *
 * @param int $user_id WordPress user ID.
 * @return \WPSellServices\Models\VendorProfile|null
 */
function wpss_get_vendor( int $user_id ): ?\WPSellServices\Models\VendorProfile {
	return \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
}

/**
 * Get a service's publish status (active / paused).
 *
 * Shared accessor for every service-status read. Resolves the canonical
 * _wpss_status meta written by the admin service metabox Status select
 * (values: 'active', 'paused').
 *
 * The three SEO integrations used to read '_wpss_service_status', which
 * nothing has ever written, so every "paused service should be noindexed"
 * rule silently evaluated false and paused services stayed indexed.
 *
 * @since 1.2.3
 *
 * @param int $service_id Service post ID.
 * @return string 'active' or 'paused'. Defaults to 'active' when unset, which
 *                matches how an unsaved service behaves everywhere else.
 */
function wpss_get_service_status( int $service_id ): string {
	$status = get_post_meta( $service_id, '_wpss_status', true );

	return is_string( $status ) && '' !== $status ? $status : 'active';
}

/**
 * The billing address fields, in display order.
 *
 * THE canonical field list — the profile form, the checkout prefill, the order
 * snapshot, the admin screen and any invoice all read it, so a field is added
 * in exactly one place.
 *
 * Keys are WooCommerce's, deliberately. On a site running WooCommerce the
 * buyer's address is ALREADY stored under these keys, so we prefill from it and
 * they never type it twice; on a standalone site we own the same keys and a
 * later Woo install inherits them. One address per user, whichever plugin
 * captured it.
 *
 * `billing_gst` is the exception — it has no Woo-core equivalent, so it is
 * ours. It is the general tax-registration field (GSTIN in India, VAT ID in the
 * EU), not one key per jurisdiction.
 *
 * @since 1.2.3
 *
 * @return array<string, array{label:string, required:bool, type:string, autocomplete:string}>
 */
function wpss_get_billing_fields(): array {
	$fields = array(
		'billing_first_name' => array(
			'label'        => __( 'First name', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'given-name',
		),
		'billing_last_name'  => array(
			'label'        => __( 'Last name', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'family-name',
		),
		'billing_company'    => array(
			'label'        => __( 'Company', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'organization',
		),
		'billing_gst'        => array(
			// GSTIN / VAT / tax registration number. A B2B buyer needs this on
			// the invoice to claim input credit, so an invoice without it is
			// unusable to them.
			'label'        => __( 'GST / VAT number', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'off',
		),
		'billing_address_1'  => array(
			'label'        => __( 'Street address', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'address-line1',
		),
		'billing_address_2'  => array(
			'label'        => __( 'Apartment, suite, etc.', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'address-line2',
		),
		'billing_city'       => array(
			'label'        => __( 'Town / City', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'address-level2',
		),
		'billing_state'      => array(
			'label'        => __( 'State / County', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'address-level1',
		),
		'billing_postcode'   => array(
			'label'        => __( 'Postcode / ZIP', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'postal-code',
		),
		'billing_country'    => array(
			'label'        => __( 'Country', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'country',
			'autocomplete' => 'country',
		),
		'billing_email'      => array(
			'label'        => __( 'Email', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'email',
			'autocomplete' => 'email',
		),
		'billing_phone'      => array(
			'label'        => __( 'Phone', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'tel',
			'autocomplete' => 'tel',
		),
	);

	/**
	 * Filter the billing address fields.
	 *
	 * @since 1.2.3
	 *
	 * @param array $fields Field definitions keyed by meta key.
	 */
	return apply_filters( 'wpss_billing_fields', $fields );
}

/**
 * Country list for the billing country selector.
 *
 * ISO-3166 alpha-2 => display name. Defers to WooCommerce's list when Woo is
 * active so the two never disagree on a country name or code; otherwise falls
 * back to WordPress's own translated list, and finally to a minimal set.
 *
 * @since 1.2.3
 *
 * @return array<string, string>
 */
function wpss_get_countries(): array {
	static $countries = null;

	if ( null !== $countries ) {
		return $countries;
	}

	// Woo is a rail we already integrate with; reuse its list when present.
	if ( function_exists( 'WC' ) && WC() && isset( WC()->countries ) ) {
		$countries = WC()->countries->get_countries();
	}

	if ( empty( $countries ) ) {
		// Complete ISO 3166-1 alpha-2 list. A partial list silently blocks
		// checkout for every country left out, so this is the whole set.
		$countries = array(
			'AF' => __( 'Afghanistan', 'wp-sell-services' ),
			'AX' => __( 'Åland Islands', 'wp-sell-services' ),
			'AL' => __( 'Albania', 'wp-sell-services' ),
			'DZ' => __( 'Algeria', 'wp-sell-services' ),
			'AS' => __( 'American Samoa', 'wp-sell-services' ),
			'AD' => __( 'Andorra', 'wp-sell-services' ),
			'AO' => __( 'Angola', 'wp-sell-services' ),
			'AI' => __( 'Anguilla', 'wp-sell-services' ),
			'AQ' => __( 'Antarctica', 'wp-sell-services' ),
			'AG' => __( 'Antigua and Barbuda', 'wp-sell-services' ),
			'AR' => __( 'Argentina', 'wp-sell-services' ),
			'AM' => __( 'Armenia', 'wp-sell-services' ),
			'AW' => __( 'Aruba', 'wp-sell-services' ),
			'AU' => __( 'Australia', 'wp-sell-services' ),
			'AT' => __( 'Austria', 'wp-sell-services' ),
			'AZ' => __( 'Azerbaijan', 'wp-sell-services' ),
			'BS' => __( 'Bahamas', 'wp-sell-services' ),
			'BH' => __( 'Bahrain', 'wp-sell-services' ),
			'BD' => __( 'Bangladesh', 'wp-sell-services' ),
			'BB' => __( 'Barbados', 'wp-sell-services' ),
			'BY' => __( 'Belarus', 'wp-sell-services' ),
			'BE' => __( 'Belgium', 'wp-sell-services' ),
			'BZ' => __( 'Belize', 'wp-sell-services' ),
			'BJ' => __( 'Benin', 'wp-sell-services' ),
			'BM' => __( 'Bermuda', 'wp-sell-services' ),
			'BT' => __( 'Bhutan', 'wp-sell-services' ),
			'BO' => __( 'Bolivia', 'wp-sell-services' ),
			'BQ' => __( 'Bonaire, Sint Eustatius and Saba', 'wp-sell-services' ),
			'BA' => __( 'Bosnia and Herzegovina', 'wp-sell-services' ),
			'BW' => __( 'Botswana', 'wp-sell-services' ),
			'BV' => __( 'Bouvet Island', 'wp-sell-services' ),
			'BR' => __( 'Brazil', 'wp-sell-services' ),
			'IO' => __( 'British Indian Ocean Territory', 'wp-sell-services' ),
			'BN' => __( 'Brunei', 'wp-sell-services' ),
			'BG' => __( 'Bulgaria', 'wp-sell-services' ),
			'BF' => __( 'Burkina Faso', 'wp-sell-services' ),
			'BI' => __( 'Burundi', 'wp-sell-services' ),
			'CV' => __( 'Cabo Verde', 'wp-sell-services' ),
			'KH' => __( 'Cambodia', 'wp-sell-services' ),
			'CM' => __( 'Cameroon', 'wp-sell-services' ),
			'CA' => __( 'Canada', 'wp-sell-services' ),
			'KY' => __( 'Cayman Islands', 'wp-sell-services' ),
			'CF' => __( 'Central African Republic', 'wp-sell-services' ),
			'TD' => __( 'Chad', 'wp-sell-services' ),
			'CL' => __( 'Chile', 'wp-sell-services' ),
			'CN' => __( 'China', 'wp-sell-services' ),
			'CX' => __( 'Christmas Island', 'wp-sell-services' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'wp-sell-services' ),
			'CO' => __( 'Colombia', 'wp-sell-services' ),
			'KM' => __( 'Comoros', 'wp-sell-services' ),
			'CG' => __( 'Congo', 'wp-sell-services' ),
			'CD' => __( 'Congo (DRC)', 'wp-sell-services' ),
			'CK' => __( 'Cook Islands', 'wp-sell-services' ),
			'CR' => __( 'Costa Rica', 'wp-sell-services' ),
			'CI' => __( "Côte d'Ivoire", 'wp-sell-services' ),
			'HR' => __( 'Croatia', 'wp-sell-services' ),
			'CU' => __( 'Cuba', 'wp-sell-services' ),
			'CW' => __( 'Curaçao', 'wp-sell-services' ),
			'CY' => __( 'Cyprus', 'wp-sell-services' ),
			'CZ' => __( 'Czechia', 'wp-sell-services' ),
			'DK' => __( 'Denmark', 'wp-sell-services' ),
			'DJ' => __( 'Djibouti', 'wp-sell-services' ),
			'DM' => __( 'Dominica', 'wp-sell-services' ),
			'DO' => __( 'Dominican Republic', 'wp-sell-services' ),
			'EC' => __( 'Ecuador', 'wp-sell-services' ),
			'EG' => __( 'Egypt', 'wp-sell-services' ),
			'SV' => __( 'El Salvador', 'wp-sell-services' ),
			'GQ' => __( 'Equatorial Guinea', 'wp-sell-services' ),
			'ER' => __( 'Eritrea', 'wp-sell-services' ),
			'EE' => __( 'Estonia', 'wp-sell-services' ),
			'SZ' => __( 'Eswatini', 'wp-sell-services' ),
			'ET' => __( 'Ethiopia', 'wp-sell-services' ),
			'FK' => __( 'Falkland Islands', 'wp-sell-services' ),
			'FO' => __( 'Faroe Islands', 'wp-sell-services' ),
			'FJ' => __( 'Fiji', 'wp-sell-services' ),
			'FI' => __( 'Finland', 'wp-sell-services' ),
			'FR' => __( 'France', 'wp-sell-services' ),
			'GF' => __( 'French Guiana', 'wp-sell-services' ),
			'PF' => __( 'French Polynesia', 'wp-sell-services' ),
			'TF' => __( 'French Southern Territories', 'wp-sell-services' ),
			'GA' => __( 'Gabon', 'wp-sell-services' ),
			'GM' => __( 'Gambia', 'wp-sell-services' ),
			'GE' => __( 'Georgia', 'wp-sell-services' ),
			'DE' => __( 'Germany', 'wp-sell-services' ),
			'GH' => __( 'Ghana', 'wp-sell-services' ),
			'GI' => __( 'Gibraltar', 'wp-sell-services' ),
			'GR' => __( 'Greece', 'wp-sell-services' ),
			'GL' => __( 'Greenland', 'wp-sell-services' ),
			'GD' => __( 'Grenada', 'wp-sell-services' ),
			'GP' => __( 'Guadeloupe', 'wp-sell-services' ),
			'GU' => __( 'Guam', 'wp-sell-services' ),
			'GT' => __( 'Guatemala', 'wp-sell-services' ),
			'GG' => __( 'Guernsey', 'wp-sell-services' ),
			'GN' => __( 'Guinea', 'wp-sell-services' ),
			'GW' => __( 'Guinea-Bissau', 'wp-sell-services' ),
			'GY' => __( 'Guyana', 'wp-sell-services' ),
			'HT' => __( 'Haiti', 'wp-sell-services' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'wp-sell-services' ),
			'HN' => __( 'Honduras', 'wp-sell-services' ),
			'HK' => __( 'Hong Kong', 'wp-sell-services' ),
			'HU' => __( 'Hungary', 'wp-sell-services' ),
			'IS' => __( 'Iceland', 'wp-sell-services' ),
			'IN' => __( 'India', 'wp-sell-services' ),
			'ID' => __( 'Indonesia', 'wp-sell-services' ),
			'IR' => __( 'Iran', 'wp-sell-services' ),
			'IQ' => __( 'Iraq', 'wp-sell-services' ),
			'IE' => __( 'Ireland', 'wp-sell-services' ),
			'IM' => __( 'Isle of Man', 'wp-sell-services' ),
			'IL' => __( 'Israel', 'wp-sell-services' ),
			'IT' => __( 'Italy', 'wp-sell-services' ),
			'JM' => __( 'Jamaica', 'wp-sell-services' ),
			'JP' => __( 'Japan', 'wp-sell-services' ),
			'JE' => __( 'Jersey', 'wp-sell-services' ),
			'JO' => __( 'Jordan', 'wp-sell-services' ),
			'KZ' => __( 'Kazakhstan', 'wp-sell-services' ),
			'KE' => __( 'Kenya', 'wp-sell-services' ),
			'KI' => __( 'Kiribati', 'wp-sell-services' ),
			'KW' => __( 'Kuwait', 'wp-sell-services' ),
			'KG' => __( 'Kyrgyzstan', 'wp-sell-services' ),
			'LA' => __( 'Laos', 'wp-sell-services' ),
			'LV' => __( 'Latvia', 'wp-sell-services' ),
			'LB' => __( 'Lebanon', 'wp-sell-services' ),
			'LS' => __( 'Lesotho', 'wp-sell-services' ),
			'LR' => __( 'Liberia', 'wp-sell-services' ),
			'LY' => __( 'Libya', 'wp-sell-services' ),
			'LI' => __( 'Liechtenstein', 'wp-sell-services' ),
			'LT' => __( 'Lithuania', 'wp-sell-services' ),
			'LU' => __( 'Luxembourg', 'wp-sell-services' ),
			'MO' => __( 'Macao', 'wp-sell-services' ),
			'MG' => __( 'Madagascar', 'wp-sell-services' ),
			'MW' => __( 'Malawi', 'wp-sell-services' ),
			'MY' => __( 'Malaysia', 'wp-sell-services' ),
			'MV' => __( 'Maldives', 'wp-sell-services' ),
			'ML' => __( 'Mali', 'wp-sell-services' ),
			'MT' => __( 'Malta', 'wp-sell-services' ),
			'MH' => __( 'Marshall Islands', 'wp-sell-services' ),
			'MQ' => __( 'Martinique', 'wp-sell-services' ),
			'MR' => __( 'Mauritania', 'wp-sell-services' ),
			'MU' => __( 'Mauritius', 'wp-sell-services' ),
			'YT' => __( 'Mayotte', 'wp-sell-services' ),
			'MX' => __( 'Mexico', 'wp-sell-services' ),
			'FM' => __( 'Micronesia', 'wp-sell-services' ),
			'MD' => __( 'Moldova', 'wp-sell-services' ),
			'MC' => __( 'Monaco', 'wp-sell-services' ),
			'MN' => __( 'Mongolia', 'wp-sell-services' ),
			'ME' => __( 'Montenegro', 'wp-sell-services' ),
			'MS' => __( 'Montserrat', 'wp-sell-services' ),
			'MA' => __( 'Morocco', 'wp-sell-services' ),
			'MZ' => __( 'Mozambique', 'wp-sell-services' ),
			'MM' => __( 'Myanmar', 'wp-sell-services' ),
			'NA' => __( 'Namibia', 'wp-sell-services' ),
			'NR' => __( 'Nauru', 'wp-sell-services' ),
			'NP' => __( 'Nepal', 'wp-sell-services' ),
			'NL' => __( 'Netherlands', 'wp-sell-services' ),
			'NC' => __( 'New Caledonia', 'wp-sell-services' ),
			'NZ' => __( 'New Zealand', 'wp-sell-services' ),
			'NI' => __( 'Nicaragua', 'wp-sell-services' ),
			'NE' => __( 'Niger', 'wp-sell-services' ),
			'NG' => __( 'Nigeria', 'wp-sell-services' ),
			'NU' => __( 'Niue', 'wp-sell-services' ),
			'NF' => __( 'Norfolk Island', 'wp-sell-services' ),
			'KP' => __( 'North Korea', 'wp-sell-services' ),
			'MK' => __( 'North Macedonia', 'wp-sell-services' ),
			'MP' => __( 'Northern Mariana Islands', 'wp-sell-services' ),
			'NO' => __( 'Norway', 'wp-sell-services' ),
			'OM' => __( 'Oman', 'wp-sell-services' ),
			'PK' => __( 'Pakistan', 'wp-sell-services' ),
			'PW' => __( 'Palau', 'wp-sell-services' ),
			'PS' => __( 'Palestine', 'wp-sell-services' ),
			'PA' => __( 'Panama', 'wp-sell-services' ),
			'PG' => __( 'Papua New Guinea', 'wp-sell-services' ),
			'PY' => __( 'Paraguay', 'wp-sell-services' ),
			'PE' => __( 'Peru', 'wp-sell-services' ),
			'PH' => __( 'Philippines', 'wp-sell-services' ),
			'PN' => __( 'Pitcairn', 'wp-sell-services' ),
			'PL' => __( 'Poland', 'wp-sell-services' ),
			'PT' => __( 'Portugal', 'wp-sell-services' ),
			'PR' => __( 'Puerto Rico', 'wp-sell-services' ),
			'QA' => __( 'Qatar', 'wp-sell-services' ),
			'RE' => __( 'Réunion', 'wp-sell-services' ),
			'RO' => __( 'Romania', 'wp-sell-services' ),
			'RU' => __( 'Russia', 'wp-sell-services' ),
			'RW' => __( 'Rwanda', 'wp-sell-services' ),
			'BL' => __( 'Saint Barthélemy', 'wp-sell-services' ),
			'SH' => __( 'Saint Helena', 'wp-sell-services' ),
			'KN' => __( 'Saint Kitts and Nevis', 'wp-sell-services' ),
			'LC' => __( 'Saint Lucia', 'wp-sell-services' ),
			'MF' => __( 'Saint Martin', 'wp-sell-services' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'wp-sell-services' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'wp-sell-services' ),
			'WS' => __( 'Samoa', 'wp-sell-services' ),
			'SM' => __( 'San Marino', 'wp-sell-services' ),
			'ST' => __( 'Sao Tome and Principe', 'wp-sell-services' ),
			'SA' => __( 'Saudi Arabia', 'wp-sell-services' ),
			'SN' => __( 'Senegal', 'wp-sell-services' ),
			'RS' => __( 'Serbia', 'wp-sell-services' ),
			'SC' => __( 'Seychelles', 'wp-sell-services' ),
			'SL' => __( 'Sierra Leone', 'wp-sell-services' ),
			'SG' => __( 'Singapore', 'wp-sell-services' ),
			'SX' => __( 'Sint Maarten', 'wp-sell-services' ),
			'SK' => __( 'Slovakia', 'wp-sell-services' ),
			'SI' => __( 'Slovenia', 'wp-sell-services' ),
			'SB' => __( 'Solomon Islands', 'wp-sell-services' ),
			'SO' => __( 'Somalia', 'wp-sell-services' ),
			'ZA' => __( 'South Africa', 'wp-sell-services' ),
			'GS' => __( 'South Georgia', 'wp-sell-services' ),
			'KR' => __( 'South Korea', 'wp-sell-services' ),
			'SS' => __( 'South Sudan', 'wp-sell-services' ),
			'ES' => __( 'Spain', 'wp-sell-services' ),
			'LK' => __( 'Sri Lanka', 'wp-sell-services' ),
			'SD' => __( 'Sudan', 'wp-sell-services' ),
			'SR' => __( 'Suriname', 'wp-sell-services' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'wp-sell-services' ),
			'SE' => __( 'Sweden', 'wp-sell-services' ),
			'CH' => __( 'Switzerland', 'wp-sell-services' ),
			'SY' => __( 'Syria', 'wp-sell-services' ),
			'TW' => __( 'Taiwan', 'wp-sell-services' ),
			'TJ' => __( 'Tajikistan', 'wp-sell-services' ),
			'TZ' => __( 'Tanzania', 'wp-sell-services' ),
			'TH' => __( 'Thailand', 'wp-sell-services' ),
			'TL' => __( 'Timor-Leste', 'wp-sell-services' ),
			'TG' => __( 'Togo', 'wp-sell-services' ),
			'TK' => __( 'Tokelau', 'wp-sell-services' ),
			'TO' => __( 'Tonga', 'wp-sell-services' ),
			'TT' => __( 'Trinidad and Tobago', 'wp-sell-services' ),
			'TN' => __( 'Tunisia', 'wp-sell-services' ),
			'TR' => __( 'Türkiye', 'wp-sell-services' ),
			'TM' => __( 'Turkmenistan', 'wp-sell-services' ),
			'TC' => __( 'Turks and Caicos Islands', 'wp-sell-services' ),
			'TV' => __( 'Tuvalu', 'wp-sell-services' ),
			'UG' => __( 'Uganda', 'wp-sell-services' ),
			'UA' => __( 'Ukraine', 'wp-sell-services' ),
			'AE' => __( 'United Arab Emirates', 'wp-sell-services' ),
			'GB' => __( 'United Kingdom', 'wp-sell-services' ),
			'US' => __( 'United States', 'wp-sell-services' ),
			'UM' => __( 'United States Minor Outlying Islands', 'wp-sell-services' ),
			'UY' => __( 'Uruguay', 'wp-sell-services' ),
			'UZ' => __( 'Uzbekistan', 'wp-sell-services' ),
			'VU' => __( 'Vanuatu', 'wp-sell-services' ),
			'VA' => __( 'Vatican City', 'wp-sell-services' ),
			'VE' => __( 'Venezuela', 'wp-sell-services' ),
			'VN' => __( 'Vietnam', 'wp-sell-services' ),
			'VG' => __( 'Virgin Islands (British)', 'wp-sell-services' ),
			'VI' => __( 'Virgin Islands (U.S.)', 'wp-sell-services' ),
			'WF' => __( 'Wallis and Futuna', 'wp-sell-services' ),
			'EH' => __( 'Western Sahara', 'wp-sell-services' ),
			'YE' => __( 'Yemen', 'wp-sell-services' ),
			'ZM' => __( 'Zambia', 'wp-sell-services' ),
			'ZW' => __( 'Zimbabwe', 'wp-sell-services' ),
		);
	}

	/**
	 * Filter the billing country list.
	 *
	 * @since 1.2.3
	 *
	 * @param array $countries ISO-2 code => country name.
	 */
	$countries = apply_filters( 'wpss_countries', $countries );

	return $countries;
}

/**
 * Resolve any stored country value to an ISO-3166 alpha-2 code.
 *
 * Country was a FREE-TEXT field on the vendor profile before 1.2.3, so stored
 * values are a mix of codes ("IN"), full names ("India") and whatever else was
 * typed. Switching that input to a select without this would render blank for
 * every existing vendor and silently drop their country on the next save.
 *
 * @since 1.2.3
 *
 * @param string $value Stored country value.
 * @return string ISO-2 code, or '' when it cannot be resolved.
 */
function wpss_resolve_country_code( string $value ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	$countries = wpss_get_countries();

	// Already a valid code.
	$upper = strtoupper( $value );
	if ( isset( $countries[ $upper ] ) ) {
		return $upper;
	}

	// Legacy free text — match on name, case-insensitively.
	foreach ( $countries as $code => $name ) {
		if ( 0 === strcasecmp( $name, $value ) ) {
			return $code;
		}
	}

	return '';
}

/**
 * Display name for a stored country value.
 *
 * Read-side counterpart of {@see wpss_resolve_country_code()}. Every surface
 * that SHOWS a country goes through this, so the vendor card, the public
 * profile and the admin screen can never disagree. Falls back to the raw value
 * when it cannot be resolved, so nothing a vendor typed simply vanishes.
 *
 * @since 1.2.3
 *
 * @param string $value Stored country value (code or legacy free text).
 * @return string Display name.
 */
function wpss_get_country_name( string $value ): string {
	$code = wpss_resolve_country_code( $value );

	if ( '' === $code ) {
		return trim( $value );
	}

	$countries = wpss_get_countries();

	return $countries[ $code ] ?? trim( $value );
}

/**
 * Read a user's saved billing address.
 *
 * Reads the WooCommerce-compatible user meta, so on a Woo site this returns the
 * address the buyer already gave WooCommerce — no re-entry, no migration.
 *
 * @since 1.2.3
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @return array<string, string> Field key => value. Missing fields are ''.
 */
function wpss_get_billing_address( int $user_id = 0 ): array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return array();
	}

	$address = array();

	foreach ( array_keys( wpss_get_billing_fields() ) as $key ) {
		$value = get_user_meta( $user_id, $key, true );

		// Fall back to the account email so a first-time buyer does not retype
		// something we already know.
		if ( '' === $value && 'billing_email' === $key ) {
			$user  = get_userdata( $user_id );
			$value = $user ? $user->user_email : '';
		}

		$address[ $key ] = is_string( $value ) ? $value : '';
	}

	/**
	 * Filter a user's billing address after it is read.
	 *
	 * @since 1.2.3
	 *
	 * @param array $address Field key => value.
	 * @param int   $user_id User ID.
	 */
	return apply_filters( 'wpss_billing_address', $address, $user_id );
}

/**
 * Save a user's billing address to their profile.
 *
 * Writes the same WooCommerce keys it reads, so the address stays shared with
 * WooCommerce rather than forking into a WPSS-only copy that drifts.
 *
 * @since 1.2.3
 *
 * @param int                   $user_id User ID.
 * @param array<string, string> $address Raw field values.
 * @return bool True when something was written.
 */
function wpss_save_billing_address( int $user_id, array $address ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	$fields  = wpss_get_billing_fields();
	$written = false;

	foreach ( $fields as $key => $definition ) {
		if ( ! array_key_exists( $key, $address ) ) {
			continue;
		}

		$value = $address[ $key ];

		switch ( $definition['type'] ) {
			case 'email':
				$value = sanitize_email( (string) $value );
				break;
			case 'country':
				// ISO-3166 alpha-2, upper-cased.
				$value = strtoupper( substr( sanitize_text_field( (string) $value ), 0, 2 ) );
				break;
			default:
				$value = sanitize_text_field( (string) $value );
		}

		update_user_meta( $user_id, $key, $value );
		$written = true;
	}

	if ( $written ) {
		/**
		 * Fires after a user's billing address is saved.
		 *
		 * @since 1.2.3
		 *
		 * @param int   $user_id User ID.
		 * @param array $address Sanitized values that were written.
		 */
		do_action( 'wpss_billing_address_saved', $user_id, $address );
	}

	return $written;
}

/**
 * Save billing fields posted with a checkout submission to the buyer's profile.
 *
 * Gateway-agnostic on purpose: any checkout completion handler — Stripe,
 * PayPal, Razorpay, offline — calls this with its own request payload, so an
 * address the buyer corrected at checkout is remembered for next time no matter
 * how they paid.
 *
 * MUST run BEFORE the order is marked paid. mark_as_paid() snapshots the
 * address from the profile, so saving afterwards would stamp the order with the
 * OLD address and silently discard the correction the buyer just made.
 *
 * Only writes keys actually present in the request, so a gateway that posts a
 * partial payload cannot blank the rest of the profile.
 *
 * @since 1.2.3
 *
 * @param array<string, mixed> $request Raw request data ($_POST or a REST payload).
 * @param int                  $user_id Optional. Defaults to the current user.
 * @return bool True when something was written.
 */
function wpss_save_billing_from_request( array $request, int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return false;
	}

	$posted = array();

	foreach ( array_keys( wpss_get_billing_fields() ) as $key ) {
		if ( isset( $request[ $key ] ) ) {
			$posted[ $key ] = wp_unslash( $request[ $key ] );
		}
	}

	if ( empty( $posted ) ) {
		return false;
	}

	// wpss_save_billing_address() sanitises per field type.
	return wpss_save_billing_address( $user_id, $posted );
}

/**
 * Whether a user's billing address has everything required.
 *
 * Drives the checkout decision: complete means the address block collapses and
 * the buyer only has to enter card details.
 *
 * @since 1.2.3
 *
 * @param int|array<string, mixed> $user_or_address User ID, or an address array to test directly.
 * @return bool
 */
function wpss_is_billing_address_complete( $user_or_address = 0 ): bool {
	$address = is_array( $user_or_address )
		? $user_or_address
		: wpss_get_billing_address( (int) $user_or_address );

	if ( empty( $address ) ) {
		return false;
	}

	foreach ( wpss_get_billing_fields() as $key => $definition ) {
		if ( ! empty( $definition['required'] ) && empty( $address[ $key ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Get a vendor's account status.
 *
 * Shared accessor for every vendor-status read. Resolves from the canonical
 * wpss_vendor_profiles.status column — the legacy _wpss_vendor_status user
 * meta key was READ in four places and written in none, so every caller fell
 * through to its own hardcoded default. The REST API reported every vendor as
 * approved regardless of their real status, and the "pending vendors cannot
 * access earnings" gate in EarningsController never fired.
 *
 * @since 1.2.3
 *
 * @param int $user_id Vendor user ID.
 * @return string One of 'active', 'pending', 'suspended', or '' when the
 *                user has no vendor profile row at all.
 */
function wpss_get_vendor_status( int $user_id ): string {
	$vendor = wpss_get_vendor( $user_id );

	return $vendor instanceof \WPSellServices\Models\VendorProfile ? $vendor->status : '';
}

/**
 * Get the datetime of a vendor's most recent completed delivery.
 *
 * Shared accessor for every "Last Delivery" display (single-service page,
 * vendor card partial). Resolves from the orders table
 * (MAX(completed_at), tip sub-orders excluded) — the legacy
 * _wpss_last_delivery user-meta key was never written.
 *
 * @since 1.2.0
 *
 * @param int $vendor_id Vendor user ID.
 * @return string|null MySQL datetime, or null when nothing was delivered yet.
 */
function wpss_get_vendor_last_delivery( int $vendor_id ): ?string {
	return ( new \WPSellServices\Services\VendorService() )->get_last_delivery_date( $vendor_id );
}

/**
 * Check if user is a vendor.
 *
 * Checks the wpss_vendor capability first, then falls back to checking
 * the user's role and vendor meta for backward compatibility with users
 * registered before the wpss_vendor capability was added to the role.
 *
 * @param int|null $user_id User ID. Defaults to current user.
 * @return bool
 */
function wpss_is_vendor( ?int $user_id = null ): bool {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	// Primary check: wpss_vendor capability.
	$is_vendor = user_can( $user_id, 'wpss_vendor' );

	// Fallback: check if user has the wpss_vendor role directly.
	if ( ! $is_vendor ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'wpss_vendor', (array) $user->roles, true ) ) {
			$is_vendor = true;
		}
	}

	// Fallback: check vendor meta for legacy vendors.
	if ( ! $is_vendor ) {
		$is_vendor = (bool) get_user_meta( $user_id, '_wpss_is_vendor', true );
	}

	/**
	 * Filter whether user is a vendor.
	 *
	 * @param bool $is_vendor Whether user is a vendor.
	 * @param int  $user_id   User ID.
	 */
	return apply_filters( 'wpss_is_vendor', $is_vendor, $user_id );
}

/**
 * Get active e-commerce adapter.
 *
 * @return \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null
 */
function wpss_get_active_adapter(): ?\WPSellServices\Integrations\Contracts\EcommerceAdapterInterface {
	return wpss()->get_integration_manager()->get_active_adapter();
}

/**
 * Sanitize HTML content.
 *
 * @param string $content HTML content.
 * @return string
 */
function wpss_sanitize_html( string $content ): string {
	return wp_kses(
		$content,
		array(
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'         => array(),
			'em'         => array(),
			'strong'     => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
		)
	);
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
 * Generate unique dispute number.
 *
 * @return string
 */
function wpss_generate_dispute_number(): string {
	$prefix = apply_filters( 'wpss_dispute_number_prefix', 'DSP-' );
	$number = wp_rand( 10000, 99999 );

	return $prefix . $number . '-' . time();
}

/**
 * Log message for debugging.
 *
 * @param mixed  $message Message to log.
 * @param string $level   Log level (info, warning, error).
 * @return void
 */
function wpss_log( $message, string $level = 'info' ): void {
	$advanced_settings = get_option( 'wpss_advanced', array() );
	$plugin_debug      = ! empty( $advanced_settings['enable_debug_mode'] );

	if ( ! $plugin_debug && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
		return;
	}

	if ( ! is_string( $message ) ) {
		$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	$log_message = sprintf(
		'[%s] [WPSS %s] %s',
		wp_date( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$message
	);

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $log_message );
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
 * Calculate time difference in human readable format.
 *
 * Both sides of the comparison must be real UTC timestamps. The stored
 * datetime is UTC, so it is parsed as UTC; "now" is time(), not
 * current_time( 'timestamp' ). current_time() returns UTC shifted by the
 * site's offset — a fake timestamp — so comparing the two added the offset to
 * every result: on a UTC+5:30 site an order placed 47 minutes ago rendered as
 * "6 hours ago". Correct on a UTC site, wrong everywhere else.
 *
 * @param string $datetime MySQL datetime string, in UTC.
 * @return string
 */
function wpss_time_ago( string $datetime ): string {
	$timestamp = strtotime( $datetime . ' UTC' );

	if ( ! $timestamp ) {
		return '';
	}

	return human_time_diff( $timestamp, time() ) . ' ' . __( 'ago', 'wp-sell-services' );
}

/**
 * Resolve a reviewer's display name for templates.
 *
 * Thin template-facing wrapper over Review::resolve_reviewer_name() so raw
 * review rows in templates (which expose reviewer_name / customer_id) render
 * migrated guest authors instead of "Anonymous". Precedence: registered user
 * display_name -> stored guest name -> "Anonymous".
 *
 * @param int         $reviewer_id   Reviewer user ID (0 for guest/legacy).
 * @param string|null $reviewer_name Stored guest author name, if any.
 * @return string
 */
function wpss_get_reviewer_name( int $reviewer_id, ?string $reviewer_name = null ): string {
	return \WPSellServices\Models\Review::resolve_reviewer_name( $reviewer_id, $reviewer_name );
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
 * Get service URL.
 *
 * @param int $service_id Service ID.
 * @return string
 */
function wpss_get_service_url( int $service_id ): string {
	return get_permalink( $service_id ) ?: '';
}

/**
 * Resolve the vendor-directory page ID.
 *
 * `wpss_pages['vendors_page']` used to be read in three places and written in
 * none: the installer never seeds a vendors page, no settings field offered
 * one, and the legacy `wpss_vendors_page` option was equally write-only. The
 * key was therefore permanently 0 on every install, which made
 * `GET /settings` report `pages.vendors = 0` and `page_urls.vendors = null`
 * even on sites that plainly HAVE a directory page.
 *
 * This is the single resolver for that page, in the order a site actually
 * carries the answer:
 *
 * 1. the mapped page (Settings -> Pages, which now offers the field),
 * 2. the legacy standalone option, for sites mapped before the page map,
 * 3. auto-discovery of a published page carrying `[wpss_vendors]` — which is
 *    what a site owner builds when they want a directory — persisted back into
 *    the page map so every reader (and the admin UI) agrees from then on.
 *
 * Returns 0 only when the site genuinely has no vendor directory; callers must
 * treat that as "no such page" rather than as an error.
 *
 * @since 1.6.1
 *
 * @return int Page ID, or 0 when the site has no vendor directory page.
 */
function wpss_get_vendors_page_id(): int {
	static $resolved = null;

	if ( null !== $resolved ) {
		return $resolved;
	}

	$pages   = get_option( 'wpss_pages', array() );
	$pages   = is_array( $pages ) ? $pages : array();
	$page_id = (int) ( $pages['vendors_page'] ?? 0 );

	// Legacy standalone option, for sites mapped before the page map existed.
	if ( ! $page_id ) {
		$page_id = (int) get_option( 'wpss_vendors_page' );
	}

	// A mapped page that has since been deleted or trashed is not an answer.
	if ( $page_id ) {
		$post = get_post( $page_id );

		if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			$page_id = 0;
		}
	}

	if ( ! $page_id ) {
		$page_id = wpss_discover_vendors_page_id();

		// Persist the discovery so the key stops being read-never-written and
		// the admin Pages panel shows the page the site is really using.
		if ( $page_id ) {
			$pages['vendors_page'] = $page_id;
			update_option( 'wpss_pages', $pages );
		}
	}

	/**
	 * Filter the resolved vendor-directory page ID.
	 *
	 * @since 1.6.1
	 *
	 * @param int $page_id Resolved page ID, or 0 when the site has none.
	 */
	$resolved = (int) apply_filters( 'wpss_vendors_page_id', $page_id );

	return $resolved;
}

/**
 * Find a published page carrying the `[wpss_vendors]` directory shortcode.
 *
 * Cached in a transient (including the "nothing found" answer) so the lookup
 * costs one query per half day rather than one per request on sites that have
 * no directory page at all.
 *
 * @since 1.6.1
 *
 * @return int Page ID, or 0 when no such page exists.
 */
function wpss_discover_vendors_page_id(): int {
	$cached = get_transient( 'wpss_vendors_page_lookup' );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$found = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			's'                      => '[wpss_vendors',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$page_id = ! empty( $found ) ? (int) $found[0] : 0;

	set_transient( 'wpss_vendors_page_lookup', $page_id, 12 * HOUR_IN_SECONDS );

	return $page_id;
}

/**
 * Get the vendor-directory (archive) URL.
 *
 * The counterpart to wpss_get_vendor_url(): that one addresses ONE vendor,
 * this one addresses the list. Returns an empty string when the site has no
 * directory, which callers should surface as "no such page" rather than
 * linking to a URL that 404s — there is no `/{vendor-slug}/` archive route,
 * only `/{vendor-slug}/{nicename}/` for a single profile, so guessing an
 * archive URL from the slug would hand clients a dead link.
 *
 * @since 1.6.1
 *
 * @return string Directory URL, or an empty string when the site has none.
 */
function wpss_get_vendors_url(): string {
	$page_id = wpss_get_vendors_page_id();
	$url     = $page_id ? (string) get_permalink( $page_id ) : '';

	/**
	 * Filter the vendor-directory URL.
	 *
	 * Themes and integrations that render a directory somewhere other than a
	 * mapped page (a CPT archive, a headless route) answer here.
	 *
	 * @since 1.6.1
	 *
	 * @param string $url     Resolved directory URL, or an empty string.
	 * @param int    $page_id Resolved directory page ID, or 0.
	 */
	return (string) apply_filters( 'wpss_vendors_url', $url, $page_id );
}

/**
 * Get vendor profile URL.
 *
 * @param int $user_id Vendor user ID.
 * @return string
 */
function wpss_get_vendor_url( int $user_id ): string {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return '';
	}

	// One resolver for the directory page — see wpss_get_vendors_page_id().
	$vendors_page = wpss_get_vendors_page_id();

	if ( $vendors_page ) {
		return add_query_arg( 'vendor', $user->user_nicename, get_permalink( $vendors_page ) );
	}

	$vendor_slug = apply_filters( 'wpss_vendor_slug', 'provider' );
	return home_url( '/' . $vendor_slug . '/' . $user->user_nicename );
}

/**
 * Resolve the page ID that the ACTIVE e-commerce rail's cart/checkout URL
 * actually lands on.
 *
 * `wpss_pages['cart']` / `['checkout']` hold the STANDALONE pages, which stay
 * mapped even after a site switches to WooCommerce or EDD. Reporting those IDs
 * beside a URL resolved through wpss_get_cart_url() / wpss_get_checkout_base_url()
 * names two different screens: a client that deep-links by ID lands on the
 * dormant standalone page while the URL it was given points at WooCommerce.
 * Deriving the ID FROM the resolved URL keeps the two answers describing the
 * same page by construction.
 *
 * @since 1.6.1
 *
 * @param string $key Either `cart` or `checkout`.
 * @return int Page ID, or 0 when the rail's URL is not a WP page.
 */
function wpss_get_active_store_page_id( string $key ): int {
	static $cache = array();

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$url = 'cart' === $key ? wpss_get_cart_url() : wpss_get_checkout_base_url();

	$page_id = '' !== $url ? (int) url_to_postid( $url ) : 0;

	// A rail whose URL is not a WP page (or an unresolvable permalink) still
	// has the mapped standalone page as the best available answer.
	if ( ! $page_id ) {
		$page_id = wpss_get_page_id( $key );
	}

	$cache[ $key ] = $page_id;

	return $page_id;
}

/**
 * Get dashboard URL.
 *
 * @param string $section Optional dashboard section slug.
 * @return string
 */
function wpss_get_dashboard_url( string $section = '' ): string {
	// First check wpss_pages option (newer, preferred).
	$pages          = get_option( 'wpss_pages', array() );
	$dashboard_page = (int) ( $pages['dashboard'] ?? 0 );

	// Fallback to legacy option for backward compatibility.
	if ( ! $dashboard_page ) {
		$dashboard_page = (int) get_option( 'wpss_dashboard_page' );
	}

	if ( ! $dashboard_page ) {
		return '';
	}

	$url = get_permalink( $dashboard_page );

	if ( ! $url ) {
		return '';
	}

	if ( '' !== $section ) {
		$url = wpss_append_dashboard_section( $url, $section );
	}

	return $url;
}

/**
 * Append a dashboard section to a base dashboard URL.
 *
 * Emits a pretty endpoint path (e.g. /dashboard/services/) when permalinks
 * are pretty, falling back to the `?section=` query arg on plain permalinks.
 * Centralizing this keeps every internal link, breadcrumb, and redirect on
 * the same URL shape, and means a single behavior change toggles them all.
 *
 * The default `orders` section maps to the bare dashboard URL (no segment)
 * to keep the canonical dashboard landing URL clean.
 *
 * @since 1.2.0
 *
 * @param string $base_url Base dashboard page permalink (may already carry query args).
 * @param string $section  Section slug (e.g. 'services', 'earnings').
 * @return string Section URL.
 */
function wpss_append_dashboard_section( string $base_url, string $section ): string {
	$section = sanitize_key( $section );

	if ( '' === $section || 'orders' === $section ) {
		return $base_url;
	}

	// Plain permalinks: keep the query-arg form.
	if ( ! get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'section', $section, $base_url );
	}

	// Split off any query string / fragment so the endpoint segment is
	// inserted into the path, not appended after the query args.
	$query    = '';
	$fragment = '';

	$hash_pos = strpos( $base_url, '#' );
	if ( false !== $hash_pos ) {
		$fragment = substr( $base_url, $hash_pos );
		$base_url = substr( $base_url, 0, $hash_pos );
	}

	$query_pos = strpos( $base_url, '?' );
	if ( false !== $query_pos ) {
		$query    = substr( $base_url, $query_pos );
		$base_url = substr( $base_url, 0, $query_pos );
	}

	$path = trailingslashit( $base_url ) . $section . '/';

	return $path . $query . $fragment;
}

/**
 * Every dashboard section slug this product knows how to address.
 *
 * "Known" is not the same as "renderable here": `analytics` is a real address
 * whose template ships in Pro, so a Free-only site must still recognise the
 * slug and explain the gap rather than treat the URL as junk. Renderability is
 * answered separately by wpss_get_dashboard_section_template().
 *
 * @since 1.6.1
 *
 * @return array<int, string> Section slugs.
 */
function wpss_get_known_dashboard_sections(): array {
	$sections = array(
		// Buying.
		'orders',
		'favorites',
		'requests',
		// Selling.
		'services',
		'sales',
		'earnings',
		'wallet',
		'portfolio',
		// Account.
		'messages',
		'notifications',
		'disputes',
		'profile',
		// Actions.
		'create',
		'create-request',
		'edit-request',
		'become-vendor',
		// Known Pro addresses. Listed here so a Free-only site answers
		// "this needs Pro" instead of bouncing the URL as unrecognised.
		'analytics',
		'subscription',
		'subscriptions',
	);

	/**
	 * Filter the set of known dashboard section slugs.
	 *
	 * Anything not in this set is treated as a mistyped URL and redirected to
	 * the dashboard's default landing section, so add-ons that register their
	 * own section must add its slug here as well as to `wpss_dashboard_sections`.
	 *
	 * @since 1.6.1
	 *
	 * @param array<int, string> $sections Known section slugs.
	 */
	$sections = (array) apply_filters( 'wpss_known_dashboard_sections', $sections );

	return array_values( array_unique( array_map( 'sanitize_key', $sections ) ) );
}

/**
 * Label-derived guesses that resolve to a real dashboard section.
 *
 * The nav item for `orders` is LABELLED "My Orders", so `?section=my-orders`
 * is the URL people type — and it used to render a dead "Section Not Available"
 * card, because nothing in the product has ever emitted that slug. The same
 * applies to "My Services" and "Sales Orders". Mapping the plausible guesses is
 * cheaper for everyone than teaching every tester the canonical slug.
 *
 * @since 1.6.1
 *
 * @return array<string, string> Alias slug => canonical slug.
 */
function wpss_get_dashboard_section_aliases(): array {
	$aliases = array(
		'my-orders'      => 'orders',
		'my-order'       => 'orders',
		'order'          => 'orders',
		'my-sales'       => 'sales',
		'sales-orders'   => 'sales',
		'vendor-orders'  => 'sales',
		'my-services'    => 'services',
		'service'        => 'services',
		'my-favorites'   => 'favorites',
		'my-portfolio'   => 'portfolio',
		'my-earnings'    => 'earnings',
		'buyer-requests' => 'requests',
		'my-profile'     => 'profile',
		'become_vendor'  => 'become-vendor',
		// Slugs the product itself has emitted into emails and gateway return
		// URLs but never had a template for — every one of them landed on the
		// same dead card. The links are fixed at source too; these entries keep
		// the mail already sitting in people's inboxes working.
		'edit-service'   => 'create',
		'stripe-connect' => 'earnings',
	);

	/**
	 * Filter the dashboard section alias map.
	 *
	 * @since 1.6.1
	 *
	 * @param array<string, string> $aliases Alias slug => canonical slug.
	 */
	return (array) apply_filters( 'wpss_dashboard_section_aliases', $aliases );
}

/**
 * Resolve a requested section slug to the canonical slug it addresses.
 *
 * Returns an empty string when the slug names nothing this product has — the
 * caller's cue to send the visitor to the default landing section instead of
 * rendering (or worse, 301-canonicalising) a dead end.
 *
 * @since 1.6.1
 *
 * @param string $section Requested section slug.
 * @return string Canonical section slug, or an empty string when unknown.
 */
function wpss_normalize_dashboard_section( string $section ): string {
	$section = sanitize_key( $section );

	if ( '' === $section ) {
		return '';
	}

	$aliases = wpss_get_dashboard_section_aliases();

	if ( isset( $aliases[ $section ] ) ) {
		$section = sanitize_key( (string) $aliases[ $section ] );
	}

	return in_array( $section, wpss_get_known_dashboard_sections(), true ) ? $section : '';
}

/**
 * Resolve the template file that renders a dashboard section.
 *
 * Runs the same `wpss_dashboard_section_template` filter the dashboard renderer
 * uses, so Pro-supplied templates and third-party overrides are accounted for.
 * An empty string means "known address, nothing here can render it" — which on
 * a Free-only site is exactly the Pro-only case.
 *
 * @since 1.6.1
 *
 * @param string $section Canonical section slug.
 * @return string Absolute template path, or an empty string when none exists.
 */
function wpss_get_dashboard_section_template( string $section ): string {
	$section = sanitize_key( $section );

	if ( '' === $section ) {
		return '';
	}

	// `wallet` and `earnings` are one screen; earnings.php renders both.
	$template_section = ( 'wallet' === $section ) ? 'earnings' : $section;
	$template_path    = WPSS_PLUGIN_DIR . "templates/dashboard/sections/{$template_section}.php";

	/** This filter is documented in src/Frontend/UnifiedDashboard.php */
	$template_path = (string) apply_filters( 'wpss_dashboard_section_template', $template_path, $section );

	return ( '' !== $template_path && file_exists( $template_path ) ) ? $template_path : '';
}

/**
 * Get order view URL.
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

	$dashboard_url = wpss_get_dashboard_url( $section );

	if ( $dashboard_url ) {
		return add_query_arg( 'order_id', $order_id, $dashboard_url );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order->order_number . '/' );
}

/**
 * Get order requirements URL.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function wpss_get_order_requirements_url( int $order_id ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	// Orders is the default section, so no section parameter needed.
	$dashboard_url = wpss_get_dashboard_url();

	if ( $dashboard_url ) {
		return add_query_arg(
			array(
				'order_id' => $order_id,
				'action'   => 'requirements',
			),
			$dashboard_url
		);
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order->order_number . '/requirements/' );
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

	$table = $wpdb->prefix . 'wpss_order_requirements';

	// Check if table exists.
	$table_exists = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
	);

	if ( ! $table_exists ) {
		// Fall back to order meta.
		$requirements = get_metadata( 'wpss_order', $order_id, '_requirements', true );
		return is_array( $requirements ) ? $requirements : array();
	}

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
 * Get max upload size in bytes.
 *
 * @return int
 */
function wpss_get_max_upload_size(): int {
	$upload_max = wp_max_upload_size();

	/**
	 * Filter the max upload size for requirements files.
	 *
	 * @param int $max_size Max size in bytes.
	 */
	return (int) apply_filters( 'wpss_max_upload_size', $upload_max );
}

/**
 * Get service packages.
 *
 * @param int $service_id Service ID.
 * @return array
 */
function wpss_get_service_packages( int $service_id ): array {
	$packages = get_post_meta( $service_id, '_wpss_packages', true );
	return is_array( $packages ) ? $packages : array();
}

/**
 * Normalize gallery meta into a flat array of attachment IDs.
 *
 * Handles all gallery storage formats:
 * - ServiceWizard format: ['images' => [id, ...], 'video' => '...']
 * - Legacy flat array: [id, id, ...]
 * - GalleryService format: [['type' => 'image', 'attachment_id' => id], ...]
 *
 * @since 1.1.0
 *
 * @param mixed $raw Raw gallery meta value (from get_post_meta).
 * @return int[] Flat array of attachment IDs.
 */
function wpss_get_gallery_ids( $raw ): array {
	if ( ! is_array( $raw ) || empty( $raw ) ) {
		return array();
	}

	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	if ( isset( $raw['images'] ) && is_array( $raw['images'] ) ) {
		return array_values( array_filter( array_map( 'absint', $raw['images'] ) ) );
	}

	// GalleryService format: [['type' => 'image', 'attachment_id' => 123], ...].
	if ( isset( $raw[0] ) && is_array( $raw[0] ) && isset( $raw[0]['type'] ) ) {
		$ids = array();
		foreach ( $raw as $item ) {
			if ( 'image' === ( $item['type'] ?? '' ) && ! empty( $item['attachment_id'] ) ) {
				$ids[] = absint( $item['attachment_id'] );
			}
		}
		return $ids;
	}

	// Legacy flat array of IDs: [123, 456, ...].
	return array_values( array_filter( array_map( 'absint', $raw ) ) );
}

/**
 * Get the video URL from gallery meta.
 *
 * @since 1.1.0
 *
 * @param mixed $raw Raw gallery meta value (from get_post_meta).
 * @return string Video URL or empty string.
 */
function wpss_get_gallery_video_url( $raw ): string {
	if ( ! is_array( $raw ) ) {
		return '';
	}

	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	if ( isset( $raw['video'] ) && is_string( $raw['video'] ) ) {
		return $raw['video'];
	}

	// GalleryService format: [['type' => 'video', 'url' => '...'], ...].
	if ( isset( $raw[0] ) && is_array( $raw[0] ) ) {
		foreach ( $raw as $item ) {
			if ( 'video' === ( $item['type'] ?? '' ) && ! empty( $item['url'] ) ) {
				return $item['url'];
			}
		}
	}

	return '';
}

/**
 * Get order confirmation URL (thank you page).
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
		return add_query_arg( 'order_id', $order_id, get_permalink( $confirmation_page ) );
	}

	// Fall back to dashboard order view.
	$dashboard_url = wpss_get_dashboard_url();
	if ( $dashboard_url ) {
		return add_query_arg( 'order_id', $order_id, $dashboard_url );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order->order_number . '/confirmation/' );
}

/**
 * Check if late requirements submission is allowed.
 *
 * @since 1.0.0
 *
 * @return bool Whether late requirements submission is enabled.
 */
function wpss_allow_late_requirements_submission(): bool {
	$order_settings = get_option( 'wpss_orders', array() );
	$allow_late     = ! empty( $order_settings['allow_late_requirements'] );

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
		'order_created'       => __( 'New Order', 'wp-sell-services' ),
		'order_status'        => __( 'Order Update', 'wp-sell-services' ),
		'new_message'         => __( 'New Message', 'wp-sell-services' ),
		'delivery_submitted'  => __( 'Delivery Submitted', 'wp-sell-services' ),
		'delivery_accepted'   => __( 'Delivery Accepted', 'wp-sell-services' ),
		'revision_requested'  => __( 'Revision Requested', 'wp-sell-services' ),
		'review_received'     => __( 'New Review', 'wp-sell-services' ),
		'dispute_opened'      => __( 'Dispute Opened', 'wp-sell-services' ),
		'dispute_resolved'    => __( 'Dispute Resolved', 'wp-sell-services' ),
		'deadline_warning'    => __( 'Deadline Warning', 'wp-sell-services' ),
		'service_approved'    => __( 'Service Approved', 'wp-sell-services' ),
		'service_rejected'    => __( 'Service Requires Changes', 'wp-sell-services' ),
		'withdrawal_pending'  => __( 'Withdrawal Request', 'wp-sell-services' ),
		'withdrawal_approved' => __( 'Withdrawal Approved', 'wp-sell-services' ),
		'withdrawal_rejected' => __( 'Withdrawal Rejected', 'wp-sell-services' ),
	);

	$title = $type_titles[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );

	return $notification_service->create( $user_id, $type, $title, $message, $data );
}

/**
 * Get default page slugs for standalone mode.
 *
 * These are used as fallbacks when no page is mapped in Settings → Pages.
 * Site owners can override by mapping WP pages in settings.
 *
 * @since 1.2.0
 *
 * @return array<string, string> Map of page_key => default slug.
 */
function wpss_get_default_page_slugs(): array {
	/**
	 * Filter default page slugs.
	 *
	 * Allows changing the default URL slugs for all WPSS pages.
	 * These only apply when no WP page is mapped in Settings → Pages.
	 *
	 * @since 1.2.0
	 *
	 * @param array $slugs Default slugs keyed by page key.
	 */
	return apply_filters(
		'wpss_default_page_slugs',
		array(
			'services_page'  => 'services',
			'dashboard'      => 'dashboard',
			'become_vendor'  => 'become-vendor',
			'create_service' => 'create-service',
			'checkout'       => 'service-checkout',
			'cart'           => 'service-cart',
		)
	);
}

/**
 * Get page URL by settings key.
 *
 * Checks mapped WP page first (Settings → Pages), then falls back
 * to the default slug. This ensures URLs work for translated or
 * custom-slug sites without hardcoded paths.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard', 'checkout').
 * @return string Page URL or empty string.
 */
function wpss_get_page_url( string $page_key ): string {
	$pages   = get_option( 'wpss_pages', array() );
	$page_id = (int) ( $pages[ $page_key ] ?? 0 );

	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	// Fallback to default slug.
	$defaults = wpss_get_default_page_slugs();
	if ( isset( $defaults[ $page_key ] ) ) {
		return home_url( '/' . $defaults[ $page_key ] . '/' );
	}

	return '';
}

/**
 * Get the mapped page ID for a given page key.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard').
 * @return int Page ID or 0.
 */
function wpss_get_page_id( string $page_key ): int {
	$pages = get_option( 'wpss_pages', array() );
	return (int) ( $pages[ $page_key ] ?? 0 );
}

/**
 * Check if the current page is a specific mapped page.
 *
 * Uses the global $post to check the page ID before any query modification,
 * making it safe to use in pre_get_posts and template_include.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard').
 * @return bool
 */
function wpss_is_page( string $page_key ): bool {
	global $post;

	$page_id = wpss_get_page_id( $page_key );

	if ( ! $page_id ) {
		return false;
	}

	// Check global $post first (available before query modification).
	if ( $post instanceof \WP_Post && (int) $post->ID === $page_id ) {
		return true;
	}

	// Fallback: check queried object.
	$queried = get_queried_object_id();
	if ( $queried && $queried === $page_id ) {
		return true;
	}

	return false;
}

/**
 * Get the Create Service URL.
 *
 * Returns the URL to the Dashboard create section where vendors can create new services.
 *
 * @since 1.1.0
 *
 * @return string Create service URL (dashboard with create section).
 */
function wpss_get_create_service_url(): string {
	$dashboard_url = wpss_get_page_url( 'dashboard' );
	if ( ! $dashboard_url ) {
		return '';
	}
	return wpss_append_dashboard_section( $dashboard_url, 'create' );
}

/**
 * Get the Become a Vendor URL.
 *
 * Returns the URL to the vendor registration page or dashboard with become-vendor section.
 *
 * @since 1.1.0
 *
 * @return string Become vendor URL.
 */
function wpss_get_become_vendor_url(): string {
	// First check for a dedicated vendor registration page.
	$vendor_page_url = wpss_get_page_url( 'vendor_registration' );
	if ( $vendor_page_url ) {
		return $vendor_page_url;
	}

	// Fall back to dashboard with become-vendor section.
	$dashboard_url = wpss_get_page_url( 'dashboard' );
	if ( $dashboard_url ) {
		return wpss_append_dashboard_section( $dashboard_url, 'become-vendor' );
	}

	return wpss_get_page_url( 'become_vendor' );
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

/**
 * Get service checkout URL.
 *
 * Generates a URL to the checkout page with service parameters.
 *
 * @since 1.1.0
 *
 * @param int   $service_id Service CPT ID.
 * @param int   $package_id Package index (0, 1, 2).
 * @param array $addons     Optional addon IDs.
 * @return string Checkout URL with service parameters.
 */
function wpss_get_service_checkout_url( int $service_id, int $package_id = 0, array $addons = array() ): string {
	// Try the active e-commerce adapter's checkout URL builder first.
	$adapter = wpss_get_ecommerce_adapter();
	if ( $adapter ) {
		$checkout_provider = $adapter->get_checkout_provider();
		if ( $checkout_provider ) {
			return $checkout_provider->get_checkout_url(
				$service_id,
				array(
					'package_id' => $package_id,
					'addons'     => $addons,
				)
			);
		}
	}

	// Fallback: use mapped checkout page with query args.
	$url = wpss_get_page_url( 'checkout' );
	if ( ! $url ) {
		return '';
	}

	$url = add_query_arg( 'service_id', $service_id, $url );
	if ( $package_id > 0 ) {
		$url = add_query_arg( 'package', $package_id, $url );
	}
	if ( ! empty( $addons ) ) {
		$url = add_query_arg( 'addons', implode( ',', $addons ), $url );
	}

	return $url;
}

/**
 * Get the base checkout URL (without service ID).
 *
 * Uses the mapped checkout page URL, or builds from the adapter's checkout slug.
 *
 * @since 1.2.0
 * @return string Base checkout URL.
 */
function wpss_get_checkout_base_url(): string {
	// If a non-standalone adapter is active, use its checkout URL.
	$adapter = wpss_get_ecommerce_adapter();
	if ( $adapter && 'standalone' !== $adapter->get_id() ) {
		$checkout_provider = $adapter->get_checkout_provider();
		if ( $checkout_provider ) {
			// Pass the service id explicitly. 0 means "no particular service",
			// which is exactly what a BASE checkout URL is.
			//
			// This used to call get_checkout_url() with no arguments.
			// CheckoutProviderInterface declares get_checkout_url( int $service_id, … )
			// with NO default, and only WCCheckoutProvider widened it with one -
			// so omitting the argument worked on WooCommerce and threw
			// ArgumentCountError on EDD, FluentCart and SureCart. This function
			// feeds the cart, every pay URL and several templates, so those
			// three rails fataled on their own checkout while Woo, the rail
			// everybody tests, stayed green.
			return $checkout_provider->get_checkout_url( 0 );
		}
	}

	$url = wpss_get_page_url( 'checkout' );
	if ( $url ) {
		return $url;
	}

	// Fallback to adapter slug.
	$slug = \WPSellServices\Integrations\Standalone\StandaloneAdapter::get_checkout_slug();
	return home_url( '/' . $slug . '/' );
}

/**
 * Get the URL a buyer uses to pay one existing order.
 *
 * This is the single seam for "send the buyer somewhere they can pay THIS
 * order" — tips, milestones, extensions and accepted proposals all resolve
 * through here, including the links we put in emails.
 *
 * The standalone checkout understands `?pay_order=N` and renders that order.
 * A cart-based rail (WooCommerce, EDD) does not: appending the query arg to
 * its checkout URL lands the buyer on an empty cart with no way to pay, so
 * those rails hook `wpss_pay_order_url` and return a URL on their own
 * payment flow instead. Never rebuild this URL inline — a caller that does
 * is correct only on standalone.
 *
 * @since 1.4.0
 *
 * @param int $order_id WPSS order ID to be paid.
 * @return string Payment URL for the active e-commerce rail.
 */
function wpss_get_pay_order_url( int $order_id ): string {
	$url = add_query_arg( 'pay_order', $order_id, wpss_get_checkout_base_url() );

	/**
	 * Filter the URL a buyer is sent to in order to pay a single order.
	 *
	 * Cart-based rails replace this entirely — see the WooCommerce
	 * implementation in Pro, which creates (or reuses) a real WC order and
	 * returns its native order-pay URL so the link works from an email with
	 * no cart session.
	 *
	 * @since 1.4.0
	 *
	 * @param string $url      Default standalone pay URL.
	 * @param int    $order_id WPSS order ID being paid.
	 */
	return (string) apply_filters( 'wpss_pay_order_url', $url, $order_id );
}

/**
 * Platform values that mark a row as a sub-order rather than a real order.
 *
 * Tips, milestones and extensions are stored as their own rows in
 * wpss_orders but they are not orders a buyer placed or a seller sold —
 * they hang off a parent order. Every list, count and stat has to agree on
 * that, so the list lives here rather than being re-authored per query.
 *
 * @since 1.4.0
 *
 * @return array<int, string> Sub-order platform values.
 */
/**
 * Decode HTML entities for a JSON payload.
 *
 * WordPress stores term names and similar strings HTML-encoded, because its
 * own consumer is HTML. A JSON API's string field is not HTML: a native client
 * renders it into a text node, so "Graphics &amp; Design" reaches the screen
 * verbatim. Entity encoding is a transport concern for HTML and does not
 * belong in the payload — decode once here rather than making every consumer
 * carry a decoder and remember to use it.
 *
 * @since 1.4.0
 *
 * @param mixed $value Raw stored value.
 * @return string Decoded text.
 */
function wpss_rest_text( $value ): string {
	return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/**
 * Whether NEW payments are taken through the plugin's own gateways.
 *
 * ONE rule, decided in one place: our gateways take payment only on the
 * standalone rail. The cart integrations are optional - a site turns one on
 * when it wants that plugin to take the money. The moment it does, WooCommerce
 * (or EDD, FluentCart, SureCart) processes ALL new payment and ours stop
 * offering a second way to pay.
 *
 * Not filterable, on purpose. Giving a buyer the choice between "pay with
 * WooCommerce" and "pay with our Stripe" on one site is confusing for them and
 * leaves the two systems disagreeing about whether the order was paid: our
 * gateway would charge on our keys with no order in the store, so no store
 * receipt, refund or report would ever know about it.
 *
 * This governs STARTING a payment, not history. Switching rails never rewrites
 * past orders: an order paid through our Stripe keeps its reference and its
 * webhooks keep being handled, and an order that went through Woo keeps its WC
 * order link, whichever rail the site later runs. Only the next order is
 * affected by the switch.
 *
 * Sub-order payments (tips, milestones, extensions) do not consult this: they
 * resolve through wpss_get_pay_order_url(), which hands off to whichever rail
 * is active.
 *
 * @since 1.4.0
 *
 * @return bool True when new payments are taken through our own gateways.
 */
function wpss_uses_standalone_payments(): bool {
	$adapter = function_exists( 'wpss_get_ecommerce_adapter' ) ? wpss_get_ecommerce_adapter() : null;

	return ! $adapter || 'standalone' === $adapter->get_id();
}

/**
 * Default checkout reassurance badges.
 *
 * ONE definition, used by the settings screen (as placeholders) and by the
 * checkout (as fallbacks), so what an owner sees while editing is exactly what
 * a buyer gets when a row is left blank.
 *
 * Every default is a statement of fact the plugin can back up. Nothing here
 * promises a refund, a guarantee or an outcome - the checkout used to say
 * "On-time Delivery / Or your money back", which no code in this plugin
 * honours and no owner agreed to. An owner who genuinely offers that can type
 * it in; we will not say it on their behalf.
 *
 * `delivery` and `revisions` carry no default sub-text because theirs comes
 * from the package being bought - see wpss_get_checkout_badges().
 *
 * @since 1.4.0
 *
 * @return array<string, array<string, string>> Keyed by badge id.
 */
function wpss_get_checkout_badge_defaults(): array {
	return array(
		'delivery'      => array(
			'label' => __( 'Delivery time', 'wp-sell-services' ),
			'title' => __( 'Delivery time', 'wp-sell-services' ),
			'note'  => '',
		),
		'communication' => array(
			'label' => __( 'Communication', 'wp-sell-services' ),
			'title' => __( 'Direct communication', 'wp-sell-services' ),
			'note'  => __( 'Message your seller on the order', 'wp-sell-services' ),
		),
		'revisions'     => array(
			'label' => __( 'Revisions', 'wp-sell-services' ),
			'title' => __( 'Revisions', 'wp-sell-services' ),
			'note'  => '',
		),
	);
}

/**
 * Build the checkout reassurance badges for a purchase.
 *
 * Owner text wins; where they have left a row blank we fall back to the real
 * numbers on the package being bought, so the badge can never contradict the
 * order it sits next to.
 *
 * @since 1.4.0
 *
 * @param array<string, mixed> $package Package being purchased.
 * @return array<int, array<string, string>> Renderable badges.
 */
function wpss_get_checkout_badges( array $package ): array {
	$settings = get_option( 'wpss_general', array() );

	if ( isset( $settings['checkout_badges_enabled'] ) && ! $settings['checkout_badges_enabled'] ) {
		return array();
	}

	$owner    = isset( $settings['checkout_badges'] ) && is_array( $settings['checkout_badges'] ) ? $settings['checkout_badges'] : array();
	$defaults = wpss_get_checkout_badge_defaults();

	$days      = isset( $package['delivery_days'] ) ? (int) $package['delivery_days'] : 0;
	$revisions = isset( $package['revisions'] ) ? (int) $package['revisions'] : null;

	// Facts about THIS purchase, used when the owner has not written their own.
	$fallback_notes = array(
		'delivery'  => $days > 0
			/* translators: %d: number of days. */
			? sprintf( _n( '%d day from requirements', '%d days from requirements', $days, 'wp-sell-services' ), $days )
			: '',
		'revisions' => null === $revisions
			? ''
			: ( -1 === $revisions
				? __( 'Unlimited revisions included', 'wp-sell-services' )
				/* translators: %d: number of revisions. */
				: sprintf( _n( '%d revision included', '%d revisions included', $revisions, 'wp-sell-services' ), $revisions ) ),
	);

	$icons = array(
		'delivery'      => "\xE2\x8F\xB1",
		'communication' => "\xF0\x9F\x92\xAC",
		'revisions'     => "\xE2\x9C\x85",
	);

	$badges = array();

	foreach ( $defaults as $key => $default ) {
		$title = trim( (string) ( $owner[ $key ]['title'] ?? '' ) );
		$note  = trim( (string) ( $owner[ $key ]['note'] ?? '' ) );

		$title = '' !== $title ? $title : $default['title'];
		$note  = '' !== $note ? $note : ( $fallback_notes[ $key ] ?? $default['note'] );

		// Nothing true to say about this one for this package - say nothing.
		if ( '' === $note ) {
			continue;
		}

		$badges[] = array(
			'icon'  => $icons[ $key ] ?? '',
			'title' => $title,
			'note'  => $note,
		);
	}

	/**
	 * Filter the checkout reassurance badges.
	 *
	 * @since 1.4.0
	 *
	 * @param array $badges  Each entry: icon, title, note.
	 * @param array $package Package being purchased.
	 */
	return (array) apply_filters( 'wpss_checkout_badges', $badges, $package );
}

/**
 * Whether the plugin is running payments in demo mode.
 *
 * A fresh install is meant to work end to end from the first minute. Until
 * this existed it could not: Stripe and PayPal ship without credentials and
 * the Test gateway was hidden behind WP_DEBUG, which is off on every
 * production site - so a new owner set up a marketplace, walked a buyer to
 * checkout and hit an empty gateway list, with nothing on screen to say a
 * step was missing.
 *
 * Demo mode fills that gap and then gets out of the way. It is ON only while
 * ALL of these hold:
 *
 *   - this site takes payment through our own gateways (standalone rail)
 *   - no real gateway has been configured yet
 *   - the owner has not turned it off
 *
 * So it disables itself the moment real credentials are saved. There is no
 * state to remember and no way to be silently stuck in test mode with a live
 * store - the thing that makes "enable a test gateway by default" dangerous
 * in most plugins.
 *
 * @since 1.4.0
 *
 * @return bool
 */
function wpss_demo_payments_enabled(): bool {
	if ( ! wpss_uses_standalone_payments() ) {
		return false;
	}

	// An explicit opt-out always wins, so an owner can run a live standalone
	// store with no gateway yet configured without a demo checkout appearing.
	if ( 'no' === get_option( 'wpss_demo_payments', '' ) ) {
		return false;
	}

	return ! wpss_has_live_gateway();
}

/**
 * Whether any real payment gateway is configured and usable.
 *
 * "Configured" means enabled AND carrying the credentials it needs - an
 * enabled gateway with empty keys cannot take money, so it does not count.
 *
 * @since 1.4.0
 *
 * @return bool
 */
function wpss_has_live_gateway(): bool {
	$gateways = wpss()->get_payment_gateways();

	foreach ( $gateways as $id => $gateway ) {
		if ( 'test' === $id ) {
			continue;
		}

		// is_enabled() is the interface method, and Stripe/PayPal implement it
		// as "enabled AND has the keys it needs" - which is exactly the
		// question here. An enabled gateway with blank credentials cannot take
		// money, so it must not count as live.
		if ( $gateway instanceof \WPSellServices\Integrations\Contracts\PaymentGatewayInterface && $gateway->is_enabled() ) {
			return true;
		}
	}

	return false;
}

/**
 * REST permission callback: the caller must be logged in.
 *
 * Use this instead of `'permission_callback' => 'is_user_logged_in'`. A bare
 * boolean callback makes WordPress answer with the code `rest_forbidden`, so
 * an anonymous caller is told it is FORBIDDEN when the truth is that it is
 * UNAUTHENTICATED. A client whose rule is "401 means refresh the token and
 * retry" then reads an expired token as a permanent denial and never
 * recovers - and the two routes that did this, /me and /dashboard, are the
 * first two a cold-starting app calls.
 *
 * The HTTP status was already 401; it was the machine-readable code that lied.
 *
 * @since 1.4.0
 *
 * @return true|WP_Error
 */
function wpss_rest_require_login() {
	if ( is_user_logged_in() ) {
		return true;
	}

	return new WP_Error(
		'rest_not_logged_in',
		__( 'You must be logged in to access this endpoint.', 'wp-sell-services' ),
		array( 'status' => 401 )
	);
}

/**
 * REST permission callback: the caller must be a site administrator.
 *
 * Answers "who are you?" before "may you?", so an anonymous caller gets 401
 * and a logged-in non-admin gets 403. Returning 403 to both is what breaks a
 * client's re-auth logic.
 *
 * @since 1.4.0
 *
 * @return true|WP_Error
 */
function wpss_rest_require_admin() {
	$logged_in = wpss_rest_require_login();

	if ( is_wp_error( $logged_in ) ) {
		return $logged_in;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return new WP_Error(
		'wpss_not_owner',
		__( 'You do not have permission to access this endpoint.', 'wp-sell-services' ),
		array( 'status' => 403 )
	);
}

/**
 * REST permission callback: the caller must be a vendor.
 *
 * One code for one condition. "You are not a vendor" was answered with four
 * different codes across the API - rest_not_vendor, not_vendor, wpss_not_vendor
 * and a plain rest_forbidden - so a client could not branch on it without
 * knowing which endpoint it had called.
 *
 * @since 1.4.0
 *
 * @return true|WP_Error
 */
function wpss_rest_require_vendor() {
	$logged_in = wpss_rest_require_login();

	if ( is_wp_error( $logged_in ) ) {
		return $logged_in;
	}

	if ( wpss_is_vendor( get_current_user_id() ) ) {
		return true;
	}

	return new WP_Error(
		'wpss_not_vendor',
		__( 'Only vendors can access this endpoint.', 'wp-sell-services' ),
		array( 'status' => 403 )
	);
}

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

/**
 * Guard: does this vendor's account status forbid taking on new work?
 *
 * The one place that owns the rule. Every vendor gate used to answer only
 * "are you a vendor?" (`wpss_is_vendor()` — role and capability), and none of
 * them asked "are you a vendor in good standing?". The web service wizard did
 * ask ({@see \WPSellServices\Frontend\ServiceWizard}), so a suspended vendor
 * was blocked in the browser and waved straight through over REST — publishing
 * services, submitting proposals and requesting payouts with a valid
 * Application Password. Passwords are minted by WordPress core and survive
 * whatever the plugin does at login, so on mobile the web-only gate reached
 * nothing at all.
 *
 * WHAT THIS BLOCKS is new supply, not existing obligations. A suspended vendor
 * must not list more work, bid for more work, or pull money out — but they can
 * still deliver, message and complete orders a buyer has already PAID for.
 * Blocking fulfilment would punish the buyer for the seller's suspension and
 * strand paid work with no way to finish it, so delivery paths deliberately do
 * not call this. Refunding a stranded order is the owner's tool for that case.
 *
 * An empty status means the user has no `wpss_vendor_profiles` row at all —
 * role-granted, legacy and demo-seeded vendors. Those are treated as active,
 * matching every other read site (`wpss_get_vendor_status( $id ) ?: 'active'`).
 * Failing closed there would lock out every vendor created before the profile
 * table existed.
 *
 * Administrators are never blocked: they act on vendors' behalf.
 *
 * @since 1.5.1
 *
 * @param int $user_id Vendor user ID. Defaults to the current user.
 * @return WP_Error|null WP_Error when the status forbids it, null when allowed.
 */
function wpss_vendor_status_block( int $user_id = 0 ) {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( user_can( $user_id, 'manage_options' ) ) {
		return null;
	}

	// Marketplace standing first. A suspended or banned member must not be able
	// to take on new work regardless of how healthy their VENDOR application
	// looks — the two are different questions, and answering only the second
	// would leave a banned member with an approved vendor row still selling.
	// Composed here rather than added at each call site so all ten vendor gates
	// inherit it and none can be forgotten.
	$account_block = wpss_account_status_block( $user_id );

	if ( $account_block ) {
		return $account_block;
	}

	$status = wpss_get_vendor_status( $user_id );

	if ( '' === $status || 'active' === $status ) {
		return null;
	}

	// One code per condition so a client can word its own message. Reuses the
	// code EarningsController already returned for pending, so no consumer
	// that already branches on it breaks.
	$blocked = array(
		'pending'   => array(
			'wpss_vendor_pending',
			__( 'Your vendor account is pending approval.', 'wp-sell-services' ),
		),
		'suspended' => array(
			'wpss_vendor_suspended',
			__( 'Your vendor account is suspended.', 'wp-sell-services' ),
		),
		'rejected'  => array(
			'wpss_vendor_rejected',
			__( 'Your vendor account was not approved.', 'wp-sell-services' ),
		),
	);

	// An unrecognised status is not a pass. A new state added to the profile
	// table must be classified deliberately rather than inheriting "allowed"
	// by falling off the end of this list.
	list( $code, $message ) = $blocked[ $status ] ?? array(
		'wpss_vendor_not_active',
		__( 'Your vendor account is not active.', 'wp-sell-services' ),
	);

	return new WP_Error( $code, $message, array( 'status' => 403 ) );
}

/**
 * REST permission callback: the caller must be a vendor in good standing.
 *
 * Login, then vendor, then status — in that order, so an anonymous caller gets
 * 401 and a logged-in one gets 403. Use this on any route that lets a vendor
 * take on new work. See {@see wpss_vendor_status_block()} for what "new work"
 * deliberately excludes.
 *
 * @since 1.5.1
 *
 * @return true|WP_Error
 */
function wpss_rest_require_active_vendor() {
	$is_vendor = wpss_rest_require_vendor();

	if ( is_wp_error( $is_vendor ) ) {
		return $is_vendor;
	}

	return wpss_vendor_status_block() ?? true;
}

/**
 * Shape a money value for the REST API.
 *
 * Returns the three fields every money value in the API carries, under
 * predictable names derived from the base key: the float (unchanged, so no
 * existing consumer breaks), the exact integer in minor units, and the
 * currency needed to interpret both.
 *
 * Use this instead of adding `*_minor` by hand. Hand-written pairs are how
 * the API ended up with money on some endpoints carrying minor units and
 * money on others not, and with a `_minor` value scaled by the store currency
 * on a row that was actually sold in a different one.
 *
 * Example: wpss_rest_money( 'total', 25.20, 'USD' ) returns
 * array( 'total' => 25.2, 'total_minor' => 2520, 'currency' => 'USD' ).
 *
 * @since 1.4.0
 *
 * @param string $key      Base field name, e.g. 'total' or 'amount'.
 * @param float  $amount   Amount in major units.
 * @param string $currency Optional. Currency of THIS amount - pass the row's
 *                         own currency, not the store default, or historic
 *                         rows scale wrongly. Defaults to the store currency.
 * @return array<string, mixed> The money fields, ready to merge into a response.
 */
function wpss_rest_money( string $key, float $amount, string $currency = '' ): array {
	$currency = '' !== $currency ? $currency : wpss_get_currency();

	return array(
		$key            => round( $amount, wpss_get_currency_decimals( $currency ) ),
		$key . '_minor' => wpss_amount_to_minor_units( $amount, $currency ),
		'currency'      => $currency,
	);
}

/**
 * Shape a user for the REST API.
 *
 * One actor shape wherever the API names a person - order participants,
 * timeline actors, review authors, vendors on a service card. Without a
 * shared shape these drift into `user_id` here, `author` there and a bare
 * display name somewhere else, and a client needs a parser per endpoint.
 *
 * @since 1.4.0
 *
 * @param int $user_id User ID. 0 or an unknown user yields null.
 * @return array<string, mixed>|null
 */
function wpss_rest_user( int $user_id ): ?array {
	if ( $user_id <= 0 ) {
		return null;
	}

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return null;
	}

	return array(
		'id'     => $user_id,
		'name'   => wpss_rest_text( $user->display_name ),
		'avatar' => get_avatar_url( $user_id ),
	);
}

/**
 * Shape a taxonomy term for the REST API.
 *
 * One definition, because there were two: /categories returned
 * {id, name, slug, description, count, parent, icon, image} while the
 * categories inside a service payload were raw WP_Term objects carrying
 * term_taxonomy_id, term_group and filter, and an `id` that was actually
 * called `term_id`. A client could not use one parser for both, so the
 * category it read off a service did not match the category list it was
 * asked to match it against.
 *
 * @since 1.4.0
 *
 * @param \WP_Term $term Term object.
 * @return array<string, mixed> Term data.
 */
function wpss_prepare_term_for_rest( \WP_Term $term ): array {
	$icon  = get_term_meta( $term->term_id, '_wpss_icon', true );
	$image = get_term_meta( $term->term_id, '_wpss_image', true );

	return array(
		'id'          => (int) $term->term_id,
		'name'        => wpss_rest_text( $term->name ),
		'slug'        => (string) $term->slug,
		'description' => wpss_rest_text( $term->description ),
		'count'       => (int) $term->count,
		'parent'      => (int) $term->parent,
		'icon'        => $icon ?: '',
		'image'       => $image ? wp_get_attachment_url( $image ) : '',
	);
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

	if ( ! is_array( $packages ) || ! isset( $packages[ $package_id ] ) || ! is_array( $packages[ $package_id ] ) ) {
		return null;
	}

	return $packages[ $package_id ];
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
 * Get the cart page URL for the active adapter.
 *
 * For WooCommerce returns the WC cart page; for standalone returns the service-checkout page.
 *
 * @since 1.2.0
 * @return string Cart URL.
 */
function wpss_get_cart_url(): string {
	$adapter = wpss_get_ecommerce_adapter();

	// WooCommerce: use WC cart page.
	if ( $adapter && 'woocommerce' === $adapter->get_id() && function_exists( 'wc_get_cart_url' ) ) {
		return wc_get_cart_url();
	}

	// Standalone: use the dedicated cart page.
	return wpss_get_page_url( 'cart' ) ?: wpss_get_checkout_base_url();
}

/**
 * Get the active e-commerce adapter or a specific adapter by ID.
 *
 * @since 1.1.0
 *
 * @param string|null $adapter_id Specific adapter ID or null for active adapter.
 * @return \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null Adapter instance or null.
 */
function wpss_get_ecommerce_adapter( ?string $adapter_id = null ): ?\WPSellServices\Integrations\Contracts\EcommerceAdapterInterface {
	$integration_mgr = wpss()->get_integration_manager();

	if ( ! $integration_mgr ) {
		return null;
	}

	// Return specific adapter if ID provided.
	if ( null !== $adapter_id ) {
		return $integration_mgr->get_adapter( $adapter_id );
	}

	// Return active adapter.
	return $integration_mgr->get_active_adapter();
}

/**
 * Get the order provider from the active e-commerce adapter.
 *
 * @since 1.2.0
 *
 * @return \WPSellServices\Integrations\Contracts\OrderProviderInterface|null Order provider or null.
 */
function wpss_get_order_provider(): ?\WPSellServices\Integrations\Contracts\OrderProviderInterface {
	$adapter = wpss_get_ecommerce_adapter();

	if ( ! $adapter ) {
		return null;
	}

	return $adapter->get_order_provider();
}

/**
 * Get a service's add-ons with the legacy meta-key fallback.
 *
 * Add-ons live under `_wpss_extras` (the frontend Service Wizard's key) but
 * the admin metabox and CLI commands write to `_wpss_addons`. Every place
 * that resolves add-on indices MUST use this helper so admin / CLI-seeded
 * services surface their add-ons in the order modal, cart, checkout, and
 * orders alike. A full meta-key consolidation is parked for 1.2 — see
 * plans/future-features/from-1.1.0-audit.md.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return array<int, array<string, mixed>> Add-on rows (title, price, delivery_time), keyed by index.
 */
function wpss_get_service_extras( int $service_id ): array {
	$extras = get_post_meta( $service_id, '_wpss_extras', true ) ?: array();

	if ( empty( $extras ) ) {
		$extras = get_post_meta( $service_id, '_wpss_addons', true ) ?: array();
	}

	return is_array( $extras ) ? $extras : array();
}

/**
 * Get a service's minimum delivery days with the dual meta-key fallback.
 *
 * Delivery days live under two historical keys: `_wpss_delivery_days`
 * (written by the frontend Service Wizard and the save_post sync) and
 * `_wpss_fastest_delivery` (written by the admin metabox and the REST API).
 * Every PHP read site MUST use this helper so services created via either
 * path surface their delivery time in SEO schema, REST responses, and
 * package fallbacks alike. Meta-query filters cannot use this helper —
 * for those, every write site syncs BOTH keys instead. A full meta-key
 * consolidation is parked for 1.2 — see
 * plans/future-features/from-1.1.0-audit.md.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return int Delivery days, 0 when neither key is set.
 */
function wpss_get_service_delivery_days( int $service_id ): int {
	$delivery_days = (int) get_post_meta( $service_id, '_wpss_delivery_days', true );

	if ( $delivery_days <= 0 ) {
		$delivery_days = (int) get_post_meta( $service_id, '_wpss_fastest_delivery', true );
	}

	return max( 0, $delivery_days );
}

/**
 * Get a service's revision count with the dual meta-key fallback.
 *
 * Revision counts live under two historical keys: `_wpss_revisions`
 * (written by the frontend Service Wizard) and `_wpss_max_revisions`
 * (written by the admin metabox, the REST API, and CLI). Every PHP read
 * site MUST use this helper so services created via either path surface
 * their revision count in REST responses and package fallbacks alike.
 * Unlike the delivery-days helper, 0 ("No revisions") and -1 ("Unlimited")
 * are both valid stored values, so the fallback only triggers when the
 * primary key is truly absent. A full meta-key consolidation is parked
 * for 1.2 - see plans/future-features/from-1.1.0-audit.md.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return int Revision count. -1 means unlimited, 0 means none (or unset).
 */
function wpss_get_service_revisions( int $service_id ): int {
	$revisions = get_post_meta( $service_id, '_wpss_revisions', true );

	if ( '' === $revisions ) {
		$revisions = get_post_meta( $service_id, '_wpss_max_revisions', true );
	}

	return (int) $revisions;
}

/**
 * Resolve addon data from checkout POST data.
 *
 * Reads addon_ids from $_POST, validates each addon belongs to the service
 * and is active, then returns addon details and total for create_order().
 *
 * @since 1.1.0
 *
 * @param int $service_id Service post ID.
 * @return array{addons: array, addons_total: float, delivery_days_extra: int}
 */
function wpss_resolve_checkout_addons( int $service_id ): array {
	$result = array(
		'addons'              => array(),
		'addons_total'        => 0,
		'delivery_days_extra' => 0,
	);

	// Try pre-resolved addons_data first (sent by checkout form as JSON).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by calling gateway.
	$addons_json = isset( $_POST['addons_data'] ) ? sanitize_text_field( wp_unslash( $_POST['addons_data'] ) ) : '';
	if ( $addons_json ) {
		$addons_array = json_decode( $addons_json, true );
		if ( is_array( $addons_array ) ) {
			foreach ( $addons_array as $addon ) {
				$addon_price                    = (float) ( $addon['price'] ?? 0 );
				$extra_days                     = (int) ( $addon['delivery_days_extra'] ?? $addon['extra_days'] ?? 0 );
				$result['addons_total']        += $addon_price;
				$result['delivery_days_extra'] += $extra_days;
				$result['addons'][]             = array(
					'id'                  => (int) ( $addon['id'] ?? 0 ),
					'name'                => sanitize_text_field( $addon['name'] ?? $addon['title'] ?? '' ),
					'price'               => $addon_price,
					'delivery_days_extra' => $extra_days,
				);
			}
			return $result;
		}
	}

	// Fallback: resolve from addon_ids (indices into _wpss_extras post meta).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by calling gateway.
	$addon_ids_raw = isset( $_POST['addon_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['addon_ids'] ) ) : '';

	if ( ! $addon_ids_raw ) {
		return $result;
	}

	$addon_indices = array_map( 'intval', explode( ',', $addon_ids_raw ) );
	$all_extras    = wpss_get_service_extras( $service_id );

	foreach ( $addon_indices as $index ) {
		if ( $index < 0 || ! isset( $all_extras[ $index ] ) ) {
			continue;
		}
		$extra                          = $all_extras[ $index ];
		$addon_price                    = (float) ( $extra['price'] ?? 0 );
		$extra_days                     = (int) ( $extra['delivery_time'] ?? $extra['delivery_days_extra'] ?? 0 );
		$result['addons_total']        += $addon_price;
		$result['delivery_days_extra'] += $extra_days;
		$result['addons'][]             = array(
			'id'                  => $index,
			'name'                => sanitize_text_field( $extra['title'] ?? '' ),
			'price'               => $addon_price,
			'delivery_days_extra' => $extra_days,
		);
	}

	return $result;
}

/**
 * Check if WooCommerce integration is enabled.
 *
 * Returns true if WooCommerce is the active e-commerce adapter (requires Pro).
 *
 * @since 1.1.0
 *
 * @return bool True if WooCommerce integration is active.
 */
function wpss_is_woocommerce_enabled(): bool {
	$adapter = wpss_get_active_adapter();
	if ( ! $adapter ) {
		return false;
	}

	return 'woocommerce' === $adapter->get_id();
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
	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Get notifications for a user.
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, unread_only).
 * @return array Array of notification objects.
 */
function wpss_get_user_notifications( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	$defaults = array(
		'limit'       => 20,
		'offset'      => 0,
		'unread_only' => false,
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE user_id = %d";
	$params = array( $user_id );

	if ( $args['unread_only'] ) {
		$sql .= ' AND is_read = 0';
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
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
	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Get services for a vendor.
 *
 * @since 1.2.0
 *
 * @param int   $vendor_id Vendor user ID.
 * @param array $args      Query arguments (limit, offset, status).
 * @return \WP_Post[] Array of service posts.
 */
function wpss_get_vendor_services( int $vendor_id, array $args = array() ): array {
	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => 'publish',
	);
	$args     = wp_parse_args( $args, $defaults );

	$query_args = array(
		'post_type'      => 'wpss_service',
		'author'         => $vendor_id,
		'posts_per_page' => $args['limit'],
		'offset'         => $args['offset'],
		'post_status'    => $args['status'],
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	return get_posts( $query_args );
}

/**
 * Render pagination for a WP_Query.
 *
 * Outputs pagination HTML using WordPress paginate_links().
 *
 * @since 1.2.0
 *
 * @param \WP_Query $query The query object to paginate.
 * @param array     $args  Optional. Arguments to customize pagination.
 * @return void
 */
function wpss_pagination( \WP_Query $query, array $args = array() ): void {
	$total_pages = $query->max_num_pages;

	if ( $total_pages <= 1 ) {
		return;
	}

	$current_page = max( 1, get_query_var( 'paged', 1 ) );

	// get_pagenum_link() resolves the current request URL. Outside the main
	// query (e.g. a REST render) it can return a non-string, which would make
	// str_replace() fatal. Guard it so the default base is always a string; an
	// off-main-query caller (wpss_render_services_grid) supplies an explicit
	// 'base' + 'format' anyway.
	$pagenum_link = get_pagenum_link( 999999999 );
	$default_base = is_string( $pagenum_link )
		? str_replace( '999999999', '%#%', esc_url( $pagenum_link ) )
		: '';

	$defaults = array(
		'base'      => $default_base,
		'format'    => '?paged=%#%',
		'current'   => $current_page,
		'total'     => $total_pages,
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
		'type'      => 'list',
	);

	$args = wp_parse_args( $args, $defaults );

	$pagination = paginate_links( $args );

	if ( $pagination ) {
		echo '<nav class="wpss-pagination" aria-label="' . esc_attr__( 'Pagination', 'wp-sell-services' ) . '">';
		echo wp_kses_post( $pagination );
		echo '</nav>';
	}
}

/**
 * Enqueue WPSS frontend assets.
 *
 * Call this from any shortcode, block, or template that needs WPSS frontend styles and scripts.
 *
 * @since 1.0.0
 * @return void
 */
function wpss_enqueue_frontend_assets(): void {
	wp_enqueue_style( 'wpss-design-system' );
	wp_enqueue_style( 'wpss-frontend' );
	// Packet H: load Lucide + bootstrap alongside the frontend bundle so every
	// <i data-lucide="…"> rendered by templates on the current surface hydrates
	// on DOMContentLoaded and on the wpss:icons:refresh CustomEvent.
	wp_enqueue_script( 'lucide' );
	wp_enqueue_script( 'wpss-icons' );
	wp_enqueue_script( 'wpss-frontend' );
}

/**
 * Check whether a URL is a recognized YouTube or Vimeo video.
 *
 * Used as the whitelist for vendor intro videos — anything that is not a
 * parseable YouTube / Vimeo link is rejected on save so the profile
 * never tries to embed an arbitrary third-party iframe.
 *
 * @since 1.1.0
 *
 * @param string $url Raw URL.
 * @return bool True if the URL is a parseable YouTube or Vimeo video link.
 */
function wpss_is_supported_video_url( string $url ): bool {
	if ( '' === $url ) {
		return false;
	}

	return null !== wpss_parse_video_embed( $url );
}

/**
 * Parse a YouTube or Vimeo URL into embed pieces.
 *
 * Returns null when the URL is not a supported provider or when the video
 * ID cannot be extracted. On success, returns:
 *   [
 *     'provider' => 'youtube'|'vimeo',
 *     'id'       => string,
 *     'embed'    => fully-qualified embed URL for an <iframe src>
 *   ]
 *
 * Supported URL shapes:
 *   - YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID,
 *     youtube.com/shorts/ID, m.youtube.com/* variants
 *   - Vimeo: vimeo.com/ID, player.vimeo.com/video/ID, channel paths with
 *     a numeric ID
 *
 * @since 1.1.0
 *
 * @param string $url Raw URL.
 * @return array{provider: string, id: string, embed: string}|null
 */
function wpss_parse_video_embed( string $url ): ?array {
	$url = trim( $url );
	if ( '' === $url ) {
		return null;
	}

	$parts = wp_parse_url( $url );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$host  = preg_replace( '/^www\./', '', $host );

	if ( 'youtu.be' === $host ) {
		$id = ltrim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube.com/embed/' . rawurlencode( $id ),
			);
		}
		return null;
	}

	if ( 'youtube.com' === $host || 'm.youtube.com' === $host ) {
		$path = (string) ( $parts['path'] ?? '' );
		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		$id = '';
		if ( '/watch' === $path && ! empty( $query['v'] ) ) {
			$id = (string) $query['v'];
		} elseif ( preg_match( '#^/(embed|shorts|v)/([A-Za-z0-9_-]{6,})#', $path, $m ) ) {
			$id = $m[2];
		}

		if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube.com/embed/' . rawurlencode( $id ),
			);
		}
		return null;
	}

	if ( 'vimeo.com' === $host || 'player.vimeo.com' === $host ) {
		$path = (string) ( $parts['path'] ?? '' );
		if ( preg_match( '#/(\d{5,})#', $path, $m ) ) {
			return array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'embed'    => 'https://player.vimeo.com/video/' . rawurlencode( $m[1] ),
			);
		}
		return null;
	}

	return null;
}

/**
 * Render a vendor intro video as a responsive 16:9 embed.
 *
 * Returns '' (not an iframe fallback) when the URL is empty or not a
 * supported provider so callers can echo unconditionally and the page
 * simply drops the video section when there isn't one.
 *
 * @since 1.1.0
 *
 * @param string $url   Raw video URL.
 * @param string $title Optional accessible title for the iframe.
 * @return string HTML wrapper + iframe, or empty string.
 */
function wpss_render_video_embed( string $url, string $title = '' ): string {
	$parsed = wpss_parse_video_embed( $url );
	if ( null === $parsed ) {
		return '';
	}

	$title = '' === $title ? __( 'Vendor intro video', 'wp-sell-services' ) : $title;

	return sprintf(
		'<div class="wpss-video-embed"><iframe src="%s" title="%s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe></div>',
		esc_url( $parsed['embed'] ),
		esc_attr( $title )
	);
}

/**
 * Render a paginated services grid (cards + pagination markup).
 *
 * Single source of truth for the services-block grid: both the REST
 * grid endpoint (ServicesController::get_grid) and the legacy
 * admin-ajax delegate (AjaxHandlers::load_services) call this so the
 * card template + every `wpss_*_service_card` extension hook + theme
 * override stay identical across both transports. Server-side rendering
 * is intentional — the card fires extension hooks a client-side JSON
 * renderer could not reproduce.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $attributes Block attributes (postsPerPage, orderBy, order, category).
 * @param int                  $page       Page number (1-based).
 * @param string               $base_url   Optional page URL the grid lives on, used as the
 *                                         pagination base. Required when rendering outside
 *                                         the main query (e.g. a REST request) where
 *                                         get_pagenum_link() cannot resolve the request URL.
 * @return array{html: string, pagination: string, total: int, pages: int} Rendered grid parts.
 */
function wpss_render_services_grid( array $attributes, int $page = 1, string $base_url = '' ): array {
	$args = array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => absint( $attributes['postsPerPage'] ?? 12 ),
		'paged'          => max( 1, $page ),
		'orderby'        => sanitize_key( $attributes['orderBy'] ?? 'date' ),
		'order'          => in_array( ( $attributes['order'] ?? 'DESC' ), array( 'ASC', 'DESC' ), true ) ? $attributes['order'] : 'DESC',
	);

	// Category filter.
	if ( ! empty( $attributes['category'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'wpss_service_category',
				'field'    => 'term_id',
				'terms'    => absint( $attributes['category'] ),
			),
		);
	}

	$query = new \WP_Query( $args );

	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			wpss_get_template_part( 'content', 'service-card' );
		}
	} else {
		echo '<p class="wpss-no-services">' . esc_html__( 'No services found.', 'wp-sell-services' ) . '</p>';
	}
	wp_reset_postdata();
	$html = ob_get_clean();

	// Pagination. When a base URL is supplied (REST/off-main-query render),
	// build an explicit base + format so paginate_links does not depend on
	// get_pagenum_link() resolving the ambient request URL.
	$pagination_args = array();
	if ( '' !== $base_url ) {
		$clean = remove_query_arg( 'paged', $base_url );
		$sep   = ( false === strpos( $clean, '?' ) ) ? '?' : '&';

		$pagination_args['base']    = $clean . '%_%';
		$pagination_args['format']  = $sep . 'paged=%#%';
		$pagination_args['current'] = max( 1, $page );
	}

	ob_start();
	wpss_pagination( $query, $pagination_args );
	$pagination = ob_get_clean();

	return array(
		'html'       => $html,
		'pagination' => $pagination,
		'total'      => (int) $query->found_posts,
		'pages'      => (int) $query->max_num_pages,
	);
}

/**
 * Validate + upload conversation message file attachments.
 *
 * Single source of truth for message attachment handling. Used by both the
 * REST ConversationsController::send_message and the legacy admin-ajax
 * AjaxHandlers::send_message so the allow-list, MIME re-check, size cap, and
 * upload behaviour are identical across transports.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $files A single $_FILES['attachments'] entry (PHP's grouped
 *                     multi-file shape: name[], type[], tmp_name[], etc.).
 * @return array{attachments: array<int, array{id:int,url:string,name:string,type:string}>, skipped: array<int,string>}
 */
function wpss_handle_message_attachments( array $files ): array {
	$attachments = array();
	$skipped     = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return array(
			'attachments' => $attachments,
			'skipped'     => $skipped,
		);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt' );
	$allowed_mimes = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/zip',
		'text/plain',
	);
	$max_size      = 10 * 1024 * 1024; // 10MB per file.

	$file_count = count( $files['name'] );
	for ( $i = 0; $i < $file_count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}

		$file = array(
			'name'     => $files['name'][ $i ],
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		$file_name = sanitize_file_name( $file['name'] );

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_types, true ) ) {
			$skipped[] = $file_name . ': ' . __( 'unsupported file type', 'wp-sell-services' );
			continue;
		}

		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime_type = $file_info['type'] ?? '';
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			$skipped[] = $file_name . ': ' . __( 'invalid MIME type', 'wp-sell-services' );
			continue;
		}

		if ( $file['size'] > $max_size ) {
			$skipped[] = $file_name . ': ' . __( 'file too large (max 10MB)', 'wp-sell-services' );
			continue;
		}

		$_FILES['upload_file'] = $file;
		$attachment_id         = media_handle_upload( 'upload_file', 0 );

		if ( ! is_wp_error( $attachment_id ) ) {
			$attachments[] = array(
				'id'   => $attachment_id,
				'url'  => wp_get_attachment_url( $attachment_id ),
				'name' => $files['name'][ $i ],
				'type' => $mime_type, // Server-verified MIME, not client-provided.
			);
		} else {
			$skipped[] = $file_name . ': ' . $attachment_id->get_error_message();
		}
	}

	return array(
		'attachments' => $attachments,
		'skipped'     => $skipped,
	);
}

/**
 * Normalize PHP's grouped $_FILES entry into a list of per-file specs.
 *
 * Turns the `name[]/type[]/tmp_name[]/error[]/size[]` shape PHP produces for a
 * multi-file field into a flat array of single-file specs (name/type/tmp_name/
 * error/size), skipping empty slots and sanitizing the client-supplied name +
 * mime. tmp_name/error/size are not user-controlled. Shared by the REST
 * deliverables endpoint and the legacy admin-ajax delivery handler so both
 * feed DeliveryService::submit() the same shape.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $files A single grouped $_FILES['field'] entry.
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function wpss_normalize_uploaded_files( array $files ): array {
	$out = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $out;
	}

	$count = count( $files['name'] );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}
		$out[] = array(
			'name'     => sanitize_file_name( $files['name'][ $i ] ),
			'type'     => sanitize_mime_type( $files['type'][ $i ] ),
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => (int) $files['error'][ $i ],
			'size'     => (int) $files['size'][ $i ],
		);
	}

	return $out;
}

/**
 * Sanitize a date string to a strict Y-m-d value or null.
 *
 * Accepts only a calendar-valid Y-m-d date that round-trips exactly; anything
 * else (empty string, partial, or impossible date like 2026-13-40) returns
 * null so the DATE column stores SQL NULL.
 *
 * @since 1.2.0
 *
 * @param string $value Raw date string.
 * @return string|null Valid Y-m-d date, or null.
 */
function wpss_sanitize_date( string $value ): ?string {
	$value = trim( sanitize_text_field( $value ) );

	if ( '' === $value ) {
		return null;
	}

	$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

	if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
		return null;
	}

	return $value;
}

/**
 * Build a sanitized vendor-profile update payload from a posted field set.
 *
 * Single source of truth for the vendor-profile form fields, shared by the
 * REST VendorsController::update_current_vendor and the legacy admin-ajax
 * AjaxHandlers::update_vendor_profile so both transports sanitize identically
 * and write the SAME wpss_vendor_profiles columns (via
 * VendorService::update_profile()). Only keys present in $src are included, so
 * partial updates leave untouched fields alone.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $src Unslashed field set (tagline, bio, country, city,
 *                         website, intro_video_url, vacation_mode,
 *                         vacation_message; avatar_id/cover_id keys signal an
 *                         intent to clear when the id is 0).
 * @param int                  $avatar_id Resolved avatar attachment id (0 = none/clear).
 * @param int                  $cover_id  Resolved cover attachment id (0 = none/clear).
 * @return array<string, mixed> Sanitized data for VendorService::update_profile().
 */
function wpss_build_vendor_profile_update( array $src, int $avatar_id, int $cover_id ): array {
	$data = array();

	if ( array_key_exists( 'tagline', $src ) ) {
		$data['tagline'] = sanitize_text_field( (string) $src['tagline'] );
	}
	if ( array_key_exists( 'bio', $src ) ) {
		$data['bio'] = sanitize_textarea_field( (string) $src['bio'] );
	}
	if ( array_key_exists( 'country', $src ) ) {
		$data['country'] = sanitize_text_field( (string) $src['country'] );
	}
	if ( array_key_exists( 'city', $src ) ) {
		$data['city'] = sanitize_text_field( (string) $src['city'] );
	}
	if ( array_key_exists( 'website', $src ) ) {
		$data['website'] = esc_url_raw( (string) $src['website'] );
	}
	if ( array_key_exists( 'intro_video_url', $src ) ) {
		// Accept only YouTube/Vimeo origins - stored verbatim, rendered through
		// the safe embed helper. Anything else clears the field so the UI falls
		// back to the no-video state.
		$raw_video               = esc_url_raw( (string) $src['intro_video_url'] );
		$data['intro_video_url'] = wpss_is_supported_video_url( $raw_video ) ? $raw_video : '';
	}
	if ( array_key_exists( 'vacation_mode', $src ) ) {
		$data['vacation_mode'] = empty( $src['vacation_mode'] ) ? 0 : 1;
	}
	if ( array_key_exists( 'vacation_message', $src ) ) {
		$data['vacation_message'] = sanitize_textarea_field( (string) $src['vacation_message'] );
	}
	if ( array_key_exists( 'vacation_return_date', $src ) ) {
		// Accept only a strict Y-m-d date; anything else (incl. empty) stores NULL.
		$data['vacation_return_date'] = wpss_sanitize_date( (string) $src['vacation_return_date'] );
	}

	if ( $avatar_id > 0 ) {
		$data['avatar_id'] = $avatar_id;
	} elseif ( array_key_exists( 'avatar_id', $src ) ) {
		$data['avatar_id'] = null;
	}

	if ( $cover_id > 0 ) {
		$data['cover_image_id'] = $cover_id;
	} elseif ( array_key_exists( 'cover_id', $src ) ) {
		$data['cover_image_id'] = null;
	}

	return $data;
}

/**
 * Render one conversation message row (the canonical messaging markup).
 *
 * Single source of truth for a message bubble in the order/dashboard thread.
 * Used by the initial server render (templates/order/conversation.php), the
 * REST message response (additive `html` field), the REST/AJAX send response,
 * and the AJAX poll - so every transport appends byte-identical markup.
 *
 * Accepts either a Message model (attachments/read_by as arrays, created_at as
 * DateTimeImmutable) or a raw $wpdb row (JSON strings, string created_at, with
 * an optional sender_name from a JOIN); the shape is normalised internally.
 *
 * @since 1.2.0
 *
 * @param object $message         Message model or raw DB row.
 * @param int    $current_user_id Viewer user ID (controls sent/received styling).
 * @return string Message row HTML.
 */
function wpss_render_message_row( object $message, int $current_user_id ): string {
	$sender_id = (int) ( $message->sender_id ?? 0 );
	$content   = (string) ( $message->content ?? '' );
	$type      = $message->type ?? ( $message->content_type ?? 'text' );
	$is_own    = $sender_id === $current_user_id;
	$is_system = 'system' === $type;

	// Normalise created_at to a timestamp.
	$created = $message->created_at ?? null;
	if ( $created instanceof \DateTimeInterface ) {
		$created_ts = $created->getTimestamp();
	} else {
		$created_ts = $created ? strtotime( (string) $created ) : time();
	}
	$time_text = wp_date( get_option( 'time_format' ), $created_ts );

	// Normalise attachments + read_by to arrays.
	$attachments = $message->attachments ?? array();
	if ( is_string( $attachments ) ) {
		$attachments = json_decode( $attachments, true ) ?: array();
	}
	$read_by = $message->read_by ?? array();
	if ( is_string( $read_by ) ) {
		$read_by = json_decode( $read_by, true ) ?: array();
	}

	// Sender display name (prefer a JOIN-supplied value, else look it up).
	$sender_name = $message->sender_name ?? '';
	if ( '' === $sender_name && $sender_id ) {
		$sender      = get_userdata( $sender_id );
		$sender_name = $sender ? $sender->display_name : '';
	}

	ob_start();

	if ( $is_system ) :
		?>
		<div class="wpss-messaging__system">
			<span class="wpss-messaging__system-text">
				<?php echo wp_kses_post( $content ); ?>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( $time_text ); ?>
				</span>
			</span>
		</div>
		<?php
	else :
		?>
		<div class="wpss-messaging__message <?php echo $is_own ? 'wpss-messaging__message--sent' : ''; ?>" data-message-id="<?php echo esc_attr( (string) ( $message->id ?? 0 ) ); ?>">
			<?php if ( ! $is_own ) : ?>
				<div class="wpss-messaging__message-avatar">
					<?php echo get_avatar( $sender_id, 32 ); ?>
				</div>
			<?php endif; ?>
			<div class="wpss-messaging__message-content">
				<div class="wpss-messaging__bubble">
					<?php if ( ! $is_own ) : ?>
						<span class="wpss-messaging__sender"><?php echo esc_html( $sender_name ); ?></span>
					<?php endif; ?>
					<div class="wpss-messaging__text">
						<?php echo wp_kses_post( nl2br( $content ) ); ?>
					</div>
					<?php if ( ! empty( $attachments ) ) : ?>
						<div class="wpss-messaging__attachments">
							<?php foreach ( $attachments as $attachment ) : ?>
								<?php
								$file_url  = $attachment['url'] ?? '';
								$file_name = $attachment['name'] ?? ( $attachment['filename'] ?? basename( (string) $file_url ) );
								$file_type = $attachment['type'] ?? '';
								$is_image  = 0 === strpos( (string) $file_type, 'image/' );
								?>
								<?php if ( $is_image && $file_url ) : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-image">
										<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>">
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-file">
										<span class="wpss-messaging__attachment-icon">
											<i data-lucide="file" class="wpss-icon" aria-hidden="true"></i>
										</span>
										<span class="wpss-messaging__attachment-info">
											<span class="wpss-messaging__attachment-name"><?php echo esc_html( $file_name ); ?></span>
										</span>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( $time_text ); ?>
					<?php $is_read = ! empty( array_diff_key( (array) $read_by, array( $current_user_id => '' ) ) ); ?>
					<?php if ( $is_own && $is_read ) : ?>
						<span class="wpss-messaging__message-status wpss-messaging__message-status--read" title="<?php esc_attr_e( 'Read', 'wp-sell-services' ); ?>">
							<i data-lucide="check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
						</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	endif;

	return (string) ob_get_clean();
}
