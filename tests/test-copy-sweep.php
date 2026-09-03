<?php
/**
 * Copy, accessibility and PHP-warning sweep from the 1.7.1 live walk (F26).
 *
 * Star ratings render through ONE helper as Lucide icons with a screen-reader
 * label, delivery statuses go through the shared status label, a dispute gets
 * its number when it is opened, the vendor route names its tab after the
 * vendor, and no emoji or text-star glyph is left in a customer-facing file.
 *
 * Run: wp eval-file tests/test-copy-sweep.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// --- star partial ---------------------------------------------------------------
$check( 'wpss_star_rating() exists', function_exists( 'wpss_star_rating' ) );
if ( function_exists( 'wpss_star_rating' ) ) {
	$html = wpss_star_rating( 4.0 );
	$check( '  renders five Lucide star icons', 5 === substr_count( $html, 'data-lucide="star"' ) );
	$check( '  four of them filled', 4 === substr_count( $html, 'wpss-star filled' ) );
	$check( '  carries a screen-reader "out of 5" label', str_contains( $html, 'screen-reader-text' ) && str_contains( $html, 'out of 5' ) );
	$check( '  no text star glyph', ! str_contains( $html, '★' ) && ! str_contains( $html, '&#9733;' ) );
	$check( '  accepts a custom label', str_contains( wpss_star_rating( 4.0, '4 stars and up' ), '4 stars and up' ) );
}

// --- status label ---------------------------------------------------------------
$check( 'status helper labels revision_requested', 'Revision Requested' === wpss_get_order_status_label( 'revision_requested' ) );
$order_view = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-view.php' );
$check( 'deliveries list uses the status helper, not ucfirst()', ! str_contains( $order_view, 'ucfirst( $delivery->status )' ) );
$check( 'delivery modal reads the max_file_size setting', ! str_contains( $order_view, '50MB' ) && str_contains( $order_view, "wpss_get_option( 'advanced', 'max_file_size' )" ) );

// --- dispute number -------------------------------------------------------------
global $wpdb;
$orders   = $wpdb->prefix . 'wpss_orders';
$disputes = $wpdb->prefix . 'wpss_disputes';
$buyer    = 999999; // Nobody. Rows are removed at the end.
$vendor   = 999998;

$wpdb->insert(
	$orders,
	array(
		'order_number'   => 'WPSS-COPY-SWEEP-' . wp_rand(),
		'customer_id'    => $buyer,
		'vendor_id'      => $vendor,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$saved_settings = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_settings, array( 'allow_disputes' => 1 ) ) );

$dispute_id = (int) ( new DisputeService() )->open( $order_id, $buyer, 'other', 'contract' );
$number     = (string) $wpdb->get_var( $wpdb->prepare( "SELECT dispute_number FROM {$disputes} WHERE id = %d", $dispute_id ) );
$check( 'open() returns a dispute', $dispute_id > 0 );
$check( '  and sets dispute_number DSP-nnnnnn', 1 === preg_match( '/^DSP-\d{6}$/', $number ) );

wp_set_current_user( 1 );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/disputes/' . $dispute_id ) );
$check( '  REST exposes it as number', '' !== $number && $number === (string) ( $rest->get_data()['number'] ?? '' ) );
wp_set_current_user( 0 );

// --- vendor route title ---------------------------------------------------------
$vendor_user = null;
foreach ( get_users( array( 'role' => 'wpss_vendor', 'number' => 20, 'fields' => 'all' ) ) as $candidate ) {
	if ( wpss_is_vendor( $candidate->ID ) ) {
		$vendor_user = $candidate;
		break;
	}
}
if ( $vendor_user ) {
	set_query_var( 'wpss_vendor', $vendor_user->user_nicename );
	( new \WPSellServices\Frontend\TemplateLoader() )->template_include( '' );
	set_query_var( 'wpss_vendor', '' );
	$check( 'vendor route document title names the vendor', str_contains( wp_get_document_title(), wpss_get_member_display_name( (int) $vendor_user->ID ) ) );
} else {
	$check( 'vendor route document title names the vendor (no vendor on this site to test with)', false );
}

// --- no emoji or text stars left ---------------------------------------------------
// Emails are plain-text surfaces: a text star in review-received.php and the
// notification/email services is legitimate there and belongs to the email
// card, so those files are skipped. Everything else must be clean.
$glyphs = array( '★', '☆', '🛡', '⏱', '🏆', '🎉', '&#9733;', '&#9734;', '&#127942;', '&#127881;', '&#128737;', '&#128274;', '&#128337;', '&#128100;', '&#128230;', '&#128260;' );
$skip   = array( 'src/Services/NotificationService.php', 'src/Services/EmailService.php' );
$dirty  = array();
$it     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$rel = str_replace( WPSS_PLUGIN_DIR, '', $file->getPathname() );
	if ( ! preg_match( '#^(src|templates|assets/js)/#', $rel ) || ! preg_match( '/\.(php|js)$/', $rel ) || str_contains( $rel, '.min.js' ) || str_contains( $rel, 'assets/js/vendor/' ) ) {
		continue;
	}
	if ( in_array( $rel, $skip, true ) || ( str_starts_with( $rel, 'templates/emails/' ) && 'templates/emails/seller-level-promotion.php' !== $rel ) ) {
		continue;
	}
	$body = (string) file_get_contents( $file->getPathname() );
	foreach ( $glyphs as $glyph ) {
		if ( str_contains( $body, $glyph ) ) {
			$dirty[] = $rel . ' (' . $glyph . ')';
			break;
		}
	}
}
$check( 'no emoji or text-star glyph in src/, templates/ or assets/js/', empty( $dirty ) );
foreach ( $dirty as $hit ) {
	echo "      {$hit}\n";
}

// --- cleanup ------------------------------------------------------------------
update_option( 'wpss_orders', $saved_settings );
$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $dispute_id ) );
$wpdb->delete( $disputes, array( 'order_id' => $order_id ) );
$wpdb->delete( $orders, array( 'id' => $order_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $order_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $order_id ) );
foreach ( array( $buyer, $vendor ) as $u ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $u ) );
}
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_messages WHERE conversation_id NOT IN (SELECT id FROM {$wpdb->prefix}wpss_conversations)" ); // phpcs:ignore

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
