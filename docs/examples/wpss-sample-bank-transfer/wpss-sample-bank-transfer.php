<?php
/**
 * Plugin Name:       WP Sell Services - Sample Bank Transfer
 * Description:       A sample payment method for WP Sell Services: the buyer places the order, pays by bank transfer, and the site owner marks it paid. Copy it to start your own gateway.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  wp-sell-services
 * License:           GPL-2.0-or-later
 * Text Domain:       wpss-sample-bank-transfer
 *
 * @package WPSSSampleBankTransfer
 */

defined( 'ABSPATH' ) || exit;

// Free builds its gateway list on `init`; the class is loaded only then, so this file never
// needs WP Sell Services to load before it.
add_filter(
	'wpss_payment_gateways',
	static function ( array $gateways ): array {
		require_once __DIR__ . '/class-gateway.php';

		$gateway = new \WPSSSampleBankTransfer\Gateway();
		$gateway->init();
		$gateways[ $gateway->get_id() ] = $gateway; // The key must be the gateway's own id.

		return $gateways;
	}
);
