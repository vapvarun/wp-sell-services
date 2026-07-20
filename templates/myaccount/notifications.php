<?php
/**
 * My Account: Notifications
 *
 * Thin wrapper around the shared notifications partial. Loaded by account
 * providers (Woo / EDD myaccount endpoints) so this surface is identical to the
 * dashboard section and the standalone account page — same markup, same styling,
 * and working mark-read.
 *
 * @package WPSellServices\Templates
 * @since   1.2.2
 *
 * @var int $user_id Optional. Defaults to the current user.
 */

defined( 'ABSPATH' ) || exit;

require WPSS_PLUGIN_DIR . 'templates/partials/notifications-list.php';
