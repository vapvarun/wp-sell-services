<?php
/**
 * Vendors: profile lookup, status, vendor pages and display names.
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
 * Get vendor profile by user ID.
 *
 * @param int $user_id WordPress user ID.
 * @return \WPSellServices\Models\VendorProfile|null
 */
function wpss_get_vendor( int $user_id ): ?\WPSellServices\Models\VendorProfile {
	return \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
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
 * Get services for a vendor.
 *
 * @since 1.2.0
 *
 * @param int   $vendor_id Vendor user ID.
 * @param array $args      Query arguments (limit, offset, status).
 * @return \WPSellServices\Models\Service[] Array of hydrated service models.
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

	// Hydrate to Service models rather than returning raw WP_Post objects.
	//
	// The sole caller (StandaloneAccountProvider::render_vendor_services) was
	// written against the model - it calls get_starting_price() and reads
	// ->title / ->id / ->thumbnail_id, none of which exist on WP_Post. That made
	// the [wpss_account] "My Services" screen a hard fatal
	// ("Call to undefined method WP_Post::get_starting_price()") for any vendor
	// with at least one published service, and silently blanked the title,
	// thumbnail and both action links besides.
	return array_map(
		array( \WPSellServices\Models\Service::class, 'from_post' ),
		get_posts( $query_args )
	);
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
 * Display name for a member who may no longer exist.
 *
 * Orders, reviews, messages and disputes outlive the people in them. A member
 * can delete their account while a counterparty still holds a completed order
 * that has to keep showing what was bought, for how much, from whom — see
 * `AccountDeletionService`, which keeps those rows precisely so the other side
 * does not lose their history.
 *
 * That leaves every display surface holding a user id with no user behind it.
 * `get_userdata()` returns false there, and `false->display_name` is a fatal on
 * PHP 8. So this is the one way a member's name should ever be resolved for
 * output: it always returns a printable string.
 *
 * @since 1.5.2
 *
 * @param int    $user_id User ID.
 * @param string $fallback Optional override for the gone-member label.
 * @return string Display name, never empty.
 */
function wpss_get_member_display_name( int $user_id, string $fallback = '' ): string {
	$user = $user_id > 0 ? get_userdata( $user_id ) : false;

	if ( $user instanceof WP_User ) {
		$name = trim( (string) $user->display_name );

		if ( '' === $name ) {
			$name = trim( (string) $user->user_login );
		}

		if ( '' !== $name ) {
			/** This filter is documented below. */
			return (string) apply_filters( 'wpss_member_display_name', $name, $user_id, true );
		}
	}

	if ( '' === $fallback ) {
		$fallback = __( 'Deleted member', 'wp-sell-services' );
	}

	/**
	 * Filter the display name shown for a member.
	 *
	 * @since 1.5.2
	 *
	 * @param string $name    The name to display.
	 * @param int    $user_id User ID.
	 * @param bool   $exists  Whether the user still exists.
	 */
	return (string) apply_filters( 'wpss_member_display_name', $fallback, $user_id, false );
}
