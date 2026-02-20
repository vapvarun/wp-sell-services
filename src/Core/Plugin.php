<?php
/**
 * Main Plugin Class
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Core;

use WPSellServices\Admin\Admin;
use WPSellServices\Frontend\Frontend;
use WPSellServices\Frontend\AjaxHandlers;
use WPSellServices\Frontend\Shortcodes;
use WPSellServices\Frontend\SingleServiceView;
use WPSellServices\Frontend\TemplateLoader;
use WPSellServices\Frontend\ServiceArchiveView;
use WPSellServices\Frontend\BuyerRequestArchiveView;
use WPSellServices\Frontend\ServiceWizard;
use WPSellServices\Frontend\VendorDashboard;
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
	 * Vendor dashboard instance.
	 *
	 * @var VendorDashboard|null
	 * @deprecated 1.1.0 Use UnifiedDashboard instead.
	 */
	private ?VendorDashboard $vendor_dashboard = null;

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
		$this->maybe_upgrade_database();
		$this->maybe_create_vendor_role();
		$this->set_locale();
		$this->define_vendor_settings_filters();
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
		$this->define_vendor_dashboard_hooks();
		$this->define_unified_dashboard_hooks();
		$this->define_auto_vendor_hooks();
		$this->define_provider_hooks();
		$this->define_cron_hooks();

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
	 * Check and upgrade database if needed.
	 *
	 * This ensures database tables are updated when the plugin is updated
	 * without deactivation/reactivation (e.g., via zip upload).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function maybe_upgrade_database(): void {
		$schema = new SchemaManager();
		if ( $schema->needs_update() ) {
			$schema->install();
		}
	}

	/**
	 * Ensure the vendor role exists.
	 *
	 * This handles existing installations where the role was never created
	 * during activation (bug fix for vendor registration errors).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function maybe_create_vendor_role(): void {
		// Vendor capabilities.
		$vendor_caps = array(
			'wpss_vendor'              => true,
			'wpss_manage_services'     => true,
			'wpss_manage_orders'       => true,
			'wpss_view_analytics'      => true,
			'wpss_respond_to_requests' => true,
			'read'                     => true,
			'upload_files'             => true,
			'edit_posts'               => true,
		);

		$role = get_role( 'wpss_vendor' );

		if ( ! $role ) {
			add_role( 'wpss_vendor', 'Vendor', $vendor_caps );
			return;
		}

		// Ensure existing role has all required capabilities.
		foreach ( $vendor_caps as $cap => $grant ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap, $grant );
			}
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
				// Vendor profile: /vendor/{username}/.
				add_rewrite_rule(
					'^vendor/([^/]+)/?$',
					'index.php?wpss_vendor=$matches[1]',
					'top'
				);

				// Service order with action: /service-order/{id}/{action}/.
				add_rewrite_rule(
					'^service-order/([0-9]+)/([^/]+)/?$',
					'index.php?wpss_service_order=$matches[1]&wpss_order_action=$matches[2]',
					'top'
				);

				// Service order view: /service-order/{id}/.
				add_rewrite_rule(
					'^service-order/([0-9]+)/?$',
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
				$vendor_settings     = get_option( 'wpss_vendor', array() );
				$registration_mode   = $vendor_settings['vendor_registration'] ?? 'open';
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
	 * Define vendor dashboard hooks.
	 *
	 * Vendor dashboard needs AJAX handlers and shortcodes for frontend display.
	 *
	 * @deprecated 1.1.0 Use define_unified_dashboard_hooks instead.
	 * @return void
	 */
	private function define_vendor_dashboard_hooks(): void {
		$this->vendor_dashboard = new VendorDashboard();

		$this->loader->add_action( 'init', $this->vendor_dashboard, 'init' );
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
	 * @since 1.1.0
	 * @return void
	 */
	private function maybe_auto_vendor_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Check if already has vendor meta.
		$has_vendor_meta = get_user_meta( $user_id, '_wpss_is_vendor', true );

		// Also check if actual vendor profile exists in database.
		$vendor_service = new \WPSellServices\Services\VendorService();
		$profile_exists = $vendor_service->get_profile( $user_id ) !== null;

		// If both meta is set AND profile exists, we're done.
		if ( $has_vendor_meta && $profile_exists ) {
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
				'verification_tier' => 'basic',
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
		// Initialize OrderWorkflowManager for cron and status change handling.
		$workflow_manager = new \WPSellServices\Services\OrderWorkflowManager();
		$workflow_manager->init();

		// Initialize EmailService for standalone email handling.
		$email_service = new \WPSellServices\Services\EmailService();
		$email_service->init();

		// Initialize DisputeWorkflowManager for dispute crons and auto-escalation.
		$dispute_workflow = new \WPSellServices\Services\DisputeWorkflowManager();
		$dispute_workflow->init();

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
