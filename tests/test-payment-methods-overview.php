<?php
/**
 * Payment methods table: on/off and order (Basecamp 10551488326, payment modules phase 2, T5).
 *
 * The switch merges into each gateway's own option (its sanitizer rebuilds the whole array, so a
 * write carrying only 'enabled' would wipe the keys); the saved order drives the checkout; unknown
 * ids are refused before anything is written; the REST route is admin only.
 *
 * Run: wp eval-file tests/test-payment-methods-overview.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! wpss_uses_standalone_payments() ) {
	echo "SKIP  the Payment methods table belongs to the standalone route\n";
	return;
}

$gateways = wpss()->get_payment_gateways();
$groups   = array();
foreach ( $gateways as $id => $gateway ) {
	$card = $gateway->get_settings_card();
	if ( $card ) {
		$groups[ $id ] = $card['option_group'];
	}
}
$snapshot = array( 'wpss_general' => get_option( 'wpss_general', false ) );
foreach ( $groups as $group ) {
	$snapshot[ $group ] = get_option( $group, false );
}

// Admin request shape: every sanitizer attached, as on options.php or an admin-ajax save.
( new \WPSellServices\Admin\Settings() )->register_settings();
foreach ( $gateways as $gateway ) {
	if ( method_exists( $gateway, 'register_settings' ) ) {
		$gateway->register_settings();
	}
}

// 1. The switch keeps every other key, for every gateway.
foreach ( $gateways as $id => $gateway ) {
	if ( ! isset( $groups[ $id ] ) || ! method_exists( $gateway, 'get_settings_fields' ) ) {
		continue;
	}
	$seed = array();
	foreach ( $gateway->get_settings_fields() as $key => $field ) {
		$seed[ $key ] = match ( $field['type'] ?? 'text' ) {
			'checkbox' => '1',
			'number'   => '3',
			'select'   => (string) array_key_first( (array) ( $field['options'] ?? array( 'x' => 1 ) ) ),
			default    => 'qa_' . $key,
		};
	}
	update_option( $groups[ $id ], $seed );
	$before = (array) get_option( $groups[ $id ] );

	$saved = wpss_save_payment_methods( array(), array( $id => false ) );
	$after = (array) get_option( $groups[ $id ] );
	unset( $before['enabled'], $after['enabled'] );
	$check( "{$id}: off keeps every other setting", true === $saved && $before === $after && '' === ( get_option( $groups[ $id ] )['enabled'] ?? null ), wp_json_encode( array_diff_assoc( $before, $after ) ) );

	wpss_save_payment_methods( array(), array( $id => true ) );
	$check( "{$id}:   and on again", '1' === ( get_option( $groups[ $id ] )['enabled'] ?? null ) );
}

// 2. Order: saved through the General sanitizer, read by the table and the checkout alike.
$ids     = array_keys( $groups );
$reverse = array_reverse( $ids );
$check( 'the order is saved', true === wpss_save_payment_methods( $reverse ) && $reverse === ( get_option( 'wpss_general' )['payment_methods'] ?? null ), wp_json_encode( get_option( 'wpss_general' )['payment_methods'] ?? null ) );
$check( '  the table lists methods in that order', $reverse === array_column( wpss_payment_method_rows(), 'id' ), wp_json_encode( array_column( wpss_payment_method_rows(), 'id' ) ) );
$checkout = array_keys( wpss_get_checkout_gateways() );
$check( '  the checkout offers them in that order', array_values( array_intersect( $reverse, $checkout ) ) === $checkout, wp_json_encode( $checkout ) );
$sorted = array_keys( wpss_sort_payment_methods( array( 'qa_new' => 1, $reverse[1] => 1, $reverse[0] => 1 ) ) );
$check( '  a method the owner never placed follows the placed ones', array( $reverse[0], $reverse[1], 'qa_new' ) === $sorted, wp_json_encode( $sorted ) );

// 3. Unknown ids are refused and nothing is written.
$general = get_option( 'wpss_general' );
$refused = wpss_save_payment_methods( array( 'qa_nope', $ids[0] ), array( $ids[0] => false ) );
$check( 'an unknown id is refused', is_wp_error( $refused ) && 'wpss_unknown_payment_method' === $refused->get_error_code() );
$check( '  and nothing was saved', $general === get_option( 'wpss_general' ) && '1' === ( get_option( $groups[ $ids[0] ] )['enabled'] ?? null ) );
update_option( 'wpss_general', array_merge( (array) $general, array( 'payment_methods' => array( 'qa_nope', $ids[0] ) ) ) );
$check( 'the General sanitizer drops an id that is not registered', array( $ids[0] ) === ( get_option( 'wpss_general' )['payment_methods'] ?? null ), wp_json_encode( get_option( 'wpss_general' )['payment_methods'] ?? null ) );

// 4. Status: a switched-on method without its keys says so.
update_option( $groups['stripe'] ?? 'wpss_stripe_settings', array( 'enabled' => '1' ) );
$fresh = new \WPSellServices\Integrations\Stripe\StripeGateway();
// Gateways are built once at boot, so the filter is too late: swap the cached instance.
$list = new ReflectionProperty( wpss(), 'payment_gateways' );
$list->setAccessible( true );
$was  = $list->getValue( wpss() );
$list->setValue( wpss(), array_merge( $was, array( 'stripe' => $fresh ) ) );
$rows = array_column( wpss_payment_method_rows(), 'status', 'id' );
$check( 'Stripe on without keys reads "Needs setup"', 'needs_setup' === ( $rows['stripe'] ?? '' ), wp_json_encode( $rows ) );
$list->setValue( wpss(), $was );

// 5. REST: admin only.
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$buyer = (int) ( get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
wp_set_current_user( $buyer );
$check( 'a member cannot read the table', 403 === rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/payments/settings' ) )->get_status() );
$post = new WP_REST_Request( 'POST', '/wpss/v1/payments/settings' );
$post->set_body_params( array( 'order' => $ids ) );
$check( '  nor save it', 403 === rest_do_request( $post )->get_status() );
wp_set_current_user( $admin );
$get = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/payments/settings' ) );
$check( 'the admin reads every method', 200 === $get->get_status() && count( $groups ) === count( $get->get_data()['methods'] ?? array() ) );
$bad = new WP_REST_Request( 'POST', '/wpss/v1/payments/settings' );
$bad->set_body_params( array( 'order' => array( 'qa_nope' ) ) );
$check( '  and an unknown id answers 400', 400 === rest_do_request( $bad )->get_status() );
$check( '  a good save answers 200', 200 === rest_do_request( $post )->get_status() && $ids === ( get_option( 'wpss_general' )['payment_methods'] ?? null ) );
wp_set_current_user( 0 );

// Put everything back exactly, bypassing the sanitizers.
foreach ( $snapshot as $option => $value ) {
	remove_all_filters( "sanitize_option_{$option}" );
	false === $value ? delete_option( $option ) : update_option( $option, $value );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
