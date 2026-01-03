<?php
/**
 * Dashboard Section: Create Service (vendor only)
 *
 * This section embeds the service creation wizard.
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

// Check if editing existing service.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking for edit ID.
$service_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

// Verify ownership if editing.
if ( $service_id ) {
	$service = get_post( $service_id );
	if ( ! $service || (int) $service->post_author !== $user_id || 'wpss_service' !== $service->post_type ) {
		?>
		<div class="wpss-alert wpss-alert--error">
			<?php esc_html_e( 'You do not have permission to edit this service.', 'wp-sell-services' ); ?>
		</div>
		<?php
		return;
	}
}

// Use the service wizard shortcode.
echo do_shortcode( $service_id ? "[wpss_service_wizard id=\"{$service_id}\"]" : '[wpss_service_wizard]' );
