<?php
/**
 * Frontend Class
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

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
		wp_style_add_data( 'wpss-design-system', 'rtl', 'replace' );

		wp_enqueue_style(
			'wpss-frontend',
			\WPSS_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'wpss-design-system' ),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-frontend', 'rtl', 'replace' );
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
		$cart_count = 0;
		if ( is_user_logged_in() ) {
			$cart       = get_user_meta( get_current_user_id(), '_wpss_cart', true );
			$cart_count = is_array( $cart ) ? count( $cart ) : 0;
		}

		wp_localize_script(
			'wpss-frontend',
			'wpssData',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'apiUrl'          => rest_url( 'wpss/v1/' ),
				'nonce'           => wp_create_nonce( 'wpss_proposal_action' ),
				'orderNonce'      => wp_create_nonce( 'wpss_order_action' ),
				'contactNonce'    => wp_create_nonce( 'wpss_service_nonce' ),
				'serviceNonce'    => wp_create_nonce( 'wpss_service_nonce' ),
				'messageNonce'    => wp_create_nonce( 'wpss_message_nonce' ),
				'sendMessageNonce' => wp_create_nonce( 'wpss_send_message' ),
				'restNonce'       => wp_create_nonce( 'wp_rest' ),
				'pollingInterval' => 10000,
				'currencyFormat'  => wpss_get_currency_format(),
				'cartCount'       => $cart_count,
				'checkoutUrl'     => wpss_get_checkout_base_url(),
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
					'messageFailed'               => __( 'Failed to send message.', 'wp-sell-services' ),
					'revisionRequired'            => __( 'Please describe what changes you need.', 'wp-sell-services' ),
					'confirmAcceptOrder'          => __( 'Are you sure you want to accept this order?', 'wp-sell-services' ),
					'confirmStartOrder'           => __( 'Are you sure you want to start working on this order?', 'wp-sell-services' ),
					'confirmDeliverOrder'         => __( 'Are you sure you want to mark this order as delivered?', 'wp-sell-services' ),
					'confirmCompleteOrder'        => __( 'Are you sure you want to mark this order as complete?', 'wp-sell-services' ),
					'confirmAcceptCancellation'   => __( 'Are you sure you want to accept this cancellation request? The order will be cancelled.', 'wp-sell-services' ),
					'confirmRejectCancellation'   => __( 'Are you sure you want to dispute this cancellation? The order will be escalated for admin review.', 'wp-sell-services' ),
					'promptReject'                => __( 'Please provide a reason for declining:', 'wp-sell-services' ),
					'promptCancel'                => __( 'Please provide a reason for cancellation:', 'wp-sell-services' ),
					'promptDispute'               => __( 'Please describe your issue:', 'wp-sell-services' ),
					'promptDefault'               => __( 'Please provide details:', 'wp-sell-services' ),
					'actionFailed'                => __( 'Action failed. Please try again.', 'wp-sell-services' ),
					'loadMoreReviews'             => __( 'Load More Reviews', 'wp-sell-services' ),
					'reviewsFailed'               => __( 'Failed to load reviews.', 'wp-sell-services' ),
					'addedToCart'                 => __( 'Added to cart!', 'wp-sell-services' ),
					'cartFailed'                  => __( 'Failed to add to cart.', 'wp-sell-services' ),
					'deliveryRequired'            => __( 'Please provide a delivery message.', 'wp-sell-services' ),
					'deliverySubmitted'           => __( 'Delivery submitted successfully!', 'wp-sell-services' ),
					'deliveryFailed'              => __( 'Failed to submit delivery.', 'wp-sell-services' ),
					'reviewSubmitted'             => __( 'Review submitted successfully!', 'wp-sell-services' ),
					'reviewFailed'                => __( 'Failed to submit review.', 'wp-sell-services' ),
					'disputeOpened'               => __( 'Dispute opened successfully. Our team will review your case.', 'wp-sell-services' ),
					'disputeFailed'               => __( 'Failed to open dispute.', 'wp-sell-services' ),
					'revisionSubmitted'           => __( 'Revision requested successfully!', 'wp-sell-services' ),
					'revisionFailed'              => __( 'Failed to request revision.', 'wp-sell-services' ),
					'vendorRegistered'            => __( 'Application submitted successfully!', 'wp-sell-services' ),
					'sellerResponse'              => __( 'Seller Response:', 'wp-sell-services' ),
					'justNow'                     => __( 'Just now', 'wp-sell-services' ),
					'confirm'                     => __( 'Confirm', 'wp-sell-services' ),
					'cancel'                      => __( 'Cancel', 'wp-sell-services' ),
					'submit'                      => __( 'Submit', 'wp-sell-services' ),
					'promptRequired'              => __( 'Please provide a response.', 'wp-sell-services' ),
					'describeDelivery'            => __( 'Describe your delivery:', 'wp-sell-services' ),
					'deliveryPlaceholder'         => __( 'Describe what you are delivering...', 'wp-sell-services' ),
					'skipRequirementsConfirm'     => __( 'You can submit requirements later. Continue to checkout?', 'wp-sell-services' ),
					'continue'                    => __( 'Continue', 'wp-sell-services' ),
					'enterReason'                 => __( 'Enter your reason...', 'wp-sell-services' ),
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

	/**
	 * Render floating mini-cart indicator in footer.
	 *
	 * Shows a cart icon with item count that persists across page navigations.
	 * Hidden when cart is empty. Updated via JS after add-to-cart AJAX calls.
	 *
	 * @return void
	 */
	public function render_mini_cart(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Hide standalone mini-cart when a non-standalone adapter (e.g. WooCommerce) is active.
		$adapter = wpss_get_ecommerce_adapter();
		if ( $adapter && 'standalone' !== $adapter->get_id() ) {
			return;
		}

		$cart         = get_user_meta( get_current_user_id(), '_wpss_cart', true );
		$cart_count   = is_array( $cart ) ? count( $cart ) : 0;
		$hidden       = 0 === $cart_count ? ' style="display:none;"' : '';
		$checkout_url = wpss_get_checkout_base_url();
		?>
		<div id="wpss-mini-cart" class="wpss-mini-cart"<?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<a href="<?php echo esc_url( $checkout_url ); ?>" class="wpss-mini-cart-link" title="<?php esc_attr_e( 'View Cart', 'wp-sell-services' ); ?>">
				<svg class="wpss-mini-cart-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="9" cy="21" r="1"></circle>
					<circle cx="20" cy="21" r="1"></circle>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
				</svg>
				<span class="wpss-cart-count"><?php echo esc_html( (string) $cart_count ); ?></span>
			</a>
		</div>
		<?php
	}
}
