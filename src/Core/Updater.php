<?php
/**
 * Plugin Updater
 *
 * Handles automatic updates via EDD Software Licensing.
 * No license activation required for the free version.
 *
 * @package WPSellServices\Core
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Core;

/**
 * Manages plugin updates via EDD Software Licensing.
 *
 * @since 1.1.0
 */
class Updater {

	/**
	 * EDD store URL.
	 *
	 * @var string
	 */
	private const STORE_URL = 'https://wbcomdesigns.com';

	/**
	 * Product ID in EDD store.
	 *
	 * @var int
	 */
	private const PRODUCT_ID = 1650261;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	private const PRODUCT_NAME = 'WP Sell Services';

	/**
	 * Initialize the updater.
	 *
	 * @return void
	 */
	public function init(): void {
		// Only run in admin.
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'setup_updater' ), 0 );
	}

	/**
	 * Setup EDD Software Licensing updater.
	 *
	 * @return void
	 */
	public function setup_updater(): void {
		// Load updater class if not already loaded.
		if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
			$updater_file = WPSS_PLUGIN_DIR . 'includes/EDD_SL_Plugin_Updater.php';
			if ( file_exists( $updater_file ) ) {
				require_once $updater_file;
			} else {
				return;
			}
		}

		// Initialize updater without license key (free version).
		new \EDD_SL_Plugin_Updater(
			self::STORE_URL,
			WPSS_PLUGIN_FILE,
			array(
				'version'   => WPSS_VERSION,
				'license'   => '',
				'item_id'   => self::PRODUCT_ID,
				'item_name' => self::PRODUCT_NAME,
				'author'    => 'Wbcom Designs',
				'beta'      => false,
			)
		);
	}
}
