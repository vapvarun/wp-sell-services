<?php
/**
 * Service catalog: galleries, sorting, category terms and the shared services grid.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

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
 * Meta key behind each service sort that orders by a stored number.
 *
 * @since 1.8.0
 *
 * @return array<string,array{key:string,order:string}> Sort slug => meta key and direction.
 */
function wpss_service_meta_sorts(): array {
	return array(
		'price_low'   => array(
			'key'   => '_wpss_starting_price',
			'order' => 'ASC',
		),
		'price_high'  => array(
			'key'   => '_wpss_starting_price',
			'order' => 'DESC',
		),
		'rating'      => array(
			'key'   => '_wpss_rating_average',
			'order' => 'DESC',
		),
		'rating_low'  => array(
			'key'   => '_wpss_rating_average',
			'order' => 'ASC',
		),
		'popular'     => array(
			'key'   => '_wpss_order_count',
			'order' => 'DESC',
		),
		'popular_low' => array(
			'key'   => '_wpss_order_count',
			'order' => 'ASC',
		),
	);
}

/**
 * Apply a catalog sort to WP_Query args without dropping services.
 *
 * Ordering by `meta_key` INNER JOINs the meta, so a service with no rating or
 * no completed order vanished from the list: "Highest Rated" showed 31 of 402.
 * (An EXISTS / NOT EXISTS meta clause cannot fix it - WP_Query then orders by an
 * arbitrary unrelated meta row for the services that lack the key.) This tags
 * the query with `wpss_service_sort`; wpss_service_sort_clauses() LEFT JOINs the
 * one key, so every service stays and those without the value sort last, newest
 * first among them. Every surface that sorts services - storefront, search,
 * REST, shortcodes - calls this one function; do not write another meta_key sort.
 *
 * @since 1.8.0
 *
 * @param array<string,mixed> $args WP_Query args.
 * @param string              $sort newest, price_low, price_high, rating, rating_low, popular or popular_low. Anything else leaves $args untouched.
 * @return array<string,mixed> The args with the sort applied.
 */
function wpss_apply_service_sort( array $args, string $sort ): array {
	if ( 'newest' === $sort ) {
		$args['orderby'] = array( 'date' => 'DESC' );
		unset( $args['meta_key'], $args['order'], $args['wpss_service_sort'] );
		return $args;
	}

	if ( ! isset( wpss_service_meta_sorts()[ $sort ] ) ) {
		return $args;
	}

	if ( ! has_filter( 'posts_clauses', 'wpss_service_sort_clauses' ) ) {
		add_filter( 'posts_clauses', 'wpss_service_sort_clauses', 10, 2 );
	}

	$args['wpss_service_sort'] = $sort;
	$args['orderby']           = array( 'date' => 'DESC' ); // Fallback only; the clause below leads.
	unset( $args['meta_key'], $args['order'] );

	return $args;
}

/**
 * `posts_clauses` half of wpss_apply_service_sort().
 *
 * @since 1.8.0
 *
 * @param array<string,string> $clauses SQL clauses.
 * @param \WP_Query            $query   The query.
 * @return array<string,string>
 */
function wpss_service_sort_clauses( array $clauses, \WP_Query $query ): array {
	global $wpdb;

	$sorts = wpss_service_meta_sorts();
	$sort  = (string) $query->get( 'wpss_service_sort' );

	if ( ! isset( $sorts[ $sort ] ) ) {
		return $clauses;
	}

	$clauses['join'] .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS wpss_sort ON ( wpss_sort.post_id = {$wpdb->posts}.ID AND wpss_sort.meta_key = %s )", $sorts[ $sort ]['key'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// A service holding the key twice must still list once.
	if ( false === strpos( $clauses['groupby'], 'ID' ) ) {
		$clauses['groupby'] = trim( "{$wpdb->posts}.ID " . ( '' !== $clauses['groupby'] ? ', ' . $clauses['groupby'] : '' ) );
	}
	$clauses['orderby'] = 'wpss_sort.meta_value IS NULL, CAST( wpss_sort.meta_value AS DECIMAL( 12, 2 ) ) ' . $sorts[ $sort ]['order'] . ", {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";

	return $clauses;
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

	// "rating", "sales" and "price" are stored numbers, sorted by the one
	// function every listing uses. This grid ordered by meta_key instead, which
	// drops a service that has no such row: "rating" listed 5 of 12, and
	// "sales" none at all, because it read _wpss_total_sales, a key nothing
	// writes (Basecamp 10375174916).
	$sorts = array(
		'rating' => 'ASC' === $args['order'] ? 'rating_low' : 'rating',
		'sales'  => 'ASC' === $args['order'] ? 'popular_low' : 'popular',
		'price'  => 'ASC' === $args['order'] ? 'price_low' : 'price_high',
	);

	if ( isset( $sorts[ $args['orderby'] ] ) ) {
		$args = wpss_apply_service_sort( $args, $sorts[ $args['orderby'] ] );
	}

	// Same catalog rule as the storefront, search and REST: a vendor on
	// vacation is not listed. A grid of one named vendor is that vendor's own
	// page and keeps their services (Basecamp 10375176013).
	if ( empty( $args['author'] ) ) {
		$args['author__not_in'] = wpss_get_vacation_vendor_ids();
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
