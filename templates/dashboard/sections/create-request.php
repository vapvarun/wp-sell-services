<?php
/**
 * Dashboard Section: Create Buyer Request
 *
 * This section embeds the buyer request form.
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

// Use the post request form shortcode.
echo do_shortcode( '[wpss_post_request]' );
