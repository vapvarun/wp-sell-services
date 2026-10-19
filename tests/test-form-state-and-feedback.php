<?php
/**
 * Forms, state and feedback: the admin order screen says what happened, a dispute decision
 * note is still on the thread after saving, the wizard names the field that is wrong, add-on
 * rows keep unique ids, the Earnings commission button is wired, portfolio images can be
 * removed, and an avatar looked up by email resolves (Basecamp 10399369371, 10398958589,
 * 10399020562, 10398584633, 10398686846, 10403821351, 10403187415, 10384898745, 10411862427).
 *
 * Run: wp eval-file tests/test-form-state-and-feedback.php
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Admin;
use WPSellServices\Services\DisputeService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$read = static fn( string $rel ): string => (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );

// --- admin order notice ------------------------------------------------------
$notice = static function ( string $updated ): string {
	$_GET['updated'] = $updated;
	$method          = new ReflectionMethod( Admin::class, 'render_order_update_notice' );
	ob_start();
	$method->invoke( new Admin() );
	unset( $_GET['updated'] );
	return (string) ob_get_clean();
};
$check( 'order screen confirms a changed status', false !== strpos( $notice( '1' ), 'notice-success' ) );
$check( 'order screen explains a refused status', false !== strpos( $notice( 'error' ), 'notice-error' ) );
$check( 'order screen says nothing changed when the status was the same', false !== strpos( $notice( 'unchanged' ), 'Nothing changed' ) );
$check( 'order screen is silent without a result', '' === $notice( '' ) );

// --- dispute decision note ---------------------------------------------------
global $wpdb;
$orders   = $wpdb->prefix . 'wpss_orders';
$disputes = $wpdb->prefix . 'wpss_disputes';
$saved    = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved, array( 'allow_disputes' => 1 ) ) );
$wpdb->insert(
	$orders,
	array(
		'order_number'   => 'WPSS-NOTE-' . wp_rand(),
		'customer_id'    => 999999,
		'vendor_id'      => 999998,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 50.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id   = (int) $wpdb->insert_id;
$service    = new DisputeService();
$dispute_id = (int) $service->open( $order_id, 999999, 'other', 'note contract' );
$marker     = 'NOTE-MARKER-' . wp_rand();
$service->transition( $dispute_id, 'pending_review', array( 'note' => $marker ) );
$thread = array_column( $service->get_evidence( $dispute_id ), 'content' );
$check( 'a decision note saved with a status change is on the dispute thread', in_array( $marker, $thread, true ) );
$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'dispute_id' => $dispute_id ) );
$wpdb->delete( $disputes, array( 'id' => $dispute_id ) );
$wpdb->delete( $orders, array( 'id' => $order_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $order_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $order_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_id' => $order_id ) );
update_option( 'wpss_orders', $saved );

// --- wizard ------------------------------------------------------------------
$wiz = $read( 'assets/js/service-wizard.js' );
$check( 'wizard validates the requirements step', false !== strpos( $wiz, "case 'requirements':" ) && false !== strpos( $wiz, 'requirementsErrors()' ) );
preg_match( '/basicPriceError\(\) \{(.*?)\n\t\t\},/s', $wiz, $bp );
$check( 'Basic package error names delivery when only delivery is missing', ! empty( $bp[1] ) && false !== strpos( $bp[1], 'validationDelivery' ) );
$wiz_php = $read( 'src/Frontend/ServiceWizard.php' );
$check( 'wizard strings carry the delivery and requirement messages', false !== strpos( $wiz_php, "'validationDelivery'" ) && false !== strpos( $wiz_php, "'validationRequirementLabel'" ) );

// --- add-on ids --------------------------------------------------------------
$admin_js = $read( 'assets/js/admin.js' );
preg_match( '/function reindexAddons\(\) \{(.*?)\n\t\}/s', $admin_js, $ra );
$check( 'reindexAddons rewrites id and label[for] with the new index', ! empty( $ra[1] ) && false !== strpos( $ra[1], 'label[for^="wpss_addon_"]' ) );

// --- edit request heading ----------------------------------------------------
$check( 'Edit Request section does not repeat the dashboard title as an h2', false === strpos( $read( 'templates/dashboard/sections/edit-request.php' ), '<h2' ) );

// --- Earnings commission button ----------------------------------------------
$vj = $read( 'assets/js/admin-vendors.js' );
preg_match( '/function initEarningsHandlers\(\) \{(.*?)\n\t\t\}/s', $vj, $eh );
$check( 'Earnings tab binds the commission save and reset', ! empty( $eh[1] ) && false !== strpos( $eh[1], 'initCommissionHandlers()' ) && 1 === substr_count( $vj, "#wpss-save-commission-detail')" ) );
$check( 'commission save reads the message from response.data', false === strpos( $vj, "+ response.message +" ) );

// --- portfolio remove --------------------------------------------------------
$ud = $read( 'assets/js/unified-dashboard.js' );
$check( 'portfolio thumbs carry a labelled remove control', false !== strpos( $ud, 'wpss-portfolio-media-remove' ) && false !== strpos( $ud, 'handlePortfolioMediaRemove' ) && false !== strpos( $read( 'src/Frontend/UnifiedDashboard.php' ), "'removeImage'" ) );
$check( 'no bare thumbnail is appended without the remove control', false === strpos( $ud, "append('<img src=\"' + " ) );

// --- avatar by email ---------------------------------------------------------
$uid = wp_insert_user( array( 'user_login' => 'qa_avatar_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-avatar-' . wp_rand() . '@example.test', 'role' => 'subscriber' ) );
$img = 0;
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 20, 'fields' => 'ids' ) ) as $candidate ) {
	if ( wp_get_attachment_image_url( (int) $candidate, 'thumbnail' ) ) {
		$img = (int) $candidate;
		break;
	}
}
if ( $img ) {
	update_user_meta( $uid, '_wpss_avatar_id', $img );
	$email = get_userdata( $uid )->user_email;
	$check( 'get_avatar_url by email returns the member photo, not Gravatar', false === strpos( (string) get_avatar_url( $email ), 'gravatar.com' ) && get_avatar_url( $email ) === get_avatar_url( $uid ) );
} else {
	echo "SKIP  no image attachment on this site for the avatar check\n";
}
wp_delete_user( $uid );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
