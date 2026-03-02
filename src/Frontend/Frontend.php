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
		// Design system tokens (must load first).
		wp_enqueue_style(
			'wpss-design-system',
			\WPSS_PLUGIN_URL . 'assets/css/design-system.css',
			array(),
			\WPSS_VERSION
		);

		wp_enqueue_style(
			'wpss-frontend',
			\WPSS_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'wpss-design-system' ),
			\WPSS_VERSION
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// Register Alpine.js (loaded in footer).
		wp_register_script(
			'alpinejs',
			\WPSS_PLUGIN_URL . 'assets/js/vendor/alpine.min.js',
			array(),
			'3.13.3',
			true
		);

		// Add defer attribute to Alpine.js so it waits for DOM and other scripts.
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 2 );

		wp_enqueue_script( 'alpinejs' );

		wp_enqueue_script(
			'wpss-frontend',
			\WPSS_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'alpinejs' ),
			\WPSS_VERSION,
			true
		);

		// Legacy 'wpss' for backward compatibility.
		wp_localize_script(
			'wpss-frontend',
			'wpss',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'wpss/v1/' ),
				'nonce'   => wp_create_nonce( 'wpss_frontend_nonce' ),
			)
		);

		// Primary 'wpssData' object used by frontend.js.
		wp_localize_script(
			'wpss-frontend',
			'wpssData',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'apiUrl'          => rest_url( 'wpss/v1/' ),
				'nonce'           => wp_create_nonce( 'wpss_proposal_action' ),
				'orderNonce'      => wp_create_nonce( 'wpss_order_action' ),
				'contactNonce'    => wp_create_nonce( 'wpss_service_nonce' ),
				'restNonce'       => wp_create_nonce( 'wp_rest' ),
				'pollingInterval' => 10000,
				'currencyFormat'  => wpss_get_currency_format(),
				'i18n'            => array(
					'loading'                     => __( 'Loading...', 'wp-sell-services' ),
					'error'                       => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'ajaxError'                   => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'sendMessage'                 => __( 'Send', 'wp-sell-services' ),
					'uploadFile'                  => __( 'Upload File', 'wp-sell-services' ),
					'confirmTitle'                => __( 'Are you sure?', 'wp-sell-services' ),
					'submitting'                  => __( 'Submitting...', 'wp-sell-services' ),
					'processing'                  => __( 'Processing...', 'wp-sell-services' ),
					'proposalDescriptionRequired' => __( 'Please provide a proposal description.', 'wp-sell-services' ),
					'proposalPriceRequired'       => __( 'Please enter a valid price.', 'wp-sell-services' ),
					'proposalDeliveryRequired'    => __( 'Please enter delivery time in days.', 'wp-sell-services' ),
					'proposalSubmitted'           => __( 'Proposal submitted successfully!', 'wp-sell-services' ),
					'proposalFailed'              => __( 'Failed to submit proposal.', 'wp-sell-services' ),
					'confirmAcceptProposal'       => __( 'Accept this proposal and create an order?', 'wp-sell-services' ),
					'rejectProposalReason'        => __( 'Please provide a reason for rejection (optional):', 'wp-sell-services' ),
					'confirmWithdrawProposal'     => __( 'Withdraw this proposal?', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Add defer attribute to Alpine.js only.
	 *
	 * Alpine must load with defer so it waits for other scripts (like service-wizard)
	 * to define their x-data functions before Alpine auto-initializes.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function add_defer_attribute( string $tag, string $handle ): string {
		// Only defer Alpine - other scripts should run immediately to register functions.
		if ( 'alpinejs' === $handle && strpos( $tag, 'defer' ) === false ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}

		return $tag;
	}
}
