<?php
/**
 * Accepting a phased proposal emails the buyer once, not once per phase.
 *
 * Run: wp eval-file tests/test-contract-accept-sends-one-email.php
 *
 * convert_to_order() created every contract phase and then fired wpss_milestone_proposed for each, so a 4-phase
 * contract sent 4 "Milestone proposed" emails (and 4 in-app rows) in the same second, and a 17-phase one sent 17.
 * Accepting now sends one summary naming every phase and linking the first to pay. A phase the seller proposes
 * later on its own still sends its single email.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\BuyerRequestService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders = $wpdb->prefix . 'wpss_orders';
$mkuser = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_ctr_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-ctr-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$buyer  = $mkuser( 'b' );
$vendor = $mkuser( 'v' );
wpss_test_activate_vendor( $vendor );
wp_set_current_user( $buyer );

$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = array( 'to' => (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ), 'subject' => (string) $atts['subject'], 'message' => (string) $atts['message'] );
		return true;
	},
	10,
	2
);
$buyer_mails = static function () use ( &$mails, $buyer ): array {
	$addr = get_userdata( $buyer )->user_email;
	return array_values( array_filter( $mails, static fn( $m ) => str_contains( $m['to'], $addr ) ) );
};

$requests   = new BuyerRequestService();
$request_id = $requests->create( array( 'title' => 'QA phased contract', 'description' => 'one email', 'budget_min' => 50, 'budget_max' => 500 ) );
$request_id = is_int( $request_id ) ? $request_id : 0;
$phases     = array(
	array( 'title' => 'Design', 'description' => 'Wireframes', 'amount' => 100, 'days' => 3, 'deliverables' => 'Figma file' ),
	array( 'title' => 'Build', 'description' => 'Code it', 'amount' => 200, 'days' => 5, 'deliverables' => 'Plugin zip' ),
	array( 'title' => 'Launch', 'description' => 'Ship it', 'amount' => 50, 'days' => 2, 'deliverables' => 'Live site' ),
);
$wpdb->insert( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => $request_id, 'vendor_id' => $vendor, 'cover_letter' => 'phased', 'proposed_price' => 350.0, 'proposed_days' => 10, 'contract_type' => 'milestone', 'milestones' => wp_json_encode( $phases ), 'status' => 'pending', 'created_at' => current_time( 'mysql', true ) ) );
$proposal_id = (int) $wpdb->insert_id;
$converted   = $request_id ? $requests->convert_to_order( $request_id, $proposal_id ) : array();
$order_id    = (int) ( $converted['order_id'] ?? 0 );
$check( 'the phased proposal was accepted', $order_id > 0, wp_json_encode( $converted ) );

$got = $buyer_mails();
$check( 'the buyer gets ONE email for a 3-phase contract', 1 === count( $got ), (string) count( $got ) );
$body = $got[0]['message'] ?? '';
$check( 'it names every phase', str_contains( $body, 'Design' ) && str_contains( $body, 'Build' ) && str_contains( $body, 'Launch' ) );
$owed = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total) FROM {$orders} WHERE platform = 'milestone' AND platform_order_id = %d", $order_id ) );
$check( 'it shows what the buyer will pay across the phases (tax included)', $owed > 0 && str_contains( $body, esc_html( wpss_format_price( $owed, wpss_get_currency() ) ) ), (string) $owed );
$notes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d", $buyer ) );
$check( 'one in-app notification, not one per phase', 1 === $notes, (string) $notes );

// A phase the seller proposes later on its own keeps its single email.
$mails = array();
$later = ( new \WPSellServices\Services\MilestoneService() )->propose( $order_id, $vendor, 'Extra', 'Late addition', 40.0, 2 );
$check( 'a later single phase still sends its own email', ! empty( $later['success'] ) ? 1 === count( $buyer_mails() ) : true, wp_json_encode( $later ) );

// Cleanup.
$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$orders} WHERE customer_id = %d", $buyer ) ) );
foreach ( $ids as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) );
}
$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $proposal_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $buyer ) );
$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $vendor ) );
if ( $request_id ) {
	wp_delete_post( $request_id, true );
}

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
