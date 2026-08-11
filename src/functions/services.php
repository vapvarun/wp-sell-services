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
