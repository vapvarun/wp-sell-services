<?php
/**
 * A posted number is stored inside the limits the screen shows for it: -4 stores
 * the field's minimum and 99999 its maximum, on every Orders, Payouts and
 * Advanced number field (Basecamp 10475418682, QA bounce).
 *
 * The limits are read from the registered field definitions, which are what the
 * browser is given, and not from the sanitizer's own table, so a sanitizer that
 * drifts from its screen fails here.
 *
 * Run: wp eval-file tests/test-settings-number-fields-match-screen.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

require_once ABSPATH . 'wp-admin/includes/template.php';

$settings = new \WPSellServices\Admin\Settings();
$settings->register_settings();

global $wp_settings_fields;

$screen_args = static function ( string $field ) use ( $wp_settings_fields ): array {
	foreach ( (array) $wp_settings_fields as $sections ) {
		foreach ( $sections as $fields ) {
			if ( isset( $fields[ $field ]['args'] ) ) {
				return $fields[ $field ]['args'];
			}
		}
	}

	return array();
};

// Field => the sanitizer that owns it and the option array key.
$fields = array(
	'auto_complete_days'        => 'sanitize_order_settings',
	'dispute_window_days'       => 'sanitize_order_settings',
	'auto_dispute_late_days'    => 'sanitize_order_settings',
	'requirements_timeout_days' => 'sanitize_order_settings',
	'review_window_days'        => 'sanitize_order_settings',
	'min_withdrawal'            => 'sanitize_payouts_settings',
	'auto_withdrawal_threshold' => 'sanitize_payouts_settings',
	'max_file_size'             => 'sanitize_advanced_settings',
);

foreach ( $fields as $field => $sanitizer ) {
	$args = $screen_args( $field );

	if ( ! isset( $args['min'], $args['max'] ) ) {
		$check( "{$field}: the screen defines a min and a max", false );
		continue;
	}

	$store = static fn( $value ) => (int) $settings->$sanitizer( array( $field => $value ) )[ $field ];

	$check( "{$field}: -4 stores the screen minimum {$args['min']}", (int) $args['min'] === $store( -4 ), (string) $store( -4 ) );
	$check( "{$field}: 99999 stores the screen maximum {$args['max']}", (int) $args['max'] === $store( 99999 ), (string) $store( 99999 ) );
	$check( "{$field}: a value inside the limits is kept", (int) $args['max'] === $store( (int) $args['max'] ) && (int) $args['min'] === $store( (int) $args['min'] ) );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
