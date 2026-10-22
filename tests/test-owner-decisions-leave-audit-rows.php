<?php
/**
 * Owner decisions that moved money, reputation or access left no audit row:
 * review delete / edit / import, a dispute opened, a seller level change, and
 * (Pro) commission rules, PayPal batches and Stripe Connect settings.
 *
 * Run: wp eval-file tests/test-owner-decisions-leave-audit-rows.php
 *
 * Writes QA reviews and one QA commission rule; audit rows, reviews and the
 * rule written here are deleted and the Stripe options restored on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ReviewService;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$audit   = $wpdb->prefix . 'wpss_audit_log';
$reviews = $wpdb->prefix . 'wpss_reviews';
$mark    = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$audit}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$review_ids = array();
$stripe     = array(
	'wpss_pro_stripe_connect_enabled' => get_option( 'wpss_pro_stripe_connect_enabled', null ),
	'wpss_pro_connect_fee_percent'    => get_option( 'wpss_pro_connect_fee_percent', null ),
);

$restore = static function () use ( $wpdb, $audit, $mark, $reviews, &$review_ids, $stripe ) {
	foreach ( $stripe as $option => $value ) {
		if ( null === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	foreach ( $review_ids as $id ) {
		$wpdb->delete( $reviews, array( 'id' => $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE id > %d", $mark ) );
	// phpcs:enable
};

$rows = static function ( string $event ) use ( $wpdb, $audit, $mark ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE id > %d AND event_type = %s", $mark, $event ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

wp_set_current_user( 1 );
$vendor  = 7831;
$service = (int) get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'author' => $vendor, 'numberposts' => 1, 'fields' => 'ids' ) )[0];

// Review import, edit (name), delete.
$imported = ( new ReviewService() )->create_imported( $vendor, $service, array( 'reviewer_name' => 'QA Audit Reviewer', 'rating' => 4, 'content' => 'QA audit ' . wp_generate_password( 8, false ) ) );
$review_id = is_object( $imported ) ? (int) $imported->id : 0;
$review_ids[] = $review_id;
$check( 'an imported review leaves an audit row', $review_id > 0 && 1 === $rows( 'review.imported' ), (string) $review_id );

$request = new WP_REST_Request( 'PUT', '/wpss/v1/reviews/' . $review_id );
$request->set_param( 'reviewer_name', 'QA Audit Renamed' );
rest_do_request( $request );
$check( 'an admin rename of a review leaves an audit row', 1 === $rows( 'review.edited' ) );

rest_do_request( new WP_REST_Request( 'DELETE', '/wpss/v1/reviews/' . $review_id ) );
$check( 'deleting a review leaves an audit row', 1 === $rows( 'review.deleted' ) );

// Dispute opened and seller level: the hooks are the seam.
do_action( 'wpss_dispute_opened', 999999, 999999, 1, array() );
$check( 'a dispute opened leaves an audit row', 1 === $rows( 'dispute.opened' ) );
do_action( 'wpss_vendor_level_updated', $vendor, 'qa_level' );
$check( 'a seller level change leaves an audit row', 1 === $rows( 'vendor.level_changed' ) );

// Pro: commission rules, Stripe Connect settings.
if ( class_exists( '\WPSellServicesPro\TieredCommission\CommissionRuleRepository' ) ) {
	$repo    = new \WPSellServicesPro\TieredCommission\CommissionRuleRepository();
	$rule_id = $repo->create( array( 'rule_type' => 'category', 'name' => 'QA audit rule', 'rate' => 12.5, 'rate_type' => 'percentage', 'conditions' => array(), 'is_active' => 0 ) );
	$check( 'a commission rule created leaves an audit row', $rule_id > 0 && 1 === $rows( 'commission.rule_created' ) );
	$repo->update( $rule_id, array( 'rate' => 13.0 ) );
	$check( 'a commission rule changed leaves an audit row', 1 === $rows( 'commission.rule_updated' ) );
	$repo->delete( $rule_id );
	$check( 'a commission rule deleted leaves an audit row', 1 === $rows( 'commission.rule_deleted' ) );

	update_option( 'wpss_pro_connect_fee_percent', 17.0 );
	update_option( 'wpss_pro_connect_fee_percent', 18.0 );
	$check( 'a Stripe Connect fee change leaves an audit row', $rows( 'settings.stripe_connect' ) >= 1 );
}
