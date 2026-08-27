<?php
/**
 * Become-a-Vendor benefit bullets.
 *
 * Extracted because these three bullets were written out TWICE in
 * Shortcodes::vendor_registration() - once for logged-out visitors and once
 * for signed-in non-vendors - so a copy correction had to be made in both
 * places or the page told two different stories depending on who was reading
 * it. One partial, both branches require it.
 *
 * The listings claim is generated from the real limit rather than asserted.
 * It said "Build unlimited service listings" while Free caps a vendor at 20 by
 * default, so members registered expecting unlimited and met a wall (Basecamp
 * 10235849910). Reading wpss_vendor.max_services_per_vendor at render means
 * the promise stays true when an owner changes the cap, and says "unlimited"
 * honestly on the installs where it is.
 *
 * The analytics claim is softened for the same reason: Free ships order and
 * earnings figures, not the Pro analytics dashboard.
 *
 * @package WPSellServices
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

$wpss_vendor_settings = get_option( 'wpss_vendor', array() );
$wpss_max_services    = isset( $wpss_vendor_settings['max_services_per_vendor'] )
	? absint( $wpss_vendor_settings['max_services_per_vendor'] )
	: 0;

/**
 * Filter the service-count promise on the Become a Vendor page.
 *
 * @since 1.7.0
 *
 * @param string $copy        The rendered claim.
 * @param int    $max_services Configured cap, 0 for unlimited.
 */
$wpss_listing_copy = apply_filters(
	'wpss_vendor_benefit_listings_copy',
	$wpss_max_services > 0
		? sprintf(
			/* translators: %s: maximum number of service listings */
			_n(
				'Publish up to %s service listing with custom packages',
				'Publish up to %s service listings with custom packages',
				$wpss_max_services,
				'wp-sell-services'
			),
			number_format_i18n( $wpss_max_services )
		)
		: __( 'Publish unlimited service listings with custom packages', 'wp-sell-services' ),
	$wpss_max_services
);
?>
<div class="wpss-vr__features">
	<div class="wpss-vr__feature">
		<span class="wpss-vr__feature-icon">
			<i data-lucide="palette" class="wpss-icon" aria-hidden="true"></i>
		</span>
		<div>
			<strong><?php esc_html_e( 'Create Services', 'wp-sell-services' ); ?></strong>
			<span><?php echo esc_html( $wpss_listing_copy ); ?></span>
		</div>
	</div>
	<div class="wpss-vr__feature">
		<span class="wpss-vr__feature-icon">
			<i data-lucide="wallet" class="wpss-icon" aria-hidden="true"></i>
		</span>
		<div>
			<strong><?php esc_html_e( 'Get Paid', 'wp-sell-services' ); ?></strong>
			<span><?php esc_html_e( 'Secure payments with flexible withdrawal options', 'wp-sell-services' ); ?></span>
		</div>
	</div>
	<div class="wpss-vr__feature">
		<span class="wpss-vr__feature-icon">
			<i data-lucide="trending-up" class="wpss-icon" aria-hidden="true"></i>
		</span>
		<div>
			<strong><?php esc_html_e( 'Grow Your Business', 'wp-sell-services' ); ?></strong>
			<span><?php esc_html_e( 'Track your orders, earnings and reviews from one dashboard', 'wp-sell-services' ); ?></span>
		</div>
	</div>
</div>
