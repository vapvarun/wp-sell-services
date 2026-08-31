<?php
/**
 * Milestone parent total contract.
 *
 * A milestone contract keeps its money on the PHASES, so the parent order
 * legitimately carries total = 0. Correct accounting, and a terrible thing to
 * show a buyer: they agreed $200, paid $200 across two phases, and the order
 * said "TOTAL AMOUNT $0.00" as though it were free or broken (Basecamp
 * 10254487153).
 *
 * Run: wp eval-file tests/test-milestone-parent-total-contract.php
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

echo "\nMilestone parent total contract\n\n";

/**
 * The template's arithmetic, kept in step with it.
 *
 * @param array $phases Decorated phases from get_for_parent().
 * @return array{total: float, paid: float, count: int}
 */
function wpss_phase_totals( array $phases ): array {
	$dropped = array( 'cancelled', 'rejected' );
	$unpaid  = array( 'pending_payment', 'refunded' );

	$total = 0.0;
	$paid  = 0.0;
	$count = 0;

	foreach ( $phases as $phase ) {
		$phase  = (array) $phase;
		$status = (string) ( $phase['status'] ?? '' );
		$amount = (float) ( $phase['amount'] ?? 0 );

		if ( in_array( $status, $dropped, true ) ) {
			continue;
		}

		++$count;
		$total += $amount;

		if ( ! in_array( $status, $unpaid, true ) ) {
			$paid += $amount;
		}
	}

	return array( 'total' => $total, 'paid' => $paid, 'count' => $count );
}

// 1. A fully paid contract reports what was agreed and what was paid.
$r = wpss_phase_totals(
	array(
		array( 'amount' => 120, 'status' => 'completed' ),
		array( 'amount' => 80, 'status' => 'completed' ),
	)
);
wpss_t( 200.0 === $r['total'], 'a two-phase contract totals both phases, not zero' );
wpss_t( 200.0 === $r['paid'], 'both paid phases count as paid' );
wpss_t( 2 === $r['count'], 'the phase count is right' );

// 2. Cancelled work is withdrawn from the contract, not counted in it.
$r = wpss_phase_totals(
	array(
		array( 'amount' => 100, 'status' => 'cancelled' ),
		array( 'amount' => 200, 'status' => 'pending_payment' ),
		array( 'amount' => 300, 'status' => 'pending_payment' ),
		array( 'amount' => 75, 'status' => 'cancelled' ),
	)
);
wpss_t( 500.0 === $r['total'], 'cancelled phases are excluded from the contract total (500, not 675)' );
wpss_t( 0.0 === $r['paid'], 'nothing unpaid is counted as paid' );
wpss_t( 2 === $r['count'], 'cancelled phases are excluded from the phase count' );

// 3. Refunded was paid and came back: still contracted, no longer paid.
$r = wpss_phase_totals(
	array(
		array( 'amount' => 100, 'status' => 'completed' ),
		array( 'amount' => 100, 'status' => 'refunded' ),
	)
);
wpss_t( 200.0 === $r['total'], 'a refunded phase stays part of the contract' );
wpss_t( 100.0 === $r['paid'], 'a refunded phase is not counted as money the buyer has parted with' );

// 4. An ordinary order has no phases and must be untouched.
$r = wpss_phase_totals( array() );
wpss_t( 0 === $r['count'], 'an order with no phases shows nothing extra' );

// 5. Against the live data, so this cannot drift from the template.
if ( class_exists( '\WPSellServices\Services\MilestoneService' ) ) {
	global $wpdb;
	$parent = (int) $wpdb->get_var(
		"SELECT platform_order_id FROM {$wpdb->prefix}wpss_orders
		 WHERE platform = 'milestone' GROUP BY platform_order_id
		 HAVING COUNT(*) > 1 ORDER BY platform_order_id DESC LIMIT 1"
	);

	if ( $parent ) {
		$phases = ( new \WPSellServices\Services\MilestoneService() )->get_for_parent( $parent );
		$live   = wpss_phase_totals( $phases );
		$stored = (float) $wpdb->get_var( $wpdb->prepare( "SELECT total FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $parent ) );

		wpss_t( ! empty( $phases ), sprintf( 'parent %d has phases to sum', $parent ) );
		wpss_t( $live['total'] > 0, sprintf( 'the phases give the buyer a real figure (%.2f) where the parent row says %.2f', $live['total'], $stored ) );

		// The decorated phases are ARRAYS keyed `amount`. Reading ->total gives
		// zero on every phase and silently reinstates the $0.00 this fixes.
		$first = (array) $phases[0];
		wpss_t( isset( $first['amount'] ), 'phases expose `amount`, which is what the template must read' );
		wpss_t( ! isset( $first['total'] ), 'phases do NOT expose `total`, so reading it would silently give zero' );
	}
}

// The service line, not just the summary.
//
// The card reported "TOTAL AMOUNT $0.00 AND service line $0.00". The first
// round fixed the summary and left the service line printing $order->total
// raw - which is deliberately 0 on a milestone parent - so the page still
// carried a bare $0.00 that read as a free order, and QA bounced it on
// exactly that. One template, two places showing the same money.
$view = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-view.php' );

wpss_t(
	false === strpos(
		$view,
		"<p class=\"wpss-service-info__price\">\n\t\t\t\t\t\t<?php echo esc_html( wpss_format_price( (float) \$order->total, \$order->currency ) ); ?>"
	),
	'the service line no longer prints the parent total raw'
);

wpss_t(
	false !== strpos( $view, '$show_phase_total' ) && substr_count( $view, '$show_phase_total' ) >= 3,
	'the service line reuses the phase figures rather than summing them again'
);

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
