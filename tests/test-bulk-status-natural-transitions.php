<?php
/**
 * Bulk Mark as Completed / Cancelled refuses orders the workflow does not allow.
 *
 * Run: wp eval-file tests/test-bulk-status-natural-transitions.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\OrderService;

$svc   = new OrderService();
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// The bulk gate is can_transition_naturally(): admin override must not count.
wp_set_current_user( 1 );
$check( 'cancelled -> completed is refused', ! $svc->can_transition_naturally( 'cancelled', 'completed' ) );
$check( 'pending_payment -> completed is refused', ! $svc->can_transition_naturally( 'pending_payment', 'completed' ) );
$check( 'pending_approval -> completed is allowed', $svc->can_transition_naturally( 'pending_approval', 'completed' ) );
$check( 'in_progress -> cancelled is allowed', $svc->can_transition_naturally( 'in_progress', 'cancelled' ) );

$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Admin.php' );
$check( 'bulk loop skips disputed and gates on can_transition_naturally', (bool) preg_match( '/\'disputed\' !== \$order->status\s*&&\s*\$order_service->can_transition_naturally\(\s*\$order->status,\s*\$new_status\s*\)\s*&&\s*\$order_service->update_status/', $src ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
