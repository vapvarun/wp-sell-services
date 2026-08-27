<?php
/**
 * Order filter groups contract.
 *
 * A buyer with 149 orders, 106 of them cancelled, could not find the one
 * waiting on them to pay, and the stats above the list disagreed with the rows
 * in it (Basecamp 10240019463).
 *
 * Both halves come down to one thing: the chips, the query behind them, the
 * per-chip counts and the stat cards must all read ONE definition of what
 * "active" means. This asserts that, and that the buckets stay mutually
 * exclusive and complete - a status in two groups is double counted, a status
 * in none is invisible.
 *
 * Run: wp eval-file tests/test-order-filters-contract.php
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

echo "\nOrder filter groups contract\n\n";

$groups = wpss_get_order_status_groups();

// 1. `all` means "no WHERE clause", not "every status" - so a status added
//    later still appears under All without being registered here first.
wpss_t( isset( $groups['all'] ) && array() === $groups['all']['statuses'], 'the All group carries no statuses, so it cannot go stale' );

// 2. Mutually exclusive. A status in two buckets is counted twice, and the
//    chip totals stop adding up to the order count.
$seen = array();
$dupes = array();
foreach ( $groups as $key => $group ) {
	foreach ( $group['statuses'] as $status ) {
		if ( isset( $seen[ $status ] ) ) {
			$dupes[] = $status . ' (' . $seen[ $status ] . ' + ' . $key . ')';
		}
		$seen[ $status ] = $key;
	}
}
wpss_t( empty( $dupes ), 'no status is in two groups (' . ( $dupes ? implode( ', ', $dupes ) : 'none' ) . ')' );

// 3. Complete. A status in no bucket is unreachable by every chip, so those
//    orders can only be found under All - which is how "where is my active
//    order?" starts.
$ref      = new ReflectionClass( '\WPSellServices\Models\ServiceOrder' );
$declared = array();
foreach ( $ref->getConstants() as $name => $value ) {
	if ( 0 === strpos( $name, 'STATUS_' ) && is_string( $value ) ) {
		$declared[] = $value;
	}
}
$orphans = array_values( array_diff( $declared, array_keys( $seen ) ) );
wpss_t( empty( $orphans ), 'every declared status belongs to a group (' . ( $orphans ? implode( ', ', $orphans ) : 'all covered' ) . ')' );

// 4. The stat cards and the chips must agree, because they are the two numbers
//    a buyer compares. This is the defect, stated directly.
$repo = new \WPSellServices\Database\Repositories\OrderRepository();
global $wpdb;
$buyer = (int) $wpdb->get_var( "SELECT customer_id FROM {$wpdb->prefix}wpss_orders GROUP BY customer_id ORDER BY COUNT(*) DESC LIMIT 1" );

if ( $buyer ) {
	$stats  = $repo->get_customer_stats( $buyer );
	$counts = $repo->count_by_customer_grouped( $buyer );

	$bucket = static function ( $key ) use ( $groups, $counts ) {
		$n = 0;
		foreach ( $groups[ $key ]['statuses'] as $s ) {
			$n += (int) ( $counts[ $s ] ?? 0 );
		}
		return $n;
	};

	wpss_t( (int) $stats['active_orders'] === $bucket( 'active' ), sprintf( 'Active stat matches the Active chip (%d / %d)', (int) $stats['active_orders'], $bucket( 'active' ) ) );
	wpss_t( (int) $stats['completed_orders'] === $bucket( 'completed' ), sprintf( 'Completed stat matches the Completed chip (%d / %d)', (int) $stats['completed_orders'], $bucket( 'completed' ) ) );
	wpss_t( (int) $stats['awaiting_payment_orders'] === $bucket( 'awaiting' ), sprintf( 'Awaiting stat matches its chip (%d / %d)', (int) $stats['awaiting_payment_orders'], $bucket( 'awaiting' ) ) );
	wpss_t( (int) $stats['disputed_orders'] === $bucket( 'disputed' ), sprintf( 'Needs-attention stat matches its chip (%d / %d)', (int) $stats['disputed_orders'], $bucket( 'disputed' ) ) );

	// 5. Every order lands in exactly one chip, so the chips add up to All -
	//    checked for EVERY buyer, not just the busiest. This started as a
	//    single-buyer assertion and passed while one customer's order sat in
	//    no chip at all, because their row carried 'pending_review' (a dispute
	//    status written onto an order). The ungrouped bucket is what makes the
	//    sum hold whatever ends up in the column.
	$all_buyers = $wpdb->get_col( "SELECT DISTINCT customer_id FROM {$wpdb->prefix}wpss_orders WHERE customer_id > 0" );
	$mismatched = array();

	foreach ( $all_buyers as $bid ) {
		$bid     = (int) $bid;
		$bcounts = $repo->count_by_customer_grouped( $bid );

		$claimed = 0;
		foreach ( $groups as $key => $group ) {
			if ( 'all' === $key ) {
				continue;
			}
			foreach ( $group['statuses'] as $s ) {
				$claimed += (int) ( $bcounts[ $s ] ?? 0 );
			}
		}

		foreach ( wpss_resolve_ungrouped_statuses( $bcounts ) as $s ) {
			$claimed += (int) ( $bcounts[ $s ] ?? 0 );
		}

		$btotal = $repo->count_by_customer( $bid );
		if ( $claimed !== $btotal ) {
			$mismatched[] = sprintf( 'buyer %d: %d chips vs %d orders', $bid, $claimed, $btotal );
		}
	}

	wpss_t(
		empty( $mismatched ),
		sprintf( 'the chips add up to the full list for all %d buyers (%s)', count( $all_buyers ), $mismatched ? implode( '; ', $mismatched ) : 'all reconcile' )
	);

	// Without the ungrouped bucket at least one buyer would NOT reconcile on
	// this install - proving the assertion above is not passing vacuously.
	$strict_mismatch = 0;
	foreach ( $all_buyers as $bid ) {
		$bcounts = $repo->count_by_customer_grouped( (int) $bid );
		if ( wpss_resolve_ungrouped_statuses( $bcounts ) ) {
			++$strict_mismatch;
		}
	}
	echo sprintf( "  NOTE  %d buyer(s) hold a status no group claims; the Other chip is what keeps them reachable\n", $strict_mismatch );

	// 6. status__in genuinely narrows, and never returns a status outside the group.
	$rows  = $repo->get_by_customer( $buyer, array( 'status__in' => $groups['awaiting']['statuses'], 'limit' => 50 ) );
	$leak  = array();
	foreach ( $rows as $r ) {
		if ( ! in_array( $r->status, $groups['awaiting']['statuses'], true ) ) {
			$leak[] = $r->status;
		}
	}
	wpss_t( empty( $leak ), 'status__in returns only statuses from the group (' . ( $leak ? implode( ',', array_unique( $leak ) ) : 'clean' ) . ')' );

	// 7. Actionable-first puts the orders needing the buyer above the finished
	//    ones, WITHOUT dropping any.
	$sorted = $repo->get_by_customer( $buyer, array( 'actionable_first' => true, 'limit' => 100 ) );
	$plain  = $repo->get_by_customer( $buyer, array( 'limit' => 100 ) );
	wpss_t( count( $sorted ) === count( $plain ), 'sorting actionable-first hides nothing' );

	$priority = wpss_get_order_status_priority();
	$ranks    = array();
	foreach ( $sorted as $r ) {
		$pos = array_search( $r->status, $priority, true );
		$ranks[] = false === $pos ? PHP_INT_MAX : $pos;
	}
	$ordered = $ranks;
	sort( $ordered );
	wpss_t( $ranks === $ordered, 'rows come back in group priority order' );
}

// 8. An unrecognised filter widens to everything rather than emptying the list -
//    a stale bookmark must never look like "you have no orders".
wpss_t( array() === wpss_resolve_order_status_group( 'not_a_real_group' ), 'an unknown filter key selects no status filter, i.e. shows all' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
