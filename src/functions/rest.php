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
