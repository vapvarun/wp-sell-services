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
<div class="wpss-dashboard-section">
	<?php require WPSS_PLUGIN_DIR . 'templates/partials/notifications-list.php'; ?>
</div>
