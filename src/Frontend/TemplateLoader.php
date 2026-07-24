<?php
/**
 * Template Loader
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Handles template loading with theme override support.
 *
 * @since 1.0.0
 */
class TemplateLoader {

	/**
	 * Template path in theme.
	 *
	 * @var string
	 */
	private string $theme_template_path = 'wp-sell-services/';

	/**
	 * Default template path in plugin.
	 *
	 * @var string
	 */
	private string $default_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->default_path = WPSS_PLUGIN_DIR . 'templates/';
	}

	/**
	 * Initialize template loader.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_filter( 'single_template', array( $this, 'single_service_template' ) );
		add_filter( 'archive_template', array( $this, 'archive_service_template' ) );
		add_filter( 'taxonomy_template', array( $this, 'taxonomy_template' ) );
		// Note: rewrite rules and query vars are registered in Plugin::register_rewrite_rules()
		// so they work in both admin and frontend contexts (required for flush).

		// Reign renders plugin pages full-width through its own layout meta
		// (same pattern Reign uses for FluentCart) so the theme keeps its
		// page wrappers and subheader. Other themes go through
		// fullwidth_template() in template_include.
		if ( 'reign-theme' === get_template() ) {
			add_filter( 'get_post_metadata', array( $this, 'reign_force_fullwidth_layout' ), 10, 4 );
		}
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'wpss_vendor';
		$vars[] = 'wpss_service_order';
		$vars[] = 'wpss_order_action';
		return $vars;
	}

	/**
	 * Add rewrite rules for vendor profiles and service orders.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		// Vendor profile: /vendor/{username}/
		add_rewrite_rule(
			'^vendor/([^/]+)/?$',
			'index.php?wpss_vendor=$matches[1]',
			'top'
		);

		// Service order with action: /service-order/{id}/{action}/
		add_rewrite_rule(
			'^service-order/([0-9]+)/([^/]+)/?$',
			'index.php?wpss_service_order=$matches[1]&wpss_order_action=$matches[2]',
			'top'
		);

		// Service order view: /service-order/{id}/
		add_rewrite_rule(
			'^service-order/([0-9]+)/?$',
			'index.php?wpss_service_order=$matches[1]',
			'top'
		);
	}

	/**
	 * Filter template include.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function template_include( string $template ): string {
		// Redirect /service-order/{id}/ to dashboard order view.
		$order_id = get_query_var( 'wpss_service_order' );
		if ( $order_id ) {
			$action = get_query_var( 'wpss_order_action' );
			if ( 'requirements' === $action ) {
				$redirect = wpss_get_order_requirements_url( (int) $order_id );
			} else {
				$redirect = wpss_get_order_url( (int) $order_id );
			}
			if ( $redirect ) {
				wp_safe_redirect( $redirect );
				exit;
			}
			// Fallback to template if dashboard URL unavailable.
			return $this->load_service_order_template( (int) $order_id );
		}

		// Check for vendor profile.
		$vendor_slug = get_query_var( 'wpss_vendor' );
		if ( $vendor_slug ) {
			// Look up user by nicename (URL slug).
			$user = get_user_by( 'slug', sanitize_text_field( $vendor_slug ) );

			// Ask the ONE authority whether this user is a vendor. This gate used
			// to read the `_wpss_is_vendor` user meta directly — a third source
			// of truth alongside the `wpss_vendor` role/capability (which
			// wpss_is_vendor() uses) and the `wpss_vendor_profiles` row. Any
			// vendor created through a path that does not write that legacy meta
			// — the demo seeder, a REST/CLI creation, an older signup — got a
			// hard 404 on their public profile while still having a profile row,
			// published services and completed orders, and every "About the
			// seller" link on their service pages pointed at that dead page.
			if ( $user && wpss_is_vendor( $user->ID ) ) {
				// Set global for template use.
				global $wpss_vendor_id;
				$wpss_vendor_id = $user->ID;

				// Also set as query var for template access.
				set_query_var( 'wpss_vendor', $user->ID );

				// F7: the /vendor/{slug}/ rewrite route renders the service-card
				// grid (Related/owned services). Unlike the [wpss_vendor_profile]
				// shortcode, this rewrite path never pulled in the frontend
				// bundle, so the cards rendered naked (no design-system / card
				// surface / grid layout). Enqueue the shared component layer here
				// so the cards match every other surface.
				wpss_enqueue_frontend_assets();

				// Track profile view (skip own views and duplicate sessions).
				$this->track_profile_view( $user->ID );

				$custom = $this->locate_template( 'vendor/profile.php' );
				if ( $custom ) {
					return $custom;
				}
			} else {
				// Vendor not found - show 404.
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
				return get_query_template( '404' );
			}
		}

		// Mapped services page — use the archive template.
		if ( wpss_is_page( 'services_page' ) ) {
			$custom = $this->locate_template( 'archive-service.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		// App-like plugin pages render full-width — never next to the
		// theme's blog sidebar/widgets. Registered as a real template per
		// the design-system rule: register, never CSS-hide theme HTML.
		$fullwidth = $this->fullwidth_template();
		if ( $fullwidth ) {
			return $fullwidth;
		}

		// Check for buyer request single.
		if ( is_singular( 'wpss_request' ) ) {
			wpss_enqueue_frontend_assets();
			$custom = $this->locate_template( 'single-request.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		// Check for buyer request archive.
		if ( is_post_type_archive( 'wpss_request' ) ) {
			$custom = $this->locate_template( 'archive-request.php' );
			if ( $custom ) {
				return $custom;
			}
		}

		return $template;
	}

	/**
	 * Resolve the full-width template for app-like plugin pages.
	 *
	 * Returns the sidebar-free template when the current request is one of
	 * the mapped plugin pages (dashboard, cart, checkout, become vendor),
	 * the page has no explicit page template selected, and the
	 * `wpss_use_fullwidth_template` filter allows it.
	 *
	 * @return string Template path, or empty string to leave the theme template.
	 */
	private function fullwidth_template(): string {
		if ( ! is_page() || ! $this->is_fullwidth_plugin_page( get_queried_object_id() ) ) {
			return '';
		}

		// Respect an explicit page-template selection made in the editor.
		$selected = get_page_template_slug();
		if ( ! empty( $selected ) && 'default' !== $selected ) {
			return '';
		}

		/**
		 * Filter whether plugin pages use the full-width template.
		 *
		 * Return false to keep the active theme's page template (including
		 * its sidebar) on dashboard, cart, checkout, and become-vendor pages.
		 *
		 * @since 1.2.0
		 *
		 * @param bool $use_fullwidth Default true.
		 */
		if ( ! apply_filters( 'wpss_use_fullwidth_template', true ) ) {
			return '';
		}

		// Reign: layout is forced via reign_force_fullwidth_layout() so the
		// theme's own page template (wrappers, subheader) stays in charge.
		$parent_theme = get_template();
		if ( 'reign-theme' === $parent_theme ) {
			return '';
		}

		// BuddyX / BuddyX Pro: use the theme's native "Page No Sidebar"
		// template so plugin pages match the theme's container styling.
		if ( in_array( $parent_theme, array( 'buddyx', 'buddyx-pro' ), true ) ) {
			$theme_fullwidth = \locate_template( 'page-templates/full-width-container.php' );
			if ( $theme_fullwidth ) {
				return $theme_fullwidth;
			}
		}

		// Every other theme: the plugin's generic sidebar-free template.
		return $this->locate_template( 'wpss-fullwidth-template.php' );
	}

	/**
	 * Check whether a page ID is one of the mapped full-width plugin pages.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private function is_fullwidth_plugin_page( int $page_id ): bool {
		if ( ! $page_id ) {
			return false;
		}

		static $page_ids = null;

		if ( null === $page_ids ) {
			/**
			 * Filter which mapped plugin pages render full-width.
			 *
			 * @since 1.2.0
			 *
			 * @param string[] $page_keys Keys from the wpss_pages option.
			 */
			$page_keys = apply_filters(
				'wpss_fullwidth_page_keys',
				array( 'dashboard', 'cart', 'checkout', 'become_vendor' )
			);

			$page_ids = array();
			foreach ( $page_keys as $page_key ) {
				$mapped_id = wpss_get_page_id( $page_key );
				if ( $mapped_id ) {
					$page_ids[] = $mapped_id;
				}
			}
		}

		return in_array( $page_id, $page_ids, true );
	}

	/**
	 * Force Reign's full-width layout on plugin pages via its layout meta.
	 *
	 * Mirrors Reign's own FluentCart integration: intercepts reads of
	 * `reign_wbcom_metabox_data` for mapped plugin pages and sets
	 * `layout.site_layout = full_width` when no explicit layout was chosen,
	 * so the theme's blog sidebar never renders next to marketplace UI.
	 * An explicitly configured per-page layout always wins.
	 *
	 * @param mixed  $metadata  Pre-filtered meta value (null = use DB).
	 * @param int    $object_id Post ID being read.
	 * @param string $meta_key  Meta key being read.
	 * @param bool   $single    Whether a single value was requested.
	 * @return mixed
	 */
	public function reign_force_fullwidth_layout( $metadata, $object_id, $meta_key, $single ) {
		if ( 'reign_wbcom_metabox_data' !== $meta_key || is_admin() ) {
			return $metadata;
		}

		if ( ! $this->is_fullwidth_plugin_page( (int) $object_id ) ) {
			return $metadata;
		}

		if ( ! apply_filters( 'wpss_use_fullwidth_template', true ) ) {
			return $metadata;
		}

		// Temporarily unhook to read the real meta without recursion.
		remove_filter( 'get_post_metadata', array( $this, 'reign_force_fullwidth_layout' ), 10 );
		$actual_meta = get_post_meta( $object_id, 'reign_wbcom_metabox_data', true );
		add_filter( 'get_post_metadata', array( $this, 'reign_force_fullwidth_layout' ), 10, 4 );

		if ( ! is_array( $actual_meta ) ) {
			$actual_meta = array();
		}

		// Only force when the page owner hasn't chosen a layout.
		if ( empty( $actual_meta['layout']['site_layout'] ) ) {
			$actual_meta['layout']['site_layout'] = 'full_width';
		}

		return $single ? array( $actual_meta ) : array( array( $actual_meta ) );
	}

	/**
	 * Load service order template with proper permission checks.
	 *
	 * @param int $order_id Service order ID.
	 * @return string Template path.
	 */
	private function load_service_order_template( int $order_id ): string {
		// Require login.
		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$order = wpss_get_order( $order_id );

		// Check order exists.
		if ( ! $order ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_query_template( '404' );
		}

		// Check user has access (customer, vendor, or admin).
		$user_id = get_current_user_id();
		if ( $order->customer_id !== $user_id && $order->vendor_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this order.', 'wp-sell-services' ), '', array( 'response' => 403 ) );
		}

		// Set globals for template use.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
		global $wpss_current_order, $order_id;
		$wpss_current_order = $order;
		$order_id           = $order->id;

		// Determine action (requirements, delivery, review, etc.).
		$action = get_query_var( 'wpss_order_action' );

		// Load appropriate template.
		switch ( $action ) {
			case 'requirements':
				$custom = $this->locate_template( 'order/order-requirements.php' );
				break;

			case 'delivery':
				$custom = $this->locate_template( 'order/order-delivery.php' );
				break;

			case 'review':
				$custom = $this->locate_template( 'order/order-review.php' );
				break;

			default:
				$custom = $this->locate_template( 'order/order-view.php' );
				break;
		}

		if ( $custom ) {
			return $custom;
		}

		// Fallback to generic order view.
		$fallback = $this->locate_template( 'order/order-view.php' );
		return $fallback ?: get_query_template( '404' );
	}

	/**
	 * Filter single service template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function single_service_template( string $template ): string {
		if ( ! is_singular( 'wpss_service' ) ) {
			return $template;
		}

		$custom = $this->locate_template( 'single-service.php' );

		return $custom ?: $template;
	}

	/**
	 * Filter archive service template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function archive_service_template( string $template ): string {
		if ( ! is_post_type_archive( 'wpss_service' ) ) {
			return $template;
		}

		$custom = $this->locate_template( 'archive-service.php' );

		return $custom ?: $template;
	}

	/**
	 * Filter taxonomy template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function taxonomy_template( string $template ): string {
		if ( is_tax( 'wpss_service_category' ) || is_tax( 'wpss_service_tag' ) ) {
			// Try taxonomy-specific template first.
			$taxonomy_template = is_tax( 'wpss_service_category' )
				? 'taxonomy-service-category.php'
				: 'taxonomy-service-tag.php';

			$custom = $this->locate_template( $taxonomy_template );
			if ( $custom ) {
				return $custom;
			}

			// Fall back to the service archive template so taxonomy pages
			// use the same card grid layout instead of the default post loop.
			$archive = $this->locate_template( 'archive-service.php' );
			if ( $archive ) {
				return $archive;
			}
		}

		return $template;
	}

	/**
	 * Locate a template file.
	 *
	 * Look in theme first, then plugin templates folder.
	 *
	 * @param string $template_name Template file name.
	 * @param string $template_path Path in theme (optional).
	 * @param string $default_path  Default path (optional).
	 * @return string Template path or empty string if not found.
	 */
	public function locate_template(
		string $template_name,
		string $template_path = '',
		string $default_path = ''
	): string {
		if ( ! $template_path ) {
			$template_path = $this->theme_template_path;
		}

		if ( ! $default_path ) {
			$default_path = $this->default_path;
		}

		// Look in theme/child-theme first.
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);

		// Fallback to plugin templates.
		if ( ! $template ) {
			$plugin_template = $default_path . $template_name;
			if ( file_exists( $plugin_template ) ) {
				$template = $plugin_template;
			}
		}

		return apply_filters( 'wpss_locate_template', $template, $template_name, $template_path );
	}

	/**
	 * Get template part.
	 *
	 * Similar to get_template_part() but with theme override support.
	 *
	 * @param string $slug Template slug.
	 * @param string $name Template name (optional).
	 * @param array  $args Arguments to pass to template (optional).
	 * @return void
	 */
	public function get_template_part( string $slug, string $name = '', array $args = array() ): void {
		$templates = array();

		if ( $name ) {
			$templates[] = "{$slug}-{$name}.php";
		}

		$templates[] = "{$slug}.php";

		$template = '';
		foreach ( $templates as $template_name ) {
			$template = $this->locate_template( $template_name );
			if ( $template ) {
				break;
			}
		}

		if ( $template ) {
			$this->load_template( $template, $args );
		}
	}

	/**
	 * Load a template with arguments.
	 *
	 * @param string $template_file Template file path.
	 * @param array  $args          Arguments to pass to template.
	 * @return void
	 */
	public function load_template( string $template_file, array $args = array() ): void {
		if ( ! file_exists( $template_file ) ) {
			return;
		}

		// Extract args to make them available in template.
		if ( ! empty( $args ) && is_array( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template_file;
	}

	/**
	 * Get template content as string.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Arguments to pass to template.
	 * @return string Template content.
	 */
	public function get_template_html( string $template_name, array $args = array() ): string {
		ob_start();

		$template = $this->locate_template( $template_name );
		if ( $template ) {
			$this->load_template( $template, $args );
		}

		return ob_get_clean();
	}

	/**
	 * Include a template file.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Arguments to pass to template.
	 * @return void
	 */
	public function include_template( string $template_name, array $args = array() ): void {
		$template = $this->locate_template( $template_name );
		if ( $template ) {
			$this->load_template( $template, $args );
		}
	}

	/**
	 * Get the theme template path.
	 *
	 * @return string
	 */
	public function get_theme_template_path(): string {
		return $this->theme_template_path;
	}

	/**
	 * Get the default plugin template path.
	 *
	 * @return string
	 */
	public function get_default_path(): string {
		return $this->default_path;
	}

	/**
	 * Check if a template exists.
	 *
	 * @param string $template_name Template file name.
	 * @return bool
	 */
	public function template_exists( string $template_name ): bool {
		return (bool) $this->locate_template( $template_name );
	}

	/**
	 * Get all available templates.
	 *
	 * @return array List of template files.
	 */
	public function get_available_templates(): array {
		$templates = array();

		// Get plugin templates.
		$plugin_templates = glob( $this->default_path . '*.php' );
		if ( $plugin_templates ) {
			foreach ( $plugin_templates as $template ) {
				$templates[] = basename( $template );
			}
		}

		// Get templates from subdirectories.
		$subdirs = array( 'partials', 'order', 'myaccount', 'dashboard', 'vendor', 'emails' );
		foreach ( $subdirs as $subdir ) {
			$subdir_templates = glob( $this->default_path . $subdir . '/*.php' );
			if ( $subdir_templates ) {
				foreach ( $subdir_templates as $template ) {
					$templates[] = $subdir . '/' . basename( $template );
				}
			}
		}

		return $templates;
	}

	/**
	 * Track a vendor profile view.
	 *
	 * Increments _wpss_profile_views user meta. Skips the vendor viewing
	 * their own profile and uses a cookie to prevent duplicate counts
	 * within the same session.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return void
	 */
	private function track_profile_view( int $vendor_id ): void {
		// Don't track the vendor viewing their own profile.
		if ( is_user_logged_in() && get_current_user_id() === $vendor_id ) {
			return;
		}

		// Check if already tracked in this session.
		$cookie_key = 'wpss_profile_viewed_' . $vendor_id;
		if ( isset( $_COOKIE[ $cookie_key ] ) ) {
			return;
		}

		// Increment profile view count.
		$views = (int) get_user_meta( $vendor_id, '_wpss_profile_views', true );
		update_user_meta( $vendor_id, '_wpss_profile_views', $views + 1 );

		// Set cookie via JavaScript (headers may already be sent at this point).
		$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$expires       = gmdate( 'D, d M Y H:i:s T', time() + DAY_IN_SECONDS );

		add_action(
			'wp_head',
			static function () use ( $cookie_key, $expires, $cookie_path, $cookie_domain ): void {
				printf(
					'<script>document.cookie=%s;</script>',
					wp_json_encode(
						esc_js( $cookie_key ) . '=1; expires=' . $expires . '; path=' . $cookie_path . ( $cookie_domain ? '; domain=' . $cookie_domain : '' )
					)
				);
			}
		);
	}
}
