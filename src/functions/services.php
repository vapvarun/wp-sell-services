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

		$out[] = array(
			'title'               => $title,
			'description'         => sanitize_textarea_field( (string) ( $addon['description'] ?? '' ) ),
			'price'               => (float) ( $addon['price'] ?? 0 ),
			'delivery_days_extra' => absint( $addon['delivery_days_extra'] ?? $addon['extra_days'] ?? $addon['delivery_time'] ?? 0 ),
			'field_type'          => in_array( $field_type, array( 'checkbox', 'quantity', 'dropdown', 'text' ), true ) ? $field_type : 'checkbox',
			'price_type'          => in_array( $price_type, array( 'flat', 'percentage', 'quantity_based' ), true ) ? $price_type : 'flat',
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
 * Resolve addon data from checkout POST data.
 *
 * Reads addon_ids from $_POST, validates each addon belongs to the service
 * and is active, then returns addon details and total for create_order().
 *
 * @since 1.1.0
 *
 * @param int    $service_id Service post ID.
 * @param string $addon_ids  Optional. Comma-separated add-on indices; overrides the request.
 * @return array{addons: array, addons_total: float, delivery_days_extra: int}
 */
function wpss_resolve_checkout_addons( int $service_id, string $addon_ids = '' ): array {
	$result = array(
		'addons'              => array(),
		'addons_total'        => 0,
		'delivery_days_extra' => 0,
	);

	// Try pre-resolved addons_data first (sent by checkout form as JSON).
	// Skipped when the caller names the ids itself (a gateway return leg
	// re-resolving from its own metadata): those are priced from post meta.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by calling gateway.
	$addons_json = ( '' === $addon_ids && isset( $_POST['addons_data'] ) ) ? sanitize_text_field( wp_unslash( $_POST['addons_data'] ) ) : '';
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

	// Fallback: resolve from addon_ids (indices into _wpss_addons post meta).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by calling gateway.
	$addon_ids_raw = '' !== $addon_ids ? $addon_ids : ( isset( $_POST['addon_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['addon_ids'] ) ) : '' );

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
		$extra_days                     = (int) $extra['delivery_days_extra'];
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
		'order'          => in_array( ( $attributes['order'] ?? 'DESC' ), array( 'ASC', 'DESC' ), true ) ? $attributes['order'] : 'DESC',
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
	);
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
	);

	$truncated = array();

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
