<?php
/**
 * Milestone phase revision contract.
 *
 * A submitted phase had exactly one buyer action - Approve - beside a link
 * into the parent chat. Catalog deliveries have a real "Request revision"
 * that flips the order back to the seller, and the two flows diverged, so
 * buyers either accepted work they were not happy with or opened a dispute
 * (Basecamp 10254720173).
 *
 * The tell that this was a gap and not a decision: MilestoneService::submit()
 * has ALWAYS listed STATUS_REVISION_REQUESTED as a valid from-state. The
 * workflow was built to be re-entered and nothing could put a phase into it.
 *
 * Run: wp eval-file tests/test-milestone-revision-contract.php
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nMilestone phase revision contract\n\n";

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$service = new \WPSellServices\Services\MilestoneService();

// Work on a real phase, restored afterwards, rather than a synthetic row -
// the guards read platform, customer_id and status off the actual table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$phase = $wpdb->get_row( "SELECT * FROM {$orders} WHERE platform = 'milestone' ORDER BY id DESC LIMIT 1" );

if ( ! $phase ) {
	echo "  SKIP  no milestone phase on this install.\n";
	return;
}

$phase_id  = (int) $phase->id;
$buyer     = (int) $phase->customer_id;
$seller    = (int) $phase->vendor_id;
$restore   = array(
	'status'       => $phase->status,
	'meta'         => $phase->meta,
	'completed_at' => $phase->completed_at,
);

/**
 * Put the phase into a known status.
 *
 * @param string $status Status to force.
 * @return void
 */
function wpss_set_phase_status( int $phase_id, string $status ): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => $status ), array( 'id' => $phase_id ) );
	wp_cache_flush();
}

// 1. Only a submitted phase can be sent back.
wpss_set_phase_status( $phase_id, 'in_progress' );
$r = $service->request_revision( $phase_id, $buyer, 'too early' );
wpss_t( ! $r['success'], 'a phase that has not been submitted cannot be sent back' );

wpss_set_phase_status( $phase_id, 'completed' );
$r = $service->request_revision( $phase_id, $buyer, 'too late' );
wpss_t( ! $r['success'], 'an approved phase cannot be sent back' );

// 2. Only the buyer.
wpss_set_phase_status( $phase_id, 'pending_approval' );
$r = $service->request_revision( $phase_id, $seller, 'seller trying' );
wpss_t( ! $r['success'], 'the seller cannot send their own phase back' );
$r = $service->request_revision( $phase_id, 0, 'nobody' );
wpss_t( ! $r['success'], 'a logged-out caller cannot send a phase back' );

$still = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $phase_id ) );
wpss_t( 'pending_approval' === $still, 'a refused request leaves the phase exactly where it was' );

// 3. The happy path.
$fired = array();
add_action(
	'wpss_milestone_revision_requested',
	function ( $id, $parent, $vendor, $customer, $reason ) use ( &$fired ) {
		$fired = compact( 'id', 'parent', 'vendor', 'customer', 'reason' );
	},
	10,
	5
);

$r = $service->request_revision( $phase_id, $buyer, 'Logo should sit left of the wordmark.' );
wpss_t( $r['success'], 'the buyer can send a submitted phase back' );

$after = $wpdb->get_row( $wpdb->prepare( "SELECT status, meta FROM {$orders} WHERE id = %d", $phase_id ) );
wpss_t( 'revision_requested' === $after->status, 'the phase lands in revision_requested, not a bespoke status' );

$meta = json_decode( (string) $after->meta, true );
wpss_t(
	'Logo should sit left of the wordmark.' === ( $meta['revision_reason'] ?? '' ),
	'what the buyer asked for is stored on the phase, not only in chat'
);

wpss_t( ! empty( $fired ) && $phase_id === $fired['id'], 'wpss_milestone_revision_requested fires for notifications to hang off' );
wpss_t( ( $fired['parent'] ?? 0 ) === (int) $phase->platform_order_id, 'the hook carries the parent contract id' );

// 4. The state the seller re-enters must be one submit() accepts - this is the
//    whole reason revision_requested was the right status to choose.
wpss_t(
	$service->submit( $phase_id, $seller, 'Moved the logo.' )['success'],
	'the seller can resubmit from revision_requested'
);
wpss_t(
	'pending_approval' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $phase_id ) ),
	'resubmitting returns the phase to the buyer for approval'
);

// Restore. The reason lands in a REAL conversation, so the message this test
// posts is cleaned up too - the first run left one in a demo thread where it
// read like genuine buyer feedback.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->update( $orders, $restore, array( 'id' => $phase_id ) );

$removed = 0;
$parent  = (int) $phase->platform_order_id;
if ( $parent > 0 ) {
	$conversation = ( new \WPSellServices\Services\ConversationService() )->get_by_order( $parent );
	if ( $conversation ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpss_messages
				WHERE conversation_id = %d AND type = 'revision' AND content LIKE %s",
				(int) $conversation->id,
				'%' . $wpdb->esc_like( 'Logo should sit left of the wordmark.' ) . '%'
			)
		);
	}
}

echo "\n  (phase #{$phase_id} restored to {$restore['status']}; {$removed} test message(s) removed)\n";

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
