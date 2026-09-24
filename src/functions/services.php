<?php
/**
 * Services: lookup, packages, gallery, add-ons and the shared services grid renderer.
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
 * Get service URL.
 *
 * @param int $service_id Service ID.
 * @return string
 */
function wpss_get_service_url( int $service_id ): string {
	return get_permalink( $service_id ) ?: '';
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

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Documents the meta shape this branch handles; not dead code.
	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	if ( isset( $raw['images'] ) && is_array( $raw['images'] ) ) {
		return array_values( array_filter( array_map( 'absint', $raw['images'] ) ) );
	}

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Documents the meta shape this branch handles; not dead code.
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

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Documents the meta shape this branch handles; not dead code.
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

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Documents the meta shape this branch handles; not dead code.
	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	if ( isset( $raw['video'] ) && is_string( $raw['video'] ) ) {
		return $raw['video'];
	}

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Documents the meta shape this branch handles; not dead code.
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

		if ( ! isset( $row['id'] ) || ! is_numeric( $row['id'] ) || (int) $row['id'] < 0 ) {
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
 * @return array{addons: array<int, array<string, mixed>>, addons_total: float, delivery_days_extra: int}|WP_Error
 */
function wpss_price_addons( int $service_id, $selection, float $package_subtotal ) {
	$definitions = wpss_get_service_extras( $service_id );
	$picked      = array_intersect_key( wpss_normalize_addon_selection( $selection ), $definitions );
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

	$result['addons_total'] = round( $result['addons_total'], $decimals );

	return $result;
}

/**
 * Whether a person may see a service at all.
 *
 * Published services are public; an unpublished one (draft, pending, private,
 * rejected) is visible only to its author and site admins. The single rule for
 * every route that resolves a service by id - GET /services/{id} had it inline
 * and its /packages, /faqs, /addons, /reviews and review-summary siblings did
 * not, so they served unpublished listings to anyone (Basecamp 10336370426).
 *
 * @since 1.8.0
 *
 * @param int      $service_id Service post ID.
 * @param int|null $user_id    Viewer; null for the current user.
 * @return bool
 */
function wpss_can_view_service( int $service_id, ?int $user_id = null ): bool {
	$service = get_post( $service_id );

	if ( ! $service || 'wpss_service' !== $service->post_type ) {
		return false;
	}

	if ( 'publish' === $service->post_status ) {
		return true;
	}

	$user_id = null === $user_id ? get_current_user_id() : $user_id;

	return $user_id > 0 && ( (int) $service->post_author === $user_id || user_can( $user_id, 'manage_options' ) );
}

/**
 * Prime the caches every service card reads, in one pass.
 *
 * WP_Query primes the posts, their meta and their terms for the result set, but
 * a service card also renders the featured image and the gallery - and those
 * ATTACHMENTS are not in the result set. Each one was therefore fetched
 * individually: measured on a 12-card grid, 18 separate
 * `SELECT * FROM wp_posts WHERE ID = N` plus 19 postmeta primes, which is most
 * of the cost of the page.
 *
 * Collecting the thumbnail and gallery IDs first and priming them together
 * turns that into one posts query and one meta query. The thumbnail/gallery
 * meta reads here are themselves free, because WP_Query already primed the
 * services' own meta.
 *
 * Safe to call with an empty or non-post array; it simply does nothing.
 *
 * @since 1.5.1
 *
 * @param array<int, \WP_Post|int> $posts Service posts (or IDs) about to be rendered.
 * @return void
 */
function wpss_prime_service_card_caches( array $posts ): void {
	if ( empty( $posts ) ) {
		return;
	}

	$service_ids = array();
	foreach ( $posts as $post ) {
		$service_ids[] = $post instanceof \WP_Post ? (int) $post->ID : (int) $post;
	}

	$service_ids = array_values( array_filter( $service_ids ) );

	if ( empty( $service_ids ) ) {
		return;
	}

	$attachment_ids = array();

	foreach ( $service_ids as $service_id ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $service_id );
		if ( $thumbnail_id ) {
			$attachment_ids[] = $thumbnail_id;
		}

		// Same resolver the card uses, so this cannot drift from what it reads.
		$gallery_ids = wpss_get_gallery_ids( get_post_meta( $service_id, '_wpss_gallery', true ) );
		if ( $gallery_ids ) {
			$attachment_ids = array_merge( $attachment_ids, $gallery_ids );
		}
	}

	$attachment_ids = array_values( array_unique( array_filter( $attachment_ids ) ) );

	if ( $attachment_ids ) {
		_prime_post_caches( $attachment_ids, false, true );
	}
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
		// The ?? guarded the comparison but not the branch that uses the value, so
		// any caller omitting `order` - the shortcode, archives, REST - emitted
		// "Undefined array key order" on every render. Resolve once, then test.
		'order'          => in_array( strtoupper( (string) ( $attributes['order'] ?? 'DESC' ) ), array( 'ASC', 'DESC' ), true )
			? strtoupper( (string) ( $attributes['order'] ?? 'DESC' ) )
			: 'DESC',
	);

	// Category filter. Accepts a term id OR a slug: the [wpss_services]
	// shortcode has always documented `category="logo-design"`, so restricting
	// this to ids would quietly break every existing use of it.
	if ( ! empty( $attributes['category'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'wpss_service_category',
				'field'    => is_numeric( $attributes['category'] ) ? 'term_id' : 'slug',
				'terms'    => is_numeric( $attributes['category'] ) ? absint( $attributes['category'] ) : sanitize_title( (string) $attributes['category'] ),
			),
		);
	}

	// Tag filter.
	if ( ! empty( $attributes['tag'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'wpss_service_tag',
			'field'    => is_numeric( $attributes['tag'] ) ? 'term_id' : 'slug',
			'terms'    => is_numeric( $attributes['tag'] ) ? absint( $attributes['tag'] ) : sanitize_title( (string) $attributes['tag'] ),
		);
	}

	// Vendor filter.
	if ( ! empty( $attributes['vendor'] ) ) {
		$args['author'] = absint( $attributes['vendor'] );
	}

	// Featured filter. Accepts a real boolean (blocks) or the string a
	// shortcode attribute arrives as.
	if ( ! empty( $attributes['featured'] ) && filter_var( $attributes['featured'], FILTER_VALIDATE_BOOLEAN ) ) {
		// The written key is `_wpss_featured` - `_wpss_is_featured` was an
		// orphan that matched nothing.
		$args['meta_query'] = array(
			array(
				'key'   => '_wpss_featured',
				'value' => '1',
			),
		);
	}

	// Sort vocabulary. "rating", "sales" and "price" are not columns WP_Query
	// understands; without this remap they fall through to post_date and the
	// grid silently ignores the sort the caller asked for.
	$orderby_meta = array(
		'rating' => '_wpss_rating_average',
		'sales'  => '_wpss_total_sales',
		'price'  => '_wpss_starting_price',
	);

	if ( isset( $orderby_meta[ $args['orderby'] ] ) ) {
		$args['meta_key'] = $orderby_meta[ $args['orderby'] ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering by a service stat is the documented behaviour of these surfaces.
		$args['orderby']  = 'meta_value_num';
	}

	$query = new \WP_Query( $args );

	wpss_prime_service_card_caches( $query->posts );

	/*
	 * Display toggles for the card, passed down rather than assumed.
	 *
	 * The Service Grid block exposes Show Rating / Show Price / Show Seller and
	 * this renderer never read them - it always loaded the card template, which
	 * always printed all three, so the toggles did nothing in the editor preview
	 * or on the frontend (Basecamp 10308731466). Absent means true, so every
	 * other caller - [wpss_services], archives, REST - renders exactly as before.
	 */
	$wpss_card_display = array(
		'wpss_show_rating' => ! array_key_exists( 'showRating', $attributes ) || (bool) $attributes['showRating'],
		'wpss_show_price'  => ! array_key_exists( 'showPrice', $attributes ) || (bool) $attributes['showPrice'],
		'wpss_show_seller' => ! array_key_exists( 'showSeller', $attributes ) || (bool) $attributes['showSeller'],
	);

	/**
	 * Filter which elements a service card renders.
	 *
	 * @since 1.7.1
	 *
	 * @param array<string,bool>  $wpss_card_display wpss_show_rating, wpss_show_price, wpss_show_seller.
	 * @param array<string,mixed> $attributes        Grid attributes.
	 */
	$wpss_card_display = (array) apply_filters( 'wpss_service_card_display', $wpss_card_display, $attributes );

	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			wpss_get_template_part( 'content', 'service-card', $wpss_card_display );
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
 * Fetch service-category terms for a chooser, with a bound.
 *
 * Eight call sites built this same get_terms() call by hand and NONE of them
 * passed `number`, so every category dropdown and filter bar on the site -
 * the service archive, the buyer-request archive, the service wizard, the
 * create/edit request forms and the block editor's category control - fetched
 * EVERY term on the site and rendered one <option> per term. On a marketplace
 * with a large taxonomy that is a select box with thousands of entries and a
 * query to match.
 *
 * The bound is deliberately generous (200) so no realistic site loses a
 * category today, and filterable for the ones that outgrow it. Callers keep
 * passing their own `hide_empty` / `parent`, because those genuinely differ:
 * an archive filter shows only categories that have services, while the
 * wizard must offer empty ones too.
 *
 * Returns a plain array on WP_Error, so callers do not each repeat that guard.
 *
 * @since 1.5.1
 *
 * @param array<string, mixed> $args Overrides passed through to get_terms().
 * @return \WP_Term[] Matching terms, or an empty array.
 */
function wpss_get_category_terms( array $args = array() ): array {
	$defaults = array(
		'taxonomy'   => 'wpss_service_category',
		'hide_empty' => true,
		/**
		 * Filter the maximum number of category terms a chooser will render.
		 *
		 * @since 1.5.1
		 *
		 * @param int $limit Maximum terms. Default 200.
		 */
		'number'     => (int) apply_filters( 'wpss_category_terms_limit', 200 ),
	);

	$terms = get_terms( wp_parse_args( $args, $defaults ) );

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Group category terms into parents each carrying their children.
 *
 * Every single-dropdown category chooser in the plugin was rendering a FLAT
 * list, so "Logo Design" sat between "Graphics & Design" and "Programming &
 * Tech" looking like a top-level category of its own (Basecamp 10208080926).
 * A buyer could not tell a subcategory from a category.
 *
 * The grouping lives here rather than in each caller because there are five of
 * them and they had already drifted - one passed parent => 0, the rest did not.
 * Markup stays with the caller: an archive filter's option values are URLs, the
 * wizard's are term ids, and a block control wants a plain array.
 *
 * Orphans - a child whose parent is missing from $terms, which happens whenever
 * hide_empty drops an empty parent - are promoted to top level rather than
 * dropped, because a category that exists and has services must remain
 * reachable.
 *
 * @since 1.6.0
 *
 * @param \WP_Term[] $terms Terms, in any order.
 * @return array<int, array{term: \WP_Term, children: \WP_Term[]}> Parents in
 *                                                                the given order.
 */
function wpss_group_category_terms( array $terms ): array {
	$by_id    = array();
	$children = array();

	foreach ( $terms as $term ) {
		if ( $term instanceof \WP_Term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}
	}

	foreach ( $by_id as $term ) {
		$parent = (int) $term->parent;

		if ( $parent > 0 && isset( $by_id[ $parent ] ) ) {
			$children[ $parent ][] = $term;
		}
	}

	$grouped = array();

	foreach ( $by_id as $id => $term ) {
		$parent = (int) $term->parent;

		// Top level, or an orphan whose parent this query did not return.
		if ( 0 === $parent || ! isset( $by_id[ $parent ] ) ) {
			$grouped[] = array(
				'term'     => $term,
				'children' => $children[ $id ] ?? array(),
			);
		}
	}

	return $grouped;
}

/**
 * Count PUBLISHED SERVICES per category term, in one query.
 *
 * A term's own `count` is not the answer. wpss_service_category is registered
 * for BOTH wpss_service and wpss_request, and WordPress counts every object in
 * the term regardless of post type - so a category holding 6 services and 3
 * buyer requests reports 9. The service archive sidebar printed that number
 * beside a result list that (correctly) showed 6.
 *
 * One grouped query for the whole sidebar rather than a count per term, so a
 * marketplace with a large taxonomy does not pay a query per row.
 *
 * @since 1.5.1
 *
 * @param int[] $term_ids Category term IDs.
 * @return array<int, int> term_id => published service count (0 when none).
 */
function wpss_get_category_service_counts( array $term_ids ): array {
	$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );

	if ( empty( $term_ids ) ) {
		return array();
	}

	global $wpdb;

	$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders generated from the ID count; every value is bound.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id, COUNT( DISTINCT p.ID ) AS total
			   FROM {$wpdb->term_taxonomy} tt
			   JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			   JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			  WHERE tt.term_id IN ({$placeholders})
			    AND tt.taxonomy = 'wpss_service_category'
			    AND p.post_type = 'wpss_service'
			    AND p.post_status = 'publish'
			  GROUP BY tt.term_id",
			$term_ids
		)
	);

	// Seed every requested term so a category with no services reads 0 rather
	// than falling back to the mixed-type count.
	$counts = array_fill_keys( $term_ids, 0 );

	foreach ( (array) $rows as $row ) {
		$counts[ (int) $row->term_id ] = (int) $row->total;
	}

	return $counts;
}

/**
 * The provider's own thumbnail for an embedded video.
 *
 * YouTube, Vimeo and the rest all publish a poster frame through oEmbed, and it
 * is the right image for a video thumb: reusing the service's featured image
 * made the video thumb look identical to the first image thumb beside it, so
 * nothing on screen said "this one is the video" except the play badge.
 *
 * CACHED, deliberately. get_data() makes an HTTP request to the provider, and
 * this runs while rendering a public page - an uncached call would put a
 * third-party round trip in front of every visitor, which is a worse version of
 * the render-time database write this same template just lost.
 *
 * Returns an empty string when the provider offers nothing, so callers can fall
 * back to their own poster.
 *
 * @since 1.6.0
 *
 * @param string $video_url Video URL.
 * @return string Thumbnail URL, or an empty string.
 */
function wpss_get_video_thumbnail_url( string $video_url ): string {
	if ( '' === $video_url || ! function_exists( '_wp_oembed_get_object' ) ) {
		return '';
	}

	$key    = 'wpss_video_thumb_' . md5( $video_url );
	$cached = get_transient( $key );

	// A miss and a known-empty answer are different: '' is cached too, so a URL
	// the provider cannot poster does not re-ask on every page view.
	if ( is_string( $cached ) ) {
		return $cached;
	}

	$data      = _wp_oembed_get_object()->get_data( $video_url );
	$thumbnail = ( is_object( $data ) && ! empty( $data->thumbnail_url ) )
		? esc_url_raw( (string) $data->thumbnail_url )
		: '';

	/**
	 * Filter how long a video's poster URL is cached.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $ttl       Seconds. Default one week.
	 * @param string $video_url The video URL.
	 */
	$ttl = (int) apply_filters( 'wpss_video_thumbnail_cache_ttl', WEEK_IN_SECONDS, $video_url );

	set_transient( $key, $thumbnail, max( HOUR_IN_SECONDS, $ttl ) );

	return $thumbnail;
}

/**
 * The limits that govern how much a vendor can put on one service.
 *
 * Single source of truth. These used to live inside ServiceWizard, which meant
 * they reached the web wizard as a template var and reached nothing else - an
 * app building a create-service screen had no way to learn max_gallery before
 * the server rejected the eighth image. Defining them here lets the wizard and
 * the REST route read the same array instead of drifting apart, which is the
 * failure this codebase keeps paying for.
 *
 * Free ships conservative numbers; Pro raises them through the filters below.
 * -1 means unlimited.
 *
 * @since 1.7.1
 *
 * @return array<string,int> Limit key => maximum, or -1 for unlimited.
 */
function wpss_get_service_limits(): array {
	return array(
		/**
		 * Max pricing packages (tiers).
		 *
		 * Free: 3 (Basic, Standard, Premium)
		 * Pro: 3 (same, but more flexibility)
		 *
		 * @param int $max Maximum packages.
		 */
		'max_packages'     => apply_filters( 'wpss_service_max_packages', 3 ),

		/**
		 * Max gallery images (additional, not including main).
		 *
		 * Free: 4
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum gallery images. -1 for unlimited.
		 */
		'max_gallery'      => apply_filters( 'wpss_service_max_gallery', 4 ),

		/**
		 * Max video URLs.
		 *
		 * Free: 1
		 * Pro: 1 (not raised — Pro lifts gallery, extras, FAQ and requirements only)
		 *
		 * @param int $max Maximum videos.
		 */
		'max_videos'       => apply_filters( 'wpss_service_max_videos', 1 ),

		/**
		 * Max service extras (add-ons).
		 *
		 * Free: 3
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum extras. -1 for unlimited.
		 */
		'max_extras'       => apply_filters( 'wpss_service_max_extras', 3 ),

		/**
		 * Max FAQs.
		 *
		 * Free: 5
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum FAQs. -1 for unlimited.
		 */
		'max_faq'          => apply_filters( 'wpss_service_max_faq', 5 ),

		/**
		 * Max buyer requirements.
		 *
		 * Free: 5
		 * Pro: Unlimited (-1)
		 *
		 * @param int $max Maximum requirements. -1 for unlimited.
		 */
		'max_requirements' => apply_filters( 'wpss_service_max_requirements', 5 ),

		/**
		 * Max service tags.
		 *
		 * The save paths already cut the list to this many; it lives here so
		 * the wizard can warn the vendor before their extra tags are dropped,
		 * and so a site can raise the cap like any other limit.
		 *
		 * @since 1.7.2
		 *
		 * @param int $max Maximum tags.
		 */
		'max_tags'         => apply_filters( 'wpss_service_max_tags', 5 ),
	);
}

/**
 * The numeric floors a service must clear to go live.
 *
 * Exists so the wizard's client-side checklist and the server-side validator
 * cannot hold different numbers. The validator below reads these, and
 * ServiceWizard hands the same array to the browser - #10304336130 was filed
 * because the 120-character floor was written out as a literal in four places.
 *
 * @since 1.7.1
 *
 * @return array{title_length:int,description_length:int,min_price:float}
 */
function wpss_service_publish_thresholds(): array {
	return array(
		'title_length'       => 10,
		'description_length' => 120,
		'min_price'          => (float) apply_filters( 'wpss_min_service_price', 5 ),
	);
}

/**
 * The rules a service must satisfy before buyers can see it.
 *
 * The one place those rules live. The frontend wizard enforced six of them
 * server-side while the admin metabox enforced none, so a site owner could
 * publish straight from wp-admin what the wizard refused from the vendor
 * dashboard: no category, no main image, no delivery time, and a price under
 * the minimum. Verified on a live install - a $2.00 service with no category
 * and no image published and appeared on /services/, auto-approved, because
 * admin-created services bypass moderation too. See Basecamp 10289819803.
 *
 * Callers pass what they have; a key that is absent is not checked, so a
 * partial save path can validate the subset it owns.
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $service {
 *     The service fields to check.
 *
 *     @type string                         $title        Service title.
 *     @type array<int,int>                 $category_ids Assigned category term ids.
 *     @type string                         $description  Service description.
 *     @type array<int,array<string,mixed>> $packages     Package rows with price + delivery_days.
 *     @type int                            $thumbnail_id Main image attachment id.
 * }
 * @return string[] User-facing error sentences; empty when the service may go live.
 */
function wpss_validate_service_publishable( array $service ): array {
	$errors = array();

	$thresholds = wpss_service_publish_thresholds();

	if ( array_key_exists( 'title', $service ) ) {
		$title = trim( (string) $service['title'] );
		if ( '' === $title ) {
			$errors[] = __( 'Please enter a service title.', 'wp-sell-services' );
		} elseif ( mb_strlen( $title ) < $thresholds['title_length'] ) {
			$errors[] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Please enter at least %d characters for the service title.', 'wp-sell-services' ),
				$thresholds['title_length']
			);
		}
	}

	if ( array_key_exists( 'category_ids', $service ) && empty( $service['category_ids'] ) ) {
		$errors[] = __( 'Please select a category.', 'wp-sell-services' );
	}

	if ( array_key_exists( 'description', $service ) ) {
		$description = trim( wp_strip_all_tags( (string) $service['description'] ) );
		if ( mb_strlen( $description ) < $thresholds['description_length'] ) {
			$errors[] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Description must be at least %d characters.', 'wp-sell-services' ),
				$thresholds['description_length']
			);
		}
	}

	if ( array_key_exists( 'packages', $service ) ) {
		$packages = array_filter(
			(array) $service['packages'],
			static function ( $package ) {
				if ( ! is_array( $package ) ) {
					return false;
				}

				// A tier the vendor switched off is not part of the offer, so it
				// must not be validated. The wizard keeps placeholder objects for
				// Standard and Premium with their names already filled in and
				// their prices empty; without this guard the placeholder passed
				// the name test below, then won "cheapest" at price 0 and had its
				// own empty fields reported against Basic - which is why a
				// Basic-only service could not be published at all.
				//
				// Checked with array_key_exists so callers that never send the
				// key (the admin metabox) keep their previous behaviour.
				if ( array_key_exists( 'enabled', $package ) && ! $package['enabled'] ) {
					return false;
				}

				return ! empty( $package['name'] ) || ! empty( $package['price'] );
			}
		);

		if ( empty( $packages ) ) {
			$errors[] = __( 'Please add at least one package.', 'wp-sell-services' );
		} else {
			// The cheapest package is the price buyers see on the card, so it is
			// the one the floor applies to - and it is the wizard's Basic tier by
			// construction.
			$min_price = $thresholds['min_price'];
			$cheapest  = null;

			foreach ( $packages as $package ) {
				if ( null === $cheapest || (float) ( $package['price'] ?? 0 ) < (float) ( $cheapest['price'] ?? 0 ) ) {
					$cheapest = $package;
				}
			}

			if ( (float) ( $cheapest['price'] ?? 0 ) < $min_price ) {
				$errors[] = sprintf(
					/* translators: %s: formatted minimum price (e.g. $5.00). */
					__( 'Basic package price must be at least %s.', 'wp-sell-services' ),
					wpss_format_price( $min_price )
				);
			}

			/*
			 * Every enabled tier, not only the cheapest one.
			 *
			 * The delivery and name checks used to run against $cheapest alone,
			 * so an enabled Standard or Premium with a price but no delivery
			 * time - or no name - published without complaint, and the buyer was
			 * shown a purchasable tier with no date on it. The price floor stays
			 * on the cheapest deliberately: that is the lowest a buyer can pay,
			 * so checking the minimum covers every tier at once (Basecamp
			 * 10320551446).
			 */
			$position = 0;

			foreach ( $packages as $key => $package ) {
				++$position;
				$name = trim( (string) ( $package['name'] ?? '' ) );

				/*
				 * The wizard keys packages by tier ('basic', 'standard'), REST
				 * and the metabox key them numerically. Naming a tier "the 1
				 * package" helps nobody, so fall back to its position instead.
				 */
				$label = '' !== $name
					? $name
					: ( is_numeric( $key ) ? '' : ucfirst( (string) $key ) );

				if ( '' === $name ) {
					$errors[] = '' !== $label
						? sprintf(
							/* translators: %s: package tier name (e.g. Standard). */
							__( 'Please name the %s package.', 'wp-sell-services' ),
							$label
						)
						: sprintf(
							/* translators: %d: position of the package in the list, starting at 1. */
							__( 'Please name package %d.', 'wp-sell-services' ),
							$position
						);
				}

				if ( empty( $package['delivery_days'] ) ) {
					$errors[] = '' !== $label
						? sprintf(
							/* translators: %s: package name or tier (e.g. Standard). */
							__( 'Please set a delivery time for the %s package.', 'wp-sell-services' ),
							$label
						)
						: sprintf(
							/* translators: %d: position of the package in the list, starting at 1. */
							__( 'Please set a delivery time for package %d.', 'wp-sell-services' ),
							$position
						);
				}
			}
		}
	}

	if ( array_key_exists( 'thumbnail_id', $service ) && empty( $service['thumbnail_id'] ) ) {
		$errors[] = __( 'Please upload a main image.', 'wp-sell-services' );
	}

	/**
	 * Filter the reasons a service may not go live.
	 *
	 * @since 1.7.1
	 *
	 * @param string[]            $errors  Error sentences.
	 * @param array<string,mixed> $service The validated input.
	 */
	return apply_filters( 'wpss_service_publish_errors', $errors, $service );
}

/**
 * Truncate a service's lists to wpss_get_service_limits().
 *
 * The one enforcer for every save path (wizard, REST, admin metabox,
 * ServiceManager). Pass whichever of the keyed lists the caller has; each is
 * cut to its cap and reported back so the caller can tell the user.
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $meta Any of packages, gallery, extras, faqs, requirements => list.
 * @return array{meta: array<string,mixed>, truncated: array<string,string>} The capped lists and, per cut key, a user-facing sentence.
 */
function wpss_enforce_service_limits( array $meta ): array {
	$limits = wpss_get_service_limits();
	$rules  = array(
		'packages'     => array( 'max_packages', __( 'packages', 'wp-sell-services' ) ),
		'gallery'      => array( 'max_gallery', __( 'additional gallery images', 'wp-sell-services' ) ),
		'extras'       => array( 'max_extras', __( 'extras', 'wp-sell-services' ) ),
		'faqs'         => array( 'max_faq', __( 'FAQs', 'wp-sell-services' ) ),
		'requirements' => array( 'max_requirements', __( 'requirements', 'wp-sell-services' ) ),
		// The wizard enforces the tag cap and REST did not, so the same service
		// could carry more tags depending on which surface created it.
		'tags'         => array( 'max_tags', __( 'tags', 'wp-sell-services' ) ),
	);

	$truncated = array();

	/*
	 * The gallery is keyed on the attachment id wherever it is rendered - most
	 * sharply in the wizard, whose x-for keys on image.id, where a repeated id
	 * breaks Alpine's reconciliation outright. A duplicate saved through REST
	 * therefore did not show up until the vendor next opened their own service
	 * to edit it, and then broke that screen. Collapse repeats at the single
	 * point every save path passes through, before the cap is applied, so the
	 * cap counts distinct images.
	 */
	if ( ! empty( $meta['gallery'] ) && is_array( $meta['gallery'] ) ) {
		$unique_gallery = array_values( array_unique( array_map( 'absint', $meta['gallery'] ) ) );

		if ( count( $unique_gallery ) !== count( $meta['gallery'] ) ) {
			$truncated['gallery_duplicates'] = __( 'The same image was listed more than once; the repeats were not saved.', 'wp-sell-services' );
		}

		$meta['gallery'] = $unique_gallery;
	}

	foreach ( $rules as $key => [ $limit_key, $label ] ) {
		$max = (int) ( $limits[ $limit_key ] ?? -1 );
		// Every save path stores the main image as the first gallery entry and
		// max_gallery counts the additional ones, so the list may hold one more.
		$cap = 'gallery' === $key ? $max + 1 : $max;

		if ( $max < 0 || empty( $meta[ $key ] ) || ! is_array( $meta[ $key ] ) || count( $meta[ $key ] ) <= $cap ) {
			continue;
		}

		$meta[ $key ]      = array_slice( $meta[ $key ], 0, $cap, true );
		$truncated[ $key ] = sprintf(
			/* translators: 1: maximum count, 2: list name (packages, gallery images, extras, FAQs, requirements) */
			__( 'A service can have at most %1$d %2$s; the extra entries were not saved.', 'wp-sell-services' ),
			$max,
			$label
		);
	}

	return array(
		'meta'      => $meta,
		'truncated' => $truncated,
	);
}

/**
 * Whether the current user may set a service's Featured flag.
 *
 * Featured is marketplace curation, not service authoring: `_wpss_featured` is
 * what the Featured Services block and `[wpss_featured_services]` select on, so
 * it decides who appears in the promoted slot on the marketplace's front page.
 * `edit_post` is therefore the wrong gate - every vendor holds it on their own
 * service, which would let any vendor promote themselves above everyone else.
 * The owner's capability is the default; a site running paid placement or a
 * vendor tier lowers it through the filter rather than by editing the metabox.
 *
 * @since 1.7.1
 *
 * @param int $service_id Service post ID.
 * @return bool
 */
function wpss_user_can_feature_service( int $service_id = 0 ): bool {
	$can = current_user_can( 'manage_options' );

	/**
	 * Filter who may mark a service as Featured.
	 *
	 * @since 1.7.1
	 *
	 * @param bool $can        Whether the current user may set the flag.
	 * @param int  $service_id Service post ID.
	 */
	return (bool) apply_filters( 'wpss_user_can_feature_service', $can, $service_id );
}

/**
 * A buyer's cart, with items whose service is no longer purchasable removed.
 *
 * Adding to the cart validates properly - add_to_cart() requires the service
 * to exist, be a wpss_service, and be published. Nothing re-checked on the way back out, and
 * ten call sites read `_wpss_cart` straight from user meta, so an item whose
 * service was deleted, trashed or paused AFTER it was added survived, rendered
 * and could be bought.
 *
 * It was bought. A 2026-09-23 smoke left this behind:
 *
 *     order #583  service_id=1604  total=0.000  status=pending_requirements
 *
 * Service 1604 no longer existed. The platform created a real order row for
 * it, at zero, parked in a status that waits on the buyer forever. On a live
 * marketplace the same thing happens when a vendor simply PAUSES a service -
 * a documented feature - and the vendor receives an order they cannot fulfil
 * for a price that never reached them (Basecamp 10330917388).
 *
 * Guarding the cart endpoint alone would have fixed the screen and left the
 * checkout path that actually creates the order untouched, so the check lives
 * here and every reader calls it.
 *
 * @since 1.7.2
 *
 * @param  int  $user_id     Buyer.
 * @param  bool $keep_paused Keep paused/unpublished items, marked unavailable,
 *                           so the buyer is told rather than watching the cart
 *                           empty itself. Deleted services are always dropped -
 *                           there is nothing to come back to. Checkout passes
 *                           false: an unavailable item must never reach an order.
 * @return array<string, array<string, mixed>> Cart items keyed as stored.
 */
function wpss_get_user_cart( int $user_id, bool $keep_paused = false ): array {
	$cart = get_user_meta( $user_id, '_wpss_cart', true );

	if ( ! is_array( $cart ) ) {
		return array();
	}

	$out     = array();
	$changed = false;

	foreach ( $cart as $key => $item ) {
		$service = get_post( (int) ( $item['service_id'] ?? 0 ) );

		$reason = wpss_service_unavailable_reason( (int) ( $item['service_id'] ?? 0 ) );

		if ( '' !== $reason ) {
			/*
			 * Never remove a line the buyer did not remove.
			 *
			 * This used to drop a DELETED service silently and keep only a
			 * paused one. On the cart screen that is indistinguishable from the
			 * site losing the item: a two-item $100 cart became one item and $75
			 * with nothing said. The WooCommerce rail was corrected first and
			 * this one was left behind - the same "fixed one rail, missed the
			 * other" mistake, on the rail every FREE install runs.
			 *
			 * Deleted and paused now read the same way on both rails: the line
			 * stays, says why it cannot be bought, is kept out of the subtotal,
			 * and is refused at checkout until the buyer removes it.
			 *
			 * The money paths still pass $keep_paused = false and drop it, so an
			 * unavailable line can never reach an order.
			 */
			if ( ! $keep_paused ) {
				$changed = true;
				continue;
			}

			$item['unavailable']        = true;
			$item['unavailable_reason'] = $reason;
		}

		$out[ $key ] = $item;
	}

	// Persist only the removal of genuinely dead rows, so a paused service
	// coming back does not find the buyer's cart already emptied.
	if ( $changed && ! $keep_paused ) {
		update_user_meta( $user_id, '_wpss_cart', $out );
	}

	return $out;
}

/**
 * Why a service cannot be bought right now, or '' when it can.
 *
 * The ONE place either rail asks "is this still purchasable". The standalone
 * cart reads it through wpss_get_user_cart(); the WooCommerce cart reads it
 * through the Pro adapter's cart re-check. Two copies of this rule is how the
 * two rails drift, and the drift already happened once: the standalone cart was
 * fixed on 2026-09-23 and WooCommerce - the rail most sites actually run - kept
 * selling unpublished services for another afternoon, because "the cart" meant
 * a different thing there and nobody asked the question twice.
 *
 * @since 1.7.2
 *
 * @param  int $service_id Service post ID.
 * @return string Empty when purchasable; otherwise a sentence for the buyer.
 */
function wpss_service_unavailable_reason( int $service_id ): string {
	$service = $service_id ? get_post( $service_id ) : null;

	if ( ! $service || 'wpss_service' !== $service->post_type ) {
		return __( 'This service is no longer offered.', 'wp-sell-services' );
	}

	if ( 'publish' !== $service->post_status ) {
		return __( 'This service is not currently available.', 'wp-sell-services' );
	}

	/**
	 * Let an integration refuse a service for its own reason.
	 *
	 * @since 1.7.2
	 *
	 * @param string $reason     Empty when purchasable.
	 * @param int    $service_id Service post ID.
	 */
	return (string) apply_filters( 'wpss_service_unavailable_reason', '', $service_id );
}
