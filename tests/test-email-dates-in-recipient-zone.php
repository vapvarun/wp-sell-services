<?php
/**
 * Every date in an email is shown in the recipient's time zone, not the sender's (Basecamp 10435884968).
 *
 * An email is usually sent by the other party's action (the buyer pays, the vendor
 * delivers), so a date label that falls back to the current user shows the sender's
 * zone. Behaviour: the extension-paid email to a vendor with their own zone, sent
 * while the buyer is logged in. Guard: every date label in an email template or
 * EmailService names its recipient.
 *
 * Run: wp eval-file tests/test-email-dates-in-recipient-zone.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\EmailService;
use WPSellServices\Services\OrderService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// 1. Guard: a date label in mail code always says who it is for.
$files = array_merge(
	glob( WP_PLUGIN_DIR . '/wp-sell-services/templates/emails/*.php' ),
	glob( WP_PLUGIN_DIR . '/wp-sell-services-pro/templates/emails/*.php' ) ?: array(),
	array( WP_PLUGIN_DIR . '/wp-sell-services/src/Services/EmailService.php' )
);
$bare = array();
foreach ( $files as $file ) {
	foreach ( (array) file( $file ) as $n => $line ) {
		if ( preg_match( '/wpss_(date|datetime|deadline|timezone)_label\s*\(/', $line ) && ! preg_match( '/recipient|->ID|\$user_id|_id\s*\)/', $line ) ) {
			$bare[] = basename( $file ) . ':' . ( $n + 1 );
		}
	}
}
$check( 'every date label in mail code names the recipient (' . ( $bare ? implode( ', ', $bare ) : 'none bare' ) . ')', array() === $bare );

// 2. Behaviour: vendor in Asia/Kolkata, site in UTC, buyer logged in.
global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$order    = ( new OrderService() )->get( (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}wpss_orders WHERE vendor_id > 0 AND customer_id > 0 AND customer_id <> vendor_id ORDER BY id LIMIT 1" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$vendor   = $order ? (int) $order->vendor_id : 0;
$saved_tz = $vendor ? $wpdb->get_var( $wpdb->prepare( "SELECT timezone FROM {$profiles} WHERE user_id = %d", $vendor ) ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$sent    = array();
$capture = static function ( $short_circuit, $atts ) use ( &$sent ) {
	$sent[] = (string) $atts['message'];
	return true;
};

try {
	if ( ! $order || null === $saved_tz && ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$profiles} WHERE user_id = %d", $vendor ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( 'fixture: an order whose vendor has a profile row', false );
	} else {
		$wpdb->update( $profiles, array( 'timezone' => 'Asia/Kolkata' ), array( 'user_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $vendor, 'wpss_vendor_timezone' );
		wp_set_current_user( (int) $order->customer_id );
		add_filter( 'pre_wp_mail', $capture, 20, 2 );

		$deadline = '2026-11-10 20:00:00';
		( new EmailService() )->send_extension_approved( $order, 50.0, 3, $deadline );

		$vendor_label = wpss_deadline_label( $deadline, '', $vendor );
		$buyer_label  = wpss_deadline_label( $deadline, '', (int) $order->customer_id );
		$body         = implode( "\n", $sent );

		$check( 'fixture: the two zones give different labels (' . $vendor_label . ' vs ' . $buyer_label . ')', $vendor_label !== $buyer_label );
		$check( 'the extension-paid email shows the new deadline in the vendor\'s zone', '' !== $body && false !== strpos( $body, esc_html( $vendor_label ) ) );
		$check( 'and not in the logged-in buyer\'s zone', false === strpos( $body, esc_html( $buyer_label ) ) );
	}
} finally {
	remove_filter( 'pre_wp_mail', $capture, 20 );
	if ( $vendor ) {
		$wpdb->update( $profiles, array( 'timezone' => $saved_tz ), array( 'user_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $vendor, 'wpss_vendor_timezone' );
	}
	wp_set_current_user( 1 );
}

echo $fails ? "\n$fails FAILED\n" : "\nOK\n";
exit( $fails ? 1 : 0 );
