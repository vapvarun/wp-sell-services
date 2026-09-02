<?php
/**
 * Plugin Activator
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Database\SchemaManager;
use WPSellServices\Services\Scheduler;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Run activation tasks (called from register_activation_hook).
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_dependencies();
		self::install();
		self::create_pages();
		self::schedule_cron_events();
		self::flush_rewrite_rules();
	}

	/**
	 * Run install/upgrade tasks safe for plugins_loaded.
	 *
	 * Handles DB schema, migrations, roles, and default options.
	 * Does NOT call create_pages() — that requires $wp_rewrite (available on init).
	 * Does NOT flush rewrite rules — that also requires init.
	 *
	 * Called from Plugin::maybe_run_install() on version change, and
	 * from activate() on first activation.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function install(): void {
		self::create_tables();
		self::create_roles();
		self::set_default_options();
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @return void
	 */
	private static function check_dependencies(): void {
		// No required dependencies for standalone mode.
	}

	/**
	 * Create database tables using SchemaManager.
	 *
	 * Creates all 18 plugin tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		$schema = new SchemaManager();
		$schema->install();
	}

	/**
	 * Create custom roles and capabilities.
	 *
	 * @return void
	 */
	private static function create_roles(): void {
		// Vendor capabilities. wpss_vendor_orders is the vendor-side order cap;
		// wpss_manage_orders is admin-only and is what can_transition() honours
		// as "force any status" - vendors never hold it. Services carry their
		// own capability type (edit_wpss_services...) rather than edit_posts, so
		// holding the vendor role does not also mean writing blog posts, and
		// holding edit_posts (every author) does not mean listing services.
		$vendor_caps = array(
			'wpss_vendor'                    => true,
			'wpss_manage_services'           => true,
			'wpss_vendor_orders'             => true,
			'wpss_view_analytics'            => true,
			'wpss_respond_to_requests'       => true,
			'read'                           => true, // Basic WordPress capability.
			'upload_files'                   => true,
			'edit_wpss_services'             => true,
			'edit_published_wpss_services'   => true,
			'publish_wpss_services'          => true,
			'delete_wpss_services'           => true,
			'delete_published_wpss_services' => true,
		);

		// Granted to vendors before 1.7.1; removed on upgrade.
		$retired_vendor_caps = array( 'wpss_manage_orders', 'edit_posts' );

		// Create the vendor role if it doesn't exist.
		if ( ! get_role( 'wpss_vendor' ) ) {
			add_role(
				'wpss_vendor',
				'Vendor', // Avoid __() here — runs before init, causes textdomain early-loading notice.
				$vendor_caps
			);
		} else {
			// Role exists, ensure it has exactly these capabilities.
			$vendor_role = get_role( 'wpss_vendor' );
			foreach ( $vendor_caps as $cap => $grant ) {
				$vendor_role->add_cap( $cap, $grant );
			}
			foreach ( $retired_vendor_caps as $cap ) {
				$vendor_role->remove_cap( $cap );
			}
		}

		// Site staff hold the vendor set plus the admin-side order cap and the
		// others_/private_ service caps so they can moderate every listing.
		$staff_caps = array_merge(
			$vendor_caps,
			array(
				'wpss_manage_orders'           => true,
				'edit_others_wpss_services'    => true,
				'edit_private_wpss_services'   => true,
				'read_private_wpss_services'   => true,
				'delete_others_wpss_services'  => true,
				'delete_private_wpss_services' => true,
			)
		);

		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $staff_caps as $cap => $grant ) {
					$role->add_cap( $cap, $grant );
				}
			}
		}

		// Authors were granted the whole vendor set up to 1.7.0, which made every
		// blog author a seller with no application. They get nothing now.
		$author_role = get_role( 'author' );
		if ( $author_role ) {
			foreach ( array_keys( $vendor_caps ) as $cap ) {
				if ( 0 === strpos( $cap, 'wpss_' ) ) {
					$author_role->remove_cap( $cap );
				}
			}
			$author_role->remove_cap( 'wpss_manage_orders' );
		}

		// Admin-only capabilities.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'wpss_manage_settings', true );
			$admin_role->add_cap( 'wpss_manage_disputes', true );
			$admin_role->add_cap( 'wpss_manage_vendors', true );
		}
	}

	/**
	 * One-time 1.7.1 upgrade: strip the retired caps from vendor USERS.
	 *
	 * VendorService::add_vendor_capabilities() also wrote wpss_manage_orders
	 * and edit_posts onto each vendor's own capability map, so fixing the role
	 * alone would leave every existing vendor able to force transitions.
	 *
	 * ponytail: full scan of the vendor role, once per site on upgrade; batch
	 * by user_id if a site with tens of thousands of vendors times out.
	 *
	 * @since 1.7.1
	 *
	 * @return void
	 */
	public static function migrate_vendor_user_caps(): void {
		$vendor_ids = get_users(
			array(
				'role'   => 'wpss_vendor',
				'fields' => 'ID',
				'number' => -1,
			)
		);

		foreach ( $vendor_ids as $vendor_id ) {
			$user = new \WP_User( (int) $vendor_id );
			$user->remove_cap( 'wpss_manage_orders' );
			$user->remove_cap( 'edit_posts' );
			$user->add_cap( 'wpss_vendor_orders' );
		}
	}

	/**
	 * One-time 1.7.1 upgrade: fold the Advanced tab's standalone copies back
	 * into wpss_advanced and drop them.
	 *
	 * The Advanced sanitizer wrote max_file_size, allowed_file_types
	 * and currency_position twice - into the array and as standalone
	 * options - and the readers only ever looked at the standalone copy. The
	 * array is now the only store. The standalone copy wins: both were written
	 * from one save so they never differ, and install() has already merged the
	 * defaults into the array by the time this runs, so "array lacks the key"
	 * would never be true.
	 *
	 * @since 1.7.1
	 *
	 * @return void
	 */
	public static function migrate_advanced_standalone_keys(): void {
		$advanced = get_option( 'wpss_advanced', array() );
		$advanced = is_array( $advanced ) ? $advanced : array();

		foreach ( array( 'max_file_size', 'allowed_file_types', 'currency_position' ) as $key ) {
			$standalone = get_option( 'wpss_' . $key, null );
			if ( null !== $standalone ) {
				$advanced[ $key ] = $standalone;
			}
			delete_option( 'wpss_' . $key );
		}

		update_option( 'wpss_advanced', $advanced );
	}

	/**
	 * Set default plugin options.
	 *
	 * Option names must match those registered in Settings.php.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = wpss_settings_defaults();

		foreach ( $defaults as $option_name => $default_values ) {
			$current = get_option( $option_name, false );
			if ( false === $current ) {
				// Fresh install — set all defaults.
				add_option( $option_name, $default_values );
			} elseif ( is_array( $current ) ) {
				// Upgrade — backfill any new keys without overwriting existing values.
				$merged = array_merge( $default_values, $current );
				if ( $merged !== $current ) {
					update_option( $option_name, $merged );
				}
			}
		}

		// Clean up old incorrectly-named options from previous versions.
		$old_options = array( 'wpss_general_settings', 'wpss_vendor_settings', 'wpss_notification_settings' );
		foreach ( $old_options as $old_option ) {
			delete_option( $old_option );
		}

		// First-run only: enable Manual/Offline payment so day-one checkout works
		// without any API keys — a fresh install must be able to take an order
		// (owner accepts bank/manual payment). Gated on BOTH "no offline settings
		// yet" AND "never activated before", so upgrades never re-enable it and an
		// owner who disabled it is never overridden (BC 10134397011).
		if ( false === get_option( 'wpss_offline_settings' ) && false === get_option( 'wpss_activated_at' ) ) {
			add_option(
				'wpss_offline_settings',
				array(
					'enabled'      => '1',
					'title'        => __( 'Manual / Offline Payment', 'wp-sell-services' ),
					'instructions' => __( 'The site owner will contact you with payment instructions after you place your order.', 'wp-sell-services' ),
				)
			);
		}

		// Set activation timestamp.
		if ( false === get_option( 'wpss_activated_at' ) ) {
			add_option( 'wpss_activated_at', time() );
		}

		// Redirect to setup wizard on first activation.
		if ( false === get_option( 'wpss_setup_wizard_completed' ) ) {
			set_transient( 'wpss_activation_redirect', true, 30 );
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * Public so {@see Plugin::maybe_run_install()} can call it on version
	 * change — otherwise new hooks added in an upgrade (e.g. the 1.1.0
	 * tipping / extension / milestone cleanup crons) only get scheduled
	 * on a full deactivate → reactivate cycle.
	 *
	 * @return void
	 */
	public static function schedule_cron_events(): void {
		// Single source of truth for every recurring Action Scheduler job the
		// free plugin owns. All hooks run under the `wpss` group so the
		// deactivator can sweep them in one call via
		// Scheduler::unschedule_all_for_group().
		//
		// Intervals kept close to the legacy WP-Cron values to preserve
		// existing timing guarantees — anything that changes cadence would
		// deserve its own changelog entry.
		$recurring = array(
			// Order lifecycle sweeps (previously scheduled at init).
			'wpss_check_late_orders'             => HOUR_IN_SECONDS,
			'wpss_auto_complete_orders'          => 12 * HOUR_IN_SECONDS,
			'wpss_send_deadline_reminders'       => DAY_IN_SECONDS,
			'wpss_send_requirements_reminders'   => DAY_IN_SECONDS,
			'wpss_check_requirements_timeout'    => DAY_IN_SECONDS,
			'wpss_process_cancellation_timeouts' => HOUR_IN_SECONDS,
			'wpss_process_offline_auto_cancel'   => HOUR_IN_SECONDS,
			'wpss_cleanup_expired_requests'      => DAY_IN_SECONDS,
			'wpss_update_vendor_stats'           => 12 * HOUR_IN_SECONDS,
			'wpss_recalculate_seller_levels'     => WEEK_IN_SECONDS,

			// Dispute response deadline check.
			'wpss_cron_daily'                    => DAY_IN_SECONDS,

			// Abandoned sub-order cleanups (tips, extensions, milestones) and
			// audit log retention.
			\WPSellServices\Services\AuditLogService::CLEANUP_HOOK => DAY_IN_SECONDS,
			\WPSellServices\Services\TippingService::CLEANUP_HOOK => DAY_IN_SECONDS,
			\WPSellServices\Services\ExtensionOrderService::CLEANUP_HOOK => DAY_IN_SECONDS,
			\WPSellServices\Services\MilestoneService::CLEANUP_HOOK => DAY_IN_SECONDS,

			// Expired guest "marked review helpful" idempotency rows in wp_options.
			'wpss_cleanup_review_votes'          => DAY_IN_SECONDS,
		);

		foreach ( $recurring as $hook => $interval ) {
			Scheduler::schedule_recurring( $hook, $interval );
		}

		// Auto-withdrawal has its own admin-driven schedule (weekly / biweekly /
		// monthly on specific dates) so it stays encapsulated in EarningsService.
		\WPSellServices\Services\EarningsService::schedule_auto_withdrawal_cron();
	}

	/**
	 * Create WooCommerce carrier product for service orders.
	 *
	 * @deprecated Moved to Pro plugin. Kept for backward compatibility.
	 * @return void
	 */
	private static function create_wc_carrier_product(): void {
		// WooCommerce integration moved to Pro plugin.
	}

	/**
	 * Create required pages with shortcodes on activation.
	 *
	 * Page titles, shortcodes and slugs come from wpss_get_page_definitions() —
	 * the one registry every creator reads, so the installer, the setup wizard
	 * and the Pages panel cannot disagree about what a page is called or where
	 * it lives. Maps page IDs in the wpss_pages option.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function create_pages(): void {
		$pages = wpss_get_page_definitions();

		$saved_pages = get_option( 'wpss_pages', array() );

		foreach ( $pages as $key => $page_data ) {
			// Skip if already mapped to a valid published page.
			if ( ! empty( $saved_pages[ $key ] ) ) {
				$existing = get_post( $saved_pages[ $key ] );
				if ( $existing && 'publish' === $existing->post_status ) {
					continue;
				}
			}

			// Check if a page with this shortcode already exists.
			$existing_page = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					's'              => $page_data['shortcode'],
					'posts_per_page' => 1,
				)
			);

			if ( ! empty( $existing_page ) ) {
				$saved_pages[ $key ] = $existing_page[0]->ID;
				continue;
			}

			// Create the page.
			$new_page = array(
				'post_title'   => $page_data['title'],
				'post_content' => $page_data['shortcode'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			);

			if ( ! empty( $page_data['slug'] ) ) {
				$new_page['post_name'] = $page_data['slug'];
			}

			$page_id = wp_insert_post( $new_page );

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$saved_pages[ $key ] = $page_id;
			}
		}

		update_option( 'wpss_pages', $saved_pages );

		self::map_existing_terms_page();
	}

	/**
	 * Point the terms setting at a terms page the site ALREADY has.
	 *
	 * Deliberately maps, never creates. A site that sells anything almost always
	 * has its own terms page, written by its owner and probably linked from the
	 * footer; publishing a second empty one under our own slug would be the
	 * plugin talking over the owner. Equally, leaving the setting empty forever
	 * means checkout and the app's `pages.terms` stay null on a site that plainly
	 * has the page - the owner just never told us which one.
	 *
	 * Only ever fills an EMPTY setting, so an owner's explicit choice is never
	 * overwritten, and only matches a published page.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	private static function map_existing_terms_page(): void {
		if ( get_option( 'wpss_terms_page' ) ) {
			return;
		}

		/**
		 * Filters the slugs searched when auto-mapping an existing terms page.
		 *
		 * @since 1.4.0
		 *
		 * @param string[] $slugs Candidate page slugs, most specific first.
		 */
		$slugs = apply_filters(
			'wpss_terms_page_slugs',
			array( 'terms', 'terms-and-conditions', 'terms-conditions', 'terms-of-service', 'terms-of-use' )
		);

		foreach ( (array) $slugs as $slug ) {
			$page = get_page_by_path( (string) $slug );

			if ( $page && 'publish' === $page->post_status ) {
				update_option( 'wpss_terms_page', (int) $page->ID );
				return;
			}
		}

		// No terms page on this site. The setting stays empty and the API reports
		// terms: null, which is the honest answer - not 0, which no client can open.
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules(): void {
		// Set flag to flush rules on next init.
		set_transient( 'wpss_flush_rewrite_rules', true, 60 );
	}
}
