<?php
/**
 * URLs and page mapping: dashboard sections, mapped pages, checkout and cart links.
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
 * @since 1.6.0
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

	// 'orders' used to be dropped here as "the default section, so leave it
	// implicit". That stopped being true in 1.2.0, when the landing section
	// became role-aware: an active vendor lands on `sales`, a buyer on `orders`.
	// From then on the Buying > My Orders link pointed at the bare dashboard
	// URL, and a member who both buys and sells clicked it and landed on Sales
	// Orders - reading "No sales yet" as "you have never bought anything".
	// A link now always names the section it means, whatever the default is.
	if ( '' === $section ) {
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
 * Append an order detail (and optional action) onto a dashboard section URL.
 *
 * Pretty: `/{dashboard}/{section}/{order_id}/` or
 * `/{dashboard}/{section}/{order_id}/{action}/`.
 * Plain permalinks keep `?order_id=` (+ `?action=`).
 *
 * `$base_url` must already include the section path (pass the result of
 * `wpss_get_dashboard_url( 'orders' )` / `'sales'`), not the bare dashboard.
 *
 * @since 1.7.0
 *
 * @param string $base_url Dashboard section URL.
 * @param int    $order_id Order ID.
 * @param string $action   Optional action slug (e.g. 'requirements').
 * @return string
 */
function wpss_append_dashboard_order( string $base_url, int $order_id, string $action = '' ): string {
	if ( $order_id <= 0 || '' === $base_url ) {
		return $base_url;
	}

	$action = sanitize_key( $action );

	// Plain permalinks: query-arg form is the canonical shape.
	if ( ! get_option( 'permalink_structure' ) ) {
		$args = array( 'order_id' => $order_id );
		if ( '' !== $action ) {
			$args['action'] = $action;
		}
		return add_query_arg( $args, $base_url );
	}

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

	$path = trailingslashit( $base_url ) . $order_id . '/';
	if ( '' !== $action ) {
		$path .= $action . '/';
	}

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
 * @since 1.6.0
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
		'proposals',
		'reviews',
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
	 * @since 1.6.0
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
 * @since 1.6.0
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
		'my-proposals'   => 'proposals',
		'my-reviews'     => 'reviews',
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
	 * @since 1.6.0
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
 * @since 1.6.0
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
 * @since 1.6.0
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
 * The plugin's page registry: what each mapped page is called, what shortcode
 * it carries, and which slug it should live at.
 *
 * This is the single definition of a WPSS page. It used to be seven, and they
 * had drifted:
 *
 * - the installer (`Activator::create_pages()`) knew the canonical slugs,
 * - `Settings::ajax_create_page()` — the creator the wizard and the Pages panel
 *   actually call — knew no slugs at all, so WordPress derived one from a
 *   client-supplied title,
 * - the wizard, the Pages panel, the setup notice and the wizard's completion
 *   check each carried their own label list, disagreeing on the names.
 *
 * The practical result: an owner who created the cart page from the wizard or
 * from Settings got the title "Cart", WordPress took the `cart` slug (or, with
 * WooCommerce installed, found it taken and appended a number), and the site
 * ended up on `/cart-2/` … `/cart-16/` while the installer's `service-cart`
 * slug was never used. Same mechanism for checkout against `/checkout/`.
 *
 * Every consumer now reads this. A page key added here appears in the
 * installer, the wizard, the Pages panel and the setup notice at once, and
 * cannot be created without its slug.
 *
 * @since 1.6.0
 *
 * @return array<string, array{title: string, shortcode: string, slug: string, required: bool}>
 */
function wpss_get_page_definitions(): array {
	$definitions = array(
		'services_page' => array(
			'title'     => __( 'Services', 'wp-sell-services' ),
			'shortcode' => '[wpss_services]',
			'slug'      => 'services',
			'required'  => true,
		),
		'vendors_page'  => array(
			'title'     => __( 'Vendors', 'wp-sell-services' ),
			'shortcode' => '[wpss_vendors]',
			'slug'      => 'vendors',
			'required'  => false,
		),
		'dashboard'     => array(
			'title'     => __( 'Dashboard', 'wp-sell-services' ),
			'shortcode' => '[wpss_dashboard]',
			'slug'      => 'dashboard',
			'required'  => true,
		),
		// Canonical slug is become-a-vendor: it matches the page title, it is
		// what shipped installs and the support docs already use, and it is the
		// URL a member guesses. New installs created become-vendor while older
		// ones carry become-a-vendor, so whichever a person typed 404'd
		// depending on when the site was installed (Basecamp 10235849842).
		// wpss_redirect_legacy_vendor_slug() sends the other form here.
		'become_vendor' => array(
			'title'     => __( 'Become a Vendor', 'wp-sell-services' ),
			'shortcode' => '[wpss_vendor_registration]',
			'slug'      => 'become-a-vendor',
			'required'  => true,
		),
		// Both carry an explicit service-* slug. These pages are only the
		// standalone rail; when WooCommerce or EDD runs the store, that plugin
		// owns /cart/ and /checkout/. Taking the generic slug is what produced
		// the /cart-2/ … /cart-16/ trail on sites running both.
		'cart'          => array(
			'title'     => __( 'Service Cart', 'wp-sell-services' ),
			'shortcode' => '[wpss_cart]',
			'slug'      => 'service-cart',
			'required'  => false,
		),
		'checkout'      => array(
			'title'     => __( 'Service Checkout', 'wp-sell-services' ),
			'shortcode' => '[wpss_checkout]',
			'slug'      => 'service-checkout',
			'required'  => true,
		),
		// Not required: an owner running SSO, a membership plugin, or plain
		// WordPress registration should not be nagged about a page they do not
		// want. When it IS mapped, wpss_marketplace_register_url() points every
		// Register link on the site at it - ours and the theme's - because they
		// all resolve through core's wp_registration_url().
		'registration'  => array(
			'title'     => __( 'Create Account', 'wp-sell-services' ),
			'shortcode' => '[wpss_register]',
			'slug'      => 'create-account',
			'required'  => false,
		),
	);

	/**
	 * Filter the page registry.
	 *
	 * Changing a title or slug here changes what the installer, the wizard and
	 * the Pages panel create — so an owner can ship "Freelancers" instead of
	 * "Vendors" without editing pages by hand afterwards. Existing pages are
	 * never renamed or moved.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, array{title: string, shortcode: string, slug: string, required: bool}> $definitions Page registry.
	 */
	return (array) apply_filters( 'wpss_page_definitions', $definitions );
}

/**
 * The pages a marketplace cannot run without, as page_key => title.
 *
 * Backs the setup notice, the wizard's page step and the wizard's completion
 * check, which each used to carry their own list and disagreed on the names —
 * the wizard offered "Checkout" and "Vendor Registration" where the installer
 * and the notice said "Service Checkout" and "Become a Vendor".
 *
 * @since 1.6.0
 *
 * @return array<string, string> Map of page_key => page title.
 */
function wpss_get_required_pages(): array {
	$required = array();

	foreach ( wpss_get_page_definitions() as $key => $definition ) {
		if ( ! empty( $definition['required'] ) ) {
			$required[ $key ] = $definition['title'];
		}
	}

	return $required;
}

/**
 * Every mapped page, with the optional ones marked.
 *
 * The setup wizard used wpss_get_required_pages() and so listed four, under
 * copy saying those were the pages the marketplace needs - while Settings >
 * Pages listed six. Nothing was ever missing (activation creates all six), but
 * two screens disagreed about what "the pages" means, which is enough to make
 * an owner go hunting.
 *
 * @since 1.7.0
 *
 * @return array<string, array{title:string,required:bool}>
 */
function wpss_get_setup_pages(): array {
	$pages = array();

	foreach ( wpss_get_page_definitions() as $key => $definition ) {
		$pages[ $key ] = array(
			'title'    => $definition['title'],
			'required' => ! empty( $definition['required'] ),
		);
	}

	return $pages;
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
	$slugs = array();

	foreach ( wpss_get_page_definitions() as $key => $definition ) {
		$slugs[ $key ] = $definition['slug'];
	}

	// A virtual route rather than an installer-created page: the dashboard
	// renders it, so it has a slug but no page and no entry in the registry.
	$slugs['create_service'] = 'create-service';

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
	return apply_filters( 'wpss_default_page_slugs', $slugs );
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
	$page_id = wpss_get_page_id( $page_key );

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
 * A mapped page that is trashed, drafted or deleted counts as unmapped: its
 * permalink is ?page_id=N, which 404s, so every link built from it broke while
 * the setting still said the page was there. Callers already treat 0 as "no
 * page", so answering 0 here fixes every link and the setup notice at once.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard').
 * @return int Page ID or 0.
 */
function wpss_get_page_id( string $page_key ): int {
	$pages   = get_option( 'wpss_pages', array() );
	$page_id = (int) ( $pages[ $page_key ] ?? 0 );

	return $page_id && 'publish' === get_post_status( $page_id ) ? $page_id : 0;
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
		// No null check on the provider: EcommerceAdapterInterface and all five
		// implementations declare get_checkout_provider(): CheckoutProviderInterface,
		// so PHP would fatal before it could return null. The guard that used to
		// be here was unreachable.
		return $adapter->get_checkout_provider()->get_checkout_url(
			$service_id,
			array(
				'package_id' => $package_id,
				'addons'     => $addons,
			)
		);
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
 * Point Register at the marketplace's own signup page when one is mapped.
 *
 * The header Register button sent buyers to wp-login.php?action=register -
 * stock WordPress chrome as the first impression of a branded marketplace
 * (Basecamp 10240020415). That button belongs to the theme, not to us, so
 * there was nothing of ours to edit.
 *
 * Filtering core's `register_url` fixes it everywhere at once instead: BuddyX
 * falls back to wp_registration_url() when it has no page of its own, and so do
 * all five of our own Register links. One filter, every surface, no template
 * edits and nothing to keep in sync.
 *
 * Returns the original URL untouched when no page is mapped, so a site using
 * SSO or plain WordPress registration is unaffected. Deliberately does NOT
 * check users_can_register: our signup page renders its own "registration is
 * disabled" notice, and core already hides the links when it is off.
 *
 * @since 1.7.0
 *
 * @param string $url Default registration URL.
 * @return string
 */
function wpss_marketplace_register_url( string $url ): string {
	// Resolved from the mapping and confirmed published - NOT via
	// wpss_get_page_url(), which falls back to the default slug whether or not
	// a page lives there. Using that helper here sent every site with no
	// registration page to /create-account/ and a 404, which is worse than the
	// stock login screen this replaces. Caught by
	// tests/test-registration-page-contract.php.
	$page_id = wpss_get_page_id( 'registration' );

	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return $url;
	}

	$page_url = get_permalink( $page_id );

	return $page_url ? $page_url : $url;
}
add_filter( 'register_url', 'wpss_marketplace_register_url' );

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
		//
		// The provider is never null - the interface and all five adapters
		// declare a non-nullable CheckoutProviderInterface return - so the
		// unreachable guard that used to wrap this is gone.
		return $adapter->get_checkout_provider()->get_checkout_url( 0 );
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
 * Standalone pretty shape: `/{checkout}/pay/{id}/`. Legacy `?pay_order=N` is
 * still accepted and 301'd when pretty permalinks are on.
 * A cart-based rail (WooCommerce, FluentCart) hooks `wpss_pay_order_url` and
 * returns a URL on its own payment flow instead; a rail that does not hook it
 * gets '' (see wpss_can_pay_single_order()).
 *
 * @since 1.4.0
 *
 * @param int $order_id WPSS order ID to be paid.
 * @return string Payment URL for the active e-commerce rail.
 */
function wpss_get_pay_order_url( int $order_id ): string {
	// No rail can pay one order here: return nothing rather than a standalone
	// URL the active store ignores. Every caller treats '' as "no button".
	if ( ! wpss_can_pay_single_order() ) {
		return '';
	}

	$base = wpss_get_checkout_base_url();

	if ( $order_id > 0 && $base && get_option( 'permalink_structure' ) ) {
		$query    = '';
		$fragment = '';
		$url      = $base;

		$hash_pos = strpos( $url, '#' );
		if ( false !== $hash_pos ) {
			$fragment = substr( $url, $hash_pos );
			$url      = substr( $url, 0, $hash_pos );
		}

		$query_pos = strpos( $url, '?' );
		if ( false !== $query_pos ) {
			$query = substr( $url, $query_pos );
			$url   = substr( $url, 0, $query_pos );
		}

		$url = trailingslashit( $url ) . 'pay/' . $order_id . '/' . $query . $fragment;
	} else {
		$url = add_query_arg( 'pay_order', $order_id, $base );
	}

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
 * Admin settings sections, keyed by the identifier the page routes on.
 *
 * Kept beside the URL builder rather than inside Admin\Settings because Pro and
 * every notice that deep-links into settings needs to name a section without
 * instantiating the settings page. Pro tabs are added through the same filter
 * the settings sidebar uses, so a Pro-only section still resolves here.
 *
 * @since 1.6.0
 *
 * @return string[] Section identifiers.
 */
function wpss_get_settings_sections(): array {
	$sections = array(
		'general',
		'pages',
		'payments',
		'commission',
		'payouts',
		'vendor',
		'orders',
		'emails',
		'advanced',
	);

	/**
	 * Filter the known admin settings sections.
	 *
	 * Pro registers its own tabs (branding, analytics, integrations) and must
	 * add them here too, or wpss_get_settings_url() will refuse to link to them.
	 *
	 * @since 1.6.0
	 *
	 * @param string[] $sections Section identifiers.
	 */
	return (array) apply_filters( 'wpss_settings_sections', $sections );
}

/**
 * Build a URL to the admin settings page, optionally to one section.
 *
 * THE single way to link into settings. 1.3.0 moved the settings page to a
 * sidebar + hash layout (all sections render at once; JS shows one based on
 * `location.hash`), but thirteen call sites across Free and Pro kept emitting
 * the pre-1.3.0 `&tab=<section>` query arg. Nothing reads `tab`, so every one of
 * those links dropped the admin on General - a notice saying "configure your
 * pages" sent them to the wrong screen and left them to find Pages themselves
 * (Basecamp 10208211769).
 *
 * Two of those links also named `gateways`, which has never been a section id;
 * the tab is `payments`. An unknown id silently lands on General as well, so the
 * mistake was invisible. Unknown sections are dropped here instead of being
 * emitted as a hash that goes nowhere.
 *
 * @since 1.6.0
 *
 * @param string $section Section identifier, e.g. 'pages'. Empty for the page itself.
 * @return string Admin URL.
 */
function wpss_get_settings_url( string $section = '' ): string {
	$url = admin_url( 'admin.php?page=wpss-settings' );

	if ( '' === $section ) {
		return $url;
	}

	if ( ! in_array( $section, wpss_get_settings_sections(), true ) ) {
		wpss_log(
			sprintf( 'wpss_get_settings_url() called with unknown section "%s"; linking to the settings page instead.', $section ),
			'warning'
		);

		return $url;
	}

	return $url . '#' . $section;
}

/**
 * Legal page links the buyer is entitled to see.
 *
 * Terms is a mapping the owner points at their OWN page; Privacy is core's
 * `wp_page_for_privacy_policy`. We create neither — publishing our own copy
 * would be the plugin talking over the owner, and core already owns privacy.
 *
 * Both entries are null when unmapped, so a caller renders nothing rather than
 * an empty link. Settings has promised since 1.4.0 that Terms is "linked from
 * checkout"; it was only ever read by the app config endpoint, so an owner who
 * mapped their page saw no change on the storefront (Basecamp 10240020620).
 *
 * @since 1.7.0
 *
 * @return array{terms_url: string|null, privacy_policy_url: string|null}
 */
function wpss_get_legal_links(): array {
	$terms_id = (int) get_option( 'wpss_terms_page' );
	$terms    = ( $terms_id > 0 && 'publish' === get_post_status( $terms_id ) )
		? (string) get_permalink( $terms_id )
		: '';

	$privacy = (string) get_privacy_policy_url();

	return array(
		'terms_url'          => '' !== $terms ? $terms : null,
		'privacy_policy_url' => '' !== $privacy ? $privacy : null,
	);
}
