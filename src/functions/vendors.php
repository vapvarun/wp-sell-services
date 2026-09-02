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
 * A vendor's profile, defaulted when they have no row yet.
 *
 * `wpss_get_vendor()` answers "is there a profile row", which is not the same
 * question as "is this a vendor". A member can hold the vendor role with no row
 * at all - granted by an admin, promoted by a filter, or created by the demo
 * seeder - and on those accounts the profile page rendered "Vendor not found."
 * about somebody the plugin's own `wpss_is_vendor()` says is a seller.
 *
 * This is the same lesson as Basecamp 10208142467, where the Become a Vendor
 * page offered "Register as Vendor" to people who were already vendors: the
 * canonical answer is `wpss_is_vendor()`, and a missing row is an empty profile,
 * not a missing person.
 *
 * Returns null only when the user genuinely is not a vendor - callers that need
 * to know whether a row exists should keep using `wpss_get_vendor()`.
 *
 * @since 1.7.0
 *
 * @param int $user_id User ID.
 * @return \WPSellServices\Models\VendorProfile|null Profile, a defaulted one, or null.
 */
function wpss_get_vendor_profile_or_default( int $user_id ): ?\WPSellServices\Models\VendorProfile {
	$profile = wpss_get_vendor( $user_id );

	if ( $profile ) {
		return $profile;
	}

	if ( ! wpss_is_vendor( $user_id ) ) {
		return null;
	}

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return null;
	}

	// Built through from_db() rather than by setting twenty properties by hand:
	// it already defaults every column it does not find, so a new field added to
	// the model is defaulted here too instead of being silently unset.
	return \WPSellServices\Models\VendorProfile::from_db(
		(object) array(
			'user_id'      => $user_id,
			'display_name' => $user->display_name,
		)
	);
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
 * A vendor is a user with an ACTIVE wpss_vendor_profiles row - the record the
 * application, approval and admin screens all write. The role and the
 * wpss_vendor capability are hints, not proof: an admin can hand out the role
 * with no profile behind it, and up to 1.7.0 every blog author carried the
 * capability, so both answered "vendor" for users who had never applied and
 * had nothing to sell from. The profile lookup is memoised per request by
 * VendorProfile::get_by_user_id(), so this stays one query per user.
 *
 * @since 1.7.1 Reads the profile row instead of the capability/role/meta.
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

	$is_vendor = 'active' === wpss_get_vendor_status( $user_id );

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
 * `wpss_pages['vendors_page']` was once read in three places and written in
 * none: no settings field offered it, the installer did not seed it, and the
 * legacy `wpss_vendors_page` option was equally write-only. The key was
 * therefore permanently 0 on every install, which made `GET /settings` report
 * `pages.vendors = 0` and `page_urls.vendors = null` even on sites that plainly
 * HAVE a directory page. Both writers now exist: `Activator::create_pages()`
 * seeds the page on install and upgrade, and Settings -> Pages maps it.
 *
 * This is the single resolver for that page, in the order a site actually
 * carries the answer:
 *
 * 1. the mapped page (Settings -> Pages, or what the installer seeded),
 * 2. the legacy standalone option, for sites mapped before the page map,
 * 3. auto-discovery of a published page carrying `[wpss_vendors]` — which is
 *    what a site owner builds when they want their own directory instead of
 *    ours — persisted back into the page map so every reader (and the admin UI)
 *    agrees from then on.
 *
 * Returns 0 only when the site genuinely has no vendor directory; callers must
 * treat that as "no such page" rather than as an error.
 *
 * @since 1.6.0
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
	 * @since 1.6.0
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
 * @since 1.6.0
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
 * @since 1.6.0
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
	 * @since 1.6.0
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
 * An empty status means the user has no `wpss_vendor_profiles` row at all: a
 * role handed out with no application behind it. That is not a vendor, so it
 * is refused with its own code and the dashboard's "Start selling" panel is
 * the way forward. Every real vendor path (register, approve, demo seeders)
 * writes the row.
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

	if ( 'active' === $status ) {
		return null;
	}

	if ( '' === $status ) {
		return new WP_Error(
			'wpss_not_vendor',
			__( 'You are not registered as a vendor.', 'wp-sell-services' ),
			array( 'status' => 403 )
		);
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

/**
 * Prime the caches every vendor card reads, in one pass.
 *
 * The vendor-card partial (templates/partials/vendor-card.php) resolves three
 * things per card, and each
 * was a query of its own: get_userdata(), wpss_get_vendor() and
 * wpss_get_vendor_last_delivery(). Measured on an 8-card grid that was 8 user
 * selects, 8 usermeta primes, 8 profile selects and 8 MAX(completed_at) scans.
 *
 * Priming them together turns that into roughly four queries regardless of how
 * many vendors are shown. Each underlying lookup is memoised, so the card code
 * is unchanged - it simply stops hitting the database.
 *
 * @since 1.5.1
 *
 * @param int[] $vendor_ids Vendor user IDs about to be rendered.
 * @return void
 */
function wpss_prime_vendor_card_caches( array $vendor_ids ): void {
	$vendor_ids = array_values( array_unique( array_filter( array_map( 'intval', $vendor_ids ) ) ) );

	if ( empty( $vendor_ids ) ) {
		return;
	}

	// Core batches users AND their meta into two queries.
	cache_users( $vendor_ids );

	\WPSellServices\Models\VendorProfile::prime( $vendor_ids );

	( new \WPSellServices\Database\Repositories\OrderRepository() )->prime_last_completed_dates( $vendor_ids );

	// Avatars are attachments, and an attachment is a post: the avatar filter
	// resolves the ID cheaply (it caches per request) but then calls
	// wp_get_attachment_image_url() on it, and THAT fetches a post plus its
	// meta one at a time. Priming them together removes two queries per vendor
	// who has an avatar.
	//
	// The two sources and their precedence mirror the resolver in
	// Plugin::define_avatar_filter(): the `_wpss_avatar_id` user meta wins, and
	// the vendor profile's avatar_id is the fallback. If that precedence ever
	// changes there, change it here too - priming the wrong ID is only a missed
	// optimisation, never a wrong avatar, because the filter still does the
	// real resolution.
	//
	// Both reads are already free: cache_users() primed the user meta and
	// VendorProfile::prime() primed the profiles, both above.
	$avatar_ids = array();
	foreach ( $vendor_ids as $vendor_id ) {
		$meta_avatar = (int) get_user_meta( $vendor_id, '_wpss_avatar_id', true );

		if ( $meta_avatar ) {
			$avatar_ids[] = $meta_avatar;
			continue;
		}

		$profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $vendor_id );
		if ( $profile && $profile->avatar_id ) {
			$avatar_ids[] = (int) $profile->avatar_id;
		}
	}

	// No array_filter(): both branches above only push a value they have already
	// checked is truthy, so there is nothing falsy left to strip.
	$avatar_ids = array_values( array_unique( $avatar_ids ) );

	if ( $avatar_ids ) {
		_prime_post_caches( $avatar_ids, false, true );
	}
}

/**
 * Whether a member is exempt from vendor selling limits.
 *
 * The site owner is not one of their own subscribers. An administrator seeding
 * demo content, importing a catalogue, or building services for a client met
 * their own paywall: the create-service wizard rendered "You have reached your
 * service limit" and POST /wpss/v1/services answered 403, with no admin-side
 * explanation of why (Basecamp 10212521285).
 *
 * ONE definition, because there are TWO independent limit gates on
 * `wpss_vendor_can_create_service` - this plugin's per-profile maximum at
 * priority 10, and Pro's subscription plan limit at priority 20. The card was
 * filed against Pro's, but a fix there alone would still leave an owner blocked
 * by this plugin's gate, which runs first. Both call this.
 *
 * @since 1.6.0
 *
 * @param int $user_id Member being checked.
 * @return bool True when selling limits do not apply to this member.
 */
function wpss_member_bypasses_limits( int $user_id ): bool {
	$bypasses = $user_id > 0 && user_can( $user_id, 'manage_options' );

	/**
	 * Filter whether a member is exempt from vendor selling limits.
	 *
	 * Defaults to site administrators. Metering admins is a legitimate product
	 * choice, but it has to be a deliberate one - returning false here restores
	 * the behaviour of an owner being blocked by their own marketplace.
	 *
	 * @since 1.6.0
	 *
	 * @param bool $bypasses Whether limits are waived for this member.
	 * @param int  $user_id  Member being checked.
	 */
	return (bool) apply_filters( 'wpss_member_bypasses_limits', $bypasses, $user_id );
}

/**
 * Count a vendor's services without loading them.
 *
 * Two callers were doing `count( get_posts( [ 'posts_per_page' => -1 ] ) )`
 * purely to produce a number - one of them on `/me`, which the app calls at
 * launch. That loads every id a vendor has ever published to discard all of
 * them, which is the scan the big-site checklist exists to stop.
 *
 * @since 1.7.0
 *
 * @param int             $vendor_id   Vendor user ID.
 * @param string|string[] $post_status Status(es) to count. Default 'publish'.
 * @return int
 */
function wpss_count_vendor_services( int $vendor_id, $post_status = 'publish' ): int {
	if ( $vendor_id <= 0 ) {
		return 0;
	}

	$query = new \WP_Query(
		array(
			'post_type'              => 'wpss_service',
			'post_status'            => $post_status,
			'author'                 => $vendor_id,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return (int) $query->found_posts;
}

/**
 * Proof points for the Become a Vendor page.
 *
 * Real figures, read from this marketplace rather than invented, and each one
 * is dropped when it would work against us: a brand-new site advertising
 * "0 sellers, 0 orders" is worse than saying nothing, because it tells the
 * visitor they would be first with none of the reassurance that usually comes
 * with that.
 *
 * The commission line is the exception - it is always shown, because what a
 * seller keeps is the question they came to the page with, and it is true on
 * day one.
 *
 * @since 1.7.0
 *
 * @return array<int,array{value:string,label:string}> Ordered, ready to render.
 */
function wpss_get_vendor_pitch_stats(): array {
	global $wpdb;

	$stats = array();

	$vendors = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_vendor_profiles WHERE status = 'active'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// A handful of sellers is not proof of anything; below this it reads as an
	// empty room rather than a marketplace.
	if ( $vendors >= 5 ) {
		$stats[] = array(
			'value' => number_format_i18n( $vendors ),
			'label' => __( 'sellers already here', 'wp-sell-services' ),
		);
	}

	$services = (int) wp_count_posts( 'wpss_service' )->publish;

	if ( $services >= 5 ) {
		$stats[] = array(
			'value' => number_format_i18n( $services ),
			'label' => __( 'services on offer', 'wp-sell-services' ),
		);
	}

	// What the seller keeps. Always shown, and taken from the real setting so a
	// site running 5% or 20% does not advertise someone else's number.
	$rate = (float) ( get_option( 'wpss_commission', array() )['commission_rate'] ?? 10 );
	$keep = max( 0, min( 100, 100 - $rate ) );

	$stats[] = array(
		/* translators: %s: percentage of each order the seller keeps. */
		'value' => sprintf( __( '%s%%', 'wp-sell-services' ), number_format_i18n( $keep ) ),
		'label' => __( 'of every order is yours', 'wp-sell-services' ),
	);

	/**
	 * Filter the proof points on the Become a Vendor page.
	 *
	 * @since 1.7.0
	 *
	 * @param array $stats Ordered stats, each with `value` and `label`.
	 */
	return apply_filters( 'wpss_vendor_pitch_stats', $stats );
}
