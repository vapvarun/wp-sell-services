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
use WPSellServices\Integrations\IntegrationManager;
use WPSellServices\PostTypes\ServicePostType;
use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Services\NotificationService;

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

		// Run the loader to register all hooks.
		$this->loader->run();
	}

	/**
	 * Register custom post types and taxonomies.
	 *
	 * @return void
	 */
	private function register_post_types(): void {
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
					dirname( WPSS_PLUGIN_BASENAME ) . '/languages'
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
