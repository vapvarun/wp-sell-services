<?php
/**
 * Whatever puts a service in the review queue (wizard, REST create, set_pending)
 * fires `wpss_service_pending_moderation`, and that one hook tells the owner.
 *
 * Run: wp eval-file tests/test-moderation-pending-admin-mail.php
 *
 * Mail is intercepted, nothing is sent.
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

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$service_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' LIMIT 1" );

$sent = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$sent ) {
		$sent[] = $atts;
		return true;
	},
	10,
	2
);

do_action( 'wpss_service_pending_moderation', $service_id );

$to_admin = array_filter(
	$sent,
	static function ( $m ) {
		return false !== stripos( (string) $m['subject'], 'pending review' );
	}
);

$check( 'the pending hook mails the owner once', 1 === count( $to_admin ), (string) count( $to_admin ) );

$rest = (string) file_get_contents( dirname( __DIR__ ) . '/src/API/ServicesController.php' );
$check( 'REST create queues through the one submit door', false !== strpos( $rest, '( new ModerationService() )->submit(' ) );

exit( $fails ? 1 : 0 );
