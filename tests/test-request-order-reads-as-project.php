<?php
/**
 * A buyer-request (custom project) order reads as a Project, not as a gig.
 *
 * Run: wp eval-file tests/test-request-order-reads-as-project.php
 *
 * The order page headed it "Service Details", named it "Request: <title>" as a link to the request post, and listed
 * the same title again as its "Package". A paying client hired a custom project, not a catalogue service. The shared
 * subject resolver now says kind=project with the plain title, the public page carries no link, and the Package row
 * and the "Service" wording are dropped for it. Catalogue orders are untouched.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( 1 );
$request_id = (int) wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA Project Title' ) );
$row        = (object) array( 'platform' => 'request', 'platform_order_id' => $request_id, 'service_id' => 0 );

$public = wpss_get_order_subject( $row, 'public' );
$admin  = wpss_get_order_subject( $row, 'admin' );
$check( 'kind is project', 'project' === ( $public['kind'] ?? '' ) );
$check( 'the label is the plain title, no "Request:" prefix', 'QA Project Title' === $public['label'], $public['label'] );
$check( 'the buyer and seller page does not link the request as if it were a gig', '' === $public['url'], $public['url'] );
$check( 'the admin keeps a way to open the request post', '' !== $admin['url'] );

// With the request post gone, the title frozen on the order still reads as the plain project title.
wp_delete_post( $request_id, true );
$gone = wpss_get_order_subject( (object) array( 'platform' => 'request', 'platform_order_id' => $request_id, 'service_id' => 0, 'meta' => array( 'proposal_snapshot' => array( 'request_title' => 'Frozen Title' ) ) ), 'public' );
$check( 'a deleted request falls back to the frozen title, still a project', 'Frozen Title' === $gone['label'] && 'project' === $gone['kind'], wp_json_encode( $gone ) );

// A catalogue order is unchanged.
$svc_id = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA Gig' ) );
$gig    = wpss_get_order_subject( (object) array( 'platform' => 'standalone', 'platform_order_id' => 0, 'service_id' => $svc_id ), 'public' );
$check( 'a catalogue order is still a service with its link', 'service' === ( $gig['kind'] ?? '' ) && '' !== $gig['url'] && 'QA Gig' === $gig['label'] );
wp_delete_post( $svc_id, true );

// The surfaces use it.
$view  = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/templates/order/order-view.php' );
$admin_src = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Admin/Admin.php' );
$mail  = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/templates/emails/new-order.php' );
$check( 'buyer order page heads a project "Project"', str_contains( $view, "'project' === \$wpss_subject['kind']" ) && str_contains( $view, "esc_html__( 'Project'" ) );
$check( 'admin order screen drops the Package row for a project', str_contains( $admin_src, "'project' !== \$subject['kind']" ) );
$check( 'new-order email drops the Package row for a project', str_contains( $mail, "is_request_order()" ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
