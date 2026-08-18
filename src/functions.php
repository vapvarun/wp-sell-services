<?php
/**
 * Helper Functions
 *
 * @package WPSellServices
 * @since   1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/*
 * This file used to hold all 148 helpers in one 6,187-line block, which made it
 * the largest file in the plugin by a wide margin and hard to navigate.
 *
 * The functions were moved into the domain files below WITHOUT being renamed,
 * resignatured or otherwise changed - a purely positional split - so every call
 * site is untouched. Add a new helper to the file matching its domain rather
 * than back here; anything genuinely domain-less belongs on a class.
 */
$wpss_function_files = array(
	'money',
	'orders',
	'vendors',
	'services',
	'templates',
	'urls',
	'rest',
	'billing',
	'moderation',
	'notifications',
	'payments',
	'misc',
);

foreach ( $wpss_function_files as $wpss_function_file ) {
	require_once __DIR__ . '/functions/' . $wpss_function_file . '.php';
}

unset( $wpss_function_files, $wpss_function_file );
