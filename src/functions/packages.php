<?php
/**
 * Service packages, add-ons, express delivery and line pricing.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

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
 * Give every package on a service a stable id, and persist it.
 *
 * A package's identity has always been its POSITION in `_wpss_packages`, so
 * reordering the tiers repoints saved carts, deep links and historical orders
 * at a different package (Basecamp #10154919857). Snapshots already protect
 * orders after the fact; this gives the API something durable to name a package
 * BY, so a client never has to send an index at all.
 *
 * Ids come from a per-service counter kept in `_wpss_package_next_id` and are
 * NEVER REUSED. That is the whole point: if tier 2 is deleted and a new one
 * added, the newcomer gets a fresh id rather than inheriting the dead tier's
 * identity and, with it, the meaning of every link that still points there.
 *
 * Writes only when something is missing an id, so it is safe to call
 * repeatedly. Deliberately not called from the read accessor above - a public
 * archive rendering fifty service cards must not turn into fifty writes.
 * Assignment happens on the write paths and in the upgrade backfill.
 *
 * @since 1.6.0
 *
 * @param int $service_id Service post ID.
 * @return array<int, array<string, mixed>> Packages, each carrying an `id`.
 */
function wpss_assign_package_ids( int $service_id ): array {
	$packages = wpss_get_service_packages( $service_id );

	if ( ! $packages ) {
		return array();
	}

	$next    = (int) get_post_meta( $service_id, '_wpss_package_next_id', true );
	$changed = false;

	// Stable ids start well above any plausible package INDEX, so the two id
	// spaces can never collide.
	//
	// This is not tidiness, it is a correctness requirement. `package_id` has to
	// accept both readings during the transition - shipped clients send the
	// index, new ones send the stable id - and a resolver cannot tell them apart
	// if the ranges overlap. With ids starting at 1, a client sending
	// package_id=1 to mean "the second tier" would silently receive the FIRST
	// tier, whose stable id is 1. That is the same silent-wrong-package failure
	// this whole change exists to prevent, introduced by the fix for it.
	//
	// Caught by the reorder test before release. Indices are 0..n for a handful
	// of tiers; 1000 leaves the two spaces permanently disjoint.
	$base = (int) apply_filters( 'wpss_package_id_base', 1000, $service_id );

	if ( $next < $base ) {
		$next = $base;
	}

	// Never hand out an id that is already in use, even if the counter was lost
	// or reset - reusing one would silently merge two different tiers.
	$used = array();
	foreach ( $packages as $package ) {
		if ( is_array( $package ) && ! empty( $package['id'] ) ) {
			$used[] = (int) $package['id'];
		}
	}

	if ( $used ) {
		$next = max( $next, max( $used ) + 1 );
	}

	foreach ( $packages as $index => $package ) {
		if ( ! is_array( $package ) ) {
			continue;
		}

		if ( ! empty( $package['id'] ) ) {
			continue;
		}

		while ( in_array( $next, $used, true ) ) {
			++$next;
		}

		$packages[ $index ]['id'] = $next;
		$used[]                   = $next;
		++$next;
		$changed = true;
	}

	if ( $changed ) {
		update_post_meta( $service_id, '_wpss_packages', $packages );
		update_post_meta( $service_id, '_wpss_package_next_id', $next );
	}

	return $packages;
}

/**
 * Resolve a package on a service from either a stable id or a legacy index.
 *
 * `POST /cart/add` has always required `package_id` and documented it as
 * "Package index/ID", while `GET /services/{id}/packages` returned no id at
 * all - so every client had to send the array index and inherit its
 * instability. From 1.6.0 the API publishes a stable `id`, but shipped clients
 * are still sending indices, and a saved cart may hold either.
 *
 * The two readings CANNOT overlap: stable ids are issued from 1000 upward
 * (see wpss_assign_package_ids) while indices are 0..n for a handful of tiers.
 * That is deliberate - an earlier draft started ids at 1, which made
 * `package_id=1` ambiguous between "the tier with id 1" and "the second tier",
 * and silently served the wrong package to every shipped client. The stable id
 * is checked first; the positional read is the fallback for older callers.
 *
 * @since 1.6.0
 *
 * @param int $service_id Service post ID.
 * @param int $package_id A stable package id, or a legacy positional index.
 * @return array{package: array<string, mixed>, index: int}|null Resolved package
 *                                                              and its current index.
 */
function wpss_resolve_service_package( int $service_id, int $package_id ): ?array {
	$packages = wpss_get_service_packages( $service_id );

	if ( ! $packages ) {
		return null;
	}

	foreach ( $packages as $index => $package ) {
		if ( is_array( $package ) && isset( $package['id'] ) && (int) $package['id'] === $package_id ) {
			return array(
				'package' => $package,
				'index'   => (int) $index,
			);
		}
	}

	// Legacy positional read. Index 0 is a real package, so this is isset(),
	// never empty().
	if ( isset( $packages[ $package_id ] ) && is_array( $packages[ $package_id ] ) ) {
		return array(
			'package' => $packages[ $package_id ],
			'index'   => (int) $package_id,
		);
	}

	return null;
}

/**
 * Normalise an add-on list into the one shape every surface reads.
 *
 * Add-ons lived in three places with three names for the extra delivery
 * days: the wizard's `_wpss_extras` meta (extra_days), the admin metabox's
 * `_wpss_addons` meta (delivery_days_extra) and the wpss_service_addons table
 * (delivery_days_extra) that REST wrote and the order modal never read
 * (Basecamp 10264294443). Post meta `_wpss_addons` is now the only store and
 * this is the only shape.
 *
 * @since 1.7.1
 *
 * @param array<int|string, mixed> $raw Add-on rows in any historical shape.
 * @return array<int, array<string, mixed>> Rows: title, description, price, delivery_days_extra, field_type, price_type, min_quantity, max_quantity, options, is_required.
 */
function wpss_normalize_service_addons( array $raw ): array {
	$out = array();

	foreach ( $raw as $addon ) {
		if ( ! is_array( $addon ) ) {
			continue;
		}

		$title = sanitize_text_field( (string) ( $addon['title'] ?? $addon['name'] ?? '' ) );
		if ( '' === $title ) {
			continue;
		}

		$field_type = sanitize_key( (string) ( $addon['field_type'] ?? 'checkbox' ) );
		$price_type = sanitize_key( (string) ( $addon['price_type'] ?? 'flat' ) );

		// "Per Quantity" pricing means nothing without a quantity to multiply.
		if ( 'quantity_based' === $price_type ) {
			$field_type = 'quantity';
		}

		$out[] = array(
			'title'               => $title,
			'description'         => sanitize_textarea_field( (string) ( $addon['description'] ?? '' ) ),
			// A negative add-on is a discount the buyer can stack; never store one.
			'price'               => max( 0.0, (float) ( $addon['price'] ?? 0 ) ),

			/*
			 * Clamp, never absint().
			 *
			 * absint( -2 ) is 2, so an add-on entered as "deliver two days
			 * SOONER" was stored as "two days LATER" and the buyer paid extra
			 * for a worse delivery date. Silently inverting the vendor's
			 * intent is the one outcome worse than ignoring it.
			 *
			 * Negative is not a supported concept here and the whole product
			 * agrees: the field is labelled "Extra Delivery Days", both inputs
			 * carry min="0", and SingleServiceView renders it as "(+N days)".
			 * A paid rush option would be a different feature with its own
			 * field, not a sign flip on this one. So a negative clamps to 0 -
			 * no extra days - which is the closest honest reading of it.
			 */
			'delivery_days_extra' => max( 0, (int) ( $addon['delivery_days_extra'] ?? $addon['extra_days'] ?? $addon['delivery_time'] ?? 0 ) ),
			'field_type'          => isset( wpss_get_addon_field_types()[ $field_type ] ) ? $field_type : 'checkbox',
			'price_type'          => isset( wpss_get_addon_price_types()[ $price_type ] ) ? $price_type : 'flat',
			'min_quantity'        => max( 1, absint( $addon['min_quantity'] ?? 1 ) ),
			'max_quantity'        => max( 1, absint( $addon['max_quantity'] ?? 10 ) ),
			'options'             => sanitize_text_field( is_array( $addon['options'] ?? null ) ? implode( ', ', $addon['options'] ) : (string) ( $addon['options'] ?? '' ) ),
			'is_required'         => ! empty( $addon['is_required'] ),
		);
	}

	return $out;
}

/**
 * Get a service's add-ons, normalised.
 *
 * The one reader of `_wpss_addons`. Every place that resolves add-on indices
 * (order modal, cart, checkout, orders, REST, admin) reads through here, so
 * the index a buyer picked means the same row on every surface.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return array<int, array<string, mixed>> Add-on rows keyed by index.
 */
function wpss_get_service_extras( int $service_id ): array {
	$addons = get_post_meta( $service_id, '_wpss_addons', true );

	return wpss_normalize_service_addons( is_array( $addons ) ? $addons : array() );
}

/**
 * Save a service's add-ons, normalised.
 *
 * The one writer. Callers cap the list with wpss_enforce_service_limits()
 * first.
 *
 * @since 1.7.1
 *
 * @param int                      $service_id Service post ID.
 * @param array<int|string, mixed> $addons     Add-on rows in any shape.
 * @return void
 */
function wpss_save_service_addons( int $service_id, array $addons ): void {
	$addons = wpss_normalize_service_addons( $addons );

	if ( empty( $addons ) ) {
		delete_post_meta( $service_id, '_wpss_addons' );
		return;
	}

	update_post_meta( $service_id, '_wpss_addons', $addons );
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
 * Add-on field types: how the buyer answers an add-on (value => label).
 *
 * @since 1.8.0
 *
 * @return array<string, string>
 */
function wpss_get_addon_field_types(): array {
	return array(
		'checkbox' => __( 'Checkbox (Yes/No)', 'wp-sell-services' ),
		'quantity' => __( 'Quantity Selector', 'wp-sell-services' ),
		'dropdown' => __( 'Dropdown Select', 'wp-sell-services' ),
		'text'     => __( 'Text Input', 'wp-sell-services' ),
	);
}

/**
 * Add-on price types: how an add-on is priced (value => label).
 *
 * @since 1.8.0
 *
 * @return array<string, string>
 */
function wpss_get_addon_price_types(): array {
	return array(
		'flat'           => __( 'Flat Price', 'wp-sell-services' ),
		'percentage'     => __( 'Percentage of Order', 'wp-sell-services' ),
		'quantity_based' => __( 'Per Quantity', 'wp-sell-services' ),
	);
}

/**
 * How an add-on's price reads to a buyer: "+$20", "+10%" or "$5 each".
 *
 * @since 1.8.0
 *
 * @param array<string, mixed> $addon Normalised add-on (wpss_get_service_extras()).
 * @return string Plain text.
 */
function wpss_addon_price_label( array $addon ): string {
	$amount = (float) ( $addon['price'] ?? 0 );

	if ( 'percentage' === ( $addon['price_type'] ?? 'flat' ) ) {
		/* translators: %s: percentage number */
		return sprintf( __( '+%s%%', 'wp-sell-services' ), number_format_i18n( $amount, floor( $amount ) === $amount ? 0 : 2 ) );
	}

	$money = wp_strip_all_tags( wpss_format_price( $amount ) );

	if ( 'quantity_based' === ( $addon['price_type'] ?? 'flat' ) ) {
		/* translators: %s: price per unit */
		return sprintf( __( '%s each', 'wp-sell-services' ), $money );
	}

	/* translators: %s: price */
	return sprintf( __( '+%s', 'wp-sell-services' ), $money );
}

/**
 * Normalise what a buyer picked into one shape, from any shape a surface sends.
 *
 * Accepts a CSV of ids ("0,2"), a list of ids, a list of {id, quantity, option,
 * text}, the manual-order map {id: {selected, quantity, option, text}}, legacy
 * cart rows {id, title, price} (the price is IGNORED - add-ons are priced from
 * the service, never from what a request says), or a JSON string of any of
 * these. Ids are indices into the service's add-ons.
 *
 * @since 1.8.0
 *
 * @param mixed $raw Selection in any supported shape.
 * @return array<int, array{id: int, quantity: int, option: string, text: string}> Keyed by add-on id.
 */
function wpss_normalize_addon_selection( $raw ): array {
	if ( is_string( $raw ) ) {
		$raw     = trim( $raw );
		$decoded = ( '' !== $raw && ( '[' === $raw[0] || '{' === $raw[0] ) ) ? json_decode( $raw, true ) : null;
		$raw     = is_array( $decoded ) ? $decoded : ( '' === $raw ? array() : explode( ',', $raw ) );
	}

	$out = array();

	foreach ( (array) $raw as $key => $entry ) {
		if ( is_array( $entry ) && array_key_exists( 'selected', $entry ) ) {
			// Manual-order map: {id: {selected, quantity, option, text}}.
			if ( empty( $entry['selected'] ) ) {
				continue;
			}
			$entry['id'] = $key;
		}

		$row = is_array( $entry ) ? $entry : array( 'id' => $entry );

		if ( ! isset( $row['id'] ) || ! is_numeric( $row['id'] ) || ( (int) $row['id'] < 0 && WPSS_EXPRESS_ADDON_ID !== (int) $row['id'] ) ) {
			continue;
		}

		$id         = (int) $row['id'];
		$out[ $id ] = array(
			'id'       => $id,
			'quantity' => max( 1, absint( $row['quantity'] ?? 1 ) ),
			'option'   => sanitize_text_field( (string) ( $row['option'] ?? '' ) ),
			'text'     => sanitize_textarea_field( (string) ( $row['text'] ?? '' ) ),
		);
	}

	return $out;
}

/**
 * Price the add-ons a buyer picked - THE one add-on pricer.
 *
 * Every surface that shows or charges an add-on reads this: the order modal's
 * quote, the cart, checkout, the app's payment intent, the admin manual order
 * and Pro's store rails. Flat add-ons cost their price; percentage add-ons are
 * that percent of the package subtotal; a quantity add-on is its unit price
 * times the quantity, clamped to its min and max. Required add-ons are always
 * included. Before 1.8.0 five surfaces priced add-ons on their own and all but
 * one treated every add-on as flat (Basecamp 10336467507).
 *
 * @since 1.8.0
 *
 * @param int   $service_id       Service post ID.
 * @param mixed $selection        What the buyer picked (any shape wpss_normalize_addon_selection() takes).
 * @param float $package_subtotal Package price x quantity, the base for percentage add-ons.
 * @param array $package          The package bought; needed to price its Express delivery
 *                                (wpss_get_package_express()). Without it Express is not offered.
 * @return array{addons: array<int, array<string, mixed>>, addons_total: float, delivery_days_extra: int}|WP_Error
 */
function wpss_price_addons( int $service_id, $selection, float $package_subtotal, array $package = array() ) {
	$definitions = wpss_get_service_extras( $service_id );
	$normalized  = wpss_normalize_addon_selection( $selection );
	$picked      = array_intersect_key( $normalized, $definitions );
	$decimals    = wpss_get_currency_decimals();

	foreach ( $definitions as $index => $definition ) {
		if ( ! empty( $definition['is_required'] ) && ! isset( $picked[ $index ] ) ) {
			$picked[ $index ] = array(
				'id'       => $index,
				'quantity' => 1,
				'option'   => '',
				'text'     => '',
			);
		}
	}

	ksort( $picked );

	$result = array(
		'addons'              => array(),
		'addons_total'        => 0.0,
		'delivery_days_extra' => 0,
	);

	foreach ( $picked as $index => $choice ) {
		$definition = $definitions[ $index ];
		$field_type = (string) $definition['field_type'];
		$quantity   = 1;
		$option     = '';
		$text       = '';

		if ( 'quantity' === $field_type ) {
			$quantity = min( max( $choice['quantity'], (int) $definition['min_quantity'] ), max( (int) $definition['min_quantity'], (int) $definition['max_quantity'] ) );
		} elseif ( 'dropdown' === $field_type ) {
			$options = array_values( array_filter( array_map( 'trim', explode( ',', (string) $definition['options'] ) ), 'strlen' ) );
			$option  = in_array( $choice['option'], $options, true ) ? $choice['option'] : '';
		} elseif ( 'text' === $field_type ) {
			$text = mb_substr( trim( $choice['text'] ), 0, 500 );
		}

		// A dropdown needs a real option and a text add-on needs text; without
		// one it was not really chosen.
		if ( ( 'dropdown' === $field_type && '' === $option ) || ( 'text' === $field_type && '' === $text ) ) {
			if ( ! empty( $definition['is_required'] ) ) {
				return new WP_Error(
					'wpss_addon_required',
					/* translators: %s: add-on title */
					sprintf( __( 'Please complete the required add-on "%s".', 'wp-sell-services' ), $definition['title'] ),
					array( 'status' => 400 )
				);
			}
			continue;
		}

		$rate  = (float) $definition['price'];
		$unit  = 'percentage' === $definition['price_type'] ? round( $package_subtotal * $rate / 100, $decimals ) : $rate;
		$price = round( $unit * $quantity, $decimals );

		$result['addons'][]             = array(
			'id'                  => (int) $index,
			'title'               => (string) $definition['title'],
			'name'                => (string) $definition['title'],
			'field_type'          => $field_type,
			'price_type'          => (string) $definition['price_type'],
			'rate'                => $rate,
			'unit_price'          => $unit,
			'quantity'            => $quantity,
			'option'              => $option,
			'text'                => $text,
			'price'               => $price,
			'delivery_days_extra' => (int) $definition['delivery_days_extra'],
		);
		$result['addons_total']        += $price;
		$result['delivery_days_extra'] += (int) $definition['delivery_days_extra'];
	}

	// Express delivery: a flat price for the package's faster delivery time,
	// which replaces the package's days rather than adding to them. It rides
	// as an add-on row so every rail that carries add-ons carries it.
	$express = wpss_get_package_express( $package );
	if ( $express && isset( $normalized[ WPSS_EXPRESS_ADDON_ID ] ) ) {
		$price                   = round( $express['price'], $decimals );
		$result['addons'][]      = array(
			'id'                  => WPSS_EXPRESS_ADDON_ID,
			'title'               => __( 'Express delivery', 'wp-sell-services' ),
			'name'                => __( 'Express delivery', 'wp-sell-services' ),
			'field_type'          => 'express',
			'price_type'          => 'flat',
			'rate'                => $price,
			'unit_price'          => $price,
			'quantity'            => 1,
			'option'              => '',
			'text'                => '',
			'price'               => $price,
			'delivery_days_extra' => 0,
			'delivery_days'       => $express['days'],
		);
		$result['addons_total'] += $price;
	}

	$result['addons_total'] = round( $result['addons_total'], $decimals );

	return $result;
}

/**
 * A package's stable id, as a saver should keep it.
 *
 * The wizard and the wp-admin editor rebuilt each package from a list of keys
 * that left `id` out, so every save stripped the ids and the next read handed
 * out new ones - a saved cart or an app link to package 1000 then pointed at
 * nothing (Basecamp 10342028625). Every package saver adds this to the row it
 * builds; wpss_assign_package_ids() numbers any package still without one.
 *
 * @since 1.8.0
 *
 * @param array $raw Package input.
 * @return array{id?: int} The id, or nothing for a new package.
 */
function wpss_package_id_from_input( array $raw ): array {
	$id = absint( $raw['id'] ?? 0 );

	return $id > 0 ? array( 'id' => $id ) : array();
}

/**
 * A package's Express delivery, when it offers one.
 *
 * Express is set per package (Basecamp 10337201764): a price and a delivery
 * time that REPLACES the package's own, which an add-on cannot express - an
 * add-on only adds days, so an "Express 24h" add-on made the order slower.
 * Offered only when it is actually faster than the package.
 *
 * @since 1.8.0
 *
 * @param array $package Package row.
 * @return array{price: float, days: int}|null
 */
function wpss_get_package_express( array $package ): ?array {
	$price = (float) ( $package['express_price'] ?? 0 );
	$days  = (int) ( $package['express_days'] ?? 0 );
	$base  = (int) ( $package['delivery_days'] ?? 0 );

	if ( $price <= 0 || $days < 1 || ( $base > 0 && $days >= $base ) ) {
		return null;
	}

	return array(
		'price' => $price,
		'days'  => $days,
	);
}

/**
 * The Express fields of a package, sanitised - the one reader of them for
 * every package saver (wizard, wp-admin editor, REST), each of which rebuilds
 * a package from its own list of keys.
 *
 * @since 1.8.0
 *
 * @param array $raw Package input.
 * @return array{express_price: float, express_days: int}
 */
function wpss_sanitize_package_express( array $raw ): array {
	$price = max( 0.0, round( (float) ( $raw['express_price'] ?? 0 ), wpss_get_currency_decimals() ) );

	return array(
		'express_price' => $price,
		'express_days'  => $price > 0 ? absint( $raw['express_days'] ?? 0 ) : 0,
	);
}

/**
 * How many days an order line takes: the package's time, or its Express time
 * when bought, plus any add-on days. At least one.
 *
 * The one count for the order modal, checkout, the order's deadline and a
 * revision's deadline.
 *
 * @since 1.8.0
 *
 * @param array $package Package row (or the order's package snapshot).
 * @param array $addons  Priced add-on rows (wpss_price_addons(), or the order's addons).
 * @return int Days.
 */
function wpss_line_delivery_days( array $package, array $addons ): int {
	$days = (int) ( $package['delivery_days'] ?? 0 );
	$days = $days > 0 ? $days : 7;

	foreach ( $addons as $addon ) {
		if ( ! empty( $addon['delivery_days'] ) ) {
			$days = (int) $addon['delivery_days'];
		}
	}

	foreach ( $addons as $addon ) {
		$days += (int) ( $addon['delivery_days_extra'] ?? 0 );
	}

	return max( 1, $days );
}
