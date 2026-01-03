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
		$this->set_locale();
		$this->register_post_types();
		$this->define_admin_hooks();
		$this->define_frontend_hooks();
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
				$notification_service->notify_order_status( $order_id, $new_status, $old_status );
			},
			null,
			10,
			3
		);

		// New order notification.
		$this->loader->add_action(
			'wpss_order_status_pending_requirements',
			function ( int $order_id ) use ( $notification_service ): void {
				$notification_service->notify_order_created( $order_id );
			}
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
							$participant_id
						);
					}
				}
			},
			null,
			10,
			2
		);
	}

	/**
	 * Set the plugin locale for internationalization.
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

		// Check if already processed.
		$is_vendor = get_user_meta( $user_id, '_wpss_is_vendor', true );

		if ( $is_vendor ) {
			return;
		}

		// Make admin a vendor.
		$vendor_service = new \WPSellServices\Services\VendorService();
		$vendor_service->register( $user_id );
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
