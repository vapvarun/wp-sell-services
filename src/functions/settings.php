<?php
/**
 * Settings: the one place a default lives.
 *
 * The installer seeds from wpss_settings_defaults(), the Settings screen
 * renders a missing key from it, and wpss_get_option() falls back to it at
 * runtime. Before this file each of the three carried its own copy, and they
 * disagreed: the screen showed "Auto-start on timeout" ticked while the cron
 * saw no key and cancelled the order (Basecamp 10264286815).
 *
 * @package WPSellServices
 * @since   1.7.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default value of every key in every settings option array.
 *
 * Option name => key => default. Add the key here when adding a field; the
 * installer, the renderer and the reader all pick it up.
 *
 * @since 1.7.1
 *
 * @return array<string, array<string, mixed>>
 */
function wpss_settings_defaults(): array {
	static $defaults = null;

	if ( null !== $defaults ) {
		return $defaults;
	}

	$defaults = array(
		'wpss_general'       => array(
			'platform_name'             => get_bloginfo( 'name' ),
			'currency'                  => wpss_detect_currency_from_locale(),
			'ecommerce_platform'        => 'auto',
			'checkout_badges_enabled'   => true,
			'use_marketplace_cart_link' => false,
			'checkout_account_creation' => false,
		),
		'wpss_commission'    => array(
			'commission_rate'     => 10,
			'enable_vendor_rates' => true,
		),
		'wpss_payouts'       => array(
			'min_withdrawal'            => 25,
			// 0 = no hold; the owner opts into a refund window if they want
			// one. See Settings.php clearance_days for the rationale.
			'clearance_days'            => 0,
			'auto_withdrawal_enabled'   => false,
			'auto_withdrawal_threshold' => 500,
			'auto_withdrawal_schedule'  => 'monthly',
		),
		'wpss_tax'           => array(
			'enable_tax'   => false,
			'tax_label'    => 'Tax',
			'tax_rate'     => 0,
			'tax_included' => false,
		),
		'wpss_vendor'        => array(
			'vendor_registration'        => 'open',
			'max_services_per_vendor'    => 20,
			// Publish-and-sell by default so a new marketplace isn't empty on
			// launch day. Consistent with open registration above.
			'require_service_moderation' => false,
			'moderate_reviews'           => false,
		),
		// Revision limits are per-package, not a global setting.
		'wpss_orders'        => array(
			'auto_complete_days'        => 3,
			'allow_disputes'            => true,
			'allow_vendor_refunds'      => false,
			'dispute_window_days'       => 14,
			'auto_dispute_late_days'    => 3,
			'allow_late_requirements'   => false,
			'requirements_timeout_days' => 7,
			// Start, never cancel: a buyer who forgot the brief should not lose
			// the order and the vendor the sale.
			'auto_start_on_timeout'     => true,
			'review_window_days'        => 30,
		),
		// ALL email types on by default; the owner disables individually.
		'wpss_notifications' => array(
			'notify_new_order'              => true,
			'notify_order_completed'        => true,
			'notify_order_cancelled'        => true,
			'notify_cancellation_requested' => true,
			'notify_delivery_submitted'     => true,
			'notify_revision_requested'     => true,
			'notify_new_message'            => true,
			'notify_vendor_contact'         => true,
			'notify_new_review'             => true,
			'notify_dispute_opened'         => true,
			'notify_withdrawal_requested'   => true,
			'notify_withdrawal_approved'    => true,
			'notify_withdrawal_rejected'    => true,
			'notify_proposal_submitted'     => true,
			'notify_proposal_accepted'      => true,
			'notify_moderation'             => true,
			'notify_tip_received'           => true,
			'notify_milestone_proposed'     => true,
			'notify_milestone_paid'         => true,
			'notify_milestone_submitted'    => true,
			'notify_milestone_approved'     => true,
			'notify_extension_proposed'     => true,
			'notify_extension_approved'     => true,
			'notify_extension_declined'     => true,
		),
		'wpss_advanced'      => array(
			'delete_data_on_uninstall' => false,
			'enable_debug_mode'        => false,
			'max_file_size'            => 10,
			'allowed_file_types'       => 'jpg,jpeg,png,gif,pdf,doc,docx',
			'currency_position'        => 'before',
		),
	);

	return $defaults;
}

/**
 * Get a plugin option value, falling back to wpss_settings_defaults().
 *
 * @param string $group   Option group without the prefix ('general', 'orders').
 * @param string $key     Option key within the group.
 * @param mixed  $default Explicit fallback; when null the shared default applies.
 * @return mixed
 */
function wpss_get_option( string $group, string $key, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Public documented helper; renaming breaks `default:` named-argument callers.
	$option_name = 'wpss_' . $group;
	$options     = get_option( $option_name, array() );

	return $options[ $key ] ?? $default ?? wpss_settings_defaults()[ $option_name ][ $key ] ?? null;
}

/**
 * Detect currency from the WordPress locale.
 *
 * @since 1.7.1
 *
 * @return string Currency code (ISO 4217).
 */
function wpss_detect_currency_from_locale(): string {
	$map = array(
		'en_GB' => 'GBP',
		'en_AU' => 'AUD',
		'en_CA' => 'CAD',
		'de_DE' => 'EUR',
		'fr_FR' => 'EUR',
		'es_ES' => 'EUR',
		'it_IT' => 'EUR',
		'nl_NL' => 'EUR',
		'pt_PT' => 'EUR',
		'pt_BR' => 'BRL',
		'ja'    => 'JPY',
		'zh_CN' => 'CNY',
		'hi_IN' => 'INR',
		'es_MX' => 'MXN',
	);

	return $map[ get_locale() ] ?? 'USD';
}
