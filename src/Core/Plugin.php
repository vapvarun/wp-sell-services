<?php
/**
 * Main Plugin Class
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Admin\Admin;
use WPSellServices\Admin\ProTeaser;
use WPSellServices\Frontend\Frontend;
use WPSellServices\Frontend\AjaxHandlers;
use WPSellServices\Frontend\Shortcodes;
use WPSellServices\Frontend\SingleServiceView;
use WPSellServices\Frontend\TemplateLoader;
use WPSellServices\Frontend\ServiceArchiveView;
use WPSellServices\Frontend\BuyerRequestArchiveView;
use WPSellServices\Frontend\ServiceWizard;
use WPSellServices\Frontend\UnifiedDashboard;
use WPSellServices\Integrations\IntegrationManager;
use WPSellServices\PostTypes\ServicePostType;
use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Taxonomies\ServiceCategoryTaxonomy;
use WPSellServices\Taxonomies\ServiceTagTaxonomy;
use WPSellServices\Services\NotificationService;
use WPSellServices\API\API;
use WPSellServices\Blocks\BlocksManager;
use WPSellServices\SEO\SEO;
use WPSellServices\Database\SchemaManager;

/**
 * Main plugin class.
 *
 * Orchestrates the plugin initialization and manages all components.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public const VERSION = '1.0.0';

	/**
	 * Loader instance for managing hooks.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Integration manager instance.
	 *
	 * @var IntegrationManager|null
	 */
	private ?IntegrationManager $integration_manager = null;

	/**
	 * Admin instance.
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * Frontend instance.
	 *
	 * @var Frontend|null
	 */
	private ?Frontend $frontend = null;

	/**
	 * Blocks manager instance.
	 *
	 * @var BlocksManager|null
	 */
	private ?BlocksManager $blocks_manager = null;

	/**
	 * SEO instance.
	 *
	 * @var SEO|null
	 */
	private ?SEO $seo = null;

	/**
	 * Abilities registrar instance.
	 *
	 * @var AbilitiesRegistrar|null
	 * @since 1.4.0
	 */
	private ?AbilitiesRegistrar $abilities_registrar = null;

	/**
	 * Registered payment gateways.
	 *
	 * @var array
	 * @since 1.1.0
	 */
	private array $payment_gateways = array();

	/**
	 * Registered wallet providers.
	 *
	 * @var array
	 * @since 1.1.0
	 */
	private array $wallet_providers = array();

	/**
	 * Registered storage providers.
	 *
	 * @var array
	 * @since 1.1.0
	 */
	private array $storage_providers = array();

	/**
	 * Registered email providers.
	 *
	 * @var array
	 * @since 1.1.0
	 */
	private array $email_providers = array();

	/**
	 * Registered analytics widgets.
	 *
	 * @var array
	 * @since 1.1.0
	 */
	private array $analytics_widgets = array();

	/**
	 * AJAX handlers instance.
	 *
	 * @var AjaxHandlers|null
	 */
	private ?AjaxHandlers $ajax_handlers = null;

	/**
	 * Shortcodes instance.
	 *
	 * @var Shortcodes|null
	 */
	private ?Shortcodes $shortcodes = null;

	/**
	 * Single service view instance.
	 *
	 * @var SingleServiceView|null
	 */
	private ?SingleServiceView $single_service_view = null;

	/**
	 * Service archive view instance.
	 *
	 * @var ServiceArchiveView|null
	 */
	private ?ServiceArchiveView $service_archive_view = null;

	/**
	 * Buyer request archive view instance.
	 *
	 * @var BuyerRequestArchiveView|null
	 */
	private ?BuyerRequestArchiveView $buyer_request_archive_view = null;

	/**
	 * Service wizard instance.
	 *
	 * @var ServiceWizard|null
	 */
	private ?ServiceWizard $service_wizard = null;

	/**
	 * Unified dashboard instance.
	 *
	 * @var UnifiedDashboard|null
	 * @since 1.1.0
	 */
	private ?UnifiedDashboard $unified_dashboard = null;

	/**
	 * Get plugin instance (Singleton).
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->loader = new Loader();
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_updater();
		$this->maybe_run_install();
		$this->set_locale();
		$this->define_vendor_settings_filters();
		$this->define_avatar_filter();
		$this->register_post_types();
		$this->register_rewrite_rules();
		$this->define_admin_hooks();
		$this->define_frontend_hooks();
		$this->define_ajax_hooks();
		$this->define_integration_hooks();
		$this->define_notification_hooks();
		$this->define_api_hooks();
		$this->define_blocks_hooks();
		$this->define_seo_hooks();
		$this->define_shortcode_hooks();
		$this->define_wizard_hooks();
		$this->define_unified_dashboard_hooks();
		$this->define_auto_vendor_hooks();
		$this->define_provider_hooks();
		$this->define_cron_hooks();
		$this->define_cascade_hooks();
		$this->define_pro_teasers();
		$this->define_abilities_hooks();

		// Post-payment tip crediting — hooks wpss_order_paid to credit the
		// vendor wallet when a tip-platform order is paid via the gateway.
		( new \WPSellServices\Services\TippingService() )->init();

		// Run the loader to register all hooks.
		$this->loader->run();

		/**
		 * Fires after the plugin is fully loaded.
		 *
		 * @since 1.0.0
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wpss_loaded', $this );
	}

	/**
	 * Initialize the plugin updater.
	 *
	 * Sets up EDD Software Licensing for automatic updates.
	 * No license required for the free version.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function init_updater(): void {
		$updater = new Updater();
		$updater->init();
	}

	/**
	 * Run install/upgrade routine when plugin version changes.
	 *
	 * Compares the stored plugin version against the code version (WPSS_VERSION).
	 * When they differ — fresh install, zip-upload update, or git pull — runs
	 * the Activator which handles DB schema, roles, and default settings.
	 *
	 * Page creation and rewrite flush are deferred to the `init` action because
	 * they require $wp_rewrite which is not available on plugins_loaded.
	 *
	 * Follows the WooCommerce check_version() / install() pattern: heavy setup
	 * work runs only on version change, not on every request.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function maybe_run_install(): void {
		$installed_version = get_option( 'wpss_version', '' );

		if ( version_compare( $installed_version, WPSS_VERSION, '<' ) ) {
			// DB, roles, settings — safe on plugins_loaded.
			Activator::install();

			// Page creation needs $wp_rewrite — defer to init.
			add_action(
				'init',
				static function () use ( $installed_version ): void {
					Activator::create_pages();
					flush_rewrite_rules();

					/**
					 * Fires after the plugin has been installed or upgraded.
					 *
					 * @since 1.3.0
					 * @param string $installed_version Previous version (empty on fresh install).
					 * @param string $new_version       Current code version.
					 */
					do_action( 'wpss_updated', $installed_version, WPSS_VERSION );
				},
				5
			);

			update_option( 'wpss_version', WPSS_VERSION );
		}
	}

	/**
	 * Define REST API hooks.
	 *
	 * @return void
	 */
	private function define_api_hooks(): void {
		$api = new API();
		$this->loader->add_action( 'rest_api_init', $api, 'register_routes' );
	}

	/**
	 * Register custom post types and taxonomies.
	 *
	 * @return void
	 */
	private function register_post_types(): void {
		// Register taxonomies first (before post types).
		$service_category = new ServiceCategoryTaxonomy();
		$service_category->init();

		$service_tag = new ServiceTagTaxonomy();
		$service_tag->init();

		// Register post types.
		$service_post_type = new ServicePostType();
		$service_post_type->init();

		$buyer_request_post_type = new BuyerRequestPostType();
		$buyer_request_post_type->init();
	}

	/**
	 * Register rewrite rules for vendor profiles and service orders.
	 *
	 * Must run in ALL contexts (admin + frontend) so rules
	 * are captured when flushed from the admin permalink page.
	 *
	 * @return void
	 */
	private function register_rewrite_rules(): void {
		add_action(
			'init',
			function (): void {
				/**
				 * Filter the vendor profile URL slug.
				 *
				 * @since 1.3.0
				 * @param string $slug Default vendor slug.
				 */
				$vendor_slug = apply_filters( 'wpss_vendor_slug', 'provider' );

				/**
				 * Filter the service order URL slug.
				 *
				 * @since 1.3.0
				 * @param string $slug Default service-order slug.
				 */
				$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );

				// Vendor profile: /vendor/{username}/.
				add_rewrite_rule(
					'^' . $vendor_slug . '/([^/]+)/?$',
					'index.php?wpss_vendor=$matches[1]',
					'top'
				);

				// Service order with action: /service-order/{id}/{action}/.
				add_rewrite_rule(
					'^' . $order_slug . '/([0-9]+)/([^/]+)/?$',
					'index.php?wpss_service_order=$matches[1]&wpss_order_action=$matches[2]',
					'top'
				);

				// Service order view: /service-order/{id}/.
				add_rewrite_rule(
					'^' . $order_slug . '/([0-9]+)/?$',
					'index.php?wpss_service_order=$matches[1]',
					'top'
				);
			},
			5
		);

		// Register query vars in all contexts.
		add_filter(
			'query_vars',
			function ( array $vars ): array {
				$vars[] = 'wpss_vendor';
				$vars[] = 'wpss_service_order';
				$vars[] = 'wpss_order_action';
				return $vars;
			}
		);

		// Flush rewrite rules once after activation (consumes transient set by Activator).
		add_action(
			'init',
			function (): void {
				if ( get_transient( 'wpss_flush_rewrite_rules' ) ) {
					delete_transient( 'wpss_flush_rewrite_rules' );
					flush_rewrite_rules();
				}
			},
			99
		);
	}

	/**
	 * Define notification event hooks.
	 *
	 * @return void
	 */
	private function define_notification_hooks(): void {
		$notification_service = new NotificationService();

		// Order status change notifications.
		$this->loader->add_action(
			'wpss_order_status_changed',
			function ( int $order_id, string $new_status, string $old_status ) use ( $notification_service ): void {
				// For new orders (pending_requirements with no old status), send specific new order notification.
				if ( 'pending_requirements' === $new_status && empty( $old_status ) ) {
					$notification_service->notify_order_created( $order_id );
					return; // Don't send generic status update for new orders.
				}

				// For all other status changes, send generic notification.
				$notification_service->notify_order_status( $order_id, $new_status, $old_status );
			},
			null,
			10,
			3
		);

		// Message sent notification.
		$this->loader->add_action(
			'wpss_message_sent',
			function ( $message, $conversation ) use ( $notification_service ): void {
				// Notify other participants.
				foreach ( $conversation->participants as $participant_id ) {
					if ( $participant_id !== $message->sender_id ) {
						$notification_service->notify_new_message(
							$conversation->id,
							$message->sender_id,
							$participant_id,
							$message->content ?? '' // Include actual message content.
						);
					}
				}
			},
			null,
			10,
			2
		);

		// Vendor registration notification.
		$this->loader->add_action(
			'wpss_vendor_registered',
			function ( int $user_id, array $profile_data ) use ( $notification_service ): void {
				$notification_service->notify_vendor_registered( $user_id, $profile_data );
			},
			null,
			10,
			2
		);

		// Vendor status update notifications (approval/rejection).
		$this->loader->add_action(
			'wpss_vendor_status_updated',
			function ( int $vendor_id, string $status ) use ( $notification_service ): void {
				if ( 'active' === $status ) {
					$notification_service->notify_vendor_approved( $vendor_id );
				} elseif ( in_array( $status, array( 'rejected', 'suspended' ), true ) ) {
					$notification_service->notify_vendor_rejected( $vendor_id );
				}
			},
			null,
			10,
			2
		);

		// Review created notification.
		$this->loader->add_action(
			'wpss_review_created',
			function ( int $review_id, int $order_id ) use ( $notification_service ): void {
				$notification_service->notify_review_received( $review_id, $order_id );
			},
			null,
			10,
			2
		);

		// Dispute resolved notification.
		$this->loader->add_action(
			'wpss_dispute_resolved',
			function ( int $dispute_id, string $resolution, $dispute, float $refund_amount ) use ( $notification_service ): void {
				$notification_service->notify_dispute_resolved( $dispute_id, $resolution, $dispute, $refund_amount );
			},
			null,
			10,
			4
		);
	}

	/**
	 * Set the plugin locale for internationalization.
	 *
	 * Note: Text domain is loaded immediately in init() method for AJAX calls.
	 * This hook ensures it's also loaded on 'init' for standard page loads.
	 *
	 * @return void
	 */
	private function set_locale(): void {
		$this->loader->add_action(
			'init',
			function (): void {
				load_plugin_textdomain(
					'wp-sell-services',
					false,
					dirname( \WPSS_PLUGIN_BASENAME ) . '/languages'
				);
			}
		);
	}

	/**
	 * Define filters to connect vendor settings to their filters.
	 *
	 * This connects the wpss_vendor_registration_open and wpss_auto_approve_vendors
	 * filters to the actual admin settings values.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function define_vendor_settings_filters(): void {
		// Connect vendor registration open/closed filter to settings.
		add_filter(
			'wpss_vendor_registration_open',
			function ( bool $default ): bool {
				$vendor_settings   = get_option( 'wpss_vendor', array() );
				$registration_mode = $vendor_settings['vendor_registration'] ?? 'open';
				return 'closed' !== $registration_mode;
			}
		);

		// Connect auto-approve vendors filter to settings.
		add_filter(
			'wpss_auto_approve_vendors',
			function ( bool $default ): bool {
				$vendor_settings   = get_option( 'wpss_vendor', array() );
				$registration_mode = $vendor_settings['vendor_registration'] ?? 'open';
				return 'open' === $registration_mode;
			}
		);

		// Connect service moderation filter to settings.
		add_filter(
			'wpss_require_service_moderation',
			function ( bool $default ): bool {
				$vendor_settings = get_option( 'wpss_vendor', array() );
				return ! empty( $vendor_settings['require_service_moderation'] );
			}
		);

		// Enforce max services per vendor limit for REST API service creation.
		add_filter(
			'wpss_vendor_can_create_service',
			function ( bool $can_create, int $vendor_id ): bool {
				if ( ! $can_create ) {
					return false;
				}

				$vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $vendor_id );
				if ( $vendor_profile && $vendor_profile->has_reached_service_limit() ) {
					return false;
				}

				return true;
			},
			10,
			2
		);
	}

	/**
	 * Filter WordPress avatar to use vendor's uploaded profile picture.
	 *
	 * Hooks into 'pre_get_avatar_data' so that both get_avatar() and
	 * get_avatar_url() return the custom image when a vendor has uploaded one.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function define_avatar_filter(): void {
		// In-memory cache to avoid repeated DB queries within a single request.
		$cache = array();

		add_filter(
			'pre_get_avatar_data',
			function ( array $args, $id_or_email ) use ( &$cache ): array {
				$user_id = 0;

				if ( is_numeric( $id_or_email ) ) {
					$user_id = (int) $id_or_email;
				} elseif ( $id_or_email instanceof \WP_User ) {
					$user_id = $id_or_email->ID;
				} elseif ( $id_or_email instanceof \WP_Post ) {
					$user_id = (int) $id_or_email->post_author;
				} elseif ( $id_or_email instanceof \WP_Comment ) {
					$user_id = (int) $id_or_email->user_id;
				}

				if ( ! $user_id ) {
					return $args;
				}

				// Check in-memory cache first.
				if ( ! array_key_exists( $user_id, $cache ) ) {
					// First check user meta (works for ALL users including customers).
					$meta_avatar = get_user_meta( $user_id, '_wpss_avatar_id', true );

					if ( $meta_avatar ) {
						$cache[ $user_id ] = (int) $meta_avatar;
					} else {
						// Fall back to vendor profiles table for vendors.
						global $wpdb;
						$table = $wpdb->prefix . 'wpss_vendor_profiles';
						$raw   = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT avatar_id FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$user_id
							)
						);

						$cache[ $user_id ] = $raw ? (int) $raw : 0;
					}
				}

				$avatar_id = $cache[ $user_id ];

				if ( ! $avatar_id ) {
					return $args;
				}

				$url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
				if ( ! $url ) {
					return $args;
				}

				$args['url']          = $url;
				$args['found_avatar'] = true;

				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Define admin-specific hooks.
	 *
	 * @return void
	 */
	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$this->admin = new Admin();

		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $this->admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
	}

	/**
	 * Define frontend-specific hooks.
	 *
	 * @return void
	 */
	private function define_frontend_hooks(): void {
		if ( is_admin() ) {
			return;
		}

		$this->frontend = new Frontend();

		$this->loader->add_action( 'wp_enqueue_scripts', $this->frontend, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->frontend, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_footer', $this->frontend, 'render_mini_cart' );

		// Initialize single service view.
		$this->single_service_view = new SingleServiceView();
		$this->single_service_view->init();

		// Initialize service archive view.
		$this->service_archive_view = new ServiceArchiveView();
		$this->service_archive_view->init();

		// Initialize buyer request archive view.
		$this->buyer_request_archive_view = new BuyerRequestArchiveView();
		$this->buyer_request_archive_view->init();

		// Initialize template loader.
		$template_loader = new TemplateLoader();
		$template_loader->init();
	}

	/**
	 * Define AJAX hooks.
	 *
	 * AJAX handlers need to be registered on both admin and frontend contexts
	 * since admin-ajax.php runs in admin context.
	 *
	 * @return void
	 */
	private function define_ajax_hooks(): void {
		$this->ajax_handlers = new AjaxHandlers();
		$this->ajax_handlers->init();
	}

	/**
	 * Define integration hooks.
	 *
	 * @return void
	 */
	private function define_integration_hooks(): void {
		$this->integration_manager = new IntegrationManager();

		$this->loader->add_action( 'init', $this->integration_manager, 'init' );
	}

	/**
	 * Define Gutenberg blocks hooks.
	 *
	 * @return void
	 */
	private function define_blocks_hooks(): void {
		$this->blocks_manager = BlocksManager::instance();

		$this->loader->add_action( 'init', $this->blocks_manager, 'init' );
	}

	/**
	 * Define SEO hooks.
	 *
	 * @return void
	 */
	private function define_seo_hooks(): void {
		$this->seo = new SEO();

		$this->loader->add_action( 'wp', $this->seo, 'init' );
	}

	/**
	 * Define shortcode hooks.
	 *
	 * Shortcodes need to be available on both frontend and admin (for editor preview).
	 *
	 * @return void
	 */
	private function define_shortcode_hooks(): void {
		$this->shortcodes = new Shortcodes();

		$this->loader->add_action( 'init', $this->shortcodes, 'init' );
	}

	/**
	 * Define wizard hooks.
	 *
	 * Service wizard needs AJAX handlers available on admin (for admin-ajax.php)
	 * and shortcode on frontend. Initialize on both contexts.
	 *
	 * @return void
	 */
	private function define_wizard_hooks(): void {
		$this->service_wizard = new ServiceWizard();

		$this->loader->add_action( 'init', $this->service_wizard, 'init' );
	}

	/**
	 * Define unified dashboard hooks.
	 *
	 * Single dashboard for both buyers and vendors.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function define_unified_dashboard_hooks(): void {
		$this->unified_dashboard = new UnifiedDashboard();

		$this->loader->add_action( 'init', $this->unified_dashboard, 'init' );
	}

	/**
	 * Define auto-vendor hooks.
	 *
	 * Automatically makes administrators vendors.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function define_auto_vendor_hooks(): void {
		// Auto-vendor for admins on admin_init.
		$this->loader->add_action(
			'admin_init',
			function (): void {
				$this->maybe_auto_vendor_admin();
			}
		);

		// Also run on plugin activation.
		register_activation_hook(
			WPSS_PLUGIN_FILE,
			function (): void {
				$this->maybe_auto_vendor_admin();
			}
		);
	}

	/**
	 * Maybe make admin user a vendor automatically.
	 *
	 * Uses a user meta flag to skip the expensive VendorService check
	 * on subsequent admin page loads after the initial vendor setup.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function maybe_auto_vendor_admin(): void {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Fast path: already checked and confirmed for this user.
		if ( get_user_meta( $user_id, '_wpss_vendor_checked', true ) ) {
			return;
		}

		// Check if already has vendor meta.
		$has_vendor_meta = get_user_meta( $user_id, '_wpss_is_vendor', true );

		// Also check if actual vendor profile exists in database.
		$vendor_service = new \WPSellServices\Services\VendorService();
		$profile_exists = $vendor_service->get_profile( $user_id ) !== null;

		// If both meta is set AND profile exists, we're done.
		if ( $has_vendor_meta && $profile_exists ) {
			update_user_meta( $user_id, '_wpss_vendor_checked', '1' );
			return;
		}

		// Register as vendor (creates profile and sets meta).
		// Note: VendorService::register() checks is_vendor() which might return true
		// if role exists. Use ensure_vendor_profile() for just the profile.
		if ( ! $profile_exists ) {
			$this->ensure_vendor_profile( $user_id );
		}

		// Ensure meta is set.
		if ( ! $has_vendor_meta ) {
			update_user_meta( $user_id, '_wpss_is_vendor', true );
		}

		update_user_meta( $user_id, '_wpss_vendor_checked', '1' );
	}

	/**
	 * Ensure vendor profile exists in database.
	 *
	 * Creates a vendor profile if one doesn't exist, without modifying roles.
	 *
	 * @since 1.1.0
	 * @param int $user_id User ID.
	 * @return bool True if profile exists or was created.
	 */
	private function ensure_vendor_profile( int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// Check if profile already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		if ( $exists ) {
			return true;
		}

		// Create profile.
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'user_id'           => $user_id,
				'display_name'      => $user->display_name,
				'status'            => 'active',
				'verification_tier' => 'new',
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Define provider hooks for Pro extension.
	 *
	 * These filters allow the Pro plugin to register additional providers
	 * for payments, wallets, storage, email, and analytics.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function define_provider_hooks(): void {
		$this->loader->add_action(
			'init',
			function (): void {
				$this->init_providers();
			},
			null,
			20 // After wpss_loaded fires so Pro can register first.
		);
	}

	/**
	 * Initialize providers via filters.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function init_providers(): void {
		// Register Test Gateway (only in debug mode).
		$this->maybe_register_test_gateway();

		// Register Stripe Gateway.
		$stripe_gateway = new \WPSellServices\Integrations\Stripe\StripeGateway();
		$stripe_gateway->init();
		$this->payment_gateways['stripe'] = $stripe_gateway;

		// Register PayPal Gateway.
		$paypal_gateway = new \WPSellServices\Integrations\PayPal\PayPalGateway();
		$paypal_gateway->init();
		$this->payment_gateways['paypal'] = $paypal_gateway;

		// Register Offline Gateway (always available).
		$offline_gateway = new \WPSellServices\Integrations\Gateways\OfflineGateway();
		$offline_gateway->init();
		$this->payment_gateways['offline'] = $offline_gateway;

		/**
		 * Filter the registered payment gateways.
		 *
		 * Allows Pro or third-party plugins to register payment gateways
		 * for standalone mode (without WooCommerce).
		 *
		 * @since 1.1.0
		 *
		 * @param array $gateways Array of payment gateway instances.
		 */
		$this->payment_gateways = apply_filters( 'wpss_payment_gateways', $this->payment_gateways );

		/**
		 * Filter the registered wallet providers.
		 *
		 * Allows Pro or third-party plugins to register wallet integrations
		 * for vendor payouts and balance management.
		 *
		 * @since 1.1.0
		 *
		 * @param array $providers Array of wallet provider instances.
		 */
		$this->wallet_providers = apply_filters( 'wpss_wallet_providers', $this->wallet_providers );

		/**
		 * Filter the registered storage providers.
		 *
		 * Allows Pro or third-party plugins to register cloud storage
		 * for service deliveries (S3, GCS, etc.).
		 *
		 * @since 1.1.0
		 *
		 * @param array $providers Array of storage provider instances.
		 */
		$this->storage_providers = apply_filters( 'wpss_storage_providers', $this->storage_providers );

		/**
		 * Filter the registered email providers.
		 *
		 * Allows Pro or third-party plugins to register email services
		 * (SendGrid, Mailgun, SES, etc.).
		 *
		 * @since 1.1.0
		 *
		 * @param array $providers Array of email provider instances.
		 */
		$this->email_providers = apply_filters( 'wpss_email_providers', $this->email_providers );

		/**
		 * Filter the registered analytics widgets.
		 *
		 * Allows Pro or third-party plugins to register analytics
		 * dashboard widgets.
		 *
		 * @since 1.1.0
		 *
		 * @param array $widgets Array of analytics widget instances.
		 */
		$this->analytics_widgets = apply_filters( 'wpss_analytics_widgets', $this->analytics_widgets );
	}

	/**
	 * Register test gateway if in debug mode.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function maybe_register_test_gateway(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$test_gateway = new \WPSellServices\Integrations\Gateways\TestGateway();
		$test_gateway->init();
		$this->payment_gateways['test'] = $test_gateway;
	}

	/**
	 * Define cron action hooks.
	 *
	 * Registers handlers for scheduled cron events.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function define_cron_hooks(): void {
		// --- OrderWorkflowManager: lazy-init on first hook fire ---
		// Singleton factory shared across all closures.
		$get_order_workflow = static function (): \WPSellServices\Services\OrderWorkflowManager {
			static $instance;
			if ( null === $instance ) {
				$instance = new \WPSellServices\Services\OrderWorkflowManager();
			}
			return $instance;
		};

		// Cron schedule registration (lightweight, no object needed).
		add_filter(
			'cron_schedules',
			static function ( array $schedules ): array {
				$schedules['wpss_hourly']      = array(
					'interval' => HOUR_IN_SECONDS,
					'display'  => 'Every Hour (WPSS)',
				);
				$schedules['wpss_twice_daily'] = array(
					'interval' => 12 * HOUR_IN_SECONDS,
					'display'  => 'Twice Daily (WPSS)',
				);
				$schedules['wpss_weekly']      = array(
					'interval' => WEEK_IN_SECONDS,
					'display'  => 'Once Weekly (WPSS)',
				);
				$schedules['weekly']           = array(
					'interval' => WEEK_IN_SECONDS,
					'display'  => 'Weekly',
				);
				$schedules['biweekly']         = array(
					'interval' => 14 * DAY_IN_SECONDS,
					'display'  => 'Every 14 Days (Bi-weekly)',
				);
				$schedules['monthly']          = array(
					'interval' => 30 * DAY_IN_SECONDS,
					'display'  => 'Monthly',
				);
				return $schedules;
			}
		);

		// Cron event scheduling (lightweight, no object needed).
		add_action(
			'init',
			static function (): void {
				$events = array(
					'wpss_check_late_orders'             => 'wpss_hourly',
					'wpss_auto_complete_orders'          => 'wpss_twice_daily',
					'wpss_send_deadline_reminders'       => 'daily',
					'wpss_send_requirements_reminders'   => 'daily',
					'wpss_check_requirements_timeout'    => 'daily',
					'wpss_recalculate_seller_levels'     => 'wpss_weekly',
					'wpss_process_cancellation_timeouts' => 'wpss_hourly',
					'wpss_process_offline_auto_cancel'   => 'wpss_hourly',
					'wpss_cleanup_expired_requests'      => 'daily',
					'wpss_update_vendor_stats'           => 'wpss_twice_daily',
				);

				foreach ( $events as $hook => $recurrence ) {
					if ( ! wp_next_scheduled( $hook ) ) {
						wp_schedule_event( time(), $recurrence, $hook );
					}
				}
			}
		);

		// Cron handlers — object created only when cron actually fires.
		$cron_hooks = array(
			'wpss_check_late_orders',
			'wpss_auto_complete_orders',
			'wpss_send_deadline_reminders',
			'wpss_send_requirements_reminders',
			'wpss_check_requirements_timeout',
			'wpss_recalculate_seller_levels',
			'wpss_process_cancellation_timeouts',
			'wpss_process_offline_auto_cancel',
			'wpss_cleanup_expired_requests',
			'wpss_update_vendor_stats',
		);

		foreach ( $cron_hooks as $hook ) {
			$method = str_replace( 'wpss_', '', $hook );
			add_action(
				$hook,
				static function () use ( $get_order_workflow, $method ): void {
					$get_order_workflow()->$method();
				}
			);
		}

		// Status change hooks.
		$status_hooks = array(
			'wpss_order_status_changed'                => array( 'handle_status_change', 10, 3 ),
			'wpss_order_status_completed'              => array( 'handle_order_completed', 10, 2 ),
			'wpss_order_status_cancelled'              => array( 'handle_order_cancelled', 10, 2 ),
			'wpss_order_status_cancellation_requested' => array( 'handle_cancellation_requested', 10, 2 ),
		);

		foreach ( $status_hooks as $hook => $config ) {
			add_action(
				$hook,
				static function ( ...$args ) use ( $get_order_workflow, $config ): void {
					$get_order_workflow()->{$config[0]}( ...$args );
				},
				$config[1],
				$config[2]
			);
		}

		// Note: wpss_order_status_changed → log_status_change wiring lives in
		// OrderWorkflowManager::define_hooks(). Having a second listener here
		// caused duplicate audit log rows because the wpss_order_status_changed
		// signature is ($order_id, $new_status, $old_status) — the positional
		// swap mismatched the log_status_change() signature and bypassed the
		// static dedup key.

		// Payment hooks.
		add_action(
			'wpss_order_paid',
			static function ( int $order_id, string $transaction_id = '' ) use ( $get_order_workflow ): void {
				$get_order_workflow()->handle_payment_complete( $order_id, $transaction_id );
			},
			10,
			2
		);

		// Set delivery deadline when requirements are submitted.
		add_action(
			'wpss_requirements_submitted',
			static function ( int $order_id, array $field_data, array $attachments ): void {
				$order_service = new \WPSellServices\Services\OrderService();
				$order_service->set_deadline_on_requirements( $order_id, $field_data, $attachments );
			},
			10,
			3
		);

		// --- EmailService: lazy-init on first hook fire ---
		$get_email_service = static function (): \WPSellServices\Services\EmailService {
			static $instance;
			if ( null === $instance ) {
				$instance = new \WPSellServices\Services\EmailService();
			}
			return $instance;
		};

		// Email hook map: hook => [ method, priority, accepted_args ].
		$email_hooks = array(
			'wpss_order_status_changed'             => array( 'handle_status_change', 20, 3 ),
			'wpss_requirements_submitted'           => array( 'send_requirements_submitted', 20, 3 ),
			'wpss_delivery_submitted'               => array( 'send_delivery_ready', 20, 2 ),
			'wpss_new_order_message'                => array( 'send_new_message', 20, 3 ),
			'wpss_send_requirements_reminder_email' => array( 'send_requirements_reminder', 10, 3 ),
			'wpss_vendor_level_promoted'            => array( 'send_level_promotion', 10, 3 ),
			'wpss_withdrawal_processed'             => array( 'send_withdrawal_status', 10, 3 ),
			'wpss_proposal_submitted'               => array( 'send_proposal_submitted', 10, 4 ),
			'wpss_proposal_accepted'                => array( 'send_proposal_accepted', 10, 3 ),
			'wpss_proposal_rejected'                => array( 'send_proposal_rejected', 10, 3 ),
		);

		foreach ( $email_hooks as $hook => $config ) {
			add_action(
				$hook,
				static function ( ...$args ) use ( $get_email_service, $config ): void {
					$get_email_service()->{$config[0]}( ...$args );
				},
				$config[1],
				$config[2]
			);
		}

		// --- DisputeWorkflowManager: lazy-init on first hook fire ---
		$get_dispute_workflow = static function (): \WPSellServices\Services\DisputeWorkflowManager {
			static $instance;
			if ( null === $instance ) {
				$instance = new \WPSellServices\Services\DisputeWorkflowManager();
			}
			return $instance;
		};

		// Dispute cron schedule registration.
		add_filter(
			'cron_schedules',
			static function ( array $schedules ): array {
				$schedules['twice_daily'] = array(
					'interval' => 12 * HOUR_IN_SECONDS,
					'display'  => 'Twice Daily',
				);
				return $schedules;
			}
		);

		// Schedule the daily dispute cron event.
		if ( ! wp_next_scheduled( 'wpss_cron_daily' ) ) {
			wp_schedule_event( time(), 'daily', 'wpss_cron_daily' );
		}

		// Dispute cron handlers.
		add_action(
			'wpss_cron_daily',
			static function () use ( $get_dispute_workflow ): void {
				$manager = $get_dispute_workflow();
				$manager->check_response_deadlines();
				$manager->auto_escalate_disputes();
				$manager->send_reminder_notifications();
				$manager->auto_open_disputes_for_late_orders();
			}
		);

		// Dispute event hooks.
		$dispute_event_hooks = array(
			'wpss_dispute_opened'             => array( 'on_dispute_opened', 10, 4 ),
			'wpss_dispute_response_submitted' => array( 'on_response_submitted', 10, 3 ),
			'wpss_dispute_evidence_added'     => array( 'on_evidence_added', 10, 2 ),
			'wpss_dispute_resolved'           => array( 'on_dispute_resolved', 10, 4 ),
		);

		foreach ( $dispute_event_hooks as $hook => $config ) {
			add_action(
				$hook,
				static function ( ...$args ) use ( $get_dispute_workflow, $config ): void {
					$get_dispute_workflow()->{$config[0]}( ...$args );
				},
				$config[1],
				$config[2]
			);
		}

		// Register EarningsService cron schedules early so they are available during activation.
		add_filter( 'cron_schedules', array( \WPSellServices\Services\EarningsService::class, 'add_cron_schedules' ) );

		// Auto-withdrawal processing.
		$this->loader->add_action(
			'wpss_process_auto_withdrawals',
			function (): void {
				$earnings_service = new \WPSellServices\Services\EarningsService();
				$earnings_service->process_auto_withdrawals();
			}
		);
	}

	/**
	 * Define cascade deletion hooks.
	 *
	 * Ensures plugin data in custom tables is cleaned up when
	 * services, buyer requests, or users are permanently deleted.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	private function define_cascade_hooks(): void {
		// Lazy-init: DataCascadeHandler only created when a post or user is deleted.
		$get_cascade_handler = static function (): \WPSellServices\Services\DataCascadeHandler {
			static $instance;
			if ( null === $instance ) {
				$instance = new \WPSellServices\Services\DataCascadeHandler();
			}
			return $instance;
		};

		add_action(
			'before_delete_post',
			static function ( int $post_id ) use ( $get_cascade_handler ): void {
				$get_cascade_handler()->on_post_deleted( $post_id );
			},
			10,
			1
		);
		add_action(
			'delete_user',
			static function ( int $user_id ) use ( $get_cascade_handler ): void {
				$get_cascade_handler()->on_user_deleted( $user_id );
			},
			10,
			1
		);
	}

	/**
	 * Define Pro upgrade teasers.
	 *
	 * Shows upgrade CTAs throughout the free plugin when Pro is not active.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function define_pro_teasers(): void {
		$pro_teaser = new ProTeaser();
		$pro_teaser->init();
	}

	/**
	 * Define WordPress Abilities API hooks.
	 *
	 * Registers marketplace abilities for AI assistant integration.
	 * Only activates on WordPress 6.9+ which includes the Abilities API.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private function define_abilities_hooks(): void {
		// Abilities API is available in WP 6.9+.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->abilities_registrar = new AbilitiesRegistrar();
		$this->abilities_registrar->init();
	}

	/**
	 * Get the loader instance.
	 *
	 * @return Loader
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	/**
	 * Get the integration manager instance.
	 *
	 * @return IntegrationManager|null
	 */
	public function get_integration_manager(): ?IntegrationManager {
		return $this->integration_manager;
	}

	/**
	 * Get the admin instance.
	 *
	 * @return Admin|null
	 */
	public function get_admin(): ?Admin {
		return $this->admin;
	}

	/**
	 * Get the frontend instance.
	 *
	 * @return Frontend|null
	 */
	public function get_frontend(): ?Frontend {
		return $this->frontend;
	}

	/**
	 * Get the blocks manager instance.
	 *
	 * @return BlocksManager|null
	 */
	public function get_blocks_manager(): ?BlocksManager {
		return $this->blocks_manager;
	}

	/**
	 * Get the SEO instance.
	 *
	 * @return SEO|null
	 */
	public function get_seo(): ?SEO {
		return $this->seo;
	}

	/**
	 * Get the shortcodes instance.
	 *
	 * @return Shortcodes|null
	 */
	public function get_shortcodes(): ?Shortcodes {
		return $this->shortcodes;
	}

	/**
	 * Get the single service view instance.
	 *
	 * @return SingleServiceView|null
	 */
	public function get_single_service_view(): ?SingleServiceView {
		return $this->single_service_view;
	}

	/**
	 * Get the service wizard instance.
	 *
	 * @return ServiceWizard|null
	 */
	public function get_service_wizard(): ?ServiceWizard {
		return $this->service_wizard;
	}

	/**
	 * Get registered payment gateways.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_payment_gateways(): array {
		return $this->payment_gateways;
	}

	/**
	 * Get a single payment gateway by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @return \WPSellServices\Integrations\Contracts\PaymentGatewayInterface|null
	 */
	public function get_payment_gateway( string $gateway_id ) {
		return $this->payment_gateways[ $gateway_id ] ?? null;
	}

	/**
	 * Get registered wallet providers.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_wallet_providers(): array {
		return $this->wallet_providers;
	}

	/**
	 * Get active wallet provider.
	 *
	 * Returns the wallet provider configured in settings, or null if none.
	 *
	 * @since 1.1.0
	 * @return object|null
	 */
	public function get_active_wallet_provider(): ?object {
		$active_id = get_option( 'wpss_wallet_provider', '' );

		if ( empty( $active_id ) || ! isset( $this->wallet_providers[ $active_id ] ) ) {
			// Return first available provider if configured one not found.
			return ! empty( $this->wallet_providers ) ? reset( $this->wallet_providers ) : null;
		}

		return $this->wallet_providers[ $active_id ];
	}

	/**
	 * Get registered storage providers.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_storage_providers(): array {
		return $this->storage_providers;
	}

	/**
	 * Get registered email providers.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_email_providers(): array {
		return $this->email_providers;
	}

	/**
	 * Get registered analytics widgets.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_analytics_widgets(): array {
		return $this->analytics_widgets;
	}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \Exception Always throws exception.
	 * @return void
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
