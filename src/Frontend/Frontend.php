<?php
/**
 * Frontend Class
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

/**
 * Handles all frontend functionality.
 *
 * @since 1.0.0
 */
class Frontend {

	/**
	 * Enqueue frontend styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style(
			'wpss-frontend',
			WPSS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WPSS_VERSION
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// Alpine.js for reactivity.
		wp_enqueue_script(
			'alpinejs',
			WPSS_PLUGIN_URL . 'assets/js/vendor/alpine.min.js',
			array(),
			'3.13.3',
			array( 'strategy' => 'defer' )
		);

		wp_enqueue_script(
			'wpss-frontend',
			WPSS_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'alpinejs' ),
			WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-frontend',
			'wpss',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'restUrl'         => rest_url( 'wpss/v1/' ),
				'nonce'           => wp_create_nonce( 'wpss_frontend_nonce' ),
				'pollingInterval' => 10000, // 10 seconds.
				'i18n'            => array(
					'loading'      => __( 'Loading...', 'wp-sell-services' ),
					'error'        => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'sendMessage'  => __( 'Send', 'wp-sell-services' ),
					'uploadFile'   => __( 'Upload File', 'wp-sell-services' ),
					'confirmTitle' => __( 'Are you sure?', 'wp-sell-services' ),
				),
			)
		);
	}
}
