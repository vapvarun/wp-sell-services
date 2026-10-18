<?php
/**
 * My Account: Notifications
 *
 * Thin wrapper around the shared notifications partial. Loaded by account
 * providers (Woo / EDD myaccount endpoints) so this surface is identical to the
 * dashboard section and the standalone account page — same markup, same styling,
 * and working mark-read.
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/myaccount/notifications.php
 *
 * @package WPSellServices\Templates
 * @since   1.2.2
 * @version 1.9.0
 *
 * @var int $user_id Optional. Defaults to the current user.
 */

defined( 'ABSPATH' ) || exit;

require wpss_locate_template( 'partials/notifications-list.php' );
