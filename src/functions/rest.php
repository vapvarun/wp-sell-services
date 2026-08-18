<?php
/**
 * REST helpers: request detection, permission callbacks and response shaping.
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
 * @since 1.6.0 Denials carry `wpss_not_admin`, not `wpss_not_owner`.
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

	// One code for one condition - the same rule the vendor gate below already
	// follows. This used to answer `wpss_not_owner`, which everywhere else in
	// the API means "you do not own THIS resource" (a portfolio item, an order,
	// a review). Lacking a capability is a different condition with a different
	// remedy, and a client could not tell the two apart without reading the
	// English message.
	//
	// Measured before the change: `wpss_not_owner` was the answer for the
	// moderation queue, the moderation count, analytics and the Pro
	// commission-rules and white-label endpoints - five capability failures
	// wearing an ownership code (Basecamp #10154921558).
	return new WP_Error(
		'wpss_not_admin',
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
 * A DELETED member still gets a shape. Only a genuinely absent actor - id 0,
 * meaning the marketplace itself acted - yields null.
 *
 * That distinction is the point. Orders, reviews, messages and disputes outlive
 * the people in them by design (see AccountDeletionService), so returning null
 * for a departed member told every client "nobody did this", and a timeline
 * entry that plainly WAS somebody's doing rendered as a system event. The
 * client needs to know a person acted, that we can no longer name them, and
 * that there is no profile behind the name to link to - hence `deleted`.
 *
 * @since 1.4.0
 *
 * @param int $user_id User ID. 0 yields null; an unknown user yields a
 *                     placeholder shape with `deleted` set.
 * @return array<string, mixed>|null
 */
function wpss_rest_user( int $user_id ): ?array {
	if ( $user_id <= 0 ) {
		return null;
	}

	$user = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return array(
			'id'      => $user_id,
			'name'    => wpss_get_member_display_name( $user_id ),
			'avatar'  => get_avatar_url( $user_id ),
			'deleted' => true,
		);
	}

	return array(
		'id'      => $user_id,
		'name'    => wpss_rest_text( $user->display_name ),
		'avatar'  => get_avatar_url( $user_id ),
		'deleted' => false,
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
 * A service's images for the REST API.
 *
 * Moved out of ServicesController so the ONE card shape below can be built
 * anywhere. It was a private method, which is why /favorites could not reuse it
 * and invented a single `thumbnail` string instead.
 *
 * @since 1.6.0
 *
 * @param int $service_id Service post ID.
 * @return array<int, array<string, mixed>> Images, featured first.
 */
function wpss_rest_service_images( int $service_id ): array {
	$images       = array();
	$thumbnail_id = get_post_thumbnail_id( $service_id );

	if ( $thumbnail_id ) {
		$images[] = array(
			'id'    => (int) $thumbnail_id,
			'url'   => wp_get_attachment_url( $thumbnail_id ),
			'sizes' => array(
				'thumbnail' => wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ),
				'medium'    => wp_get_attachment_image_url( $thumbnail_id, 'medium' ),
				'large'     => wp_get_attachment_image_url( $thumbnail_id, 'large' ),
			),
		);
	}

	$gallery_ids = wpss_get_gallery_ids( get_post_meta( $service_id, '_wpss_gallery', true ) );

	foreach ( $gallery_ids as $gallery_id ) {
		$gallery_id = (int) $gallery_id;

		// The gallery meta normally also contains the featured image; do not
		// ship it twice.
		if ( $gallery_id === (int) $thumbnail_id ) {
			continue;
		}

		$images[] = array(
			'id'    => $gallery_id,
			'url'   => wp_get_attachment_url( $gallery_id ),
			'sizes' => array(
				'thumbnail' => wp_get_attachment_image_url( $gallery_id, 'thumbnail' ),
				'medium'    => wp_get_attachment_image_url( $gallery_id, 'medium' ),
				'large'     => wp_get_attachment_image_url( $gallery_id, 'large' ),
			),
		);
	}

	return $images;
}

/**
 * A service's rating summary for the REST API.
 *
 * @since 1.6.0
 *
 * @param int $service_id Service post ID.
 * @return array{average: float, count: int}
 */
function wpss_rest_service_rating( int $service_id ): array {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_reviews';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rating = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix.
			"SELECT AVG(rating) AS average, COUNT(*) AS count FROM {$table} WHERE service_id = %d AND status = 'approved'",
			$service_id
		)
	);

	return array(
		'average' => $rating ? round( (float) $rating->average, 2 ) : 0.0,
		'count'   => $rating ? (int) $rating->count : 0,
	);
}

/**
 * THE service card shape.
 *
 * One definition, because there were three. /services returned `images[]`,
 * `pricing{}` and `rating{}`; /favorites returned a `thumbnail` string, a flat
 * `price` and a flat `rating` float; and neither used the shared actor shape for
 * the vendor. A client could not write one renderer for "a service card", which
 * is the whole complaint on Basecamp 10154919636.
 *
 * Terms are resolved through wpss_prepare_term_for_rest() and text through
 * wpss_rest_text(), so a category reaches a native client as "Graphics & Design"
 * rather than HTML-encoded.
 *
 * @since 1.6.0
 *
 * @param \WP_Post $service Service post.
 * @return array<string, mixed>
 */
function wpss_rest_service_card( \WP_Post $service ): array {
	$terms = wp_get_object_terms( $service->ID, 'wpss_service_category', array( 'fields' => 'all' ) );

	$categories = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$categories[] = wpss_prepare_term_for_rest( $term );
			}
		}
	}

	$tags = wp_get_object_terms( $service->ID, 'wpss_service_tag', array( 'fields' => 'names' ) );

	return array(
		'id'          => (int) $service->ID,
		'title'       => wpss_rest_text( $service->post_title ),
		'slug'        => $service->post_name,
		'description' => $service->post_content,
		'excerpt'     => $service->post_excerpt,
		'status'      => $service->post_status,
		'link'        => get_permalink( $service->ID ),
		'vendor'      => wpss_rest_user( (int) $service->post_author ),
		// Services carry no currency of their own - they are priced in the store
		// currency, which is the helper's default.
		'pricing'     => wpss_rest_money( 'base_price', (float) get_post_meta( $service->ID, '_wpss_starting_price', true ) ),
		'delivery'    => array(
			'time'      => wpss_get_service_delivery_days( $service->ID ) ?: 7,
			'revisions' => wpss_get_service_revisions( $service->ID ),
		),
		'images'      => wpss_rest_service_images( $service->ID ),
		'categories'  => $categories,
		'tags'        => is_wp_error( $tags ) ? array() : $tags,
		'rating'      => wpss_rest_service_rating( $service->ID ),
		'created_at'  => wpss_rest_date( $service->post_date_gmt ),
		'updated_at'  => wpss_rest_date( $service->post_modified_gmt ),
	);
}

/**
 * ISO-8601 for a JSON payload, from whatever the store happened to hold.
 *
 * The global twin of RestController::format_datetime(), for the shared shapes
 * above and for any caller that is not a controller. A bare MySQL string is
 * read as UTC, which is what WordPress stores in the *_gmt columns and in this
 * plugin's own tables.
 *
 * @since 1.6.0
 *
 * @param mixed $value DateTimeInterface, MySQL datetime string, or empty.
 * @return string|null ISO-8601 with offset, or null.
 */
function wpss_rest_date( $value ): ?string {
	if ( $value instanceof \DateTimeInterface ) {
		return $value->format( 'c' );
	}

	if ( ! is_string( $value ) || '' === $value || '0000-00-00 00:00:00' === $value ) {
		return null;
	}

	try {
		return ( new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) ) )->format( 'c' );
	} catch ( \Exception $e ) {
		return null;
	}
}

/**
 * Normalise any date-looking values inside a free-form payload.
 *
 * For blobs the API does not own the shape of - a notification's `data` column
 * holds whatever the producing service chose to store, and some producers store
 * a raw MySQL datetime. A client should not have to parse dates one way in the
 * payload and another inside a nested blob.
 *
 * Only keys that NAME a date are touched, and only when the value actually
 * looks like a MySQL datetime, so a field that happens to hold similar text is
 * left alone.
 *
 * @since 1.6.0
 *
 * @param array<string, mixed> $data Arbitrary stored data.
 * @return array<string, mixed>
 */
function wpss_rest_normalise_dates( array $data ): array {
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) {
			$data[ $key ] = wpss_rest_normalise_dates( $value );
			continue;
		}

		if ( ! is_string( $value ) || ! is_string( $key ) ) {
			continue;
		}

		$looks_like_a_date = (bool) preg_match( '/(_at|_on|_deadline|_date|date|since|expires)$/i', $key );

		if ( $looks_like_a_date && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			$data[ $key ] = wpss_rest_date( $value );
		}
	}

	return $data;
}
