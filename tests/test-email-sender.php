<?php
/**
 * Who a marketplace email comes from (Basecamp 10504371480).
 *
 * Run: wp eval-file tests/test-email-sender.php
 *
 * The marketplace's own sender is the default. WooCommerce's sender is borrowed
 * only when WooCommerce is the active rail, and wpss_email_from_address lets a
 * site pick a plugin-specific sender.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$sent = array();
add_filter( 'pre_wp_mail', static function ( $pre, $atts ) use ( &$sent ) { $sent[] = $atts; return true; }, 10, 2 );

$from = static function () use ( &$sent ): string {
	$sent = array();
	( new \WPSellServices\Services\EmailService() )->send( 'qa-sender@example.test', 'Sender probe', 'vendor_contact', array() );
	$headers = implode( "\n", (array) ( $sent[0]['headers'] ?? array() ) );

	return preg_match( '/^From:\s*(.*)$/mi', $headers, $m ) ? trim( $m[1] ) : '';
};

delete_transient( 'wpss_email_cooldown_' . md5( 'qa-sender@example.test|vendor_contact' ) );
$default = $from();
$check( 'default sender is the site admin address', false !== strpos( $default, '<' . get_option( 'admin_email' ) . '>' ), $default );

add_filter( 'wpss_email_from_address', $f = static fn() => 'orders@example.test' );
delete_transient( 'wpss_email_cooldown_' . md5( 'qa-sender@example.test|vendor_contact' ) );
$check( 'wpss_email_from_address sets a plugin-specific sender', false !== strpos( $from(), '<orders@example.test>' ), $from() );
remove_filter( 'wpss_email_from_address', $f );

add_filter( 'wpss_email_from_address', $f = static fn() => 'not an address' );
delete_transient( 'wpss_email_cooldown_' . md5( 'qa-sender@example.test|vendor_contact' ) );
$check( 'an invalid address falls back to the admin address', false !== strpos( $from(), '<' . get_option( 'admin_email' ) . '>' ), $from() );
remove_filter( 'wpss_email_from_address', $f );

$source = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Services/EmailService.php' );
$check( 'WooCommerce sender is borrowed only on the WooCommerce rail', 1 === preg_match( "/'woocommerce' === \\\$adapter->get_id\(\) && function_exists\( 'WC' \)/", $source ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
