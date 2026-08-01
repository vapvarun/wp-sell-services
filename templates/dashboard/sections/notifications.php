<?php
/**
 * Dashboard Section: Notifications
 *
 * Thin wrapper around the shared notifications partial so the dashboard, the
 * standalone account page and the myaccount template all render the identical
 * surface (list + working mark-read) instead of three divergent copies.
 *
 * @package WPSellServices\Templates
 * @since   1.2.2
 *
 * @var int           $user_id        Current user ID.
 * @var VendorService $vendor_service Vendor service instance.
 * @var bool          $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpss_dashboard_section_before', 'notifications', $user_id );
?>
<div class="wpss-section wpss-section--notifications wpss-card">
	<?php
	// The dashboard shell already renders "Notifications" as the page h1, so the
	// partial must not repeat it here. The other two surfaces have no page title
	// of their own and keep theirs.
	$wpss_show_heading = false;
	require WPSS_PLUGIN_DIR . 'templates/partials/notifications-list.php';
	?>
</div>
